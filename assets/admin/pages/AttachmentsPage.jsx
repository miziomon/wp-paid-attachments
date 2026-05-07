/**
 * Pagina lista attachment protetti con editor per-attachment.
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Spinner, Notice, SearchControl, SelectControl, Flex, FlexItem } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import AttachmentRow from '../components/AttachmentRow';
import AttachmentEditor from '../components/AttachmentEditor';

const FILTER_OPTIONS = [
	{ label: 'Tutti', value: 'all' },
	{ label: 'Solo protetti', value: 'protected' },
	{ label: 'Solo non protetti', value: 'unprotected' },
];

export default function AttachmentsPage() {
	const [ items, setItems ]             = useState( [] );
	const [ isLoading, setIsLoading ]     = useState( true );
	const [ notice, setNotice ]           = useState( null );
	const [ search, setSearch ]           = useState( '' );
	const [ filter, setFilter ]           = useState( 'all' );
	const [ page, setPage ]               = useState( 1 );
	const [ totalPages, setTotalPages ]   = useState( 1 );
	const [ editing, setEditing ]         = useState( null );

	const load = useCallback( () => {
		setIsLoading( true );
		apiFetch( {
			path: `/wppa/v1/admin/attachments?per_page=20&page=${ page }&search=${ encodeURIComponent( search ) }&filter=${ filter }`,
			parse: false,
		} )
			.then( async ( response ) => {
				const data = await response.json();
				setTotalPages( parseInt( response.headers.get( 'X-WP-TotalPages' ) || '1', 10 ) );
				setItems( data );
			} )
			.catch( () =>
				setNotice( { type: 'error', message: 'Errore nel caricamento degli attachment.' } )
			)
			.finally( () => setIsLoading( false ) );
	}, [ page, search, filter ] );

	useEffect( () => {
		const timer = setTimeout( load, search ? 400 : 0 );
		return () => clearTimeout( timer );
	}, [ load, search ] );

	function handleSaved( updatedConfig ) {
		setItems( ( prev ) =>
			prev.map( ( item ) =>
				item.id === updatedConfig.attachment_id
					? { ...item, protected: updatedConfig.enabled, config: updatedConfig }
					: item
			)
		);
		setEditing( null );
		setNotice( { type: 'success', message: 'Configurazione salvata.' } );
	}

	return (
		<div className="wppa-attachments-page">
			<h1 className="wp-heading-inline">{ 'Attachment protetti' }</h1>

			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<Flex className="wppa-attachments-page__toolbar" align="center">
				<FlexItem>
					<SearchControl
						value={ search }
						onChange={ ( v ) => { setSearch( v ); setPage( 1 ); } }
						placeholder="Cerca per nome file…"
					/>
				</FlexItem>
				<FlexItem>
					<SelectControl
						value={ filter }
						options={ FILTER_OPTIONS }
						onChange={ ( v ) => { setFilter( v ); setPage( 1 ); } }
					/>
				</FlexItem>
			</Flex>

			{ isLoading ? (
				<Spinner />
			) : items.length === 0 ? (
				<p>{ 'Nessun attachment trovato.' }</p>
			) : (
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>{ 'Anteprima' }</th>
							<th>{ 'Nome file' }</th>
							<th>{ 'Protezione' }</th>
							<th style={ { textAlign: 'center' } }>{ 'Views' }</th>
							<th style={ { textAlign: 'center' } }>{ 'Donazioni' }</th>
							<th style={ { textAlign: 'center' } }>{ 'Free views' }</th>
							<th>{ 'Data' }</th>
							<th>{ 'Azioni' }</th>
						</tr>
					</thead>
					<tbody>
						{ items.map( ( item ) => (
							<AttachmentRow
								key={ item.id }
								item={ item }
								onEdit={ () => setEditing( item ) }
							/>
						) ) }
					</tbody>
				</table>
			) }

			{ totalPages > 1 && (
				<Flex className="wppa-attachments-page__pagination" justify="center">
					<FlexItem>
						<button
							className="button"
							disabled={ page <= 1 }
							onClick={ () => setPage( ( p ) => p - 1 ) }
						>
							{ '‹ Precedente' }
						</button>
					</FlexItem>
					<FlexItem>
						{ `Pagina ${ page } di ${ totalPages }` }
					</FlexItem>
					<FlexItem>
						<button
							className="button"
							disabled={ page >= totalPages }
							onClick={ () => setPage( ( p ) => p + 1 ) }
						>
							{ 'Successiva ›' }
						</button>
					</FlexItem>
				</Flex>
			) }

			{ editing && (
				<AttachmentEditor
					item={ editing }
					onClose={ () => setEditing( null ) }
					onSaved={ handleSaved }
				/>
			) }
		</div>
	);
}
