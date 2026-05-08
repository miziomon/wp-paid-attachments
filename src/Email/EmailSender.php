<?php
/**
 * Invio email transazionali del plugin.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Email;

use PaidAttachments\Database\UnlockCodeRepository;
use PaidAttachments\Domain\AttachmentConfig;

/**
 * Invia l'email con il codice di sblocco al donatore.
 *
 * L'email parte ESCLUSIVAMENTE dopo la verifica del webhook PayPal,
 * mai direttamente dalla request di checkout.
 */
final class EmailSender {

	/**
	 * Repository codici di sblocco.
	 *
	 * @var UnlockCodeRepository
	 */
	private UnlockCodeRepository $code_repo;

	/**
	 * Renderer template.
	 *
	 * @var TemplateRenderer
	 */
	private TemplateRenderer $renderer;

	/**
	 * Costruttore.
	 *
	 * @param UnlockCodeRepository $code_repo Repository codici.
	 * @param TemplateRenderer     $renderer  Renderer template.
	 */
	public function __construct( UnlockCodeRepository $code_repo, TemplateRenderer $renderer ) {
		$this->code_repo = $code_repo;
		$this->renderer  = $renderer;
	}

	/**
	 * Genera un codice di sblocco e invia l'email al donatore.
	 *
	 * @param string           $payer_email   Email del donatore.
	 * @param int              $attachment_id ID attachment WP.
	 * @param AttachmentConfig $config        Configurazione attachment.
	 * @param int              $payment_id    ID del pagamento registrato.
	 * @return string|null Codice in chiaro generato (da usare per auto-validazione PDT) o null in caso di errore.
	 */
	public function send_unlock_email( string $payer_email, int $attachment_id, AttachmentConfig $config, int $payment_id ): ?string {
		// Genera codice di sblocco.
		$plain_code = $this->generate_plain_code();
		$hash       = password_hash( $plain_code, PASSWORD_BCRYPT );
		$prefix     = substr( str_replace( '-', '', $plain_code ), 0, 4 );

		$validity_days = $config->code_validity_days > 0 ? $config->code_validity_days : 30;
		$expires       = new \DateTimeImmutable( '+' . $validity_days . ' days' );

		$code_id = $this->code_repo->insert_code(
			$attachment_id,
			$hash,
			$prefix,
			$payer_email,
			$expires,
			$payment_id,
			$config->code_max_uses
		);

		if ( ! $code_id ) {
			return null;
		}

		// Link auto-validante.
		$unlock_url = add_query_arg(
			array(
				'attachment_id' => $attachment_id,
				'wppa_unlock'   => rawurlencode( $plain_code ),
			),
			get_permalink( $attachment_id )
		);

		$variables = array(
			'site_name'        => get_bloginfo( 'name' ),
			'unlock_code'      => $plain_code,
			'unlock_url'       => $unlock_url,
			'attachment_title' => get_the_title( $attachment_id ),
			'expires_date'     => $expires->format( 'd/m/Y' ),
		);

		// Soggetto email (da config, poi da impostazioni globali, poi default).
		$settings = get_option( 'wppa_settings', array() );
		$subject  = $config->custom_email_subject
			? $this->renderer->replace_placeholders( $config->custom_email_subject, $variables )
			: $this->renderer->replace_placeholders(
				empty( $settings['default_email_subject'] ) ? 'Il tuo codice di sblocco da {{site_name}}' : (string) $settings['default_email_subject'],
				$variables
			);

		// Template HTML.
		$custom_html  = WPPA_PLUGIN_DIR . 'templates/email/custom-html.php';
		$default_html = WPPA_PLUGIN_DIR . 'templates/email/default-html.php';
		$html_tpl     = file_exists( $custom_html ) ? $custom_html : $default_html;
		$html         = $this->renderer->render( $html_tpl, $variables );

		// Template plain-text (fallback: solo HTML se il file testo non esiste).
		$custom_text  = WPPA_PLUGIN_DIR . 'templates/email/custom-text.php';
		$default_text = WPPA_PLUGIN_DIR . 'templates/email/default-text.php';
		$text_tpl     = file_exists( $custom_text ) ? $custom_text : ( file_exists( $default_text ) ? $default_text : '' );
		$text         = $text_tpl ? $this->renderer->render( $text_tpl, $variables ) : '';

		$from    = $this->get_from_header();
		$headers = array( 'From: ' . $from );

		if ( $text ) {
			// Multipart: prima plain-text poi HTML (ordine preferito dai client email).
			$boundary  = 'wppa_' . wp_generate_password( 16, false );
			$headers[] = 'MIME-Version: 1.0';
			$headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

			$body = "--{$boundary}\r\n"
				. "Content-Type: text/plain; charset=UTF-8\r\n"
				. "Content-Transfer-Encoding: 8bit\r\n\r\n"
				. $text . "\r\n\r\n"
				. "--{$boundary}\r\n"
				. "Content-Type: text/html; charset=UTF-8\r\n"
				. "Content-Transfer-Encoding: 8bit\r\n\r\n"
				. $html . "\r\n\r\n"
				. "--{$boundary}--";
		} else {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			$body      = $html;
		}

		$sent = wp_mail( $payer_email, $subject, $body, $headers );

		return $sent ? $plain_code : null;
	}

	/**
	 * Genera un codice di sblocco casuale nel formato XXXX-XXXX-XXXX.
	 *
	 * Usa un charset senza caratteri ambigui (0/O, 1/I/L).
	 *
	 * @return string
	 */
	private function generate_plain_code(): string {
		$charset = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
		$length  = strlen( $charset );
		$code    = '';

		for ( $i = 0; $i < 12; $i++ ) {
			$code .= $charset[ random_int( 0, $length - 1 ) ];
			if ( 3 === $i || 7 === $i ) {
				$code .= '-';
			}
		}

		return $code;
	}

	/**
	 * Costruisce l'header From: per l'email.
	 *
	 * @return string
	 */
	private function get_from_header(): string {
		$from_email = (string) get_option( 'admin_email', '' );
		$from_name  = get_bloginfo( 'name' );

		return sprintf( '%s <%s>', $from_name, $from_email );
	}
}
