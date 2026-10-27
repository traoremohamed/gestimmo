<?php

use App\Models\Agence;
use Illuminate\Support\Facades\Route;

// domaine personnalisé d'une agence : son site vitrine ; sinon la page de connexion
Route::get('/', function () {
    $agence = Agence::where('domaine', request()->getHost())->where('vitrine_active', true)->first();

    return $agence ? redirect()->route('vitrine.accueil', $agence->slug) : redirect()->route('connexion');
});
