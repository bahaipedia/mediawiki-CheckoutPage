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
		// but only if it has less than $options['maxConcurrent'] names already.
		$userList = new CheckoutPageUserList( Title::newFromText( $options['accessPage'] ) );

		$currentUserName = $user->getName();
		$usernames = $userList->getUsernames();

		foreach ( $existingUsernames as $name ) {
			if ( $name == $currentUserName ) {
				// This user already has this page checked out.
				// Can easily happen when user clicks on "Check out" button twice.
				return Status::newGood();
			}
		}

		if ( count( $usernames ) >= (int)$options['maxConcurrent'] ) {
			return Status::newFatal( 'checkoutpage-user-limit-reached' );
		}

		$status = $userList->addUser( $user );
		if ( !$status->isOK() ) {
			// Failed to add.
			return $status;
		}

		// Remember the time when checkout should be revoked (NOW timestamp + $options['checkoutDays']).
		$ts = new MWTimestamp();
		$ts->timestamp->modify( '+' . intval( $options['checkoutDays'] ) . ' days' );
		$expiryTimestamp = $ts->getTimestamp( TS_MW );

		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert( 'page_props',
			[
				'pp_page' => $pageId,
				'pp_propname' => 'checkoutExpiry.' . $currentUserName,
				'pp_value' => $expiryTimestamp
			],
			__METHOD__,
			[ 'IGNORE' ]
		);

		// Successfully checked out.
		return Status::newGood();
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
