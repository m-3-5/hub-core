@extends('guides.layout')

@section('title', 'Condizioni di acquisto protetto')
@section('description', 'Come funziona l\'acquisto con pagamento protetto di Hub Core: chi custodisce i soldi, quando li riceve il venditore, come segnalare un problema e come ottenere il rimborso.')
@section('back_url', route('guides.index'))
@section('top', 'Condizioni')

@php $days = \M35\HubPayments\Support\ProtectedCheckout::holdDays(); @endphp

@section('content')
<h1>Condizioni di acquisto protetto</h1>
<p class="lead">Quando compri da un venditore su inm35.it con il pulsante «Pagamento protetto» ti tuteliamo fino alla consegna. Qui spieghiamo come funziona, in modo semplice.</p>

<h2>1. Chi è chi</h2>
<ul>
    <li><strong>Il venditore</strong> è l'attività che ti vende il prodotto o il servizio: è lei a consegnarlo e a rispondere della sua qualità, delle informazioni e della garanzia legale.</li>
    <li><strong>Hub Core (M 3.5 S.R.L.)</strong> mette a disposizione la piattaforma e custodisce il tuo pagamento fino alla consegna, tramite Stripe. Non è il venditore.</li>
    <li><strong>Stripe</strong> è il servizio che incassa il pagamento e lo trasferisce al venditore. I dati della tua carta li gestisce Stripe: non li vediamo né li conserviamo.</li>
</ul>

<h2>2. Come funziona il pagamento</h2>
<ol>
    <li>Paghi con carta (o con il metodo mostrato da Stripe): l'importo è quello che vedi sulla pagina, senza commissioni aggiunte.</li>
    <li>I soldi restano custoditi da Hub Core: il venditore non li riceve subito.</li>
    <li>Il venditore consegna il prodotto o eroga il servizio.</li>
    <li>Quando hai ricevuto quanto acquistato premi <strong>«Ho ricevuto tutto»</strong> nella pagina del tuo ordine: i soldi vengono consegnati al venditore. Se non premi nulla e non segnali problemi, vengono consegnati automaticamente dopo <strong>{{ $days }} giorni</strong>.</li>
</ol>

<h2>3. Se qualcosa non va</h2>
<ul>
    <li>Dalla pagina del tuo ordine (il link è nell'email di conferma) puoi premere <strong>«Segnala un problema»</strong> e spiegare cosa non va. Il pagamento si ferma subito e nessuno lo riceve finché non è risolto.</li>
    <li>Contattiamo il venditore e cerchiamo una soluzione (consegna, sostituzione, accordo). Se la segnalazione è fondata e non viene risolta, <strong>ti rimborsiamo l'intero importo pagato</strong>.</li>
    <li>Segnala <strong>entro i {{ $days }} giorni</strong> dal pagamento (prima che i soldi vengano consegnati). Dopo, scrivici comunque: valutiamo ogni caso, ma il rimborso dipende dal venditore.</li>
    <li>Il rimborso torna sulla carta usata per pagare; i tempi di accredito dipendono dalla tua banca.</li>
</ul>

<h2>4. I tuoi diritti di consumatore</h2>
<p>Se acquisti come consumatore da un venditore professionista valgono le regole di legge: informazioni chiare sul prodotto, <strong>garanzia legale di conformità</strong> e, per gli acquisti a distanza, <strong>diritto di recesso di 14 giorni</strong> dove previsto (non vale, ad esempio, per servizi già erogati su tua richiesta o per prodotti personalizzati). Il venditore è responsabile di rispettarli e te li indica nella sua pagina; il pagamento protetto <em>si aggiunge</em> a questi diritti, non li sostituisce.</p>

<h2>5. Se il venditore è un privato</h2>
<p>Negli annunci puoi comprare anche da un <strong>privato</strong> (lo trovi indicato sulla pagina). Tra privati <strong>non valgono</strong> la garanzia legale di conformità e il recesso previsti per i consumatori verso i professionisti: controlla bene l'oggetto e le foto prima di pagare. Resta però la protezione di Hub Core: il pagamento è custodito, puoi segnalare un problema e, se non ricevi l'oggetto o è diverso da come descritto, <strong>ti rimborsiamo</strong>.</p>

<h2>6. Costi</h2>
<p>Per te nessun costo in più: paghi il prezzo indicato. Le commissioni di Stripe e di Hub Core sono a carico del venditore.</p>

<h2>7. I tuoi dati</h2>
<p>Per gestire l'ordine usiamo nome, email e telefono che inserisci al pagamento: li comunichiamo al venditore (per consegna o prenotazione) e a Stripe (per il pagamento). Conserviamo i dati dell'ordine e la tua accettazione di queste condizioni (data, versione e indirizzo IP) per gestire eventuali segnalazioni.</p>

<h2>8. Accettazione</h2>
<p>Prima di pagare ti chiediamo di spuntare la casella «Ho letto e accetto le condizioni di acquisto protetto». Registriamo la versione di queste condizioni ({{ \M35\HubPayments\Support\BuyerTerms::VERSION }}), la data e l'indirizzo IP.</p>

<p class="small">Versione {{ \M35\HubPayments\Support\BuyerTerms::VERSION }} · Vendi anche tu? Leggi le <a href="{{ route('terms.economic') }}">condizioni economiche per chi vende</a>. Per domande scrivici su WhatsApp o chiedi a Max.</p>
@endsection
