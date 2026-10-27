@extends('layouts.backLayout.designadmin')

@section('content')
<x-page :titre="$page->titre" module="Site web" :retour="route('siteweb.index')">
    <x-slot:badge><span class="badge badge-soft-secondary">{{ $page->est_accueil ? 'page d\'accueil' : '/p/' . $page->slug }}</span></x-slot:badge>
    <x-slot:actions>
        <a href="{{ route('siteweb.apercu', $page) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light"><i class="ti ti-eye"></i> Aperçu</a>
    </x-slot:actions>

    <div class="row g-3">
        <div class="col-xl-8">
            @forelse ($page->sections as $s)
                @php($def = $types[$s->type] ?? ['libelle' => $s->type, 'icone' => 'ti ti-square'])
                <div class="card mb-2 {{ $s->visible ? '' : 'opacity-50' }}">
                    <div class="card-body d-flex align-items-center gap-3 py-2">
                        <div class="ui-kpi-ico"><i class="{{ $def['icone'] }}"></i></div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $s->titre ?: $def['libelle'] }}</div>
                            <small class="text-muted">{{ $def['libelle'] }}{{ $s->ancre ? ' · #' . $s->ancre : '' }}{{ count($s->elements ?? []) ? ' · ' . count($s->elements) . ' élément(s)' : '' }}{{ $s->dans_menu ? ' · dans le menu' : '' }}</small>
                        </div>
                        @can('siteweb-edit')
                            <div class="btn-group btn-group-sm">
                                <form method="POST" action="{{ route('siteweb.sections.deplacer', [$s, 'haut']) }}">@csrf<button class="btn btn-light" @disabled($loop->first) title="Monter"><i class="ti ti-arrow-up"></i></button></form>
                                <form method="POST" action="{{ route('siteweb.sections.deplacer', [$s, 'bas']) }}">@csrf<button class="btn btn-light" @disabled($loop->last) title="Descendre"><i class="ti ti-arrow-down"></i></button></form>
                                <form method="POST" action="{{ route('siteweb.sections.visibilite', $s) }}">@csrf<button class="btn btn-light" title="{{ $s->visible ? 'Masquer' : 'Afficher' }}"><i class="ti ti-{{ $s->visible ? 'eye' : 'eye-off' }}"></i></button></form>
                                <a href="{{ route('siteweb.sections.edit', $s) }}" class="btn btn-soft" title="Modifier"><i class="ti ti-pencil"></i></a>
                                <form method="POST" action="{{ route('siteweb.sections.destroy', $s) }}" data-confirm="Supprimer ce bloc ?">@csrf @method('DELETE')<button class="btn btn-light text-danger" title="Supprimer"><i class="ti ti-trash"></i></button></form>
                            </div>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="card"><div class="ui-vide"><i class="ti ti-layout-off"></i>Page vide : ajoutez un premier bloc.</div></div>
            @endforelse

            @can('siteweb-edit')
                <div class="card mt-3">
                    <div class="card-header"><i class="ti ti-plus"></i> Ajouter un bloc</div>
                    <div class="card-body d-flex flex-wrap gap-2">
                        @foreach ($types as $code => $t)
                            <form method="POST" action="{{ route('siteweb.sections.store', $page) }}">@csrf<input type="hidden" name="type" value="{{ $code }}">
                                <button class="btn btn-light btn-sm"><i class="{{ $t['icone'] }}"></i> {{ $t['libelle'] }}</button></form>
                        @endforeach
                    </div>
                </div>
            @endcan
        </div>

        <div class="col-xl-4">
            <form method="POST" action="{{ route('siteweb.pages.update', $page) }}" class="card">
                @csrf @method('PUT')
                <div class="card-header">Page</div>
                <fieldset class="card-body row" @cannot('siteweb-edit') disabled @endcannot>
                    <x-form.input name="titre" label="Titre (menu)" :value="$page->titre" required col="col-12" />
                    @unless ($page->est_accueil)<x-form.input name="slug" label="Adresse (/p/…)" :value="$page->slug" col="col-12" />@endunless
                    <x-form.input name="seo_titre" label="Titre pour les moteurs de recherche" :value="$page->seo_titre" col="col-12" />
                    <x-form.textarea name="seo_description" label="Description pour les moteurs de recherche" :value="$page->seo_description" rows="2" />
                    <x-form.input name="ordre" label="Ordre dans le menu" type="number" :value="$page->ordre" col="col-md-6" />
                    <div class="col-12 d-flex flex-column gap-1">
                        @foreach (['publie' => 'Page visible', 'dans_menu' => 'Dans le menu', 'est_accueil' => 'Page d\'accueil'] as $k => $l)
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="{{ $k }}" value="1" id="p-{{ $k }}" @checked($page->{$k})><label class="form-check-label" for="p-{{ $k }}">{{ $l }}</label></div>
                        @endforeach
                    </div>
                </fieldset>
                @can('siteweb-edit')<div class="card-footer"><button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer</button></div>@endcan
            </form>
            @can('siteweb-edit')
                @unless ($page->est_accueil)
                    <form method="POST" action="{{ route('siteweb.pages.destroy', $page) }}" class="mt-2" data-confirm="Supprimer cette page et ses blocs ?">@csrf @method('DELETE')
                        <button class="btn btn-link text-danger"><i class="ti ti-trash"></i> Supprimer la page</button></form>
                @endunless
            @endcan
        </div>
    </div>
</x-page>
@endsection
