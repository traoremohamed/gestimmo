<?php

namespace App\Http\Controllers\Location;

use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Bail;
use App\Models\DecompteLigne;
use App\Models\DecompteSortie;
use App\Models\Referentiel;
use App\Services\Location\SortieService;
use App\Services\Validation\ValidationService;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Décompte de sortie du locataire : préparation, justificatifs, validation, restitution, PDF signé QR.
 */
class SortieController extends Controller
{
    public function __construct(protected SortieService $service)
    {
    }

    public function preparer(Bail $bail)
    {
        $decompte = $this->service->preparer($bail);

        return redirect()->route('decomptes.show', $decompte);
    }

    public function show(DecompteSortie $decompte)
    {
        $decompte->load(['bail.bien.proprietaire', 'bail.etatsLieux', 'locataire', 'lignes.justificatifs', 'encaissement', 'depense', 'etatLieux']);

        return view('location.decompte', [
            'decompte' => $decompte,
            'entree' => $decompte->bail->etatsLieux->where('type', 'entree')->sortByDesc('date')->first(),
            'sortie' => $decompte->etatLieux,
            'typesAjout' => collect(DecompteSortie::TYPES)->except(['depot_garantie', 'caution_sodeci', 'caution_cie', 'impaye'])->map(fn ($t) => $t[0]),
            'modes' => Referentiel::options('mode_paiement'),
        ]);
    }

    public function ajouterLigne(Request $request, DecompteSortie $decompte)
    {
        $types = array_keys(DecompteSortie::TYPES);
        $donnees = $request->validate(['type' => ['required', Rule::in($types)], 'libelle' => ['required', 'string', 'max:255'], 'montant' => ['required', 'numeric', 'min:0'],
            'justificatif' => ['nullable', 'file']]);
        $ligne = $this->service->ajouterLigne($decompte, $donnees['type'], $donnees['libelle'], $donnees['montant']);
        if ($request->hasFile('justificatif')) {
            $this->service->joindre($ligne, $request->file('justificatif'));
        }

        return back()->with('success', 'Ligne ajoutée.');
    }

    public function modifierLigne(Request $request, DecompteSortie $decompte, DecompteLigne $ligne)
    {
        abort_unless($ligne->decompte_id === $decompte->id, 404);
        $donnees = $request->validate(['libelle' => ['required', 'string', 'max:255'], 'montant' => ['required', 'numeric', 'min:0'], 'justificatif' => ['nullable', 'file']]);
        if ($decompte->estModifiable() && !in_array($ligne->type, ['depot_garantie', 'caution_sodeci', 'caution_cie', 'impaye'], true)) {
            $this->service->modifierLigne($ligne, $donnees['libelle'], $donnees['montant']);
        }
        if ($request->hasFile('justificatif')) {
            $this->service->joindre($ligne, $request->file('justificatif'));
        }

        return back()->with('success', 'Ligne enregistrée.');
    }

    public function supprimerLigne(DecompteSortie $decompte, DecompteLigne $ligne)
    {
        abort_unless($ligne->decompte_id === $decompte->id, 404);
        $this->service->supprimerLigne($ligne);

        return back()->with('success', 'Ligne supprimée.');
    }

    public function valider(Request $request, DecompteSortie $decompte)
    {
        if ($attente = $this->soumettre('DECOMPTE_SORTIE', $decompte)) {
            return $attente;
        }
        $this->service->valider($decompte, $request->user());
        Audit::logSave(['action' => 'VALIDER DECOMPTE SORTIE', 'code_piece' => $decompte->numero, 'menu' => 'BAUX', 'etat' => 'Succès', 'objet' => 'GESTION LOCATIVE']);

        return back()->with('success', 'Décompte ' . $decompte->numero . ' validé : ' . $decompte->libelleStatut() . '.');
    }

    public function restituer(Request $request, DecompteSortie $decompte)
    {
        $donnees = $request->validate([
            'mode_paiement' => ['required', Rule::in(Referentiel::options('mode_paiement')->keys())],
            'reference' => [Rule::requiredIf(in_array($request->mode_paiement, ['CHEQUE', 'VIREMENT', 'MOBILE_MONEY'], true)), 'nullable', 'string', 'max:100'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'preuve' => [parametre('sortie.preuve_obligatoire') ? 'required' : 'nullable', 'file'],
        ]);
        if (ValidationService::soumise('RESTITUTION_GARANTIE', $decompte)) {
            // la preuve est archivée tout de suite (GED du décompte) ; le paiement sera enregistré après approbation
            if ($request->hasFile('preuve')) {
                app(\App\Services\Ged\GedService::class)->televerser($request->file('preuve'), ['titre' => 'Preuve de restitution — ' . $decompte->numero, 'categorie' => 'JUSTIFICATIF'], $decompte);
            }

            return $this->soumettre('RESTITUTION_GARANTIE', $decompte, collect($donnees)->except('preuve')->all());
        }
        $this->service->restituer($decompte, $donnees, $request->file('preuve'), $request->user());
        Audit::logSave(['action' => 'RESTITUTION GARANTIES', 'code_piece' => $decompte->numero, 'menu' => 'BAUX', 'etat' => 'Succès', 'objet' => 'GESTION LOCATIVE']);

        return back()->with('success', 'Restitution enregistrée et notifiée au locataire.');
    }

    public function pdf(DecompteSortie $decompte)
    {
        $decompte->load(['bail.bien.proprietaire', 'bail.etatsLieux', 'locataire', 'lignes.justificatifs', 'etatLieux']);

        return Pdf::telecharger('pdf.decompte_sortie', [
            'decompte' => $decompte, 'entree' => $decompte->bail->etatsLieux->where('type', 'entree')->sortByDesc('date')->first(), 'sortie' => $decompte->etatLieux,
        ], 'decompte-' . ($decompte->numero ?? 'brouillon'), objet: $decompte, type: 'DECOMPTE_SORTIE',
            etat: md5($decompte->statut . '|' . $decompte->total_credits . '|' . $decompte->total_retenues . '|' . $decompte->lignes->count()));
    }
}
