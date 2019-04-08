<?php

/**
 * The high-level code for the getUserData API call.
 * See index.php for usage details.
 */

 /*
 @@ cleanup...
makeSalesforceRequestWithTokenExpirationCheck
: only takes one parameter, a function which returns a promise.

createNewSFObject
: no longer takes 4th parameter, onfail handler.
*/

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );
require_once( '../api-core-functions.php' );

// process the GET parameters
if ( !isset($_GET['parts']) ) {
    errorExit( 400, 'GET parameter "parts" not found.' );
}
$requestedParts = explode( ',', $_GET['parts'] );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$contactID = '';

// Get the ID of the Contact entry in salesforce
getSalesforceContactID( $firebaseUid )->then( function($contactID) {
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($contactID) {
        // make simultaneous requests to salesforce
        global $requestedParts, $apiFunctions;
        $promises = array();
        // call the appropriate API functions based on the requested parts passed through GET parameters
        foreach( $requestedParts as $part ) {
            // call the API function and store the promise
            $promise = $apiFunctions['getUserData'][$part]( $contactID );
            array_push( $promises, $promise );
        }
        // return an all-promise so the results of the request can be handled
        return \React\Promise\all( $promises );
    });
})->then( function($responses) {
    // all the request promises return an associative array. When these rpomises resolve, merge the arrays,
    // cast it to an object, convert it to JSON, and echo the output.
    $masterArray = array();
    foreach( $responses as $response ) {
        $masterArray = array_merge( $masterArray, $response );
    }
    http_response_code( 200 );
    echo json_encode( (object)$masterArray, JSON_PRETTY_PRINT );
})->otherwise(
    $handleRequestFailure
);

$loop->run();
