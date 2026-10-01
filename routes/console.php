<?php

use App\Models\SaasAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('saas:install', function () {
    $centralDatabase = (string) env('CENTRAL_DB_DATABASE', 'lorito_central');
    if (! preg_match('/^[a-zA-Z0-9_]+$/', $centralDatabase)) {
        $this->error('CENTRAL_DB_DATABASE solo puede contener letras, números y guion bajo.');

        return 1;
    }
    DB::connection('mysql_server')->statement("CREATE DATABASE IF NOT EXISTS `{$centralDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    DB::purge('central');
    $exitCode = Artisan::call('migrate', ['--database' => 'central', '--path' => 'database/migrations/central', '--force' => true, '--no-interaction' => true]);
    if ($exitCode !== 0) {
        $this->error(Artisan::output());

        return $exitCode;
    }

    Tenant::firstOrCreate(
        ['slug' => env('TENANT_DEFAULT_SLUG', 'lorito')],
        ['name' => 'Lorito local', 'owner_email' => env('SUPERADMIN_EMAIL', 'admin@lorito.local'), 'database_name' => env('DB_DATABASE', 'loritogt'), 'subscription_status' => 'active', 'subscription_plan' => 'Desarrollo local', 'subscription_amount' => 0],
    );

    $email = env('SUPERADMIN_EMAIL');
    $password = env('SUPERADMIN_PASSWORD');
    if ($email === 'bcotto@macawsa.com' && $password) {
        $admin = SaasAdmin::where('email', $email)->where('role', 'superadmin')->first();
        if ($admin) {
            $admin->forceFill(['name' => 'Bryan Cotto', 'password' => Hash::make($password)])->save();
            $this->info("Superadministrador actualizado: {$email}");
        }
    } else {
        $this->info('El superadministrador se crea mediante la migración central.');
    }
    $this->info('SaaS inicializado. Base central: '.$centralDatabase.'; tenant local: '.env('TENANT_DEFAULT_SLUG', 'lorito'));
})->purpose('Prepara el registro SaaS de Lorito y el tenant local');
