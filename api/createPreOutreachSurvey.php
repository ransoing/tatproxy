<?php

/**
 * The high-level code for the createPreOutreachSurvey API call.
 * See index.php for usage details.
 * 
 * Adds a new object in Salesforce, which is linked to the user's Contact object via a Lookup field.
 * Also creates an Event activity on the user's Contact object.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

/**
 * @@ new input structure:
campaignId,
locations: [{
	name,
	type,
	address,
	city,
	state,
	zip,
	date,
	hasContactedManager,
	contactName,
	contactTitle,
	contactEmail,
	contactPhone
}],
isReadyToReceive,
mailingAddress,
mailingCity,
mailingState,
mailingZip,
feelsPrepared,
questions

 */

makeSalesforceRequestWithTokenExpirationCheck( function() {
    return getAllSalesforceQueryRecordsAsync( "SELECT Id, FirstName, LastName FROM Contact WHERE TAT_App_Firebase_UID__c != NULL" );
})->then( function($a) {
    print_r($a);
})->otherwise( $handleRequestFailure );

$loop->run();

exit;

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();
// map POST data to salesforce fields
$sfData = array(
    'Name' =>       $postData->locationName,
    'Type__c' =>    $postData->locationType,
    'Address__c' =>  $postData->locationAddress,
    'City__c' =>    $postData->locationCity,
    'State__c' =>   $postData->locationState,
    'Zip__c' =>     $postData->locationZip,
    'Planned_Date__c' =>    $postData->date
);

$locationType = getLocationType( $postData->locationType );
$now = date('c');
$eventData = array(
    'Subject' =>  'TAT App Pre-Outreach Survey response',
    'Description' => formatQAs(
        array( 'What location do you plan on visiting?', implode("\n", array(
            "{$postData->locationName} ({$locationType})",
            $postData->locationAddress,
            "{$postData->locationCity}, {$postData->locationState} {$postData->locationZip}"
        ))),
        array( 'When do you plan on visiting this location?', $postData->date ),
        array( 'Have you contacted the general manager (or other contact) to make an appointment?', $postData->hasContactedManager ? 'Yes' : 'No' ),
        array( 'Are you ready to receive TAT materials?', $postData->isReadyToReceive ? 'Yes' : 'No' ),
        array( 'What is a good mailing address to send the materials to?', implode("\n", array(
            $postData->mailingAddress,
            "{$postData->mailingCity}, {$postData->mailingState} {$postData->mailingZip}"
        )))
    ),
    'StartDateTime' =>  $now,
    'EndDateTime' =>    $now
);

getSalesforceContactID( $firebaseUid )->then( function($contactID) use ($sfData, $eventData) {
    // get a list of the volunteers who are on this user's team.
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($contactID) {
        return getAllSalesforceQueryRecordsAsync( "SELECT Id FROM Contact WHERE TAT_App_Team_Coordinator__c = '$contactID'" );
    })->then( function( $teamMembers ) use($contactID, $sfData) {
        // convert the data into a simple array
        $teamMemberIds = array( $contactID );
        foreach( $teamMembers as $member ) {
            array_push( $teamMemberIds, $member->Id );
        }
        
        // for everyone on the volunteer team, create a new Volunteer Activity object.
        $promises = array();
        foreach( $teamMemberIds as $memberId ) {
            // call the API function and store the promise
            $dataCopy = $sfData;
            $dataCopy['Contact__c'] = $memberId;
            array_push( $promises, salesforceAPIPostAsync( 'sobjects/TAT_App_Volunteer_Activity__c/', $dataCopy ) );
        }
        return \React\Promise\all( $promises );
    })->then( function() use ($eventData, $contactID) {
        // For the current user, create an Event activity on his Contact detailing the info given in the survey.
        $eventData['WhoId'] = $contactID;
        return salesforceAPIPostAsync( 'sobjects/Event/', $eventData );
    });
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);


$loop->run();
