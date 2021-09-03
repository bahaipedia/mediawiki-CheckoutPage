<?php

/*
 * Extension:CheckoutPage - MediaWiki extension.
 *
 * TBD
 */

use MediaWiki\Revision\RevisionRecord;

/**
 * @file
 * Methods to read/change a page with the list of usernames, e.g. "* Name 1\n* User 2\n* User 3".
 */

class CheckoutPageUserList {
	/** @var Title */
	protected $title;

	/**
	 * @var string[]|null
	 * Array of usernames (can be empty array) or null if getUsernames() hasn't loaded it yet.
	 */
	protected $usernames = null;

	/**
	 * @var int|false
	 * Latest revision ID at the moment of loadUsernames().
	 * Used to avoid overwriting the list when 2 users are trying to checkout at the same time,
	 * or when "remove expired checkouts" logic happens at the same time as a new checkout.
	 */
	protected $originalRevId = false;

	/**
	 * @param Title $title
	 */
	public function __construct( Title $title ) {
		$this->title = $title;
	}

	public function getUsernames() {
		$this->loadUsernames();
		return $this->usernames;
	}

	/**
	 * Populate $this->usernames array. Used in getUsernames(), addUser() and removeUser().
	 */
	protected function loadUsernames() {
		if ( $this->usernames !== null ) {
			// Already loaded.
			return;
		}

		$page = new WikiPage( $this->title );
		$content = $page->getContent( RevisionRecord::RAW );

		$text = '';
		if ( $content && method_exists( $content, 'getText' ) ) {
			$text = $content->getText();
			$this->originalRevId = $page->getLatest();
		}

		// Parse the list, find all usernames.
		$this->usernames = [];
		$lines = preg_split( '/[\r\n]+/', $text );
		foreach ( $lines as $line ) {
			$username = preg_replace( '/^\*\s*/', '', $line, 1 );
			if ( $username ) {
				$this->usernames[] = $username;
			}
		}
	}

	/**
	 * Returns true if user is on the list, false otherwise.
	 * @param User $user
	 * @return bool
	 */
	public function hasUser( User $user ) {
		$this->loadUsernames();
		return in_array( $user->getName(), $this->usernames );
	}

	/**
	 * Add user to the list.
	 * @param User $user
	 * @return Status
	 */
	public function addUser( User $user ) {
		$this->loadUsernames();

		$this->usernames[] = $user->getName();
		return $this->persist();
	}

	/**
	 * Remove user from the list.
	 * @param User $user
	 * @return Status
	 */
	public function removeUser( User $user ) {
		$this->loadUsernames();

		$oldCount = count( $this->usernames );
		$excludedName = $user->getName();

		$this->usernames = array_filter( $this->usernames, function ( $value ) use ( $excludedName ) {
			return $value !== $excludedName;
		} );
		if ( count( $this->usernames ) === $oldCount ) {
			// This user wasn't on the list to begin with.
			return Status::newGood();
		}

		return $this->persist();
	}

	/**
	 * Save the modified list of $this->usernames. Used in addUser() and removeUser().
	 * @return Status
	 */
	protected function persist() {
		$text = '';
		foreach ( $this->usernames as $name ) {
			$text .= '* ' . $name . "\n";
		}

		$user = User::newSystemUser( 'CheckoutPage' );
		$summary = wfMessage( 'checkoutpage-update' )->inContentLanguage()->escaped();

		$page = new WikiPage( $this->title );
		return $page->doEditContent(
			ContentHandler::makeContent( $text, null, CONTENT_MODEL_WIKITEXT ),
			CommentStoreComment::newUnsavedComment( $summary ),
			EDIT_INTERNAL,
			$this->originalRevId,
			$user
		);
	}
}
