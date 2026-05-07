<?php
/**
 * Pagina admin lista attachment protetti.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Admin;

/**
 * Pagina "Attachment protetti" — mount point React.
 *
 * Implementazione completa in Slice 4.
 */
final class AttachmentsListPage {

	/**
	 * Renderizza il mount point JSX per la lista attachment.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( AdminMenu::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accesso non autorizzato.', 'wp-paid-attachments' ) );
		}

		echo '<div class="wrap">';
		echo '<div id="wppa-admin-attachments-root"></div>';
		echo '</div>';
	}
}
