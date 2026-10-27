@extends('layouts.backLayout.designadmin')

@section('content')
<x-page :titre="'Profil ' . $role->name" module="Configuration" :retour="route('roles.index')">
    <x-slot:actions>
        @can('attribuer')<a href="{{ route('menuprofillayout', \App\Helpers\Crypt::UrlCrypt($role->id)) }}" class="btn btn-sm btn-primary"><i class="ti ti-shield-lock"></i> Droits d'accès</a>@endcan
    </x-slot:actions>
    <p class="text-muted">{{ $role->description ?: 'Aucune description.' }} — {{ $role->estModele() ? 'Profil modèle de la plateforme' : 'Profil de l\'agence ' . $role->agence?->nom }}</p>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Écran</th><th>Permission</th><th>Code</th></tr></thead>
                <tbody>
                @forelse($rolePermissions->sortBy(fn ($p) => $p->sousmenu?->libelle) as $p)
                    <tr><td>{{ $p->sousmenu?->libelle }}</td><td>{{ $p->lib_permission }}</td><td><code class="small">{{ $p->name }}</code></td></tr>
                @empty
                    <tr><td colspan="3"><x-vide message="Aucune permission attribuée." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-page>
@endsection
