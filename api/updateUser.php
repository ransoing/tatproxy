<?php

/**
 * The high-level code for the updateUser API call.
 * See index.php for usage details.
 * 
 * Updates the user's Contact object in Salesforce.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();

// map POST data to salesforce fields
$sfData = array(
    'TAT_App_Volunteer_Type__c' =>  $postData->volunteerType,
    'TAT_App_Materials_Address__c' => $postData->mailingAddress,
    'TAT_App_Materials_City__c' =>  $postData->mailingCity,
    'TAT_App_Materials_State__c' => $postData->mailingState,
    'TAT_App_Materials_Zip__c' =>   $postData->mailingZip,
    'TAT_App_Team_Coordinator__c' => $postData->coordinatorId
);

// Verify that the user has a Contact object
getSalesforceContactID( $firebaseUid )->then( function($contactID) use($sfData) {
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($contactID, $sfData) {
        return salesforceAPIPatchAsync( 'sobjects/Contact/' . $contactID, $sfData );
    });
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);

$loop->run();
