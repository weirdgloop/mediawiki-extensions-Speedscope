/* eslint-disable no-jquery/no-global-selector */
$( () => {
	$( '#wpPreview, #wpDiff' ).on( 'click', () => {
		$( '.mw-speedscope-profile-notice' ).remove();
	} );
	$( '#wpProfilePreview' ).on( 'click', ( event ) => {
		$( '.mw-speedscope-profile-notice' ).remove();

		const promise = require( 'mediawiki.page.preview' ).doPreview( {
			isLivePreview: true,
			previewHeader: mw.message( 'preview' ).escaped(),
			previewNote: mw.message( 'previewnote' ).parseDom(),
			createSpinner: true,
			parseParams: {
				wpProfilePreview: 1
			},
			responseHandler: ( config, response ) => {
				const profileUrl = response.parse.jsconfigvars.speedscopeProfileUrl;
				if ( !profileUrl ) {
					mw.log.error( 'Preview response did not provide the profile URL!' );
					return;
				}
				// Wrap the parsed message, as mw.util.messageBox needs a Node
				const $contents = $( '<div>' ).append( mw.message(
					'speedscope-editpage-profile-notice',
					$( '<a>' )
						.attr( 'href', profileUrl )
						.attr( 'target', '_blank' )
						.text( mw.msg( 'speedscope-editpage-profile-link-label' ) )
				).parseDom() );
				const $messageBox = $( mw.util.messageBox( $contents[ 0 ] ) )
					.addClass( 'mw-speedscope-profile-notice' );
				$( '#wikiPreview' ).prepend( $messageBox );
			}
		} );

		if ( !promise ) {
			return;
		}

		event.preventDefault();
	} );
} );
