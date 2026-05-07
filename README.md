# WP Paid Attachments

Plugin WordPress per monetizzare singoli attachment (immagini ad alta risoluzione)
tramite donazione PayPal opzionale, con sblocco via codice ricevuto per email.

## Caratteristiche principali

- Soft paywall su singoli attachment WordPress (immagini)
- Donazione PayPal opzionale (Donate classico + Smart Buttons)
- Codici di sblocco univoci con scadenza configurabile e hashing bcrypt
- Visualizzazione gratuita opzionale (per-sessione, nessuna traccia persistente lato client)
- Pannello admin con statistiche aggregate (donazioni, free views, conversion rate)
- Web Components vanilla per il frontend pubblico (zero jQuery, Shadow DOM)
- Endpoint REST API namespace `wppa/v1`
- Architettura estensibile tramite `PaymentProviderInterface`
- Build pipeline con `@wordpress/scripts` (JSX admin + SCSS/BEM)

## Requisiti

- WordPress 6.4+
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Account PayPal Business (per ricevere donazioni)

## Installazione

### Manuale

1. Scarica o clona il repository in `wp-content/plugins/wp-paid-attachments/`
2. Installa le dipendenze:
   ```bash
   composer install --no-dev
   npm install && npm run build
   ```
3. Attiva il plugin da **Amministrazione WordPress → Plugin**

### Tramite Composer (progetto WP)

```json
{
    "require": {
        "mavida/wp-paid-attachments": "^0.1"
    }
}
```

## Sviluppo locale

### Prerequisiti

- Node.js 20+
- Composer 2+
- Docker (per wp-env)

### Setup

```bash
git clone <repo-url>
cd wp-paid-attachments
composer install
npm install
npm run build
npm run wp-env start
```

L'ambiente sarà disponibile su `http://localhost:8888`.
Credenziali admin: `admin` / `password`.

### Comandi utili

| Comando | Scopo |
|---|---|
| `npm run build` | Build asset produzione |
| `npm run start` | Build asset con watch |
| `npm run wp-env start` | Avvia ambiente Docker |
| `npm run wp-env stop` | Ferma ambiente |
| `npm run wp-env:cli -- wp ...` | Esegue WP-CLI nel container |
| `composer lint` | Esegue PHPCS (WordPress Coding Standards) |
| `composer lint:fix` | Esegue PHPCBF (autocorrezione) |
| `composer lint:summary` | Report riassuntivo PHPCS |

### Test dei webhook PayPal in locale

Il container locale non è raggiungibile da PayPal. Per testare i webhook usa il
**PayPal Webhooks Simulator** nel [PayPal Developer Dashboard](https://developer.paypal.com):

1. Vai su *My Apps & Credentials → (seleziona app sandbox) → Webhooks*
2. Copia l'URL del tuo endpoint webhook (dopo averlo esposto con un tunnel, oppure usa
   il simulatore integrato di PayPal per inviare eventi di test a un endpoint temporaneo)
3. Usa il pulsante **Simulate** per inviare eventi `PAYMENT.CAPTURE.COMPLETED`

Per ambienti staging/produzione con URL pubblico, il webhook funziona direttamente senza
configurazioni aggiuntive.

## Configurazione

### 1. Impostazioni PayPal

Dal menu **Paid Attachments → Impostazioni** configura:

- **PayPal Business Email** (per Donate classico)
- **Hosted Button ID** (per Donate classico, creato su paypal.com)
- **Client ID** e **Client Secret** (per Smart Buttons, da [developer.paypal.com](https://developer.paypal.com))
- **Modalità**: Sandbox (test) o Live (produzione)

### 2. Default globali

Configura i valori di default che saranno applicati a tutti i nuovi attachment protetti:
importi suggeriti, modalità pagamento, validità codice, opzione free-view, testi email.

### 3. Abilitare la protezione su un attachment

1. Vai su **Media Library** e seleziona un'immagine
2. Nella sidebar dell'editor troverai il pannello **Paid Attachment**
3. In alternativa usa il menu **Paid Attachments → Attachment protetti**
4. Abilita il toggle "Protezione attiva" e personalizza le impostazioni

## Architettura

Il plugin segue un'architettura OOP con PSR-4 autoload:

```
src/
├── Plugin.php           — Bootstrap singleton, registrazione hook centralizzata
├── Activator.php        — Lifecycle attivazione (tabelle DB, default options)
├── Deactivator.php      — Lifecycle disattivazione
├── Admin/               — Menu, pagine admin, integrazione Media Library
├── Frontend/            — Rendering attachment page, enqueue asset pubblici
├── Payment/             — PaymentProviderInterface, implementazioni PayPal
├── Database/            — Schema, Repository per ogni tabella custom
├── Domain/              — Value objects (UnlockCode, Payment, AttachmentConfig…)
├── Email/               — EmailSender, TemplateRenderer con placeholder
├── REST/                — Controller REST (namespace wppa/v1)
└── Support/             — Hmac, RateLimiter, Logger
```

Il frontend pubblico è realizzato interamente con Web Components vanilla
(`<wppa-donation-widget>`, `<wppa-unlock-form>`) per evitare dipendenze pesanti.

## Sicurezza

- Tutti i token di visualizzazione/download sono firmati con HMAC (SHA-256, secret generato all'attivazione)
- I codici di sblocco sono hashati con bcrypt; solo il prefix (4 char) è salvato in chiaro
- Rate limiting: 5 tentativi/ora per codice, 10 free-view/giorno per IP, 20 checkout/ora per IP
- Nonce WordPress su tutte le azioni; capability check `manage_options` su endpoint admin
- Gli attachment protetti ricevono `noindex` (meta tag + `X-Robots-Tag`)
- Webhook PayPal verificati tramite firma crittografica (PayPal Webhooks API)

## Contribuire

1. Fork del repository
2. Crea un branch feature: `git checkout -b feat/nome-feature`
3. Assicurati che `composer lint` e `npm run build` passino senza errori
4. Apri una Pull Request descrivendo le modifiche

## Licenza

GPL v2 or later. Testo completo disponibile su
[gnu.org](https://www.gnu.org/licenses/gpl-2.0.html).

## Autore

Sviluppato da [Mavida snc](https://mavida.com).
