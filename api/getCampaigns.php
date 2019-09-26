<?php

/**
 * The high-level code for the getCampaigns API call.
 * See index.php for usage details.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

$firebaseUid = verifyFirebaseLogin();

addToLog( 'command: getCampaigns' );

getSalesforceContactID( $firebaseUid )->then( function($contactID) {
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($contactID) {
        return getAllSalesforceQueryRecordsAsync(
            "SELECT Id, Name, CreatedDate, EndDate, IsActive FROM Campaign WHERE Id IN (SELECT CampaignId FROM CampaignMember WHERE CampaignMember.ContactId = '{$contactID}')"
        )->then( function($response) {
            // convert the campaigns to a nicer format
            $campaigns = array();
            foreach( $response as $campaign ) {
                // ignore this campaign if the end date is in the past, or IsActive is false
                $endTime = strtotime( $campaign->EndDate );
                $createdTime = strtotime( $campaign->CreatedDate );
                $daysSinceCreated = round( (time() - $createdTime) / (60 * 60 * 24) );
                if ( $endTime > time() && $campaign->IsActive ) {
                    array_push( $campaigns, array(
                        'salesforceId' => $campaign->Id,
                        'name' => $campaign->Name,
                        'daysSinceCreated' => $daysSinceCreated
                    ));
                }
            }
            echo json_encode( $campaigns );
        });
    });
})->otherwise(
    $handleRequestFailure
);

$loop->run();
