@props(['objet', 'titre' => 'Documents', 'categorie' => 'AUTRE'])

{{-- Documents GED rattachés à un dossier (bail, tiers, bien, vente, décompte…) avec ajout direct --}}
@if(app(\App\Support\AgenceCourante::class)->moduleActif('ged') && auth()->user()->can('ged-index'))
    @php($documents = \App\Models\Document::where('documentable_type', $objet->getMorphClass())->where('documentable_id', $objet->getKey())
        ->when(!auth()->user()->can('ged-confidentiel'), fn ($q) => $q->where('confidentiel', false))->latest('id')->get())
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="ti ti-folders"></i> {{ $titre }} <span class="badge text-bg-light border">{{ $documents->count() }}</span></span>
            @can('ged-create')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalGed-{{ $objet->getMorphClass() }}-{{ $objet->getKey() }}"><i class="ti ti-upload"></i> Ajouter</button>
            @endcan
        </div>
        <ul class="list-group list-group-flush">
            @forelse($documents as $d)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <a href="{{ route('ged.show', $d) }}" class="text-reset text-decoration-none small">
                        <i class="ti {{ $d->icone() }} text-primary"></i> {{ $d->titre }}
                        <span class="text-muted">· {{ \App\Models\Referentiel::libelle('categorie_document', $d->categorie) }} · {{ $d->tailleLisible() }} · {{ $d->created_at->format('d/m/Y') }}</span>
                    </a>
                    <span class="text-nowrap">
                        @if($d->apercuPossible())<a href="{{ route('ged.apercu', $d) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light" title="Aperçu"><i class="ti ti-eye"></i></a>@endif
                        <a href="{{ route('ged.telecharger', $d) }}" class="btn btn-sm btn-light" title="Télécharger"><i class="ti ti-download"></i></a>
                    </span>
                </li>
            @empty
                <li class="list-group-item small text-muted">Aucun document.</li>
            @endforelse
        </ul>
    </div>
    @can('ged-create')
        @include('ged._televerser', ['objet' => $objet, 'categorie' => $categorie])
    @endcan
@endif
