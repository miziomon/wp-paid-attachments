/**
 * Card bianca con titolo e testo di aiuto: unità base del layout dell'admin.
 *
 * @param {Object}  props
 * @param {string}  props.title       Titolo della card.
 * @param {string}  props.description Testo di aiuto sotto il titolo (opzionale).
 * @param {Element} props.children    Contenuto.
 */
export default function Section( { title, description, children } ) {
	return (
		<section className="wppa-admin__card">
			{ title && (
				<h2
					className={
						'wppa-admin__card-title' +
						( description ? '' : ' is-bare' )
					}
				>
					{ title }
				</h2>
			) }
			{ description && (
				<p className="wppa-admin__card-desc">{ description }</p>
			) }
			{ children }
		</section>
	);
}
