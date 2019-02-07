<?php

// To support CORS, return 200 for HEAD or OPTIONS requests.
if ( $_SERVER['REQUEST_METHOD'] === 'HEAD' || $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
    http_response_code( 200 );
    exit;
}

function errorExit( $httpCode, $errorMessage ) {
    http_response_code( $httpCode );
    echo "{\"error\":\"${errorMessage}\"}";
    exit;
}

// gets POST data sent as either JSON or as form-encoded data.
// returns an object, or exits the script with an error.
function getPOSTData() {
    // check headers for one of two specific content-types
    $headers = getallheaders();
    $headerError = 'You must specify a Content-Type of either `application/x-www-form-urlencoded` or `application/json`';
    if ( !isset($headers['Content-Type']) ) {
        errorExit( 400, $headerError );
    }

    if ( $headers['Content-Type'] === 'application/json' ) {
        // parse the input as json
        $requestBody = file_get_contents( 'php://input' );
        $data = json_decode( $requestBody );
        if ( $data === null ) {
            errorExit( 400, 'Error parsing JSON' );
        }
        return $data;
    } else if  ( $headers['Content-Type'] === 'application/x-www-form-urlencoded' ) {
        // convert the $_POST data from an associative array to an object
        return (object)$_POST;
    } else {
        errorExit( 400, $headerError );
    }
}

// exit the script if the http request failed to execute, or if the response returned an http code other than 200.
// Also echo an error message in json format.
function exitIfResponseHasError( $response ) {
    // $response['error'] will be set if there was an error with the curl request itself or with parsing the API's JSON response.
    // $response['error'] is false only if the request completed successfully.
    // if the API returned an error, then the httpCode will be something other than 200
    if ( $response['error'] || $response['httpCode'] !== 200 ) {
        // httpCode may or may not be set.
        http_response_code( $response['httpCode'] ? $response['httpCode'] : 500 );
        // ['error'] may have an error message. If not, the error message is contained in ['content'].
        $errorResponse = (object)array( 'error' => $response['error'] ? $response['error'] : $response['content'] );
        echo json_encode( $errorResponse );
        exit;
    }
}

// performs a SOQL query and returns all records. This may take several requests to the API.
// i.e. getAllSalesforceQueryRecords( "SELECT Name from Contact WHERE Name LIKE 'S%' OR Name LIKE 'A%' OR Name LIKE 'R%'" )
// No need to run exitIfResponseHasError --- this peforms that with each request.
function getAllSalesforceQueryRecords( $query ) {
    $response = salesforceAPIGet( 'query/', array('q' => $query) );
    exitIfResponseHasError( $response );
    $records = $response['content']->records;

    while( !$response['content']->done ) {
        // get the segment of the url after /vXX.X/
        $nextRecordsUrl = $response['content']->nextRecordsUrl;
        $urlSegment = substr( $nextRecordsUrl, strpos($nextRecordsUrl, 'query/') );
        // make request for the next batch
        $response = salesforceAPIGet( $urlSegment );
        exitIfResponseHasError( $response );
        // concat the new records with the ones we have so far
        $records = array_merge( $records, $response['content']->records );
    }
    return $records;
}

// checks the POST parameters for a firebase ID token, which is proof of login, and verifies this token against firebase.
// If there was an error in this verification, the script echoes an error message and quits. Otherwise, it returns the
// user's salesforce Contact object ID.
function verifyFirebaseLogin() {
    $postData = getPOSTData();

    // verify that the required parameters are present
    if ( !isset($postData->firebaseIdToken) ) {
        errorExit( 400, '`firebaseIdToken` must be present in the POST parameters.' );
    }

    // verify against Firebase that the ID token is valid (i.e. it represents a logged-in user)
    $firebaseResponse = firebaseAuthAPIPost(
        'getAccountInfo',
        array( 'idToken' => $postData->firebaseIdToken )
    );
    // check if there was an error with the request itself
    if ( $firebaseResponse['error'] ) {
        errorExit( 400, "The request to Firebase failed to execute: " . $firebaseResponse['error'] );
    }
    // check if there was an error in the response from Firebase
    if ( isset($firebaseResponse['content']->error) ) {
        errorExit( 400, "The request to Firebase returned with an error: " . $firebaseResponse['content']->error->message );
    }

    // query fireDatabase to get the salesforce ID
    $fireDatabaseResponse = firebaseDbAPIGet(
        'users/' . $firebaseResponse['content']->users[0]->localId . '/salesforceId',
        array( 'auth' => $postData->firebaseIdToken )
    );
    // check if there was an error with the request itself
    if ( $firebaseResponse['error'] ) {
        errorExit( 400, "The request to FireDatabase failed to execute: " . $fireDatabaseResponse['error'] );
    }
    // check if there was an error in the response from Firebase
    if ( isset($fireDatabaseResponse['content']->error) ) {
        errorExit( 400, "The request to FireDatabase returned with an error: " . $fireDatabaseResponse['content']->error );
    }

    return $fireDatabaseResponse['content'];
}