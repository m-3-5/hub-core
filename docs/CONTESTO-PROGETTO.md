# Contesto progetto Hub Core

Documento di riferimento per chat nuove e collaboratori. Aggiornare quando cambiano decisioni importanti.

## Cos'è

**Hub Core** è un Laravel 13 multi-tenant su **inm35.it** che gestisce promo digitali, moduli futuri (servizi, store, agenda…) per attività clienti.

| Tenant | Slug | Sito pubblico | Piano |
|--------|------|---------------|-------|
| Beauty of Image | `beauty-of-image` | beautyofimage.com | dedicated |
| Piramide35 | `piramide35` | piramide35.com | dedicated |

Workspace premium futuri: `app.beautyofimage.com`, `app.piramide35.com` (DB separati `hub_beauty`, `hub_piramide35`).

## Architettura

```
beautyofimage.com (WordPress)
    │ shortcode [beauty_promos], webhook sync
    │ hub-ponte.php → SSO titolari
    ▼
inm35.it (hub-core Laravel)
    │ API /api/v1/{tenant}/promos
    │ Admin /admin/tenants/{tenant}/promos
    │ Landing /p/{tenant}/{slug}
    ▼
Gemini (opzionale, PROMO_AI_IMAGES=false di default → SVG)
```

## Auth admin

- Login: `/admin/login`
- Reset password email: `/admin/password/dimenticata` (SMTP Aruba `noreply@inm35.net`, `out.postassl.it:465`)
- Bridge WordPress: `/auth/wp-bridge` — utenti con `wp_username` nel DB hub
- Emergenza SSH: `php artisan hub:reset-password {email}`

## Promo

- 5 promo/mese incluse (`PROMO_INCLUDED_QUOTA=5`)
- Tier base (SVG) vs volantino IA €24 (paywall Stripe futuro)
- Archivio pubblico: `/p/{tenant}`
- Scadenza automatica via `ends_at` + scope `active()`

## WordPress Beauty — deploy file

Dopo `git pull` su Plesk, copiare da:

```
deploy/beautyofimage-wordpress/
├── hub-ponte.php              → root WP
├── mu-plugins/
│   ├── beauty-hub-core.php    → wp-content/mu-plugins/
│   └── beauty-hub-control.php → wp-content/mu-plugins/
└── README.md
```

Secrets da allineare: `HUB_WEBHOOK_SECRET`, `HUB_BRIDGE_SECRET` (hub `.env`) = `BEAUTY_HUB_*` (plugin WP).

## Repo correlati

| Repo | Path locale Herd |
|------|------------------|
| hub-core | `Herd/hub-core` |
| beautyofimage | `Herd/beautyofimage` |

## Decisioni prodotto (non implementare senza richiesta)

- Chat IA utenti finali: **Gemini**, non Cursor API
- Google Business posting: fase 2
- Stripe paywall volantino IA: futuro
- Hub resta multi-tenant; clienti premium = fork Laravel + DB dedicato

## Deploy Plesk rapido

```bash
git pull origin master
composer install --no-dev
php artisan migrate --force
php artisan config:clear && php artisan route:clear
php artisan config:cache && php artisan route:cache
```

Vedi `docs/DEPLOY-PLESK.md` per il dettaglio.

## Ultimi commit rilevanti

- Promo archivio, share, quota create form
- Mail Aruba + `hub:test-mail`, `hub:reset-password`
- Bridge WP `dest=promos` + pacchetto `deploy/beautyofimage-wordpress/`
