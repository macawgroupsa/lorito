<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 60)->unique();
            $table->string('owner_email')->index();
            $table->string('database_name', 64)->unique();
            $table->string('subscription_status', 20)->default('trial')->index();
            $table->string('subscription_plan', 40)->default('Prueba gratis');
            $table->decimal('subscription_amount', 10, 2)->default(0);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->text('subscription_notes')->nullable();
            $table->timestamps();
        });
        Schema::connection('central')->create('saas_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('saas_admins');
        Schema::connection('central')->dropIfExists('tenants');
    }
};
