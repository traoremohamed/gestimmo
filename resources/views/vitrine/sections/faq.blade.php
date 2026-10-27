<section id="{{ $s->ancre ?: 'bloc-' . $s->id }}" class="s-section {{ $s->sombre ? 'sombre' : '' }}" style="{{ $s->couleur_fond ? 'background:' . $s->couleur_fond . ';' : '' }}{{ $s->image_fond ? 'background:url(' . \App\Services\Site\SiteService::url($s->image_fond) . ') center/cover;' : '' }}">
    <div class="container" style="max-width:860px">
        @include('vitrine.sections._entete')
        <div class="accordion" id="faq-{{ $s->id }}">
            @foreach ($el as $i => $x)
                <div class="accordion-item">
                    <h3 class="accordion-header"><button class="accordion-button {{ $i ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faq-{{ $s->id }}-{{ $i }}">{{ $x['question'] ?? '' }}</button></h3>
                    <div id="faq-{{ $s->id }}-{{ $i }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" data-bs-parent="#faq-{{ $s->id }}"><div class="accordion-body">{!! nl2br(e(strip_tags($x['reponse'] ?? ''))) !!}</div></div>
                </div>
            @endforeach
        </div>
    </div>
</section>
