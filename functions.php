<?php

error_reporting(E_ALL | E_NOTICE); //@@##
ini_set('display_errors', TRUE);

require_once( __DIR__ . '/vendor/autoload.php' );

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Kreait\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

// $salesforceOAuthBase = 'https://login.salesforce.com/services/oauth2';// @@
$salesforceOAuthBase = 'https://test.salesforce.com/services/oauth2';

$loop = \React\EventLoop\Factory::create();
$browser = new \Clue\React\Buzz\Browser( $loop );

$config = null;
$sfAuth = null;

	
// set up the mailer
getConfig();
$mailerSetUp = true;
if (
	!isset($config->mailer) ||
	!isset($config->mailer->host) ||
	!isset($config->mailer->username) ||
	!isset($config->mailer->password) ||
	!isset($config->mailer->encryptionType) ||
	!isset($config->mailer->port) ||
	!isset($config->mailer->replyTo)
) {
	$mailerSetUp = false;
}

function sendMail( $to, $subject, $htmlBody, $debug = false ) {
	$config = getConfig();
	$mail = new PHPMailer( true );
	$mail->isSMTP();
	if ( $debug ) {
		$mail->SMTPDebug = 2;
	}
	$mail->Host       = $config->mailer->host;
	$mail->SMTPAuth   = true;
	$mail->Username   = $config->mailer->username;
	$mail->Password   = $config->mailer->password;
	$mail->SMTPSecure = $config->mailer->encryptionType;
	$mail->Port       = $config->mailer->port;

	$mail->setFrom( $config->mailer->username, 'TAT App Proxy' );
	$mail->addAddress( $to );
	$mail->addReplyTo( $config->mailer->replyTo );
	$mail->isHTML( true );
	$mail->Subject = $subject;
	$mail->Body = $htmlBody;
	
	try {
		$mail->send();
	} catch (Exception $e) {
		if ( $debug ) {
			echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
		}
	}
}

// verify the presence of app resource files
function resourceFilesAreSetUp() {
	$dir = __DIR__ . '/external-resources';
	$files = [ '/i18n/trx_en.json', '/i18n/trx_es.json', '/scripts/surveys.js', '/version' ];
	foreach( $files as $file ) {
		if ( !file_exists("{$dir}{$file}") ) {
			return false;
		}
	}
	return true;
}

// verify that things are set up for the notifications job to be run.
function getNotificationStatus() {
	// determine if the config has been set up right for notifications
	$config = getConfig();
	if ( empty($config) || empty($config->notifications ) || empty($config->notifications->cronSecret) ) {
		return [
			'error' => 'Cron secret not defined.',
			'instructions' => 'Edit <code>config.json</code> on the server and add the cron secret string (see <code>config-sample.json</code> for proper formatting).'
		];
	}
	// show an error if the notification job has never been run
	$filepath = __DIR__ . '/notifications-last-run';
	if ( !file_exists($filepath) ) {
		return [
			'error' => 'Notification job has not been run.',
			'instructions' => 'Schedule a cron job to make a daily POST request to <code>https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . 'run-notifications.php</code>.' .
				'Use the cron secret string (defined in <code>config.json</code>) as the POST body.'
		];
	}
	// show an error if it's been more than 25 hours since the notification job was last run
	$hoursDiff = round( ( time() - intval( file_get_contents($filepath) ) ) / 3600 );
	if ( $hoursDiff >= 25 ) {
		return [
			'error' => 'Notification job stopped running.',
			'instructions' => 'It has been ' . $hoursDiff . ' hours since the notification job last ran. Check that the cron job is running correctly.'
		];
	}
	
	return [
		'error' => false
	];
}

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

			// now check to see if the google service account is set up.
			try {
				@getSpecialRegistrationCodes();
			} catch ( \Exception $e ) {
				return [
					'code' => 5,
					'error' => $e->getMessage(),
					'instructions' => 'Go to \'Project settings\' in the Firebase console, then the \'Service accounts\' tab, and click \'Generate a new private key\'. Place the file as <code>google-service-account.json</code> in the same directory as <code>config.json</code> on the server. Ensure that the service account has privileges to read all Firebase data for the project.'
				];
			}

			// all is well.
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
$sfAuthInstructions = 'The account needs to read and write a large variety of objects.'
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
		'instructions' => 'Please authenticate using a Salesforce account with edit privileges for all objects. ' . $sfAuthInstructions
	],
	4 => [
		'code' => 4,
		'error' => 'Authentication is invalid or has expired.', // @@ send an email to someone to tell them to authenticate the proxy right now
		'instructions' => 'Please re-authenticate using a Salesforce account with edit privileges for all objects. ' . $sfAuthInstructions
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
	global $salesforceOAuthBase;
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
		
		$refreshResponse = post( "${salesforceOAuthBase}/token", array(
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


$firebaseMessaging;
$gServiceAccountCredentialsFilepath = __DIR__ . '/google-service-account.json';

function sendNotification( $title, $body, $data, $fcmDeviceTokens ) {
	global $firebaseMessaging, $gServiceAccountCredentialsFilepath;
	if ( !isset($firebaseMessaging) ) {
		// authenticate as the firebase service account
		$firebaseServiceAccount = Firebase\ServiceAccount::fromJsonFile( $gServiceAccountCredentialsFilepath );
		$firebaseMessaging = (new Firebase\Factory)->withServiceAccount( $firebaseServiceAccount )->createMessaging();
	}
	$message = CloudMessage::new()
		->withNotification( Notification::create( $title, $body ) )
		->withData( array(
			'notification_foreground' => true,
			'data' => json_encode( $data )
		));
	// sendMulticast can do up to 100 individual devices at a time, so we need to break up the list of devices into chunks of 100
	$fcmChunks = array_chunk( $fcmDeviceTokens, 100 );
	foreach( $fcmChunks as $fcmChunk ) {
		$firebaseMessaging->sendMulticast( $message, $fcmChunk );
	}
}
