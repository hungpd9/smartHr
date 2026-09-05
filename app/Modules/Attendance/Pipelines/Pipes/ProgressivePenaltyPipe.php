<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Pipelines\Pipes;

use App\Modules\Attendance\Pipelines\AttendanceCalculationContext;
use App\Modules\PolicyEngine\Services\PolicyResolutionService;
use Closure;

class ProgressivePenaltyPipe
{
    public function __construct(
        protected PolicyResolutionService $policyService
    ) {}

    public function handle(AttendanceCalculationContext $context, Closure $next)
    {
        $totalViolationMinutes = $context->lateMinutes + $context->earlyMinutes;

        if ($totalViolationMinutes <= 0) {
            $context->latePenaltyAmount = 0.00;
            return $next($context);
        }

        // Gọi PolicyResolutionService để tính tiền phạt theo bậc nấc và hệ số tần suất tháng
        $penaltyResult = $this->policyService->calculateLatePenalty(
            $context->tenantId,
            $context->workDate,
            $totalViolationMinutes,
            $context->violationCountThisMonth
        );

        $context->latePenaltyAmount = (float) ($penaltyResult['final_fine'] ?? 0.00);

        if ($context->latePenaltyAmount > 0) {
            $context->addNote(
                "Phạt muộn/sớm: " . number_format($context->latePenaltyAmount, 0, ',', '.') . " VNĐ " .
                "(Nấc {$totalViolationMinutes} phút, lần thứ {$context->violationCountThisMonth} trong tháng - Hệ số x{$penaltyResult['multiplier']})."
            );
        }

        return $next($context);
    }
}
