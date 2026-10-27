@extends('pdf.layout')

@section('titre', 'Relevé ' . $reversement->numero)
@section('reference', 'Relevé de gérance n° ' . $reversement->numero)

@section('contenu')
    <h1>Relevé de gérance</h1>
    <p>
        Propriétaire : <strong>{{ $reversement->proprietaire->nom_complet }}</strong><br>
        Période : du {{ $reversement->periode_debut->format('d/m/Y') }} au {{ $reversement->periode_fin->format('d/m/Y') }}
        @if($reversement->proprietaire->rib)<br>Compte : {{ $reversement->proprietaire->banque }} {{ $reversement->proprietaire->rib }}@endif
    </p>

    <table class="grille">
        <thead><tr><th>Libellé</th><th class="droite">Encaissé</th><th class="droite">Commission</th></tr></thead>
        <tbody>
            @foreach($reversement->lignes as $ligne)
                <tr><td>{{ $ligne->libelle }}</td><td class="droite">{{ montant($ligne->montant_encaisse) }}</td><td class="droite">{{ montant($ligne->montant_commission) }} ({{ $ligne->taux_commission }} %)</td></tr>
            @endforeach
            <tr class="total"><td>Total</td><td class="droite">{{ montant($reversement->montant_encaisse) }}</td><td class="droite">{{ montant($reversement->montant_commission) }}</td></tr>
        </tbody>
    </table>

    @if($reversement->depenses->isNotEmpty())
        <h2>Dépenses engagées pour votre compte</h2>
        <table class="grille">
            @foreach($reversement->depenses as $depense)
                <tr><td>{{ $depense->date_depense->format('d/m/Y') }} — {{ $depense->libelle }}</td><td class="droite" style="width: 30%">{{ montant($depense->montant) }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Récapitulatif</h2>
    <table class="grille">
        <tr><td>Loyers encaissés</td><td class="droite">{{ montant($reversement->montant_encaisse) }}</td></tr>
        <tr><td>Commission de gestion</td><td class="droite">- {{ montant($reversement->montant_commission) }}</td></tr>
        <tr><td>Dépenses</td><td class="droite">- {{ montant($reversement->montant_deductions) }}</td></tr>
        <tr class="total"><td>Net reversé</td><td class="droite">{{ montant($reversement->montant_net) }}</td></tr>
    </table>
    @if($reversement->paye_le)
        <p>Réglé le {{ $reversement->paye_le->format('d/m/Y') }} par {{ \App\Models\Referentiel::libelle('mode_paiement', $reversement->mode_paiement) }} {{ $reversement->reference_paiement }}.</p>
    @endif
@endsection
