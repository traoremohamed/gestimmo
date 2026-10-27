@extends('vitrine.layout')

{{-- Page composée de blocs (config/site.php), dans l'ordre choisi par l'agence --}}
@section('titre', $page->seo_titre ?: ($page->est_accueil ? ($siteVitrine->titre ?: $agenceCourante->nom) . ($siteVitrine->slogan ? ' — ' . $siteVitrine->slogan : '') : $page->titre . ' — ' . ($siteVitrine->titre ?: $agenceCourante->nom)))
@section('description', $page->seo_description ?: ($siteVitrine->description ?: $siteVitrine->slogan))

@section('contenu')
    @if (!$page->est_accueil && $sections->doesntContain(fn ($s) => in_array($s->type, ['SLIDER', 'HERO'], true)))
        <div class="py-5 text-center" style="background: rgba(var(--s-p-rgb), .08)"><div class="container"><h1 class="s-titre display-5 mb-0">{{ $page->titre }}</h1></div></div>
    @endif
    @foreach ($sections as $s)
        @includeIf('vitrine.sections.' . strtolower($s->type), ['s' => $s, 'o' => $s->options ?? [], 'el' => $s->elements ?? [], 'd' => $donnees[$s->id] ?? null])
    @endforeach
@endsection
