<?php

/**
 * The code for the updateNotificationPreferences API call.
 * See index.php for usage details.
 * 
 * Adds a Firebase Cloud Messaging token to a list of tokens saved in Salesforce if it is not yet present.
 * Associates a number of preferences with that FCM token.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();

addToLog( 'command: updateNotificationPreferences. POST data received:', $postData );

// get a list of FCM tokens and preferences, saved in salesforce
getSalesforceContactID( $firebaseUid )->then( function($contactID) use ($postData) {
    return makeSalesforceRequestWithTokenExpirationCheck( function() use ($contactID) {
        logSection( 'Retrieving FCM preferences saved in user\'s Contact' );
        return salesforceAPIGetAsync( 'sobjects/Contact/' . $contactID, array('fields' => 'TAT_App_Notification_Preferences__c') );
    })->then( function($contact) use ($postData, $contactID) {
        logSection( 'Parsing FCM tokens JSON' );
        $tokensObj = (
            empty( $contact->TAT_App_Notification_Preferences__c ) ?
            (object) array() :
            json_decode( $contact->TAT_App_Notification_Preferences__c )
        );

        if ( is_null($tokensObj) ) {
            $tokensObj = (object) array();
        }
        $fcmToken = $postData->fcmToken;
        if ( !isset($tokensObj->$fcmToken) ) {
            $tokensObj->$fcmToken = (object) array();
        }

        if ( isset($postData->language) ) {
            $tokensObj->$fcmToken->language = $postData->language;
        }
        if ( isset($postData->preEventSurveyReminderEnabled) ) {
            $tokensObj->$fcmToken->preEventSurveyReminderEnabled = $postData->preEventSurveyReminderEnabled;
        }
        if ( isset($postData->reportReminderEnabled) ) {
            $tokensObj->$fcmToken->reportReminderEnabled = $postData->reportReminderEnabled;
        }
        if ( isset($postData->upcomingEventsReminderEnabled) ) {
            $tokensObj->$fcmToken->upcomingEventsReminderEnabled = $postData->upcomingEventsReminderEnabled;
        }

        // update the data in salesforce
        logSection( 'Updating FCM tokens JSON' );
        return salesforceAPIPatchAsync( "sobjects/Contact/{$contactID}/", array(
            'TAT_App_Notification_Preferences__c' => json_encode( $tokensObj )
        ));
    });
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);

$loop->run();
