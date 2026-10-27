@extends('layouts.backLayout.designadmin')

@section('content')
<x-page titre="Demande de contact" module="Tiers & CRM" :retour="route('demandescontact.index')">
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">Message reçu le {{ $element->created_at->format('d/m/Y à H:i') }}</div>
                <div class="card-body">
                    <dl class="fiche">
                        <dt>Nom</dt><dd>{{ $element->nom }}</dd>
                        <dt>Coordonnées</dt><dd>{{ collect([$element->telephone, $element->email])->filter()->implode(' · ') }}</dd>
                        @if($element->bien)<dt>Bien</dt><dd><a href="{{ route('biens.show', $element->bien) }}">{{ $element->bien->designation }}</a></dd>@endif
                        <dt>Sujet</dt><dd>{{ $element->sujet ?: '—' }}</dd>
                        <dt>Message</dt><dd style="white-space: pre-line">{{ $element->message }}</dd>
                        <dt>Newsletter</dt><dd>{{ $element->newsletter ? 'Oui' : 'Non' }}</dd>
                    </dl>
                    @can('tiers-create')
                        <a href="{{ route('tiers.create', ['role' => 'prospect']) }}" class="btn btn-sm btn-outline-primary"><i class="ti ti-user-plus"></i> Créer la fiche prospect</a>
                    @endcan
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <form method="POST" action="{{ route('demandescontact.update', $element) }}" class="card">
                @csrf @method('PUT')
                <div class="card-header">Traitement</div>
                <div class="card-body row">
                    @foreach($champs as $champ)
                        @include('crud.champ', ['champ' => $champ, 'element' => $element])
                    @endforeach
                    @if($element->traitePar)
                        <p class="small text-muted mb-0">Traitée par {{ $element->traitePar->nom_complet }} le {{ $element->traite_le?->format('d/m/Y H:i') }}</p>
                    @endif
                </div>
                <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer</button></div>
            </form>
        </div>
    </div>
</x-page>
@endsection
