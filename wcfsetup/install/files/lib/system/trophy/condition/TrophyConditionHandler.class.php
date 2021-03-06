<?php
namespace wcf\system\trophy\condition;
use wcf\data\object\type\ObjectType;
use wcf\data\object\type\ObjectTypeCache;
use wcf\data\trophy\Trophy;
use wcf\data\trophy\TrophyList;
use wcf\data\user\trophy\UserTrophyAction;
use wcf\data\user\User;
use wcf\data\user\UserList;
use wcf\system\SingletonFactory;

/**
 * Handles trophy conditions. 
 *
 * @author	Joshua Ruesweg
 * @copyright	2001-2017 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\System\Trophy\Condition
 * @since	3.1
 */
class TrophyConditionHandler extends SingletonFactory {
	/**
	 * definition name for trophy conditions
	 * @var string
	 */
	const CONDITION_DEFINITION_NAME = 'com.woltlab.wcf.condition.trophy';
	
	/**
	 * list of grouped trophy condition object types
	 * @var	ObjectType[][]
	 */
	protected $groupedObjectTypes = [];
	
	/**
	 * @inheritDoc
	 */
	protected function init() {
		$objectTypes = ObjectTypeCache::getInstance()->getObjectTypes(self::CONDITION_DEFINITION_NAME);
		
		foreach ($objectTypes as $objectType) {
			if (!$objectType->conditiongroup) continue;
			
			if (!isset($this->groupedObjectTypes[$objectType->conditiongroup])) {
				$this->groupedObjectTypes[$objectType->conditiongroup] = [];
			}
			
			$this->groupedObjectTypes[$objectType->conditiongroup][$objectType->objectTypeID] = $objectType;
		}
	}
	
	/**
	 * Returns the list of grouped trophy condition object types.
	 *
	 * @return	ObjectType[][]
	 */
	public function getGroupedObjectTypes() {
		return $this->groupedObjectTypes;
	}
	
	/**
	 * Assign trophies based on rules. 
	 * 
	 * @param 	integer		$maxAssigns
	 */
	public function assignTrophies($maxAssigns = 500) {
		$trophyList = new TrophyList();
		$trophyList->getConditionBuilder()->add('awardAutomatically = ?', [1]);
		$trophyList->getConditionBuilder()->add('isDisabled = ?', [0]);
		$trophyList->readObjects();
		
		$i = 0;
		foreach ($trophyList as $trophy) {
			$users = $this->getUsers($trophy);
			
			foreach ($users as $user) {
				(new UserTrophyAction([], 'create', [
					'data' => [
						'trophyID' => $trophy->trophyID,
						'userID' => $user->userID,
						'time' => TIME_NOW
					]
				]))->executeAction();
				
				if (++$i >= $maxAssigns) return;
			}
		}
	}
	
	/**
	 * Returns the users who fulfill all conditions of the given trophy.
	 *
	 * @param	Trophy		$trophy
	 * @return	User[]
	 */
	private function getUsers(Trophy $trophy) {
		$userList = new UserList();
		
		$conditions = $trophy->getConditions();
		foreach ($conditions as $condition) {
			$condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
		}
		
		// prevent multiple awards from a trophy for a user 
		$userList->getConditionBuilder()->add('user_table.userID NOT IN (SELECT userID FROM wcf'.WCF_N.'_user_trophy WHERE trophyID IN (?))', [$trophy->trophyID]);
		$userList->readObjects();
		
		return $userList->getObjects();
	}
	
	/**
	 * Returns the outstanding trophy assignment count. 
	 * 
	 * @return	integer
	 */
	public function getOutstandingTrophyAssignmentCount() {
		$trophyList = new TrophyList();
		$trophyList->getConditionBuilder()->add('awardAutomatically = ?', [1]);
		$trophyList->getConditionBuilder()->add('isDisabled = ?', [0]);
		$trophyList->readObjects();
		
		$outstandingCount = 0;
		foreach ($trophyList as $trophy) {
			$userList = new UserList();
			
			$conditions = $trophy->getConditions();
			foreach ($conditions as $condition) {
				$condition->getObjectType()->getProcessor()->addUserCondition($condition, $userList);
			}
			
			// prevent multiple awards from a trophy for a user 
			$userList->getConditionBuilder()->add('user_table.userID NOT IN (SELECT userID FROM wcf'.WCF_N.'_user_trophy WHERE trophyID IN (?))', [$trophy->trophyID]);
			$outstandingCount += $userList->countObjects();
		}
		
		return $outstandingCount; 
	}
}
