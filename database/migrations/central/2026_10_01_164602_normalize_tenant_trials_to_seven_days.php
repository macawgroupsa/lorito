<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::connection('central')->table('tenants')->where('subscription_status', 'trial')
            ->update(['trial_ends_at' => DB::raw('DATE_ADD(created_at, INTERVAL 7 DAY)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing trial dates are not restored when this data migration is rolled back.
    }
};
