<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Enums;

enum RequestType: string
{
    case LEAVE = 'LEAVE';
    case OVERTIME = 'OVERTIME';
    case NIGHT_SHIFT = 'NIGHT_SHIFT';
    case TIMESHEET_EDIT = 'TIMESHEET_EDIT';
    case SHIFT_SWAP = 'SHIFT_SWAP';
    case RESIGNATION = 'RESIGNATION';
    case REMOTE_WORK = 'REMOTE_WORK';

    public function label(): string
    {
        return match ($this) {
            self::LEAVE => 'Đơn xin nghỉ phép',
            self::OVERTIME => 'Đơn đăng ký làm thêm giờ (OT)',
            self::NIGHT_SHIFT => 'Đơn thỏa thuận làm đêm / OT con nhỏ (Đ.137 BLLĐ)',
            self::TIMESHEET_EDIT => 'Đơn giải trình / sửa chấm công',
            self::SHIFT_SWAP => 'Đơn xin đổi ca làm việc',
            self::RESIGNATION => 'Đơn xin thôi việc',
            self::REMOTE_WORK => 'Đơn đăng ký làm việc từ xa',
        };
    }
}
