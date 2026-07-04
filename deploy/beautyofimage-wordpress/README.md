# File WordPress da copiare su beautyofimage.com

Dopo `git pull` su **inm35.it**, questi file sono in:

```
deploy/beautyofimage-wordpress/
```

**Non vanno eseguiti su inm35.it** — sono solo un pacchetto pronto da copiare sul sito WordPress Beauty.

## Dove incollare su beautyofimage.com

| File in questa cartella | Destinazione su WordPress |
|-------------------------|---------------------------|
| `hub-ponte.php` | **Root** del sito (`/hub-ponte.php`) |
| `mu-plugins/beauty-hub-core.php` | `wp-content/mu-plugins/beauty-hub-core.php` |
| `mu-plugins/beauty-hub-control.php` | `wp-content/mu-plugins/beauty-hub-control.php` |
| `mu-plugins/beauty-hub-services-sync.php` | `wp-content/mu-plugins/beauty-hub-services-sync.php` |

`beauty-hub-services-sync.php` è **completamente indipendente** dagli altri file: non li modifica e non ne dipende. Aggiunge lo shortcode `[beauty_services]` (servizi/pagamenti pubblicati dall'hub), sincronizzato con lo stesso meccanismo di `[beauty_promos]` (webhook + cron ogni 15 minuti), ma su un endpoint REST separato (`/wp-json/beauty-hub/v1/sync-services`) così non tocca la sincronizzazione delle promo.

## Prima di copiare

1. In `beauty-hub-core.php` e in `beauty-hub-services-sync.php` imposta i secret (devono coincidere con il `.env` hub su inm35.it):
   - `BEAUTY_HUB_WEBHOOK_SECRET` = `HUB_WEBHOOK_SECRET`
   - `BEAUTY_HUB_BRIDGE_SECRET` = `HUB_BRIDGE_SECRET` (solo in `beauty-hub-core.php`)

2. Su inm35.it (`.env`, locale e Plesk) imposta anche:
   - `HUB_SERVICES_WEBHOOK_URL=https://beautyofimage.com/wp-json/beauty-hub/v1/sync-services`
   (in aggiunta a `HUB_WEBHOOK_URL`/`HUB_WEBHOOK_SECRET` già esistenti, che restano solo per le promo)

3. Sulla pagina WordPress dove vuoi mostrare i servizi, inserisci lo shortcode:
   ```
   [beauty_services]
   ```
   oppure `[beauty_services columns="2"]` per una griglia a 2 colonne.

4. `beauty-hub-control.php` richiede che su WordPress esistano già:
   - `wp-content/mu-plugins/beauty-shortcodes.php`
   - `wp-content/mu-plugins/shortcode-lavori.php`  
   (non sono in questo pacchetto — sono specifici del sito Beauty)

## Verifica

1. Login su beautyofimage.com come titolare (`info`, `pasquale`, `emilia`, …)
2. Menu → **AREA TITOLARI** → **Promo & volantini**
3. Deve aprire `https://inm35.it/admin/tenants/beauty-of-image/promos` già loggato
4. Per i servizi: crea/pubblica un servizio nell'hub, poi apri la pagina WordPress con `[beauty_services]` — deve comparire entro pochi secondi (webhook) o al massimo 15 minuti (cron di backup)

## Utenti Hub (campo `wp_username`)

| Login WP | Email hub |
|----------|-----------|
| `info` | info@beautyofimage.com |
| `emilia` | emilia@beautyofimage.com |
| `pasquale` | pasquale.costantino@ferrero.com |

Altri titolari WP: creare utente hub con `wp_username` uguale al login WordPress.
