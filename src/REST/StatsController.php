<?php
/**
 * Controller REST per le statistiche del plugin.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\REST;

use PaidAttachments\Database\StatsRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Espone i dati aggregati di donazioni e free view all'area admin.
 *
 * Endpoint esposto:
 *   GET /wppa/v1/admin/stats?range=7|30|90
 */
final class StatsController extends RestController {

	/**
	 * Repository statistiche aggregate.
	 *
	 * @var StatsRepository
	 */
	private StatsRepository $stats_repo;

	/**
	 * Costruttore.
	 *
	 * @param StatsRepository $stats_repo Repository statistiche.
	 */
	public function __construct( StatsRepository $stats_repo ) {
		$this->stats_repo = $stats_repo;
	}

	/**
	 * Registra le route REST.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/admin/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'range' => array(
						'type'    => 'integer',
						'default' => 30,
						'enum'    => array( 7, 30, 90 ),
					),
				),
			)
		);
	}

	/**
	 * GET /wppa/v1/admin/stats — Restituisce statistiche per l'intervallo indicato.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response
	 */
	public function get_stats( WP_REST_Request $request ): WP_REST_Response {
		$range = (int) $request->get_param( 'range' );
		$data  = $this->stats_repo->get_stats( $range );

		return $this->success( $data );
	}
}
