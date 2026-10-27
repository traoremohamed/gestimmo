@extends('pdf.layout')

@php
    $e = $bulletin->employe;
    $groupes = ['GAINS' => 'Gains', 'NON_IMPOSABLES' => 'Éléments non imposables', 'RETENUES' => 'Cotisations et impôts', 'AUTRES' => 'Autres retenues'];
    $lignes = collect($bulletin->lignes);
@endphp

@section('titre', 'Bulletin ' . $bulletin->numero)
@section('reference', 'Bulletin n° ' . $bulletin->numero)

@section('contenu')
    <h1>Bulletin de paie — {{ $bulletin->periode->libelle }}</h1>

    <table style="width: 100%; margin-bottom: 10px">
        <tr>
            <td class="cadre" style="width: 50%; vertical-align: top">
                <strong>{{ $e->civilite }} {{ $e->nom_complet }}</strong><br>
                Matricule : {{ $e->matricule }} — CNPS : {{ $e->numero_cnps ?: '—' }}<br>
                Poste : {{ \App\Models\Referentiel::libelle('poste', $e->poste) ?? '—' }}<br>
                Embauche : {{ $e->date_embauche->format('d/m/Y') }} — {{ (float) $e->nombre_parts }} part(s)
            </td>
            <td class="cadre" style="vertical-align: top">
                Contrat : {{ \App\Models\Rh\Contrat::TYPES[$bulletin->contrat->type_contrat] }} {{ $bulletin->contrat->categorie ? '— cat. ' . $bulletin->contrat->categorie : '' }}<br>
                Période : du {{ $bulletin->periode->date_debut->format('d/m/Y') }} au {{ $bulletin->periode->date_fin->format('d/m/Y') }}<br>
                Jours rémunérés : {{ (float) $bulletin->jours_travailles }}<br>
                Paiement : {{ \App\Models\Referentiel::libelle('mode_paiement', $bulletin->contrat->mode_paiement) }}
            </td>
        </tr>
    </table>

    <table class="grille">
        <thead><tr><th>N°</th><th>Rubrique</th><th class="droite">Base</th><th class="droite">Taux / nombre</th><th class="droite">Gains</th><th class="droite">Retenues</th></tr></thead>
        <tbody>
            @foreach($groupes as $groupe => $titre)
                @php($g = $lignes->where('groupe', $groupe))
                @continue($g->isEmpty())
                <tr><td colspan="6" style="background: #fafafa"><strong>{{ $titre }}</strong></td></tr>
                @foreach($g as $l)
                    <tr>
                        <td>{{ $l['numero'] }}</td>
                        <td>{{ $l['libelle'] }}</td>
                        <td class="droite">{{ $l['base'] !== null ? montant($l['base'], false) : '' }}</td>
                        <td class="droite">{{ $l['taux'] !== null ? (float) $l['taux'] . ' %' : ($l['nombre'] ?? '') }}</td>
                        <td class="droite">{{ $l['type'] === 'GAIN' ? montant($l['montant'], false) : '' }}</td>
                        <td class="droite">{{ $l['type'] !== 'GAIN' ? montant($l['montant'], false) : '' }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr class="total"><td colspan="4" class="droite">Totaux</td><td class="droite">{{ montant($bulletin->brut, false) }}</td>
                <td class="droite">{{ montant((float) $bulletin->cotisations_salariales + (float) $bulletin->impots + (float) $bulletin->retenues, false) }}</td></tr>
        </tbody>
    </table>

    <table class="grille" style="margin-top: 10px">
        <tr><td>Brut imposable</td><td class="droite">{{ montant($bulletin->brut_imposable) }}</td><td>Charges patronales</td><td class="droite">{{ montant($bulletin->cotisations_patronales) }}</td></tr>
        <tr><td>Arrondi</td><td class="droite">{{ montant($bulletin->totaux['ARRONDI'] ?? 0) }}</td><td>Coût employeur</td><td class="droite">{{ montant($bulletin->cout_employeur) }}</td></tr>
        <tr class="total"><td colspan="3" class="droite" style="font-size: 12px">NET À PAYER</td><td class="droite" style="font-size: 12px">{{ montant($bulletin->net_a_payer) }}</td></tr>
    </table>

    <p class="petit" style="margin-top: 20px">Pour vous aider à faire valoir vos droits, conservez ce bulletin de paie sans limitation de durée.</p>
@endsection
