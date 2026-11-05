@extends('layouts.app')
@section('title', 'Tahlil')

@section('content')
    <x-page-header :title="$center->name.' — tahlil'" subtitle="So'nggi 6 oy" />
    @if (auth()->user()->role === \App\Enums\Role::Owner)@include('center._nav')@endif
    @include('center._analytics-body', ['a' => $a])
@endsection
