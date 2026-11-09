@props([
    'id',
    'title' => null,
    'closeLabel' => 'Close',
])

<dialog id="{{ $id }}" class="msc-dialog" aria-labelledby="{{ $id }}-title">
    <div class="msc-dialog__panel">
        <div class="msc-dialog__header">
            @if($title)
                <h2 id="{{ $id }}-title" class="msc-dialog__title">{{ $title }}</h2>
            @else
                <span id="{{ $id }}-title" class="msc-sr-only">Dialog</span>
            @endif
            <button type="button" class="msc-dialog__close" data-msc-modal-close aria-label="{{ $closeLabel }}">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        {{ $slot }}
    </div>
</dialog>