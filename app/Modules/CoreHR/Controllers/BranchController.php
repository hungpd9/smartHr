<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CoreHR\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class BranchController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['nullable', 'exists:tenants,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_remote_or_island' => ['boolean'],
        ]);

        $validated['tenant_id'] = $validated['tenant_id'] ?? 1;
        $validated['secret_key'] = bin2hex(random_bytes(16));
        $validated['is_active'] = true;

        Branch::create($validated);

        return redirect()->back()->with('success', 'Thiết lập chi nhánh mới thành công!');
    }
}
