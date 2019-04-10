<?php

/**
 * The high-level code for the contactSearch API call.
 * See index.php for usage details.
 * 
 * Searches Contact objects in Salesforce and returns the first one for which either
 * the given email matches any of the Contact's email fields, or the given phone number
 * matches any of the Contact's phone number fields.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );
require_once( '../api-core-functions.php' );

// process the GET parameters
if ( !isset($_GET['email']) || !isset($_GET['phone']) ) {
    errorExit( 400, 'You must define both GET parameters "email" and "phone".' );
}

// make the request.
makeSalesforceRequestWithTokenExpirationCheck( function() {
    global $apiFunctions;
    return $apiFunctions['contactSearch']( $_GET['email'], $_GET['phone'] );
})->then( function($response) {
    echo json_encode( (object)array( 'salesforceId' => $response ), JSON_PRETTY_PRINT );
})->otherwise(
    $handleRequestFailure
);

$loop->run();
