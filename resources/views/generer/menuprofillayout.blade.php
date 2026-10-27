@extends('layouts.backLayout.designadmin')

@section('content')
<x-page :titre="'Droits du profil ' . $role->name" module="Configuration" :retour="route('menuprofil')">
    <x-slot:badge>
        @if($role->estModele())<span class="badge badge-soft-info">profil modèle</span>@else<span class="badge badge-soft-primary">{{ $role->agence?->nom }}</span>@endif
    </x-slot:badge>
    <x-slot:actions>
        @can('attribuer')
            @if ($lectureSeule)
                <span class="badge badge-soft-info align-self-center">Lecture seule — créez un profil à partir de ce modèle pour l'adapter</span>
                @can('role-create')<a href="{{ route('roles.create', ['modele' => $role->id]) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-copy"></i> Créer à partir de ce modèle</a>@endcan
            @else
                <button type="submit" form="form-permissions" class="btn btn-sm btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer les attributions</button>
            @endif
        @endcan
    </x-slot:actions>

    <p class="text-muted small">Cocher un groupe coche toute sa descendance. Cocher une permission rend visible la page qui la porte.
        Seuls les modules actifs de l'agence sont proposés ; les écrans de la plateforme ne sont jamais attribués à un profil d'agence.</p>

    <form action="{{ route('menuprofillayout', \App\Helpers\Crypt::UrlCrypt($role->id)) }}" method="POST" id="form-permissions">
        @csrf
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                <div class="ui-recherche flex-grow-1" style="max-width: none">
                    <i class="ti ti-search"></i>
                    <input type="search" id="filtre-droits" placeholder="Rechercher un module, un menu, une permission…" autocomplete="off" aria-label="Filtrer les droits">
                </div>
                <button type="button" class="btn btn-sm btn-light" data-action="deplier">Tout déplier</button>
                <button type="button" class="btn btn-sm btn-light" data-action="replier">Tout replier</button>
                <span class="small text-muted ms-2" id="compteur-droits"></span>
            </div>
        </div>

        <fieldset @disabled($lectureSeule)>
            @foreach ($arbre as $module)
                <div class="card mb-3 module-droits">
                    <div class="card-header d-flex align-items-center gap-2">
                        <input type="checkbox" class="form-check-input coche-groupe" title="Tout cocher" aria-label="Tout cocher">
                        <span class="flex-grow-1">{{ $module['menu'] }}</span>
                        <span class="badge badge-soft-secondary">{{ count($module['noeuds']) }}</span>
                    </div>
                    <div class="card-body py-2">
                        @forelse ($module['noeuds'] as $noeud)
                            @include('generer._noeud', ['noeud' => $noeud])
                        @empty
                            <div class="text-muted small py-2">Aucun écran dans ce module.</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </fieldset>
    </form>
</x-page>
@endsection

@push('styles')
<style>
    .droit-noeud { border-left: 2px solid var(--ui-border); margin-left: .5rem; padding-left: .9rem; }
    .droit-ligne { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; padding: .45rem .5rem; border-radius: 8px; }
    .droit-ligne:hover { background: var(--ui-surface-2); }
    .droit-enfants { display: none; }
    .droit-noeud.ouvert > .droit-enfants { display: block; }
    .droit-noeud.ouvert > .droit-ligne .bascule .ti { transform: rotate(90deg); }
    .bascule { border: 0; background: none; padding: 0; color: var(--ui-text-muted); width: 1.2rem; }
    .bascule .ti { transition: transform .15s; }
    .droit-permissions { display: flex; flex-wrap: wrap; gap: .4rem; margin-left: auto; }
    .droit-permissions label { font-size: .75rem; border: 1px solid var(--ui-border); border-radius: 20px; padding: .15rem .6rem; cursor: pointer; display: inline-flex; gap: .3rem; align-items: center; }
    .droit-permissions input:checked + span { color: var(--ui-primary); font-weight: 600; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    function maj() {
        var n = document.querySelectorAll('.coche-page:checked').length;
        var p = document.querySelectorAll('.coche-permission:checked').length;
        document.getElementById('compteur-droits').textContent = n + ' écran(s) · ' + p + ' permission(s) cochés';
        document.querySelectorAll('.coche-groupe').forEach(function (g) {
            var conteneur = g.closest('.droit-noeud, .module-droits');
            var cases = conteneur.querySelectorAll('.coche-page, .coche-permission');
            var cochees = conteneur.querySelectorAll('.coche-page:checked, .coche-permission:checked');
            g.checked = cases.length > 0 && cochees.length === cases.length;
            g.indeterminate = cochees.length > 0 && cochees.length < cases.length;
        });
    }
    document.querySelectorAll('.coche-groupe').forEach(function (g) {
        g.addEventListener('change', function () {
            g.closest('.droit-noeud, .module-droits').querySelectorAll('.coche-page, .coche-permission').forEach(function (c) { c.checked = g.checked; });
            maj();
        });
    });
    document.querySelectorAll('.coche-page').forEach(function (c) {
        c.addEventListener('change', function () {
            if (!c.checked) { c.closest('.droit-ligne').querySelectorAll('.coche-permission').forEach(function (p) { p.checked = false; }); }
            maj();
        });
    });
    document.querySelectorAll('.coche-permission').forEach(function (p) {
        p.addEventListener('change', function () {
            if (p.checked) { var page = p.closest('.droit-ligne').querySelector('.coche-page'); if (page) { page.checked = true; } }
            maj();
        });
    });
    document.querySelectorAll('.bascule').forEach(function (b) {
        b.addEventListener('click', function () { b.closest('.droit-noeud').classList.toggle('ouvert'); });
    });
    document.querySelector('[data-action="deplier"]').addEventListener('click', function () { document.querySelectorAll('.droit-noeud').forEach(function (n) { n.classList.add('ouvert'); }); });
    document.querySelector('[data-action="replier"]').addEventListener('click', function () { document.querySelectorAll('.droit-noeud').forEach(function (n) { n.classList.remove('ouvert'); }); });
    var normaliser = function (s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    document.getElementById('filtre-droits').addEventListener('input', function () {
        var t = normaliser(this.value.trim());
        document.querySelectorAll('.droit-noeud').forEach(function (n) {
            var ok = !t || normaliser(n.textContent).indexOf(t) !== -1;
            n.style.display = ok ? '' : 'none';
            if (t && ok) { n.classList.add('ouvert'); }
        });
        document.querySelectorAll('.module-droits').forEach(function (m) { m.style.display = !t || normaliser(m.textContent).indexOf(t) !== -1 ? '' : 'none'; });
    });
    maj();
})();
</script>
@endpush
