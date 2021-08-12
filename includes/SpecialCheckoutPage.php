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

		// We are not checking for an anti-CSRF token, because the action of checkout/return is not
		// important enough for CSRF-caused checkout/return of the book to have any negative impact.
		// Being able to link to [[Special:CheckoutPage/{{PAGENAME}}]], on the other hand, is quite convenient.

		$out = $this->getOutput();
		$user = $this->getUser();
		$title = Title::newFromText( $targetPageName );

		if ( $this->getRequest()->getBool( 'return' ) ) {
			// User wants to return the book.
			CheckoutPage::revokeAccess( $user, $title );

			$out->addHTML( $this->msg( 'checkoutpage-returned' )->escaped() );
			$out->returnToMain();
			return;
		}

		// User wants to checkout the book.
		$status = CheckoutPage::grantAccess( $user, $title );
		if ( !$status->isOK() ) {
			$out->addHTML( Xml::tags( 'div', [ 'class' => 'error' ], $status->getHTML() ) );
			return;
		}

		// If checkout was successful, return the user back to the article (it should now be readable).
		$out->redirect( $title->getFullURL() );
	}
}

