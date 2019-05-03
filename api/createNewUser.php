<?php

/**
 * The high-level code for the createNewUser API call.
 * See index.php for usage details.
 * 
 * Either creates a new Contact object, or fills in details on an existing Contact object.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();

// verify registration code
if ( $postData->registrationCode !== getConfig()->app->registrationPassword ) {
    $message = json_encode((object)array(
        'errorCode' => 'INCORRECT_REGISTRATION_CODE',
        'message' => 'The registration code was incorrect.'
    ));
    errorExit( 400, $message );
}

// map POST data to salesforce fields
$sfData = array(
    'TAT_App_Firebase_UID__c' =>    $firebaseUid,
    'TAT_App_Volunteer_Type__c' =>  $postData->volunteerType,
    'TAT_App_Materials_Address__c' => $postData->mailingAddress,
    'TAT_App_Materials_City__c' =>  $postData->mailingCity,
    'TAT_App_Materials_State__c' => $postData->mailingState,
    'TAT_App_Materials_Zip__c' =>   $postData->mailingZip,
    'TAT_App_Is_On_Volunteer_Team__c' =>  $postData->partOfTeam,
    'TAT_App_Is_Team_Coordinator__c' =>   $postData->isCoordinator,
    'TAT_App_Team_Coordinator__c' => $postData->coordinatorId
);

if ( empty($postData->salesforceId) ) {
    // only include these fields if we're creating a new Contact object
    $sfData = array_merge( $sfData, array(
        'FirstName' =>                  $postData->firstName,
        'LastName' =>                   $postData->lastName,
        'npe01__HomeEmail__c' =>        $postData->email,
        'npe01__Preferred_Email__c' =>  'Personal',
        'HomePhone' =>                  $postData->phone
    ));
}

// First, verify that no Contact in salesforce has the given firebaseUid
getSalesforceContactID( $firebaseUid )->then(
    function() {
        // we got a ContactID, which means this firebase user already has a salesforce entry! We shouldn't let the user proceed.
        $message = json_encode((object)array(
            'errorCode' => 'FIREBASE_USER_ALREADY_IN_SALESFORCE',
            'message' => 'The specified Firebase user already has an associated Contact entry in Salesforce, and is not allowed to create a new one.'
        ));
        throw new Exception( $message );
    },
    function( $e ) {
        if ( $e && @$e->getMessage() && json_decode( $e->getMessage() )->errorCode === 'FIREBASE_USER_NOT_IN_SALESFORCE' ) {
            // this is what we were looking for; the user can proceed with making a new account.
            return;
        } else {
            throw $e;
        }
    }
)->then( function() use ($sfData, $postData) {
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($sfData, $postData) {
        if ( empty($postData->salesforceId) ) {
            // create a new Contact object
            return salesforceAPIPostAsync( 'sobjects/Contact/', $sfData );
        } else {
            // update an existing contact object
            return salesforceAPIPatchAsync( 'sobjects/Contact/' . $postData->salesforceId, $sfData );
        }
    });
})->then( function($response) use($postData) {
    // echo the ID of the object created/updated
    echo json_encode( (object)array(
        'contactId' => empty($postData->salesforceId) ? $response->id : $postData->salesforceId
    ));
})->otherwise(
    $handleRequestFailure
);


$loop->run();
