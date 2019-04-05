<?php

/**
 * The high-level code for the getUserData API call.
 * See index.php for usage details.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );
require_once( '../api-core-functions.php' );

// process the GET parameters
if ( !isset($_GET['parts']) ) {
    errorExit( 400, 'GET parameter "parts" not found.' );
}
$requestedParts = explode( ',', $_GET['parts'] );


/**
 * Run this function when all salesforce http requests succeed
 */
$handleRequestSuccess = function( $responses ) {
    // all the request promises return an associative array. When these rpomises resolve, merge the arrays,
    // cast it to an object, convert it to JSON, and echo the output.
    $masterArray = array();
    foreach( $responses as $response ) {
        $masterArray = array_merge( $masterArray, $response );
    }
    http_response_code( 200 );
    echo json_encode( (object)$masterArray, JSON_PRETTY_PRINT );
};


/**
 * function to make simultaneous http requests to salesforce, but uses GET parameters
 * to only use the ones that are needed.
 */
$makeRequests = function() {
    global $contactID, $requestedParts, $apiFunctions;
    $promises = array();
    // call the appropriate API functions based on the requested parts passed through GET parameters
    foreach( $requestedParts as $part ) {
        // call the API function and store the promise
        $promise = $apiFunctions['getUserData'][$part]( $contactID );
        array_push( $promises, $promise );
    }
    // return an all-promise so the results of the request can be handled
    return \React\Promise\all( $promises );
};

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$contactID = '';

// Get the ID of the Contact entry in salesforce
getSalesforceContactID( $firebaseUid )->then(
    function( $retrievedContactID ) {
        global $contactID;
        return $contactID = $retrievedContactID;
    }
)->then(
    // Use the Contact ID to make all http requests. If any of them fail, check if the failure is due to
    // an expired token. If it is, refresh the token and try the requests again.
    function() use ($makeRequests, $handleRequestFailure, $handleRequestSuccess) {
        return makeSalesforceRequestWithTokenExpirationCheck( $makeRequests, $handleRequestSuccess, $handleRequestFailure );
    },
    $handleRequestFailure
);

$loop->run();
