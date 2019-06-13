<?php

/**
 * The high-level code for the createPreOutreachSurvey API call.
 * See index.php for usage details.
 * 
 * Adds multiple new objects in Salesforce, which are linked to the user's Contact object via a Lookup field.
 * Also creates an Event activity on the user's Contact object.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();

// sanitize campaignId by removing quotes
$postData->campaignId = str_replace( array("'", '"'), "", $postData->campaignId );

// this code uses the composite/sobjects/ API endpoint, which can create no more than 200 objects simultaneously
// throw an error if too may locations are submitted at one time
if ( isset($postData->locations) && sizeof($postData->locations) > 200 ) {
    $message = json_encode((object)array(
        'errorCode' => 'TOO_MANY_LOCATIONS',
        'message' => 'You may only submit up to 200 locations in one request.'
    ));

    errorExit( 400, $message );
}

getSalesforceContactID( $firebaseUid )->then( function($contactID) use ($postData, $firebaseUid) {
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($contactID, $postData) {
        // get details on the contact, so we can update the user's regular address fields if they are empty
        return salesforceAPIGetAsync(
            "sobjects/Contact/{$contactID}/",
            array( 'fields' => 'npe01__Home_Address__c' ) // @@ get MailingAddress instead of this?
        );
    })->then( function($contact) use ($contactID, $postData) {
        // always update the TAT_App address fields
        $addressData = array(
            'TAT_App_Materials_Address__c' => $postData->mailingAddress,
            'TAT_App_Materials_City__c' =>  $postData->mailingCity,
            'TAT_App_Materials_State__c' => $postData->mailingState,
            'TAT_App_Materials_Zip__c' =>   $postData->mailingZip
        );

        // // update the Contact's address info in SF if the home address field is empty
        // @@ This requires setting the following fields: MailingStreet, MailingCity, MailingState, MailingPostalCode
        // @@ The fields must be set rather exactly, i.e. the state must be "Colorado" and not "CO"
        // if ( empty($contact->npe01__Home_Address__c) ) {
        //     $addressData['npe01__Home_Address__c'] = "{$postData->mailingAddress}, {$postData->mailingCity}, {$postData->mailingState} {$postData->mailingZip}, United States";
        // }

        // make the request to update the Contact
        return salesforceAPIPatchAsync( 'sobjects/Contact/' . $contactID, $addressData );

    })->then( function() use ($postData, $contactID, $firebaseUid) {

        // create one TAT_App_Outreach_Location per object in $postData->locations
        $outreachLocations = array();
        foreach( $postData->locations as $location ) {
            array_push( $outreachLocations, array(
                'attributes' => array( 'type' => 'TAT_App_Outreach_Location__c' ),
                'Campaign__c' =>    $postData->campaignId,
                'Team_Lead__c' =>   $contactID,
                'Name' =>           $location->name,
                'Type__c' =>        $location->type,
                'Address__c' =>     $location->address,
                'City__c' =>        $location->city,
                'State__c' =>       $location->state,
                'Zip__c' =>         $location->zip,
                'Planned_Date__c' =>    $location->date,
                'Has_Contacted_Manager__c' => $location->hasContactedManager,
                'Contact_Name__c' =>    $location->contactName,
                'Contact_Title__c' =>   $location->contactTitle,
                'Contact_Email__c' =>   $location->contactEmail,
                'Contact_Phone__c' =>   $location->contactPhone
            ));
        }

        // create an event on the user who submitted the survey, containing some other details.
        $now = date('c');
        $eventData = array(
            'Subject' =>  'TAT App Pre-Outreach Survey Response',
            'Description' => formatQAs(
                array( 'Are you ready to receive TAT materials?', $postData->isReadyToReceive ? 'Yes' : 'No' ),
                array( 'What is a good mailing address to send the materials to?', "{$postData->mailingAddress}\n{$postData->mailingCity}, {$postData->mailingState} {$postData->mailingZip}" ),
                array( 'After watching the training video, do you feel equipped for your outreach?', $postData->feelsPrepared ? 'Yes' : 'No' ),
                array( 'What questions do you have for TAT staff?', $postData->questions )
            ),
            'StartDateTime' =>  $now,
            'EndDateTime' =>    $now
        );

        // create Event on contact
        return createNewSFObject( $firebaseUid, 'sobjects/Event/', $eventData, 'WhoId')->then( function($response) use ($outreachLocations) {
            // create outreach locations
            return salesforceAPIPostAsync( 'composite/sobjects/', array(
                'allOrNone' => true,
                'records' => $outreachLocations
            ));
        })->then( function($response) {
            // check that the request was successful
            return $response[0]->success;
        })->then( function() use ($contactID, $postData) {
            // Add all team members to the campaign. First get all contacts who have this user as their team lead
            return getAllSalesforceQueryRecordsAsync( "SELECT Id FROM Contact WHERE TAT_App_Team_Coordinator__c = '{$contactID}'" );
        })->then( function($records) use ($postData) {
            // create a CampaignMember linking each contact to the campaign
            if ( sizeof($records) > 0 ) {
                $campaignMembers = array();
                foreach( $records as $record ) {
                    array_push( $campaignMembers, array(
                        'attributes' => array( 'type' => 'CampaignMember' ),
                        'CampaignId' => $postData->campaignId,
                        'ContactId' => $record->Id
                    ));
                }
                // send it
                return salesforceAPIPostAsync( 'composite/sobjects/', array(
                    'allOrNone' => false,
                    'records' => $campaignMembers
                ));
            } else {
                return true;
            }

            // @@@ Send an email with survey results
        });
    });
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);


$loop->run();
