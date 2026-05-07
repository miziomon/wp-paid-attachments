<?php
/**
 * Logica eseguita alla disattivazione del plugin.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments;

/**
 * Gestisce l'hook di disattivazione del plugin.
 *
 * Non rimuove dati: quella responsabilità appartiene a uninstall.php.
 * Eventuali flush di rewrite rules o pulizie di cache leggere vanno qui.
 */
final class Deactivator {

	/**
	 * Callback per register_deactivation_hook().
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Nessuna azione necessaria al momento.
		// Se in futuro registreremo custom rewrite rules o cron events,
		// la pulizia andrà implementata qui.
	}
}
