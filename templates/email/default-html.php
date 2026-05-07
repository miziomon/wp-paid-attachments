<?php
/**
 * Template email HTML per il codice di sblocco.
 *
 * Variabili disponibili:
 *   $site_name, $unlock_code, $unlock_url, $attachment_title, $expires_date
 *
 * @package PaidAttachments
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $site_name ); ?></title>
</head>
<body style="background:#f4f4f4;margin:0;padding:20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;max-width:100%">

	<!-- Header -->
	<tr>
		<td style="background:#2271b1;padding:24px 32px">
			<h1 style="margin:0;color:#fff;font-size:1.2rem;font-weight:700">
				<?php echo esc_html( $site_name ); ?>
			</h1>
		</td>
	</tr>

	<!-- Body -->
	<tr>
		<td style="padding:32px">
			<h2 style="margin:0 0 12px;font-size:1.1rem;color:#1d2327">
				Il tuo codice di sblocco per <em><?php echo esc_html( $attachment_title ); ?></em>
			</h2>
			<p style="color:#646970;margin:0 0 24px">
				Grazie per la tua donazione! Usa il codice qui sotto per visualizzare e scaricare l'immagine in alta risoluzione.
			</p>

			<!-- Codice -->
			<div style="background:#f0f6fc;border:2px solid #2271b1;border-radius:6px;padding:20px;text-align:center;margin:0 0 24px">
				<span style="font-size:1.75rem;font-weight:700;letter-spacing:.15em;color:#1d2327;font-family:monospace">
					<?php echo esc_html( $unlock_code ); ?>
				</span>
			</div>

			<!-- CTA -->
			<p style="text-align:center;margin:0 0 16px">
				<a href="<?php echo esc_url( $unlock_url ); ?>"
					style="background:#2271b1;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:700;display:inline-block">
					Sblocca direttamente l'immagine →
				</a>
			</p>

			<p style="color:#a7aaad;font-size:.85rem;text-align:center;margin:0">
				Il codice è valido fino al <strong><?php echo esc_html( $expires_date ); ?></strong>.<br>
				In alternativa puoi incollarlo manualmente sulla pagina dell'immagine.
			</p>
		</td>
	</tr>

	<!-- Footer -->
	<tr>
		<td style="background:#f4f4f4;padding:16px 32px;text-align:center">
			<p style="color:#a7aaad;font-size:.8rem;margin:0">
				Questa email è stata inviata da <strong><?php echo esc_html( $site_name ); ?></strong>.<br>
				Non rispondere a questa email.
			</p>
		</td>
	</tr>

</table>
</td></tr>
</table>
</body>
</html>
