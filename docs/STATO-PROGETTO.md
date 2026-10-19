# Stato del progetto Hub Core (documento vivo)

> Aggiornato: **2026-10-14** (pezzo 3 dei pagamenti protetti). Si aggiorna a ogni pezzo consegnato (commit su `hub-core`).
> Contesto tecnico e architettura: `CONTESTO-PROGETTO.md` · roadmap lunga: `PIANO-SVILUPPO.md` · pagamenti: `MODULO-PAGAMENTI.md` · deploy: `DEPLOY-PLESK.md`.

## 1. In produzione su inm35.it (verificato)

| Area | Cosa c'è | Note |
|------|----------|------|
| SEO / indicizzazione | sitemap, llms.txt, IndexNow, JSON-LD | Search Console: sitemap «non recuperabile» (transitorio), pagine chiave inviate |
| Landing siti web | offerta «1 € per partire» (29/59/99 €/mese × 10), pagamento 1 € reale, lead in admin | Terms in `LANDING_START_TERMS`; prova pagamento 1 € da fare |
| Admin a card immagine | promo e annunci come card uguali con X / matita | altre pagine da convertire (clienti, ordini, preventivi) |
| Marchio | logo + favicon di piramide35.com su tutte le pagine | |
| Registrazione a passi | wizard `/registrati`, ospite → registrazione all'ultimo passo | |
| Controllo IA ospiti | Gemini blocca volgare/violenza/terrorismo (se IA non risponde: non pubblica) | solo promo ospiti, non ancora tutte le pubblicazioni |
| Chat Max pubblica | in basso a destra, risposte IA + ripiego senza IA | |
| Prima settimana gratis | 7 gg senza carta, poi 1 € (se non paga, non succede nulla), poi prova, poi canone | email promemoria con comando giornaliero |
| Pagina prezzi | rifatta, con «Indietro», FAQ trattenute Stripe | |
| Guide pagamenti | `/guide/pagamenti` (protetti, Stripe, PayPal, bonifico, carte/conti) + `/condizioni-economiche` | |
| Scelta «come vuoi ricevere i soldi» | si apre quando si inizia a vendere | PayPal/bonifico per ora = richiesta al team |
| **Pagamenti protetti – pezzo 1** | collegamento Stripe Connect (Express) del venditore | Beauty «Collegato» in **modalità di prova** |
| **Pagamenti protetti – pezzo 2** | cassa su inm35.it: paga Hub Core, soldi trattenuti 7 giorni, ordine «Trattenuto», ricevute | commit 711cb02, migrazione eseguita |

## 2. Pagamenti protetti: stato e regole decise

- Modello: il cliente paga Hub Core (Stripe, conto M 3.5); il venditore riceve **dopo conferma del cliente o dopo 7 giorni** senza segnalazioni. Il venditore può sempre vendere anche direttamente (proprie chiavi Stripe, PayPal, bonifico).
- **Tutti i costi sono del venditore** (commissioni Stripe, rimborsi, contestazioni, recupero somme); il prezzo del cliente non cambia. Scritto in «Condizioni economiche» §4; accettazione obbligatoria prima di collegare (registrata in `tenants.settings.seller_terms`, versione `SellerTerms::VERSION`). Se il testo cambia in modo rilevante: alzare la versione.
- Commissione Hub Core: oggi 0 €; sugli ordini protetti si tratterrà al rilascio (non più mensile).
- **Chiave Stripe in produzione = TEST** (`HUB_STRIPE_SECRET_KEY`): tutto il flusso protetto oggi gira in prova. Per incassi veri: chiave live + rifare il collegamento con dati veri.

| Pezzo | Stato |
|-------|-------|
| 1 Collegamento venditore (Connect) | ✅ |
| 2 Cassa + trattenuta + ordine «Trattenuto» + ricevute | ✅ **provato dal vivo il 13/10** (sandbox, 1 €): ordine «Trattenuto fino al 20/10», email al compratore e ai venditori, pagina `/ordine/{codice}`, webhook ok |
| 3 Rilascio ai venditori | ✅ **online e provato dal vivo in sandbox il 14–15/10**: rilascio (1,00 € → commissione Stripe reale 0,28 € → 0,72 € al venditore), «Segnala un problema» (blocca, ticket, email), «Problema risolto», rimborso completo: «Ho ricevuto tutto» (rilascio immediato), comando `hub:release-protected-payments` ogni ora (Transfer = incasso − commissione Stripe reale − commissione Hub Core, idempotente), «Segnala un problema» (blocca + ticket + email), contestazioni/rimborsi da Stripe che bloccano l'ordine, rimborso e ripresa dal pannello team `/admin/protected-orders`, condizioni di acquisto del compratore con accettazione obbligatoria (`/condizioni-acquisto`, registrate su ordine), condizioni venditore v2026-10-14 (da riaccettare) |
| 4 Privati che vendono con un annuncio | ✅ **online e provato dal vivo il 15/10** (privato di prova collegato dalla pagina Annunci, acquisto da annuncio, oggetto «Venduto» e fuori bacheca; dati di prova poi eliminati con `hub:make-test-seller --cleanup`): categoria «Oggetti in vendita» a prezzo fisso, opzione «Vendi con pagamento protetto», pulsante «Compra con pagamento protetto» nella pagina annuncio, ordine → oggetto «Venduto»; il collegamento Stripe e gli ordini sono nella pagina Annunci (non serve il modulo Servizi); condizioni venditore/compratore estese ai privati (v2026-10-15) |
| 5 Rifiniture da decidere | PayPal/bonifico automatici, IA di controllo sugli annunci con vendita, recensioni |

**Chi può vendere con i pagamenti protetti oggi:** aziende ed enti con Servizi/Prodotti; **privati con annunci «Oggetti in vendita»** (non immobili: restano contatti diretti).

## 3. Problemi aperti

1. **Webhook Stripe live: 401.** La Dashboard (live) ha l'endpoint `hub-core-billing-live` → `https://inm35.it/stripe/billing/webhook`. Era 404 dal 7 ottobre; dal 14 ottobre il sito risponde (alias nel codice) ma rifiuta la firma (401): `HUB_STRIPE_WEBHOOK_SECRET` sul server è il segreto dell'endpoint **sandbox** (`exquisite-oasis`, → `/api/stripe/webhook`, 0 errori), non quello live. Soluzione: nel `.env` mettere **entrambi** i segreti separati da virgola: `HUB_STRIPE_WEBHOOK_SECRET=whsec_SANDBOX,whsec_LIVE` (il codice li accetta tutti). Gli eventi falliti sono tutti `checkout.session.expired` (innocui). Stripe disattiva l'endpoint il 16 ottobre 2026 se continua a fallire.
2. **Il server usa chiavi Stripe di prova** (`HUB_STRIPE_SECRET_KEY` = test + segreto webhook sandbox). Tutti i pagamenti protetti oggi girano in sandbox. Per il live: chiave live + segreto live (già accettato in virgola) + ripetere il collegamento dei venditori con dati veri.
3. Sicurezza corretta il 14 ottobre: il webhook di fatturazione ignora le sessioni senza `metadata.tenant_id` (prima usava `client_reference_id`, che nelle vendite dirette dei venditori è un numero d'ordine e poteva coincidere con un id di tenant).

## 4. Da fare per l'utente (non automatizzabili)

- ~~Beauty: accettazione delle condizioni economiche~~ fatta il 13/10.
- Eliminare gli ordini/prodotti di prova «Prova carrello (da eliminare)».
- Commercialista/legale: IVA/fattura sulla commissione, DAC7, recesso 14 giorni, clausola «recupero somme».
- Prova pagamento reale 1 € (preventivo e landing), Bing Webmaster Tools (login Microsoft), nome del gruppo Facebook (`LANDING_FB_GROUP_NAME`; suggerito «Sibaritide Digitale – Attività, Web e Idee»), eliminare i prodotti di prova su inm35.it.
- Verificare tariffe Stripe reali (default 1,5% + 0,25 €: `HUB_STRIPE_FEE_PERCENT` / `HUB_STRIPE_FEE_FIXED_CENTS`).

## 5. Idee in coda

- IA di controllo contenuti su tutte le pubblicazioni dei tenant; Max IA dentro l'app (loggati).
- Altre pagine admin image-first; creazione sito con IA per l'offerta 1 € (foto a tema dei clienti).
- Commissione «a carico del compratore» (stile Subito/Vinted): scartata per ora, valutare con commercialista.

## 6. Come si rilascia (promemoria)

1. `php artisan test` in locale → commit → `git push origin master`.
2. Plesk → Laravel Toolkit → Deployment → **Deploy** (verificare «Last commit» nella Dashboard: a volte il primo clic non parte).
3. Artisan: `migrate --force` (se ci sono migrazioni), `optimize:clear`, `route:cache`.
4. Controllare una pagina nuova online.

## 7. Regola di grafica (decisione dell'utente, 15/10)
Le pagine per chi naviga da cellulare devono essere **essenziali e da vera app**: barra in alto con nome e stato, un'unica schermata di stato con icona grande, azioni in una **barra fissa in basso** con tasti da almeno 48 px, gutter da 16 px, nessun scroll orizzontale, supporto «safe area» di iPhone e tema scuro. Già applicata a: pagina ordine del compratore (`/ordine/{codice}`), pagina annuncio (barra «prezzo + Compra»), pagina prodotto/servizio (barra d'acquisto fissa), guide e condizioni (layout `guides.layout`). Da estendere alle altre pagine pubbliche e admin quando si toccano.

## 8. Prossimi passi (piano concordato il 15/10)

### A. Grafica da app anche nelle pagine admin — ✅ fatto il 15/10 (commit d6a3a81, online; rifinitura c8654d5 da deployare)
Implementata in `layouts/partials/app-mobile.blade.php` (classi `.chips`, `.fab-bar`, `.tiles`, `.rtable`), inclusa dai layout admin e app; verificata a 390 px su dashboard, servizi, ordini, annunci, pagamenti protetti. Tema scuro nell'admin non fatto (colori inline). Testo originale del piano:
Applicare la regola del §7 a: home admin (`resources/views/admin/dashboard.blade.php`, `layouts/admin.blade.php`), app tenant (`layouts/app.blade.php`), ordini, servizi/prodotti, annunci, pagamenti protetti (`/admin/protected-orders`), scheda «Pagamenti protetti» e «Come vuoi ricevere i soldi?». Linee guida: barra in alto compatta, voci principali come **card/tessere grandi con icona**, azioni primarie in barra fissa in basso, tabelle trasformate in **liste a card** su cellulare, tasti ≥ 48 px, safe area iPhone. Verificare a 390 px (iframe) e mantenere i test verdi (le stringhe controllate dai test non vanno rimosse).

### B. Passaggio al live dei pagamenti protetti (da fare insieme, con login dell'utente)
Oggi il server usa chiavi Stripe **di prova** (sandbox). Per incassare soldi veri:
1. **Prima:** commercialista/legale legge condizioni venditore e compratore (§4 e privati). L'utente lo farà quando avrà un commercialista; PDF pronto in `docs/condizioni-pagamenti-protetti.pdf` (si rigenera dalle viste `guides.economic-terms` e `guides.buyer-terms`).
2. Stripe **live**: Connect già configurato (profilo piattaforma inviato l'11/10 sull'account M 3.5); verificare in Connect → Impostazioni che gli account Express siano ammessi e controllare le tariffe reali (aggiornare `HUB_STRIPE_FEE_PERCENT` / `HUB_STRIPE_FEE_FIXED_CENTS` se diverse da 1,5% + 0,25 €).
3. Webhook live `hub-core-billing-live` (→ `https://inm35.it/stripe/billing/webhook`): aggiungere gli eventi `checkout.session.completed`, `charge.dispute.created`, `charge.refunded`, `customer.subscription.updated/deleted` e copiarne il segreto.
4. `.env` su Plesk (lo incolla l'utente, non l'agente): `HUB_STRIPE_SECRET_KEY=sk_live_…` e `HUB_STRIPE_WEBHOOK_SECRET=whsec_LIVE` (si possono tenere più segreti separati da virgola); poi `optimize:clear` + `route:cache`.
5. **I collegamenti creati in sandbox non valgono più**: Beauty e ogni venditore devono ricollegarsi con dati veri. ✅ Comando pronto: `php artisan hub:reset-connect {slug}` (o `--all`, `--dry-run`, `--force`): azzera `tenants.settings.connect` (le condizioni accettate restano) e si rifiuta se ci sono pagamenti ancora trattenuti/bloccati. Serve perché gli `acct_…` di prova non esistono in live.
6. Prova reale a basso importo: 1 € con carta vera dell'utente su un prodotto di prova → controllare ordine, email, «Ho ricevuto tutto», trasferimento e commissione reale; poi rimborso.
7. Pulizia: eliminare il prodotto/ordine «Prova carrello (da eliminare)» di Beauty.

**Da verificare prima del live (costi Connect):** con «separate charges and transfers» la piattaforma (M 3.5) paga a Stripe, oltre alla commissione di incasso che già scaliamo al venditore, i costi di Connect (account attivo/mese e bonifici). Controllare i prezzi Connect del proprio account live e decidere se scalarli al venditore o assorbirli; il testo delle condizioni (§4) promette che i costi delle vendite sono a carico del venditore.

## 9. Passaggio al live — stato del 15/10 (sera)
**Stripe è LIVE sul sito.** Fatto:
- Chiave `sk_live_…` dedicata («hub-core inm35.it», creata il 15/10 nell'account M 3.5) e segreto del webhook live `hub-core-billing-live` salvati nel `.env` di Plesk; cache pulita. Verifica con `php artisan hub:stripe-check` (chiave LIVE accettata, account M 3.5, incassi e bonifici abilitati).
- Dati di prova azzerati con `hub:reset-connect --all` (era solo Beauty) e `hub:reset-billing --all --apply` (era solo Piramide 35). Verificato: nessun dato Stripe di prova rimasto.
- Grafica da app anche nell'admin, footer con i dati di Piramide 35 (config/company.php), PDF delle condizioni in `docs/condizioni-pagamenti-protetti.pdf`.
- Beauty: vendite dirette sul proprio conto Stripe (chiave live propria) **già collegate e non toccate**.

**Da fare (domani):**
1. Collegare i pagamenti protetti di Beauty (Connect, dati veri: documento e IBAN) con «Collega i pagamenti protetti» da `/admin/tenants/beauty-of-image/services`.
2. Portare il prodotto «Prova carrello (da eliminare)» da 1,00 € a 0,50 € e fare il test reale: acquisto con carta, «Ho ricevuto tutto», trasferimento, poi rimborso. Controllare email e consegna del webhook in Stripe (il webhook live non ha più errori 401 se il segreto è giusto).
3. Eliminare il prodotto di prova di Beauty e l'ordine #5.
4. Decidere sui costi Connect a carico della piattaforma (conti attivi/mese e bonifici): vedi nota nel §8.
5. Il commercialista leggerà le condizioni quando ci sarà (PDF pronto).
6. Dubbio aperto: nel corpo della landing siti web compaiono ancora «Ora siamo a Fabrizio» / «Vieni a trovarci a Fabrizio» (la sede legale è a Senise, non in Calabria): da decidere se riscrivere.
7. Bloccato dal classificatore di sicurezza: l'agente non può lanciare comandi che modificano dati di produzione via Artisan; li lancia l'utente da Plesk → Laravel → Artisan.
8. Chiave Stripe «Hub Core» del 25 luglio (mai usata): da eliminare o ruotare nella pagina Chiavi API, se non serve.

## 10. Stato del 15/10 (sera) e prossimo lavoro

### Fatto oggi (tutto online, ultimo commit a0ac03e)
- **Grafica da app nell'admin** (`layouts/partials/app-mobile.blade.php`: `.chips`, `.fab-bar`, `.tiles`, `.rtable`, `.ccard`, `.kpis`, `.rowcards`).
- **Dashboard super admin a card cliente** + **scheda cliente** (`/admin/tenants/{slug}`, `admin.tenants.show`) con indicatori, anteprime e ordini; striscia «Il tuo lavoro in breve» nella home di ogni attività (`App\Support\TenantOverview`).
- **Footer con i dati di Piramide 35** (`config/company.php`, partial `layouts.partials.company-footer`): progetto a brevetto di Antonio Massimo Castronuovo, M 3.5 S.R.L., P.IVA 01999520768, sede legale Senise (PZ) — non in Calabria.
- **Stripe LIVE** attivo sul sito (chiave dedicata + segreto webhook nel `.env`); comandi `hub:stripe-check`, `hub:reset-connect`, `hub:reset-billing`. Dati di prova azzerati.
- **Pagina pubblica promo neutra** (non più testi da centro estetico), SEO aggiornato alla modifica, colore e carattere predefiniti neutri per le nuove attività (indaco, «Moderno»), pulsante «Condividi» nativo.
- **Portale annunci a categorie distinte** (`ClassifiedAd::CATEGORY_META`): Affitto, Case vacanza, Vendita immobili, Oggetti in vendita, Servizi e lezioni, **Libri** (autore + link d'acquisto esterno Amazon, `rel="sponsored nofollow"`). Ogni categoria ha campi, icona e unità di prezzo propri; bacheca con tessere categoria e conteggi; link cliccabili sicuri nelle descrizioni (`App\Support\Linkify`).
- **Messaggi degli annunci** inoltrati all'email di contatto dell'annuncio (mai mostrata) e non agli indirizzi ospite `@guest.hub-core.local`.
- **Pulizia:** eliminati i clienti di prova (Azienda/Ente/Privato Test QA) e il doppione Hazar.
- **Contenuti pubblicati:** Sonia (annuncio bilocale, annuncio e promo «Lezioni scolastiche di Economia e Commercio»), Hazar (traduzioni FR/EN/AR a 18 €/pagina, due libri con link Amazon).

### NUOVO LAVORO DA FARE: rivedere tutta la grafica (richiesta dell'utente, 15/10)
L'utente: «le card vanno bene ma devono essere **più piccole lato desktop**, e il tutto deve essere **molto più comprensibile e ordinato**».
Piano:
1. **Card più compatte su desktop:** ridurre dimensioni e spaziature di `.ccard`, `.tile`, `.kpi`, `.rc`, `.mcard`/`.mgrid`, `.svc-card`, tessere di `layouts/app` (`.module-tile`); più colonne e meno altezza; il layout da telefono resta com'è (verificare a 390 px e a 1280 px).
2. **Gerarchia chiara in ogni pagina admin:** un titolo, una frase, poi le azioni principali; sezioni con titoli coerenti e spaziature uguali; stop a pagine con troppe schede di spiegazione (es. scheda «Pagamenti protetti» e «Trattenute di Stripe»: tenerle compatte e comprimibili).
3. **Linguaggio semplice e coerente:** stesse parole per le stesse cose (Promo / Servizi / Prodotti / Annunci / Ordini / Messaggi), icone sempre uguali, stati con le stesse etichette e colori (Attivo, Bozza, In prova, Scaduto).
4. **Ordine di lettura:** prima «cosa devo fare adesso» (messaggi nuovi, ordini da gestire, pagamenti bloccati), poi i numeri, poi l'elenco.
5. **Tema scuro** dell'admin (oggi non c'è: colori inline da portare a variabili).
6. Controllare anche le pagine pubbliche (bacheca annunci, promo, ordine) con la stessa regola.
Criterio di fine: schermate prima/dopo a 390 px e 1280 px per dashboard, scheda cliente, home attività, servizi/prodotti, annunci, ordini, pagamenti protetti; test verdi; deploy.

### Ancora aperto
- **Domani:** collegare i pagamenti protetti di Beauty (Connect con dati veri) e test reale da 0,50 € con rimborso; pulizia del prodotto/ordine di prova.
- Costi Connect a carico piattaforma vs venditore (nota §8/§9).
- Commercialista per le condizioni (PDF pronto in `docs/condizioni-pagamenti-protetti.pdf`).
- Frasi «Ora siamo a Fabrizio» nella landing siti web: da decidere se riscrivere (sede legale a Senise).
- Foto vere per le promo/annunci di Sonia e Hazar al posto delle grafiche.
- Email di contatto vuota nell'annuncio bilocale di Sonia.
- Affiliazione Amazon (codice Partner) se si vuole guadagnare dai link ai libri.

### Aggiunte della sera del 15/10 (online, ultimo commit 535a4b6)
- **Condivisione social:** sezione a sé, discreta e con icone piccole dei social (WhatsApp, Facebook, X, Telegram, copia link, «Condividi» di sistema da telefono) in fondo a annunci, prodotti e servizi (`layouts/partials/share-bar.blade.php`, parametri `shareSection`/`shareLabel`/`shareDark`); più compatta su promo, archivi e bacheca.
- **Icone fluttuanti discrete su tutte le pagine pubbliche** (`public/js/floating-share.js`, iniettato dal middleware `InjectFloatingShare`): compaiono dopo ~2,5 s di pausa, si aprono avvicinando il mouse, si nascondono per la sessione; non compaiono in admin/app/ordini/noindex.
- **Anteprime social complete** (og:url, og:type, og:image con dimensioni, twitter:card, immagine di riserva `images/og-hub-core.png`) su annunci, promo (`Promo::shareImageUrl()`), prodotti, bacheca, sito generato. Dopo il deploy: «Scrape Again» in Facebook Sharing Debugger per le pagine già condivise.
- **SEO e ricerca automatiche:** JSON-LD per tipo di annuncio (Product/Book/Service/RealEstateListing), ricerca interna `/cerca` (noindex) con OpenSearch `/opensearch.xml` e SearchAction nella home, segnalazione automatica a IndexNow a ogni pubblicazione/modifica (`App\Services\IndexNow`, eventi in `AppServiceProvider`), sitemap rigenerata subito (`Cache::forget('sitemap.xml')`), comando `hub:indexnow` già esistente per l'invio completo.
- **Annunci:** email di contatto di Sonia impostata anche sul bilocale (mai mostrata al pubblico).

## 11. Grafica condivisa (19/10/2026)
Richiesta: «tutto sotto occhio subito, foto tagliate bene e uguali nelle card, grafica da app». Non esisteva un template pronto adatto a Blade (cambiarlo avrebbe significato riscrivere ogni pagina): costruito uno stile unico.
- **`public/css/hub-ui.css`**, incluso per ultimo da `layouts/partials/ui.blade.php` in tutte le pagine complete (aggiungerlo a ogni nuova pagina con `@include('layouts.partials.ui')` prima di `</head>`).
- **Foto delle card sempre 4:3, ritagliate al centro (`object-fit: cover`)**: card promo pubbliche, schede admin (`.mcard`), righe scorrevoli (`.rc`, `.service-scroll-*`), annunci. Per una nuova card: contenitore con classe `ui-media` + `<img>`.
- **Menu sempre visibile** (`layouts/partials/bottom-nav.blade.php`): barra laterale con tutte le sezioni su computer, barra in basso con le 5 principali su telefono, per l'attività (Home, Promo, Servizi, Prodotti, Preventivi, Annunci, Ordini, Messaggi con contatore, Profilo, Abbonamento) e per l'amministratore (Clienti, Ticket, Richieste sito, Pagamenti protetti, Commissioni, Attività, Feedback). Barra in alto delle pagine pubbliche (`layouts/partials/public-nav.blade.php`): Promo, Annunci, Siti web, Prezzi, Guide, Cerca, Accedi.
- Colore di marca dell'admin: viola `#6366f1` (via il rosa di Beauty). Test: `tests/Feature/HubUiTest.php`.
- **Anteprima locale senza Herd/MySQL**: sqlite + `php -S 127.0.0.1:8010 -t public` con le variabili `DB_CONNECTION=sqlite`, `DB_DATABASE=...`, `SESSION_DRIVER=file`, `CACHE_STORE=file`; dati dimostrativi con foto di proporzioni diverse (script fuori dal repo).
- **Ancora da fare:** tema scuro dell'admin (colori inline da portare a variabili), ritocco pagina per pagina (wizard, profilo, fatturazione, pagina promo pubblica) e scheda cliente con meno blocchi.

## 12. Sito web con Max, filtri, link segreto, archivio, «Dove siamo» (19/10/2026)
- **Cosa fa il sito «Crea sito»** (modulo `website`, ora attivo): l'attività risponde a 4 domande in `/admin/tenants/{t}/sitebuilder` e ottiene una pagina `/site/{slug}`. Il pulsante «Parti con 1 €» della landing è un'altra cosa: incassa su Stripe e registra l'ordine, il sito su misura lo costruiamo noi.
- **Solo siti seri** (`M35\HubSitebuilder\Services\SiteGuard`): controllo dell'IA (`ContentModerator`) su nome e testi, più regola anti-spam (max un link). Se l'IA non risponde il sito NON va online. Vale anche per le modifiche dal link segreto.
- **Link segreto**: alla prima pubblicazione parte una email a ogni titolare con email vera (mai `@guest.hub-core.local`) con `/sito/modifica/{token}`: senza account, token da 48 caratteri (si salva solo l'impronta SHA-256), valido 90 giorni, un nuovo invio annulla il vecchio. Il sito è pubblico solo se l'email è confermata (`guest_verified_at`); il titolare lo vede in anteprima.
- **Vita del sito** (`hub:sites-lifecycle`, senza `--apply` mostra soltanto): prova finita, abbonamento non pagato/annullato, nessuna modifica da 120 giorni (non per chi ha abbonamento attivo) → avviso via email e 14 giorni di tolleranza → sito tolto dal pubblico e salvato in **Archivio siti** (`/admin/site-archive`, solo amministratore, solo testi) con avviso al titolare. Email mai confermata da 30 giorni → archivio silenzioso. Modifica/pagamento durante la tolleranza annulla l'avviso. Scheda cliente → «Vetrina sul sito e sito web» → «Non rimuovere mai il suo sito».
- **Pianificazione spenta di default**: per accenderla mettere `HUB_SITE_LIFECYCLE=true` nel `.env` (poi `optimize:clear`); regolabili `HUB_SITE_INACTIVE_DAYS`, `HUB_SITE_GRACE_DAYS`, `HUB_SITE_UNVERIFIED_DAYS`. Richiede la migrazione `2026_10_19_000001_add_lifecycle_to_generated_sites` (`migrate --force`).
- **Dove siamo** (`config/presence.php`): punti di presenza nella landing siti web (Corigliano-Rossano/Fabrizio sede operativa, Castrovillari, Castiglione delle Stiviere, Senise sede legale) con pulsante Google Maps; la posizione su Google non si aggiorna da sola (servirebbe un'API a pagamento). **I siti dei clienti** compaiono solo se l'amministratore spunta «Mostra tra i clienti sul sito» nella scheda cliente (serve https e email confermata).
- `hub:create-tenant` crea un cliente con il titolare (link per scegliere la password).

### Per la prossima sessione (in ordine)
1. **Rifinire la grafica** pagina per pagina (§11) dopo averla vista online.
2. **Beauty:** collegare i pagamenti protetti (Connect, dati veri) e fare il test reale da 0,50 € con rimborso.
3. Foto vere al posto delle grafiche per Sonia/Hazar; costi Connect; commercialista (PDF pronto); frasi «Ora siamo a Fabrizio» nella landing.
4. Nota operativa: il deploy da Plesk va fatto con **Laravel → Deployment → Deploy**, poi Artisan `optimize:clear` e `route:cache`; l'agente non può lanciare comandi Artisan che modificano dati di produzione (li lancia l'utente).

