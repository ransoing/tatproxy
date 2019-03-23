<?php
// This file has functions which each directly map to an API call or API option.
// Each function must return a function which resolves with an associative array. The code that handles these
// promises merges the results of each promise together, and casts the merged array into an object, which it
// then outputs as the response to the API request.

// As mentioned elsewhere, for the getUserData API functions, multiple of these functions can be invoked simultaneously
// through the API by calling /api/getUserData/parts=[function1],[function2],[...]
// For example:
// /api/getUserData?parts=basic,hoursLogs

// This array maps API calls and getUserData 'parts' parameters to functions
$apiFunctions = array();

/**
 * Searches for a Contact by phone or email.
 * If either phone or email matches, it resolves with the first matching Contact ID. Otherwise it resolves with false.
 * URL: /api/contactSearch?email=[emailAddress]&phone=[phoneNumber]
 */
$apiFunctions['contactSearch'] = function( $email, $phone ) {
    // construct the query
    $query = "SELECT Id from Contact WHERE ";

    // match any one of several email fields
    $emailFields = array( 'npe01__HomeEmail__c', 'npe01__WorkEmail__c', 'npe01__AlternateEmail__c' );
    foreach( $emailFields as $i => $emailField ) {
        $emailFields[$i] = "${emailField}='${email}'";
    }
    $query .= implode( ' OR ', $emailFields );

    // match any one of several phone fields
    // remove all but digits from the phone number we are searching for.
    $phone = preg_replace( '/\D/', '', $phone );
    // use only the last 10 digits.
    $phone = substr( $phone, -10 );
    // salesforce phone fields might have parentheses, dashes, or dots. We need to use wildcards to account for any of these cases.
    // i.e. for the phone number 123-456-7890, search for '%123%456%7890'. This will match '1234567890' and '(123) 456-7890' and '123.456.7890'
    if ( strlen($phone) === 10 ) {
        $phoneWithWildcards = '%' . substr($phone, 0, 3) . '%' . substr($phone, 3, 3) . '%' . substr($phone, 6, 4);
    } else {
        // unknown / bad format
        $phoneWithWildcards = $phone;
    }
    $phoneFields = array( 'HomePhone', 'MobilePhone', 'npe01__WorkPhone__c', 'OtherPhone' );
    foreach( $phoneFields as $i => $phoneField ) {
        $phoneFields[$i] = "${phoneField} LIKE '${phoneWithWildcards}'";
    }
    $query .= ' OR ' . implode( ' OR ', $phoneFields );

    return getAllSalesforceQueryRecordsAsync(
        $query
    )->then( function($records) {
        // if a record was found, return the Id of the first one. Otherwise return false.
        if ( sizeof($records) > 0 ) {
            return $records[0]->Id;
        } else {
            // return some expected error so that the app can know when no Contact entry matches the given email/phone
            throw new Exception( json_encode((object)array(
                'errorCode' => 'NO_MATCHING_ENTRY',
                'message' => 'There is no Contact that has the specified email address or phone number.'
            )));
        }
    });
};


$apiFunctions['getUserData'] = array();

/**
 * Gets miscellaneous data on the user.
 * URL: /api/getUserData?parts=basic
 */
$apiFunctions['getUserData']['basic'] = function( $contactID ) {
    return salesforceAPIGetAsync(
        "sobjects/Contact/${contactID}/",
        array('fields' => 'TAT_App_Volunteer_Type__c,TAT_App_Has_Watched_Training_Videos__c,FirstName,LastName,TAT_App_Materials_Address__c,TAT_App_Materials_City__c,TAT_App_Materials_State__c,TAT_App_Materials_Zip__c')
    )->then( function($response) use ($contactID) {
        // convert to a format that the app expects
        return array(
            'salesforceId' => $contactID,
            'volunteerType' => $response->TAT_App_Volunteer_Type__c,
            'hasCompletedTrainingFeedback' => $response->TAT_App_Has_Watched_Training_Videos__c,
            'firstName' => $response->FirstName,
            'lastName' => $response->LastName,
            'address' => $response->TAT_App_Materials_Address__c,
            'city' => $response->TAT_App_Materials_City__c,
            'state' => $response->TAT_App_Materials_State__c,
            'zip' => $response->TAT_App_Materials_Zip__c
        );
    });
};

/**
 * Gets a listing of hours log entries that the user has submitted.
 * URL: /api/getUserData?parts=hoursLogs
 */
$apiFunctions['getUserData']['hoursLogs'] = function ( $contactID ) {
    return getAllSalesforceQueryRecordsAsync(
        "SELECT Description__c, Date__c, Num_Hours__c from TAT_App_Hours_Log_Entry__c WHERE Contact__c = '$contactID'"
    )->then( function($records) {
        // convert to a format that the app expects
        $hoursLogs = array();
        foreach( $records as $record ) {
            array_push( $hoursLogs, (object)array(
                'taskDescription' => $record->Description__c,
                'date' => $record->Date__c,
                'numHours' => $record->Num_Hours__c
            ));
        }

        return array(
            'hoursLogs' => $hoursLogs
        );
    });
};

/**
 * Retrieves info on all pre-outreach and post-outreach forms that the user has submitted,
 * and figures out which pre-outreach locations do not have an associated post-outreach form, and
 * returns the data on those.
 * URL: /api/getUserData?parts=unfinishedOutreachTargets
 */
$apiFunctions['getUserData']['unfinishedOutreachTargets'] = function ( $contactID ) {
    $promises = array(
        // get all outreach targets
        getAllSalesforceQueryRecordsAsync( "SELECT Id, Location_Name__c, Location_Type__c, TAT_App_Materials_Address__c, TAT_App_Materials_City__c, TAT_App_Materials_State__c, TAT_App_Materials_Zip__c FROM TAT_App_Outreach_Target__c WHERE Contact__c = '$contactID'" ),
        // get all outreach reports
        getAllSalesforceQueryRecordsAsync( "SELECT Follow_Up_Date__c, Accomplishments__c, Outreach_Target__c FROM TAT_App_Outreach_Report__c WHERE Outreach_Target__r.Contact__c = '$contactID'" )
    );
    return \React\Promise\all( $promises )->then(
        function( $responses ) {
            $outreachTargetRecords = $responses[0];
            $outreachReportRecords = $responses[1];

            // convert outreach targets/records to a better format
            $outreachTargets = array();
            foreach( $outreachTargetRecords as $record ) {
                // find post-reports for this target
                $targetIsFinished = false;
                $postReports = array();
                foreach( $outreachReportRecords as $report ) {
                    if ( $report->Outreach_Target__c == $record->Id ) {
                        // if any reports for this target have a follow-up date of 'null', then the volunteer is done with this location
                        if ( $report->Follow_Up_Date__c == null ) {
                            $targetIsFinished = true;
                            break;
                        }
                        array_push( $postReports, (object)array(
                            'followUpDate' => $report->Follow_Up_Date__c,
                        ));
                    }
                }
                if ( $targetIsFinished ) {
                    // don't include this outreach target in the list passed to the app.
                    // we only want to show outreach targets that require a post-outreach report.
                    continue;
                }
                array_push( $outreachTargets, (object)array(
                    'id' => $record->Id,
                    'name' => $record->Location_Name__c,
                    'type' => $record->Location_Type__c,
                    'address' => $record->TAT_App_Materials_Address__c,
                    'city' => $record->TAT_App_Materials_City__c,
                    'state' => $record->TAT_App_Materials_State__c,
                    'zip' => $record->TAT_App_Materials_Zip__c,
                    'postReports' => $postReports
                ));
            }

            return array(
                'unfinishedOutreachTargets' => $outreachTargets
            );
        }
    );
};
