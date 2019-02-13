<?php

require_once( __DIR__ . '/vendor/autoload.php' );

$loop = \React\EventLoop\Factory::create();
$browser = new \Clue\React\Buzz\Browser( $loop );

$config = null;
$sfAuth = null;

$noConfigInstructions = 'Copy <code>config-sample.json</code> as <code>config.json</code> on the server and replace the sample values with real ones.';

// Statuses for firebase connection
$fbConfigInstructions = 'The API key can be found in the Firebase console, by clicking the "Web setup" button.';
$fbStatuses = [
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
		'error' => 'Firebase API key not defined.',
		'instructions' => 'Edit <code>config.json</code> on the server and add the Firebase API key (see <code>config-sample.json</code> for proper formatting). ' . $fbConfigInstructions
	],
	3 => [
		'code' => 3,
		'error' => 'Invalid Firebase API key.',
		'instructions' => 'Edit <code>config.json</code> on the server and replace the Firebase API key with a valid one. ' . $fbConfigInstructions
	],
	4 => [
		'code' => 4,
		'error' => 'Unexpected error.',
		'instructions' => ''
	]
];

/**
 * Gets the status of the connectivity to firebase.
 * Returns an associative array with keys 'error', 'instructions', 'code', and 'errorDetails' (for some errors).
 */
function getFirebaseStatus() {
	$config = getConfig();
	global $fbStatuses;
	unset( $fbStatuses[4]['errorDetails'] );

	// check whether firebase connection config exists
	if ( empty($config) ) { return $fbStatuses[1]; }
	// check whether crucial info is defined in the config
	if ( empty($config->firebase) || empty($config->firebase->apiKey) ) { return $fbStatuses[2]; }
	// make a call to firebase to see if the key is valid
	$apiResponse = firebaseAuthAPIPost( 'getAccountInfo', array('idToken'=>'intentionallyBogus') );

	if ( !$apiResponse['error'] && isset($apiResponse['content']) ) {
		// in the response content, expect a specific error regarding a bad ID token (because we don't have one right now).
		// Any other error indicates something wrong with the configuration.
		// check for indications of a bad API key.
		if ( @$apiResponse['content']->error->status === 'INVALID_ARGUMENT' ) {
			return $fbStatuses[3];
		}
		// check for indications of a bad ID token (which implies a good API key)
		if ( $apiResponse['content']->error->message === 'INVALID_ID_TOKEN' ) {
			return $fbStatuses[0];
		}
	}

	// unexpected error found. Return the whole response as error details.
	$fbStatuses[4]['errorDetails'] = $apiResponse;
	return $fbStatuses[4];
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
		'instructions' => 'Edit <code>config.json</code> on the server and define a callback URL, consumer secret, and consumer key (see <code>config-sample.json</code> for proper formatting). ' . $sfConfigInstructions
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
 * Returns an associative array with keys 'error', 'instructions', 'code', and 'errorDetails' (for some errors).
 */
function getSalesforceStatus() {
	$config = getConfig();
	global $sfStatuses;
	unset( $sfStatuses[5]['errorDetails'] );

	// check whether salesforce connection config exists
	if ( empty($config) ) { return $sfStatuses[1]; }
	// check whether crucial info is defined in the config
	if ( empty($config->salesforce) ) { return $sfStatuses[2]; }
	$sf = $config->salesforce;
	if ( empty($sf->authSuccessURL) || empty($sf->consumerSecret) || empty($sf->consumerKey) || empty($sf->APIName) ) {
		return $sfStatuses[2];
	}
	// check whether the proxy has authentication data
	$sfAuth = getSFAuth();
	if ( empty($sfAuth) || empty($sfAuth->refresh_token) || empty($sfAuth->instance_url) || empty($sfAuth->access_token) ) {
		return $sfStatuses[3];
	}
	// check whether the auth key works
	$apiResponse = salesforceAPIGet( '' );
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
	// use cURL to make the request (as opposed to file_get_contents), so we can get the response content when the response is not 200

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
 * Makes a POST request to the firebase API and returns an assoc array with 'httpCode' and 'content'.
 * Returns an array with 'error' if the request fails or if the response (expected to be json-formatted) cannot be parsed.
 */
function firebaseAuthAPIPost( $urlSegment, $data = array() ) {
	$config = getConfig();
	// build the URL, with the API key appended
	$url = 'https://www.googleapis.com/identitytoolkit/v3/relyingparty/' . $urlSegment . '?key=' . $config->firebase->apiKey;
	$response = post( $url, $data );
	
	if ( $response['error'] ) return $response;
	
	// parse json
	$response['content'] = json_decode( $response['content'] );
	if ( $response['content'] === null ) {
		return array( 'error' => 'Malformed json.' );
	}

	return $response;
}


/**
 * Makes a GET request to the salesforce API and returns an assoc array with 'httpCode' and 'content'.
 * Automatically refreshes the access token if needed.
 * Returns an array with 'error' if the request fails or if the response (expected to be json-formatted) cannot be parsed.
 */
function salesforceAPIGet( $urlSegment, $data = array(), $allowRefreshAuthToken = true ) {
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

	// check if the auth token has expired so we can refresh it
	if (
		$allowRefreshAuthToken &&
		$response['httpCode'] === 401 &&
		isset($response['content'][0]) &&
		isset($response['content'][0]->errorCode) &&
		$response['content'][0]->errorCode === 'INVALID_SESSION_ID'
	) {
		// get a new auth token using the refresh token
		$config = getConfig();
		
		$refreshResponse = post( 'https://login.salesforce.com/services/oauth2/token', array(
			'grant_type'	=> 'refresh_token',
			'refresh_token'	=> $sfAuth->refresh_token,
			'client_id'     => $config->salesforce->consumerKey,
			'client_secret' => $config->salesforce->consumerSecret,
			'format'		=> 'json'
		));
		if ( $refreshResponse['error'] || $refreshResponse['httpCode'] !== 200 ) {
			// refresh token didn't work, so return the original response
			return $response;
		} else {
			// save the new access token to disk and to the global variable
			global $sfAuth;
			$sfAuth->access_token = json_decode( $refreshResponse['content'] )->access_token;
			file_put_contents( __DIR__ . '/sf-auth.json', json_encode($sfAuth) );
			// try the call to the API again
			return salesforceAPIGet( $urlSegment, $data, false );
		}
	}

	return $response;
}
