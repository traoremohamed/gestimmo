@extends('layouts.backLayout.designadmin')

@php
    $libChamps = [
        'chemin' => ['Dossier de destination', 'ex. D:\\Sauvegardes\\Immobilier, \\\\NAS\\sauvegardes ou /mnt/sauvegardes'],
        'hote' => ['Serveur (hôte)', 'ex. sauvegarde.mon-entreprise.ci'], 'port' => ['Port', '22'], 'utilisateur' => ['Utilisateur', ''], 'dossier' => ['Dossier distant', 'ex. /sauvegardes/immobilier'],
        'url' => ['URL WebDAV du dossier', 'ex. https://cloud.exemple.ci/remote.php/dav/files/immobilier/Sauvegardes'],
        'lien_dossier' => ['Lien du dossier Google Drive', 'https://drive.google.com/drive/folders/…'],
        'mode_auth' => ['Authentification Google', ''], 'client_id' => ['Client ID OAuth (mode OAuth)', ''],
        'mot_de_passe' => ['Mot de passe', ''], 'cle_privee' => ['Clé privée SSH (facultatif, à la place du mot de passe)', ''],
        'compte_service_json' => ['Compte de service Google (contenu du fichier JSON)', ''], 'client_secret' => ['Client secret OAuth', ''], 'refresh_token' => ['Refresh token OAuth', ''],
    ];
    $couleurs = ['SUCCES' => 'success', 'PARTIEL' => 'warning', 'ECHEC' => 'danger', 'EN_COURS' => 'info'];
    $jours = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];
@endphp

@section('content')
<x-page titre="Sauvegardes" module="Plateforme">
    <x-slot:actions>
        @can('sauvegardes-gerer')
            <form method="POST" action="{{ route('sauvegardes.lancer') }}" data-confirm="Lancer une sauvegarde maintenant ? (peut durer plusieurs minutes)">@csrf
                <button class="btn btn-sm btn-primary"><i class="ti ti-player-play me-1"></i>Sauvegarder maintenant</button></form>
        @endcan
    </x-slot:actions>
    <p class="text-muted">Base de données, code et documents (pièces, GED) de toutes les agences dans une archive chiffrée, envoyée sur vos serveurs ou votre Google Drive.</p>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card ui-kpi"><div class="card-body"><div class="small text-muted">Dernière sauvegarde réussie</div>
            <div class="fs-5 fw-bold {{ $derniere && $derniere->debut_at->gt(now()->subDay()) ? 'text-success' : 'text-danger' }}">{{ $derniere?->debut_at->format('d/m/Y H:i') ?? 'aucune' }}</div></div></div></div>
        <div class="col-md-3"><div class="card ui-kpi"><div class="card-body"><div class="small text-muted">Planification</div>
            <div class="fs-6 fw-bold">{{ $config->actif ? ($config->frequence === 'QUOTIDIENNE' ? 'Tous les jours' : 'Chaque ' . $jours[$config->jour_semaine]) . ' à ' . $config->heure : 'désactivée' }}</div></div></div></div>
        <div class="col-md-3"><div class="card ui-kpi"><div class="card-body"><div class="small text-muted">Chiffrement</div>
            <div class="fs-6 fw-bold {{ $config->cle_chiffrement ? 'text-success' : 'text-danger' }}">{{ $config->cle_chiffrement ? 'AES-256 actif' : 'non chiffré' }}</div></div></div></div>
        <div class="col-md-3"><div class="card ui-kpi"><div class="card-body"><div class="small text-muted">Destinations actives</div>
            <div class="fs-5 fw-bold">{{ $destinations->where('actif', true)->count() }}</div></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-5">
            <form method="POST" action="{{ route('sauvegardes.configurer') }}" class="card mb-3">@csrf
                <div class="card-header"><h5><i class="ti ti-settings me-1"></i>Planification et contenu</h5></div>
                <div class="card-body row g-2 small">
                    <div class="col-12"><div class="form-check form-switch"><input type="hidden" name="actif" value="0"><input type="checkbox" class="form-check-input" name="actif" value="1" id="sv-actif" @checked($config->actif)><label class="form-check-label" for="sv-actif">Sauvegarde automatique</label></div></div>
                    <div class="col-4"><label class="form-label">Fréquence</label><select name="frequence" class="form-select form-select-sm"><option value="QUOTIDIENNE" @selected($config->frequence === 'QUOTIDIENNE')>Quotidienne</option><option value="HEBDOMADAIRE" @selected($config->frequence === 'HEBDOMADAIRE')>Hebdomadaire</option></select></div>
                    <div class="col-4"><label class="form-label">Jour (hebdo)</label><select name="jour_semaine" class="form-select form-select-sm">@foreach ($jours as $k => $j)<option value="{{ $k }}" @selected($config->jour_semaine == $k)>{{ $j }}</option>@endforeach</select></div>
                    <div class="col-4"><label class="form-label">Heure</label><input type="time" name="heure" value="{{ $config->heure }}" class="form-control form-control-sm"></div>
                    <div class="col-12"><label class="form-label">Contenu</label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach (['inclure_base' => 'Base de données', 'inclure_code' => 'Code de l\'application', 'inclure_documents' => 'Documents & archives GED', 'inclure_vendor' => 'Dépendances (vendor)'] as $c => $l)
                                <div class="form-check"><input type="hidden" name="{{ $c }}" value="0"><input type="checkbox" class="form-check-input" name="{{ $c }}" value="1" id="sv-{{ $c }}" @checked($config->$c)><label class="form-check-label" for="sv-{{ $c }}">{{ $l }}</label></div>
                            @endforeach
                        </div></div>
                    <div class="col-6"><label class="form-label">Archives gardées sur ce serveur</label><input type="number" min="1" name="retention_locale" value="{{ $config->retention_locale }}" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label">Chemin de pg_dump</label><input type="text" name="pg_dump_chemin" value="{{ $config->pg_dump_chemin }}" class="form-control form-control-sm" placeholder="pg_dump (PATH)"></div>
                    <div class="col-12 border-top pt-2"><label class="form-label fw-semibold">Clé de chiffrement de l'archive (AES-256)</label>
                        <div class="text-muted mb-1">{{ $config->cle_chiffrement ? 'Une clé est enregistrée (jamais réaffichée). Saisissez-en une nouvelle pour la remplacer.' : 'Aucune clé : archives non chiffrées, fichier .env exclu.' }}
                            Conservez-la hors du serveur : sans elle, les archives sont illisibles.</div>
                        <div class="row g-2"><div class="col-6"><input type="password" name="cle_chiffrement" class="form-control form-control-sm" placeholder="Nouvelle clé (12 caractères min.)" autocomplete="new-password"></div>
                            <div class="col-6"><input type="password" name="cle_chiffrement_confirmation" class="form-control form-control-sm" placeholder="Confirmation" autocomplete="new-password"></div></div>
                    </div>
                </div>
                @can('sauvegardes-gerer')<div class="card-footer bg-transparent text-end"><button class="btn btn-sm btn-primary">Enregistrer</button></div>@endcan
            </form>

            @can('sauvegardes-gerer')
                <form method="POST" action="{{ route('sauvegardes.destinations.store') }}" class="card" id="form-destination">@csrf
                    <div class="card-header"><h5><i class="ti ti-server me-1"></i>Ajouter une destination</h5></div>
                    <div class="card-body row g-2 small">
                        <div class="col-7"><label class="form-label">Libellé</label><input type="text" name="libelle" class="form-control form-control-sm" required placeholder="ex. NAS du service informatique"></div>
                        <div class="col-5"><label class="form-label">Archives gardées</label><input type="number" min="1" name="retention" value="14" class="form-control form-control-sm"></div>
                        <div class="col-12"><label class="form-label">Type</label><select name="type" class="form-select form-select-sm" id="dest-type">
                            @foreach ($types as $k => [$l]) <option value="{{ $k }}">{{ $l }}</option> @endforeach</select></div>
                        @foreach ($types as $type => [$l, $champs, $secrets])
                            <div class="col-12 champs-type" data-type="{{ $type }}">
                                <div class="row g-2">
                                    @foreach ($champs as $c)
                                        <div class="{{ in_array($c, ['port']) ? 'col-3' : 'col-12' }}"><label class="form-label">{{ $libChamps[$c][0] }}</label>
                                            @if ($c === 'mode_auth')
                                                <select name="parametres[mode_auth]" class="form-select form-select-sm"><option value="COMPTE_SERVICE">Compte de service (Drive partagé)</option><option value="OAUTH">OAuth (Drive personnel)</option></select>
                                            @else
                                                <input type="text" name="parametres[{{ $c }}]" class="form-control form-control-sm" placeholder="{{ $libChamps[$c][1] }}">
                                            @endif
                                        </div>
                                    @endforeach
                                    @foreach ($secrets as $c)
                                        <div class="col-12"><label class="form-label">{{ $libChamps[$c][0] }}</label>
                                            @if (in_array($c, ['cle_privee', 'compte_service_json']))
                                                <textarea name="secrets[{{ $c }}]" rows="3" class="form-control form-control-sm font-monospace"></textarea>
                                            @else
                                                <input type="password" name="secrets[{{ $c }}]" class="form-control form-control-sm" autocomplete="new-password">
                                            @endif
                                        </div>
                                    @endforeach
                                    @if ($type === 'GOOGLE_DRIVE')
                                        <div class="col-12 text-muted">Compte de service : partagez le dossier (de préférence un <strong>Drive partagé</strong>) avec l'adresse « client_email » du compte, en éditeur.
                                            Drive personnel : utilisez le mode OAuth (client ID / secret + refresh token avec la portée drive).</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="card-footer bg-transparent text-end"><button class="btn btn-sm btn-primary"><i class="ti ti-plus me-1"></i>Ajouter</button></div>
                </form>
            @endcan
        </div>

        <div class="col-xl-7">
            <div class="card mb-3">
                <div class="card-header"><h5><i class="ti ti-server-2 me-1"></i>Destinations</h5></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($destinations as $d)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div><strong>{{ $d->libelle }}</strong> <span class="badge badge-soft-secondary">{{ $d->type }}</span> @if(!$d->actif)<span class="badge badge-soft-danger">inactive</span>@endif
                                    <div class="text-muted">{{ $d->p('chemin') ?? $d->p('hote') ?? $d->p('url') ?? $d->p('lien_dossier') }} · {{ $d->retention }} archive(s) gardée(s)</div>
                                    @if ($d->dernier_test_at)<div class="{{ str_starts_with((string) $d->dernier_test_resultat, 'OK') ? 'text-success' : 'text-danger' }}">Test du {{ $d->dernier_test_at->format('d/m/Y H:i') }} : {{ $d->dernier_test_resultat }}</div>@endif</div>
                                @can('sauvegardes-gerer')
                                    <div class="d-flex gap-1">
                                        <form method="POST" action="{{ route('sauvegardes.destinations.tester', $d) }}">@csrf<button class="btn btn-sm btn-outline-primary">Tester</button></form>
                                        <form method="POST" action="{{ route('sauvegardes.destinations.destroy', $d) }}" data-confirm="Supprimer cette destination ?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button></form>
                                    </div>
                                @endcan
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Aucune destination : les archives restent seulement sur ce serveur. Ajoutez au moins une destination externe.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card">
                <div class="card-header"><h5><i class="ti ti-history me-1"></i>Historique</h5></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small">
                        <thead><tr><th>N°</th><th>Début</th><th>Contenu</th><th>Taille</th><th>Statut</th><th>Destinations</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($sauvegardes as $s)
                            <tr>
                                <td>{{ $s->numero }}<div class="text-muted">{{ strtolower($s->declenchement) }}</div></td>
                                <td>{{ $s->debut_at->format('d/m/Y H:i') }}<div class="text-muted">{{ $s->fin_at ? $s->debut_at->diffInSeconds($s->fin_at) . ' s' : '' }}</div></td>
                                <td>{{ implode(', ', array_map('strtolower', $s->contenu ?? [])) }} @if($s->chiffree)<i class="ti ti-lock text-success" title="chiffrée"></i>@endif</td>
                                <td>{{ $s->taille_octets ? number_format($s->taille_octets / 1048576, 1, ',', ' ') . ' Mo' : '—' }}</td>
                                <td><span class="badge bg-{{ $couleurs[$s->statut] ?? 'secondary' }}">{{ strtolower($s->statut) }}</span>@if($s->erreur)<div class="text-danger" style="max-width:220px">{{ \Illuminate\Support\Str::limit($s->erreur, 120) }}</div>@endif</td>
                                <td>@foreach ($s->destinations ?? [] as $r)<div class="{{ $r['statut'] === 'SUCCES' ? 'text-success' : 'text-danger' }}" title="{{ $r['message'] }}">{{ $r['libelle'] }} : {{ strtolower($r['statut']) }}</div>@endforeach</td>
                                <td>@if ($s->chemin_local)@can('sauvegardes-telecharger')<a href="{{ route('sauvegardes.telecharger', $s) }}" class="btn btn-sm btn-outline-secondary" title="Télécharger"><i class="ti ti-download"></i></a>@endcan @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted text-center py-4">Aucune sauvegarde.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-2">{{ $sauvegardes->links() }}</div>
            </div>
            <details class="card mt-3"><summary class="card-header"><strong>Procédure de restauration</strong></summary>
                <pre class="card-body small mb-0" style="white-space:pre-wrap">{{ \App\Services\Sauvegarde\SauvegardeService::procedureRestauration((bool) $config->cle_chiffrement) }}</pre></details>
        </div>
    </div>
</x-page>
@endsection

@push('scripts')
<script>
    (function () {
        const type = document.getElementById('dest-type');
        if (!type) { return; }
        const maj = () => document.querySelectorAll('.champs-type').forEach(b => {
            const visible = b.dataset.type === type.value;
            b.style.display = visible ? '' : 'none';
            b.querySelectorAll('input, select, textarea').forEach(c => c.disabled = !visible);
        });
        type.addEventListener('change', maj); maj();
    })();
</script>
@endpush
