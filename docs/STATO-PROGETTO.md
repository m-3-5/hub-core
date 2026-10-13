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
| 3 Rilascio ai venditori | ✅ **online dal 14/10; rilascio provato in sandbox** (1,00 € → commissione Stripe reale 0,28 € → 0,72 € al venditore); restano da provare dal vivo segnalazione e rimborso: «Ho ricevuto tutto» (rilascio immediato), comando `hub:release-protected-payments` ogni ora (Transfer = incasso − commissione Stripe reale − commissione Hub Core, idempotente), «Segnala un problema» (blocca + ticket + email), contestazioni/rimborsi da Stripe che bloccano l'ordine, rimborso e ripresa dal pannello team `/admin/protected-orders`, condizioni di acquisto del compratore con accettazione obbligatoria (`/condizioni-acquisto`, registrate su ordine), condizioni venditore v2026-10-14 (da riaccettare) |
| 4 Privati che vendono con un annuncio | ✅ **costruito il 15/10**: categoria «Oggetti in vendita» a prezzo fisso, opzione «Vendi con pagamento protetto», pulsante «Compra con pagamento protetto» nella pagina annuncio, ordine → oggetto «Venduto»; il collegamento Stripe e gli ordini sono nella pagina Annunci (non serve il modulo Servizi); condizioni venditore/compratore estese ai privati (v2026-10-15) |
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
