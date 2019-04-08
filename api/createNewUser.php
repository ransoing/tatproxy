<?php

/**
 * The high-level code for the createNewUser API call.
 * See index.php for usage details.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );
require_once( '../api-core-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();

// verify registration code
if ( $postData->registrationCode !== getConfig()->app->registrationPassword ) {
    $message = json_encode((object)array(
        'errorCode' => 'INCORRECT_REGISTRATION_CODE',
        'message' => 'The registration code was incorrect.'
    ));
    errorExit( 400, $message );
}

// either create a new Contact object, or update an existing one
if ( $postData->salesforceId ) {
    // update an existing contact object. First verify that the specified ContactID doesn't already have an associated FirebaseUID.
    
} else {
    // create a new Contact object

}

// $loop->run();
