/**
 * Pagina impostazioni globali del plugin.
 */

import { useState, useEffect } from '@wordpress/element';
import { Spinner, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import SettingsForm from '../components/SettingsForm';

export default function SettingsPage() {
	const [ settings, setSettings ] = useState( null );
	const [ isSaving, setIsSaving ]   = useState( false );
	const [ notice, setNotice ]        = useState( null );

	useEffect( () => {
		apiFetch( { path: '/wppa/v1/admin/settings' } )
			.then( setSettings )
			.catch( () =>
				setNotice( { type: 'error', message: 'Errore nel caricamento delle impostazioni.' } )
			);
	}, [] );

	function handleSave( updated ) {
		setIsSaving( true );
		setNotice( null );

		apiFetch( {
			path: '/wppa/v1/admin/settings',
			method: 'POST',
			data: updated,
		} )
			.then( ( saved ) => {
				setSettings( saved );
				setNotice( { type: 'success', message: 'Impostazioni salvate.' } );
			} )
			.catch( () =>
				setNotice( { type: 'error', message: 'Errore nel salvataggio delle impostazioni.' } )
			)
			.finally( () => setIsSaving( false ) );
	}

	return (
		<div className="wppa-settings-page">
			<h1 className="wp-heading-inline">{ 'WP Paid Attachments — Impostazioni' }</h1>

			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ ! settings ? (
				<Spinner />
			) : (
				<SettingsForm
					settings={ settings }
					onSave={ handleSave }
					isSaving={ isSaving }
				/>
			) }
		</div>
	);
}
