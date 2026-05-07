<?php
/**
 * Controller REST per la gestione degli attachment protetti.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\REST;

use PaidAttachments\Database\AttachmentConfigRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Gestisce gli endpoint REST per la lista e la configurazione degli attachment.
 *
 * Endpoints esposti:
 *   GET  /wppa/v1/admin/attachments
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
	 * Costruttore.
	 *
	 * @param AttachmentConfigRepository $config_repo Repository configurazioni.
	 */
	public function __construct( AttachmentConfigRepository $config_repo ) {
		$this->config_repo = $config_repo;
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
				),
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
	 * GET /wppa/v1/admin/attachments — Lista attachment immagine paginata.
	 *
	 * @param WP_REST_Request $request Richiesta REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_attachments( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$search   = (string) $request->get_param( 'search' );
		$filter   = (string) $request->get_param( 'filter' );

		$query_args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $search ) ) {
			$query_args['s'] = $search;
		}

		$query = new \WP_Query( $query_args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$config  = $this->config_repo->find_by_attachment_id( $post->ID );
			$enabled = $config ? (bool) $config->enabled : false;

			if ( 'protected' === $filter && ! $enabled ) {
				continue;
			}
			if ( 'unprotected' === $filter && $enabled ) {
				continue;
			}

			$items[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ),
				'date'      => get_the_date( 'c', $post ),
				'thumbnail' => wp_get_attachment_image_url( $post->ID, 'thumbnail' )
					? wp_get_attachment_image_url( $post->ID, 'thumbnail' )
					: '',
				'filename'  => basename( get_attached_file( $post->ID ) ? get_attached_file( $post->ID ) : '' ),
				'link'      => get_attachment_link( $post->ID ),
				'protected' => $enabled,
				'config'    => $config ? $this->config_to_array( $config ) : null,
			);
		}

		$response = $this->success( $items );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

		return $response;
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
					'id'     => $id,
					'exists' => false,
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
		);
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
