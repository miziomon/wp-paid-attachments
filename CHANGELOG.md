# Changelog

Tutte le modifiche significative a questo progetto saranno documentate in questo file.

Il formato segue [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
e questo progetto aderisce al [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> **Nota sulla cronologia delle versioni.**
> I tag git pubblicati sono `v0.2.0` e `v0.3.0`. La release `v0.2.0` è la prima release pubblica del plugin e raccoglie tutte le iterazioni di sviluppo precedenti, documentate qui sotto come sub-entry `[0.2.0-dev.N]` (storiche, non corrispondono a tag git separati).

## [0.3.0] — 2026-05-08

### Added

- Supporto **Conti Personali PayPal** tramite IPN (Instant Payment Notification): nuova classe `PaidAttachments\Payment\IpnVerifier` esegue il round-trip di verifica su `ipnpb.paypal.com` con `cmd=_notify-validate`
- Nuovo endpoint REST `POST /wppa/v1/ipn/paypal` (`IpnController`) per ricevere e processare le notifiche IPN — riusa la stessa logica di idempotenza, scrittura `Payment` e invio email del flusso webhook v2
- Nuovo setting `paypal_account_type` (`business` | `personal`) nella tab "Integrazione PayPal": commuta la UI e il flusso di conferma pagamento
- UI Impostazioni: sezione "Configurazione IPN" con istruzioni step-by-step per impostare l'URL IPN nel pannello PayPal, mostrata solo per account Personale
- `PayPalDonateProvider`: aggiunge automaticamente il parametro `notify_url` nel form Donate quando l'account è Personale, in modo che PayPal invii l'IPN al nostro endpoint

### Changed

- UI Impostazioni → Tab "Integrazione PayPal": le sezioni "Credenziali API" e "Webhook ID" sono ora nascoste quando si seleziona "Conto Personale" (non sono utilizzabili senza Conto Business)

## [0.2.0] — 2026-05-07

*Prima release pubblica del plugin. Bundle di tutte le iterazioni di sviluppo elencate sotto.*

### [0.2.0-dev.5] — Auto-update e release pipeline

#### Added

- Sistema di aggiornamento automatico da GitHub Releases: `src/Support/Updater.php` usa `yahnis-elsts/plugin-update-checker` v5 — WordPress mostra la notifica di aggiornamento nel pannello plugin quando viene pubblicata una nuova release con ZIP allegato
- Script di release automatizzato `scripts/release.js` (+ comandi npm `release:patch`, `release:minor`, `release:major`): esegue in sequenza bump versione, build, `composer --no-dev`, generazione ZIP, commit + tag + push git, creazione GitHub Release con ZIP come asset

### [0.2.0-dev.4] — Paywall fix e PayPal Smart Buttons inline

#### Fixed

- Titolo e testo del paywall non visualizzati nel widget: `render_widget()` ora legge `default_paywall_title` e `default_paywall_text` dalle impostazioni globali e li passa come attributi `paywall-title` e `paywall-text` separati
- Pulsante "Dona per sbloccare" non funzionante: implementato `_startDonation()` nel Web Component con branch per `paypal_donate` (redirect a PayPal Donate) e `paypal_smart` (lazy-load SDK + Smart Buttons inline)
- Fallback automatico da `paypal_donate` a `paypal_smart` quando il Donate Button ID non è configurato ma il Client ID è presente, per evitare errori silenziosi

#### Added

- Integrazione PayPal Smart Buttons nel widget frontend: selezione importo → click "Dona" → carica SDK PayPal lazy, renderizza Smart Buttons inline, chiama `/checkout` (crea ordine) e `/checkout/capture` (conferma); l'email con il codice parte dal webhook
- Pulsante "Dona per sbloccare" in modalità `paypal_donate` reindirizza direttamente a `paypal.com/donate/?hosted_button_id=...` con importo e valuta precompilati
- Messaggi di errore/successo inline nel widget per feedback all'utente dopo la donazione

### [0.2.0-dev.3] — Stats columns e build pipeline

#### Added

- Lista attachment admin: 3 nuove colonne "Views", "Donazioni", "Free views" visibili solo per gli attachment protetti (query batch per-ID, nessuna query aggiuntiva per i non protetti)
- Comando `npm run build:zip`: compila JS e genera `zip/wp-paid-attachments-{version}.zip` pronto per l'upload su un sito WordPress
- Dipendenza `archiver` (devDependency) per la creazione del pacchetto ZIP
- Script `scripts/build-zip.js` che include `src/`, `build/`, `vendor/`, `languages/`, `templates/` + file PHP root

#### Changed

- Cartella `zip/` aggiunta a `.gitignore` (artifact di build, non versionato)

### [0.2.0-dev.2] — Master code, free-view download, UX admin

#### Added

- Pagina di attachment non protetti: immagine HD con pulsante "Scarica immagine" (link diretto)
- Codice di sblocco master superadmin: campo in Impostazioni > Avanzate, funziona su qualsiasi attachment, formato libero (case-insensitive, trattini ignorati)
- Free view (Stato D): aggiunto pulsante "Scarica immagine HD" con token di download separato
- Admin pannello attachment: link "Visualizza →" (target _blank) per aprire la pagina di attachment direttamente dalla lista
- Pannello impostazioni: passato da accordion a TabPanel con 5 tab (PayPal, Default, Free View, Testi & Email, Avanzate)
- Pannello impostazioni PayPal: istruzioni guidate per Client ID/Secret, Donate Button e configurazione Webhook
- Istruzioni step-by-step per la creazione e configurazione del Webhook PayPal nell'area admin

#### Fixed

- Redirect automatico di WordPress (WP 6.4+) dalle attachment page disabilitato per TUTTI gli attachment, non solo i protetti
- Blocco `core/post-featured-image` nascosto per tutte le attachment immagine (evita duplicati e bypass del paywall nei block-theme)
- Pulsante "Ho già un codice di sblocco" e navigazione tra stati del widget non funzionante (`_goTo()` ora usa `display: block` invece di reset inline)
- Endpoint `/free-view` ora restituisce anche `download_token` (TYPE_DOWNLOAD, TTL 1h) oltre al token di visualizzazione

#### Security

- Codice master superadmin verificato prima del rate-limiting, senza impatto sui contatori tentativi utente

### [0.2.0-dev.1] — Scaffolding iniziale

#### Added

- Struttura iniziale del plugin (Slice 1: scaffold infrastrutturale)
- Schema database (tabelle: attachment_config, payments, unlock_codes, free_views)
- Pannello admin con sottomenu Impostazioni / Attachment protetti / Statistiche
- Configurazione globale e override per attachment (config-cascade)
- Integrazione PayPal Donate classico
- Integrazione PayPal Smart Buttons
- Generazione e validazione codici di sblocco con scadenza
- Email post-donazione con codice e link auto-validante
- Web Components frontend (`<wppa-donation-widget>`, `<wppa-unlock-form>`)
- Visualizzazione gratuita opzionale (sessionStorage, per-sessione)
- Endpoint REST API namespace `wppa/v1`
- Webhook PayPal con verifica firma e idempotenza
- Statistiche aggregate (donazioni, free views, conversion rate)
- Payment Provider Interface estensibile
- i18n con text domain `wp-paid-attachments`
- Build pipeline con `@wordpress/scripts`
- Configurazione PHPCS + WPCS
- Ambiente di sviluppo locale wp-env

#### Security

- Rate limiting su tentativi codice, free views, checkout
- Token HMAC firmato per visualizzazione e download file (TTL configurabile)
- Hashing bcrypt dei codici di sblocco
- Sanitization e escaping su tutti gli I/O
- Capability check su tutti gli endpoint admin
- `noindex` su attachment protetti e response endpoint file

[0.3.0]: https://github.com/miziomon/wp-paid-attachments/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/miziomon/wp-paid-attachments/releases/tag/v0.2.0
