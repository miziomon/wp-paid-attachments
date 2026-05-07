<?php
/**
 * Pagina admin delle impostazioni globali del plugin.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Admin;

/**
 * Renderizza il mount point JSX per la pagina Impostazioni.
 *
 * La UI effettiva è costruita interamente in React (assets/admin/index.jsx)
 * e montata su <div id="wppa-admin-settings-root">.
 */
final class SettingsPage {

	/**
	 * Handle dello script JS della pagina admin.
	 */
	const SCRIPT_HANDLE = 'wppa-admin';

	/**
	 * Registra gli hook per l'enqueue degli asset della pagina.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Carica script e stili solo sulla pagina corretta.
	 *
	 * @param string $hook_suffix Suffix della pagina admin corrente.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Carica gli asset solo sulla pagina del plugin.
		if ( ! str_contains( $hook_suffix, AdminMenu::MENU_SLUG ) ) {
			return;
		}

		$build_dir = WPPA_PLUGIN_DIR . 'build/';
		$build_url = WPPA_PLUGIN_URL . 'build/';

		// Asset index generato da @wordpress/scripts.
		$asset_file = $build_dir . 'index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$build_url . 'index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			self::SCRIPT_HANDLE . '-style',
			$build_url . 'index.css',
			array( 'wp-components' ),
			$asset['version']
		);

		// Passa i dati necessari al JS (nonce REST, URL API, impostazioni correnti).
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'wppaAdmin',
			array(
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'apiRoot'   => esc_url_raw( rest_url( 'wppa/v1' ) ),
				'pluginUrl' => WPPA_PLUGIN_URL,
				'debug'     => WPPA_DEBUG,
			)
		);
	}

	/**
	 * Renderizza l'HTML della pagina (mount point per React).
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( AdminMenu::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accesso non autorizzato.', 'wp-paid-attachments' ) );
		}

		echo '<div class="wrap">';
		echo '<div id="wppa-admin-settings-root"></div>';
		echo '</div>';
	}
}
