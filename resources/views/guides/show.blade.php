@extends('guides.layout')

@section('title', $guide['title'])
@section('description', $guide['summary'])
@section('top', 'Guida')

@section('content')
<p><a href="{{ route('guides.index') }}">← Tutte le guide</a></p>
<h1>{{ $guide['emoji'] }} {{ $guide['title'] }}</h1>
<p class="lead">{{ $guide['summary'] }}</p>

@include('guides.content.'.$slug)

<div class="box warn">
    <b>⚠️ Prima di tutto la sicurezza</b>
    <p>Non dare mai a nessuno (nemmeno a noi) la password del tuo conto o i codici che ricevi per SMS. Usa sempre i siti ufficiali indicati qui e attiva la verifica in due passaggi.</p>
</div>

<h2>Altre guide</h2>
<div class="cards">
    @foreach ($guides as $s => $g)
        @continue($s === $slug)
        <a class="card" href="{{ route('guides.show', $s) }}"><span class="ico">{{ $g['emoji'] }}</span><div><b>{{ $g['title'] }}</b><span>{{ $g['summary'] }}</span></div></a>
    @endforeach
</div>
<p class="small" style="margin-top:16px">Le informazioni sono indicative e i servizi esterni possono cambiare condizioni e tariffe: controlla sempre il sito ufficiale. Vedi anche le <a href="{{ route('terms.economic') }}">condizioni economiche</a>.</p>
@endsection
