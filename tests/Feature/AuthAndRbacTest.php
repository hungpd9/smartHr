<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_screen_renders_successfully(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/Login')
            ->has('demoUsers', 5)
        );
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@smarthr.vn',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@smarthr.vn',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::where('email', 'admin@smarthr.vn')->first();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_rbac_employee_cannot_access_core_hr(): void
    {
        $employeeUser = User::where('role', UserRole::EMPLOYEE)->first();

        $response = $this->actingAs($employeeUser)->get('/core-hr');

        $response->assertStatus(403);
    }

    public function test_rbac_hr_admin_can_access_core_hr(): void
    {
        $hrUser = User::where('role', UserRole::HR_ADMIN)->first();

        $response = $this->actingAs($hrUser)->get('/core-hr');

        $response->assertStatus(200);
    }

    public function test_quick_switch_role_in_local_environment(): void
    {
        $admin = User::where('role', UserRole::SUPER_ADMIN)->first();

        $response = $this->actingAs($admin)->get('/auth/switch-role/ACCOUNTANT');

        $response->assertRedirect('/');
        $this->assertEquals(UserRole::ACCOUNTANT, auth()->user()->role);
    }

    public function test_user_can_update_preferences(): void
    {
        $user = User::where('email', 'admin@smarthr.vn')->first();

        $response = $this->actingAs($user)->patchJson('/user/preferences', [
            'theme_preference' => 'dark',
            'locale_preference' => 'en',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'theme_preference' => 'dark',
            'locale_preference' => 'en',
        ]);

        $user->refresh();
        $this->assertEquals('dark', $user->theme_preference);
        $this->assertEquals('en', $user->locale_preference);
    }
}
