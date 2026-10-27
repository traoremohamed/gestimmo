@extends('layouts.backLayout.designadmin')

@section('content')
<x-page :titre="$document->titre" module="GED" :retour="route('ged.index')">
    <x-slot:badge><span class="badge text-bg-light border">{{ $document->numero }}</span></x-slot:badge>
    <x-slot:actions>
        @if($document->apercuPossible())<a href="{{ route('ged.apercu', $document) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light"><i class="ti ti-eye"></i> Aperçu</a>@endif
        <a href="{{ route('ged.telecharger', $document) }}" class="btn btn-sm btn-primary"><i class="ti ti-download"></i> Télécharger</a>
        <a href="{{ route('ged.attestation', $document) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"><i class="ti ti-qrcode"></i> Attestation signée QR</a>
        @can('ged-delete')
            <form method="POST" action="{{ route('ged.destroy', $document) }}" data-confirm="Supprimer ce document ?">@csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button></form>
        @endcan
    </x-slot:actions>

    <div class="row">
        <div class="col-lg-8">
            @if($document->apercuPossible())
                <div class="card mb-3">
                    @if(str_starts_with($document->mime, 'image/'))
                        <img src="{{ route('ged.apercu', $document) }}" alt="{{ $document->titre }}" class="img-fluid rounded">
                    @else
                        <iframe src="{{ route('ged.apercu', $document) }}" title="{{ $document->titre }}" class="w-100 rounded" style="height: 70vh; border: 0"></iframe>
                    @endif
                </div>
            @else
                <div class="card mb-3"><div class="card-body text-center py-5 text-muted">
                    <i class="ti {{ $document->icone() }}" style="font-size: 3rem"></i>
                    <p class="mt-2 mb-0">Aperçu indisponible pour ce format ({{ strtoupper($document->extension) }}) : téléchargez le fichier pour l'ouvrir.</p>
                </div></div>
            @endif
        </div>
        <div class="col-lg-4">
            <div class="card mb-3"><div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5">Catégorie</dt><dd class="col-7">{{ \App\Models\Referentiel::libelle('categorie_document', $document->categorie) }}</dd>
                    <dt class="col-5">Fichier</dt><dd class="col-7 text-break">{{ $document->nom_original }}</dd>
                    <dt class="col-5">Taille</dt><dd class="col-7">{{ $document->tailleLisible() }} · {{ $document->mime }}</dd>
                    <dt class="col-5">Rattaché à</dt><dd class="col-7">{{ \App\Http\Controllers\Ged\DocumentController::OBJETS[$document->documentable_type] ?? '—' }} {{ $document->documentable?->numero ?? $document->documentable?->nom_complet ?? '' }}</dd>
                    <dt class="col-5">Tiers</dt><dd class="col-7">{{ $document->tiers?->nom_complet ?? '—' }}</dd>
                    <dt class="col-5">Origine</dt><dd class="col-7">{{ $document->source === 'genere' ? 'Généré par l\'application' : 'Téléversé par ' . $document->auteur?->name }}</dd>
                    <dt class="col-5">Ajouté le</dt><dd class="col-7">{{ $document->created_at->format('d/m/Y H:i') }}</dd>
                    <dt class="col-5">Intégrité</dt><dd class="col-7">
                        @if($integre)<span class="text-success"><i class="ti ti-shield-check"></i> fichier intact</span>
                        @else<span class="text-danger"><i class="ti ti-shield-x"></i> fichier modifié ou absent</span>@endif</dd>
                    <dt class="col-5">SHA-256</dt><dd class="col-7"><code class="small text-break">{{ $document->empreinte_sha256 }}</code></dd>
                </dl>
                @if($document->description)<p class="mt-2 mb-0">{{ $document->description }}</p>@endif
            </div></div>

            @if($signature)
                <div class="card mb-3"><div class="card-body d-flex gap-3 align-items-center">
                    <img src="{{ $signature['image'] }}" alt="QR code de vérification" style="width: 96px">
                    <div class="small">Signature QR — code <strong>{{ $signature['code'] }}</strong><br>
                        {{ $document->jeton->revoque_at ? 'Révoquée : ' . $document->jeton->motif_revocation : 'Valide' }} · {{ $document->jeton->nb_verifications }} vérification(s)<br>
                        <a href="{{ $signature['url'] }}" target="_blank" rel="noopener">Page de vérification</a></div>
                </div></div>
            @endif

            <div class="card mb-3">
                <div class="card-header">Versions</div>
                <ul class="list-group list-group-flush small">
                    @foreach($versions as $v)
                        <li class="list-group-item {{ $v->id === $document->id ? 'active' : '' }}"><a href="{{ route('ged.show', $v) }}" class="{{ $v->id === $document->id ? 'text-white' : '' }}">
                            v{{ $v->version }} — {{ $v->created_at->format('d/m/Y H:i') }} · {{ $v->tailleLisible() }}</a></li>
                    @endforeach
                </ul>
                @can('ged-create')
                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('ged.version', $document) }}" enctype="multipart/form-data" class="d-flex gap-2">
                            @csrf
                            <input type="file" name="fichier" class="form-control form-control-sm" required aria-label="Nouvelle version">
                            <button class="btn btn-sm btn-outline-primary text-nowrap">Nouvelle version</button>
                        </form>
                    </div>
                @endcan
            </div>
        </div>
    </div>
</x-page>
@endsection
