<?php
$config = null;
$sfAuth = null;

$noConfigInstructions = 'Copy <code>config-sample.json</code> as <code>config.json</code> on the server and replace the sample values with real ones.';


// Database connectivity statuses and error messages
$dbStatuses = [
	0 => [
		'code' => 0,
		'error' => false
	],
	1 => [
		'code' => 1,
		'error' => 'No config file found.',
		'instructions' => $noConfigInstructions
	],
	2 => [
		'code' => 2,
		'error' => 'Database access credentials not defined.',
		'instructions' => 'Edit <code>config.json</code> and define a username, password, and database name for the MySQL connection.'
	],
	3 => [
		'code' => 3,
		'error' => 'MySQL service unavailable.',
		'instructions' => ''
	],
	4 => [
		'code' => 4,
		'error' => 'Database access credentials are invalid.',
		'instructions' => 'Ensure that the MySQL connection credentials in <code>config.json</code> are correct, '
			. 'and that the defined database exists. When creating the user in MySQL, you may need to explicitly '
			. 'set the hostname to <code>localhost</code> rather than <code>%</code>.'
	],
	5 => [
		'code' => 5,
		'error' => 'Insufficient privileges to edit database tables.',
		'instructions' => ''
	],
	6 => [
		'code' => 6,
		'error' => 'Unexpected error.',
		'instructions' => ''
	]
];

/**
 * Gets the status of the connectivity to the database.
 * Returns an associative array with keys 'error' and 'instructions'.
 */
function getDBStatus() {
	$config = getConfig();
	global $dbStatuses;

	if ( empty($config) || empty($config->mysql) ) {
		return $dbStatuses[1];
	}
	$db = $config->mysql;
	if ( empty($db->username) || empty($db->password) || empty($db->databaseName) ) {
		return $dbStatuses[2];
	}
	// test the connection to the mysql db, which contains user login data.
	@$mysqli = new mysqli( 'localhost', $db->username, $db->password, $db->databaseName );
	if ( !$mysqli->connect_errno ) {
		// check if the expected table exists
		$tables = $mysqli->query( "SHOW TABLES LIKE 'users'" );
		if ( $tables->num_rows === 0 ) {
			// attempt to create the table
			$madeTable = $mysqli->query( "CREATE TABLE `".$db->databaseName."`.`users` ( "
				. "`id` INT UNSIGNED NOT NULL AUTO_INCREMENT , "
				. "`salesforce_link` VARCHAR(50) NULL , "
				. "`auth_email` VARCHAR(100) NOT NULL , "
				. "`auth_token` VARCHAR(50) NOT NULL , "
				. "PRIMARY KEY (`id`)) ENGINE = InnoDB;"
			);
			if ( !$madeTable ) {
				return $dbStatuses[5];
			}
		}

		return $dbStatuses[0];
	}
	switch( $mysqli->connect_errno ) {
		case 2002:
			return $dbStatuses[3];
			break;
		case 1227:
		case 1698:
		case 1044:
		case 1045:
			return $dbStatuses[4];
			break;
		default:
			return $dbStatuses[6];
	}
}


// salesforce connectivity statuses and error messages
$sfConfigInstructions = 'The salesforce connection vlaues must match the values given for the Connected App titled "TAT Mobile App". '
	. 'This can be found in the <a href="https://success.salesforce.com/answers?id=9063A000000DbnVQAS" target="_blank">App Manager</a>.';
$sfAuthInstructions = 'A read-only account is ideal. The account only needs read-access to the TAT app user data.'
	. '<br><b>Do NOT authenticate with an admin account.</b>';

$sfStatuses = [
	0 => [
		'code' => 0,
		'error' => false
	],
	1 => [
		'code' => 1,
		'error' => 'No config file found.',
		'instructions' => $noConfigInstructions . '<br><br>' . $sfConfigInstructions
	],
	2 => [
		'code' => 2,
		'error' => 'Salesforce API access credentials not defined.',
		'instructions' => 'Edit <code>config.json</code> and define a callback URL, consumer secret, and consumer key. ' . $sfConfigInstructions
	],
	3 => [
		'code' => 3,
		'error' => 'Proxy hasn\'t been authenticated.',
		'instructions' => 'Please authenticate using a Salesforce account with restricted privileges. ' . $sfAuthInstructions
	],
	4 => [
		'code' => 4,
		'error' => 'Authentication is invalid or has expired.',
		'instructions' => 'Please re-authenticate using a Salesforce account with restricted privileges. ' . $sfAuthInstructions
	],
	5 => [
		'code' => 5,
		'error' => 'Unexpected error.',
		'instructions' => ''
	]
];

/**
 * Gets the status of the connectivity to salesforce.
 * Returns an associative array with keys 'error' and 'instructions'.
 */
function getSFStatus() {
	$config = getConfig();
	global $sfStatuses;
	unset( $sfStatuses[5]['errorDetails'] );

	// check whether salesforce connection config exists
	if ( empty($config) || empty($config->salesforce) ) {
		return $sfStatuses[1];
	}
	// check whether crucial info is defined in the config
	$sf = $config->salesforce;
	if ( empty($sf->authSuccessURL) || empty($sf->consumerSecret) || empty($sf->consumerKey) || empty($sf->APIName) ) {
		return $sfStatuses[2];
	}
	// check whether the proxy has authentication data
	$sfAuth = getSFAuth();
	// refresh_token may only be needed if "Configure ID Token" is checked in the Salesforce Connected App settings?
	if ( empty($sfAuth) || /* empty($sfAuth->refresh_token) || */ empty($sfAuth->instance_url) || empty($sfAuth->access_token) ) {
		return $sfStatuses[3];
	}
	// check whether the auth key works
	$apiResponse = apiGet( '' );
	if ( $apiResponse['error'] ) {
		$sfStatuses[5]['errorDetails'] = $apiResponse;
		return $sfStatuses[5];
	}
	if ( $apiResponse['httpCode'] !== 200 ) {
		return $sfStatuses[4];
	}

	// no errors.
	return $sfStatuses[0];
}


/** Gets the data contained in config.json */
function getConfig() {
	global $config;
	if ( !isset($config) || !$config ) {
		$config = json_decode( @file_get_contents(__DIR__ . '/config.json') );
	}
	return $config;
}

/** Gets the data contained in sf-auth.json */
function getSFAuth() {
	global $sfAuth;
	if ( !isset($sfAuth) || !$sfAuth ) {
		$sfAuth = json_decode( @file_get_contents(__DIR__ . '/sf-auth.json') );
	}
	return $sfAuth;
}

/**
 * Executes a curl handler and returns the result as an assoc object, with 'httpCode' and 'content'
 */
function curlExecAndFormat( $curlHandler ) {
	// execute a curl request and format it into a sensible object
	curl_setopt( $curlHandler, CURLOPT_RETURNTRANSFER, true );
	$response = curl_exec( $curlHandler );
	if ( $response !== false ) {
		return array(
			'httpCode' => curl_getinfo( $curlHandler, CURLINFO_HTTP_CODE ),
			'content' => $response,
			'error' => false
		);
	} else {
		return array( 'error' => curl_error($curlHandler) );
	}
}

/**
 * Makes a POST request. This is a blocking function.
 * `$url` is a string, and `$data` is an associative array of keys and values to send in the POST request.
 * Returns an array with 'error' on error, or an array with 'httpCode' and 'content'.
 */
function post( $url, $data ) {
	// use cURL to make the request, so we can get the response content when the response is not 200
	/*
	// test response
	sleep( 1 );
	return array(
		'httpCode' => 200,
		'content' => '{"id":"https://login.salesforce.com/id/00Dx0000000BV7z/005x00000012Q9P","issued_at":"1278448101416","refresh_token":"5Aep8614iLM.Dq661ePDmPEgaAW9Oh_L3JKkDpB4xReb54_pZebnUG0h6Sb4KUVDpNtWEofWM39yg==","instance_url":"https://na78.salesforce.com/","signature":"CMJ4l+CCaPQiKjoOEwEig9H4wqhpuLSk4J2urAe+fVg=","access_token":"00Dx0000000BV7z!AR8AQP0jITN80ESEsj5EbaZTFG0RNBaT1cyWk7TrqoDjoNIWQ2ME_sTZzBjfmOE6zMHq6y8PIW4eWze9JksNEkWUl.Cju7m4"}'
	);
	*/

	// Create a connection
	$ch = curl_init( $url );
	// Setting our options
	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query($data) );
	// Get the response
	$response = curlExecAndFormat( $ch );
	curl_close( $ch );
	return $response;
}

/**
 * Makes a GET request to the salesforce API and returns an assoc array with 'httpCode' and 'content'.
 * Returns an array with 'error' if the request fails or if the response (expected to be json-formatted) cannot be parsed.
 */
function apiGet( $urlSegment, $data = array() ) {
	$sfAuth = getSFAuth();
	$url = $sfAuth->instance_url . '/services/data/v44.0/' . $urlSegment . '.json?' . http_build_query( $data );
	$ch = curl_init( $url );
	// add access token to header
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $sfAuth->access_token) );
	$response = curlExecAndFormat( $ch );
	if ( $response['error'] ) {
		return $response;
	}
	// parse json
	$response['content'] = json_decode( $response['content'] );
	if ( $response['content'] === null ) {
		return array( 'error' => 'Malformed json.' );
	}
	return $response;
}
