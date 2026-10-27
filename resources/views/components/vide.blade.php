@props(['message' => 'Aucun élément pour le moment.', 'icone' => 'ti-inbox'])

<div class="text-center text-muted py-5">
    <i class="ti {{ $icone }} fs-1 d-block mb-2 opacity-50"></i>
    {{ $message }}
    @if($slot->isNotEmpty())
        <div class="mt-3">{{ $slot }}</div>
    @endif
</div>
