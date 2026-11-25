<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const array NAMES = ['fees.receipt.post', 'fees.receipt.reverse'];

    public function up(): void
    {
        DB::table('permissions')->insert(array_map(
            fn (string $name): array => ['name' => $name, 'guard_name' => 'web'],
            self::NAMES,
        ));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('guard_name', 'web')->whereIn('name', self::NAMES)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
