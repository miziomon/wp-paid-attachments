<?php
/**
 * Template email plain-text per il codice di sblocco.
 *
 * Variabili disponibili:
 *   $site_name, $unlock_code, $unlock_url, $attachment_title, $expires_date
 *
 * @package PaidAttachments
 */

defined( 'ABSPATH' ) || exit;
?>
Grazie per la tua donazione a <?php echo esc_html( $site_name ); ?>!

Il tuo codice di sblocco per "<?php echo esc_html( $attachment_title ); ?>" è:

	<?php echo esc_html( $unlock_code ); ?>

Sblocca direttamente l'immagine cliccando il link qui sotto:
<?php echo esc_url( $unlock_url ); ?>

Il codice è valido fino al <?php echo esc_html( $expires_date ); ?>.
In alternativa puoi incollarlo manualmente sulla pagina dell'immagine.

---
Questa email è stata inviata da <?php echo esc_html( $site_name ); ?>.
Non rispondere a questa email.
