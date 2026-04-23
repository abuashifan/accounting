<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'journal.create' => 'Buat jurnal',
            'journal.update' => 'Update jurnal (draft)',
            'journal.void' => 'Void jurnal',
            'journal.override_period' => 'Override period closed',
            'settings.manage' => 'Kelola pengaturan aplikasi',
            'transactions.override_posted_edit_delete' => 'Override edit/delete transaksi posted (dangerous)',
        ];

        foreach ($permissions as $name => $label) {
            Permission::query()->updateOrCreate(
                ['name' => $name],
                ['label' => $label],
            );
        }

        $admin = Role::query()->updateOrCreate(
            ['name' => 'admin'],
            ['label' => 'Administrator'],
        );
        $accountant = Role::query()->updateOrCreate(
            ['name' => 'accountant'],
            ['label' => 'Accountant'],
        );
        $journalInput = Role::query()->updateOrCreate(
            ['name' => 'journal_input'],
            ['label' => 'Journal Input Only'],
        );

        $admin->permissions()->sync(Permission::query()->pluck('id')->all());
        $accountant->permissions()->sync(
            Permission::query()
                ->whereIn('name', ['journal.create', 'journal.update', 'journal.void'])
                ->pluck('id')
                ->all()
        );
        $journalInput->permissions()->sync(
            Permission::query()
                ->whereIn('name', ['journal.create'])
                ->pluck('id')
                ->all()
        );

        $adminUser = User::query()->where('email', 'admin@example.com')->first();
        if ($adminUser) {
            $adminUser->roles()->syncWithoutDetaching([$admin->id]);
        }

        $accountantUser = User::query()->updateOrCreate(
            ['email' => 'accountant@example.com'],
            ['name' => 'Accountant', 'password' => bcrypt('password'), 'email_verified_at' => now()]
        );
        $accountantUser->roles()->syncWithoutDetaching([$accountant->id]);

        $inputUser = User::query()->updateOrCreate(
            ['email' => 'input@example.com'],
            ['name' => 'Journal Input', 'password' => bcrypt('password'), 'email_verified_at' => now()]
        );
        $inputUser->roles()->syncWithoutDetaching([$journalInput->id]);
    }
}
