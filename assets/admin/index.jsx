/**
 * Punto di ingresso admin: un'unica pagina con le schede del plugin.
 */

import { createRoot, useEffect, useState } from '@wordpress/element';
import { Notice, Spinner, TabPanel } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import AttachmentsPage from './pages/AttachmentsPage';
import StatsPage from './pages/StatsPage';
import UpdatesPage from './pages/UpdatesPage';
import SettingsForm from './components/SettingsForm';
import './styles/admin.scss';

const TABS = [
	{ name: 'attachments', title: 'Attachment' },
	{ name: 'stats', title: 'Statistiche' },
	{ name: 'payments', title: 'Pagamenti' },
	{ name: 'paywall', title: 'Paywall ed email' },
	{ name: 'advanced', title: 'Avanzate' },
	{ name: 'updates', title: 'Aggiornamenti' },
];

// Sezioni di impostazioni mostrate in ciascuna scheda.
const SETTINGS_SECTIONS = {
	payments: [ 'paypal', 'defaults' ],
	paywall: [ 'freeview', 'texts' ],
	advanced: [ 'advanced' ],
};

function App() {
	// Scheda iniziale da ?tab=... (es. il link "Impostazioni" nell'elenco plugin).
	const [ initialTab ] = useState( () => {
		const requested = new URLSearchParams( window.location.search ).get(
			'tab'
		);
		return TABS.some( ( tab ) => tab.name === requested )
			? requested
			: 'attachments';
	} );

	// Impostazioni caricate una volta sola e condivise dalle schede che le modificano.
	const [ form, setForm ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/wppa/v1/admin/settings' } )
			.then( setForm )
			.catch( () =>
				setNotice( {
					type: 'error',
					message: 'Errore nel caricamento delle impostazioni.',
				} )
			);
	}, [] );

	function set( key, value ) {
		setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
	}

	function save() {
		setIsSaving( true );
		setNotice( null );
		apiFetch( {
			path: '/wppa/v1/admin/settings',
			method: 'POST',
			data: form,
		} )
			.then( ( saved ) => {
				setForm( saved );
				setNotice( {
					type: 'success',
					message: 'Impostazioni salvate.',
				} );
			} )
			.catch( () =>
				setNotice( {
					type: 'error',
					message: 'Errore nel salvataggio delle impostazioni.',
				} )
			)
			.finally( () => setIsSaving( false ) );
	}

	function renderSettings( sections ) {
		return (
			<>
				{ notice && (
					<Notice
						status={ notice.type }
						isDismissible
						onRemove={ () => setNotice( null ) }
					>
						{ notice.message }
					</Notice>
				) }
				{ ! form ? (
					<Spinner />
				) : (
					<SettingsForm
						form={ form }
						set={ set }
						sections={ sections }
						onSave={ save }
						isSaving={ isSaving }
					/>
				) }
			</>
		);
	}

	return (
		<div className="wppa-admin">
			<header className="wppa-admin__header">
				<h1>{ 'Paid Attachments' }</h1>
				<p>
					{
						'Paywall sulle pagine attachment: immagini protette, pagamenti PayPal e codici di sblocco.'
					}
				</p>
			</header>
			<TabPanel
				tabs={ TABS }
				initialTabName={ initialTab }
				onSelect={ () => setNotice( null ) }
			>
				{ ( tab ) => (
					<div className="wppa-admin__panel">
						{ 'attachments' === tab.name && <AttachmentsPage /> }
						{ 'stats' === tab.name && <StatsPage /> }
						{ 'updates' === tab.name && <UpdatesPage /> }
						{ SETTINGS_SECTIONS[ tab.name ] &&
							renderSettings( SETTINGS_SECTIONS[ tab.name ] ) }
					</div>
				) }
			</TabPanel>
		</div>
	);
}

const container = document.getElementById( 'wppa-admin-root' );
if ( container ) {
	createRoot( container ).render( <App /> );
}
