<?php

namespace App\Providers;

use App\Http\Middleware\RedirectIfFirstPasswordNotChanged;
use App\Logging\AuditLogger;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

/**
 * Branche le module RBAC : config, canal de log "audit", middlewares et routes.
 * A déclarer dans bootstrap/providers.php.
 */
class RbacServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Canal de log écrivant dans le schéma d'audit
        $this->app['config']->set('logging.channels.audit', [
            'driver' => 'custom',
            'via' => AuditLogger::class,
            'level' => 'info',
        ]);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
        $router->aliasMiddleware('check_first_password_changed_flag', RedirectIfFirstPasswordNotChanged::class);

        Route::middleware('web')->group(base_path('routes/rbac.php'));
    }
}
