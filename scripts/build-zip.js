/**
 * Crea un archivio ZIP distribuibile del plugin.
 *
 * Uso: node scripts/build-zip.js
 * Output: zip/wp-paid-attachments-{version}.zip
 *
 * Da eseguire DOPO `npm run build` e `composer install --no-dev`.
 */

const archiver = require( 'archiver' );
const fs       = require( 'fs' );
const path     = require( 'path' );

const ROOT    = path.resolve( __dirname, '..' );
const pkg     = require( path.join( ROOT, 'package.json' ) );
const VERSION = pkg.version;
const SLUG    = 'wp-paid-attachments';
const ZIP_DIR = path.join( ROOT, 'zip' );
const ZIP_OUT = path.join( ZIP_DIR, `${ SLUG }-${ VERSION }.zip` );

// File singoli da includere nella root del plugin.
const FILES = [
	'wp-paid-attachments.php',
	'uninstall.php',
	'index.php',
];

// Cartelle da includere (ricorsive).
const DIRS = [
	'src',
	'build',
	'vendor',
	'languages',
	'templates',
];

if ( ! fs.existsSync( ZIP_DIR ) ) {
	fs.mkdirSync( ZIP_DIR, { recursive: true } );
}

const output  = fs.createWriteStream( ZIP_OUT );
const archive = archiver( 'zip', { zlib: { level: 9 } } );

output.on( 'close', () => {
	const kb = Math.round( archive.pointer() / 1024 );
	console.log( `\nZIP creato: zip/${ SLUG }-${ VERSION }.zip (${ kb } KB)\n` );
} );

archive.on( 'warning', ( err ) => {
	if ( err.code === 'ENOENT' ) {
		console.warn( 'Attenzione:', err.message );
	} else {
		throw err;
	}
} );

archive.on( 'error', ( err ) => { throw err; } );
archive.pipe( output );

FILES.forEach( ( file ) => {
	const full = path.join( ROOT, file );
	if ( fs.existsSync( full ) ) {
		archive.file( full, { name: `${ SLUG }/${ file }` } );
	} else {
		console.warn( `File non trovato, saltato: ${ file }` );
	}
} );

DIRS.forEach( ( dir ) => {
	const full = path.join( ROOT, dir );
	if ( fs.existsSync( full ) ) {
		archive.directory( full, `${ SLUG }/${ dir }` );
	} else {
		console.warn( `Cartella non trovata, saltata: ${ dir }` );
	}
} );

archive.finalize();
