<?php
/**
 * Controller REST per la gestione degli attachment protetti.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\REST;

use PaidAttachments\Database\AttachmentConfigRepository;
use PaidAttachments\Database\StatsRepository;
use PaidAttachments\Frontend\AttachmentPageRenderer;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Gestisce gli endpoint REST per la lista e la configurazione degli attachment.
 *
 * Endpoints esposti:
 *   GET  /wppa/v1/admin/attachments
 *   GET  /wppa/v1/admin/attachments/filters
 *   GET  /wppa/v1/admin/attachments/{id}/config
 *   POST /wppa/v1/admin/attachments/{id}/config
 */
final class AttachmentController extends RestController {

	/**
	 * Repository configurazioni attachment.
	 *
	 * @var AttachmentConfigRepository
	 */
	private AttachmentConfigRepository $config_repo;

	/**
	 * Repository statistiche aggregate.
	 *
	 * @var StatsRepository
	 */
	private StatsRepository $stats_repo;

	/**
	 * Costruttore.
	 *
	 * @param AttachmentConfigRepository $config_repo Repository configurazioni.
	 * @param StatsRepository            $stats_repo  Repository statistiche.
	 */
	public function __construct( AttachmentConfigRepository $config_repo, StatsRepository $stats_repo ) {
		$this->config_repo = $config_repo;
		$this->stats_repo  = $stats_repo;
	}

	/**
	 * Registra le route REST.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/admin/attachments',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_attachments' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'filter'   => array(
						'type'    => 'string',
						'default' => 'all',
						'enum'    => array( 'all', 'protected', 'unprotected' ),
					),
					// Tipo MIME esatto (es. image/jpeg) o tipo principale (es. image).
					'mime'     => array(
						'type'              => 'string',
						'default'           => '',
						'validate_callback' => fn ( $value ) => '' === $value || in_array( $value, $this->get_mime_filter_values(), true ),
					),
					// Anno e mese di caricamento nel formato AAAAMM.
					'month'    => array(
						'type'    => 'string',
						'default' => '',
						'pattern' => '^(\d{6})?$',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/attachments/filters',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_filters' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/admin/attachments/(?P<id>\d+)/config',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_config' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_config' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'id' => array(
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
						),
					),
				),
			)
		);
	}

	/**
	 * GET /wppa/v1/admin/attachments — Lista paginata di tutti gli attachment (ogni tipo di file).
	 *
	 * Il filtro `protected` / `unprotected` viene applicato direttamente nella
	 * WP_Query tramite `post__in` / `post__not_in`, così paginazione e conteggi
	 * totali sono sempre coerenti con il set filtrato.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_attachments( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$search   = (string) $request->get_param( 'search' );
		$filter   = (string) $request->get_param( 'filter' );
		$mime     = (string) $request->get_param( 'mime' );
		$month    = (string) $request->get_param( 'month' );

		// Recupera gli ID protetti una volta sola quando serve il filtro.
		if ( 'protected' === $filter || 'unprotected' === $filter ) {
			$protected_ids = $this->config_repo->get_protected_ids();

			// Nessun attachment protetto in DB: risposta vuota immediata per "protected".
			if ( 'protected' === $filter && empty( $protected_ids ) ) {
				$response = $this->success( array() );
				$response->header( 'X-WP-Total', '0' );
				$response->header( 'X-WP-TotalPages', '0' );
				return $response;
			}
		}

		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $search ) ) {
			$query_args['s'] = $search;
		}

		if ( '' !== $mime ) {
			$query_args['post_mime_type'] = $mime;
		}

		if ( '' !== $month ) {
			$query_args['m'] = $month;
		}

		if ( 'protected' === $filter && ! empty( $protected_ids ) ) {
			$query_args['post__in'] = $protected_ids;
		} elseif ( 'unprotected' === $filter && ! empty( $protected_ids ) ) {
			$query_args['post__not_in'] = $protected_ids;
		}

		$query = new \WP_Query( $query_args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$config  = $this->config_repo->find_by_attachment_id( $post->ID );
			$enabled = $config ? (bool) $config->enabled : false;

			$items[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'date'      => get_the_date( 'c', $post ),
				// Con $icon = true i file non immagine restituiscono l'icona del tipo di file.
				'thumbnail' => (string) wp_get_attachment_image_url( $post->ID, 'thumbnail', true ),
				'mime_type' => $post->post_mime_type,
				'filename'  => basename( get_attached_file( $post->ID ) ? get_attached_file( $post->ID ) : '' ),
				'link'      => get_attachment_link( $post->ID ),
				'protected' => $enabled,
				'config'    => $config ? $this->config_to_array( $config ) : null,
				'stats'     => null,
			);
		}

		// Aggiunge stats per soli attachment protetti in un'unica query batch.
		$items_protected_ids = array_column(
			array_filter( $items, static fn ( array $i ) => $i['protected'] ),
			'id'
		);

		if ( ! empty( $items_protected_ids ) ) {
			$stats_map = $this->stats_repo->get_per_attachment_stats( $items_protected_ids );
			foreach ( $items as &$item ) {
				if ( $item['protected'] && isset( $stats_map[ $item['id'] ] ) ) {
					$item['stats'] = $stats_map[ $item['id'] ];
				}
			}
			unset( $item );
		}

		$response = $this->success( $items );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

		return $response;
	}

	/**
	 * GET /wppa/v1/admin/attachments/filters — Opzioni dei filtri per mese e per tipo.
	 *
	 * @return WP_REST_Response
	 */
	public function get_filters(): WP_REST_Response {
		global $wpdb, $wp_locale;

		// Stessa query della libreria media di WordPress (wp_enqueue_media()).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
				FROM {$wpdb->posts}
				WHERE post_type = %s
				ORDER BY year DESC, month DESC",
				'attachment'
			)
		);

		$months = array();
		foreach ( $rows as $row ) {
			$months[] = array(
				'value' => sprintf( '%04d%02d', $row->year, $row->month ),
				'label' => sprintf( '%s %d', $wp_locale->get_month( $row->month ), $row->year ),
			);
		}

		$types = array();
		foreach ( $this->get_mime_filter_values() as $value ) {
			$types[] = array(
				'value' => $value,
				// I tipi principali (es. "image") filtrano tutti i sottotipi.
				'label' => str_contains( $value, '/' ) ? $value : $value . '/* (tutti)',
			);
		}

		return $this->success(
			array(
				'months' => $months,
				'types'  => $types,
			)
		);
	}

	/**
	 * Valori ammessi per il filtro tipo: i MIME presenti nella libreria e, per i tipi
	 * principali con più sottotipi, il tipo principale da solo (es. "image").
	 *
	 * @return string[]
	 */
	private function get_mime_filter_values(): array {
		$mimes = get_available_post_mime_types( 'attachment' );
		sort( $mimes );

		$majors = array_count_values( array_map( static fn ( string $m ) => strtok( $m, '/' ), $mimes ) );

		$values = array();
		foreach ( $mimes as $m ) {
			$major = strtok( $m, '/' );
			if ( $majors[ $major ] > 1 && ! in_array( $major, $values, true ) ) {
				$values[] = $major;
			}
			$values[] = $m;
		}

		return $values;
	}

	/**
	 * GET /wppa/v1/admin/attachments/{id}/config — Configurazione di un attachment.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_config( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (int) $request->get_param( 'id' );

		if ( ! get_post( $id ) ) {
			return $this->error( 'not_found', __( 'Attachment non trovato.', 'wp-paid-attachments' ), 404 );
		}

		$config = $this->config_repo->find_by_attachment_id( $id );

		if ( ! $config ) {
			return $this->success(
				array(
					'id'            => $id,
					'exists'        => false,
					'redirect_mode' => $this->get_redirect_mode( $id ),
				)
			);
		}

		return $this->success( $this->config_to_array( $config ) );
	}

	/**
	 * POST /wppa/v1/admin/attachments/{id}/config — Salva la configurazione di un attachment.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_config( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (int) $request->get_param( 'id' );

		if ( ! get_post( $id ) ) {
			return $this->error( 'not_found', __( 'Attachment non trovato.', 'wp-paid-attachments' ), 404 );
		}

		$body = $request->get_json_params();

		if ( ! is_array( $body ) ) {
			return $this->error( 'invalid_payload', __( 'Payload JSON non valido.', 'wp-paid-attachments' ) );
		}

		$data = $this->sanitize_config( $body );
		$this->config_repo->upsert( $id, $data );

		// Override redirect per-attachment: salvato come post meta (vale anche senza protezione).
		if ( isset( $body['redirect_mode'] ) ) {
			$redirect_mode = in_array( $body['redirect_mode'], array( 'parent', 'none' ), true ) ? $body['redirect_mode'] : '';
			if ( '' === $redirect_mode ) {
				delete_post_meta( $id, AttachmentPageRenderer::REDIRECT_META_KEY );
			} else {
				update_post_meta( $id, AttachmentPageRenderer::REDIRECT_META_KEY, $redirect_mode );
			}
		}

		$config = $this->config_repo->find_by_attachment_id( $id );

		return $this->success( $config ? $this->config_to_array( $config ) : array_merge( array( 'attachment_id' => $id ), $data ) );
	}

	/**
	 * Converte un AttachmentConfig in array serializzabile.
	 *
	 * @param \PaidAttachments\Domain\AttachmentConfig $config Oggetto configurazione.
	 * @return array<string, mixed>
	 */
	private function config_to_array( \PaidAttachments\Domain\AttachmentConfig $config ): array {
		return array(
			'attachment_id'      => $config->attachment_id,
			'enabled'            => (bool) $config->enabled,
			'payment_mode'       => $config->payment_mode,
			'currency'           => $config->currency,
			'suggested_amounts'  => $config->suggested_amounts,
			'code_validity_days' => $config->code_validity_days,
			'max_uses'           => $config->code_max_uses,
			'allow_free_view'    => (bool) $config->allow_free_view,
			'paywall_text'       => $config->custom_text,
			'email_subject'      => $config->custom_email_subject,
			'email_message'      => $config->custom_email_body,
			'redirect_mode'      => $this->get_redirect_mode( $config->attachment_id ),
		);
	}

	/**
	 * Override redirect per-attachment: '' (default globale), 'parent' o 'none'.
	 *
	 * @param int $attachment_id ID attachment.
	 * @return string
	 */
	private function get_redirect_mode( int $attachment_id ): string {
		return (string) get_post_meta( $attachment_id, AttachmentPageRenderer::REDIRECT_META_KEY, true );
	}

	/**
	 * Sanitizza i dati di configurazione in ingresso.
	 *
	 * @param array<string, mixed> $data Dati da sanitizzare.
	 * @return array<string, mixed>
	 */
	private function sanitize_config( array $data ): array {
		return array(
			'enabled'              => isset( $data['enabled'] ) ? (bool) $data['enabled'] : false,
			'payment_mode'         => in_array( $data['payment_mode'] ?? '', array( 'paypal_donate', 'paypal_smart', 'both' ), true )
				? $data['payment_mode']
				: null,
			'currency'             => isset( $data['currency'] )
				? strtoupper( sanitize_text_field( $data['currency'] ) )
				: null,
			'suggested_amounts'    => isset( $data['suggested_amounts'] ) && is_array( $data['suggested_amounts'] )
				? array_map( 'absint', $data['suggested_amounts'] )
				: null,
			'code_validity_days'   => isset( $data['code_validity_days'] )
				? absint( $data['code_validity_days'] )
				: null,
			'code_max_uses'        => isset( $data['max_uses'] )
				? absint( $data['max_uses'] )
				: null,
			'allow_free_view'      => isset( $data['allow_free_view'] )
				? (bool) $data['allow_free_view']
				: null,
			'custom_text'          => isset( $data['paywall_text'] )
				? wp_kses_post( $data['paywall_text'] )
				: null,
			'custom_email_subject' => isset( $data['email_subject'] )
				? sanitize_text_field( $data['email_subject'] )
				: null,
			'custom_email_body'    => isset( $data['email_message'] )
				? wp_kses_post( $data['email_message'] )
				: null,
		);
	}
}
