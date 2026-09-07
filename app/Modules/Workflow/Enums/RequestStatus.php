<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Enums;

enum RequestStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ phê duyệt',
            self::APPROVED => 'Đã phê duyệt',
            self::REJECTED => 'Bị từ chối',
            self::CANCELLED => 'Đã hủy bỏ',
        };
    }
}
