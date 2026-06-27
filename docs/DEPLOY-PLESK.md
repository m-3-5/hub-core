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
TENANT_BEAUTY_URL=https://app.beautyofimage.com
TENANT_PIRAMIDE35_DATABASE=hub_piramide35
TENANT_PIRAMIDE35_URL=https://app.piramide35.com

# WordPress Beauty — promo sul sito (se plugin installato)
HUB_WEBHOOK_URL=https://beautyofimage.com/wp-json/beauty-hub/v1/sync
HUB_WEBHOOK_SECRET=***stesso secret del mu-plugin WordPress***
```

> Le password DB dedicate (`hub_beauty_user`, ecc.) **non** vanno in questo `.env` — serviranno sui futuri siti `app.*`. Qui basta `hub_core_user` con accesso anche a `hub_beauty` e `hub_piramide35`.

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

### Se `hub:provision-workspace` fallisce su CREATE DATABASE

L’utente MySQL potrebbe non avere permesso di creare DB. In quel caso:

1. Crea `hub_beauty` e `hub_piramide35` dal pannello Plesk
2. Rilancia solo:

```bash
php artisan hub:provision-workspace beauty-of-image
php artisan hub:provision-workspace piramide35
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
```

Su WordPress: mu-plugin `beauty-hub-core.php` in `wp-content/mu-plugins/` con `BEAUTY_HUB_URL=https://inm35.it` e lo stesso secret. Pagina promozioni con shortcode `[beauty_promos]`.

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
