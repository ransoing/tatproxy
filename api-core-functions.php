<?php
// This file has functions which each directly map to an API call or API option.
// Each function must return a function which resolves with an associative array. The code that handles these
// promises merges the results of each promise together, and casts the merged array into an object, which it
// then outputs as the response to the API request.

// As mentioned elsewhere, for the getUserData API functions, multiple of these functions can be invoked simultaneously
// through the API by calling /api/getUserData/parts=[function1],[function2],[...]
// For example:
// /api/getUserData?parts=basic,unfinishedActivities

// This array maps API calls and getUserData 'parts' parameters to functions
$apiFunctions = array();

/**
 * Searches for a Contact by phone or email.
 * If either phone or email matches, it resolves with the first matching Contact ID. Otherwise it resolves with false.
 * URL: /api/contactSearch?email=[emailAddress]&phone=[phoneNumber]
 */
$apiFunctions['contactSearch'] = function( $email, $phone ) {
    // construct the query
    $query = "SELECT Id, TAT_App_Firebase_UID__c from Contact WHERE ";

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
            // check if the returned record already has an associated firebase UID
            if ( empty($records[0]->TAT_App_Firebase_UID__c) ) {
                return $records[0]->Id;
            } else {
                // return some expected error so the app knows that the matching Contact already has an associated firebase account
                throw new Exception( json_encode((object)array(
                    'errorCode' => 'ENTRY_ALREADY_HAS_ACCOUNT',
                    'message' => 'There is already a user account associated with this Contact entry.'
                )));
            }
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
    $queryFields = array(
        'TAT_App_Volunteer_Type__c',
        'TAT_App_Has_Watched_Training_Video__c',
        'FirstName',
        'LastName',
        'AccountId',
        'TAT_App_Materials_Street__c',
        'TAT_App_Materials_City__c',
        'TAT_App_Materials_State__c',
        'TAT_App_Materials_Zip__c',
        'TAT_App_Materials_Country__c',
        'TAT_App_Is_Team_Coordinator__c',
        'TAT_App_Team_Coordinator__c'
    );
    return salesforceAPIGetAsync(
        "sobjects/Contact/${contactID}/",
        array( 'fields' => implode(',', $queryFields) )
    )->then( function($response) use ($contactID) {
        // convert to a format that the app expects
        return array(
            'salesforceId' => $contactID,
            'volunteerType' => $response->TAT_App_Volunteer_Type__c,
            'hasCompletedTrainingFeedback' => $response->TAT_App_Has_Watched_Training_Video__c,
            'firstName' => $response->FirstName,
            'lastName' => $response->LastName,
            'accountId' => $response->AccountId,
            'street' => $response->TAT_App_Materials_Street__c,
            'city' => $response->TAT_App_Materials_City__c,
            'state' => $response->TAT_App_Materials_State__c,
            'zip' => $response->TAT_App_Materials_Zip__c,
            'country' => $response->TAT_App_Materials_Country__c,
            'isOnVolunteerTeam' => true, // @@ get this info from the user's Account
            'isTeamCoordinator' => $response->TAT_App_Is_Team_Coordinator__c,
            'teamCoordinatorId' => $response->TAT_App_Team_Coordinator__c
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
                'Street__c',
                'City__c',
                'State__c',
                'Zip__c',
                'Country__c',
                'Contact_Name__c',
                'Contact_Title__c',
                'Contact_Email__c',
                'Contact_Phone__c',
            );
            
            $teamCoordinator = $response->TAT_App_Is_Team_Coordinator__c ? $contactID : $response->TAT_App_Team_Coordinator__c;
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
                        'contact' => (object)array(
                            'name' => $record->Contact_Name__c,
                            'title' => $record->Contact_Title__c,
                            'email' => $record->Contact_Email__c,
                            'phone' => $record->Contact_Phone__c
                        )
                    ));
                }

                return array( 'outreachLocations' => $unfinishedOutreachLocations );
            });

        } else {
            // @@TODO
        }
    });
    
};
