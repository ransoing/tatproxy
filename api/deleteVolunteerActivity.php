<?php

/**
 * The high-level code for the deleteVolunteerActivity API call.
 * See index.php for usage details.
 * 
 * Removes a Volunteer Activity object from salesforce.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();

// sanitize activityId by removing quotes
$activityId = str_replace( array("'", '"'), "", $postData->activityId );

getSalesforceContactID( $firebaseUid )->then( function($contactID) use ($postData, $activityId) {
    // get a list of volunteer activities for this user.
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($contactID, $activityId) {
        return getAllSalesforceQueryRecordsAsync( "SELECT Id FROM TAT_App_Volunteer_Activity__c WHERE Contact__c = '$contactID' AND Id = '$activityId'" );
    })->then( function( $activities ) use($activityId) {
        if ( sizeof($activities) == 0 ) {
            $message = json_encode((object)array(
                'errorCode' => 'INVALID_ACTIVITY_ID',
                'message' => 'There is no activity with that ID that belongs to the specified user.'
            ));
            errorExit( 400, $message );
        }

        // delete the thing!
        return salesforceAPIDeleteAsync( 'sobjects/TAT_App_Volunteer_Activity__c/' . $activityId );
    });
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);


$loop->run();
