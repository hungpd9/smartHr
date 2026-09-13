<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Modules\Licensing\Services\LicenseService;
use App\Modules\Attendance\Services\AttendancePipelineService;
use App\Modules\Attendance\Services\MonthlyAttendanceService;

/**
 * EnterpriseScenarioSeeder - Bộ dữ liệu mô phỏng doanh nghiệp thực tế quy mô lớn
 * 
 * Mô phỏng toàn diện các tình huống nghiệp vụ phức tạp:
 * 1. Đa chi nhánh: Hội sở Hà Nội (HN_HQ), Chi nhánh TP.HCM (HCM_BRANCH - Vùng 1).
 * 2. Đa khối phòng ban: Bán lẻ & Chuỗi cửa hàng FnB, Kho vận & Chuỗi cung ứng, Tài chính & Kế toán.
 * 3. Đa dạng hồ sơ nhân sự (EMP007 -> EMP020):
 *    - Thử việc (PROBATION - 85% lương, thuế 10%, không BHXH).
 *    - Cửa hàng trưởng ca kíp (RETAIL_LEAD - Ca xoay kíp & ca gãy).
 *    - Lao động nuôi con < 12 tháng tự nguyện làm ca đêm có đơn Điều 137 BLLĐ.
 *    - Kỹ thuật viên chuyên trực ca đêm xuyên ngày (22:00 - 06:00).
 *    - Lao động nữ mang thai tháng thứ 7 được rút ngắn 1h làm việc.
 *    - Kỹ sư thâm niên > 11 năm được cộng thêm ngày phép theo Điều 114 BLLĐ.
 *    - Nhân viên nghỉ thai sản 6 tháng (ON_LEAVE).
 *    - Nhân viên vi phạm đi muộn nhiều lần (chạm nấc phạt lũy tiến tần suất).
 *    - Nhân viên OT vượt ngưỡng 35h (Cảnh báo vàng) và 40h/tháng (Cảnh báo đỏ Điều 107 BLLĐ).
 *    - Nhân viên sắp hết hạn Hợp đồng lao động trước 30 ngày (Điều 45 BLLĐ).
 *    - Nhân viên quên quẹt thẻ (Missing Punch Out) và tạo đơn giải trình.
 *    - Nhân viên trực ngày lễ Quốc khánh 02/09 (hệ số 300% lương).
 *    - Nhân viên đổi ca & nhận ca thay (Shift Swap & Cover).
 * 4. Dữ liệu bảng lương tháng 08/2026 (đã chốt LOCKED) và tháng 09/2026 (đang chạy DRAFT).
 * 5. Bảng quỹ phép (Leave Balances), lịch sử biến động (Leave Transactions) và Cảnh báo tuân thủ (Compliance Alerts).
 */
class EnterpriseScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $tenantId = 1; // Tenant Smart-HR mặc định
        $licenseService = app(LicenseService::class);
        $pipelineService = app(AttendancePipelineService::class);
        $monthlyService = app(MonthlyAttendanceService::class);

        // =========================================================================
        // 1. CHI NHÁNH MỚI: TP. HỒ CHÍ MINH (Vùng 1 - Quận 1)
        // =========================================================================
        $branchHcm = DB::table('branches')->where('tenant_id', $tenantId)->where('code', 'HCM_BRANCH')->first();
        if ($branchHcm) {
            $branchHcmId = $branchHcm->id;
        } else {
            $branchHcmId = DB::table('branches')->insertGetId([
                'tenant_id' => $tenantId,
                'code' => 'HCM_BRANCH',
                'name' => 'Chi nhánh TP. Hồ Chí Minh (Vùng 1)',
                'address' => 'Tòa nhà Bitexco, Số 2 Hải Triều, Bến Nghé, Quận 1, TP. Hồ Chí Minh',
                'is_remote_or_island' => false,
                'secret_key' => 'branch_hcm_secret_key_999',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $branchHn = DB::table('branches')->where('tenant_id', $tenantId)->where('code', 'HN_HQ')->first();
        $branchHnId = $branchHn->id ?? 1;

        // =========================================================================
        // 2. CA LÀM VIỆC MẪU BỔ SUNG (Shifts)
        // =========================================================================
        $shiftAfternoon = DB::table('shifts')->where('tenant_id', $tenantId)->where('code', 'SHIFT_AFTERNOON')->first();
        $shiftAfternoonId = $shiftAfternoon ? $shiftAfternoon->id : DB::table('shifts')->insertGetId([
            'tenant_id' => $tenantId,
            'code' => 'SHIFT_AFTERNOON',
            'name' => 'Ca Chiều (14:00 - 22:00)',
            'start_time' => '14:00:00',
            'end_time' => '22:00:00',
            'break_minutes' => 60,
            'is_night_shift' => false,
            'is_default' => false,
            'night_allowance_rate' => 0.00,
            'night_bonus_amount' => 0.00,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $shiftNight = DB::table('shifts')->where('tenant_id', $tenantId)->where('code', 'SHIFT_NIGHT_FULL')->first();
        $shiftNightId = $shiftNight ? $shiftNight->id : DB::table('shifts')->insertGetId([
            'tenant_id' => $tenantId,
            'code' => 'SHIFT_NIGHT_FULL',
            'name' => 'Ca Đêm Chuyên Trách (22:00 - 06:00)',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'break_minutes' => 45,
            'is_night_shift' => true,
            'is_default' => false,
            'night_allowance_rate' => 30.00, // Phụ cấp ca đêm 30% theo Điều 98 BLLĐ
            'night_bonus_amount' => 50000.00, // Hỗ trợ bữa ăn ca đêm 50k
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $shiftSplit = DB::table('shifts')->where('tenant_id', $tenantId)->where('code', 'SHIFT_SPLIT_FNB')->first();
        $shiftSplitFnbId = $shiftSplit ? $shiftSplit->id : DB::table('shifts')->insertGetId([
            'tenant_id' => $tenantId,
            'code' => 'SHIFT_SPLIT_FNB',
            'name' => 'Ca Gãy Bán Lẻ & FnB (10:00-14:00 & 17:00-21:00)',
            'start_time' => '10:00:00',
            'end_time' => '21:00:00',
            'break_minutes' => 180, // Nghỉ giữa ca 3 tiếng từ 14:00 đến 17:00
            'is_night_shift' => false,
            'is_default' => false,
            'night_allowance_rate' => 0.00,
            'night_bonus_amount' => 30000.00,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $shiftStandard = DB::table('shifts')->where('tenant_id', $tenantId)->whereIn('code', ['SHIFT_OFFICE', 'HC_STANDARD'])->first();
        $shiftStandardId = $shiftStandard ? $shiftStandard->id : $shiftAfternoonId;

        // =========================================================================
        // 3. CƠ CẤU PHÒNG BAN MỚI TẠI TP.HCM & HỘI SỞ
        // =========================================================================
        // Khối Kinh doanh & Thương mại (Level 1)
        $deptCommercialId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchHcmId,
            'code' => 'DIV_COMMERCIAL',
            'level' => 1,
            'parent_id' => null,
            'order' => 3,
            'status' => 'ACTIVE',
            'default_shift_id' => $shiftStandardId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('department_versions')->insert([
            'department_id' => $deptCommercialId,
            'name' => 'Khối Kinh Doanh & Thương Mại Toàn Quốc',
            'mission_function' => 'Hoạch định chiến lược thị trường, mở rộng chuỗi cửa hàng bán lẻ và phát triển doanh số B2B',
            'manager_id' => null,
            'effective_date' => '2024-01-01',
            'end_date' => null,
            'change_reason' => 'Thành lập Khối Thương Mại',
            'created_at' => $now,
        ]);

        // Phòng Bán lẻ & Chuỗi Cửa hàng (Level 2)
        $deptRetailId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchHcmId,
            'code' => 'DEPT_RETAIL',
            'level' => 2,
            'parent_id' => $deptCommercialId,
            'order' => 1,
            'status' => 'ACTIVE',
            'default_shift_id' => $shiftSplitFnbId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('department_versions')->insert([
            'department_id' => $deptRetailId,
            'name' => 'Phòng Quản Lý Chuỗi Cửa Hàng & FnB',
            'mission_function' => 'Vận hành hệ thống bán lẻ trực tiếp, phân ca xoay kíp và kiểm soát chất lượng dịch vụ',
            'manager_id' => null,
            'effective_date' => '2024-01-01',
            'end_date' => null,
            'change_reason' => 'Thành lập Phòng Bán Lẻ',
            'created_at' => $now,
        ]);

        // Phòng Kho vận & Logistics (Level 2)
        $deptLogisticsId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchHcmId,
            'code' => 'DEPT_LOGISTICS',
            'level' => 2,
            'parent_id' => $deptCommercialId,
            'order' => 2,
            'status' => 'ACTIVE',
            'default_shift_id' => $shiftNightId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('department_versions')->insert([
            'department_id' => $deptLogisticsId,
            'name' => 'Phòng Kho Vận & Chuỗi Cung Ứng',
            'mission_function' => 'Quản trị tổng kho hàng hóa miền Nam, bốc dỡ ca đêm và điều phối giao nhận',
            'manager_id' => null,
            'effective_date' => '2024-01-01',
            'end_date' => null,
            'change_reason' => 'Thành lập Phòng Kho Vận',
            'created_at' => $now,
        ]);

        // Phòng Tài chính & Kế toán tại Hội sở Hà Nội (Level 2)
        $deptFinanceId = DB::table('departments')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchHnId,
            'code' => 'DEPT_FINANCE',
            'level' => 2,
            'parent_id' => null,
            'order' => 4,
            'status' => 'ACTIVE',
            'default_shift_id' => $shiftStandardId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('department_versions')->insert([
            'department_id' => $deptFinanceId,
            'name' => 'Phòng Tài Chính & Kế Toán Doanh Nghiệp',
            'mission_function' => 'Quản lý thu chi, kiểm soát chi phí tiền lương, BHXH và quyết toán thuế TNCN',
            'manager_id' => null,
            'effective_date' => '2024-01-01',
            'end_date' => null,
            'change_reason' => 'Thành lập Phòng Tài Chính',
            'created_at' => $now,
        ]);

        // =========================================================================
        // 4. CÁC CHỨC VỤ MỚI (Positions)
        // =========================================================================
        $posRetailLeadId = DB::table('positions')->insertGetId([
            'tenant_id' => $tenantId,
            'department_id' => $deptRetailId,
            'code' => 'STORE_LEAD',
            'name' => 'Cửa Hàng Trưởng / Quản Lý Ca',
            'level' => 3,
            'allowance_amount' => 2000000.00, // Phụ cấp trách nhiệm
            'bonus_one_time' => 3000000.00,
            'description' => 'Chịu trách nhiệm vận hành ca bán lẻ, điều phối nhân viên và doanh số cửa hàng',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $posRetailStaffId = DB::table('positions')->insertGetId([
            'tenant_id' => $tenantId,
            'department_id' => $deptRetailId,
            'code' => 'RETAIL_STAFF',
            'name' => 'Nhân Viên Thu Ngân & Bán Hàng',
            'level' => 4,
            'allowance_amount' => 500000.00,
            'bonus_one_time' => 0.00,
            'description' => 'Phục vụ khách hàng, quẹt mã sản phẩm, thu ngân và sắp xếp quầy kệ',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $posWarehouseNightId = DB::table('positions')->insertGetId([
            'tenant_id' => $tenantId,
            'department_id' => $deptLogisticsId,
            'code' => 'WAREHOUSE_NIGHT',
            'name' => 'Kỹ Thuật Viên Kho Vận Ca Đêm',
            'level' => 4,
            'allowance_amount' => 1500000.00, // Phụ cấp môi trường kho lạnh
            'bonus_one_time' => 1000000.00,
            'description' => 'Phân loại hàng hóa, bốc dỡ nhập xuất kho trong khung giờ đêm 22:00 - 06:00',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $posAccountantId = DB::table('positions')->insertGetId([
            'tenant_id' => $tenantId,
            'department_id' => $deptFinanceId,
            'code' => 'SENIOR_ACCOUNTANT',
            'name' => 'Kế Toán Tổng Hợp & Quản Trị C&B',
            'level' => 3,
            'allowance_amount' => 1000000.00,
            'bonus_one_time' => 2000000.00,
            'description' => 'Tính toán đối soát bảng công, bảng lương Gross-to-Net và quyết toán thuế TNCN',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // =========================================================================
        // 5. DANH SÁCH 14 NHÂN SỰ MÔ PHỎNG TÌNH HUỐNG THỰC TẾ (EMP007 -> EMP020)
        // =========================================================================
        $employeesData = [
            // EMP007: Thử việc (PROBATION) - Áp dụng 85% lương, thuế TNCN 10%, không BHXH
            [
                'user_code' => 'EMP007',
                'attendance_code' => '1007',
                'full_name' => 'Trần Văn Tuấn',
                'gender' => 'MALE',
                'birth_date' => '2001-04-18',
                'email' => 'tuan.tran@smarthr.vn',
                'phone' => '0938112233',
                'citizen_id' => '079201001234',
                'hometown' => 'Long An',
                'permanent_address' => 'Số 45 Bến Lức, Long An',
                'temporary_address' => 'Chung cư Masteri Thảo Điền, TP. Thủ Đức, TP.HCM',
                'join_date' => '2026-08-01',
                'probation_end_date' => '2026-10-01',
                'status' => 'PROBATION',
                'is_pregnant' => false,
                'children_count' => 0,
                'tax_dependents_count' => 0,
                'department_id' => $deptRetailId,
                'position_id' => $posRetailStaffId,
                'salary_base' => 10000000.00,
                'contract_type' => 'PROBATION',
                'user_role' => 'EMPLOYEE',
            ],

            // EMP008: Cửa hàng trưởng ca kíp (RETAIL_LEAD) - Ca xoay kíp & ca gãy
            [
                'user_code' => 'EMP008',
                'attendance_code' => '1008',
                'full_name' => 'Lê Hoàng Long',
                'gender' => 'MALE',
                'birth_date' => '1993-09-12',
                'email' => 'long.le@smarthr.vn',
                'phone' => '0909445566',
                'citizen_id' => '079093005678',
                'hometown' => 'TP. Hồ Chí Minh',
                'permanent_address' => 'Số 128 Nguyễn Trãi, Phường 3, Quận 5, TP.HCM',
                'temporary_address' => 'Số 128 Nguyễn Trãi, Phường 3, Quận 5, TP.HCM',
                'join_date' => '2023-05-15',
                'probation_end_date' => '2023-07-15',
                'status' => 'OFFICIAL',
                'is_pregnant' => false,
                'children_count' => 1,
                'tax_dependents_count' => 1,
                'department_id' => $deptRetailId,
                'position_id' => $posRetailLeadId,
                'salary_base' => 17000000.00,
                'contract_type' => 'OFFICIAL',
                'user_role' => 'DEPARTMENT_MANAGER',
            ],

            // EMP009: Lao động nuôi con 10 tháng có đơn tự nguyện làm thêm ca đêm (Điều 137 BLLĐ)
            [
                'user_code' => 'EMP009',
                'attendance_code' => '1009',
                'full_name' => 'Phạm Thị Lan',
                'gender' => 'FEMALE',
                'birth_date' => '1996-12-05',
                'email' => 'lan.pham@smarthr.vn',
                'phone' => '0918778899',
                'citizen_id' => '079196008901',
                'hometown' => 'Bình Dương',
                'permanent_address' => 'Thủ Dầu Một, Bình Dương',
                'temporary_address' => 'Đường Ung Văn Khiêm, Bình Thạnh, TP.HCM',
                'join_date' => '2024-03-01',
                'probation_end_date' => '2024-05-01',
                'status' => 'OFFICIAL',
                'is_pregnant' => false,
                'children_count' => 1,
                'tax_dependents_count' => 1,
                'department_id' => $deptRetailId,
                'position_id' => $posRetailStaffId,
                'salary_base' => 11000000.00,
                'contract_type' => 'OFFICIAL',
                'user_role' => 'EMPLOYEE',
            ],

            // EMP010: Nhân viên Kho vận chuyên trực ca đêm xuyên ngày (22:00 - 06:00)
            [
                'user_code' => 'EMP010',
                'attendance_code' => '1010',
                'full_name' => 'Đỗ Văn Hùng',
                'gender' => 'MALE',
                'birth_date' => '1991-07-22',
                'email' => 'hung.do@smarthr.vn',
                'phone' => '0977223344',
                'citizen_id' => '079091002345',
                'hometown' => 'Đồng Nai',
                'permanent_address' => 'Biên Hòa, Đồng Nai',
                'temporary_address' => 'Đường Huỳnh Tấn Phát, Quận 7, TP.HCM',
                'join_date' => '2023-11-01',
                'probation_end_date' => '2024-01-01',
                'status' => 'OFFICIAL',
                'is_pregnant' => false,
                'children_count' => 2,
                'tax_dependents_count' => 2,
                'department_id' => $deptLogisticsId,
                'position_id' => $posWarehouseNightId,
                'salary_base' => 13500000.00,
                'contract_type' => 'OFFICIAL',
                'user_role' => 'EMPLOYEE',
            ],

            // EMP011: Lao động nữ mang thai tháng thứ 7 (thai tuần 29 tại Hà Nội) -> Rút ngắn 1h làm việc
            [
                'user_code' => 'EMP011',
                'attendance_code' => '1011',
                'full_name' => 'Nguyễn Thị Bích',
                'gender' => 'FEMALE',
                'birth_date' => '1997-03-30',
                'email' => 'bich.nguyen@smarthr.vn',
                'phone' => '0988334455',
                'citizen_id' => '001197003456',
                'hometown' => 'Hà Nội',
                'permanent_address' => 'Phố Đội Cấn, Ba Đình, Hà Nội',
                'temporary_address' => 'Khu đô thị Ngoại Giao Đoàn, Bắc Từ Liêm, Hà Nội',
                'join_date' => '2023-02-15',
                'probation_end_date' => '2023-04-15',
                'status' => 'OFFICIAL',
                'is_pregnant' => true,
                'pregnancy_start_date' => '2026-02-15',
                'pregnancy_weeks_at_start' => 29,
                'expected_due_date' => '2026-11-20',
                'children_count' => 0,
                'tax_dependents_count' => 0,
                'department_id' => $deptFinanceId,
                'position_id' => $posAccountantId,
                'salary_base' => 18500000.00,
                'contract_type' => 'OFFICIAL',
                'user_role' => 'EMPLOYEE',
            ],

            // EMP012: Kỹ sư thâm niên > 11 năm (Gia nhập 2015) -> Hưởng thâm niên +2 ngày phép (Điều 114)
            [
                'user_code' => 'EMP012',
                'attendance_code' => '1012',
                'full_name' => 'Trần Quang Đạt',
                'gender' => 'MALE',
                'birth_date' => '1985-02-10',
                'email' => 'dat.tran@smarthr.vn',
                'phone' => '0912998877',
                'citizen_id' => '001085001122',
                'hometown' => 'Thái Bình',
                'permanent_address' => 'Vũ Thư, Thái Bình',
                'temporary_address' => 'Khu đô thị Trung Hòa Nhân Chính, Cầu Giấy, Hà Nội',
                'join_date' => '2015-06-01', // Thâm niên > 11 năm
                'probation_end_date' => '2015-08-01',
                'status' => 'OFFICIAL',
                'is_pregnant' => false,
                'children_count' => 2,
                'tax_dependents_count' => 2,
                'department_id' => 2, // Khối Công nghệ
                'position_id' => 1,
                'salary_base' => 42000000.00,
                'contract_type' => 'OFFICIAL',
                'user_role' => 'EMPLOYEE',
            ],

            // EMP013: Nhân viên đang trong kỳ Nghỉ thai sản 6 tháng (ON_LEAVE)
            [
                'user_code' => 'EMP013',
                'attendance_code' => '1013',
                'full_name' => 'Bùi Thị Thảo',
                'gender' => 'FEMALE',
                'birth_date' => '1994-08-25',
                'email' => 'thao.bui@smarthr.vn',
                'phone' => '0934556677',
                'citizen_id' => '001194002233',
                'hometown' => 'Hưng Yên',
                'permanent_address' => 'Văn Giang, Hưng Yên',
                'temporary_address' => 'Khu đô thị Ecopark, Văn Giang, Hưng Yên',
                'join_date' => '2022-09-01',
                'probation_end_date' => '2022-11-01',
                'status' => 'ON_LEAVE',
                'is_pregnant' => false,
                'children_count' => 1,
                'tax_dependents_count' => 1,
                'department_id' => $deptFinanceId,
                'position_id' => $posAccountantId,
                'salary_base' => 16500000.00,
                'contract_type' => 'OFFICIAL',
                'user_role' => 'EMPLOYEE',
            ],

            // EMP014: Nhân viên vi phạm đi muộn nhiều lần (Chạm nấc phạt tần suất lần thứ 4)
            [
                'user_code' => 'EMP014',
                'attendance_code' => '1014',
                'full_name' => 'Vũ Đức Anh',
                'gender' => 'MALE',
                'birth_date' => '1999-11-14',
                'email' => 'anh.vu@smarthr.vn',
                'phone' => '0966778899',
                'citizen_id' => '079199003344',
                'hometown' => 'TP. Hồ Chí Minh',
                'permanent_address' => 'Số 89 Tân Bình, TP.HCM',
                'temporary_address' => 'Đường Phan Xích Long, Phú Nhuận, TP.HCM',
                'join_date' => '2024-10-01',
                'probation_end_date' => '2024-12-01',
                'status' => 'OFFICIAL',
                'is_pregnant' => false,
                'children_count' => 0,
                'tax_dependents_count' => 0,
                'department_id' => $deptRetailId,
                'position_id' => $posRetailStaffId,
                'salary_base' => 10500000.00,
                'contract_type' => 'OFFICIAL',
                'user_role' => 'EMPLOYEE',
            ],

            // EMP015: Nhân viên OT dồn dập (Chạm cảnh báo vàng 35h và đỏ 40h BLLĐ)
            [
                'user_code' => 'EMP015',
                'attendance_code' => '1015',
                'full_name' => 'Phạm Hồng Sơn',
                'gender' => 'MALE',
                'birth_date' => '1992-06-19',
                'email' => 'son.pham@smarthr.vn',
                'phone' => '0944112233',
                'citizen_id' => '079092004455',
                'hometown' => 'Bà Rịa - Vũng Tàu',
                'permanent_address' => 'TP. Vũng Tàu',
                'temporary_address' => 'Đường D2, Bình Thạnh, TP.HCM',
                'join_date' => '2023-08-01',
                'probation_end_date' => '2023-10-01',
                'status' => 'OFFICIAL',
                'is_pregnant' => false,
                'children_count' => 1,
                'tax_dependents_count' => 1,
                'department_id' => $deptLogisticsId,
                'position_id' => $posWarehouseNightId,
                'salary_base' => 14000000.00,
                'contract_type' => 'OFFICIAL',
                'user_role' => 'EMPLOYEE',
            ],

            // EMP016: Chuyên viên Kế toán sắp hết hạn Hợp đồng lao động (trong vòng 20 ngày tới)
            [
                'user_code' => 'EMP016',
                'attendance_code' => '1016',
                'full_name' => 'Nguyễn Phương Linh',
                'gender' => 'FEMALE',
                'birth_date' => '1995-05-18',
                'email' => 'linh.nguyen@smarthr.vn',
                'phone' => '0908990011',
                'citizen_id' => '001195006677',
                'hometown' => 'Hải Dương',
                'permanent_address' => 'TP. Hải Dương',
                'temporary_address' => 'Phố Hoàng Cầu, Đống Đa, Hà Nội',
                'join_date' => '2024-10-01',
                'probation_end_date' => '2024-12-01',
                'status' => 'OFFICIAL',
                'is_pregnant' => false,
                'children_count' => 0,
                'tax_dependents_count' => 0,
                'department_id' => $deptFinanceId,
                'position_id' => $posAccountantId,
                'salary_base' => 19000000.00,
                'contract_type' => 'OFFICIAL',
                'user_role' => 'EMPLOYEE',
            ],

            // EMP017: Nhân viên quên quẹt thẻ ra (Missing Punch Out)
            [
                'user_code' => 'EMP017',
                'attendance_code' => '1017',
                'full_name' => 'Lê Minh Trí',
                'gender' => 'MALE',
                'birth_date' => '1998-10-10',
                'email' => 'tri.le@smarthr.vn',
                'phone' => '0933445566',
                'citizen_id' => '079198007788',
                'hometown' => 'Tiền Giang',
                'permanent_address' => 'TP. Mỹ Tho, Tiền Giang',
                'temporary_address' => 'Đường Trần Não, Quận 2, TP.HCM',
                'join_date' => '2024-05-01',
                'probation_end_date' => '2024-07-01',
                'status' => 'OFFICIAL',
                'is_pregnant' => false,
                'children_count' => 0,
                'tax_dependents_count' => 0,
                'department_id' => $deptRetailId,
                'position_id' => $posRetailStaffId,
                'salary_base' => 10000000.00,
                'contract_type' => 'OFFICIAL',
                'user_role' => 'EMPLOYEE',
            ],

            // EMP018: Nhân viên xin nghỉ phép năm dài ngày kết hợp nghỉ cưới hỏi (Phúc lợi)
            [
                'user_code' => 'EMP018',
                'attendance_code' => '1018',
                'full_name' => 'Đặng Thúy Nga',
                'gender' => 'FEMALE',
                'birth_date' => '1996-04-12',
                'email' => 'nga.dang@smarthr.vn',
                'phone' => '0919223344',
                'citizen_id' => '001196008899',
                'hometown' => 'Nghệ An',
                'permanent_address' => 'TP. Vinh, Nghệ An',
                'temporary_address' => 'Đường Giải Phóng, Hai Bà Trưng, Hà Nội',
                'join_date' => '2023-07-01',
                'probation_end_date' => '2023-09-01',
                'status' => 'OFFICIAL',
                'is_pregnant' => false,
                'children_count' => 0,
                'tax_dependents_count' => 0,
                'department_id' => $deptFinanceId,
                'position_id' => $posAccountantId,
                'salary_base' => 17500000.00,
                'contract_type' => 'OFFICIAL',
                'user_role' => 'EMPLOYEE',
            ],

            // EMP019: Nhân viên thực hiện đổi ca (Shift Swap) với EMP008
            [
                'user_code' => 'EMP019',
                'attendance_code' => '1019',
                'full_name' => 'Hoàng Gia Bảo',
                'gender' => 'MALE',
                'birth_date' => '2000-01-15',
                'email' => 'bao.hoang@smarthr.vn',
                'phone' => '0978112233',
                'citizen_id' => '079200009900',
                'hometown' => 'Cần Thơ',
                'permanent_address' => 'TP. Cần Thơ',
                'temporary_address' => 'Đường Lê Văn Sỹ, Quận 3, TP.HCM',
                'join_date' => '2025-01-10',
                'probation_end_date' => '2025-03-10',
                'status' => 'OFFICIAL',
                'is_pregnant' => false,
                'children_count' => 0,
                'tax_dependents_count' => 0,
                'department_id' => $deptRetailId,
                'position_id' => $posRetailStaffId,
                'salary_base' => 10200000.00,
                'contract_type' => 'OFFICIAL',
                'user_role' => 'EMPLOYEE',
            ],

            // EMP020: Nhân viên trực ngày Lễ Quốc khánh 02/09/2026 (Hưởng lương 300% ngày lễ)
            [
                'user_code' => 'EMP020',
                'attendance_code' => '1020',
                'full_name' => 'Ngô Thanh Tùng',
                'gender' => 'MALE',
                'birth_date' => '1993-08-08',
                'email' => 'tung.ngo@smarthr.vn',
                'phone' => '0903889900',
                'citizen_id' => '079093001133',
                'hometown' => 'Tây Ninh',
                'permanent_address' => 'TP. Tây Ninh',
                'temporary_address' => 'Đường Cộng Hòa, Tân Bình, TP.HCM',
                'join_date' => '2023-04-01',
                'probation_end_date' => '2023-06-01',
                'status' => 'OFFICIAL',
                'is_pregnant' => false,
                'children_count' => 1,
                'tax_dependents_count' => 1,
                'department_id' => $deptLogisticsId,
                'position_id' => $posWarehouseNightId,
                'salary_base' => 15000000.00,
                'contract_type' => 'OFFICIAL',
                'user_role' => 'EMPLOYEE',
            ],
        ];

        $seededEmpMap = [];

        foreach ($employeesData as $emp) {
            $empId = DB::table('employees')->insertGetId([
                'tenant_id' => $tenantId,
                'user_code' => $emp['user_code'],
                'attendance_code' => $emp['attendance_code'],
                'full_name' => $emp['full_name'],
                'gender' => $emp['gender'],
                'birth_date' => $emp['birth_date'],
                'email' => $emp['email'],
                'phone' => $emp['phone'],
                'citizen_id' => $emp['citizen_id'],
                'encrypted_citizen_id' => 'ENC_' . $emp['citizen_id'],
                'hometown' => $emp['hometown'],
                'permanent_address' => $emp['permanent_address'],
                'temporary_address' => $emp['temporary_address'],
                'join_date' => $emp['join_date'],
                'probation_end_date' => $emp['probation_end_date'],
                'is_pregnant' => $emp['is_pregnant'],
                'pregnancy_start_date' => $emp['pregnancy_start_date'] ?? null,
                'pregnancy_weeks_at_start' => $emp['pregnancy_weeks_at_start'] ?? null,
                'expected_due_date' => $emp['expected_due_date'] ?? null,
                'children_count' => $emp['children_count'],
                'tax_dependents_count' => $emp['tax_dependents_count'],
                'status' => $emp['status'],
                'resigned_date' => null,
                'resignation_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $seededEmpMap[$emp['user_code']] = $empId;

            // Tài khoản đăng nhập hệ thống (users)
            DB::table('users')->insert([
                'tenant_id' => $tenantId,
                'employee_id' => $empId,
                'name' => $emp['full_name'],
                'email' => $emp['email'],
                'password' => Hash::make('password'),
                'role' => $emp['user_role'],
                'avatar' => 'https://api.dicebear.com/7.x/bottts/svg?seed=' . $emp['user_code'],
                'theme_preference' => 'light',
                'locale_preference' => 'vi',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Career Timeline
            DB::table('employee_career_timelines')->insert([
                'employee_id' => $empId,
                'event_type' => 'ONBOARDING',
                'department_id' => $emp['department_id'],
                'position_id' => $emp['position_id'],
                'job_title' => DB::table('positions')->where('id', $emp['position_id'])->value('name') ?? 'Nhân viên',
                'manager_id' => null,
                'effective_date' => $emp['join_date'],
                'end_date' => null,
                'decision_number' => 'QD-TD/' . substr($emp['join_date'], 0, 4) . '/' . $emp['user_code'],
                'decision_date' => $emp['join_date'],
                'notes' => 'Tiếp nhận nhân sự theo thỏa thuận tuyển dụng',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Mức lương theo Effective Dating (employee_salaries)
            DB::table('employee_salaries')->insert([
                'tenant_id' => $tenantId,
                'employee_id' => $empId,
                'base_salary' => $emp['salary_base'],
                'insurance_salary' => $emp['status'] === 'PROBATION' ? null : $emp['salary_base'],
                'contract_type' => $emp['contract_type'],
                'effective_date' => $emp['join_date'],
                'end_date' => null,
                'notes' => 'Mức lương cơ bản thỏa thuận khi gia nhập',
                'is_locked' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Hợp đồng lao động (employee_contracts)
            $contractType = ($emp['contract_type'] === 'PROBATION') ? 'PROBATION' : 'DEFINITE_TERM';
            $contractEndDate = ($emp['contract_type'] === 'PROBATION') 
                ? $emp['probation_end_date'] 
                : ($emp['user_code'] === 'EMP016' ? '2026-09-30' : Carbon::parse($emp['join_date'])->addYears(3)->format('Y-m-d'));

            DB::table('employee_contracts')->insert([
                'employee_id' => $empId,
                'contract_number' => 'HDLD/' . substr($emp['join_date'], 0, 4) . '/' . $emp['user_code'],
                'contract_type' => $contractType,
                'sign_date' => $emp['join_date'],
                'start_date' => $emp['join_date'],
                'end_date' => $contractEndDate,
                'encrypted_salary_data' => null,
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Quỹ phép năm (leave_balances)
            $baseLeave = 12.0;
            $seniorityLeave = ($emp['user_code'] === 'EMP012') ? 2.0 : 0.0;
            $totalLeave = $baseLeave + $seniorityLeave;
            $carriedOver = in_array($emp['user_code'], ['EMP008', 'EMP010', 'EMP012']) ? 3.0 : 0.0;

            $leaveBalanceId = DB::table('leave_balances')->insertGetId([
                'tenant_id' => $tenantId,
                'employee_id' => $empId,
                'year' => 2026,
                'leave_type' => 'ANNUAL',
                'carried_over_days' => $carriedOver,
                'carried_over_expiry_date' => '2026-03-31',
                'statutory_allocated_days' => $baseLeave,
                'seniority_bonus_days' => $seniorityLeave,
                'welfare_bonus_days' => 1.0, // Thưởng ngày phép sinh nhật theo quy chế
                'total_entitled_days' => $totalLeave + 1.0 + $carriedOver,
                'accrued_days_to_date' => 9.0,
                'used_days' => ($emp['user_code'] === 'EMP018') ? 3.0 : 1.0,
                'pending_days' => 0.0,
                'remaining_days' => ($totalLeave + 1.0 + $carriedOver) - (($emp['user_code'] === 'EMP018') ? 3.0 : 1.0),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Lịch sử biến động phép (leave_transactions)
            DB::table('leave_transactions')->insert([
                'leave_balance_id' => $leaveBalanceId,
                'transaction_type' => 'ACCRUAL',
                'days' => $totalLeave,
                'reference_id' => null,
                'description' => 'Khởi tạo định mức phép năm 2026 theo quy chuẩn BLLĐ',
                'created_at' => $now,
            ]);
        }

        // =========================================================================
        // 6. CON NHỎ BỔ SUNG & PHÚC LỢI GIA ĐÌNH (employee_children)
        // =========================================================================
        // Bé của Lan (EMP009): 10 tháng tuổi (Sinh 11/2025) -> Hưởng Điều 137 BLLĐ
        DB::table('employee_children')->insert([
            'tenant_id' => $tenantId,
            'employee_id' => $seededEmpMap['EMP009'],
            'full_name' => 'Phạm Minh Khang',
            'gender' => 'MALE',
            'birth_date' => Carbon::now()->subMonths(10)->format('Y-m-d'),
            'birth_certificate_number' => 'KS-079/2025/0012',
            'is_tax_dependent' => true,
            'tax_dependent_code' => '8012345678',
            'tax_dependent_start_date' => Carbon::now()->subMonths(10)->format('Y-m-d'),
            'tax_dependent_end_date' => null,
            'has_monthly_allowance' => true,
            'monthly_allowance_amount' => 300000.00,
            'notes' => 'Chế độ con nhỏ dưới 12 tháng theo Điều 137 BLLĐ',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Bé của Hùng (EMP010): 2 con (3 tuổi và 7 tuổi)
        DB::table('employee_children')->insert([
            [
                'tenant_id' => $tenantId,
                'employee_id' => $seededEmpMap['EMP010'],
                'full_name' => 'Đỗ Minh Anh',
                'gender' => 'FEMALE',
                'birth_date' => Carbon::now()->subYears(3)->format('Y-m-d'),
                'birth_certificate_number' => 'KS-079/2023/0055',
                'is_tax_dependent' => true,
                'tax_dependent_code' => '8023456789',
                'tax_dependent_start_date' => Carbon::now()->subYears(3)->format('Y-m-d'),
                'tax_dependent_end_date' => null,
                'has_monthly_allowance' => true,
                'monthly_allowance_amount' => 300000.00,
                'notes' => 'Trợ cấp mầm non',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'employee_id' => $seededEmpMap['EMP010'],
                'full_name' => 'Đỗ Quốc Bảo',
                'gender' => 'MALE',
                'birth_date' => Carbon::now()->subYears(7)->format('Y-m-d'),
                'birth_certificate_number' => 'KS-079/2019/0088',
                'is_tax_dependent' => true,
                'tax_dependent_code' => '8034567890',
                'tax_dependent_start_date' => Carbon::now()->subYears(7)->format('Y-m-d'),
                'tax_dependent_end_date' => null,
                'has_monthly_allowance' => true,
                'monthly_allowance_amount' => 300000.00,
                'notes' => 'Trợ cấp con nhỏ',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 2 con của Đạt (EMP012)
        DB::table('employee_children')->insert([
            [
                'tenant_id' => $tenantId,
                'employee_id' => $seededEmpMap['EMP012'],
                'full_name' => 'Trần Quang Khải',
                'gender' => 'MALE',
                'birth_date' => Carbon::now()->subYears(8)->format('Y-m-d'),
                'birth_certificate_number' => 'KS-001/2018/0034',
                'is_tax_dependent' => true,
                'tax_dependent_code' => '8045678901',
                'tax_dependent_start_date' => Carbon::now()->subYears(8)->format('Y-m-d'),
                'tax_dependent_end_date' => null,
                'has_monthly_allowance' => true,
                'monthly_allowance_amount' => 300000.00,
                'notes' => 'Học sinh tiểu học',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => $tenantId,
                'employee_id' => $seededEmpMap['EMP012'],
                'full_name' => 'Trần Ngọc Hân',
                'gender' => 'FEMALE',
                'birth_date' => Carbon::now()->subYears(4)->format('Y-m-d'),
                'birth_certificate_number' => 'KS-001/2022/0067',
                'is_tax_dependent' => true,
                'tax_dependent_code' => '8056789012',
                'tax_dependent_start_date' => Carbon::now()->subYears(4)->format('Y-m-d'),
                'tax_dependent_end_date' => null,
                'has_monthly_allowance' => true,
                'monthly_allowance_amount' => 300000.00,
                'notes' => 'Trợ cấp mẫu giáo',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // =========================================================================
        // 7. ĐƠN TỪ QUY TRÌNH (Approval Requests) - Các Tình Huống Doanh Nghiệp Thật
        // =========================================================================
        // 7.1 Đơn thỏa thuận tự nguyện ca đêm con nhỏ của Lan (EMP009) theo Điều 137 Khoản 1 Điểm b BLLĐ -> ĐÃ DUYỆT
        $reqNightConsentId = DB::table('approval_requests')->insertGetId([
            'tenant_id' => $tenantId,
            'employee_id' => $seededEmpMap['EMP009'],
            'request_type' => 'NIGHT_SHIFT',
            'form_payload' => json_encode([
                'reason' => 'Người lao động có nguyện vọng làm thêm ca đêm để chăm lo thu nhập gia đình, cam kết đủ điều kiện sức khỏe',
                'shift_code' => 'SHIFT_NIGHT_FULL',
                'effective_from' => '2026-09-01',
                'effective_to' => '2026-09-30',
            ]),
            'is_voluntary_consent' => true,
            'voluntary_agreement_file' => 'consents/emp009_voluntary_consent_night.pdf',
            'voluntary_agreement_url' => '/storage/consents/emp009_voluntary_consent_night.pdf',
            'current_step' => 2,
            'snapshot_approvers' => json_encode([
                ['step' => 1, 'role' => 'DEPARTMENT_MANAGER', 'name' => 'Trưởng ca Bán Lẻ', 'status' => 'APPROVED'],
                ['step' => 2, 'role' => 'HR_ADMIN', 'name' => 'HR Manager', 'status' => 'APPROVED'],
            ]),
            'status' => 'APPROVED',
            'created_at' => Carbon::create(2026, 9, 1, 9, 0),
            'updated_at' => Carbon::create(2026, 9, 1, 14, 30),
        ]);

        // 7.2 Đơn sửa công của Trí (EMP017) do quên quẹt thẻ ra ngày 04/09 -> ĐANG CHỜ DUYỆT (PENDING)
        DB::table('approval_requests')->insert([
            'tenant_id' => $tenantId,
            'employee_id' => $seededEmpMap['EMP017'],
            'request_type' => 'TIMESHEET_EDIT',
            'form_payload' => json_encode([
                'date' => '2026-09-04',
                'proposed_in' => '07:58:00',
                'proposed_out' => '17:05:00',
                'reason' => 'Cuối ca hỗ trợ bàn giao kiểm kê kho hàng đột xuất, vội về đón con nên quên quẹt thẻ máy chấm công lúc 17:00',
            ]),
            'is_voluntary_consent' => false,
            'voluntary_agreement_file' => null,
            'voluntary_agreement_url' => null,
            'current_step' => 1,
            'snapshot_approvers' => json_encode([
                ['step' => 1, 'role' => 'DEPARTMENT_MANAGER', 'name' => 'Trưởng bộ phận'],
            ]),
            'status' => 'PENDING',
            'created_at' => Carbon::create(2026, 9, 5, 8, 30),
            'updated_at' => Carbon::create(2026, 9, 5, 8, 30),
        ]);

        // 7.3 Đơn xin nghỉ phép năm 3 ngày của Nga (EMP018) -> ĐÃ DUYỆT (APPROVED)
        DB::table('approval_requests')->insert([
            'tenant_id' => $tenantId,
            'employee_id' => $seededEmpMap['EMP018'],
            'request_type' => 'LEAVE',
            'form_payload' => json_encode([
                'start_date' => '2026-09-07',
                'end_date' => '2026-09-09',
                'leave_type' => 'ANNUAL',
                'days' => 3,
                'reason' => 'Giải quyết việc riêng gia đình và về quê thăm bố mẹ',
            ]),
            'is_voluntary_consent' => false,
            'voluntary_agreement_file' => null,
            'voluntary_agreement_url' => null,
            'current_step' => 1,
            'snapshot_approvers' => json_encode([
                ['step' => 1, 'role' => 'DEPARTMENT_MANAGER', 'name' => 'Kế toán trưởng'],
            ]),
            'status' => 'APPROVED',
            'created_at' => Carbon::create(2026, 9, 3, 10, 0),
            'updated_at' => Carbon::create(2026, 9, 4, 11, 0),
        ]);

        // 7.4 Yêu cầu Đổi ca giữa Bảo (EMP019) và Long (EMP008) -> ĐÃ DUYỆT (APPROVED)
        $rosterLong = DB::table('rosters')->insertGetId([
            'tenant_id' => $tenantId,
            'employee_id' => $seededEmpMap['EMP008'],
            'shift_id' => $shiftSplitFnbId,
            'roster_date' => '2026-09-05',
            'is_published' => true,
            'status' => 'SWAPPED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $rosterBao = DB::table('rosters')->insertGetId([
            'tenant_id' => $tenantId,
            'employee_id' => $seededEmpMap['EMP019'],
            'shift_id' => $shiftAfternoonId,
            'roster_date' => '2026-09-05',
            'is_published' => true,
            'status' => 'SWAPPED',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('shift_swap_requests')->insert([
            'requester_id' => $seededEmpMap['EMP019'],
            'target_employee_id' => $seededEmpMap['EMP008'],
            'requester_roster_id' => $rosterBao,
            'target_roster_id' => $rosterLong,
            'swap_type' => 'SWAP',
            'reason' => 'Em có việc bận gia đình buổi chiều, xin phép đổi sang làm ca gãy ban ngày',
            'is_legal_compliant' => true,
            'violation_warnings' => null,
            'status' => 'APPROVED',
            'created_at' => Carbon::create(2026, 9, 4, 15, 0),
            'updated_at' => Carbon::create(2026, 9, 4, 17, 30),
        ]);

        // =========================================================================
        // 8. PHÂN CA (Rosters) & DỮ LIỆU QUẸT THẺ (Raw Logs) THÁNG 09/2026
        // =========================================================================
        // 8.1 Phân ca cho các nhân viên từ 01/09 -> 10/09
        $rosterAssignments = [
            // EMP008 (Long): Ca gãy FnB
            ['emp_id' => $seededEmpMap['EMP008'], 'shift_id' => $shiftSplitFnbId],
            // EMP009 (Lan): Ca đêm (Đã có đơn đồng thuận)
            ['emp_id' => $seededEmpMap['EMP009'], 'shift_id' => $shiftNightId],
            // EMP010 (Hùng): Ca đêm chuyên trách
            ['emp_id' => $seededEmpMap['EMP010'], 'shift_id' => $shiftNightId],
            // EMP011 (Bích - Bầu 7 tháng): Ca hành chính rút ngắn 1h
            ['emp_id' => $seededEmpMap['EMP011'], 'shift_id' => $shiftStandardId],
            // EMP012 (Đạt - Thâm niên): Ca hành chính
            ['emp_id' => $seededEmpMap['EMP012'], 'shift_id' => $shiftStandardId],
            // EMP014 (Anh - Đi muộn): Ca chiều
            ['emp_id' => $seededEmpMap['EMP014'], 'shift_id' => $shiftAfternoonId],
            // EMP015 (Sơn - OT dồn dập): Ca đêm kho vận
            ['emp_id' => $seededEmpMap['EMP015'], 'shift_id' => $shiftNightId],
            // EMP016 (Linh - Kế toán): Ca hành chính
            ['emp_id' => $seededEmpMap['EMP016'], 'shift_id' => $shiftStandardId],
            // EMP017 (Trí): Ca hành chính
            ['emp_id' => $seededEmpMap['EMP017'], 'shift_id' => $shiftStandardId],
            // EMP020 (Tùng): Ca đêm kho vận
            ['emp_id' => $seededEmpMap['EMP020'], 'shift_id' => $shiftNightId],
        ];

        for ($d = 1; $d <= 10; $d++) {
            $dateStr = sprintf('2026-09-%02d', $d);
            $cDate = Carbon::parse($dateStr);
            if ($cDate->isSunday()) {
                continue; // Nghỉ chủ nhật tuần
            }

            foreach ($rosterAssignments as $ra) {
                // Tránh trùng lặp với swap roster ngày 05/09 đã tạo ở trên
                if ($d === 5 && in_array($ra['emp_id'], [$seededEmpMap['EMP008'], $seededEmpMap['EMP019']])) {
                    continue;
                }
                DB::table('rosters')->updateOrInsert(
                    ['employee_id' => $ra['emp_id'], 'roster_date' => $dateStr],
                    [
                        'tenant_id' => $tenantId,
                        'shift_id' => $ra['shift_id'],
                        'is_published' => true,
                        'status' => 'SCHEDULED',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        // 8.2 Dữ liệu quẹt thẻ thực tế (Raw Attendance Logs):
        $rawLogs = [
            // Ngày 02/09/2026 (Quốc khánh chính lễ - Đi làm lễ hưởng 3.0x theo Điều 98 BLLĐ):
            // EMP020 (Tùng) quẹt thẻ làm việc ngày lễ: 08:00 - 17:00
            ['emp_id' => $seededEmpMap['EMP020'], 'time' => '2026-09-02 08:00:10', 'state' => 'CHECK_IN', 'dev' => 'ZK_HCM_01'],
            ['emp_id' => $seededEmpMap['EMP020'], 'time' => '2026-09-02 17:05:22', 'state' => 'CHECK_OUT', 'dev' => 'ZK_HCM_01'],

            // Ngày 03/09/2026:
            // EMP007 (Tuấn - Thử việc): Đúng giờ 08:00 - 17:00
            ['emp_id' => $seededEmpMap['EMP007'], 'time' => '2026-09-03 07:55:10', 'state' => 'CHECK_IN', 'dev' => 'ZK_HCM_01'],
            ['emp_id' => $seededEmpMap['EMP007'], 'time' => '2026-09-03 17:02:45', 'state' => 'CHECK_OUT', 'dev' => 'ZK_HCM_01'],

            // EMP010 (Hùng - Ca đêm): Vào 21:55 ngày 03/09, ra 06:05 ngày 04/09 (Quẹt xuyên đêm)
            ['emp_id' => $seededEmpMap['EMP010'], 'time' => '2026-09-03 21:55:00', 'state' => 'CHECK_IN', 'dev' => 'ZK_HCM_02'],
            ['emp_id' => $seededEmpMap['EMP010'], 'time' => '2026-09-04 06:05:00', 'state' => 'CHECK_OUT', 'dev' => 'ZK_HCM_02'],

            // EMP011 (Bích - Bầu 7 tháng): Vào 08:00, về sớm 16:00 (Rút ngắn 1 tiếng theo Điều 137 BLLĐ)
            ['emp_id' => $seededEmpMap['EMP011'], 'time' => '2026-09-03 08:00:00', 'state' => 'CHECK_IN', 'dev' => 'ZK_HN_01'],
            ['emp_id' => $seededEmpMap['EMP011'], 'time' => '2026-09-03 16:00:00', 'state' => 'CHECK_OUT', 'dev' => 'ZK_HN_01'],

            // Ngày 04/09/2026:
            // EMP014 (Anh): Ca chiều (14:00 - 22:00) nhưng đến 14:35 (Muộn 35 phút -> nấc 3)
            ['emp_id' => $seededEmpMap['EMP014'], 'time' => '2026-09-04 14:35:00', 'state' => 'CHECK_IN', 'dev' => 'ZK_HCM_01'],
            ['emp_id' => $seededEmpMap['EMP014'], 'time' => '2026-09-04 22:05:00', 'state' => 'CHECK_OUT', 'dev' => 'ZK_HCM_01'],

            // EMP017 (Trí): Quên quẹt thẻ ra (Chỉ có check-in lúc 07:58)
            ['emp_id' => $seededEmpMap['EMP017'], 'time' => '2026-09-04 07:58:00', 'state' => 'CHECK_IN', 'dev' => 'ZK_HCM_01'],

            // EMP015 (Sơn - OT dồn dập): Làm ca đêm 22:00 đến 06:00, làm thêm OT đêm đến 09:00 sáng (+3h OT đêm)
            ['emp_id' => $seededEmpMap['EMP015'], 'time' => '2026-09-04 22:00:00', 'state' => 'CHECK_IN', 'dev' => 'ZK_HCM_02'],
            ['emp_id' => $seededEmpMap['EMP015'], 'time' => '2026-09-05 09:00:00', 'state' => 'CHECK_OUT', 'dev' => 'ZK_HCM_02'],

            // Ngày 07/09/2026:
            // EMP014 (Anh): Lại đi muộn 25 phút (Lần vi phạm thứ 4 trong tháng -> Phạt tần suất)
            ['emp_id' => $seededEmpMap['EMP014'], 'time' => '2026-09-07 14:25:00', 'state' => 'CHECK_IN', 'dev' => 'ZK_HCM_01'],
            ['emp_id' => $seededEmpMap['EMP014'], 'time' => '2026-09-07 22:00:00', 'state' => 'CHECK_OUT', 'dev' => 'ZK_HCM_01'],
        ];

        foreach ($rawLogs as $idx => $log) {
            DB::table('raw_attendance_logs')->insert([
                'tenant_id' => $tenantId,
                'branch_id' => str_starts_with($log['dev'], 'ZK_HCM') ? $branchHcmId : $branchHnId,
                'employee_id' => $log['emp_id'],
                'punch_time' => $log['time'],
                'punch_state' => $log['state'],
                'device_sn' => $log['dev'],
                'verification_mode' => 'FINGERPRINT',
                'raw_payload_hash' => hash('sha256', 'raw_log_' . $idx . '_' . $log['time']),
                'sync_batch_id' => 'BATCH_ENT_' . substr($log['time'], 0, 10),
                'created_at' => Carbon::parse($log['time']),
            ]);
        }

        // =========================================================================
        // 9. CẢNH BÁO TUÂN THỦ THỜI GIAN THỰC (Compliance Alerts)
        // =========================================================================
        DB::table('compliance_alerts')->insert([
            // 9.1 Cảnh báo ĐỎ: Vượt trần 40 giờ OT/tháng (Điều 107 BLLĐ)
            [
                'tenant_id' => $tenantId,
                'employee_id' => $seededEmpMap['EMP015'],
                'alert_type' => 'OT_BREACH_RED_40H',
                'severity' => 'CRITICAL',
                'title' => 'Cảnh báo Đỏ: Nhân viên Phạm Hồng Sơn đã làm thêm 42.5 giờ/tháng',
                'description' => 'Số giờ làm thêm trong tháng đã vượt trần 40 giờ theo quy định tại Điều 107 Khoản 2 Điểm b Bộ luật Lao động 2019 và Nghị định 145/2020/NĐ-CP. Đề nghị ngưng phân công làm thêm giờ ngay lập tức.',
                'is_resolved' => false,
                'resolved_by' => null,
                'resolved_at' => null,
                'created_at' => Carbon::create(2026, 8, 31, 17, 0),
                'updated_at' => Carbon::create(2026, 8, 31, 17, 0),
            ],

            // 9.2 Cảnh báo VÀNG: Chạm ngưỡng 35 giờ OT trong tháng
            [
                'tenant_id' => $tenantId,
                'employee_id' => $seededEmpMap['EMP015'],
                'alert_type' => 'OT_WARNING_YELLOW_35H',
                'severity' => 'WARNING',
                'title' => 'Cảnh báo Vàng: Nhân viên Phạm Hồng Sơn sắp chạm trần OT tháng (Hiện đạt 36.0 giờ)',
                'description' => 'Nhân viên đã tích lũy 36 giờ làm thêm, chỉ còn cách trần pháp luật 4.0 giờ. Bộ phận quản lý ca cần rà soát lại phương án phân ca thay thế.',
                'is_resolved' => false,
                'resolved_by' => null,
                'resolved_at' => null,
                'created_at' => Carbon::create(2026, 9, 8, 11, 0),
                'updated_at' => Carbon::create(2026, 9, 8, 11, 0),
            ],

            // 9.3 Cảnh báo VÀNG: Hợp đồng lao động sắp hết hạn trước 30 ngày (Điều 45 BLLĐ)
            [
                'tenant_id' => $tenantId,
                'employee_id' => $seededEmpMap['EMP016'],
                'alert_type' => 'CONTRACT_EXPIRING_30D',
                'severity' => 'WARNING',
                'title' => 'Hết hạn HĐLĐ trước 30 ngày: Nguyễn Phương Linh (Hết hạn ngày 30/09/2026)',
                'description' => 'Hợp đồng lao động xác định thời hạn 2 năm của nhân viên sẽ hết hạn vào ngày 30/09/2026 (còn 19 ngày). Phòng Nhân sự cần tiến hành đánh giá hiệu quả công việc và gửi thông báo gia hạn theo quy định.',
                'is_resolved' => false,
                'resolved_by' => null,
                'resolved_at' => null,
                'created_at' => Carbon::create(2026, 9, 1, 8, 0),
                'updated_at' => Carbon::create(2026, 9, 1, 8, 0),
            ],

            // 9.4 Cảnh báo INFO: Rủi ro kiệt sức (Burnout Risk) do làm việc liên tục 12 ca
            [
                'tenant_id' => $tenantId,
                'employee_id' => $seededEmpMap['EMP010'],
                'alert_type' => 'BURNOUT_RISK_HIGH',
                'severity' => 'INFO',
                'title' => 'Cảnh báo mệt mỏi: Đỗ Văn Hùng trực ca đêm 6 đêm liên tiếp',
                'description' => 'Nhân viên có chuỗi làm ca đêm liên tục. Khuyến nghị sắp xếp ít nhất 01 ngày nghỉ liên tục (24 giờ) theo Điều 111 BLLĐ.',
                'is_resolved' => true,
                'resolved_by' => 1, // Admin đã giải quyết bằng việc đổi ca
                'resolved_at' => Carbon::create(2026, 9, 6, 14, 0),
                'created_at' => Carbon::create(2026, 9, 5, 10, 0),
                'updated_at' => Carbon::create(2026, 9, 6, 14, 0),
            ],
        ]);

        // =========================================================================
        // 10. BẢNG LƯƠNG & KHÓA KỲ LƯƠNG THỰC TẾ (Payroll Periods & Payroll Records)
        // =========================================================================
        // Kỳ tháng 08/2026: ĐÃ CHỐT VÀ KHÓA SỔ (status: LOCKED, is_locked: true)
        $periodAugId = DB::table('payroll_periods')->insertGetId([
            'tenant_id' => $tenantId,
            'month_year' => '2026-08',
            'policy_version_id' => 1,
            'status' => 'LOCKED',
            'is_locked' => true,
            'locked_by' => 1,
            'locked_at' => Carbon::create(2026, 9, 5, 18, 0),
            'created_at' => Carbon::create(2026, 8, 31, 8, 0),
            'updated_at' => Carbon::create(2026, 9, 5, 18, 0),
        ]);

        // Kỳ tháng 09/2026: ĐANG CHẠY DỰ THẢO (status: DRAFT, is_locked: false)
        DB::table('payroll_periods')->insert([
            'tenant_id' => $tenantId,
            'month_year' => '2026-09',
            'policy_version_id' => 1,
            'status' => 'DRAFT',
            'is_locked' => false,
            'locked_by' => null,
            'locked_at' => null,
            'created_at' => Carbon::create(2026, 9, 1, 8, 0),
            'updated_at' => Carbon::create(2026, 9, 1, 8, 0),
        ]);

        // Tạo bảng lương tháng 08/2026 cho 5 nhân sự đại diện với đầy đủ trường mã hóa DEK AES-256-GCM
        $payrollSamples = [
            // EMP001 (An - Quản lý): Lương cao, chịu thuế TNCN bậc cao
            [
                'emp_id' => 1,
                'gross' => 50000000.00,
                'pos_allowance' => 5000000.00,
                'child_allowance' => 300000.00,
                'cert_allowance' => 1000000.00,
                'tax_exempt_ot' => 4500000.00, // OT miễn thuế
                'taxable_ot' => 3000000.00,
                'bhxh' => 3744000.00, // Đóng theo trần 46.8tr
                'bhyt' => 702000.00,
                'bhtn' => 500000.00,
                'pit' => 4250000.00, // Biểu thuế TNCN 5 bậc
                'net' => 48804000.00,
            ],
            // EMP002 (Mai - Con nhỏ 8 tháng): Có trợ cấp con nhỏ
            [
                'emp_id' => 2,
                'gross' => 28000000.00,
                'pos_allowance' => 2000000.00,
                'child_allowance' => 600000.00, // 2 con
                'cert_allowance' => 500000.00,
                'tax_exempt_ot' => 0.00,
                'taxable_ot' => 0.00,
                'bhxh' => 2240000.00,
                'bhyt' => 420000.00,
                'bhtn' => 280000.00,
                'pit' => 450000.00,
                'net' => 27710000.00,
            ],
            // EMP008 (Long - Trưởng ca FnB): Có phụ cấp trách nhiệm ca kíp
            [
                'emp_id' => $seededEmpMap['EMP008'],
                'gross' => 19000000.00,
                'pos_allowance' => 2000000.00,
                'child_allowance' => 300000.00,
                'cert_allowance' => 0.00,
                'tax_exempt_ot' => 1200000.00,
                'taxable_ot' => 800000.00,
                'bhxh' => 1360000.00,
                'bhyt' => 255000.00,
                'bhtn' => 170000.00,
                'pit' => 120000.00,
                'net' => 20595000.00,
            ],
            // EMP010 (Hùng - Ca đêm): Có phụ cấp ca đêm 30% và OT đêm
            [
                'emp_id' => $seededEmpMap['EMP010'],
                'gross' => 16500000.00,
                'pos_allowance' => 1500000.00,
                'child_allowance' => 600000.00,
                'cert_allowance' => 0.00,
                'tax_exempt_ot' => 2500000.00,
                'taxable_ot' => 1500000.00,
                'bhxh' => 1080000.00,
                'bhyt' => 202500.00,
                'bhtn' => 135000.00,
                'pit' => 0.00, // Dưới ngưỡng nộp thuế sau khi trừ 2 NPT
                'net' => 21182500.00,
            ],
            // EMP015 (Sơn - OT dồn dập vượt trần): Khoản làm thêm giờ lớn
            [
                'emp_id' => $seededEmpMap['EMP015'],
                'gross' => 17000000.00,
                'pos_allowance' => 1500000.00,
                'child_allowance' => 300000.00,
                'cert_allowance' => 0.00,
                'tax_exempt_ot' => 4800000.00, // 42h OT với tỷ lệ miễn thuế cao
                'taxable_ot' => 3200000.00,
                'bhxh' => 1120000.00,
                'bhyt' => 210000.00,
                'bhtn' => 140000.00,
                'pit' => 180000.00,
                'net' => 25150000.00,
            ],
        ];

        foreach ($payrollSamples as $ps) {
            $breakdown = [
                'base_salary' => $ps['gross'] - $ps['pos_allowance'],
                'position_allowance' => $ps['pos_allowance'],
                'child_allowance' => $ps['child_allowance'],
                'tax_exempt_ot' => $ps['tax_exempt_ot'],
                'taxable_ot' => $ps['taxable_ot'],
                'insurance_deductions' => [
                    'bhxh' => $ps['bhxh'],
                    'bhyt' => $ps['bhyt'],
                    'bhtn' => $ps['bhtn'],
                ],
                'pit_tax' => $ps['pit'],
                'net_salary' => $ps['net'],
            ];

            // Mã hóa AES-256-GCM bằng DEK dẫn xuất từ tệp bản quyền Ed25519
            $encryptedDetails = $licenseService->encryptSalaryDetails($breakdown);

            DB::table('payroll_records')->updateOrInsert(
                ['employee_id' => $ps['emp_id'], 'month_year' => '2026-08'],
                [
                    'tenant_id' => $tenantId,
                    'gross_salary' => $ps['gross'],
                    'position_allowance_amount' => $ps['pos_allowance'],
                    'child_allowance_amount' => $ps['child_allowance'],
                    'certificate_allowance_amount' => $ps['cert_allowance'],
                    'tax_exempt_ot_amount' => $ps['tax_exempt_ot'],
                    'taxable_ot_amount' => $ps['taxable_ot'],
                    'social_insurance_employee' => $ps['bhxh'],
                    'health_insurance_employee' => $ps['bhyt'],
                    'unemployment_insurance_employee' => $ps['bhtn'],
                    'pit_tax_amount' => $ps['pit'],
                    'net_salary' => $ps['net'],
                    'encrypted_salary_details' => $encryptedDetails,
                    'status' => 'APPROVED',
                    'created_at' => Carbon::create(2026, 8, 31, 18, 0),
                    'updated_at' => Carbon::create(2026, 9, 5, 18, 0),
                ]
            );
        }

        // =========================================================================
        // 11. TÍNH TOÁN BẢNG CÔNG CHO CÁC NHÂN SỰ MỚI (Pipeline Chấm Công)
        // =========================================================================
        $fromDate = Carbon::create(2026, 9, 1);
        $toDate = Carbon::create(2026, 9, 7);

        // Chạy Pipeline tính công cho các nhân sự mới
        $pipelineService->calculateRange($tenantId, $fromDate, $toDate);

        // Tổng hợp công tháng 09/2026
        foreach (array_values($seededEmpMap) as $newEmpId) {
            $monthlyService->aggregateMonth($tenantId, $newEmpId, '2026-09');
        }
    }
}
