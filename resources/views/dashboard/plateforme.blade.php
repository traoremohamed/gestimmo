@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Administration de la plateforme" module="Plateforme">
    <x-slot:actions>
        @can('agences-create')<a href="{{ route('agences.create') }}" class="btn btn-sm btn-primary"><i class="ti ti-plus"></i> Nouvelle agence</a>@endcan
    </x-slot:actions>

    <div class="row">
        <x-stat libelle="Agences" :valeur="$agences->count()" icone="ti-building" :detail="$agences->filter->abonnementValide()->count() . ' active(s)'" />
        <x-stat libelle="Utilisateurs" :valeur="$utilisateurs" icone="ti-users" />
    </div>

    <div class="card">
        <div class="card-header">Choisissez une agence (sélecteur en haut de page) pour accéder à ses modules</div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Agence</th><th>Offre</th><th class="text-end">Utilisateurs</th><th>Modules</th><th>Abonnement</th><th></th></tr></thead>
                <tbody>
                    @foreach($agences as $agence)
                        <tr>
                            <td class="fw-medium">{{ $agence->raison_sociale }}<div class="small text-muted">{{ $agence->code }}</div></td>
                            <td>{{ $agence->plan }}</td>
                            <td class="text-end">{{ $agence->users_count }}</td>
                            <td class="small">{{ collect($agence->modulesActifs())->map(fn ($m) => config("immobilier.modules.$m.libelle"))->implode(', ') }}</td>
                            <td>{!! $agence->abonnementValide() ? '<span class="badge bg-success">Actif</span>' : '<span class="badge bg-danger">Suspendu</span>' !!}
                                <span class="small text-muted">{{ $agence->abonnement_fin?->format('d/m/Y') }}</span></td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('agences.basculer') }}">@csrf<input type="hidden" name="agence_id" value="{{ $agence->id }}">
                                    <button class="btn btn-sm btn-outline-primary">Ouvrir</button></form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-page>
@endsection
