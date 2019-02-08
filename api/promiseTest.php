<?php
require_once( '../functions.php' );
require_once( '../api-functions.php' );


// functions which return with a promise that resolves with parts of the user data object

function getBasicUserData( $contactID ) {
    return salesforceAPIGetAsync(
        "sobjects/Contact/${contactID}/",
        array('fields' => 'App_volunteer_type__c,App_has_watched_training_video__c,FirstName,LastName')
    )->then( function($response) {
        // convert to a format that the app expects
        return (object)array(
            'volunteerType' => $response['content']->App_volunteer_type__c,
            'hasWatchedTrainingVideo' => $response['content']->App_has_watched_training_video__c,
            'firstName' => $response['content']->FirstName,
            'lastName' => $response['content']->LastName
        );
    });
}

function getHoursLogs( $contactID ) {
    return getAllSalesforceQueryRecordsAsync(
        "SELECT Description__c, Date__c, NumHours__c from AppHoursLogEntry__c WHERE ContactID__c = '$contactID'"
    )->then( function($records) {
        // convert to a format that the app expects
        $hoursLogs = array();
        foreach( $records as $record ) {
            array_push( $hoursLogs, (object)array(
                'taskDescription' => $record->Description__c,
                'date' => $record->Date__c,
                'numHours' => $record->NumHours__c
            ));
        }

        return (object)array(
            'hoursLogs' => $hoursLogs
        );
    });
}

/////////////////////////////////////////////////

$handleRequestSuccess = function( $responses ) {
    foreach( $responses as $response ) {
        echo json_encode( $response, JSON_PRETTY_PRINT );
        echo "\n\n";
    }
};

$handleRequestFailure = function( $e ) {
    errorExit( 400, (string)$e->getResponse()->getBody() );
};



function makeRequests() {
    $contactID = '0031N00001tVsAmQAK';
    $promises = array(
        getBasicUserData( $contactID ),
        getHoursLogs( $contactID )
    );
    return \React\Promise\all( $promises );
}

makeRequests()->then(
    $handleRequestSuccess,
    function( $e ) use ($handleRequestFailure) {
        // find out if the error was due to an expired token
        if ( !empty($e->getResponse()) && !empty($e->getResponse()->getBody())  ) {
            $response = $e->getResponse();
            $bodyString = (string)$response->getBody();
            $body = getJsonBodyFromResponse( $response );
            // check if the token was expired so we can refresh it
            if (
                $response->getStatusCode() === 401 &&
                isset( $body[0] ) &&
                isset( $body[0]->errorCode ) &&
                $body[0]->errorCode === 'INVALID_SESSION_ID'
            ) {
                // refresh the token.
                refreshSalesforceTokenAsync()->then( function() {
                    // make the original request again.
                    makeRequests()->then( $handleRequestSuccess, $handleRequestFailure );
                }, $handleRequestFailure );
            } else {
                throw $e;
            }
        } else {
            throw $e;
        }
    }
)->then(
    function() {},
    $handleRequestFailure
);

$loop->run();











// $contactID = '0031N00001tVsAmQAK';
// $urlSegment = "sobjects/Contact/${contactID}/";
// $data = array('fields' => 'App_volunteer_type__c,App_has_watched_training_video__c,FirstName,LastName');
// $sfAuth = getSFAuth();
// $url = $sfAuth->instance_url . '/services/data/v44.0/' . $urlSegment . '.json?' . http_build_query( $data );
// $promise1 = $browser->get( $url, array('Authorization' => 'Bearer ' . $sfAuth->access_token) );


// try {
//     // $responses = \Clue\React\Block\awaitAll( array($promise1), $loop );
//     $response = \Clue\React\Block\await( $promise1, $loop );
//     echo "Success\n\n";
//     // echo (string)$responses[0]->getBody() . "\n\n";
//     echo (string)$response->getBody() . "\n\n";
// } catch( Exception $e ) {
//     echo "Rejected\n\n";
//     echo "Exception message:\n" . (string)$e->getMessage() . "\n\n";
//     echo "Body:\n";
//     $bodyString = (string)$e->getResponse()->getBody();
//     json_decode( $bodyString );
//     echo json_encode( json_decode($bodyString), JSON_PRETTY_PRINT );
//     echo "\n\n";
// }

// echo "\n\nAfterwards";



// $allPromises->then(
//     function( $responses ) {
//         echo "Resolved \n";
//         echo (string)$responses[0]->getBody();
//     },
//     function( $reason ) {
//         echo "Rejected \n\n";
//         // print_r( $reason );
//         echo "*Exception message*\n";
//         echo (string)$reason->getMessage() . "\n\n";
//         echo "*Body*\n";
//         echo (string)$reason->getResponse()->getBody();
//         return;
//     }
// );
// echo "Before loop.\n\n";
// $loop->run();


// echo <<<EOT
// {
//     "firstName" : "Bob",
//     "lastName" : "Smith",
//     "volunteerType": "truckStopVolunteer",
//     "hasWatchedTrainingVideo": true
// }
// EOT;
// exit;

/**
 * POST: /api/getBasicUserData
 * Gets a user's basic data from salesforce
 * POST Parameters:
 * firebaseIdToken: {string} - The user's Firebase login ID token, which is obtained after the user authenticates with Firebase.
 * 
 * Example:
 * URL: /api/getBasicUserData
 * POST data: 'firebaseIdToken=abcd1234'
 * 
 * Returns a JSON object containing some basic data on the user: name, volunteer type, and whether the user has watched the training video.
 * 
 * ```
 * {
 *      firstName: string,
 *      lastName: string,
 *      volunteerType: string,
 *      hasWatchedTrainingVideo: boolean
 * }
 * ```
 */



/*
$contactID = verifyFirebaseLogin();

// get volunteer type and whether the user has watched the training video
$contactResponse = salesforceAPIGet(
    "sobjects/Contact/${contactID}/",
    array('fields' => 'App_volunteer_type__c,App_has_watched_training_video__c,FirstName,LastName')
);
exitIfResponseHasError( $contactResponse );

// return a response in a format that the app expects
$responseContent = (object)array(
    'volunteerType' => $contactResponse['content']->App_volunteer_type__c,
    'hasWatchedTrainingVideo' => $contactResponse['content']->App_has_watched_training_video__c,
    'firstName' => $contactResponse['content']->FirstName,
    'lastName' => $contactResponse['content']->LastName
);
http_response_code( 200 );
echo json_encode( $responseContent, JSON_PRETTY_PRINT );
*/