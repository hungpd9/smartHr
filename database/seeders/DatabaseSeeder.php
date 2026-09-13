<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        // 2. Branches (Chi nhánh)
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

        $branchDnId = DB::table('branches')->insertGetId([
            'tenant_id' => $tenantId,
            'code' => 'DN_HUB',
            'name' => 'Trung tâm R&D Công nghệ Đà Nẵng',
            'address' => 'Công viên Phần mềm Đà Nẵng, Hải Châu, TP. Đà Nẵng',
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

        // 3. Departments (Cơ cấu Đa tầng: Khối Level 1 -> Phòng ban Level 2 -> Team Level 3)
        // Khối 1: Khối Công nghệ & Phần mềm (Hà Nội)
        $deptTechId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchHnId,
            'code' => 'TECH_DEV',
            'parent_id' => null,
            'level' => 1,
            'order' => 1,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Phòng Dev 1 (Hà Nội) - Level 2
        $deptDev1Id = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchHnId,
            'code' => 'DEV_DEPT_1',
            'parent_id' => $deptTechId,
            'level' => 2,
            'order' => 1,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Teams thuộc Dev 1 - Level 3
        $teamBackendId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchHnId,
            'code' => 'TEAM_BACKEND',
            'parent_id' => $deptDev1Id,
            'level' => 3,
            'order' => 1,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $teamFrontendId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchHnId,
            'code' => 'TEAM_FRONTEND',
            'parent_id' => $deptDev1Id,
            'level' => 3,
            'order' => 2,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Phòng Dev 2 (Đà Nẵng) - Level 2
        $deptDev2Id = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchDnId,
            'code' => 'DEV_DEPT_2',
            'parent_id' => $deptTechId,
            'level' => 2,
            'order' => 2,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Teams thuộc Dev 2 (Đà Nẵng) - Level 3
        $teamCloudId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchDnId,
            'code' => 'TEAM_CLOUD_DEVOPS',
            'parent_id' => $deptDev2Id,
            'level' => 3,
            'order' => 1,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $teamAiId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchDnId,
            'code' => 'TEAM_AI_DATA',
            'parent_id' => $deptDev2Id,
            'level' => 3,
            'order' => 2,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Khối 2: Khối Kinh doanh & Bán lẻ - Level 1
        $deptSalesId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchPqId,
            'code' => 'SALES_RETAIL',
            'parent_id' => null,
            'level' => 1,
            'order' => 2,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Cửa hàng Phú Quốc - Level 2
        $deptStorePqId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchPqId,
            'code' => 'STORE_PQ_01',
            'parent_id' => $deptSalesId,
            'level' => 2,
            'order' => 1,
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 4. Department Versions (Effective Dating)
        DB::table('department_versions')->insert([
            [
                'department_id' => $deptTechId,
                'name' => 'Khối Công nghệ & Phần mềm',
                'mission_function' => 'Nghiên cứu, phát triển và vận hành hệ sinh thái nền tảng số Smart-HR toàn quốc',
                'manager_id' => null,
                'effective_date' => Carbon::create(2025, 1, 1),
                'end_date' => null,
                'change_reason' => 'Quyết định thành lập khối công nghệ mũi nhọn',
                'created_at' => $now,
            ],
            [
                'department_id' => $deptDev1Id,
                'name' => 'Phòng Phát triển Phần mềm Số 1 (Hà Nội)',
                'mission_function' => 'Chịu trách nhiệm kiến trúc Core HR và hệ thống chấm công trực tuyến',
                'manager_id' => null,
                'effective_date' => Carbon::create(2025, 1, 1),
                'end_date' => null,
                'change_reason' => 'Kiện toàn phòng phát triển phần mềm hội sở',
                'created_at' => $now,
            ],
            [
                'department_id' => $teamBackendId,
                'name' => 'Team Backend & Core Engine',
                'mission_function' => 'Phát triển backend Laravel 13, Policy Engine và kết nối thiết bị IoT',
                'manager_id' => null,
                'effective_date' => Carbon::create(2025, 1, 1),
                'end_date' => null,
                'change_reason' => 'Thành lập nhóm cốt lõi',
                'created_at' => $now,
            ],
            [
                'department_id' => $teamFrontendId,
                'name' => 'Team Frontend & Mobile App',
                'mission_function' => 'Xây dựng giao diện Vue 3 / Element Plus và ứng dụng di động',
                'manager_id' => null,
                'effective_date' => Carbon::create(2025, 1, 1),
                'end_date' => null,
                'change_reason' => 'Thành lập nhóm giao diện',
                'created_at' => $now,
            ],
            [
                'department_id' => $deptDev2Id,
                'name' => 'Phòng Phát triển Phần mềm Số 2 (Đà Nẵng)',
                'mission_function' => 'Trung tâm nghiên cứu Cloud, DevOps và trí tuệ nhân tạo nhân sự',
                'manager_id' => null,
                'effective_date' => Carbon::create(2025, 3, 1),
                'end_date' => null,
                'change_reason' => 'Thành lập chi nhánh trung tâm phần mềm miền Trung',
                'created_at' => $now,
            ],
            [
                'department_id' => $teamCloudId,
                'name' => 'Team Cloud & DevOps Infrastructure',
                'mission_function' => 'Vận hành hạ tầng Redis, MySQL cluster và Kubernetes',
                'manager_id' => null,
                'effective_date' => Carbon::create(2025, 3, 1),
                'end_date' => null,
                'change_reason' => 'Thành lập nhóm hạ tầng',
                'created_at' => $now,
            ],
            [
                'department_id' => $teamAiId,
                'name' => 'Team AI & Big Data Analytics',
                'mission_function' => 'Phát triển thuật toán phân ca tối ưu và phát hiện gian lận chấm công',
                'manager_id' => null,
                'effective_date' => Carbon::create(2025, 3, 1),
                'end_date' => null,
                'change_reason' => 'Thành lập nhóm AI',
                'created_at' => $now,
            ],
            [
                'department_id' => $deptSalesId,
                'name' => 'Khối Kinh doanh & Chuỗi Bán lẻ',
                'mission_function' => 'Quản lý vận hành hệ thống chuỗi bán lẻ',
                'manager_id' => null,
                'effective_date' => Carbon::create(2025, 6, 1),
                'end_date' => null,
                'change_reason' => 'Thành lập khối kinh doanh',
                'created_at' => $now,
            ],
            [
                'department_id' => $deptStorePqId,
                'name' => 'Cửa hàng Bán lẻ & Phân phối Phú Quốc',
                'mission_function' => 'Quản lý chuỗi bán lẻ và phân phối khu vực huyện đảo Phú Quốc',
                'manager_id' => null,
                'effective_date' => Carbon::create(2025, 6, 1),
                'end_date' => null,
                'change_reason' => 'Mở rộng thị trường miền Nam hải đảo',
                'created_at' => $now,
            ]
        ]);

        // 5. Positions (Chức vụ gắn liền phòng ban kèm phụ cấp chức vụ)
        $posCtoId = DB::table('positions')->insertGetId([
            'tenant_id' => $tenantId,
            'department_id' => $deptTechId,
            'code' => 'CTO',
            'name' => 'Giám đốc Công nghệ',
            'level' => 5,
            'allowance_amount' => 5000000.00,
            'bonus_one_time' => 10000000.00,
            'description' => 'Chịu trách nhiệm toàn diện về chiến lược công nghệ và hạ tầng toàn tập đoàn.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $posDev1ManagerId = DB::table('positions')->insertGetId([
            'tenant_id' => $tenantId,
            'department_id' => $deptDev1Id,
            'code' => 'MGR_DEV1',
            'name' => 'Trưởng phòng Phát triển Số 1',
            'level' => 4,
            'allowance_amount' => 3000000.00,
            'bonus_one_time' => 5000000.00,
            'description' => 'Điều hành và quản lý nhân sự phòng Dev 1 tại Hà Nội.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $posDev2ManagerId = DB::table('positions')->insertGetId([
            'tenant_id' => $tenantId,
            'department_id' => $deptDev2Id,
            'code' => 'MGR_DEV2',
            'name' => 'Trưởng phòng Phát triển Số 2 (Đà Nẵng)',
            'level' => 4,
            'allowance_amount' => 3000000.00,
            'bonus_one_time' => 5000000.00,
            'description' => 'Chỉ đạo trực tiếp các dự án R&D Cloud & AI tại văn phòng Đà Nẵng.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $posDevLeadId = DB::table('positions')->insertGetId([
            'tenant_id' => $tenantId,
            'department_id' => $teamBackendId,
            'code' => 'DEV_LEAD',
            'name' => 'Trưởng nhóm Kỹ thuật (Tech Lead)',
            'level' => 3,
            'allowance_amount' => 1500000.00,
            'bonus_one_time' => 2000000.00,
            'description' => 'Chịu trách nhiệm kiến trúc kỹ thuật và phân bổ task cho các kỹ sư lập trình.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $posSeniorDevId = DB::table('positions')->insertGetId([
            'tenant_id' => $tenantId,
            'department_id' => null, // Vị trí dùng chung
            'code' => 'SENIOR_DEV',
            'name' => 'Kỹ sư Phần mềm Cao cấp',
            'level' => 2,
            'allowance_amount' => 500000.00,
            'bonus_one_time' => 0.00,
            'description' => 'Lập trình viên giàu kinh nghiệm, phụ trách các module phức tạp.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $posSalesStaffId = DB::table('positions')->insertGetId([
            'tenant_id' => $tenantId,
            'department_id' => $deptStorePqId,
            'code' => 'SALES_SPECIALIST',
            'name' => 'Chuyên viên Bán lẻ & Quản trị Kho',
            'level' => 1,
            'allowance_amount' => 0.00,
            'bonus_one_time' => 0.00,
            'description' => 'Phục vụ khách hàng tại showroom và điều phối kho tại Phú Quốc.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 6. Employees (Hồ sơ nhân sự toàn diện)
        // EMP001: Nguyễn Văn An - Giám đốc Công nghệ kiêm Trưởng phòng Dev 1
        $emp1Id = DB::table('employees')->insertGetId([
            'tenant_id' => $tenantId,
            'user_code' => 'EMP001',
            'attendance_code' => '1001',
            'full_name' => 'Nguyễn Văn An',
            'gender' => 'MALE',
            'birth_date' => '1988-05-12',
            'email' => 'an.nguyen@smarthr.vn',
            'phone' => '0901234567',
            'citizen_id' => '001088001234',
            'encrypted_citizen_id' => 'ENC_001088001234',
            'hometown' => 'Nam Định',
            'permanent_address' => 'Số 18 Duy Tân, Dịch Vọng Hậu, Cầu Giấy, Hà Nội',
            'temporary_address' => 'Chung cư The Zei, Lê Đức Thọ, Mỹ Đình 2, Nam Từ Liêm, Hà Nội',
            'join_date' => '2024-01-15',
            'probation_end_date' => '2024-03-15',
            'is_pregnant' => false,
            'pregnancy_start_date' => null,
            'pregnancy_weeks_at_start' => null,
            'expected_due_date' => null,
            'children_count' => 1,
            'tax_dependents_count' => 1,
            'status' => 'OFFICIAL',
            'resigned_date' => null,
            'resignation_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // EMP002: Trần Thị Mai - Tech Lead Backend (Có con 8 tháng tuổi + con 5 tuổi)
        $emp2Id = DB::table('employees')->insertGetId([
            'tenant_id' => $tenantId,
            'user_code' => 'EMP002',
            'attendance_code' => '1002',
            'full_name' => 'Trần Thị Mai',
            'gender' => 'FEMALE',
            'birth_date' => '1995-10-20',
            'email' => 'mai.tran@smarthr.vn',
            'phone' => '0912345678',
            'citizen_id' => '001195005678',
            'encrypted_citizen_id' => 'ENC_001195005678',
            'hometown' => 'Bắc Ninh',
            'permanent_address' => 'Phố Mới, Tiên Du, Bắc Ninh',
            'temporary_address' => 'Khu đô thị Vinhomes Ocean Park, Gia Lâm, Hà Nội',
            'join_date' => '2024-06-01',
            'probation_end_date' => '2024-08-01',
            'is_pregnant' => false,
            'pregnancy_start_date' => null,
            'pregnancy_weeks_at_start' => null,
            'expected_due_date' => null,
            'children_count' => 2,
            'tax_dependents_count' => 2,
            'status' => 'OFFICIAL',
            'resigned_date' => null,
            'resignation_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // EMP003: Lê Thị Hoa - Mang thai tuần 26 (tháng thứ 6) tại đảo Phú Quốc
        $emp3Id = DB::table('employees')->insertGetId([
            'tenant_id' => $tenantId,
            'user_code' => 'EMP003',
            'attendance_code' => '1003',
            'full_name' => 'Lê Thị Hoa',
            'gender' => 'FEMALE',
            'birth_date' => '1998-03-15',
            'email' => 'hoa.le@smarthr.vn',
            'phone' => '0987654321',
            'citizen_id' => '001198009876',
            'encrypted_citizen_id' => 'ENC_001198009876',
            'hometown' => 'Kiên Giang',
            'permanent_address' => 'Thị trấn Dương Đông, TP. Phú Quốc, Kiên Giang',
            'temporary_address' => 'Đường Trần Hưng Đạo, Khu phố 7, Dương Đông, Phú Quốc',
            'join_date' => '2025-02-01',
            'probation_end_date' => '2025-04-01',
            'is_pregnant' => true,
            'pregnancy_start_date' => Carbon::now()->subMonths(6)->format('Y-m-d'),
            'pregnancy_weeks_at_start' => 26,
            'expected_due_date' => Carbon::now()->addMonths(3)->format('Y-m-d'),
            'children_count' => 0,
            'tax_dependents_count' => 0,
            'status' => 'OFFICIAL',
            'resigned_date' => null,
            'resignation_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // EMP004: Đặng Quốc Hưng - Trưởng phòng Dev 2 tại Đà Nẵng
        $emp4Id = DB::table('employees')->insertGetId([
            'tenant_id' => $tenantId,
            'user_code' => 'EMP004',
            'attendance_code' => '1004',
            'full_name' => 'Đặng Quốc Hưng',
            'gender' => 'MALE',
            'birth_date' => '1990-11-08',
            'email' => 'hung.dang@smarthr.vn',
            'phone' => '0905123456',
            'citizen_id' => '048090002345',
            'encrypted_citizen_id' => 'ENC_048090002345',
            'hometown' => 'Đà Nẵng',
            'permanent_address' => '120 Bạch Đằng, Hải Châu, TP. Đà Nẵng',
            'temporary_address' => 'Chung cư Monarchy, An Hải Tây, Sơn Trà, TP. Đà Nẵng',
            'join_date' => '2024-03-01',
            'probation_end_date' => '2024-05-01',
            'is_pregnant' => false,
            'pregnancy_start_date' => null,
            'pregnancy_weeks_at_start' => null,
            'expected_due_date' => null,
            'children_count' => 1,
            'tax_dependents_count' => 1,
            'status' => 'OFFICIAL',
            'resigned_date' => null,
            'resignation_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // EMP005: Vũ Minh Thư - Kỹ sư AI & Data cao cấp
        $emp5Id = DB::table('employees')->insertGetId([
            'tenant_id' => $tenantId,
            'user_code' => 'EMP005',
            'attendance_code' => '1005',
            'full_name' => 'Vũ Minh Thư',
            'gender' => 'FEMALE',
            'birth_date' => '1996-08-14',
            'email' => 'thu.vu@smarthr.vn',
            'phone' => '0935678901',
            'citizen_id' => '048196003456',
            'encrypted_citizen_id' => 'ENC_048196003456',
            'hometown' => 'Quảng Nam',
            'permanent_address' => 'Điện Bàn, Quảng Nam',
            'temporary_address' => 'Đường 2/9, Hải Châu, TP. Đà Nẵng',
            'join_date' => '2025-01-10',
            'probation_end_date' => '2025-03-10',
            'is_pregnant' => false,
            'pregnancy_start_date' => null,
            'pregnancy_weeks_at_start' => null,
            'expected_due_date' => null,
            'children_count' => 1,
            'tax_dependents_count' => 1,
            'status' => 'OFFICIAL',
            'resigned_date' => null,
            'resignation_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // EMP006: Hoàng Đức Nam - Nhân viên đã thôi việc (Offboarding)
        $emp6Id = DB::table('employees')->insertGetId([
            'tenant_id' => $tenantId,
            'user_code' => 'EMP006',
            'attendance_code' => '1006',
            'full_name' => 'Hoàng Đức Nam',
            'gender' => 'MALE',
            'birth_date' => '1992-04-25',
            'email' => 'nam.hoang@smarthr.vn',
            'phone' => '0978901234',
            'citizen_id' => '001092004567',
            'encrypted_citizen_id' => 'ENC_001092004567',
            'hometown' => 'Hải Phòng',
            'permanent_address' => 'Ngô Quyền, TP. Hải Phòng',
            'temporary_address' => 'Hoàng Mai, Hà Nội',
            'join_date' => '2024-02-01',
            'probation_end_date' => '2024-04-01',
            'is_pregnant' => false,
            'pregnancy_start_date' => null,
            'pregnancy_weeks_at_start' => null,
            'expected_due_date' => null,
            'children_count' => 0,
            'tax_dependents_count' => 0,
            'status' => 'TERMINATED',
            'resigned_date' => '2026-08-31',
            'resignation_reason' => 'Chuyển định cư nước ngoài cùng gia đình',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Cập nhật Trưởng phòng cho department_versions
        DB::table('department_versions')->where('department_id', $deptTechId)->update(['manager_id' => $emp1Id]);
        DB::table('department_versions')->where('department_id', $deptDev1Id)->update(['manager_id' => $emp1Id]);
        DB::table('department_versions')->where('department_id', $teamBackendId)->update(['manager_id' => $emp2Id]);
        DB::table('department_versions')->where('department_id', $deptDev2Id)->update(['manager_id' => $emp4Id]);

        // 7. Employee Children (Quản lý Con nhỏ & Phúc lợi Gia đình)
        DB::table('employee_children')->insert([
            // Bé 1 của Mai: 8 tháng tuổi -> Hưởng Điều 137 BLLĐ (<12 tháng: 60p có lương, cấm đêm/OT) + Trợ cấp 300k
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp2Id,
                'full_name' => 'Trần Bảo An',
                'gender' => 'MALE',
                'birth_date' => Carbon::now()->subMonths(8)->format('Y-m-d'),
                'birth_certificate_number' => 'GKS-HN-2026-00128',
                'is_tax_dependent' => true,
                'tax_dependent_code' => 'MST_DEP_88301',
                'tax_dependent_start_date' => '2026-01-01',
                'tax_dependent_end_date' => null,
                'has_monthly_allowance' => true,
                'monthly_allowance_amount' => 300000.00,
                'notes' => 'Bé đang bú mẹ hoàn toàn, được hưởng trọn vẹn chế độ Điều 137 BLLĐ',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // Bé 2 của Mai: 5 tuổi (60 tháng) -> Hưởng chế độ con 1-11 tuổi (30p có lương) + Trợ cấp 300k
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp2Id,
                'full_name' => 'Trần Gia Hân',
                'gender' => 'FEMALE',
                'birth_date' => Carbon::now()->subYears(5)->format('Y-m-d'),
                'birth_certificate_number' => 'GKS-HN-2021-00892',
                'is_tax_dependent' => true,
                'tax_dependent_code' => 'MST_DEP_88302',
                'tax_dependent_start_date' => '2024-06-01',
                'tax_dependent_end_date' => null,
                'has_monthly_allowance' => true,
                'monthly_allowance_amount' => 300000.00,
                'notes' => 'Đang học trường mầm non Hoa Sen',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // Con của Nguyễn Văn An: 3 tuổi (36 tháng)
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp1Id,
                'full_name' => 'Nguyễn Tuệ Minh',
                'gender' => 'FEMALE',
                'birth_date' => Carbon::now()->subYears(3)->format('Y-m-d'),
                'birth_certificate_number' => 'GKS-ND-2023-01024',
                'is_tax_dependent' => true,
                'tax_dependent_code' => 'MST_DEP_77201',
                'tax_dependent_start_date' => '2024-01-15',
                'tax_dependent_end_date' => null,
                'has_monthly_allowance' => true,
                'monthly_allowance_amount' => 300000.00,
                'notes' => 'Con gái đầu lòng của CTO',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // Con của Đặng Quốc Hưng: 2 tuổi
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp4Id,
                'full_name' => 'Đặng Minh Khôi',
                'gender' => 'MALE',
                'birth_date' => Carbon::now()->subYears(2)->format('Y-m-d'),
                'birth_certificate_number' => 'GKS-DN-2024-00456',
                'is_tax_dependent' => true,
                'tax_dependent_code' => 'MST_DEP_48201',
                'tax_dependent_start_date' => '2024-03-01',
                'tax_dependent_end_date' => null,
                'has_monthly_allowance' => true,
                'monthly_allowance_amount' => 300000.00,
                'notes' => 'Đang đăng ký giảm trừ gia cảnh thuế TNCN',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // Con của Vũ Minh Thư: 4 tuổi
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp5Id,
                'full_name' => 'Phạm Thiên Ân',
                'gender' => 'MALE',
                'birth_date' => Carbon::now()->subYears(4)->format('Y-m-d'),
                'birth_certificate_number' => 'GKS-QN-2022-00789',
                'is_tax_dependent' => true,
                'tax_dependent_code' => 'MST_DEP_92101',
                'tax_dependent_start_date' => '2025-01-10',
                'tax_dependent_end_date' => null,
                'has_monthly_allowance' => true,
                'monthly_allowance_amount' => 300000.00,
                'notes' => 'Hưởng chế độ phụ cấp con nhỏ hàng tháng',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 8. Employee Certificates (Bằng cấp & Chứng chỉ Chuyên môn)
        DB::table('employee_certificates')->insert([
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp1Id,
                'name' => 'AWS Certified Solutions Architect - Professional',
                'certificate_type' => 'PROFESSIONAL_CERTIFICATE',
                'field_of_study' => 'Cloud Computing & System Architecture',
                'issued_by' => 'Amazon Web Services (AWS)',
                'issue_date' => '2023-08-15',
                'expiry_date' => '2026-08-15',
                'monthly_allowance' => 1500000.00, // Phụ cấp chứng chỉ 1.5tr/tháng
                'one_time_reward' => 0.00,
                'file_url' => '/uploads/certificates/aws-sap-an.pdf',
                'is_verified' => true,
                'verified_by' => null,
                'verified_at' => '2024-01-15 09:00:00',
                'notes' => 'Chứng chỉ kiến trúc sư đám mây chuyên nghiệp cấp độ cao nhất',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp2Id,
                'name' => 'Thạc sĩ Khoa học Máy tính (Master of Computer Science)',
                'certificate_type' => 'DEGREE',
                'field_of_study' => 'Khoa học Máy tính & Kỹ thuật Phần mềm',
                'issued_by' => 'Đại học Bách Khoa Hà Nội',
                'issue_date' => '2022-11-20',
                'expiry_date' => null,
                'monthly_allowance' => 0.00,
                'one_time_reward' => 5000000.00, // Thưởng 1 lần khi gia nhập có bằng Thạc sĩ
                'file_url' => '/uploads/certificates/master-cs-mai.pdf',
                'is_verified' => true,
                'verified_by' => $emp1Id,
                'verified_at' => '2024-06-01 10:00:00',
                'notes' => 'Tốt nghiệp thủ khoa đầu ra ngành CS',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp4Id,
                'name' => 'Project Management Professional (PMP)',
                'certificate_type' => 'PROFESSIONAL_CERTIFICATE',
                'field_of_study' => 'Quản trị Dự án Phần mềm',
                'issued_by' => 'Project Management Institute (PMI)',
                'issue_date' => '2023-05-10',
                'expiry_date' => '2026-05-10',
                'monthly_allowance' => 2000000.00, // Phụ cấp PMP 2tr/tháng
                'one_time_reward' => 0.00,
                'file_url' => '/uploads/certificates/pmp-hung.pdf',
                'is_verified' => true,
                'verified_by' => $emp1Id,
                'verified_at' => '2024-03-01 08:30:00',
                'notes' => 'Đã thẩm định và xác thực trên cổng PMI registry',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp5Id,
                'name' => 'IELTS Academic 8.0',
                'certificate_type' => 'LANGUAGE',
                'field_of_study' => 'Tiếng Anh Học thuật',
                'issued_by' => 'British Council Vietnam',
                'issue_date' => '2024-09-12',
                'expiry_date' => '2026-09-12',
                'monthly_allowance' => 1000000.00, // Phụ cấp ngoại ngữ 1tr/tháng
                'one_time_reward' => 0.00,
                'file_url' => '/uploads/certificates/ielts-thu.pdf',
                'is_verified' => true,
                'verified_by' => $emp1Id,
                'verified_at' => '2025-01-10 14:00:00',
                'notes' => 'Phục vụ giao tiếp với các đối tác nước ngoài và đọc tài liệu AI',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 9. Career Timelines (Effective Dating)
        DB::table('employee_career_timelines')->insert([
            [
                'employee_id' => $emp1Id,
                'event_type' => 'ONBOARDING',
                'department_id' => $deptTechId,
                'position_id' => $posCtoId,
                'job_title' => 'Giám đốc Công nghệ',
                'manager_id' => null,
                'effective_date' => '2024-01-15',
                'end_date' => null,
                'decision_number' => 'QĐ-01/2024/HR',
                'decision_date' => '2024-01-10',
                'notes' => 'Tiếp nhận nhân sự và bổ nhiệm vị trí Giám đốc Công nghệ',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $emp2Id,
                'event_type' => 'ONBOARDING',
                'department_id' => $teamBackendId,
                'position_id' => $posDevLeadId,
                'job_title' => 'Trưởng nhóm Phát triển Phần mềm',
                'manager_id' => $emp1Id,
                'effective_date' => '2024-06-01',
                'end_date' => null,
                'decision_number' => 'QĐ-15/2024/HR',
                'decision_date' => '2024-05-25',
                'notes' => 'Tiếp nhận nhân sự chính thức',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $emp3Id,
                'event_type' => 'ONBOARDING',
                'department_id' => $deptStorePqId,
                'position_id' => $posSalesStaffId,
                'job_title' => 'Chuyên viên Bán lẻ',
                'manager_id' => null,
                'effective_date' => '2025-02-01',
                'end_date' => null,
                'decision_number' => 'QĐ-08/2025/HR',
                'decision_date' => '2025-01-20',
                'notes' => 'Phân công công tác tại chi nhánh Phú Quốc',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $emp4Id,
                'event_type' => 'ONBOARDING',
                'department_id' => $deptDev2Id,
                'position_id' => $posDev2ManagerId,
                'job_title' => 'Trưởng phòng Phát triển Số 2',
                'manager_id' => $emp1Id,
                'effective_date' => '2024-03-01',
                'end_date' => null,
                'decision_number' => 'QĐ-10/2024/HR',
                'decision_date' => '2024-02-20',
                'notes' => 'Bổ nhiệm Trưởng phòng Dev 2 tại Đà Nẵng',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $emp5Id,
                'event_type' => 'ONBOARDING',
                'department_id' => $teamAiId,
                'position_id' => $posSeniorDevId,
                'job_title' => 'Kỹ sư AI & Data cao cấp',
                'manager_id' => $emp4Id,
                'effective_date' => '2025-01-10',
                'end_date' => null,
                'decision_number' => 'QĐ-02/2025/HR',
                'decision_date' => '2025-01-05',
                'notes' => 'Tuyển dụng chuyên gia AI gia nhập R&D Đà Nẵng',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // Timeline thôi việc của EMP006
            [
                'employee_id' => $emp6Id,
                'event_type' => 'ONBOARDING',
                'department_id' => $teamBackendId,
                'position_id' => $posSeniorDevId,
                'job_title' => 'Kỹ sư Phần mềm',
                'manager_id' => $emp2Id,
                'effective_date' => '2024-02-01',
                'end_date' => '2026-08-31',
                'decision_number' => 'QĐ-05/2024/HR',
                'decision_date' => '2024-01-28',
                'notes' => 'Gia nhập đội ngũ Backend',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $emp6Id,
                'event_type' => 'OFFBOARDING',
                'department_id' => $teamBackendId,
                'position_id' => $posSeniorDevId,
                'job_title' => 'Kỹ sư Phần mềm',
                'manager_id' => $emp2Id,
                'effective_date' => '2026-08-31',
                'end_date' => null,
                'decision_number' => 'QĐ-TV-2026-08',
                'decision_date' => '2026-08-15',
                'notes' => 'Hoàn tất bàn giao tài sản và nghỉ việc theo nguyện vọng cá nhân',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 10. Employee Contracts
        DB::table('employee_contracts')->insert([
            [
                'employee_id' => $emp1Id,
                'contract_number' => 'HDLD-2024-001',
                'contract_type' => 'INDEFINITE_TERM',
                'sign_date' => '2024-01-15',
                'start_date' => '2024-01-15',
                'end_date' => null,
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $emp2Id,
                'contract_number' => 'HDLD-2024-045',
                'contract_type' => 'DEFINITE_TERM',
                'sign_date' => '2024-06-01',
                'start_date' => '2024-06-01',
                'end_date' => '2027-05-31',
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $emp3Id,
                'contract_number' => 'HDLD-2025-089',
                'contract_type' => 'DEFINITE_TERM',
                'sign_date' => '2025-02-01',
                'start_date' => '2025-02-01',
                'end_date' => '2026-01-31',
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $emp4Id,
                'contract_number' => 'HDLD-2024-023',
                'contract_type' => 'INDEFINITE_TERM',
                'sign_date' => '2024-03-01',
                'start_date' => '2024-03-01',
                'end_date' => null,
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'employee_id' => $emp5Id,
                'contract_number' => 'HDLD-2025-012',
                'contract_type' => 'DEFINITE_TERM',
                'sign_date' => '2025-01-10',
                'start_date' => '2025-01-10',
                'end_date' => '2028-01-09',
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 11. Policy Versions (Zero-Hardcode)
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

        // 12. Attendance Policy Configs
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

        // 12b. Payroll Policy Configs (Lương, Thuế & Bảo hiểm 2026)
        DB::table('payroll_policy_configs')->insert([
            'policy_version_id' => $policyVerId,
            'standard_working_days_mode' => 'FIXED',
            'fixed_standard_working_days' => 26.00,
            'standard_working_hours_per_day' => 8.00,
            'payment_day' => 10,
            'pay_early_on_holiday_weekend' => true,
            'personal_deduction' => 11000000.00,
            'dependent_deduction' => 4400000.00,
            'default_child_allowance' => 300000.00,
            'base_salary' => 2340000.00,
            'max_salary_bhxh' => 46800000.00,
            'max_salary_bhtn' => 99200000.00,
            'company_bhxh_rate' => 17.50,
            'company_bhyt_rate' => 3.00,
            'company_bhtn_rate' => 1.00,
            'employee_bhxh_rate' => 8.00,
            'employee_bhyt_rate' => 1.50,
            'employee_bhtn_rate' => 1.00,
            'probation_salary_percentage' => 85.00,
            'probation_pit_rate' => 10.00,
            'probation_pit_threshold' => 2000000.00,
            'ot_weekday_day_rate' => 1.50,
            'ot_weekday_night_rate' => 2.00,
            'ot_weekend_day_rate' => 2.00,
            'ot_weekend_night_rate' => 2.70,
            'ot_holiday_day_rate' => 3.00,
            'ot_holiday_night_rate' => 3.90,
            'ot_tax_exempt_daily_limit_percent' => 50,
            'ot_tax_exempt_monthly_limit_hours' => 40,
            'ot_tax_exempt_yearly_limit_hours' => 200,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 13. Child Welfare Policies
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
                'visibility' => 'VISIBLE',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'policy_version_id' => $policyVerId,
                'name' => 'Phúc lợi Riêng: Hỗ trợ Con từ 1 - 11 tuổi',
                'min_age_months' => 13,
                'max_age_months' => 132, // Đến hết 11 tuổi
                'daily_exemption_minutes' => 30, // Miễn trừ 30 phút có hưởng lương
                'pay_type' => 'PAID',
                'prohibit_night_shift' => false,
                'prohibit_overtime' => false,
                'is_statutory' => false,
                'is_active' => true,
                'visibility' => 'VISIBLE',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ]);

        // 13b. Welfare Schemes (Chế độ Phúc lợi Doanh nghiệp Đa năng - Quản lý Ẩn/Hiện VISIBLE/HIDDEN)
        DB::table('welfare_schemes')->insert([
            [
                'tenant_id' => $tenantId,
                'policy_version_id' => $policyVerId,
                'code' => 'SCHEME_CON_NHO_12T',
                'name' => 'Chế độ Lao động Nuôi con Dưới 12 Tháng (Điều 137 BLLĐ)',
                'category' => 'CHILD_CARE',
                'visibility' => 'VISIBLE',
                'is_statutory' => true,
                'daily_exemption_minutes' => 60,
                'pay_type' => 'PAID',
                'prohibit_night_shift' => true,
                'prohibit_overtime' => true,
                'additional_leave_days_per_year' => 0.0,
                'monthly_allowance_amount' => 300000.00,
                'one_time_bonus_amount' => 0.00,
                'is_tax_exempt' => true,
                'applicable_gender' => 'FEMALE',
                'child_min_age_months' => 0,
                'child_max_age_months' => 12,
                'min_seniority_months' => 0,
                'description' => 'Miễn trừ 60 phút làm việc có hưởng lương, tuyệt đối cấm ca đêm và làm thêm giờ',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'policy_version_id' => $policyVerId,
                'code' => 'SCHEME_CON_NHO_11T',
                'name' => 'Phúc lợi Doanh nghiệp: Hỗ trợ Nuôi Con từ 1 - 11 tuổi',
                'category' => 'CHILD_CARE',
                'visibility' => 'VISIBLE',
                'is_statutory' => false,
                'daily_exemption_minutes' => 30,
                'pay_type' => 'PAID',
                'prohibit_night_shift' => false,
                'prohibit_overtime' => false,
                'additional_leave_days_per_year' => 0.0,
                'monthly_allowance_amount' => 300000.00,
                'one_time_bonus_amount' => 0.00,
                'is_tax_exempt' => false,
                'applicable_gender' => 'ALL',
                'child_min_age_months' => 13,
                'child_max_age_months' => 132,
                'min_seniority_months' => 6,
                'description' => 'Miễn trừ 30 phút/ngày và hỗ trợ 300.000 đ/tháng cho mỗi bé',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'policy_version_id' => $policyVerId,
                'code' => 'SCHEME_THAI_SAN_T7',
                'name' => 'Chế độ Bảo vệ Thai sản Nữ từ tháng thứ 7 (Điều 137 BLLĐ)',
                'category' => 'MATERNITY_FEMALE',
                'visibility' => 'VISIBLE',
                'is_statutory' => true,
                'daily_exemption_minutes' => 60,
                'pay_type' => 'PAID',
                'prohibit_night_shift' => true,
                'prohibit_overtime' => true,
                'additional_leave_days_per_year' => 0.0,
                'monthly_allowance_amount' => 0.00,
                'one_time_bonus_amount' => 2000000.00,
                'is_tax_exempt' => true,
                'applicable_gender' => 'FEMALE',
                'child_min_age_months' => null,
                'child_max_age_months' => null,
                'min_seniority_months' => 0,
                'description' => 'Giảm 1 giờ làm việc/ngày hoặc chuyển công việc nhẹ hơn, cấm làm đêm và OT',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'policy_version_id' => $policyVerId,
                'code' => 'SCHEME_PHU_NU_CHUKY',
                'name' => 'Chế độ Chăm sóc Sức khỏe Phụ nữ Chu kỳ (Điều 137 BLLĐ)',
                'category' => 'HEALTH_WELLNESS',
                'visibility' => 'VISIBLE',
                'is_statutory' => true,
                'daily_exemption_minutes' => 30,
                'pay_type' => 'PAID',
                'prohibit_night_shift' => false,
                'prohibit_overtime' => false,
                'additional_leave_days_per_year' => 0.0,
                'monthly_allowance_amount' => 0.00,
                'one_time_bonus_amount' => 0.00,
                'is_tax_exempt' => true,
                'applicable_gender' => 'FEMALE',
                'child_min_age_months' => null,
                'child_max_age_months' => null,
                'min_seniority_months' => 0,
                'description' => 'Nghỉ 30 phút mỗi ngày trong thời gian hành kinh (tối thiểu 3 ngày/tháng)',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        ]);

        // 13c. Danh mục Phụ cấp Doanh nghiệp (Kế thừa từ timekeeper2)
        DB::table('allowances')->insert([
            [
                'tenant_id' => $tenantId,
                'code' => 'PC_ANTRUA',
                'name' => 'Phụ cấp Ăn trưa ca làm việc',
                'apply_type' => 'COMPANY_FIXED',
                'is_insurance_taxable' => false,
                'is_pit_taxable' => false,
                'is_included_in_ot_wage' => false,
                'tax_exemption_limit' => 730000.00,
                'default_amount' => 730000.00,
                'visibility' => 'VISIBLE',
                'notes' => 'Mức ăn trưa tối đa được miễn thuế TNCN theo Thông tư 26/2015',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'code' => 'PC_XANGXE',
                'name' => 'Phụ cấp Đi lại & Xăng xe',
                'apply_type' => 'COMPANY_FIXED',
                'is_insurance_taxable' => false,
                'is_pit_taxable' => true,
                'is_included_in_ot_wage' => false,
                'tax_exemption_limit' => 0.00,
                'default_amount' => 500000.00,
                'visibility' => 'VISIBLE',
                'notes' => 'Hỗ trợ chi phí xăng xe đi lại hàng tháng',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'code' => 'PC_DIENTHOAI',
                'name' => 'Phụ cấp Điện thoại & Liên lạc',
                'apply_type' => 'COMPANY_FIXED',
                'is_insurance_taxable' => false,
                'is_pit_taxable' => false,
                'is_included_in_ot_wage' => false,
                'tax_exemption_limit' => 300000.00,
                'default_amount' => 300000.00,
                'visibility' => 'VISIBLE',
                'notes' => 'Khoán chi tiền điện thoại phục vụ công tác theo quy chế',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'code' => 'PC_TRACHNHIEM',
                'name' => 'Phụ cấp Trách nhiệm & Quản lý',
                'apply_type' => 'INDIVIDUAL_FIXED',
                'is_insurance_taxable' => true,
                'is_pit_taxable' => true,
                'is_included_in_ot_wage' => true,
                'tax_exemption_limit' => 0.00,
                'default_amount' => 1500000.00,
                'visibility' => 'VISIBLE',
                'notes' => 'Chi trả theo vị trí chức danh và bổ nhiệm',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'code' => 'PC_CONNHO',
                'name' => 'Trợ cấp Nuôi Con nhỏ',
                'apply_type' => 'INDIVIDUAL_FIXED',
                'is_insurance_taxable' => false,
                'is_pit_taxable' => false,
                'is_included_in_ot_wage' => false,
                'tax_exemption_limit' => 300000.00,
                'default_amount' => 300000.00,
                'visibility' => 'VISIBLE',
                'notes' => 'Trợ cấp công ty 300k/tháng/bé (chế độ bảo trợ gia đình)',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 13d. Ma Trận Phân Quyền & Ủy Quyền Hệ Thống (Permission Assignments)
        $permData = [
            // SUPER_ADMIN: Toàn quyền
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'SUPER_ADMIN', 'module_code' => 'CORE_HR', 'can_view' => true, 'can_manage' => true, 'can_approve' => true, 'notes' => 'Toàn quyền Core HR'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'SUPER_ADMIN', 'module_code' => 'PAYROLL_SALARY', 'can_view' => true, 'can_manage' => true, 'can_approve' => true, 'notes' => 'Toàn quyền Lương & Thu nhập'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'SUPER_ADMIN', 'module_code' => 'ATTENDANCE', 'can_view' => true, 'can_manage' => true, 'can_approve' => true, 'notes' => 'Toàn quyền Chấm công'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'SUPER_ADMIN', 'module_code' => 'POLICY_WELFARE', 'can_view' => true, 'can_manage' => true, 'can_approve' => true, 'notes' => 'Toàn quyền Quy chế & Phúc lợi'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'SUPER_ADMIN', 'module_code' => 'OFFICE_RESOURCES', 'can_view' => true, 'can_manage' => true, 'can_approve' => true, 'notes' => 'Toàn quyền Tài nguyên văn phòng & IT'],

            // HR_ADMIN: Quản lý Hồ sơ, Quy chế, Chấm công; Xem và Set lương nhân viên
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'HR_ADMIN', 'module_code' => 'CORE_HR', 'can_view' => true, 'can_manage' => true, 'can_approve' => true, 'notes' => 'Quản lý Hồ sơ & Tổ chức'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'HR_ADMIN', 'module_code' => 'PAYROLL_SALARY', 'can_view' => true, 'can_manage' => true, 'can_approve' => false, 'notes' => 'Trưởng phòng HR xem & set lương nhân viên'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'HR_ADMIN', 'module_code' => 'ATTENDANCE', 'can_view' => true, 'can_manage' => true, 'can_approve' => true, 'notes' => 'Quản trị công & Điểm danh'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'HR_ADMIN', 'module_code' => 'POLICY_WELFARE', 'can_view' => true, 'can_manage' => true, 'can_approve' => true, 'notes' => 'Cấu hình quy chế & chế độ'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'HR_ADMIN', 'module_code' => 'OFFICE_RESOURCES', 'can_view' => true, 'can_manage' => true, 'can_approve' => true, 'notes' => 'Quản lý tài nguyên công ty'],

            // ACCOUNTANT: Quản trị Lương & Thuế; Xem Hồ sơ (chỉ đọc); Chốt công tháng
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'ACCOUNTANT', 'module_code' => 'CORE_HR', 'can_view' => true, 'can_manage' => false, 'can_approve' => false, 'notes' => 'Kế toán chỉ xem hồ sơ để tính lương'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'ACCOUNTANT', 'module_code' => 'PAYROLL_SALARY', 'can_view' => true, 'can_manage' => true, 'can_approve' => true, 'notes' => 'Kế toán quản lý cấu hình & tính lương'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'ACCOUNTANT', 'module_code' => 'ATTENDANCE', 'can_view' => true, 'can_manage' => false, 'can_approve' => true, 'notes' => 'Kế toán chốt công tháng'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'ACCOUNTANT', 'module_code' => 'POLICY_WELFARE', 'can_view' => true, 'can_manage' => false, 'can_approve' => false, 'notes' => 'Kế toán xem quy chế'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'ACCOUNTANT', 'module_code' => 'OFFICE_RESOURCES', 'can_view' => true, 'can_manage' => true, 'can_approve' => true, 'notes' => 'Kế toán duyệt đề xuất mua VPP, tạm ứng'],

            // EMPLOYEE: Nhân viên bình thường
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'EMPLOYEE', 'module_code' => 'CORE_HR', 'can_view' => false, 'can_manage' => false, 'can_approve' => false, 'notes' => 'Nhân viên không xem hồ sơ nhân sự khác'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'EMPLOYEE', 'module_code' => 'PAYROLL_SALARY', 'can_view' => false, 'can_manage' => false, 'can_approve' => false, 'notes' => 'Không được xem lương công ty'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'EMPLOYEE', 'module_code' => 'ATTENDANCE', 'can_view' => true, 'can_manage' => false, 'can_approve' => false, 'notes' => 'Xem bảng công cá nhân'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'EMPLOYEE', 'module_code' => 'POLICY_WELFARE', 'can_view' => true, 'can_manage' => false, 'can_approve' => false, 'notes' => 'Xem chính sách công ty'],
            ['tenant_id' => $tenantId, 'subject_type' => 'ROLE', 'subject_id' => null, 'subject_role' => 'EMPLOYEE', 'module_code' => 'OFFICE_RESOURCES', 'can_view' => true, 'can_manage' => false, 'can_approve' => false, 'notes' => 'Báo hỏng máy tính, đề xuất VPP'],
        ];

        foreach ($permData as $pd) {
            $pd['created_at'] = $now;
            $pd['updated_at'] = $now;
            DB::table('permission_assignments')->insert($pd);
        }

        // 14. PIT Tax Brackets (Biểu thuế TNCN 5 bậc hiệu lực từ 2026)
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

        // 15. Shifts
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

        // 16. System Users & RBAC Accounts (5 Core Roles)
        $defaultPassword = Hash::make('password');
        DB::table('users')->insert([
            [
                'tenant_id' => $tenantId,
                'employee_id' => null,
                'name' => 'Admin Quản trị',
                'email' => 'admin@smarthr.vn',
                'password' => $defaultPassword,
                'role' => 'SUPER_ADMIN',
                'avatar' => null,
                'theme_preference' => 'system',
                'locale_preference' => 'vi',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp1Id,
                'name' => 'Nguyễn Văn An (HR)',
                'email' => 'hr@smarthr.vn',
                'password' => $defaultPassword,
                'role' => 'HR_ADMIN',
                'avatar' => null,
                'theme_preference' => 'system',
                'locale_preference' => 'vi',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'employee_id' => null,
                'name' => 'Phạm Thị Lan (Kế toán Lương)',
                'email' => 'accountant@smarthr.vn',
                'password' => $defaultPassword,
                'role' => 'ACCOUNTANT',
                'avatar' => null,
                'theme_preference' => 'system',
                'locale_preference' => 'vi',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp1Id,
                'name' => 'Nguyễn Văn An (Trưởng Bộ phận)',
                'email' => 'manager@smarthr.vn',
                'password' => $defaultPassword,
                'role' => 'DEPARTMENT_MANAGER',
                'avatar' => null,
                'theme_preference' => 'system',
                'locale_preference' => 'vi',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp2Id,
                'name' => 'Trần Thị Mai (Nhân viên)',
                'email' => 'employee@smarthr.vn',
                'password' => $defaultPassword,
                'role' => 'EMPLOYEE',
                'avatar' => null,
                'theme_preference' => 'system',
                'locale_preference' => 'vi',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 17. Holidays (11 ngày nghỉ lễ tết năm 2026 theo Điều 112 Bộ luật Lao động 2019)
        DB::table('holidays')->insert([
            [
                'tenant_id' => $tenantId,
                'name' => 'Tết Dương lịch 2026',
                'holiday_date' => '2026-01-01',
                'year' => 2026,
                'is_statutory' => true,
                'notes' => 'Nghỉ 01 ngày theo Điều 112 Khoản 1 Điểm a BLLĐ',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Tết Âm lịch (29 tháng Chạp)',
                'holiday_date' => '2026-02-16',
                'year' => 2026,
                'is_statutory' => true,
                'notes' => 'Kỳ nghỉ Tết Âm lịch Bính Ngọ 2026',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Tết Âm lịch (Mùng 1 Tết Bính Ngọ)',
                'holiday_date' => '2026-02-17',
                'year' => 2026,
                'is_statutory' => true,
                'notes' => 'Kỳ nghỉ Tết Âm lịch Bính Ngọ 2026',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Tết Âm lịch (Mùng 2 Tết)',
                'holiday_date' => '2026-02-18',
                'year' => 2026,
                'is_statutory' => true,
                'notes' => 'Kỳ nghỉ Tết Âm lịch Bính Ngọ 2026',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Tết Âm lịch (Mùng 3 Tết)',
                'holiday_date' => '2026-02-19',
                'year' => 2026,
                'is_statutory' => true,
                'notes' => 'Kỳ nghỉ Tết Âm lịch Bính Ngọ 2026',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Tết Âm lịch (Mùng 4 Tết)',
                'holiday_date' => '2026-02-20',
                'year' => 2026,
                'is_statutory' => true,
                'notes' => 'Kỳ nghỉ Tết Âm lịch Bính Ngọ 2026',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Giỗ Tổ Hùng Vương (10/3 Âm lịch)',
                'holiday_date' => '2026-04-26',
                'year' => 2026,
                'is_statutory' => true,
                'notes' => 'Nghỉ 01 ngày theo Điều 112 Khoản 1 Điểm e BLLĐ',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Ngày Chiến thắng (30/4)',
                'holiday_date' => '2026-04-30',
                'year' => 2026,
                'is_statutory' => true,
                'notes' => 'Nghỉ 01 ngày theo Điều 112 Khoản 1 Điểm c BLLĐ',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Ngày Quốc tế Lao động (01/5)',
                'holiday_date' => '2026-05-01',
                'year' => 2026,
                'is_statutory' => true,
                'notes' => 'Nghỉ 01 ngày theo Điều 112 Khoản 1 Điểm d BLLĐ',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Ngày Quốc khánh (Liền kề trước 02/9)',
                'holiday_date' => '2026-09-01',
                'year' => 2026,
                'is_statutory' => true,
                'notes' => 'Nghỉ theo Điều 112 Khoản 1 Điểm đ BLLĐ',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'name' => 'Ngày Quốc khánh (Chính lễ 02/9)',
                'holiday_date' => '2026-09-02',
                'year' => 2026,
                'is_statutory' => true,
                'notes' => 'Nghỉ theo Điều 112 Khoản 1 Điểm đ BLLĐ',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 18. Rosters (Phân ca làm việc tuần đầu tháng 09/2026: 01/09 -> 10/09)
        $shiftStandard = DB::table('shifts')->where('tenant_id', $tenantId)->where('code', 'HC_STANDARD')->first();
        $shiftMorning = DB::table('shifts')->where('tenant_id', $tenantId)->where('code', 'SHIFT_MORNING')->first();

        for ($d = 1; $d <= 10; $d++) {
            $checkDate = Carbon::create(2026, 9, $d);
            if ($checkDate->isWeekend()) {
                continue; // Ngày cuối tuần (Thứ Bảy, Chủ Nhật) là ngày nghỉ
            }
            $dateStr = sprintf('2026-09-%02d', $d);
            // EMP001, EMP002, EMP004, EMP005 ca hành chính
            DB::table('rosters')->insert([
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $emp1Id,
                    'shift_id' => $shiftStandard->id,
                    'roster_date' => $dateStr,
                    'is_published' => true,
                    'status' => 'SCHEDULED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $emp2Id,
                    'shift_id' => $shiftStandard->id,
                    'roster_date' => $dateStr,
                    'is_published' => true,
                    'status' => 'SCHEDULED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $emp3Id,
                    'shift_id' => $shiftMorning->id,
                    'roster_date' => $dateStr,
                    'is_published' => true,
                    'status' => 'SCHEDULED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $emp4Id,
                    'shift_id' => $shiftStandard->id,
                    'roster_date' => $dateStr,
                    'is_published' => true,
                    'status' => 'SCHEDULED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'tenant_id' => $tenantId,
                    'employee_id' => $emp5Id,
                    'shift_id' => $shiftStandard->id,
                    'roster_date' => $dateStr,
                    'is_published' => true,
                    'status' => 'SCHEDULED',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        // 19. Raw Attendance Logs (Dữ liệu quẹt thẻ thực tế từ máy chấm công vân tay/face)
        // Ngày 03/09/2026:
        // EMP001 (An): Đúng giờ (08:02 - 17:05)
        DB::table('raw_attendance_logs')->insert([
            ['tenant_id' => $tenantId, 'branch_id' => $branchHnId, 'employee_id' => $emp1Id, 'punch_time' => '2026-09-03 08:02:15', 'punch_state' => 'CHECK_IN', 'device_sn' => 'ZK_HN_01', 'verification_mode' => 'FINGERPRINT', 'raw_payload_hash' => hash('sha256', 'p1'), 'sync_batch_id' => 'BATCH_20260903_01'],
            ['tenant_id' => $tenantId, 'branch_id' => $branchHnId, 'employee_id' => $emp1Id, 'punch_time' => '2026-09-03 17:05:40', 'punch_state' => 'CHECK_OUT', 'device_sn' => 'ZK_HN_01', 'verification_mode' => 'FINGERPRINT', 'raw_payload_hash' => hash('sha256', 'p2'), 'sync_batch_id' => 'BATCH_20260903_02'],
        ]);

        // EMP002 (Mai - Mẹ nuôi con 8 tháng): Quẹt muộn 45p, về sớm 15p -> Tổng 60p, được miễn trừ trọn vẹn theo Điều 137 BLLĐ
        DB::table('raw_attendance_logs')->insert([
            ['tenant_id' => $tenantId, 'branch_id' => $branchHnId, 'employee_id' => $emp2Id, 'punch_time' => '2026-09-03 08:45:10', 'punch_state' => 'CHECK_IN', 'device_sn' => 'ZK_HN_01', 'verification_mode' => 'FACE', 'raw_payload_hash' => hash('sha256', 'p3'), 'sync_batch_id' => 'BATCH_20260903_01'],
            ['tenant_id' => $tenantId, 'branch_id' => $branchHnId, 'employee_id' => $emp2Id, 'punch_time' => '2026-09-03 16:45:20', 'punch_state' => 'CHECK_OUT', 'device_sn' => 'ZK_HN_01', 'verification_mode' => 'FACE', 'raw_payload_hash' => hash('sha256', 'p4'), 'sync_batch_id' => 'BATCH_20260903_02'],
        ]);

        // Ngày 04/09/2026:
        // EMP001 (An): Muộn 22 phút (08:22:00) -> Chạm nấc phạt 16-30 phút (100.000 đ)
        DB::table('raw_attendance_logs')->insert([
            ['tenant_id' => $tenantId, 'branch_id' => $branchHnId, 'employee_id' => $emp1Id, 'punch_time' => '2026-09-04 08:22:00', 'punch_state' => 'CHECK_IN', 'device_sn' => 'ZK_HN_01', 'verification_mode' => 'FINGERPRINT', 'raw_payload_hash' => hash('sha256', 'p5'), 'sync_batch_id' => 'BATCH_20260904_01'],
            ['tenant_id' => $tenantId, 'branch_id' => $branchHnId, 'employee_id' => $emp1Id, 'punch_time' => '2026-09-04 17:01:10', 'punch_state' => 'CHECK_OUT', 'device_sn' => 'ZK_HN_01', 'verification_mode' => 'FINGERPRINT', 'raw_payload_hash' => hash('sha256', 'p6'), 'sync_batch_id' => 'BATCH_20260904_02'],
        ]);

        // EMP002 (Mai): Về sớm 60 phút (16:00:00) -> Miễn trừ 60 phút Điều 137 -> 0 phút vi phạm
        DB::table('raw_attendance_logs')->insert([
            ['tenant_id' => $tenantId, 'branch_id' => $branchHnId, 'employee_id' => $emp2Id, 'punch_time' => '2026-09-04 08:00:00', 'punch_state' => 'CHECK_IN', 'device_sn' => 'ZK_HN_01', 'verification_mode' => 'FACE', 'raw_payload_hash' => hash('sha256', 'p7'), 'sync_batch_id' => 'BATCH_20260904_01'],
            ['tenant_id' => $tenantId, 'branch_id' => $branchHnId, 'employee_id' => $emp2Id, 'punch_time' => '2026-09-04 16:00:00', 'punch_state' => 'CHECK_OUT', 'device_sn' => 'ZK_HN_01', 'verification_mode' => 'FACE', 'raw_payload_hash' => hash('sha256', 'p8'), 'sync_batch_id' => 'BATCH_20260904_02'],
        ]);

        // Ngày 05/09/2026 (Thứ Bảy):
        // EMP001 (An): Đi làm thêm thứ Bảy từ 08:00 đến 12:00 -> 4.0 giờ OT cuối tuần (hệ số 2.0x)
        DB::table('raw_attendance_logs')->insert([
            ['tenant_id' => $tenantId, 'branch_id' => $branchHnId, 'employee_id' => $emp1Id, 'punch_time' => '2026-09-05 08:00:00', 'punch_state' => 'CHECK_IN', 'device_sn' => 'ZK_HN_01', 'verification_mode' => 'FINGERPRINT', 'raw_payload_hash' => hash('sha256', 'p9'), 'sync_batch_id' => 'BATCH_20260905_01'],
            ['tenant_id' => $tenantId, 'branch_id' => $branchHnId, 'employee_id' => $emp1Id, 'punch_time' => '2026-09-05 12:00:00', 'punch_state' => 'CHECK_OUT', 'device_sn' => 'ZK_HN_01', 'verification_mode' => 'FINGERPRINT', 'raw_payload_hash' => hash('sha256', 'p10'), 'sync_batch_id' => 'BATCH_20260905_02'],
        ]);

        // 20. Approval Requests (Các đơn từ chờ duyệt để kiểm thử Bảng công Tạm tính - Timesheet Preview)
        DB::table('approval_requests')->insert([
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp1Id,
                'request_type' => 'TIMESHEET_EDIT',
                'form_payload' => json_encode([
                    'date' => '2026-09-04',
                    'proposed_in' => '08:00:00',
                    'proposed_out' => '17:01:10',
                    'reason' => 'Đến sớm 07:55 để xử lý sự cố máy chủ cho khách hàng, quên quẹt thẻ máy lúc 08:00',
                ]),
                'is_voluntary_consent' => false,
                'voluntary_agreement_file' => null,
                'voluntary_agreement_url' => null,
                'current_step' => 1,
                'snapshot_approvers' => json_encode([['step' => 1, 'role' => 'HR_ADMIN', 'name' => 'HR Manager']]),
                'status' => 'PENDING',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'employee_id' => $emp2Id,
                'request_type' => 'LEAVE',
                'form_payload' => json_encode([
                    'date' => '2026-09-08',
                    'leave_type' => 'ANNUAL',
                    'reason' => 'Đưa con nhỏ đi tiêm chủng định kỳ',
                ]),
                'is_voluntary_consent' => false,
                'voluntary_agreement_file' => null,
                'voluntary_agreement_url' => null,
                'current_step' => 1,
                'snapshot_approvers' => json_encode([['step' => 1, 'role' => 'DEPARTMENT_MANAGER', 'name' => 'Trưởng bộ phận']]),
                'status' => 'PENDING',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 21. Tự động chạy 7-Pipe Pipeline để tính toán sẵn bảng công từ ngày 01/09 đến 06/09/2026
        $pipelineService = app(\App\Modules\Attendance\Services\AttendancePipelineService::class);
        $monthlyService = app(\App\Modules\Attendance\Services\MonthlyAttendanceService::class);

        $fromDate = Carbon::create(2026, 9, 1);
        $toDate = Carbon::create(2026, 9, 6);

        $pipelineService->calculateRange($tenantId, $fromDate, $toDate);

        // Tổng hợp công tháng cho các nhân viên
        $monthlyService->aggregateMonth($tenantId, $emp1Id, '2026-09');
        $monthlyService->aggregateMonth($tenantId, $emp2Id, '2026-09');
        $monthlyService->aggregateMonth($tenantId, $emp3Id, '2026-09');
        $monthlyService->aggregateMonth($tenantId, $emp4Id, '2026-09');
        $monthlyService->aggregateMonth($tenantId, $emp5Id, '2026-09');

        // 22. Bổ sung bộ kịch bản dữ liệu doanh nghiệp thực tế quy mô lớn (Enterprise Scenario Seeder)
        if (!app()->runningUnitTests()) {
            $this->call(EnterpriseScenarioSeeder::class);
        }
    }
}
