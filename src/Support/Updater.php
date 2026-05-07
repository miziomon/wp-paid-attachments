<?php
/**
 * Aggiornamento automatico del plugin da GitHub Releases.
 *
 * Usa plugin-update-checker (yahnis-elsts/plugin-update-checker) per
 * agganciarsi al sistema di aggiornamenti nativo di WordPress e controllare
 * la presenza di nuove versioni su GitHub.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Support;

/**
 * Registra l'update checker che punta al repository GitHub del plugin.
 *
 * Il checker confronta WPPA_VERSION con il tag più recente su GitHub.
 * Quando viene pubblicata una nuova release con un ZIP allegato come asset,
 * WordPress mostra la notifica di aggiornamento nel pannello plugin.
 */
final class Updater {

	/**
	 * URL del repository GitHub del plugin.
	 */
	const REPO_URL = 'https://github.com/miziomon/wp-paid-attachments/';

	/**
	 * Inizializza il controllo aggiornamenti.
	 *
	 * Da chiamare su `plugins_loaded` tramite Plugin::init().
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! class_exists( 'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
			return;
		}

		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			self::REPO_URL,
			WPPA_PLUGIN_FILE,
			'wp-paid-attachments'
		);

		// Usa i release asset (ZIP allegato alla release) invece del
		// codice sorgente auto-generato da GitHub (che non include vendor/ e build/).
		$checker->getVcsApi()->enableReleaseAssets();
	}
}
