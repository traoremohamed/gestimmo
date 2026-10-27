@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Documents (GED)" module="GED">
    <x-slot:actions>
        <x-export :url="request()->fullUrlWithoutQuery(['format', 'page'])" />
        @can('ged-create')
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalGed"><i class="ti ti-upload"></i> Ajouter des documents</button>
        @endcan
    </x-slot:actions>

    <p class="text-muted small mb-3">{{ number_format($volume->n, 0, ',', ' ') }} document(s) — {{ number_format($volume->octets / 1048576, 1, ',', ' ') }} Mo.
        Tout format accepté (PDF, images, Excel, Word, archives…) sauf les fichiers exécutables et scripts. Chaque document porte une signature QR vérifiable.</p>

    <div class="card">
        <div class="card-body border-bottom pb-0">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3 mb-3"><label class="form-label" for="q">Recherche</label>
                    <input type="search" name="q" id="q" value="{{ request('q') }}" class="form-control" placeholder="Titre, n°, nom de fichier…"></div>
                <x-form.select name="categorie" label="Catégorie" :options="$categories" :value="request('categorie')" col="col-md-2" placeholder="Toutes" />
                <x-form.select name="objet" label="Rattaché à" :options="$objets" :value="request('objet')" col="col-md-2" placeholder="Tous" />
                <x-form.select name="format_fichier" label="Format" :options="['pdf' => 'PDF', 'image' => 'Images', 'tableur' => 'Excel / tableurs', 'texte' => 'Word / texte']" :value="request('format_fichier')" col="col-md-2" placeholder="Tous" />
                <x-form.select name="source" label="Origine" :options="['televerse' => 'Téléversé', 'genere' => 'Généré par l\'application']" :value="request('source')" col="col-md-2" placeholder="Toutes" />
                <div class="col-auto mb-3"><button class="btn btn-outline-primary"><i class="ti ti-filter"></i> Filtrer</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Document</th><th>Catégorie</th><th>Rattaché à</th><th>Tiers</th><th class="text-end">Taille</th><th>Ajouté</th><th></th></tr></thead>
                <tbody>
                @forelse($documents as $d)
                    <tr class="ligne-cliquable" data-href="{{ route('ged.show', $d) }}">
                        <td><a href="{{ route('ged.show', $d) }}" class="text-reset text-decoration-none">
                            <i class="ti {{ $d->icone() }} fs-5 text-primary"></i> <span class="fw-medium">{{ $d->titre }}</span>
                            @if($d->confidentiel)<span class="badge text-bg-warning ms-1">confidentiel</span>@endif
                            @if($d->version > 1)<span class="badge text-bg-light border ms-1">v{{ $d->version }}</span>@endif
                            <div class="small text-muted">{{ $d->numero }} · {{ $d->nom_original }}</div></a></td>
                        <td>{{ \App\Models\Referentiel::libelle('categorie_document', $d->categorie) }}</td>
                        <td class="small">{{ $objets[$d->documentable_type] ?? '' }}</td>
                        <td class="small">{{ $d->tiers?->nom_complet }}</td>
                        <td class="text-end small">{{ $d->tailleLisible() }}</td>
                        <td class="small">{{ $d->created_at->format('d/m/Y') }}<div class="text-muted">{{ $d->source === 'genere' ? 'Généré' : $d->auteur?->name }}</div></td>
                        <td class="text-end text-nowrap">
                            @if($d->apercuPossible())<a href="{{ route('ged.apercu', $d) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light" title="Aperçu"><i class="ti ti-eye"></i></a>@endif
                            <a href="{{ route('ged.telecharger', $d) }}" class="btn btn-sm btn-light" title="Télécharger"><i class="ti ti-download"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-vide /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $documents->links() }}</div>
    </div>

    @can('ged-create')
        @include('ged._televerser', ['objet' => null])
    @endcan
</x-page>
@endsection
