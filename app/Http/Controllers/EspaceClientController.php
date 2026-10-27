<?php

namespace App\Http\Controllers;

use App\Models\Encaissement;
use App\Models\Loyer;
use App\Models\Reversement;
use App\Models\Tiers;
use App\Services\Finance\EncaissementService;
use App\Support\Pdf;
use Illuminate\Support\Facades\Auth;

/**
 * Espace client : un acquéreur, locataire, propriétaire ou copropriétaire consulte
 * uniquement ses propres contrats, sommes dues et reçus (contrôle d'appartenance
 * systématique, contrairement à l'ancien espace client).
 */
class EspaceClientController extends Controller
{
    public function index(EncaissementService $caisse)
    {
        $tiers = $this->tiers();
        $tiers->load(['ventes.echeances', 'ventes.biens', 'baux.bien', 'biensEnGestion', 'lotsCopropriete.copropriete', 'sejours.bien']);

        return view('espace.index', [
            'tiers' => $tiers,
            'dettes' => $caisse->dettesOuvertes($tiers),
            'encaissements' => $tiers->encaissements()->valides()->latest('date_encaissement')->limit(30)->get(),
            'quittances' => Loyer::whereNotNull('quittance_numero')->whereHas('bail', fn ($q) => $q->where('locataire_id', $tiers->id))->latest('date_echeance')->limit(24)->get(),
            'reversements' => Reversement::where('proprietaire_id', $tiers->id)->latest()->limit(12)->get(),
        ]);
    }

    public function recu(Encaissement $encaissement)
    {
        abort_unless($encaissement->tiers_id === $this->tiers()->id, 404);
        $encaissement->load(['tiers', 'caissier', 'affectations.affectable']);

        return Pdf::telecharger('pdf.recu', ['encaissement' => $encaissement], 'recu-' . $encaissement->numero, objet: $encaissement, type: 'RECU', etat: $encaissement->statut);
    }

    public function quittance(Loyer $loyer)
    {
        $loyer->load(['bail.locataire', 'bail.bien.proprietaire', 'affectations.encaissement']);
        abort_unless($loyer->bail->locataire_id === $this->tiers()->id && $loyer->quittance_numero, 404);

        return Pdf::telecharger('pdf.quittance', ['loyer' => $loyer], 'quittance-' . $loyer->quittance_numero, objet: $loyer, type: 'QUITTANCE', etat: $loyer->quittance_numero);
    }

    protected function tiers(): Tiers
    {
        return Tiers::where('user_id', Auth::id())->first()
            ?? abort(403, 'Votre compte n\'est rattaché à aucune fiche client. Contactez votre agence.');
    }
}
