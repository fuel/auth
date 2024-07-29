<?php
/**
 * Fuel is a fast, lightweight, community driven PHP 5.4+ framework.
 *
 * @package    Fuel
 * @version    1.9-dev
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2019 Fuel Development Team
 * @link       https://fuelphp.com
 */

/**
 * NOTICE:
 *
 * If you need to make modifications to the default configuration, copy
 * this file to your app/config folder, and make them in there.
 *
 * This will allow you to upgrade fuel without losing your custom config.
 */

return array(
	/**
	 * The authentication system or systems to use. Authentication systems
	 * are called in the order they are defined here.
	 */
    'driver'                 => 'Simpleauth',

	/**
	 * If 'false', verification stops as soon as a driver has validated, if
	 * 'true', all drivers must validate the user before being logged in.
	 */
    'verify_multiple_logins' => false,

	/**
	 * The the of login to use. Acceptable values are:
	 * - username, user logs in with username and password
	 * - email, user logs in with email address and password
	 * - both, user logs in with username or email address, and password
	 *
	 * 'both' is default legacy behaviour, it is advised not to use it,
	 * as it is not very secure (a user can be created with as the username
	 * the email address of another user, and steal or block that login.
	 */
	'login_type' => 'both',

	/**
	 * A random salt used in password hashing
	 */
    'salt'                   => 'put_your_salt_here',

	/**
	 * Number of iterations used when hashing the password
	 */
    'iterations'             => 10000,
);
