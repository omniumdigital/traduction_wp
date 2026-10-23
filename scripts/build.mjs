// Builds admin/front JS with esbuild. Replaces @wordpress/scripts (its webpack/stylelint
// dependency tree carries unfixable high-severity advisories).
// Each assets/src/<entry>/index.(js|jsx) produces assets/build/<entry>.js and
// <entry>.asset.php (dependencies + version), like @wordpress/dependency-extraction-webpack-plugin.
import { build, context } from 'esbuild';
import { createHash } from 'node:crypto';
import { existsSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const SRC = 'assets/src';
const OUT = 'assets/build';
const watch = process.argv.includes( '--watch' );

const camel = ( s ) => s.replace( /-([a-z])/g, ( _, c ) => c.toUpperCase() );

// Map bare imports to WordPress script globals and record the script handle.
function wpGlobals( handles ) {
	const resolveGlobal = ( path ) => {
		if ( path === 'react' ) return [ 'React', 'react' ];
		if ( path === 'react-dom' ) return [ 'ReactDOM', 'react-dom' ];
		if ( path === 'react/jsx-runtime' ) return [ 'ReactJSXRuntime', 'react-jsx-runtime' ];
		if ( path.startsWith( '@wordpress/' ) ) {
			const pkg = path.slice( '@wordpress/'.length );
			return [ `wp.${ camel( pkg ) }`, `wp-${ pkg }` ];
		}
		return null;
	};
	return {
		name: 'wp-globals',
		setup( b ) {
			b.onResolve( { filter: /^(react|react-dom|react\/jsx-runtime|@wordpress\/[a-z0-9-]+)$/ }, ( args ) => {
				const g = resolveGlobal( args.path );
				if ( ! g ) return null;
				handles.add( g[ 1 ] );
				return { path: g[ 0 ], namespace: 'wp-global' };
			} );
			b.onLoad( { filter: /.*/, namespace: 'wp-global' }, ( args ) => ( {
				contents: `module.exports = window.${ args.path };`,
				loader: 'js',
			} ) );
		},
	};
}

function entries() {
	if ( ! existsSync( SRC ) ) return [];
	return readdirSync( SRC, { withFileTypes: true } )
		.filter( ( d ) => d.isDirectory() )
		.map( ( d ) => ( { name: d.name, file: [ 'index.jsx', 'index.js' ].map( ( f ) => join( SRC, d.name, f ) ).find( existsSync ) } ) )
		.filter( ( e ) => e.file );
}

function writeAssetFile( name, handles ) {
	const js = join( OUT, `${ name }.js` );
	const version = createHash( 'sha256' ).update( readFileSync( js ) ).digest( 'hex' ).slice( 0, 20 );
	const deps = [ ...handles ].sort().map( ( h ) => `'${ h }'` ).join( ', ' );
	writeFileSync( join( OUT, `${ name }.asset.php` ), `<?php return array( 'dependencies' => array( ${ deps } ), 'version' => '${ version }' );\n` );
}

for ( const { name, file } of entries() ) {
	const handles = new Set();
	const options = {
		entryPoints: { [ name ]: file },
		bundle: true,
		outdir: OUT,
		format: 'iife',
		target: 'es2020',
		jsx: 'automatic',
		minify: ! watch,
		sourcemap: watch,
		loader: { '.js': 'jsx' },
		plugins: [ wpGlobals( handles ), { name: 'asset-file', setup: ( b ) => b.onEnd( ( r ) => r.errors.length || writeAssetFile( name, handles ) ) } ],
		logLevel: 'info',
	};
	if ( watch ) {
		await ( await context( options ) ).watch();
	} else {
		await build( options );
	}
}
