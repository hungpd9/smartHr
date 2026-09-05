<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case HR_ADMIN = 'HR_ADMIN';
    case ACCOUNTANT = 'ACCOUNTANT';
    case DEPARTMENT_MANAGER = 'DEPARTMENT_MANAGER';
    case EMPLOYEE = 'EMPLOYEE';

    public function label(string $locale = 'vi'): string
    {
        return match ($this) {
            self::SUPER_ADMIN => $locale === 'en' ? 'System Administrator' : 'Quản trị viên Hệ thống',
            self::HR_ADMIN => $locale === 'en' ? 'HR Administrator' : 'Chuyên viên HR Cấp cao',
            self::ACCOUNTANT => $locale === 'en' ? 'Payroll Accountant' : 'Kế toán Tiền lương',
            self::DEPARTMENT_MANAGER => $locale === 'en' ? 'Department Manager' : 'Trưởng Bộ phận',
            self::EMPLOYEE => $locale === 'en' ? 'Regular Employee' : 'Nhân viên',
        };
    }

    public function description(string $locale = 'vi'): string
    {
        return match ($this) {
            self::SUPER_ADMIN => $locale === 'en' ? 'Full system control & licensing' : 'Toàn quyền cấu hình, tài khoản & bản quyền',
            self::HR_ADMIN => $locale === 'en' ? 'HR profiles, org chart, policies & compliance' : 'Hồ sơ nhân sự, quy chế & giám sát tuân thủ BLLĐ',
            self::ACCOUNTANT => $locale === 'en' ? 'Payroll calculation, PIT tax & insurance' : 'Tính toán tiền lương, thuế TNCN & bảo hiểm',
            self::DEPARTMENT_MANAGER => $locale === 'en' ? 'Approve requests, shift rostering & attendance' : 'Phê duyệt đơn từ, phân ca & chấm công bộ phận',
            self::EMPLOYEE => $locale === 'en' ? 'Personal attendance, leaves, OT & policies' : 'Chấm công cá nhân, viết đơn từ & xem quy chế',
        };
    }

    /**
     * Danh sách các module mà vai trò được phép truy cập
     */
    public function allowedModules(): array
    {
        return match ($this) {
            self::SUPER_ADMIN, self::HR_ADMIN => [
                'dashboard',
                'core_hr',
                'employee_children',
                'policy',
                'attendance',
                'rostering',
                'leaves',
                'workflow',
                'payroll',
                'compliance',
                'licensing',
            ],
            self::ACCOUNTANT => [
                'dashboard',
                'payroll',
                'employee_children',
                'policy',
                'leaves',
                'workflow',
            ],
            self::DEPARTMENT_MANAGER => [
                'dashboard',
                'rostering',
                'workflow',
                'attendance',
                'leaves',
            ],
            self::EMPLOYEE => [
                'dashboard',
                'attendance',
                'leaves',
                'policy',
            ],
        };
    }
}
