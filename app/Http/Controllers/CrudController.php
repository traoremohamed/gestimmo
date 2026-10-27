<?php

namespace App\Http\Controllers;

use App\Helpers\Audit;
use App\Support\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\View;

/**
 * CRUD déclaratif des entités simples : la sous-classe décrit ses champs, colonnes et règles ;
 * les vues génériques crud/index et crud/form les affichent.
 *
 * Champ  : ['nom', 'label', 'type' => text|email|tel|number|montant|date|datetime|textarea|select|checkbox|couleur,
 *           'options' => [...] | Closure, 'requis' => bool, 'col' => 'col-md-6', 'aide' => '...', 'regles' => [...]]
 * Colonne: ['Libellé', fn (Model $m) => string|HtmlString, 'classe css']
 *
 * Les enregistrements sont toujours recherchés via le modèle (scope agence) : un identifiant
 * d'une autre agence renvoie 404.
 */
abstract class CrudController extends Controller
{
    /** @var class-string<Model> */
    protected string $modele;

    protected string $route;

    protected string $titre;

    protected string $titreSingulier;

    protected string $module = '';

    protected array $with = [];

    /** @var string[] colonnes de la recherche plein texte */
    protected array $recherche = [];

    protected ?string $permission = null;

    protected string $tri = 'id';

    protected string $sens = 'desc';

    /** vue dédiée pour la fiche (sinon la fiche renvoie vers la modification) */
    protected ?string $vueFiche = null;

    /** vue partielle de boutons supplémentaires en tête de liste */
    protected ?string $vueActions = null;

    abstract protected function champs(?Model $element = null): array;

    abstract protected function colonnes(): array;

    protected function filtres(): array
    {
        return [];
    }

    protected function filtrer(Builder $query, Request $request): Builder
    {
        return $query;
    }

    protected function regles(?Model $element = null): array
    {
        $regles = [];
        foreach ($this->champs($element) as $champ) {
            if (($champ['type'] ?? null) === 'titre') {
                continue;
            }
            if (isset($champ['regles'])) {
                $regles[$champ['nom']] = $champ['regles'];
                continue;
            }

            $base = !empty($champ['requis']) ? ['required'] : ['nullable'];
            $regles[$champ['nom']] = array_merge($base, match ($champ['type'] ?? 'text') {
                'email' => ['email', 'max:255'],
                'number' => ['numeric'],
                'montant' => ['numeric', 'min:0'],
                'date', 'datetime' => ['date'],
                'checkbox' => ['boolean'],
                'select' => empty($champ['multiple']) ? [\Illuminate\Validation\Rule::in(array_map('strval', array_keys($this->options($champ))))] : ['array'],
                'couleur' => ['regex:/^#[0-9a-fA-F]{6}$/'],
                'image' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
                'textarea' => ['string', 'max:20000'],
                default => ['string', 'max:255'],
            });
        }

        return $regles;
    }

    /**
     * Ajustements avant enregistrement (valeurs calculées, auteur…).
     */
    protected function preparer(array $donnees, Request $request, ?Model $element = null): array
    {
        return $donnees;
    }

    protected function apresEnregistrement(Model $element, Request $request, bool $creation): void
    {
    }

    protected function supprimable(Model $element): bool
    {
        return true;
    }

    public function index(Request $request)
    {
        $query = $this->modele::query()->with($this->with);

        if ($request->filled('q') && $this->recherche) {
            $terme = '%' . mb_strtolower($request->string('q')) . '%';
            $query->where(function ($q) use ($terme) {
                foreach ($this->recherche as $colonne) {
                    $q->orWhereRaw("lower(cast($colonne as text)) like ?", [$terme]);
                }
            });
        }

        $query = $this->filtrer($query, $request)->orderBy($this->tri, $this->sens);

        // export de la liste filtrée (toutes les pages) : Excel, PDF signé QR ou CSV
        if (array_key_exists((string) $request->query('format'), Export::FORMATS)) {
            $colonnes = $this->colonnes();

            return Export::telecharger($request->query('format'), $this->titre, array_map(fn ($c) => $c[0], $colonnes),
                $query->limit(20000)->get()->map(fn ($e) => array_map(fn ($c) => Export::depuisHtml(($c[1])($e)), $colonnes))->all(),
                null, $request->filled('q') ? 'Recherche : ' . $request->string('q') : null);
        }

        $elements = $query->paginate(25)->withQueryString();

        return view('crud.index', $this->donneesVue() + [
            'elements' => $elements,
            'colonnes' => $this->colonnes(),
            'filtres' => $this->filtres(),
        ]);
    }

    public function create()
    {
        return view('crud.form', $this->donneesVue() + ['element' => null, 'champs' => $this->champs()]);
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);
        $element = $this->modele::create($this->preparer($donnees, $request));
        $this->apresEnregistrement($element, $request, true);

        $this->auditer('CREER', $element);

        return redirect()->route($this->route . '.index')->with('success', $this->titreSingulier . ' enregistré(e) avec succès.');
    }

    public function show(string $id)
    {
        $element = $this->trouver($id);

        if ($this->vueFiche) {
            return view($this->vueFiche, $this->donneesVue() + ['element' => $element]);
        }

        return redirect()->route($this->route . '.edit', $element);
    }

    public function edit(string $id)
    {
        $element = $this->trouver($id);

        return view('crud.form', $this->donneesVue() + ['element' => $element, 'champs' => $this->champs($element)]);
    }

    public function update(Request $request, string $id)
    {
        $element = $this->trouver($id);
        $donnees = $this->valider($request, $element);
        $element->update($this->preparer($donnees, $request, $element));
        $this->apresEnregistrement($element, $request, false);

        $this->auditer('MODIFIER', $element);

        return redirect()->route($this->route . '.index')->with('success', $this->titreSingulier . ' modifié(e) avec succès.');
    }

    public function destroy(string $id)
    {
        $element = $this->trouver($id);

        if (!$this->supprimable($element)) {
            return back()->with('error', 'Cet élément est utilisé : il ne peut pas être supprimé. Désactivez-le plutôt.');
        }

        $element->delete();
        $this->auditer('SUPPRIMER', $element);

        return redirect()->route($this->route . '.index')->with('success', $this->titreSingulier . ' supprimé(e).');
    }

    protected function trouver(string $id): Model
    {
        return $this->modele::query()->with($this->with)->findOrFail($id);
    }

    protected function valider(Request $request, ?Model $element = null): array
    {
        $regles = $this->regles($element);
        $donnees = $request->validate($regles, [], $this->attributsValidation($element));

        // cases décochées, images téléversées (disque public), image inchangée si aucun fichier
        foreach ($this->champs($element) as $champ) {
            $type = $champ['type'] ?? null;
            if ($type === 'checkbox') {
                $donnees[$champ['nom']] = $request->boolean($champ['nom']);
            } elseif ($type === 'image') {
                if ($request->hasFile($champ['nom'])) {
                    $donnees[$champ['nom']] = $request->file($champ['nom'])->store('agences/' . agence()?->id . '/' . $this->route, 'public');
                } else {
                    unset($donnees[$champ['nom']]);
                    unset($regles[$champ['nom']]);
                }
            }
        }

        return Arr::only($donnees, array_keys($regles));
    }

    protected function attributsValidation(?Model $element): array
    {
        return collect($this->champs($element))->reject(fn ($c) => ($c['type'] ?? null) === 'titre')
            ->mapWithKeys(fn ($c) => [$c['nom'] => mb_strtolower($c['label'])])->all();
    }

    public function options(array $champ): array
    {
        $options = $champ['options'] ?? [];
        $options = $options instanceof \Closure ? $options() : $options;

        return is_iterable($options) ? collect($options)->all() : [];
    }

    protected function donneesVue(): array
    {
        return [
            'route' => $this->route,
            'titre' => $this->titre,
            'titreSingulier' => $this->titreSingulier,
            'module' => $this->module,
            'permission' => $this->permission ?? $this->route,
            'controleur' => $this,
            'avecRecherche' => !empty($this->recherche),
            'vueActions' => $this->vueActions,
        ];
    }

    protected function auditer(string $action, Model $element): void
    {
        Audit::logSave(['action' => $action, 'code_piece' => $element->getKey(), 'menu' => mb_strtoupper($this->titre), 'etat' => 'Succès', 'objet' => mb_strtoupper($this->module)]);
    }
}
