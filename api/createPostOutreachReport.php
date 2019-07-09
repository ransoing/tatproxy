<?php

/**
 * The high-level code for the createPostOutreachReport API call.
 * See index.php for usage details.
 * 
 * Modifies an Outreach Location object, marking it as complete and adding some details.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();

// sanitize outreachLocationId by removing quotes
$postData->outreachLocationId = str_replace( array("'", '"'), "", $postData->outreachLocationId );

getSalesforceContactID( $firebaseUid )->then( function($contactID) use ($postData) {

    $miscAnswers = formatQAs(
        array( 'What were you able to accomplish?', $postData->accomplishments ),
        array( 'Do you plan to follow up with your contact?', $postData->willFollowUp ),
        array( 'When will you follow up?', $postData->followUpDate )
    );

    $sfData = array(
        'Is_Completed__c' => true,
        'Completion_Date__c' => $postData->completionDate,
        'Total_Man_Hours__c' => $postData->totalHours,
        'Post_Outreach_Report_Submitted_By__c' => $contactID,
        'Misc_Post_Outreach_Report_Answers__c' => $miscAnswers
    );

    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($sfData, $postData, $contactID) {
        // modify the outreach location
        return salesforceAPIPatchAsync( 'sobjects/TAT_App_Outreach_Location__c/' . $postData->outreachLocationId, $sfData );
    })->then( function() use ($postData) {
        // get outreach location info
        $fields = array( 'Id', 'Name', 'Contact_Email__c', 'Contact_First_Name__c', 'Contact_Last_Name__c', 'Contact_Phone__c', 'Contact__Title__c', 'Country__c', 'State__c', 'City__c', 'Street__c', 'Zip__c', 'Type__c', 'Campaign__c' );
        return salesforceAPIGetAsync(
            'sobjects/TAT_App_Outreach_Location__c/' . $postData->outreachLocationId,
            array('fields' => implode(',', $fields) )
        );
    })->then( function($outreachLocation) use ($postData) {
        // get the opportunity related to the Outreach Location's campaign
        return getAllSalesforceQueryRecordsAsync(
            "SELECT Id FROM Opportunity WHERE CampaignId IN (SELECT Campaign__c FROM TAT_App_Outreach_Location__c WHERE Id = '{$outreachLocation->Campaign__c}' )"
        )->then( function($records) {
            // change the Opportunity stage to Closed/Won
            if ( sizeof($records) > 0 ) {
                $patchData = array(
                    'StageName' => 'Closed/Won',
                    'CloseDate' => explode( 'T', date('c') )[0] // today, YYYY-MM-DD
                );
                return salesforceAPIPatchAsync( 'sobjects/Opportunity/' . $records[0]->Id, $patchData );
            } else {
                return true;
            }
        })->then( function() use ($outreachLocation) {
            // search to see if an account for this outreach location already exists
            return getAllSalesforceQueryRecordsAsync(
                "SELECT Id FROM Account WHERE Name = '{$outreachLocation->name}' " .
                "AND BillingState = '{$outreachLocation->State__c}' " .
                "AND BillingCity = '{$outreachLocation->City__c}' " .
                "AND BillingStreet = '{$outreachLocation->Street__c}'"
            );
        })->then( function($records) use ($postData, $outreachLocation) {
            // ultimately return the ID of an Account; either a new one or one that already exists

            if ( sizeof($records) > 0 ) {
                // use this account.
                return $records[0]->Id;
            }

            // create a new account

            // mapping from Outreach Location type to Account type
            $typeMapping = array(
                'cdlSchool' => 'CDL School',
                'truckingCompany' => 'Trucking Company',
                'truckStop' => 'Truck Stop/Travel Plaza'
            );

            $fields = array(
                'Name' => $outreachLocation->Name,
                // @@ 'OwnerId' => 
                'Type' => $typeMapping[ $outreachLocation->Type__c ],
                'BillingCountry' => $outreachLocation->Country__c,
                'BillingStreet' => $outreachLocation->Street__c,
                'BillingCity' => $outreachLocation->City__c,
                'BillingState' => $outreachLocation->State__c,
                'BillingPostalCode' => $outreachLocation->Zip__c
            );
            return salesforceAPIPostAsync( 'sobjects/Account', $fields )->then( function($newAccount) {
                return $newAccount->id;
            });

        })->then( function($accountId) use ($postData, $outreachLocation) {
            // ultimately return the ID of an account again
            if ( !empty($outreachLocation->Contact_First_Name__c) ) {
                // create a Contact associated with the account
                $fields = array(
                    'FirstName' => $outreachLocation->Contact_First_Name__c,
                    'LastName' => $outreachLocation->Contact_Last_Name__c,
                    'Title' => $outreachLocation->Contact__Title__c,
                    'npe01__Preferred_Email__c' =>  'Work',
                    'npe01__WorkEmail__c' => $outreachLocation->Contact_Email__c,
                    'npe01__PreferredPhone__c' => 'Work',
                    'npe01__WorkPhone__c' => $outreachLocation->Contact_Phone__c,
                    'AccountId' => $accountId
                );
                return salesforceAPIPostAsync( 'sobjects/Contact', $fields )->then( function($newContact) use ($accountId) {
                    // edit the Account to have the Contact we just created as the primary contact
                    return salesforceAPIPatchAsync( 'sobjects/Account/' . $accountId, array('npe01__One2OneContact__c' => $newContact->id) )->then( function() use ($accountId) {
                        return $accountId;
                    });
                });
            } else {
                return $accountId;
            }
        })->then( function($accountId) use ($outreachLocation) {
            // send an email with results. First, get the person to send an email to --- the owner of the campaign.
            return getAllSalesforceQueryRecordsAsync( "SELECT Username FROM User WHERE Id IN (SELECT OwnerId FROM Campaign WHERE Campaign.Id = '{$outreachLocation->Campaign__c}')" );
        })->then( function($records) use ($postData, $outreachLocation) {
            if ( sizeof($records) === 0 ) {
                // nobody to email :(
                return true;
            }
            $instanceUrl = getSFAuth()->instance_url;
            $emailContent = "<p>See the details <a href='{$instanceUrl}/lightning/r/TAT_App_Outreach_Location__c/{$outreachLocation->Id}/view'>in Salesforce</a>.</p>"
                . "<p>Related Account: <a href='{$instanceUrl}/lightning/r/Account/{$accountId}/view'>see details in Salesforce</a>.<p>";
            // the email address is the 'Username' field of the User object
            sendMail( $records[0]->Username, 'Post-outreach report completed', $emailContent );
            return true;
        });
        // @@TODO create/modify objects in salesforce depending on the specific accomplishments made
    });
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);


$loop->run();
