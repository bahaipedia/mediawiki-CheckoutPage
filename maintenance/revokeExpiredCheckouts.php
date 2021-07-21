<?php

/*
 * Extension:CheckoutPage - MediaWiki extension.
 *
 * TBD
 */

/**
 * @file
 * Removes usernames from access pages if their checkouts have expired.
 *
 * Usage (from MediaWiki directory):
 * php maintenance/runScript.php extensions/CheckoutPage/maintenance/revokeExpiredCheckouts.php
 */

require_once "$IP/maintenance/Maintenance.php";

class RevokeExpiredCheckouts extends Maintenance {
	public function execute() {
		CheckoutPage::revokeExpiredCheckouts();
	}
}

$maintClass = 'RevokeExpiredCheckouts';
require_once RUN_MAINTENANCE_IF_MAIN;
