<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantDatabaseProvisioner
{
    public function drop(Tenant $tenant): void
    {
        if (! preg_match('/^lorito_tenant_[a-z0-9_]+$/', $tenant->database_name)) throw new RuntimeException('El nombre de la base de datos no es válido.');
        DB::purge('tenant');
        DB::connection('mysql_server')->statement("DROP DATABASE IF EXISTS `{$tenant->database_name}`");
    }

    public function provision(Tenant $tenant): void
    {
        if (! preg_match('/^lorito_tenant_[a-z0-9_]+$/', $tenant->database_name)) {
            throw new RuntimeException('El nombre de la base de datos no es válido.');
        }

        DB::connection('mysql_server')->statement("CREATE DATABASE `{$tenant->database_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        config(['database.connections.tenant.database' => $tenant->database_name]);
        DB::purge('tenant');

        try {
            $exitCode = Artisan::call('migrate', ['--database' => 'tenant', '--path' => 'database/migrations', '--force' => true, '--no-interaction' => true]);
            if ($exitCode !== 0) throw new RuntimeException('No se pudieron preparar las tablas del nuevo cliente. '.Artisan::output());
        } catch (\Throwable $exception) {
            $this->drop($tenant);
            throw $exception;
        }
    }
}
