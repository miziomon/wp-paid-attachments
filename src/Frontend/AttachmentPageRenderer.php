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

		// Per le attachment PROTETTE prendiamo controllo completo del template.
		// Alcuni temi (Blocksy, Astra, Kadence ecc.) renderizzano l'immagine
		// HD fuori da `the_content` con template/block custom, bypassando il
		// nostro filtro principale. L'unico modo robusto è sostituire l'intera
		// risposta HTML, mantenendo solo header e footer del tema.
		$post = get_post();
		if ( ! $post ) {
			return;
		}

		$config = $this->config_repo->find_by_attachment_id( $post->ID );
		if ( ! $config || ! $config->enabled ) {
			return;
		}

		$this->render_protected_template( $post->ID, $config );
		exit;
	}

	/**
	 * Renderizza un template minimale per attachment protetti.
	 *
	 * Usa `get_header()` / `get_footer()` del tema attivo per preservare
	 * stili e navigazione, ma sostituisce il contenuto principale con il
	 * solo Web Component `<wppa-donation-widget>`.
	 *
	 * @param int              $attachment_id ID attachment.
	 * @param AttachmentConfig $config        Configurazione attachment.
	 * @return void
	 */
	private function render_protected_template( int $attachment_id, AttachmentConfig $config ): void {
		// Header tema (carica wp_head, stili, nav, ecc.).
		get_header();

		printf(
			'<main id="wppa-protected-main" class="wppa-protected-main" style="max-width:760px;margin:2rem auto;padding:0 1rem;">%s</main>',
			$this->render_widget( $attachment_id, $config ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

		// Footer tema (carica wp_footer, scripts, ecc.).
		get_footer();
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

		// Impostazioni globali per titolo/testo paywall e credenziali PayPal.
		$settings = get_option( 'wppa_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		// Titolo paywall: impostazione globale, altrimenti titolo attachment.
		$default_title = trim( (string) ( $settings['default_paywall_title'] ?? '' ) );
		$paywall_title = '' !== $default_title ? $default_title : get_the_title();

		// Testo paywall: override per-attachment, altrimenti impostazione globale.
		$default_text = trim( (string) ( $settings['default_paywall_text'] ?? '' ) );
		$paywall_text = '' !== $config->custom_text ? $config->custom_text : $default_text;

		$amounts           = wp_json_encode( $config->suggested_amounts );
		$currency          = esc_attr( $config->currency );
		$allow_free_view   = $config->allow_free_view ? 'true' : 'false';
		$paypal_client_id  = esc_attr( (string) ( $settings['paypal_client_id'] ?? '' ) );
		$paypal_donate_btn = esc_attr( (string) ( $settings['paypal_donate_button_id'] ?? '' ) );

		// Fallback automatico: se donate mode ma il Button ID manca e il Client ID c'è → smart.
		$raw_mode = $config->payment_mode;
		if ( 'paypal_donate' === $raw_mode && '' === $paypal_donate_btn && '' !== $paypal_client_id ) {
			$raw_mode = 'paypal_smart';
		}
		$payment_mode = esc_attr( $raw_mode );
		$api_root     = esc_attr( rest_url( 'wppa/v1' ) );
		$nonce        = esc_attr( wp_create_nonce( 'wp_rest' ) );

		return sprintf(
			'<wppa-donation-widget
				attachment-id="%d"
				thumbnail="%s"
				paywall-title="%s"
				paywall-text="%s"
				amounts="%s"
				currency="%s"
				allow-free-view="%s"
				payment-mode="%s"
				paypal-client-id="%s"
				paypal-donate-button-id="%s"
				api-root="%s"
				nonce="%s"
			></wppa-donation-widget>',
			$attachment_id,
			esc_attr( $thumbnail_url ),
			esc_attr( $paywall_title ),
			esc_attr( $paywall_text ),
			esc_attr( $amounts ),
			$currency,
			$allow_free_view,
			$payment_mode,
			$paypal_client_id,
			$paypal_donate_btn,
			$api_root,
			$nonce
		);
	}
}
