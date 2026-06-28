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

## Prima di copiare

1. In `beauty-hub-core.php` imposta i secret (devono coincidere con il `.env` hub su inm35.it):
   - `BEAUTY_HUB_WEBHOOK_SECRET` = `HUB_WEBHOOK_SECRET`
   - `BEAUTY_HUB_BRIDGE_SECRET` = `HUB_BRIDGE_SECRET`

2. `beauty-hub-control.php` richiede che su WordPress esistano già:
   - `wp-content/mu-plugins/beauty-shortcodes.php`
   - `wp-content/mu-plugins/shortcode-lavori.php`  
   (non sono in questo pacchetto — sono specifici del sito Beauty)

## Verifica

1. Login su beautyofimage.com come titolare (`info`, `pasquale`, `emilia`, …)
2. Menu → **AREA TITOLARI** → **Promo & volantini**
3. Deve aprire `https://inm35.it/admin/tenants/beauty-of-image/promos` già loggato

## Utenti Hub (campo `wp_username`)

| Login WP | Email hub |
|----------|-----------|
| `info` | info@beautyofimage.com |
| `emilia` | emilia@beautyofimage.com |
| `pasquale` | pasquale.costantino@ferrero.com |

Altri titolari WP: creare utente hub con `wp_username` uguale al login WordPress.
