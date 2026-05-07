<?php
/**
 * Sostituzione del contenuto della attachment page protetta.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Frontend;

use PaidAttachments\Database\AttachmentConfigRepository;
use PaidAttachments\Domain\AttachmentConfig;

/**
 * Intercetta `the_content` sulle attachment page protette e inietta
 * il Web Component `<wppa-donation-widget>` al posto dell'immagine originale.
 *
 * L'URL del file HD non viene mai incluso nel HTML iniziale della pagina.
 * L'accesso avviene solo via endpoint REST con token HMAC firmato.
 */
final class AttachmentPageRenderer {

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
	 * Registra gli hook WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'the_content', array( $this, 'replace_content' ), 20 );
		add_action( 'wp_head', array( $this, 'maybe_add_noindex' ) );
		// Priorità 1: intercetta prima dei redirect WP (priorità 10).
		add_action( 'template_redirect', array( $this, 'prevent_attachment_redirect' ), 1 );
		// Nasconde il blocco featured-image del tema (mostrerebbe l'immagine HD linkabile al file).
		add_filter( 'render_block', array( $this, 'maybe_hide_featured_image_block' ), 10, 2 );
	}

	/**
	 * Impedisce i redirect di WordPress su tutte le attachment page.
	 *
	 * WordPress 6.4+ reindirizza tutte le attachment page verso il post parent
	 * o la homepage (opzione `wp_attachment_pages_redirect`). Questo metodo
	 * rimuove quel comportamento per TUTTI gli attachment, protetti o no.
	 *
	 * @return void
	 */
	public function prevent_attachment_redirect(): void {
		if ( ! is_attachment() ) {
			return;
		}

		// WordPress 6.4+: rimuove il redirect automatico delle attachment page.
		remove_action( 'template_redirect', 'wp_redirect_to_attachment_parent_post' );
		// Rimuove anche il redirect canonico che può interferire.
		remove_action( 'template_redirect', 'redirect_canonical' );
	}

	/**
	 * Sostituisce il contenuto della attachment page con il Web Component.
	 *
	 * @param string $content Contenuto originale del post.
	 * @return string
	 */
	public function replace_content( string $content ): string {
		if ( ! is_attachment() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post ) {
			return $content;
		}

		$config = $this->config_repo->find_by_attachment_id( $post->ID );
		if ( ! $config || ! $config->enabled ) {
			// Non protetto: per le immagini mostra il visualizzatore con download diretto.
			$mime = (string) get_post_mime_type( $post->ID );
			if ( str_starts_with( $mime, 'image/' ) ) {
				return $this->render_unprotected( $post->ID );
			}
			return $content;
		}

		return $this->render_widget( $post->ID, $config );
	}

	/**
	 * Aggiunge `<meta name="robots" content="noindex">` sulle attachment page
	 * protette per impedire l'indicizzazione.
	 *
	 * @return void
	 */
	public function maybe_add_noindex(): void {
		if ( ! is_attachment() ) {
			return;
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		if ( ! $this->config_repo->is_protected( $post->ID ) ) {
			return;
		}

		echo '<meta name="robots" content="noindex">' . "\n";
	}

	/**
	 * Nasconde il blocco `core/post-featured-image` su tutte le attachment page immagine.
	 *
	 * In block-theme come TT5, il template mostra l'immagine come featured image.
	 * Per le pagine protette bypasserebbe il paywall; per quelle non protette
	 * creerebbe un duplicato con il render_unprotected() in the_content.
	 *
	 * @param string               $block_content HTML del blocco renderizzato.
	 * @param array<string, mixed> $block         Dati del blocco.
	 * @return string
	 */
	public function maybe_hide_featured_image_block( string $block_content, array $block ): string {
		if ( 'core/post-featured-image' !== ( $block['blockName'] ?? '' ) ) {
			return $block_content;
		}

		if ( ! is_attachment() ) {
			return $block_content;
		}

		$post = get_post();
		if ( ! $post ) {
			return $block_content;
		}

		// Nasconde per tutte le attachment immagine (protette e non).
		$mime = (string) get_post_mime_type( $post->ID );
		if ( ! str_starts_with( $mime, 'image/' ) ) {
			return $block_content;
		}

		return '';
	}

	/**
	 * Genera l'HTML per attachment non protetti: immagine grande + pulsante download.
	 *
	 * L'URL diretto del file è esposto perché l'attachment non è protetto.
	 *
	 * @param int $attachment_id ID attachment WP.
	 * @return string HTML del viewer non-protetto.
	 */
	private function render_unprotected( int $attachment_id ): string {
		$large_url = wp_get_attachment_image_url( $attachment_id, 'large' );
		$full_url  = wp_get_attachment_url( $attachment_id );

		if ( ! $large_url || ! $full_url ) {
			return '';
		}

		$title = esc_attr( get_the_title( $attachment_id ) );

		return sprintf(
			'<div class="wppa-unprotected-attachment" style="max-width:640px;margin:0 auto;">
				<img src="%s" alt="%s" style="max-width:100%%;height:auto;display:block;margin:0 auto 1rem;border-radius:4px;" />
				<p style="text-align:center;">
					<a href="%s" download style="display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1.25rem;background:#00a32a;color:#fff;text-decoration:none;border-radius:4px;font-size:.95rem;font-weight:600;">
						&#11015; Scarica immagine
					</a>
				</p>
			</div>',
			esc_url( $large_url ),
			$title,
			esc_url( $full_url )
		);
	}

	/**
	 * Genera l'HTML del Web Component con i dati di configurazione.
	 *
	 * I dati sensibili (URL HD) non sono mai inclusi qui — passano solo
	 * tramite endpoint REST autenticato con token HMAC.
	 *
	 * @param int              $attachment_id ID attachment WP.
	 * @param AttachmentConfig $config        Configurazione attachment.
	 * @return string HTML del Web Component.
	 */
	private function render_widget( int $attachment_id, AttachmentConfig $config ): string {
		// Thumbnail per anteprima (non il file HD).
		$thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'medium' );
		$thumbnail_url = $thumbnail_url ? $thumbnail_url : '';

		$title           = esc_attr( $config->custom_text ? $config->custom_text : get_the_title() );
		$amounts         = wp_json_encode( $config->suggested_amounts );
		$currency        = esc_attr( $config->currency );
		$allow_free_view = $config->allow_free_view ? 'true' : 'false';
		$api_root        = esc_attr( rest_url( 'wppa/v1' ) );
		$nonce           = esc_attr( wp_create_nonce( 'wp_rest' ) );

		return sprintf(
			'<wppa-donation-widget
				attachment-id="%d"
				thumbnail="%s"
				title="%s"
				amounts="%s"
				currency="%s"
				allow-free-view="%s"
				api-root="%s"
				nonce="%s"
			></wppa-donation-widget>',
			$attachment_id,
			esc_attr( $thumbnail_url ),
			$title,
			esc_attr( $amounts ),
			$currency,
			$allow_free_view,
			$api_root,
			$nonce
		);
	}
}
