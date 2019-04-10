<?php

/**
 * The high-level code for the createHoursLogEntry API call.
 * See index.php for usage details.
 * 
 * Adds an Event activity on the user's Contact object in Salesforce, containing details on the completed survey.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );
require_once( '../api-core-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();
$now = date('c');
// map POST data to salesforce fields
$sfData = array(
    'Subject' =>  'TAT App Testimonial/Feedback response',
    'Description' => formatQAs(
        array( 'What advice would you give another volunteer?', $postData->advice ),
        array( 'What was the best part of your experience?', $postData->bestPart ),
        array( 'What could have been improved about your experience?', $postData->improvements ),
        array( 'Do you give permission for TAT to anonymously quote you on social media or in reports?', $postData->givesAnonPermission ? 'Yes' : 'No' ),
        array( 'Do you give permission for TAT to use your name, organization, and quotes on social media and in reports?', $postData->givesNamePermission ? 'Yes' : 'No' )
    ),
    'StartDateTime' =>  $now,
    'EndDateTime' =>    $now
);

createNewSFObject( $firebaseUid, 'sobjects/Event/', $sfData, 'WhoId' )->then(
    function( $response ) {
        // new id is $response->id
        echo '{"success": true}';
    },
    $handleRequestFailure
);

$loop->run();
