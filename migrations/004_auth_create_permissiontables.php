<?php

namespace Fuel\Migrations;

include __DIR__."/../normalizedrivertypes.php";

class Auth_Create_Permissiontables
{
	function up()
	{
		// get the drivers defined
		$drivers = normalize_driver_types();

		if (in_array('Ormauth', $drivers))
		{
			// get the tablename
			\Config::load('ormauth', true);
			$table = \Config::get('ormauth.table_name', 'users');

			// make sure the configured DB is used
			\DBUtil::set_connection(\Config::get('ormauth.db_connection', null));

			// table users_perms
			\DBUtil::create_table($table.'_permissions', array(
				'id' => array('type' => 'int', 'constraint' => 11, 'auto_increment' => true),
				'area' => array('type' => 'varchar', 'constraint' => 25),
				'permission' => array('type' => 'varchar', 'constraint' => 25),
				'description' => array('type' => 'varchar', 'constraint' => 255),
				'user_id' => array('type' => 'int', 'constraint' => 11, 'default' => 0),
				'created_at' => array('type' => 'int', 'constraint' => 11, 'default' => 0),
				'updated_at' => array('type' => 'int', 'constraint' => 11, 'default' => 0),
			), array('id'));

			// add a unique index on group and permission
			\DBUtil::create_index($table.'_permissions', array('area', 'permission'), 'permission', 'UNIQUE');
		}

		// reset any DBUtil connection set
		\DBUtil::set_connection(null);
	}

	function down()
	{
		// get the drivers defined
		$drivers = normalize_driver_types();

		if (in_array('Ormauth', $drivers))
		{
			// get the tablename
			\Config::load('ormauth', true);
			$table = \Config::get('ormauth.table_name', 'users');

			// make sure the configured DB is used
			\DBUtil::set_connection(\Config::get('ormauth.db_connection', null));

			// drop the admin_users_perms table
			\DBUtil::drop_table($table.'_permissions');
		}

		// reset any DBUtil connection set
		\DBUtil::set_connection(null);
	}
}
