<?php

require_once( './functions.php' );
require_once( './api-support-functions.php' );

/**
 * This file should be run once daily via cron. It sends notifications to devices, to remind users to fill out reports.
 * It can be run via a POST request, if the POST body contains a secret string, defined in config.json:notifications.cronSecret
 * This is for security, so that an anonymous person can't hit this URL repeatedly, spamming devices with notifications.
 */

logSection( 'Running notifications job' );

// the cronSecret is sent via POST, just as a simple string.
$sentSecret = file_get_contents( 'php://input' );
// check it against the saved secret
$config = getConfig();
if ( $sentSecret !== $config->notifications->cronSecret ) {
    echo 'You must send a valid secret via POST in order to run the notifications job.';
    return;
}

// save the time this job was run
file_put_contents( __DIR__ . '/notifications-last-run', time() );

// open translation files
$languages = (object) array (
    'en' => json_decode( file_get_contents(__DIR__ . '/external-resources/i18n/trx_en.json') ),
    'es' => json_decode( file_get_contents(__DIR__ . '/external-resources/i18n/trx_es.json') )
);
$defaultLanguage = 'en';

/**
 * $dotSeparatedKeys: a string in object dot notation, like 'server.notifications.postOutreach.title'
 * $language: the language code to use, i.e. 'es' or 'en'
 * $replacements: an associative array. The key is the value in {{brackets}} to replace, and the value is
 *      what to replace it with.
 */
function getTranslation( $dotSeparatedKeys, $language, $replacements = array() ) {
    global $languages, $defaultLanguage;
    $keys = explode( '.', $dotSeparatedKeys );
    $translationPointer = $languages->$language;
    foreach( $keys as $key ) {
        if ( !isset($translationPointer->$key) || empty($translationPointer->$key) ) {
            // if no translation is available, return a blank string or use the default language
            if ( $language === $defaultLanguage ) return '';
            else return getTranslation( $dotSeparatedKeys, $defaultLanguage, $replacements );
        }
        $translationPointer = $translationPointer->$key;
    }
    // replace keywords in {{brackets}}
    $translation = $translationPointer;
    foreach( $replacements as $key => $val ) {
        $translation = str_replace( '{{' . $key. '}}', $val, $translation );
    }
    return $translation;
}


makeSalesforceRequestWithTokenExpirationCheck( function() {
    logSection( 'Getting list of unfinished outreach locations' );
    // get today's date, and one week ago, both in format YYYY-MM-DD
    $today = substr( date('c'), 0, 10 );
    $oneWeekAgo = substr( date('c', time() - 7 * 24 * 60 * 60), 0, 10 );
    return getAllSalesforceQueryRecordsAsync(
        "SELECT Team_Lead__c, Planned_Date__c, Name, Id, Type__c FROM TAT_App_Outreach_Location__c " .
        "WHERE Is_Completed__c = false " .
        "AND Planned_date__c <= $today " .
        "AND Planned_date__c > $oneWeekAgo "
    );
})->then( function( $records ) {
    $promises = array();
    foreach( $records as $outreachLocation ) {
        // get a list of team members associated with this location's team lead
        logSection( 'Getting team members for outreach location ' . $outreachLocation->Id );
        $promise = getAllSalesforceQueryRecordsAsync(
            "SELECT TAT_App_Notification_Preferences__c, TAT_App_Volunteer_Type__c FROM Contact " .
            "WHERE ( TAT_App_Team_Coordinator__c = '$outreachLocation->Team_Lead__c' OR Id = '$outreachLocation->Team_Lead__c' ) " .
            "AND TAT_App_Volunteer_Type__c = 'volunteerDistributor'"
        )->then( function($teamResults) use ($outreachLocation) {
            // go through each team member's devices registered via FCM tokens, creating a list of which devices to send a
            // notification to about this outreach location, and in what language
            $fcmTokens = array();
            foreach( $teamResults as $teamMember ) {
                $prefs = json_decode( $teamMember->TAT_App_Notification_Preferences__c );
                if ( !is_null($prefs) ) {
                    foreach( $prefs as $token => $devicePrefs ) {
                        // for this fcm token, only add it to the tokens array if the device has opted in to notifications of the right type
                        if ( $devicePrefs->reportReminderEnabled ) {
                            if ( !isset($fcmTokens[$devicePrefs->language]) ) {
                                $fcmTokens[$devicePrefs->language] = array();
                            }
                            array_push( $fcmTokens[$devicePrefs->language], $token );
                        }
                    }
                }
            }

            // for each language, send a notification to multiple devices for this outreach location
            logSection( 'Sending notifications for outreach location ' . $outreachLocation->Id );
            foreach( $fcmTokens as $language => $tokens ) {
                sendNotification(
                    getTranslation( 'server.notifications.postOutreach.title', $language ),
                    getTranslation( 'server.notifications.postOutreach.body', $language, array('location' => $outreachLocation->Name) ),
                    array(
                        'type' => 'outreach_location',
                        'salesforceId' => $outreachLocation->Id
                    ),
                    $tokens
                );
            }
        });
    }
    return \React\Promise\all( $promises );
})->otherwise(
    $handleRequestFailure
);

$loop->run();

