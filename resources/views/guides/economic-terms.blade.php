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
    <li>Se il cliente segnala un problema il pagamento si ferma finché non è risolto; in caso di rimborso la trattenuta può non essere recuperabile.</li>
    <li><strong>Commissione Hub Core:</strong> può essere prevista sulle vendite fatte tramite inm35.it, a carico dell'attività e <em>senza cambiare il prezzo pagato dal cliente</em>. Al momento è <strong>0 €</strong>: se cambiasse, lo comunicheremo prima.</li>
</ul>

<h2>4. Vendite dirette (fuori da Hub Core)</h2>
<p>Puoi anche incassare sul tuo sito con il tuo conto Stripe, con PayPal o con bonifico, come spiegato nelle <a href="{{ route('guides.index') }}">guide</a>. In quel caso i costi sono quelli del servizio che scegli, la protezione di Hub Core non c'è e rimborsi, contestazioni e ricevute sono a tuo carico.</p>

<h2>5. Servizi di terzi</h2>
<p>PayPal, banche, conti online e carte hanno le loro tariffe e condizioni, che non dipendono da noi e possono cambiare: controlla sempre il sito ufficiale.</p>

<h2>6. Tasse e fatture</h2>
<p>Gli importi di Hub Core sono IVA esclusa. Le tasse, le fatture e le ricevute sulle tue vendite restano di tua responsabilità: per dubbi rivolgiti a un commercialista.</p>

<p class="small">Ultimo aggiornamento: {{ now()->locale('it')->translatedFormat('d F Y') }}. Per domande chiedi a Max o scrivici su WhatsApp.</p>
@endsection
