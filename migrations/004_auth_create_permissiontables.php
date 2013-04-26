<?php

namespace Fuel\Migrations;

class Auth_Create_Permissiontables
{
	function up()
	{
		// get the driver used
		\Config::load('auth', true);

		if (\Config::get('auth.driver') == 'Ormauth')
		{
			// get the tablename
			\Config::load('ormauth', true);
			$table = \Config::get('ormauth.table_name', 'users');

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
	}

	function down()
	{
		// get the driver used
		\Config::load('auth', true);

		if (\Config::get('auth.driver') == 'Ormauth')
		{
			// get the tablename
			\Config::load('ormauth', true);
			$table = \Config::get('ormauth.table_name', 'users');

			// drop the admin_users_perms table
			\DBUtil::drop_table($table.'_permissions');
		}
	}
}
