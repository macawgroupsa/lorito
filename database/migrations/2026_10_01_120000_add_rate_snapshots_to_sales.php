<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('commission_percentage', 5, 2)->default(0)->after('amount_quetzales');
            $table->unsignedInteger('pieces_per_quetzal')->default(80)->after('commission_percentage');
            $table->decimal('prize_per_quetzal', 12, 2)->default(80)->after('pieces_per_quetzal');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['commission_percentage', 'pieces_per_quetzal', 'prize_per_quetzal']);
        });
    }
};
