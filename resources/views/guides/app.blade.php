@extends('guides.layout')

@section('title', 'Come si usa inm35.it')
@section('description', 'Guida passo passo: installare l\'app, creare promo, prodotti, servizi e preventivi, fare il sito con l\'IA, pubblicare annunci, collegare Facebook, ricaricare il credito.')
@section('back_url', route('guides.features'))
@section('top', 'Guida all\'app')

{{-- Tieni allineato con il codice: ogni passo qui deve corrispondere a un tasto vero dell'app.
     Promemoria completo per chi modifica il codice: docs/FUNZIONI-INM35.md --}}
@section('content')
<h1>Come si usa inm35.it</h1>
<p class="lead">Tutto dal telefono. Tocca una voce per saltare lì.</p>

<div class="cards">
    <a class="card" href="#installa"><span class="ico">📲</span><div><b>Installa l'app</b></div></a>
    <a class="card" href="#promo"><span class="ico">🎯</span><div><b>Crea una promo</b></div></a>
    <a class="card" href="#prodotti-servizi"><span class="ico">🛍️</span><div><b>Prodotti e servizi</b></div></a>
    <a class="card" href="#preventivi"><span class="ico">🧾</span><div><b>Preventivi</b></div></a>
    <a class="card" href="#sito"><span class="ico">🌐</span><div><b>Il tuo sito con l'IA</b></div></a>
    <a class="card" href="#annunci"><span class="ico">📌</span><div><b>Annunci</b></div></a>
    <a class="card" href="#facebook"><span class="ico">📘</span><div><b>Pubblica da Facebook</b></div></a>
    <a class="card" href="#credito"><span class="ico">💶</span><div><b>Credito e ricariche</b></div></a>
    <a class="card" href="#pagamenti"><span class="ico">🛡️</span><div><b>Ricevere i soldi</b></div></a>
    <a class="card" href="#assistenza"><span class="ico">💬</span><div><b>Messaggi e aiuto</b></div></a>
</div>

<h2 id="installa">📲 Installa l'app</h2>
<ol>
    <li>Apri <a href="{{ route('app.index') }}">inm35.it/app</a> dal telefono.</li>
    <li><strong>Android:</strong> premi «Installa». <strong>iPhone:</strong> tocca Condividi, poi «Aggiungi a Home».</li>
    <li>Entra con email e password, con il link che ti mandiamo per email o con Google.</li>
</ol>

<h2 id="promo">🎯 Crea una promo</h2>
<ol>
    <li>Nell'app tocca <strong>«Crea promo»</strong>.</li>
    <li>Scegli: <strong>📷 Carica una foto</strong> (l'IA ti propone titolo e testo) oppure <strong>✨ Crea tu la grafica</strong> (la disegna l'IA).</li>
    <li>Controlla <strong>titolo</strong>, <strong>descrizione</strong> e la <strong>scadenza</strong>.</li>
    <li>Nel riepilogo premi <strong>«Pubblica»</strong>: 1 €, scalato dal credito.</li>
</ol>
<details class="box"><summary><b>Scopri di più</b></summary>
    <p>La promo ha la sua pagina da mandare su WhatsApp e compare nelle «Offerte» del tuo sito. Dalla pagina della promo puoi creare anche un video e le immagini per i social. Modificarla dopo non costa. Puoi salvarla come bozza e pubblicarla più tardi.</p>
</details>

<h2 id="prodotti-servizi">🛍️ Prodotti e servizi</h2>
<ol>
    <li>Tocca <strong>«Prodotti»</strong> o <strong>«Servizi»</strong>, poi aggiungi.</li>
    <li>Foto (o grafica dell'IA), nome, due righe e <strong>prezzo</strong>. Per i servizi anche <strong>quanto dura</strong>.</li>
    <li>Se vuoi, mettilo <strong>«In promo»</strong> fino a una data: l'etichetta sparisce da sola.</li>
    <li>Lascia acceso <strong>«Mostralo sul tuo sito e su inm35.it»</strong> e pubblica: 1 €.</li>
</ol>
<p class="small">Con il sito fatto su inm35.it hai 3 promo, 3 prodotti e 3 servizi inclusi.</p>

<h2 id="preventivi">🧾 Preventivi</h2>
<ol>
    <li>Tocca <strong>«Preventivi»</strong>.</li>
    <li>Scrivi <strong>cosa</strong> e <strong>quanto</strong>.</li>
    <li>Mandi al cliente il <strong>link per pagare</strong>.</li>
</ol>
<p class="small">Per incassare serve aver scelto come ricevere i soldi: vedi <a href="#pagamenti">Ricevere i soldi</a>.</p>

<h2 id="sito">🌐 Il tuo sito con l'IA</h2>
<ol>
    <li>Nell'app apri <strong>«Sito web»</strong> (o vai su <a href="{{ route('site.start') }}">inm35.it/crea-sito</a>).</li>
    <li>Descrivi la tua attività in due righe: l'IA scrive i testi. Oppure tocca <strong>«Preferisco scrivere io»</strong>.</li>
    <li>Passa le schermate: frase in prima pagina, <strong>«Cosa offri?»</strong> (servizi e <strong>marchi trattati</strong>, uno per riga), chi siete, pulsante, telefono, foto e logo, indirizzo e orari, social.</li>
    <li>Premi <strong>«Metti online»</strong>: 1 €, una volta sola.</li>
</ol>
<details class="box"><summary><b>Scopri di più</b></summary>
    <ul>
        <li><strong>Cambiare il sito:</strong> «✨ Rifallo con l'IA» e ripassi i passi; quello che hai scritto resta. Modificare non costa.</li>
        <li><strong>Disegno in alto:</strong> l'IA crea un disegno a tema; «Fai un altro disegno» se non ti piace (gratis).</li>
        <li><strong>Online o offline:</strong> «Metti offline» lo nasconde a tutti tranne a te; «Rimetti online» quando vuoi.</li>
        <li><strong>Servizi in più, 1 € ciascuno, una volta sola</strong> (nel riepilogo del sito):
            <ul>
                <li>🌍 <strong>Lingue</strong>: inglese, tedesco, francese, spagnolo… tradotte dall'IA, si aggiornano gratis se cambi il sito;</li>
                <li>🔎 <strong>SEO e parole chiave</strong>: l'IA sceglie le ricerche che fa la gente e scrive titolo e descrizione per Google;</li>
                <li>🤖 <strong>Fatti trovare dall'IA</strong>: una scheda chiara per ChatGPT, Gemini e gli altri assistenti;</li>
                <li>📡 <strong>Indicizza il sito</strong>: entra nella mappa di inm35.it letta da Google e viene segnalato subito a Bing.</li>
            </ul>
        </li>
        <li>Nel sito compaiono da sole le tue promo, prodotti e servizi, e i post di Facebook con #novita, #evento, #blog, #lavori.</li>
    </ul>
</details>

<h2 id="annunci">📌 Annunci</h2>
<ul>
    <li><strong>Attività:</strong> nell'app tocca <strong>«Annunci»</strong> e pubblica. Gratis.</li>
    <li><strong>Privati:</strong> vai su <a href="{{ route('classifieds.private.create') }}">inm35.it/annunci/nuovo</a> (Vendo, Cerco, Lavoro), scrivi l'annuncio e conferma dal link che ti arriva per email. Lo controlliamo noi, resta online 30 giorni. Gratis.</li>
</ul>

<h2 id="facebook">📘 Pubblica da Facebook</h2>
<ol>
    <li>Nell'app apri <strong>«Facebook»</strong> e premi <strong>«Collega Facebook»</strong>.</li>
    <li>Scegli la pagina della tua attività.</li>
    <li>Scrivi i post come sempre, con un hashtag: <strong>#promo</strong>, <strong>#prodotto</strong>, <strong>#servizio</strong> (1 €) oppure <strong>#novita</strong>, <strong>#evento</strong>, <strong>#blog</strong>, <strong>#lavori</strong> (gratis).</li>
</ol>
<p><a class="btn-ghost" href="{{ route('guides.facebook') }}">Guida completa agli hashtag</a></p>

<h2 id="credito">💶 Credito e ricariche</h2>
<ul>
    <li>🎁 La prima volta: <strong>15 € di credito in regalo</strong>. Ogni cosa a 1 € si scala da lì.</li>
    <li>💳 <strong>Ricarica</strong> da 5 € in «Pagamenti»: con la carta (pagina sicura di Stripe), con bonifico o in contanti.</li>
    <li>🎮 <strong>Buoni dei giochi</strong> di <a href="https://piramide35.com" target="_blank" rel="noopener">piramide35.com</a>: scrivi il codice in «Pagamenti» → «Hai un codice buono?». I primi {{ \App\Support\PayPerUse::euro(\App\Support\PayPerUse::gameFreeCents()) }} li usi subito, il resto dopo la prima ricarica da 5 €.</li>
    <li>⏳ Credito finito? Puoi andare avanti fino a 5 € e saldi con la prossima ricarica.</li>
    <li>⭐ <strong>Premium</strong>, per chi pubblica tanto: {{ \App\Support\PayPerUse::euro(\App\Support\PayPerUse::premiumCents()) }} al mese scalati dal credito e <strong>tutto illimitato</strong>. Si rinnova da solo; in «Pagamenti» premi «Non rinnovare» quando vuoi. Dopo, per un mese paghi {{ \App\Support\PayPerUse::euro((int) config('services.hub_billing.pay_per_use.premium.exit_cents', 80)) }} a pubblicazione, poi 1 €.</li>
    <li>Niente abbonamento obbligatorio: se un mese non pubblichi niente, non paghi niente.</li>
    <li>🧾 A ogni acquisto e ricarica ti arriva la ricevuta per email.</li>
</ul>
<p class="small"><a href="{{ route('pricing.show') }}">Tutti i prezzi</a></p>

<h2 id="pagamenti">🛡️ Ricevere i soldi dai tuoi clienti</h2>
<p>Con i <strong>pagamenti protetti</strong> chi compra paga tramite inm35.it e tu ricevi i soldi dopo la consegna. Oppure incassi tu direttamente (Stripe, PayPal, bonifico).</p>
<p><a class="btn-ghost" href="{{ route('guides.index') }}">Guide: come ricevere i soldi</a></p>

<h2 id="assistenza">💬 Messaggi e aiuto</h2>
<ul>
    <li>Chi ti scrive da una promo o da un annuncio: lo trovi nell'app in <strong>«Messaggi»</strong> e ti arriva anche per email.</li>
    <li>Hai un dubbio? Ti rispondiamo noi, con l'aiuto della nostra intelligenza artificiale: chat qui in basso a destra o <a href="mailto:{{ config('company.email') }}">{{ config('company.email') }}</a>.</li>
</ul>
@endsection
