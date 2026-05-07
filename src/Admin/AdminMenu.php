<?php
/**
 * Registrazione del menu admin top-level e dei sottomenu.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Admin;

/**
 * Registra il menu "Paid Attachments" con i tre sottomenu nell'admin WP.
 */
final class AdminMenu {

	/**
	 * Slug della pagina principale del plugin.
	 */
	const MENU_SLUG = 'wp-paid-attachments';

	/**
	 * Capability richiesta per accedere al menu.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Registra gli hook per il menu admin.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
	}

	/**
	 * Aggiunge le voci di menu nell'admin WP.
	 *
	 * @return void
	 */
	public function add_menus(): void {
		// Menu top-level — redirect alla pagina Impostazioni.
		add_menu_page(
			__( 'WP Paid Attachments', 'wp-paid-attachments' ),
			__( 'Paid Attachments', 'wp-paid-attachments' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( SettingsPage::class, 'render' ),
			'dashicons-money-alt',
			58
		);

		// Sottomenu: Impostazioni (stessa callback della voce principale).
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Impostazioni', 'wp-paid-attachments' ),
			__( 'Impostazioni', 'wp-paid-attachments' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( SettingsPage::class, 'render' )
		);

		// Sottomenu: Attachment protetti (Slice 4).
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Attachment protetti', 'wp-paid-attachments' ),
			__( 'Attachment protetti', 'wp-paid-attachments' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-attachments',
			array( AttachmentsListPage::class, 'render' )
		);

		// Sottomenu: Statistiche (Slice 11).
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Statistiche', 'wp-paid-attachments' ),
			__( 'Statistiche', 'wp-paid-attachments' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-stats',
			array( StatsPage::class, 'render' )
		);
	}
}
