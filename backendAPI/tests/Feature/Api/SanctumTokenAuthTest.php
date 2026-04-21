<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SanctumTokenAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fokus mode token API (bukan SPA cookie).
        config(['sanctum.stateful' => []]);
    }

    public function test_can_issue_token_and_create_journal_via_api(): void
    {
        $permissionCreate = Permission::query()->create(['name' => 'journal.create']);
        $role = Role::query()->create(['name' => 'journal_input']);
        $role->permissions()->sync([$permissionCreate->id]);

        $user = User::query()->create([
            'name' => 'Journal Input',
            'email' => 'input@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $user->roles()->sync([$role->id]);

        $period = AccountingPeriod::query()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
            'locked_by' => null,
            'locked_at' => null,
        ]);

        $kas = Account::query()->create(['code' => '1000', 'name' => 'Kas', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);
        $pendapatan = Account::query()->create(['code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'parent_id' => null, 'is_active' => true]);

        $login = $this->postJson('/api/auth/token', [
            'email' => 'input@example.com',
            'password' => 'password',
            'token_name' => 'test',
        ]);
        $login->assertOk();

        $token = $login->json('data.token');
        $this->assertIsString($token);

        $create = $this
            ->withToken($token)
            ->postJson('/api/journals', [
                'date' => '2026-04-21',
                'description' => 'API create',
                'accounting_period_id' => $period->id,
                'lines' => [
                    ['account_id' => $kas->id, 'debit' => 1000, 'credit' => 0],
                    ['account_id' => $pendapatan->id, 'debit' => 0, 'credit' => 1000],
                ],
            ]);

        $create->assertCreated();
        $create->assertJsonPath('success', true);
        $this->assertSame('draft', $create->json('data.status'));
    }

    public function test_input_only_user_cannot_void_journal_via_api(): void
    {
        $permissionCreate = Permission::query()->create(['name' => 'journal.create']);
        $permissionVoid = Permission::query()->create(['name' => 'journal.void']);

        $roleInput = Role::query()->create(['name' => 'journal_input']);
        $roleInput->permissions()->sync([$permissionCreate->id]);

        $roleAdmin = Role::query()->create(['name' => 'admin']);
        $roleAdmin->permissions()->sync([$permissionCreate->id, $permissionVoid->id]);

        $period = AccountingPeriod::query()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
            'locked_by' => null,
            'locked_at' => null,
        ]);

        $kas = Account::query()->create(['code' => '1000', 'name' => 'Kas', 'type' => 'asset', 'parent_id' => null, 'is_active' => true]);
        $pendapatan = Account::query()->create(['code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'parent_id' => null, 'is_active' => true]);

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->roles()->sync([$roleAdmin->id]);
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/journals', [
            'date' => '2026-04-21',
            'description' => 'API create',
            'accounting_period_id' => $period->id,
            'lines' => [
                ['account_id' => $kas->id, 'debit' => 1000, 'credit' => 0],
                ['account_id' => $pendapatan->id, 'debit' => 0, 'credit' => 1000],
            ],
        ])->assertCreated();

        $journalId = (int) $created->json('data.id');

        $input = User::query()->create([
            'name' => 'Input',
            'email' => 'input@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $input->roles()->sync([$roleInput->id]);
        $this->assertFalse($input->fresh()->hasPermission('journal.void'));
        $this->assertFalse(Gate::forUser($input->fresh())->allows('journal.void'));
        Sanctum::actingAs($input);

        $voidResponse = $this->postJson("/api/journals/{$journalId}/void", ['reason' => 'Tidak boleh'])
            ->assertForbidden();

        $this->assertNull(
            AuditLog::query()->where('action', 'journal.voided')->where('entity_id', $journalId)->first(),
            'Audit log tidak boleh tercatat bila void ditolak.'
        );
    }
}
