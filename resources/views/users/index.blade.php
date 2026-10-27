@extends('layouts.backLayout.designadmin')

@php($horsAgence = auth()->user()->estSuperAdmin() && !app(\App\Support\AgenceCourante::class)->existe())

@section('content')
<x-page titre="Utilisateurs" module="Paramétrage">
    <x-slot:actions>
        @can('users-create')
            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-user-plus"></i> Ajouter un utilisateur</a>
        @endcan
    </x-slot:actions>

    <p class="text-muted small">{{ $horsAgence ? 'Comptes de la plateforme et de toutes les agences.' : 'Comptes de votre agence uniquement.' }}
        Chaque compte reçoit un profil ; les droits se règlent dans « Droits d'accès ».</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle ui-table">
                <thead><tr><th>Utilisateur</th><th>Identifiant</th><th>Profil</th>@if($horsAgence)<th>Agence</th>@endif<th>Dernière connexion</th><th>État</th><th></th></tr></thead>
                <tbody>
                @foreach ($data as $user)
                    <tr class="ligne-cliquable" data-href="{{ route('users.show', \App\Helpers\Crypt::UrlCrypt($user->id)) }}">
                        <td><div class="d-flex align-items-center gap-2"><span class="ui-avatar">{{ $user->initiales }}</span>
                            <div><div class="fw-semibold">{{ $user->nom_complet }}</div><div class="small text-muted">{{ $user->email }}</div></div></div></td>
                        <td><code>{{ $user->login_users }}</code></td>
                        <td>@foreach($user->roles as $r)<span class="badge badge-soft-primary">{{ $r->name }}</span>@endforeach
                            @if($user->est_super_admin)<span class="badge badge-soft-danger">Super administrateur</span>@endif</td>
                        @if($horsAgence)<td class="small">{{ $user->agence?->nom ?? 'Plateforme' }}</td>@endif
                        <td class="small text-muted">{{ $user->derniere_connexion_at?->format('d/m/Y H:i') ?? 'jamais' }}</td>
                        <td>
                            @if($user->verrouille_jusqu_a?->isFuture())<span class="badge badge-soft-danger"><i class="ti ti-lock"></i> verrouillé</span>
                            @elseif($user->flag_actif_users && $user->accesAutorise())<span class="badge badge-soft-success">actif</span>
                            @else<span class="badge badge-soft-secondary">inactif</span>@endif
                        </td>
                        <td class="text-end">
                            @can('users-edit')<a href="{{ route('users.edit', \App\Helpers\Crypt::UrlCrypt($user->id)) }}" class="btn btn-sm btn-light" title="Modifier"><i class="ti ti-pencil"></i></a>@endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-page>
@endsection
