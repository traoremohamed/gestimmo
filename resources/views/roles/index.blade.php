@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Profils" module="Configuration">
    <x-slot:actions>
        @can('role-create')<a href="{{ route('roles.create') }}" class="btn btn-sm btn-primary"><i class="ti ti-plus"></i> Nouveau profil</a>@endcan
    </x-slot:actions>
    <p class="text-muted small">Profils de votre agence et profils modèles de la plateforme (lecture seule). Un nouveau profil peut reprendre les droits d'un profil existant.</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Profil</th><th>Type</th><th class="text-end">Permissions</th><th class="text-end">Utilisateurs</th><th></th></tr></thead>
                <tbody>
                @foreach ($roles as $role)
                    @php($hid = \App\Helpers\Crypt::UrlCrypt($role->id))
                    <tr class="ligne-cliquable" data-href="{{ route('roles.show', $hid) }}">
                        <td class="fw-semibold">{{ $role->name }}<div class="small text-muted fw-normal">{{ $role->description }}</div></td>
                        <td>@if($role->estModele())<span class="badge badge-soft-info">modèle plateforme</span>@else<span class="badge badge-soft-primary">{{ $role->agence?->nom }}</span>@endif</td>
                        <td class="text-end">{{ $role->permissions_count }}</td>
                        <td class="text-end">{{ $role->users_count }}</td>
                        <td class="text-end text-nowrap">
                            @can('attribuer')<a href="{{ route('menuprofillayout', $hid) }}" class="btn btn-sm btn-light" title="Droits"><i class="ti ti-shield-lock"></i></a>@endcan
                            @if(!$role->estModele() || auth()->user()->estSuperAdmin())
                                @can('role-edit')<a href="{{ route('roles.edit', $hid) }}" class="btn btn-sm btn-light" title="Modifier"><i class="ti ti-pencil"></i></a>@endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-page>
@endsection
