<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('register*')) {
            return $next($request);
        }
        if ($request->is('saas*') || $request->is('superadmin*')) {
            config(['database.default' => 'mysql', 'session.cookie' => 'lorito_platform_session']);
            DB::setDefaultConnection('mysql');

            return $next($request);
        }

        $slug = $request->header('X-Tenant') ?: $request->query('tenant');
        $baseDomain = (string) env('TENANT_BASE_DOMAIN', '');
        $host = $request->getHost();
        if (! $slug && $baseDomain && str_ends_with($host, '.'.$baseDomain)) {
            $slug = substr($host, 0, -strlen('.'.$baseDomain));
        }
        $slug ??= env('TENANT_DEFAULT_SLUG', 'lorito');

        $tenant = Tenant::where('slug', $slug)->first();
        if (! $tenant) {
            return response('Este negocio no está registrado. Revisa el enlace de acceso.', 404);
        }
        if (! $tenant->isAvailable()) {
            $message = 'La suscripción de este negocio está vencida o pausada. Contacta a Lorito para reactivarla.';

            return $request->expectsJson() ? response()->json(['message' => $message], 402) : response($message, 402);
        }

        config([
            'database.connections.tenant.database' => $tenant->database_name,
            'database.default' => 'tenant',
            'session.cookie' => 'lorito_session_'.$tenant->slug,
            'cache.prefix' => 'lorito:'.$tenant->slug.':',
        ]);
        DB::purge('tenant');
        DB::setDefaultConnection('tenant');
        app()->instance('currentTenant', $tenant);

        return $next($request);
    }
}
