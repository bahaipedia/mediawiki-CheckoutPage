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

		foreach ( $usernames as $name ) {
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
				'pp_value' => $dbw->timestamp( $expiryTimestamp )
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
	 * @return bool True if no errors, false otherwise.
	 */
	public static function revokeExpiredCheckouts() {
		$dbw = wfGetDB( DB_MASTER );
		$res = $dbw->select(
			[
				'a' => 'page_props',
				'b' => 'page_props'
			],
			[
				'a.pp_propname AS prefixedUsername',
				'b.pp_value AS accessPage',
				'a.pp_page AS articleId'
			],
			[
				'a.pp_propname ' . $dbw->buildLike( 'checkoutExpiry.', $dbw->anyString() ),
				'a.pp_value < ' . wfTimestampNow(),
				'b.pp_propname' => 'accessPage'
			],
			__METHOD__,
			[],
			[
					'b' => [ 'INNER JOIN', [
						'a.pp_page=b.pp_page'
					] ],
			]
		);
		if ( $res->numRows() === 0 ) {
			// Nothing expired.
			return Status::newGood();
		}

		$allSuccessful = true;
		foreach ( $res as $row ) {
			$username = preg_replace( '/^checkoutExpiry\./', '', $row->prefixedUsername, 1 );
			$accessPageName = $row->accessPage;

			// TODO: can reduce the number of edits when 2+ usernames must be removed from the same access page,
			// which is possible if the cron job that calls revokeExpiredCheckouts() is invoked rarely.
			// Currently removal of each username will result in 1 edit.
			$userList = new CheckoutPageUserList( Title::newFromText( $accessPageName ) );
			$status = $userList->removeUser( User::newFromName( $username ) );

			if ( !$status->isOK() ) {
				// Not a fatal error, so that we can continue revoking other checkouts.
				error_log( "Failed to revoke access of $username to $accessPageName."  );
				$allSuccessful = false;
				continue;
			}

			$dbw->delete( 'page_props', [
				'pp_propname' => $row->prefixedUsername,
				'pp_page' => $row->articleId
			], __METHOD__ );
		}

		return $allSuccessful;
	}
}
