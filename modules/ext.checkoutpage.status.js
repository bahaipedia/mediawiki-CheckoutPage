/*
	Show checkout status (available, not yet available, can return) on the restricted articles.
*/

( function () {
	'use strict';

	var $target = $( '.checkoutpage-status' );

	if ( !$target.length ) {
		// This page has neither {{#checkout:}} nor {{#checkoutstatus:}} on it.
		return;
	}

	var api = new mw.Api();
	api.get( {
		action: 'query',
		prop: 'checkoutstatus',
		cstitle: mw.config.get( 'wgPageName' )
	} ).done( function ( ret ) {
		$target.html( ret.query.checkoutstatus.html );
	} );
} )();
