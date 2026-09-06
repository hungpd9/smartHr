<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Modules\Rostering\Models\Roster;
use App\Modules\Rostering\Models\ShiftSwapRequest;

class ShiftSwapService
{
    public function __construct(
        protected RosteringConstraintValidator $validator
    ) {}

    /**
     * Tạo đề xuất đổi ca hoặc bắt ca thay
     */
    public function createSwapRequest(
        int $requesterId,
        int $requesterRosterId,
        int $targetEmployeeId,
        ?int $targetRosterId,
        string $swapType = 'SWAP',
        ?string $reason = null
    ): ShiftSwapRequest {
        $reqRoster = Roster::with('shift')->findOrFail($requesterRosterId);
        $targetRoster = $targetRosterId ? Roster::with('shift')->findOrFail($targetRosterId) : null;

        $violations = [];
        $isCompliant = true;

        // 1. Kiểm tra tính hợp pháp khi Target nhận ca của Requester
        $checkTarget = $this->validator->validateShiftAssignment(
            $targetEmployeeId,
            $reqRoster->shift_id,
            $reqRoster->roster_date
        );

        if ($checkTarget['is_hard_violation']) {
            $isCompliant = false;
            $violations = array_merge($violations, $checkTarget['hard_violations']);
        }

        // 2. Nếu là hình thức đổi ca (SWAP), kiểm tra tính hợp pháp khi Requester nhận ca của Target
        if ($swapType === 'SWAP' && $targetRoster) {
            $checkReq = $this->validator->validateShiftAssignment(
                $requesterId,
                $targetRoster->shift_id,
                $targetRoster->roster_date
            );

            if ($checkReq['is_hard_violation']) {
                $isCompliant = false;
                $violations = array_merge($violations, $checkReq['hard_violations']);
            }
        }

        return ShiftSwapRequest::create([
            'requester_id' => $requesterId,
            'target_employee_id' => $targetEmployeeId,
            'requester_roster_id' => $requesterRosterId,
            'target_roster_id' => $targetRosterId,
            'swap_type' => $swapType,
            'reason' => $reason,
            'is_legal_compliant' => $isCompliant,
            'violation_warnings' => $violations,
            'status' => 'PENDING_MANAGER', // Đưa lên Quản lý duyệt
        ]);
    }

    /**
     * Phê duyệt hoán đổi ca làm việc và cập nhật bảng rosters
     */
    public function approveSwap(int $requestId, ?int $approverId = null): ShiftSwapRequest
    {
        $swap = ShiftSwapRequest::with(['requesterRoster', 'targetRoster'])->findOrFail($requestId);

        if ($swap->status === 'APPROVED') {
            return $swap;
        }

        DB::transaction(function () use ($swap) {
            $reqRoster = $swap->requesterRoster;
            $targetRoster = $swap->targetRoster;

            if ($swap->swap_type === 'SWAP' && $targetRoster) {
                // Hoán đổi shift_id giữa 2 roster
                $reqShiftId = $reqRoster->shift_id;
                $targetShiftId = $targetRoster->shift_id;

                $reqRoster->update([
                    'shift_id' => $targetShiftId,
                    'status' => 'SWAPPED',
                ]);

                $targetRoster->update([
                    'shift_id' => $reqShiftId,
                    'status' => 'SWAPPED',
                ]);
            } elseif ($swap->swap_type === 'COVER') {
                // Target nhận ca thay, Requester chuyển thành ngày nghỉ (OFF)
                $reqShiftId = $reqRoster->shift_id;

                // Gán ca cho Target vào ngày của Requester
                Roster::updateOrCreate(
                    [
                        'tenant_id' => $reqRoster->tenant_id,
                        'employee_id' => $swap->target_employee_id,
                        'roster_date' => $reqRoster->roster_date,
                    ],
                    [
                        'shift_id' => $reqShiftId,
                        'status' => 'SCHEDULED',
                    ]
                );

                // Xóa ca của Requester
                $reqRoster->delete();
            }

            $swap->update(['status' => 'APPROVED']);
        });

        return $swap->fresh();
    }

    /**
     * Từ chối yêu cầu đổi ca
     */
    public function rejectSwap(int $requestId, ?string $reason = null): ShiftSwapRequest
    {
        $swap = ShiftSwapRequest::findOrFail($requestId);
        $swap->update([
            'status' => 'REJECTED',
            'reason' => $reason ? "{$swap->reason} | Lý do từ chối: {$reason}" : $swap->reason,
        ]);

        return $swap;
    }
}
