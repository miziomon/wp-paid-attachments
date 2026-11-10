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
	 * Pagina delle release, linkata dalla scheda Aggiornamenti.
	 */
	const RELEASES_URL = 'https://github.com/miziomon/wp-paid-attachments/releases';

	/**
	 * Istanza del checker, conservata per il controllo manuale dalla scheda Aggiornamenti.
	 *
	 * @var object|null
	 */
	private static ?object $checker = null;

	/**
	 * Inizializza l'update checker.
	 *
	 * Chiamato da Plugin::init() su `plugins_loaded`. Se la libreria non è
	 * disponibile (es. vendor/ mancante), il metodo esce silenziosamente.
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
		self::$checker = $checker;
	}

	/**
	 * Stato dell'aggiornamento in base all'ultimo controllo salvato (nessuna chiamata a GitHub).
	 *
	 * @return array{current: string, latest: string, update_available: bool, checked_at: int, releases_url: string}|null
	 *         Null se il checker non è disponibile.
	 */
	public static function status(): ?array {
		if ( null === self::$checker ) {
			return null;
		}

		$update = self::$checker->getUpdate();

		return array(
			'current'          => WPPA_VERSION,
			'latest'           => $update ? (string) $update->version : WPPA_VERSION,
			'update_available' => null !== $update,
			'checked_at'       => (int) self::$checker->getUpdateState()->getLastCheck(),
			'releases_url'     => self::RELEASES_URL,
		);
	}

	/**
	 * Interroga subito GitHub e restituisce lo stato aggiornato.
	 *
	 * @return array{current: string, latest: string, update_available: bool, checked_at: int, releases_url: string}|null
	 */
	public static function check(): ?array {
		if ( null === self::$checker ) {
			return null;
		}

		self::$checker->checkForUpdates();

		return self::status();
	}
}
