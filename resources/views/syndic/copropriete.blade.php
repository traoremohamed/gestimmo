@extends('layouts.backLayout.designadmin')

@php
    $totalLots = (float) $copro->lots->sum('tantiemes');
    $equilibre = abs($totalLots - (float) $copro->total_tantiemes) < 0.0001;
    $tantiemes = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', ' '), '0'), ',');
@endphp

@section('content')
<x-page :titre="$copro->nom" module="Syndic" :retour="route('coproprietes.index')">
    <x-slot:actions>
        @can('appelsfonds-create')<a href="{{ route('appelsfonds.create', ['copropriete_id' => $copro->id]) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-file-invoice"></i> Appel de fonds</a>@endcan
        @can('assemblees-create')<a href="{{ route('assemblees.create', ['copropriete_id' => $copro->id]) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-users-group"></i> Assemblée générale</a>@endcan
        @can('coproprietes-edit')<a href="{{ route('coproprietes.edit', $copro) }}" class="btn btn-sm btn-primary"><i class="ti ti-pencil"></i> Modifier</a>@endcan
    </x-slot:actions>

    <div class="row">
        <x-stat libelle="Lots" :valeur="$copro->lots->count()" icone="ti-layout-grid" />
        <x-stat libelle="Tantièmes répartis" :valeur="$tantiemes($totalLots) . ' / ' . $copro->total_tantiemes" icone="ti-chart-pie" :detail="$equilibre ? 'Répartition équilibrée' : 'Répartition à compléter'" />
        <x-stat libelle="Appelé (tous exercices)" :valeur="montant($situations->sum(fn ($s) => (float) $s['appele']))" icone="ti-file-invoice" />
        <x-stat libelle="Reste dû par les copropriétaires" :valeur="montant($situations->sum(fn ($s) => (float) $s['reste']))" icone="ti-alert-circle" />
    </div>

    @unless($equilibre)
        <div class="alert alert-warning"><i class="ti ti-alert-triangle"></i> La somme des tantièmes des lots ({{ $tantiemes($totalLots) }}) ne correspond pas au total déclaré ({{ $copro->total_tantiemes }}). Les appels sont répartis au prorata des tantièmes saisis.</div>
    @endunless

    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#lots" type="button">Lots & copropriétaires</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cles" type="button">Clés de répartition</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#budgets" type="button">Budgets</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#appels" type="button">Derniers appels</button></li>
    </ul>
    <div class="tab-content card rounded-top-0 mb-4">
        <div class="tab-pane fade show active" id="lots">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Lot</th><th>Désignation</th><th>Copropriétaire</th><th class="text-end">Tantièmes</th><th class="text-end">Quote-part</th><th class="text-end">Reste dû</th><th></th></tr></thead>
                    <tbody>
                        @foreach($copro->lots->sortBy('numero', SORT_NATURAL) as $lot)
                            <tr>
                                <td class="fw-medium">{{ $lot->numero }}</td>
                                <td>{{ $lot->designation }} @if($lot->bien)<a href="{{ route('biens.show', $lot->bien) }}" class="small">({{ $lot->bien->reference }})</a>@endif</td>
                                <td>@if($lot->coproprietaire)<a href="{{ route('tiers.show', $lot->coproprietaire) }}">{{ $lot->coproprietaire->nom_complet }}</a>@else<span class="text-muted">—</span>@endif</td>
                                <td class="text-end">{{ $tantiemes($lot->tantiemes) }}</td>
                                <td class="text-end">{{ $copro->total_tantiemes ? round((float) $lot->tantiemes / $copro->total_tantiemes * 100, 2) : 0 }} %</td>
                                <td class="text-end montant">{{ $lot->coproprietaire_id ? montant($situations[$lot->coproprietaire_id]['reste'] ?? 0) : '' }}</td>
                                <td class="text-end">
                                    @can('coproprietes-edit')
                                        <x-action :url="route('coproprietes.lots.destroy', [$copro, $lot])" methode="DELETE" icone="ti-trash" classe="btn btn-sm btn-light text-danger" confirmer="Supprimer ce lot ?" />
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @can('coproprietes-edit')
                <form method="POST" action="{{ route('coproprietes.lots.store', $copro) }}" class="card-body border-top row g-2 align-items-end">
                    @csrf
                    <x-form.input name="numero" label="N° de lot" required col="col-md-2 mb-0" />
                    <x-form.input name="designation" label="Désignation" placeholder="Appartement A3, parking 12…" col="col-md-3 mb-0" />
                    <x-form.select name="coproprietaire_id" label="Copropriétaire" :options="$tiers" recherche col="col-md-3 mb-0" />
                    <x-form.input name="tantiemes" label="Tantièmes" type="number" step="0.0001" min="0" required col="col-md-2 mb-0" />
                    @if($biens->isNotEmpty())
                        <x-form.select name="bien_id" label="Bien" :options="$biens" col="col-md-2 mb-0" />
                    @endif
                    <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="ti ti-plus"></i> Ajouter</button></div>
                </form>
            @endcan
        </div>

        <div class="tab-pane fade" id="cles">
            <div class="card-body">
                <p class="small text-muted">Les charges générales sont réparties selon les tantièmes des lots. Créez une clé spéciale pour les charges qui ne concernent qu'une partie des lots (ascenseur, bâtiment B, parkings…).</p>
                @foreach($copro->cles as $cle)
                    @php($parLot = $cle->lots->pluck('tantiemes', 'copro_lot_id'))
                    <form method="POST" action="{{ route('coproprietes.cles.update', [$copro, $cle]) }}" class="border rounded p-3 mb-3">
                        @csrf @method('PUT')
                        <div class="d-flex justify-content-between mb-2"><strong>{{ $cle->libelle }}</strong><span class="small text-muted">Total : {{ $tantiemes($cle->lots->sum('tantiemes')) }}</span></div>
                        <div class="row g-2">
                            @foreach($copro->lots->sortBy('numero', SORT_NATURAL) as $lot)
                                <div class="col-6 col-md-2">
                                    <label class="form-label small mb-0" for="cle{{ $cle->id }}_{{ $lot->id }}">Lot {{ $lot->numero }}</label>
                                    <input type="number" step="0.0001" min="0" id="cle{{ $cle->id }}_{{ $lot->id }}" name="tantiemes[{{ $lot->id }}]" value="{{ isset($parLot[$lot->id]) ? (float) $parLot[$lot->id] : '' }}" class="form-control form-control-sm">
                                </div>
                            @endforeach
                        </div>
                        @can('coproprietes-edit')<div class="text-end mt-2"><button class="btn btn-sm btn-outline-primary">Enregistrer</button></div>@endcan
                    </form>
                @endforeach
                @can('coproprietes-edit')
                    <form method="POST" action="{{ route('coproprietes.cles.store', $copro) }}" class="row g-2 align-items-end">
                        @csrf
                        <x-form.input name="libelle" label="Nouvelle clé de répartition" placeholder="Ex. : Ascenseur" required col="col-md-6 mb-0" />
                        <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="ti ti-plus"></i> Créer</button></div>
                    </form>
                @endcan
            </div>
        </div>

        <div class="tab-pane fade" id="budgets">
            <div class="card-body">
                @foreach($copro->budgets->sortByDesc('exercice') as $budget)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Exercice {{ $budget->exercice }} — {{ montant($budget->montant_total) }}</strong>
                            <span class="d-flex gap-2 align-items-center">
                                <span class="badge bg-{{ ['brouillon' => 'secondary', 'vote' => 'success', 'cloture' => 'dark'][$budget->statut] }}">{{ \App\Models\Budget::STATUTS[$budget->statut] }}</span>
                                @can('coproprietes-edit')
                                    @if($budget->statut === 'brouillon')
                                        <x-action :url="route('coproprietes.budgets.voter', [$copro, $budget])" libelle="Marquer voté" icone="ti-check" classe="btn btn-sm btn-success" confirmer="Le budget a-t-il été voté en assemblée générale ?" />
                                    @endif
                                @endcan
                                @can('appelsfonds-create')
                                    @if($budget->statut === 'vote' && !$budget->appels()->exists())
                                        <x-action :url="route('coproprietes.budgets.appels', [$copro, $budget])" libelle="Générer les appels" icone="ti-file-invoice" classe="btn btn-sm btn-primary">
                                            <select name="nombre" class="form-select form-select-sm d-inline-block w-auto" aria-label="Nombre d'appels">
                                                @foreach([4 => 'Trimestriels', 2 => 'Semestriels', 12 => 'Mensuels', 1 => 'Annuel'] as $n => $l)
                                                    <option value="{{ $n }}" @selected($n === (int) parametre('syndic.appels_par_an'))>{{ $l }}</option>
                                                @endforeach
                                            </select>
                                        </x-action>
                                    @endif
                                @endcan
                            </span>
                        </div>
                        <table class="table table-sm mb-0">
                            @foreach($budget->lignes as $ligne)
                                <tr><td>{{ $postes[$ligne->poste] ?? $ligne->poste }} {{ $ligne->libelle ? '— ' . $ligne->libelle : '' }}</td>
                                    <td class="small text-muted">{{ $ligne->cle?->libelle ?? 'Charges générales' }}</td>
                                    <td class="text-end montant">{{ montant($ligne->montant) }}</td></tr>
                            @endforeach
                        </table>
                    </div>
                @endforeach

                @can('coproprietes-edit')
                    <form method="POST" action="{{ route('coproprietes.budgets.store', $copro) }}" class="border rounded p-3">
                        @csrf
                        <h6>Saisir / modifier un budget prévisionnel</h6>
                        <div class="row g-2 mb-2">
                            <x-form.input name="exercice" label="Exercice" type="number" :value="now()->year + (now()->month > 9 ? 1 : 0)" required col="col-md-2 mb-0" />
                        </div>
                        @foreach(range(0, 7) as $i)
                            <div class="row g-2 mb-1">
                                <div class="col-md-4"><select name="lignes[{{ $i }}][poste]" class="form-select form-select-sm" aria-label="Poste">
                                    @foreach($postes as $code => $libelle)<option value="{{ $code }}" @selected(($postes->keys()[$i] ?? null) === $code)>{{ $libelle }}</option>@endforeach
                                </select></div>
                                <div class="col-md-3"><input name="lignes[{{ $i }}][libelle]" class="form-control form-control-sm" placeholder="Précision" aria-label="Précision"></div>
                                <div class="col-md-3"><select name="lignes[{{ $i }}][cle_repartition_id]" class="form-select form-select-sm" aria-label="Clé">
                                    <option value="">Charges générales</option>
                                    @foreach($copro->cles as $cle)<option value="{{ $cle->id }}">{{ $cle->libelle }}</option>@endforeach
                                </select></div>
                                <div class="col-md-2"><input type="number" step="0.01" min="0" name="lignes[{{ $i }}][montant]" class="form-control form-control-sm text-end" placeholder="Montant" aria-label="Montant"></div>
                            </div>
                        @endforeach
                        <div class="text-end mt-2"><button class="btn btn-primary btn-sm"><i class="ti ti-device-floppy"></i> Enregistrer le budget</button></div>
                    </form>
                @endcan
            </div>
        </div>

        <div class="tab-pane fade" id="appels">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>N°</th><th>Libellé</th><th>Échéance</th><th class="text-end">Montant</th><th>Statut</th></tr></thead>
                    <tbody>
                        @forelse($appels as $appel)
                            <tr><td><a href="{{ route('appelsfonds.show', $appel) }}">{{ $appel->numero ?? 'Brouillon' }}</a></td><td>{{ $appel->libelle }}</td>
                                <td>{{ $appel->date_echeance->format('d/m/Y') }}</td><td class="text-end montant">{{ montant($appel->montant_total) }}</td><td><x-statut :valeur="$appel->statut" /></td></tr>
                        @empty
                            <tr><td colspan="5"><x-vide message="Aucun appel de fonds." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-page>
@endsection
