<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AppSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JournalAutoPostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fokus mode token API (bukan SPA cookie).
        config(['sanctum.stateful' => []]);
    }

    public function test_auto_post_posts_journal_on_create_even_for_input_only_user(): void
    {
        AppSetting::setBool('journals.auto_post', true);

        $permissionCreate = Permission::query()->create(['name' => 'journal.create']);
        $role = Role::query()->create(['name' => 'journal_input']);
        $role->permissions()->sync([$permissionCreate->id]);

        $user = User::query()->create([
            'name' => 'Input',
            'email' => 'input@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $user->roles()->sync([$role->id]);
        $this->assertTrue($user->fresh()->hasPermission('journal.create'));
        $this->assertFalse($user->fresh()->hasPermission('journal.update'));

        Sanctum::actingAs($user);

        $period = AccountingPeriod::query()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
            'locked_by' => null,
            'locked_at' => null,
        ]);

        $kas = Account::query()->create(['code' => '1000', 'name' => 'Kas', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);
        $pendapatan = Account::query()->create(['code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'parent_id' => null, 'is_active' => true]);

        $create = $this->postJson('/api/journals', [
            'date' => '2026-04-21',
            'description' => 'Auto post create',
            'accounting_period_id' => $period->id,
            'lines' => [
                ['account_id' => $kas->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $pendapatan->id, 'debit' => 0, 'credit' => 1000],
            ],
        ]);

        $create->assertCreated();
        $create->assertJsonPath('success', true);
        $this->assertSame('posted', $create->json('data.status'));
    }
}

