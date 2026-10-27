@extends('layouts.backLayout.designadmin')

@php use App\Models\Compta\Compte; use App\Models\Compta\Schema; @endphp

@section('content')
<x-page titre="Plan comptable & imputations" module="Comptabilité">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#imputations" type="button">Règles d'imputation</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#plan" type="button">Plan comptable ({{ $comptes->count() }})</button></li>
    </ul>
    <div class="tab-content card rounded-top-0">
        <div class="tab-pane fade show active" id="imputations">
            <form method="POST" action="{{ route('plancomptable.schemas') }}">
                @csrf @method('PUT')
                <datalist id="comptes">@foreach($comptes as $c)<option value="{{ $c->numero }}">{{ $c->libelle }}</option>@endforeach</datalist>
                <div class="card-body pb-0"><p class="small text-muted">Chaque opération génère une écriture selon ces règles. Les loyers des biens gérés pour le compte de propriétaires et les fonds des copropriétés restent en comptes de tiers (4671, 4672) : ce ne sont pas des produits de l'agence.</p></div>
                @foreach($schemas as $operation => $regles)
                    <div class="px-3"><h6 class="text-primary mt-3">{{ Schema::OPERATIONS[$operation] ?? $operation }}</h6></div>
                    <table class="table table-sm mb-0">
                        <tbody>
                            @foreach($regles as $s)
                                <tr>
                                    <td class="ps-3">{{ $s->libelle }} <span class="small text-muted">({{ $s->cle }})</span></td>
                                    <td style="width: 160px"><input name="schemas[{{ $s->id }}][compte]" value="{{ $s->compte }}" list="comptes" class="form-control form-control-sm" required aria-label="Compte"></td>
                                    <td class="small text-muted" style="width: 260px">{{ $comptes->firstWhere('numero', $s->compte)?->libelle }}</td>
                                    <td style="width: 140px">
                                        <select name="schemas[{{ $s->id }}][journal]" class="form-select form-select-sm" aria-label="Journal">
                                            <option value="">—</option>
                                            @foreach($journaux as $j)<option value="{{ $j->code }}" @selected($s->journal === $j->code)>{{ $j->code }}</option>@endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
                @can('plancomptable-edit')<div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer les règles</button></div>@endcan
            </form>
        </div>
        <div class="tab-pane fade" id="plan">
            @can('plancomptable-edit')
                <form method="POST" action="{{ route('plancomptable.comptes.store') }}" class="card-body border-bottom row g-2 align-items-end">
                    @csrf
                    <x-form.input name="numero" label="Numéro" required col="col-md-2 mb-0" />
                    <x-form.input name="libelle" label="Intitulé" required col="col-md-5 mb-0" />
                    <x-form.select name="type" label="Type" :options="Compte::TYPES" col="col-md-3 mb-0" />
                    <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="ti ti-plus"></i> Ajouter</button></div>
                </form>
            @endcan
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 exampleData">
                    <thead><tr><th>N°</th><th>Intitulé</th><th>Classe</th><th>Type</th><th class="text-end">Lignes</th><th>Actif</th></tr></thead>
                    <tbody>
                        @foreach($comptes as $c)
                            <tr><td><a href="{{ route('comptabilite.grandlivre', $c) }}">{{ $c->numero }}</a></td><td>{{ $c->libelle }}</td><td>{{ $c->classe }}</td>
                                <td>{{ Compte::TYPES[$c->type] ?? '' }}</td><td class="text-end">{{ $c->lignes_count }}</td><td>{!! $c->actif ? '<i class="ti ti-check text-success"></i>' : '' !!}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-page>
@endsection
