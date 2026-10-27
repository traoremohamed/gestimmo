@php $contact = array_filter($siteVitrine->contact ?? []); $carte = \App\Services\Site\SiteService::carteSure($siteVitrine->carte_url); @endphp
<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container">
        @include('vitrine.sections._entete')
        <div class="row g-4">
            <div class="{{ !empty($o['formulaire']) ? 'col-lg-5' : 'col-lg-8 mx-auto' }}">
                <div class="card s-carte p-4">
                    @if ($s->contenu)<div class="mb-3">{!! $s->contenu !!}</div>@endif
                    @if (!empty($contact['telephone']))<p class="mb-2"><i class="ti ti-phone me-2 text-marque"></i><a href="tel:{{ preg_replace('/\s+/', '', $contact['telephone']) }}">{{ $contact['telephone'] }}</a></p>@endif
                    @if (!empty($contact['whatsapp']))<p class="mb-2"><i class="ti ti-brand-whatsapp me-2" style="color:#25D366"></i><a href="https://wa.me/{{ preg_replace('/\D+/', '', $contact['whatsapp']) }}" target="_blank" rel="noopener">{{ $contact['whatsapp'] }}</a></p>@endif
                    @if (!empty($contact['email']))<p class="mb-2"><i class="ti ti-mail me-2 text-marque"></i><a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></p>@endif
                    @if (!empty($contact['adresse']))<p class="mb-2"><i class="ti ti-map-pin me-2 text-marque"></i>{{ $contact['adresse'] }}</p>@endif
                    @if (!empty($o['horaires']) && $siteVitrine->horaires)<hr><div class="small"><strong>Horaires</strong><br>{!! nl2br(e($siteVitrine->horaires)) !!}</div>@endif
                    @if (!empty($o['carte']) && $carte)<iframe src="{{ $carte }}" class="w-100 rounded-4 border-0 mt-3" style="min-height:240px" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Plan d'accès"></iframe>@endif
                </div>
            </div>
            @if (!empty($o['formulaire']))
                <div class="col-lg-7">
                    <form method="POST" action="{{ ($apercu ?? false) ? '#' : route('vitrine.contact.envoyer') }}" class="card s-carte p-4 row g-3 m-0">
                        @csrf
                        <input type="text" name="site_web" class="piege" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <div class="col-md-6"><label class="form-label" for="c-nom-{{ $s->id }}">Nom et prénoms</label><input name="nom" id="c-nom-{{ $s->id }}" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label" for="c-tel-{{ $s->id }}">Téléphone</label><input name="telephone" id="c-tel-{{ $s->id }}" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label" for="c-mail-{{ $s->id }}">E-mail</label><input type="email" name="email" id="c-mail-{{ $s->id }}" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label" for="c-sujet-{{ $s->id }}">Sujet</label><input name="sujet" id="c-sujet-{{ $s->id }}" class="form-control"></div>
                        <div class="col-12"><label class="form-label" for="c-msg-{{ $s->id }}">Message</label><textarea name="message" id="c-msg-{{ $s->id }}" rows="4" class="form-control" required></textarea></div>
                        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="form-check"><input type="checkbox" name="newsletter" value="1" id="c-nl-{{ $s->id }}" class="form-check-input"><label for="c-nl-{{ $s->id }}" class="form-check-label small">Recevoir nos nouvelles offres</label></div>
                            <button class="btn btn-s"><i class="ti ti-send"></i> Envoyer</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</section>
