<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Services\DepartmentService;
use App\Modules\CoreHR\Requests\UpdateDepartmentVersionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class DepartmentController extends Controller
{
    public function __construct(
        protected DepartmentService $departmentService
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'mission_function' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:departments,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'effective_date' => ['nullable', 'date'],
        ]);

        $this->departmentService->createDepartment($validated);

        return redirect()->back()->with('success', 'Thành lập phòng ban mới thành công!');
    }

    public function updateVersion(int $id, UpdateDepartmentVersionRequest $request): RedirectResponse
    {
        $department = Department::findOrFail($id);
        $this->departmentService->publishNewVersion($department, $request->validated());

        return redirect()->back()->with('success', 'Ban hành phiên bản phòng ban mới thành công!');
    }
}
