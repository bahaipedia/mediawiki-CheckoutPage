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
		$this->requireLogin();

		parent::execute( $targetPageName );
		if ( !$targetPageName ) {
			// Called as [[Special:CheckoutPage]] without parameter.
			throw new ErrorPageError( 'notargettitle', 'notargettext' );
		}

		$title = Title::newFromText( $targetPageName );
		$status = CheckoutPage::grantAccess( $this->getUser(), $title );
		if ( !$status->isOK() ) {
			$this->getOutput()->addHTML( Xml::tags( 'div', [ 'class' => 'error' ], $status->getHTML() ) );
			return;
		}

		// If checkout was successful, return the user back to the article (it should now be readable).
		$this->getOutput()->redirect( $title->getFullURL() );
	}
}

