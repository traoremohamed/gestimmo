@extends('pdf.layout')

@php($tiers = $dossier->tiers)

@section('titre', $relance->objet)
@section('reference', 'Relance n° ' . $relance->id . ' — niveau ' . $relance->niveau)

@section('contenu')
    <table style="width: 100%; margin-bottom: 20px">
        <tr>
            <td style="width: 55%"></td>
            <td class="cadre">
                <strong>{{ $tiers->civilite }} {{ $tiers->nom_complet }}</strong><br>
                {{ $tiers->adresse }}<br>{{ collect([$tiers->boite_postale, $tiers->ville])->filter()->implode(' ') }}<br>{{ $tiers->telephone }}
            </td>
        </tr>
    </table>

    <p>{{ $agence?->ville ?: '' }}, le {{ $relance->created_at->format('d/m/Y') }}</p>
    <p><strong>Objet : {{ $relance->objet }}</strong>
        @if($relance->niveau >= 4)<br><strong>Lettre recommandée avec accusé de réception</strong>@endif</p>

    <div style="white-space: pre-line; line-height: 1.5">{{ $relance->message }}</div>

    <table class="signatures"><tr><td></td><td class="droite"><strong>{{ parametre('general.signataire') }}</strong><br>{{ $agence?->raison_sociale }}</td></tr></table>
@endsection
