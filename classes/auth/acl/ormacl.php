<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.6
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2013 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Auth;

/**
 * OrmAuth ORM driven acl driver
 *
 * @package     Fuel
 * @subpackage  Auth
 */
class Auth_Acl_Ormacl extends \Auth_Acl_Driver
{
	/*
	 * @var  array  list of valid roles
	 */
	protected static $_valid_roles = array();

	/*
	 * class init
	 */
	public static function _init()
	{
		// get the list of valid roles
		try
		{
			static::$_valid_roles = \Cache::get(\Config::get('ormauth.cache_prefix', 'auth').'.roles');
		}
		catch (\CacheNotFoundException $e)
		{
			static::$_valid_roles = \Model\Auth_Role::find('all');
			\Cache::set(\Config::get('ormauth.cache_prefix', 'auth').'.roles', static::$_valid_roles);
		}
	}

	/*
	 * Return the list of defined roles
	 */
	public function roles()
	{
		return static::$_valid_roles;
	}

	/*
	 * Check if the user has the required permissions
	 */
	public function has_access($condition, Array $entity)
	{
		// get the group driver instance
		$group_driver = \Auth::group($entity[0]);

		// parse the requested permissions so we can check them
		$condition = static::_parse_conditions($condition);

		// if we couldn't parse the conditions, don't have a driver, or the driver doesn't export roles, bail out
		if ( ! is_array($condition) || empty($group_driver) || ! is_callable(array($group_driver, 'get_roles')))
		{
			return false;
		}

		// get the permission area and the permission rights to be checked
		$area    = $condition[0];
		$rights  = (array) $condition[1];

		// fetch the current user object
		$user = Auth::get_user();

		// assemble the current users effective rights
		$cache_key = \Config::get('ormauth.cache_prefix', 'auth').'.permissions.user_'.($user ? $user->id : 0);
		try
		{
			$current_rights = \Cache::get($cache_key);
		}
		catch (\CacheNotFoundException $e)
		{
			// get the role objects assigned to this group
			$current_roles  = $entity[1]->roles;

			// if we have a user, add the roles directly assigned to the user
			if ($user)
			{
				$current_roles = \Arr::merge($current_roles, Auth::get_user()->roles);
			}

			// some storage to collect the current rights
			$current_rights = array();

			foreach ($current_roles as $role)
			{
				// if one of the roles has a global Allowed or Denied filter, we're done
				if ( ! empty($role->filter))
				{
					$current_rights = ($role->filter == 'A' ? true : false);
					break;
				}

				// fetch the permissions of this role
				foreach ($role->permissions as $permission)
				{
					isset($current_rights[$permission->area]) or $current_rights[$permission->area] = array();
					in_array($permission->permission, $current_rights[$permission->area]) or $current_rights[$permission->area][] = $permission->permission;
				}
			}

			// if this user doesn't have a global filter applied...
			if (is_array($current_rights))
			{
				// add the users personal rights
				if ($user)
				{
					foreach ($user->permissions as $permission)
					{
						isset($current_rights[$permission->area]) or $current_rights[$permission->area] = array();
						in_array($permission->permission, $current_rights[$permission->area]) or $current_rights[$permission->area][] = $permission->permission;
					}
				}
			}

			// save the rights in the cache
			\Cache::set($cache_key, $current_rights);
		}

		// was a global filter applied?
		if (is_bool($current_rights))
		{
			// we're done here
			return $current_rights;
		}

		// start checking rights, terminate false when right not found
		foreach ($rights as $right)
		{
			if ( ! isset($current_rights[$area]) or ! in_array($right, $current_rights[$area]))
			{
				return false;
			}
		}

		// all necessary rights were found, return true
		return true;
	}
}
