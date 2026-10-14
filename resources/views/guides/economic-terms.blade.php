@extends('guides.layout')

@section('title', 'Condizioni economiche')
@section('description', 'Costi e trattenute di Hub Core, di Stripe e dei servizi di pagamento: cosa paghi, quando ricevi i soldi e cosa può cambiare.')
@section('back_url', route('guides.index'))
@section('top', 'Condizioni')

@php
    $fees = \M35\HubPayments\Support\StripeFees::class;
    $ex = $fees::example();
    $hb = config('services.hub_billing');
@endphp

@section('content')
<h1>Condizioni economiche</h1>
<p class="lead">Qui spieghiamo in chiaro quanto costa usare Hub Core e vendere con i pagamenti. Le cifre dei servizi esterni sono <strong>indicative</strong>.</p>

<h2>1. Abbonamento Hub Core</h2>
<ul>
    <li><strong>Privati:</strong> gratis.</li>
    <li><strong>Aziende ed enti:</strong> {{ $hb['free_days'] }} giorni gratis senza carta; poi l'offerta di lancio di {{ $hb['launch_offer_price_eur'] }} €; dopo altri {{ $hb['trial_days'] }} giorni di prova parte il canone di {{ $hb['monthly_price_eur'] }} €/mese o {{ $hb['annual_price_eur'] }} €/anno. Puoi disdire quando vuoi.</li>
    <li>Moduli, quote incluse ed extra: vedi la pagina <a href="{{ route('pricing.show') }}">Prezzi</a>. Prezzi IVA esclusa.</li>
</ul>

<h2>2. Trattenute di Stripe sui pagamenti con carta</h2>
<p>Quando un cliente paga con carta, Stripe (il servizio che gestisce i pagamenti) applica una commissione. Può essere <strong>quella che indichiamo o diversa</strong>: la decide Stripe e può cambiare per tipo di carta, paese, metodo di pagamento e nel tempo.</p>
<table>
    <tr><th>Voce</th><th>Indicazione</th></tr>
    <tr><td>Carte europee</td><td>indicativamente {{ $fees::rateLabel() }}</td></tr>
    <tr><td>Carte extra-UE, altre valute, altri metodi</td><td>possono costare di più</td></tr>
    <tr><td>Bonifici verso il tuo conto e gestione del conto Stripe collegato</td><td>possono esserci piccoli costi, visibili nella tua pagina Stripe</td></tr>
</table>
<p>Esempio: vendita da {{ $ex['amount'] }} € → trattenuta circa {{ $ex['fee'] }} € → ricevi circa {{ $ex['net'] }} €. Le tariffe ufficiali sono su <a href="https://stripe.com/it/pricing" target="_blank" rel="noopener">stripe.com/it/pricing</a>. La trattenuta viene sottratta dall'incasso e nel pannello vedi sempre l'importo netto.</p>

<h2>3. Pagamenti protetti Hub Core</h2>
<ul>
    <li>Il cliente paga tramite Hub Core; i soldi sono trattenuti fino alla consegna.</li>
    <li>Il venditore li riceve quando il cliente preme «Ho ricevuto tutto», oppure dopo <strong>7 giorni</strong> senza segnalazioni: il rilascio è automatico (controllo ogni ora).</li>
    <li>Prima di pagare, <strong>il compratore accetta le «<a href="{{ route('terms.buyer') }}">condizioni di acquisto protetto</a>»</strong> (le stesse che leggi qui in sintesi): sa che i soldi sono custoditi da Hub Core, come confermare la ricezione e come segnalare un problema. Registriamo versione, data e indirizzo IP della sua accettazione.</li>
    <li>Se il cliente segnala un problema il pagamento si ferma finché non è risolto; il venditore e il team Hub Core vengono avvisati subito per email.</li>
    <li><strong>Cosa ricevi:</strong> l'importo pagato meno la commissione effettiva di Stripe sul pagamento e l'eventuale commissione Hub Core, calcolati al momento del rilascio sui valori reali di Stripe (non sulla stima). L'importo è accreditato sul tuo conto Stripe collegato e Stripe lo bonifica al tuo IBAN con i suoi tempi (di solito pochi giorni lavorativi).</li>
    <li><strong>Commissione Hub Core:</strong> può essere prevista sulle vendite fatte tramite inm35.it, a carico dell'attività e <em>senza cambiare il prezzo pagato dal cliente</em>. Al momento è <strong>0 €</strong>: se cambiasse, lo comunicheremo prima.</li>
</ul>

<h2>4. Chi sopporta i costi (da leggere con attenzione)</h2>
<p>Hub Core non anticipa e non sopporta alcun costo delle vendite fatte dalle attività. <strong>Tutti i costi generati dalle tue vendite sono a tuo carico</strong> e vengono scalati dai tuoi incassi:</p>
<ul>
    <li><strong>Commissioni di Stripe</strong> su ogni pagamento (voce 2), anche quando la tariffa applicata è diversa da quella indicativa.</li>
    <li><strong>Rimborsi:</strong> se un cliente ha diritto al rimborso (merce non consegnata o non conforme, annullamento, recesso o altro motivo), gli rimborsiamo l'intero importo pagato. La commissione di Stripe di norma non viene restituita: resta a tuo carico, e il rimborso viene scalato dai tuoi incassi.</li>
    <li><strong>Contestazioni con la carta (chargeback):</strong> se la banca del cliente apre una contestazione il rilascio si ferma automaticamente; se la perdi, l'importo contestato e il costo che Stripe applica a ogni contestazione sono a tuo carico. Ti chiederemo i documenti per difenderti (prova di consegna, comunicazioni con il cliente).</li>
    <li><strong>Recupero delle somme:</strong> se un rimborso o una contestazione arriva dopo che i soldi ti sono già stati accreditati, autorizzi Hub Core a recuperarli annullando il trasferimento, trattenendoli dai tuoi incassi successivi e, se non bastano, a chiederti di restituirli entro 15 giorni. Fino al saldo possiamo sospendere i pagamenti protetti.</li>
    <li><strong>Segnalazioni e blocchi:</strong> se un cliente segnala un problema, il pagamento resta fermo finché non è risolto. Ti impegni a collaborare e a rispondere al cliente e al team Hub Core in modo tempestivo (entro 3 giorni lavorativi). Se la segnalazione è fondata e non viene risolta, il cliente viene rimborsato e il rimborso è a tuo carico.</li>
    <li><strong>Obblighi di consegna:</strong> consegni o eroghi quanto venduto nei tempi indicati nella tua pagina, e rispetti garanzia legale, diritto di recesso e informazioni al consumatore. Premere «Ho ricevuto tutto» o il passare dei giorni non ti esonera da questi obblighi.</li>
    <li><strong>Uso corretto:</strong> puoi usare i pagamenti protetti solo per vendere beni e servizi leciti, tuoi, descritti in modo veritiero. Possiamo sospendere i pagamenti protetti e trattenere le somme in caso di sospetta frode o uso scorretto.</li>
    <li><strong>Nessuna sorpresa per il cliente:</strong> il prezzo che vede il cliente è quello che hai impostato; non gli aggiungiamo commissioni.</li>
</ul>
<p>Accettando queste condizioni prima di collegare i pagamenti protetti, confermi di averle lette e capite. Registriamo data, versione ({{ \M35\HubPayments\Support\SellerTerms::VERSION }}) e utente che accetta. Se le condizioni cambiano in modo rilevante ti avvisiamo con almeno 30 giorni di anticipo e ti chiediamo di accettare di nuovo.</p>

<h2>5. Se vendi come privato con un annuncio</h2>
<ul>
    <li>Anche i privati possono vendere <strong>oggetti</strong> con un annuncio e il pulsante «Compra con pagamento protetto»: valgono le stesse regole di questa pagina (pagamento custodito, rilascio dopo la conferma o i {{ \M35\HubPayments\Support\ProtectedCheckout::holdDays() }} giorni, costi a tuo carico).</li>
    <li><strong>Cosa si può vendere:</strong> oggetti leciti, tuoi, descritti in modo veritiero. Sono vietati armi, droghe, farmaci, animali vivi, prodotti contraffatti o rubati, contenuti per adulti e tutto ciò che la legge vieta di vendere. Non è possibile vendere immobili con il pagamento protetto (restano i contatti diretti).</li>
    <li><strong>Vendita occasionale:</strong> vendere di tanto in tanto oggetti usati è un'attività non professionale. Se vendi in modo continuativo o a scopo di guadagno, o oltre le soglie fissate dalla legge, devi aprire una partita IVA e rispettare gli obblighi fiscali: la valutazione e la responsabilità sono tue (per dubbi, rivolgiti a un commercialista).</li>
    <li><strong>Verifica dell'identità:</strong> per ricevere i soldi Stripe ti chiede documento e IBAN. Le piattaforme possono essere obbligate a comunicare all'Agenzia delle Entrate i dati dei venditori e dei loro incassi: ci impegniamo a farlo quando richiesto.</li>
    <li><strong>Consegna:</strong> concordi tu con l'acquirente ritiro o spedizione. Prima della conferma o della scadenza dei giorni non ricevi i soldi, quindi spedisci con tracciamento e conserva la prova di consegna.</li>
    <li><strong>Tutela dell'acquirente:</strong> poiché vendi come privato non si applicano garanzia legale e recesso da consumatore, ma l'acquirente è protetto dal pagamento custodito: se non riceve l'oggetto o è diverso da come descritto, viene rimborsato a tuo carico.</li>
</ul>

<h2>6. Vendite dirette (fuori da Hub Core)</h2>
<p>Puoi anche incassare sul tuo sito con il tuo conto Stripe, con PayPal o con bonifico, come spiegato nelle <a href="{{ route('guides.index') }}">guide</a>. In quel caso i costi sono quelli del servizio che scegli, la protezione di Hub Core non c'è e rimborsi, contestazioni e ricevute sono a tuo carico.</p>

<h2>7. Servizi di terzi</h2>
<p>PayPal, banche, conti online e carte hanno le loro tariffe e condizioni, che non dipendono da noi e possono cambiare: controlla sempre il sito ufficiale.</p>

<h2>8. Tasse, fatture e obblighi di vendita</h2>
<p>Gli importi di Hub Core sono IVA esclusa. Le tasse, le fatture, le ricevute e gli obblighi verso i consumatori (informazioni sul prodotto, garanzia legale, diritto di recesso di 14 giorni nelle vendite a distanza quando previsto) restano di tua responsabilità: per dubbi rivolgiti a un commercialista. Per le piattaforme come Hub Core può esserci l'obbligo di comunicare all'Agenzia delle Entrate i dati dei venditori e dei loro incassi: ci impegniamo a farlo quando richiesto.</p>

<p class="small">Versione {{ \M35\HubPayments\Support\SellerTerms::VERSION }}. Per domande chiedi a Max o scrivici su WhatsApp.</p>
@endsection
