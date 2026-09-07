<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use App\Models\User;
use App\Enums\UserRole;
use App\Modules\CoreHR\Models\Tenant;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\DepartmentVersion;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeCareerTimeline;
use App\Modules\CoreHR\Models\EmployeeChild;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Payroll\Models\PayrollRecord;
use App\Modules\Compliance\Models\ComplianceAlert;
use App\Modules\Reporting\Enums\ReportType;
use App\Modules\Reporting\Models\ReportExportHistory;
use App\Modules\Reporting\Services\ReportDataProviderService;
use App\Modules\Reporting\Services\StreamingExportService;

class ReportingEngineEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected Branch $branch;
    protected Department $department;
    protected User $superAdmin;
    protected Employee $employee1;
    protected Employee $employee2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Tập đoàn GoldStar Báo Cáo',
            'code' => 'GOLDSTAR_RPT',
            'domain' => 'goldstar-rpt.smarthr.vn',
            'is_active' => true,
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Doanh nghiệp Đối thủ',
            'code' => 'RIVAL_CORP',
            'domain' => 'rival.smarthr.vn',
            'is_active' => true,
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Chi nhánh Hà Nội',
            'code' => 'HN_BRANCH',
            'secret_key' => 'rpt_gold_star_secret_123',
            'is_active' => true,
        ]);

        $this->department = Department::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Khối Công nghệ & Phần mềm',
            'code' => 'TECH_DEPT',
            'level' => 1,
            'status' => 'ACTIVE',
        ]);

        DepartmentVersion::create([
            'department_id' => $this->department->id,
            'name' => 'Khối Công nghệ & Phần mềm',
            'effective_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $this->position = Position::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'code' => 'TECH_STAFF',
            'name' => 'Kỹ sư phần mềm',
            'level' => 1,
        ]);

        $this->superAdmin = User::create([
            'name' => 'Tổng Giám Đốc',
            'email' => 'ceo@goldstar.com',
            'password' => bcrypt('Secret@123'),
            'role' => UserRole::SUPER_ADMIN->value,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->employee1 = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => 'EMP001',
            'full_name' => 'Nguyễn Văn Minh',
            'gender' => 'MALE',
            'birth_date' => '1992-04-12',
            'email' => 'minh.nv@goldstar.com',
            'status' => 'OFFICIAL',
            'contract_type' => 'FULL_TIME',
            'join_date' => '2024-01-01',
        ]);

        EmployeeCareerTimeline::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'branch_id' => $this->branch->id,
            'event_type' => 'ONBOARDING',
            'effective_date' => '2026-09-01',
            'job_title' => 'Kỹ sư phần mềm cao cấp',
        ]);

        $this->employee2 = Employee::create([
            'tenant_id' => $this->tenant->id,
            'user_code' => 'EMP002',
            'full_name' => 'Trần Thị Lan',
            'gender' => 'FEMALE',
            'birth_date' => '1995-08-20',
            'email' => 'lan.tt@goldstar.com',
            'status' => 'OFFICIAL',
            'contract_type' => 'FULL_TIME',
            'join_date' => '2025-06-01',
        ]);

        EmployeeCareerTimeline::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee2->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'branch_id' => $this->branch->id,
            'event_type' => 'ONBOARDING',
            'effective_date' => '2026-09-02',
            'job_title' => 'Chuyên viên Nhân sự',
        ]);
    }

    /**
     * Test 1: Bảng tổng hợp công tháng trích xuất đúng các chỉ số công, OT và muộn/sớm
     */
    public function test_timesheet_summary_report_extracts_accurate_metrics(): void
    {
        MonthlyAttendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'month_year' => '2026-09',
            'total_standard_days' => 21.5,
            'total_late_minutes' => 25,
            'total_ot_hours' => 14.0,
            'submission_status' => 'MANAGER_APPROVED',
        ]);

        /** @var ReportDataProviderService $provider */
        $provider = app(ReportDataProviderService::class);
        $result = $provider->getReportData($this->tenant->id, ReportType::TIMESHEET_SUMMARY->value, ['month_year' => '2026-09']);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals('EMP001', $result['rows'][0]['user_code']);
        $this->assertEquals(21.5, $result['rows'][0]['actual_work_days']);
        $this->assertEquals(14.0, $result['rows'][0]['total_ot_hours']);
        $this->assertEquals(25, $result['rows'][0]['late_minutes']);
        $this->assertEquals('MANAGER_APPROVED', $result['rows'][0]['submission_status']);
        $this->assertEquals(21.5, $result['summary']['total_actual_days']);
    }

    /**
     * Test 2: Bảng phân tích chi phí lương bóc tách đúng OT miễn thuế NĐ 145, bảo hiểm và thuế TNCN
     */
    public function test_payroll_analysis_report_calculates_decree_145_and_taxes(): void
    {
        PayrollRecord::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'month_year' => '2026-09',
            'gross_salary' => 32000000,
            'net_salary' => 26500000,
            'position_allowance_amount' => 1000000,
            'child_allowance_amount' => 500000,
            'certificate_allowance_amount' => 500000,
            'taxable_ot_amount' => 1200000,
            'tax_exempt_ot_amount' => 1800000,
            'social_insurance_employee' => 2000000,
            'health_insurance_employee' => 375000,
            'unemployment_insurance_employee' => 250000,
            'pit_tax_amount' => 1500000,
            'salary_breakdown' => [
                'base_salary' => 25000000,
                'employer_cost' => 5875000,
            ],
            'status' => 'APPROVED',
        ]);

        /** @var ReportDataProviderService $provider */
        $provider = app(ReportDataProviderService::class);
        $result = $provider->getReportData($this->tenant->id, ReportType::PAYROLL_ANALYSIS->value, ['month_year' => '2026-09']);

        $this->assertEquals(1, $result['total']);
        $row = $result['rows'][0];
        $this->assertEquals(32000000, $row['gross_salary']);
        $this->assertEquals(1800000, $row['ot_tax_exempt']);
        $this->assertEquals(2625000, $row['employee_insurance']);
        $this->assertEquals(5875000, $row['employer_cost']);
        $this->assertEquals(1500000, $row['pit_tax']);
        $this->assertEquals(26500000, $row['net_salary']);
    }

    /**
     * Test 3: Báo cáo biến động nhân sự tổng hợp đúng số lượng tuyển mới, thôi việc và cơ cấu
     */
    public function test_headcount_turnover_report_tracks_recruitment_and_offboarding(): void
    {
        /** @var ReportDataProviderService $provider */
        $provider = app(ReportDataProviderService::class);
        $result = $provider->getReportData($this->tenant->id, ReportType::HEADCOUNT_TURNOVER->value, ['month_year' => '2026-09']);

        $this->assertGreaterThanOrEqual(1, $result['total']);
        $techDeptRow = collect($result['rows'])->firstWhere('department', 'Khối Công nghệ & Phần mềm');
        $this->assertNotNull($techDeptRow);
        $this->assertEquals(2, $techDeptRow['total_headcount']);
        $this->assertEquals(2, $techDeptRow['onboard_count']);
        $this->assertEquals(0, $techDeptRow['offboard_count']);
        $this->assertEquals(2, $techDeptRow['net_growth']);
    }

    /**
     * Test 4: Báo cáo rà soát tuân thủ pháp luật lao động tổng hợp đúng các rủi ro
     */
    public function test_compliance_audit_report_summarizes_labor_breaches(): void
    {
        ComplianceAlert::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'alert_type' => 'OT_BREACH_RED_40H',
            'severity' => 'CRITICAL',
            'title' => 'Vi phạm trần làm thêm giờ tháng (Đ.107 BLLĐ)',
            'description' => 'Nhân viên đã tích lũy 42.5h OT trong tháng 09/2026',
            'labor_code_reference' => 'Điều 107 Khoản 2 Điểm b BLLĐ 2019',
            'is_resolved' => false,
        ]);

        /** @var ReportDataProviderService $provider */
        $provider = app(ReportDataProviderService::class);
        $result = $provider->getReportData($this->tenant->id, ReportType::COMPLIANCE_AUDIT->value, []);

        $this->assertEquals(1, $result['total']);
        $row = $result['rows'][0];
        $this->assertEquals('CRITICAL', $row['severity']);
        $this->assertEquals('CHỜ XỬ LÝ', $row['status']);
        $this->assertEquals(1, $result['summary']['critical_count']);
    }

    /**
     * Test 5: Báo cáo phúc lợi con nhỏ & giảm trừ gia cảnh xác định đúng con dưới 12 tháng
     */
    public function test_child_welfare_report_lists_dependents_and_allowances(): void
    {
        EmployeeChild::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'full_name' => 'Nguyễn Minh Khôi',
            'birth_date' => Carbon::today()->subMonths(6)->toDateString(),
            'is_tax_dependent' => true,
            'tax_dependent_code' => 'NPT_123456',
            'monthly_allowance_amount' => 1000000,
            'is_active' => true,
        ]);

        /** @var ReportDataProviderService $provider */
        $provider = app(ReportDataProviderService::class);
        $result = $provider->getReportData($this->tenant->id, ReportType::CHILD_WELFARE->value, []);

        $this->assertEquals(1, $result['total']);
        $row = $result['rows'][0];
        $this->assertEquals('Nguyễn Minh Khôi', $row['child_name']);
        $this->assertEquals('ĐỦ ĐIỀU KIỆN', $row['is_under_12m']);
        $this->assertEquals('CÓ (NPT)', $row['is_tax_dependent']);
        $this->assertEquals(1000000, $row['monthly_allowance']);
        $this->assertEquals(1, $result['summary']['under_12m_count']);
    }

    /**
     * Test 6: Kỹ thuật Streaming CSV Export trả về đúng Header, UTF-8 BOM và dữ liệu
     */
    public function test_streaming_csv_export_includes_utf8_bom_and_headers(): void
    {
        MonthlyAttendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'month_year' => '2026-09',
            'total_standard_days' => 22.0,
            'total_ot_hours' => 5.0,
        ]);

        /** @var StreamingExportService $service */
        $service = app(StreamingExportService::class);

        $response = $service->export(
            tenantId: $this->tenant->id,
            userId: $this->superAdmin->id,
            reportType: ReportType::TIMESHEET_SUMMARY->value,
            format: 'CSV',
            filters: ['month_year' => '2026-09']
        );

        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('SmartHR_TIMESHEET_SUMMARY', (string)$response->headers->get('Content-Disposition'));

        // Đọc nội dung Stream
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Kiểm tra UTF-8 BOM (\xEF\xBB\xBF)
        $bom = substr($content, 0, 3);
        $this->assertEquals("\xEF\xBB\xBF", $bom);

        // Kiểm tra tiêu đề tiếng Việt có mặt trong CSV
        $this->assertStringContainsString('Mã NV', $content);
        $this->assertStringContainsString('Họ và tên', $content);
        $this->assertStringContainsString('EMP001', $content);
        $this->assertStringContainsString('Nguyễn Văn Minh', $content);
    }

    /**
     * Test 7: Quá trình xuất báo cáo tự động lưu vết kiểm toán vào report_export_histories
     */
    public function test_export_creates_audit_history_record(): void
    {
        /** @var StreamingExportService $service */
        $service = app(StreamingExportService::class);

        $response = $service->export(
            tenantId: $this->tenant->id,
            userId: $this->superAdmin->id,
            reportType: ReportType::HEADCOUNT_TURNOVER->value,
            format: 'EXCEL_TSV',
            filters: ['month_year' => '2026-09'],
            ipAddress: '127.0.0.1'
        );

        ob_start();
        $response->sendContent();
        ob_end_clean();

        $history = ReportExportHistory::where('tenant_id', $this->tenant->id)
            ->where('report_type', 'HEADCOUNT_TURNOVER')
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals($this->superAdmin->id, $history->user_id);
        $this->assertEquals('EXCEL_TSV', $history->file_format);
        $this->assertGreaterThanOrEqual(1, $history->row_count);
        $this->assertGreaterThanOrEqual(0, $history->execution_time_ms);
        $this->assertEquals('127.0.0.1', $history->ip_address);
    }

    /**
     * Test 8: Đảm bảo tính cô lập Multi-Tenant trong phân hệ Báo cáo
     */
    public function test_tenant_isolation_in_reporting(): void
    {
        // Tạo dữ liệu cho đối thủ ở tenant khác
        $rivalEmp = Employee::create([
            'tenant_id' => $this->otherTenant->id,
            'user_code' => 'RIVAL01',
            'full_name' => 'Gián Điệp Doanh Nghiệp',
            'gender' => 'MALE',
            'birth_date' => '1990-01-01',
            'email' => 'spy@rival.com',
            'status' => 'OFFICIAL',
            'contract_type' => 'FULL_TIME',
            'join_date' => '2025-01-01',
        ]);

        MonthlyAttendance::create([
            'tenant_id' => $this->otherTenant->id,
            'employee_id' => $rivalEmp->id,
            'month_year' => '2026-09',
            'total_standard_days' => 22.0,
            'total_ot_hours' => 50.0,
        ]);

        /** @var ReportDataProviderService $provider */
        $provider = app(ReportDataProviderService::class);
        $result = $provider->getReportData($this->tenant->id, ReportType::TIMESHEET_SUMMARY->value, ['month_year' => '2026-09']);

        // Khẳng định không bao giờ có nhân viên của tenant khác
        $rivalFound = collect($result['rows'])->contains(fn ($r) => $r['user_code'] === 'RIVAL01');
        $this->assertFalse($rivalFound);
    }

    /**
     * Test 9: Bộ lọc theo chi nhánh và phòng ban hoạt động chính xác
     */
    public function test_filter_by_branch_and_department(): void
    {
        // Tạo phòng ban thứ 2
        $hrDept = Department::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Phòng Hành chính Nhân sự',
            'code' => 'HR_DEPT',
            'level' => 1,
            'status' => 'ACTIVE',
        ]);

        DepartmentVersion::create([
            'department_id' => $hrDept->id,
            'name' => 'Phòng Hành chính Nhân sự',
            'effective_date' => '2026-01-01',
            'is_active' => true,
        ]);

        // Đóng timeline cũ của employee2 và chuyển sang HR_DEPT
        EmployeeCareerTimeline::where('employee_id', $this->employee2->id)->update(['end_date' => '2026-09-04']);

        EmployeeCareerTimeline::create([
            'employee_id' => $this->employee2->id,
            'department_id' => $hrDept->id,
            'position_id' => $this->position->id,
            'event_type' => 'TRANSFER',
            'effective_date' => '2026-09-05',
            'job_title' => 'Trưởng nhóm Tuyển dụng',
        ]);

        MonthlyAttendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee1->id,
            'month_year' => '2026-09',
            'total_standard_days' => 22.0,
        ]);

        MonthlyAttendance::create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $this->employee2->id,
            'month_year' => '2026-09',
            'total_standard_days' => 20.0,
        ]);

        /** @var ReportDataProviderService $provider */
        $provider = app(ReportDataProviderService::class);

        // Lọc theo TECH_DEPT -> chỉ trả về employee1
        $techResult = $provider->getReportData($this->tenant->id, ReportType::TIMESHEET_SUMMARY->value, [
            'month_year' => '2026-09',
            'department_id' => $this->department->id,
        ]);

        $this->assertEquals(1, $techResult['total']);
        $this->assertEquals('EMP001', $techResult['rows'][0]['user_code']);
    }

    /**
     * Test 10: Màn hình /reports render thành công qua Inertia với đầy đủ props
     */
    public function test_reporting_index_screen_renders_successfully(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/reports');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Report/Index')
            ->has('reportTypes')
            ->has('branches')
            ->has('departments')
            ->has('currentFilters')
            ->has('selectedReportType')
            ->has('initialPreview')
            ->has('recentExports')
        );
    }
}
