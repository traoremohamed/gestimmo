@extends('pdf.layout')

@section('titre', 'Facture ' . $sejour->numero)
@section('reference', 'Facture n° ' . $sejour->numero)

@section('contenu')
    <h1>Facture de séjour</h1>
    <p>Client : <strong>{{ $sejour->client->nom_complet }}</strong> — {{ $sejour->client->contact }}<br>
        Logement : {{ $sejour->bien->designation }}<br>
        Séjour du {{ $sejour->date_arrivee->format('d/m/Y') }} au {{ $sejour->date_depart->format('d/m/Y') }} — {{ $sejour->nb_personnes }} personne(s)</p>

    <table class="grille">
        <thead><tr><th>Désignation</th><th class="droite">Quantité</th><th class="droite">Prix unitaire</th><th class="droite">Total</th></tr></thead>
        <tbody>
            <tr><td>Nuitées</td><td class="droite">{{ $sejour->nb_nuits }}</td><td class="droite">{{ montant($sejour->prix_nuitee) }}</td><td class="droite">{{ montant((float) $sejour->prix_nuitee * $sejour->nb_nuits) }}</td></tr>
            @if((float) $sejour->frais_supplementaires)<tr><td>Frais supplémentaires</td><td></td><td></td><td class="droite">{{ montant($sejour->frais_supplementaires) }}</td></tr>@endif
            @if((float) $sejour->remise)<tr><td>Remise</td><td></td><td></td><td class="droite">- {{ montant($sejour->remise) }}</td></tr>@endif
            <tr class="total"><td colspan="3" class="droite">Total</td><td class="droite">{{ montant($sejour->montant) }}</td></tr>
            <tr><td colspan="3" class="droite">Déjà réglé</td><td class="droite">{{ montant($sejour->montant_paye) }}</td></tr>
            <tr class="total"><td colspan="3" class="droite">Reste à payer</td><td class="droite">{{ montant($sejour->resteAPayer()) }}</td></tr>
        </tbody>
    </table>
    @if($sejour->estSolde())<p class="centre" style="margin-top: 20px"><span class="tampon">PAYÉE</span></p>@endif
@endsection
