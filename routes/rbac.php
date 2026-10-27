<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\ConnexionController;
use App\Http\Controllers\GenerermenuController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ParametreController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SousmenuController;
use App\Http\Controllers\TableauBordController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module RBAC / CONFIGURATION
|--------------------------------------------------------------------------
| Chargées automatiquement par App\Providers\RbacServiceProvider (groupe "web").
*/

Route::match(['get', 'post'], '/login', [ConnexionController::class, 'login'])->name('login');
Route::match(['get', 'post'], '/connexion', [ConnexionController::class, 'login'])->name('connexion');
Route::view('/403', 'errors.403')->name('403');

Route::middleware('auth')->group(function () {

    Route::post('/deconnexion', [ConnexionController::class, 'logout'])->name('logout');

    Route::match(['get', 'post'], '/modifiermotdepasse', [ProfilController::class, 'updatepassword'])->name('modifier.mot.passe');

    Route::middleware('check_first_password_changed_flag')->group(function () {

        Route::get('/dashboard', [TableauBordController::class, 'index'])->name('dashboard');

        // profils : propres à l'agence (ou modèles de la plateforme), une permission par action
        Route::get('/roles', [RoleController::class, 'index'])->middleware('can:role-index')->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->middleware('can:role-create')->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->middleware('can:role-create')->name('roles.store');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->middleware('can:role-index')->name('roles.show');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('can:role-edit')->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('can:role-edit')->name('roles.update');

        // utilisateurs de l'agence
        Route::get('/users', [UserController::class, 'index'])->middleware('can:users-index')->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->middleware('can:users-create')->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->middleware('can:users-create')->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->middleware('can:users-index')->name('users.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('can:users-edit')->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->middleware('can:users-edit')->name('users.update');
        Route::post('/users/{user}/deverrouiller', [UserController::class, 'deverrouiller'])->middleware('can:users-edit')->name('users.deverrouiller');

        // structure des menus et permissions : administration de la plateforme
        Route::middleware(\App\Http\Middleware\ExigerSuperAdmin::class)->group(function () {
            Route::resource('permissions', PermissionController::class)->except(['destroy', 'show']);
            Route::resource('menus', MenuController::class)->except(['destroy', 'show']);
            Route::resource('sousmenus', SousmenuController::class)->except(['destroy', 'show']);
        });

        Route::middleware('can:attribuer')->group(function () {
            Route::get('/menuprofil', [GenerermenuController::class, 'parametragemenu'])->name('menuprofil');
            Route::match(['get', 'post'], '/menuprofillayout/{id}', [GenerermenuController::class, 'menuprofillayout'])->name('menuprofillayout');
        });

        Route::middleware(['can:parametresysteme-index', \App\Http\Middleware\ExigerSuperAdmin::class])->group(function () {
            Route::get('/parametresysteme', [ParametreController::class, 'parametresysteme'])->name('parametresysteme');
            Route::match(['get', 'post'], '/creerparametresysteme', [ParametreController::class, 'creerparametresysteme'])
                ->middleware('can:parametresysteme-create')->name('creerparametresysteme');
            Route::match(['get', 'post'], '/modifierparametresysteme/{id}', [ParametreController::class, 'modifierparametresysteme'])
                ->middleware('can:parametresysteme-edit')->name('modifierparametresysteme');
            Route::get('/activeparametresysteme/{id}/{id1}', [ParametreController::class, 'activeparametresysteme'])
                ->middleware('can:parametresysteme-edit')->name('activeparametresysteme');
            Route::get('/desactiveparametresysteme/{id}/{id1}', [ParametreController::class, 'desactiveparametresysteme'])
                ->middleware('can:parametresysteme-edit')->name('desactiveparametresysteme');
        });

        Route::middleware('can:audit-index')->group(function () {
            Route::get('/audits', [AuditController::class, 'index'])->name('audits.index');
        });
    });
});
