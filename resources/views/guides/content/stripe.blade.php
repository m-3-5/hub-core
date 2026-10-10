@php $fees = \M35\HubPayments\Support\StripeFees::class; @endphp
<h2>Cos'è</h2>
<p>Stripe è un servizio che permette di incassare con carte di credito, debito e prepagate (e altri metodi, come Apple Pay e Google Pay). I soldi arrivano sul tuo conto bancario. Hub Core lo collega ai tuoi prodotti e servizi creando i link di pagamento al posto tuo.</p>

<h2>Come aprire un conto Stripe</h2>
<ol>
    <li>Vai su <a href="https://dashboard.stripe.com/register" target="_blank" rel="noopener">dashboard.stripe.com/register</a> (sito ufficiale) e crea l'account con la tua email.</li>
    <li>Scegli <strong>Italia</strong> come paese e conferma l'email.</li>
    <li>Compila i dati dell'attività: se sei un privato o un libero professionista puoi indicare te stesso, se hai un'impresa i dati della società.</li>
    <li>Carica un documento d'identità e aggiungi l'<strong>IBAN</strong> dove vuoi ricevere i soldi.</li>
    <li>Attiva la <strong>verifica in due passaggi</strong> per proteggere l'account.</li>
</ol>

<h2>Dove trovo le chiavi da inserire in Hub Core</h2>
<ol>
    <li>In Stripe apri <strong>Sviluppatori → Chiavi API</strong>.</li>
    <li>Copia la <strong>Secret key</strong> (inizia con <code>sk_live_</code> quando sei in modalità reale) e incollala nella pagina Servizi di Hub Core, nella sezione «vendite dirette sul tuo sito».</li>
    <li>Non condividere mai quella chiave con nessuno: chi la ha può muovere soldi sul tuo conto.</li>
</ol>

<h2>Suggerimenti utili</h2>
<ul>
    <li>Prova prima in <strong>modalità test</strong> di Stripe: puoi fare pagamenti finti senza rischi.</li>
    <li>Controlla la pagina <a href="https://stripe.com/it/pricing" target="_blank" rel="noopener">stripe.com/it/pricing</a> per le tariffe aggiornate.</li>
    <li>Imposta un'email di ricevuta per i clienti e il nome che compare sull'estratto conto della carta.</li>
    <li>I bonifici verso il tuo conto arrivano con tempi che dipendono dalla banca e dal tuo storico: i primi possono richiedere qualche giorno in più.</li>
</ul>

<div class="box">
    <b>Quanto costa</b>
    <p>Stripe trattiene su ogni pagamento indicativamente <strong>{{ $fees::rateLabel() }}</strong>. Può essere diverso (carte extra-UE, altri metodi): vedi le <a href="{{ route('terms.economic') }}">condizioni economiche</a>.</p>
</div>

<div class="box warn">
    <b>Vendita diretta</b>
    <p>Con il tuo Stripe incassi subito e gestisci tu rimborsi e contestazioni: Hub Core non interviene. Se vuoi essere tutelato e tutelare chi compra, usa i <a href="{{ route('guides.show', 'pagamenti-protetti') }}">pagamenti protetti</a>.</p>
</div>

<a class="btn-ghost" href="https://dashboard.stripe.com/register" target="_blank" rel="noopener">Apri un conto Stripe ↗</a>
