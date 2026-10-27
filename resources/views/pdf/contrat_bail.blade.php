@extends('pdf.layout')

@php($locataire = $bail->locataire)

@section('titre', 'Bail ' . $bail->numero)
@section('reference', 'Bail n° ' . $bail->numero)

@section('contenu')
    <h1>Contrat de bail {{ \App\Models\Bail::TYPES[$bail->type_bail] === 'Habitation' ? 'à usage d\'habitation' : \App\Models\Bail::TYPES[$bail->type_bail] }}</h1>

    <h2>Les parties</h2>
    <p>
        <strong>Le bailleur</strong> : {{ $bail->bien->proprietaire?->nom_complet ?? $agence?->raison_sociale }},
        représenté par <strong>{{ $agence?->raison_sociale }}</strong>{{ $bail->bien->proprietaire ? ', mandataire' : '' }}.<br>
        <strong>Le locataire</strong> : {{ $locataire->civilite }} {{ $locataire->nom_complet }}
        @if($locataire->date_naissance), né(e) le {{ $locataire->date_naissance->format('d/m/Y') }}@endif
        @if($locataire->nationalite), de nationalité {{ $locataire->nationalite->nationalite }}@endif
        @if($locataire->profession), {{ $locataire->profession }}@endif, joignable au {{ $locataire->contact ?: '—' }}.
        @if($bail->garant)<br><strong>Le garant</strong> : {{ $bail->garant->nom_complet }}, caution solidaire du locataire.@endif
    </p>

    <h2>Désignation du bien</h2>
    <p>{{ $bail->bien->designation }}{{ $bail->bien->immeuble ? ', immeuble ' . $bail->bien->immeuble->nom : '' }}
        {{ $bail->bien->superficie ? '— ' . number_format((float) $bail->bien->superficie, 0, ',', ' ') . ' m²' : '' }}
        {{ $bail->bien->nb_pieces ? '— ' . $bail->bien->nb_pieces . ' pièce(s)' : '' }}.</p>

    <h2>Durée</h2>
    <p>Le bail prend effet le <strong>{{ $bail->date_debut->format('d/m/Y') }}</strong> pour une durée de {{ $bail->duree_mois }} mois
        {{ $bail->tacite_reconduction ? ', renouvelable par tacite reconduction' : ', jusqu\'au ' . $bail->date_fin?->format('d/m/Y') }}.</p>

    <h2>Conditions financières</h2>
    <table class="grille">
        <tbody>
            <tr><td>Loyer mensuel</td><td class="droite">{{ montant($bail->loyer_mensuel) }}</td></tr>
            <tr><td>Charges mensuelles</td><td class="droite">{{ montant($bail->charges_mensuelles) }}</td></tr>
            <tr><td>Périodicité / échéance</td><td class="droite">{{ $bail->periodicite_mois }} mois, payable le {{ $bail->jour_echeance }}</td></tr>
            <tr><td>Dépôt de garantie ({{ $bail->mois_caution }} mois)</td><td class="droite">{{ montant($bail->depot_garantie) }}</td></tr>
            <tr><td>Avance sur loyer</td><td class="droite">{{ $bail->mois_avance }} mois</td></tr>
            <tr><td>Frais d'agence</td><td class="droite">{{ montant($bail->frais_agence) }}</td></tr>
            @if((float) $bail->taux_penalite)<tr><td>Pénalité de retard</td><td class="droite">{{ $bail->taux_penalite }} % du loyer après {{ parametre('location.delai_grace_jours') }} jours</td></tr>@endif
            @if((float) $bail->taux_revision)<tr><td>Révision annuelle</td><td class="droite">{{ $bail->taux_revision }} %</td></tr>@endif
        </tbody>
    </table>

    <h2>Obligations</h2>
    <p>Le locataire s'engage à payer le loyer et les charges aux échéances convenues, à user paisiblement des lieux, à les entretenir
        et à les restituer en bon état. Le dépôt de garantie sera restitué en fin de bail, déduction faite des sommes dues et des réparations locatives.
        @if($bail->observation)<br>{{ $bail->observation }}@endif</p>

    <p>Fait à {{ $agence?->ville ?: '…………' }}, le {{ $bail->date_debut->format('d/m/Y') }}, en deux exemplaires.</p>
    <table class="signatures">
        <tr>
            <td><strong>Le locataire</strong><br><span class="petit">(lu et approuvé)</span></td>
            <td class="droite"><strong>Pour le bailleur</strong><br>{{ parametre('general.signataire') }}</td>
        </tr>
    </table>
@endsection
