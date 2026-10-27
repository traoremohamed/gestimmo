@extends('pdf.layout')

@section('titre', $titre)
@section('reference', $sousTitre ?? '')

@section('contenu')
    <h1>{{ $titre }}</h1>
    @if($sousTitre)<p class="centre petit">{{ $sousTitre }}</p>@endif

    <table class="grille">
        <thead>
            <tr>@foreach($colonnes as $c)<th>{{ $c }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse($lignes as $ligne)
                <tr>
                    @foreach(array_values($ligne) as $v)
                        <td class="{{ is_float($v) || is_int($v) ? 'droite' : '' }}">{{ \App\Support\Export::cellule($v) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($colonnes) }}" class="centre">Aucune donnée.</td></tr>
            @endforelse
            @if($total)
                <tr class="total">
                    @foreach(array_values($total) as $v)
                        <td class="{{ is_float($v) || is_int($v) ? 'droite' : '' }}">{{ \App\Support\Export::cellule($v) }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
@endsection
