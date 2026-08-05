<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * "Prod Owner" — the role the permission sheet's Prod Owner column describes.
 *
 * It had no counterpart in the app: the sheet grants that column Dashboard
 * Lite, Dashboard > Performance, All Transaction (limited filter) and
 * Tutorial > Management, but every one of those was landing on a role that did
 * not exist, so RolePermissionSyncSeeder's `if ($role)` guard silently dropped
 * them.
 *
 * Only the role itself is created here. Its permissions live in
 * RolePermissionSyncSeeder like every other role's — run this seeder BEFORE
 * that one, otherwise the sync finds no role to grant to.
 *
 * firstOrCreate, not create: this seeder is safe to re-run, and re-running it
 * must not throw on the roles.name unique index.
 */
class ProdOwnerRoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate([
            'name' => 'prod_owner',
            'guard_name' => 'web',
        ]);
    }
}
