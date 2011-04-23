<?php

return array(

	/**
	 * DB table name for the user table
	 */
	'table_name' => 'simpleusers',

	/**
	 * Groups as id => array(name => <string>, roles => <array>)
	 */
	'groups' => array(
		-1	=> array('name' => 'Banned', 'roles' => array('banned')),
		0	=> array('name' => 'Guests', 'roles' => array()),
		1	=> array('name' => 'Users', 'roles' => array('user')),
		50	=> array('name' => 'Moderators', 'roles' => array('user', 'moderator')),
		100	=> array('name' => 'Administrators', 'roles' => array('user', 'moderator', 'admin')),
	),

	/**
	 * Roles as name => array(location => rights)
	 */
	'roles' => array(
		'#'          => array('website' => array('read')), // default rights
		'banned'     => false,
		'user'       => array('comments' => array('create', 'read')),
		'moderator'  => array('comments' => array('update', 'delete')),
		'admin'      => array(
			'website'  => array('create', 'update', 'delete'),
			'admin'    => array('create', 'read', 'update', 'delete'),
		),
		'super'      => true,
	),

	/**
	 * Salt for the login hash
	 */
	'login_hash_salt' => 'put_some_salt_in_here',
);