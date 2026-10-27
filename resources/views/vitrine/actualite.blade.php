@extends('vitrine.layout')

@section('titre', $publication->titre . ' — ' . $agenceCourante->nom)
@section('description', $publication->resume)

@section('contenu')
<article class="container py-5" style="max-width: 820px">
    <nav class="small mb-3"><a href="{{ route('vitrine.actualites') }}"><i class="ti ti-arrow-left"></i> Actualités</a></nav>
    <div class="small text-muted">{{ ($publication->publie_le ?? $publication->created_at)->locale('fr')->translatedFormat('d F Y') }}</div>
    <h1 class="h2 mb-3">{{ $publication->titre }}</h1>
    @if($publication->image)<img src="{{ asset('storage/' . $publication->image) }}" alt="" class="img-fluid rounded mb-4">@endif
    @if($publication->resume)<p class="lead">{{ $publication->resume }}</p>@endif
    <div style="white-space: pre-line">{{ $publication->contenu }}</div>
    @if($publication->video_url)<p class="mt-4"><a href="{{ $publication->video_url }}" target="_blank" rel="noopener"><i class="ti ti-player-play"></i> Voir la vidéo</a></p>@endif
</article>
@endsection
