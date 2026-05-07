/**
 * Punto di ingresso admin — monta le 3 pagine del plugin su root DOM dedicati.
 */

import { createRoot } from '@wordpress/element';
import SettingsPage from './pages/SettingsPage';
import AttachmentsPage from './pages/AttachmentsPage';
import StatsPage from './pages/StatsPage';
import './styles/admin.scss';

function mountIfExists( rootId, Component ) {
	const el = document.getElementById( rootId );
	if ( ! el ) return;
	createRoot( el ).render( <Component /> );
}

mountIfExists( 'wppa-admin-settings-root', SettingsPage );
mountIfExists( 'wppa-admin-attachments-root', AttachmentsPage );
mountIfExists( 'wppa-admin-stats-root', StatsPage );
