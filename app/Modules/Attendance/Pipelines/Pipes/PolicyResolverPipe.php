<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Pipelines\Pipes;

use App\Modules\Attendance\Pipelines\AttendanceCalculationContext;
use App\Modules\PolicyEngine\Services\PolicyResolutionService;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Holiday;
use Closure;

class PolicyResolverPipe
{
    public function __construct(
        protected PolicyResolutionService $policyService
    ) {}

    public function handle(AttendanceCalculationContext $context, Closure $next)
    {
        // 1. Nạp hồ sơ nhân viên
        if (!$context->employee) {
            $context->employee = Employee::where('tenant_id', $context->tenantId)
                ->find($context->employeeId);
        }

        // 2. Nạp phiên bản quy chế áp dụng (Effective Dating)
        $context->policyVersion = $this->policyService->resolveForDate($context->tenantId, $context->workDate);

        // 3. Kiểm tra ngày nghỉ lễ tết (Điều 112 BLLĐ 2019)
        $dateStr = $context->workDate->format('Y-m-d');
        $holiday = Holiday::where('tenant_id', $context->tenantId)
            ->where('holiday_date', $dateStr)
            ->first();

        if ($holiday) {
            $context->isHoliday = true;
            $context->holidayName = $holiday->name;

            // Nếu là ngày lễ và không có quẹt thẻ (nhân viên nghỉ lễ đúng luật)
            if (!$context->actualIn && !$context->actualOut) {
                $context->status = 'HOLIDAY';
                $context->standardHours = 8.00; // Hưởng nguyên lương ngày lễ theo luật
                $context->addNote("Nghỉ lễ hưởng nguyên lương: {$holiday->name}");
                // Kết thúc xử lý, không tính phạt vi phạm
                return $context;
            }
        }

        return $next($context);
    }
}
