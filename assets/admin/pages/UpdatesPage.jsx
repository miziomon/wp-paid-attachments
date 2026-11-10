/**
 * Scheda Aggiornamenti: versione installata, ultima release su GitHub,
 * controllo manuale e installazione.
 */

import { useEffect, useState } from '@wordpress/element';
import { Button, ExternalLink, Notice, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import Section from '../components/Section';

/**
 * Data dell'ultimo controllo, oppure "mai".
 *
 * @param {number} ts Timestamp Unix dell'ultimo controllo (0 se mai fatto).
 * @return {string} Data formattata.
 */
const checkedAt = ( ts ) =>
	ts
		? new Date( ts * 1000 ).toLocaleString( 'it-IT', {
				dateStyle: 'medium',
				timeStyle: 'short',
		  } )
		: 'mai';

export default function UpdatesPage() {
	const [ status, setStatus ] = useState( null );
	const [ busy, setBusy ] = useState( '' ); // 'check' | 'install'
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/wppa/v1/admin/update' } )
			.then( setStatus )
			.catch( ( e ) =>
				setNotice( { status: 'error', text: e.message } )
			);
	}, [] );

	function check() {
		setBusy( 'check' );
		setNotice( null );
		apiFetch( { path: '/wppa/v1/admin/update/check', method: 'POST' } )
			.then( ( data ) => {
				setStatus( data );
				setNotice(
					data.update_available
						? {
								status: 'warning',
								text: `È disponibile la versione ${ data.latest }.`,
						  }
						: {
								status: 'success',
								text: "Il plugin è aggiornato all'ultima versione.",
						  }
				);
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) )
			.finally( () => setBusy( '' ) );
	}

	function install() {
		setBusy( 'install' );
		setNotice( null );
		apiFetch( { path: '/wppa/v1/admin/update/install', method: 'POST' } )
			.then( ( data ) => {
				setNotice( {
					status: 'success',
					text: `Aggiornato alla versione ${ data.version }. Ricarico la pagina…`,
				} );
				// Gli script dell'admin sono cambiati: serve ricaricare per usare la versione nuova.
				setTimeout( () => window.location.reload(), 2000 );
			} )
			.catch( ( e ) => {
				setNotice( { status: 'error', text: e.message } );
				setBusy( '' );
			} );
	}

	if ( ! status && ! notice ) {
		return <Spinner />;
	}

	return (
		<Section
			title="Aggiornamenti"
			description="Il plugin si aggiorna dalle release pubblicate su GitHub. WordPress controlla da solo ogni 12 ore: qui puoi farlo subito."
		>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }

			{ status && (
				<>
					<dl className="wppa-admin__facts">
						<div>
							<dt>{ 'Versione installata' }</dt>
							<dd>{ status.current }</dd>
						</div>
						<div>
							<dt>{ 'Ultima versione' }</dt>
							<dd>
								{ status.latest }
								{ status.update_available && (
									<span className="wppa-admin__badge">
										{ 'Nuova' }
									</span>
								) }
							</dd>
						</div>
						<div>
							<dt>{ 'Ultimo controllo' }</dt>
							<dd>{ checkedAt( status.checked_at ) }</dd>
						</div>
					</dl>

					<div className="wppa-admin__actions">
						<Button
							variant="secondary"
							isBusy={ 'check' === busy }
							disabled={ !! busy }
							onClick={ check }
						>
							{ 'check' === busy
								? 'Controllo in corso…'
								: 'Verifica aggiornamenti' }
						</Button>
						{ status.update_available && (
							<Button
								variant="primary"
								isBusy={ 'install' === busy }
								disabled={ !! busy }
								onClick={ install }
							>
								{ 'install' === busy
									? 'Installazione in corso…'
									: `Installa la versione ${ status.latest }` }
							</Button>
						) }
					</div>

					<p className="wppa-admin__hint">
						<ExternalLink href={ status.releases_url }>
							{ 'Note di versione su GitHub' }
						</ExternalLink>
					</p>
				</>
			) }
		</Section>
	);
}
