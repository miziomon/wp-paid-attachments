<?php
/**
 * Classe principale del plugin — singleton che centralizza la registrazione degli hook.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments;

use PaidAttachments\Admin\AdminMenu;
use PaidAttachments\Admin\SettingsPage;
use PaidAttachments\Database\AttachmentConfigRepository;
use PaidAttachments\Frontend\AssetsLoader;
use PaidAttachments\Frontend\AttachmentPageRenderer;
use PaidAttachments\Database\PaymentRepository;
use PaidAttachments\Database\UnlockCodeRepository;
use PaidAttachments\Email\EmailSender;
use PaidAttachments\Email\TemplateRenderer;
use PaidAttachments\Payment\PayPalDonateProvider;
use PaidAttachments\Payment\PayPalSmartButtonsProvider;
use PaidAttachments\Database\StatsRepository;
use PaidAttachments\REST\AdminController;
use PaidAttachments\REST\AttachmentController;
use PaidAttachments\REST\CheckoutController;
use PaidAttachments\REST\FreeViewController;
use PaidAttachments\REST\StatsController;
use PaidAttachments\REST\UnlockController;
use PaidAttachments\REST\WebhookController;

/**
 * Bootstrap centrale del plugin.
 *
 * Istanziata una sola volta su `plugins_loaded`. Ogni slice successivo
 * aggiungerà chiamate ai sotto-componenti all'interno di init().
 */
final class Plugin {

	/**
	 * Istanza singleton.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Costruttore privato — usare get_instance().
	 */
	private function __construct() {}

	/**
	 * Restituisce l'istanza singleton del plugin.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registra tutti gli hook. Chiamato su `plugins_loaded`.
	 *
	 * @return void
	 */
	public function init(): void {
		// Carica le traduzioni del plugin.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Admin: menu e asset.
		( new AdminMenu() )->register();
		( new SettingsPage() )->register();

		// Frontend pubblico.
		$config_repo = new AttachmentConfigRepository( $GLOBALS['wpdb'] );
		( new AssetsLoader() )->register();
		( new AttachmentPageRenderer( $config_repo ) )->register();

		// REST API.
		$stats_repo = new StatsRepository( $GLOBALS['wpdb'] );
		( new AdminController() )->register();
		( new AttachmentController( $config_repo, $stats_repo ) )->register();
		( new StatsController( $stats_repo ) )->register();
		$code_repo    = new UnlockCodeRepository( $GLOBALS['wpdb'] );
		$payment_repo = new PaymentRepository( $GLOBALS['wpdb'] );
		$email_sender = new EmailSender( $code_repo, new TemplateRenderer() );
		$providers    = array( new PayPalDonateProvider(), new PayPalSmartButtonsProvider() );

		( new UnlockController( $config_repo, $code_repo ) )->register();
		( new FreeViewController( $config_repo ) )->register();
		( new CheckoutController( $config_repo, $payment_repo, new PayPalSmartButtonsProvider() ) )->register();
		( new WebhookController( $providers, $payment_repo, $config_repo, $email_sender ) )->register();
	}

	/**
	 * Carica il text domain per le traduzioni.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-paid-attachments',
			false,
			dirname( plugin_basename( WPPA_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
