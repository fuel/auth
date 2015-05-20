<?php

namespace Fuel\Migrations;

include __DIR__."/../normalizedrivertypes.php";

class Auth_Add_Permissionsfilter
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

			// modify the filter field to add the 'remove' filter
			\DBUtil::modify_fields($table.'_roles', array(
				'filter' => array('type' => 'enum', 'constraint' => "'', 'A', 'D', 'R'", 'default' => ''),
			));
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
			\DBUtil::set_connection($connection = \Config::get('ormauth.db_connection', null));

			// modify the filter field to add the 'remove' filter
			\DB::update($table.'_roles')->set(array('filter' => 'D'))->where('filter', '=', 'R')->execute($connection);

			\DBUtil::modify_fields($table.'_roles', array(
				'filter' => array('type' => 'enum', 'constraint' => "'', 'A', 'D'", 'default' => ''),
			));
		}

		// reset any DBUtil connection set
		\DBUtil::set_connection(null);
	}
}
