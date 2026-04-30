<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SuperAdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        $entryUserId = (int) env('SUPERADMIN_SEED_ENTRY', 1706);

        DB::table('trrole')->updateOrInsert(
            ['c_role' => 'A-0'],
            [
                'n_role'   => 'SuperAdmin',
                'e_role'   => 'ini role punya Superadmin',
                'f_active' => true,
                'i_entry'  => $entryUserId,
                'd_entry'  => now(),
            ]
        );
    }
}