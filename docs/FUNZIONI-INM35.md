# Funzioni di inm35.it: promemoria per chi lavora sul codice (persone e IA)

Aggiornato il 28/11/2026. Leggilo **prima** di cambiare una funzione per i clienti. Per il contesto generale (tenant, deploy, decisioni) leggi `docs/CONTESTO-PROGETTO.md`. Per lo stato del progetto leggi `docs/STATO-PROGETTO.md`.

## Regola d'oro: le guide pubbliche seguono il codice

Quando cambi, aggiungi o accendi una funzione per i clienti, aggiorna **nello stesso commit**:

| Pagina | File | Cosa contiene |
|---|---|---|
| `/cosa-puoi-fare` | `resources/views/guides/features.blade.php` | Le funzioni **attive**, una riga ciascuna, più «In arrivo» |
| `/guida/app` | `resources/views/guides/app.blade.php` | Passo passo, con i **nomi veri dei tasti** dell'app |
| `/guida/facebook` | `resources/views/guides/facebook.blade.php` | Collegamento della pagina Facebook e hashtag |
| `/prezzi` | `resources/views/pricing.blade.php` | Prezzi e come si paga |
| Max in chat | prompt in `app/Http/Controllers/MaxChatController.php` | Le stesse regole, scritte per l'IA. Dentro ci sono i prezzi scritti a mano, come i prezzi e i tetti dei pacchetti: aggiornali se cambi la config |
| Questo file | `docs/FUNZIONI-INM35.md` | Promemoria per chi lavora sul codice |

Le pagine pubbliche sono anche in `SitemapController` e in `LlmsController` (llms.txt). Stile delle pagine:
- poco testo e icone;
- «Scopri di più» a scomparsa;
- grafica da app sul telefono.

Sono richieste di Max, salvate in memoria.

## Funzioni attive (config `hub.modules`, `active => true`)

| Funzione | Nell'app | Codice principale | Note |
|---|---|---|---|
| Promo | «Crea promo» | `PromoController`, `Admin/WizardController` (route `admin.wizard.show`, kind `promo`) | Foto (l'IA propone i testi) o grafica disegnata dall'IA. Pagina pubblica `/p/{tenant}/{slug}`. Video e pacchetto immagini |
| Prodotti | «Prodotti» | `packages/hub-payments`, wizard kind `product` | Prezzo obbligatorio, etichetta «In promo» fino a una data, «Mostralo sul tuo sito» |
| Servizi | «Servizi» | `packages/hub-payments`, wizard kind `service` | Come i prodotti, più la durata |
| Preventivi | «Preventivi» | `hub-payments` QuoteController | Importo libero con link di pagamento |
| Sito con l'IA | «Sito web», `/crea-sito` | `packages/hub-sitebuilder` (SiteBuilderController, SiteContent, show.blade.php) | Vedi la sezione sotto |
| Annunci | «Annunci» (attività); `/annunci/nuovo` (privati) | `ClassifiedAdController`, `PrivateAdController`, `ClassifiedReviewController` | Gratis. I privati confermano con un link via email; li controlla Max; durano 30 giorni |
| Facebook | «Facebook» | `FacebookController`, `Services/FacebookPages.php`, `Services/FacebookSections.php` | Vedi la sezione sotto |
| Pagamenti protetti | «Pagamenti protetti» | `hub-payments`, Stripe Connect | Paga Hub Core; il venditore riceve dopo la conferma o dopo 7 giorni |
| Messaggi clienti | «Messaggi» | `customerTickets` (da promo e annunci) | Arrivano anche per email |
| «Scrivi a Max» | ticket verso Max | `Admin/TicketController` | Solo dal cliente a Max: la risposta resta salvata ma **non** parte un'email |

Funzioni in arrivo (`active => false`): Vendi (negozio completo), Agenda, Affitti, Gift card, Card clienti. **Non** presentarle come attive nelle guide.

## Soldi: si paga solo col credito (`app/Support/PayPerUse.php`)

**Decisione di Max del 28/11/2026:** i pagamenti arrivano solo dalle ricariche del credito.

**Prezzi** (config `services.hub_billing.pay_per_use`):
- annunci gratis;
- promo, prodotti e servizi: 1 € l'uno; nello stesso mese dal 6° 0,80 €, dal 21° 0,50 €;
- sito, ogni lingua del sito, ogni opzione del sito: 1 €;
- chi ha il sito ha 3 promo, 3 prodotti e 3 servizi inclusi (`included_with_site`);
- si paga una volta per ogni oggetto: modificare non costa.

**Ordine in `charge()`:**
1. credito **spendibile** (`spendableCents`);
2. carta salvata: solo se `card_enabled`, che è **spenta** di base (`HUB_PPU_CARD=false`);
3. fido fino a `overdraft_cents` (5 €), che si salda con la ricarica;
4. altrimenti `false`: l'oggetto resta da pubblicare (vedi `PendingPublication`).

**Credito:**
- benvenuto di 15 € una volta sola (`grantWelcome`);
- ricariche da 5 € in su: carta su Stripe Checkout (`CreditTopUp`), oppure bonifico o contanti segnati da Max in «Pagamenti» (`manualTopUp`);
- per ogni acquisto e ricarica parte una ricevuta non fiscale al cliente e a Max (`CreditNotices`).

**Credito dei giochi di piramide35.com:**
- **Codici:** buoni `M35G-…` firmati con HMAC (`app/Support/GameVoucher.php`, pagina `/buono`, `GIOCO_BUONI_SEGRETO`). Si riscattano al massimo 3 buoni al mese, ognuno fino a 5 €.
- **Spendibili subito:** fino a `game_free_cents` (3 €, `HUB_PPU_GAME_FREE_CENTS`).
- **Il resto è bloccato** (`gameLockedCents`) finché l'attività non fa una ricarica vera di almeno `topup_min_cents`. Contano carta, bonifico e contanti; non contano i «regali» segnati da Max.
- Dopo quella ricarica si sblocca tutto, per sempre.
- Il bloccato si calcola dai movimenti (`reason = voucher`), non da un campo salvato.

**Carta «paghi dopo 30 giorni»:**
- Il codice c'è ancora: `CardSetup`, `hub:bill-usage`, e i test la accendono in `phpunit.xml`.
- Le voci già `pending` vengono comunque addebitate.
- Per riaccenderla basta `HUB_PPU_CARD=true`, poi si riaggiornano le guide.

**Pacchetti facoltativi Base / Premium / Business** (Max, 28/11/2026). Codice in `App\Support\Packages`, configurazione in `services.hub_billing.pay_per_use.premium`.

| | Base | Premium | Business |
|---|---|---|---|
| Prezzo al mese | 9 € | 19 € | 39 € |
| Promo + prodotti + servizi al mese | 15 | 50 | 150 |
| Sito | incluso | incluso | incluso |
| SEO, «Fatti trovare dall'IA», «Indicizza» | — | inclusi | inclusi |
| Lingue del sito | — | 2 | tutte |
| «Lo facciamo noi» e assistenza prioritaria | — | — | sì |
| Volantini IA e «In evidenza» | — | in arrivo (solo testo) | in arrivo (solo testo) |

- **Pagamento:** dal credito spendibile (`Packages::buy`), per 1, 3, 6 o 12 mesi, con sconto del 0, 10, 15 o 20% (prezzo arrotondato ai 10 centesimi).
- **Tetto mensile:** il «mese» dura 30 giorni dall'acquisto (`periodStart`). Oltre il tetto si paga `over_cents` (0,50 €).
- **Movimenti:** quelli dentro il pacchetto hanno `reason = premium` e contano per il tetto. Le lingue si contano dall'inizio del pacchetto (`started_at`).
- **Allungare:** stesso pacchetto, i mesi si aggiungono in coda.
- **Cambiare pacchetto:** la parte non usata del vecchio torna nel credito come movimento `premium_refund`.
- **Rinnovo:** `hub:renew-premium` ogni giorno alle 08:30, stesso pacchetto e stessi mesi, finché `renew` è vero e c'è credito. Il cliente lo ferma o lo riprende in «Pagamenti».
- **Uscita:** per `exit_days` (30) giorni si paga `exit_cents` (0,80 €), poi il prezzo normale.
- **Stato salvato** in `settings.ppu_premium`: `plan`, `months`, `paid_cents`, `bought_at`, `until`, `renew`, `started_at`, `ended_at`.
- **Non è un abbonamento obbligatorio.** Max: «non si pagherà mai un euro al mese». Nelle guide va scritto sempre così: se un mese non pubblichi niente, non paghi niente.
- **Menu:** dal 28/11 la voce «Abbonamento» non c'è più; si usa solo «Pagamenti» (`admin.credit.show`). Il vecchio abbonamento Stripe (`admin.billing.show`) resta solo per chi lo aveva già.
- **Da collegare quando ci saranno:** i volantini con l'IA da mettere in pagamento (oggi «in arrivo» in `PromoController`) e la pubblicità «In evidenza».

**Servizi in più del sito** (`SiteAddons`, `SiteTranslations`). Costano 1 € l'uno, una volta sola; nei pacchetti Premium e Business sono inclusi.

| Servizio | Cosa fa |
|---|---|
| Lingue | Traduzione con l'IA. Si ritraduce gratis quando il sito cambia |
| SEO | Titolo, descrizione, parole chiave e testo alternativo delle foto, scritti dall'IA. Si riscrive gratis quando il sito cambia |
| «Fatti trovare dall'IA» | `llms.txt` del sito e dati strutturati |
| «Indicizza» | Il sito entra nella sitemap di inm35.it (che Google legge da Search Console) e viene segnalato con IndexNow a Bing e agli altri motori |

Sono tutti nelle guide (`/guida/app#sito`) e nei prezzi.

**Abbonamenti vecchi:** chi ha un abbonamento attivo e lo staff non pagano (`exempt`).

**Fattura elettronica:** la ricevuta è non fiscale. Max è stato avvisato.

## Facebook

- **App Meta:** «Hub Core», pubblicata. Webhook sul campo `feed` all'indirizzo `/api/facebook/webhook` (`routes/api.php`), Graph API v27.0.
- **Collegamento:** il cliente apre «Facebook» nell'app, poi «Collega Facebook», poi sceglie la pagina. Si leggono solo i post; su Facebook non si pubblica niente.
- **#promo** (più gli hashtag in più scelti dal cliente): `FacebookPages::handlePost` crea una promo. Serve una foto. Costa 1 €.
- **Altri hashtag** (`FacebookSections::TAGS`), con questa priorità:

| Hashtag | Cosa crea | Prezzo |
|---|---|---|
| #prodotto | prodotto | 1 €, con il prezzo scritto nel testo |
| #servizio, #servizi, #nuovoservizio | servizio | 1 € |
| #evento | «Novità ed eventi» del sito, con la data se c'è | gratis |
| #novita, #news | «Novità ed eventi» del sito | gratis |
| #blog | articolo | gratis |
| #lavori, #galleria | galleria del sito | gratis |

- Le novità e i lavori stanno in `answers.news` / `answers.facebook_works` del sito; se ne tengono al massimo 12.
- **Va online da solo** se `PayPerUse::canPay` e se il controllo dei contenuti passa. Altrimenti resta «da completare» nel pannello.
- **Beauty of Image** ha un sistema suo in beauty-app: la guida vale per tutti gli altri clienti.

## Sito con l'IA (`packages/hub-sitebuilder`)

**Creazione guidata** (`resources/views/admin/wizard.blade.php`), questi passi in ordine:
1. describe (l'IA scrive);
2. tagline;
3. services (con il riquadro facoltativo **«Marchi trattati»** → `answers.brands`);
4. about;
5. cta;
6. phone;
7. photos;
8. where;
9. social;
10. review → «Metti online».

**Salvataggio:**
- `SiteContent::answers()` pulisce i dati.
- `SiteContent::save()` tiene le chiavi che il link segreto non manda: `extras`, `info`, `media`, `brands`.
- Tiene sempre `i18n`, `addons`, `news`, `facebook_works`, `offline_at`.

**Gli altri tasti:**
- **Disegno in alto:** SVG semplice fatto da Gemini (`SiteBuilderHeroGenerator::illustration`), tasto «Fai un altro disegno», comando `hub:site-art`.
- **Online/offline:** `answers.offline_at`; offline lo vede solo il titolare.
- **Lingue e opzioni** (SEO, IA, indicizza): 1 € ciascuna.

**Pagina pubblica** (`resources/views/public/show.blade.php`):
- l'alto della pagina sceglie da solo tra art, bg, split e brand;
- i marchi sono sotto i servizi;
- le novità di Facebook hanno la loro sezione;
- og:image e favicon vengono dal sito, mai da Hub Core.

## Aiuto ai clienti

- Max risponde con l'aiuto dell'IA. Nelle guide si scrive: «ti rispondiamo noi, con l'aiuto della nostra intelligenza artificiale».
- La chat pubblica è `partials/max-public-chat` (home e guide). La guida IA *dentro* l'app non c'è ancora.
- Non esiste un ticket aperto da Max verso il cliente: per scrivere per primo si usa l'email.

## Da fare dopo (idee di Max)

- **Pubblicità a pagamento (ads)**: comparire subito in alto nella bacheca degli annunci, delle promo o nella home. Non è ancora fatta; quando arriva va messa nei prezzi e nelle guide.

- Una chat per creare manifesti, volantini e biglietti da visita per l'offerta «1 €».
- Una pagina «Cosa puoi fare» su misura per i settori, se serve.

## Prima di consegnare

- **Test:** `php -d memory_limit=1G vendor/bin/phpunit`. Non usare `artisan test`: va in crash per la memoria.
- **Commit e push:** dopo ogni commit si fa subito `git push`.
- **Deploy:** solo con il «sì» esplicito di Max. Si fa da Plesk:
  1. Deployment → Deploy;
  2. Artisan → `optimize:clear`, `route:cache`, e `migrate --force` se ci sono migrazioni.

Vedi `docs/DEPLOY-PLESK.md`.
