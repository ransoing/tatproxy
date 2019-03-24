<?php

/**
 * The high-level code for the contactSearch API call.
 * See index.php for usage details.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );
require_once( '../api-core-functions.php' );

// process the GET parameters
if ( !isset($_GET['email']) || !isset($_GET['phone']) ) {
    errorExit( 400, 'You must define both GET parameters "email" and "phone".' );
}


/**
 * Run this function when all salesforce http requests succeed
 */
$handleRequestSuccess = function( $response ) {
    http_response_code( 200 );
    echo json_encode( (object)array( 'salesforceId' => $response ), JSON_PRETTY_PRINT );
};

$makeRequest = function() {
    global $apiFunctions;
    return $apiFunctions['contactSearch']( $_GET['email'], $_GET['phone'] );
};

// make the request.
makeSalesforceRequestWithTokenExpirationCheck( $makeRequest, $handleRequestSuccess, $handleRequestFailure );

$loop->run();
