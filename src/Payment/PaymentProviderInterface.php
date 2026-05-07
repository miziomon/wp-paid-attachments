<?php
/**
 * Interfaccia per i provider di pagamento.
 *
 * @package PaidAttachments
 */

declare(strict_types=1);

namespace PaidAttachments\Payment;

/**
 * Contratto per tutti i provider di pagamento del plugin.
 *
 * Ogni implementazione deve:
 * - Generare l'URL o il payload per avviare il pagamento.
 * - Elaborare i dati del webhook per confermare/rifiutare il pagamento.
 * - Restituire un array normalizzato (TransactionData) da salvare nel DB.
 */
interface PaymentProviderInterface {

	/**
	 * Identificativo univoco del provider (es. 'paypal_donate', 'paypal_smart').
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Genera l'URL o il payload per avviare il flusso di pagamento.
	 *
	 * @param int    $attachment_id ID attachment WP.
	 * @param float  $amount        Importo scelto dal donatore.
	 * @param string $currency     Codice valuta ISO 4217.
	 * @param string $return_url   URL di ritorno post-pagamento.
	 * @param string $cancel_url   URL di annullamento.
	 * @return array{url?: string, payload?: array<string, mixed>}
	 *         'url' per redirect, 'payload' per checkout inline.
	 */
	public function create_payment( int $attachment_id, float $amount, string $currency, string $return_url, string $cancel_url ): array;

	/**
	 * Verifica e processa un webhook in arrivo.
	 *
	 * @param array<string, mixed> $headers Header HTTP della request.
	 * @param string               $body    Body grezzo della request.
	 * @return array{
	 *   valid: bool,
	 *   transaction_id?: string,
	 *   attachment_id?: int,
	 *   amount?: float,
	 *   currency?: string,
	 *   payer_email?: string,
	 *   event_type?: string,
	 * } Dati transazione normalizzati, o array con `valid: false`.
	 */
	public function process_webhook( array $headers, string $body ): array;
}
