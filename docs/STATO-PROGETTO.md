# Stato del progetto Hub Core (documento vivo)

> Aggiornato: **2026-10-14** (commit 124e97e). Si aggiorna a ogni pezzo consegnato (commit su `hub-core`).
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
| 2 Cassa + trattenuta + ordine «Trattenuto» + ricevute | ✅ (da provare con acquisto di prova) |
| 3 Rilascio: pulsante «Ho ricevuto», comando giornaliero (Transfer = incasso − fee Stripe reale − commissione), «Segnala un problema», rimborsi | ⏳ da fare |
| 4 Viste venditore/compratore complete, notifiche, PayPal/bonifico automatici | ⏳ da fare |

## 3. Problemi aperti

1. **Webhook Stripe 404 (email Stripe del 10 e 13 ottobre).** La Dashboard (modalità live) invia a `https://inm35.it/stripe/billing/webhook`, ma l'app serviva solo `/api/stripe/webhook` → 404 dal 7 ottobre. **Corretto e online dal 14 ottobre** (commit 124e97e: alias `/stripe/billing/webhook` + più segreti separati da virgola; ora risponde 401 senza firma invece di 404). Resta da verificare con un evento reale (Dashboard → Webhook → «Invia di nuovo»): se risponde 401 il segreto in `.env` non è quello dell'endpoint live. Stripe smette di inviare il 16 ottobre 2026 se continua a fallire.
2. **Webhook in modalità di prova mancante.** I pagamenti protetti di prova generano eventi in *modalità test*, che richiedono un endpoint **test** in Stripe (Sviluppatori → Webhook, con «Modalità di prova» attiva) verso `/api/stripe/webhook`, evento `checkout.session.completed`. Il suo segreto va aggiunto in `HUB_STRIPE_WEBHOOK_SECRET` dopo quello live, separato da virgola.
3. Verifica che `HUB_STRIPE_WEBHOOK_SECRET` in produzione sia il segreto dell'endpoint live (altrimenti 401 invece di 404).

## 4. Da fare per l'utente (non automatizzabili)

- Beauty: spuntare l'accettazione delle condizioni economiche (impegna l'attività) in `/admin/tenants/beauty-of-image/services`.
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
