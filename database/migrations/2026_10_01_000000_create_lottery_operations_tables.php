<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('draw_time');
            $table->unsignedSmallInteger('lock_minutes')->default(10);
            $table->unsignedInteger('pieces_per_quetzal')->default(80);
            $table->decimal('prize_per_quetzal', 12, 2)->default(80);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('sales_points', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('sales_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_point_id')->constrained('sales_points')->cascadeOnDelete();
            $table->foreignId('play_id')->constrained('plays')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('commission_percentage', 5, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['sales_point_id', 'play_id', 'name']);
        });

        Schema::create('draw_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('play_id')->constrained()->cascadeOnDelete();
            $table->date('draw_date');
            $table->string('winning_number', 10);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('entered_at')->nullable();
            $table->timestamps();
            $table->unique(['play_id', 'draw_date']);
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_list_id')->constrained('sales_lists')->cascadeOnDelete();
            $table->foreignId('play_id')->constrained()->cascadeOnDelete();
            $table->date('sale_date');
            $table->string('number', 10);
            $table->decimal('amount_quetzales', 10, 2);
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sold_at')->useCurrent();
            $table->timestamps();
            $table->index(['play_id', 'sale_date', 'number']);
            $table->index(['sales_list_id', 'sale_date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admin');
            $table->foreignId('sales_point_id')->nullable()->constrained('sales_points')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_point_id');
            $table->dropColumn('role');
        });
        Schema::dropIfExists('sales');
        Schema::dropIfExists('draw_results');
        Schema::dropIfExists('sales_lists');
        Schema::dropIfExists('sales_points');
        Schema::dropIfExists('plays');
    }
};
