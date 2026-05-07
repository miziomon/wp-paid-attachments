/**
 * Riga nella tabella lista attachment.
 */

import { Button } from '@wordpress/components';

export default function AttachmentRow( { item, onEdit } ) {
	return (
		<tr className={ `wppa-attachment-row ${ item.protected ? 'wppa-attachment-row--protected' : '' }` }>
			<td className="wppa-attachment-row__thumb">
				{ item.thumbnail ? (
					<img src={ item.thumbnail } alt={ item.title } width={ 50 } height={ 50 } />
				) : (
					<span className="dashicons dashicons-format-image" />
				) }
			</td>
			<td className="wppa-attachment-row__name">
				<strong>{ item.title || item.filename }</strong>
				{ item.title && item.filename !== item.title && (
					<br />
				) }
				<span className="wppa-attachment-row__filename">{ item.filename }</span>
			</td>
			<td className="wppa-attachment-row__status">
				{ item.protected ? (
					<span className="wppa-badge wppa-badge--protected">{ 'Protetto' }</span>
				) : (
					<span className="wppa-badge wppa-badge--free">{ 'Non protetto' }</span>
				) }
			</td>
			<td className="wppa-attachment-row__stat" style={ { textAlign: 'center' } }>
				{ item.protected && item.stats ? item.stats.total_views : '—' }
			</td>
			<td className="wppa-attachment-row__stat" style={ { textAlign: 'center' } }>
				{ item.protected && item.stats ? item.stats.donations : '—' }
			</td>
			<td className="wppa-attachment-row__stat" style={ { textAlign: 'center' } }>
				{ item.protected && item.stats ? item.stats.free_views : '—' }
			</td>
			<td className="wppa-attachment-row__date">
				{ new Date( item.date ).toLocaleDateString( 'it-IT' ) }
			</td>
			<td className="wppa-attachment-row__actions">
				<Button variant="secondary" size="small" onClick={ onEdit }>
					{ 'Configura' }
				</Button>
				{ item.link && (
					<a
						href={ item.link }
						target="_blank"
						rel="noreferrer"
						className="button button-small"
						style={ { marginLeft: '6px' } }
					>
						{ 'Visualizza →' }
					</a>
				) }
			</td>
		</tr>
	);
}
