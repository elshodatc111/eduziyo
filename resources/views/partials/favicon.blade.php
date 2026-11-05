@if ($f = \App\Support\Brand::faviconUrl())
    <link rel="icon" href="{{ $f }}">
@else
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
@endif
