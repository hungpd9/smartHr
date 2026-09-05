<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->after('tenant_id')->constrained('employees')->nullOnDelete();
            $table->string('role', 30)->default('EMPLOYEE')->after('password')->comment('SUPER_ADMIN, HR_ADMIN, ACCOUNTANT, DEPARTMENT_MANAGER, EMPLOYEE');
            $table->string('avatar', 255)->nullable()->after('role');
            $table->string('theme_preference', 10)->default('system')->after('avatar')->comment('light, dark, system');
            $table->string('locale_preference', 5)->default('vi')->after('theme_preference')->comment('vi, en');
            $table->boolean('is_active')->default(true)->after('locale_preference');

            $table->index(['tenant_id', 'role'], 'idx_users_tenant_role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_tenant_role');
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['employee_id']);
            $table->dropColumn([
                'tenant_id',
                'employee_id',
                'role',
                'avatar',
                'theme_preference',
                'locale_preference',
                'is_active',
            ]);
        });
    }
};
