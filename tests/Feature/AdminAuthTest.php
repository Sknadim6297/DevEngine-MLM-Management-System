<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
