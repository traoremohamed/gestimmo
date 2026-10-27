@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Sous modules" module="Configuration">
    <x-slot:actions>
        @can('sous-module-create')<a href="{{ route('sousmenus.create') }}" class="btn btn-sm btn-primary"><i class="ti ti-plus"></i> Ajouter un sous module</a>@endcan
    </x-slot:actions>
    <p class="text-muted small">Arborescence des menus : autant de niveaux d'imbrication que nécessaire. Un sous-menu sans lien sert de groupe ;
        un écran « plateforme » n'est jamais attribué à un profil d'agence.</p>

    @foreach ($menus as $menu)
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>{{ $menu->menu }} @if($menu->code)<code class="small text-muted ms-1">{{ $menu->code }}</code>@endif
                    @unless($menu->is_valide)<span class="badge badge-soft-secondary ms-2">inactif</span>@endunless</span>
                <span class="text-muted small fw-normal">{{ count($parParent['menu-' . $menu->id_menu] ?? []) }} élément(s) de premier niveau</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Libellé</th><th>Lien</th><th class="text-center">Priorité</th><th class="text-center">Permissions</th><th class="text-center">Statut</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    @forelse ($parParent['menu-' . $menu->id_menu] ?? [] as $sousmenu)
                        @include('sousmenus._noeud', ['sousmenu' => $sousmenu, 'profondeur' => 0])
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Aucun sous module.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</x-page>
@endsection
