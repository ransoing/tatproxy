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
$apiFunctions['basic'] = function( $contactID ) {
    return salesforceAPIGetAsync(
        "sobjects/Contact/${contactID}/",
        array('fields' => 'App_volunteer_type__c,App_has_watched_training_video__c,FirstName,LastName')
    )->then( function($response) use ($contactID) {
        // convert to a format that the app expects
        return array(
            'salesforceId' => $contactID,
            'volunteerType' => $response->App_volunteer_type__c,
            'hasWatchedTrainingVideo' => $response->App_has_watched_training_video__c,
            'firstName' => $response->FirstName,
            'lastName' => $response->LastName
        );
    });
};

/**
 * Gets a listing of hours log entries that the user has submitted.
 * URL: /api/getUserData?parts=hoursLogs
 */
$apiFunctions['hoursLogs'] = function ( $contactID ) {
    return getAllSalesforceQueryRecordsAsync(
        "SELECT Description__c, Date__c, NumHours__c from AppHoursLogEntry__c WHERE ContactID__c = '$contactID'"
    )->then( function($records) {
        // convert to a format that the app expects
        $hoursLogs = array();
        foreach( $records as $record ) {
            array_push( $hoursLogs, (object)array(
                'taskDescription' => $record->Description__c,
                'date' => $record->Date__c,
                'numHours' => $record->NumHours__c
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
$apiFunctions['unfinishedOutreachTargets'] = function ( $contactID ) {
    $promises = array(
        // get all outreach targets
        getAllSalesforceQueryRecordsAsync( "SELECT Id, LocationName__c, LocationType__c, Address__c, City__c, State__c, Zip__c FROM AppOutreachTarget__c WHERE ContactID__c = '$contactID'" ),
        // get all outreach reports
        getAllSalesforceQueryRecordsAsync( "SELECT FollowUpDate__c, Accomplishments__c, AppOutreachTarget__c FROM AppOutreachReport__c WHERE AppOutreachTarget__r.ContactID__c = '$contactID'" )
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
                    if ( $report->AppOutreachTarget__c == $record->Id ) {
                        // if any reports for this target have a follow-up date of 'null', then the volunteer is done with this location
                        if ( $report->FollowUpDate__c == null ) {
                            $targetIsFinished = true;
                            break;
                        }
                        array_push( $postReports, (object)array(
                            'followUpDate' => $report->FollowUpDate__c,
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
                    'name' => $record->LocationName__c,
                    'type' => $record->LocationType__c,
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
