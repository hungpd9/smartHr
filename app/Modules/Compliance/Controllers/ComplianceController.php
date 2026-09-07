<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Models\ComplianceAlert;
use App\Modules\Compliance\Services\ComplianceScannerService;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\Department;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComplianceController extends Controller
{
    public function __construct(
        protected ComplianceScannerService $scannerService
    ) {}

    /**
     * Màn hình Trung tâm Giám sát Tuân thủ Pháp lý & Cảnh báo Sớm BLLĐ
     */
    public function index(Request $request): Response
    {
        $tenantId = (int) ($request->user()->tenant_id ?? 1);

        // Các bộ lọc
        $severity = $request->input('severity');
        $alertType = $request->input('alert_type');
        $isResolved = $request->input('is_resolved'); // '0' (chưa giải quyết), '1' (đã giải quyết), null (tất cả)
        $search = $request->input('search');
        $departmentId = $request->input('department_id');
        $branchId = $request->input('branch_id');

        $query = ComplianceAlert::where('tenant_id', $tenantId)
            ->with([
                'employee.currentTimeline.department.currentVersion',
                'employee.currentTimeline.position',
                'employee.currentTimeline.department.branch',
                'resolvedBy',
            ]);

        if ($severity) {
            $query->where('severity', $severity);
        }

        if ($alertType) {
            $query->where('alert_type', $alertType);
        }

        if ($isResolved !== null && $isResolved !== '') {
            $query->where('is_resolved', (bool) (int) $isResolved);
        } else {
            // Mặc định ưu tiên hiển thị các cảnh báo chưa được xử lý lên trước
            $query->orderBy('is_resolved', 'asc');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($empQ) use ($search) {
                      $empQ->where('full_name', 'like', "%{$search}%")
                           ->orWhere('user_code', 'like', "%{$search}%");
                  });
            });
        }

        if ($departmentId) {
            $query->whereHas('employee.currentTimeline', function ($tlQ) use ($departmentId) {
                $tlQ->where('department_id', $departmentId);
            });
        }

        if ($branchId) {
            $query->whereHas('employee.currentTimeline.department', function ($deptQ) use ($branchId) {
                $deptQ->where('branch_id', $branchId);
            });
        }

        // Sắp xếp: Ưu tiên CRITICAL -> WARNING -> INFO, và mới nhất
        $query->orderByRaw("FIELD(severity, 'CRITICAL', 'WARNING', 'INFO')")
              ->orderBy('created_at', 'desc');

        $alerts = $query->paginate(15)->withQueryString();

        // Thống kê Dashboard KPI
        $stats = $this->scannerService->getComplianceDashboardStats($tenantId);

        // Danh sách phòng ban & chi nhánh phục vụ bộ lọc
        $departments = Department::where('tenant_id', $tenantId)
            ->with('currentVersion')
            ->get();

        $branches = Branch::where('tenant_id', $tenantId)->get();

        // Từ điển Căn cứ Pháp lý & Khung Phạt Hành chính
        $legalReferences = $this->getLegalReferences();

        return Inertia::render('Compliance/Index', [
            'alerts' => $alerts,
            'stats' => $stats,
            'departments' => $departments,
            'branches' => $branches,
            'legalReferences' => $legalReferences,
            'filters' => [
                'severity' => $severity,
                'alert_type' => $alertType,
                'is_resolved' => $isResolved,
                'search' => $search,
                'department_id' => $departmentId,
                'branch_id' => $branchId,
            ],
        ]);
    }

    /**
     * Kích hoạt quét tức thì toàn bộ tuân thủ BLLĐ từ giao diện
     */
    public function scanNow(Request $request): RedirectResponse
    {
        $tenantId = (int) ($request->user()->tenant_id ?? 1);
        $targetDate = $request->input('target_date', Carbon::today()->toDateString());

        $result = $this->scannerService->scanAll($tenantId, $targetDate);

        $score = $result['stats']['compliance_score'] ?? 100;
        $count = $result['total_alerts_generated_or_updated'] ?? 0;

        return redirect()->back()->with('success', "Đã quét xong toàn bộ hệ thống! Ghi nhận/cập nhật {$count} cảnh báo. Điểm sức khỏe tuân thủ hiện tại: {$score}%.");
    }

    /**
     * Đánh dấu đã giải quyết / khắc phục một cảnh báo vi phạm
     */
    public function resolve(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $userId = (int) ($request->user()->id ?? 1);

        $this->scannerService->resolveAlert(
            alertId: $id,
            resolvedByUserId: $userId,
            resolutionNotes: $request->input('resolution_notes')
        );

        return redirect()->back()->with('success', 'Đã ghi nhận biện pháp khắc phục và hoàn tất cảnh báo vi phạm thành công.');
    }

    /**
     * Khắc phục hàng loạt nhiều cảnh báo
     */
    public function bulkResolve(Request $request): RedirectResponse
    {
        $request->validate([
            'alert_ids' => ['required', 'array', 'min:1'],
            'alert_ids.*' => ['integer', 'exists:compliance_alerts,id'],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $userId = (int) ($request->user()->id ?? 1);
        $count = $this->scannerService->bulkResolve(
            alertIds: $request->input('alert_ids'),
            resolvedByUserId: $userId,
            resolutionNotes: $request->input('resolution_notes')
        );

        return redirect()->back()->with('success', "Đã khắc phục thành công {$count} cảnh báo vi phạm tuân thủ.");
    }

    /**
     * Danh mục từ điển trích dẫn điều luật BLLĐ 2019 & Nghị định 12/2022/NĐ-CP
     */
    protected function getLegalReferences(): array
    {
        return [
            'OT_BREACH_RED_40H' => [
                'law' => 'Điều 107 Khoản 2 Điểm b Bộ luật Lao động 2019',
                'decree' => 'Nghị định 145/2020/NĐ-CP (Điều 60)',
                'penalty' => 'Phạt tiền từ 10.000.000đ đến 75.000.000đ đối với người sử dụng lao động (Điều 18 Nghị định 12/2022/NĐ-CP).',
                'solution' => 'Dừng ngay việc phân ca làm thêm giờ cho nhân sự trong tháng này; chuyển giao các công việc phát sinh ngoài giờ cho nhân sự còn quỹ thời gian.',
            ],
            'OT_WARNING_YELLOW_35H' => [
                'law' => 'Điều 107 Khoản 2 Điểm b Bộ luật Lao động 2019',
                'decree' => 'Nghị định 145/2020/NĐ-CP',
                'penalty' => 'Cảnh báo rủi ro cận ngưỡng đỏ vi phạm trần 40 giờ.',
                'solution' => 'Chủ động điều phối giảm tải giờ làm thêm trong các ngày còn lại của tháng.',
            ],
            'OT_ANNUAL_CAP_BREACH' => [
                'law' => 'Điều 107 Khoản 2 Điểm c Bộ luật Lao động 2019',
                'decree' => 'Nghị định 145/2020/NĐ-CP (Điều 61, 62)',
                'penalty' => 'Phạt tiền từ 10.000.000đ đến 75.000.000đ và bị đình chỉ hoạt động tăng ca.',
                'solution' => 'Tuyệt đối không bố trí thêm giờ làm thêm cho đến hết năm tài chính; rà soát đối soát văn bản thông báo Sở LĐ-TB&XH nếu thuộc ngành nghề được phép làm đến 300h.',
            ],
            'REST_BETWEEN_SHIFTS_BREACH' => [
                'law' => 'Điều 110 Bộ luật Lao động 2019',
                'decree' => 'Nghị định 145/2020/NĐ-CP',
                'penalty' => 'Phạt tiền từ 4.000.000đ đến 40.000.000đ (Điều 18 Nghị định 12/2022/NĐ-CP).',
                'solution' => 'Điều chỉnh lại lịch phân ca hoặc đổi ca làm việc để bảo đảm thời gian nghỉ tối thiểu 12 giờ liên tục giữa 2 ca.',
            ],
            'WEEKLY_REST_BREACH' => [
                'law' => 'Điều 111 Bộ luật Lao động 2019',
                'decree' => 'Nghị định 145/2020/NĐ-CP',
                'penalty' => 'Phạt tiền từ 4.000.000đ đến 40.000.000đ (Điều 18 Nghị định 12/2022/NĐ-CP).',
                'solution' => 'Bố trí ngay 01 ngày nghỉ bù có hưởng lương trong tuần tiếp theo để bảo đảm sức khỏe người lao động.',
            ],
            'PREGNANT_NIGHT_BREACH' => [
                'law' => 'Điều 137 Khoản 1 Điểm a Bộ luật Lao động 2019',
                'decree' => 'Nghị định 145/2020/NĐ-CP (Chương VI)',
                'penalty' => 'Phạt tiền từ 20.000.000đ đến 40.000.000đ (Điều 28 Nghị định 12/2022/NĐ-CP).',
                'solution' => 'Chuyển ngay nhân sự sang ca làm việc ban ngày chuẩn 8h; hủy bỏ toàn bộ lịch phân ca đêm và lịch làm thêm giờ.',
            ],
            'CONTRACT_EXPIRING_30D' => [
                'law' => 'Điều 20 & Điều 45 Bộ luật Lao động 2019',
                'decree' => 'Nghị định 145/2020/NĐ-CP',
                'penalty' => 'Hợp đồng tự động chuyển thành vô thời hạn nếu quá 30 ngày không ký mới hoặc phát sinh tranh chấp bồi thường.',
                'solution' => 'Hoàn tất đánh giá công việc và phát hành văn bản thông báo gia hạn hoặc ký hợp đồng mới trước ngày hết hạn.',
            ],
            'BURNOUT_RISK_HIGH' => [
                'law' => 'Luật An toàn, Vệ sinh Lao động 2015',
                'decree' => 'Tiêu chuẩn Lao động và Sức khỏe nghề nghiệp',
                'penalty' => 'Rủi ro gia tăng tai nạn lao động, bồi thường chi phí y tế và giảm năng suất vận hành.',
                'solution' => 'Cắt giảm giờ tăng ca, luân phiên ca làm việc và tạo điều kiện nghỉ dưỡng phục hồi sức khỏe.',
            ],
        ];
    }
}
