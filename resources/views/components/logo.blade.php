@props(['class' => 'h-10 w-10', 'center' => null])
@php($src = \App\Support\Brand::centerLogoUrl($center) ?? \App\Support\Brand::logoUrl())
@if ($src)
    <img src="{{ $src }}" alt="{{ $center?->name ?? \App\Support\Brand::name() }}" {{ $attributes->merge(['class' => "inline-block $class shrink-0 rounded-xl bg-white object-contain"]) }}>
@else
    <span {{ $attributes->merge(['class' => "inline-flex $class items-center justify-center rounded-xl bg-brand-600 font-black tracking-tight text-white shadow-sm"]) }}>{{ \App\Support\Brand::initial() }}</span>
@endif
