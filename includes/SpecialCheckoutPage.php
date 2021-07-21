<?php

/*
 * Extension:CheckoutPage - MediaWiki extension.
 *
 * TBD
 */

/**
 * @file
 * Implements [[Special:CheckoutPage]].
 *
 * Allows a reader to "check out" the page (get temporary access to it)
 * when this page is restricted to only be readable by N people at the same time.
 */

class SpecialCheckoutPage extends UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'CheckoutPage' );
	}

	/**
	 * Check out the page NameOfArticle when visiting Special:CheckoutPage/NameOfArticle.
	 * @param string $param
	 */
	public function execute( $targetPageName ) {
		parent::execute( $targetPageName );
		if ( !$targetPageName ) {
			// Called as [[Special:CheckoutPage]] without parameter.
			throw new ErrorPageError( 'notargettitle', 'notargettext' );
		}

		$title = Title::newFromText( $targetPageName );
		$pageId = $title->getArticleID( Title::READ_LATEST );
		if ( !$pageId ) {
			// Page doesn't exist.
			throw new ErrorPageError( 'nopagetitle', 'nopagetext' );
		}

		// Determine options like "checkoutDays" that are set by {{#checkout:}} syntax on that page.
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'page_props',
			[ 'pp_propname AS name', 'pp_value AS value' ],
			[
				'pp_page' => $pageId,
				'pp_propname' => [ 'maxConcurrent', 'checkoutDays', 'accessPage' ]
			],
			__METHOD__
		);
		if ( $res->numRows() !== 3 ) {
			throw new ErrorPageError( 'checkoutpage-error', 'checkoutpage-not-enabled-for-page' );
		}

		$options = [];
		foreach ( $res as $row ) {
			$options[$row->name] = $row->value;
		}

		// TODO: add username to the page $options['accessPage'],
		// but only if it has less than $options['maxConcurrent'] names already,
		// and remember the time when checkout should be revoked (NOW timestamp + $options['checkoutDays']).

		$this->getOutput()->addHTML( 'TODO' );
	}
}

