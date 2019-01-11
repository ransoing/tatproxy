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

// @@ for now, return some mock data. This will eventually need to be Salesforce data
$responseContent = (object)array(
    'volunteerType' => 'truckStopVolunteer',
    'hasWatchedTrainingVideo' => true,
    'hoursLogs' => array(
        (object)array(
            'taskDescription' => 'Handed out TAT flyers to every truck stop in Nebraska',
            'date' => '2018-11-29T00:00:00Z',
            'numHours' => 14
        ), (object)array(
            'taskDescription' => 'Convinced the manager at Love\'s to train 1000 employees.',
            'date' => '2018-11-15T00:00:00Z',
            'numHours' => 3
        )
    ),
    'incompletePostReports' => array(
        (object)array( 'title' => 'Some truck stop' ),
        (object)array( 'title' => 'Some other truck stop' )
    )
);
http_response_code( 200 );
echo json_encode( $responseContent );
