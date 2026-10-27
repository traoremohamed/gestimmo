{{-- Un élément répétable d'un bloc (diapositive, carte, photo…) --}}
<div class="border rounded-3 p-2 mb-2 el-bloc">
    <input type="hidden" name="elements[{{ $i }}][_supprimer]" value="0" data-supprimer>
    <div class="row g-2 align-items-end">
        @foreach ($def as $cle => [$libelle, $type])
            @if ($type === 'image')
                <div class="col-md-4">
                    <label class="form-label small">{{ $libelle }}</label>
                    <input type="file" name="elements[{{ $i }}][image_fichier]" accept="image/*" class="form-control form-control-sm">
                    <input type="hidden" name="elements[{{ $i }}][{{ $cle }}]" value="{{ $el[$cle] ?? '' }}">
                    @if (!empty($el[$cle]))<img src="{{ \App\Services\Site\SiteService::url($el[$cle]) }}" class="mt-1" style="height:44px;border-radius:6px" alt="">@endif
                </div>
            @elseif ($type === 'textarea')
                <div class="col-md-8"><label class="form-label small">{{ $libelle }}</label><textarea name="elements[{{ $i }}][{{ $cle }}]" rows="2" class="form-control form-control-sm">{{ $el[$cle] ?? '' }}</textarea></div>
            @else
                <div class="col-md-4"><label class="form-label small">{{ $libelle }}</label><input type="{{ $type === 'number' ? 'number' : 'text' }}" name="elements[{{ $i }}][{{ $cle }}]" value="{{ $el[$cle] ?? '' }}" class="form-control form-control-sm"></div>
            @endif
        @endforeach
        <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-light text-danger" data-retirer title="Retirer"><i class="ti ti-trash"></i></button></div>
    </div>
</div>
