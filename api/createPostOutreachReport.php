<?php

/**
 * The high-level code for the createPostOutreachReport API call.
 * See index.php for usage details.
 * 
 * Modifies a Volunteer Activity object associated with the user's Contact entry --
 * marks it as complete.
 * Also creates an Event activity on the user's Contact object.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();

// sanitize activityId by removing quotes
$activityId = str_replace( array("'", '"'), "", $postData->activityId );

// verify that the Volunteer Activity belongs to the user
getSalesforceContactID( $firebaseUid )->then( function($contactID) use ($postData, $activityId) {
    // get a list of volunteer activities for this user.
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($contactID, $activityId) {
        return getAllSalesforceQueryRecordsAsync( "SELECT Id, Name__c, Type__c, Address__c, City__c, State__c, Zip__c FROM TAT_App_Volunteer_Activity__c WHERE Contact__c = '$contactID' AND Id = '$activityId'" );
    })->then( function( $activities ) use($activityId, $postData, $contactID) {
        if ( sizeof($activities) == 0 ) {
            $message = json_encode((object)array(
                'errorCode' => 'INVALID_ACTIVITY_ID',
                'message' => 'There is no activity with that ID that belongs to the specified user.'
            ));
            errorExit( 400, $message );
        }

        // modify the activity
        $params = array( 'Completed__c' => true );
        return salesforceAPIPatchAsync( 'sobjects/TAT_App_Volunteer_Activity__c/' . $activityId, $params )->then( function() use($postData, $contactID, $activities) {
            // create an Event on the user's Contact
            // convert POST data to Event text
            $activity = $activities[0]; // there will only be one since we queried by ID
            $type = getLocationType( $activity->Type__c );
            $now = date('c');
            $eventData = array(
                'Subject' =>  'TAT App Post-Outreach Report response',
                'Description' => formatQAs(
                    array( 'What location did you visit?', implode("\n", array(
                        "{$activity->Name__c} ({$type})",
                        $activity->Address__c,
                        "{$activity->City__c}, {$activity->State__c} {$activity->Zip__c}"
                    ))),
                    array( 'What were you able to accomplish?', $postData->accomplishments ),
                    array( 'Do you plan to follow up with your contact?', $postData->willFollowUp ? 'Yes' : 'No' ),
                    array( 'When will you follow up?', $postData->followUpDate )
                ),
                'StartDateTime' =>  $now,
                'EndDateTime' =>    $now,
                'WhoId' => $contactID
            );
    
            return salesforceAPIPostAsync( 'sobjects/Event/', $eventData );
        });
    });
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);


$loop->run();
