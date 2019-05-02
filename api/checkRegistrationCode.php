<?php

/**
 * The high-level code for the checkRegistrationCode API call.
 * See index.php for usage details.
 * 
 * Responds with whether the provided registration code is valid. The registration code is defined in `config.json`.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// process the GET parameters
if ( !isset($_GET['code']) ) {
    errorExit( 400, 'You must define the "code" GET parameter.' );
}

if ( $_GET['code'] === getConfig()->app->registrationPassword ) {
    http_response_code( 200 );
    echo json_encode( (object)array( 'success' => TRUE ), JSON_PRETTY_PRINT );
} else {
    $message = json_encode((object)array(
        'errorCode' => 'INCORRECT_REGISTRATION_CODE',
        'message' => 'The registration code was incorrect.'
    ));
    errorExit( 400, $message );
}
