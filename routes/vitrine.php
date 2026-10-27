<?php

use App\Http\Controllers\Vitrine\SiteController;
use App\Http\Middleware\ResoudreAgenceVitrine;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site vitrine public des agences : /site/{slug}/…
|--------------------------------------------------------------------------
*/

Route::prefix('site/{slug}')->name('vitrine.')->middleware(ResoudreAgenceVitrine::class)->controller(SiteController::class)->group(function () {
    Route::get('/', 'accueil')->name('accueil');
    Route::get('/biens', 'biens')->name('biens');
    Route::get('/biens/{reference}', 'bien')->name('bien');
    Route::post('/biens/{reference}/visite', 'demanderVisite')->middleware('throttle:5,1')->name('visite');
    Route::get('/actualites', 'actualites')->name('actualites');
    Route::get('/actualites/{publication}', 'actualite')->name('actualite');
    Route::get('/contact', 'contact')->name('contact');
    Route::get('/p/{page}', 'page')->where('page', '[a-z0-9-]+')->name('page');
    Route::post('/contact', 'envoyerContact')->middleware('throttle:5,1')->name('contact.envoyer');
});
