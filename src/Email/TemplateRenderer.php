<?php
/**
 * Renderer per i template email del plugin.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Email;

/**
 * Sostituisce i placeholder nei template email con i valori effettivi.
 *
 * Placeholder supportati:
 *   {{site_name}}      Nome del sito WP
 *   {{unlock_code}}    Codice di sblocco formattato (XXXX-XXXX-XXXX)
 *   {{unlock_url}}     URL diretto per auto-sblocco
 *   {{attachment_title}} Titolo dell'attachment
 *   {{expires_date}}   Data di scadenza del codice
 */
final class TemplateRenderer {

	/**
	 * Renderizza il template HTML con i dati forniti.
	 *
	 * @param string               $template  Percorso del file template PHP.
	 * @param array<string, mixed> $variables Variabili da iniettare nel template.
	 * @return string HTML renderizzato.
	 */
	public function render( string $template, array $variables ): string {
		if ( ! file_exists( $template ) ) {
			return $this->render_fallback( $variables );
		}

		ob_start();
		extract( $variables, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		require $template;
		return (string) ob_get_clean();
	}

	/**
	 * Sostituisce i placeholder `{{key}}` in una stringa con i valori dell'array.
	 *
	 * @param string               $text      Testo con placeholder.
	 * @param array<string, mixed> $variables Valori da sostituire.
	 * @return string Testo con i placeholder sostituiti.
	 */
	public function replace_placeholders( string $text, array $variables ): string {
		foreach ( $variables as $key => $value ) {
			$text = str_replace( '{{' . $key . '}}', (string) $value, $text );
		}

		return $text;
	}

	/**
	 * Template di fallback usato quando il file PHP non esiste.
	 *
	 * @param array<string, mixed> $v Variabili template.
	 * @return string HTML email minimale.
	 */
	private function render_fallback( array $v ): string {
		$code  = esc_html( (string) ( $v['unlock_code'] ?? '' ) );
		$url   = esc_url( (string) ( $v['unlock_url'] ?? '' ) );
		$title = esc_html( (string) ( $v['attachment_title'] ?? '' ) );
		$site  = esc_html( (string) ( $v['site_name'] ?? get_bloginfo( 'name' ) ) );

		return "
<html>
<body style='font-family:sans-serif;max-width:560px;margin:0 auto;padding:20px'>
<h2>Il tuo codice di sblocco — $site</h2>
<p>Grazie per la tua donazione! Il tuo codice per sbloccare <strong>$title</strong> è:</p>
<p style='font-size:1.5rem;letter-spacing:.1em;font-weight:bold;text-align:center'>$code</p>
<p><a href='$url'>Clicca qui per sbloccare direttamente l'immagine</a></p>
<p style='color:#666;font-size:.85rem'>Il codice è valido per 30 giorni.</p>
</body>
</html>";
	}
}
