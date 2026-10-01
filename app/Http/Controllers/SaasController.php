<?php

namespace App\Http\Controllers;

use App\Models\SaasAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantDatabaseProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaasController extends Controller
{
    public function register()
    {
        return view('saas.register');
    }

    public function storeRegistration(Request $request, TenantDatabaseProvisioner $provisioner)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash:ascii', 'min:3', 'max:40', 'unique:central.tenants,slug', Rule::notIn(['www', 'admin', 'saas', 'superadmin', 'api', 'register', 'lorito'])],
            'owner_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $tenant = Tenant::create([
            'name' => $data['business_name'], 'slug' => strtolower($data['slug']), 'owner_email' => $data['email'],
            'database_name' => 'lorito_tenant_'.strtolower(Str::slug($data['slug'], '_')).'_'.Str::lower(Str::random(5)),
            'subscription_status' => 'trial', 'subscription_plan' => 'Prueba gratis', 'subscription_amount' => 0,
            'trial_ends_at' => now()->addDays(7),
        ]);

        try {
            $provisioner->provision($tenant);
            DB::setDefaultConnection('tenant');
            User::create(['name' => $data['owner_name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'role' => 'admin']);
        } catch (\Throwable $exception) {
            try {
                $provisioner->drop($tenant);
            } catch (\Throwable) {
                report($exception);
            }
            Tenant::whereKey($tenant->id)->delete();
            report($exception);

            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['registration' => 'No se pudo preparar la cuenta. Verifica que el servidor MySQL permita crear bases de datos e inténtalo de nuevo.']);
        }

        $url = rtrim(config('app.url'), '/').'/'.'?tenant='.$tenant->slug;

        return redirect()->away($url)->with('status', 'La cuenta está lista. Ya puedes ingresar con tu correo.');
    }

    public function login()
    {
        return view('saas.login');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $admin = SaasAdmin::where('email', $credentials['email'])->where('role', 'superadmin')->first();
        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            return back()->withErrors(['email' => 'Correo o contraseña incorrectos.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->put('superadmin_id', $admin->id);

        return redirect('/superadmin');
    }

    public function dashboard(Request $request)
    {
        if (! $this->currentSuperadmin($request)) {
            return redirect('/superadmin/login');
        }

        return view('saas.dashboard', ['tenants' => Tenant::orderByDesc('created_at')->get(), 'admin' => $this->currentSuperadmin($request)]);
    }

    public function updateSubscription(Request $request, Tenant $tenant)
    {
        if (! $this->currentSuperadmin($request)) {
            abort(403);
        }
        $data = $request->validate([
            'subscription_status' => ['required', Rule::in(['trial', 'active', 'suspended'])],
            'subscription_plan' => ['required', Rule::in(['Prueba gratis', 'Mensual', 'Anual'])],
            'subscription_amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'trial_ends_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date'],
            'subscription_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['subscription_status'] === 'active' && empty($data['subscription_ends_at'])) {
            $data['subscription_ends_at'] = now()->addMonth();
        }
        $tenant->update($data);

        return back()->with('status', 'Suscripción actualizada para '.$tenant->name.'.');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('superadmin_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/superadmin/login');
    }

    private function currentSuperadmin(Request $request): ?SaasAdmin
    {
        $superadminId = $request->session()->get('superadmin_id');

        return $superadminId ? SaasAdmin::whereKey($superadminId)->where('role', 'superadmin')->first() : null;
    }
}
