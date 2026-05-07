<?php
/**
 * Base class per i controller REST del plugin.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Classe base astratta per tutti i controller REST di `wppa/v1`.
 */
abstract class RestController {

	/**
	 * Namespace REST del plugin.
	 */
	const NAMESPACE = 'wppa/v1';

	/**
	 * Registra le route REST. Chiamato su `rest_api_init`.
	 *
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * Registra l'hook `rest_api_init`.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Verifica che l'utente corrente abbia capacità di gestione opzioni.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return bool|WP_Error
	 */
	public function check_admin_permission( WP_REST_Request $request ): bool|WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Accesso non autorizzato.', 'wp-paid-attachments' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Restituisce una risposta JSON di successo.
	 *
	 * @param mixed $data    Dati da serializzare.
	 * @param int   $status  Codice HTTP (default 200).
	 * @return WP_REST_Response
	 */
	protected function success( mixed $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( $data, $status );
	}

	/**
	 * Restituisce una risposta di errore.
	 *
	 * @param string $code    Codice errore.
	 * @param string $message Messaggio leggibile.
	 * @param int    $status  Codice HTTP.
	 * @return WP_Error
	 */
	protected function error( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
