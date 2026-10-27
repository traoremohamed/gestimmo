@extends('layouts.backLayout.designadmin')

@php
    $onglets = ['EMAIL' => ['E-mail (SMTP)', 'ti-mail'], 'SMS' => ['SMS', 'ti-message'], 'WHATSAPP' => ['WhatsApp', 'ti-brand-whatsapp'],
        'GABARIT' => ['Modèle d\'e-mail', 'ti-palette'], 'MODELES' => ['Messages', 'ti-template'], 'JOURNAL' => ['Journal d\'envoi', 'ti-history']];
    if (!$enAgence) unset($onglets['MODELES']);
    $statutsEnvoi = ['ENVOYE' => 'success', 'SIMULE' => 'info', 'ECHEC' => 'danger'];
@endphp

@section('content')
<x-page titre="Intégrations & messages" module="Paramétrage">
    <p class="text-muted small">
        {{ $enAgence ? 'Canaux propres à votre agence. Tant qu\'un canal n\'est pas actif ici, celui de la plateforme est utilisé.' : 'Canaux de la plateforme, utilisés par les agences qui n\'ont pas configuré les leurs.' }}
        Paramètres appliqués au prochain envoi, sans redémarrage ; mots de passe et clés chiffrés en base.
    </p>

    <div class="row g-3 mb-3">
        @foreach (array_keys($catalogue) as $code)
            @php($canal = $canaux[$code])
            <div class="col-sm-4">
                <a href="{{ route('integrations.index', ['onglet' => $code]) }}" class="card h-100 text-decoration-none text-reset {{ $onglet === $code ? 'border-primary' : '' }}">
                    <div class="card-body d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-icon mb-2"><i class="ti {{ $catalogue[$code]['icone'] }}"></i></div>
                            <div class="fw-semibold">{{ $catalogue[$code]['libelle'] }}</div>
                            <div class="small text-muted">{{ $canal->exists ? $catalogue[$code]['fournisseurs'][$canal->fournisseur]['libelle'] ?? $canal->fournisseur : 'Non configuré' }}</div>
                        </div>
                        <div class="text-end">
                            @if ($canal->actif)
                                <span class="badge rounded-pill text-bg-success">Actif</span>
                            @elseif ($plateforme[$code])
                                <span class="badge rounded-pill text-bg-light border">Canal plateforme</span>
                            @else
                                <span class="badge rounded-pill text-bg-secondary">Désactivé</span>
                            @endif
                            @if ($canal->sandbox)<div class="small text-info mt-1">sandbox</div>@endif
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card">
        <ul class="nav nav-tabs px-3 pt-2">
            @foreach ($onglets as $code => [$libelle, $icone])
                <li class="nav-item"><a class="nav-link @if($onglet === $code) active @endif" href="{{ route('integrations.index', ['onglet' => $code]) }}"><i class="ti {{ $icone }}"></i> {{ $libelle }}</a></li>
            @endforeach
        </ul>
        <div class="card-body">
            @if (isset($catalogue[$onglet]))
                @include('admin.integrations_canal', ['code' => $onglet, 'canal' => $canaux[$onglet], 'def' => $catalogue[$onglet]])

            @elseif ($onglet === 'GABARIT')
                <div class="row g-4">
                    <div class="col-xl-5">
                        <form method="POST" action="{{ route('integrations.gabarit') }}" class="row">
                            @csrf
                            <div class="col-md-4 mb-3"><label class="form-label">Couleur</label><input type="color" name="couleur" value="{{ $gabarit['couleur'] }}" class="form-control form-control-color w-100"></div>
                            <x-form.input name="entete" label="Titre de l'en-tête" :value="$gabarit['entete']" col="col-md-8" />
                            <x-form.input name="logo_url" label="URL du logo (https://…)" :value="$gabarit['logo']" col="col-12" aide="Par défaut : le logo de l'agence (Mon agence)." />
                            <x-form.textarea name="signature" label="Signature" :value="$gabarit['signature']" col="col-12" rows="2" />
                            <x-form.textarea name="pied_page" label="Pied de page" :value="$gabarit['pied']" col="col-12" rows="2" />
                            @can('integrations-edit')<div class="col-12"><button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer</button></div>@endcan
                        </form>
                    </div>
                    <div class="col-xl-7"><iframe src="{{ route('integrations.apercu') }}" title="Aperçu de l'e-mail" class="w-100 border rounded" style="height:460px"></iframe></div>
                </div>

            @elseif ($onglet === 'MODELES')
                <p class="text-muted small">Texte de chaque notification envoyée par l'application et canaux utilisés. Variables entre accolades, ex. <code>{nom}</code>, <code>{montant}</code>.
                    Le texte SMS sert aussi à WhatsApp. Les relances de recouvrement ont leurs propres textes (Recouvrement › Niveaux de relance).</p>
                <div class="accordion" id="acc-modeles">
                    @foreach ($modeles as $m)
                        <div class="accordion-item">
                            <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m-{{ $m->id }}">
                                {{ $m->libelle }} <span class="ms-2 small text-muted">{{ implode(' · ', array_map(fn ($c) => \App\Services\Integration\NotificationService::CANAUX[$c] ?? $c, (array) $m->canaux)) ?: 'aucun canal' }}</span>
                                @unless($m->actif)<span class="badge text-bg-secondary ms-2">inactif</span>@endunless</button></h2>
                            <div id="m-{{ $m->id }}" class="accordion-collapse collapse" data-bs-parent="#acc-modeles">
                                <form method="POST" action="{{ route('integrations.modele', $m) }}" class="accordion-body row">
                                    @csrf @method('PUT')
                                    <div class="col-12 d-flex flex-wrap gap-3 mb-3">
                                        @foreach (\App\Services\Integration\NotificationService::CANAUX as $c => $l)
                                            <div class="form-check"><input class="form-check-input" type="checkbox" name="canaux[]" value="{{ $c }}" id="mc-{{ $m->id }}-{{ $c }}" @checked(in_array($c, (array) $m->canaux))>
                                                <label class="form-check-label" for="mc-{{ $m->id }}-{{ $c }}">{{ $l }}</label></div>
                                        @endforeach
                                        <div class="form-check form-switch ms-auto"><input type="hidden" name="actif" value="0"><input class="form-check-input" type="checkbox" name="actif" value="1" id="ma-{{ $m->id }}" @checked($m->actif)>
                                            <label class="form-check-label" for="ma-{{ $m->id }}">Actif</label></div>
                                    </div>
                                    <x-form.input name="sujet" label="Objet de l'e-mail" :value="$m->sujet" col="col-12" />
                                    <x-form.textarea name="corps_email" label="Message e-mail" :value="$m->corps_email" col="col-md-7" rows="6" />
                                    <x-form.textarea name="corps_sms" label="Message SMS / WhatsApp (160 caractères par SMS)" :value="$m->corps_sms" col="col-md-5" rows="6" />
                                    @if ($m->variables)<div class="col-12 small text-muted mb-2">Variables : {{ $m->variables }}</div>@endif
                                    @can('integrations-edit')<div class="col-12"><button class="btn btn-primary btn-sm">Enregistrer</button></div>@endcan
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>

            @elseif ($onglet === 'JOURNAL')
                <form method="GET" class="d-flex gap-2 mb-3">
                    <input type="hidden" name="onglet" value="JOURNAL">
                    <select name="canal" class="form-select" style="max-width:200px" onchange="this.form.submit()" aria-label="Canal"><option value="">Tous les canaux</option>
                        @foreach (\App\Services\Integration\NotificationService::CANAUX as $c => $l)<option value="{{ $c }}" @selected(request('canal') === $c)>{{ $l }}</option>@endforeach</select>
                    <select name="statut" class="form-select" style="max-width:200px" onchange="this.form.submit()" aria-label="Statut"><option value="">Tous les statuts</option>
                        @foreach ($statutsEnvoi as $s => $c)<option value="{{ $s }}" @selected(request('statut') === $s)>{{ $s }}</option>@endforeach</select>
                </form>
                <div class="table-responsive"><table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Date</th><th>Canal</th><th>Destinataire</th><th>Objet / message</th><th>Statut</th><th>Détail</th></tr></thead>
                    <tbody>
                    @forelse ($envois as $e)
                        <tr><td class="small text-nowrap">{{ $e->envoye_at->format('d/m/Y H:i:s') }}</td><td>{{ $e->canal }} <span class="small text-muted">{{ $e->fournisseur }}</span></td>
                            <td class="small">{{ $e->destinataire }}</td><td class="small">{{ $e->sujet ?? $e->modele }}</td>
                            <td><span class="badge text-bg-{{ $statutsEnvoi[$e->statut] ?? 'secondary' }}">{{ $e->statut }}</span></td>
                            <td class="small text-muted">{{ \Illuminate\Support\Str::limit((string) ($e->erreur ?? $e->reference_externe), 120) }} {{ $e->duree_ms ? '· ' . $e->duree_ms . ' ms' : '' }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucun envoi.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <div class="mt-2">{{ $envois->links() }}</div>
            @endif
        </div>
    </div>
</x-page>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.choix-fournisseur').forEach(function (choix) {
    choix.addEventListener('change', function () {
        choix.closest('form').querySelectorAll('.groupe-fournisseur').forEach(function (g) {
            var actif = g.dataset.fournisseur === choix.value;
            g.classList.toggle('d-none', !actif);
            g.querySelectorAll('input, select, textarea').forEach(function (c) { c.disabled = !actif; });   // seuls les champs du fournisseur choisi sont envoyés
        });
    });
});
</script>
@endpush
