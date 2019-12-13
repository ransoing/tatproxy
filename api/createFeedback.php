<?php

/**
 * The high-level code for the createTestimonial API call.
 * See index.php for usage details.
 * 
 * Adds an Event activity on the user's Contact object in Salesforce, containing details on the completed survey.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();
$now = date('c');

addToLog( 'command: createFeedback. POST data received:', $postData );

// map POST data to salesforce fields
$sfData = array(
    'Advice__c' => $postData->advice,
    'Best_Part__c' => $postData->bestPart,
    'Improvements__c' => $postData->improvements,
    'Gives_Anonymous_Permission__c' => $postData->givesAnonPermission,
    'Gives_Named_Permission__c' => $postData->givesNamePermission
);

if ( isset($postData->campaignId) && !empty($postData->campaignId) ) {
    $sfData['Campaign__c'] = $postData->campaignId;
}

logSection( 'Creating Feedback object in salesforce' );
createNewSFObject( $firebaseUid, 'sobjects/TAT_App_Feedback__c/', $sfData, 'Volunteer__c' )->then(
    function( $response ) {
        // new id is $response->id
        echo '{"success": true}';
    },
    $handleRequestFailure
);

$loop->run();
