{{-- Formulaire de recherche de biens (catalogue public). Variables : $d (types, programmes), $o (options) --}}
<form method="GET" action="{{ route('vitrine.biens') }}" class="s-recherche row g-2 align-items-end text-start text-body">
    <div class="col-md"><label class="form-label small fw-semibold" for="dest-{{ $s->id }}">Je souhaite</label>
        <select name="destination" id="dest-{{ $s->id }}" class="form-select"><option value="">Acheter ou louer</option><option value="vente">Acheter</option><option value="location">Louer</option><option value="meuble">Séjour meublé</option></select></div>
    <div class="col-md"><label class="form-label small fw-semibold" for="type-{{ $s->id }}">Type de bien</label>
        <select name="type_bien" id="type-{{ $s->id }}" class="form-select"><option value="">Tous les types</option>@foreach ($d['types'] ?? [] as $code => $libelle)<option value="{{ $code }}">{{ $libelle }}</option>@endforeach</select></div>
    @if (!empty($o['programme']) && count($d['programmes'] ?? []))
        <div class="col-md"><label class="form-label small fw-semibold" for="prog-{{ $s->id }}">Programme</label>
            <select name="programme_id" id="prog-{{ $s->id }}" class="form-select"><option value="">Tous</option>@foreach ($d['programmes'] as $id => $nom)<option value="{{ $id }}">{{ $nom }}</option>@endforeach</select></div>
    @endif
    @if (!empty($o['budget']))
        <div class="col-md"><label class="form-label small fw-semibold" for="budget-{{ $s->id }}">Budget maximum</label><input type="number" min="0" step="1000" name="budget" id="budget-{{ $s->id }}" class="form-control" placeholder="FCFA"></div>
    @endif
    <div class="col-md-auto"><button class="btn btn-s w-100 px-4"><i class="ti ti-search"></i> Rechercher</button></div>
</form>
