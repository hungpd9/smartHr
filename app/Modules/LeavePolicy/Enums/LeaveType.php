<?php

declare(strict_types=1);

namespace App\Modules\LeavePolicy\Enums;

enum LeaveType: string
{
    case ANNUAL = 'ANNUAL';
    case COMPENSATION = 'COMPENSATION';

    public function label(): string
    {
        return match ($this) {
            self::ANNUAL => 'Phép năm (Annual Leave)',
            self::COMPENSATION => 'Nghỉ bù OT (Comp-off)',
        };
    }
}
