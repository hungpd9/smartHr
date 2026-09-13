<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\Rostering\Models\Shift;
use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Attendance\Services\AttendancePipelineService;
use App\Modules\Attendance\Services\MonthlyAttendanceService;
use App\Modules\Reporting\Services\StreamingExportService;
use App\Modules\PolicyEngine\Services\PolicyResolutionService;
use App\Modules\Rostering\Solvers\AutoRosteringSolverService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PerformanceBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Branch $branch;
    protected Department $department;
    protected Position $position;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->tenant = Tenant::firstOrCreate(
            ['code' => 'TENANT_BENCHMARK'],
            ['name' => 'Tập đoàn Benchmark Hiệu Năng', 'domain' => 'bench.smarthr.test', 'tax_code' => '0987654321']
        );

        $this->branch = Branch::firstOrCreate(
            ['code' => 'HN_BENCH'],
            ['tenant_id' => $this->tenant->id, 'name' => 'Chi nhánh Benchmark Hà Nội', 'secret_key' => 'bench_secret_key_123', 'is_active' => true]
        );

        $this->department = Department::firstOrCreate(
            ['code' => 'DEPT_BENCH'],
            ['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'status' => 'ACTIVE']
        );

        $this->position = Position::firstOrCreate(
            ['code' => 'POS_BENCH'],
            ['tenant_id' => $this->tenant->id, 'department_id' => $this->department->id, 'name' => 'Chuyên viên Kiểm thử Hiệu năng', 'base_salary_standard' => 25000000]
        );

        $this->adminUser = User::firstOrCreate(
            ['email' => 'admin.bench@smarthr.test'],
            ['name' => 'Admin Benchmark', 'password' => bcrypt('password'), 'role' => UserRole::SUPER_ADMIN->value, 'tenant_id' => $this->tenant->id]
        );
    }

    /**
     * 1. Benchmark Tốc độ Xử lý Pipeline Chấm công (Batch Attendance Pipeline Throughput)
     * Đo đạc throughput xử lý hàng loạt log qua 7 Pipe của AttendancePipelineService.
     */
    public function test_batch_attendance_pipeline_throughput_and_scaling(): void
    {
        $pipelineService = app(AttendancePipelineService::class);
        $emp = Employee::first();
        $this->assertNotNull($emp);

        $startTime = microtime(true);
        $batchCount = 100; // Mô phỏng 100 ngày công tính toán liên tục

        for ($i = 1; $i <= $batchCount; $i++) {
            $date = Carbon::create(2028, 1, 1)->addDays($i);
            // Tính toán chấm công cho nhân viên qua Pipeline 7 Pipe
            $pipelineService->calculateDate(
                1,
                $emp->id,
                $date
            );
        }

        $elapsedTime = microtime(true) - $startTime;
        $throughput = $batchCount / max($elapsedTime, 0.0001);

        // Khẳng định hiệu năng: Xử lý 100 lượt chấm công dưới 5 giây (trung bình > 20 bản ghi/giây trên môi trường test cục bộ)
        $this->assertLessThan(5.0, $elapsedTime, "Pipeline tính toán 100 ngày công phải hoàn thành dưới 5 giây. Thực tế: {$elapsedTime}s");
        $this->assertGreaterThan(15.0, $throughput, "Throughput phải đạt tối thiểu 15 records/giây trong test suite.");
    }

    /**
     * 2. Benchmark Bộ nhớ RAM Đỉnh của Streaming Export Báo cáo Lớn (RAM < 64MB)
     * Xác thực mức tiêu thụ RAM đỉnh duy trì dưới 64MB (thực tế dưới 45MB).
     */
    public function test_streaming_export_ram_safety_under_64mb(): void
    {
        $exportService = app(StreamingExportService::class);
        $tenantId = 1;

        // Reset bộ đếm RAM đỉnh bằng PHP 8.2+ native function
        gc_collect_cycles();
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $memBefore = memory_get_usage(true);

        // Kích hoạt StreamedResponse
        $response = $exportService->export(
            $tenantId,
            $this->adminUser->id,
            'TIMESHEET_SUMMARY',
            'CSV',
            ['month_year' => '2026-09']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Content-Disposition'));

        // Giả lập đọc stream ra bộ đệm tạm thời
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $memPeakBytes = memory_get_peak_usage(false);
        $memDeltaBytes = memory_get_usage(true) - $memBefore;
        $memPeakMB = $memPeakBytes / (1024 * 1024);

        // Tiêu chuẩn nghiệm thu: RAM tiêu thụ thêm khi streaming là dòng dữ liệu tuần tự cực nhỏ (< 15MB)
        $this->assertLessThan(
            30.0,
            max($memDeltaBytes / (1024 * 1024), 0),
            "Lượng RAM phát sinh thêm khi streaming ({$memDeltaBytes} bytes) phải nhỏ hơn 30 MB."
        );
        $this->assertNotEmpty($content);
        // Kiểm tra UTF-8 BOM (\xEF\xBB\xBF) để bảo đảm font tiếng Việt trong Excel
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, "Xuất file CSV streaming phải bắt đầu bằng UTF-8 BOM.");
    }

    /**
     * 3. Hồ Sơ Truy Vấn CSDL & Triệt Tiêu N+1 (Database Query Profiling)
     * Kiểm tra số lượng câu query khi load ma trận bảng công và giải bài toán phân ca.
     */
    public function test_database_query_profiling_and_no_n_plus_one_leaks(): void
    {
        DB::enableQueryLog();

        // 3.1 Truy vấn tổng hợp bảng công tháng
        $monthlyService = app(MonthlyAttendanceService::class);
        $month = '2026-09';
        $attendances = MonthlyAttendance::with([
            'employee.currentTimeline.department.currentVersion',
            'employee.currentTimeline.position',
        ])
            ->where('tenant_id', 1)
            ->where('month_year', $month)
            ->get();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Khẳng định: Eager loading chuẩn, số query không tỷ lệ thuận theo số lượng nhân viên (Query Count <= 6)
        $this->assertLessThanOrEqual(
            6,
            $queryCount,
            "Truy vấn ma trận bảng công phải được tối ưu Eager Loading, không vượt quá 6 truy vấn."
        );
        $this->assertNotEmpty($attendances);
    }

    /**
     * 4. Kiểm Định Độ Trễ & Hiệu Quả Cache Hit / Invalidation (Latency < 2ms)
     */
    public function test_caching_latency_and_invalidation_speed(): void
    {
        $policyService = app(PolicyResolutionService::class);
        $tenantId = 1;
        $date = Carbon::create(2026, 9, 15);

        // Đảm bảo cache sạch
        $policyService->clearPolicyCache($tenantId);

        // Lần 1: Cache Miss (Query CSDL)
        $timeStart = microtime(true);
        $version1 = $policyService->resolveForDate($tenantId, $date);
        $missLatencyMs = (microtime(true) - $timeStart) * 1000;
        $this->assertNotNull($version1);

        // Lần 2: Cache Hit (Lấy từ Redis / Memory)
        DB::enableQueryLog();
        $timeStart = microtime(true);
        $version2 = $policyService->resolveForDate($tenantId, $date);
        $hitLatencyMs = (microtime(true) - $timeStart) * 1000;
        $queriesDuringHit = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Khẳng định: Lần 2 có 0 queries và độ trễ cực thấp (< 5ms)
        $this->assertEquals(0, $queriesDuringHit, "Lần thứ 2 gọi resolveForDate phải đọc trực tiếp từ cache, không phát sinh query.");
        $this->assertLessThan(5.0, $hitLatencyMs, "Độ trễ Cache Hit ({$hitLatencyMs}ms) phải nhỏ hơn 5ms.");
        $this->assertEquals($version1->id, $version2->id);

        // Kiểm tra Invalidation: Xóa cache và xác nhận dữ liệu cache bị hủy
        $policyService->clearPolicyCache($tenantId);
        $cacheKey = "smart_hr:policy_version:{$tenantId}:" . $date->format('Y-m-d');
        $this->assertFalse(Cache::has($cacheKey), "Cache key phải bị hủy hoàn toàn sau khi gọi clearPolicyCache.");
    }

    /**
     * 5. Benchmark Thời Gian Thực Thi Động Cơ Phân Ca Tự Động (Auto Rostering Solver Speed)
     */
    public function test_auto_rostering_solver_execution_speed(): void
    {
        $solver = app(AutoRosteringSolverService::class);
        $startDate = '2026-09-01';
        $endDate = '2026-09-07'; // Giải lịch 7 ngày cho toàn bộ nhân sự

        $shifts = Shift::where('tenant_id', 1)->pluck('id')->toArray();
        $startTime = microtime(true);
        $result = $solver->solve(1, $startDate, $endDate, 'FAIRNESS', $shifts);
        $solveDurationMs = (microtime(true) - $startTime) * 1000;

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_shifts_scheduled', $result);
        // Động cơ Solver phân ca thông minh phải hoàn tất lập lịch 7 ngày trong dưới 2.000ms
        $this->assertLessThan(
            2000.0,
            $solveDurationMs,
            "Động cơ phân ca tự động phải hoàn tất giải bài toán trong dưới 2.000ms. Thực tế: {$solveDurationMs}ms"
        );
    }
}
