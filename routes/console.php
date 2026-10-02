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
    $tenantDatabase = (string) env('DB_DATABASE', 'loritogt');
    $databases = [
        'central' => $centralDatabase,
        'del negocio inicial' => $tenantDatabase,
    ];

    foreach ($databases as $databasePurpose => $databaseName) {
        if (! preg_match('/^[a-zA-Z0-9_]{1,64}$/', $databaseName)) {
            $this->error('El nombre de la base '.$databasePurpose.' solo puede contener hasta 64 letras, números y guiones bajos.');

            return 1;
        }
    }

    try {
        foreach ($databases as $databaseName) {
            $databaseExists = DB::connection('mysql_server')->selectOne(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
                [$databaseName],
            );

            if (! $databaseExists) {
                DB::connection('mysql_server')->statement("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
    } catch (Throwable $exception) {
        $this->error('No se pudieron crear las bases de datos de Lorito. El usuario MySQL configurado debe tener permiso para crear bases de datos. '.$exception->getMessage());

        return 1;
    }

    $migrationTargets = [
        'central' => 'database/migrations/central',
        'tenant' => 'database/migrations',
    ];

    foreach ($migrationTargets as $connection => $path) {
        DB::purge($connection);
        $exitCode = Artisan::call('migrate', ['--database' => $connection, '--path' => $path, '--force' => true, '--no-interaction' => true]);
        if ($exitCode !== 0) {
            $this->error(Artisan::output());

            return $exitCode;
        }
    }

    Tenant::firstOrCreate(
        ['slug' => env('TENANT_DEFAULT_SLUG', 'lorito')],
        ['name' => 'Lorito local', 'owner_email' => env('SUPERADMIN_EMAIL', 'admin@lorito.local'), 'database_name' => $tenantDatabase, 'subscription_status' => 'active', 'subscription_plan' => 'Desarrollo local', 'subscription_amount' => 0],
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
    $this->info('SaaS inicializado. Base central: '.$centralDatabase.'; base del negocio inicial: '.$tenantDatabase.'; tenant: '.env('TENANT_DEFAULT_SLUG', 'lorito'));
})->purpose('Prepara el registro SaaS de Lorito y el tenant local');

Artisan::command('saas:deploy', function () {
    if (! app()->environment('production')) {
        $this->info('Aprovisionamiento SaaS omitido fuera de producción.');

        return 0;
    }

    $exitCode = Artisan::call('saas:install');
    $output = trim(Artisan::output());

    if ($output !== '') {
        $this->line($output);
    }

    return $exitCode;
})->purpose('Aprovisiona las bases de datos SaaS durante despliegues de producción');
