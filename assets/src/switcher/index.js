document.querySelectorAll( '[data-omtr-switcher]' ).forEach( ( switcher ) => {
	const trigger = switcher.querySelector( 'summary' );
	const sync = () => trigger?.setAttribute( 'aria-expanded', switcher.open ? 'true' : 'false' );
	switcher.addEventListener( 'toggle', sync );
	switcher.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' && switcher.open ) {
			switcher.open = false;
			trigger?.focus();
		}
	} );
	document.addEventListener( 'click', ( event ) => {
		if ( switcher.open && ! switcher.contains( event.target ) ) switcher.open = false;
	} );
	sync();
} );
