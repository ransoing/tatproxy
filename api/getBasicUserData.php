<?php
require_once( '../functions.php' );
require_once( '../api-functions.php' );


/**
 * POST: /api/getBasicUserData
 * Gets a user's basic data from salesforce
 * POST Parameters:
 * firebaseIdToken: {string} - The user's Firebase login ID token, which is obtained after the user authenticates with Firebase.
 * 
 * Example:
 * URL: /api/getBasicUserData
 * POST data: 'firebaseIdToken=abcd1234'
 * 
 * Returns a JSON object containing some basic data on the user: name, volunteer type, and whether the user has watched the training video.
 * 
 * ```
 * {
 *      firstName: string,
 *      lastName: string,
 *      volunteerType: string,
 *      hasWatchedTrainingVideo: boolean
 * }
 * ```
 */


//@@ $firebaseUser = verifyFirebaseLogin();
// @@TODO: the salesforce contactID should be retrieved from the firebase db
$contactID = '0031N00001tVsAmQAK';
// $contactID = '003o000000LD6rLAAT'; // helen


// get volunteer type and whether the user has watched the training video
$contactResponse = salesforceAPIGet(
    "sobjects/Contact/${contactID}/",
    array('fields' => 'App_volunteer_type__c,App_has_watched_training_video__c,FirstName,LastName')
);
exitIfResponseHasError( $contactResponse );

// return a response in a format that the app expects
$responseContent = (object)array(
    'volunteerType' => $contactResponse['content']->App_volunteer_type__c,
    'hasWatchedTrainingVideo' => $contactResponse['content']->App_has_watched_training_video__c,
    'firstName' => $contactResponse['content']->FirstName,
    'lastName' => $contactResponse['content']->LastName
);
http_response_code( 200 );
echo json_encode( $responseContent, JSON_PRETTY_PRINT );
