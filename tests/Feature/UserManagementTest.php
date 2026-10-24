<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('users.store'), [
                'name' => 'Ravi Kumar',
                'email' => 'ravi@example.com',
                'role' => 'technician',
                'password' => 'secret-pass',
                'is_active' => '1',
                'receives_leads' => '0',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => 'ravi@example.com', 'role' => 'technician', 'is_active' => true]);
    }

    public function test_blank_password_on_update_keeps_existing_password(): void
    {
        $user = User::factory()->create();
        $originalHash = $user->password;

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('users.update', $user), [
                'name' => 'Renamed',
                'email' => $user->email,
                'role' => 'manager',
                'password' => '',
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertSame($originalHash, $user->password);
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'role' => 'sales', 'is_active' => '1'])
            ->assertSessionHas('error');

        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_deleting_a_user_deactivates_instead(): void
    {
        $user = User::factory()->create(['receives_leads' => true]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('users.destroy', $user))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false, 'receives_leads' => false]);
    }

    public function test_managers_cannot_manage_users(): void
    {
        $this->actingAs(User::factory()->role('manager')->create())
            ->get(route('users.index'))
            ->assertForbidden();
    }
}
