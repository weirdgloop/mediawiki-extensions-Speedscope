$( () => {
	// eslint-disable-next-line no-jquery/no-global-selector
	$( '#wpPreview, #wpDiff' ).click( () => {
		$( '.mw-speedscope-profile-notice' ).remove();
	} );
} );
