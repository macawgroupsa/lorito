<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('central')->table('saas_admins', function (Blueprint $table) {
            $table->string('role', 30)->default('disabled')->index();
        });
        $central = DB::connection('central');
        $central->table('saas_admins')->update(['role' => 'disabled']);
        $central->table('saas_admins')->updateOrInsert(
            ['email' => 'bcotto@macawsa.com'],
            [
                'name' => 'Bryan Cotto',
                'role' => 'superadmin',
                'password' => '$2y$10$pcmW2b/nRwRPUWcjf/QkJ.SnA69pDDc7kJKCFXaLaPHiLLlv/p0m6',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('central')->table('saas_admins')->where('email', 'bcotto@macawsa.com')->delete();
        Schema::connection('central')->table('saas_admins', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
