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
    <li>Il venditore li riceve quando il cliente conferma, oppure dopo <strong>7 giorni</strong> senza segnalazioni.</li>
    <li>Se il cliente segnala un problema il pagamento si ferma finché non è risolto.</li>
    <li><strong>Commissione Hub Core:</strong> può essere prevista sulle vendite fatte tramite inm35.it, a carico dell'attività e <em>senza cambiare il prezzo pagato dal cliente</em>. Al momento è <strong>0 €</strong>: se cambiasse, lo comunicheremo prima.</li>
    <li>Il venditore riceve l'importo pagato dal cliente <strong>meno</strong> la commissione effettiva di Stripe e l'eventuale commissione Hub Core. Nel pannello vedi sempre il netto di ogni vendita.</li>
</ul>

<h2>4. Chi sopporta i costi (da leggere con attenzione)</h2>
<p>Hub Core non anticipa e non sopporta alcun costo delle vendite fatte dalle attività. <strong>Tutti i costi generati dalle tue vendite sono a tuo carico</strong> e vengono scalati dai tuoi incassi:</p>
<ul>
    <li><strong>Commissioni di Stripe</strong> su ogni pagamento (voce 2), anche quando la tariffa applicata è diversa da quella indicativa.</li>
    <li><strong>Rimborsi:</strong> se un cliente ha diritto al rimborso (merce non consegnata o non conforme, annullamento, recesso o altro motivo), gli rimborsiamo l'intero importo pagato. La commissione di Stripe di norma non viene restituita: resta a tuo carico, e il rimborso viene scalato dai tuoi incassi.</li>
    <li><strong>Contestazioni con la carta (chargeback):</strong> l'importo contestato e il costo che Stripe applica a ogni contestazione sono a tuo carico. Ti chiederemo i documenti per difenderti (prova di consegna, comunicazioni con il cliente).</li>
    <li><strong>Recupero delle somme:</strong> se un rimborso o una contestazione arriva dopo che i soldi ti sono già stati accreditati, autorizzi Hub Core a recuperarli annullando il trasferimento, trattenendoli dai tuoi incassi successivi e, se non bastano, a chiederti di restituirli entro 15 giorni. Fino al saldo possiamo sospendere i pagamenti protetti.</li>
    <li><strong>Segnalazioni e blocchi:</strong> se un cliente segnala un problema, il pagamento resta fermo finché non è risolto. Se la segnalazione è fondata e non risolta, il cliente viene rimborsato.</li>
    <li><strong>Nessuna sorpresa per il cliente:</strong> il prezzo che vede il cliente è quello che hai impostato; non gli aggiungiamo commissioni.</li>
</ul>
<p>Accettando queste condizioni prima di collegare i pagamenti protetti, confermi di averle lette e capite. Registriamo data, versione ({{ \M35\HubPayments\Support\SellerTerms::VERSION }}) e utente che accetta. Se le condizioni cambiano in modo rilevante ti avvisiamo con almeno 30 giorni di anticipo e ti chiediamo di accettare di nuovo.</p>

<h2>5. Vendite dirette (fuori da Hub Core)</h2>
<p>Puoi anche incassare sul tuo sito con il tuo conto Stripe, con PayPal o con bonifico, come spiegato nelle <a href="{{ route('guides.index') }}">guide</a>. In quel caso i costi sono quelli del servizio che scegli, la protezione di Hub Core non c'è e rimborsi, contestazioni e ricevute sono a tuo carico.</p>

<h2>6. Servizi di terzi</h2>
<p>PayPal, banche, conti online e carte hanno le loro tariffe e condizioni, che non dipendono da noi e possono cambiare: controlla sempre il sito ufficiale.</p>

<h2>7. Tasse, fatture e obblighi di vendita</h2>
<p>Gli importi di Hub Core sono IVA esclusa. Le tasse, le fatture, le ricevute e gli obblighi verso i consumatori (informazioni sul prodotto, garanzia legale, diritto di recesso di 14 giorni nelle vendite a distanza quando previsto) restano di tua responsabilità: per dubbi rivolgiti a un commercialista. Per le piattaforme come Hub Core può esserci l'obbligo di comunicare all'Agenzia delle Entrate i dati dei venditori e dei loro incassi: ci impegniamo a farlo quando richiesto.</p>

<p class="small">Versione {{ \M35\HubPayments\Support\SellerTerms::VERSION }}. Per domande chiedi a Max o scrivici su WhatsApp.</p>
@endsection
