/**
 * Riga nella tabella lista attachment.
 *
 * I pulsanti "Configura" e "Visualizza" sono row-actions WP-style:
 * nascosti di default, visibili all'hover della riga tramite CSS.
 */

export default function AttachmentRow( { item, onEdit } ) {
	return (
		<tr className={ `wppa-attachment-row ${ item.protected ? 'wppa-attachment-row--protected' : '' }` }>

			{ /* Anteprima */ }
			<td className="wppa-attachment-row__thumb">
				{ item.thumbnail ? (
					<img src={ item.thumbnail } alt={ item.title } width={ 50 } height={ 50 } />
				) : (
					<span className="dashicons dashicons-format-image" />
				) }
			</td>

			{ /* Nome file + row-actions */ }
			<td className="wppa-attachment-row__name">
				<strong>
					{ item.protected && (
						<span
							className="dashicons dashicons-lock wppa-lock-icon"
							title="Attachment protetto"
							aria-label="Protetto"
						/>
					) }
					{ item.title || item.filename }
				</strong>
				{ item.filename && item.title !== item.filename && (
					<span className="wppa-attachment-row__filename">{ item.filename }</span>
				) }
				<div className="wppa-row-actions">
					<span>
						<button
							type="button"
							className="button-link"
							onClick={ onEdit }
						>
							{ 'Configura' }
						</button>
					</span>
					{ item.link && (
						<span>
							{ ' | ' }
							<a
								href={ item.link }
								target="_blank"
								rel="noreferrer"
							>
								{ 'Visualizza →' }
							</a>
						</span>
					) }
				</div>
			</td>

			{ /* Stats — solo per protetti */ }
			<td className="wppa-attachment-row__stat" style={ { textAlign: 'center' } }>
				{ item.protected && item.stats ? item.stats.total_views : '—' }
			</td>
			<td className="wppa-attachment-row__stat" style={ { textAlign: 'center' } }>
				{ item.protected && item.stats ? item.stats.donations : '—' }
			</td>
			<td className="wppa-attachment-row__stat" style={ { textAlign: 'center' } }>
				{ item.protected && item.stats ? item.stats.free_views : '—' }
			</td>

		</tr>
	);
}
