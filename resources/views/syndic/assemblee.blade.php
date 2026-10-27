@extends('layouts.backLayout.designadmin')

@php
    use App\Models\Resolution;
    $statut = $assemblee->statut->value;
    $total = $assemblee->copropriete->total_tantiemes;
@endphp

@section('content')
<x-page :titre="'AG ' . \App\Models\Assemblee::TYPES[$assemblee->type] . ' du ' . $assemblee->date_heure->format('d/m/Y à H:i')" module="Syndic" :retour="route('assemblees.index')">
    <x-slot:badge><x-statut :valeur="$assemblee->statut" class="fs-6" /></x-slot:badge>
    <x-slot:actions>
        @can('assemblees-edit')
            @if($statut === 'planifiee')
                <x-action :url="route('assemblees.convoquer', $assemblee)" libelle="Marquer convoquée" icone="ti-mail" classe="btn btn-sm btn-primary" />
            @endif
            <a href="{{ route('assemblees.edit', $assemblee) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-pencil"></i> Modifier</a>
        @endcan
    </x-slot:actions>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="fiche mb-0">
                        <dt>Copropriété</dt><dd><a href="{{ route('coproprietes.show', $assemblee->copropriete) }}">{{ $assemblee->copropriete->nom }}</a> ({{ $total }} tantièmes)</dd>
                        <dt>Lieu</dt><dd>{{ $assemblee->lieu ?: '—' }}</dd>
                        @if($assemblee->convoquee_le)<dt>Convoquée le</dt><dd>{{ $assemblee->convoquee_le->format('d/m/Y') }}</dd>@endif
                        @if($assemblee->tantiemes_presents !== null)<dt>Présents / représentés</dt><dd>{{ (float) $assemblee->tantiemes_presents }} tantièmes ({{ round((float) $assemblee->tantiemes_presents / $total * 100, 1) }} %)</dd>@endif
                        <dt>Ordre du jour</dt><dd style="white-space: pre-line">{{ $assemblee->ordre_du_jour ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
            @can('assemblees-edit')
                @if($statut !== 'tenue' && $statut !== 'annulee')
                    <form method="POST" action="{{ route('assemblees.cloturer', $assemblee) }}" class="card mb-4">
                        @csrf
                        <div class="card-header">Clôture et procès-verbal</div>
                        <div class="card-body row">
                            <x-form.input name="tantiemes_presents" label="Tantièmes présents ou représentés" type="number" step="0.0001" min="0" :max="$total" required col="col-12" />
                            <x-form.textarea name="proces_verbal" label="Procès-verbal" rows="5" />
                        </div>
                        <div class="card-footer text-end"><button class="btn btn-success btn-sm">Clôturer l'assemblée</button></div>
                    </form>
                @elseif($assemblee->proces_verbal)
                    <div class="card mb-4"><div class="card-header">Procès-verbal</div><div class="card-body small" style="white-space: pre-line">{{ $assemblee->proces_verbal }}</div></div>
                @endif
            @endcan
        </div>

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">Résolutions</div>
                <ul class="list-group list-group-flush">
                    @forelse($assemblee->resolutions as $resolution)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div><strong>{{ $resolution->numero }}. {{ $resolution->intitule }}</strong>
                                    <div class="small text-muted">{{ Resolution::MAJORITES[$resolution->majorite] }}</div>
                                    @if($resolution->description)<div class="small">{{ $resolution->description }}</div>@endif
                                </div>
                                <div>
                                    @if($resolution->adoptee === true)<span class="badge bg-success">Adoptée</span>
                                    @elseif($resolution->adoptee === false)<span class="badge bg-danger">Rejetée</span>
                                    @else<span class="badge bg-light text-dark">Non votée</span>@endif
                                </div>
                            </div>
                            @can('assemblees-edit')
                                <form method="POST" action="{{ route('assemblees.resolutions.voter', [$assemblee, $resolution]) }}" class="row g-2 mt-1 align-items-end">
                                    @csrf @method('PUT')
                                    <div class="col-md-3"><label class="form-label small mb-0">Pour</label><input type="number" step="0.0001" min="0" name="voix_pour" value="{{ (float) $resolution->voix_pour }}" class="form-control form-control-sm" required></div>
                                    <div class="col-md-3"><label class="form-label small mb-0">Contre</label><input type="number" step="0.0001" min="0" name="voix_contre" value="{{ (float) $resolution->voix_contre }}" class="form-control form-control-sm" required></div>
                                    <div class="col-md-3"><label class="form-label small mb-0">Abstention</label><input type="number" step="0.0001" min="0" name="voix_abstention" value="{{ (float) $resolution->voix_abstention }}" class="form-control form-control-sm"></div>
                                    <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100">Enregistrer le vote</button></div>
                                </form>
                            @endcan
                        </li>
                    @empty
                        <li class="list-group-item"><x-vide message="Aucune résolution à l'ordre du jour." /></li>
                    @endforelse
                </ul>
                @can('assemblees-edit')
                    <form method="POST" action="{{ route('assemblees.resolutions.store', $assemblee) }}" class="card-body border-top row g-2 align-items-end">
                        @csrf
                        <x-form.input name="intitule" label="Nouvelle résolution" required col="col-md-5 mb-0" />
                        <x-form.select name="majorite" label="Majorité requise" :options="Resolution::MAJORITES" value="simple" required col="col-md-5 mb-0" />
                        <div class="col-md-2"><button class="btn btn-outline-primary w-100"><i class="ti ti-plus"></i></button></div>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-page>
@endsection
