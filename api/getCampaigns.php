<?php

/**
 * The high-level code for the getCampaigns API call.
 * See index.php for usage details.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

$firebaseUid = verifyFirebaseLogin();

getSalesforceContactID( $firebaseUid )->then( function($contactID) {
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($contactID) {
        return getAllSalesforceQueryRecordsAsync(
            "SELECT Id, Name, CreatedDate, IsActive FROM Campaign WHERE Id IN (SELECT CampaignId FROM CampaignMember WHERE CampaignMember.ContactId = '{$contactID}')"
        )->then( function($response) {
            // convert the campaigns to a nicer format
            $campaigns = array();
            foreach( $response as $campaign ) {
                // @@ verify whether we should indeed filter by date and IsActive
                // find out how old this campaign is... ignore it if it was created too long ago
                $date = strtotime( $campaign->CreatedDate );
                $daysSinceCreated = round( (time() - $date) / (60 * 60 * 24) );
                if ( $daysSinceCreated < 365 && $campaign->IsActive ) {
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
