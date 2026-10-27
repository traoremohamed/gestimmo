<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// tâches métier des agences (réservations, loyers, pénalités) — nécessite « php artisan schedule:run » chaque minute (cron)
Schedule::command('immobilier:taches-quotidiennes')->dailyAt('02:00')->withoutOverlapping();

// sauvegardes de la plateforme : vérification toutes les 15 min, lancement à l'heure paramétrée (Plateforme › Sauvegardes) ou manuel
Artisan::command('immobilier:sauvegarde {--contenu=* : BASE, CODE, DOCUMENTS} {--planifiee : respecter la planification}', function () {
    if ($this->option('planifiee') && !\App\Services\Sauvegarde\SauvegardeService::estDue()) {
        return;
    }
    $s = \App\Services\Sauvegarde\SauvegardeService::lancer($this->option('planifiee') ? 'PLANIFIEE' : 'MANUEL', $this->option('contenu') ?: null);
    $this->info("Sauvegarde {$s->numero} : {$s->statut} — " . number_format($s->taille_octets / 1048576, 1) . ' Mo' . ($s->erreur ? " ({$s->erreur})" : ''));
})->purpose('Sauvegarde de la base, du code et des documents');
Schedule::command('immobilier:sauvegarde --planifiee')->everyFifteenMinutes()->withoutOverlapping(720);

// diagnostic de sécurité de l'installation (même contrôles que Plateforme › Sécurité)
Artisan::command('immobilier:securite', function () {
    $controles = \App\Services\Securite\DiagnosticSecurite::controles();
    foreach ($controles as $c) {
        $this->line(sprintf('[%s] %s — %s', $c['ok'] ? ' OK ' : ($c['gravite'] === 'CRITIQUE' ? 'CRIT' : 'ALER'), $c['libelle'], $c['detail']));
    }
    $this->info('Score : ' . \App\Services\Securite\DiagnosticSecurite::score($controles) . ' %');
})->purpose('Diagnostic de sécurité de l\'installation');
