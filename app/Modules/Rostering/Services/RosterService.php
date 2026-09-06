<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Validation\ValidationException;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Department;
use App\Modules\Rostering\Models\Shift;
use App\Modules\Rostering\Models\Roster;

class RosterService
{
    public function __construct(
        protected RosteringConstraintValidator $validator
    ) {}

    /**
     * Lấy cấu trúc ma trận phân ca dạng lưới (Grid Matrix)
     */
    public function getRosterMatrix(
        int $tenantId,
        string $startDate,
        string $endDate,
        ?int $departmentId = null
    ): array {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        // 1. Tạo danh sách các ngày trong chu kỳ
        $dates = [];
        $period = CarbonPeriod::create($start, $end);
        foreach ($period as $dt) {
            $dates[] = [
                'date' => $dt->toDateString(),
                'day_name' => $dt->locale('vi')->isoFormat('ddd'),
                'day_number' => $dt->format('d/m'),
                'is_weekend' => $dt->isWeekend(),
            ];
        }

        // 2. Lấy danh sách nhân viên
        $empQuery = Employee::where('tenant_id', $tenantId)
            ->whereIn('status', ['PROBATION', 'OFFICIAL'])
            ->with([
                'children' => fn($q) => $q->where('is_active', true),
                'timelines' => function ($q) use ($end) {
                    $q->where('effective_date', '<=', $end->toDateString())
                      ->where(function ($query) use ($end) {
                          $query->whereNull('end_date')->orWhere('end_date', '>=', $end->toDateString());
                      })
                      ->with(['department.defaultShift']);
                }
            ]);

        if ($departmentId) {
            $empQuery->whereHas('timelines', function ($q) use ($departmentId, $end) {
                $q->where('department_id', $departmentId)
                  ->where('effective_date', '<=', $end->toDateString())
                  ->where(function ($query) use ($end) {
                      $query->whereNull('end_date')->orWhere('end_date', '>=', $end->toDateString());
                  });
            });
        }

        $employees = $empQuery->orderBy('user_code')->get();

        // 3. Lấy toàn bộ phân ca trong khoảng thời gian
        $rosters = Roster::where('tenant_id', $tenantId)
            ->whereBetween('roster_date', [$start->toDateString(), $end->toDateString()])
            ->with('shift')
            ->get()
            ->groupBy('employee_id');

        // Ca mặc định toàn hệ thống
        $tenantDefaultShift = Shift::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        // 4. Định dạng dữ liệu ma trận
        $matrix = [];
        foreach ($employees as $emp) {
            $empRosters = $rosters->get($emp->id, collect())->keyBy(function ($r) {
                return Carbon::parse($r->roster_date)->toDateString();
            });

            // Xác định cờ bảo vệ con nhỏ & thai sản
            $hasChildUnder12m = false;
            if ($emp->children && $emp->children->count() > 0) {
                foreach ($emp->children as $c) {
                    if ($c->birth_date && Carbon::parse($c->birth_date)->diffInMonths($start) < 12) {
                        $hasChildUnder12m = true;
                        break;
                    }
                }
            }
            if (!$hasChildUnder12m && $emp->child_birth_date) {
                if (Carbon::parse($emp->child_birth_date)->diffInMonths($start) < 12) {
                    $hasChildUnder12m = true;
                }
            }

            $currentTimeline = $emp->timelines->first();
            $dept = $currentTimeline?->department;
            $deptName = $dept?->name ?? '---';

            // Ưu tiên ca mặc định phòng ban, sau đó tới ca mặc định tenant
            $empDefaultShift = ($dept && $dept->defaultShift && $dept->defaultShift->is_active)
                ? $dept->defaultShift
                : $tenantDefaultShift;

            $daysMap = [];
            foreach ($dates as $d) {
                $dtStr = $d['date'];
                $r = $empRosters->get($dtStr);

                if ($r && $r->shift_id) {
                    $daysMap[$dtStr] = [
                        'roster_id' => $r->id,
                        'shift_id' => $r->shift_id,
                        'shift_code' => $r->shift?->code,
                        'shift_name' => $r->shift?->name,
                        'start_time' => $r->shift?->start_time,
                        'end_time' => $r->shift?->end_time,
                        'is_night_shift' => (bool)($r->shift?->is_night_shift ?? false),
                        'work_hours' => (float)($r->shift?->work_hours ?? 0.0),
                        'is_published' => (bool)($r->is_published ?? false),
                        'status' => $r->status ?? 'SCHEDULED',
                        'is_default_inherited' => false,
                        'violation_flags' => $r->violation_flags ?? [],
                    ];
                } elseif ($r && $r->shift_id === null) {
                    // Đã gán rõ ràng ngày nghỉ (OFF)
                    $daysMap[$dtStr] = [
                        'roster_id' => $r->id,
                        'shift_id' => null,
                        'shift_code' => null,
                        'shift_name' => 'Nghỉ',
                        'start_time' => null,
                        'end_time' => null,
                        'is_night_shift' => false,
                        'work_hours' => 0.0,
                        'is_published' => (bool)($r->is_published ?? false),
                        'status' => 'OFF',
                        'is_default_inherited' => false,
                        'violation_flags' => [],
                    ];
                } else {
                    // Chưa gán ca riêng -> Tự động kế thừa ca mặc định (nếu có và không phải cuối tuần)
                    if ($empDefaultShift && !$d['is_weekend']) {
                        $daysMap[$dtStr] = [
                            'roster_id' => null,
                            'shift_id' => $empDefaultShift->id,
                            'shift_code' => $empDefaultShift->code,
                            'shift_name' => $empDefaultShift->name,
                            'start_time' => $empDefaultShift->start_time,
                            'end_time' => $empDefaultShift->end_time,
                            'is_night_shift' => (bool)$empDefaultShift->is_night_shift,
                            'work_hours' => (float)$empDefaultShift->work_hours,
                            'is_published' => true,
                            'status' => 'DEFAULT',
                            'is_default_inherited' => true,
                            'violation_flags' => [],
                        ];
                    } else {
                        $daysMap[$dtStr] = [
                            'roster_id' => null,
                            'shift_id' => null,
                            'shift_code' => null,
                            'shift_name' => $d['is_weekend'] ? 'Nghỉ tuần' : 'Chưa xếp',
                            'start_time' => null,
                            'end_time' => null,
                            'is_night_shift' => false,
                            'work_hours' => 0.0,
                            'is_published' => false,
                            'status' => 'OFF',
                            'is_default_inherited' => false,
                            'violation_flags' => [],
                        ];
                    }
                }
            }

            $matrix[] = [
                'employee_id' => $emp->id,
                'user_code' => $emp->user_code,
                'full_name' => $emp->full_name,
                'department_name' => $deptName,
                'gender' => $emp->gender,
                'has_child_under_12m' => $hasChildUnder12m,
                'is_pregnant' => (bool)($emp->is_pregnant ?? false),
                'days' => $daysMap,
            ];
        }

        // 5. Lấy danh sách ca làm việc kích hoạt
        $shifts = Shift::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get();

        $departments = Department::where('tenant_id', $tenantId)
            ->with(['currentVersion', 'defaultShift'])
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'code' => $d->code,
                'name' => $d->currentVersion?->name ?? $d->code,
                'default_shift_id' => $d->default_shift_id,
                'default_shift_name' => $d->defaultShift?->name,
            ]);

        return [
            'dates' => $dates,
            'matrix' => $matrix,
            'shifts' => $shifts,
            'departments' => $departments,
            'defaultShift' => $tenantDefaultShift,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'selectedDepartmentId' => $departmentId,
        ];
    }

    /**
     * Gán ca làm việc cho nhân viên theo ngày (có kiểm tra BLLĐ 2019)
     */
    public function assignShift(
        int $tenantId,
        int $employeeId,
        string $date,
        ?int $shiftId,
        bool $force = false
    ): Roster {
        $rosterDate = Carbon::parse($date)->toDateString();

        // Nếu shiftId là null, xóa ca (đặt thành ngày nghỉ OFF)
        if (!$shiftId) {
            Roster::where('tenant_id', $tenantId)
                ->where('employee_id', $employeeId)
                ->where('roster_date', $rosterDate)
                ->delete();

            $mockRoster = new Roster([
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'roster_date' => $rosterDate,
                'shift_id' => null,
                'status' => 'OFF',
            ]);
            return $mockRoster;
        }

        // Kiểm tra ràng buộc BLLĐ 2019
        $validation = $this->validator->validateShiftAssignment($employeeId, $shiftId, $rosterDate);

        if ($validation['is_hard_violation'] && !$force) {
            throw ValidationException::withMessages([
                'shift_id' => $validation['hard_violations'],
            ]);
        }

        $allWarnings = array_merge($validation['hard_violations'], $validation['warnings']);

        // Tạo hoặc cập nhật phân ca
        $roster = Roster::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'roster_date' => $rosterDate,
            ],
            [
                'shift_id' => $shiftId,
                'status' => 'SCHEDULED',
                'violation_flags' => $allWarnings,
            ]
        );

        return $roster->load('shift');
    }

    /**
     * Công bố lịch phân ca cho nhân viên xem
     */
    public function publishRosters(
        int $tenantId,
        string $startDate,
        string $endDate,
        ?int $departmentId = null
    ): int {
        $query = Roster::where('tenant_id', $tenantId)
            ->whereBetween('roster_date', [$startDate, $endDate]);

        if ($departmentId) {
            $query->whereHas('employee.timelines', function ($q) use ($departmentId, $endDate) {
                $q->where('department_id', $departmentId)
                  ->where('effective_date', '<=', $endDate)
                  ->where(function ($sub) use ($endDate) {
                      $sub->whereNull('end_date')->orWhere('end_date', '>=', $endDate);
                  });
            });
        }

        return $query->update(['is_published' => true]);
    }

    /**
     * Xác định ca làm việc mặc định cho nhân viên tại ngày cụ thể
     * Ưu tiên:
     * 1. Ca mặc định cấu hình riêng cho phòng ban của nhân viên
     * 2. Ca mặc định toàn doanh nghiệp (is_default = true)
     * 3. Ca kích hoạt đầu tiên của doanh nghiệp
     */
    public function resolveDefaultShift(int $tenantId, int $employeeId, Carbon $date): ?Shift
    {
        $targetDateStr = $date->toDateString();

        // 1. Kiểm tra ca mặc định của phòng ban nhân viên
        $employee = Employee::where('tenant_id', $tenantId)
            ->where('id', $employeeId)
            ->with([
                'timelines' => function ($q) use ($targetDateStr) {
                    $q->where('effective_date', '<=', $targetDateStr)
                      ->where(function ($sub) use ($targetDateStr) {
                          $sub->whereNull('end_date')->orWhere('end_date', '>=', $targetDateStr);
                      })
                      ->with('department.defaultShift');
                }
            ])
            ->first();

        $dept = $employee?->timelines->first()?->department;
        if ($dept && $dept->defaultShift && $dept->defaultShift->is_active) {
            return $dept->defaultShift;
        }

        // 2. Ca mặc định của toàn hệ thống (tenant)
        $tenantDefault = Shift::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if ($tenantDefault) {
            return $tenantDefault;
        }

        // 3. Fallback: Ca đầu tiên kích hoạt
        return Shift::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('start_time')
            ->first();
    }
}
