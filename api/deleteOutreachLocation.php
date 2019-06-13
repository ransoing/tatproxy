<?php

/**
 * The high-level code for the deleteOutreachLocation API call.
 * See index.php for usage details.
 * 
 * Removes an Outreach Location object from salesforce.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();

// sanitize outreachLocationId by removing quotes
$locationId = str_replace( array("'", '"'), "", $postData->outreachLocationId );

// get the contact ID just to verify that this is a valid app user who is in salesforce
getSalesforceContactID( $firebaseUid )->then( function() use ($locationId) {
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($locationId) {
        return salesforceAPIDeleteAsync( 'sobjects/TAT_App_Outreach_Location__c/' . $locationId );
    });
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);

$loop->run();
