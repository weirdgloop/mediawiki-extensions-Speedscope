/* eslint-disable no-jquery/no-global-selector */
$( () => {
	$( '#wpPreview, #wpDiff' ).on( 'click', () => {
		$( '.mw-speedscope-profile-notice' ).remove();
	} );
} );
