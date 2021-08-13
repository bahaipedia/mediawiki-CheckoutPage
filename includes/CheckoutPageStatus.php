<?php

/*
 * Extension:CheckoutPage - MediaWiki extension.
 *
 * TBD
 */

/**
 * @file
 * Methods to display current status of the page (available or not) and action buttons for it.
 */

class CheckoutPageStatus {
	/**
	 * Returns text that informs whether the page $title is available for checkout, not yet available
	 * or is already checked out, and suggests possible action (checkout/return) or time of availability.
	 * @param User $user
	 * @param Title $title
	 * @return string
	 */
	public static function getStatusHTML( User $user, Title $title ) {
		$checkoutUrl = SpecialPage::getTitleFor( 'CheckoutPage', $title->getPrefixedDBKey() )->getFullURL();

		// Not DB_REPLICA, because this message is displayed immediately after checkout,
		// so its correctness must not be affected by database lag.
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select( 'page_props',
			[
				'pp_propname AS prop',
				'pp_value AS value',
			],
			[
				'pp_page' => $title->getArticleID( Title::READ_LATEST )
			],
			__METHOD__
		);
		if ( $res->numRows() === 0 ) {
			// This page can't be checked out (doesn't have {{#checkout:}} on it).
			return '';
		}

		// Determine the current status from page_props of the restricted article.
		$maxConcurrent = 1;
		$usernameToExpiry = [];
		$earliestExpiry = 0;

		foreach ( $res as $row ) {
			if ( $row->prop === 'maxConcurrent' ) {
				$maxConcurrent = (int)$row->value;
			} elseif ( preg_match( '/^checkoutExpiry\.(.*)$/', $row->prop, $matches ) ) {
				$username = $matches[1];
				$expiry = $row->value;

				$usernameToExpiry[$username] = $expiry;
				if ( !$earliestExpiry || $earliestExpiry > $expiry ) {
					$earliestExpiry = $expiry;
				}
			}
		}

		$username = $user->getName();
		if ( $user->isLoggedIn() && isset( $usernameToExpiry[$username] ) ) {
			// This user has already checked out this page.
			// Information to show: expiration time. Possible action: return the book immediately.

			$daysRemaining = self::daysTo( $usernameToExpiry[$username] );

			// "5 days remaining, return now" link.
			$actionText = wfMessage( 'checkoutpage-status-return' )->numParams( $daysRemaining )->escaped();

			return Xml::element( 'a', [
				'class' => 'checkoutpage-return',
				'href' => wfAppendQuery( $checkoutUrl, [ 'return' => 1 ] )
			], $actionText );
		}

		// This user doesn't have access yet.
		$copiesAvailable = $maxConcurrent - count( $usernameToExpiry );
		if ( $copiesAvailable > 0 ) {
			// Possible to checkout: show "Checkout (1 copy available)" link.
			$actionText = wfMessage( 'checkoutpage-status-available' )->numParams( $copiesAvailable )->escaped();

			return Xml::element( 'a', [
				'class' => 'checkoutpage-available',
				'href' => $checkoutUrl
			], $actionText );
		}

		// Not yet available for checkout: show "Checkout available in 5 days" text (without a link).
		$daysUntilAvailable = self::daysTo( $earliestExpiry );
		$actionText = wfMessage( 'checkoutpage-status-unavailable' )->numParams( $daysUntilAvailable )->escaped();

		return Xml::element( 'span', [ 'class' => 'checkoutpage-unavailable' ], $actionText );
	}

	/**
	 * Return the number of days (rounded up) from now to $timestamp.
	 * @param string $timestamp
	 * @return int
	 */
	protected static function daysTo( $timestamp ) {
		$seconds = wfTimestamp( TS_UNIX, $timestamp );
		$secondsNow = wfTimestamp( TS_UNIX );

		return max( 0, ceil( ( $seconds - $secondsNow ) / 86400 ) );
	}
}
