@php
    $logo = \App\Helpers\Menu::get_logo();
    $appli = @$logo->mot_cle ?: config('rbac.app_name');
    $couleur = '#' . ltrim(@\App\Helpers\Menu::get_info_couleur()->mot_cle ?: '01619b', '#');
    $couleur = preg_match('/^#[0-9a-fA-F]{6}$/', $couleur) ? $couleur : '#01619b';
    [$r, $g, $b] = sscanf($couleur, '#%02x%02x%02x');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion · {{ $appli }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/ui.css') }}?v=1">
    <style>:root { --ui-primary: {{ $couleur }}; --ui-primary-rgb: {{ $r }}, {{ $g }}, {{ $b }}; }</style>
</head>
<body>
<div class="ui-auth">
    <section class="ui-auth-panneau">
        <div class="d-flex align-items-center gap-2 fw-bold fs-5">
            @if (!empty($logo->logo_logo))
                <img src="{{ asset(config('rbac.parametres.upload_path') . '/' . $logo->logo_logo) }}" alt="" height="36">
            @else
                <span class="ui-brand-logo"><i class="ti ti-building-estate"></i></span>
            @endif
            {{ $appli }}
        </div>
        <div>
            <h1>Pilotez votre agence,<br>du lot vendu au loyer encaissé.</h1>
            <p class="opacity-75 mt-3 mb-0" style="max-width: 460px">Promotion, vente, gestion locative, meublés, syndic, recouvrement, paie et comptabilité :
                une seule application pour toutes vos agences.</p>
            <div class="ui-auth-points">
                <div><i class="ti ti-home-dollar"></i><span><strong>Ventes et locations suivies de bout en bout</strong><br><small class="opacity-75">Réservation, échéancier, bail, quittances, état des lieux et restitution.</small></span></div>
                <div><i class="ti ti-qrcode"></i><span><strong>Documents infalsifiables</strong><br><small class="opacity-75">Reçus, quittances et contrats portent un QR code vérifiable en ligne.</small></span></div>
                <div><i class="ti ti-route"></i><span><strong>Validations sous contrôle</strong><br><small class="opacity-75">Circuits d'approbation paramétrables et journal d'audit inaltérable.</small></span></div>
            </div>
        </div>
        <small class="opacity-50">© {{ date('Y') }} {{ $appli }}</small>
    </section>
    <section class="ui-auth-form">
        <div>
            <h2 class="fw-bold mb-1">Connexion</h2>
            <p class="text-muted mb-4">Identifiez-vous pour accéder à votre espace.</p>

            @foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $cle => $classe)
                @if (is_string(session($cle)))<div class="alert alert-{{ $classe }} small">{{ session($cle) }}</div>@endif
            @endforeach

            <form method="POST" action="{{ route('connexion') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="username">E-mail ou identifiant</label>
                    <input type="text" class="form-control form-control-lg @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username') }}" autocomplete="username" autofocus required>
                    @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Mot de passe</label>
                    <div class="input-group">
                        <input type="password" class="form-control form-control-lg @error('password') is-invalid @enderror" id="password" name="password" autocomplete="current-password" required>
                        <button type="button" class="btn btn-light border" data-voir="password" title="Afficher le mot de passe" aria-label="Afficher le mot de passe"><i class="ti ti-eye"></i></button>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Se souvenir de moi</label>
                </div>
                <button class="btn btn-primary btn-lg w-100" type="submit"><i class="ti ti-login-2"></i> Se connecter</button>
            </form>
        </div>
    </section>
</div>
<script>
    document.querySelectorAll('[data-voir]').forEach(function (b) {
        b.addEventListener('click', function () {
            var champ = document.getElementById(b.dataset.voir);
            champ.type = champ.type === 'password' ? 'text' : 'password';
            b.querySelector('i').className = champ.type === 'password' ? 'ti ti-eye' : 'ti ti-eye-off';
        });
    });
</script>
</body>
</html>
