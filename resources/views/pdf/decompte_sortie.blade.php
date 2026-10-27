@extends('pdf.layout')

@php
    use App\Models\DecompteSortie;
    use App\Models\EtatLieux;
    $bail = $decompte->bail;
    $piecesEntree = collect($entree?->pieces ?? [])->keyBy('piece');
@endphp

@section('titre', 'Décompte de sortie ' . $decompte->numero)
@section('reference', 'Décompte ' . ($decompte->numero ?? 'provisoire'))

@section('contenu')
    <h1>Décompte de sortie du locataire</h1>
    @if($decompte->estModifiable())<p class="centre"><span class="tampon annule">PROVISOIRE — NON VALIDÉ</span></p>@endif

    <table class="grille">
        <tr><th style="width: 25%">Locataire</th><td>{{ $decompte->locataire->nom_complet }}</td><th style="width: 18%">Bail</th><td>{{ $bail->numero }}</td></tr>
        <tr><th>Bien</th><td>{{ $bail->bien->designation }}</td><th>Propriétaire</th><td>{{ $bail->bien->proprietaire?->nom_complet ?? 'Agence' }}</td></tr>
        <tr><th>Entrée</th><td>{{ $bail->date_debut->format('d/m/Y') }}</td><th>Sortie</th><td>{{ $decompte->date_sortie->format('d/m/Y') }}</td></tr>
    </table>

    <h2>Liquidation des garanties</h2>
    <table class="grille">
        <thead><tr><th>Désignation</th><th class="droite">Dû au locataire</th><th class="droite">Dû par le locataire</th><th>Justificatif</th></tr></thead>
        <tbody>
        @foreach($decompte->lignes->sortBy(fn ($l) => [$l->sens === 'credit' ? 0 : 1, $l->ordre]) as $l)
            <tr>
                <td>{{ $l->libelle }}</td>
                <td class="droite">{{ $l->sens === 'credit' ? \App\Support\Montant::formater($l->montant) : '' }}</td>
                <td class="droite">{{ $l->sens === 'debit' ? \App\Support\Montant::formater($l->montant) : '' }}</td>
                <td class="petit">{{ $l->justificatifs->pluck('numero')->implode(', ') }}</td>
            </tr>
        @endforeach
        <tr class="total"><td>Totaux</td><td class="droite">{{ \App\Support\Montant::formater($decompte->total_credits) }}</td><td class="droite">{{ \App\Support\Montant::formater($decompte->total_retenues) }}</td><td></td></tr>
        <tr class="total">
            <td>{{ (float) $decompte->reste_du > 0 ? 'RESTE DÛ PAR LE LOCATAIRE' : 'SOLDE À RESTITUER AU LOCATAIRE' }}</td>
            <td colspan="2" class="droite">{{ \App\Support\Montant::formater((float) $decompte->reste_du > 0 ? $decompte->reste_du : $decompte->montant_a_rembourser) }}</td><td></td>
        </tr>
        </tbody>
    </table>
    @if($decompte->rembourse_le)<p>Restitué le {{ $decompte->rembourse_le->format('d/m/Y') }}.</p>@endif

    @if($entree || $sortie)
        <h2>États des lieux</h2>
        <table class="grille">
            <thead><tr><th>Pièce</th><th>Entrée {{ $entree?->date?->format('d/m/Y') }}</th><th>Sortie {{ $sortie?->date?->format('d/m/Y') }}</th></tr></thead>
            <tbody>
            @foreach(collect($sortie?->pieces ?? $entree?->pieces ?? []) as $p)
                @php($e = $piecesEntree->get($p['piece']))
                <tr><td>{{ $p['piece'] }}</td><td>{{ EtatLieux::ETATS[$e['etat'] ?? ''] ?? '—' }} {{ $e['commentaire'] ?? '' }}</td>
                    <td>{{ $sortie ? (EtatLieux::ETATS[$p['etat'] ?? ''] ?? '—') . ' ' . ($p['commentaire'] ?? '') : '—' }}</td></tr>
            @endforeach
            <tr><td>Index CIE / SODECI</td><td>{{ $entree?->compteurs['cie_index'] ?? '—' }} / {{ $entree?->compteurs['sodeci_index'] ?? '—' }}</td>
                <td>{{ $sortie?->compteurs['cie_index'] ?? '—' }} / {{ $sortie?->compteurs['sodeci_index'] ?? '—' }}</td></tr>
            </tbody>
        </table>
    @endif

    <table class="signatures">
        <tr><td>Le locataire<br><span class="petit">« Lu et approuvé »</span></td><td class="droite">Pour l'agence<br>{{ parametre('general.signataire') }}</td></tr>
    </table>
@endsection
