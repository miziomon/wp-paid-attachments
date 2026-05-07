<?php
/**
 * Logica eseguita all'attivazione del plugin.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments;

/**
 * Gestisce l'hook di attivazione del plugin.
 *
 * Al momento contiene solo i controlli di compatibilità. La creazione
 * delle tabelle DB e delle option di default sarà aggiunta in Slice 2
 * tramite Database\Schema::install().
 */
final class Activator {

	/**
	 * Callback per register_activation_hook().
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Verifica versione PHP prima di fare qualsiasi altra cosa.
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			// Traduzione non disponibile qui (textdomain non ancora caricato).
			wp_die(
				'WP Paid Attachments richiede PHP 8.1 o superiore. La versione attuale è ' . esc_html( PHP_VERSION ) . '.',
				'Attivazione non riuscita',
				array( 'back_link' => true )
			);
		}

		// Verifica versione WordPress.
		global $wp_version;
		if ( version_compare( $wp_version, '6.4', '<' ) ) {
			wp_die(
				'WP Paid Attachments richiede WordPress 6.4 o superiore. La versione attuale è ' . esc_html( $wp_version ) . '.',
				'Attivazione non riuscita',
				array( 'back_link' => true )
			);
		}

		// Installa/aggiorna le tabelle DB.
		Database\Schema::install();

		// Genera il secret HMAC alla prima attivazione (non sovrascrivere se già presente).
		if ( ! get_option( 'wppa_secret_key' ) ) {
			add_option( 'wppa_secret_key', wp_generate_password( 64, true, true ), '', 'yes' );
		}

		// Scrive le impostazioni di default se non già presenti.
		if ( ! get_option( 'wppa_settings' ) ) {
			add_option( 'wppa_settings', self::get_default_settings(), '', 'no' );
		}
	}

	/**
	 * Restituisce il set di impostazioni globali di default del plugin.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_default_settings(): array {
		return array(
			// --- PayPal ---
			'paypal_email'               => '',
			'paypal_button_id'           => '',
			'paypal_client_id'           => '',
			'paypal_client_secret'       => '',
			'paypal_mode'                => 'sandbox',
			// --- Default per nuovi attachment ---
			'default_payment_mode'       => 'paypal_donate',
			'default_suggested_amounts'  => array( 1, 3, 5 ),
			'default_min_amount'         => 1.00,
			'default_currency'           => 'EUR',
			'default_code_validity_days' => 30,
			'default_code_max_uses'      => 0,
			'default_allow_free_view'    => true,
			'default_custom_text'        => '',
			'default_email_subject'      => '',
			'default_email_body'         => '',
			// --- Mittente email ---
			'email_from'                 => '',
			'email_from_name'            => '',
			// --- Avanzate ---
			'debug_logging'              => false,
			'delete_data_on_uninstall'   => false,
		);
	}
}
