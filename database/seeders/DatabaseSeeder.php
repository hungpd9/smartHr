<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Tenant
        $tenantId = DB::table('tenants')->insertGetId([
            'code' => 'SMARTHR',
            'name' => 'Tập đoàn Công nghệ & Bán lẻ Smart-HR',
            'domain' => 'hrm.smarthr.vn',
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. Branches
        $branchHnId = DB::table('branches')->insertGetId([
            'tenant_id' => $tenantId,
            'code' => 'HN_HQ',
            'name' => 'Hội sở chính Hà Nội',
            'address' => 'Tòa nhà Smart-HR Tower, Cầu Giấy, Hà Nội',
            'is_remote_or_island' => false,
            'secret_key' => bin2hex(random_bytes(16)),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $branchPqId = DB::table('branches')->insertGetId([
            'tenant_id' => $tenantId,
            'code' => 'PQ_STORE',
            'name' => 'Trung tâm Phân phối & Cửa hàng Huyện đảo Phú Quốc',
            'address' => 'Đường Trần Hưng Đạo, Dương Đông, TP. Phú Quốc',
            'is_remote_or_island' => true, // Vùng biên giới / hải đảo áp dụng Đ.137 Khoản 1 Điểm a
            'secret_key' => bin2hex(random_bytes(16)),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. Departments
        $deptTechId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchHnId,
            'code' => 'TECH_DEV',
            'parent_id' => null,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $deptSalesId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchPqId,
            'code' => 'SALES_RETAIL',
            'parent_id' => null,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 4. Department Versions (Effective Dating)
        DB::table('department_versions')->insert([
            [
                'department_id' => $deptTechId,
                'name' => 'Khối Công nghệ & Phần mềm',
                'mission_function' => 'Nghiên cứu, phát triển và vận hành hệ sinh thái nền tảng số Smart-HR',
                'manager_id' => null,
                'effective_date' => Carbon::create(2025, 1, 1),
                'end_date' => null,
                'change_reason' => 'Quyết định thành lập và bổ sung chức năng nhiệm vụ mới',
                'created_at' => $now,
            ],
            [
                'department_id' => $deptSalesId,
                'name' => 'Phòng Kinh doanh & Bán lẻ Phú Quốc',
                'mission_function' => 'Quản lý chuỗi bán lẻ và phân phối khu vực hải đảo',
                'manager_id' => null,
                'effective_date' => Carbon::create(2025, 6, 1),
                'end_date' => null,
                'change_reason' => 'Mở rộng thị trường miền Nam',
                'created_at' => $now,
            ]
        ]);

        // 5. Positions
        $posCtoId = DB::table('positions')->insertGetId([
            'tenant_id' => $tenantId,
            'code' => 'CTO',
            'name' => 'Giám đốc Công nghệ',
            'level' => 5,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $posDevLeadId = DB::table('positions')->insertGetId([
            'tenant_id' => $tenantId,
            'code' => 'DEV_LEAD',
            'name' => 'Trưởng nhóm Phát triển Phần mềm',
            'level' => 3,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $posSalesStaffId = DB::table('positions')->insertGetId([
            'tenant_id' => $tenantId,
            'code' => 'SALES_SPECIALIST',
            'name' => 'Chuyên viên Bán lẻ',
            'level' => 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 6. Employees
        $emp1Id = DB::table('employees')->insertGetId([
            'tenant_id' => $tenantId,
            'user_code' => 'EMP001',
            'full_name' => 'Nguyễn Văn An',
            'gender' => 'MALE',
            'birth_date' => '1988-05-12',
            'email' => 'an.nguyen@smarthr.vn',
            'phone' => '0901234567',
            'encrypted_citizen_id' => 'ENC_001088001234',
            'join_date' => '2024-01-15',
            'is_pregnant' => false,
            'pregnancy_start_date' => null,
            'child_birth_date' => null,
            'status' => 'OFFICIAL',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Lao động nuôi con 8 tháng tuổi (Thuộc Điều 137 Khoản 1 Điểm b)
        $emp2Id = DB::table('employees')->insertGetId([
            'tenant_id' => $tenantId,
            'user_code' => 'EMP002',
            'full_name' => 'Trần Thị Mai',
            'gender' => 'FEMALE',
            'birth_date' => '1995-10-20',
            'email' => 'mai.tran@smarthr.vn',
            'phone' => '0912345678',
            'encrypted_citizen_id' => 'ENC_001195005678',
            'join_date' => '2024-06-01',
            'is_pregnant' => false,
            'pregnancy_start_date' => null,
            'child_birth_date' => Carbon::now()->subMonths(8)->format('Y-m-d'), // Con 8 tháng tuổi
            'status' => 'OFFICIAL',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Lao động mang thai tháng thứ 6 tại chi nhánh Hải đảo (Thuộc Điều 137 Khoản 1 Điểm a)
        $emp3Id = DB::table('employees')->insertGetId([
            'tenant_id' => $tenantId,
            'user_code' => 'EMP003',
            'full_name' => 'Lê Thị Hoa',
            'gender' => 'FEMALE',
            'birth_date' => '1998-03-15',
            'email' => 'hoa.le@smarthr.vn',
            'phone' => '0987654321',
            'encrypted_citizen_id' => 'ENC_001198009876',
            'join_date' => '2025-02-01',
            'is_pregnant' => true,
            'pregnancy_start_date' => Carbon::now()->subMonths(6)->format('Y-m-d'), // Mang thai tháng thứ 6
            'child_birth_date' => null,
            'status' => 'OFFICIAL',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Cập nhật Trưởng phòng cho department_versions
        DB::table('department_versions')->where('department_id', $deptTechId)->update(['manager_id' => $emp1Id]);

        // 7. Policy Versions (Zero-Hardcode)
        $policyVerId = DB::table('policy_versions')->insertGetId([
            'tenant_id' => $tenantId,
            'code' => 'POLICY_2026_V1',
            'name' => 'Quy chế Quản trị Nhân sự & Tiền lương 2026 (Tuân thủ BLLĐ 2019)',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 8. Attendance Policy Configs
        DB::table('attendance_policy_configs')->insert([
            'policy_version_id' => $policyVerId,
            'grace_period_late_minutes' => 5, // Miễn trừ 5 phút
            'grace_period_early_minutes' => 5,
            'leave_unit_hours' => 4.00,
            'early_leave_unit_hours' => 0.25,
            'min_rest_hours_between_shifts' => 12, // BLLĐ Điều 110
            'min_weekly_rest_hours' => 24, // BLLĐ Điều 111
            'max_weekly_standard_hours' => 48, // BLLĐ Điều 105
            'ot_to_rostered_rate' => 1.00,
            'compensation_rate' => 8.00,
            'penalty_tiers_json' => json_encode([
                ['min' => 1, 'max' => 15, 'fine' => 50000],
                ['min' => 16, 'max' => 30, 'fine' => 100000],
                ['min' => 31, 'max' => 60, 'fine' => 200000],
            ]),
            'frequency_penalty_json' => json_encode([
                ['count' => 1, 'multiplier' => 1.0],
                ['count' => 3, 'multiplier' => 1.5],
                ['count' => 5, 'multiplier' => 2.0],
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 9. Child Welfare Policies
        DB::table('child_welfare_policies')->insert([
            [
                'policy_version_id' => $policyVerId,
                'name' => 'Chế độ Lao động Nuôi con Dưới 12 Tháng (Bắt buộc theo Luật)',
                'min_age_months' => 0,
                'max_age_months' => 12,
                'daily_exemption_minutes' => 60, // 60 phút có hưởng lương
                'pay_type' => 'PAID',
                'prohibit_night_shift' => true,
                'prohibit_overtime' => true,
                'is_statutory' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'policy_version_id' => $policyVerId,
                'name' => 'Phúc lợi Riêng: Hỗ trợ Con từ 1 - 6 tuổi',
                'min_age_months' => 13,
                'max_age_months' => 72,
                'daily_exemption_minutes' => 30, // Về sớm 30 phút
                'pay_type' => 'PAID',
                'prohibit_night_shift' => false,
                'prohibit_overtime' => false,
                'is_statutory' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ]);

        // 10. PIT Tax Brackets (Biểu thuế TNCN 5 bậc hiệu lực từ 2026)
        DB::table('pit_tax_brackets')->insert([
            [
                'policy_version_id' => $policyVerId,
                'tier' => 1,
                'min_income' => 0.00,
                'max_income' => 10000000.00,
                'tax_rate' => 5.00,
                'quick_subtraction' => 0.00,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'policy_version_id' => $policyVerId,
                'tier' => 2,
                'min_income' => 10000000.01,
                'max_income' => 30000000.00,
                'tax_rate' => 10.00,
                'quick_subtraction' => 500000.00,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'policy_version_id' => $policyVerId,
                'tier' => 3,
                'min_income' => 30000000.01,
                'max_income' => 60000000.00,
                'tax_rate' => 20.00,
                'quick_subtraction' => 3500000.00,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'policy_version_id' => $policyVerId,
                'tier' => 4,
                'min_income' => 60000000.01,
                'max_income' => 100000000.00,
                'tax_rate' => 30.00,
                'quick_subtraction' => 9500000.00,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'policy_version_id' => $policyVerId,
                'tier' => 5,
                'min_income' => 100000000.01,
                'max_income' => null,
                'tax_rate' => 35.00,
                'quick_subtraction' => 14500000.00,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 11. Shifts
        DB::table('shifts')->insert([
            [
                'tenant_id' => $tenantId,
                'code' => 'HC_STANDARD',
                'name' => 'Ca Hành chính (08:00 - 17:00)',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_minutes' => 60,
                'is_night_shift' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'code' => 'SHIFT_MORNING',
                'name' => 'Ca Sáng (06:00 - 14:00)',
                'start_time' => '06:00:00',
                'end_time' => '14:00:00',
                'break_minutes' => 30,
                'is_night_shift' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'code' => 'SHIFT_AFTERNOON',
                'name' => 'Ca Chiều (14:00 - 22:00)',
                'start_time' => '14:00:00',
                'end_time' => '22:00:00',
                'break_minutes' => 30,
                'is_night_shift' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'code' => 'SHIFT_NIGHT',
                'name' => 'Ca Đêm (22:00 - 06:00)',
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'break_minutes' => 45,
                'is_night_shift' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
