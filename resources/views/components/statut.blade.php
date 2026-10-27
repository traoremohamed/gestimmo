@props(['valeur'])

{{-- Badge d'un statut (enum App\Enums\* avec libelle() / couleur()) --}}
@if($valeur instanceof \BackedEnum && method_exists($valeur, 'libelle'))
    <span {{ $attributes->class(['badge', 'bg-' . $valeur->couleur(), 'text-dark' => in_array($valeur->couleur(), ['warning', 'light', 'info'])]) }}>{{ $valeur->libelle() }}</span>
@elseif($valeur !== null && $valeur !== '')
    <span {{ $attributes->class(['badge bg-secondary']) }}>{{ $valeur }}</span>
@endif
