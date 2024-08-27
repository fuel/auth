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

namespace Auth;

/**
 * SimpleAuth basic login driver
 *
 * @package     Fuel
 * @subpackage  Auth
 */
class Auth_Login_Simpleauth extends \Auth_Login_Driver
{
	/**
	 * Load the config and setup the remember-me session if needed
	 */
	public static function _init()
	{
		\Config::load('simpleauth', true);

		// setup the remember-me session object if needed
		if (\Config::get('simpleauth.remember_me.enabled', false))
		{
			static::$remember_me = \Session::forge(array(
				'driver' => 'cookie',
				'cookie' => array(
					'cookie_name' => \Config::get('simpleauth.remember_me.cookie_name', 'rmcookie'),
				),
				'encrypt_cookie' => true,
				'expire_on_close' => false,
				'expiration_time' => \Config::get('simpleauth.remember_me.expiration', 86400 * 31),
			));
		}
	}

	/**
	 * @var  Database_Result  when login succeeded
	 */
	protected $user = null;

	/**
	 * @var  array  value for guest login
	 */
	protected static $guest_login = array(
		'id' => 0,
		'username' => 'guest',
		'group' => '0',
		'login_hash' => false,
		'email' => false,
	);

	/**
	 * @var  array  SimpleAuth class config
	 */
	protected $config = array(
		'drivers' => array('group' => array('Simplegroup')),
		'additional_fields' => array('profile_fields'),
	);

	/**
	 * Check for login
	 *
	 * @return  bool
	 */
	protected function perform_check()
	{
		// fetch the username and login hash from the session
		$username    = \Session::get('username');
		$login_hash  = \Session::get('login_hash');

		// only worth checking if there's both a username and login-hash
		if ( ! empty($username) and ! empty($login_hash))
		{
			if (is_null($this->user) or ($this->user['username'] != $username and $this->user != static::$guest_login))
			{
				$this->user = \DB::select_array(\Config::get('simpleauth.table_columns', array('*')))
					->where('username', '=', $username)
					->from(\Config::get('simpleauth.table_name'))
					->execute(\Config::get('simpleauth.db_connection'))->current();
			}

			// return true when login was verified, and either the hash matches or multiple logins are allowed
			if ($this->user and (\Config::get('simpleauth.multiple_logins', false) or $this->user['login_hash'] === $login_hash))
			{
				return true;
			}
		}

		// not logged in, do we have remember-me active and a stored user_id?
		elseif (static::$remember_me and $user_id = static::$remember_me->get('user_id', null))
		{
			return $this->force_login($user_id);
		}

		// no valid login when still here, ensure empty session and optionally set guest_login
		$this->user = \Config::get('simpleauth.guest_login', true) ? static::$guest_login : false;
		\Session::delete('username');
		\Session::delete('login_hash');

		return false;
	}

	/**
	 * Check the user exists
	 *
	 * @return  bool
	 */
	public function validate_user($username_or_email = '', $password = '')
	{
		$username_or_email = trim($username_or_email) ?: trim(\Input::post(\Config::get('simpleauth.username_post_key', 'username')));
		$password = trim($password) ?: trim(\Input::post(\Config::get('simpleauth.password_post_key', 'password')));

		if (empty($username_or_email) or empty($password))
		{
			return false;
		}

		$user = \DB::select_array(\Config::get('simpleauth.table_columns', array('*')))
			->from(\Config::get('simpleauth.table_name'));

		switch (\Config::get('auth.login_type', 'both'))
		{
			case "username":
				$user->where('username', '=', $username_or_email);
				break;

			case "email":
				$user->where('email', '=', $username_or_email);
				break;

			default:
				$user->where('username', '=', $username_or_email)
					->or_where('email', '=', $username_or_email);
		}

		$user = $user->execute(\Config::get('simpleauth.db_connection'))->current();

		if ($user)
		{
			$password = $this->hash_password($password.$user['salt']);
			if ($password === $user['password'])
			{
				return $user;
			}
		}

		return false;
	}

	/**
	 * Login user
	 *
	 * @param   string
	 * @param   string
	 * @return  bool
	 */
	public function login($username_or_email = '', $password = '')
	{
		if ( ! ($this->user = $this->validate_user($username_or_email, $password)))
		{
			$this->user = \Config::get('simpleauth.guest_login', true) ? static::$guest_login : false;
			\Session::delete('username');
			\Session::delete('login_hash');
			return false;
		}

		// register so Auth::logout() can find us
		Auth::_register_verified($this);

		\Session::set('username', $this->user['username']);
		\Session::set('login_hash', $this->create_login_hash());
		\Session::instance()->rotate();
		return true;
	}

	/**
	 * Force login user
	 *
	 * @param   string
	 * @return  bool
	 */
	public function force_login($user_id = '')
	{
		if (empty($user_id))
		{
			return false;
		}

		$this->user = \DB::select_array(\Config::get('simpleauth.table_columns', array('*')))
			->where_open()
			->where('id', '=', $user_id)
			->where_close()
			->from(\Config::get('simpleauth.table_name'))
			->execute(\Config::get('simpleauth.db_connection'))
			->current();

		if ($this->user == false)
		{
			$this->user = \Config::get('simpleauth.guest_login', true) ? static::$guest_login : false;
			\Session::delete('username');
			\Session::delete('login_hash');
			return false;
		}

		// store the logged-in user and it's hash in the session
		\Session::set('username', $this->user['username']);
		\Session::set('login_hash', \Fuel::$is_cli ? $this->user['login_hash'] : $this->create_login_hash());

		// and rotate the session id, we've elevated rights
		\Session::instance()->rotate();

		// register so Auth::logout() can find us
		Auth::_register_verified($this);

		return true;
	}

	/**
	 * Logout user
	 *
	 * @return  bool
	 */
	public function logout()
	{
		$this->user = \Config::get('simpleauth.guest_login', true) ? static::$guest_login : false;
		\Session::delete('username');
		\Session::delete('login_hash');
		return true;
	}

	/**
	 * Create new user
	 *
	 * @param   string
	 * @param   string
	 * @param   string  must contain valid email address
	 * @param   int     group id
	 * @param   Array
	 * @return  bool
	 */
	public function create_user($username, $password, $email, $group = 1, Array $profile_fields = array())
	{
		$username = trim($username);
		$password = trim($password);
		$email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);

		if (empty($username) or empty($password) or empty($email))
		{
			throw new \SimpleUserUpdateException('Username, password or email address is not given, or email address is invalid', 1);
		}

		// check for duplicates
		$same_users = \DB::select_array(\Config::get('simpleauth.table_columns', array('*')))
			->where('username', '=', $username)
			->from(\Config::get('simpleauth.table_name'))
			->execute(\Config::get('simpleauth.db_connection'));

		if ($same_users->count() > 0)
		{
			throw new \SimpleUserUpdateException('Username already exists', 3);
		}

		$same_users = \DB::select_array(\Config::get('simpleauth.table_columns', array('*')))
			->where('email', '=', $email)
			->from(\Config::get('simpleauth.table_name'))
			->execute(\Config::get('simpleauth.db_connection'));

		if ($same_users->count() > 0)
		{
				throw new \SimpleUserUpdateException('Email address already exists', 2);
		}

		// generate a salt for this user
		$salt = bin2hex(random_bytes(8));

		$user = array(
			'username'        => (string) $username,
			'password'        => $this->hash_password((string) $password . $salt),
			'salt'            => $salt,
			'email'           => $email,
			'group'           => (int) $group,
			'profile_fields'  => serialize($profile_fields),
			'last_login'      => 0,
			'login_hash'      => '',
			'created_at'      => \Date::forge()->get_timestamp(),
		);
		$result = \DB::insert(\Config::get('simpleauth.table_name'))
			->set($user)
			->execute(\Config::get('simpleauth.db_connection'));

		return ($result[1] > 0) ? $result[0] : false;
	}

	/**
	 * Update a user's properties
	 * Note: to update password the old password must be passed as old_password
	 *
	 * @param   Array  properties to be updated including profile fields
	 * @param   string username, email, or null for the current user
	 * @return  bool
	 */
	public function update_user($values, $username_or_email = null)
	{
		if (empty($username_or_email))
		{
			$username_or_email = $this->user['username'];
		}

		$user = \DB::select_array(\Config::get('simpleauth.table_columns', array('*')))
			->from(\Config::get('simpleauth.table_name'));

		switch (\Config::get('auth.login_type', 'both'))
		{
			case "username":
				$user->where('username', '=', $username_or_email);
				break;

			case "email":
				$user->where('email', '=', $username_or_email);
				break;

			default:
				$user->where('username', '=', $username_or_email)
					->or_where('email', '=', $username_or_email);
		}

		$current_values = $user->execute(\Config::get('simpleauth.db_connection'))->current();

		// updating the current user?
		$current_user = $current_values['id'] == $this->user['id'];

		if (empty($current_values))
		{
			throw new \SimpleUserUpdateException('User not found', 4);
		}

		$update = array();

		if (array_key_exists('password', $values))
		{
			if (empty($values['old_password'])
				or $current_values->get('password') != $this->hash_password(trim($values['old_password']).$current_values->get('salt')))
			{
				throw new \SimpleUserWrongPassword('Old password is invalid');
			}

			$password = trim(strval($values['password']));
			if ($password === '')
			{
				throw new \SimpleUserUpdateException('Password can\'t be empty.', 6);
			}

			$salt = bin2hex(random_bytes(8));
			$update['password'] = $this->hash_password(trim($password).$salt);
			$update['salt'] = $salt;
			unset($values['password']);
		}

		if (array_key_exists('old_password', $values))
		{
			unset($values['old_password']);
		}

		if (array_key_exists('email', $values))
		{
			$email = filter_var(trim($values['email']), FILTER_VALIDATE_EMAIL);
			if ( ! $email)
			{
				throw new \SimpleUserUpdateException('Email address is not valid', 7);
			}
			$matches = \DB::select()
				->where('email', '=', $email)
				->where('id', '!=', $current_values[0]['id'])
				->from(\Config::get('simpleauth.table_name'))
				->execute(\Config::get('simpleauth.db_connection'));
			if (count($matches))
			{
				throw new \SimpleUserUpdateException('Email address is already in use', 11);
			}
			$update['email'] = $email;
			unset($values['email']);
		}

		if (array_key_exists('group', $values))
		{
			if (is_numeric($values['group']))
			{
				$update['group'] = (int) $values['group'];
			}
			unset($values['group']);
		}

		if ( ! empty($values))
		{
			$profile_fields = @unserialize($current_values->get('profile_fields')) ?: array();
			foreach ($values as $key => $val)
			{
				if ($val === null)
				{
					unset($profile_fields[$key]);
				}
				else
				{
					$profile_fields[$key] = $val;
				}
			}
			$update['profile_fields'] = serialize($profile_fields);
		}

		$update['updated_at'] = \Date::forge()->get_timestamp();

		$affected_rows = \DB::update(\Config::get('simpleauth.table_name'))
			->set($update)
			->where('id', '=', $current_values['id'])
			->execute(\Config::get('simpleauth.db_connection'));

		// Refresh user
		if ($this->user['id'] == $current_values['id'])
		{
			$this->user = \DB::select_array(\Config::get('simpleauth.table_columns', array('*')))
				->where('id', '=', $current_values['id'])
				->from(\Config::get('simpleauth.table_name'))
				->execute(\Config::get('simpleauth.db_connection'))->current();
		}

		// we might have changed the username, this prevents the current
		// user being logged logged out due to a username mismatch
		if ($current_user)
		{
			\Session::set('username', $this->user['username']);
		}

		return $affected_rows > 0;
	}

	/**
	 * Change a user's password
	 *
	 * @param   string
	 * @param   string
	 * @param   string  username or email, or null for current user
	 * @return  bool
	 */
	public function change_password($old_password, $new_password, $username_or_email = null)
	{
		try
		{
			return (bool) $this->update_user(array('old_password' => $old_password, 'password' => $new_password), $username_or_email);
		}
		// Only catch the wrong password exception
		catch (SimpleUserWrongPassword $e)
		{
			return false;
		}
	}

	/**
	 * Generates new random password, sets it for the given username or email address, and returns the
	 * new password. To be used for resetting a user's forgotten password.
	 *
	 * @param   string  $username_or_email
	 * @return  string
	 */
	public function reset_password($username_or_email)
	{
		$user =  \DB::select()
			->from(\Config::get('simpleauth.table_name'))
			->limit(1);

		switch (\Config::get('auth.login_type', 'both'))
		{
			case "username":
				$user->where('username', '=', $username_or_email);
				break;

			case "email":
				$user->where('email', '=', $username_or_email);
				break;

			default:
				$user->where('username', '=', $username_or_email)
					->or_where('email', '=', $username_or_email);
		}

		$user = $user->execute(\Config::get('simpleauth.db_connection'))->current();

		if ($user)
		{
			// generate a new salt for this user
			$salt = bin2hex(random_bytes(8));

			$new_password = \Str::random('alnum', 8);
			$password = $this->hash_password($new_password . $salt);

			$affected_rows = \DB::update(\Config::get('simpleauth.table_name'))
			->set(array('password' => $password, 'salt' => $salt))
			->where('id', '=', $user['id'])
			->execute(\Config::get('simpleauth.db_connection'));
		}

		if ( ! $user or ! $affected_rows)
		{
			throw new \SimpleUserUpdateException('Failed to reset password, user was invalid.', 8);
		}

		return $password;
	}

	/**
	 * Deletes a given user, identified by username or email address
	 *
	 * @param   string
	 * @return  bool
	 */
	public function delete_user($username_or_email)
	{
		if (empty($username_or_email))
		{
			throw new \SimpleUserUpdateException('Cannot delete user with empty username', 9);
		}

		$user = \DB::delete(\Config::get('simpleauth.table_name'));

		switch (\Config::get('auth.login_type', 'both'))
		{
			case "username":
				$user->where('username', '=', $username_or_email);
				break;

			case "email":
				$user->where('email', '=', $username_or_email);
				break;

			default:
				$user->where('username', '=', $username_or_email)
					->or_where('email', '=', $username_or_email);
		}

		$affected_rows = $user->execute(\Config::get('simpleauth.db_connection'));

		return $affected_rows > 0;
	}

	/**
	 * Creates a temporary hash that will validate the current login
	 *
	 * @return  string
	 */
	public function create_login_hash()
	{
		if (empty($this->user))
		{
			throw new \SimpleUserUpdateException('User not logged in, can\'t create login hash.', 10);
		}

		$last_login = \Date::forge()->get_timestamp();
		$login_hash = sha1(\Config::get('simpleauth.login_hash_salt').$this->user['username'].$last_login);

		\DB::update(\Config::get('simpleauth.table_name'))
			->set(array('last_login' => $last_login, 'login_hash' => $login_hash))
			->where('id', '=', $this->user['id'])
			->execute(\Config::get('simpleauth.db_connection'));

		$this->user['login_hash'] = $login_hash;

		return $login_hash;
	}

	/**
	 * Get the user's ID
	 *
	 * @return  Array  containing this driver's ID & the user's ID
	 */
	public function get_user_id()
	{
		if (empty($this->user))
		{
			return false;
		}

		return array($this->id, (int) $this->user['id']);
	}

	/**
	 * Get the user's groups
	 *
	 * @return  Array  containing the group driver ID & the user's group ID
	 */
	public function get_groups()
	{
		if (empty($this->user))
		{
			return false;
		}

		return array(array('Simplegroup', $this->user['group']));
	}

	/**
	 * Getter for user data
	 *
	 * @param  string  name of the user field to return
	 * @param  mixed  value to return if the field requested does not exist
	 *
	 * @return  mixed
	 */
	public function get($field, $default = null)
	{
		if (isset($this->user[$field]))
		{
			return $this->user[$field];
		}
		elseif (isset($this->user['profile_fields']))
		{
			return $this->get_profile_fields($field, $default);
		}

		return $default;
	}

	/**
	 * Get the user's emailaddress
	 *
	 * @return  string
	 */
	public function get_email()
	{
		return $this->get('email', false);
	}

	/**
	 * Get the user's screen name
	 *
	 * @return  string
	 */
	public function get_screen_name()
	{
		if (empty($this->user))
		{
			return false;
		}

		return $this->user['username'];
	}

	/**
	 * Get the user's profile fields
	 *
	 * @return  Array
	 */
	public function get_profile_fields($field = null, $default = null)
	{
		if (empty($this->user))
		{
			return false;
		}

		if (isset($this->user['profile_fields']))
		{
			is_array($this->user['profile_fields']) or $this->user['profile_fields'] = (@unserialize($this->user['profile_fields']) ?: array());
		}
		else
		{
			$this->user['profile_fields'] = array();
		}

		return is_null($field) ? $this->user['profile_fields'] : \Arr::get($this->user['profile_fields'], $field, $default);
	}

	/**
	 * Extension of base driver method to default to user group instead of user id
	 */
	public function has_access($condition, $driver = null, $user = null)
	{
		if (is_null($user))
		{
			$groups = $this->get_groups();
			$user = reset($groups);
		}
		return parent::has_access($condition, $driver, $user);
	}

	/**
	 * Extension of base driver because this supports a guest login when switched on
	 */
	public function guest_login()
	{
		return \Config::get('simpleauth.guest_login', true);
	}
}

// end of file simpleauth.php
