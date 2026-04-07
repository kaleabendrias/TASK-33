@props(['href', 'icon' => 'home', 'label'])
@php
    $active = request()->is(ltrim($href, '/') . '*');
@endphp
<a
    href="{{ $href }}"
    class="flex items-center px-3 py-2 text-sm rounded-lg transition {{ $active ? 'bg-brand-600 text-white font-semibold' : 'text-surface-200 hover:bg-surface-700' }}"
    @if($active) aria-current="page" @endif
    tabindex="0"
>
    <x-icon :name="$icon" class="w-5 h-5 mr-3 opacity-70"/>
    {{ $label }}
</a>
