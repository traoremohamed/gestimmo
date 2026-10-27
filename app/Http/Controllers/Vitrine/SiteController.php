<?php

namespace App\Http\Controllers\Vitrine;

use App\Http\Controllers\Controller;
use App\Models\Bien;
use App\Models\Site\Page;
use App\Services\Site\SiteService;
use App\Models\DemandeContact;
use App\Models\Programme;
use App\Models\Publication;
use App\Models\Referentiel;
use App\Models\RendezVous;
use App\Models\Slide;
use App\Models\Temoignage;
use App\Services\Integration\NotificationService;
use Illuminate\Http\Request;

/**
 * Site vitrine public (remplace IndexController) : seuls les biens et contenus publiés sont exposés.
 */
class SiteController extends Controller
{
    /** Page d'accueil composée par l'agence (blocs paramétrables). */
    public function accueil()
    {
        return $this->rendre(SiteService::accueil(SiteService::pour(agence())) ?? abort(404));
    }

    /** Page libre du site (/site/{slug}/p/{page}). */
    public function page(string $slug, string $page)
    {
        $site = SiteService::pour(agence());

        return $this->rendre(Page::where('site_id', $site->id)->where('slug', $page)->where('publie', true)->firstOrFail());
    }

    /** Rendu d'une page (aussi utilisé par l'aperçu du back-office). */
    public function rendre(Page $page, bool $apercu = false)
    {
        $sections = $page->sections()->where('visible', true)->get();
        $donnees = $sections->mapWithKeys(fn ($s) => [$s->id => SiteService::donnees($s)])->all();

        return response()->view('vitrine.page', ['page' => $page, 'pageCourante' => $page, 'sections' => $sections, 'donnees' => $donnees, 'apercu' => $apercu])
            ->header('X-Robots-Tag', $apercu ? 'noindex' : 'all');
    }

    public function biens(Request $request)
    {
        $biens = $this->biensPublies()
            ->when($request->filled('type_bien'), fn ($q) => $q->where('type_bien', $request->type_bien))
            ->when($request->query('destination') === 'vente', fn ($q) => $q->where('a_vendre', true))
            ->when($request->query('destination') === 'location', fn ($q) => $q->where('a_louer', true))
            ->when($request->query('destination') === 'meuble', fn ($q) => $q->where('meuble', true))
            ->when($request->filled('budget'), fn ($q) => $q->where(fn ($s) => $s->where('prix_vente', '<=', $request->budget)->orWhere('loyer_mensuel', '<=', $request->budget)))
            ->when($request->filled('programme_id'), fn ($q) => $q->where('programme_id', $request->programme_id))
            ->latest()->paginate(12)->withQueryString();

        return view('vitrine.biens', [
            'biens' => $biens,
            'types' => Referentiel::options('type_bien'),
            'programmes' => Programme::where('publie', true)->pluck('nom', 'id'),
        ]);
    }

    public function bien(string $slug, string $reference)
    {
        $bien = $this->biensPublies()->with(['photos', 'programme'])->where('reference', $reference)->firstOrFail();

        return view('vitrine.bien', [
            'bien' => $bien,
            'similaires' => $this->biensPublies()->where('type_bien', $bien->type_bien)->whereKeyNot($bien->id)->limit(3)->get(),
        ]);
    }

    public function demanderVisite(Request $request, string $slug, string $reference)
    {
        $bien = $this->biensPublies()->where('reference', $reference)->firstOrFail();
        $donnees = $this->validerContact($request, ['date_souhaitee' => ['required', 'date', 'after:now']]);

        if ($donnees === null) {
            return back()->with('success', 'Merci, votre demande a bien été envoyée.');
        }

        RendezVous::create([
            'bien_id' => $bien->id,
            'programme_id' => $bien->programme_id,
            'objet' => 'VISITE',
            'debut' => $donnees['date_souhaitee'],
            'statut' => 'planifie',
            'source' => 'vitrine',
            'contact_nom' => $donnees['nom'],
            'contact_telephone' => $donnees['telephone'] ?? null,
            'contact_email' => $donnees['email'] ?? null,
            'commentaire' => $donnees['message'] ?? null,
        ]);

        return back()->with('success', 'Merci ! Votre demande de visite a été transmise, un conseiller vous contactera pour la confirmer.');
    }

    public function actualites()
    {
        return view('vitrine.actualites', ['publications' => Publication::enLigne()->latest('publie_le')->paginate(9)]);
    }

    public function actualite(string $slug, string $publication)
    {
        return view('vitrine.actualite', ['publication' => Publication::enLigne()->where('slug', $publication)->firstOrFail()]);
    }

    public function contact(Request $request)
    {
        return view('vitrine.contact', ['bien' => $request->filled('bien') ? $this->biensPublies()->where('reference', $request->bien)->first() : null]);
    }

    public function envoyerContact(Request $request)
    {
        $donnees = $this->validerContact($request, ['sujet' => ['nullable', 'string', 'max:255'], 'bien_reference' => ['nullable', 'string', 'max:40'], 'newsletter' => ['boolean']]);

        if ($donnees !== null) {
            DemandeContact::create([
                'bien_id' => isset($donnees['bien_reference']) ? $this->biensPublies()->where('reference', $donnees['bien_reference'])->value('id') : null,
                'nom' => $donnees['nom'],
                'email' => $donnees['email'] ?? null,
                'telephone' => $donnees['telephone'] ?? null,
                'sujet' => $donnees['sujet'] ?? null,
                'message' => $donnees['message'] ?? '',
                'newsletter' => $request->boolean('newsletter'),
                'ip' => $request->ip(),
            ]);
            try {
                NotificationService::notifier('DEMANDE_CONTACT', ['email' => $donnees['email'] ?? null, 'telephone' => $donnees['telephone'] ?? null, 'nom' => $donnees['nom']],
                    ['sujet' => $donnees['sujet'] ?? 'Demande d\'information']);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'Merci, votre message a bien été envoyé. Nous vous répondrons rapidement.');
    }

    /**
     * Validation commune ; renvoie null si le piège à robots (champ caché « site_web ») est rempli.
     */
    protected function validerContact(Request $request, array $regles = []): ?array
    {
        if ($request->filled('site_web')) {
            return null;
        }

        return $request->validate($regles + [
            'nom' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:telephone', 'email', 'max:255'],
            'telephone' => ['nullable', 'required_without:email', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:3000'],
        ], [
            'email.required_without' => 'Indiquez un e-mail ou un téléphone pour que nous puissions vous répondre.',
            'telephone.required_without' => 'Indiquez un téléphone ou un e-mail pour que nous puissions vous répondre.',
        ]);
    }

    protected function biensPublies()
    {
        return Bien::with('photos')->where('publie', true)->whereIn('statut', ['disponible', 'reserve', 'en_construction']);
    }
}
