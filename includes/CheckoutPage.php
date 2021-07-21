<?php

/*
 * Extension:CheckoutPage - MediaWiki extension.
 *
 * TBD
 */

use MediaWiki\Revision\RevisionRecord;

/**
 * @file
 * Main logic of granting/revoking temporary access.
 */

class CheckoutPage {
	/**
	 * Grant $user temporary access to the page $title.
	 * @param User $user
	 * @param Title $title
	 * @return Status
	 */
	public static function grantAccess( User $user, Title $title ) {
		$pageId = $title->getArticleID( Title::READ_LATEST );
		if ( !$pageId ) {
			// Page doesn't exist.
			return Status::newFatal( 'nopagetext' );
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
		$ts = new MWTimestamp();
		$ts->timestamp->modify( '+' . intval( $options['checkoutDays'] ) . ' days' );
		$expiryTimestamp = $ts->getTimestamp( TS_MW );

		$accessPageTitle = Title::newFromText( $options['accessPage'] );
		$accessPage = new WikiPage( $accessPageTitle );
		$content = $accessPage->getContent( RevisionRecord::RAW );

		// TODO

		return Status::newFatal( 'not yet implemented' );
		// return Status::newGood();
	}

	/**
	 * Revoke all checkouts that have expired. This can be called periodically.
	 * @param User $user
	 * @param Title $title
	 * @return Status
	 */
	public static function revokeExpiredCheckouts() {
		// TODO
	}
}
