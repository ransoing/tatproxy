<?php
require_once( '../functions.php' );
require_once( '../api-functions.php' );

$testing = true;

if ( !$testing ) {
    $postData = getPOSTData();
}

/**
 * POST: /api/getUserData
 * Gets a user's data from salesforce
 * POST Parameters:
 * firebaseIdToken: {string} - The user's Firebase login ID token, which is obtained after the user authenticates with Firebase.
 * 
 * Example:
 * URL: /api/getUserData
 * POST data: 'firebaseIdToken=abcd1234'
 * 
 * Returns a JSON object containing all the data on the user that the app needs.
 */

if ( !$testing ) {
    // verify that the required parameters are present
    if ( !isset($postData->firebaseIdToken) ) {
    errorExit( 400, '`firebaseIdToken` must be present in the POST parameters.' );
    }

    // verify against Firebase that the ID token is valid (i.e. it represents a logged-in user)
    $firebaseResponse = firebaseAPIPost( 'getAccountInfo', array('idToken' => $postData->firebaseIdToken) );
    // check if there was an error with the request itself
    if ( $firebaseResponse['error'] ) {
        errorExit( 400, "The request to Firebase failed to execute: " . $firebaseResponse['error'] );
    }
    // check if there was an error in the response from Firebase
    if ( isset($firebaseResponse['content']->error) ) {
        errorExit( 400, "The request to Firebase returned with an error: " . $firebaseResponse['content']->error->message );
    }
}


// @@TODO: the salesforce contactID should be retrieved from the firebase db
$contactID = '0031N00001tVsAmQAK';
// $contactID = '003o000000LD6rLAAT'; // helen


// get volunteer type and whether the user has watched the training video
$contactResponse = salesforceAPIGet( "sobjects/Contact/${contactID}/", array('fields' => 'App_volunteer_type__c,App_has_watched_training_video__c') );
exitIfResponseHasError( $contactResponse );

// get hours logs
$records = getAllSalesforceQueryRecords( "SELECT Description__c, Date__c, NumHours__c from AppHoursLogEntry__c WHERE ContactID__c = '$contactID'" );
// convert the response to the format that the app expects
$hoursLogs = array();
foreach( $records as $record ) {
    array_push( $hoursLogs, (object)array(
        'taskDescription' => $record->Description__c,
        'date' => $record->Date__c,
        'numHours' => $record->NumHours__c
    ));
}

// @@TODO: if volunteer type is truck stop volunteer, get outreach target/records. otherwise, get events.

// get all outreach targets
$outreachTargetRecords = getAllSalesforceQueryRecords( "SELECT LocationName__c, LocationType__c, Address__c, City__c, State__c, Zip__c FROM AppOutreachTarget__c WHERE ContactID__c = '$contactID'" );
// get all outreach reports
$outreachReportRecords = getAllSalesforceQueryRecords( "SELECT FollowUpDate__c, Accomplishments__c, AppOutreachTarget__c FROM AppOutreachReport__c WHERE AppOutreachTarget__r.ContactID__c = '$contactID'" );
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


// return a response in a format that the app expects
$responseContent = (object)array(
    'volunteerType' => $contactResponse['content']->App_volunteer_type__c,
    'hasWatchedTrainingVideo' => $contactResponse['content']->App_has_watched_training_video__c,
    'hoursLogs' => $hoursLogs,
    'unfinishedOutreachTargets' => $outreachTargets
);
http_response_code( 200 );
echo json_encode( $responseContent, JSON_PRETTY_PRINT );
