<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountsCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => []]);
    }

    public function test_can_create_account_via_api(): void
    {
        $role = Role::query()->create(['name' => 'admin']);
        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        Sanctum::actingAs($user);

        $res = $this->postJson('/api/accounts', [
            'code' => '1400',
            'name' => 'Inventory',
            'type' => 'asset',
            'parent_id' => null,
            'is_active' => true,
        ]);

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', '1400');

        $this->assertDatabaseHas('accounts', [
            'code' => '1400',
            'name' => 'Inventory',
            'type' => 'asset',
        ]);
    }
}

