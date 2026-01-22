<?php

namespace CategoryWatch;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Page\ProperPageIdentity;
use Title;
use MediaWiki\Edit\EditResult;
use LinksUpdate;


class Hook {

	public static function onLinksUpdateComplete(
		$linksUpdate,
	): void {

		wfDebugLog( 'CategoryWatch', __METHOD__ );

		$userIdentity = $linksUpdate->getTriggeringUser();
		if ( !$userIdentity ) {
			wfDebugLog( 'CategoryWatch', 'Triggering userIdentity is NULL' );
		} else {
			wfDebugLog(
				'CategoryWatch',
				'Triggering userIdentity: id=' . $userIdentity->getId() .
				' name=' . $userIdentity->getName()
			);
		}


		$user = MediaWikiServices::getInstance()
			->getUserFactory()
			->newFromId( $userIdentity->getId() );


		$title = $linksUpdate->getTitle();
		if ( !$title || $title->inNamespace( NS_CATEGORY ) ) {
			wfDebugLog(
				'CategoryWatch',
				'Skipping category page edit: ' . ( $title ? $title->getPrefixedText() : 'null' )
			);
			return;
		}

		$parserOutput = $linksUpdate->getParserOutput();
		if ( !$parserOutput ) {
			wfDebugLog( 'CategoryWatch', 'No ParserOutput available in LinksUpdateComplete' );
			return;
		}

		wfDebugLog(
			'CategoryWatch',
			'ns=' . $title->getNamespace() . ' key=' . $title->getDBkey()
		);


		$dbr = MediaWikiServices::getInstance()
			->getDBLoadBalancer()
			->getConnection( DB_REPLICA );

		$newCategories = $dbr->selectFieldValues(
			'categorylinks',
			'cl_to',
			[ 'cl_from' => $title->getArticleID() ],
			__METHOD__
		);

		wfDebugLog(
			'CategoryWatch',
			'Categories from DB: ' . implode( ',', $newCategories )
		);


		$pageId = $title->getArticleID();
		$revisionId = $linksUpdate->getRevisionRecord()
			? $linksUpdate->getRevisionRecord()->getId()
			: null;

		foreach ( $newCategories as $catDbKey ) {
			//$catTitle = Title::makeTitle( NS_CATEGORY, $catDbKey );
			$catTitle = Title::makeTitleSafe( NS_CATEGORY, $catDbKey );

			if ( self::getWatchedItemStore()->countWatchers( $catTitle ) > 0 ) {
				wfDebugLog(
					'CategoryWatch',
					'Creating ADD notification for ' . $catDbKey
				);
			wfDebugLog(
				'CategoryWatch',
				'class exists=' . ( class_exists( 'CategoryWatch\\EchoEventPresentationModel' ) ? 'YES' : 'NO' )
			);


				if ( !\EchoEvent::create( [
					'type'  => 'categorywatch-add',
					'title' => $catTitle,
					'page'  => null,
					'agent' => $user,
					'extra' => [
						'pageid' => $pageId,
						'revid'  => $revisionId,
					],
				] ) ) {
					wfDebugLog( 'CategoryWatch', 'EchoEvent::create failed' );
}
		global $wgEchoNotifications;

		wfDebugLog(
			'CategoryWatch',
			'event type registered=' .
			( isset( $wgEchoNotifications['categorywatch-add'] ) ? 'YES' : 'NO' )
		);
			// After EchoEvent::create(...)
			$evRow = $dbr->selectRow(
				'echo_event',
				[ 'event_id', 'event_type' ],
				[ 'event_type' => 'categorywatch-add' ],
				__METHOD__,
				[ 'ORDER BY' => 'event_id DESC' ]
			);

			wfDebugLog(
				'CategoryWatch',
				'echo_event latest=' . ( $evRow ? (int)$evRow->event_id : 'NONE' )
			);

			if ( $evRow ) {
				$notifCount = (int)$dbr->selectField(
					'echo_notification',
					'COUNT(*)',
					[ 'notification_event' => (int)$evRow->event_id ],
					__METHOD__
				);

				wfDebugLog( 'CategoryWatch', 'echo_notification count=' . $notifCount );
			}
				\EchoEvent::create( [
					'type'  => 'categorywatch-add',
					'title' => $catTitle,
					'page'  => $catTitle,
					'agent' => $user,
					'extra' => [
						'pageid' => $pageId,
						'revid'  => $revisionId,
					],
				] );
			}
		}
	}

	/**
	 * Explain bundling
	 *
	 * @param string &$bundleString to use
	 */
	public static function onEchoGetBundleRules( string $eventType, &$bundleString ) {
		wfDebugLog( 'CategoryWatch', __METHOD__ );

		switch ( $eventType ) {
			case 'categorywatch-add':
				$bundleString = 'categorywatch';
				break;
		}
	}

		public static function locateUsers( \EchoEvent $event ): array {
			wfDebugLog( 'CategoryWatch', '### LOCATEUSERS TEST ' . microtime(true) );

			wfDebugLog( 'CategoryWatch', __METHOD__ );

			$title = $event->getTitle();
			if ( !$title ) {
				return [];
			}

			$services = MediaWikiServices::getInstance();
			$dbr = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );
			$row = $dbr->selectRow(
				'watchlist',
				'*',
				[
					'wl_namespace' => 14,   // NS_CATEGORY
					'wl_title' => 'Lists',
				],
				__METHOD__
			);
			wfDebugLog( 'CategoryWatch', 'watchlist row=' . ( $row ? 'FOUND' : 'NOT FOUND' ) );

			$dbType = $services->getMainConfig()->get( 'DBtype' );

			// SQLITE: watchlist stores wl_user
			if ( $dbType === 'sqlite' ) {
				$userIds = $dbr->selectFieldValues(
					'watchlist',
					'wl_user',
					[
						'wl_namespace' => $title->getNamespace(),
						'wl_title' => $title->getDBkey(),
					],
					__METHOD__
				);
			} 
			// MYSQL / MARIADB: watchlist stores wl_actor
			else {
				$actorIds = $dbr->selectFieldValues(
					'watchlist',
					'wl_actor',
					[
						'wl_namespace' => $title->getNamespace(),
						'wl_title' => $title->getDBkey(),
					],
					__METHOD__
				);

				if ( !$actorIds ) {
					return [];
				}

				$userIds = $dbr->selectFieldValues(
					'actor',
					'actor_user',
					[ 'actor_id' => $actorIds ],
					__METHOD__
				);
			}

			if ( empty( $userIds ) ) {
				return [];
			}

			$userIdentityLookup = $services->getUserIdentityLookup();
			$users = [];

			foreach ( $userIds as $id ) {
				$identity = $userIdentityLookup->getUserIdentityByUserId( (int)$id );
				if ( $identity ) {
					$users[] = $identity;
				}
			}

			wfDebugLog(
			'CategoryWatch',
			'returning users=' . implode(
				',',
				array_map( fn ( $u ) => $u->getName(), $users )
			)
		);


			return $users;
		}

	public static function onBeforeCreateEchoEvent(
		array &$notifications, array &$notificationCategories, array &$icons
	) {
		$icons['categorywatch']['path'] = 'CategoryWatch/assets/catwatch.svg';

		$notifications['categorywatch-add'] = [
			// 'bundle' => [
			// 	'web' => true,
			// 	'email' => true,
			// 	'expandable' => true,
			// ],
			'section' => 'alert',
			'web' => true,
			'email' => false,
			'notify-agent' => true,
			'bundle' => [
				'web' => true,
				'email' => false,
			],
			'title-message' => 'categorywatch-add-title',
			'group' => 'neutral',
			'user-locators' => [ 'CategoryWatch\\Hook::locateUsers' ],
			'presentation-model' => 'CategoryWatch\\EchoEventPresentationModel',
		];

		$notificationCategories['categorywatch'] = [
			'priority' => 2,
			'tooltip' => 'echo-pref-tooltip-categorywatch'
		];
	}

	private static function getWatchedItemStore() {
		wfDebugLog( 'CategoryWatch', __METHOD__ );
		return MediaWikiServices::getInstance()->getWatchedItemStore();
	}

}