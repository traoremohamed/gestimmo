@php
    // gabarit public paramétrable (réglages du site : identité, couleurs, police, thème, coordonnées, réseaux, menu, pied de page)
    $site = $siteVitrine;
    $u = fn ($c) => \App\Services\Site\SiteService::url($c);
    $nom = $site->titre ?: $agenceCourante->nom;
    $contact = array_filter($site->contact ?? []);
    $reseaux = array_filter($site->reseaux ?? []);
    $couleur = preg_match('/^#[0-9a-f]{6}$/i', (string) $site->couleur_principale) ? $site->couleur_principale : '#01619b';
    [$r, $g, $b] = sscanf($couleur, '#%02x%02x%02x');
    $police = in_array($site->police, config('site.polices'), true) ? $site->police : 'Inter';
    $apercu = $apercu ?? false;
    $pageCourante = $pageCourante ?? null;
    $ancres = isset($sections) ? $sections->filter(fn ($s) => $s->dans_menu && $s->ancre) : collect();
    $lienPage = fn ($p) => $apercu ? route('siteweb.apercu', $p) : ($p->est_accueil ? route('vitrine.accueil') : route('vitrine.page', $p->slug));
    $logo = $u($site->logo) ?: $agenceCourante->logo_url;
@endphp
<!DOCTYPE html>
<html lang="fr" data-bs-theme="{{ $site->theme === 'SOMBRE' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre', $nom . ($site->slogan ? ' — ' . $site->slogan : ''))</title>
    <meta name="description" content="@yield('description', $site->description ?: $site->slogan)">
    @if ($site->mots_cles)<meta name="keywords" content="{{ $site->mots_cles }}">@endif
    @if ($apercu)<meta name="robots" content="noindex">@endif
    <meta property="og:title" content="{{ $nom }}"><meta property="og:description" content="{{ $site->description }}">
    @if ($site->favicon)<link rel="icon" href="{{ $u($site->favicon) }}">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family={{ urlencode($police) }}:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
    <style>
        :root { --marque: {{ $couleur }}; --s-p: {{ $couleur }}; --s-p-rgb: {{ $r }}, {{ $g }}, {{ $b }}; --s-s: {{ $site->couleur_secondaire }}; }
        body { font-family: '{{ $police }}', system-ui, sans-serif; background: var(--bs-body-bg); }
        a { color: var(--s-p); }
        .s-nav { backdrop-filter: blur(8px); background: rgba(var(--bs-body-bg-rgb), .92); border-bottom: 1px solid var(--bs-border-color); }
        .s-nav .navbar-brand { font-weight: 800; color: var(--s-p); }
        .s-nav .nav-link { font-weight: 600; }
        .s-nav .nav-link.active, .s-nav .nav-link:hover { color: var(--s-p); }
        .btn-s, .btn-marque { background: var(--s-p); border-color: var(--s-p); color: #fff; font-weight: 600; }
        .btn-s:hover, .btn-marque:hover { filter: brightness(.92); color: #fff; }
        .btn-s2 { background: var(--s-s); border-color: var(--s-s); color: #111; font-weight: 600; }
        .text-marque { color: var(--s-p); }
        .s-section, .section { padding: 72px 0; }
        .s-section.sombre { background: #111827; color: #f9fafb; }
        .s-titre { font-weight: 800; letter-spacing: -.02em; }
        .section-titre { font-weight: 800; margin-bottom: 2rem; }
        .s-sous-titre { color: var(--bs-secondary-color); }
        .s-section.sombre .s-sous-titre { color: #cbd5e1; }
        .s-trait { width: 56px; height: 4px; background: var(--s-p); border-radius: 4px; margin: 12px auto 0; }
        .s-slide { background-size: cover; background-position: center; display: flex; align-items: center; color: #fff; position: relative; background-color: var(--s-p); }
        .s-slide::before { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(0,0,0,.68), rgba(0,0,0,.18)); }
        .s-slide > .container { position: relative; }
        .s-h-MOYENNE { min-height: 420px; } .s-h-GRANDE { min-height: 560px; } .s-h-PLEIN_ECRAN { min-height: calc(100vh - 64px); }
        .hero { position: relative; min-height: 440px; display: flex; align-items: center; color: #fff; background: linear-gradient(135deg, var(--s-p), rgba(var(--s-p-rgb), .6)); background-size: cover; background-position: center; }
        .hero::before { content: ''; position: absolute; inset: 0; background: rgba(0,0,0,.35); }
        .hero > * { position: relative; }
        .s-carte { border: 0; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.06); height: 100%; }
        .s-ico { width: 56px; height: 56px; border-radius: 14px; display: grid; place-items: center; font-size: 1.6rem; background: rgba(var(--s-p-rgb), .12); color: var(--s-p); }
        .carte-bien { border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,.07); transition: transform .15s; height: 100%; }
        .carte-bien:hover { transform: translateY(-4px); }
        .carte-bien img { aspect-ratio: 4/3; object-fit: cover; }
        .carte-bien .prix, .s-prix { color: var(--s-p); font-weight: 800; font-variant-numeric: tabular-nums; }
        .placeholder-photo { aspect-ratio: 4/3; background: rgba(var(--s-p-rgb), .1); display: grid; place-items: center; color: var(--s-p); font-size: 3rem; }
        .s-recherche { background: var(--bs-body-bg); border-radius: 18px; box-shadow: 0 20px 50px rgba(0,0,0,.12); padding: 1.25rem; }
        .s-galerie img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 12px; transition: transform .25s; }
        .s-galerie img:hover { transform: scale(1.03); }
        .s-chiffre { font-size: 2.6rem; font-weight: 800; color: var(--s-p); }
        .s-section.sombre .s-chiffre { color: #fff; }
        .s-avatar { width: 96px; height: 96px; border-radius: 50%; object-fit: cover; background: rgba(var(--s-p-rgb), .12); color: var(--s-p); display: grid; place-items: center; font-size: 2rem; font-weight: 800; margin: 0 auto; }
        .s-partenaire img { max-height: 64px; max-width: 100%; filter: grayscale(1); opacity: .75; transition: all .2s; }
        .s-partenaire img:hover { filter: none; opacity: 1; }
        .s-pied { background: #0b1220; color: #cbd5e1; }
        .s-pied a { color: #fff; text-decoration: none; }
        .s-flottant { position: fixed; right: 18px; bottom: 18px; width: 56px; height: 56px; border-radius: 50%; background: #25D366; color: #fff; display: grid; place-items: center; font-size: 1.8rem; box-shadow: 0 8px 24px rgba(0,0,0,.2); z-index: 1030; }
        .s-apercu { position: fixed; left: 0; right: 0; bottom: 0; background: #f59e0b; color: #111; text-align: center; font-weight: 600; padding: 6px; z-index: 1040; }
        .piege { position: absolute; left: -9999px; }
        {!! strip_tags((string) $site->css) !!}
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top s-nav">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ $apercu ? route('siteweb.apercu') : route('vitrine.accueil') }}">
            @if ($logo)<img src="{{ $logo }}" alt="{{ $nom }}" style="height:40px">@endif <span>{{ $nom }}</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#s-menu" aria-label="Menu"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="s-menu">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                @foreach ($ancres as $s)<li class="nav-item"><a class="nav-link" href="#{{ $s->ancre }}">{{ $s->titre }}</a></li>@endforeach
                @foreach ($pagesVitrine->where('est_accueil', false) as $p)
                    <li class="nav-item"><a class="nav-link {{ $pageCourante?->id === $p->id ? 'active' : '' }}" href="{{ $lienPage($p) }}">{{ $p->titre }}</a></li>
                @endforeach
                @if ($site->menu_biens)
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('vitrine.biens') && request('destination') === 'vente' ? 'active' : '' }}" href="{{ route('vitrine.biens', ['destination' => 'vente']) }}">Acheter</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('vitrine.biens') && request('destination') === 'location' ? 'active' : '' }}" href="{{ route('vitrine.biens', ['destination' => 'location']) }}">Louer</a></li>
                @endif
                @if ($site->menu_actualites)<li class="nav-item"><a class="nav-link {{ request()->routeIs('vitrine.actualite*') ? 'active' : '' }}" href="{{ route('vitrine.actualites') }}">Actualités</a></li>@endif
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('vitrine.contact') ? 'active' : '' }}" href="{{ route('vitrine.contact') }}">Contact</a></li>
                @if ($site->lien_espace_client)
                    <li class="nav-item ms-lg-2"><a class="btn btn-sm btn-s" href="{{ $apercu ? '#' : route('connexion') }}"><i class="ti ti-user"></i> Espace client</a></li>
                @endif
            </ul>
        </div>
    </div>
</nav>

@if (session('success'))<div class="container mt-3"><div class="alert alert-success">{{ session('success') }}</div></div>@endif
@if ($errors->any())<div class="container mt-3"><div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>@endif

@yield('contenu')

<footer class="s-pied pt-5 pb-3">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-5">
                <div class="fw-bold fs-5 text-white">{{ $nom }}</div>
                <div class="small mt-2">{!! nl2br(e($site->pied_page ?: ($site->description ?: $site->slogan))) !!}</div>
                <div class="fs-4 d-flex gap-3 mt-3">
                    @foreach (['facebook' => 'brand-facebook', 'instagram' => 'brand-instagram', 'tiktok' => 'brand-tiktok', 'youtube' => 'brand-youtube', 'linkedin' => 'brand-linkedin'] as $k => $i)
                        @if (!empty($reseaux[$k]))<a href="{{ $reseaux[$k] }}" target="_blank" rel="noopener" aria-label="{{ $k }}"><i class="ti ti-{{ $i }}"></i></a>@endif
                    @endforeach
                </div>
            </div>
            <div class="col-md-4 small">
                <h6 class="text-white">Nous contacter</h6>
                @if (!empty($contact['adresse']))<div><i class="ti ti-map-pin"></i> {{ $contact['adresse'] }}</div>@endif
                @if (!empty($contact['telephone']))<div><i class="ti ti-phone"></i> <a href="tel:{{ preg_replace('/\s+/', '', $contact['telephone']) }}">{{ $contact['telephone'] }}</a></div>@endif
                @if (!empty($contact['email']))<div><i class="ti ti-mail"></i> <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></div>@endif
                @if ($site->horaires)<div class="mt-2">{!! nl2br(e($site->horaires)) !!}</div>@endif
            </div>
            <div class="col-md-3 small">
                <h6 class="text-white">Liens</h6>
                @foreach ($pagesVitrine as $p)<div><a href="{{ $lienPage($p) }}">{{ $p->titre }}</a></div>@endforeach
                <div><a href="{{ route('vitrine.biens') }}">Tous nos biens</a></div>
                @if ($site->lien_espace_client)<div><a href="{{ route('connexion') }}">Espace client</a></div>@endif
            </div>
        </div>
        <div class="border-top border-secondary pt-3 mt-4 small text-center opacity-75">© {{ date('Y') }} {{ $agenceCourante->raison_sociale }}</div>
    </div>
</footer>
@if (!empty($contact['whatsapp']))
    <a class="s-flottant" href="https://wa.me/{{ preg_replace('/\D+/', '', $contact['whatsapp']) }}" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="ti ti-brand-whatsapp"></i></a>
@endif
@if ($apercu)<div class="s-apercu">Aperçu {{ $agenceCourante->vitrine_active ? '(site publié)' : '— le site n\'est pas encore publié' }} · <a href="{{ route('siteweb.index') }}">retour à l'édition</a></div>@endif
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
