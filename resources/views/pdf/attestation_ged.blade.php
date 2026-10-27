@extends('pdf.layout')

@section('titre', 'Attestation d\'archivage ' . $document->numero)
@section('reference', 'Document ' . $document->numero)

@section('contenu')
    <h1>Attestation d'archivage électronique</h1>

    <p>{{ $agence?->raison_sociale }} atteste que le document décrit ci-dessous est conservé dans sa gestion électronique de documents
        depuis le {{ $document->created_at->format('d/m/Y à H:i') }}. Son empreinte numérique garantit que tout fichier présenté
        comme étant ce document peut être contrôlé : la page de vérification compare l'empreinte du fichier à celle de l'original.</p>

    <table class="grille">
        <tr><th style="width: 32%">Numéro d'archivage</th><td>{{ $document->numero }} (version {{ $document->version }})</td></tr>
        <tr><th>Titre</th><td>{{ $document->titre }}</td></tr>
        <tr><th>Catégorie</th><td>{{ \App\Models\Referentiel::libelle('categorie_document', $document->categorie) }}</td></tr>
        <tr><th>Fichier</th><td>{{ $document->nom_original }} — {{ $document->tailleLisible() }}</td></tr>
        @if($document->tiers)<tr><th>Concerne</th><td>{{ $document->tiers->nom_complet }}</td></tr>@endif
        <tr><th>Empreinte SHA-256</th><td style="font-family: monospace; font-size: 8.5px">{{ $document->empreinte_sha256 }}</td></tr>
        <tr><th>Origine</th><td>{{ $document->source === 'genere' ? 'Généré par l\'application' : 'Téléversé par ' . $document->auteur?->name }}</td></tr>
    </table>
@endsection
