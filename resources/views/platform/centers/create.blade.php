@extends('layouts.app')
@section('title', 'Yangi markaz')

@section('content')
    <x-page-header title="Yangi markaz" subtitle="Markaz «tarif kutilmoqda» holatida ochiladi; tarif bergach yoki direktor sotib olgach faollashadi" />
    <form method="POST" action="{{ route('platform.centers.store') }}" class="max-w-2xl space-y-6">
        @csrf
        <div class="card card-body grid gap-5 sm:grid-cols-2">
            <x-input name="name" label="Markaz nomi" required class="sm:col-span-2" />
            <x-input name="branch_name" label="Birinchi filial nomi" required class="sm:col-span-2" />
            <x-input name="owner_name" label="Direktor to'liq ismi" required />
            <x-input name="owner_phone" phone label="Direktor telefoni" required />
        </div>
        <div class="flex gap-2"><button class="btn-primary">Yaratish</button><a href="{{ route('platform.centers.index') }}" class="btn-secondary">Bekor qilish</a></div>
    </form>
@endsection
