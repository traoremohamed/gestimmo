<?php

namespace App\Http\Controllers\Patrimoine;

use App\Enums\RoleTiers;
use App\Enums\StatutBien;
use App\Http\Controllers\CrudController;
use App\Models\Bien;
use App\Models\Immeuble;
use App\Models\Lotissement;
use App\Models\Media;
use App\Models\Programme;
use App\Models\Referentiel;
use App\Models\Tiers;
use App\Services\Patrimoine\BienService;
use App\Support\Cellule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BienController extends CrudController
{
    protected string $modele = Bien::class;

    protected string $route = 'biens';

    protected string $titre = 'Biens';

    protected string $titreSingulier = 'Bien';

    protected string $module = 'Patrimoine';

    protected array $with = ['programme', 'lotissement', 'immeuble', 'proprietaire'];

    protected array $recherche = ['reference', 'libelle', 'ilot', 'numero_lot', 'porte'];

    protected ?string $vueFiche = 'patrimoine.bien';

    protected ?string $vueActions = 'patrimoine._actions_biens';

    public function __construct(protected BienService $service)
    {
    }

    protected function champs(?Model $element = null): array
    {
        return [
            ['nom' => '_t1', 'label' => 'Identification', 'type' => 'titre'],
            ['nom' => 'libelle', 'label' => 'Libellé', 'requis' => true],
            ['nom' => 'reference', 'label' => 'Référence', 'col' => 'col-md-3', 'aide' => 'Laisser vide pour une référence automatique',
                'regles' => ['nullable', 'string', 'max:40', Rule::unique('biens', 'reference')->where('agence_id', agence()?->id)->ignore($element?->id)]],
            ['nom' => 'type_bien', 'label' => 'Type de bien', 'type' => 'select', 'options' => fn () => Referentiel::options('type_bien'), 'requis' => true, 'col' => 'col-md-3'],
            ['nom' => 'usage', 'label' => 'Usage', 'type' => 'select', 'options' => ['habitation' => 'Habitation', 'professionnel' => 'Professionnel', 'mixte' => 'Mixte'], 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'habitation'],
            ['nom' => 'statut', 'label' => 'Statut', 'type' => 'select', 'options' => StatutBien::options(), 'requis' => true, 'col' => 'col-md-3', 'defaut' => 'disponible'],

            ['nom' => '_t2', 'label' => 'Localisation', 'type' => 'titre'],
            ['nom' => 'programme_id', 'label' => 'Programme', 'type' => 'select', 'options' => fn () => Programme::orderBy('nom')->pluck('nom', 'id'), 'col' => 'col-md-4'],
            ['nom' => 'lotissement_id', 'label' => 'Lotissement', 'type' => 'select', 'options' => fn () => Lotissement::orderBy('nom')->pluck('nom', 'id'), 'col' => 'col-md-4'],
            ['nom' => 'immeuble_id', 'label' => 'Immeuble', 'type' => 'select', 'options' => fn () => Immeuble::orderBy('nom')->pluck('nom', 'id'), 'col' => 'col-md-4'],
            ['nom' => 'ilot', 'label' => 'Îlot', 'col' => 'col-md-3'],
            ['nom' => 'numero_lot', 'label' => 'N° de lot', 'col' => 'col-md-3'],
            ['nom' => 'etage', 'label' => 'Étage', 'col' => 'col-md-3'],
            ['nom' => 'porte', 'label' => 'Porte', 'col' => 'col-md-3'],
            ['nom' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['nullable', 'numeric', 'between:-90,90']],
            ['nom' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'col' => 'col-md-3', 'regles' => ['nullable', 'numeric', 'between:-180,180']],

            ['nom' => '_t3', 'label' => 'Caractéristiques', 'type' => 'titre'],
            ['nom' => 'superficie', 'label' => 'Superficie', 'type' => 'number', 'col' => 'col-md-3'],
            ['nom' => 'unite_superficie', 'label' => 'Unité', 'type' => 'select', 'options' => ['m2' => 'm²', 'ha' => 'hectare'], 'col' => 'col-md-3', 'defaut' => 'm2', 'requis' => true],
            ['nom' => 'nb_pieces', 'label' => 'Pièces', 'type' => 'number', 'col' => 'col-md-2', 'regles' => ['nullable', 'integer', 'min:0', 'max:500']],
            ['nom' => 'nb_chambres', 'label' => 'Chambres', 'type' => 'number', 'col' => 'col-md-2', 'regles' => ['nullable', 'integer', 'min:0', 'max:500']],
            ['nom' => 'nb_salons', 'label' => 'Salons', 'type' => 'number', 'col' => 'col-md-2', 'regles' => ['nullable', 'integer', 'min:0', 'max:100']],
            ['nom' => 'nb_salles_bain', 'label' => 'Salles d\'eau', 'type' => 'number', 'col' => 'col-md-2', 'regles' => ['nullable', 'integer', 'min:0', 'max:100']],
            ['nom' => 'nb_toilettes', 'label' => 'Toilettes', 'type' => 'number', 'col' => 'col-md-2', 'regles' => ['nullable', 'integer', 'min:0', 'max:100']],
            ['nom' => 'nb_cuisines', 'label' => 'Cuisines', 'type' => 'number', 'col' => 'col-md-2', 'regles' => ['nullable', 'integer', 'min:0', 'max:100']],
            ['nom' => 'delai_livraison_mois', 'label' => 'Délai de livraison (mois)', 'type' => 'number', 'col' => 'col-md-4', 'regles' => ['nullable', 'integer', 'min:0', 'max:240']],
            ['nom' => 'equipements', 'label' => 'Équipements', 'type' => 'select', 'multiple' => true, 'options' => fn () => Referentiel::options('equipement'), 'col' => 'col-md-8'],

            ['nom' => '_t4', 'label' => 'Commercialisation', 'type' => 'titre'],
            ['nom' => 'a_vendre', 'label' => 'À vendre', 'type' => 'checkbox', 'col' => 'col-md-4'],
            ['nom' => 'a_louer', 'label' => 'À louer', 'type' => 'checkbox', 'col' => 'col-md-4'],
            ['nom' => 'meuble', 'label' => 'Meublé (location à la nuitée)', 'type' => 'checkbox', 'col' => 'col-md-4'],
            ['nom' => 'prix_vente', 'label' => 'Prix de vente', 'type' => 'montant', 'col' => 'col-md-3'],
            ['nom' => 'loyer_mensuel', 'label' => 'Loyer mensuel', 'type' => 'montant', 'col' => 'col-md-3'],
            ['nom' => 'charges_mensuelles', 'label' => 'Charges mensuelles', 'type' => 'montant', 'col' => 'col-md-3'],
            ['nom' => 'prix_nuitee', 'label' => 'Prix de la nuitée', 'type' => 'montant', 'col' => 'col-md-3'],
            ['nom' => 'proprietaire_id', 'label' => 'Propriétaire / mandant (gestion pour compte de tiers)', 'type' => 'select', 'recherche' => true,
                'options' => fn () => Tiers::avecRole(RoleTiers::Proprietaire)->orderBy('nom')->get()->pluck('nom_complet', 'id')],
            ['nom' => 'publie', 'label' => 'Publié sur le site vitrine', 'type' => 'checkbox'],
            ['nom' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rows' => 4],
        ];
    }

    protected function colonnes(): array
    {
        return [
            ['Bien', fn ($b) => Cellule::deuxLignes($b->reference . ' — ' . $b->libelle, collect([$b->programme?->nom, $b->lotissement?->nom, $b->immeuble?->nom])->filter()->implode(' · '))],
            ['Type', fn ($b) => Referentiel::libelle('type_bien', $b->type_bien)],
            ['Superficie', fn ($b) => $b->superficie ? number_format((float) $b->superficie, 0, ',', ' ') . ' ' . ($b->unite_superficie === 'ha' ? 'ha' : 'm²') : '', 'text-end'],
            ['Prix / loyer', fn ($b) => $b->a_vendre ? Cellule::montant($b->prix_vente) : ($b->a_louer ? Cellule::montant($b->loyer_mensuel) : ($b->meuble ? Cellule::montant($b->prix_nuitee) : '')), 'text-end'],
            ['Destination', fn ($b) => collect([$b->a_vendre ? 'Vente' : null, $b->a_louer ? 'Location' : null, $b->meuble ? 'Meublé' : null])->filter()->implode(', ')],
            ['Statut', fn ($b) => Cellule::statut($b->statut)],
        ];
    }

    protected function filtres(): array
    {
        return [
            ['nom' => 'type_bien', 'label' => 'Type', 'options' => fn () => Referentiel::options('type_bien')],
            ['nom' => 'statut', 'label' => 'Statut', 'options' => StatutBien::options()],
            ['nom' => 'programme_id', 'label' => 'Programme', 'options' => fn () => Programme::orderBy('nom')->pluck('nom', 'id')],
            ['nom' => 'destination', 'label' => 'Destination', 'options' => ['vente' => 'À vendre', 'location' => 'À louer', 'meuble' => 'Meublé']],
        ];
    }

    protected function filtrer(Builder $query, $request): Builder
    {
        return $query->filtrer($request->only(['type_bien', 'statut', 'programme_id', 'lotissement_id', 'immeuble_id', 'destination']));
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);
        $bien = $this->service->creer($donnees);
        $this->auditer('CREER', $bien);

        return redirect()->route('biens.show', $bien)->with('success', 'Bien ' . $bien->reference . ' créé.');
    }

    public function update(Request $request, string $id)
    {
        $bien = $this->trouver($id);
        $this->service->modifier($bien, $this->valider($request, $bien));
        $this->auditer('MODIFIER', $bien);

        return redirect()->route('biens.show', $bien)->with('success', 'Bien modifié.');
    }

    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        $donnees['equipements'] = $request->input('equipements', []);

        return $donnees;
    }

    protected function valider(Request $request, ?Model $element = null): array
    {
        return $this->preparer(parent::valider($request, $element), $request, $element);
    }

    protected function supprimable(Model $element): bool
    {
        return !$element->venteBiens()->exists() && !$element->baux()->exists() && !$element->sejours()->exists();
    }

    public function show(string $id)
    {
        $bien = $this->trouver($id);
        $bien->load(['medias', 'reservations.tiers', 'venteBiens.vente.acquereur', 'baux.locataire', 'mandat']);

        return view('patrimoine.bien', $this->donneesVue() + ['element' => $bien]);
    }

    public function generation()
    {
        return view('patrimoine.generation', [
            'lotissements' => Lotissement::orderBy('nom')->pluck('nom', 'id'),
            'immeubles' => Immeuble::orderBy('nom')->pluck('nom', 'id'),
            'programmes' => Programme::orderBy('nom')->pluck('nom', 'id'),
            'types' => Referentiel::options('type_bien'),
        ]);
    }

    public function generer(Request $request)
    {
        $donnees = $request->validate([
            'cible' => ['required', Rule::in(['lotissement', 'immeuble'])],
            'lotissement_id' => ['required_if:cible,lotissement', 'nullable', Rule::exists('lotissements', 'id')->where('agence_id', agence()->id)],
            'immeuble_id' => ['required_if:cible,immeuble', 'nullable', Rule::exists('immeubles', 'id')->where('agence_id', agence()->id)],
            'programme_id' => ['nullable', Rule::exists('programmes', 'id')->where('agence_id', agence()->id)],
            'type_bien' => ['required', Rule::in(Referentiel::options('type_bien')->keys())],
            'prefixe' => ['required', 'string', 'max:60'],
            'ilot' => ['nullable', 'string', 'max:50'],
            'debut' => ['required', 'integer', 'min:0'],
            'fin' => ['required', 'integer', 'gte:debut'],
            'par_etage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'superficie' => ['nullable', 'numeric', 'min:0'],
            'nb_pieces' => ['nullable', 'integer', 'min:0'],
            'nb_chambres' => ['nullable', 'integer', 'min:0'],
            'prix_vente' => ['nullable', 'numeric', 'min:0'],
            'loyer_mensuel' => ['nullable', 'numeric', 'min:0'],
            'a_vendre' => ['boolean'],
            'a_louer' => ['boolean'],
        ]);

        $modele = array_filter([
            'type_bien' => $donnees['type_bien'],
            'programme_id' => $donnees['programme_id'] ?? null,
            'lotissement_id' => $donnees['cible'] === 'lotissement' ? $donnees['lotissement_id'] : null,
            'immeuble_id' => $donnees['cible'] === 'immeuble' ? $donnees['immeuble_id'] : null,
            'ilot' => $donnees['ilot'] ?? null,
            'superficie' => $donnees['superficie'] ?? null,
            'nb_pieces' => $donnees['nb_pieces'] ?? null,
            'nb_chambres' => $donnees['nb_chambres'] ?? null,
            'prix_vente' => $donnees['prix_vente'] ?? null,
            'loyer_mensuel' => $donnees['loyer_mensuel'] ?? null,
        ], fn ($v) => $v !== null && $v !== '') + ['a_vendre' => $request->boolean('a_vendre'), 'a_louer' => $request->boolean('a_louer')];

        if (!empty($modele['immeuble_id']) && empty($modele['programme_id'])) {
            $modele['programme_id'] = Immeuble::find($modele['immeuble_id'])?->programme_id;
        }
        if (!empty($modele['lotissement_id']) && empty($modele['programme_id'])) {
            $modele['programme_id'] = Lotissement::find($modele['lotissement_id'])?->programme_id;
        }

        $nombre = $this->service->generer($modele, (int) $donnees['debut'], (int) $donnees['fin'], $donnees['prefixe'], (int) ($donnees['par_etage'] ?? 0));
        $this->auditer('GENERER', new Bien());

        return redirect()->route('biens.index', array_filter(['lotissement_id' => $modele['lotissement_id'] ?? null, 'immeuble_id' => $modele['immeuble_id'] ?? null]))
            ->with('success', $nombre . ' bien(s) généré(s). Les numéros déjà existants ont été ignorés.');
    }

    public function ajouterMedia(Request $request, Bien $bien)
    {
        $collection = $request->validate(['collection' => ['required', Rule::in(['photos', 'plans', 'documents'])]])['collection'];
        $request->validate([
            'fichiers' => ['required', 'array', 'max:20'],
            'fichiers.*' => ['file', 'max:8192', 'mimes:' . ($collection === 'photos' ? BienService::MIMES_PHOTOS : BienService::MIMES_DOCUMENTS)],
        ]);

        foreach ($request->file('fichiers') as $fichier) {
            $this->service->ajouterMedia($bien, $fichier, $collection);
        }

        return back()->with('success', count($request->file('fichiers')) . ' fichier(s) ajouté(s).');
    }

    public function supprimerMedia(Bien $bien, Media $media)
    {
        abort_unless($media->mediable_type === $bien->getMorphClass() && $media->mediable_id === $bien->id, 404);
        $media->delete();

        return back()->with('success', 'Fichier supprimé.');
    }
}
