<?php

/**
 * The high-level code for the createHoursLogEntry API call.
 * See index.php for usage details.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );
require_once( '../api-core-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();
// map POST data to salesforce fields
$sfData = array(
    'Description__c' =>  $postData->description,
    'Date__c' =>         $postData->date,
    'Num_Hours__c' =>    $postData->numHours
);

createNewSFObject( $firebaseUid, 'sobjects/TAT_App_Hours_Log_Entry__c/', $sfData, 'Contact__c' )->then(
    function( $response ) {
        // new id is $response->id
        echo '{"success": true}';
    },
    $handleRequestFailure
);

$loop->run();
