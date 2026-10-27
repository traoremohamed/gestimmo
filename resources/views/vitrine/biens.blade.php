@extends('vitrine.layout')

@section('titre', 'Nos biens — ' . $agenceCourante->nom)

@section('contenu')
<div class="container py-5">
    <h1 class="h3 mb-4">Nos biens</h1>
    <form class="row g-2 mb-4">
        <div class="col-md-3">
            <select name="destination" class="form-select" aria-label="Projet">
                <option value="">Tous les projets</option>
                @foreach(['vente' => 'À vendre', 'location' => 'À louer', 'meuble' => 'Meublé'] as $code => $libelle)
                    <option value="{{ $code }}" @selected(request('destination') === $code)>{{ $libelle }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="type_bien" class="form-select" aria-label="Type">
                <option value="">Tous types</option>
                @foreach($types as $code => $libelle)<option value="{{ $code }}" @selected(request('type_bien') === $code)>{{ $libelle }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="programme_id" class="form-select" aria-label="Programme">
                <option value="">Tous programmes</option>
                @foreach($programmes as $id => $nom)<option value="{{ $id }}" @selected((int) request('programme_id') === $id)>{{ $nom }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><input type="number" name="budget" value="{{ request('budget') }}" class="form-control" placeholder="Budget max." aria-label="Budget"></div>
        <div class="col-md-2"><button class="btn btn-marque w-100"><i class="ti ti-filter"></i> Filtrer</button></div>
    </form>

    <div class="row g-4">
        @forelse($biens as $bien)
            <div class="col-md-6 col-lg-4">@include('vitrine._carte')</div>
        @empty
            <div class="col-12 text-center text-muted py-5"><i class="ti ti-home-search fs-1 d-block mb-2"></i> Aucun bien ne correspond à votre recherche pour le moment.
                <div class="mt-3"><a href="{{ route('vitrine.contact') }}" class="btn btn-marque">Confiez-nous votre recherche</a></div></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $biens->links() }}</div>
</div>
@endsection
