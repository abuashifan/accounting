<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\DTOs\JournalData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Services\JournalService;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class JournalServiceEngineTest extends TestCase
{
    use RefreshDatabase;

    private AccountingPeriod $periodOpen;

    private Account $kas;

    private Account $pendapatan;

    private Account $beban;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        $this->periodOpen = AccountingPeriod::query()->create([
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
            'locked_by' => null,
            'locked_at' => null,
        ]);

        $this->kas = Account::query()->create([
            'code' => '1000',
            'name' => 'Kas',
            'type' => 'asset',
            'parent_id' => null,
            'is_active' => true,
        ]);

        $this->pendapatan = Account::query()->create([
            'code' => '4000',
            'name' => 'Pendapatan',
            'type' => 'revenue',
            'parent_id' => null,
            'is_active' => true,
        ]);

        $this->beban = Account::query()->create([
            'code' => '5000',
            'name' => 'Beban',
            'type' => 'expense',
            'parent_id' => null,
            'is_active' => true,
        ]);
    }

    private function seedRbac(): void
    {
        $permissions = [
            'journal.create',
            'journal.update',
            'journal.void',
            'journal.override_period',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission]);
        }

        Role::query()->firstOrCreate(['name' => 'admin']);
        Role::query()->firstOrCreate(['name' => 'accountant']);
    }

    private function makeUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        $role = Role::query()->create(['name' => 'role_'.uniqid()]);
        $permissionIds = Permission::query()->whereIn('name', $permissions)->pluck('id')->all();
        $role->permissions()->sync($permissionIds);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_update_rewrites_lines_and_creates_audit_old_vs_new(): void
    {
        $user = $this->makeUserWithPermissions(['journal.create', 'journal.update']);
        $this->actingAs($user);

        $service = app(JournalService::class);

        $created = $service->create(new JournalData(
            date: '2026-04-21',
            description: 'Jurnal awal',
            accounting_period_id: $this->periodOpen->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 1000, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 1000),
            ],
        ));

        $this->assertSame('draft', $created->status);

        $updated = $service->update($created->id, new JournalData(
            date: '2026-04-22',
            description: 'Jurnal update',
            accounting_period_id: $this->periodOpen->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 2500, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 2500),
            ],
        ));

        $updated->refresh()->load('journalLines');
        $this->assertSame($created->journal_no, $updated->journal_no);
        $this->assertSame('2026-04-22', $updated->date->format('Y-m-d'));
        $this->assertSame('draft', $updated->status);

        $auditUpdated = AuditLog::query()
            ->where('action', 'journal.updated')
            ->where('entity_id', $updated->id)
            ->first();

        $this->assertNotNull($auditUpdated);
        $this->assertSame($user->id, $auditUpdated->user_id);
        $this->assertNotEmpty($auditUpdated->before);
        $this->assertNotEmpty($auditUpdated->after);
        $this->assertSame('Jurnal awal', $auditUpdated->before['description']);
        $this->assertSame('Jurnal update', $auditUpdated->after['description']);
    }

    public function test_void_sets_status_and_keeps_lines_and_writes_audit(): void
    {
        $user = $this->makeUserWithPermissions(['journal.create', 'journal.void']);
        $this->actingAs($user);

        $service = app(JournalService::class);

        $created = $service->create(new JournalData(
            date: '2026-04-21',
            description: 'Jurnal untuk void',
            accounting_period_id: $this->periodOpen->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 1000, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 1000),
            ],
        ));

        $this->assertSame('draft', $created->status);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $created->id]);

        $voided = $service->void($created->id, 'Kesalahan input');

        $this->assertSame('void', $voided->status);
        $this->assertDatabaseHas('journal_entries', ['id' => $created->id, 'status' => 'void']);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $created->id]);

        $auditVoided = AuditLog::query()
            ->where('action', 'journal.voided')
            ->where('entity_id', $created->id)
            ->first();

        $this->assertNotNull($auditVoided);
        $this->assertSame('draft', $auditVoided->before['status']);
        $this->assertSame('void', $auditVoided->after['status']);
        $this->assertSame('Kesalahan input', $auditVoided->reason);
    }

    public function test_period_closed_blocks_update_without_override_permission(): void
    {
        $periodClosed = AccountingPeriod::query()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_closed' => true,
            'locked_by' => null,
            'locked_at' => now(),
        ]);

        $user = $this->makeUserWithPermissions(['journal.create', 'journal.update']);
        $this->actingAs($user);

        $service = app(JournalService::class);

        $created = $service->create(new JournalData(
            date: '2026-04-21',
            description: 'Jurnal awal',
            accounting_period_id: $this->periodOpen->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 1000, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 1000),
            ],
        ));

        $this->expectException(AuthorizationException::class);

        $service->update($created->id, new JournalData(
            date: '2026-04-22',
            description: 'Pindah ke period closed',
            accounting_period_id: $periodClosed->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 1500, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 1500),
            ],
        ));
    }

    public function test_period_closed_allows_update_with_override_permission(): void
    {
        $periodClosed = AccountingPeriod::query()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_closed' => true,
            'locked_by' => null,
            'locked_at' => now(),
        ]);

        $user = $this->makeUserWithPermissions(['journal.create', 'journal.update', 'journal.override_period']);
        $this->actingAs($user);

        $service = app(JournalService::class);

        $created = $service->create(new JournalData(
            date: '2026-04-21',
            description: 'Jurnal awal',
            accounting_period_id: $this->periodOpen->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 1000, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 1000),
            ],
        ));

        $updated = $service->update($created->id, new JournalData(
            date: '2025-06-01',
            description: 'Override period closed',
            accounting_period_id: $periodClosed->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 2000, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 2000),
            ],
        ));

        $this->assertSame($periodClosed->id, $updated->accounting_period_id);
        $this->assertSame('Override period closed', $updated->description);
    }

    public function test_posting_lifecycle_draft_to_posted_and_blocks_update_after_posted(): void
    {
        $user = $this->makeUserWithPermissions(['journal.create', 'journal.update']);
        $this->actingAs($user);

        $service = app(JournalService::class);

        $created = $service->create(new JournalData(
            date: '2026-04-21',
            description: 'Draft akan dipost',
            accounting_period_id: $this->periodOpen->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 1000, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 1000),
            ],
        ));

        $posted = $service->post($created->id);
        $this->assertSame('posted', $posted->status);

        $this->expectException(ValidationException::class);
        $service->update($created->id, new JournalData(
            date: '2026-04-22',
            description: 'Harus gagal karena posted',
            accounting_period_id: $this->periodOpen->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 1200, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 1200),
            ],
        ));
    }

    public function test_journal_number_is_sequential_per_year_and_unique(): void
    {
        $user = $this->makeUserWithPermissions(['journal.create']);
        $this->actingAs($user);

        $service = app(JournalService::class);

        $j1 = $service->create(new JournalData(
            date: '2026-01-15',
            description: 'No 1',
            accounting_period_id: $this->periodOpen->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 100, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 100),
            ],
        ));

        $j2 = $service->create(new JournalData(
            date: '2026-02-15',
            description: 'No 2',
            accounting_period_id: $this->periodOpen->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 200, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 200),
            ],
        ));

        $this->assertMatchesRegularExpression('/^JRN-2026-\\d{6}$/', $j1->journal_no);
        $this->assertMatchesRegularExpression('/^JRN-2026-\\d{6}$/', $j2->journal_no);
        $this->assertNotSame($j1->journal_no, $j2->journal_no);

        $n1 = (int) substr($j1->journal_no, -6);
        $n2 = (int) substr($j2->journal_no, -6);
        $this->assertSame($n1 + 1, $n2);
    }

    public function test_permission_checks_block_create_update_void_post(): void
    {
        $service = app(JournalService::class);

        $userNoCreate = $this->makeUserWithPermissions([]);
        $this->actingAs($userNoCreate);

        $this->expectException(AuthorizationException::class);
        $service->create(new JournalData(
            date: '2026-04-21',
            description: 'Harus ditolak',
            accounting_period_id: $this->periodOpen->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 1000, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 1000),
            ],
        ));

        $userCreateOnly = $this->makeUserWithPermissions(['journal.create']);
        $this->actingAs($userCreateOnly);

        $created = $service->create(new JournalData(
            date: '2026-04-21',
            description: 'Buat dulu',
            accounting_period_id: $this->periodOpen->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 1000, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 1000),
            ],
        ));

        $this->expectException(AuthorizationException::class);
        $service->update($created->id, new JournalData(
            date: '2026-04-22',
            description: 'Harus ditolak update',
            accounting_period_id: $this->periodOpen->id,
            lines: [
                new JournalLineData(account_id: $this->kas->id, debit: 1100, credit: 0),
                new JournalLineData(account_id: $this->pendapatan->id, debit: 0, credit: 1100),
            ],
        ));
    }
}
