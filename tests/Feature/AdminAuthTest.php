<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_can_login_with_seeded_credentials(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
        ]);

        $this->post('/admin/login', [
            'email' => 'admin@gmail.com',
            'password' => 'admin123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs(User::first());
    }

    public function test_wrong_password_shows_validation_error(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
        ]);

        $this->from('/admin/login')
            ->post('/admin/login', [
                'email' => 'admin@gmail.com',
                'password' => 'wrongpass',
            ])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['email']);
    }

    public function test_non_admin_user_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_security_headers_are_present(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->from(route('login'))
                ->post(route('login.submit'), [
                    'email' => 'admin@gmail.com',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect(route('login'));
        }

        $this->post(route('login.submit'), [
            'email' => 'admin@gmail.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_logout_logs_out_invalidates_session_and_redirects_to_login(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->withSession(['logout-test' => 'value']);

        $this->post(route('admin.logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertFalse(Session::has('logout-test'));
    }
}
