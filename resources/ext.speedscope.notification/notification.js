$( () => {
	const profileId = mw.config.get( 'speedscopeProfileId' );
	const endpoint = mw.config.get( 'speedscopeEndpoint' );

	const $speedscopeLink = $( '<a>' )
		.attr( 'href', `${ endpoint }/view/${ profileId }` )
		.attr( 'target', '_blank' )
		.text( mw.msg( 'speedscope-notification-link-view' ) );
	const $jsonLink = $( '<a>' )
		.attr( 'href', `${ endpoint }/profile/${ profileId }` )
		.attr( 'target', '_blank' )
		.text( mw.msg( 'speedscope-notification-link-json' ) );
	const $metadataLink = $( '<a>' )
		.attr( 'href', `${ endpoint }/metadata/${ profileId }` )
		.attr( 'target', '_blank' )
		.text( mw.msg( 'speedscope-notification-link-metadata' ) );

	mw.notify(
		$( '<div>' ).append(
			$speedscopeLink,
			mw.message( 'word-separator' ).parseDom(),
			mw.message( 'parentheses' ).params(
				$jsonLink,
				mw.message( 'comma-separator' ).parseDom(),
				$metadataLink
			).parseDom()
		),
		{
			autoHide: false,
			title: mw.msg( 'speedscope-notification-success' ),
			type: 'success'
		}
	);
} );
