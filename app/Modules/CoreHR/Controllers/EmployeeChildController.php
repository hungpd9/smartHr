<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\EmployeeChild;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class EmployeeChildController extends Controller
{
    public function index(Request $request): Response
    {
        $tenantId = $request->user()?->tenant_id ?? 1;
        $search = $request->query('search');
        $employeeId = $request->query('employee_id');
        $ageGroup = $request->query('age_group');
        $taxDependent = $request->query('tax_dependent');

        $query = EmployeeChild::query()
            ->where('tenant_id', $tenantId)
            ->with(['employee.currentTimeline.department.currentVersion', 'employee.currentTimeline.position']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('birth_certificate_number', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($empQ) use ($search) {
                      $empQ->where('full_name', 'like', "%{$search}%")
                           ->orWhere('user_code', 'like', "%{$search}%");
                  });
            });
        }

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $now = Carbon::now();

        if ($ageGroup === 'UNDER_12M') {
            $query->where('birth_date', '>=', $now->copy()->subMonths(12)->format('Y-m-d'));
        } elseif ($ageGroup === '1_TO_6Y') {
            $query->where('birth_date', '<', $now->copy()->subMonths(12)->format('Y-m-d'))
                  ->where('birth_date', '>=', $now->copy()->subMonths(72)->format('Y-m-d'));
        } elseif ($ageGroup === '6_TO_11Y') {
            $query->where('birth_date', '<', $now->copy()->subMonths(72)->format('Y-m-d'))
                  ->where('birth_date', '>=', $now->copy()->subMonths(132)->format('Y-m-d'));
        } elseif ($ageGroup === 'OVER_11Y') {
            $query->where('birth_date', '<', $now->copy()->subMonths(132)->format('Y-m-d'));
        }

        if ($taxDependent !== null && $taxDependent !== '') {
            $query->where('is_tax_dependent', $taxDependent === '1');
        }

        // Thống kê KPIs toàn diện
        $baseQuery = EmployeeChild::where('tenant_id', $tenantId)->where('is_active', true);
        $totalChildrenCount = (clone $baseQuery)->count();
        $under12mCount = (clone $baseQuery)->where('birth_date', '>=', $now->copy()->subMonths(12)->format('Y-m-d'))->count();
        $range1to11Count = (clone $baseQuery)
            ->where('birth_date', '<', $now->copy()->subMonths(12)->format('Y-m-d'))
            ->where('birth_date', '>=', $now->copy()->subMonths(132)->format('Y-m-d'))
            ->count();
        $taxDependentCount = (clone $baseQuery)->where('is_tax_dependent', true)->count();
        $totalMonthlyAllowance = (clone $baseQuery)->where('has_monthly_allowance', true)->sum('monthly_allowance_amount');

        $children = $query->latest('id')->paginate(12)->through(function ($child) {
            return [
                'id' => $child->id,
                'employee_id' => $child->employee_id,
                'full_name' => $child->full_name,
                'gender' => $child->gender,
                'birth_date' => $child->birth_date ? Carbon::parse($child->birth_date)->format('Y-m-d') : null,
                'birth_date_display' => $child->birth_date ? Carbon::parse($child->birth_date)->format('d/m/Y') : '',
                'birth_certificate_number' => $child->birth_certificate_number,
                'age_text' => $child->age_text,
                'is_tax_dependent' => $child->is_tax_dependent,
                'tax_dependent_code' => $child->tax_dependent_code,
                'tax_dependent_start_date' => $child->tax_dependent_start_date ? Carbon::parse($child->tax_dependent_start_date)->format('Y-m-d') : null,
                'tax_dependent_end_date' => $child->tax_dependent_end_date ? Carbon::parse($child->tax_dependent_end_date)->format('Y-m-d') : null,
                'has_monthly_allowance' => $child->has_monthly_allowance,
                'monthly_allowance_amount' => (float) $child->monthly_allowance_amount,
                'notes' => $child->notes,
                'is_active' => $child->is_active,
                'applicable_policies' => $child->applicable_policies,
                'employee' => $child->employee ? [
                    'id' => $child->employee->id,
                    'user_code' => $child->employee->user_code,
                    'full_name' => $child->employee->full_name,
                    'gender' => $child->employee->gender,
                    'department_name' => $child->employee->currentTimeline?->department?->currentVersion?->name ?? 'Chưa phân bổ',
                    'position_name' => $child->employee->currentTimeline?->position?->name ?? 'Chưa bổ nhiệm',
                ] : null,
            ];
        });

        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', '!=', 'TERMINATED')
            ->select('id', 'user_code', 'full_name', 'gender')
            ->orderBy('full_name')
            ->get();

        return Inertia::render('EmployeeChildren/Index', [
            'children' => $children,
            'employees' => $employees,
            'kpis' => [
                'totalChildrenCount' => $totalChildrenCount,
                'under12mCount' => $under12mCount,
                'range1to11Count' => $range1to11Count,
                'taxDependentCount' => $taxDependentCount,
                'totalMonthlyAllowance' => (float) $totalMonthlyAllowance,
            ],
            'filters' => [
                'search' => $search,
                'employee_id' => $employeeId,
                'age_group' => $ageGroup,
                'tax_dependent' => $taxDependent,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:MALE,FEMALE,OTHER'],
            'birth_date' => ['required', 'date'],
            'birth_certificate_number' => ['nullable', 'string', 'max:100'],
            'is_tax_dependent' => ['boolean'],
            'tax_dependent_code' => ['nullable', 'string', 'max:50'],
            'tax_dependent_start_date' => ['nullable', 'date'],
            'tax_dependent_end_date' => ['nullable', 'date'],
            'has_monthly_allowance' => ['boolean'],
            'monthly_allowance_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['tenant_id'] = $request->user()?->tenant_id ?? 1;
        $validated['monthly_allowance_amount'] = $validated['monthly_allowance_amount'] ?? 300000.00;

        $child = EmployeeChild::create($validated);

        $employee = Employee::find($validated['employee_id']);
        if ($employee) {
            $employee->recalculateDependentsAndChildrenCounts();
        }

        return redirect()->back()->with('success', "Thêm hồ sơ con nhỏ ({$child->full_name}) thành công!");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $child = EmployeeChild::findOrFail($id);

        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:MALE,FEMALE,OTHER'],
            'birth_date' => ['required', 'date'],
            'birth_certificate_number' => ['nullable', 'string', 'max:100'],
            'is_tax_dependent' => ['boolean'],
            'tax_dependent_code' => ['nullable', 'string', 'max:50'],
            'tax_dependent_start_date' => ['nullable', 'date'],
            'tax_dependent_end_date' => ['nullable', 'date'],
            'has_monthly_allowance' => ['boolean'],
            'monthly_allowance_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $child->update($validated);

        $employee = Employee::find($child->employee_id);
        if ($employee) {
            $employee->recalculateDependentsAndChildrenCounts();
        }

        return redirect()->back()->with('success', "Cập nhật hồ sơ bé {$child->full_name} thành công!");
    }

    public function destroy(int $id): RedirectResponse
    {
        $child = EmployeeChild::findOrFail($id);
        $empId = $child->employee_id;
        $childName = $child->full_name;

        $child->delete();

        $employee = Employee::find($empId);
        if ($employee) {
            $employee->recalculateDependentsAndChildrenCounts();
        }

        return redirect()->back()->with('success', "Đã xóa hồ sơ bé {$childName} thành công!");
    }
}
