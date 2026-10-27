@extends('layouts.backLayout.designadmin')

@section('content')
<x-page :titre="$titre" :module="$module">
    <x-slot:actions>
        @if($vueActions)
            @include($vueActions)
        @endif
        <x-export :url="request()->fullUrlWithoutQuery(['format', 'page'])" />
        @can($permission . '-create')
            <a href="{{ route($route . '.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus"></i> Ajouter</a>
        @endcan
    </x-slot:actions>

    <div class="card">
        @if($avecRecherche || $filtres)
            <div class="card-body border-bottom pb-0">
                <form method="GET" class="row g-2 align-items-end">
                    @if($avecRecherche)
                        <div class="col-md-4 mb-3">
                            <label class="form-label" for="q">Recherche</label>
                            <input type="search" name="q" id="q" value="{{ request('q') }}" class="form-control" placeholder="Rechercher…">
                        </div>
                    @endif
                    @foreach($filtres as $filtre)
                        <x-form.select :name="$filtre['nom']" :label="$filtre['label']" :options="$controleur->options($filtre)" :value="request($filtre['nom'])" col="col-md-3" placeholder="Tous" />
                    @endforeach
                    <div class="col-auto mb-3">
                        <button class="btn btn-outline-primary"><i class="ti ti-filter"></i> Filtrer</button>
                        @if(request()->query())
                            <a href="{{ route($route . '.index') }}" class="btn btn-link">Réinitialiser</a>
                        @endif
                    </div>
                </form>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        @foreach($colonnes as $colonne)
                            <th class="{{ $colonne[2] ?? '' }}">{{ $colonne[0] }}</th>
                        @endforeach
                        <th class="text-end no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($elements as $element)
                        {{-- clic sur la ligne : fiche, sinon modification --}}
                        @php($lienLigne = Route::has($route . '.show') ? route($route . '.show', $element) : (auth()->user()->can($permission . '-edit') && Route::has($route . '.edit') ? route($route . '.edit', $element) : null))
                        <tr @if($lienLigne) class="ligne-cliquable" data-href="{{ $lienLigne }}" @endif>
                            @foreach($colonnes as $colonne)
                                <td class="{{ $colonne[2] ?? '' }}">{{ $colonne[1]($element) }}</td>
                            @endforeach
                            <td class="text-end text-nowrap no-print">
                                @if(Route::has($route . '.show'))
                                    <a href="{{ route($route . '.show', $element) }}" class="btn btn-sm btn-light" title="Voir"><i class="ti ti-eye"></i></a>
                                @endif
                                @can($permission . '-edit')
                                    <a href="{{ route($route . '.edit', $element) }}" class="btn btn-sm btn-light" title="Modifier"><i class="ti ti-pencil"></i></a>
                                @endcan
                                @if(Route::has($route . '.destroy'))
                                    @can($permission . '-delete')
                                        <x-action :url="route($route . '.destroy', $element)" methode="DELETE" icone="ti-trash" classe="btn btn-sm btn-light text-danger"
                                            titre="Supprimer" confirmer="Supprimer cet élément ?" />
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($colonnes) + 1 }}"><x-vide /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($elements->hasPages())
            <div class="card-footer">{{ $elements->links() }}</div>
        @endif
    </div>
</x-page>
@endsection
