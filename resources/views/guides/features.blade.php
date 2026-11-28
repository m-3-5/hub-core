@extends('guides.layout')

@section('title', 'Cosa puoi fare con inm35.it')
@section('description', 'Promo, prodotti, servizi, preventivi, sito web con l\'IA, annunci e pubblicazione da Facebook: tutto dal telefono, paghi solo quello che pubblichi col credito.')
@section('back_url', route('welcome'))
@section('top', 'Cosa puoi fare')

{{-- Solo le funzioni ATTIVE (config/hub.php «active» => true). Quando se ne accende una nuova, aggiungila qui e in guides/app.blade.php.
     Promemoria completo: docs/FUNZIONI-INM35.md --}}
@section('content')
<h1>Cosa puoi fare con inm35.it</h1>
<p class="lead">Un'app sola per far conoscere la tua attività. Dal telefono, in pochi minuti.</p>

<p><a class="btn-main" href="{{ route('app.index') }}">📲 Scarica l'app</a> <a class="btn-ghost" href="{{ route('guides.app') }}">📖 Guida passo passo</a></p>
<p class="small">Sul telefono apri il link e premi «Installa» (su iPhone: Condividi → «Aggiungi a Home»).</p>

@php
    $features = [
        ['🎯', 'Promo', 'Volantino digitale con la sua pagina, da mandare su WhatsApp. L\'IA scrive il testo o disegna la grafica.', 'promo'],
        ['🛍️', 'Prodotti', 'La tua vetrina con prezzo e foto, anche nel tuo sito.', 'prodotti-servizi'],
        ['✂️', 'Servizi', 'Trattamenti, lavori, lezioni: prezzo, durata e link di pagamento.', 'prodotti-servizi'],
        ['🧾', 'Preventivi', 'Scrivi l\'importo e mandi al cliente il link per pagare.', 'preventivi'],
        ['🌐', 'Sito web con l\'IA', 'Pronto in pochi minuti: servizi, marchi trattati, mappa, orari, WhatsApp.', 'sito'],
        ['🌍', 'Lingue, SEO e Google', 'Traduzioni, parole chiave, scheda per ChatGPT e Gemini, segnalazione a Google e Bing: 1 € l\'uno.', 'sito'],
        ['📌', 'Annunci', 'Vendo, cerco, lavoro, affitti: gratis per tutti, anche per i privati.', 'annunci'],
        ['📘', 'Pubblica da Facebook', 'Scrivi il post con #promo, #prodotto, #servizio… e va da solo su inm35.it e nel tuo sito.', 'facebook'],
        ['🛡️', 'Pagamenti protetti', 'Chi compra paga tramite inm35.it, tu ricevi i soldi dopo la consegna.', 'pagamenti'],
        ['💬', 'Messaggi dei clienti', 'Chi ti scrive da una promo o da un annuncio: lo trovi nell\'app e ti arriva per email.', 'assistenza'],
    ];
@endphp

<div class="cards">
    @foreach ($features as [$ico, $title, $text, $anchor])
        <a class="card" href="{{ route('guides.app') }}#{{ $anchor }}">
            <span class="ico">{{ $ico }}</span>
            <div><b>{{ $title }}</b><span>{{ $text }}</span></div>
        </a>
    @endforeach
</div>

<h2>Quanto costa</h2>
<div class="box ok">
    <p>📌 <strong>Annunci: gratis.</strong> 🎯 Promo, prodotti e servizi: <strong>1 € l'uno</strong>, meno se ne pubblichi di più. 🌐 Sito: <strong>1 €</strong>.</p>
    <p>🎁 La prima volta hai <strong>15 € di credito in regalo</strong>. Poi ricarichi da 5 € (carta, bonifico o contanti) o vinci credito ai giochi di <a href="https://piramide35.com" target="_blank" rel="noopener">piramide35.com</a>. Niente abbonamento.</p>
    @if (\App\Support\Packages::enabled())<p>⭐ Pubblichi tantissimo? Scegli un <strong>pacchetto</strong>: Base 9 €, Premium 19 €, Business 39 € al mese dal credito, con tante pubblicazioni incluse. Lo fermi quando vuoi.</p>@endif
    <p class="small"><a href="{{ route('pricing.show') }}">Tutti i prezzi</a></p>
</div>

<h2>In arrivo</h2>
<p class="small">🛒 Negozio online completo · 📅 Agenda e prenotazioni · 🏠 Affitti per B&amp;B · 🎁 Gift card · 💳 Card clienti</p>

<h2>Hai bisogno di aiuto?</h2>
<p>Ti rispondiamo noi, con l'aiuto della nostra intelligenza artificiale: scrivi nella chat qui in basso a destra o a <a href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a>.</p>
@endsection
