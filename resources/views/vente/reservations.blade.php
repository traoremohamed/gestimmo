@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Réservations" module="Ventes">
    <x-slot:actions>
        @can('reservations-create')
            <a href="{{ route('reservations.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-lock"></i> Réserver un bien</a>
        @endcan
    </x-slot:actions>

    <ul class="nav nav-tabs mb-0">
        @foreach(['' => 'Actives', 'convertie' => 'Converties', 'liberee' => 'Libérées', 'expiree' => 'Expirées'] as $statut => $libelle)
            <li class="nav-item"><a class="nav-link @if(request('statut', '') === $statut) active @endif" href="{{ route('reservations.index', array_filter(['statut' => $statut])) }}">{{ $libelle }}</a></li>
        @endforeach
    </ul>
    <div class="card rounded-top-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>N°</th><th>Client</th><th>Bien</th><th>Réservé le</th><th>Expire le</th><th>Commercial</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse($reservations as $reservation)
                        <tr>
                            <td>{{ $reservation->numero }}</td>
                            <td><a href="{{ route('tiers.show', $reservation->tiers) }}">{{ $reservation->tiers->nom_complet }}</a></td>
                            <td><a href="{{ route('biens.show', $reservation->bien) }}">{{ $reservation->bien->designation }}</a></td>
                            <td>{{ $reservation->date_reservation->format('d/m/Y') }}</td>
                            <td class="{{ $reservation->estActive() && $reservation->date_expiration->diffInDays(today(), false) > -2 ? 'text-danger fw-medium' : '' }}">{{ $reservation->date_expiration->format('d/m/Y') }}</td>
                            <td>{{ $reservation->commercial?->nom_complet }}</td>
                            <td><x-statut :valeur="$reservation->statut" /></td>
                            <td class="text-end text-nowrap">
                                @if($reservation->estActive())
                                    @can('ventes-create')
                                        @if($reservation->vente)
                                            <a href="{{ route('ventes.show', $reservation->vente) }}" class="btn btn-sm btn-outline-primary">Voir le contrat</a>
                                        @else
                                            <x-action :url="route('reservations.convertir', $reservation)" libelle="Établir le contrat" icone="ti-file-plus" classe="btn btn-sm btn-success" />
                                        @endif
                                    @endcan
                                    @can('reservations-edit')
                                        <x-action :url="route('reservations.liberer', $reservation)" libelle="Libérer" icone="ti-lock-open" classe="btn btn-sm btn-outline-secondary" confirmer="Libérer le bien ?" />
                                    @endcan
                                @elseif($reservation->vente)
                                    <a href="{{ route('ventes.show', $reservation->vente) }}" class="btn btn-sm btn-light">{{ $reservation->vente->numero ?? 'Contrat' }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-vide message="Aucune réservation." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reservations->hasPages())<div class="card-footer">{{ $reservations->links() }}</div>@endif
    </div>
</x-page>
@endsection
