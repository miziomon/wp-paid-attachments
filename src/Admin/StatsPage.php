<?php
/**
 * Pagina admin statistiche.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Admin;

/**
 * Pagina "Statistiche" — mount point React.
 *
 * Implementazione completa in Slice 11.
 */
final class StatsPage {

	/**
	 * Renderizza il mount point JSX per le statistiche.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( AdminMenu::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accesso non autorizzato.', 'wp-paid-attachments' ) );
		}

		echo '<div class="wrap">';
		echo '<div id="wppa-admin-stats-root"></div>';
		echo '</div>';
	}
}
