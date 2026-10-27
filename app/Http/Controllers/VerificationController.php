<?php

namespace App\Http\Controllers;

use App\Support\QrCode;
use Illuminate\Http\Request;

/**
 * Vérification publique d'un document signé QR : authenticité, révocation et, si un fichier est présenté,
 * conformité à l'original archivé (empreinte SHA-256). Aucune donnée personnelle n'est affichée.
 */
class VerificationController extends Controller
{
    public function show(string $jeton)
    {
        [$resultat, $qr] = QrCode::verifier($jeton);

        return view('verification', ['resultat' => $resultat, 'qr' => $qr?->load('agence'), 'conforme' => null, 'jetonPresente' => $jeton]);
    }

    public function comparer(Request $request, string $jeton)
    {
        $request->validate(['fichier' => ['required', 'file', 'max:51200']]);
        [$resultat, $qr, $conforme] = QrCode::verifier($jeton, hash_file('sha256', $request->file('fichier')->getRealPath()));

        return view('verification', ['resultat' => $resultat, 'qr' => $qr?->load('agence'), 'conforme' => $conforme, 'jetonPresente' => $jeton]);
    }
}
