<?php

/**
 * POST: /api/getUserData?parts=[part1,part2,...]
 * Gets data about a user from salesforce
 * POST Parameters:
 *  firebaseIdToken: {string} (required)
 *      The user's Firebase login ID token, which can be obtained through the firebase API
 *      after the user authenticates with Firebase. Note that this is not the uid -- this is
 *      the token that is used as proof of authentication in requests to firebase.
 * 
 * GET Parameters:
 *  parts (required)
 *      A comma-delimited list of which parts of the user data to return.
 *      Acceptable values in the list are "basic", "hoursLogs", "unfinishedOutreachTargets"
 * 
 * Example:
 * URL: /api/getUserData?parts=basic,hoursLogs,unfinishedOutreachTargets
 * POST data: 'firebaseIdToken=abcd1234'
 * 
 * Returns a JSON object containing data on the user.
 * 
 * If "basic" is in the list of parts, the API will return basic info on the user.
 * The following properties will be included in the returned object:
 * ```
 * {
 *      salesforceId: {string}, // the salesforce object identifier
 *      firstName: {string},
 *      lastName: {string},
 *      volunteerType: {string},
 *      hasWatchedTrainingVideo: {boolean}
 * }
 * ```
 * 
 * If "hoursLogs" is in the list of parts, the API will return an array of hours log entries that the
 * user has previously submitted.
 * The following properties will be included in the returned object:
 * ```
 * {
 *      hoursLogs: [
 *          {
 *              taskDescription: {string},
 *              date: {string},
 *              numHours: {number}
 *          }, {
 *              ...
 *          }
 *      ]
 * }
 * ```
 * 
 * If "unfinishedOutreachTargets" is in the list of parts, the API will return a list of outreach targets
 * (locations identified in pre-outreach form submissions) which the user has followed up with and does not
 * plan any additional follow-ups. Additional planned follow-up dates (identified by post-outreach surveys)
 * are included in the response.
 * The following properties will be included in the returned object:
 * ```
 * {
 *      unfinishedOutreachTargets: [
 *          {
 *              id: {string}, // the salesforce object identifier
 *              name: {string},
 *              type: {string},
 *              address: {string},
 *              city: {string},
 *              state: {string},
 *              zip: {string},
 *              postReports: [
 *                  {
 *                      followUpDate: {string | null}
 *                  }, {
 *                      ...
 *                  }
 *              ]
 *          }, {
 *              ...
 *          }
 *      ]
 * }
 * ```
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );
require_once( '../api-core-functions.php' );

// process the GET parameters
if ( !isset($_GET['parts']) ) {
    errorExit( 400, 'GET parameter "parts" not found.' );
}
$requestedParts = explode( ',', $_GET['parts'] );


/**
 * Run this function when all salesforce http requests succeed
 */
$handleRequestSuccess = function( $responses ) {
    // all the request promises return an associative array. When these rpomises resolve, merge the arrays,
    // cast it to an object, convert it to JSON, and echo the output.
    $masterArray = array();
    foreach( $responses as $response ) {
        $masterArray = array_merge( $masterArray, $response );
    }
    http_response_code( 200 );
    echo json_encode( (object)$masterArray, JSON_PRETTY_PRINT );
};

/**
 * Run this function when any salesforce http request fails (and the access token doesn't need to be refreshed)
 */
$handleRequestFailure = function( $e ) {
    errorExit( 400, (string)$e->getResponse()->getBody() );
};



/**
 * function to make simultaneous http requests to salesforce, but uses GET parameters
 * to only use the ones that are needed.
 */
function makeRequests() {
    global $contactID, $requestedParts, $apiFunctions;
    $promises = array();
    // call the appropriate API functions based on the requested parts passed through GET parameters
    foreach( $requestedParts as $part ) {
        // call the API function and store the promise
        $promise = $apiFunctions[$part]( $contactID );
        array_push( $promises, $promise );
    }
    // return an all-promise so the results of the request can be handled
    return \React\Promise\all( $promises );
}

// @@TODO: verifying the firebase login should return a firebase uid. Change verifyFirebaseLogin function.
// $contactID = verifyFirebaseLogin();

// @@TODO: the contactID will be the ID of the (yet-to-be-created) AppUser object in salesforce.
// Store the firebase uid as a property on the AppUser object, and retrieve the AppUser ID by
// making a query on salesforce to retrieve AppUser by firebase uid.
// getAllSalesforceQueryRecordsAsync( '...' );
$contactID = '0031N00001tVsAmQAK';

// Make all http requests. If any of them fail, check if the failure is due to an expired token.
// If it is, refresh the token and try the requests again.
makeRequests()->then(
    $handleRequestSuccess,
    function( $e ) use ($handleRequestFailure, $handleRequestSuccess) {
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
                refreshSalesforceTokenAsync()->then( function() use ($handleRequestFailure, $handleRequestSuccess) {
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
