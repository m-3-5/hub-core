@extends('guides.layout')

@section('title', 'Email di Hub Core')
@section('description', 'Scegli se ricevere le idee del mese da Hub Core.')
@section('back_url', route('welcome'))
@section('top', 'Email')


@section('content')
<h1>Idee del mese per {{ $tenant->name }}</h1>

@if (! empty($done))
    <p class="lead">{{ $out ? 'Fatto: non ti mandiamo più le idee del mese.' : 'Fatto: riceverai di nuovo le idee del mese.' }}</p>
@endif

@if ($out)
    <p>Non ricevi le email con idee e offerte. Le email su pagamenti, accesso e messaggi dei clienti continuano ad arrivare, perché servono al servizio.</p>
    <form method="POST" action="{{ request()->fullUrl() }}">
        @csrf
        <input type="hidden" name="resubscribe" value="1">
        <button type="submit" class="btn">Voglio di nuovo le idee del mese</button>
    </form>
@else
    <p>Una volta al mese ti mandiamo qualche idea per farti trovare da più clienti, e a volte un'offerta.</p>
    <form method="POST" action="{{ request()->fullUrl() }}">
        @csrf
        <button type="submit" class="btn">Non mandatemele più</button>
    </form>
@endif
@endsection
