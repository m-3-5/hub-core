# Piano di sviluppo M35 Hub

> Documento di riferimento condiviso (locale + produzione via Git su Plesk).  
> Ultimo aggiornamento: **maggio 2026**  
> Repo: [hub-core](https://github.com/m-3-5/hub-core) → deploy su **inm35.it**

---

## 1. Visione

**Hub** = piattaforma multi-tenant dove i clienti si registrano, provano i servizi in demo o abbonamento, e accumulano dati.

**Premium / “tutto mio”** = progetto **Laravel dedicato** con **database proprio**, deploy separato. L’hub può **leggere** informazioni aggregate sull’azienda e **esportare/clonare** i dati quando il cliente passa al piano dedicato.

**Regola tecnologica:** tutto ciò che è “serio” (promo, agenda, affitti, shop, sharing, siti strutturati) → **Laravel**. Solo le **paginette statiche** semplici restano fuori da questo stack.

**Clienti premium attuali:** Beauty of Image, Piramide 35.

---

## 2. Architettura (schema)

```mermaid
flowchart TB
    subgraph hub [Hub multi-tenant — hub-core · inm35.it]
        AUTH[Login / registrazione]
        DEMO[Demo moduli su hub]
        DATA[Dati tenant condivisi]
        BILLING[Abbonamenti e piani]
        SHARING[Sharing / card condivise — solo hub]
    end

    subgraph premium [Workspace dedicati — Laravel separato]
        BEAUTY[beauty-workspace · DB hub_beauty]
        PIRA[piramide-workspace · DB hub_piramide35]
        CLIENT_N[cliente-n-workspace · DB dedicato]
    end

    subgraph public [Siti pubblici — transizione]
        WP_B[beautyofimage.com — WordPress per ora]
        WP_P[piramide35.com — sito attuale per ora]
        STATIC[Statiche semplici — eccezione]
    end

    subgraph future [Futuro]
        PWA[App scaricabili / PWA]
    end

    AUTH --> DEMO
    DEMO --> DATA
    BILLING --> DEMO
  SHARING --> DATA
    DATA -->|export / clone / API| BEAUTY
    DATA -->|export / clone / API| PIRA
    BEAUTY --> WP_B
    PIRA --> WP_P
    BEAUTY --> PWA
    hub -->|SSO bridge · lettura metadati| premium
```

---

## 3. Tre livelli commerciali (per ogni servizio)

| Livello | Nome | Dove vive | Modello |
|--------|------|-----------|---------|
| **0** | Demo | Hub multi-tenant | Gratuito / limitato |
| **1** | Abbonamento | Hub multi-tenant | Canone mensile, dati su hub |
| **2** | Tutto mio | Laravel dedicato + DB proprio | Setup + hosting; dati clonati dall’hub |

### Flusso tipo (es. Agenda)

1. Cliente si registra su **inm35.it** → prova **Agenda** in demo.
2. Usa appuntamenti e clienti sull’hub (abbonamento).
3. **Resta in abbonamento** → continua sull’hub.
4. **Vuole tutto suo** → si clona `agenda-base` + import dati del tenant → `agenda-cliente.it`.

Stesso schema per: **Promo**, **Affitti**, **Shop**, **Sito web**, **Sharing**.

---

## 4. Hub vs workspace dedicato

| Cosa | Hub (`hub-core`) | Workspace premium (Laravel dedicato) |
|------|------------------|--------------------------------------|
| Login centrale | ✅ | Bridge SSO dall’hub |
| Registro tenant, piani, billing | ✅ | Riceve solo riferimento (`tenant_id`, `plan`) |
| Demo / abbonamento moduli | ✅ | — |
| Promo, prodotti, agenda del cliente | Copia durante uso hub | ✅ fonte di verità dopo il fork |
| Sharing / card cross-tenant | ✅ sempre hub | Integrazione via API hub |
| Deploy hub | Non tocca i workspace | Indipendente |
| Sito pubblico | Embed / API verso WP o statico | Laravel o statico generato |

### Cosa significa “io pesco informazioni dall’hub”

L’hub mantiene il **registro centrale** di ogni azienda:

- slug, nome, dominio, colori, moduli attivi, piano (`demo` | `subscription` | `dedicated`)
- utenti e permessi (`tenant_user`)
- per i dedicati: URL workspace, stato sync, ultimo export
- **Sharing**: rete card/sconti tra strutture (dati cross-tenant solo su hub)

Il workspace premium **non sostituisce** l’hub per identità e servizi di rete; **sostituisce** l’hub per i dati operativi del modulo forkato.

---

## 5. Moduli e repository (target)

| Modulo | Hub (demo/abbonamento) | Template fork (`*-base`) | Note |
|--------|------------------------|--------------------------|------|
| **Promo** | ✅ attivo | `hub-module-promo` | Beauty, Piramide primi fork |
| **Servizi** | pianificato | `hub-module-services` | Listino trattamenti |
| **Shop** | pianificato | `hub-module-shop` | Stripe |
| **Agenda** | pianificato | `hub-module-agenda` | Prenotazioni |
| **Affitti** | pianificato | `hub-module-affitti` | Da generalizzare Serenella |
| **Sito web** | pianificato | statico o Laravel | Form + IA per base |
| **Annunci** | pianificato | `hub-module-classifieds` | Bakeca |
| **Gift card** | pianificato | `hub-module-giftcard` | |
| **Sharing** | pianificato | vedi §6 | Resta ancorato all’hub |

### Repo previsti

```
hub-core                 → piattaforma (questo repo)
beauty-workspace         → Laravel dedicato Beauty
piramide-workspace       → Laravel dedicato Piramide
hub-module-agenda        → template agenda (fork)
hub-module-affitti       → template affitti (fork)
…
```

Codice condiviso tra hub e fork: **package Composer** (`m35/hub-promo`, ecc.) per non duplicare bugfix.

---

## 6. Sharing (card clienti / sconti condivisi)

Modulo hub: **Card clienti** (`loyalty` in `config/hub.php`) — tessere fedeltà e sconti **tra più strutture**.

### Perché resta sull’hub (anche con clienti premium)

Lo sharing è **cross-tenant** per natura: una card valida in Beauty e in un altro centro. Non ha senso duplicarlo in ogni DB dedicato.

| Aspetto | Scelta |
|---------|--------|
| Dati card, rete partner, validazioni | **DB hub** (`hub_core`) |
| Negozio premium | Legge/scrive via **API hub** (`/api/sharing/...`) |
| Piano abbonamento | Sharing incluso o add-on su hub |
| Piano “tutto mio” | Moduli locali dedicati + **connettore Sharing** verso hub |

### Evoluzione commerciale Sharing

- **Demo:** poche card, pochi partner, limiti su hub.
- **Abbonamento:** rete attiva, gestione su hub.
- **Tutto mio:** fork moduli operativi (shop, promo…) + **sharing sempre su hub** (o contratto enterprise con replica — non prioritario).

Tecnologia: **Laravel** (stesso stack), mai WordPress per questo servizio.

---

## 7. Domini e transizione (Beauty & Piramide)

### Situazione attuale (non cambiare subito)

| Cliente | Sito pubblico (resta) | Hub / workspace |
|---------|----------------------|-----------------|
| Beauty | **beautyofimage.com** (WordPress) | promo su hub-core |
| Piramide | **piramide35.com** (sito attuale) | promo su hub-core |

### Strategia domini — risposta breve

**Sì:** si può partire con un **sottodominio** e spostare dopo sul **dominio definitivo** senza rifare il progetto. Basta configurazione (DNS + `.env` + certificato SSL), non riscrittura del codice.

### Fasi consigliate

#### Fase A — Ora (transizione)

Usare sottodomini **sotto inm35.it** per i workspace Laravel (zero impatto sui siti live):

| Cliente | Workspace admin (nuovo Laravel) | Sito pubblico (invariato) |
|---------|--------------------------------|---------------------------|
| Beauty | `beautyofimage.inm35.it` | beautyofimage.com |
| Piramide | `piramide35.inm35.it` | piramide35.com |
| Hub | `inm35.it` | — |

Vantaggi: DNS tutto sotto il vostro Plesk, nessuna modifica sui domini dei clienti, SSL immediato.

#### Fase B — Workspace sul dominio del cliente (quando pronti)

Aggiungere un record DNS sul dominio **loro** (es. nel pannello del registrar):

```
app.beautyofimage.com  →  CNAME o A verso server Plesk
```

Su Plesk: **alias di dominio** sullo stesso sito Laravel (`beauty-workspace`).

Aggiornare:

- `APP_URL=https://app.beautyofimage.com`
- `SESSION_DOMAIN` / cookie se necessario
- URL nei bridge WordPress e webhook promo
- `tenants.settings.workspace_url` nell’hub

**I dati e il database non cambiano.** È lo stesso deploy, nuovo hostname.

#### Fase C — Sito pubblico su Laravel (futuro)

Quando rifate **beautyofimage.com**:

1. Il workspace Laravel già esiste (`app.beautyofimage.com`).
2. Si aggiunge la parte **pubblica** (landing, promo, shop) nello stesso progetto o in deploy parallelo.
3. Si punta la **root** `beautyofimage.com` al `public/` Laravel (o reverse proxy).
4. WordPress viene dismesso o reindirizzato (301).

Stesso percorso per **piramide35.com**.

### Regola d’oro per il codice

**Mai hardcodare** `inm35.it` o domini cliente nel codice. Usare sempre:

- `APP_URL`
- `config/hub.php` / `tenants.settings`
- `PromoLinks`, webhook, bridge → da configurazione

Così la migrazione dominio è **solo ops**, non sviluppo.

### Schema transizione domini

```
OGGI
  inm35.it                    → hub-core
  beautyofimage.com           → WordPress (pubblico)
  piramide35.com              → sito attuale

STEP 1 — workspace M35
  beautyofimage.inm35.it      → beauty-workspace (admin + API)
  piramide35.inm35.it         → piramide-workspace

STEP 2 — workspace sul dominio cliente
  app.beautyofimage.com       → stesso beauty-workspace
  app.piramide35.com          → stesso piramide-workspace

STEP 3 — sito definitivo (dopo)
  beautyofimage.com           → Laravel pubblico (+ app. o stesso deploy)
  piramide35.com              → Laravel pubblico
```

---

## 8. Comunicazione hub ↔ workspace

| Meccanismo | Uso |
|------------|-----|
| **SSO bridge** | Login da hub o da WordPress → workspace (già previsto per WP) |
| **Export / clone** | `hub:export-tenant {slug} --module=promo` → import nel workspace |
| **API lettura** | Hub legge stato promo pubblicate, metadati azienda |
| **Webhook** | Workspace notifica hub (opzionale) |
| **Sharing API** | Workspace premium chiama hub per card/sconti di rete |

Sync continuo bidirezionale: **non prioritario**. Preferire export al fork + eventuale API leggera.

---

## 9. Piano di sviluppo per fasi

### Fase 0 — Stabilizzazione hub (in corso)

- [x] Shell multiservizio, auth, moduli UI
- [x] Promo Beauty + Piramide su hub
- [x] WordPress embed / webhook Beauty
- [ ] Budget IA per promo + fallback immagini (upload / statiche)
- [x] Ricalibrazione immagini carousel welcome
- [x] Campo `plan` su tenant (`demo` | `subscription` | `dedicated`)
- [ ] Documentazione deploy Plesk aggiornata

### Fase 1 — Premium Beauty & Piramide (workspace)

- [x] Creare DB `hub_beauty` e `hub_piramide35` (locale: `php artisan hub:provision-workspace`)
- [ ] Scaffold repo `beauty-workspace` e `piramide-workspace` (Laravel)
- [ ] Estrarre modulo promo in package condiviso o copia controllata
- [ ] Comando export promo da hub-core
- [ ] Deploy: `beautyofimage.inm35.it`, `piramide35.inm35.it`
- [ ] Bridge SSO hub → workspace
- [ ] WordPress beautyofimage.com: aggiornare webhook verso workspace (quando pronto)

### Fase 2 — Moduli hub demo + fork

- [ ] `hub-module-agenda` su hub (demo)
- [ ] `hub:fork-module agenda {tenant}` → istanza dedicata
- [ ] Stesso per affitti (da base Serenella)
- [ ] Sito web: modulo form + generazione base (statico o Laravel)

### Fase 3 — Sharing

- [ ] Modello dati card / partner / sconti su hub_core
- [ ] API Sharing per workspace premium
- [ ] UI hub: gestione rete e abbonamento Sharing
- [ ] Integrazione demo → abbonamento → connettore su fork dedicati

### Fase 4 — Siti definitivi cliente

- [ ] Laravel pubblico Beauty su beautyofimage.com
- [ ] Laravel pubblico Piramide su piramide35.com
- [ ] Migrazione DNS root + dismissione WP (Beauty)
- [ ] App scaricabili / PWA (stesso backend workspace)

### Fase 5 — Promo: scadenza visibile, archivio, pagamento

- [ ] Mostrare data scadenza offerta al pubblico (landing, API, WordPress)
- [ ] API e shortcode: promo **attive** + **scadute** (archivio)
- [ ] Stile visivo: attive normali, scadute con ombra/opacità ridotta
- [ ] Paywall modulo Promo: abbonamento o crediti prima di creare
- [ ] Integrazione pagamenti (Stripe / Plesk billing) per sblocco servizio

### Fase 6 — Assistente IA su inm35.it

- [ ] Widget chat in hub (domande su servizi, guida utente)
- [ ] Raccolta feedback strutturato per servizi attivi
- [ ] Knowledge base iniziale (documentazione hub, FAQ, moduli)
- [ ] Apprendimento: log conversazioni + revisione umana (non auto-deploy cieco)
- [ ] Stack: **Gemini** (o OpenAI) via API Laravel — **non** Cursor SDK in produzione

---

## 13. Visione servizi modulari + visione hub (aggiornamento importante)

Ogni servizio (Promo, Agenda, Affitti, Shop, Sito…) segue lo **stesso modello**:

```
┌─────────────────────────────────────────────────────────────┐
│  HUB inm35.it (Laravel)                                     │
│  • Registro clienti, login, billing, assistente IA          │
│  • Demo / abbonamento su multi-tenant                       │
│  • API di lettura: prodotti, servizi, aggiornamenti         │
│  • Comando fork: copia intero progetto → ambiente cliente   │
└──────────────────────────┬──────────────────────────────────┘
                           │
         ┌─────────────────┼─────────────────┐
         ▼                 ▼                 ▼
  hub-module-promo   hub-module-agenda   hub-module-affitti
  (package Laravel)  (package Laravel)   (package Laravel)
         │                 │                 │
         ▼                 ▼                 ▼
  Abbonamento hub    Abbonamento hub    Abbonamento hub
  (multi-tenant DB)  (multi-tenant)     (multi-tenant)
         │                 │                 │
         ▼                 ▼                 ▼
  Fork "tutto mio"   Fork dedicato      Fork dedicato
  Laravel + DB       Laravel + DB       Laravel + DB
  app.cliente.com    app.cliente.com    app.cliente.com
```

### Due modalità per il cliente

| Modalità | Cosa ottiene | Dove vivono i dati |
|----------|--------------|-------------------|
| **Abbonamento hub** | Usa il servizio su inm35.it | DB hub (tenant_id) |
| **Progetto proprio** | Copia completa Laravel + DB sul suo dominio | DB dedicato cliente |

### Visione hub sempre attiva (fattibile)

Anche quando il cliente ha il **progetto copiato** sul suo ambiente, l'hub mantiene **visibilità** (non controllo totale, ma oversight):

| Cosa l'hub vede | Come |
|-----------------|------|
| Tenant registrato, piano, moduli attivi | `hub_core.tenants` |
| Metadati azienda, stato sync | API heartbeat dal workspace |
| Elenco promo/servizi pubblicati (sintesi) | Webhook / API read-only dal workspace |
| Versione software deployata | Header `X-Hub-Module-Version` |
| Feedback utenti | Assistente IA → hub centralizzato |

Il workspace dedicato **non dipende** dall'hub per funzionare offline, ma **notifica** l'hub se configurato (contratto API + chiave tenant).

### Comando fork (target)

```bash
php artisan hub:fork-module promo beauty-of-image
# → crea/aggiorna repo beauty-workspace
# → export dati tenant
# → deploy su app.beautyofimage.com
# → hub conserva registry + API read
```

**Fattibilità:** sì, con package Composer condivisi (`m35/hub-promo`, ecc.) e API leggere.  
Complessità media-alta; conviene **un modulo alla volta** partendo da Promo (già avviato).

---

## 14. Assistente IA — scelta tecnologica

| Opzione | Uso corretto | Per inm35.it pubblico |
|---------|--------------|------------------------|
| **Gemini API** | Chat, testi, analisi immagini volantino | ✅ Consigliato (già integrato) |
| **OpenAI API** | Alternativa chat | ✅ Possibile |
| **Cursor SDK** | Agenti che modificano codice in CI/dev | ❌ Non per utenti finali sul sito |

L'assistente su inm35.it deve:

1. Rispondere su servizi, prezzi, come usare i moduli (RAG su docs + FAQ).
2. Chiedere feedback dopo l'uso di un modulo (rating, testo, NPS leggero).
3. Salvare conversazioni in `hub_core` per migliorare le risposte (**revisione umana**, non auto-modifica del codice in produzione).

**Auto-ammaestramento:** fine-tuning o aggiornamento knowledge base da log approvati — non Cursor che committa codice da solo su Plesk.

Cursor resta lo strumento **di sviluppo** (come ora in locale), non il motore chat del cliente.

---

## 15. Promo — scadenza e archivio (specifiche)

### Scadenza visibile al pubblico

- Landing promo: badge «Valida fino al …» se `ends_at` presente.
- WordPress `[beauty_promos]`: stessa etichetta sotto il titolo.
- API JSON: campo `ends_at` già esposto → usare in UI.

### Archivio «tutte le promo»

- **Attive:** card normali, in cima.
- **Scadute:** restano visibili, `opacity: 0.72`, ombra più soft, badge «Scaduta il …».
- API nuova query param: `?include=expired` o endpoint `/promos/archive`.
- Ordine: attive prima (per `published_at`), poi scadute.

---

## 10. Deploy e file (Git → Plesk)

```bash
# Produzione hub (inm35.it)
git pull
composer install --no-dev --optimize-autoloader
# verificare .env (APP_URL, DB_*, GEMINI_*, HUB_*)
php artisan migrate --force
php artisan storage:link
php artisan hub:provision-workspace beauty-of-image
php artisan hub:provision-workspace piramide35
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Workspace dedicati: **sito Plesk separato** per ogni Laravel (docroot `public/`), DB dedicato, `.env` proprio.

Questo file (`docs/PIANO-SVILUPPO.md`) viaggia con Git: consultabile in repo e in locale.

---

## 11. Decisioni prese (log)

| Data | Decisione |
|------|-----------|
| 2026-05 | Hub resta multi-tenant; premium = Laravel + DB separato |
| 2026-05 | Beauty e Piramide = primi workspace premium |
| 2026-05 | Siti pubblici attuali non si toccano finché non si è in Fase 4 |
| 2026-05 | Workspace prima su `*.inm35.it`, poi `app.*.com`, infine root dominio |
| 2026-05 | Sharing resta servizio hub; fork dedicati si integrano via API |
| 2026-06 | Immagini promo: volantino + SVG fallback (`PROMO_AI_IMAGES=false`) |
| 2026-06 | Promo: scadenza visibile + archivio attive/scadute (Fase 5) |
| 2026-06 | Paywall per creazione promo (Fase 5) |
| 2026-06 | Assistente IA hub: Gemini + feedback, non Cursor in produzione |
| 2026-06 | Ogni servizio = Laravel+DB forkabile; hub mantiene visione via API |

---

## 16. Prossimo passo operativo

1. ~~Database workspace Beauty/Piramide su Plesk~~ ✅
2. **Fase 5a:** scadenza visibile + archivio promo (landing + WordPress + API)
3. **Fase 5b:** paywall modulo Promo (Stripe o manuale iniziale)
4. Scaffold `beauty-workspace` + export promo
5. Prototipo widget assistente IA (Gemini) su inm35.it

> Per aggiornare questo piano: modificare questo file e commit su `hub-core`.  
> Riferimento conversazioni: documento vivo da usare con Cursor / team M35.
