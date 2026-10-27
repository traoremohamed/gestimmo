@php
    use App\Helpers\Menu;

    $utilisateur = Auth::user();
    $idutil = $utilisateur->id;
    $arbre = $utilisateur->flag_mdp ? Menu::arbre($idutil) : [];
    $naroles = $utilisateur->estSuperAdmin() ? 'Super administrateur' : Menu::get_menu_profil($idutil);
    $logo = Menu::get_logo();
    $agenceActive = $agenceCourante ?? null;
    $couleurPlateforme = '#' . ltrim(@Menu::get_info_couleur()->mot_cle ?: '01619b', '#');
    $couleur = $agenceActive?->couleur ?: $couleurPlateforme;
    $couleur = preg_match('/^#[0-9a-fA-F]{6}$/', $couleur) ? $couleur : '#01619b';
    [$r, $g, $b] = sscanf($couleur, '#%02x%02x%02x');
    $appli = $agenceActive?->nom ?: (@$logo->mot_cle ?: config('rbac.app_name'));
    $logoUrl = $agenceActive?->logo_url ?: (!empty($logo->logo_logo) ? asset(config('rbac.parametres.upload_path') . '/' . $logo->logo_logo) : null);

    // agences de travail : toutes pour le super administrateur, sinon l'agence principale et les agences supplémentaires
    $agencesDisponibles = $utilisateur->estSuperAdmin()
        ? \App\Models\Agence::where('actif', true)->orderBy('raison_sociale')->get(['id', 'raison_sociale', 'sigle', 'ville'])
        : (count($utilisateur->agencesAutorisees()) > 1 ? \App\Models\Agence::whereIn('id', $utilisateur->agencesAutorisees())->orderBy('raison_sociale')->get(['id', 'raison_sociale', 'sigle', 'ville']) : collect());
    $alertesNav = $agenceActive && $utilisateur->flag_mdp ? app(\App\Services\Alertes::class)->pour($utilisateur) : [];
    $nbValidations = 0;
    if ($agenceActive && Route::has('validations.index') && $utilisateur->can('validations-index')) {
        try { $nbValidations = \App\Services\Validation\ValidationService::aDecider($utilisateur)->count(); } catch (\Throwable $e) { $nbValidations = 0; }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titre', $appli) · {{ $appli }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}?v=1">
    <style>:root { --ui-primary: {{ $couleur }}; --ui-primary-rgb: {{ $r }}, {{ $g }}, {{ $b }}; }</style>
    @stack('styles')
</head>
<body>

<aside class="ui-sidebar" id="ui-sidebar">
    <a href="{{ route('dashboard') }}" class="ui-brand">
        <span class="ui-brand-logo">@if($logoUrl)<img src="{{ $logoUrl }}" alt="">@else<i class="ti ti-building-estate"></i>@endif</span>
        <span class="ui-brand-text">
            <strong>{{ $appli }}</strong>
            <small>{{ $agenceActive ? 'Gestion immobilière' : ($utilisateur->estSuperAdmin() ? 'Administration de la plateforme' : 'Gestion immobilière') }}</small>
        </span>
    </a>

    <div class="ui-menu-search">
        <i class="ti ti-search"></i>
        <input type="search" id="ui-filtre-menu" placeholder="Rechercher un écran…" autocomplete="off" aria-label="Rechercher un écran">
    </div>

    <nav class="ui-menu" aria-label="Menu principal">
        <ul>
            <li class="ui-noeud {{ request()->routeIs('dashboard') ? 'actif' : '' }}">
                <a href="{{ route('dashboard') }}" class="ui-lien"><i class="ui-ico ti ti-layout-dashboard"></i><span class="ui-libelle">Tableau de bord</span></a>
            </li>
        </ul>
        @foreach ($arbre as $module)
            <div class="ui-module">
                <div class="ui-menu-titre">{{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::lower($module['menu'])) }}</div>
                <ul>
                    @foreach ($module['noeuds'] as $noeud)
                        @include('layouts.partials.menu-noeud', ['noeud' => $noeud])
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="ui-sidebar-pied"><i class="ti ti-shield-check"></i> Accès tracés · documents signés par QR code</div>
</aside>
<div class="ui-voile"></div>

<div class="ui-main">
    <header class="ui-topbar no-print">
        <button class="ui-btn-ico" type="button" data-ui="menu" title="Menu" aria-label="Menu"><i class="ti ti-menu-2"></i></button>

        @if ($utilisateur->estSuperAdmin())
            <span class="ui-badge-plateforme d-none d-md-inline-flex align-items-center gap-1">
                <i class="ti {{ $agenceActive ? 'ti-eye' : 'ti-cloud' }}"></i> {{ $agenceActive ? 'Vous administrez ' . $agenceActive->nom : 'Plateforme SaaS' }}
            </span>
        @endif
        <span class="d-none d-xl-inline text-muted small ms-2"><i class="ti ti-calendar"></i> {{ now()->locale('fr')->translatedFormat('l d F Y') }}</span>
        <div class="flex-grow-1"></div>

        <div class="d-flex align-items-center gap-2">
            @if ($agencesDisponibles->isNotEmpty())
                <div class="dropdown">
                    <button class="ui-contexte dropdown-toggle" data-bs-toggle="dropdown" type="button" title="Agence de travail">
                        <i class="ti ti-building text-primary"></i><span>{{ $agenceActive?->nom ?? 'Choisir une agence' }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" style="min-width: 280px; max-height: 70vh; overflow-y: auto">
                        <h6 class="dropdown-header">Agence de travail</h6>
                        @if ($utilisateur->estSuperAdmin())
                            <form method="POST" action="{{ route('agences.basculer') }}">@csrf
                                <button class="dropdown-item {{ $agenceActive ? '' : 'active' }}"><i class="ti ti-cloud me-1"></i> Plateforme (aucune agence)</button>
                            </form>
                        @endif
                        @foreach ($agencesDisponibles as $a)
                            <form method="POST" action="{{ route('agences.basculer') }}">@csrf
                                <input type="hidden" name="agence_id" value="{{ $a->id }}">
                                <button class="dropdown-item d-flex justify-content-between align-items-center {{ $agenceActive?->id === $a->id ? 'active' : '' }}">
                                    <span>{{ $a->sigle ?: $a->raison_sociale }} @if($a->ville)<small class="opacity-75">· {{ $a->ville }}</small>@endif</span>
                                    @if($agenceActive?->id === $a->id)<i class="ti ti-check"></i>@endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @elseif ($agenceActive)
                <span class="ui-contexte d-none d-md-flex"><i class="ti ti-building text-primary"></i><span>{{ $agenceActive->nom }}</span></span>
            @endif

            <div class="dropdown">
                <button class="ui-btn-ico" type="button" data-bs-toggle="dropdown" title="Alertes" aria-label="Alertes">
                    <i class="ti ti-bell"></i>@if(count($alertesNav))<span class="badge rounded-pill bg-danger">{{ count($alertesNav) }}</span>@endif
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0" style="width: 350px">
                    <div class="px-3 py-2 border-bottom fw-semibold d-flex justify-content-between">Alertes <a href="{{ route('alertes') }}" class="small">Tout voir</a></div>
                    @forelse(array_slice($alertesNav, 0, 8) as $a)
                        <a href="{{ $a['url'] }}" class="dropdown-item d-flex gap-2 py-2 small text-wrap">
                            <i class="ti {{ $a['icone'] }} text-{{ $a['niveau'] }} fs-5"></i><span><strong>{{ $a['nombre'] }}</strong> {{ $a['message'] }}</span>
                        </a>
                    @empty
                        <div class="px-3 py-3 small text-muted">Aucune alerte.</div>
                    @endforelse
                </div>
            </div>
            @if (Route::has('validations.index') && $agenceActive && $utilisateur->can('validations-index'))
                <a href="{{ route('validations.index') }}" class="ui-btn-ico" title="Validations à décider" aria-label="Validations à décider">
                    <i class="ti ti-checks"></i>@if ($nbValidations > 0)<span class="badge rounded-pill bg-warning text-dark">{{ $nbValidations }}</span>@endif
                </a>
            @endif
            <a href="{{ route('aide.index') }}" class="ui-btn-ico" title="Guide d'utilisation" aria-label="Guide d'utilisation"><i class="ti ti-help"></i></a>
            <button class="ui-btn-ico" type="button" data-ui="theme" title="Thème clair / sombre" aria-label="Thème clair ou sombre"><i class="ti ti-moon-stars"></i></button>

            <div class="dropdown">
                <a href="#" class="ui-user" data-bs-toggle="dropdown">
                    <span class="ui-avatar">{{ $utilisateur->initiales }}</span>
                    <span class="ui-user-nom d-none d-lg-flex"><strong>{{ $utilisateur->nom_complet }}</strong><small>{{ $naroles }}</small></span>
                    <i class="ti ti-chevron-down text-muted small d-none d-lg-inline"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-2 small text-muted">Dernière connexion : {{ $utilisateur->derniere_connexion_at?->format('d/m/Y H:i') ?? '—' }}</li>
                    <li><a class="dropdown-item" href="{{ route('modifier.mot.passe') }}"><i class="ti ti-key me-2"></i>Modifier mon mot de passe</a></li>
                    <li><a class="dropdown-item" href="{{ route('aide.index') }}"><i class="ti ti-book me-2"></i>Guide d'utilisation</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="dropdown-item text-danger" type="submit"><i class="ti ti-logout me-2"></i>Déconnexion</button></form></li>
                </ul>
            </div>
        </div>
    </header>

    <div class="ui-toasts">
        @foreach (['error' => ['danger', 'alert-circle'], 'errors' => ['danger', 'alert-circle'], 'warning' => ['warning', 'alert-triangle'], 'info' => ['info', 'info-circle']] as $cle => [$classe, $ico])
            @if (is_string(session($cle)))
                <div class="ui-toast ui-toast-{{ $classe }}" role="alert">
                    <i class="ti ti-{{ $ico }}"></i>
                    <div class="flex-grow-1 small">{{ session($cle) }}</div>
                    <button type="button" class="btn-close btn-sm" data-fermer aria-label="Fermer"></button>
                </div>
            @endif
        @endforeach
    </div>

    <main class="ui-contenu">
        @yield('content')
    </main>

    <footer class="ui-pied no-print">
        <span>© {{ date('Y') }} {{ $appli }}</span>
        <span>{{ $agenceActive?->raison_sociale }}</span>
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="{{ asset('js/ui.js') }}?v=1"></script>
@stack('scripts')
@yield('js_perso')
</body>
</html>
