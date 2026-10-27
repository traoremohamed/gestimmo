@extends('layouts.backLayout.designadmin')

@php
    $champs = $def['champs'] ?? [];
    $elements = $section->elements ?? [];
    $url = fn ($c) => \App\Services\Site\SiteService::url($c);
@endphp

@section('content')
<x-page :titre="'Bloc : ' . ($def['libelle'] ?? $section->type)" module="Site web" :retour="route('siteweb.pages.edit', $section->page)">
    <form method="POST" action="{{ route('siteweb.sections.update', $section) }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card mb-3">
                    <div class="card-header">Contenu</div>
                    <div class="card-body row">
                        @if (in_array('titre', $champs, true) || $section->type === 'SLIDER')<x-form.input name="titre" label="Titre" :value="$section->titre" col="col-md-6" />@endif
                        @if (in_array('sous_titre', $champs, true))<x-form.input name="sous_titre" label="Sous-titre" :value="$section->sous_titre" col="col-md-6" />@endif
                        @if (in_array('contenu', $champs, true))
                            <div class="col-12 mb-3"><label class="form-label" for="contenu">Texte</label>
                                <textarea name="contenu" id="contenu" rows="{{ $section->type === 'HTML' ? 12 : 6 }}" class="form-control {{ $section->type === 'HTML' ? 'font-monospace' : '' }}">{{ $section->contenu }}</textarea>
                                <div class="form-text">Mise en forme possible : &lt;p&gt;, &lt;strong&gt;, &lt;em&gt;, &lt;ul&gt;&lt;li&gt;, &lt;a href&gt;, &lt;h3&gt;. Les scripts sont retirés.</div></div>
                        @endif
                        @foreach (['image' => 'Image', 'image_fond' => 'Image de fond'] as $k => $l)
                            @if (in_array($k, $champs, true))
                                <div class="col-md-6 mb-3"><label class="form-label" for="f-{{ $k }}">{{ $l }}</label><input type="file" name="{{ $k }}_fichier" id="f-{{ $k }}" accept="image/*" class="form-control">
                                    @if ($section->{$k})<div class="d-flex align-items-center gap-2 mt-2"><img src="{{ $url($section->{$k}) }}" style="height:56px;border-radius:6px" alt="">
                                        <div class="form-check"><input class="form-check-input" type="checkbox" name="{{ $k }}_retirer" value="1" id="r-{{ $k }}"><label class="form-check-label small" for="r-{{ $k }}">Retirer</label></div></div>@endif
                                </div>
                            @endif
                        @endforeach
                        @if (in_array($section->type, ['BIENS', 'PROGRAMMES', 'ACTUALITES'], true))
                            <div class="col-12"><div class="alert alert-light border small mb-0"><i class="ti ti-refresh"></i> Bloc automatique : son contenu vient de l'application (éléments publiés), mis à jour en temps réel.</div></div>
                        @endif
                        @if (!$champs && empty($def['elements']) && empty($def['options']))<p class="text-muted mb-0">Ce bloc utilise les informations des réglages du site (contact, carte, horaires).</p>@endif
                    </div>
                </div>

                @if (!empty($def['elements']))
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center"><span>Éléments</span>
                            <button type="button" class="btn btn-sm btn-soft" id="el-ajouter"><i class="ti ti-plus"></i> Ajouter</button></div>
                        <div class="card-body" id="el-liste">
                            @foreach ($elements as $i => $el)
                                @include('siteweb._element', ['i' => $i, 'el' => $el, 'def' => $def['elements']])
                            @endforeach
                        </div>
                        <template id="el-modele">@include('siteweb._element', ['i' => '__I__', 'el' => [], 'def' => $def['elements']])</template>
                    </div>
                @endif
            </div>

            <div class="col-xl-4">
                <div class="card mb-3">
                    <div class="card-header">Affichage</div>
                    <div class="card-body row">
                        <x-form.input name="ancre" label="Ancre (lien #…)" :value="$section->ancre" col="col-md-6" placeholder="services" />
                        <div class="col-md-6 mb-3"><label class="form-label" for="cf">Couleur de fond</label><input type="color" name="couleur_fond" id="cf" class="form-control form-control-color w-100" value="{{ $section->couleur_fond ?: '#ffffff' }}"></div>
                        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="couleur_fond_active" value="1" id="s-cf" @checked($section->couleur_fond)><label class="form-check-label" for="s-cf">Utiliser cette couleur de fond</label></div></div>
                        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="dans_menu" value="1" id="s-menu" @checked($section->dans_menu)><label class="form-check-label" for="s-menu">Lien dans le menu (titre + ancre)</label></div></div>
                        <div class="col-12 mb-2"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="sombre" value="1" id="s-sombre" @checked($section->sombre)><label class="form-check-label" for="s-sombre">Fond sombre, texte clair</label></div></div>
                        @foreach ($def['options'] ?? [] as $cle => $o)
                            @php([$libelle, $type] = $o)
                            @php($valeur = $section->options[$cle] ?? null)
                            @if ($type === 'switch')
                                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="options[{{ $cle }}]" value="1" id="o-{{ $cle }}" @checked($valeur)><label class="form-check-label" for="o-{{ $cle }}">{{ $libelle }}</label></div></div>
                            @elseif ($type === 'select')
                                <x-form.select :name="'options[' . $cle . ']'" :label="$libelle" :options="$o[2]" :value="$valeur" col="col-12" placeholder="" />
                            @else
                                <x-form.input :name="'options[' . $cle . ']'" :label="$libelle" :type="$type === 'number' ? 'number' : 'text'" :value="$valeur" col="col-12" />
                            @endif
                        @endforeach
                    </div>
                </div>
                @can('siteweb-edit')<button class="btn btn-primary w-100"><i class="ti ti-device-floppy"></i> Enregistrer le bloc</button>@endcan
            </div>
        </div>
    </form>
</x-page>
@endsection

@push('scripts')
<script>
    (function () {
        var b = document.getElementById('el-ajouter'); if (!b) { return; }
        var liste = document.getElementById('el-liste'), modele = document.getElementById('el-modele').innerHTML;
        var n = {{ count($elements) }} + 100;
        b.addEventListener('click', function () { liste.insertAdjacentHTML('beforeend', modele.replace(/__I__/g, String(n++))); });
        liste.addEventListener('click', function (e) {
            var x = e.target.closest('[data-retirer]'); if (!x) { return; }
            var bloc = x.closest('.el-bloc'); bloc.querySelector('[data-supprimer]').value = '1'; bloc.classList.add('d-none');
        });
    })();
</script>
@endpush
