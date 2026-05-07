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
	 * @return bool True se l'email è stata inviata correttamente.
	 */
	public function send_unlock_email( string $payer_email, int $attachment_id, AttachmentConfig $config, int $payment_id ): bool {
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
			return false;
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
		$custom_template  = WPPA_PLUGIN_DIR . 'templates/email/custom-html.php';
		$default_template = WPPA_PLUGIN_DIR . 'templates/email/default-html.php';
		$template         = file_exists( $custom_template ) ? $custom_template : $default_template;

		$html = $this->renderer->render( $template, $variables );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $this->get_from_header(),
		);

		return wp_mail( $payer_email, $subject, $html, $headers );
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
