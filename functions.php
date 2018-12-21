<?php
$config = json_decode( @file_get_contents(__DIR__ . '/config.json') );

// Gets the status of the connectivity to the database.
// returns an integer:
// 0: OK connection
// 1: No config file
// 2: No credentials provided
// 3: MySQL not found
// 4: Invalid credentials
// 5: Unexpected error
function getDBStatus() {
	$config = getConfig();

	if ( empty($config) || empty($config->mysql) ) {
		return 1;
	}
	$db = $config->mysql;
	if ( empty($db->username) || empty($db->password) || empty($db->databaseName) ) {
		return 2;
	}
	// test the connection to the mysql db, which contains user login data.
	@$mysqli = new mysqli( 'localhost', $db->username, $db->password, $db->databaseName );
	if ( !$mysqli->connect_errno ) {
		return 0;
	}
	switch( $mysqli->connect_errno ) {
		case 2002:
			return 3;
			break;
		case 1227:
		case 1698:
		case 1044:
		case 1045:
			return 4;
			break;
		default:
			return 5;
	}
}

// Gets the status of the connectivity to salesforce.
// returns an integer:
// 0: OK connection
// 1: No config file
// 2: Important data not defined in config file
// 3: Not authenticated
// 4: Authentication isn't working
// 5: Unexpected error
function getSFStatus() {
	$config = getConfig();

	if ( empty($config) || empty($config->salesforce) ) {
		return 1;
	}
	$sf = $config->salesforce;
	if ( empty($sf->authSuccessURL) || empty($sf->consumerSecret) || empty($sf->consumerKey) || empty($sf->APIName) ) {
		return 2;
	}
	// @@??
	// test to see if there is an auth key
	// test to see if the auth key works
	return 3;
}

function getConfig() {
	global $config;
	return $config;
}