<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // agence courante (multi-agences) : après la session et AVANT la liaison des modèles de route,
        // sinon {vente}, {bien}… seraient chargés sans le filtre agence (accès aux données d'une autre agence)
        $middleware->web(append: [\App\Http\Middleware\DefinirAgenceCourante::class]);
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\DefinirAgenceCourante::class,
        );
        $middleware->alias(['module' => \App\Http\Middleware\ModuleActif::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // règle métier non respectée (bien indisponible, trop-perçu…) : retour au formulaire avec le message
        $exceptions->render(function (\DomainException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
    })->create();
