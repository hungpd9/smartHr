<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Enums;

enum ApprovalAction: string
{
    case APPROVE = 'APPROVE';
    case REJECT = 'REJECT';
    case FORWARD = 'FORWARD';
    case REQUEST_INFO = 'REQUEST_INFO';

    public function label(): string
    {
        return match ($this) {
            self::APPROVE => 'Chấp thuận',
            self::REJECT => 'Từ chối',
            self::FORWARD => 'Chuyển tiếp cấp cao hơn',
            self::REQUEST_INFO => 'Yêu cầu bổ sung thông tin',
        };
    }
}
