@extends('pdf.layout')

@php($bail = $loyer->bail)

@section('titre', 'Quittance ' . $loyer->quittance_numero)
@section('reference', 'Quittance n° ' . $loyer->quittance_numero)

@section('contenu')
    <h1>Quittance de loyer</h1>

    <div class="cadre">
        Je soussigné(e), <strong>{{ $agence?->raison_sociale }}</strong>, agissant
        {{ $bail->bien->proprietaire ? 'pour le compte de ' . $bail->bien->proprietaire->nom_complet . ', propriétaire,' : 'en qualité de bailleur,' }}
        déclare avoir reçu de <strong>{{ $bail->locataire->nom_complet }}</strong>, locataire du bien
        <strong>{{ $bail->bien->designation }}</strong> (bail n° {{ $bail->numero }}), la somme de :
        <p style="font-size: 14px; text-align: center; margin: 10px 0"><strong>{{ montant($loyer->montant) }}</strong></p>
        au titre de : <strong>{{ $loyer->libelle }}</strong>
        @if($loyer->periode_debut) pour la période du {{ $loyer->periode_debut->format('d/m/Y') }} au {{ $loyer->periode_fin?->format('d/m/Y') }}@endif,
        et lui en donne quittance, sous réserve de tous droits.
    </div>

    <h2>Détail</h2>
    <table class="grille">
        <tbody>
            @if((float) $loyer->montant_loyer)<tr><td>Loyer</td><td class="droite">{{ montant($loyer->montant_loyer) }}</td></tr>@endif
            @if((float) $loyer->montant_charges)<tr><td>Charges</td><td class="droite">{{ montant($loyer->montant_charges) }}</td></tr>@endif
            <tr class="total"><td>Total réglé</td><td class="droite">{{ montant($loyer->montant_paye) }}</td></tr>
        </tbody>
    </table>

    <h2>Règlement(s)</h2>
    <table class="grille">
        <thead><tr><th>Reçu</th><th>Date</th><th>Mode</th><th class="droite">Montant</th></tr></thead>
        <tbody>
            @foreach($loyer->affectations as $affectation)
                @continue($affectation->encaissement->estAnnule())
                <tr>
                    <td>{{ $affectation->encaissement->numero }}</td>
                    <td>{{ $affectation->encaissement->date_encaissement->format('d/m/Y') }}</td>
                    <td>{{ \App\Models\Referentiel::libelle('mode_paiement', $affectation->encaissement->mode_paiement) }}</td>
                    <td class="droite">{{ montant($affectation->montant) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td><span class="tampon">ACQUITTÉ</span></td>
            <td class="droite">Fait à {{ $agence?->ville ?: '…………' }}, le {{ now()->format('d/m/Y') }}<br><strong>{{ parametre('general.signataire') }}</strong></td>
        </tr>
    </table>
    <p class="petit">Cette quittance annule tous les reçus qui auraient pu être donnés pour acompte sur la présente période. {{ parametre('general.mentions_recu') }}</p>
@endsection
