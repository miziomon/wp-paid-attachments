# Prompt vibe coding — Plugin WordPress "wp-paid-attachments"

> **Istruzioni d'uso**: copia tutto il contenuto qui sotto (dalla riga "## Contesto" in poi) e incollalo come primo messaggio in Claude Code. Esegui prima in **plan mode** per validare la pianificazione, poi procedi con l'implementazione.

---

## Contesto

Sei un senior WordPress developer con 20+ anni di esperienza in PHP, e profonda conoscenza di JavaScript moderno (ES2022+, Web Components, JSX), SCSS/BEM e best practice WordPress. Stai sviluppando un plugin WordPress open source che sarà ospitato su Git e potenzialmente distribuito su WordPress.org.

L'obiettivo del plugin è permettere ai proprietari di blog WordPress di **monetizzare singoli attachment (immagini ad alta risoluzione)** tramite un modello "soft paywall" basato su donazione PayPal opzionale ma consigliata, con la possibilità di sbloccare l'immagine tramite codice ricevuto via email dopo la donazione.

### Prima di iniziare — verifica delle skill disponibili

**Prima di entrare in plan mode**, verifica se nell'ambiente Claude Code sono presenti skill installate (file `SKILL.md`) potenzialmente rilevanti per WordPress, plugin development, PHP, JSX, o ambienti correlati. Controlla in:

1. `.claude/skills/` nella root del progetto
2. `~/.claude/skills/` nella home dell'utente
3. `/mnt/skills/` se presente
4. Eventuale tag `<available_skills>` nelle istruzioni di sistema

**Se trovi skill rilevanti**: leggile per intero con il tool `view` PRIMA di pianificare, e dichiara esplicitamente quali stai applicando. Le skill installate hanno priorità sulle convenzioni generiche di questo prompt in caso di conflitto su dettagli implementativi (es. struttura cartelle, naming, pattern di codice). In caso di conflitto sostanziale, segnalalo e chiedimi conferma.

**Se non trovi skill rilevanti**: procedi seguendo le specifiche di questo prompt.

### Modalità di lavoro

Dopo la verifica skill, **leggi attentamente tutto questo documento, poi entra in plan mode** e proponi una pianificazione strutturata: architettura, struttura file, schema database, flusso utente, ordine di implementazione. Aspetta la mia approvazione prima di iniziare l'implementazione.

---

## 1. Identità del plugin

- **Nome pubblico**: WP Paid Attachments
- **Slug / cartella plugin**: `wp-paid-attachments`
- **File principale**: `wp-paid-attachments.php`
- **Text domain**: `wp-paid-attachments`
- **Namespace PHP**: `PaidAttachments\`
- **Prefisso DB / hooks / options / transient**: `wppa_`
- **Costanti PHP**: `WPPA_VERSION`, `WPPA_PLUGIN_DIR`, `WPPA_PLUGIN_URL`, `WPPA_PLUGIN_FILE`, `WPPA_DEBUG`
- **Versione iniziale**: 0.1.0
- **Autore**: Mavida snc
- **Author URI**: https://mavida.it (o equivalente)
- **Plugin URI**: URL del repository Git
- **Licenza**: GPL v2 or later
- **Requisiti minimi**: WordPress 6.4+, PHP 8.1+

### Importante — assenza di riferimenti "Mavida" nel codice

Il nome **Mavida** deve apparire **esclusivamente** in:
- Header del file principale del plugin (campo `Author` e `Author URI`)
- File `README.md` (sezione autore/credits)
- File `composer.json` (campo `authors`)
- File `package.json` (campo `author`)
- File `LICENSE` (eventuale copyright header)

**Non deve apparire** in:
- Nomi di classi, namespace, metodi, funzioni
- Nomi di tabelle DB, opzioni, hook, filter, transient
- Commenti di codice (eccetto eventuale header del singolo file di tipo "@author Mavida snc" se ritenuto utile per contesto)
- Stringhe user-facing
- Asset JS/CSS, ID/classi CSS, custom element names
- Path di file/cartelle interne

---

## 2. Stack tecnologico

### Backend
- **PHP 8.1+** con OOP, namespace, type hints rigorosi (`declare(strict_types=1)` in tutti i file PHP)
- **Autoload PSR-4** via Composer (`composer.json` con autoload `PaidAttachments\\` → `src/`)
- Pattern: classi piccole e single-responsibility, dependency injection manuale (no container)
- Tabelle DB custom (non solo postmeta) per query efficienti
- Tutte le query DB tramite `$wpdb->prepare()`
- Tutti gli output sanitizzati con `esc_*()` appropriati
- Tutti gli input validati con `sanitize_*()` appropriati
- Nonce WordPress su tutte le azioni admin/AJAX/REST
- Rate limiting via `set_transient()` dove appropriato

### Frontend admin
- **JSX + @wordpress/scripts** (build configurato in `package.json`)
- Componenti React funzionali con hooks
- `@wordpress/components` per i controlli UI nativi (Button, TextControl, ToggleControl, ecc.)
- `@wordpress/api-fetch` per le chiamate REST
- `@wordpress/i18n` per le traduzioni (`__()`, `_n()`, ecc.)
- Build output in `/build/` (non committato)

### Frontend pubblico (sulla attachment page)
- **Web Component vanilla** (`class extends HTMLElement`) con Shadow DOM
- Niente jQuery, niente React sul frontend pubblico (per leggerezza)
- Custom events per la comunicazione
- Attributi reattivi via `observedAttributes` / `attributeChangedCallback`
- Custom element names con prefisso `wppa-`: `<wppa-donation-widget>`, `<wppa-unlock-form>`

### Stili
- **SCSS con metodologia BEM** rigorosa
- Variabili CSS custom properties (`--wppa-*`) per theming
- Build SCSS via `@wordpress/scripts`
- Selettori massimo 2 livelli di nesting
- Nomi BEM: `block__element--modifier`, prefisso block `wppa-`

### Coding Standards
- **PHPCS + WordPress Coding Standards (WPCS)** obbligatori
- Configurazione in `.phpcs.xml.dist` (vedi sezione 12)
- `composer require --dev`:
  - `squizlabs/php_codesniffer` ^3.10
  - `wp-coding-standards/wpcs` ^3.1
  - `phpcompatibility/phpcompatibility-wp` ^2.1
  - `dealerdirect/phpcodesniffer-composer-installer` ^1.0
- Script composer:
  - `composer lint` → esegue PHPCS
  - `composer lint:fix` → esegue PHPCBF
- Tutto il codice PHP deve passare `composer lint` senza errori (warning minimi tollerati ma documentati)
- **JS**: ESLint config WordPress (`@wordpress/eslint-plugin`)
- **SCSS**: stylelint config WordPress (incluso in wp-scripts)

### Ambiente di sviluppo locale
- **wp-env** (`@wordpress/env`) come ambiente Docker locale
- Configurazione in `.wp-env.json` (vedi sezione 12)
- Script npm:
  - `npm run wp-env start` → avvia ambiente (port 8888)
  - `npm run wp-env stop`
  - `npm run wp-env destroy`
  - `npm run wp-env:cli -- wp ...` → wrapper WP-CLI

### Dipendenze esterne
- Integrazione PayPal: HTTP nativo (`wp_remote_post`, `wp_remote_get`) per evitare dipendenze pesanti, con verifica firma webhook manuale
- Nessuna altra dipendenza pesante: il plugin deve essere self-contained

---

## 3. Architettura ad alto livello

```
wp-paid-attachments/
├── wp-paid-attachments.php           # Bootstrap, header WP, hook activate/deactivate
├── uninstall.php                     # Pulizia totale dati alla disinstallazione
├── composer.json                     # PSR-4 autoload + dev deps (PHPCS, WPCS)
├── package.json                      # @wordpress/scripts, wp-env
├── .wp-env.json                      # Configurazione ambiente locale
├── .phpcs.xml.dist                   # Configurazione PHPCS/WPCS
├── .gitignore
├── .editorconfig
├── README.md                         # In italiano
├── CHANGELOG.md                      # Keep a Changelog + SemVer
├── LICENSE                           # GPL v2
│
├── src/
│   ├── Plugin.php                    # Classe principale (singleton/main bootstrap)
│   ├── Activator.php                 # Logica activation hook (creazione tabelle, opzioni default)
│   ├── Deactivator.php               # Logica deactivation hook
│   │
│   ├── Admin/
│   │   ├── AdminMenu.php
│   │   ├── SettingsPage.php
│   │   ├── AttachmentsListPage.php
│   │   ├── StatsPage.php
│   │   └── MediaLibraryIntegration.php
│   │
│   ├── Frontend/
│   │   ├── AttachmentPageRenderer.php
│   │   ├── AssetsLoader.php
│   │   └── ShortcodeHandler.php
│   │
│   ├── Payment/
│   │   ├── PaymentProviderInterface.php
│   │   ├── PayPalDonateProvider.php
│   │   ├── PayPalSmartButtonsProvider.php
│   │   ├── PaymentProviderFactory.php
│   │   └── WebhookHandler.php
│   │
│   ├── Database/
│   │   ├── Schema.php
│   │   ├── UnlockCodeRepository.php
│   │   ├── PaymentRepository.php
│   │   ├── StatsRepository.php
│   │   └── AttachmentConfigRepository.php
│   │
│   ├── Domain/
│   │   ├── UnlockCode.php
│   │   ├── AttachmentConfig.php
│   │   ├── Payment.php
│   │   └── ViewToken.php
│   │
│   ├── Email/
│   │   ├── EmailSender.php
│   │   └── TemplateRenderer.php
│   │
│   ├── REST/
│   │   ├── RestController.php
│   │   ├── CheckoutController.php
│   │   ├── UnlockController.php
│   │   ├── FreeViewController.php
│   │   └── AdminController.php
│   │
│   └── Support/
│       ├── Hmac.php
│       ├── Logger.php
│       └── RateLimiter.php
│
├── assets/
│   ├── admin/
│   │   ├── index.jsx
│   │   ├── components/
│   │   │   ├── SettingsForm.jsx
│   │   │   ├── AttachmentRow.jsx
│   │   │   ├── AttachmentEditor.jsx
│   │   │   ├── EmailTemplateEditor.jsx
│   │   │   └── StatsDashboard.jsx
│   │   └── styles/
│   │       └── admin.scss
│   │
│   └── public/
│       ├── unlock-form.js
│       ├── donation-widget.js
│       └── styles/
│           └── public.scss
│
├── templates/
│   ├── email/
│   │   ├── default-html.php
│   │   └── default-text.php
│   └── frontend/
│       └── attachment-paywall.php
│
└── languages/
    └── wp-paid-attachments.pot
```

---

## 4. Schema database

Quattro tabelle custom (con prefisso `{$wpdb->prefix}wppa_`):

### Tabella `wppa_attachment_config`
Configurazione per ogni attachment protetto.

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `attachment_id` | BIGINT UNSIGNED UNIQUE | FK a `wp_posts.ID` |
| `enabled` | TINYINT(1) DEFAULT 1 | |
| `payment_mode` | VARCHAR(32) | `paypal_donate` \| `paypal_smart` \| `both` |
| `suggested_amounts` | JSON | Es. `[1, 3, 5]` |
| `min_amount` | DECIMAL(10,2) | |
| `currency` | VARCHAR(3) DEFAULT 'EUR' | |
| `code_validity_days` | INT DEFAULT 30 | |
| `code_max_uses` | INT DEFAULT 0 | 0 = illimitato |
| `custom_text` | LONGTEXT | HTML permesso |
| `custom_email_subject` | VARCHAR(255) NULL | |
| `custom_email_body` | LONGTEXT NULL | |
| `allow_free_view` | TINYINT(1) DEFAULT 1 | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

Indice su `attachment_id`.

### Tabella `wppa_payments`

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `attachment_id` | BIGINT UNSIGNED | |
| `provider` | VARCHAR(32) | |
| `provider_transaction_id` | VARCHAR(128) | |
| `amount` | DECIMAL(10,2) | |
| `currency` | VARCHAR(3) | |
| `status` | VARCHAR(32) | `pending` \| `completed` \| `failed` \| `refunded` |
| `donor_email` | VARCHAR(255) | |
| `donor_name` | VARCHAR(255) NULL | |
| `unlock_code_id` | BIGINT UNSIGNED NULL | |
| `ip_address` | VARCHAR(45) | |
| `user_agent` | VARCHAR(500) NULL | |
| `metadata` | JSON | Payload completo PayPal |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

Indici: `attachment_id`, `provider_transaction_id` UNIQUE, `donor_email`, `status`.

### Tabella `wppa_unlock_codes`

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `attachment_id` | BIGINT UNSIGNED | |
| `code_hash` | VARCHAR(255) | bcrypt |
| `code_prefix` | VARCHAR(8) | Primi 4 char in chiaro |
| `payment_id` | BIGINT UNSIGNED NULL | |
| `email` | VARCHAR(255) | |
| `expires_at` | DATETIME | |
| `max_uses` | INT DEFAULT 0 | |
| `used_count` | INT DEFAULT 0 | |
| `last_used_at` | DATETIME NULL | |
| `last_used_ip` | VARCHAR(45) NULL | |
| `revoked` | TINYINT(1) DEFAULT 0 | |
| `created_at` | DATETIME | |

Indici: `attachment_id`, `email`, `code_prefix`, `expires_at`.

### Tabella `wppa_free_views`

| Campo | Tipo | Note |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| `attachment_id` | BIGINT UNSIGNED | |
| `ip_address` | VARCHAR(45) | |
| `user_agent` | VARCHAR(500) NULL | |
| `created_at` | DATETIME | |

Indici: `attachment_id`, `created_at`.

---

## 5. Requisiti funzionali dettagliati

### 5.1 Pannello di controllo admin

Menu top-level **"Paid Attachments"** con icona dashicon (`dashicons-money-alt`) e i seguenti sottomenu:

#### 5.1.1 Sottomenu "Impostazioni" (`SettingsPage.php`)

**Sezione "PayPal"**
- PayPal Business Email
- PayPal Hosted Button ID (per Donate classico)
- PayPal Client ID (per Smart Buttons)
- PayPal Client Secret (password)
- Modalità: Sandbox / Live (toggle)

**Sezione "Default per nuovi attachment"**
- Modalità pagamento default
- Importi suggeriti default (es. `1, 3, 5`)
- Importo minimo default
- Valuta default
- Validità codice in giorni default
- Max usi codice default
- Permettere visualizzazione gratuita default

**Sezione "Testi default"**
- Testo paywall default (Editor TinyMCE)
- Subject email default
- Body email default (HTML, placeholder documentati)

**Sezione "Avanzate"**
- Email del mittente
- Nome del mittente
- Abilita logging debug
- Pulizia cache transient

UI in **JSX + @wordpress/components**, mount in `<div id="wppa-admin-root">`.

#### 5.1.2 Sottomenu "Attachment protetti" (`AttachmentsListPage.php`)

Lista filtrata di tutti gli attachment di tipo immagine. Per ogni riga:
- Thumbnail
- Titolo / nome file
- Data upload
- Stato protezione (badge)
- Donazioni ricevute (count + totale)
- Visualizzazioni gratuite (count)
- Conversion rate
- Azioni: Configura / Disattiva / Vedi statistiche

Filtri: stato, ricerca, ordinamento.

Click su "Configura" → modal/drawer (`AttachmentEditor.jsx`):
- Toggle "Abilita protezione"
- Override sezione testo personalizzato
- Override modalità pagamento
- Override importi suggeriti
- Override validità codice
- Override max usi
- Override permettere visualizzazione gratuita
- Override email subject/body
- Pulsante "Reset a default globale"
- Salva via REST API

#### 5.1.3 Sottomenu "Statistiche" (`StatsPage.php`)
- KPI: totale donazioni, totale ricevuto, totale visualizzazioni gratuite, conversion rate medio
- Grafico temporale (ultimi 30 giorni)
- Top 10 attachment per ricavi
- Top 10 attachment per visualizzazioni
- Filtro periodo: 7gg / 30gg / 90gg / custom

#### 5.1.4 Integrazione Media Library
- Colonna "Paid" nella tabella di Media Library con badge stato
- Pannello "Paid Attachment" nella sidebar di edit attachment

### 5.2 Frontend pubblico — flusso utente

#### 5.2.1 Detection attachment protetto

```php
if ( is_attachment() && $this->config_repo->is_protected( get_the_ID() ) ) {
    // Sostituisci rendering standard
}
```

Se l'attachment è protetto e abilitato, **nascondi l'immagine ad alta risoluzione** dalla pagina e mostra il **paywall renderizzato dal Web Component**.

#### 5.2.2 Stati del paywall

Il Web Component `<wppa-donation-widget>` ha 4 stati interni:

**Stato A — Paywall iniziale**
- Preview a bassa risoluzione (thumbnail/medium WordPress) con overlay
- Testo personalizzato configurato
- Opzioni di pagamento secondo `payment_mode`:
  - `paypal_donate`: pulsante "Dona con PayPal"
  - `paypal_smart`: rendering Smart Buttons inline
  - `both`: tab/toggle tra le due modalità
- Importi suggeriti come pillole + campo "Altro importo"
- Link secondario "Ho già un codice di sblocco" → Stato B
- Se `allow_free_view = true`: link terziario meno prominente "Continua senza donare" → Stato D

**Stato B — Inserimento codice**
- Input testo (formato `XXXX-XXXX-XXXX`)
- Submit → POST `/wp-json/wppa/v1/unlock`
- Se valido: Stato C
- Se non valido: errore (rate-limited)
- Link back a Stato A

**Stato C — Immagine sbloccata**
- Immagine ad alta risoluzione (URL firmato HMAC, scadenza 1 ora)
- Pulsante "Scarica originale"
- Se utente ha appena pagato: ringraziamento + "il codice è stato inviato a {email}"
- Cookie firmato HMAC per ricordare lo sblocco

**Stato D — Visualizzazione gratuita una tantum**
- Immagine ad alta risoluzione
- Banner persistente: "Stai vedendo questa immagine gratuitamente. Se ti piace, considera una donazione."
- Salva in `sessionStorage` (`wppa_freeview_{attachment_id}`)
- POST silenziosa a `/wp-json/wppa/v1/free-view` per logging

#### 5.2.3 Web Components

**`<wppa-donation-widget>`**
Attributi: `attachment-id`, `payment-mode`, `amounts`, `min-amount`, `currency`, `allow-free-view`, `custom-text-html`

Eventi custom:
- `wppa:donation-completed`
- `wppa:code-submitted`
- `wppa:code-validated`
- `wppa:free-view-clicked`

**`<wppa-unlock-form>`**
Attributi: `attachment-id`
Eventi: `wppa:unlock-success`, `wppa:unlock-failure`

Entrambi usano Shadow DOM, espongono CSS custom properties (`--wppa-primary-color`, `--wppa-radius`, `--wppa-font`).

### 5.3 Endpoint REST API

Namespace: `wppa/v1`

| Metodo | Endpoint | Auth | Scopo |
|---|---|---|---|
| POST | `/checkout` | Nonce | Crea ordine PayPal Smart |
| POST | `/checkout/capture` | Nonce | Capture ordine PayPal Smart |
| POST | `/webhook/paypal` | Firma PayPal | Riceve webhook PayPal |
| POST | `/unlock` | Nonce | Valida codice |
| POST | `/free-view` | Nonce | Registra visualizzazione gratuita |
| GET | `/download/{token}` | Token HMAC | Serve file alta risoluzione |
| GET | `/admin/stats` | `manage_options` | Stats aggregate |
| GET | `/admin/attachments` | `manage_options` | Lista attachment |
| POST | `/admin/attachment/{id}/config` | `manage_options` | Salva config |
| GET | `/admin/settings` | `manage_options` | Get settings |
| POST | `/admin/settings` | `manage_options` | Salva settings |

Tutti gli endpoint con validazione argomenti (`args` con `validate_callback` e `sanitize_callback`).

### 5.4 Logica codici di sblocco

**Generazione**:
- Formato `XXXX-XXXX-XXXX` (12 char, charset escluso 0/O, 1/I/L)
- Generato con `random_bytes(12)`
- Hashato con `password_hash($code, PASSWORD_BCRYPT)`
- Salvato `code_prefix` (primi 4 char in chiaro)

**Validazione**:
- Input normalizzato (uppercase, no spazi/dash)
- `WHERE code_prefix = ?` per restringere candidati
- Loop con `password_verify`
- Check `expires_at > NOW()`, `revoked = 0`, `max_uses` rispettato
- Update `used_count`, `last_used_at`, `last_used_ip`
- Rate limiting: 5 tentativi/ora per IP (transient)

### 5.5 Email post-donazione

Inviata via `wp_mail()` dopo conferma webhook PayPal.

Placeholder template:
- `{{donor_name}}`, `{{donor_email}}`, `{{amount}}`, `{{currency}}`
- `{{attachment_title}}`, `{{attachment_url}}`
- `{{unlock_code}}` (solo qui in chiaro)
- `{{direct_link}}` (auto-validante: `?attachment_id=N&wppa_unlock=XXX`)
- `{{expires_at}}`, `{{site_name}}`

Email plain text fallback obbligatoria.

### 5.6 Protezione del file

**Strategia ibrida**:
- File resta in `wp-content/uploads/`
- L'attachment page **non emette mai l'URL diretto del file originale**
- Visualizzazione sbloccata: `<img src="?wppa_view={token_hmac}">` (TTL 1h)
- Endpoint legge con `readfile()` e header appropriati
- Download: `?wppa_download={token_hmac}` con `Content-Disposition: attachment`
- Token HMAC: `attachment_id|user_session|expires_at`, firmato con `wp_salt('auth') . get_option('wppa_secret_key')`
- Visualizzazione gratuita: TTL 5 minuti
- Post-sblocco: cookie firmato + token 24h rigenerato

### 5.7 Pagina success/cancel post-PayPal

Success: `/?attachment_id=N&wppa_payment=success&token=...`
Cancel: `/?attachment_id=N&wppa_payment=cancel`

---

## 6. Requisiti non funzionali

### Sicurezza
- Tutti gli output escapati: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`
- Tutti gli input sanitizzati: `sanitize_text_field`, `sanitize_email`, `absint`, `floatval`
- Nonce su tutto (`wp_create_nonce('wp_rest')`)
- Capability check: `current_user_can('manage_options')` per endpoint admin
- Webhook PayPal: verifica firma + idempotenza via UNIQUE su `provider_transaction_id`
- HMAC secret generato all'attivazione, salvato in option, mai esposto al frontend
- Rate limiting:
  - Tentativi codice: 5/ora per IP
  - Free views: 10/giorno per IP
  - Submit checkout: 20/ora per IP
- Logging in `wp-content/uploads/wppa-logs/{YYYY-MM}.log` (opt-in)

### Performance
- Indici DB su tutte le colonne usate in WHERE/JOIN
- Caching transient stats (TTL 5 min)
- Lazy load PayPal SDK
- Asset frontend < 30KB minified+gzipped

### Compatibilità
- WordPress 6.4+
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Browser: ultimi 2 versioni di Chrome, Firefox, Safari, Edge
- Mobile responsive

### i18n
- Stringhe via `__()`, `_e()`, `esc_html__()`, `_n()` con text domain `wp-paid-attachments`
- `.pot` in `/languages/`
- `load_plugin_textdomain()` su `init`
- Stringhe user-facing in inglese, commenti di codice in italiano (per logica di business) o inglese (per dettagli tecnici)

### Disinstallazione
File `uninstall.php` con `if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;`:
- Drop tabelle custom (con conferma via opzione `wppa_delete_data_on_uninstall`, default false)
- Eliminazione opzioni `wppa_*`
- Eliminazione transient `wppa_*`
- Eliminazione postmeta `_wppa_*`

---

## 7. Implementazione miglioramenti M1-M8

### M1 — Importi suggeriti multipli
Array JSON di importi nel config attachment. Pillole cliccabili nel widget. Default `[1, 3, 5]`.

### M2 — Statistiche
Pagina dedicata + colonne in lista attachment. Repository `StatsRepository` con metodi:
- `get_overall_stats(DateRange $range): array`
- `get_top_by_revenue(int $limit, DateRange $range): array`
- `get_top_by_views(int $limit, DateRange $range): array`
- `get_daily_series(DateRange $range): array`

### M3 — Configurazione globale + override
Pattern "config cascade": `get_config($attachment_id)` parte dai default globali, applica overrides. Implementato in `AttachmentConfig::merge_with_global_defaults()`.

### M4 — Email template configurabile
Editor email globale + override per attachment. Placeholder documentati. Preview live.

### M5 — Modal/lightbox
Tutto il flusso inline sulla attachment page tramite Web Component, niente redirect (eccetto PayPal).

### M6 — Link diretto via email + codice
Email contiene codice + link auto-validante (`?attachment_id=N&wppa_unlock={code}`). Web Component intercetta param URL e va in Stato C.

### M7 — Download multi-formato (configurabile)
Toggle "Permetti download multipli formati" per attachment. Versione semplice: usa size WordPress esistenti.

### M8 — Payment Provider Interface
```php
interface PaymentProviderInterface {
    public function get_id(): string;
    public function create_checkout_session( AttachmentConfig $config, float $amount, string $email ): CheckoutSession;
    public function capture_session( string $session_id ): PaymentResult;
    public function verify_webhook_signature( array $headers, string $body ): bool;
    public function parse_webhook_event( string $body ): WebhookEvent;
}
```
Factory `PaymentProviderFactory::for( string $provider_id )`.

---

## 8. Specifiche per la "donazione opzionale"

1. **Bypass per-attachment e per-sessione**: nuovo attachment = nuova richiesta, reload = nuova richiesta (`sessionStorage` non `localStorage`).
2. **Pulsante "continua senza donare" meno prominente**: link testuale piccolo, grigio, sottolineato, vs bottone primario per donazione.
3. **Free-view**: immagine alta risoluzione + banner persistente "Hai accesso gratuito... considera donazione".
4. **Tracking**: ogni free-view in `wppa_free_views` per stats, no PII oltre IP/UA.
5. **Configurabilità**: admin può disabilitare bypass globalmente o per-attachment.

---

## 9. Criteri di accettazione

1. ✅ Si attiva senza errori su WP 6.4+ / PHP 8.1+
2. ✅ Crea le 4 tabelle DB con `dbDelta()`
3. ✅ Menu admin "Paid Attachments" con 3 sottomenu funzionanti
4. ✅ Selezione attachment dalla Media Library + abilitazione protezione
5. ✅ Testo personalizzato HTML per attachment
6. ✅ Configurazione importi suggeriti
7. ✅ Configurazione validità codice in giorni
8. ✅ Scelta tra PayPal Donate, Smart Buttons, o entrambi
9. ✅ Toggle "donazione opzionale" per attachment
10. ✅ Attachment page mostra paywall con testo configurato
11. ✅ Flusso PayPal completo → codice via email
12. ✅ Email contiene codice + link auto-validante
13. ✅ Inserimento codice → immagine alta risoluzione visibile
14. ✅ "Continua senza donare" → immagine visibile, sessionStorage settato, reload re-prompta
15. ✅ Statistiche aggregate funzionanti
16. ✅ URL diretto del file non esposto per attachment protetti
17. ✅ Endpoint REST validano nonce e capability
18. ✅ Webhook PayPal verifica firma e gestisce idempotenza
19. ✅ Build SCSS/JSX funzionante (`npm run build`)
20. ✅ `composer lint` passa senza errori
21. ✅ `npm run wp-env start` avvia ambiente locale funzionante
22. ✅ Funziona end-to-end in modalità PayPal Sandbox
23. ✅ Repo Git con tutti i file richiesti (vedi sezione 12)
24. ✅ README.md in italiano, completo
25. ✅ CHANGELOG.md secondo Keep a Changelog

---

## 10. Note operative per Claude Code

### Modalità di lavoro
1. **Verifica skill PRIMA di plan mode** (vedi sezione "Contesto")
2. **Plan mode obbligatorio**: piano dettagliato prima di scrivere codice
3. **Sviluppo iterativo per slice verticali**: feature end-to-end completa prima di passare alla successiva
4. **Commit logici**: dopo ogni slice, suggerisci messaggio di commit (Conventional Commits: `feat:`, `fix:`, `refactor:`, ecc.)
5. **Aggiorna CHANGELOG.md** ad ogni slice nella sezione `[Unreleased]`
6. **Domande prima di assumere**: se ambiguo, chiedi

### Convenzioni di codice
- **PHP**: WordPress Coding Standards (con deroghe pragmatiche documentate, es. naming OOP)
- **JS**: ESLint config WordPress
- **SCSS**: BEM rigoroso, no `!important` se non documentato
- **Naming hooks/filter**: `wppa_action_name`, `wppa_filter_name`
- **Naming classi PHP**: `PascalCase` (deroga a WPCS)
- **Naming metodi/var PHP**: `snake_case` (rispetta WPCS)
- **Commenti**: italiano per logica business, inglese per tecnica riutilizzabile
- **PHPDoc completo** su tutti i metodi pubblici

### Logging e debug
- Helper `Logger::info/warning/error()` su file dedicato
- Costante `WPPA_DEBUG` (default false)

### Test manuali
Dopo ogni slice, fornisci una **checklist di test manuali** per validazione.

### Cosa NON fare
- ❌ Non aggiungere dipendenze npm/composer non necessarie
- ❌ Non usare jQuery
- ❌ Non usare `eval()`, `unserialize()` su input utente
- ❌ Non `SELECT *`
- ❌ Non concatenare SQL: sempre `$wpdb->prepare()`
- ❌ Non lasciare codice morto/commentato
- ❌ Non over-engineerare

---

## 11. Specifiche file di repository

Tutti i file seguenti devono essere creati nella root del progetto.

### 11.1 `README.md` (in italiano)

Struttura richiesta:

```markdown
# WP Paid Attachments

Plugin WordPress per monetizzare singoli attachment (immagini ad alta risoluzione)
tramite donazione PayPal opzionale, con sblocco via codice ricevuto per email.

## Caratteristiche principali

- Soft paywall su singoli attachment WordPress
- Donazione PayPal opzionale (Donate classico + Smart Buttons)
- Codici di sblocco univoci con scadenza configurabile
- Visualizzazione gratuita opzionale (per-sessione)
- Pannello admin con statistiche
- Web Components vanilla per il frontend pubblico
- Estendibile tramite Payment Provider Interface

## Requisiti

- WordPress 6.4+
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- Account PayPal Business (per ricevere donazioni)

## Installazione

[istruzioni installazione manuale + via composer]

## Sviluppo locale

### Prerequisiti
- Node.js 20+ (vedi `.nvmrc` se presente)
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

L'ambiente sarà disponibile su http://localhost:8888 (admin: admin/password).

### Comandi utili

| Comando | Scopo |
|---|---|
| `npm run build` | Build asset produzione |
| `npm run start` | Build asset con watch |
| `npm run wp-env start` | Avvia ambiente Docker |
| `npm run wp-env stop` | Ferma ambiente |
| `composer lint` | Esegue PHPCS |
| `composer lint:fix` | Esegue PHPCBF |

## Configurazione

[guida configurazione PayPal, impostazioni globali, attivazione attachment]

## Architettura

[breve panoramica architettura, link a SPEC.md se presente]

## Contribuire

[guidelines contribuzione]

## Licenza

GPL v2 or later. Vedi [LICENSE](LICENSE).

## Autore

Sviluppato da [Mavida snc](https://mavida.it).
```

### 11.2 `CHANGELOG.md`

Formato [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) v1.1.0, [Semantic Versioning](https://semver.org/) v2.0.0.

Struttura iniziale:

```markdown
# Changelog

Tutte le modifiche significative a questo progetto saranno documentate in questo file.

Il formato segue [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
e questo progetto aderisce al [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Struttura iniziale del plugin
- Schema database (tabelle: attachment_config, payments, unlock_codes, free_views)
- Pannello admin con sottomenu Impostazioni / Attachment protetti / Statistiche
- Configurazione globale e override per attachment
- Integrazione PayPal Donate classico
- Integrazione PayPal Smart Buttons
- Generazione e validazione codici di sblocco con scadenza
- Email post-donazione con codice e link auto-validante
- Web Components frontend (<wppa-donation-widget>, <wppa-unlock-form>)
- Visualizzazione gratuita opzionale (sessionStorage)
- Endpoint REST API namespace wppa/v1
- Webhook PayPal con verifica firma e idempotenza
- Statistiche aggregate (donazioni, free views, conversion rate)
- Payment Provider Interface estensibile
- i18n con text domain wp-paid-attachments
- Build pipeline con @wordpress/scripts
- Configurazione PHPCS + WPCS
- Ambiente di sviluppo locale wp-env

### Security
- Rate limiting su tentativi codice, free views, checkout
- HMAC firmato per token di visualizzazione e download
- Hashing bcrypt dei codici di sblocco
- Sanitization e escaping su tutti gli I/O
- Capability check su tutti gli endpoint admin

[unreleased]: https://github.com/<owner>/wp-paid-attachments/compare/v0.1.0...HEAD
```

Sezioni standard ad ogni release: `Added`, `Changed`, `Deprecated`, `Removed`, `Fixed`, `Security`.

Quando arriverà la prima release, la sezione `[Unreleased]` sarà rinominata in `[0.1.0] - YYYY-MM-DD` e creato il link comparativo in fondo.

### 11.3 `LICENSE`

File con il testo completo della licenza **GPL v2** (https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt).

Header opzionale:
```
WP Paid Attachments
Copyright (C) 2025 Mavida snc

This program is free software; you can redistribute it and/or modify
[...]
```

### 11.4 `.gitignore`

Includere almeno:

```gitignore
# Dipendenze
/node_modules/
/vendor/

# Build artifacts
/build/
/dist/
*.min.js
*.min.css

# Ambiente
.env
.env.local
.env.*.local

# wp-env
.wp-env.override.json

# IDE / Editor
.idea/
.vscode/
*.sublime-project
*.sublime-workspace
.project
.settings/

# OS
.DS_Store
Thumbs.db
ehthumbs.db
Desktop.ini

# Log
*.log
/logs/

# PHPUnit
.phpunit.result.cache
/coverage/

# PHPCS
.phpcs-cache

# Composer
composer.phar

# Plugin packaging
/wp-paid-attachments.zip
```

### 11.5 `.editorconfig`

```ini
# EditorConfig — https://editorconfig.org
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true
indent_style = tab
indent_size = 4

[*.{js,jsx,ts,tsx,json,yml,yaml}]
indent_style = space
indent_size = 2

[*.{scss,css}]
indent_style = tab
indent_size = 4

[*.md]
trim_trailing_whitespace = false
indent_style = space
indent_size = 2

[Makefile]
indent_style = tab

[composer.json]
indent_style = space
indent_size = 4
```

### 11.6 `composer.json`

```json
{
    "name": "mavida/wp-paid-attachments",
    "description": "Plugin WordPress per monetizzare attachment tramite donazione PayPal opzionale.",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "authors": [
        {
            "name": "Mavida snc",
            "homepage": "https://mavida.it",
            "role": "Developer"
        }
    ],
    "require": {
        "php": ">=8.1",
        "composer/installers": "^2.2"
    },
    "require-dev": {
        "squizlabs/php_codesniffer": "^3.10",
        "wp-coding-standards/wpcs": "^3.1",
        "phpcompatibility/phpcompatibility-wp": "^2.1",
        "dealerdirect/phpcodesniffer-composer-installer": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "PaidAttachments\\": "src/"
        }
    },
    "config": {
        "allow-plugins": {
            "composer/installers": true,
            "dealerdirect/phpcodesniffer-composer-installer": true
        },
        "sort-packages": true
    },
    "scripts": {
        "lint": "phpcs",
        "lint:fix": "phpcbf",
        "lint:summary": "phpcs --report=summary"
    }
}
```

### 11.7 `package.json`

```json
{
    "name": "wp-paid-attachments",
    "version": "0.1.0",
    "description": "Plugin WordPress per monetizzare attachment tramite donazione PayPal opzionale.",
    "private": true,
    "license": "GPL-2.0-or-later",
    "author": "Mavida snc <https://mavida.it>",
    "scripts": {
        "build": "wp-scripts build",
        "start": "wp-scripts start",
        "format": "wp-scripts format",
        "lint:js": "wp-scripts lint-js",
        "lint:css": "wp-scripts lint-style",
        "packages-update": "wp-scripts packages-update",
        "wp-env": "wp-env",
        "wp-env:cli": "wp-env run cli wp"
    },
    "devDependencies": {
        "@wordpress/scripts": "^30.0.0",
        "@wordpress/env": "^10.0.0"
    }
}
```

> ⚠️ Verifica le versioni più recenti di `@wordpress/scripts` e `@wordpress/env` al momento dell'implementazione (npm view).

### 11.8 `.wp-env.json`

```json
{
    "core": "WordPress/WordPress",
    "phpVersion": "8.1",
    "plugins": [
        "."
    ],
    "config": {
        "WP_DEBUG": true,
        "WP_DEBUG_LOG": true,
        "WP_DEBUG_DISPLAY": false,
        "SCRIPT_DEBUG": true,
        "WPPA_DEBUG": true
    },
    "mappings": {
        "wp-content/uploads": "./tests/fixtures/uploads"
    },
    "env": {
        "tests": {
            "config": {
                "WP_DEBUG": true
            }
        }
    }
}
```

### 11.9 `.phpcs.xml.dist`

```xml
<?xml version="1.0"?>
<ruleset name="WP Paid Attachments">
    <description>Coding standards per WP Paid Attachments</description>

    <!-- File da analizzare -->
    <file>./src</file>
    <file>./wp-paid-attachments.php</file>
    <file>./uninstall.php</file>
    <file>./templates</file>

    <!-- Esclusioni -->
    <exclude-pattern>*/vendor/*</exclude-pattern>
    <exclude-pattern>*/node_modules/*</exclude-pattern>
    <exclude-pattern>*/build/*</exclude-pattern>
    <exclude-pattern>*/tests/fixtures/*</exclude-pattern>

    <!-- Argomenti CLI -->
    <arg value="sp"/>
    <arg name="basepath" value="."/>
    <arg name="colors"/>
    <arg name="extensions" value="php"/>
    <arg name="parallel" value="8"/>
    <arg name="cache" value=".phpcs-cache"/>

    <!-- Standard WordPress -->
    <rule ref="WordPress">
        <!-- Deroghe pragmatiche documentate -->

        <!-- Permettiamo nomi di classi PascalCase (per allineamento OOP moderno) -->
        <exclude name="WordPress.Files.FileName"/>

        <!-- Yoda conditions: lasciate al criterio del developer -->
        <exclude name="WordPress.PHP.YodaConditions"/>
    </rule>

    <!-- Compatibilità PHP -->
    <rule ref="PHPCompatibilityWP"/>
    <config name="testVersion" value="8.1-"/>

    <!-- Compatibilità WordPress -->
    <config name="minimum_wp_version" value="6.4"/>

    <!-- Text domain -->
    <rule ref="WordPress.WP.I18n">
        <properties>
            <property name="text_domain" type="array">
                <element value="wp-paid-attachments"/>
            </property>
        </properties>
    </rule>

    <!-- Prefissi consentiti -->
    <rule ref="WordPress.NamingConventions.PrefixAllGlobals">
        <properties>
            <property name="prefixes" type="array">
                <element value="wppa"/>
                <element value="WPPA"/>
                <element value="PaidAttachments"/>
            </property>
        </properties>
    </rule>
</ruleset>
```

---

## 12. Prima azione richiesta

1. **Verifica skill** disponibili nell'ambiente (vedi sezione "Contesto"). Dichiara cosa hai trovato.
2. **Entra in plan mode** e:
   - Riassumi in 5-7 punti la tua comprensione del progetto
   - Lista le **3-5 ambiguità o decisioni** più critiche da chiarire
   - Proponi un **ordine di implementazione** con slice verticali numerati e testabili end-to-end
   - Indica **rischi tecnici** che vedi
   - Indica eventuali **conflitti tra skill installate e specifiche** di questo prompt
3. **Aspetta la mia approvazione** prima di scrivere il primo file di codice.

Procedi.