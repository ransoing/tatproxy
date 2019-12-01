<?php

/**
 * The code for the unregisterFcmToken API call.
 * See index.php for usage details.
 * 
 * Removes a Firebase Cloud Messaging token from a list of tokens saved in Salesforce.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();

addToLog( 'command: unregisterFcmToken. POST data received:', $postData );
$fcmToken = $postData->fcmToken;

// get a list of FCM tokens, saved in salesforce, and remove the given token from it
getSalesforceContactID( $firebaseUid )->then( function($contactID) use ($fcmToken) {
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($fcmToken, $contactID) {
        logSection( 'Retrieving FCM tokens saved in user\'s Contact' );
        return salesforceAPIGetAsync(
            'sobjects/Contact/' . $contactID, array('fields' => 'TAT_App_Notification_Preferences__c')
        )->then( function($contact) use ($fcmToken, $contactID) {
            $fcmTokensString = $contact->TAT_App_Notification_Preferences__c;
            logSection( 'Parsing FCM tokens JSON' );
            $tokensObj = ( isset($fcmTokensString) ? json_decode($fcmTokensString) : array() );
            if ( is_null($tokensObj) ) {
                // something wrong with the parsing. Quit now.
                return;
            }

            // remove the token from the object
            unset( $tokensObj->$fcmToken );

            logSection( 'Updating FCM tokens JSON' );
            return salesforceAPIPatchAsync( "sobjects/Contact/{$contactID}/", array(
                'TAT_App_Notification_Preferences__c' => json_encode( $tokensObj )
            ));
        });
    });
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);

$loop->run();
