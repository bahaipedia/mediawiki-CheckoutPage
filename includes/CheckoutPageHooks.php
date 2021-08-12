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

		if ( !$maxConcurrent || !$checkoutDays || !$accessPage || $maxConcurrent < 1 || $checkoutDays < 1 ) {
			return Xml::tags( 'div', [ 'class' => 'error' ], wfMessage( 'checkoutpage-missing-params' ) );
		}

		// Remember these options in the database.
		$pout = $parser->getOutput();
		$pout->setProperty( 'maxConcurrent', $maxConcurrent );
		$pout->setProperty( 'checkoutDays', $checkoutDays );
		$pout->setProperty( 'accessPage', $accessPage );

		// When successful, {{#checkout:}} will show "N days remaining, return now" link.
		// TODO: this is subject to parser cache and shouldn't be added to HTML directly.
		// It should instead be added by JavaScript, which in turn would perform an API call,
		// which in turn will return the value of getStatusHTML().
		$returnLink = CheckoutPageStatus::getStatusHTML( $parser->getUser(), $parser->getTitle() );
		return [ $returnLink, 'noparse' => true, 'isHTML' => true ];
	}

	/**
	 * {{#checkoutstatus:}} is supposed to be used on "access denied" error pages of AccessControl.
	 * It displays checkout status (whether the page is accessible or not, with "Checkout" button if yes
	 * and with "when it will become accessible?" information if not).
	 *
	 * WARNING: {{#checkoutstatus:}} should NOT be used in the article itself (because the parser cache
	 * will prevent this information from being updated), only on error pages (which are not cached).
	 *
	 * @param Parser $parser
	 * @return array
	 */
	public static function pfStatus( Parser $parser ) {
		$returnLink = CheckoutPageStatus::getStatusHTML( $parser->getUser(), $parser->getTitle() );
		return [ $returnLink, 'noparse' => true, 'isHTML' => true ];
	}
}
