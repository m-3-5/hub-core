# Deploy su Plesk (inm35.it)

Guida rapida per aggiornare **hub-core** in produzione dopo `git pull`.

> **Prerequisito:** i commit devono essere su GitHub (`git push` da locale).

---

## Cosa include questo deploy

| Novità | Note |
|--------|------|
| Piano di sviluppo | `docs/PIANO-SVILUPPO.md` |
| Immagini carousel welcome | 7 PNG rigenerate, layout 4:3 |
| Tenant premium | Beauty + Piramide → `plan=dedicated` |
| DB workspace | `hub_beauty`, `hub_piramide35` |
| Comando nuovo | `php artisan hub:provision-workspace {slug}` |

I workspace Laravel separati (`beautyofimage.inm35.it`) **non sono ancora deployati** — questo deploy aggiorna solo **hub-core** su inm35.it e prepara i database premium.

---

## Step 1 — Push da locale (una tantum)

Sul PC, nella cartella del progetto:

```powershell
git push origin master
```

Se Plesk fa pull automatico da GitHub, passa allo step 2. Altrimenti: **Plesk → Git → Pull** o aggiorna manualmente.

---

## Step 2 — Database su Plesk

### Già esistente
- `hub_core` + utente `hub_core_user` (o equivalente)

### Da creare (Pannello Plesk → Database → Aggiungi database)

| Database | Uso |
|----------|-----|
| `hub_beauty` | Workspace premium Beauty |
| `hub_piramide35` | Workspace premium Piramide |

**Opzione A (semplice):** stesso utente `hub_core_user` con accesso a tutti e tre i DB.

**Opzione B (più sicura):** utente dedicato per ogni DB — in quel caso serviranno variabili extra nel `.env` (per ora il comando usa le credenziali di `DB_*` principali).

---

## Step 3 — File `.env` su Plesk

Apri il `.env` nella root del sito (non committarlo). Verifica / aggiungi:

```env
APP_URL=https://inm35.it
APP_ENV=production
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hub_core
DB_USERNAME=hub_core_user
DB_PASSWORD="***password con ! % ~ ^ tra virgolette doppie***"

# Una sola riga GEMINI_API_KEY (no duplicati)
GEMINI_API_KEY=***
GEMINI_MODEL=gemini-flash-lite-latest

HUB_DEFAULT_PASSWORD=HubCore2026!
HUB_BRIDGE_SECRET=***

# Workspace premium (DB già creati su Plesk)
TENANT_BEAUTY_DATABASE=hub_beauty
TENANT_BEAUTY_DB_USERNAME=hub_beauty_user
TENANT_BEAUTY_DB_PASSWORD="***password beauty — virgolette se ha % ! ~ ^***"
TENANT_BEAUTY_URL=https://app.beautyofimage.com

TENANT_PIRAMIDE35_DATABASE=hub_piramide35
TENANT_PIRAMIDE35_DB_USERNAME=hub_piramide35_user
TENANT_PIRAMIDE35_DB_PASSWORD="***password piramide***"
TENANT_PIRAMIDE35_URL=https://app.piramide35.com

# WordPress Beauty — promo sul sito (se plugin installato)
HUB_WEBHOOK_URL=https://beautyofimage.com/wp-json/beauty-hub/v1/sync
HUB_WEBHOOK_SECRET=***stesso secret del mu-plugin WordPress***

# Email (reset password admin, notifiche future)
# Casella Aruba Business: noreply@inm35.net
MAIL_MAILER=smtp
MAIL_HOST=out.postassl.it
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=noreply@inm35.net
MAIL_PASSWORD="***password casella — vedi workspace-credentials.local.md***"
MAIL_FROM_ADDRESS="noreply@inm35.net"
MAIL_FROM_NAME="Hub Core"
```

> Con `TENANT_*_DB_USERNAME` e `TENANT_*_DB_PASSWORD` l'hub entra nei workspace con gli utenti dedicati — **non serve** aggiungere `hub_core_user` a `hub_beauty` / `hub_piramide35`.

### Email e recupero password admin

Casella usata: **noreply@inm35.net** (Aruba Business Mail).

| Parametro SMTP Aruba Business | Valore |
|-------------------------------|--------|
| Server in uscita | `out.postassl.it` |
| Porta | `465` |
| Sicurezza | SSL (`MAIL_SCHEME=smtps`) |
| Utente | indirizzo completo (`noreply@inm35.net`) |
| Mittente | `noreply@inm35.net` |

Guida ufficiale: [Aruba Business — client di posta](https://guide.arubabusiness.it/email/configurazioni-email-arubabusiness/utilizzare-casella-arubabusiness-client-posta)

Il flusso **“Password dimenticata”** è già attivo:

| URL | Uso |
|-----|-----|
| https://inm35.it/admin/login | Login + checkbox “Ricordami” |
| https://inm35.it/admin/password/dimenticata | Richiesta link via email |

Con `MAIL_MAILER=log` (default) il link **non arriva in casella** — compare in `storage/logs/laravel.log`.

#### Attivare email vere su Plesk

1. **Plesk → Mail → Crea indirizzo email** (es. `hub@inm35.it` o `noreply@inm35.it`)
2. Nel `.env` imposta `MAIL_MAILER=smtp` come nell’esempio sopra  
   - Host tipico: `mail.inm35.it`  
   - Porta **465** + `MAIL_SCHEME=smtps`, oppure **587** + `MAIL_SCHEME=tls`
3. Pulisci la cache config:
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```
4. Prova l’invio:
   ```bash
   php artisan hub:test-mail startupm3.5@gmail.com
   ```
5. Se ok, prova il reset:
   ```bash
   php artisan hub:send-access-link startupm3.5@gmail.com
   ```
   Oppure dal browser: https://inm35.it/admin/password/dimenticata

**Alternativa senza SMTP:** su molti server Plesk Linux funziona `MAIL_MAILER=sendmail` con solo `MAIL_FROM_ADDRESS` impostato.

#### Emergenza — password dimenticata (SSH, senza email)

```bash
# Imposta password direttamente (genera una casuale se omessa)
php artisan hub:reset-password startupm3.5@gmail.com

# Oppure con password scelta
php artisan hub:reset-password info@beautyofimage.com --password="NuovaPasswordSicura123!"
```

Account admin noti (da seeder): `startupm3.5@gmail.com`, `info@beautyofimage.com`, `emilia@beautyofimage.com`.

> **Futuro (non ancora implementato):** codice SMS sullo smartphone, app key / TOTP — per ora usare email + “Ricordami” sul login.

**Importante:** se in passato hai eseguito `config:cache` con `.env` sbagliato:

```bash
php artisan config:clear
```

prima di riconfigurare.

---

## Step 4 — Comandi SSH su Plesk

Entra nella cartella del progetto (dove c’è `artisan`), es.:

```bash
cd ~/httpdocs
# oppure il path reale del sito inm35.it
```

Poi esegui **in ordine**:

```bash
git pull origin master

composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan db:seed --class=BeautyOfImageSeeder --force
php artisan db:seed --class=Piramide35Seeder --force

php artisan storage:link

php artisan hub:provision-workspace beauty-of-image
php artisan hub:provision-workspace piramide35

# Promo Beauty "Piega 10€" — attiva 10 giorni, poi torna la promo precedente
php artisan db:seed --class=BeautyPiega10PromoSeeder --force

php artisan gemini:discover-models --force

php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Se `hub:provision-workspace` fallisce (Access denied)

Su Plesk i DB sono già creati con utenti dedicati, ma **hub_core_user** deve poterci scrivere per le migration da inm35.it:

1. **Plesk → Database → hub_beauty → User Management**
2. **Add Database User** → seleziona utente esistente **`hub_core_user`**
3. Privilegi: **tutti** (o almeno SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP)
4. Ripeti per **hub_piramide35**

Poi rilancia con `--skip-create` (il DB esiste già):

```bash
php artisan hub:provision-workspace beauty-of-image --skip-create
php artisan hub:provision-workspace piramide35 --skip-create
```

---

## Step 5 — Verifiche

| Controllo | URL / azione |
|-----------|----------------|
| Home carousel | https://inm35.it — immagini card senza testo tagliato |
| Login admin | https://inm35.it/admin/login |
| Tenant Beauty | Accedi come `info@beautyofimage.com` |
| Promo piega 10€ Beauty | https://inm35.it/p/beauty-of-image/piega-10euro — **scade automaticamente dopo 10 giorni** |
| Sito WordPress Beauty | https://beautyofimage.com/promozioni — plugin + webhook (vedi sotto) |

### Promo Piega — scadenza automatica

Il seeder imposta `ends_at` = **10 giorni dal deploy**. Finché è attiva, compare per prima (data pubblicazione più recente).  
Dopo la scadenza esce dall’API e dal sito; **torna in evidenza la promo precedente** se è ancora `published` (es. `always_active`).

Non serve cron aggiuntivo: il filtro `active()` controlla `ends_at` a ogni richiesta.

### WordPress Beauty — promo visibile sul sito

Nel `.env` di **hub-core** su Plesk:

```env
HUB_WEBHOOK_URL=https://beautyofimage.com/wp-json/beauty-hub/v1/sync
HUB_WEBHOOK_SECRET=***stesso secret del plugin WordPress***
HUB_BRIDGE_SECRET=***stesso valore di BEAUTY_HUB_BRIDGE_SECRET su WordPress***
```

Su WordPress: mu-plugin `beauty-hub-core.php` v1.2+ in `wp-content/mu-plugins/` con:
- `BEAUTY_HUB_URL=https://inm35.it`
- `BEAUTY_HUB_WEBHOOK_SECRET` = stesso di `HUB_WEBHOOK_SECRET`
- `BEAUTY_HUB_BRIDGE_SECRET` = stesso di `HUB_BRIDGE_SECRET`

Root sito: `hub-ponte.php` (ponte SSO titolari → admin promo Hub).

Menu **AREA TITOLARI → Promo & volantini** → `https://beautyofimage.com/hub-ponte.php?dest=promos`

**Utenti Hub riconosciuti** (campo `wp_username` in tabella `users`):
| Login WordPress | Email Hub |
|-----------------|-----------|
| `info` | info@beautyofimage.com |
| `emilia` | emilia@beautyofimage.com |
| `pasquale` | pasquale.costantino@ferrero.com |

Per altri titolari WP (es. `rosalia`): creare utente Hub con `wp_username` uguale al login WP.

Dopo il seed la promo è già **pubblicata**; il webhook parte al prossimo publish manuale da admin, oppure WordPress sincronizza via API/cron entro 15 minuti.

---

## Step 6 — Permessi (se errori 500 / storage)

```bash
chmod -R ug+rwx storage bootstrap/cache
```

Su Plesk spesso è già a posto; serve solo se compaiono errori di scrittura.

---

## Document root Plesk

Deve puntare a **`public/`** (non la root del repo).

```
/path/to/hub-core/public
```

---

## Riepilogo ordine operazioni

```
1. git push          (locale)
2. git pull          (Plesk / SSH)
3. Crea DB Plesk     (hub_beauty, hub_piramide35)
4. Aggiorna .env
5. composer install
6. migrate + seed + provision-workspace ×2
7. config/route/view cache
8. Verifica sito
```

---

## Prossimi deploy (quando ci saranno workspace separati)

Non fa parte di questo deploy:

- Sito `beautyofimage.inm35.it` → repo `beauty-workspace`
- Sito `piramide35.inm35.it` → repo `piramide-workspace`

Vedi `docs/PIANO-SVILUPPO.md` §7 (domini in transizione).
