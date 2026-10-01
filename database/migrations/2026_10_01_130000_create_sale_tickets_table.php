<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_code', 24)->unique();
            $table->foreignId('sales_point_id')->constrained()->restrictOnDelete();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name', 120)->nullable();
            $table->decimal('total_quetzales', 12, 2);
            $table->date('sale_date');
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
            $table->index(['sales_point_id', 'sale_date']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('sale_ticket_id')->nullable()->after('id')->constrained('sale_tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropConstrainedForeignId('sale_ticket_id'));
        Schema::dropIfExists('sale_tickets');
    }
};
