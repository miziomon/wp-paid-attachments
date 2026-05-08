<?php
/**
 * Plugin Name:       WP Paid Attachments
 * Plugin URI:        https://github.com/miziomon/wp-paid-attachments
 * Description:       Monetizza singoli attachment (immagini HD) tramite donazione PayPal opzionale con sblocco via codice email.
 * Version:           0.4.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Mavida snc
 * Author URI:        https://mavida.it
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-paid-attachments
 * Domain Path:       /languages
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

// Blocco accesso diretto.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Costanti del plugin (guard per evitare conflitti con wp-config.php).
if ( ! defined( 'WPPA_VERSION' ) ) {
	define( 'WPPA_VERSION', '0.4.0' );
}
if ( ! defined( 'WPPA_PLUGIN_FILE' ) ) {
	define( 'WPPA_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'WPPA_PLUGIN_DIR' ) ) {
	define( 'WPPA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'WPPA_PLUGIN_URL' ) ) {
	define( 'WPPA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WPPA_DEBUG' ) ) {
	define( 'WPPA_DEBUG', defined( 'WP_DEBUG' ) && WP_DEBUG );
}

// Autoload PSR-4 via Composer.
$wppa_autoload = WPPA_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! file_exists( $wppa_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__(
				'WP Paid Attachments: dipendenze Composer non trovate. Esegui "composer install" nella cartella del plugin.',
				'wp-paid-attachments'
			);
			echo '</p></div>';
		}
	);
	return;
}
require_once $wppa_autoload;

// Hook ciclo di vita — devono stare nel file principale (non in hook).
register_activation_hook( __FILE__, array( 'PaidAttachments\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PaidAttachments\\Deactivator', 'deactivate' ) );

// Inizializzazione su plugins_loaded per garantire che tutti i plugin siano caricati.
add_action(
	'plugins_loaded',
	static function (): void {
		PaidAttachments\Plugin::get_instance()->init();
	}
);
