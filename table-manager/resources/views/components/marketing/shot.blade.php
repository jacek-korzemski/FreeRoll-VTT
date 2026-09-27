@props(['shot', 'priority' => false])

<figure class="overflow-hidden rounded-xl border border-white/10 bg-black/30">
    <img
        src="{{ $shot['src'] }}"
        alt="{{ $shot['alt'] }}"
        width="{{ $shot['width'] }}"
        height="{{ $shot['height'] }}"
        decoding="async"
        @if ($priority) fetchpriority="high" @else loading="lazy" @endif
        class="h-auto w-full"
    >
</figure>
