<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Enums;

enum RosteringStrategy: string
{
    case FAIRNESS = 'FAIRNESS';
    case COST_OPTIMIZATION = 'COST_OPTIMIZATION';

    public function label(): string
    {
        return match ($this) {
            self::FAIRNESS => 'Công bằng Nhân sự (Cân bằng ca đêm & cuối tuần)',
            self::COST_OPTIMIZATION => 'Tối ưu Chi phí Quỹ lương (Hạn chế tối đa OT)',
        };
    }
}
