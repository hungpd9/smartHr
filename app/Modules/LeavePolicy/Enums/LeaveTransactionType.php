<?php

declare(strict_types=1);

namespace App\Modules\LeavePolicy\Enums;

enum LeaveTransactionType: string
{
    case ACCRUAL = 'ACCRUAL';
    case USAGE = 'USAGE';
    case EXPIRATION = 'EXPIRATION';
    case ADJUSTMENT = 'ADJUSTMENT';
    case COMPENSATION_CONVERT = 'COMPENSATION_CONVERT';

    public function label(): string
    {
        return match ($this) {
            self::ACCRUAL => 'Tích lũy phép (Accrual)',
            self::USAGE => 'Sử dụng nghỉ phép (Usage)',
            self::EXPIRATION => 'Hết hạn phép tồn (Expiration)',
            self::ADJUSTMENT => 'Điều chỉnh thủ công (Adjustment)',
            self::COMPENSATION_CONVERT => 'Quy đổi làm thêm giờ sang nghỉ bù (Comp-off Convert)',
        };
    }
}
