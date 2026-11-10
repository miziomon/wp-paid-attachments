<?php
/**
 * Endpoint REST della scheda Aggiornamenti.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\REST;

use PaidAttachments\Support\Updater;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Stato, controllo manuale e installazione degli aggiornamenti da GitHub.
 *
 * - GET  /wppa/v1/admin/update          stato dall'ultimo controllo salvato
 * - POST /wppa/v1/admin/update/check    controlla subito su GitHub
 * - POST /wppa/v1/admin/update/install  installa la nuova versione
 */
final class UpdateController extends RestController {

	/**
	 * Registra le route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/admin/update',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/update/check',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'check' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/update/install',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'install' ),
				'permission_callback' => array( $this, 'check_update_permission' ),
			)
		);
	}

	/**
	 * L'installazione richiede la capability specifica di WordPress.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return bool|WP_Error
	 */
	public function check_update_permission( WP_REST_Request $request ): bool|WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( current_user_can( 'update_plugins' ) ) {
			return true;
		}

		return $this->error( 'rest_forbidden', __( 'Non hai il permesso di aggiornare i plugin.', 'wp-paid-attachments' ), 403 );
	}

	/**
	 * GET /admin/update.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function status(): WP_REST_Response|WP_Error {
		return $this->wrap( Updater::status() );
	}

	/**
	 * POST /admin/update/check.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function check(): WP_REST_Response|WP_Error {
		return $this->wrap( Updater::check() );
	}

	/**
	 * POST /admin/update/install — aggiorna il plugin con l'upgrader di WordPress.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function install(): WP_REST_Response|WP_Error {
		$status = Updater::check();
		if ( null === $status ) {
			return $this->unavailable();
		}
		if ( ! $status['update_available'] ) {
			return $this->error( 'no_update', __( 'Il plugin è già aggiornato.', 'wp-paid-attachments' ), 409 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$basename   = plugin_basename( WPPA_PLUGIN_FILE );
		$was_active = is_plugin_active( $basename );

		// La skin "ajax" raccoglie i messaggi senza stamparli: la risposta resta JSON valido.
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade( $basename );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( true !== $result ) {
			$messages = array_map( 'wp_strip_all_tags', array_map( 'strval', $skin->get_upgrade_messages() ) );
			$errors   = $skin->get_errors();
			if ( $errors->has_errors() ) {
				$messages[] = $errors->get_error_message();
			}
			return $this->error( 'update_failed', implode( ' ', array_filter( $messages ) ), 500 );
		}

		// L'upgrader può lasciare il plugin disattivato: si ripristina lo stato precedente.
		if ( $was_active && ! is_plugin_active( $basename ) ) {
			activate_plugin( $basename, '', false, true );
		}

		return $this->success(
			array(
				'installed' => true,
				'version'   => $status['latest'],
			)
		);
	}

	/**
	 * Risposta standard, o errore se il checker non è disponibile.
	 *
	 * @param array<string, mixed>|null $status Stato dell'aggiornamento.
	 * @return WP_REST_Response|WP_Error
	 */
	private function wrap( ?array $status ): WP_REST_Response|WP_Error {
		return null === $status ? $this->unavailable() : $this->success( $status );
	}

	/**
	 * Errore per checker non inizializzato (es. vendor/ mancante).
	 *
	 * @return WP_Error
	 */
	private function unavailable(): WP_Error {
		return $this->error( 'no_updater', __( 'Il sistema di aggiornamento non è disponibile.', 'wp-paid-attachments' ), 500 );
	}
}
