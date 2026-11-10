<?php
/**
 * Menu e pagina di amministrazione del plugin.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Admin;

/**
 * Registra un'unica pagina admin (app React a schede) e ne carica gli asset.
 */
final class AdminMenu {

	/**
	 * Slug della pagina.
	 */
	const MENU_SLUG = 'wp-paid-attachments';

	/**
	 * Capability richiesta.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Handle di script e stile dell'admin.
	 */
	const SCRIPT_HANDLE = 'wppa-admin';

	/**
	 * Registra gli hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPPA_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
	}

	/**
	 * Aggiunge la voce di menu.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'WP Paid Attachments', 'wp-paid-attachments' ),
			__( 'Paid Attachments', 'wp-paid-attachments' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-money-alt',
			58
		);
	}

	/**
	 * Link "Impostazioni" nella riga del plugin, nell'elenco dei plugin.
	 *
	 * @param array<int|string, string> $links Link esistenti.
	 * @return array<int|string, string>
	 */
	public function add_settings_link( array $links ): array {
		$url = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=payments' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Impostazioni', 'wp-paid-attachments' ) . '</a>' );

		return $links;
	}

	/**
	 * Stampa il mount point dell'app React.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accesso non autorizzato.', 'wp-paid-attachments' ) );
		}

		echo '<div class="wrap"><div id="wppa-admin-root"></div></div>';
	}

	/**
	 * Carica gli asset solo nella pagina del plugin.
	 *
	 * @param string $hook_suffix Suffisso della pagina admin corrente.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! str_contains( $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}

		$asset_file = WPPA_PLUGIN_DIR . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			WPPA_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			self::SCRIPT_HANDLE . '-style',
			WPPA_PLUGIN_URL . 'build/index.css',
			array( 'wp-components' ),
			$asset['version']
		);

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
}
