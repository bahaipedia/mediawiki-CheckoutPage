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
		$parser->setFunctionHook( 'checkout', [ self::class, 'parserFunction' ] );
	}

	/**
	 * For any page with {{#checkout:}} syntax, remember checkout properties (e.g. expiration time)
	 * in the page_props table for this page.
	 */
	public static function parserFunction( Parser $parser, ...$params ) {
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

		// When successful, {{#checkout:}} syntax is invisible.
		return '';
	}
}
