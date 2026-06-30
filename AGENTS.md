# Hub Core — guida per l'agente AI

Leggi prima **`docs/CONTESTO-PROGETTO.md`** per architettura, tenant, deploy e decisioni prodotto.

## Repo e ambienti

| Cosa | Dove |
|------|------|
| Hub Laravel (produzione) | `inm35.it` — repo `m-3-5/hub-core` |
| Hub locale | `hub-core.test` (Herd) |
| WordPress Beauty | `beautyofimage.com` — repo separato `beautyofimage` |
| File WP da copiare | `deploy/beautyofimage-wordpress/` (dopo git pull su Plesk) |

## Comandi utili

```bash
php artisan hub:test-mail {email}
php artisan hub:reset-password {email}
php artisan hub:send-access-link {email}
php artisan hub:provision-workspace {slug} --skip-create
```

## Documentazione

- `docs/CONTESTO-PROGETTO.md` — contesto completo
- `docs/MODULO-PAGAMENTI.md` — servizi Stripe
- `docs/DEPLOY-PLESK.md` — deploy produzione
- `docs/PIANO-SVILUPPO.md` — roadmap
- `docs/workspace-credentials.local.md` — credenziali (gitignored, solo locale)

## Regole

- Non committare `.env` né password
- Tenant Beauty: slug `beauty-of-image`; Piramide: `piramide35`
- Promo pubbliche: `/p/{tenant}` archivio, `/p/{tenant}/{slug}` landing
- Bridge WP→Hub: `/auth/wp-bridge` + `HUB_BRIDGE_SECRET`
