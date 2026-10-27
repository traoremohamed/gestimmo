@extends('layouts.backLayout.designadmin')

@push('styles')
<style>
    .planning td, .planning th { padding: 0 !important; height: 34px; min-width: 42px; font-size: .75rem; text-align: center; vertical-align: middle; }
    .planning th.bien { min-width: 160px; text-align: left; padding: 0 .5rem !important; }
    .planning .occupe a { display: block; height: 100%; line-height: 34px; color: #fff; text-decoration: none; overflow: hidden; white-space: nowrap; }
    .planning .weekend { background: #fafafc; }
</style>
@endpush

@section('content')
<x-page titre="Séjours — résidences meublées" module="Résidences meublées">
    <x-slot:actions>
        @can('sejours-create')
            <a href="{{ route('sejours.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus"></i> Nouveau séjour</a>
        @endcan
    </x-slot:actions>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Planning d'occupation</span>
            <span class="d-flex gap-1">
                <a class="btn btn-sm btn-light" href="{{ route('sejours.index', ['du' => $debut->copy()->subDays(14)->toDateString()]) }}"><i class="ti ti-chevron-left"></i></a>
                <a class="btn btn-sm btn-light" href="{{ route('sejours.index') }}">Aujourd'hui</a>
                <a class="btn btn-sm btn-light" href="{{ route('sejours.index', ['du' => $debut->copy()->addDays(14)->toDateString()]) }}"><i class="ti ti-chevron-right"></i></a>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered planning mb-0">
                <thead>
                    <tr>
                        <th class="bien">Bien</th>
                        @foreach($jours as $jour)
                            <th class="{{ $jour->isWeekend() ? 'weekend' : '' }} {{ $jour->isToday() ? 'text-primary' : '' }}">{{ $jour->locale('fr')->translatedFormat('D') }}<br>{{ $jour->format('d/m') }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($biens as $bien)
                        <tr>
                            <th class="bien"><a href="{{ route('biens.show', $bien) }}">{{ $bien->reference }}</a> <span class="small text-muted">{{ montant($bien->prix_nuitee, false) }}</span></th>
                            @foreach($jours as $jour)
                                @php($sejour = $bien->sejours->first(fn ($s) => $s->date_arrivee->lte($jour) && $s->date_depart->gt($jour)))
                                @if($sejour)
                                    <td class="occupe bg-{{ $sejour->statut->couleur() }}" title="{{ $sejour->client?->nom_complet }} — {{ $sejour->numero }}">
                                        <a href="{{ route('sejours.show', $sejour) }}">{{ $sejour->date_arrivee->isSameDay($jour) || $jour->isSameDay($jours->first()) ? \Illuminate\Support\Str::limit($sejour->client?->nom, 8, '') : '' }}</a>
                                    </td>
                                @else
                                    <td class="{{ $jour->isWeekend() ? 'weekend' : '' }}">
                                        @can('sejours-create')<a href="{{ route('sejours.create', ['bien_id' => $bien->id, 'arrivee' => $jour->toDateString()]) }}" class="text-muted text-decoration-none d-block" title="Réserver">·</a>@endcan
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="15"><x-vide message="Aucun bien meublé : cochez « Meublé » sur la fiche d'un bien." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Séjours en cours et à venir</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>N°</th><th>Client</th><th>Bien</th><th>Arrivée</th><th>Départ</th><th class="text-end">Montant</th><th class="text-end">Payé</th><th>Statut</th></tr></thead>
                <tbody>
                    @forelse($sejours as $s)
                        <tr>
                            <td><a href="{{ route('sejours.show', $s) }}">{{ $s->numero }}</a></td>
                            <td>{{ $s->client?->nom_complet }}</td>
                            <td>{{ $s->bien?->reference }}</td>
                            <td>{{ $s->date_arrivee->format('d/m/Y') }}</td>
                            <td>{{ $s->date_depart->format('d/m/Y') }} <span class="small text-muted">({{ $s->nb_nuits }} n.)</span></td>
                            <td class="text-end montant">{{ montant($s->montant) }}</td>
                            <td class="text-end montant">{{ montant($s->montant_paye) }}</td>
                            <td><x-statut :valeur="$s->statut" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-vide message="Aucun séjour." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($sejours->hasPages())<div class="card-footer">{{ $sejours->links() }}</div>@endif
    </div>
</x-page>
@endsection
