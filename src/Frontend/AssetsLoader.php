<?php
/**
 * Caricamento condizionale degli asset pubblici del plugin.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Frontend;

/**
 * Registra ed esegue l'enqueue degli asset JS/CSS pubblici solo sulle
 * attachment page protette.
 */
final class AssetsLoader {

	/**
	 * Handle per il Web Component JS.
	 */
	const WIDGET_HANDLE = 'wppa-widget';

	/**
	 * Registra gli hook WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
	}

	/**
	 * Carica gli asset solo se siamo su una attachment page protetta.
	 *
	 * @return void
	 */
	public function maybe_enqueue(): void {
		if ( ! $this->is_protected_attachment_page() ) {
			return;
		}

		$build_dir = WPPA_PLUGIN_DIR . 'build/';
		$build_url = WPPA_PLUGIN_URL . 'build/';

		$asset_file = $build_dir . 'widget.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			self::WIDGET_HANDLE,
			$build_url . 'widget.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			self::WIDGET_HANDLE . '-style',
			$build_url . 'widget.css',
			array(),
			$asset['version']
		);

		// Dati per il widget: nonce REST, API root, dati attachment corrente.
		wp_localize_script(
			self::WIDGET_HANDLE,
			'wppaPublic',
			array(
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'apiRoot' => esc_url_raw( rest_url( 'wppa/v1' ) ),
			)
		);
	}

	/**
	 * Verifica se la pagina corrente è una attachment page protetta.
	 *
	 * @return bool
	 */
	private function is_protected_attachment_page(): bool {
		if ( ! is_attachment() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wppa_attachment_config';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_var( $wpdb->prepare( "SELECT enabled FROM `{$table}` WHERE attachment_id = %d LIMIT 1", $post->ID ) );

		return '1' === (string) $result;
	}
}
