@extends('guides.layout')

@section('title', 'Guide: come si usa inm35.it e come ricevere i soldi')
@section('description', 'Guide semplici per scegliere come farti pagare: pagamenti protetti Hub Core, Stripe, PayPal, bonifico, carte e conti online.')
@section('back_url', route('welcome'))
@section('top', 'Guide')

@section('content')
<h1>Come si usa inm35.it</h1>
<div class="cards" style="margin-bottom:28px">
    <a class="card" href="{{ route('guides.features') }}"><span class="ico">✨</span><div><b>Cosa puoi fare con inm35.it</b><span>Tutte le cose attive, in breve.</span></div></a>
    <a class="card" href="{{ route('guides.app') }}"><span class="ico">📖</span><div><b>Guida passo passo</b><span>Promo, prodotti, servizi, sito, annunci, credito.</span></div></a>
    <a class="card" href="{{ route('guides.facebook') }}"><span class="ico">📘</span><div><b>Pubblica da Facebook</b><span>Collega la pagina e usa gli hashtag.</span></div></a>
</div>

<h2>Come vuoi ricevere i soldi?</h2>
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
