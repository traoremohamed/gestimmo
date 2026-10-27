<?php

namespace App\Services\Site;

use App\Models\Agence;
use App\Models\Bail;
use App\Models\Bien;
use App\Models\Programme;
use App\Models\Publication;
use App\Models\Site\Page;
use App\Models\Site\Section;
use App\Models\Site\Site;
use App\Models\Slide;
use App\Models\Temoignage;
use App\Models\Tiers;
use App\Models\User;
use App\Models\VenteBien;
use App\Support\AgenceCourante;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Site vitrine paramétrable de chaque agence (repris de MARIEATTIEKESAAS) : réglages, modèle de départ construit
 * à partir des blocs de l'ancien site, données des blocs automatiques, images, nettoyage du contenu libre.
 */
class SiteService
{
    /** Site de l'agence, créé avec ses réglages par défaut (identité, couleur, coordonnées de l'agence). */
    public static function pour(Agence $agence): Site
    {
        return app(AgenceCourante::class)->pour($agence, function () use ($agence) {
            $site = Site::firstOrNew(['agence_id' => $agence->id]);
            if (!$site->exists) {
                $site->fill([
                    'titre' => $agence->nom, 'slogan' => parametre('vitrine.slogan') ?: 'Trouvez le bien qui vous ressemble',
                    'couleur_principale' => $agence->couleur ?: '#01619b',
                    'contact' => ['telephone' => $agence->telephone, 'whatsapp' => $agence->telephone, 'email' => $agence->email,
                        'adresse' => trim(collect([$agence->adresse, $agence->ville])->filter()->implode(', '))],
                    'description' => parametre('vitrine.a_propos') ?: null,
                ])->save();
            }

            return $site;
        });
    }

    public static function accueil(Site $site): ?Page
    {
        return Page::where('site_id', $site->id)->where('publie', true)->orderByDesc('est_accueil')->orderBy('ordre')->first();
    }

    /**
     * Modèle de départ inspiré de l'ancien site : diaporama des biens, recherche, services, derniers biens, programmes,
     * chiffres, agents, actualités, témoignages, appel à l'action et contact ; pages « À propos » et « Nos agents ».
     * Ne remplace jamais une page existante.
     */
    public static function creerModele(Site $site): Page
    {
        return DB::transaction(function () use ($site) {
            $accueil = Page::firstOrCreate(['site_id' => $site->id, 'slug' => ''], ['agence_id' => $site->agence_id, 'titre' => 'Accueil', 'est_accueil' => true, 'dans_menu' => true, 'publie' => true, 'ordre' => 1]);
            if ($accueil->sections()->doesntExist()) {
                $blocs = [
                    ['SLIDER', 'accueil', false, null, null, null, ['source' => 'BIENS', 'destination' => 'TOUS', 'nombre' => 5, 'hauteur' => 'GRANDE', 'intervalle' => 6], [
                        ['titre' => $site->titre, 'texte' => $site->slogan, 'bouton_libelle' => 'Voir nos biens', 'bouton_lien' => '#biens'],
                    ]],
                    ['RECHERCHE', 'recherche', false, 'Trouvez votre bien', 'Terrains, villas, appartements, bureaux : à vendre ou à louer.', null, ['budget' => true, 'programme' => true], []],
                    ['CARTES', 'services', true, 'Nos services', 'Un accompagnement complet, de la recherche à la remise des clés.', null, ['colonnes' => '4'], [
                        ['icone' => 'ti ti-home-dollar', 'titre' => 'Vente', 'texte' => 'Terrains et maisons en toute sécurité, avec un échéancier adapté.'],
                        ['icone' => 'ti ti-key', 'titre' => 'Location', 'texte' => 'Appartements, villas et bureaux, avec un suivi de A à Z.'],
                        ['icone' => 'ti ti-building-community', 'titre' => 'Gestion & syndic', 'texte' => 'Gestion locative et administration de copropriétés.'],
                        ['icone' => 'ti ti-bed', 'titre' => 'Résidences meublées', 'texte' => 'Séjours de courte et moyenne durée, prêts à vivre.'],
                    ]],
                    ['BIENS', 'biens', true, 'Nos derniers biens', null, null, ['destination' => 'TOUS', 'nombre' => 6, 'affichage' => 'GRILLE', 'bouton' => true], []],
                    ['PROGRAMMES', 'programmes', true, 'Nos programmes', null, null, ['nombre' => 4], []],
                    ['CHIFFRES', null, false, 'L\'agence en chiffres', null, null, ['automatique' => true], []],
                    ['EQUIPE', 'agents', true, 'Nos agents', 'Des conseillers à votre écoute.', null, ['automatique' => true], []],
                    ['ACTUALITES', 'actualites', false, 'Nos actualités', null, null, ['nombre' => 3], []],
                    ['TEMOIGNAGES', 'temoignages', false, 'Ils nous font confiance', null, null, ['automatique' => true], []],
                    ['CTA', null, false, 'Un projet immobilier ?', '<p>Nos conseillers vous répondent et organisent vos visites.</p>', null, ['bouton_libelle' => 'Nous contacter', 'bouton_lien' => '#contact'], []],
                    ['CONTACT', 'contact', true, 'Contact', 'Une question, une visite ? Écrivez-nous ou appelez-nous.', null, ['formulaire' => true, 'carte' => true, 'horaires' => true], []],
                ];
                foreach ($blocs as $i => [$type, $ancre, $menu, $titre, $sousTitre, $contenu, $options, $elements]) {
                    // pour le type CTA, le « sous-titre » du tableau est son contenu
                    if ($type === 'CTA') {
                        [$contenu, $sousTitre] = [$sousTitre, null];
                    }
                    Section::create(['agence_id' => $site->agence_id, 'page_id' => $accueil->id, 'type' => $type, 'ancre' => $ancre, 'dans_menu' => $menu, 'titre' => $titre,
                        'sous_titre' => $sousTitre, 'contenu' => $contenu, 'options' => $options, 'elements' => $elements, 'visible' => true, 'ordre' => $i + 1,
                        'sombre' => in_array($type, ['CHIFFRES', 'CTA'], true)]);
                }
            }

            $pages = [
                ['a-propos', 'À propos', [['TEXTE_IMAGE', 'Qui sommes-nous ?', '<p>' . e($site->description ?: 'Présentez ici votre agence, son histoire, ses valeurs et ses engagements.') . '</p>', ['position_image' => 'DROITE']],
                    ['CHIFFRES', 'L\'agence en chiffres', null, ['automatique' => true]], ['FAQ', 'Questions fréquentes', null, []]]],
                ['nos-agents', 'Nos agents', [['EQUIPE', 'Nos agents', null, ['automatique' => true]], ['CONTACT', 'Prendre rendez-vous', null, ['formulaire' => true, 'carte' => false, 'horaires' => true]]]],
            ];
            foreach ($pages as $o => [$slug, $titre, $sections]) {
                $page = Page::firstOrCreate(['site_id' => $site->id, 'slug' => $slug], ['agence_id' => $site->agence_id, 'titre' => $titre, 'dans_menu' => true, 'publie' => true, 'ordre' => $o + 2]);
                if ($page->sections()->doesntExist()) {
                    foreach ($sections as $i => [$type, $t, $contenu, $options]) {
                        Section::create(['agence_id' => $site->agence_id, 'page_id' => $page->id, 'type' => $type, 'titre' => $t, 'contenu' => $contenu, 'options' => $options,
                            'elements' => $type === 'FAQ' ? [['question' => 'Comment réserver un bien ?', 'reponse' => 'Contactez-nous : un acompte bloque le bien pendant quelques jours, le temps de signer le contrat.']] : [],
                            'visible' => true, 'ordre' => $i + 1]);
                    }
                }
            }

            return $accueil;
        });
    }

    // ------------------------------------------------------------------ données des blocs automatiques

    public static function biensPublies()
    {
        return Bien::with(['photos', 'programme'])->where('publie', true)->whereIn('statut', ['disponible', 'reserve', 'en_construction']);
    }

    /** Données d'un bloc selon son type et ses options. */
    public static function donnees(Section $s): mixed
    {
        $o = $s->options ?? [];
        $nombre = fn (int $defaut) => max(1, min(48, (int) ($o['nombre'] ?? $defaut) ?: $defaut));
        $destination = function ($q) use ($o) {
            return match ($o['destination'] ?? 'TOUS') {
                'VENTE' => $q->where('a_vendre', true), 'LOCATION' => $q->where('a_louer', true), 'MEUBLE' => $q->where('meuble', true), default => $q,
            };
        };

        return match ($s->type) {
            'SLIDER' => match ($o['source'] ?? 'MANUEL') {
                'BIENS' => $destination(self::biensPublies())->whereHas('photos')->latest()->limit($nombre(5))->get(),
                'SLIDES' => Slide::where('actif', true)->orderBy('ordre')->get(),
                default => null,
            },
            'BIENS' => $destination(self::biensPublies())->when(!empty($o['type_bien']), fn ($q) => $q->where('type_bien', $o['type_bien']))->latest()->limit($nombre(6))->get(),
            'PROGRAMMES' => Programme::where('publie', true)->where('actif', true)->withCount(['biens as disponibles' => fn ($q) => $q->where('publie', true)->where('statut', 'disponible')])->limit($nombre(4))->get(),
            'ACTUALITES' => Publication::enLigne()->latest('publie_le')->limit($nombre(3))->get(),
            'TEMOIGNAGES' => !empty($o['automatique']) ? Temoignage::where('actif', true)->latest()->limit(9)->get() : collect(),
            'EQUIPE' => !empty($o['automatique']) ? self::agents() : collect(),
            'CHIFFRES' => !empty($o['automatique']) ? self::chiffres() : [],
            'RECHERCHE', 'HERO' => ['types' => \App\Models\Referentiel::options('type_bien'), 'programmes' => Programme::where('publie', true)->pluck('nom', 'id')],
            default => null,
        };
    }

    /** Commerciaux actifs de l'agence (profil COMMERCIAL ou fonction renseignée). */
    public static function agents(): Collection
    {
        return User::deLAgence()->where('flag_actif_users', true)
            ->where(fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['COMMERCIAL', "DIRECTEUR D'AGENCE"]))->orWhereNotNull('fonction'))
            ->orderBy('name')->limit(12)->get()
            ->map(fn ($u) => ['nom' => $u->nom_complet, 'fonction' => $u->fonction ?: ($u->roles->first()?->name === 'COMMERCIAL' ? 'Conseiller commercial' : 'Direction'),
                'telephone' => $u->cel_users, 'email' => $u->email, 'image' => null]);
    }

    /** @return array<int, array{valeur: string, libelle: string}> */
    public static function chiffres(): array
    {
        $f = fn ($n) => number_format((int) $n, 0, ',', ' ');

        return array_values(array_filter([
            ['valeur' => $f(VenteBien::whereHas('vente', fn ($q) => $q->whereIn('statut', ['en_cours', 'solde']))->count()), 'libelle' => 'Biens vendus'],
            ['valeur' => $f(Bail::where('statut', 'actif')->count()), 'libelle' => 'Biens en location'],
            ['valeur' => $f(Programme::where('actif', true)->count()), 'libelle' => 'Programmes'],
            ['valeur' => $f(Tiers::count()), 'libelle' => 'Clients accompagnés'],
        ], fn ($c) => $c['valeur'] !== '0'));
    }

    // ------------------------------------------------------------------ images et contenu

    public static function enregistrerImage(UploadedFile $fichier, int $agenceId): string
    {
        return $fichier->store(config('site.images.dossier', 'site') . '/' . $agenceId, config('site.images.disque', 'public'));
    }

    public static function url(?string $chemin): ?string
    {
        if (!$chemin) {
            return null;
        }

        return preg_match('#^https?://#i', $chemin) ? $chemin : asset('storage/' . ltrim($chemin, '/'));
    }

    /** Contenu riche saisi par l'agence : balises de mise en forme autorisées, scripts et événements retirés. */
    public static function nettoyer(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        $html = preg_replace('#<(script|style|iframe|object|embed|form)[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|link|meta)[^>]*/?>#is', '', $html);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*(javascript|data|vbscript):[^"\']*\2/i', '$1="#"', $html);

        return strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote><span><img><hr><table><thead><tbody><tr><th><td><small>');
    }

    /** Lien d'intégration de carte autorisé (Google Maps, OpenStreetMap) ; sinon rien. */
    public static function carteSure(?string $url): ?string
    {
        if (!$url) {
            return null;
        }
        if (preg_match('/src="([^"]+)"/', $url, $m)) {
            $url = $m[1];                                              // code d'intégration complet collé
        }

        return preg_match('#^https://(www\.)?(google\.[a-z.]+/maps/embed|maps\.google\.[a-z.]+/maps|www\.openstreetmap\.org/export/embed)#i', $url) ? $url : null;
    }

    /** Lien sûr pour un bouton : ancre, chemin interne ou http(s). */
    public static function lien(?string $lien): ?string
    {
        if (!$lien) {
            return null;
        }

        return preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $lien) ? $lien : '#';
    }
}
