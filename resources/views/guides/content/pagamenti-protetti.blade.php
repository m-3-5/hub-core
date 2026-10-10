@php $fees = \M35\HubPayments\Support\StripeFees::class; $ex = $fees::example(); @endphp
<h2>Come funziona</h2>
<ol>
    <li>Il cliente trova il tuo prodotto o servizio su inm35.it e <strong>paga tramite Hub Core</strong>.</li>
    <li>Il pagamento resta <strong>al sicuro</strong> mentre tu consegni.</li>
    <li>Quando il cliente conferma «Ho ricevuto», oppure dopo <strong>7 giorni</strong> senza problemi, i soldi vanno sul tuo conto.</li>
    <li>Se qualcosa non va, il cliente può segnalarlo: il pagamento si ferma e ci pensiamo noi, con il rimborso se serve.</li>
</ol>

<div class="box ok">
    <b>Perché conviene a te</b>
    <p>Sai che il cliente ha già pagato <em>prima</em> di consegnare. Niente più «ti pago dopo» e niente rischio di non essere pagato.</p>
</div>

<h2>Come si attiva (5 minuti)</h2>
<ol>
    <li>Entra nella tua area e apri <strong>Servizi</strong> o <strong>Prodotti</strong>.</li>
    <li>Premi <strong>«Collega i pagamenti protetti»</strong>.</li>
    <li>Si apre la procedura di <strong>Stripe</strong>, il nostro partner per i pagamenti: inserisci i dati dell'attività (o i tuoi, se sei un privato), un documento d'identità e dove vuoi ricevere i soldi (IBAN o carta di debito).</li>
    <li>Quando Stripe ha verificato tutto, in Hub Core compare «Collegato».</li>
</ol>

<h2>Cosa ti serve</h2>
<ul>
    <li>Un documento d'identità valido e il codice fiscale.</li>
    <li>Un <strong>IBAN</strong> intestato a te o alla tua attività (un conto corrente, una carta con IBAN o un conto online vanno bene: vedi la guida su <a href="{{ route('guides.show', 'carte-e-conti-online') }}">carte e conti online</a>).</li>
    <li>Se hai partita IVA, i dati dell'attività.</li>
</ul>

<h2>Quanto costa</h2>
<p>Stripe trattiene una commissione su ogni pagamento con carta: indicativamente <strong>{{ $fees::rateLabel() }}</strong> (di più per carte extra-UE). Esempio: su {{ $ex['amount'] }} € la trattenuta è circa {{ $ex['fee'] }} € e ricevi circa <strong>{{ $ex['net'] }} €</strong>. Le tariffe possono essere diverse da queste: vedi le <a href="{{ route('terms.economic') }}">condizioni economiche</a>.</p>

<h2>E se voglio anche vendere direttamente?</h2>
<p>Puoi: sul tuo sito continui a incassare con il tuo conto, come ti spiegano le guide su <a href="{{ route('guides.show', 'stripe') }}">Stripe</a>, <a href="{{ route('guides.show', 'paypal') }}">PayPal</a> e <a href="{{ route('guides.show', 'bonifico') }}">bonifico</a>. In quel caso la protezione di Hub Core non c'è: ricordati di conservare ricevute e conversazioni.</p>

<a class="btn-main" href="{{ route('registration.create') }}">Inizia gratis</a>
