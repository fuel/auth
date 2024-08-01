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

namespace Fuel\Migrations;

include __DIR__."/../normalizedrivertypes.php";

class Auth_Update_Userindex
{
	function up()
	{
		// get the drivers defined
		$drivers = normalize_driver_types();

		if (in_array('Simpleauth', $drivers))
		{
			// get the tablename
			\Config::load('simpleauth', true);
			$table = \Config::get('simpleauth.table_name', 'users');

			// make sure the correct connection is used
			$this->dbconnection('simpleauth');
		}
		elseif (in_array('Ormauth', $drivers))
		{
			// get the tablename
			\Config::load('ormauth', true);
			$table = \Config::get('ormauth.table_name', 'users');

			// make sure the correct connection is used
			$this->dbconnection('ormauth');
		}

		// only do this if the user table does exist
		if (\DBUtil::table_exists($table))
		{
			try
			{
				// add a unique index on username
				\DBUtil::create_index($table, 'username', 'username', 'UNIQUE');

				// add a unique index on email
				\DBUtil::create_index($table, 'email', 'email', 'UNIQUE');

				// drop the old compound index
				\DBUtil::drop_index($table, 'username');
			}
			catch  (\Exception $e)
			{
				// index creation could fail in case of duplicate email and/or usernames
				// in which case the old index won't be dropped, and we'll continue
				// without the new indexes.
			}

			// add a salt column
			\DBUtil::add_fields($table, array(
				'salt' => array('after' => 'password', 'type' => 'char', 'constraint' => 16, 'null' => false, 'default' => ''),
			));

			\Cli::write('AUTH-012: A per-user password salt has been introduced. Ideally, users should reset their passwords so that a salt will be generated.', 'yellow');
		}

		// reset any DBUtil connection set
		$this->dbconnection(false);
	}

	function down()
	{
		\Cli::write('AUTH-012: A per-user password salt was added in this migration. This can not be reversed, there is no way to remove the salt from stored password hashes! ', 'yellow');
		return false;
	}

	/**
	 * check if we need to override the db connection for auth tables
	 */
	protected function dbconnection($type = null)
	{
		static $connection;

		switch ($type)
		{
			// switch to the override connection
			case 'simpleauth':
			case 'ormauth':
				if ($connection = \Config::get($type.'.db_connection', null))
				{
					\DBUtil::set_connection($connection);
				}
				break;

			// switch back to the configured migration connection, or the default one
			case false:
				if ($connection)
				{
					\DBUtil::set_connection(\Config::get('migrations.connection', null));
				}
				break;

			default:
				// noop
		}
	}
}
