/**
 * Form impostazioni globali — layout a tab con @wordpress/components.
 */

import { useState } from '@wordpress/element';
import {
	Button,
	TabPanel,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
	ExternalLink,
} from '@wordpress/components';

const PAYMENT_MODE_OPTIONS = [
	{ label: 'PayPal Donate (hosted button)', value: 'paypal_donate' },
	{ label: 'PayPal Smart Buttons', value: 'paypal_smart' },
	{ label: 'Entrambe (tab a scelta utente)', value: 'both' },
];

const CURRENCY_OPTIONS = [
	{ label: 'EUR — Euro', value: 'EUR' },
	{ label: 'USD — Dollaro USA', value: 'USD' },
	{ label: 'GBP — Sterlina', value: 'GBP' },
];

const PAYPAL_MODE_OPTIONS = [
	{ label: 'Sandbox (test)', value: 'sandbox' },
	{ label: 'Live (produzione)', value: 'live' },
];

const PAYPAL_ACCOUNT_TYPE_OPTIONS = [
	{ label: 'Business (REST API + Webhook v2)', value: 'business' },
	{ label: 'Personale (solo Donate Button + IPN)', value: 'personal' },
];

const TABS = [
	{ name: 'paypal',   title: 'Integrazione PayPal' },
	{ name: 'defaults', title: 'Default attachment' },
	{ name: 'freeview', title: 'Visualizzazione gratuita' },
	{ name: 'texts',    title: 'Testi & Email' },
	{ name: 'advanced', title: 'Avanzate' },
];

export default function SettingsForm( { settings, onSave, isSaving } ) {
	const [ form, setForm ] = useState( settings );

	function set( key, value ) {
		setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}

	function handleSubmit( e ) {
		e.preventDefault();
		onSave( form );
	}

	return (
		<form className="wppa-settings-form" onSubmit={ handleSubmit }>
			<TabPanel tabs={ TABS } className="wppa-settings-tabs">
				{ ( tab ) => (
					<div className="wppa-settings-tab-content">

						{ /* ── Tab: Integrazione PayPal ── */ }
						{ tab.name === 'paypal' && (
							<>
								<SelectControl
									label="Tipo di account PayPal"
									value={ form.paypal_account_type || 'business' }
									options={ PAYPAL_ACCOUNT_TYPE_OPTIONS }
									onChange={ ( v ) => set( 'paypal_account_type', v ) }
									help={
										( form.paypal_account_type || 'business' ) === 'personal'
											? 'Conto Personale: utilizzo del solo pulsante Donate hosted; conferma pagamenti via IPN. Smart Buttons e Webhook v2 non disponibili.'
											: 'Conto Business: pieno accesso a REST API, Smart Buttons inline e Webhook v2.'
									}
								/>

								<SelectControl
									label="Ambiente"
									value={ form.paypal_mode }
									options={ PAYPAL_MODE_OPTIONS }
									onChange={ ( v ) => set( 'paypal_mode', v ) }
									help="Usa Sandbox per i test; passa a Live quando sei pronto per la produzione."
								/>

								{ ( form.paypal_account_type || 'business' ) === 'business' && (
									<>
										<hr className="wppa-settings-sep" />
										<p className="wppa-settings-section-title">
											<strong>{ 'Credenziali API (Client ID + Secret)' }</strong>
										</p>
										<p className="description">
											{ 'Client ID e Secret si trovano nel ' }
											<ExternalLink href="https://developer.paypal.com/developer/applications">
												{ 'PayPal Developer Dashboard' }
											</ExternalLink>
											{ '. Procedura: ' }
											<strong>{ 'My Apps & Credentials → Create App' }</strong>
											{ ' → seleziona il tipo "Merchant" → copia le credenziali Sandbox o Live.' }
										</p>

										<TextControl
											label="Client ID PayPal"
											value={ form.paypal_client_id }
											onChange={ ( v ) => set( 'paypal_client_id', v ) }
											className="wppa-settings-form__wide"
										/>
										<TextControl
											label="Client Secret PayPal"
											value={ form.paypal_client_secret }
											type="password"
											onChange={ ( v ) => set( 'paypal_client_secret', v ) }
											className="wppa-settings-form__wide"
										/>
									</>
								) }

								{ ( form.paypal_account_type || 'business' ) === 'business' && (
									<>
										<hr className="wppa-settings-sep" />
										<p className="wppa-settings-section-title">
											<strong>{ 'PayPal Donate Button (modalità "Donate")' }</strong>
										</p>
										<p className="description">
											{ 'Il pulsante Donate è un pulsante hosted di PayPal: ' }
											{ 'il donatore viene reindirizzato su paypal.com, completa la donazione, ' }
											{ 'poi torna al tuo sito. Non richiede Client ID/Secret — usa solo il Hosted Button ID.' }
											<br />
											{ 'Per ottenerlo: ' }
											<ExternalLink href="https://www.paypal.com/donate/buttons/partner">
												{ 'paypal.com/donate/buttons' }
											</ExternalLink>
											{ ' → crea il pulsante → copia l\'ID dalla sezione "Website" del codice embed.' }
										</p>
										<TextControl
											label="Donate Button ID (Hosted Button)"
											value={ form.paypal_donate_button_id }
											onChange={ ( v ) => set( 'paypal_donate_button_id', v ) }
											placeholder="Es: ABC123XYZ"
											className="wppa-settings-form__wide"
										/>
									</>
								) }

								{ ( form.paypal_account_type || 'business' ) === 'personal' && (
									<>
										<hr className="wppa-settings-sep" />
										<p className="wppa-settings-section-title">
											<strong>{ 'Merchant ID PayPal (Conto Personale)' }</strong>
										</p>
										<p className="description">
											{ 'Per i Conti Personali PayPal usiamo il pulsante Donate "non-hosted": serve solo il tuo ' }
											<strong>{ 'Merchant ID' }</strong>
											{ ' (anche detto Account ID).' }
											<br />
											{ 'Per ottenerlo: ' }
											<ExternalLink href="https://www.paypal.com/donate/buttons">
												{ 'paypal.com/donate/buttons' }
											</ExternalLink>
											{ ' → crea il pulsante → nel codice HTML che PayPal ti mostra cerca ' }
											<code>{ '<input name="business" value="XXXXXXXXX" />' }</code>
											{ '. Il valore in ' }
											<code>{ 'value' }</code>
											{ ' è il tuo Merchant ID.' }
											<br />
											{ 'Esempio: in ' }
											<code>{ '<input name="business" value="3224NEH5QZ2S8" />' }</code>
											{ ' il Merchant ID è ' }
											<code>{ '3224NEH5QZ2S8' }</code>
											{ '.' }
										</p>
										<TextControl
											label="Merchant ID PayPal"
											value={ form.paypal_merchant_id || '' }
											onChange={ ( v ) => set( 'paypal_merchant_id', v ) }
											placeholder="Es: 3224NEH5QZ2S8"
											className="wppa-settings-form__wide"
										/>
									</>
								) }

								{ ( form.paypal_account_type || 'business' ) === 'business' && (
									<>
										<hr className="wppa-settings-sep" />
										<p className="wppa-settings-section-title">
											<strong>{ 'Webhook ID (per verifica firma)' }</strong>
										</p>
										<p className="description">
											{ 'Il Webhook notifica il plugin quando un pagamento è completato. Procedura:' }
											<ol style={ { margin: '8px 0 8px 1.5rem', padding: 0 } }>
												<li>
													{ 'Vai su ' }
													<ExternalLink href="https://developer.paypal.com/developer/applications">
														{ 'Developer Dashboard → My Apps' }
													</ExternalLink>
													{ ' → apri la tua app.' }
												</li>
												<li>
													{ 'Sezione "Webhooks" → ' }
													<strong>{ 'Add Webhook' }</strong>
													{ '.' }
												</li>
												<li>
													{ 'URL webhook da inserire: ' }
													<code>{ window.location.origin + '/wp-json/wppa/v1/webhook/paypal' }</code>
												</li>
												<li>
													{ 'Evento da selezionare: ' }
													<strong>{ 'PAYMENT.CAPTURE.COMPLETED' }</strong>
												</li>
												<li>{ 'Salva e copia il Webhook ID.' }</li>
											</ol>
											{ 'In modalità Sandbox puoi usare il ' }
											<ExternalLink href="https://developer.paypal.com/dashboard/webhooksimulator">
												{ 'Webhook Simulator' }
											</ExternalLink>
											{ ' per testare senza una transazione reale.' }
										</p>
										<TextControl
											label="Webhook ID PayPal"
											value={ form.paypal_webhook_id }
											onChange={ ( v ) => set( 'paypal_webhook_id', v ) }
											placeholder="Es: 1AB23456CD789012E"
											className="wppa-settings-form__wide"
										/>
									</>
								) }

								{ ( form.paypal_account_type || 'business' ) === 'personal' && (
									<>
										<hr className="wppa-settings-sep" />
										<p className="wppa-settings-section-title">
											<strong>{ 'Configurazione IPN (Conto Personale)' }</strong>
										</p>
										<p className="description">
											{ 'I Conti Personali non hanno accesso a Client ID/Secret né ai Webhook v2. Per ricevere la conferma del pagamento dal server PayPal usiamo il sistema legacy ' }
											<strong>{ 'IPN (Instant Payment Notification)' }</strong>
											{ '. Procedura una tantum:' }
											<ol style={ { margin: '8px 0 8px 1.5rem', padding: 0 } }>
												<li>
													{ 'Accedi al tuo conto PayPal su ' }
													<ExternalLink href="https://www.paypal.com/businessmanage/preferences/website">
														{ 'paypal.com → Profilo → Strumenti di vendita' }
													</ExternalLink>
													{ '.' }
												</li>
												<li>
													{ 'Cerca la voce ' }
													<strong>{ 'Notifiche di pagamento istantaneo (IPN)' }</strong>
													{ ' → ' }
													<strong>{ 'Aggiorna' }</strong>
													{ '.' }
												</li>
												<li>
													{ 'Imposta come URL di notifica: ' }
													<code>{ window.location.origin + '/wp-json/wppa/v1/ipn/paypal' }</code>
												</li>
												<li>
													{ 'Seleziona ' }
													<strong>{ 'Ricevi messaggi IPN (abilitato)' }</strong>
													{ ' e salva.' }
												</li>
											</ol>
											{ 'Il pagamento sarà confermato in automatico tramite round-trip su ' }
											<code>{ 'ipnpb.paypal.com' }</code>
											{ '. Non sono richiesti campi aggiuntivi.' }
										</p>
									</>
								) }
							</>
						) }

						{ /* ── Tab: Default attachment ── */ }
						{ tab.name === 'defaults' && (
							<>
								<SelectControl
									label="Modalità di pagamento predefinita"
									value={ form.default_payment_mode }
									options={ PAYMENT_MODE_OPTIONS }
									onChange={ ( v ) => set( 'default_payment_mode', v ) }
								/>
								<SelectControl
									label="Valuta"
									value={ form.default_currency }
									options={ CURRENCY_OPTIONS }
									onChange={ ( v ) => set( 'default_currency', v ) }
								/>
								<TextControl
									label="Importi suggeriti (separati da virgola)"
									value={ ( form.default_suggested_amounts || [] ).join( ', ' ) }
									onChange={ ( v ) =>
										set(
											'default_suggested_amounts',
											v
												.split( ',' )
												.map( ( n ) => parseInt( n.trim(), 10 ) )
												.filter( ( n ) => ! isNaN( n ) && n > 0 )
										)
									}
									help="Es: 1, 3, 5"
								/>
								<NumberControl
									label="Validità codice (giorni)"
									value={ form.default_code_validity_days }
									min={ 1 }
									onChange={ ( v ) => set( 'default_code_validity_days', parseInt( v, 10 ) || 30 ) }
								/>
								<NumberControl
									label="Usi massimi per codice (0 = illimitati)"
									value={ form.default_max_uses }
									min={ 0 }
									onChange={ ( v ) => set( 'default_max_uses', parseInt( v, 10 ) || 0 ) }
								/>
							</>
						) }

						{ /* ── Tab: Visualizzazione gratuita ── */ }
						{ tab.name === 'freeview' && (
							<>
								<ToggleControl
									label="Permetti visualizzazione gratuita una tantum"
									help="Se attivo, l'utente può vedere l'immagine HD una volta senza donare (per sessione browser)."
									checked={ form.allow_free_view }
									onChange={ ( v ) => set( 'allow_free_view', v ) }
								/>
								{ form.allow_free_view && (
									<NumberControl
										label="Limite giornaliero per IP"
										value={ form.free_view_daily_limit }
										min={ 1 }
										onChange={ ( v ) =>
											set( 'free_view_daily_limit', parseInt( v, 10 ) || 10 )
										}
									/>
								) }
							</>
						) }

						{ /* ── Tab: Testi & Email ── */ }
						{ tab.name === 'texts' && (
							<>
								<p className="description" style={ { marginBottom: '1rem' } }>
									{ 'I segnaposto ' }
									<code>{ '{{site_name}}' }</code>
									{ ', ' }
									<code>{ '{{attachment_title}}' }</code>
									{ ', ' }
									<code>{ '{{unlock_code}}' }</code>
									{ ' vengono sostituiti automaticamente nell\'email.' }
								</p>
								<TextControl
									label="Titolo paywall predefinito"
									value={ form.default_paywall_title }
									onChange={ ( v ) => set( 'default_paywall_title', v ) }
									className="wppa-settings-form__wide"
								/>
								<TextareaControl
									label="Testo paywall predefinito"
									value={ form.default_paywall_text }
									onChange={ ( v ) => set( 'default_paywall_text', v ) }
									rows={ 4 }
									help="Testo mostrato agli utenti sotto il titolo del paywall. HTML supportato."
									className="wppa-settings-form__wide"
								/>
								<TextControl
									label="Oggetto email predefinito"
									value={ form.default_email_subject }
									onChange={ ( v ) => set( 'default_email_subject', v ) }
									className="wppa-settings-form__wide"
								/>
								<TextareaControl
									label="Messaggio email aggiuntivo"
									value={ form.default_email_message }
									onChange={ ( v ) => set( 'default_email_message', v ) }
									rows={ 6 }
									help="Testo aggiuntivo nell'email sopra il codice. HTML supportato."
									className="wppa-settings-form__wide"
								/>
							</>
						) }

						{ /* ── Tab: Avanzate ── */ }
						{ tab.name === 'advanced' && (
							<>
								<TextControl
									label="Codice di sblocco master (superadmin)"
									value={ form.master_unlock_code }
									onChange={ ( v ) => set( 'master_unlock_code', v ) }
									help="Se impostato, questo codice sblocca qualsiasi attachment protetto. Lascia vuoto per disabilitare. Formato libero (case-insensitive, trattini ignorati)."
									className="wppa-settings-form__wide"
								/>
								<hr className="wppa-settings-sep" />
								<ToggleControl
									label="Elimina tutti i dati alla disinstallazione"
									help="⚠️ Se attivo, alla disinstallazione del plugin verranno eliminate le tabelle del database e tutte le impostazioni. Irreversibile."
									checked={ form.delete_data_on_uninstall }
									onChange={ ( v ) => set( 'delete_data_on_uninstall', v ) }
								/>
							</>
						) }

					</div>
				) }
			</TabPanel>

			<div className="wppa-settings-form__actions">
				<Button
					variant="primary"
					type="submit"
					isBusy={ isSaving }
					disabled={ isSaving }
				>
					{ isSaving ? 'Salvataggio…' : 'Salva impostazioni' }
				</Button>
			</div>
		</form>
	);
}
