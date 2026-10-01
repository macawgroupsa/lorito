<?php

use App\Http\Controllers\OperationsController;
use App\Http\Controllers\SaasController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));
Route::get('/register', [SaasController::class, 'register']);
Route::post('/register', [SaasController::class, 'storeRegistration']);
Route::get('/superadmin/login', [SaasController::class, 'login'])->name('superadmin.login');
Route::post('/superadmin/login', [SaasController::class, 'authenticate'])->name('superadmin.authenticate');
Route::get('/superadmin', [SaasController::class, 'dashboard'])->name('superadmin.dashboard');
Route::post('/superadmin/tenants/{tenant}/subscription', [SaasController::class, 'updateSubscription'])->name('superadmin.subscription');
Route::post('/superadmin/logout', [SaasController::class, 'logout'])->name('superadmin.logout');
Route::redirect('/saas/login', '/superadmin/login');
Route::redirect('/saas', '/superadmin');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
    if (! Auth::attempt($credentials, $request->boolean('remember'))) {
        return response()->json(['message' => 'Correo o contraseña incorrectos.'], 422);
    }
    $request->session()->regenerate();

    return response()->json(['user' => $request->user(), 'csrf_token' => $request->session()->token()]);
});

Route::middleware('auth')->prefix('api')->group(function () {
    Route::get('/bootstrap', [OperationsController::class, 'bootstrap']);
    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    });

    Route::post('/sales', [OperationsController::class, 'storeSale']);
    Route::post('/tickets', [OperationsController::class, 'storeTicket']);
    Route::get('/tickets/{ticket}/receipt', [OperationsController::class, 'receipt'])->name('tickets.receipt');
    Route::delete('/sales/{sale}', [OperationsController::class, 'deleteSale']);
    Route::middleware('admin')->group(function () {
        Route::post('/plays', [OperationsController::class, 'storePlay']);
        Route::put('/plays/{play}', [OperationsController::class, 'updatePlay']);
        Route::delete('/plays/{play}', [OperationsController::class, 'deletePlay']);
        Route::post('/points', [OperationsController::class, 'storePoint']);
        Route::put('/points/{point}', [OperationsController::class, 'updatePoint']);
        Route::delete('/points/{point}', [OperationsController::class, 'deletePoint']);
        Route::post('/lists', [OperationsController::class, 'storeList']);
        Route::put('/lists/{salesList}', [OperationsController::class, 'updateList']);
        Route::delete('/lists/{salesList}', [OperationsController::class, 'deleteList']);
        Route::post('/results', [OperationsController::class, 'storeResult']);
        Route::post('/users', [OperationsController::class, 'storeUser']);
        Route::put('/users/{user}', [OperationsController::class, 'updateUser']);
        Route::delete('/users/{user}', [OperationsController::class, 'deleteUser']);
    });
});
