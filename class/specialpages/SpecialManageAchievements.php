<?php

namespace Achiev;

use \SpecialPage;
use \User;

class SpecialManageAchievements extends SpecialPage {
	
	protected $successMessage = '';

	const MODE_LIST = 1;
	const MODE_PROG = 2;
	const MODE_ACHIEVER = 3;
	const MODE_TITLE = 4;
	const MODE_RECENT = 5;
	const MODE_USER = 6;
	const MODE_MSG = 7;
	const MODE_TOKEN = 8;
	const MODE_RANDOM = 9;

	function __construct() {
		parent::__construct( 'ManageAchievements', 'manageachievements' );
	}

	public function doesWrites() {
		return true;
	}

	function getGroupName() {
		return 'users';
	}
	
	public static function getMode( $request, $par ) {
		$mode = strtolower( $request->getVal( 'action', $par ) );

		switch ( $mode ) {
			case 'list':
			case self::MODE_LIST:
				return self::MODE_LIST;
			case 'prog':
			case self::MODE_PROG:
				return self::MODE_PROG;
			case 'achiever':
			case self::MODE_ACHIEVER:
				return self::MODE_ACHIEVER;
			case 'title':
			case self::MODE_TITLE:
				return self::MODE_TITLE;
			case 'recent':
			case self::MODE_RECENT:
				return self::MODE_RECENT;
			case 'user':
			case self::MODE_USER:
				return self::MODE_USER;
			case 'msg':
			case self::MODE_MSG:
				return self::MODE_MSG;
			case 'token':
			case self::MODE_TOKEN:
				return self::MODE_TOKEN;
			case 'random':
			case self::MODE_RANDOM:
				return self::MODE_RANDOM;
			default:
				return false;
		}
	}

	public static function buildTools( $lang, $linkRenderer = null ) {
		if ( !$lang instanceof \Language ) {
			// back-compat where the first parameter was $unused
			global $wgLang;
			$lang = $wgLang;
		}
		if ( !$linkRenderer ) {
			$linkRenderer = \MediaWikiServices::getInstance()->getLinkRenderer();
		}

		$tools = [];
		$modes = [
			'list',
			'prog',
			'achiever',
			'title',
			'recent',
			'user',
			'msg',
			'token'
		];

		foreach ( $modes as $mode ) {
			$tools[] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'ManageAchievements', $mode ),
				wfMessage( 'manageachievements-mode-' . $mode )->text()
			);
		}

		return \Html::rawElement(
			'span',
			[ 'class' => 'mw-manageachievements-toollinks' ],
			wfMessage( 'parentheses' )->rawParams( $lang->pipeList( $tools ) )->escaped()
		);
	}

	function execute( $mode ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();
		$lang = $this->getLanguage();

		// Set the page title, robot policies, etc.
		$this->setHeaders();

		if ( !$user->isAllowed( 'manageachievements' ) ) {
			throw new \PermissionsError( 'manageachievements' );
		}
		
		$this->checkReadOnly();

		$out->addSubtitle( 
			self::buildTools(
				$this->getLanguage(),
				$this->getLinkRenderer()
			)
		);

		$mode = self::getMode( $request, $mode );

		$target = $request->getVal( 'user' );

		if ( is_string( $target ) && ($target = trim( $target )) !== '' ) {
			$cuser = User::newFromName( $target );
		}

		switch ( $mode ) {
			case self::MODE_LIST:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-list' ) );
				$allachievs = AchievementHandler::AchievementsFromAll();
				AchievementHandler::sortAchievements( $allachievs );
				
				$table = '';
				foreach ( $allachievs as &$achiev ) {
					$id = $achiev->getID();
					$count = [];
					$table .= \ExtAchievement::buildAchievBlock( $achiev, null, $count, $user );
				}
				$out->addHTML( $table );
				$out->addModules( [ 'ext.pref.achievement' ] );
				break;
			case self::MODE_PROG:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-list' ) );
				$this->showAchievForm( 'processProgInput' );
				break;
			case self::MODE_ACHIEVER:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-achiever' ) );
				$this->showAchievForm( 'processAchieverInput' );
				break;
			case self::MODE_TITLE:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-title' ) );
				$dbw = wfGetDB( DB_SLAVE );

				$list = [];
				$res = $dbw->select(
					'user_properties',
					[ 'up_user', 'up_value' ],
					[ 'up_property' => 'achievtitle', 'up_value <> \'\'' ],
					__METHOD__,
					[ 'ORDER BY' => 'up_value ASC' ]
				);
				if ( $res ) {
					$out->addHTML( $this->msg( 'manage-achiev-user-count' )->rawParams( $dbw->numRows( $res ) )->escaped() . '<br/>' );
					$out->addHTML( '<ul>' );
					while ( $row = $res->fetchRow() ) {
						$stage = 0;
						$achiev = AchievementHandler::AchievementFromStagedID( $row['up_value'], $stage );
						if ( $achiev !== false ) {
							$out->addHTML('<li>' . User::whoIs( $row['up_user'] ) . ' '. $achiev->getAfterLinkMsg( $stage, false ) . '</a></li>' );
						}
						
					}
					$out->addHTML( '</ul>' );
				}
				break;
			case self::MODE_RECENT:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-recent' ) );
				$dbw = wfGetDB( DB_SLAVE );

				$list = [];
				$res = $dbw->select(
					'echo_event',
					[ 'event_type', 'event_agent_id', 'event_extra' ],
					[ 'event_type' => ['achiev-award','achiev-remove'] ],
					__METHOD__,
					[ 'ORDER BY' => 'event_id DESC', 'LIMIT' => 100 ]
				);
				if ( $res ) {
					$out->addHTML( '<ul>' );
					while ( $row = $res->fetchRow() ) {
						$extra = unserialize( $row['event_extra'] );
						$stage = 0;
						$achiev = AchievementHandler::AchievementFromStagedID( $extra['achievid'], $stage );
						if ( $achiev !== false ) {
							$auser = \User::newFromId( $row['event_agent_id'] );
							$ts = AchievementHandler::quickCheckUserAchiev( $auser, $extra['achievid'] );
							
							$out->addHTML('<li>' . $auser->getName() . ': '. 
							$this->msg( 'notification-body-' . $row['event_type'] )->rawParams($achiev->getNameMsg( $stage ), $achiev->getDescMsg( $stage )).
							( $ts ? $this->msg( 'parentheses' )->rawParams( $lang->userTimeAndDate($ts, $user) )->escaped() : '' ).'</li>' );
						}
					}
					$out->addHTML( '</ul>' );
				}
				break;
			case self::MODE_USER:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-user' ) );
				$this->showUserForm( 'processUserInput' );
				$out->addModules( [ 'ext.pref.achievement' ] );
				break;
			case self::MODE_MSG:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-msg' ) );
				$list = AchievementHandler::AchievementsFromAll();
				$json = [];

				foreach ( $list as &$achiev ) {
					$namekeys = $achiev->listNameMsgKeys();
					$desckeys = $achiev->listDescMsgKeys();
					foreach ( $namekeys as $i => $msgkey ) {
						$json[$msgkey] = $this->msg( $msgkey )->exists() ? $this->msg( $msgkey )->plain() : '';
						$json[$desckeys[$i]] = $this->msg( $desckeys[$i] )->exists() ? $this->msg( $desckeys[$i] )->plain() : '';
					}
				}
				$out->addHTML( '<textarea rows="40">' . htmlspecialchars(
					preg_replace( '/^\s+/m', "\t", json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) )
				) . '</textarea>' );
				break;
			case self::MODE_TOKEN:
				$out->setPageTitle( $this->msg( 'manageachievements-mode-token' ) );
				$this->showTokenForm( 'processTokenInput' );
				break;
			case self::MODE_RANDOM:
				$list = AchievementHandler::AchievementsFromCounter( 'random' );
				$out->addHTML( '<ul>' );
				foreach ( $list as $ac ) {
					$out->addHTML( '<li>'.$ac->getID().': '.date('Y-m-d H:i:s', $ac->getCounter()->getNextTimestamp() )."</li>\n" );
				}
				$out->addHTML( '</ul>' );
				break;
			default:
				
		}

		$out->addHTML( $this->successMessage );

		return;
	}

	protected function showAchievForm ( $callback ) {
		$formDescriptor = array(
			'achiev' => array(
				'label-message' => 'manage-achiev-name',
				'class' => 'HTMLTextField',
				'default' => '',
			),
		);

		$htmlForm = new \HTMLForm( $formDescriptor, $this->getContext(), 'manage-achiev' );
		$htmlForm->setWrapperLegend();

		$htmlForm->setMethod( 'get' );
		$htmlForm->setSubmitText( $this->msg('htmlform-submit') );
		$htmlForm->setSubmitCallback( [ $this, $callback ] );  

		return $htmlForm->show();
	}

	protected function showUserForm ( $callback ) {
		$formDescriptor = array(
			'user' => array(
				'label-message' => 'manage-achiev-user',
				'class' => 'HTMLTextField',
				'default' => $this->getUser()->getName(),
			),
		);

		$htmlForm = new \HTMLForm( $formDescriptor, $this->getContext(), 'manage-achiev' );
		$htmlForm->setWrapperLegend();

		$htmlForm->setMethod( 'get' );
		$htmlForm->setSubmitText( $this->msg('htmlform-submit') );
		$htmlForm->setSubmitCallback( [ $this, $callback ] );  

		return $htmlForm->show();
	}

	protected function showTokenForm ( $callback ) {
		$formDescriptor = array(
			'achiev' => array(
				'label-message' => 'manage-achiev-name',
				'class' => 'HTMLTextField',
				'default' => '',
			),
			'target' => array(
				'label-message' => 'manage-achiev-token-user',
				'class' => 'HTMLTextField',
				'default' => '',
			),
			'count' => array(
				'label-message' => 'manage-achiev-token-count',
				'class' => 'HTMLTextField',
				'default' => '',
			),
			'time' => array(
				'label-message' => 'manage-achiev-token-time',
				'class' => 'HTMLTextField',
				'default' => '86400',
			),
		);

		$htmlForm = new \HTMLForm( $formDescriptor, $this->getContext(), 'manage-achiev' );
		$htmlForm->setWrapperLegend();

		$htmlForm->setMethod( 'post' );
		$htmlForm->setSubmitText( $this->msg('htmlform-submit') );
		$htmlForm->setSubmitCallback( [ $this, $callback ] );  

		return $htmlForm->show();
	}

	public function processProgInput ( $data ) {
		if ( !empty( $data['achiev'] ) ) {
			$id = $data['achiev'];
			$achiev = AchievementHandler::AchievementFromID( $id );
			if ( $achiev ) {
				$dbw = wfGetDB( DB_SLAVE );

				$res = $dbw->select(
					'achievements',
					[ 'ac_user', 'ac_count' ],
					[ 'ac_id' => $id, 'ac_count>0' ],
					__METHOD__,
					[ 'ORDER BY' => 'ac_count DESC', 'LIMIT' => 200 ]
				);
				$count = $dbw->numRows( $res );
				$this->successMessage .= $achiev->getNameMsg() . $this->msg( 'manage-achiev-user-count' )->rawParams( $count == 200?'200+':$count )->escaped() . '<br/>';
				if ( $res ) {
					$lr = $this->getLinkRenderer();
					$this->successMessage .= '<ol>';
					while ( $row = $res->fetchRow() ) {
						$this->successMessage .= '<li>' . User::whoIs( $row['ac_user'] ) . ': ' . $row['ac_count'] . '</li>';
					}
					$this->successMessage .= '</ol>';
				}
			}
		}
		return false;
	}

	public function processAchieverInput ( $data ) {
		if ( !empty( $data['achiev'] ) ) {
			$id = $data['achiev'];
			$stage = 0;
			$achiev = AchievementHandler::AchievementFromStagedID( $id, $stage );
			if ( $achiev ) {
				$dbw = wfGetDB( DB_SLAVE );

				$list = AchievementHandler::getAchievers( $id );
				$this->successMessage .= $achiev->getNameMsg( $stage ) . $this->msg( 'manage-achiev-user-count' )->rawParams( count( $list ) )->escaped() . '<br/>';
				$this->successMessage .=  '<ul>';
				foreach ( $list as $userid ) {
					$this->successMessage .= '<li>'.User::whoIs( $userid ).'</li>';
				}
				$this->successMessage .=  '</ul>';
			}
		}
		return false;
	}

	public function processUserInput ( $data ) {
		if ( !empty( $data['user'] ) ) {
			if ( is_string( $data['user'] ) && ($data['user'] = trim( $data['user'] )) !== '' ) {
				$user = User::newFromName( $data['user'] );
			}
			if ( !($user instanceof User) || $user->isAnon() || $user->isBlocked() ) {
				return false;
			}
			AchievementHandler::updateUserAchievs( $user );
			$allachievs = AchievementHandler::AchievementsFromAll();
			AchievementHandler::sortAchievements( $allachievs );
			$userachievs = AchievementHandler::getUserAchievs( $user );
			$counts = AchievementHandler::getUserCounts( $user );
			
			$this->successMessage = '';
			foreach ( $allachievs as &$achiev ) {
				$id = $achiev->getID();
				if ( empty( $userachievs[$id] ) ) {
					if ( !$achiev->getConfig( 'hidden', false ) ) {
						$this->successMessage .= \ExtAchievement::buildAchievBlock( $achiev, [], $counts, $user );
					}
				} else {
					$tss = $userachievs[$id];
					$this->successMessage .= \ExtAchievement::buildAchievBlock( $achiev, $tss, $counts, $user );
				}
			}
		}
		return false;
	}
	
	public function processTokenInput ( $data ) {
		if ( !empty( $data['achiev'] ) ) {
			$stage = 0;
			$stagename = trim( $data['achiev'] );
			$achiev = AchievementHandler::AchievementFromStagedID( $stagename, $stage );
			if ( $achiev ) {
				if ( !empty( $data['target'] ) ) $target = User::newFromName( $data['target'] );
				else $target = null;

				if ( !empty( $data['count'] ) ) $count = intval( $data['count'] );
				else $count = null;

				if ( !empty( $data['time'] ) ) $time = intval( $data['time'] );
				else $time = 86400;
				
				$token = Token::getToken( $this->getUser(), $achiev, $stage, $count, $target, $time );
				if ( $token ) {
					if ( $target instanceof \User ) $this->successMessage .= $target->getName() . '<br />';
					if ( !is_null( $count ) ) $this->successMessage .= $count . '<br />';
					if ( !is_null( $time ) ) $this->successMessage .= $time . '<br />';
					$this->successMessage .= $token->toString() . '<br />';
					$this->successMessage .= $token->toUrl();
				} else {
					if ( $achiev->isAwardable() ) {
						return $this->msg( 'manage-achiev-token-fail' )->parse();
					} else {
						return $this->msg( 'manage-achiev-token-notawardable' )->parse();
					}
				}
			} else {
				return $this->msg( 'manage-achiev-token-invalid' )->parse();
			}
		}
		return false;
	}
}