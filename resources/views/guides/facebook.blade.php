@extends('guides.layout')

@section('title', 'Pubblica da Facebook')
@section('description', 'Scrivi il post sulla tua pagina Facebook una volta sola: con un hashtag diventa promo, prodotto, servizio, novità, evento, articolo o foto del tuo sito su inm35.it.')
@section('back_url', url('/'))
@section('top', 'Facebook')

@section('content')
<h1>Pubblica da Facebook</h1>
<p class="lead">Scrivi il post sulla pagina Facebook della tua attività, come fai sempre. Aggiungi <strong>un hashtag</strong> e il post finisce da solo nel posto giusto su inm35.it e nel tuo sito: niente da ricopiare.</p>

<h2>1. Collega la pagina (una volta sola)</h2>
<ol>
    <li>Entra su <a href="{{ url('/') }}">inm35.it</a> e apri <strong>Facebook</strong> nel menu (o in Profilo).</li>
    <li>Premi <strong>«Collega Facebook»</strong>, entra con il tuo Facebook e dai il permesso all'app «Hub Core».</li>
    <li>Scegli la <strong>pagina della tua attività</strong>. Fatto: vedi «✅ Collegata».</li>
</ol>
<p>Leggiamo solo i post della pagina che scegli. Non pubblichiamo niente su Facebook al posto tuo e puoi scollegare quando vuoi.</p>

<h2>2. Gli hashtag: cosa fa ognuno</h2>
<table>
    <thead><tr><th>Hashtag</th><th>Dove va il post</th><th>Costo</th></tr></thead>
    <tbody>
        <tr><td><strong>#promo</strong></td><td>Diventa una <strong>promo</strong> con la sua pagina, da condividere su WhatsApp, e compare nelle «Offerte» del tuo sito. Serve una foto. Puoi scegliere anche altri hashtag per le promo (es. #offerta, #blackfriday) nella pagina Facebook del pannello.</td><td>1 € (poi a scalare)</td></tr>
        <tr><td><strong>#prodotto</strong></td><td>Diventa un <strong>prodotto</strong> in vetrina e nel tuo sito («Acquista online»). Scrivi il prezzo nel testo, es. «25 €» o «€ 25,90».</td><td>1 €</td></tr>
        <tr><td><strong>#servizio</strong><br><small>(anche #servizi, #nuovoservizio)</small></td><td>Diventa un <strong>servizio</strong> in vetrina e nel tuo sito. Scrivi il prezzo, es. «40 €».</td><td>1 €</td></tr>
        <tr><td><strong>#novita</strong><br><small>(anche #novità, #news)</small></td><td>Va in <strong>«Novità ed eventi»</strong> del tuo sito, con la foto e il link al post.</td><td>gratis</td></tr>
        <tr><td><strong>#evento</strong></td><td>Va in «Novità ed eventi» come <strong>evento</strong>, con la data se la scrivi: «24 ottobre alle 18:30» o «24/10 ore 21».</td><td>gratis</td></tr>
        <tr><td><strong>#blog</strong></td><td>Diventa un <strong>articolo</strong> in «Novità ed eventi»: la prima riga è il titolo, il resto il testo.</td><td>gratis</td></tr>
        <tr><td><strong>#lavori</strong><br><small>(anche #galleria)</small></td><td>La foto va nella <strong>galleria</strong> del tuo sito (le ultime 12).</td><td>gratis</td></tr>
    </tbody>
</table>
<p>Un post <strong>senza questi hashtag</strong> resta solo su Facebook. Se ne metti più di uno vale il primo in quest'ordine: #promo, #prodotto, #servizio, #evento, #novita, #blog, #lavori.</p>

<h2>3. Come scrivere il post</h2>
<ul>
    <li><strong>Prima riga = titolo.</strong> Esempio: «Crema viso idratante».</li>
    <li><strong>Dopo, il testo</strong>: cosa è, per chi, fino a quando. Gli altri hashtag (es. #skincare) restano come parole.</li>
    <li><strong>Una bella foto</strong>: per promo e lavori è obbligatoria, per gli altri è consigliata.</li>
</ul>
<p><em>Esempio:</em><br>
«Piega + trattamento lucidante<br>Capelli morbidi per le feste, 35 € fino al 31 dicembre #servizio»</p>

<h2>4. Quando va online da solo</h2>
<ul>
    <li><strong>Promo, prodotti e servizi</strong> vanno online subito se hai salvato la carta in «Pagamenti» (l'addebito arriva dopo 30 giorni, tutto insieme) e il testo passa il controllo. Prodotti e servizi servono anche del prezzo.</li>
    <li>Altrimenti restano <strong>da completare</strong>: li trovi in Promo, Prodotti o Servizi del pannello, aggiungi quello che manca e pubblichi tu.</li>
    <li><strong>Novità, eventi, articoli e lavori</strong> sono gratis e vanno nel tuo sito, se hai fatto il sito su inm35.it.</li>
    <li>Con il sito, i primi 3 prodotti e 3 servizi del mese sono inclusi.</li>
</ul>

<h2>5. Togliere qualcosa</h2>
<p>Cancellare il post su Facebook non lo toglie da inm35.it: eliminalo dal pannello (Promo, Prodotti, Servizi); le foto della galleria le cambi da «Sito web». Le novità più vecchie escono da sole quando ne arrivano di nuove (il sito ne tiene 12); per toglierne una prima, scrivici. Per scollegare la pagina: Facebook nel menu → «Scollega la pagina». Dettagli in <a href="{{ route('privacy') }}#facebook">Privacy</a>.</p>
@endsection
