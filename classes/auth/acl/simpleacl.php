<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2011 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Auth;


class Auth_Acl_SimpleAcl extends \Auth_Acl_Driver {

	protected static $_valid_roles = array();

	public static function _init()
	{
		static::$_valid_roles = array_keys(\Config::get('simpleauth.roles'));
	}

	public function has_access($condition, Array $entity)
	{
		$group = \Auth::group($entity[0]);
		if ( ! is_array($condition) || empty($group) || ! is_callable(array($group, 'get_roles')))
		{
			return false;
		}

		$area_heirarchy = is_array($condition[0]) ? $condition[0] : array($condition[0]);
		$rights_needed  = $condition[1];
		$rights_have = array();
		$roles_have  = $group->get_roles($entity[1]);
		if ( ! is_array($roles_have))
		{
			return false;
		}
		$roles = \Config::get('simpleauth.roles', array());
		// Add default role
		array_unshift($roles_have, '#');
		foreach ($roles_have as $role)
		{
			// continue if the role wasn't found
			if ( ! array_key_exists($role, $roles))
			{
				continue;
			}
			$rights = $roles[$role];

			// Drill down as far as possible
			foreach ($area_heirarchy as $key => $area)
			{
				// Pick up the wildcard rights at each level
				if (isset($rights['#']))
				{
					if ($rights['#'] === true)
					{
						return true;
					}
					elseif ($rights['#'] === false)
					{
						return false;
					}
					$rights_have = array_unique(array_merge($rights_have, $rights['#']));
				}
				if (isset($rights[$area]))
				{
					$rights = $rights[$area];
				}
				else
				{
					// this role doesn't have rights for the specified area
					$role = false;
					break;
				}
			}
			if ($role === false)
			{
				continue;
			}
			
			// if the role has a negative wildcard (false) return false
			if ($rights === false)
			{
				return false;
			}
			// if the role has a positive wildcard (true) return true
			elseif ($rights === true)
			{
				return true;
			}
			// if there are roles for the current area, merge them with earlier fetched roles
			else
			{
				$rights_have = array_unique(array_merge($rights_have, $rights));
			}
		} // foreach roles_have

		// start checking rights, terminate false when right not found
		foreach ($rights_needed as $right)
		{
			if ( ! in_array($right, $rights_have))
			{
				return false;
			}
		}

		// all necessary rights were found, return true
		return true;
	}
}

/* end of file simpleacl.php */
