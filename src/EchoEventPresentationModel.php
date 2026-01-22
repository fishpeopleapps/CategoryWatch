<?php

namespace CategoryWatch;

use MediaWiki\MediaWikiServices;
use Message;
use Title;

wfDebugLog( 'CategoryWatch', 'PRESENTATION MODEL FILE LOADED' );


class EchoEventPresentationModel extends \EchoEventPresentationModel {

	/**
	 * Tell the caller if this event can be rendered.
	 *
	 * @return bool
	 */
	// public function canRender() {
	// 	wfDebugLog( 'CategoryWatch', __METHOD__ );
	// 	return (bool)$this->event->getTitle();
	// }

	/**
	 * Which of the registered icons to use.
	 *
	 * @return string
	 */
	public function getIconType() {
		wfDebugLog( 'CategoryWatch', __METHOD__ );
		return 'categorywatch';
	}

	/**
	 * The header of this event's display
	 *
	 * @return Message
	 */
	public function getHeaderMessage() {
		wfDebugLog( 'CategoryWatch', 'PresentationModel hit for event ' . $this->event->getType() );
		if ( $this->isBundled() ) {
			$msg = $this->msg( 'categorywatch-notification-bundle' );
			$msg->params( $this->getBundleCount() );
			$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
			$msg->params( $this->getViewingUserForGender() );
		} else {
			$msg = $this->msg( 'categorywatch-notification-' . $this->event->getType() . '-header' );
			$msg->params( $this->getPageTitle() );
			$msg->params( $this->getTruncatedTitleText( $this->getPageTitle(), true ) );
			$msg->params( $this->event->getTitle() );
			$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
		}
		return $msg;
	}

	/**
	 * Shorter display
	 *
	 * @return Message
	 */
	public function getCompactHeaderMessage() {
		wfDebugLog( 'CategoryWatch', __METHOD__ );
		$msg = parent::getCompactHeaderMessage();
		$msg->params( $this->getViewingUserForGender() );
		return $msg;
	}

	/**
	 * Summary of edit
	 *
	 * @return string
	 */
	public function getRevisionEditSummary() {
		wfDebugLog( 'CategoryWatch', __METHOD__ );
		$msg = $this->getMessageWithAgent(
			'categorywatch-notification-' . $this->event->getType() . '-summary'
		);
		$msg->params( $this->getPageTitle() );
		$msg->params( $this->getTruncatedTitleText( $this->getPageTitle(), true ) );
		$msg->params( $this->event->getTitle() );
		$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
		return $msg;
	}

	/**
	 * Body to display
	 *
	 * @return Message
	 */
	public function getBodyMessage() {
		wfDebugLog( 'CategoryWatch', 'PresentationModel hit for event ' . $this->event->getType() );
		$msg = $this->getMessageWithAgent(
			'categorywatch-notification-' . $this->event->getType() . '-body'
		);
		$msg->params( $this->getPageTitle() );
		$msg->params( $this->event->getTitle() );
		return $msg;
	}

	/**
	 * @return Title
	 */
	public function getPageTitle() {
		$pageId = (int)$this->event->getExtraParam( 'pageid' );

		$page = MediaWikiServices::getInstance()
			->getWikiPageFactory()
			->newFromID( $pageId );

		return $page
			? $page->getTitle()
			: Title::makeTitle( NS_SPECIAL, 'Badtitle' );
	}


	/**
	 * Provide the main link
	 *
	 * @return array
	 */
	public function getPrimaryLink() {
		wfDebugLog( 'CategoryWatch', __METHOD__ );
		$title = $this->event->getTitle();
		return [
			'url' => $title->getFullURL(),
			'label' => $title->getPrefixedText(),
		];
	}

	/**
	 * Aux links
	 *
	 * @return array
	 */
	public function getSecondaryLinks() {
		wfDebugLog( 'CategoryWatch', __METHOD__ );
		if ( $this->isBundled() ) {
			// For the bundle, we don't need secondary actions
			return [];
		} else {
			return [
				$this->getAgentLink(),
				[
					'url' => $this->getPageTitle()->getFullURL(),
					'label' => $this->getPageTitle()->getPrefixedText()
				]
			];
		}
	}

	public function jsonSerialize(): array {
		wfDebugLog( 'CategoryWatch', __METHOD__ );
		$body = $this->getBodyMessage();

		return [
			'header' => $this->getHeaderMessage()->parse(),
			'compactHeader' => $this->getCompactHeaderMessage()->parse(),
			'body' => $body ? $body->parse() : '',
			'icon' => $this->getIconType(),
			'links' => [
				'primary' => $this->getPrimaryLinkWithMarkAsRead() ?: [],
				'secondary' => array_values( array_filter( $this->getSecondaryLinks() ) ),
			],
		];
	}
}
