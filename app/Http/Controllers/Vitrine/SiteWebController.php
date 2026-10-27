<?php

namespace App\Http\Controllers\Vitrine;

use App\Helpers\Audit;
use App\Http\Controllers\Controller;
use App\Models\Site\Page;
use App\Models\Site\Section;
use App\Services\Site\SiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Back-office du site vitrine (règles de gestion reprises de MARIEATTIEKESAAS) : identité, contact et apparence,
 * pages, blocs (ajout, édition, ordre, visibilité), aperçu, publication.
 */
class SiteWebController extends Controller
{
    protected function site()
    {
        return SiteService::pour(agence());
    }

    public function index()
    {
        $site = $this->site();

        return view('siteweb.index', [
            'site' => $site, 'agence' => agence(),
            'pages' => Page::where('site_id', $site->id)->withCount('sections')->orderByDesc('est_accueil')->orderBy('ordre')->get(),
            'adresse' => route('vitrine.accueil', agence()->slug),
        ]);
    }

    public function reglages(Request $request)
    {
        $site = $this->site();
        $agence = agence();
        $d = $request->validate([
            'titre' => 'nullable|string|max:255', 'slogan' => 'nullable|string|max:255', 'description' => 'nullable|string|max:500', 'mots_cles' => 'nullable|string|max:255',
            'couleur_principale' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'couleur_secondaire' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'police' => ['required', Rule::in(config('site.polices'))], 'theme' => 'required|in:CLAIR,SOMBRE',
            'domaine' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i', Rule::unique('agences', 'domaine')->ignore($agence->id)],
            'contact.telephone' => 'nullable|string|max:40', 'contact.whatsapp' => 'nullable|string|max:40', 'contact.email' => 'nullable|email|max:255', 'contact.adresse' => 'nullable|string|max:255',
            'reseaux.facebook' => 'nullable|url|max:255', 'reseaux.instagram' => 'nullable|url|max:255', 'reseaux.tiktok' => 'nullable|url|max:255', 'reseaux.youtube' => 'nullable|url|max:255', 'reseaux.linkedin' => 'nullable|url|max:255',
            'horaires' => 'nullable|string|max:2000', 'carte_url' => 'nullable|string|max:2000', 'pied_page' => 'nullable|string|max:2000', 'css' => 'nullable|string|max:10000',
            'logo' => 'nullable|image|max:' . config('site.images.taille_max_ko'), 'favicon' => 'nullable|image|max:512',
        ]);
        foreach (['logo', 'favicon'] as $f) {
            unset($d[$f]);
            if ($request->hasFile($f)) {
                $d[$f] = SiteService::enregistrerImage($request->file($f), $agence->id);
            }
        }
        $agence->update(['domaine' => !empty($d['domaine']) ? Str::lower($d['domaine']) : null]);
        unset($d['domaine']);
        $d['carte_url'] = SiteService::carteSure($d['carte_url'] ?? null);
        $d['css'] = isset($d['css']) ? strip_tags($d['css']) : null;
        foreach (['lien_espace_client', 'menu_biens', 'menu_actualites'] as $b) {
            $d[$b] = $request->boolean($b);
        }
        $site->update($d);
        Audit::logSave(['action' => 'MODIFIER REGLAGES SITE', 'code_piece' => $site->id, 'menu' => 'SITE WEB', 'etat' => 'Succès', 'objet' => 'VITRINE']);

        return back()->with('success', 'Réglages du site enregistrés.');
    }

    public function modele()
    {
        $page = SiteService::creerModele($this->site());

        return redirect()->route('siteweb.pages.edit', $page)->with('success', 'Site créé à partir du modèle immobilier : modifiez les textes et les images.');
    }

    /** Publication : le site public n'est visible que publié (module vitrine actif et abonnement valide). */
    public function publier(Request $request)
    {
        $site = $this->site();
        $publier = $request->boolean('publie');
        if ($publier && !Page::where('site_id', $site->id)->where('publie', true)->exists()) {
            return back()->with('error', 'Créez au moins une page avant de publier.');
        }
        agence()->update(['vitrine_active' => $publier]);
        Audit::logSave(['action' => $publier ? 'PUBLIER SITE' : 'DEPUBLIER SITE', 'code_piece' => $site->id, 'menu' => 'SITE WEB', 'etat' => 'Succès', 'objet' => 'VITRINE']);

        return back()->with('success', $publier ? 'Site publié : il est visible de tous.' : 'Site dépublié : il n\'est plus visible.');
    }

    // ------------------------------------------------------------------ pages

    public function creerPage(Request $request)
    {
        $site = $this->site();
        $d = $request->validate(['titre' => 'required|string|max:255']);
        $accueil = !Page::where('site_id', $site->id)->exists();
        $page = Page::create(['site_id' => $site->id, 'titre' => $d['titre'], 'slug' => $accueil ? '' : $this->slugLibre($site->id, $d['titre']), 'est_accueil' => $accueil,
            'dans_menu' => true, 'publie' => true, 'ordre' => (int) Page::where('site_id', $site->id)->max('ordre') + 1]);

        return redirect()->route('siteweb.pages.edit', $page);
    }

    public function page(Page $page)
    {
        $this->exiger($page);
        $page->load('sections');

        return view('siteweb.page', ['page' => $page, 'site' => $this->site(), 'types' => config('site.types')]);
    }

    public function modifierPage(Request $request, Page $page)
    {
        $this->exiger($page);
        $d = $request->validate(['titre' => 'required|string|max:255', 'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]*$/'],
            'seo_titre' => 'nullable|string|max:255', 'seo_description' => 'nullable|string|max:300', 'ordre' => 'nullable|integer|min:0']);
        $d['dans_menu'] = $request->boolean('dans_menu');
        $d['publie'] = $request->boolean('publie');
        if ($request->boolean('est_accueil')) {
            // une seule page d'accueil, toujours à l'adresse racine ; l'ancienne reçoit une adresse
            foreach (Page::where('site_id', $page->site_id)->where('id', '!=', $page->id)->where('est_accueil', true)->get() as $ancienne) {
                $ancienne->update(['est_accueil' => false, 'slug' => $this->slugLibre($page->site_id, $ancienne->titre, $ancienne->id)]);
            }
            $d['est_accueil'] = true;
            $d['slug'] = '';
            $d['publie'] = true;
        } elseif ($page->est_accueil) {
            $d['slug'] = '';
        } else {
            $d['slug'] = $this->slugLibre($page->site_id, ($d['slug'] ?? '') ?: $d['titre'], $page->id);
        }
        $page->update($d);

        return back()->with('success', 'Page enregistrée.');
    }

    public function supprimerPage(Page $page)
    {
        $this->exiger($page);
        abort_if($page->est_accueil, 422, 'La page d\'accueil ne peut pas être supprimée.');
        $page->delete();

        return redirect()->route('siteweb.index')->with('success', 'Page supprimée.');
    }

    // ------------------------------------------------------------------ blocs

    public function ajouterSection(Request $request, Page $page)
    {
        $this->exiger($page);
        $d = $request->validate(['type' => ['required', Rule::in(array_keys(config('site.types')))]]);
        $def = config('site.types.' . $d['type']);
        $section = Section::create(['page_id' => $page->id, 'type' => $d['type'], 'titre' => in_array('titre', $def['champs'] ?? [], true) ? $def['libelle'] : null,
            'visible' => true, 'options' => collect($def['options'] ?? [])->map(fn ($o) => $o[1] === 'select' ? array_key_first($o[2]) : ($o[1] === 'switch' ? true : null))->all(),
            'elements' => [], 'ordre' => (int) $page->sections()->max('ordre') + 1]);

        return redirect()->route('siteweb.sections.edit', $section);
    }

    public function section(Section $section)
    {
        $this->exiger($section->page);

        return view('siteweb.section', ['section' => $section, 'def' => config('site.types.' . $section->type), 'site' => $this->site()]);
    }

    public function modifierSection(Request $request, Section $section)
    {
        $page = $section->page;
        $this->exiger($page);
        $def = config('site.types.' . $section->type);
        $max = 'max:' . config('site.images.taille_max_ko');
        $request->validate(['titre' => 'nullable|string|max:255', 'sous_titre' => 'nullable|string|max:255', 'contenu' => 'nullable|string|max:20000',
            'ancre' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]*$/'], 'couleur_fond' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'image_fichier' => "nullable|image|$max", 'image_fond_fichier' => "nullable|image|$max", 'options' => 'nullable|array', 'elements' => 'nullable|array',
            'elements.*.image_fichier' => "nullable|image|$max"]);
        $agenceId = (int) $section->agence_id;

        $d = $request->only(['titre', 'sous_titre', 'ancre']);
        $d['couleur_fond'] = $request->boolean('couleur_fond_active') ? $request->input('couleur_fond') : null;
        $d['contenu'] = in_array('contenu', $def['champs'] ?? [], true) ? SiteService::nettoyer($request->input('contenu')) : null;
        $d['dans_menu'] = $request->boolean('dans_menu');
        $d['sombre'] = $request->boolean('sombre');
        foreach (['image', 'image_fond'] as $champ) {
            if ($request->hasFile($champ . '_fichier')) {
                $d[$champ] = SiteService::enregistrerImage($request->file($champ . '_fichier'), $agenceId);
            } elseif ($request->boolean($champ . '_retirer')) {
                $d[$champ] = null;
            }
        }
        // options du type : seules celles déclarées sont conservées
        $options = [];
        foreach ($def['options'] ?? [] as $cle => $o) {
            $options[$cle] = match ($o[1]) {
                'switch' => $request->boolean("options.$cle"),
                'select' => array_key_exists((string) $request->input("options.$cle"), $o[2]) ? $request->input("options.$cle") : array_key_first($o[2]),
                'number' => $request->filled("options.$cle") ? (int) $request->input("options.$cle") : null,
                default => trim(strip_tags((string) $request->input("options.$cle"))) ?: null,
            };
        }
        $d['options'] = $options;
        // éléments répétables (diapositives, cartes…) : image téléversée, conservée ou retirée
        $elements = [];
        foreach ($request->input('elements', []) as $i => $el) {
            if (!empty($el['_supprimer'])) {
                continue;
            }
            $ligne = [];
            foreach ($def['elements'] ?? [] as $cle => [$libelle, $type]) {
                if ($type === 'image') {
                    $fichier = $request->file("elements.$i.image_fichier");
                    $ligne[$cle] = $fichier ? SiteService::enregistrerImage($fichier, $agenceId) : ($el[$cle] ?? null);
                } elseif ($type === 'textarea') {
                    $ligne[$cle] = isset($el[$cle]) ? strip_tags((string) $el[$cle], '<br><strong><em>') : null;
                } else {
                    $ligne[$cle] = isset($el[$cle]) ? trim(strip_tags((string) $el[$cle])) : null;
                }
            }
            if (array_filter($ligne, fn ($v) => $v !== null && $v !== '')) {
                $elements[] = $ligne;
            }
        }
        $d['elements'] = $elements;
        $section->update($d);

        return redirect()->route('siteweb.pages.edit', $page)->with('success', 'Bloc « ' . ($def['libelle'] ?? $section->type) . ' » enregistré.');
    }

    public function deplacerSection(Section $section, string $sens)
    {
        $this->exiger($section->page);
        $sections = Section::where('page_id', $section->page_id)->orderBy('ordre')->orderBy('id')->get()->values();
        $i = $sections->search(fn ($s) => $s->id === $section->id);
        $j = $sens === 'haut' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < $sections->count()) {
            $ordre = $sections->pluck('id')->all();
            [$ordre[$i], $ordre[$j]] = [$ordre[$j], $ordre[$i]];
            foreach ($ordre as $k => $id) {
                Section::whereKey($id)->update(['ordre' => $k + 1]);
            }
        }

        return back();
    }

    public function basculerSection(Section $section)
    {
        $this->exiger($section->page);
        $section->update(['visible' => !$section->visible]);

        return back();
    }

    public function supprimerSection(Section $section)
    {
        $this->exiger($section->page);
        $section->delete();

        return back()->with('success', 'Bloc supprimé.');
    }

    /** Aperçu (publié ou non) dans le gabarit public. */
    public function apercu(?Page $page = null)
    {
        $site = $this->site();
        $page ??= SiteService::accueil($site) ?? Page::where('site_id', $site->id)->orderBy('ordre')->first();
        abort_unless($page, 404, 'Aucune page : créez la page d\'accueil.');
        $this->exiger($page);

        URL::defaults(['slug' => agence()->slug]);
        View::share('siteVitrine', $site);
        View::share('pagesVitrine', Page::where('site_id', $site->id)->where('publie', true)->where('dans_menu', true)->orderByDesc('est_accueil')->orderBy('ordre')->get());

        return app(SiteController::class)->rendre($page, true);
    }

    protected function exiger(?Page $page): void
    {
        abort_unless($page && (int) $page->site_id === (int) $this->site()->id, 404);
    }

    /** Adresse de page libre et unique, jamais celle d'une page système (biens, actualités, contact…). */
    protected function slugLibre(int $siteId, string $base, ?int $sauf = null): string
    {
        $base = Str::slug($base) ?: 'page';
        $base = in_array($base, config('site.slugs_reserves'), true) ? $base . '-agence' : $base;
        $slug = $base;
        for ($i = 2; Page::where('site_id', $siteId)->where('slug', $slug)->when($sauf, fn ($q) => $q->whereKeyNot($sauf))->exists(); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }
}
