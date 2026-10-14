# Bozze di promo da post esterni (Facebook / Instagram)

Il sito del cliente (es. Beauty of Image) legge i post con `#promo` e li manda a Hub Core come **bozza da approvare**.

## Chiamata

`POST https://inm35.it/api/v1/{tenantSlug}/promos/drafts`

Firma identica a checkout e preventivi (`HUB_BRIDGE_SECRET`):

- `X-Hub-Timestamp`: unix time (max 5 minuti di scarto)
- `X-Hub-Signature`: `sha256=` + HMAC-SHA256(`timestamp + "." + corpo_grezzo`, `HUB_BRIDGE_SECRET`)

Corpo JSON:

| campo | | note |
|---|---|---|
| `source` | obbligatorio | `facebook` o `instagram` |
| `source_id` | obbligatorio | id del post; con `source` identifica il post: **una sola promo per post, per sempre** |
| `source_url` | opzionale | https, mostrato come «Vedi il post originale» |
| `title` | obbligatorio | titolo della promo |
| `description` | opzionale | testo |
| `image_url` | obbligatorio | https, host che finisce in `fbcdn.net`, `cdninstagram.com` o `facebook.com` (config `hub.promo_import.image_hosts`) |
| `ends_at` | opzionale | ISO 8601; se manca, all'approvazione la scadenza diventa +1 mese |
| `posted_at` | opzionale | ISO 8601 |
| `publish` | opzionale | `false` (default): bozza; `true`: pubblicata subito + webhook di sincronizzazione |

## Risposte

- `201` creata: `{"duplicate": false, "promo": {"id", "slug", "status": "draft", "title", "admin_url", "public_url": null}}`
- `200` già ricevuta: `{"duplicate": true, "promo": {...} | null, "deleted": bool}` (`promo: null` e `deleted: true` se la bozza era stata eliminata: **non** viene ricreata)
- `401` firma assente, errata o scaduta
- `422` dati non validi, host della foto non consentito, foto non scaricabile (link scaduto…): nulla viene registrato, il sito riprova al giro dopo
- `429` troppe richieste (60/min)

## Cosa fa l'hub

1. Controlla `source` + `source_id` (anche tra le promo eliminate: tabella `promo_sources`).
2. Scarica **subito** la foto e la salva sul proprio disco (i link di Facebook scadono).
3. Crea la promo `draft` (non compare in `/api/v1/{tenant}/promos`, nell'archivio, né nella sitemap).
4. Manda l'email «Nuova promo da approvare: {titolo}» agli utenti del tenant (gli stessi degli avvisi ordini), con foto e pulsante.
5. Nel pannello promo compare la sezione **Da approvare (n)** con *Approva e pubblica*, *Modifica*, *Elimina* (scartata per sempre).

All'approvazione: stato `published`, scadenza a un mese se mancava, webhook `promo.published` verso il sito.
