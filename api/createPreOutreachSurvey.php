<?php

/**
 * The high-level code for the createPreOutreachSurvey API call.
 * See index.php for usage details.
 * 
 * Adds a new object in Salesforce, which is linked to the user's Contact object via a Lookup field.
 * Also creates an Event activity on the user's Contact object.
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

getSalesforceContactID( $firebaseUid )->then( function($contactID) use ($sfData) {
    // get a list of the volunteers who are on this user's team.
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($contactID) {
        return getAllSalesforceQueryRecordsAsync( "SELECT Id FROM Contact WHERE TAT_App_Team_Coordinator__c = '$contactID'" );
    })->then( function( $teamMembers ) use($contactID, $sfData) {
        // convert the data into a simple array
        $teamMemberIds = array( $contactID );
        foreach( $teamMembers as $member ) {
            array_push( $teamMemberIds, $member->Id );
        }
        
        // for everyone on the volunteer team, create a new Volunteer Activity object.
        $promises = array();
        foreach( $teamMemberIds as $memberId ) {
            // call the API function and store the promise
            $dataCopy = $sfData;
            $dataCopy['Contact__c'] = $memberId;
            salesforceAPIPostAsync( 'sobjects/TAT_App_Volunteer_Activity__c/', $dataCopy );
            array_push( $promises, createNewSFObject($firebaseUid, 'sobjects/TAT_App_Volunteer_Activity__c/', $dataCopy) );
        }
        return \React\Promise\all( $promises );
    });
})->then( function() {
    // @@ For the current user, create an Event activity on his Contact detailing the info given in the survey.
})->otherwise(
    $handleRequestFailure
);


$loop->run();
