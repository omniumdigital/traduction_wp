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

document.addEventListener('click', (event) => {
	const link = event.target.closest('a[data-omtr-pref]');
	if (!link) return;
	const language = link.dataset.omtrPref;
	if (!/^[a-z]{2,3}$/.test(language)) return;
	// Cookie Store is unavailable in older browsers supported by WordPress.
	// biome-ignore lint/suspicious/noDocumentCookie: Functional preference, explicitly enabled by the site owner.
	document.cookie = `omtr_lang=${language}; Path=/; Max-Age=31536000; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
});
