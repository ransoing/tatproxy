<?php

/**
 * The high-level code for the startRegistration API call.
 * See index.php for usage details.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );
require_once( '../api-core-functions.php' );

// process the GET parameters
if ( !isset($_GET['pass']) ) {
    errorExit( 400, 'You must define the "pass" GET parameter.' );
}

if ( $_GET['pass'] === getConfig()->app->registrationPassword ) {
    http_response_code( 200 );
    echo json_encode( (object)array( 'success' => TRUE ), JSON_PRETTY_PRINT );
} else {
    $message = json_encode((object)array(
        'errorCode' => 'INCORRECT_PASSWORD',
        'message' => 'The password was incorrect.'
    ));
    errorExit( 400, $message );
}
