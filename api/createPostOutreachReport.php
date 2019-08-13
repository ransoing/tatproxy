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
        array( 'Other accomplishments:', (isset($postData->otherAccomplishments) ? $postData->otherAccomplishments : '') ),
        array( 'Do you plan to follow up with your contact?', $postData->willFollowUp ),
        array( 'When will you follow up?', $postData->followUpDate )
    );

    $sfData = array(
        'Is_Completed__c' => true,
        'Completion_Date__c' => $postData->completionDate,
        'Total_Man_Hours__c' => $postData->totalHours,
        'Accomplishments__c' => implode( ';', $postData->accomplishments ),
        'Post_Outreach_Report_Submitted_By__c' => $contactID,
        'Misc_Post_Outreach_Report_Answers__c' => $miscAnswers
    );

    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($sfData, $postData, $contactID) {
        // modify the outreach location
        return salesforceAPIPatchAsync( 'sobjects/TAT_App_Outreach_Location__c/' . $postData->outreachLocationId, $sfData );
    })->then( function() use ($postData) {
        // get outreach location info
        $fields = array( 'Id', 'Name', 'Contact_Email__c', 'Contact_First_Name__c', 'Contact_Last_Name__c', 'Contact_Phone__c', 'Contact_Title__c', 'Country__c', 'State__c', 'City__c', 'Street__c', 'Zip__c', 'Type__c', 'Campaign__c' );
        return salesforceAPIGetAsync(
            'sobjects/TAT_App_Outreach_Location__c/' . $postData->outreachLocationId,
            array('fields' => implode(',', $fields) )
        );
    })->then( function($outreachLocation) use ($postData) {
        // not all http calls depend on previous http calls. separate them into various 'threads' that can be performed simultaneously

        // get the opportunity related to the Outreach Location's campaign
        $promiseToChangeCampaignOpportunity = getAllSalesforceQueryRecordsAsync(
            "SELECT Id FROM Opportunity WHERE CampaignId = '{$outreachLocation->Campaign__c}'"
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
        });

        // search to see if an account for this outreach location already exists
        $promiseToMakeAccountAndContact = getAllSalesforceQueryRecordsAsync( sprintf(
            "SELECT Id FROM Account WHERE Name = '%s' AND BillingState = '%s' AND BillingCity = '%s' AND BillingStreet = '%s'",
            escapeSingleQuotes($outreachLocation->Name),
            escapeSingleQuotes($outreachLocation->State__c),
            escapeSingleQuotes($outreachLocation->City__c),
            escapeSingleQuotes($outreachLocation->Street__c)
        ))->then( function($records) use ($postData, $outreachLocation) {
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

            // create a Contact associated with the account. This must happen after the account is created, because we need to insert
            // the right AccountId when this contact is created, not after
            $fields = array(
                'FirstName' => $postData->contactFirstName,
                'LastName' => $postData->contactLastName,
                'Title' => $postData->contactTitle,
                'npe01__Preferred_Email__c' =>  'Work',
                'npe01__PreferredPhone__c' => 'Work',
                'npe01__Primary_Address_Type__c' => 'Work',
                'MailingCountry' => $outreachLocation->Country__c,
                'MailingStreet' => $outreachLocation->Street__c,
                'MailingCity' => $outreachLocation->City__c,
                'MailingState' => $outreachLocation->State__c,
                'MailingPostalCode' => $outreachLocation->Zip__c,
                'AccountId' => $accountId
            );
            if ( isset($postData->contactEmail) && !empty($postData->contactEmail) ) {
                $fields['npe01__WorkEmail__c'] = $postData->contactEmail;
            }
            if ( isset($postData->contactPhone) && !empty($postData->contactPhone) ) {
                $fields['npe01__WorkPhone__c'] = $postData->contactPhone;
            }

            return salesforceAPIPostAsync( 'sobjects/Contact', $fields )->then( function($newContact) use ($accountId) {
                // edit the Account to have the Contact we just created as the primary contact
                return salesforceAPIPatchAsync(
                    'sobjects/Account/' . $accountId, array('npe01__One2OneContact__c' => $newContact->id)
                )->then( function() use ($accountId, $newContact) {
                    return array(
                        'accountId' => $accountId,
                        'contactId' => $newContact->id
                    );
                });
            });
        });

        // get the owner of this campaign. 'Username' is the user's email address.
        $promiseToGetCampiagnOwner = getAllSalesforceQueryRecordsAsync(
            "SELECT Username, Id FROM User WHERE Id IN (SELECT OwnerId FROM Campaign WHERE Campaign.Id = '{$outreachLocation->Campaign__c}')"
        )->then( function($records) {
            if ( sizeof($records) === 0 ) {
                throw new Exception( 'Failed to get campaign owner.\n' . json_encode($records) );
            }
            return $records[0];
        });


        return \React\Promise\all( array(
            $promiseToChangeCampaignOpportunity,
            $promiseToMakeAccountAndContact,
            $promiseToGetCampiagnOwner
        ))->then( function($promiseResults) use ($outreachLocation) {
            $accountId = $promiseResults[1]['accountId'];
            $contactId = $promiseResults[1]['contactId'];
            $campaignOwner = $promiseResults[2];

            // create new opportunities in salesforce depending on the specific accomplishments made. This must be done after the
            // new Contact has been created, because the opportunities need to point to this Contact
            // using a for-loop instead of array_map has the benefit of filtering out invalid values of `$postData->accomplishments`
            $newOpps = array();
            $oneMonthFromToday = new DateTime();
            $oneMonthFromToday->add( new DateInterval('P1M') );
            $inOneMonthDate = $oneMonthFromToday->format( 'Y/m/d' );
            $inOneMonthISO = $oneMonthFromToday->format( 'c' );

            // create an array with fields and values that are common to all types of opportunities.
            // if-blocks will merge some data with this array
            $defaultOpp = array(
                'attributes' => array( 'type' => 'Opportunity' ),
                '@@AccountName' => $outreachLocation->Name,
                '@@PrimaryContact' => $contactId,
                '@@CloseDate' => $inOneMonthISO,
                '@@OppOwner' => $campaignOwner->Id,
                '@@CampaignSource' => $outreachLocation->Campaign__c,
                '@@Stage' => 'Pledged'
            );

            foreach ( $postData->accomplishments as $accomplishment ) {
                if ( $outreachLocation->Type__c === 'truckStop' ) {
                    if ( $accomplishment === 'willDistributeMaterials' ) {
                        array_push( $newOpps, array_merge($defaultOpp, array(
                            '@@OppType' => 'Distribution Point',
                            '@@OppName' => $outreachLocation->Name . ' - Distribution Point - ' . $inOneMonthDate,
                            '@@Probability' => 100,
                            '@@LocationType' => 'Truck Stop'
                        )));
                    } else if ( $accomplishment === 'willTrainEmployees' ) {
                        array_push( $newOpps, array_merge($defaultOpp, array(
                            '@@OppType' => 'Registered TAT Trained',
                            '@@OppName' => $outreachLocation->Name . ' - Reg TAT Trained - ' . $inOneMonthDate,
                            '@@Probability' => 100,
                            '@@TotalTrained' => 0
                        )));
                    }
                } else if ( $outreachLocation->Type__c === 'cdlSchool' ) {
                    if ( $accomplishment === 'willUseTatTraining' ) {
                        array_push( $newOpps, array_merge($defaultOpp, array(
                        )));
                    } else if ( $accomplishment === 'willPassOnInfo' ) {
                        array_push( $newOpps, array_merge($defaultOpp, array(
                        )));
                    }
                } else if ( $outreachLocation->Type__c === 'truckingCompany' ) {
                    if ( $accomplishment === 'willTrainDrivers' ) {
                        array_push( $newOpps, array_merge($defaultOpp, array(
                        )));
                    }
                }
            }

            // add one more opportunity for 'otherAccomplishments'
            if ( isset($postData->otherAccomplishments) && !empty($postData->otherAccomplishments) ) {
                array_push( $newOpps, array_merge($defaultOpp, array(
                    '@@OppType' => 'Other Involvement',
                    '@@OppName' => $outreachLocation->Name . ' - OI: from Vol Dis Outreach - ' . $inOneMonthDate,
                    '@@Stage' => 'Prospecting',
                    '@@Description' => $postData->otherAccomplishments,
                    '@@Probability' => 0
                )));
            }

            return salesforceAPIPostAsync( 'composite/sobjects/', array(
                'allOrNone' => true,
                'records' => $newOpps
            ))->then( function($responses) use ($outreachLocation, $accountId, $contactId, $campaignOwner) {
                // check that the request was successful
                if ( !$responses[0]->success ) {
                    throw new Exception( 'Failed to create opportunities.\n' . json_encode($responses) );
                }

                // send an email with results.
                $instanceUrl = getSFAuth()->instance_url;
                $emailContent = "<p>Outreach completed at {$outreachLocation->Name}. Click the links below to view the relevant objects in Salesforce.</p>"
                    . "<p><a href='{$instanceUrl}/lightning/r/TAT_App_Outreach_Location__c/{$outreachLocation->Id}/view'>TAT App Outreach Location</a> (contains post-outreach survey results)</p>"
                    . "<p><a href='{$instanceUrl}/lightning/r/Account/{$accountId}/view'>Account</a><p>"
                    . "<p><a href='{$instanceUrl}/lightning/r/Contact/{$contactId}/view'>Contact</a><p>";
                // @@ add links to new opportunities
                // the email address is the 'Username' field of the User object
                sendMail( $campaignOwner->Username, 'Post-outreach report completed', $emailContent );
                return true;
            });
        });
    });
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);


$loop->run();
