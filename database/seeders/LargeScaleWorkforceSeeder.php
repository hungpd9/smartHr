<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * LargeScaleWorkforceSeeder - Bộ dữ liệu mở rộng quy mô 100 nhân sự hoạt động đa dạng
 * 
 * Đáp ứng đầy đủ các yêu cầu nghiệp vụ thực tế:
 * 1. Đa dạng thâm niên:
 *    - Lâu năm (> 5 - 8 năm): Hợp đồng không xác định thời hạn, cộng ngày phép thâm niên Đ.114 BLLĐ.
 *    - Trung hạn (2 - 4 năm): Lực lượng chuyên viên nòng cốt.
 *    - Mới vào (< 1 năm): Ký hợp đồng có thời hạn.
 *    - Thử việc (PROBATION): 85% lương, thuế 10%, chưa BHXH.
 * 2. Đa dạng hoàn cảnh gia đình & chế độ thai sản:
 *    - Người có nhiều con (2, 3, 4 con) kèm bản ghi con cái và mã số thuế người phụ thuộc.
 *    - Người mới có thai (< 28 tuần / dưới tháng thứ 7).
 *    - Người mang thai từ tháng thứ 7 (>= 28 tuần): Giảm 1h làm việc/ngày, cấm ca đêm/OT theo Đ.137 BLLĐ.
 *    - Người nuôi con nhỏ dưới 12 tháng (cho con bú): Nghỉ 60 phút có lương hàng ngày theo Đ.137 BLLĐ.
 *    - Nhân viên nghỉ thai sản 6 tháng (status = ON_LEAVE).
 * 3. Phân bổ đều 4 chi nhánh toàn quốc: Hà Nội, Đà Nẵng, Phú Quốc, TP. Hồ Chí Minh.
 */
class LargeScaleWorkforceSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $now = Carbon::now();

        // 1. Kiểm tra chi nhánh và phòng ban có sẵn
        $branches = DB::table('branches')->where('tenant_id', $tenantId)->get()->keyBy('code');
        $branchHnId = $branches['HN_HQ']->id ?? 1;
        $branchDnId = $branches['DN_HUB']->id ?? 2;
        $branchPqId = $branches['PQ_STORE']->id ?? 3;
        $branchHcmId = $branches['HCM_BRANCH']->id ?? 4;

        // Lấy danh sách phòng ban theo chi nhánh
        $deptHnIds = DB::table('departments')->where('tenant_id', $tenantId)->where('branch_id', $branchHnId)->pluck('id')->all();
        $deptDnIds = DB::table('departments')->where('tenant_id', $tenantId)->where('branch_id', $branchDnId)->pluck('id')->all();
        $deptPqIds = DB::table('departments')->where('tenant_id', $tenantId)->where('branch_id', $branchPqId)->pluck('id')->all();
        $deptHcmIds = DB::table('departments')->where('tenant_id', $tenantId)->where('branch_id', $branchHcmId)->pluck('id')->all();

        // Fallback nếu thiếu phòng ban
        if (empty($deptHnIds)) $deptHnIds = [1, 2, 3, 4, 13];
        if (empty($deptDnIds)) $deptDnIds = [5, 6, 7];
        if (empty($deptPqIds)) $deptPqIds = [8, 9];
        if (empty($deptHcmIds)) $deptHcmIds = [10, 11, 12];

        // Lấy danh sách chức vụ
        $positions = DB::table('positions')->where('tenant_id', $tenantId)->get()->keyBy('code');
        $posSeniorDevId = $positions['SENIOR_DEV']->id ?? 5;
        $posDevLeadId = $positions['DEV_LEAD']->id ?? 4;
        $posRetailStaffId = $positions['RETAIL_STAFF']->id ?? 8;
        $posRetailLeadId = $positions['STORE_LEAD']->id ?? 7;
        $posSalesSpecialistId = $positions['SALES_SPECIALIST']->id ?? 6;
        $posWarehouseNightId = $positions['WAREHOUSE_NIGHT']->id ?? 9;
        $posAccountantId = $positions['SENIOR_ACCOUNTANT']->id ?? 10;

        // 2. Dữ liệu Họ, Tên đệm và Tên tiếng Việt tự nhiên
        $familyNames = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Vũ', 'Phan', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương', 'Lý', 'Đinh', 'Đoàn'];
        $maleMiddleNames = ['Văn', 'Quốc', 'Đức', 'Hoàng', 'Minh', 'Hữu', 'Công', 'Đình', 'Thành', 'Bảo', 'Gia', 'Trọng'];
        $maleFirstNames = ['Nam', 'Tuấn', 'Dũng', 'Phong', 'Kiên', 'Huy', 'Tùng', 'Việt', 'Sơn', 'Quân', 'Thành', 'Long', 'Đạt', 'Bách', 'Hiếu', 'Thịnh', 'Khoa', 'Khang', 'Lâm'];
        
        $femaleMiddleNames = ['Thị', 'Phương', 'Thanh', 'Mai', 'Ngọc', 'Thu', 'Kim', 'Bảo', 'Ánh', 'Diệu', 'Mỹ', 'Hồng'];
        $femaleFirstNames = ['Lan', 'Hương', 'Hà', 'Trang', 'Yến', 'Nga', 'Linh', 'Thảo', 'Thủy', 'Oanh', 'Ngân', 'Hằng', 'Dung', 'Trúc', 'Châu', 'Quỳnh', 'Vân', 'Tuyết', 'Hiền'];

        $provinces = [
            'Hà Nội', 'TP. Hồ Chí Minh', 'Đà Nẵng', 'Hải Phòng', 'Cần Thơ',
            'Quảng Ninh', 'Bắc Ninh', 'Nghệ An', 'Hà Tĩnh', 'Thanh Hóa',
            'Thừa Thiên Huế', 'Quảng Nam', 'Khánh Hòa', 'Lâm Đồng', 'Bình Dương',
            'Đồng Nai', 'Bà Rịa - Vũng Tàu', 'Kiên Giang', 'An Giang', 'Tiền Giang'
        ];

        // 3. Chuẩn bị danh sách 81 nhân sự (EMP021 -> EMP101)
        // Mục tiêu:
        // - Lâu năm (2018 - 2021): 20 người
        // - Trung hạn (2022 - 2024): 40 người
        // - Mới vào (2025 - 2026): 15 người
        // - Thử việc (07 - 08/2026): 6 người
        // - Nữ: ~36 người, Nam: ~45 người
        // - Thai sản & Con nhỏ:
        //   + 6 người mang thai tháng thứ 7 (tuần 29-33)
        //   + 8 người mới mang thai (tuần 10-22)
        //   + 12 người nuôi con nhỏ dưới 12 tháng (cho con bú)
        //   + 3 người nghỉ thai sản 6 tháng (status = ON_LEAVE)
        //   + 25 người có nhiều con (2-4 con)

        $employeesToInsert = [];
        $childrenToInsert = [];
        $alertsToInsert = [];

        for ($idx = 21; $idx <= 101; $idx++) {
            $userCode = sprintf('EMP%03d', $idx);
            $attCode = (string) (1000 + $idx);

            // Xác định giới tính (Nữ: các chỉ số chẵn hoặc chỉ định, đảm bảo ~45% nữ)
            $isFemale = ($idx % 2 === 0) || ($idx >= 35 && $idx <= 55 && $idx % 3 === 0);
            $gender = $isFemale ? 'FEMALE' : 'MALE';

            // Sinh họ và tên
            $fName = $familyNames[($idx * 7) % count($familyNames)];
            if ($isFemale) {
                $mName = $femaleMiddleNames[($idx * 3) % count($femaleMiddleNames)];
                $lName = $femaleFirstNames[($idx * 5) % count($femaleFirstNames)];
            } else {
                $mName = $maleMiddleNames[($idx * 3) % count($maleMiddleNames)];
                $lName = $maleFirstNames[($idx * 5) % count($maleFirstNames)];
            }
            $fullName = "{$fName} {$mName} {$lName}";

            // Xác định nhóm thâm niên
            $tenureGroup = 'MID';
            $joinDateStr = '2023-04-15';
            $status = 'OFFICIAL';
            $contractType = 'DEFINITE_TERM';
            $baseSalary = 16000000.00;

            if ($idx <= 40) {
                // Nhóm Lâu năm (> 5 - 8 năm: 2018 - 2021)
                $tenureGroup = 'SENIOR';
                $joinYear = 2018 + ($idx % 4); // 2018 -> 2021
                $joinMonth = sprintf('%02d', 1 + ($idx % 12));
                $joinDay = sprintf('%02d', 1 + ($idx % 27));
                $joinDateStr = "{$joinYear}-{$joinMonth}-{$joinDay}";
                $contractType = 'INDEFINITE_TERM';
                $baseSalary = 24000000.00 + ($idx % 10) * 1500000.00; // 24tr -> 37.5tr
            } elseif ($idx <= 80) {
                // Nhóm Trung hạn (2 - 4 năm: 2022 - 2024)
                $tenureGroup = 'MID';
                $joinYear = 2022 + ($idx % 3); // 2022 -> 2024
                $joinMonth = sprintf('%02d', 1 + ($idx % 12));
                $joinDay = sprintf('%02d', 1 + ($idx % 27));
                $joinDateStr = "{$joinYear}-{$joinMonth}-{$joinDay}";
                $contractType = ($idx % 2 === 0) ? 'INDEFINITE_TERM' : 'DEFINITE_TERM';
                $baseSalary = 15000000.00 + ($idx % 8) * 1000000.00; // 15tr -> 22tr
            } elseif ($idx <= 95) {
                // Nhóm Mới vào (< 1 - 2 năm: 2025 - đầu 2026)
                $tenureGroup = 'JUNIOR';
                $joinYear = 2025 + ($idx % 2); // 2025 -> 2026
                $joinMonth = sprintf('%02d', 1 + ($idx % 6));
                $joinDay = sprintf('%02d', 1 + ($idx % 27));
                $joinDateStr = "{$joinYear}-{$joinMonth}-{$joinDay}";
                $contractType = 'DEFINITE_TERM';
                $baseSalary = 12000000.00 + ($idx % 5) * 800000.00; // 12tr -> 15.2tr
            } else {
                // Nhóm Thử việc (PROBATION: 6 người từ EMP096 đến EMP101)
                $tenureGroup = 'PROBATION';
                $status = 'PROBATION';
                $contractType = 'PROBATION';
                $joinMonth = sprintf('%02d', 7 + ($idx % 2)); // Tháng 7 hoặc tháng 8/2026
                $joinDay = sprintf('%02d', 1 + ($idx % 20));
                $joinDateStr = "2026-{$joinMonth}-{$joinDay}";
                $baseSalary = 10000000.00;
            }

            // Năm sinh phù hợp thâm niên
            if ($tenureGroup === 'SENIOR') {
                $birthYear = 1982 + ($idx % 8); // 1982 -> 1989 (37 - 44 tuổi)
            } elseif ($tenureGroup === 'MID') {
                $birthYear = 1990 + ($idx % 8); // 1990 -> 1997 (29 - 36 tuổi)
            } else {
                $birthYear = 1998 + ($idx % 6); // 1998 -> 2003 (23 - 28 tuổi)
            }
            $birthDateStr = sprintf('%d-%02d-%02d', $birthYear, 1 + ($idx % 12), 1 + ($idx % 28));

            // Xác định Chi nhánh & Phòng ban & Chức danh
            $branchTarget = $idx % 4; // 0: HN, 1: DN, 2: PQ, 3: HCM
            if ($branchTarget === 0) {
                $branchId = $branchHnId;
                $deptId = $deptHnIds[$idx % count($deptHnIds)];
                $posId = ($idx % 5 === 0) ? $posDevLeadId : (($idx % 3 === 0) ? $posAccountantId : $posSeniorDevId);
            } elseif ($branchTarget === 1) {
                $branchId = $branchDnId;
                $deptId = $deptDnIds[$idx % count($deptDnIds)];
                $posId = ($idx % 4 === 0) ? $posDevLeadId : $posSeniorDevId;
            } elseif ($branchTarget === 2) {
                $branchId = $branchPqId;
                $deptId = $deptPqIds[$idx % count($deptPqIds)];
                $posId = ($idx % 3 === 0) ? $posRetailLeadId : $posSalesSpecialistId;
            } else {
                $branchId = $branchHcmId;
                $deptId = $deptHcmIds[$idx % count($deptHcmIds)];
                $posId = ($idx % 4 === 0) ? $posWarehouseNightId : (($idx % 2 === 0) ? $posRetailStaffId : $posSalesSpecialistId);
            }

            // Tình trạng Thai sản & Con cái
            $isPregnant = false;
            $pregStartDate = null;
            $pregWeeks = null;
            $expectedDueDate = null;
            $childrenCount = 0;
            $taxDependents = 0;
            $isNursing = false;
            $isOnLeaveMaternity = false;

            if ($isFemale) {
                // Phân bổ các nhóm trường hợp nữ đặc thù
                if (in_array($idx, [22, 28, 44, 58, 72, 86], true)) {
                    // 1. Mang thai tháng thứ 7 trở lên (>= 28 tuần / Điều 137 BLLĐ)
                    $isPregnant = true;
                    $pregWeeks = 30 + ($idx % 4); // 30 -> 33 tuần
                    $pregStartDate = Carbon::parse('2026-09-14')->subWeeks($pregWeeks)->format('Y-m-d');
                    $expectedDueDate = Carbon::parse($pregStartDate)->addWeeks(40)->format('Y-m-d'); // 10/2026 -> 11/2026
                    $childrenCount = ($idx % 3 == 0) ? 1 : 0;
                    $taxDependents = $childrenCount;
                } elseif (in_array($idx, [26, 36, 48, 62, 76, 82, 90, 94], true)) {
                    // 2. Mới mang thai (< 28 tuần / từ 10 đến 22 tuần)
                    $isPregnant = true;
                    $pregWeeks = 10 + (($idx * 2) % 13); // 10 -> 22 tuần
                    $pregStartDate = Carbon::parse('2026-09-14')->subWeeks($pregWeeks)->format('Y-m-d');
                    $expectedDueDate = Carbon::parse($pregStartDate)->addWeeks(40)->format('Y-m-d'); // 01/2027 -> 04/2027
                    $childrenCount = ($idx % 2 == 0) ? 1 : 0;
                    $taxDependents = $childrenCount;
                } elseif (in_array($idx, [30, 40, 52, 66, 80, 88, 92, 98, 100, 32, 54, 74], true)) {
                    // 3. Nuôi con nhỏ dưới 12 tháng tuổi (cho con bú / Điều 137 BLLĐ)
                    $isNursing = true;
                    $childrenCount = 1 + ($idx % 2); // 1 hoặc 2 con (có con út < 12 tháng)
                    $taxDependents = $childrenCount;
                } elseif (in_array($idx, [24, 60, 84], true)) {
                    // 4. Nghỉ thai sản 6 tháng (status = ON_LEAVE)
                    $isOnLeaveMaternity = true;
                    $status = 'ON_LEAVE';
                    $childrenCount = 1;
                    $taxDependents = 1;
                } else {
                    // Nữ bình thường: có người có con, người chưa có con
                    if ($tenureGroup === 'SENIOR' || $tenureGroup === 'MID') {
                        $childrenCount = 1 + ($idx % 3); // 1 -> 3 con
                        $taxDependents = $childrenCount;
                    }
                }
            } else {
                // Nam giới: nhiều người có gia đình, có 2 - 4 con
                if ($tenureGroup === 'SENIOR') {
                    $childrenCount = 2 + ($idx % 3); // 2 -> 4 con
                    $taxDependents = $childrenCount;
                } elseif ($tenureGroup === 'MID') {
                    $childrenCount = 1 + ($idx % 3); // 1 -> 3 con
                    $taxDependents = $childrenCount;
                } else {
                    $childrenCount = ($idx % 2 === 0) ? 1 : 0;
                    $taxDependents = $childrenCount;
                }
            }

            $province = $provinces[$idx % count($provinces)];
            $citizenId = sprintf('0%02d%02d%06d', ($idx % 95) + 1, ($birthYear % 100), 100000 + $idx * 37);

            $employeesToInsert[] = [
                'user_code' => $userCode,
                'attendance_code' => $attCode,
                'full_name' => $fullName,
                'gender' => $gender,
                'birth_date' => $birthDateStr,
                'email' => strtolower(sprintf('emp%03d@smarthr.vn', $idx)),
                'phone' => sprintf('09%08d', 10000000 + $idx * 11111),
                'citizen_id' => $citizenId,
                'encrypted_citizen_id' => 'ENC_' . $citizenId,
                'hometown' => $province,
                'permanent_address' => "Số " . ($idx * 5) . " Đường Lê Lợi, " . $province,
                'temporary_address' => "Số " . ($idx * 5) . " Đường Lê Lợi, " . $province,
                'join_date' => $joinDateStr,
                'probation_end_date' => Carbon::parse($joinDateStr)->addMonths(2)->format('Y-m-d'),
                'is_pregnant' => $isPregnant,
                'pregnancy_start_date' => $pregStartDate,
                'pregnancy_weeks_at_start' => $pregWeeks,
                'expected_due_date' => $expectedDueDate,
                'children_count' => $childrenCount,
                'tax_dependents_count' => $taxDependents,
                'status' => $status,
                'department_id' => $deptId,
                'position_id' => $posId,
                'salary_base' => $baseSalary,
                'contract_type' => $contractType,
                'user_role' => ($idx % 15 === 0) ? 'DEPARTMENT_MANAGER' : 'EMPLOYEE',
                'tenure_group' => $tenureGroup,
                'is_nursing' => $isNursing,
                'is_on_leave_maternity' => $isOnLeaveMaternity,
            ];
        }

        // =========================================================================
        // 4. CHÈN BẢN GHI VÀO CSDL VỚI ĐẦY ĐỦ RÀNG BUỘC TOÀN VẸN
        // =========================================================================
        DB::beginTransaction();
        try {
            foreach ($employeesToInsert as $emp) {
                // A. Bảng employees
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
                    'encrypted_citizen_id' => $emp['encrypted_citizen_id'],
                    'hometown' => $emp['hometown'],
                    'permanent_address' => $emp['permanent_address'],
                    'temporary_address' => $emp['temporary_address'],
                    'join_date' => $emp['join_date'],
                    'probation_end_date' => $emp['probation_end_date'],
                    'is_pregnant' => $emp['is_pregnant'],
                    'pregnancy_start_date' => $emp['pregnancy_start_date'],
                    'pregnancy_weeks_at_start' => $emp['pregnancy_weeks_at_start'],
                    'expected_due_date' => $emp['expected_due_date'],
                    'children_count' => $emp['children_count'],
                    'tax_dependents_count' => $emp['tax_dependents_count'],
                    'status' => $emp['status'],
                    'resigned_date' => null,
                    'resignation_reason' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // B. Bảng users
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

                // C. Bảng employee_career_timelines (Effective Dating)
                DB::table('employee_career_timelines')->insert([
                    'employee_id' => $empId,
                    'event_type' => 'ONBOARDING',
                    'department_id' => $emp['department_id'],
                    'position_id' => $emp['position_id'],
                    'job_title' => DB::table('positions')->where('id', $emp['position_id'])->value('name') ?? 'Chuyên viên',
                    'manager_id' => null,
                    'effective_date' => $emp['join_date'],
                    'end_date' => null,
                    'decision_number' => 'QD-TD/' . substr($emp['join_date'], 0, 4) . '/' . $emp['user_code'],
                    'decision_date' => $emp['join_date'],
                    'notes' => 'Tiếp nhận nhân sự theo quyết định tuyển dụng',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // D. Bảng employee_contracts
                $contractEndDate = ($emp['contract_type'] === 'INDEFINITE_TERM') 
                    ? null 
                    : (($emp['contract_type'] === 'PROBATION') 
                        ? Carbon::parse($emp['join_date'])->addMonths(2)->format('Y-m-d')
                        : Carbon::parse($emp['join_date'])->addYears(3)->format('Y-m-d'));

                DB::table('employee_contracts')->insert([
                    'employee_id' => $empId,
                    'contract_number' => 'HDLD/' . substr($emp['join_date'], 0, 4) . '/' . $emp['user_code'],
                    'contract_type' => $emp['contract_type'],
                    'sign_date' => $emp['join_date'],
                    'start_date' => $emp['join_date'],
                    'end_date' => $contractEndDate,
                    'encrypted_salary_data' => 'ENC_SALARY_' . $emp['salary_base'],
                    'status' => 'ACTIVE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // E. Bảng employee_salaries
                DB::table('employee_salaries')->insert([
                    'tenant_id' => $tenantId,
                    'employee_id' => $empId,
                    'base_salary' => $emp['salary_base'],
                    'insurance_salary' => min($emp['salary_base'], 36000000.00),
                    'contract_type' => $emp['status'] === 'PROBATION' ? 'PROBATION' : 'OFFICIAL',
                    'effective_date' => $emp['join_date'],
                    'end_date' => null,
                    'notes' => 'Mức lương cơ sở ban đầu theo thang bảng lương',
                    'is_locked' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // F. Bảng employee_children (Hồ sơ con cái & Chế độ nuôi con)
                if ($emp['children_count'] > 0) {
                    for ($c = 1; $c <= $emp['children_count']; $c++) {
                        $childGender = ($c % 2 === 0) ? 'FEMALE' : 'MALE';
                        
                        // Nếu nhân sự thuộc nhóm nuôi con nhỏ < 12 tháng và đây là con út
                        if ($c === $emp['children_count'] && ($emp['is_nursing'] || $emp['is_on_leave_maternity'])) {
                            // Con từ 1 đến 11 tháng tuổi (sinh từ 10/2025 đến 08/2026)
                            $monthsAgo = 1 + ($empId % 10);
                            $childBirthDate = Carbon::parse('2026-09-14')->subMonths($monthsAgo)->format('Y-m-d');
                        } else {
                            // Con lớn: từ 2 đến 12 tuổi
                            $yearsAgo = 2 + ($c * 3) + ($empId % 3);
                            $childBirthDate = Carbon::parse('2026-09-14')->subYears($yearsAgo)->format('Y-m-d');
                        }

                        $childName = ($childGender === 'MALE') 
                            ? "{$fName} {$mName} Bảo" 
                            : "{$fName} {$mName} An";

                        DB::table('employee_children')->insert([
                            'tenant_id' => $tenantId,
                            'employee_id' => $empId,
                            'full_name' => $childName,
                            'gender' => $childGender,
                            'birth_date' => $childBirthDate,
                            'birth_certificate_number' => sprintf('GKS-%s-%02d', $emp['user_code'], $c),
                            'is_tax_dependent' => true,
                            'tax_dependent_code' => sprintf('MST-%s-%02d', $emp['user_code'], $c),
                            'tax_dependent_start_date' => $childBirthDate,
                            'tax_dependent_end_date' => null,
                            'has_monthly_allowance' => true,
                            'monthly_allowance_amount' => 300000.00,
                            'notes' => 'Con phụ thuộc hưởng chính sách phúc lợi gia đình',
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                // G. Bảng leave_balances (Quỹ phép năm 2026 kèm ngày thâm niên Điều 114 BLLĐ)
                $yearsOfService = max(0, 2026 - (int) substr($emp['join_date'], 0, 4));
                $seniorityDays = floor($yearsOfService / 5); // Cứ 5 năm thêm 1 ngày phép
                $totalLeaveDays = 12.00 + $seniorityDays;

                DB::table('leave_balances')->insert([
                    'tenant_id' => $tenantId,
                    'employee_id' => $empId,
                    'year' => 2026,
                    'leave_type' => 'ANNUAL',
                    'carried_over_days' => 0.00,
                    'carried_over_expiry_date' => '2026-03-31',
                    'statutory_allocated_days' => 12.00,
                    'annual_entitlement' => $totalLeaveDays,
                    'seniority_bonus_days' => $seniorityDays,
                    'welfare_bonus_days' => 0.00,
                    'total_entitled_days' => $totalLeaveDays,
                    'accrued_days_to_date' => 9.00,
                    'used_days' => (float) ($empId % 4),
                    'pending_days' => 0.00,
                    'remaining_days' => $totalLeaveDays - (float) ($empId % 4),
                    'carried_forward_days' => 0.00,
                    'comp_off_hours' => 0.00,
                    'taken_paid_days' => (float) ($empId % 4),
                    'taken_unpaid_days' => 0.00,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // H. Bảng compliance_alerts (Cảnh báo tuân thủ thai sản tháng thứ 7)
                if ($emp['is_pregnant'] && $emp['pregnancy_weeks_at_start'] >= 28) {
                    DB::table('compliance_alerts')->insert([
                        'tenant_id' => $tenantId,
                        'employee_id' => $empId,
                        'alert_type' => 'PREGNANT_NIGHT_BREACH',
                        'severity' => 'WARNING',
                        'title' => 'Bảo vệ lao động mang thai từ tháng thứ 7 (Điều 137 BLLĐ)',
                        'description' => sprintf(
                            'Nhân sự %s (%s) mang thai tuần thứ %d (dự sinh %s). Theo Đ.137 BLLĐ: Nghiêm cấm phân ca đêm (22:00-06:00), cấm làm thêm giờ và được rút ngắn 1h làm việc/ngày.',
                            $emp['full_name'],
                            $emp['user_code'],
                            $emp['pregnancy_weeks_at_start'],
                            $emp['expected_due_date']
                        ),
                        'is_resolved' => false,
                        'resolved_by' => null,
                        'resolved_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Xóa cache dashboard để thống kê cập nhật tức thì
        \Illuminate\Support\Facades\Cache::forget("smart_hr:dashboard_analytics:{$tenantId}");
    }
}
