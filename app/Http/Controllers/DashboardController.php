<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\PolicyEngine\Models\PolicyVersion;
use App\Modules\Compliance\Models\ComplianceAlert;
use App\Modules\Workflow\Models\ApprovalRequest;
use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\Attendance\Models\RawAttendanceLog;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::today();

        // 1. Thống kê số liệu thời gian thực
        $totalEmployees = Employee::where('status', '!=', 'TERMINATED')->count();
        $todayPunchesCount = RawAttendanceLog::whereDate('punch_time', $today)->count();
        $pendingRequestsCount = ApprovalRequest::where('status', 'PENDING')->count();
        $activeComplianceAlertsCount = ComplianceAlert::where('is_resolved', false)->count();

        // 2. Điểm danh hôm nay
        $todayAttendances = DailyAttendance::with(['employee', 'roster.shift'])
            ->where('work_date', $today)
            ->limit(10)
            ->get()
            ->map(function ($att) {
                return [
                    'code' => $att->employee->user_code,
                    'name' => $att->employee->full_name,
                    'dept' => $att->employee->currentTimeline?->department?->currentVersion?->name ?? 'Chưa gán',
                    'shift' => $att->roster?->shift?->name ?? 'Chưa phân ca',
                    'in' => $att->actual_in ? Carbon::parse($att->actual_in)->format('H:i') : '--:--',
                    'out' => $att->actual_out ? Carbon::parse($att->actual_out)->format('H:i') : '--:--',
                    'status' => $att->status,
                    'ot' => ($att->ot_day_hours + $att->ot_night_hours) . 'h',
                ];
            });

        // Nếu chưa có log ngày hôm nay, lấy danh sách nhân viên mẫu
        if ($todayAttendances->isEmpty()) {
            $todayAttendances = Employee::with(['careerTimelines.department.currentVersion'])
                ->limit(5)
                ->get()
                ->map(function ($emp) {
                    return [
                        'code' => $emp->user_code,
                        'name' => $emp->full_name,
                        'dept' => $emp->currentTimeline?->department?->currentVersion?->name ?? 'Khối Công nghệ & Phần mềm',
                        'shift' => 'Ca Hành chính (08:00 - 17:00)',
                        'in' => '07:55',
                        'out' => '--:--',
                        'status' => 'WORKING',
                        'ot' => '0h',
                    ];
                });
        }

        // 3. Cây Timeline Nghề nghiệp mẫu (Effective Dating)
        $careerEvents = EmployeeCareerTimeline::with(['department.currentVersion', 'position'])
            ->orderByDesc('effective_date')
            ->limit(5)
            ->get()
            ->map(function ($t) {
                return [
                    'date' => Carbon::parse($t->effective_date)->format('d/m/Y'),
                    'status' => $t->end_date === null ? 'Active' : 'Historical',
                    'title' => $t->position?->name ?? $t->job_title,
                    'desc' => $t->notes ?? ($t->decision_number ? "Số QĐ: {$t->decision_number}" : 'Biến động nhân sự'),
                ];
            });

        if ($careerEvents->isEmpty()) {
            $careerEvents = collect([
                [
                    'date' => '01/01/2026',
                    'status' => 'Active',
                    'title' => 'Áp dụng Quy chế Quản trị 2026 (BLLĐ 2019 & Thuế TNCN 5 Bậc)',
                    'desc' => 'Phiên bản POLICY_2026_V1 ban hành toàn tập đoàn',
                ],
                [
                    'date' => '01/06/2025',
                    'status' => 'Historical',
                    'title' => 'Thành lập Chi nhánh Huyện đảo Phú Quốc',
                    'desc' => 'Áp dụng ngưỡng bảo vệ thai sản từ tháng 6 (Đ.137 Khoản 1 Điểm a)',
                ],
                [
                    'date' => '15/01/2024',
                    'status' => 'Historical',
                    'title' => 'Khởi tạo Doanh nghiệp & Tuyển dụng Cấp cao',
                    'desc' => 'Gia nhập đội ngũ sáng lập hệ sinh thái Smart-HR',
                ],
            ]);
        }

        // 4. Chính sách đang hiệu lực
        $activePolicy = PolicyVersion::where('is_active', true)->latest('effective_from')->first();

        // 5. Tính toán dữ liệu 4 Biểu đồ Quản trị Điều hành (Executive Analytics) với Cache 5 phút
        $tenantId = (int) ($request->user()?->tenant_id ?? 1);
        $analytics = \Illuminate\Support\Facades\Cache::remember("smart_hr:dashboard_analytics:{$tenantId}", 300, function () {
            $currentMonth = '2026-09';

            // Biểu đồ 1: Burnout Fatigue Risk (Nguy cơ Quá tải & Trần OT Điều 107)
            $monthlyRecords = \App\Modules\Attendance\Models\MonthlyAttendance::with('employee')
                ->where('month_year', $currentMonth)
                ->get();

            $burnoutData = $monthlyRecords->map(function ($m) {
                $ot = (float) $m->total_ot_hours;
                $status = 'NORMAL';
                $badge = 'success';
                if ($ot >= 40) {
                    $status = 'BREACH';
                    $badge = 'danger';
                } elseif ($ot >= 35) {
                    $status = 'ALERT';
                    $badge = 'warning';
                } elseif ($ot >= 20) {
                    $status = 'ELEVATED';
                    $badge = 'info';
                }

                return [
                    'employee_name' => $m->employee?->full_name ?? 'Nhân viên',
                    'user_code' => $m->employee?->user_code ?? '',
                    'ot_hours' => $ot,
                    'max_threshold' => 40.0,
                    'status' => $status,
                    'badge' => $badge,
                    'percentage' => min(100, round(($ot / 40.0) * 100, 1)),
                ];
            })->sortByDesc('ot_hours')->values()->take(5);

            // Biểu đồ 2: Turnover Trend (Xu hướng Biến động Nhân sự 2026: Tuyển mới vs Thôi việc)
            $months = ['01', '02', '03', '04', '05', '06', '07', '08', '09'];
            $turnoverData = collect($months)->map(function ($m) {
                $monthPrefix = "2026-{$m}";
                $onboardCount = EmployeeCareerTimeline::where('event_type', 'ONBOARDING')
                    ->where('effective_date', 'like', "{$monthPrefix}%")
                    ->count();
                $offboardCount = EmployeeCareerTimeline::where('event_type', 'OFFBOARDING')
                    ->where('effective_date', 'like', "{$monthPrefix}%")
                    ->count();

                return [
                    'month' => "T{$m}",
                    'onboarding' => $onboardCount,
                    'offboarding' => $offboardCount,
                    'net_growth' => $onboardCount - $offboardCount,
                ];
            });

            // Biểu đồ 3: Headcount Distribution (Cơ cấu Nhân sự theo Khối & Chi nhánh)
            $departments = \App\Modules\CoreHR\Models\Department::with('currentVersion')
                ->whereIn('level', [1, 2])
                ->where('status', 'ACTIVE')
                ->get();

            $headcountData = $departments->map(function ($d) {
                $count = Employee::whereHas('currentTimeline', function ($q) use ($d) {
                    $q->where('department_id', $d->id)
                      ->orWhereHas('department', fn ($subQ) => $subQ->where('parent_id', $d->id));
                })->where('status', '!=', 'TERMINATED')->count();

                return [
                    'name' => $d->currentVersion?->name ?? $d->code,
                    'count' => $count,
                ];
            })->filter(fn ($item) => $item['count'] > 0)->values();

            // Biểu đồ 4: Attendance Health Index (Chỉ số Sức khỏe Kỷ luật Công tác)
            $totalDaily = DailyAttendance::count();
            $healthIndex = [
                'on_time' => $totalDaily > 0 ? round((DailyAttendance::where('late_minutes', 0)->where('early_minutes', 0)->where('status', 'PRESENT')->count() / $totalDaily) * 100, 1) : 85.0,
                'late_early' => $totalDaily > 0 ? round((DailyAttendance::where(fn ($q) => $q->where('late_minutes', '>', 0)->orWhere('early_minutes', '>', 0))->count() / $totalDaily) * 100, 1) : 10.0,
                'leave' => $totalDaily > 0 ? round((DailyAttendance::whereIn('status', ['LEAVE_PAID', 'LEAVE_UNPAID'])->count() / $totalDaily) * 100, 1) : 4.0,
                'absent' => $totalDaily > 0 ? round((DailyAttendance::where('status', 'ABSENT')->count() / $totalDaily) * 100, 1) : 1.0,
            ];

            return [
                'burnoutData' => $burnoutData,
                'turnoverData' => $turnoverData,
                'headcountData' => $headcountData,
                'healthIndex' => $healthIndex,
            ];
        });

        return Inertia::render('Dashboard', [
            'metrics' => [
                'totalEmployees' => $totalEmployees,
                'todayPunchesCount' => $todayPunchesCount,
                'pendingRequestsCount' => $pendingRequestsCount,
                'activeComplianceAlertsCount' => $activeComplianceAlertsCount,
            ],
            'analytics' => $analytics,
            'todayAttendances' => $todayAttendances,
            'careerEvents' => $careerEvents,
            'policyVersion' => $activePolicy?->code ?? 'POLICY_2026_V1',
        ]);
    }
}
