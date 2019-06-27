<?php
/**
 * Fuel is a fast, lightweight, community driven PHP 5.4+ framework.
 *
 * @package    Fuel
 * @version    1.8.2
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2019 Fuel Development Team
 * @link       https://fuelphp.com
 */

/**
 * Attempt to determine whether the app is SimpleAuth or OrmAuth based
 *
 * @return  string  normalized Auth driver
 */
if ( ! function_exists('normalize_driver_types'))
{
	function normalize_driver_types()
	{
		// get the drivers configured
		\Config::load('auth', true);

		$drivers = \Config::get('auth.driver', array());
		is_array($drivers) or $drivers = array($drivers);

		$results = array();

		foreach ($drivers as $driver)
		{
			// determine the driver classname
			$class = \Inflector::get_namespace($driver).'Auth_Login_'.\Str::ucwords(\Inflector::denamespace($driver));

			// Auth's Simpleauth
			if ($class == 'Auth_Login_Simpleauth' or $class == 'Auth\Auth_Login_Simpleauth')
			{
				$driver = 'Simpleauth';
			}

			// Auth's Ormauth
			elseif ($class == 'Auth_Login_Ormauth' or $class == 'Auth\Auth_Login_Ormauth')
			{
				$driver = 'Ormauth';
			}
			elseif (class_exists($class))
			{
				// Extended fromm Auth's Simpleauth
				if (get_parent_class($class) == 'Auth\Auth_Login_Simpleauth')
				{
					$driver = 'Simpleauth';
				}

				// Extended fromm Auth's Ormauth
				elseif (get_parent_class($class) == 'Auth\Auth_Login_Ormauth')
				{
					$driver = 'Ormauth';
				}
			}

			// store the normalized driver name
			in_array($driver, $results) or $results[] = $driver;
		}

		return $results;
	}
}
