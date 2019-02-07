<?php
require_once( '../functions.php' );
require_once( '../api-functions.php' );

// echo <<<EOT
// {
//     "hoursLogs": [
//         {
//             "taskDescription": "Second hours log entry",
//             "date": "2019-01-01",
//             "numHours": 12
//         },
//         {
//             "taskDescription": "Test description\\nof task",
//             "date": "2019-01-15",
//             "numHours": 4
//         },
//         {
//             "taskDescription": "oh hello.",
//             "date": "2112-12-12",
//             "numHours": 400
//         }
//     ]
// }
// EOT;
// exit;

/**
 * POST: /api/getHoursLogs
 * Gets the logs of volunteer hours (from salesforce) that the user has submitted
 * POST Parameters:
 * firebaseIdToken: {string} - The user's Firebase login ID token, which is obtained after the user authenticates with Firebase.
 * 
 * Example:
 * URL: /api/getHoursLogs
 * POST data: 'firebaseIdToken=abcd1234'
 * 
 * Returns a JSON object containing an array of hours log entries.
 * 
 * ```
 * [
 *  {
 *      taskDescription: string,
 *      date: string,
 *      numHours: number
 *  }
 * ]
 * ```
 */


$contactID = verifyFirebaseLogin();

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

$response = (object)array(
    'hoursLogs' => $hoursLogs
);

http_response_code( 200 );
echo json_encode( $response, JSON_PRETTY_PRINT );
