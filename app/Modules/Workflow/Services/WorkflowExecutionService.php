<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Services;

use App\Models\User;
use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\LeavePolicy\Enums\LeaveTransactionType;
use App\Modules\LeavePolicy\Enums\LeaveType;
use App\Modules\LeavePolicy\Models\LeaveBalance;
use App\Modules\LeavePolicy\Models\LeaveTransaction;
use App\Modules\LeavePolicy\Models\OvertimeAccumulation;
use App\Modules\LeavePolicy\Services\LeaveBalanceService;
use App\Modules\Rostering\Models\Roster;
use App\Modules\Workflow\Enums\ApprovalAction;
use App\Modules\Workflow\Enums\RequestStatus;
use App\Modules\Workflow\Enums\RequestType;
use App\Modules\Notification\Services\NotificationDispatcherService;
use App\Modules\Workflow\Models\ApprovalHistory;
use App\Modules\Workflow\Models\ApprovalRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowExecutionService
{
    public function __construct(
        protected WorkflowRoutingService $routingService,
        protected LeaveBalanceService $leaveBalanceService
    ) {}

    /**
     * Tạo đơn yêu cầu mới và khởi tạo chuỗi duyệt (Snapshot Approvers)
     */
    public function createRequest(
        int $tenantId,
        int $employeeId,
        string $requestType,
        array $payload,
        bool $isVoluntaryConsent = false,
        ?string $agreementFile = null,
        ?string $agreementUrl = null
    ): ApprovalRequest {
        $employee = Employee::with(['children'])->where('tenant_id', $tenantId)->findOrFail($employeeId);

        // 1. Kiểm tra ràng buộc Bộ luật Lao động 2019 và nghiệp vụ đặc thù
        $this->validateComplianceAndConstraints($employee, $requestType, $payload, $isVoluntaryConsent);

        return DB::transaction(function () use (
            $tenantId,
            $employee,
            $requestType,
            $payload,
            $isVoluntaryConsent,
            $agreementFile,
            $agreementUrl
        ) {
            // 2. Tạm giữ ngày phép nếu là đơn xin nghỉ phép năm
            if ($requestType === RequestType::LEAVE->value) {
                $leaveCategory = $payload['leave_category'] ?? 'ANNUAL';
                if ($leaveCategory === 'ANNUAL') {
                    $requestedDays = (float)($payload['days'] ?? $payload['duration_days'] ?? 1.0);
                    $targetYear = (int)($payload['year'] ?? Carbon::parse($payload['start_date'] ?? now())->year);

                    $balance = LeaveBalance::where('tenant_id', $tenantId)
                        ->where('employee_id', $employee->id)
                        ->where('year', $targetYear)
                        ->where('leave_type', LeaveType::ANNUAL->value)
                        ->first();

                    if (!$balance) {
                        $balance = $this->leaveBalanceService->initializeOrUpdateAnnualBalance($employee, $targetYear);
                    }

                    if ((float)$balance->remaining_days < $requestedDays) {
                        throw ValidationException::withMessages([
                            'days' => "Số dư phép năm khả dụng không đủ (Còn lại: {$balance->remaining_days} ngày, Yêu cầu: {$requestedDays} ngày)."
                        ]);
                    }

                    // Đóng băng tạm giữ
                    $balance->pending_days = (float)$balance->pending_days + $requestedDays;
                    $balance->remaining_days = max(0.0, (float)$balance->remaining_days - $requestedDays);
                    $balance->save();
                }
            }

            // 3. Phân giải chuỗi người duyệt snapshot theo cơ cấu tổ chức
            $snapshotApprovers = $this->routingService->resolveApprovalChain(
                $tenantId,
                $employee,
                $requestType,
                $payload
            );

            // 4. Tạo bản ghi đơn phê duyệt
            $createdRequest = ApprovalRequest::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'request_type' => $requestType,
                'form_payload' => $payload,
                'is_voluntary_consent' => $isVoluntaryConsent,
                'voluntary_agreement_file' => $agreementFile,
                'voluntary_agreement_url' => $agreementUrl,
                'current_step' => 1,
                'snapshot_approvers' => $snapshotApprovers,
                'status' => RequestStatus::PENDING->value,
            ]);

            // Phát thông báo đến cấp duyệt đầu tiên
            $firstStep = $snapshotApprovers[0] ?? null;
            if ($firstStep && !empty($firstStep['approver_id'])) {
                $this->notifyApproverForStep($tenantId, (int) $firstStep['approver_id'], $createdRequest, $employee);
            }

            return $createdRequest;
        });
    }

    /**
     * Phê duyệt bước hiện tại của quy trình
     */
    public function approveStep(
        ApprovalRequest $request,
        Employee $approver,
        string $comment = '',
        bool $verifiedConsent = false,
        ?User $actorUser = null
    ): ApprovalRequest {
        if ($request->status !== RequestStatus::PENDING->value) {
            throw ValidationException::withMessages([
                'approval' => 'Đơn này không ở trạng thái chờ duyệt (Hiện tại: ' . $request->status . ').'
            ]);
        }

        return DB::transaction(function () use ($request, $approver, $comment, $verifiedConsent, $actorUser) {
            $snapshot = $request->snapshot_approvers;
            $currentStep = (int)$request->current_step;
            $stepIndex = $currentStep - 1;

            if (!isset($snapshot[$stepIndex])) {
                throw new \RuntimeException("Không tìm thấy cấu hình duyệt cho cấp {$currentStep}.");
            }

            $currentStepConfig = $snapshot[$stepIndex];

            // Kiểm tra quyền duyệt: đúng approver_id hoặc là HR_ADMIN / SUPER_ADMIN
            $isAuthorized = ($currentStepConfig['approver_id'] === $approver->id);
            if (!$isAuthorized && $actorUser) {
                $roleVal = $actorUser->role instanceof \App\Enums\UserRole ? $actorUser->role->value : (string)$actorUser->role;
                if (in_array($roleVal, ['HR_ADMIN', 'SUPER_ADMIN', 'ADMIN'], true)) {
                    $isAuthorized = true;
                }
            }

            if (!$isAuthorized) {
                $user = User::where('email', $approver->email)->first();
                if ($user) {
                    $roleVal = $user->role instanceof \App\Enums\UserRole ? $user->role->value : (string)$user->role;
                    if (in_array($roleVal, ['HR_ADMIN', 'SUPER_ADMIN', 'ADMIN'], true)) {
                        $isAuthorized = true;
                    }
                }
            }

            if (!$isAuthorized) {
                throw ValidationException::withMessages([
                    'approver' => 'Nhân sự không có quyền phê duyệt cấp này.'
                ]);
            }

            // Ghi lịch sử phê duyệt
            ApprovalHistory::create([
                'request_id' => $request->id,
                'approver_id' => $approver->id,
                'step' => $currentStep,
                'action' => ApprovalAction::APPROVE->value,
                'comment' => $comment ?: 'Đã phê duyệt',
                'verified_child_under_12m_consent' => $verifiedConsent,
                'created_at' => now(),
            ]);

            // Cập nhật snapshot cho bước này
            $snapshot[$stepIndex]['status'] = 'APPROVED';
            $snapshot[$stepIndex]['action_at'] = now()->toDateTimeString();
            $snapshot[$stepIndex]['comment'] = $comment;
            $snapshot[$stepIndex]['approver_name'] = $approver->full_name;

            // Kiểm tra xem đã là cấp duyệt cuối cùng hay chưa
            if ($currentStep >= count($snapshot)) {
                $request->status = RequestStatus::APPROVED->value;
                $request->snapshot_approvers = $snapshot;
                $request->save();

                // Kích hoạt nghiệp vụ hậu phê duyệt
                $this->dispatchPostApprovalEffect($request);

                // Phát thông báo kết quả phê duyệt tới người tạo đơn
                $this->notifySubmitterStatus($request, 'APPROVED', $approver->full_name, $comment);
            } else {
                // Chuyển sang cấp duyệt tiếp theo
                $request->current_step = $currentStep + 1;
                $snapshot[$stepIndex + 1]['status'] = 'PENDING';
                $request->snapshot_approvers = $snapshot;
                $request->save();

                // Phát thông báo tới người duyệt cấp tiếp theo
                $nextStep = $snapshot[$stepIndex + 1] ?? null;
                if ($nextStep && !empty($nextStep['approver_id'])) {
                    $this->notifyApproverForStep($request->tenant_id, (int) $nextStep['approver_id'], $request, $request->employee);
                }
            }

            return $request->fresh();
        });
    }

    /**
     * Từ chối đơn tại bước hiện tại
     */
    public function rejectStep(
        ApprovalRequest $request,
        Employee $approver,
        string $comment = '',
        ?User $actorUser = null
    ): ApprovalRequest {
        if ($request->status !== RequestStatus::PENDING->value) {
            throw ValidationException::withMessages([
                'approval' => 'Đơn này không ở trạng thái chờ duyệt.'
            ]);
        }

        return DB::transaction(function () use ($request, $approver, $comment, $actorUser) {
            $snapshot = $request->snapshot_approvers;
            $currentStep = (int)$request->current_step;
            $stepIndex = $currentStep - 1;

            if (!isset($snapshot[$stepIndex])) {
                throw new \RuntimeException("Không tìm thấy cấu hình duyệt cho cấp {$currentStep}.");
            }

            $currentStepConfig = $snapshot[$stepIndex];

            // Kiểm tra quyền từ chối: đúng approver_id hoặc là HR_ADMIN / SUPER_ADMIN
            $isAuthorized = ($currentStepConfig['approver_id'] === $approver->id);
            if (!$isAuthorized && $actorUser) {
                $roleVal = $actorUser->role instanceof \App\Enums\UserRole ? $actorUser->role->value : (string)$actorUser->role;
                if (in_array($roleVal, ['HR_ADMIN', 'SUPER_ADMIN', 'ADMIN'], true)) {
                    $isAuthorized = true;
                }
            }

            if (!$isAuthorized) {
                $user = User::where('email', $approver->email)->first();
                if ($user) {
                    $roleVal = $user->role instanceof \App\Enums\UserRole ? $user->role->value : (string)$user->role;
                    if (in_array($roleVal, ['HR_ADMIN', 'SUPER_ADMIN', 'ADMIN'], true)) {
                        $isAuthorized = true;
                    }
                }
            }

            if (!$isAuthorized) {
                throw ValidationException::withMessages([
                    'approver' => 'Nhân sự không có quyền phê duyệt cấp này.'
                ]);
            }

            // Ghi lịch sử
            ApprovalHistory::create([
                'request_id' => $request->id,
                'approver_id' => $approver->id,
                'step' => $currentStep,
                'action' => ApprovalAction::REJECT->value,
                'comment' => $comment ?: 'Từ chối phê duyệt',
                'verified_child_under_12m_consent' => false,
                'created_at' => now(),
            ]);

            if (isset($snapshot[$stepIndex])) {
                $snapshot[$stepIndex]['status'] = 'REJECTED';
                $snapshot[$stepIndex]['action_at'] = now()->toDateTimeString();
                $snapshot[$stepIndex]['comment'] = $comment;
            }

            $request->status = RequestStatus::REJECTED->value;
            $request->snapshot_approvers = $snapshot;
            $request->save();

            // Hoàn lại phép tạm giữ nếu là đơn phép năm
            $this->rollbackPendingHolds($request);

            // Phát thông báo từ chối tới người tạo đơn
            $this->notifySubmitterStatus($request, 'REJECTED', $approver->full_name, $comment);

            return $request->fresh();
        });
    }

    /**
     * Hủy đơn do chính nhân sự nộp hoặc quản trị viên
     */
    public function cancelRequest(ApprovalRequest $request, Employee $canceller): ApprovalRequest
    {
        if ($request->status !== RequestStatus::PENDING->value) {
            throw ValidationException::withMessages([
                'cancel' => 'Chỉ có thể hủy đơn khi đang ở trạng thái chờ duyệt.'
            ]);
        }

        return DB::transaction(function () use ($request, $canceller) {
            $request->status = RequestStatus::CANCELLED->value;
            $request->save();

            // Hoàn lại phép tạm giữ nếu là đơn xin nghỉ
            $this->rollbackPendingHolds($request);

            return $request->fresh();
        });
    }

    /**
     * Kích hoạt hiệu ứng sau khi đơn được phê duyệt toàn bộ cấp
     */
    public function dispatchPostApprovalEffect(ApprovalRequest $request): void
    {
        $payload = $request->form_payload ?? [];
        $tenantId = (int)$request->tenant_id;
        $employeeId = (int)$request->employee_id;

        switch ($request->request_type) {
            case RequestType::LEAVE->value:
                $leaveCategory = $payload['leave_category'] ?? 'ANNUAL';
                if ($leaveCategory === 'ANNUAL') {
                    $requestedDays = (float)($payload['days'] ?? $payload['duration_days'] ?? 1.0);
                    $targetYear = (int)($payload['year'] ?? Carbon::parse($payload['start_date'] ?? now())->year);

                    $balance = LeaveBalance::where('tenant_id', $tenantId)
                        ->where('employee_id', $employeeId)
                        ->where('year', $targetYear)
                        ->where('leave_type', LeaveType::ANNUAL->value)
                        ->first();

                    if ($balance) {
                        $balance->pending_days = max(0.0, (float)$balance->pending_days - $requestedDays);
                        $balance->used_days = (float)$balance->used_days + $requestedDays;
                        $balance->taken_paid_days = $balance->used_days;
                        $balance->save();

                        LeaveTransaction::create([
                            'leave_balance_id' => $balance->id,
                            'transaction_type' => LeaveTransactionType::USAGE->value,
                            'days' => -$requestedDays,
                            'reference_id' => $request->id,
                            'description' => "Trừ quỹ phép năm từ Đơn phê duyệt #{$request->id}" . (!empty($payload['reason']) ? " ({$payload['reason']})" : ""),
                            'created_at' => now(),
                        ]);
                    }
                }
                break;

            case RequestType::OVERTIME->value:
                $otHours = (float)($payload['hours'] ?? $payload['ot_hours'] ?? 0.0);
                $otDate = Carbon::parse($payload['date'] ?? $payload['work_date'] ?? now());
                $year = $otDate->year;
                $monthKey = sprintf('%02d', $otDate->month);

                $otAccumulation = OvertimeAccumulation::firstOrNew([
                    'tenant_id' => $tenantId,
                    'employee_id' => $employeeId,
                    'year' => $year,
                ]);

                $otAccumulation->total_ot_hours_year = (float)($otAccumulation->total_ot_hours_year ?? 0.0) + $otHours;
                $monthly = $otAccumulation->monthly_ot_json ?? [];
                $monthly[$monthKey] = (float)($monthly[$monthKey] ?? 0.0) + $otHours;
                $otAccumulation->monthly_ot_json = $monthly;
                $otAccumulation->is_exceeded_200h = ((float)$otAccumulation->total_ot_hours_year > 200.0);
                $otAccumulation->is_exceeded_300h = ((float)$otAccumulation->total_ot_hours_year > 300.0);
                $otAccumulation->save();
                break;

            case RequestType::SHIFT_SWAP->value:
                $requesterRosterId = $payload['requester_roster_id'] ?? null;
                $targetRosterId = $payload['target_roster_id'] ?? null;

                if ($requesterRosterId && $targetRosterId) {
                    $reqRoster = Roster::find($requesterRosterId);
                    $tgtRoster = Roster::find($targetRosterId);

                    if ($reqRoster && $tgtRoster) {
                        $tempShift = $reqRoster->shift_id;
                        $reqRoster->shift_id = $tgtRoster->shift_id;
                        $reqRoster->status = 'SWAPPED';
                        $reqRoster->save();

                        $tgtRoster->shift_id = $tempShift;
                        $tgtRoster->status = 'SWAPPED';
                        $tgtRoster->save();
                    }
                }
                break;

            case RequestType::TIMESHEET_EDIT->value:
                $workDate = $payload['work_date'] ?? null;
                if ($workDate) {
                    $daily = DailyAttendance::where('tenant_id', $tenantId)
                        ->where('employee_id', $employeeId)
                        ->where('work_date', $workDate)
                        ->first();

                    if ($daily) {
                        if (!empty($payload['actual_in'])) {
                            $daily->actual_in = Carbon::parse($payload['actual_in']);
                        }
                        if (!empty($payload['actual_out'])) {
                            $daily->actual_out = Carbon::parse($payload['actual_out']);
                        }
                        if (isset($payload['standard_hours'])) {
                            $daily->standard_hours = (float)$payload['standard_hours'];
                        }
                        $daily->save();
                    }
                }
                break;

            case RequestType::NIGHT_SHIFT->value:
                // Nếu đơn làm đêm có cam kết tự nguyện đã được phê duyệt, lưu cờ vào bảng rosters nếu có
                $workDate = $payload['work_date'] ?? null;
                if ($workDate) {
                    $roster = Roster::where('tenant_id', $tenantId)
                        ->where('employee_id', $employeeId)
                        ->where('roster_date', $workDate)
                        ->first();
                    if ($roster) {
                        $flags = $roster->violation_flags ?? [];
                        $flags['child_under_12m_night_consented'] = true;
                        $roster->violation_flags = $flags;
                        $roster->save();
                    }
                }
                break;
        }
    }

    /**
     * Hoàn lại các ngày phép đã tạm giữ khi đơn bị hủy hoặc từ chối
     */
    protected function rollbackPendingHolds(ApprovalRequest $request): void
    {
        if ($request->request_type === RequestType::LEAVE->value) {
            $payload = $request->form_payload ?? [];
            $leaveCategory = $payload['leave_category'] ?? 'ANNUAL';
            if ($leaveCategory === 'ANNUAL') {
                $requestedDays = (float)($payload['days'] ?? $payload['duration_days'] ?? 1.0);
                $targetYear = (int)($payload['year'] ?? Carbon::parse($payload['start_date'] ?? now())->year);

                $balance = LeaveBalance::where('tenant_id', $request->tenant_id)
                    ->where('employee_id', $request->employee_id)
                    ->where('year', $targetYear)
                    ->where('leave_type', LeaveType::ANNUAL->value)
                    ->first();

                if ($balance && (float)$balance->pending_days >= $requestedDays) {
                    $balance->pending_days = max(0.0, (float)$balance->pending_days - $requestedDays);
                    $balance->remaining_days = (float)$balance->remaining_days + $requestedDays;
                    $balance->save();
                }
            }
        }
    }

    /**
     * Kiểm tra tuân thủ Bộ luật Lao động 2019
     */
    protected function validateComplianceAndConstraints(
        Employee $employee,
        string $requestType,
        array $payload,
        bool $isVoluntaryConsent
    ): void {
        // Kiểm tra lao động nuôi con nhỏ dưới 12 tháng tuổi (Điều 137 Khoản 1 Điểm b BLLĐ 2019)
        $hasChildUnder12m = $employee->children->contains(function ($child) {
            if (!$child->is_active || !$child->birth_date) {
                return false;
            }
            return Carbon::parse($child->birth_date)->diffInMonths(Carbon::today()) < 12;
        });

        if ($hasChildUnder12m) {
            if ($requestType === RequestType::NIGHT_SHIFT->value && !$isVoluntaryConsent) {
                throw ValidationException::withMessages([
                    'is_voluntary_consent' => 'Theo Điều 137 Khoản 1 Điểm b BLLĐ 2019, người lao động nuôi con dưới 12 tháng tuổi chỉ làm việc ban đêm khi có cam kết thỏa thuận tự nguyện bằng văn bản.'
                ]);
            }

            if ($requestType === RequestType::OVERTIME->value && !$isVoluntaryConsent) {
                throw ValidationException::withMessages([
                    'is_voluntary_consent' => 'Theo Điều 137 Khoản 1 Điểm b BLLĐ 2019, người lao động nuôi con dưới 12 tháng tuổi chỉ làm thêm giờ khi có cam kết thỏa thuận tự nguyện bằng văn bản.'
                ]);
            }
        }

        // Kiểm tra số giờ làm thêm tối đa trong ngày (Điều 107 BLLĐ 2019: không quá 50% giờ bình thường = 4 giờ/ngày)
        if ($requestType === RequestType::OVERTIME->value) {
            $hours = (float)($payload['hours'] ?? $payload['ot_hours'] ?? 0.0);
            if ($hours > 4.0) {
                throw ValidationException::withMessages([
                    'hours' => 'Thời gian làm thêm giờ trong ngày không được vượt quá 4.0 giờ theo quy định Điều 107 BLLĐ 2019.'
                ]);
            }
        }
    }

    /**
     * Gửi thông báo đến cấp duyệt
     */
    protected function notifyApproverForStep(int $tenantId, int $approverId, ApprovalRequest $request, ?Employee $employee = null): void
    {
        try {
            $approverUser = User::where('employee_id', $approverId)->first();
            if (!$approverUser) {
                $approverEmp = Employee::find($approverId);
                if ($approverEmp) {
                    $approverUser = User::where('email', $approverEmp->email)->first();
                }
            }
            if ($approverUser) {
                $empName = $employee?->full_name ?? 'Nhân viên';
                $empCode = $employee?->user_code ? " ({$employee->user_code})" : '';
                app(NotificationDispatcherService::class)->sendToUser(
                    tenantId: $tenantId,
                    userId: $approverUser->id,
                    type: 'WORKFLOW_PENDING_APPROVAL',
                    title: "Đơn cần duyệt: {$request->request_type} - {$empName}",
                    content: "Nhân viên {$empName}{$empCode} đã gửi một đơn {$request->request_type} cần bạn phê duyệt.",
                    actionUrl: '/workflow/requests?tab=to_approve',
                    payload: ['request_id' => $request->id, 'type' => $request->request_type],
                    severity: 'INFO',
                    employeeId: $approverId
                );
            }
        } catch (\Throwable) {
            // Không làm gián đoạn luồng nghiệp vụ
        }
    }

    /**
     * Gửi thông báo kết quả phê duyệt đến người nộp đơn
     */
    protected function notifySubmitterStatus(ApprovalRequest $request, string $status, string $approverName, string $comment = ''): void
    {
        try {
            $submitterUser = User::where('employee_id', $request->employee_id)->first();
            if (!$submitterUser) {
                $emp = $request->employee ?? Employee::find($request->employee_id);
                if ($emp) {
                    $submitterUser = User::where('email', $emp->email)->first();
                }
            }
            if ($submitterUser) {
                $isApproved = ($status === 'APPROVED');
                $type = $isApproved ? 'WORKFLOW_APPROVED' : 'WORKFLOW_REJECTED';
                $severity = $isApproved ? 'SUCCESS' : 'WARNING';
                $actionText = $isApproved ? 'phê duyệt' : 'từ chối';
                $title = "Đơn {$request->request_type} đã được {$actionText}";
                $reasonText = (!$isApproved && $comment) ? " Lý do: {$comment}" : '';
                $content = "Đơn {$request->request_type} của bạn đã được {$approverName} {$actionText}.{$reasonText}";

                app(NotificationDispatcherService::class)->sendToUser(
                    tenantId: $request->tenant_id,
                    userId: $submitterUser->id,
                    type: $type,
                    title: $title,
                    content: $content,
                    actionUrl: '/workflow/requests?tab=my_requests',
                    payload: ['request_id' => $request->id, 'status' => $status],
                    severity: $severity,
                    employeeId: $request->employee_id
                );
            }
        } catch (\Throwable) {
            // Không làm gián đoạn luồng nghiệp vụ
        }
    }
}

