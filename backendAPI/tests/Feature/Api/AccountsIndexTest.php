<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fokus mode token API (bukan SPA cookie).
        config(['sanctum.stateful' => []]);
    }

    public function test_can_list_active_accounts_sorted_by_code(): void
    {
        $user = User::query()->create([
            'name' => 'Tester',
            'email' => 'tester@example.com',
            'password' => bcrypt('password'),
        ]);
        Sanctum::actingAs($user);

        Account::query()->create(['code' => '2000', 'name' => 'Hutang', 'type' => 'liability', 'parent_id' => null, 'is_active' => true]);
        Account::query()->create(['code' => '1000', 'name' => 'Kas', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);
        Account::query()->create(['code' => '9999', 'name' => 'Inactive', 'type' => 'asset', 'parent_id' => null, 'is_active' => false]);

        $res = $this->getJson('/api/accounts');

        $res->assertOk();
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('message', 'OK');

        $data = $res->json('data');
        $this->assertCount(2, $data);
        $this->assertSame('1000', $data[0]['code']);
        $this->assertSame('2000', $data[1]['code']);
    }
}

