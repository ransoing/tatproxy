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
$sfData = array();
if ( isset($postData->coordinatorId) ) {
    $sfData['TAT_App_Team_Coordinator__c'] = $postData->coordinatorId;
}
if ( isset($postData->hasWatchedTrainingVideo) ) {
    $sfData['TAT_App_Has_Watched_Training_Video__c'] = $postData->hasWatchedTrainingVideo;
}


// Verify that the user has a Contact object
getSalesforceContactID( $firebaseUid )->then( function($contactID) use($sfData, $postData) {
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($contactID, $sfData) {
        return salesforceAPIPatchAsync( 'sobjects/Contact/' . $contactID, $sfData );
    })->then( function() use ($contactID, $postData) {
        if ( !isset($postData->coordinatorId) ) {
            return true;
        }

        // find all unfinished outreach locations submitted by the newly selected team lead, so we can add
        // this team member to the team lead's relevant campaigns
        return \React\Promise\all( array(
            // get all relevant campaigns
            getAllSalesforceQueryRecordsAsync( "SELECT Campaign__c FROM TAT_App_Outreach_Location__c WHERE Is_Completed__c = false AND Team_Lead__c = '{$postData->coordinatorId}'" ),
            // get all campaigns that the user is already part of
            getAllSalesforceQueryRecordsAsync( "SELECT CampaignId FROM CampaignMember WHERE ContactId = '{$contactID}'" )
        ))->then( function($responses) use ($contactID, $postData) {
            $teamLeadsCampaigns = array_map( function($outreachLocation) {
                return $outreachLocation->Campaign__c;
            }, $responses[0] );
            $usersCurrentCampaigns = array_map( function($member) {
                return $member->CampaignId;
            }, $responses[1] );

            $campaignsToAddUserTo = array();
            foreach( $teamLeadsCampaigns as $campaign ) {
                // if the user isn't already part of this campaign, add it to the array
                if ( !in_array($campaign, $usersCurrentCampaigns) && !in_array($campaign, $campaignsToAddUserTo) ) {
                    array_push( $campaignsToAddUserTo, $campaign );
                }
            }
            
            // create a CampaignMember linking the contact to each campaign
            if ( sizeof($campaignsToAddUserTo) == 0 ) {
                return true;
            }

            $newCampaignMembers = array_map( function($campaign) use ($contactID) {
                return array(
                    'attributes' => array( 'type' => 'CampaignMember' ),
                    'CampaignId' => $campaign,
                    'ContactId' => $contactID
                );
            }, $campaignsToAddUserTo );

            // send it
            return salesforceAPIPostAsync( 'composite/sobjects/', array(
                'allOrNone' => false,
                'records' => $newCampaignMembers
            ));
        });
    });
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);

$loop->run();
