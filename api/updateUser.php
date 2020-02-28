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

addToLog( 'command: updateUser. POST data received:', $postData );

// map POST data to salesforce fields
$sfData = array();
if ( isset($postData->coordinatorId) ) {
    $sfData['TAT_App_Team_Coordinator__c'] = $postData->coordinatorId;
}
if ( isset($postData->hasWatchedTrainingVideo) ) {
    $sfData['TAT_App_Has_Watched_Training_Video__c'] = $postData->hasWatchedTrainingVideo;
}
if ( isset($postData->trainingVideoLastWatchedDate) ) {
    $sfData['TAT_App_Training_Video_Last_Watched_Date__c'] = $postData->trainingVideoLastWatchedDate;
}
if ( isset($postData->trainingVideoRequiredForTeam) ) {
    $sfData['TAT_App_Team_Must_Watch_Training_Video__c'] = $postData->trainingVideoRequiredForTeam;
}


// Verify that the user has a Contact object
getSalesforceContactID( $firebaseUid )->then( function($contactId) use($sfData, $postData) {
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($contactId, $sfData) {
        logSection( 'Updating Contact with new info' );
        return salesforceAPIPatchAsync( 'sobjects/Contact/' . $contactId, $sfData );
    })->then( function() use ($contactId, $postData) {
        if ( !isset($postData->coordinatorId) ) {
            return true;
        }

        // add the user to the new team lead's campaign, but only if the team lead has exactly one active campaign.
        // otherwise just silently don't add the user to any campaigns
        logSection( 'Adding the Contact to the team lead\'s campaign' );
        return getActiveCampaigns( $postData->coordinatorId )->then( function($campaigns) use ($contactId) {
            if ( sizeof($campaigns) === 1 ) {
                return addContactToCampaign( $contactId, $campaigns[0]->Id );
            }
        });
    });
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);

$loop->run();
