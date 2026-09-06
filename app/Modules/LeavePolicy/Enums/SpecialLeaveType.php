<?php

declare(strict_types=1);

namespace App\Modules\LeavePolicy\Enums;

enum SpecialLeaveType: string
{
    case MARRIAGE_SELF = 'MARRIAGE_SELF';
    case MARRIAGE_CHILD = 'MARRIAGE_CHILD';
    case FUNERAL_IMMEDIATE = 'FUNERAL_IMMEDIATE';

    public function label(): string
    {
        return match ($this) {
            self::MARRIAGE_SELF => 'Bản thân kết hôn (Điều 115 BLLĐ)',
            self::MARRIAGE_CHILD => 'Con đẻ/con nuôi kết hôn (Điều 115 BLLĐ)',
            self::FUNERAL_IMMEDIATE => 'Tứ thân phụ mẫu / Vợ/chồng / Con mất (Điều 115 BLLĐ)',
        };
    }

    public function statutoryDays(): float
    {
        return match ($this) {
            self::MARRIAGE_SELF => 3.0,
            self::MARRIAGE_CHILD => 1.0,
            self::FUNERAL_IMMEDIATE => 3.0,
        };
    }
}
