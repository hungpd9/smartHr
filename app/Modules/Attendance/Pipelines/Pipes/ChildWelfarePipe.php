<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Pipelines\Pipes;

use App\Modules\Attendance\Pipelines\AttendanceCalculationContext;
use Carbon\Carbon;
use Closure;

class ChildWelfarePipe
{
    public function handle(AttendanceCalculationContext $context, Closure $next)
    {
        $employee = $context->employee;

        if (!$employee || $employee->gender !== 'FEMALE') {
            return $next($context);
        }

        // 1. Chế độ con dưới 12 tháng tuổi (Điều 137 Khoản 1 Điểm b BLLĐ 2019)
        if ($employee->hasChildUnder12MonthsAt($context->workDate)) {
            $context->childExemptionMinutes = 60; // 60 phút có hưởng lương mỗi ngày làm việc
            $context->addNote('Áp dụng chế độ con dưới 12 tháng tuổi (Điều 137 BLLĐ: miễn trừ 60 phút/ngày).');
            return $next($context);
        }

        // 2. Chế độ mở rộng của doanh nghiệp (từ policy_versions -> child_welfare_policies)
        if ($context->policyVersion && $context->policyVersion->childWelfarePolicies) {
            $maxExemption = 0;
            $bestPolicyNote = null;

            // Nạp danh sách con
            $children = $employee->relationLoaded('children')
                ? $employee->children->where('is_active', true)
                : $employee->children()->where('is_active', true)->get();

            foreach ($children as $child) {
                if ($child->birth_date && $child->birth_date->lte($context->workDate)) {
                    $ageMonths = (int) $child->birth_date->diffInMonths($context->workDate);

                    $matchedPolicy = $context->policyVersion->childWelfarePolicies
                        ->filter(fn ($p) => $p->visibility !== 'HIDDEN' && $p->is_active)
                        ->first(function ($p) use ($ageMonths) {
                            return $ageMonths >= $p->min_age_months && $ageMonths <= $p->max_age_months;
                        });

                    if ($matchedPolicy && (int) $matchedPolicy->daily_exemption_minutes > $maxExemption) {
                        $maxExemption = (int) $matchedPolicy->daily_exemption_minutes;
                        $bestPolicyNote = "Áp dụng phúc lợi con nhỏ mở rộng ({$child->full_name}, {$child->age_text}): {$matchedPolicy->name} (miễn trừ {$matchedPolicy->daily_exemption_minutes} phút/ngày).";
                    }
                }
            }

            if ($maxExemption > 0) {
                $context->childExemptionMinutes = $maxExemption;
                $context->addNote($bestPolicyNote);
            }
        }

        return $next($context);
    }
}
