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
        return getActiveCampaigns( $contactID );
    })->then( function($campaigns) {
        // convert the arrays to a nicer format, which the app will expect
        echo json_encode( array_map( function($campaign) {
            $createdTime = strtotime( $campaign->CreatedDate );
            $daysSinceCreated = round( (time() - $createdTime) / (60 * 60 * 24) );
            return array(
                'salesforceId' => $campaign->Id,
                'name' => $campaign->Name,
                'daysSinceCreated' => $daysSinceCreated
            );
        }, $campaigns ));
    });
})->otherwise(
    $handleRequestFailure
);

$loop->run();
