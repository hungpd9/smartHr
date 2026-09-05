<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Tenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'theme_preference',
        'locale_preference',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPER_ADMIN;
    }

    public function isHr(): bool
    {
        return $this->role === UserRole::HR_ADMIN || $this->role === UserRole::SUPER_ADMIN;
    }

    public function isAccountant(): bool
    {
        return $this->role === UserRole::ACCOUNTANT || $this->role === UserRole::SUPER_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::DEPARTMENT_MANAGER || $this->role === UserRole::SUPER_ADMIN;
    }

    public function isEmployee(): bool
    {
        return $this->role === UserRole::EMPLOYEE;
    }

    public function canAccessModule(string $module): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($module, $this->role->allowedModules(), true);
    }
}
