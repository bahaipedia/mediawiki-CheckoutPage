<?php

/*
 * Extension:CheckoutPage - MediaWiki extension.
 *
 * TBD
 */

/**
 * @file
 * Hooks of Extension:CheckoutPage.
 */

class CheckoutPageHooks {
	/**
	 * Register {{#checkout:}} syntax.
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'checkout', [ self::class, 'pfCheckout' ] );
		$parser->setFunctionHook( 'checkoutstatus', [ self::class, 'pfStatus' ] );
	}

	/**
	 * For any page with {{#checkout:}} syntax, remember checkout properties (e.g. expiration time)
	 * in the page_props table for this page.
	 * @param Parser $parser
	 * @param mixed ...$params
	 * @return array
	 */
	public static function pfCheckout( Parser $parser, ...$params ) {
		// Interpret options like checkout_days=5
		$maxConcurrent = 1;
		$checkoutDays = 0;
		$accessPage = '';

		$options = [];
		foreach ( $params as $keyval ) {
			[ $key, $val ] = array_map( 'trim', explode( '=', $keyval . '=' ) );
			if ( $val ) {
				$options[$key] = $val;
			}
		}

		$maxConcurrent = (int)( $options['max_concurrent_users'] ?? 0 );
		$checkoutDays = (int)( $options['checkout_days'] ?? 0 );
		$accessPage = trim( $options['access_page'] ?? '' );
		$allowedUsersPage = trim( $options['allowed_users'] ?? '' );

		if ( !$maxConcurrent || !$checkoutDays || !$accessPage || $maxConcurrent < 1 || $checkoutDays < 1 ) {
			return Xml::tags( 'div', [ 'class' => 'error' ], wfMessage( 'checkoutpage-missing-params' ) );
		}

		// Remember these options in the database.
		$pout = $parser->getOutput();
		$pout->setProperty( 'maxConcurrent', $maxConcurrent );
		$pout->setProperty( 'checkoutDays', $checkoutDays );
		$pout->setProperty( 'accessPage', $accessPage );
		$pout->setProperty( 'allowedUsersPage', $allowedUsersPage );

		// Re-apply page properties "checkoutExpiry.<username>",
		// so that they are not deleted when the page is edited.
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select( 'page_props',
			[
				'pp_propname AS prop',
				'pp_value AS value'
			],
			[
				'pp_page' => $parser->getTitle()->getArticleID( Title::READ_LATEST ),
				'pp_propname ' . $dbw->buildLike( 'checkoutExpiry.', $dbw->anyString() )
			],
			__METHOD__
		);
		foreach ( $res as $row ) {
			$pout->setProperty( $row->prop, $row->value );
		}

		// When successful, {{#checkout:}} will show "N days remaining, return now" link.
		return self::addStatusToParserOutput( $pout );
	}

	/**
	 * {{#checkoutstatus:}} is supposed to be used on "access denied" error pages of AccessControl.
	 * It displays checkout status (whether the page is accessible or not, with "Checkout" button if yes
	 * and with "when it will become accessible?" information if not).
	 *
	 * @param Parser $parser
	 * @return array
	 */
	public static function pfStatus( Parser $parser ) {
		return self::addStatusToParserOutput( $parser->getOutput() );
	}

	/**
	 * Add "checkout status" box to the page. Used by both {{#checkout:}} and {{#checkoutstatus:}}.
	 *
	 * @param ParserOutput $pout
	 * @return array This value should be returned by the parser function that called this method.
	 */
	protected static function addStatusToParserOutput( ParserOutput $pout ) {
		$pout->addModules( 'ext.checkoutpage.status' );
		$pout->addModuleStyles( 'ext.checkoutpage.status.css' );

		$html = Xml::tags( 'div', [ 'class' => 'checkoutpage-status' ], '' );
		return [ $html, 'noparse' => true, 'isHTML' => true ];
	}
}
