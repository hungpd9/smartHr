<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;
use App\Modules\Reporting\Enums\ReportType;
use App\Modules\Reporting\Services\ReportDataProviderService;
use App\Modules\Reporting\Services\StreamingExportService;
use App\Modules\Reporting\Models\ReportExportHistory;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\Department;

class ReportController extends Controller
{
    public function __construct(
        protected ReportDataProviderService $dataProvider,
        protected StreamingExportService $exportService
    ) {}

    /**
     * Màn hình chính Trung tâm báo cáo & phân tích
     */
    public function index(Request $request): Response
    {
        $tenantId = (int)($request->user()?->tenant_id ?? 1);
        $currentMonth = Carbon::now()->format('Y-m');

        // Danh sách các loại báo cáo hỗ trợ
        $reportTypes = array_map(fn (ReportType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'category' => $type->category(),
        ], ReportType::cases());

        // Danh mục Chi nhánh & Phòng ban phục vụ bộ lọc
        $branches = Branch::where('tenant_id', $tenantId)->select('id', 'name', 'code')->get();
        $departments = Department::with('currentVersion')
            ->where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->currentVersion?->name ?? $d->code,
            ]);

        $selectedType = (string)$request->input('report_type', ReportType::TIMESHEET_SUMMARY->value);
        $filters = [
            'month_year' => $request->input('month_year', $currentMonth),
            'branch_id' => $request->input('branch_id'),
            'department_id' => $request->input('department_id'),
            'search' => $request->input('search'),
            'severity' => $request->input('severity'),
            'is_resolved' => $request->input('is_resolved', 'ALL'),
            'is_tax_dependent' => $request->input('is_tax_dependent', 'ALL'),
        ];

        // Lấy dữ liệu xem trước ban đầu
        $previewData = $this->dataProvider->getReportData(
            tenantId: $tenantId,
            reportType: $selectedType,
            filters: $filters,
            page: (int)$request->input('page', 1),
            perPage: 15
        );

        // Lịch sử xuất gần nhất (Audit Trail)
        $recentExports = ReportExportHistory::forTenant($tenantId)
            ->with('user:id,name,email')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'report_type' => $item->report_type,
                'report_label' => $item->report_type_label,
                'file_name' => $item->file_name,
                'file_format' => $item->file_format,
                'row_count' => $item->row_count,
                'execution_time_ms' => $item->execution_time_ms,
                'user_name' => $item->user?->name ?? 'Hệ thống',
                'created_at' => Carbon::parse($item->created_at)->format('d/m/Y H:i:s'),
            ]);

        return Inertia::render('Report/Index', [
            'reportTypes' => $reportTypes,
            'branches' => $branches,
            'departments' => $departments,
            'currentFilters' => $filters,
            'selectedReportType' => $selectedType,
            'initialPreview' => $previewData,
            'recentExports' => $recentExports,
        ]);
    }

    /**
     * API tải dữ liệu xem trước khi người dùng đổi loại báo cáo hoặc bộ lọc
     */
    public function preview(Request $request): JsonResponse
    {
        $tenantId = (int)($request->user()?->tenant_id ?? 1);
        $reportType = (string)$request->input('report_type', ReportType::TIMESHEET_SUMMARY->value);
        $filters = $request->input('filters', []);
        $page = (int)$request->input('page', 1);
        $perPage = (int)$request->input('per_page', 15);

        $data = $this->dataProvider->getReportData(
            tenantId: $tenantId,
            reportType: $reportType,
            filters: $filters,
            page: $page,
            perPage: $perPage
        );

        return response()->json($data);
    }

    /**
     * Tải về file báo cáo Streaming (CSV UTF-8 BOM hoặc Excel TSV)
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $tenantId = (int)($user?->tenant_id ?? 1);
        $userId = (int)($user?->id ?? 1);

        $reportType = (string)$request->input('report_type', ReportType::TIMESHEET_SUMMARY->value);
        $format = (string)$request->input('format', 'CSV');

        $filters = [
            'month_year' => $request->input('month_year', Carbon::now()->format('Y-m')),
            'branch_id' => $request->input('branch_id'),
            'department_id' => $request->input('department_id'),
            'search' => $request->input('search'),
            'severity' => $request->input('severity'),
            'is_resolved' => $request->input('is_resolved', 'ALL'),
            'is_tax_dependent' => $request->input('is_tax_dependent', 'ALL'),
        ];

        return $this->exportService->export(
            tenantId: $tenantId,
            userId: $userId,
            reportType: $reportType,
            format: $format,
            filters: $filters,
            ipAddress: $request->ip()
        );
    }

    /**
     * API tra cứu lịch sử xuất báo cáo phân trang
     */
    public function history(Request $request): JsonResponse
    {
        $tenantId = (int)($request->user()?->tenant_id ?? 1);

        $histories = ReportExportHistory::forTenant($tenantId)
            ->with('user:id,name,email')
            ->latest()
            ->paginate(15);

        return response()->json($histories);
    }
}
