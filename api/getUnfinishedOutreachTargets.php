<?php
require_once( '../functions.php' );
require_once( '../api-functions.php' );


/**
 * POST: /api/getUnfinishedOutreachTargets
 * Gets a list of outreach targets that the user is planning on contacting.
 * POST Parameters:
 * firebaseIdToken: {string} - The user's Firebase login ID token, which is obtained after the user authenticates with Firebase.
 * 
 * Example:
 * URL: /api/getUnfinishedOutreachTargets
 * POST data: 'firebaseIdToken=abcd1234'
 * 
 * Returns a JSON object containing an array of the outreach targets.
 * 
 * ```
 * [
 *  {
 *      id: string,
 *      name: string,
 *      type: string,
 *      address: string,
 *      city: string
 *      state: string
 *      zip: string
 *      postReports: {
 *          followUpDate: string | null
 *      }[]
 *  }
 * ]
 * ```
 */


//@@ $firebaseUser = verifyFirebaseLogin();
// @@TODO: the salesforce contactID should be retrieved from the firebase db
$contactID = '0031N00001tVsAmQAK';
// $contactID = '003o000000LD6rLAAT'; // helen


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

$response = (object)array(
    'unfinishedOutreachTargets' => $outreachTargets
);

http_response_code( 200 );
echo json_encode( $response, JSON_PRETTY_PRINT );
