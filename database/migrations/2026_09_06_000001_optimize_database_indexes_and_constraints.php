<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tối ưu index cho bảng permission_assignments
        Schema::table('permission_assignments', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'subject_type', 'subject_role', 'module_code'],
                'idx_perm_lookup_role'
            );
            $table->index(
                ['tenant_id', 'subject_type', 'subject_id', 'module_code'],
                'idx_perm_lookup_id'
            );
        });

        // 2. Tối ưu index cho bảng welfare_schemes
        Schema::table('welfare_schemes', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'visibility', 'category'],
                'idx_welfare_visibility_cat'
            );
        });

        // 3. Tối ưu index cho bảng allowances
        Schema::table('allowances', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'visibility', 'apply_type'],
                'idx_allowance_visibility_type'
            );
        });

        // 4. Tối ưu index cho bảng employee_children
        Schema::table('employee_children', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'employee_id', 'birth_date'],
                'idx_children_emp_birth'
            );
        });

        // 5. Tối ưu index cho bảng child_welfare_policies
        Schema::table('child_welfare_policies', function (Blueprint $table) {
            $table->index(
                ['policy_version_id', 'visibility', 'is_statutory'],
                'idx_child_policy_vis'
            );
        });
    }

    public function down(): void
    {
        Schema::table('permission_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_perm_lookup_role');
            $table->dropIndex('idx_perm_lookup_id');
        });

        Schema::table('welfare_schemes', function (Blueprint $table) {
            $table->dropIndex('idx_welfare_visibility_cat');
        });

        Schema::table('allowances', function (Blueprint $table) {
            $table->dropIndex('idx_allowance_visibility_type');
        });

        Schema::table('employee_children', function (Blueprint $table) {
            $table->dropIndex('idx_children_emp_birth');
        });

        Schema::table('child_welfare_policies', function (Blueprint $table) {
            $table->dropIndex('idx_child_policy_vis');
        });
    }
};
