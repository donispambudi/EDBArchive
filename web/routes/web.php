<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DatabaseColumnController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\FheContextController;
use App\Http\Controllers\FheJobController;
use App\Http\Controllers\FheKeyRegistryController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\SchemeController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\UserController;
use App\Models\FheContext;
use App\Models\FheKeyRegistry;
use App\Models\Library;
use App\Models\ProviderDatabase;
use App\Models\Scheme;
use App\Models\Share;
use App\Models\ShareItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Admin Starter Layout
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $stats = [
            'active_shares' => Share::where('status', 'released')->count(),
            'users' => User::count(),
            'databases' => ProviderDatabase::count(),
            'libraries' => Library::where('is_active', true)->count(),
            'schemes' => Scheme::where('is_active', true)->count(),
        ];

        $shares = Share::with(['database', 'owner', 'recipient'])
            ->withCount('items')
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        $pendingContextCount = FheContext::where('context_status', '<>', FheContext::STATUS_GENERATED)->count();

        $pendingContexts = FheContext::with('schemeRecord.library')
            ->where('context_status', '<>', FheContext::STATUS_GENERATED)
            ->latest()
            ->limit(6)
            ->get();

        $pendingKeyCount = FheKeyRegistry::where('generation_status', '<>', FheKeyRegistry::GENERATION_GENERATED)->count();

        $pendingKeys = FheKeyRegistry::with(['owner', 'fheContext.schemeRecord.library'])
            ->where('generation_status', '<>', FheKeyRegistry::GENERATION_GENERATED)
            ->latest()
            ->limit(6)
            ->get();

        $shareItemEncryptionRaw = ShareItem::query()
            ->selectRaw('encryption, COUNT(*) as total')
            ->groupBy('encryption')
            ->pluck('total', 'encryption');
        $shareItemEncryptionTotal = (int) $shareItemEncryptionRaw->sum();
        $shareItemEncryptionChart = collect(ShareItem::ENCRYPTION_TYPES)
            ->mapWithKeys(fn (string $encryption) => [
                $encryption => (int) ($shareItemEncryptionRaw[$encryption] ?? 0),
            ]);

        $shareStatusRaw = Share::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $shareStatusTotal = (int) $shareStatusRaw->sum();
        $shareStatusChart = collect(Share::STATUSES)
            ->mapWithKeys(fn (string $status) => [
                $status => (int) ($shareStatusRaw[$status] ?? 0),
            ]);

        return view('dashboard', compact(
            'stats',
            'shares',
            'pendingContextCount',
            'pendingContexts',
            'pendingKeyCount',
            'pendingKeys',
            'shareItemEncryptionChart',
            'shareItemEncryptionTotal',
            'shareStatusChart',
            'shareStatusTotal'
        ));
    })->name('dashboard');

    Route::resource('users', UserController::class);
    Route::resource('libraries', LibraryController::class);
    Route::resource('schemes', SchemeController::class);
    Route::post('/fhe-contexts/{fheContext}/generate', [FheContextController::class, 'generate'])
        ->name('fhe-contexts.generate');
    Route::resource('fhe-contexts', FheContextController::class);
    Route::post('/fhe-key-registry/{keyRegistry}/generate', [FheKeyRegistryController::class, 'generate'])
        ->name('fhe-key-registry.generate');
    Route::resource('fhe-key-registry', FheKeyRegistryController::class)
        ->parameters(['fhe-key-registry' => 'keyRegistry']);
    Route::resource('fhe-jobs', FheJobController::class)
        ->only(['index', 'show'])
        ->parameters(['fhe-jobs' => 'fheJob']);
    Route::get('/databases/{database}/columns', [DatabaseColumnController::class, 'editDatabaseColumns'])
        ->name('databases.columns.edit');
    Route::put('/databases/{database}/columns', [DatabaseColumnController::class, 'updateDatabaseColumns'])
        ->name('databases.columns.update');
    Route::resource('databases', DatabaseController::class);
    Route::resource('database-columns', DatabaseColumnController::class)
        ->parameters(['database-columns' => 'databaseColumn']);
    Route::post('/shares/{share}/items/bulk', [ShareController::class, 'storeAllConfiguredItems'])->name('shares.items.bulk-store');
    Route::delete('/shares/{share}/items', [ShareController::class, 'destroyAllItems'])->name('shares.items.destroy-all');
    Route::post('/shares/{share}/items', [ShareController::class, 'storeItem'])->name('shares.items.store');
    Route::put('/shares/{share}/items/{item}', [ShareController::class, 'updateItem'])->name('shares.items.update');
    Route::delete('/shares/{share}/items/{item}', [ShareController::class, 'destroyItem'])->name('shares.items.destroy');
    Route::get('/shares/{share}/bundle/download', [ShareController::class, 'downloadBundle'])->name('shares.bundle.download');
    Route::post('/shares/{share}/bundle/generate', [ShareController::class, 'generateBundle'])->name('shares.bundle.generate');
    Route::resource('shares', ShareController::class);

    Route::get('/reports', function () {
        return view('dashboard');
    })->name('reports.index');

    Route::get('/reports/monthly', function () {
        return view('dashboard');
    })->name('reports.monthly');

    Route::get('/reports/annual', function () {
        return view('dashboard');
    })->name('reports.annual');

    Route::get('/settings', function () {
        return view('dashboard');
    })->name('settings.index');

    Route::get('/profile', function () {
        return view('dashboard');
    })->name('profile');

    Route::get('/password/change', function () {
        return view('auth.change-password');
    })->name('password.change');

    Route::post('/password/change', function (Request $request) {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return redirect()
            ->route('password.change')
            ->with('status', 'Password updated successfully.');
    })->name('password.update');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
