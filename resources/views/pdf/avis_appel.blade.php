@extends('pdf.layout')

@section('titre', 'Avis ' . $appel->numero)
@section('reference', 'Appel de fonds n° ' . $appel->numero)

@section('contenu')
    <h1>Avis d'appel de fonds</h1>

    <table style="width: 100%"><tr>
        <td style="width: 50%; vertical-align: top">
            <strong>Syndicat des copropriétaires</strong><br>{{ $appel->copropriete->nom }}<br>{{ $appel->copropriete->adresse }}
        </td>
        <td class="cadre" style="vertical-align: top">
            <strong>{{ $ligne->coproprietaire?->nom_complet ?? 'Copropriétaire du lot ' . $ligne->lot->numero }}</strong><br>
            {{ $ligne->coproprietaire?->adresse }}<br>{{ $ligne->coproprietaire?->contact }}
        </td>
    </tr></table>

    <h2>{{ $appel->libelle }}</h2>
    <table class="grille">
        <thead><tr><th>Lot</th><th class="droite">Tantièmes du lot</th><th class="droite">Montant total appelé</th><th class="droite">Votre quote-part</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ $ligne->lot->numero }} {{ $ligne->lot->designation }}</td>
                <td class="droite">{{ (float) $ligne->tantiemes }}</td>
                <td class="droite">{{ montant($appel->montant_total) }}</td>
                <td class="droite"><strong>{{ montant($ligne->montant) }}</strong></td>
            </tr>
            @if((float) $ligne->montant_paye)
                <tr><td colspan="3" class="droite">Déjà réglé</td><td class="droite">- {{ montant($ligne->montant_paye) }}</td></tr>
            @endif
            <tr class="total"><td colspan="3" class="droite">Reste à payer</td><td class="droite">{{ montant($ligne->resteAPayer()) }}</td></tr>
        </tbody>
    </table>

    <p>Merci de régler cette somme <strong>avant le {{ $appel->date_echeance->format('d/m/Y') }}</strong>
        @if($appel->copropriete->rib) par virement au compte du syndicat : {{ $appel->copropriete->banque }} {{ $appel->copropriete->rib }},@endif
        ou auprès de la caisse de {{ $agence?->raison_sociale }}.</p>

    <table class="signatures"><tr><td></td><td class="droite"><strong>Le syndic</strong><br>{{ $agence?->raison_sociale }}</td></tr></table>
@endsection
