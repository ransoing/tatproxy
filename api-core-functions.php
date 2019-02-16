<?php
// This file has functions which return with a promise that resolves with parts of a user data object, which
// ends up being echoed. Each of these functions directly map to an API option.
// Each function must resolve with an associative array. The code that handles these promises merges the results
// of each promise together, and casts the merged array into an object, which it then outputs as the response
// to the API request.
// As mentioned elsewhere, multiple of these functions can be invoked simultaneously through the API by calling
// /api/getUserData/parts=[function1],[function2],[...]
// For example:
// /api/getUserData?parts=basic,hoursLogs

// This array maps 'parts' parameters to functions
$apiFunctions = array();

/**
 * Gets miscellaneous data on the user.
 * URL: /api/getUserData?parts=basic
 */
$apiFunctions['basic'] = function( $appUserID ) {
    return salesforceAPIGetAsync(
        "sobjects/TAT_App_User__c/${appUserID}/",
        array('fields' => 'Volunteer_Type__c,Has_Watched_Training_Videos__c,First_Name__c,Last_Name__c,Address__c,City__c,State__c,Zip__c,Phone__c,Email__c')
    )->then( function($response) use ($appUserID) {
        // convert to a format that the app expects
        return array(
            'salesforceId' => $appUserID,
            'volunteerType' => $response->Volunteer_Type__c,
            'hasWatchedTrainingVideo' => $response->Has_Watched_Training_Videos__c,
            'firstName' => $response->First_Name__c,
            'lastName' => $response->Last_Name__c,
            'address' => $response->Address__c,
            'city' => $response->City__c,
            'state' => $response->State__c,
            'zip' => $response->Zip__c,
            'phone' => $response->Phone__c,
            'email' => $response->Email__c
        );
    });
};

/**
 * Gets a listing of hours log entries that the user has submitted.
 * URL: /api/getUserData?parts=hoursLogs
 */
$apiFunctions['hoursLogs'] = function ( $appUserID ) {
    return getAllSalesforceQueryRecordsAsync(
        "SELECT Description__c, Date__c, Num_Hours__c from TAT_App_Hours_Log_Entry__c WHERE TAT_App_User__c = '$appUserID'"
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
$apiFunctions['unfinishedOutreachTargets'] = function ( $appUserID ) {
    $promises = array(
        // get all outreach targets
        getAllSalesforceQueryRecordsAsync( "SELECT Id, Location_Name__c, Location_Type__c, Address__c, City__c, State__c, Zip__c FROM TAT_App_Outreach_Target__c WHERE TAT_App_User__c = '$appUserID'" ),
        // get all outreach reports
        getAllSalesforceQueryRecordsAsync( "SELECT Follow_Up_Date__c, Accomplishments__c, Outreach_Target__c FROM TAT_App_Outreach_Report__c WHERE Outreach_Target__r.TAT_App_User__c = '$appUserID'" )
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
                    'address' => $record->Address__c,
                    'city' => $record->City__c,
                    'state' => $record->State__c,
                    'zip' => $record->Zip__c,
                    'postReports' => $postReports
                ));
            }

            return array(
                'unfinishedOutreachTargets' => $outreachTargets
            );
        }
    );
};
