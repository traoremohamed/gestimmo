<?php

namespace App\Http\Controllers;

use App\Support\AgenceCourante;

/**
 * Guide d'utilisation : un chapitre par module (config/aide.php).
 */
class AideController extends Controller
{
    public function index(AgenceCourante $agence)
    {
        return view('aide.index', ['chapitres' => $this->chapitres($agence)]);
    }

    public function chapitre(AgenceCourante $agence, string $chapitre)
    {
        $chapitres = $this->chapitres($agence);
        abort_unless(isset($chapitres[$chapitre]), 404);

        return view('aide.chapitre', ['chapitres' => $chapitres, 'code' => $chapitre, 'c' => $chapitres[$chapitre]]);
    }

    protected function chapitres(AgenceCourante $agence): array
    {
        return collect(config('aide.chapitres'))->map(fn ($c) => $c + ['actif' => !$c['module'] || $agence->moduleActif($c['module'])])->all();
    }
}
