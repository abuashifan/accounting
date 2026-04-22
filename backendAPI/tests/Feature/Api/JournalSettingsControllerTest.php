<?php

namespace Tests\Feature\Api;

use App\Models\AppSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fokus mode token API (bukan SPA cookie).
        config(['sanctum.stateful' => []]);
    }

    public function test_non_admin_cannot_view_or_update_journal_settings(): void
    {
        $user = User::query()->create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/settings/journals')->assertForbidden();
        $this->putJson('/api/settings/journals', ['auto_post' => true])->assertForbidden();
    }

    public function test_admin_can_toggle_auto_post_setting(): void
    {
        $permission = Permission::query()->create(['name' => 'settings.manage']);
        $role = Role::query()->create(['name' => 'admin']);
        $role->permissions()->sync([$permission->id]);

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $admin->roles()->sync([$role->id]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/settings/journals')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.auto_post', false);

        $this->putJson('/api/settings/journals', ['auto_post' => true])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.auto_post', true);

        $this->assertTrue(AppSetting::getBool('journals.auto_post', false));
    }
}

