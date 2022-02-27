<?php

/**
 * The code for the getUserData API call.
 * See index.php for usage details.
 * 
 * Returns relevant data from the user's Contact object and related objects.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

addToLog( 'command: getUserData. GET params received:', $_GET );

// Define functions used by this API call.
// Each function directly maps to an API call or API option.
// Each function must return a Promise which resolves with an associative array. The code that handles these
// promises merges the results of each promise together, and casts the merged array into an object, which it
// then outputs as the response to the API request.

// As mentioned elsewhere, for the getUserData API functions, multiple of these functions can be invoked simultaneously
// through the API by calling /api/getUserData/parts=[function1],[function2],[...]
// For example:
// /api/getUserData?parts=basic,unfinishedActivities

// This array maps API calls and getUserData 'parts' parameters to functions
$apiFunctions = array();

$apiFunctions['getUserData'] = array();

/**
 * Gets miscellaneous data on the user.
 * URL: /api/getUserData?parts=basic
 */
$apiFunctions['getUserData']['basic'] = function( $contactID ) {
    $queryFields = array(
        'TAT_App_Volunteer_Type__c',
        'TAT_App_Has_Watched_Training_Video__c',
        'TAT_App_Training_Video_Last_Watched_Date__c',
        'FirstName',
        'LastName',
        'AccountId',
        'TAT_App_Materials_Street__c',
        'TAT_App_Materials_City__c',
        'TAT_App_Materials_State__c',
        'TAT_App_Materials_Zip__c',
        'TAT_App_Materials_Country__c',
        'TAT_App_Is_Team_Coordinator__c',
        'TAT_App_Team_Coordinator__c',
        'TAT_App_Team_Must_Watch_Training_Video__c',
        'TAT_App_Notification_Preferences__c'
    );
    return salesforceAPIGetAsync(
        "sobjects/Contact/${contactID}/",
        array( 'fields' => implode(',', $queryFields) )
    )->then( function($response) use ($contactID) {
        // convert to a format that the app expects
        return array(
            'salesforceId' => $contactID,
            'volunteerType' => $response->TAT_App_Volunteer_Type__c,
            'hasWatchedTrainingVideo' => $response->TAT_App_Has_Watched_Training_Video__c,
            'trainingVideoLastWatchedDate' => $response->TAT_App_Training_Video_Last_Watched_Date__c,
            'firstName' => $response->FirstName,
            'lastName' => $response->LastName,
            'accountId' => $response->AccountId,
            'street' => $response->TAT_App_Materials_Street__c,
            'city' => $response->TAT_App_Materials_City__c,
            'state' => $response->TAT_App_Materials_State__c,
            'zip' => $response->TAT_App_Materials_Zip__c,
            'country' => $response->TAT_App_Materials_Country__c,
            'isOnVolunteerTeam' => $response->TAT_App_Is_Team_Coordinator__c || !empty( $response->TAT_App_Team_Coordinator__c ),
            'isTeamCoordinator' => $response->TAT_App_Is_Team_Coordinator__c,
            'teamCoordinatorId' => $response->TAT_App_Team_Coordinator__c,
            'trainingVideoRequiredForTeam' => $response->TAT_App_Team_Must_Watch_Training_Video__c,
            'notificationPreferences' => json_decode( $response->TAT_App_Notification_Preferences__c )
        );
    });
};


/**
 * If the user is a Volunteer Distributor, get not-completed TAT App Outreach Locations belonging to the user's team lead.
 * If the user is an Ambassador Volunteer, get not-completed Campaign/Events.
 * URL: /api/getUserData?parts=unfinishedActivities
 */
$apiFunctions['getUserData']['unfinishedActivities'] = function ( $contactID ) {
    // first get the user's volunteer type
    return salesforceAPIGetAsync(
        "sobjects/Contact/${contactID}/",
        array( 'fields' => 'TAT_App_Volunteer_Type__c,TAT_App_Team_Coordinator__c,TAT_App_Is_Team_Coordinator__c' )
    )->then( function($response) use ($contactID) {
        // get all the Outreach Locations for the user's team lead, which haven't been completed
        if ( $response->TAT_App_Volunteer_Type__c === 'volunteerDistributor' ) {
            $queryFields = array(
                'Id',
                'Name',
                'Planned_Date__c',
                'Is_Completed__c',
                'Team_Lead__c',
                'Type__c',
                'Campaign__c',
                'Street__c',
                'City__c',
                'State__c',
                'Zip__c',
                'Country__c',
                'Contact_First_Name__c',
                'Contact_Last_Name__c',
                'Contact_Title__c',
                'Contact_Email__c',
                'Contact_Phone__c',
            );
            
            $teamCoordinator = !empty( $response->TAT_App_Team_Coordinator__c ) ? $response->TAT_App_Team_Coordinator__c : $contactID;
            return getAllSalesforceQueryRecordsAsync(
                "SELECT " . implode(',', $queryFields) . " FROM TAT_App_Outreach_Location__c " .
                "WHERE Team_Lead__c = '$teamCoordinator' " .
                "AND Is_Completed__c = false"
            )->then( function( $records ) {
                // convert to a better format
                $unfinishedOutreachLocations = array();
                foreach( $records as $record ) {
                    array_push( $unfinishedOutreachLocations, (object)array(
                        'id' => $record->Id,
                        'name' => $record->Name,
                        'type' => $record->Type__c,
                        'street' => $record->Street__c,
                        'city' => $record->City__c,
                        'state' => $record->State__c,
                        'zip' => $record->Zip__c,
                        'country' => $record->Country__c,
                        'date' => $record->Planned_Date__c,
                        'campaignId' => $record->Campaign__c,
                        'contact' => (object)array(
                            'firstName' => $record->Contact_First_Name__c,
                            'lastName' => $record->Contact_Last_Name__c,
                            'title' => $record->Contact_Title__c,
                            'email' => $record->Contact_Email__c,
                            'phone' => $record->Contact_Phone__c
                        )
                    ));
                }

                return array( 'outreachLocations' => $unfinishedOutreachLocations );
            });

        } else {
            // the volunteer is a TAT Ambassador.
            // Get all Campaign/Events that are active, and have 'TAT Ambassador Event' checked, and that have the user as a member,
            // and are not completed.
            $today = substr( date( 'c' ), 0, 10 );
            $queryFields = array(
                'Id',
                'Name',
                'StartDate',
                'EndDate',
                'IsActive',
                'TAT_Ambassador_Event__c',
                'TAT_App_Report_Completed__c',
                'Location_of_Event__c',
                'State__c'
            );
            return getAllSalesforceQueryRecordsAsync(
                "SELECT " . implode(',', $queryFields) . " FROM Campaign " .
                "WHERE TAT_App_Report_Completed__c = false " .
                "AND TAT_Ambassador_Event__c = true " .
                "AND IsActive = true " .
                "AND Id IN (SELECT CampaignId FROM CampaignMember WHERE CampaignMember.ContactId = '{$contactID}') " .
                "AND (EndDate = NULL OR EndDate <= {$today})"
            )->then( function( $records ) {
                // convert to a better format
                $unfinishedEvents = array();
                foreach( $records as $record ) {
                    array_push( $unfinishedEvents, (object)array(
                        'id' => $record->Id,
                        'name' => $record->Name,
                        'startDate' => $record->StartDate,
                        'endDate' => $record->EndDate,
                        'location' => $record->Location_of_Event__c,
                        'state' => $record->State__c
                    ));
                }

                return array( 'events' => $unfinishedEvents );
            });
        }
    });
    
};

// //// end defining functions. Start high-level code.



// process the GET parameters
if ( !isset($_GET['parts']) ) {
    errorExit( 400, 'GET parameter "parts" not found.' );
}
$requestedParts = explode( ',', $_GET['parts'] );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$contactID = '';

// Get the ID of the Contact entry in salesforce
getSalesforceContactID( $firebaseUid )->then( function($contactID) {
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($contactID) {
        // make simultaneous requests to salesforce
        global $requestedParts, $apiFunctions;
        $promises = array();
        // call the appropriate API functions based on the requested parts passed through GET parameters
        foreach( $requestedParts as $part ) {
            // call the API function and store the promise
            $promise = $apiFunctions['getUserData'][$part]( $contactID );
            array_push( $promises, $promise );
        }
        // return an all-promise so the results of the request can be handled
        return \React\Promise\all( $promises );
    });
})->then( function($responses) {
    // all the request promises return an associative array. When these promises resolve, merge the arrays,
    // cast it to an object, convert it to JSON, and echo the output.
    $masterArray = array();
    foreach( $responses as $response ) {
        $masterArray = array_merge( $masterArray, $response );
    }
    http_response_code( 200 );
    echo json_encode( (object)$masterArray, JSON_PRETTY_PRINT );
})->otherwise(
    $handleRequestFailure
);

$loop->run();
