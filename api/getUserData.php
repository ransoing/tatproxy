<?php
require_once( '../functions.php' );
require_once( '../api-functions.php' );

$postData = getPOSTData();

/**
 * POST: /api/getUserData
 * Gets a user's data from salesforce
 * POST Parameters:
 * firebaseIdToken: {string} - The user's Firebase login ID token, which is obtained after the user authenticates with Firebase.
 * 
 * Example:
 * URL: /api/getUserData
 * POST data: 'firebaseIdToken=abcd1234'
 * 
 * Returns a JSON object containing all the data on the user that the app needs.
 */

 // verify that the required parameters are present
 if ( !isset($postData->firebaseIdToken) ) {
    errorExit( 400, '`firebaseIdToken` must be present in the POST parameters.' );
 }

 // verify against Firebase that the ID token is valid (i.e. it represents a logged-in user)
 $firebaseResponse = firebaseAPIPost( 'getAccountInfo', array('idToken' => $postData->firebaseIdToken) );
 // check if there was an error with the request itself
if ( $firebaseResponse['error'] ) {
    errorExit( 400, "The request to Firebase failed to execute: " . $firebaseResponse['error'] );
}
// check if there was an error in the response from Firebase
if ( isset($firebaseResponse['content']->error) ) {
    errorExit( 400, "The request to Firebase returned with an error: " . $firebaseResponse['content']->error->message );
}

// @@ for now, return to the client the data from Firebase. This will eventually need to be Salesforce data
http_response_code( 200 );
echo json_encode( $firebaseResponse['content'] );
