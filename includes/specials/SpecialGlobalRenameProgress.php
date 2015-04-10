<?php

class SpecialGlobalRenameProgress extends FormSpecialPage {
	/**
	 * @var GlobalRenameUserStatus
	 */
	private $renameuserStatus;

	function __construct() {
		parent::__construct( 'GlobalRenameProgress' );
	}

	function getFormFields() {
		return array(
			'username' => array(
				'id' => 'mw-renameprogress-username',
				'label-message' => 'centralauth-rename-progress-username',
				'type' => 'text',
				'name' => 'username',
				'default' => $this->getRequest()->getVal( 'username', $this->par ),
			)
		);
	}

	function alterForm( HTMLForm $form ) {
		$form->setMethod( 'GET' );
		$form->setAction( $this->getPageTitle()->getLocalURL() );
		$form->setSubmitText( $this->msg( 'centralauth-rename-viewprogress')->text() );
	}

	function showLogExtract( $name ) {
		$caTitle = Title::makeTitleSafe( NS_SPECIAL, 'CentralAuth/' . $name );
		$out = $this->getOutput();
		LogEventsList::showLogExtract( $out, 'gblrename', $caTitle, '', array(
			'showIfEmpty' => true, // @todo set this to false and don't show the fieldset
			'wrap' => Xml::fieldset( $this->msg( 'centralauth-rename-progress-logs-fieldset' )->text(), '$1' ),
		) );
	}

	/**
	 * There's a race condition of some kind in cache purging (T94491),
	 * so see if the cache still thinks they're being renamed and purge
	 * it if it's wrong
	 *
	 * @param string $name
	 */
	function checkCachePurge( $name ) {
		$ca = new CentralAuthUser( $name );
		if ( $ca->renameInProgress() ) {
			$ca->quickInvalidateCache();
		}
	}

	function onSubmit( array $data ) {
		$name = User::getCanonicalName( $data['username'], 'usable' );
		if ( !$name ) {
			return false;
		}
		$out = $this->getOutput();

		$this->renameuserStatus = new GlobalRenameUserStatus( $name );
		$names = $this->renameuserStatus->getNames();
		if ( !$names ) {
			$this->checkCachePurge( $name );
			$out->addWikiMsg( 'centralauth-rename-notinprogress', $name );
			$this->getForm()->displayForm( false );
			$this->showLogExtract( $name );
			return true;
		}

		list( $oldName, $newName ) = $names;

		$statuses = $this->renameuserStatus->getStatuses();

		$this->getForm()->displayForm( false );
		// $newname will always be defined since we check
		// for 0 result rows above
		$caUser = new CentralAuthUser( $newName );
		$attached = $caUser->listAttached();
		foreach ( $attached as $wiki ) {
			// If it's not in the db table, and there is
			// an attached acount, assume it's done.
			if ( !isset( $statuses[$wiki] ) ) {
				$statuses[$wiki] = 'done';
			}
		}
		ksort( $statuses );
		$table = Html::openElement( 'table', array( 'class' => 'wikitable sortable' ) );
		$table .= Html::openElement( 'tr' );
		$table .= Html::element( 'th', array(), $this->msg( 'centralauth-rename-table-domain' )->text() );
		$table .= Html::element( 'th', array(), $this->msg( 'centralauth-rename-table-status' )->text() );
		$table .= Html::closeElement( 'tr' );
		foreach( $statuses as $wiki => $status ) {
			$table .= Html::openElement( 'tr' );
			$table .= Html::element( 'td', array(), WikiMap::getWiki( $wiki )->getDisplayName() );
			// Messages used: centralauth-rename-table-status-inprogress
			// centralauth-rename-table-status-queued, centralauth-rename-table-status-done
			$table .= Html::rawElement( 'td', array(), $this->msg( "centralauth-rename-table-status-$status" )->parse() );
			$table .= Html::closeElement( 'tr' );
		}
		$table .= Html::closeElement( 'table' );
		$fieldset = Xml::fieldset( $this->msg( 'centralauth-rename-progress-fieldset' )->text(), $table );

		$this->showLogExtract( $newName );
		$out->addHTML( $fieldset );
		return true;
	}
}
