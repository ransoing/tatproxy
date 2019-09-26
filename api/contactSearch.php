<?php

/**
 * The code for the contactSearch API call.
 * See index.php for usage details.
 * 
 * Searches Contact objects in Salesforce and returns the first one for which either
 * the given email matches any of the Contact's email fields, or the given phone number
 * matches any of the Contact's phone number fields.
 *
 * If either phone or email matches, it resolves with the first matching Contact ID. Otherwise it resolves with false.
 * URL: /api/contactSearch?email=[emailAddress]&phone=[phoneNumber]
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// process the GET parameters
if ( !isset($_GET['email']) || !isset($_GET['phone']) ) {
    errorExit( 400, 'You must define both GET parameters "email" and "phone".' );
}

addToLog( 'command: contactSearch. GET params:', $_GET );

// make the request.
makeSalesforceRequestWithTokenExpirationCheck( function() {
    
    $email = $_GET['email'];
    $phone = $_GET['phone'];

    // construct the query
    $query = "SELECT Id, TAT_App_Firebase_UID__c from Contact WHERE ";

    // match any one of several email fields
    $emailFields = array( 'npe01__HomeEmail__c', 'npe01__WorkEmail__c', 'npe01__AlternateEmail__c' );
    foreach( $emailFields as $i => $emailField ) {
        $emailFields[$i] = sprintf( "%s='%s'", $emailField, escapeSingleQuotes($email) );
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
        // take the single quotes out of the phone number
        $phone = str_replace( "'", "", $phone );
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
                throw new ExpectedException( json_encode((object)array(
                    'errorCode' => 'ENTRY_ALREADY_HAS_ACCOUNT',
                    'message' => 'There is already a user account associated with this Contact entry.'
                )));
            }
        } else {
            // return some expected error so that the app can know when no Contact entry matches the given email/phone
            throw new ExpectedException( json_encode((object)array(
                'errorCode' => 'NO_MATCHING_ENTRY',
                'message' => 'There is no Contact that has the specified email address or phone number.'
            )));
        }
    });
})->then( function($response) {
    echo json_encode( (object)array( 'salesforceId' => $response ), JSON_PRETTY_PRINT );
})->otherwise(
    $handleRequestFailure
);

$loop->run();
