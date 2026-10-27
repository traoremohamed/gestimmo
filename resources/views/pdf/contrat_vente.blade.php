@extends('pdf.layout')

@php($acquereur = $vente->acquereur)

@section('titre', 'Contrat ' . $vente->numero)
@section('reference', 'Contrat n° ' . $vente->numero)

@section('contenu')
    <h1>Contrat de réservation et de vente</h1>

    <h2>Entre les soussignés</h2>
    <p>
        <strong>{{ $agence?->raison_sociale }}</strong>{{ $agence?->rccm ? ', RCCM ' . $agence->rccm : '' }}, ci-après « le Vendeur »,<br>
        et <strong>{{ $acquereur->civilite }} {{ $acquereur->nom_complet }}</strong>
        @if($acquereur->date_naissance), né(e) le {{ $acquereur->date_naissance->format('d/m/Y') }}{{ $acquereur->lieu_naissance ? ' à ' . $acquereur->lieu_naissance : '' }}@endif
        @if($acquereur->nationalite), de nationalité {{ $acquereur->nationalite->nationalite }}@endif
        @if($acquereur->profession), {{ $acquereur->profession }}@endif,
        demeurant {{ $acquereur->adresse ?: '—' }}, joignable au {{ $acquereur->contact ?: '—' }}, ci-après « l'Acquéreur ».
    </p>

    <h2>Désignation du ou des biens</h2>
    <table class="grille">
        <thead><tr><th>Référence</th><th>Désignation</th><th>Programme / lotissement</th><th class="droite">Superficie</th><th class="droite">Prix</th></tr></thead>
        <tbody>
            @foreach($vente->lignes as $ligne)
                <tr>
                    <td>{{ $ligne->bien->reference }}</td>
                    <td>{{ $ligne->bien->libelle }} {{ $ligne->bien->ilot ? '— Îlot ' . $ligne->bien->ilot : '' }} {{ $ligne->bien->numero_lot ? 'Lot ' . $ligne->bien->numero_lot : '' }}</td>
                    <td>{{ $ligne->bien->programme?->nom }} {{ $ligne->bien->lotissement ? '/ ' . $ligne->bien->lotissement->nom : '' }}</td>
                    <td class="droite">{{ $ligne->bien->superficie ? number_format((float) $ligne->bien->superficie, 0, ',', ' ') . ' m²' : '' }}</td>
                    <td class="droite">{{ montant($ligne->prix) }}</td>
                </tr>
            @endforeach
            @if((float) $vente->remise)
                <tr><td colspan="4" class="droite">Remise</td><td class="droite">- {{ montant($vente->remise) }}</td></tr>
            @endif
            <tr class="total"><td colspan="4" class="droite">Prix net</td><td class="droite">{{ montant($vente->montant_net) }}</td></tr>
        </tbody>
    </table>

    @if($vente->frais->where('statut.value', '!=', 'annule')->isNotEmpty())
        <h2>Frais annexes à la charge de l'Acquéreur</h2>
        <table class="grille">
            <tbody>
                @foreach($vente->frais as $frais)
                    <tr><td>{{ $frais->libelle }}</td><td class="droite" style="width: 30%">{{ montant($frais->montant) }}</td></tr>
                @endforeach
                <tr class="total"><td class="droite">Total des frais</td><td class="droite">{{ montant($vente->montant_frais) }}</td></tr>
            </tbody>
        </table>
    @endif

    <h2>Modalités de paiement — {{ \App\Models\Vente::MODES_FINANCEMENT[$vente->mode_financement] }}</h2>
    <table class="grille">
        <thead><tr><th>N°</th><th>Libellé</th><th>Date d'échéance</th><th class="droite">Montant</th></tr></thead>
        <tbody>
            @foreach($vente->echeances->sortBy('numero') as $echeance)
                <tr><td>{{ $echeance->numero }}</td><td>{{ $echeance->libelle }}</td><td>{{ $echeance->date_echeance->format('d/m/Y') }}</td><td class="droite">{{ montant($echeance->montant) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Conditions</h2>
    <p>
        L'Acquéreur s'engage à respecter l'échéancier ci-dessus. En cas de désistement, une retenue de
        {{ parametre('vente.taux_retenue_desistement') }} % des sommes versées au titre du prix sera appliquée ; les frais annexes engagés
        ne sont pas remboursables. Le transfert de propriété intervient après paiement intégral du prix et des frais.
        @if($vente->observation)<br>{{ $vente->observation }}@endif
    </p>

    <p>Fait à {{ $agence?->ville ?: '…………' }}, le {{ $vente->date_contrat?->format('d/m/Y') }}, en deux exemplaires originaux.</p>

    <table class="signatures">
        <tr>
            <td><strong>L'Acquéreur</strong><br><span class="petit">(lu et approuvé)</span></td>
            <td class="droite"><strong>Pour le Vendeur</strong><br>{{ parametre('general.signataire') }}</td>
        </tr>
    </table>
@endsection
