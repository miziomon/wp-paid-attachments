<?php
/**
 * Repository per le statistiche aggregate.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Database;

/**
 * Fornisce query aggregate su wppa_payments e wppa_free_views con cache transient 5 min.
 */
final class StatsRepository {

	/**
	 * TTL del transient cache per le statistiche aggregate (secondi).
	 */
	const CACHE_TTL = 300;

	/**
	 * Istanza wpdb.
	 *
	 * @var \wpdb
	 */
	private \wpdb $db;

	/**
	 * Costruttore.
	 *
	 * @param \wpdb $db Istanza globale $wpdb.
	 */
	public function __construct( \wpdb $db ) {
		$this->db = $db;
	}

	/**
	 * Restituisce il set completo di statistiche per l'intervallo richiesto.
	 *
	 * @param int $days Giorni di intervallo (7, 30 o 90).
	 * @return array{overview: array<string, mixed>, series: list<array<string, mixed>>, top_attachments: list<array<string, mixed>>}
	 */
	public function get_stats( int $days = 30 ): array {
		$cache_key = 'wppa_stats_' . $days;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$stats = array(
			'overview'        => $this->get_overview( $days ),
			'series'          => $this->get_daily_series( $days ),
			'top_attachments' => $this->get_top_attachments( $days ),
		);

		set_transient( $cache_key, $stats, self::CACHE_TTL );

		return $stats;
	}

	/**
	 * Invalida la cache per tutti gli intervalli standard.
	 *
	 * @return void
	 */
	public function flush_cache(): void {
		foreach ( array( 7, 30, 90 ) as $days ) {
			delete_transient( 'wppa_stats_' . $days );
		}
	}

	/**
	 * Restituisce i KPI riepilogative per l'intervallo.
	 *
	 * @param int $days Giorni di intervallo.
	 * @return array<string, mixed>
	 */
	private function get_overview( int $days ): array {
		$pt = $this->db->prefix . 'wppa_payments';
		$fv = $this->db->prefix . 'wppa_free_views';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT
				    COUNT(*) AS total_donations,
				    COALESCE( SUM(amount), 0 ) AS total_revenue,
				    COALESCE( AVG(amount), 0 ) AS avg_amount,
				    COUNT( DISTINCT donor_email ) AS unique_donors
				FROM `{$pt}`
				WHERE status = 'completed'
				  AND created_at >= DATE_SUB( NOW(), INTERVAL %d DAY )",
				$days
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$free_views = (int) $this->db->get_var(
			$this->db->prepare(
				"SELECT COUNT(*) FROM `{$fv}` WHERE created_at >= DATE_SUB( NOW(), INTERVAL %d DAY )",
				$days
			)
		);

		$data            = is_array( $row ) ? $row : array();
		$total_donations = (int) ( $data['total_donations'] ?? 0 );
		$total_events    = $total_donations + $free_views;

		return array(
			'total_donations' => $total_donations,
			'total_revenue'   => round( (float) ( $data['total_revenue'] ?? 0 ), 2 ),
			'avg_amount'      => round( (float) ( $data['avg_amount'] ?? 0 ), 2 ),
			'unique_donors'   => (int) ( $data['unique_donors'] ?? 0 ),
			'free_views'      => $free_views,
			'conversion_rate' => $total_events > 0 ? round( $total_donations / $total_events * 100, 1 ) : 0.0,
		);
	}

	/**
	 * Restituisce la serie giornaliera di donazioni e revenue.
	 *
	 * @param int $days Giorni di intervallo.
	 * @return list<array{date: string, donations: int, revenue: float}>
	 */
	private function get_daily_series( int $days ): array {
		$pt = $this->db->prefix . 'wppa_payments';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT
				    DATE(created_at) AS date,
				    COUNT(*) AS donations,
				    COALESCE( SUM(amount), 0 ) AS revenue
				FROM `{$pt}`
				WHERE status = 'completed'
				  AND created_at >= DATE_SUB( NOW(), INTERVAL %d DAY )
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				$days
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static fn ( array $r ) => array(
				'date'      => (string) $r['date'],
				'donations' => (int) $r['donations'],
				'revenue'   => round( (float) $r['revenue'], 2 ),
			),
			$rows
		);
	}

	/**
	 * Restituisce i top attachment per revenue nell'intervallo.
	 *
	 * @param int $days  Giorni di intervallo.
	 * @param int $limit Numero massimo di risultati.
	 * @return list<array{attachment_id: int, title: string, donations: int, revenue: float}>
	 */
	private function get_top_attachments( int $days, int $limit = 10 ): array {
		$pt = $this->db->prefix . 'wppa_payments';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT
				    attachment_id,
				    COUNT(*) AS donations,
				    COALESCE( SUM(amount), 0 ) AS revenue
				FROM `{$pt}`
				WHERE status = 'completed'
				  AND created_at >= DATE_SUB( NOW(), INTERVAL %d DAY )
				GROUP BY attachment_id
				ORDER BY revenue DESC
				LIMIT %d",
				$days,
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			function ( array $r ): array {
				$id    = (int) $r['attachment_id'];
				$title = get_the_title( $id );
				return array(
					'attachment_id' => $id,
					'title'         => $title ? (string) $title : "Attachment #{$id}",
					'donations'     => (int) $r['donations'],
					'revenue'       => round( (float) $r['revenue'], 2 ),
				);
			},
			$rows
		);
	}
}
