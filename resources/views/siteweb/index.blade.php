@extends('layouts.backLayout.designadmin')

@php($c = $site->contact ?? [])
@php($r = $site->reseaux ?? [])

@section('content')
<x-page titre="Site web" module="Site vitrine">
    <x-slot:badge>
        @if($agence->vitrine_active)<span class="badge badge-soft-success fs-6"><i class="ti ti-world"></i> publié</span>@else<span class="badge badge-soft-secondary fs-6">non publié</span>@endif
    </x-slot:badge>
    <x-slot:actions>
        <a href="{{ route('siteweb.apercu') }}" target="_blank" rel="noopener" class="btn btn-sm btn-light"><i class="ti ti-eye"></i> Aperçu</a>
        @if($agence->vitrine_active)<a href="{{ $adresse }}" target="_blank" rel="noopener" class="btn btn-sm btn-light"><i class="ti ti-external-link"></i> Voir le site</a>@endif
        @can('siteweb-publier')
            <form method="POST" action="{{ route('siteweb.publier') }}" class="d-inline" data-confirm="{{ $agence->vitrine_active ? 'Dépublier le site ?' : 'Publier le site ? Il sera visible de tous.' }}">
                @csrf <input type="hidden" name="publie" value="{{ $agence->vitrine_active ? 0 : 1 }}">
                <button class="btn btn-sm {{ $agence->vitrine_active ? 'btn-outline-danger' : 'btn-primary' }}"><i class="ti ti-{{ $agence->vitrine_active ? 'world-off' : 'world-upload' }}"></i> {{ $agence->vitrine_active ? 'Dépublier' : 'Publier le site' }}</button>
            </form>
        @endcan
    </x-slot:actions>

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center"><span><i class="ti ti-files"></i> Pages</span>
                    @can('siteweb-edit')
                        <form method="POST" action="{{ route('siteweb.pages.store') }}" class="d-flex gap-1">@csrf
                            <input name="titre" class="form-control form-control-sm" placeholder="Nouvelle page" required style="width:170px" aria-label="Titre de la nouvelle page"><button class="btn btn-sm btn-soft" title="Créer"><i class="ti ti-plus"></i></button>
                        </form>
                    @endcan
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($pages as $p)
                        <a href="{{ route('siteweb.pages.edit', $p) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="ti ti-{{ $p->est_accueil ? 'home' : 'file' }} text-primary"></i>
                            <span class="flex-grow-1">{{ $p->titre }} <small class="text-muted">/{{ $p->est_accueil ? '' : 'p/' . $p->slug }}</small></span>
                            @unless ($p->publie)<span class="badge badge-soft-secondary">masquée</span>@endunless
                            <span class="badge badge-soft-primary">{{ $p->sections_count }} bloc(s)</span>
                        </a>
                    @empty
                        <div class="p-3">
                            <p class="text-muted small mb-2">Aucune page. Partez du modèle immobilier : diaporama des biens, recherche, services, derniers biens, programmes,
                                chiffres, agents, actualités, témoignages et contact sont créés avec vos informations.</p>
                            @can('siteweb-edit')<form method="POST" action="{{ route('siteweb.modele') }}">@csrf<button class="btn btn-primary w-100"><i class="ti ti-wand"></i> Créer le site à partir du modèle</button></form>@endcan
                        </div>
                    @endforelse
                </div>
            </div>
            <div class="alert alert-info small">
                <strong>Adresse du site :</strong> <a href="{{ $adresse }}" target="_blank" rel="noopener">{{ $adresse }}</a><br>
                Pages système toujours disponibles : catalogue des biens (recherche, fiche, demande de visite), actualités et contact.
                Les contenus du catalogue et des blocs automatiques viennent de l'application (biens publiés, programmes, témoignages, actualités, bannières).
            </div>
        </div>

        <div class="col-xl-7">
            <form method="POST" action="{{ route('siteweb.reglages') }}" enctype="multipart/form-data" class="card">
                @csrf @method('PUT')
                <div class="card-header"><i class="ti ti-settings"></i> Identité, contact et apparence</div>
                <fieldset class="card-body row" @cannot('siteweb-edit') disabled @endcannot>
                    <x-form.input name="titre" label="Nom affiché" :value="$site->titre" col="col-md-6" />
                    <x-form.input name="slogan" label="Slogan" :value="$site->slogan" col="col-md-6" />
                    <x-form.textarea name="description" label="Description (moteurs de recherche, partage)" :value="$site->description" rows="2" />
                    <x-form.input name="mots_cles" label="Mots-clés" :value="$site->mots_cles" col="col-md-6" />
                    <x-form.input name="domaine" label="Nom de domaine personnalisé" :value="$agence->domaine" col="col-md-6" placeholder="www.monagence.ci" aide="Pointé vers la plateforme, il affiche directement votre site." />
                    <div class="col-md-3 mb-3"><label class="form-label" for="cp">Couleur principale</label><input type="color" name="couleur_principale" id="cp" class="form-control form-control-color w-100" value="{{ $site->couleur_principale }}"></div>
                    <div class="col-md-3 mb-3"><label class="form-label" for="cs">Couleur secondaire</label><input type="color" name="couleur_secondaire" id="cs" class="form-control form-control-color w-100" value="{{ $site->couleur_secondaire }}"></div>
                    <x-form.select name="police" label="Police" :options="array_combine(config('site.polices'), config('site.polices'))" :value="$site->police" col="col-md-3" placeholder="" />
                    <x-form.select name="theme" label="Thème" :options="['CLAIR' => 'Clair', 'SOMBRE' => 'Sombre']" :value="$site->theme" col="col-md-3" placeholder="" />
                    <div class="col-md-6 mb-3"><label class="form-label" for="logo">Logo</label><input type="file" name="logo" id="logo" accept="image/*" class="form-control">@if ($site->logo)<img src="{{ \App\Services\Site\SiteService::url($site->logo) }}" class="mt-2" style="max-height:48px" alt="Logo actuel">@endif</div>
                    <div class="col-md-6 mb-3"><label class="form-label" for="favicon">Icône d'onglet (favicon)</label><input type="file" name="favicon" id="favicon" accept="image/*" class="form-control"></div>
                    <x-form.input name="contact[telephone]" label="Téléphone" :value="$c['telephone'] ?? null" col="col-md-3" />
                    <x-form.input name="contact[whatsapp]" label="WhatsApp" :value="$c['whatsapp'] ?? null" col="col-md-3" />
                    <x-form.input name="contact[email]" label="E-mail" :value="$c['email'] ?? null" col="col-md-6" />
                    <x-form.input name="contact[adresse]" label="Adresse" :value="$c['adresse'] ?? null" col="col-12" />
                    @foreach (['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin'] as $k)
                        <x-form.input :name="'reseaux[' . $k . ']'" :label="ucfirst($k)" :value="$r[$k] ?? null" col="col-md-4" placeholder="https://" />
                    @endforeach
                    <x-form.textarea name="horaires" label="Horaires d'ouverture" :value="$site->horaires" col="col-md-6" rows="3" />
                    <x-form.textarea name="carte_url" label="Carte : lien d'intégration Google Maps ou OpenStreetMap" :value="$site->carte_url" col="col-md-6" rows="3" />
                    <x-form.textarea name="pied_page" label="Texte du pied de page" :value="$site->pied_page" rows="2" />
                    <div class="col-12 mb-2"><h6 class="text-uppercase text-muted small">Menu du site</h6></div>
                    <x-form.checkbox name="menu_biens" label="Liens « Acheter » et « Louer » (catalogue des biens)" :value="$site->menu_biens" col="col-md-6" />
                    <x-form.checkbox name="menu_actualites" label="Lien « Actualités »" :value="$site->menu_actualites" col="col-md-6" />
                    <x-form.checkbox name="lien_espace_client" label="Bouton « Espace client » (connexion des clients)" :value="$site->lien_espace_client" col="col-md-6" />
                    <x-form.textarea name="css" label="CSS personnalisé (avancé)" :value="$site->css" rows="3" />
                </fieldset>
                @can('siteweb-edit')<div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Enregistrer</button></div>@endcan
            </form>
        </div>
    </div>
</x-page>
@endsection
