<?php

/*
 * Extension:CheckoutPage - MediaWiki extension.
 *
 * TBD
 */

/**
 * @file
 * API to show checkout status of the current article.
 */

class ApiQueryCheckoutStatus extends ApiQueryBase {
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'cs' );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$page = $this->getTitleOrPageId( $params );

		$statusHTML = CheckoutPageStatus::getStatusHTML( $this->getUser(), $page->getTitle() );

		$result = [ 'html' => $statusHTML ];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function mustBePosted() {
		return false;
	}

	public function isWriteMode() {
		return false;
	}

	public function getAllowedParams() {
		return [
			'title' => null,
			'pageid' => [
				ApiBase::PARAM_TYPE => 'integer'
			]
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=query&prop=checkoutstatus&cstitle=Restricted_page'
				=> 'apihelp-query+checkoutstatus-example'
		];
	}
}
