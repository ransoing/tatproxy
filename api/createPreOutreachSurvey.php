<?php

/**
 * The high-level code for the createPreOutreachSurvey API call.
 * See index.php for usage details.
 * 
 * Adds a new object in Salesforce, which is linked to the user's Contact object via a Lookup field.
 * Also creates an Activity on the user's Contact object.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();
// map POST data to salesforce fields
$sfData = array(
    'Name__c' =>    $postData->locationName,
    'Type__c' =>    $postData->locationType,
    'Address_c' =>  $postData->locationAddress,
    'City__c' =>    $postData->locationCity,
    'State__c' =>   $postData->locationState,
    'Zip__c' =>     $postData->locationZip,
    'Date__c' =>    $postData->date
);

// @@ get a list of the volunteers who are on this user's team.

// @@ for each of those volunteers, and for the current user, create a new Volunteer Activity object.

// @@ For the current user, create an activity on his Contact detailing the info given in the survey.

createNewSFObject( $firebaseUid, 'sobjects/TAT_App_Volunteer_Activity__c/', $sfData, 'Contact__c' )->then(
    function( $response ) {
        // new id is $response->id
        echo '{"success": true}';
    },
    $handleRequestFailure
);

$loop->run();
