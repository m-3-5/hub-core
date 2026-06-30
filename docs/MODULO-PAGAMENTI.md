# Modulo pagamenti — servizi (Stripe)

Package: `packages/hub-payments` (`m35/hub-payments`)

## Uso staff (inm35.it)

1. App Beauty → tile **Servizi**
2. Inserire **Secret key** Stripe del salone (una tantum)
3. **+ Nuovo servizio** → titolo, descrizione, prezzo €
4. Copiare link (WhatsApp / email al cliente)

## Demo / quota

- **3 servizi** inclusi per tenant (env `HUB_PAYMENTS_SERVICES_QUOTA`)
- Oltre la quota: messaggio paywall (pagamento hub in arrivo)

## API sito

```
GET /api/v1/beauty-of-image/services
```

Solo servizi con `published_to_site=true`.

## Deploy

```bash
composer install
php artisan migrate
php artisan db:seed --class=BeautyOfImageSeeder
```

Beauty: abilitare modulo `services` in `tenant.settings.modules`.

## Stripe Beauty

- Conto Stripe del cliente (non M35)
- Klarna: attivo su Dashboard Stripe → Metodi di pagamento
- Chiavi salvate cifrate in `tenant.settings.stripe`
