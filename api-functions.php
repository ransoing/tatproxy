<?php

// To support CORS, return 200 for HEAD or OPTIONS requests.
if ( $_SERVER['REQUEST_METHOD'] === 'HEAD' || $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
    http_response_code( 200 );
    exit;
}

function errorExit( $httpCode, $errorMessage ) {
    http_response_code( $httpCode );
    echo $errorMessage;
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

/**
 * Makes a GET request to the salesforce API and returns a Promise. Does not atomatically refresh the access token.
 * Resolves with the salesforce response, or rejects with an error object. Use ->getMessage() to get the error message
 * or ->getResponse() to get the response object.
 */
function salesforceAPIGetAsync( $urlSegment, $data = array() ) {
	return new \React\Promise\Promise( function(callable $resolve, callable $reject) use ($urlSegment, $data) {
		global $browser;

		$sfAuth = getSFAuth();
		$url = $sfAuth->instance_url . '/services/data/v44.0/' . $urlSegment . '.json?' . http_build_query( $data );
		
		// add access token to header and make the request
		$browser->get( $url, array('Authorization' => 'Bearer ' . $sfAuth->access_token) )->then(
			function( $response ) use ($resolve) {
				$resolve( getJsonBodyFromResponse($response) );
			},
			function( $e ) use ($reject) {
				$reject( $e );
			}
		);
	});
}

// performs a SOQL query and returns all records. This may take several requests to the API.
// i.e. getAllSalesforceQueryRecordsAsync( "SELECT Name from Contact WHERE Name LIKE 'S%' OR Name LIKE 'A%' OR Name LIKE 'R%'" )
function getAllSalesforceQueryRecordsAsync( $query ) {
    return new \React\Promise\Promise( function(callable $resolve, callable $reject) use ($query) {
        $records = [];
        return salesforceAPIGetAsync( 'query/', array('q' => $query) )->then(
            function( $response ) use (&$records) {
                $records = $response->records;
                return getNextRecordsAsync( $response, $records );
            },
            function( $e ) use ($reject) {
                $reject( $e );
            }
        )->then( function() use ($resolve, &$records) {
            $resolve( $records );
        }, function($e) use ($reject) {
            $reject( $e );
        });
    });
}

/**
 * Recursively gets the next set of records in a query.
 */
function getNextRecordsAsync( $response, &$records ) {
    return new \React\Promise\Promise( function(callable $resolve, callable $reject) use ($response, &$records) {
        if ( $response->done ) {
            $resolve();
        } else {
            // get the segment of the url after /vXX.X/
            $nextRecordsUrl = $response->nextRecordsUrl;
            $urlSegment = substr( $nextRecordsUrl, strpos($nextRecordsUrl, 'query/') );
            // make request for the next batch
            return salesforceAPIGetAsync( $urlSegment )->then(
                function( $nextResponse ) use ($resolve, $reject, &$records) {
                    // concat the new records with the ones we have so far
                    $records = array_merge( $records, $nextResponse->records );
                    return getNextRecordsAsync( $nextResponse, $records );
                }
            )->then( function() use ($resolve) {
                $resolve();
            }, function($e) use ($reject) {
                $reject();
            });
        }
    });
}

/**
 * Attempts to refresh the salesforce access token. Returns a promise. Resolves with true on success,
 * or rejects with an error object, which has ->getMessage() and ->getResponse()
 */
function refreshSalesforceTokenAsync() {
	return new \React\Promise\Promise( function(callable $resolve, callable $reject) {
		global $browser;
		$sfAuth = getSFAuth();
		$config = getConfig();

		// get a new auth token using the refresh token
		$browser->post(
			'https://login.salesforce.com/services/oauth2/token',
			array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			http_build_query( array(
				'grant_type'	=> 'refresh_token',
				'refresh_token'	=> $sfAuth->refresh_token,
				'client_id'     => $config->salesforce->consumerKey,
				'client_secret' => $config->salesforce->consumerSecret,
				'format'		=> 'json'
			))
		)->then(
			function( $refreshResponse ) use ($resolve, $reject) {
				// save the new access token to disk and to the global variable
				global $sfAuth;
				$refreshBody = getJsonBodyFromResponse( $refreshResponse );
				$sfAuth->access_token = $refreshBody->access_token;
				file_put_contents( __DIR__ . '/sf-auth.json', json_encode($sfAuth) );
				$resolve( true );
			}, function( $e ) use ($reject) {
				// the refresh token didn't work
				$reject( $e );
			}
		);
	});
}


function getJsonBodyFromResponse( $response ) {
	$json = json_decode( (string)$response->getBody() );
	if ( $json === null ) throw new Exception( 'Malformed json.' );
	return $json;
}