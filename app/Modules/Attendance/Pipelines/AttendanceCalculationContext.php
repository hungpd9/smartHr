<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Pipelines;

use App\Modules\CoreHR\Models\Employee;
use App\Modules\PolicyEngine\Models\PolicyVersion;
use App\Modules\Rostering\Models\Roster;
use App\Modules\Rostering\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceCalculationContext
{
    public int $tenantId;
    public int $employeeId;
    public Carbon $workDate;
    public ?Employee $employee = null;
    public Collection $rawLogs;

    // Roster & Shift
    public ?Roster $roster = null;
    public ?Shift $shift = null;

    // Policy & Rules
    public ?PolicyVersion $policyVersion = null;
    public bool $isHoliday = false;
    public ?string $holidayName = null;

    // Actual Punches
    public ?Carbon $actualIn = null;
    public ?Carbon $actualOut = null;
    public bool $isMissingCheckIn = false;
    public bool $isMissingCheckOut = false;

    // Calculated Work Metrics
    public float $standardHours = 0.00;
    public int $lateMinutes = 0;
    public int $earlyMinutes = 0;
    public int $childExemptionMinutes = 0;
    public float $latePenaltyAmount = 0.00;

    // Overtime Metrics
    public float $otDayHours = 0.00;
    public float $otNightHours = 0.00;
    public float $otWeekendHours = 0.00;
    public float $otHolidayHours = 0.00;

    // Attendance Status: PRESENT, LATE_EARLY, ABSENT, LEAVE_PAID, LEAVE_UNPAID, HOLIDAY
    public string $status = 'PRESENT';

    // Monthly frequency tracking
    public int $violationCountThisMonth = 1;

    // Diagnostic & Explanations
    public array $notes = [];

    public function __construct(int $tenantId, int $employeeId, Carbon $workDate)
    {
        $this->tenantId = $tenantId;
        $this->employeeId = $employeeId;
        $this->workDate = $workDate->copy()->startOfDay();
        $this->rawLogs = collect();
    }

    public function addNote(string $note): void
    {
        $this->notes[] = $note;
    }
}
