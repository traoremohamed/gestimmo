@props(['url', 'methode' => 'POST', 'libelle' => null, 'icone' => null, 'classe' => 'btn btn-sm btn-outline-primary', 'confirmer' => null, 'titre' => null])

{{-- Bouton d'action en écriture (jamais un lien GET qui modifie des données) --}}
<form method="POST" action="{{ $url }}" class="d-inline" @if($confirmer) data-confirm="{{ $confirmer }}" @endif>
    @csrf
    @if(strtoupper($methode) !== 'POST')
        @method($methode)
    @endif
    {{ $slot }}
    <button type="submit" class="{{ $classe }}" @if($titre) title="{{ $titre }}" @endif>
        @if($icone)<i class="ti {{ $icone }}"></i>@endif {{ $libelle }}
    </button>
</form>
