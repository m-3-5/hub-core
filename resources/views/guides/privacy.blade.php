@extends('guides.layout')

@section('title', 'Privacy')
@section('description', 'Come Hub Core (inm35.it) tratta i dati personali: account, accesso con Google, testi scritti con l\'IA, pagamenti, siti e promo pubblicati.')
@section('back_url', route('guides.index'))
@section('top', 'Privacy')

@php $co = config('company'); @endphp

@section('content')
<h1>Privacy</h1>
<p class="lead">Come trattiamo i tuoi dati quando usi Hub Core su inm35.it. In breve: solo quelli che servono, mai venduti, niente pubblicità che ti segue.</p>

<h2>1. Chi è il titolare</h2>
<p><strong>{{ $co['name'] }}</strong> · P.IVA {{ $co['vat'] }} · {{ $co['seat']['street'] }}, {{ $co['seat']['zip'] }} {{ $co['seat']['city'] }} ({{ $co['seat']['province'] }}) · email <a href="mailto:{{ $co['email'] }}">{{ $co['email'] }}</a> · PEC {{ $co['pec'] }}.<br>
Non abbiamo nominato un Responsabile della protezione dei dati (DPO), perché non è obbligatorio per la nostra attività: per ogni domanda scrivi ai recapiti qui sopra.</p>

<h2>2. Quali dati trattiamo</h2>
<ul>
    <li><strong>Registrazione e account:</strong> nome dell'attività, tipo (azienda, ente, privato), il tuo nome, email, telefono se lo lasci, password (salvata solo in forma cifrata).</li>
    <li><strong>Accesso con Google:</strong> se scegli «Continua con Google» riceviamo da Google solo il tuo <strong>indirizzo email</strong> e la conferma che è verificato. Non riceviamo la password di Google, i contatti né altri dati del tuo account Google.</li>
    <li><strong>Contenuti che pubblichi:</strong> testi, foto, prezzi e contatti di promo, servizi, prodotti, annunci e del tuo sito. Sono pubblici per scelta tua.</li>
    <li><strong>Testi scritti o controllati con l'IA:</strong> quando chiedi all'IA di scrivere i testi del sito o di leggere una foto, e quando controlliamo che un contenuto sia serio prima di pubblicarlo, quei testi o foto vengono inviati a Google (Gemini) per essere elaborati.</li>
    <li><strong>Messaggi e richieste:</strong> quello che scrivi a noi o alle attività tramite i moduli e la chat.</li>
    <li><strong>Pagamenti:</strong> carta, IBAN e dati di pagamento li inserisci tu sulle pagine sicure di Stripe. Noi non vediamo il numero completo della carta: teniamo solo identificativi tecnici e l'esito dei pagamenti.</li>
    <li><strong>Dati tecnici:</strong> indirizzo IP, data e ora delle richieste e cookie tecnici indispensabili (per restare collegato), nei registri del server per sicurezza e antispam.</li>
</ul>
<p>Non usiamo cookie di profilazione né strumenti di pubblicità che ti seguono.</p>

<h2>3. Perché e con quale base giuridica</h2>
<ul>
    <li>Darti il servizio che hai chiesto (account, pubblicazioni, sito, pagamenti, assistenza): art. 6.1.b GDPR.</li>
    <li>Obblighi contabili e fiscali: art. 6.1.c GDPR.</li>
    <li>Sicurezza, blocco di spam e contenuti vietati, recupero di somme dovute: nostro legittimo interesse, art. 6.1.f GDPR.</li>
</ul>
<p>Non facciamo marketing senza un tuo consenso separato e non prendiamo decisioni automatiche che ti riguardano: il controllo dell'IA può solo fermare una pubblicazione, e puoi sempre scriverci.</p>

<h2>4. Per quanto tempo</h2>
<ul>
    <li>Account e contenuti: finché usi Hub Core; i siti di chi non paga o non usa vengono tolti dopo un avviso.</li>
    <li>Richieste e messaggi: al massimo 24 mesi dall'ultimo contatto, salvo contratto.</li>
    <li>Fatture e documenti contabili: 10 anni, come vuole la legge.</li>
    <li>Registri tecnici: di norma non oltre 12 mesi.</li>
</ul>

<h2>5. A chi li comunichiamo</h2>
<p>I tuoi dati non vengono mai venduti. Li trattano i collaboratori autorizzati di {{ $co['name'] }} e, per la loro parte:</p>
<ul>
    <li>Google (accesso con Google; IA Gemini per scrivere e controllare i testi);</li>
    <li>Stripe Payments Europe, Ltd. (pagamenti);</li>
    <li>fornitori di hosting, server e posta elettronica del sito;</li>
    <li>commercialista e consulenti, per gli obblighi fiscali;</li>
    <li>autorità pubbliche, quando la legge lo richiede.</li>
</ul>
<p>Alcuni fornitori (come Google e Stripe) possono trattare dati fuori dallo Spazio economico europeo, con le garanzie previste dal GDPR (ad esempio le clausole contrattuali standard della Commissione europea).</p>

<h2>6. I tuoi diritti</h2>
<p>Puoi chiedere in ogni momento di vedere, correggere, cancellare o portare via i tuoi dati, limitarne l'uso o opporti (articoli 15-22 GDPR). Scrivi a <a href="mailto:{{ $co['email'] }}">{{ $co['email'] }}</a> o via PEC a {{ $co['pec'] }}. Puoi anche fare reclamo al Garante per la protezione dei dati personali (<a href="https://www.garanteprivacy.it" target="_blank" rel="noopener">garanteprivacy.it</a>).</p>

<h2>7. Modifiche</h2>
<p>Aggiorniamo questa pagina quando il servizio cambia. Ultimo aggiornamento: 4 novembre 2026. Vedi anche le <a href="{{ route('terms.economic') }}">Condizioni economiche</a>.</p>
<p style="color:#6b6b7b;font-size:.9rem">Informativa scritta in buona fede su come il sito funziona oggi. Non sostituisce una consulenza legale: per esigenze specifiche rivolgiti a un consulente privacy.</p>
@endsection
