/**
 * Pagina statistiche — KPI, grafico donazioni giornaliere, top 10 attachment.
 */

import { useState, useEffect } from '@wordpress/element';
import { Spinner, SelectControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

/* ── KPI Card ──────────────────────────────────────── */

function KpiCard( { label, value, sub } ) {
	return (
		<div className="wppa-kpi-card">
			<span className="wppa-kpi-card__value">{ value }</span>
			<span className="wppa-kpi-card__label">{ label }</span>
			{ sub && <span className="wppa-kpi-card__sub">{ sub }</span> }
		</div>
	);
}

/* ── Bar Chart (SVG) ───────────────────────────────── */

function BarChart( { series } ) {
	if ( ! series || series.length === 0 ) {
		return <p className="wppa-no-data">{ 'Nessun dato nel periodo selezionato.' }</p>;
	}

	const W = 560;
	const H = 180;
	const PADDING = { top: 16, right: 8, bottom: 40, left: 40 };
	const innerW = W - PADDING.left - PADDING.right;
	const innerH = H - PADDING.top - PADDING.bottom;

	const maxDonations = Math.max( ...series.map( ( d ) => d.donations ), 1 );
	const barW = Math.max( 4, Math.floor( innerW / series.length ) - 2 );

	return (
		<svg
			viewBox={ `0 0 ${ W } ${ H }` }
			className="wppa-bar-chart"
			aria-label="Donazioni giornaliere"
			role="img"
		>
			{ /* Griglia orizzontale */ }
			{ [ 0, 0.25, 0.5, 0.75, 1 ].map( ( t ) => {
				const y = PADDING.top + innerH * ( 1 - t );
				return (
					<g key={ t }>
						<line
							x1={ PADDING.left }
							y1={ y }
							x2={ W - PADDING.right }
							y2={ y }
							stroke="#e2e4e7"
							strokeWidth="1"
						/>
						<text
							x={ PADDING.left - 4 }
							y={ y + 4 }
							textAnchor="end"
							fontSize="10"
							fill="#646970"
						>
							{ Math.round( maxDonations * t ) }
						</text>
					</g>
				);
			} ) }

			{ /* Barre */ }
			{ series.map( ( d, i ) => {
				const barH = Math.max( 2, ( d.donations / maxDonations ) * innerH );
				const x = PADDING.left + ( innerW / series.length ) * i + 1;
				const y = PADDING.top + innerH - barH;
				const dateLabel = d.date ? d.date.slice( 5 ) : ''; // MM-DD
				return (
					<g key={ d.date }>
						<rect
							x={ x }
							y={ y }
							width={ barW }
							height={ barH }
							fill="#2271b1"
							rx="2"
						>
							<title>{ `${ d.date }: ${ d.donations } donazioni, €${ d.revenue }` }</title>
						</rect>
						{ series.length <= 31 && (
							<text
								x={ x + barW / 2 }
								y={ H - PADDING.bottom + 12 }
								textAnchor="middle"
								fontSize="9"
								fill="#646970"
								transform={ `rotate(-45, ${ x + barW / 2 }, ${ H - PADDING.bottom + 12 })` }
							>
								{ dateLabel }
							</text>
						) }
					</g>
				);
			} ) }
		</svg>
	);
}

/* ── Top 10 Table ──────────────────────────────────── */

function TopTable( { items } ) {
	if ( ! items || items.length === 0 ) {
		return <p className="wppa-no-data">{ 'Nessun dato nel periodo selezionato.' }</p>;
	}

	return (
		<table className="wp-list-table widefat fixed striped wppa-top-table">
			<thead>
				<tr>
					<th>{ 'Attachment' }</th>
					<th style={ { textAlign: 'right', width: '100px' } }>{ 'Donazioni' }</th>
					<th style={ { textAlign: 'right', width: '100px' } }>{ 'Revenue' }</th>
				</tr>
			</thead>
			<tbody>
				{ items.map( ( item ) => (
					<tr key={ item.attachment_id }>
						<td>{ item.title }</td>
						<td style={ { textAlign: 'right' } }>{ item.donations }</td>
						<td style={ { textAlign: 'right' } }>{ `€${ item.revenue.toFixed( 2 ) }` }</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}

/* ── StatsPage ─────────────────────────────────────── */

export default function StatsPage() {
	const [ range, setRange ]   = useState( 30 );
	const [ data, setData ]     = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ]   = useState( null );

	useEffect( () => {
		setLoading( true );
		setError( null );
		apiFetch( { path: `/wppa/v1/admin/stats?range=${ range }` } )
			.then( setData )
			.catch( () => setError( 'Errore nel caricamento delle statistiche.' ) )
			.finally( () => setLoading( false ) );
	}, [ range ] );

	const ov = data?.overview ?? {};

	return (
		<div className="wppa-stats-page wrap">
			<div className="wppa-stats-header">
				<h1 className="wp-heading-inline">{ 'Statistiche' }</h1>
				<SelectControl
					value={ String( range ) }
					options={ [
						{ label: 'Ultimi 7 giorni', value: '7' },
						{ label: 'Ultimi 30 giorni', value: '30' },
						{ label: 'Ultimi 90 giorni', value: '90' },
					] }
					onChange={ ( v ) => setRange( parseInt( v, 10 ) ) }
					style={ { marginLeft: '1rem' } }
				/>
			</div>

			{ error && <div className="notice notice-error"><p>{ error }</p></div> }

			{ loading ? (
				<Spinner />
			) : (
				<>
					{ /* KPI */ }
					<div className="wppa-kpi-grid">
						<KpiCard
							label="Donazioni"
							value={ ov.total_donations ?? 0 }
						/>
						<KpiCard
							label="Revenue totale"
							value={ `€${ ( ov.total_revenue ?? 0 ).toFixed( 2 ) }` }
							sub={ `Media €${ ( ov.avg_amount ?? 0 ).toFixed( 2 ) }` }
						/>
						<KpiCard
							label="Donatori unici"
							value={ ov.unique_donors ?? 0 }
						/>
						<KpiCard
							label="Visualizzazioni gratuite"
							value={ ov.free_views ?? 0 }
							sub={ `Conversion rate ${ ov.conversion_rate ?? 0 }%` }
						/>
					</div>

					{ /* Grafico donazioni */ }
					<div className="wppa-stats-section">
						<h2>{ 'Donazioni giornaliere' }</h2>
						<BarChart series={ data?.series ?? [] } />
					</div>

					{ /* Top 10 */ }
					<div className="wppa-stats-section">
						<h2>{ 'Top attachment per revenue' }</h2>
						<TopTable items={ data?.top_attachments ?? [] } />
					</div>
				</>
			) }
		</div>
	);
}
