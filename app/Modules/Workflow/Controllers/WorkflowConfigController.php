<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Requests\UpdateWorkflowConfigRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class WorkflowConfigController extends Controller
{
    /**
     * Cập nhật hoặc tạo mới cấu hình quy trình phê duyệt
     */
    public function update(UpdateWorkflowConfigRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);

        WorkflowDefinition::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'request_type' => $request->input('request_type'),
            ],
            [
                'name' => 'Quy trình phê duyệt ' . $request->input('request_type'),
                'total_levels' => (int) $request->input('total_levels'),
                'steps_config' => $request->input('steps_config'),
                'condition_rules' => $request->input('condition_rules'),
                'is_active' => (bool) $request->input('is_active', true),
            ]
        );

        return redirect()->back()->with('success', 'Đã lưu cấu hình quy trình phê duyệt thành công.');
    }
}
