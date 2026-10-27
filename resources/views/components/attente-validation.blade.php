@props(['objet'])

{{-- Bandeau : une demande de validation est en attente sur ce dossier --}}
@if($objet?->exists && ($demandeEnAttente = \App\Services\Validation\ValidationService::enAttente($objet)))
    <div class="alert alert-warning d-flex align-items-center gap-2" role="status">
        <i class="ti ti-hourglass fs-4"></i>
        <div class="flex-grow-1">
            <strong>{{ \App\Services\Validation\Catalogue::libelle($demandeEnAttente->action) }}</strong> en attente de validation
            — demande {{ $demandeEnAttente->numero }}, étape « {{ $demandeEnAttente->circuit?->etapes()->where('ordre', $demandeEnAttente->etape_courante)->value('libelle') }} ».
        </div>
        @can('validations-index')<a href="{{ route('validations.show', $demandeEnAttente) }}" class="btn btn-sm btn-warning">Voir la demande</a>@endcan
    </div>
@endif
