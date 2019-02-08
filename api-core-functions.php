<?php
// functions which return with a promise that resolves with parts of the user data object.
// these functions directly map to API options

function getBasicUserData( $contactID ) {
    return salesforceAPIGetAsync(
        "sobjects/Contact/${contactID}/",
        array('fields' => 'App_volunteer_type__c,App_has_watched_training_video__c,FirstName,LastName')
    )->then( function($response) {
        // convert to a format that the app expects
        return array(
            'volunteerType' => $response->App_volunteer_type__c,
            'hasWatchedTrainingVideo' => $response->App_has_watched_training_video__c,
            'firstName' => $response->FirstName,
            'lastName' => $response->LastName
        );
    });
}

function getHoursLogs( $contactID ) {
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
}

function getUnfinishedOutreachTargets( $contactID ) {
    $promises = array(
        // get all outreach targets
        getAllSalesforceQueryRecordsAsync( "SELECT LocationName__c, LocationType__c, Address__c, City__c, State__c, Zip__c FROM AppOutreachTarget__c WHERE ContactID__c = '$contactID'" ),
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
                $id = substr( $record->attributes->url, strrpos($record->attributes->url, '/') +1 );
                // find post-reports for this target
                $targetIsFinished = false;
                $postReports = array();
                foreach( $outreachReportRecords as $report ) {
                    if ( $report->AppOutreachTarget__c == $id ) {
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
                    'id' => $id,
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
}