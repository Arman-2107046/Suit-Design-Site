{{--
    A round avatar with the person's initials, its colour fixed by their name so
    the same admin is always the same colour across the team list and the log.
    $name, $initials, $size (px, default 36)
--}}
@php
    $size ??= 36;
    $hue = $name ? crc32($name) % 360 : 220;
    $background = $name
        ? "linear-gradient(135deg, hsl({$hue} 72% 58%), hsl(" . (($hue + 38) % 360) . " 68% 44%))"
        : '#e2e8f0';
@endphp
<span aria-hidden="true" style="
    display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center;
    width: {{ $size }}px; height: {{ $size }}px; border-radius: 9999px;
    background: {{ $background }}; color: {{ $name ? '#fff' : '#64748b' }};
    font-size: {{ round($size * 0.36) }}px; font-weight: 650; letter-spacing: 0.02em;
" class="ct-avatar">{{ $initials }}</span>
