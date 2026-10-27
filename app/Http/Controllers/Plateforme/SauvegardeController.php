<?php

namespace App\Http\Controllers\Plateforme;

use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Systeme\Sauvegarde;
use App\Models\Systeme\SauvegardeConfig;
use App\Models\Systeme\SauvegardeDestination;
use App\Services\Sauvegarde\SauvegardeService;
use App\Services\Sauvegarde\Transferts;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Plateforme › Sauvegardes (repris de MARIEATTIEKESAAS) : planification, chiffrement, destinations, lancement manuel, historique.
 * Réservé au super administrateur (middleware ExigerSuperAdmin).
 */
class SauvegardeController extends Controller
{
    public function index()
    {
        return view('systeme.sauvegardes', [
            'config' => SauvegardeConfig::courante(),
            'destinations' => SauvegardeDestination::orderBy('id')->get(),
            'sauvegardes' => Sauvegarde::with('lanceur')->latest('id')->paginate(20),
            'types' => SauvegardeDestination::TYPES,
            'derniere' => Sauvegarde::where('statut', 'SUCCES')->latest('id')->first(),
        ]);
    }

    public function configurer(Request $request)
    {
        $d = $request->validate([
            'actif' => 'nullable|boolean', 'frequence' => 'required|in:QUOTIDIENNE,HEBDOMADAIRE', 'jour_semaine' => 'required|integer|between:1,7',
            'heure' => 'required|date_format:H:i', 'inclure_base' => 'nullable|boolean', 'inclure_code' => 'nullable|boolean', 'inclure_documents' => 'nullable|boolean',
            'inclure_vendor' => 'nullable|boolean', 'retention_locale' => 'required|integer|between:1,365', 'pg_dump_chemin' => 'nullable|string|max:255',
            'cle_chiffrement' => 'nullable|string|min:12|max:200|confirmed', 'supprimer_cle' => 'nullable|boolean',
        ]);
        $config = SauvegardeConfig::courante();
        $config->fill(collect($d)->except(['cle_chiffrement', 'cle_chiffrement_confirmation', 'supprimer_cle'])->all() + [
            'actif' => $request->boolean('actif'), 'inclure_base' => $request->boolean('inclure_base'), 'inclure_code' => $request->boolean('inclure_code'),
            'inclure_documents' => $request->boolean('inclure_documents'), 'inclure_vendor' => $request->boolean('inclure_vendor')]);
        if (!empty($d['cle_chiffrement'])) {
            $config->definirCle($d['cle_chiffrement']);
        } elseif ($request->boolean('supprimer_cle')) {
            $config->definirCle(null);
        }
        $config->save();
        $this->journal('CONFIGURER SAUVEGARDES', $config->id);

        return back()->with('success', 'Configuration des sauvegardes enregistrée.' . (!$config->cle_chiffrement ? ' Attention : sans clé, les archives ne sont pas chiffrées et le fichier .env en est exclu.' : ''));
    }

    public function enregistrerDestination(Request $request, ?SauvegardeDestination $destination = null)
    {
        $d = $request->validate([
            'libelle' => 'required|string|max:120', 'type' => ['required', Rule::in(array_keys(SauvegardeDestination::TYPES))], 'retention' => 'required|integer|between:1,365',
            'actif' => 'nullable|boolean', 'parametres' => 'nullable|array', 'parametres.*' => 'nullable|string|max:2000', 'secrets' => 'nullable|array', 'secrets.*' => 'nullable|string|max:20000',
        ]);
        [, $champs, $secrets] = SauvegardeDestination::TYPES[$d['type']];
        $destination ??= new SauvegardeDestination();
        $destination->fill(['libelle' => $d['libelle'], 'type' => $d['type'], 'retention' => $d['retention'], 'actif' => $request->boolean('actif', true),
            'parametres' => array_intersect_key($d['parametres'] ?? [], array_flip($champs))]);
        $destination->definirSecrets(array_intersect_key($d['secrets'] ?? [], array_flip($secrets)));
        $destination->save();
        $this->journal('ENREGISTRER DESTINATION ' . $destination->type, $destination->id);

        return back()->with('success', 'Destination « ' . $destination->libelle . ' » enregistrée : testez la connexion.');
    }

    public function testerDestination(SauvegardeDestination $destination)
    {
        try {
            $message = Transferts::tester($destination);
            $ok = true;
        } catch (\Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 450);
            $ok = false;
        }
        $destination->update(['dernier_test_at' => now(), 'dernier_test_resultat' => ($ok ? 'OK — ' : 'ÉCHEC — ') . $message]);

        return back()->with($ok ? 'success' : 'error', $destination->libelle . ' : ' . $message);
    }

    public function supprimerDestination(SauvegardeDestination $destination)
    {
        $destination->delete();
        $this->journal('SUPPRIMER DESTINATION ' . $destination->type, $destination->id);

        return back()->with('success', 'Destination supprimée (les archives déjà déposées sont conservées).');
    }

    public function lancer(Request $request)
    {
        $contenu = array_values(array_intersect((array) $request->input('contenu', []), ['BASE', 'CODE', 'DOCUMENTS'])) ?: null;
        try {
            $s = SauvegardeService::lancer('MANUEL', $contenu);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($s->statut === 'SUCCES' ? 'success' : 'error', "Sauvegarde {$s->numero} : " . strtolower($s->statut)
            . ($s->taille_octets ? ' (' . number_format($s->taille_octets / 1048576, 1, ',', ' ') . ' Mo)' : '') . ($s->erreur ? ' — ' . $s->erreur : ''));
    }

    public function telecharger(Sauvegarde $sauvegarde)
    {
        abort_unless($sauvegarde->chemin_local && is_file($sauvegarde->chemin_local), 404, 'Archive locale supprimée par la rétention.');
        $this->journal('TELECHARGER SAUVEGARDE', $sauvegarde->id, ['fichier' => $sauvegarde->fichier]);

        return response()->download($sauvegarde->chemin_local, $sauvegarde->fichier);
    }

    protected function journal(string $action, $piece, array $valeurs = []): void
    {
        Audit::logSave(['action' => $action, 'code_piece' => $piece, 'menu' => 'SAUVEGARDES', 'etat' => 'Succès', 'objet' => 'PLATEFORME', 'nouvelles_valeurs' => $valeurs ?: null]);
    }
}
