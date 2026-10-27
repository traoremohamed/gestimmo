@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Droits d'accès" module="Configuration">
    <x-slot:actions>
        @can('role-create')<a href="{{ route('roles.create') }}" class="btn btn-sm btn-primary"><i class="ti ti-plus"></i> Nouveau profil</a>@endcan
    </x-slot:actions>
    <p class="text-muted small">Choisissez un profil pour régler les écrans et les actions auxquels il donne accès. Les profils modèles de la plateforme sont en lecture seule :
        créez un profil de votre agence à partir d'un modèle pour l'adapter.</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Profil</th><th>Type</th><th class="text-end">Écrans</th><th class="text-end">Permissions</th><th class="text-end">Utilisateurs</th><th></th></tr></thead>
                <tbody>
                @foreach ($roles as $role)
                    <tr class="ligne-cliquable" data-href="{{ route('menuprofillayout', \App\Helpers\Crypt::UrlCrypt($role->id)) }}">
                        <td class="fw-semibold">{{ $role->name }}<div class="small text-muted fw-normal">{{ $role->description }}</div></td>
                        <td>@if($role->estModele())<span class="badge badge-soft-info">modèle</span>@else<span class="badge badge-soft-primary">{{ $role->agence?->nom }}</span>@endif</td>
                        <td class="text-end">{{ $role->sousmenus_count }}</td>
                        <td class="text-end">{{ $role->permissions_count }}</td>
                        <td class="text-end">{{ $role->users_count }}</td>
                        <td class="text-end"><a href="{{ route('menuprofillayout', \App\Helpers\Crypt::UrlCrypt($role->id)) }}" class="btn btn-sm btn-light"><i class="ti ti-shield-lock"></i> Droits</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-page>
@endsection
