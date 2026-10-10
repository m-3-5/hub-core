@extends('guides.layout')

@section('title', 'Come ricevere i soldi delle tue vendite')
@section('description', 'Guide semplici per scegliere come farti pagare: pagamenti protetti Hub Core, Stripe, PayPal, bonifico, carte e conti online.')
@section('back_url', route('welcome'))
@section('top', 'Guide')

@section('content')
<h1>Come vuoi ricevere i soldi?</h1>
<p class="lead">Quando vendi puoi scegliere la strada che preferisci. Qui trovi spiegato, passo dopo passo, come aprire gli account e cosa controllare.</p>

<div class="cards">
    @foreach ($guides as $slug => $g)
        <a class="card" href="{{ route('guides.show', $slug) }}">
            <span class="ico">{{ $g['emoji'] }}</span>
            <div><span class="tag">{{ $g['level'] }}</span><b>{{ $g['title'] }}</b><span>{{ $g['summary'] }}</span></div>
        </a>
    @endforeach
</div>

<div class="box">
    <b>Non sai quale scegliere?</b>
    <p>Se vendi tramite inm35.it scegli i <a href="{{ route('guides.show', 'pagamenti-protetti') }}">pagamenti protetti</a>: ti tutelano e tutelano chi compra. Sul tuo sito puoi sempre incassare anche direttamente.</p>
    <p class="small">Costi e condizioni: leggi le <a href="{{ route('terms.economic') }}">condizioni economiche</a>.</p>
</div>
@endsection
