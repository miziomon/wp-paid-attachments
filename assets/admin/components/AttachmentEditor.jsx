/**
 * Drawer/modal editor per la configurazione per-attachment.
 */

import { useState, useEffect } from '@wordpress/element';
import {
	Button,
	Modal,
	Panel,
	PanelBody,
	PanelRow,
	SelectControl,
	TextControl,
	ToggleControl,
	Spinner,
	Notice,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const PAYMENT_MODE_OPTIONS = [
	{ label: '— Default globale —', value: '' },
	{ label: 'PayPal Donate', value: 'paypal_donate' },
	{ label: 'PayPal Smart Buttons', value: 'paypal_smart' },
	{ label: 'Entrambe', value: 'both' },
];

export default function AttachmentEditor( { item, onClose, onSaved } ) {
	const [ form, setForm ]       = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ]   = useState( null );

	useEffect( () => {
		apiFetch( { path: `/wppa/v1/admin/attachments/${ item.id }/config` } )
			.then( ( config ) => {
				setForm( {
					enabled:            config.enabled ?? false,
					payment_mode:       config.payment_mode ?? '',
					currency:           config.currency ?? '',
					suggested_amounts:  ( config.suggested_amounts || [] ).join( ', ' ),
					code_validity_days: config.code_validity_days ?? '',
					max_uses:           config.max_uses ?? '',
					allow_free_view:    config.allow_free_view ?? true,
					paywall_text:       config.paywall_text ?? '',
					email_subject:      config.email_subject ?? '',
					email_message:      config.email_message ?? '',
				} );
			} )
			.catch( () => setNotice( { type: 'error', message: 'Errore nel caricamento della configurazione.' } ) );
	}, [ item.id ] );

	function set( key, value ) {
		setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}

	function handleSave() {
		setIsSaving( true );
		setNotice( null );

		const payload = {
			...form,
			suggested_amounts: form.suggested_amounts
				.split( ',' )
				.map( ( n ) => parseInt( n.trim(), 10 ) )
				.filter( ( n ) => ! isNaN( n ) && n > 0 ),
			code_validity_days: form.code_validity_days ? parseInt( form.code_validity_days, 10 ) : null,
			max_uses:           form.max_uses !== '' ? parseInt( form.max_uses, 10 ) : null,
			payment_mode:       form.payment_mode || null,
			currency:           form.currency || null,
		};

		apiFetch( {
			path:   `/wppa/v1/admin/attachments/${ item.id }/config`,
			method: 'POST',
			data:   payload,
		} )
			.then( onSaved )
			.catch( () => setNotice( { type: 'error', message: 'Errore nel salvataggio.' } ) )
			.finally( () => setIsSaving( false ) );
	}

	return (
		<Modal
			title={ `Configura: ${ item.title || item.filename }` }
			onRequestClose={ onClose }
			className="wppa-attachment-editor"
			size="large"
		>
			{ notice && (
				<Notice status={ notice.type } isDismissible onRemove={ () => setNotice( null ) }>
					{ notice.message }
				</Notice>
			) }

			{ ! form ? (
				<Spinner />
			) : (
				<>
					<Panel>
						<PanelBody title="Protezione" initialOpen={ true }>
							<PanelRow>
								<ToggleControl
									label="Abilita protezione su questo attachment"
									checked={ form.enabled }
									onChange={ ( v ) => set( 'enabled', v ) }
								/>
							</PanelRow>
						</PanelBody>

						{ form.enabled && (
							<>
								<PanelBody title="Pagamento" initialOpen={ true }>
									<PanelRow>
										<SelectControl
											label="Modalità di pagamento"
											value={ form.payment_mode }
											options={ PAYMENT_MODE_OPTIONS }
											onChange={ ( v ) => set( 'payment_mode', v ) }
											help="Lascia vuoto per usare il default globale."
										/>
									</PanelRow>
									<PanelRow>
										<TextControl
											label="Valuta (es. EUR)"
											value={ form.currency }
											onChange={ ( v ) => set( 'currency', v ) }
											placeholder="Default globale"
										/>
									</PanelRow>
									<PanelRow>
										<TextControl
											label="Importi suggeriti (separati da virgola)"
											value={ form.suggested_amounts }
											onChange={ ( v ) => set( 'suggested_amounts', v ) }
											placeholder="Default globale (es. 1, 3, 5)"
										/>
									</PanelRow>
								</PanelBody>

								<PanelBody title="Codice di sblocco" initialOpen={ false }>
									<PanelRow>
										<NumberControl
											label="Validità codice (giorni)"
											value={ form.code_validity_days }
											min={ 1 }
											onChange={ ( v ) => set( 'code_validity_days', v ) }
											placeholder="Default globale"
										/>
									</PanelRow>
									<PanelRow>
										<NumberControl
											label="Usi massimi per codice (0 = illimitati)"
											value={ form.max_uses }
											min={ 0 }
											onChange={ ( v ) => set( 'max_uses', v ) }
											placeholder="Default globale"
										/>
									</PanelRow>
									<PanelRow>
										<ToggleControl
											label="Permetti visualizzazione gratuita"
											checked={ form.allow_free_view }
											onChange={ ( v ) => set( 'allow_free_view', v ) }
										/>
									</PanelRow>
								</PanelBody>

								<PanelBody title="Testi personalizzati" initialOpen={ false }>
									<PanelRow>
										<TextControl
											label="Testo paywall"
											value={ form.paywall_text }
											onChange={ ( v ) => set( 'paywall_text', v ) }
											placeholder="Default globale"
											className="wppa-attachment-editor__wide"
										/>
									</PanelRow>
									<PanelRow>
										<TextControl
											label="Oggetto email"
											value={ form.email_subject }
											onChange={ ( v ) => set( 'email_subject', v ) }
											placeholder="Default globale"
											className="wppa-attachment-editor__wide"
										/>
									</PanelRow>
									<PanelRow>
										<TextControl
											label="Messaggio email"
											value={ form.email_message }
											onChange={ ( v ) => set( 'email_message', v ) }
											placeholder="Default globale"
											className="wppa-attachment-editor__wide"
										/>
									</PanelRow>
								</PanelBody>
							</>
						) }
					</Panel>

					<div className="wppa-attachment-editor__actions">
						<Button
							variant="primary"
							onClick={ handleSave }
							isBusy={ isSaving }
							disabled={ isSaving }
						>
							{ isSaving ? 'Salvataggio…' : 'Salva' }
						</Button>
						<Button variant="tertiary" onClick={ onClose }>
							{ 'Annulla' }
						</Button>
					</div>
				</>
			) }
		</Modal>
	);
}
