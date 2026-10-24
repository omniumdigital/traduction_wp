// Compiles languages/*.po into the files WordPress loads at runtime, without gettext or WP-CLI:
// - <locale>.mo for PHP strings (load_plugin_textdomain);
// - <locale>-<md5(assets/build/<entry>.js)>.json (Jed format) for each JS entry, read by
//   wp_set_script_translations(). An entry gets every string whose literal appears in its sources.
import { createHash } from 'node:crypto';
import { existsSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const LANG_DIR = 'languages';
const SRC = 'assets/src';
const DOMAIN = 'omnium-translate';

const unquote = ( s ) => JSON.parse( s.replace( /\t/g, '\\t' ) );

function parsePo( text ) {
	const entries = [];
	let current = null;
	let field = null;
	const flush = () => {
		if ( current && current.msgid !== undefined ) entries.push( current );
		current = null;
	};
	for ( const raw of text.split( '\n' ) ) {
		const line = raw.trim();
		if ( line === '' ) {
			flush();
			continue;
		}
		if ( line.startsWith( '#' ) ) continue;
		const match = line.match( /^(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+\])?)\s+(".*")$/ );
		if ( match ) {
			if ( match[ 1 ] === 'msgctxt' || ( match[ 1 ] === 'msgid' && current?.msgstr !== undefined ) ) flush();
			current = current || {};
			field = match[ 1 ];
			current[ field ] = unquote( match[ 2 ] );
			if ( field.startsWith( 'msgstr' ) ) current.msgstr = current.msgstr ?? '';
		} else if ( line.startsWith( '"' ) && current && field ) {
			current[ field ] += unquote( line );
		}
	}
	flush();
	return entries.map( ( e ) => ( {
		context: e.msgctxt,
		id: e.msgid,
		plural: e.msgid_plural,
		strings: e.msgid_plural
			? Object.keys( e )
					.filter( ( k ) => /^msgstr\[\d+\]$/.test( k ) )
					.sort()
					.map( ( k ) => e[ k ] )
			: [ e.msgstr ?? '' ],
	} ) );
}

function writeMo( entries, file ) {
	const items = entries
		.filter( ( e ) => e.strings.some( ( s ) => s !== '' ) || e.id === '' )
		.map( ( e ) => ( {
			key: ( e.context ? `${ e.context }\u0004` : '' ) + e.id + ( e.plural ? `\u0000${ e.plural }` : '' ),
			value: e.strings.join( '\u0000' ),
		} ) )
		.sort( ( a, b ) => Buffer.compare( Buffer.from( a.key ), Buffer.from( b.key ) ) );
	const count = items.length;
	const keys = items.map( ( i ) => Buffer.from( i.key, 'utf8' ) );
	const values = items.map( ( i ) => Buffer.from( i.value, 'utf8' ) );
	const headerSize = 28;
	const tablesSize = count * 16;
	let offset = headerSize + tablesSize;
	const header = Buffer.alloc( headerSize + tablesSize );
	header.writeUInt32LE( 0x950412de, 0 );
	header.writeUInt32LE( 0, 4 );
	header.writeUInt32LE( count, 8 );
	header.writeUInt32LE( headerSize, 12 );
	header.writeUInt32LE( headerSize + count * 8, 16 );
	header.writeUInt32LE( 0, 20 );
	header.writeUInt32LE( 0, 24 );
	const chunks = [];
	keys.forEach( ( k, i ) => {
		header.writeUInt32LE( k.length, headerSize + i * 8 );
		header.writeUInt32LE( offset, headerSize + i * 8 + 4 );
		chunks.push( k, Buffer.from( [ 0 ] ) );
		offset += k.length + 1;
	} );
	values.forEach( ( v, i ) => {
		header.writeUInt32LE( v.length, headerSize + count * 8 + i * 8 );
		header.writeUInt32LE( offset, headerSize + count * 8 + i * 8 + 4 );
		chunks.push( v, Buffer.from( [ 0 ] ) );
		offset += v.length + 1;
	} );
	writeFileSync( file, Buffer.concat( [ header, ...chunks ] ) );
}

function sourceText( dir ) {
	return readdirSync( dir, { withFileTypes: true, recursive: true } )
		.filter( ( d ) => d.isFile() && /\.(jsx?|tsx?)$/.test( d.name ) )
		.map( ( d ) => readFileSync( join( d.parentPath ?? d.path, d.name ), 'utf8' ) )
		.join( '\n' );
}

function writeJson( entries, locale, entry ) {
	const source = sourceText( join( SRC, entry ) );
	const literal = ( s ) => [ JSON.stringify( s ), `'${ s.replace( /\\/g, '\\\\' ).replace( /'/g, "\\'" ) }'` ];
	const header = entries.find( ( e ) => e.id === '' && ! e.context );
	const pluralForms = header?.strings[ 0 ].match( /Plural-Forms:\s*([^\n]+)/ )?.[ 1 ] ?? 'nplurals=2; plural=(n > 1);';
	const messages = { '': { domain: 'messages', lang: locale, 'plural-forms': pluralForms } };
	for ( const e of entries ) {
		if ( e.id === '' || ! e.strings.some( ( s ) => s !== '' ) ) continue;
		if ( ! literal( e.id ).some( ( l ) => source.includes( l ) ) ) continue;
		messages[ ( e.context ? `${ e.context }\u0004` : '' ) + e.id ] = e.strings;
	}
	if ( Object.keys( messages ).length === 1 ) return;
	const hash = createHash( 'md5' ).update( `assets/build/${ entry }.js` ).digest( 'hex' );
	const json = { domain: 'messages', locale_data: { messages } };
	writeFileSync( join( LANG_DIR, `${ DOMAIN }-${ locale }-${ hash }.json` ), JSON.stringify( json ) );
}

const jsEntries = existsSync( SRC )
	? readdirSync( SRC, { withFileTypes: true } )
			.filter( ( d ) => d.isDirectory() )
			.map( ( d ) => d.name )
	: [];

for ( const file of readdirSync( LANG_DIR ).filter( ( f ) => f.endsWith( '.po' ) ) ) {
	const locale = file.slice( `${ DOMAIN }-`.length, -'.po'.length );
	const entries = parsePo( readFileSync( join( LANG_DIR, file ), 'utf8' ) );
	writeMo( entries, join( LANG_DIR, file.replace( /\.po$/, '.mo' ) ) );
	for ( const entry of jsEntries ) writeJson( entries, locale, entry );
	console.log( `i18n: ${ file } compiled` );
}
