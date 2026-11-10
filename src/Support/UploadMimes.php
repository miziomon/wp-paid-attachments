<?php
/**
 * Tipi di file aggiuntivi consentiti nella libreria media.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Support;

/**
 * Abilita l'upload dei file Markdown (.md, .markdown).
 *
 * Il MIME è `text/plain` e non `text/markdown`: finfo riconosce i file Markdown
 * come `text/plain` e wp_check_filetype_and_ext() accetta un file `text/plain`
 * solo se l'estensione è mappata su uno dei tipi testuali noti (text/plain,
 * text/csv, ...). Con `text/markdown` l'upload verrebbe rifiutato.
 */
final class UploadMimes {

	/**
	 * Registra gli hook WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'upload_mimes', array( $this, 'add_markdown' ) );
	}

	/**
	 * Aggiunge le estensioni Markdown all'elenco dei MIME consentiti.
	 *
	 * @param array<string, string> $mimes MIME consentiti, indicizzati per estensione.
	 * @return array<string, string>
	 */
	public function add_markdown( array $mimes ): array {
		$mimes['md|markdown'] = 'text/plain';
		return $mimes;
	}
}
