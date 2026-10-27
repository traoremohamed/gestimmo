@if ($s->titre || $s->sous_titre)
    <div class="text-center mb-5">
        @if ($s->titre)<h2 class="s-titre display-6">{{ $s->titre }}</h2><div class="s-trait"></div>@endif
        @if ($s->sous_titre)<p class="s-sous-titre mt-3 mb-0 fs-5">{{ $s->sous_titre }}</p>@endif
    </div>
@endif
