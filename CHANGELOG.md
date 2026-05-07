# Changelog

Tutte le modifiche significative a questo progetto saranno documentate in questo file.

Il formato segue [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
e questo progetto aderisce al [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] — 2026-05-07

### Added

- Pagina di attachment non protetti: immagine HD con pulsante "Scarica immagine" (link diretto)
- Codice di sblocco master superadmin: campo in Impostazioni > Avanzate, funziona su qualsiasi attachment, formato libero (case-insensitive, trattini ignorati)
- Free view (Stato D): aggiunto pulsante "Scarica immagine HD" con token di download separato
- Admin pannello attachment: link "Visualizza →" (target _blank) per aprire la pagina di attachment direttamente dalla lista
- Pannello impostazioni: passato da accordion a TabPanel con 5 tab (PayPal, Default, Free View, Testi & Email, Avanzate)
- Pannello impostazioni PayPal: istruzioni guidate per Client ID/Secret, Donate Button e configurazione Webhook
- Istruzioni step-by-step per la creazione e configurazione del Webhook PayPal nell'area admin

### Fixed

- Redirect automatico di WordPress (WP 6.4+) dalle attachment page disabilitato per TUTTI gli attachment, non solo i protetti
- Blocco `core/post-featured-image` nascosto per tutte le attachment immagine (evita duplicati e bypass del paywall nei block-theme)
- Pulsante "Ho già un codice di sblocco" e navigazione tra stati del widget non funzionante (`_goTo()` ora usa `display: block` invece di reset inline)
- Endpoint `/free-view` ora restituisce anche `download_token` (TYPE_DOWNLOAD, TTL 1h) oltre al token di visualizzazione

### Security

- Codice master superadmin verificato prima del rate-limiting, senza impatto sui contatori tentativi utente

## [0.1.0] — 2026-05-07

### Added

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

### Security

- Rate limiting su tentativi codice, free views, checkout
- Token HMAC firmato per visualizzazione e download file (TTL configurabile)
- Hashing bcrypt dei codici di sblocco
- Sanitization e escaping su tutti gli I/O
- Capability check su tutti gli endpoint admin
- `noindex` su attachment protetti e response endpoint file

[0.2.0]: https://github.com/miziomon/wp-paid-attachments/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/miziomon/wp-paid-attachments/releases/tag/v0.1.0
