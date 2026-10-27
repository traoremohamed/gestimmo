@extends('vitrine.layout')

@section('titre', 'Contact — ' . $agenceCourante->nom)

@section('contenu')
<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-7">
            <h1 class="h3 mb-4">Contactez-nous</h1>
            <form method="POST" action="{{ route('vitrine.contact.envoyer') }}" class="row g-3">
                @csrf
                <input type="text" name="site_web" class="piege" tabindex="-1" autocomplete="off" aria-hidden="true">
                @if($bien)<input type="hidden" name="bien_reference" value="{{ $bien->reference }}">@endif
                <div class="col-md-6"><label class="form-label" for="nom">Nom et prénoms</label><input name="nom" id="nom" value="{{ old('nom') }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label" for="telephone">Téléphone</label><input name="telephone" id="telephone" value="{{ old('telephone') }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label" for="email">E-mail</label><input type="email" name="email" id="email" value="{{ old('email') }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label" for="sujet">Sujet</label><input name="sujet" id="sujet" value="{{ old('sujet', $bien ? 'Information sur le bien ' . $bien->reference : '') }}" class="form-control"></div>
                <div class="col-12"><label class="form-label" for="message">Message</label><textarea name="message" id="message" rows="5" class="form-control" required>{{ old('message') }}</textarea></div>
                <div class="col-12"><div class="form-check"><input type="checkbox" name="newsletter" value="1" id="newsletter" class="form-check-input"><label for="newsletter" class="form-check-label">Recevoir nos nouvelles offres</label></div></div>
                <div class="col-12"><button class="btn btn-marque"><i class="ti ti-send"></i> Envoyer</button></div>
            </form>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <h2 class="h5">{{ $agenceCourante->raison_sociale }}</h2>
                    @if($agenceCourante->adresse || $agenceCourante->ville)<p><i class="ti ti-map-pin"></i> {{ collect([$agenceCourante->adresse, $agenceCourante->ville])->filter()->implode(', ') }}</p>@endif
                    @if($agenceCourante->telephone)<p><i class="ti ti-phone"></i> {{ $agenceCourante->telephone }}</p>@endif
                    @if($agenceCourante->email)<p><i class="ti ti-mail"></i> {{ $agenceCourante->email }}</p>@endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
