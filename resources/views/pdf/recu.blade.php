@extends('pdf.layout')

@section('titre', 'Reçu ' . $encaissement->numero)
@section('reference', 'Reçu n° ' . $encaissement->numero)

@section('contenu')
    <h1>Reçu de paiement</h1>

    @if($encaissement->estAnnule())
        <p class="centre"><span class="tampon annule">ANNULÉ le {{ $encaissement->annule_le?->format('d/m/Y') }}</span></p>
    @endif

    <div class="cadre">
        Reçu de <strong>{{ $encaissement->tiers?->nom_complet }}</strong> la somme de
        <strong style="font-size: 13px">{{ montant($encaissement->montant) }}</strong>
        le {{ $encaissement->date_encaissement->format('d/m/Y') }}, par {{ \App\Models\Referentiel::libelle('mode_paiement', $encaissement->mode_paiement) }}
        {{ $encaissement->reference ? '(réf. ' . $encaissement->reference . ($encaissement->banque ? ' — ' . $encaissement->banque : '') . ')' : '' }}.
    </div>

    <h2>Affectation</h2>
    <table class="grille">
        <thead><tr><th>Objet du règlement</th><th class="droite">Montant</th><th class="droite">Reste dû</th></tr></thead>
        <tbody>
            @foreach($encaissement->affectations as $affectation)
                <tr>
                    <td>{{ $affectation->affectable?->libelleAffectation() }}</td>
                    <td class="droite">{{ montant($affectation->montant) }}</td>
                    <td class="droite">{{ $affectation->affectable ? montant($affectation->affectable->resteAPayer()) : '' }}</td>
                </tr>
            @endforeach
            <tr class="total"><td>Total</td><td class="droite">{{ montant($encaissement->montant) }}</td><td></td></tr>
        </tbody>
    </table>

    @if($encaissement->observation)<p>{{ $encaissement->observation }}</p>@endif

    <table class="signatures">
        <tr>
            <td><strong>Le client</strong></td>
            <td class="droite"><strong>Le caissier</strong><br>{{ $encaissement->caissier?->nom_complet }}</td>
        </tr>
    </table>
    <p class="petit">{{ parametre('general.mentions_recu') }}</p>
@endsection
