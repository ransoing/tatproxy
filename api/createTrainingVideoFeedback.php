<?php

/**
 * The high-level code for the createTrainingVideoFeedback API call.
 * See index.php for usage details.
 * 
 * Adds an Event activity on the user's Contact object in Salesforce, containing details on the completed survey.
 * Also Edits the user's Contact object to record that the user has watched the training videos.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );
require_once( '../api-core-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();
$now = date('c');
// map POST data to salesforce fields
// ... for the Event activity
$eventData = array(
    'Subject' =>  'TAT App Training Feedback response',
    'Description' => formatQAs(
        array( 'Do you feel confident in presenting the TAT PowerPoint presentation? / Do you feel equipped for your outreach?', $postData->feelsPrepared ? 'Yes' : 'No' ),
        array( 'What questions do you have for TAT staff?', $postData->questions )
    ),
    'StartDateTime' =>  $now,
    'EndDateTime' =>    $now
);
// ... for editing the Contact object
$contactData = array(
    'TAT_App_Has_Watched_Training_Video__c' => true
);

createNewSFObject( $firebaseUid, 'sobjects/Event/', $eventData, 'WhoId' )->then( function() use ($firebaseUid) {
    return getSalesforceContactID( $firebaseUid );
})->then( function($contactID) use($contactData) {
    // createNewSFObject already made a call to makeSalesforceRequestWithTokenExpirationCheck, so no need to do that again here
    return salesforceAPIPatchAsync( 'sobjects/Contact/' . $contactID, $contactData );
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);

$loop->run();
