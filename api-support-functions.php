<?php

$appUserIdCacheFilePath = __DIR__ . '/app-user-ids.json';
$appUserIdSqliteFilePath = __DIR__ . '/app-user-ids.sqlite';

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


/**
 * Checks the POST parameters for a firebase ID token, which is proof of login, and verifies this token against firebase.
 * If there was an error in this verification, the script echoes an error message and quits. Otherwise, it returns the
 * user's salesforce Contact object ID.
 */
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


function getSalesforceAppUserID( $firebaseUid ) {
    // see if we've already saved the appUserID for this firebase user
    $cachedID = getCachedAppUserID( $firebaseUid );
    if ( $cachedID !== false ) {
        // @@
        echo "Read from cache file\n";
        // return a promise with the saved ID
        $deferred = new \React\Promise\Deferred();
        $deferred->resolve( $cachedID );
        return $deferred->promise();
    }

    // @@ change the query to something like    WHERE firebase_uid__c = '$firebaseUid'
    return getAllSalesforceQueryRecordsAsync( "SELECT Id from Contact WHERE FirstName = 'Ransom'" )->then(
        function( $queryRecords ) {
            print_r($queryRecords );
            if ( sizeof($queryRecords) === 0 ) {
                throw new Exception( 'User does not exist in salesforce.' ); // @@ return some expected error so that the app can know when the user is a new user, without salesforce id.
            }
            $appUserID = $queryRecords[0]->Id;
            // write the ID to file so we can avoid this http request in the future
            cacheAppUserID( $firebaseUid, $appUserID );
        }
    );
}


/**
 * Returns a string representing the AppUserID, or returns false if not present.
 */
function getCachedAppUserID( $firebaseUid ) {
    // the cache may be saved as a json file, or saved in a sqlite db
    if ( class_exists('SQLite3') && file_exists($appUserIdSqliteFilePath) ) {
        // load from sqlite
        $db = new SQLite3();
        $db->open( $appUserIdSqliteFilePath );
        $result = $db->query( "SELECT * FROM cache WHERE firebaseUid='$firebaseUid'" );
        $row = $result->fetchArray();
        $db->close();
        if ( $row ) {
            return $row['appUserID'];
        }
    } else if ( file_exists($appUserIdCacheFilePath) ) {
        // load from json file
        $appUserIdCache = json_decode( file_get_contents($appUserIdCacheFilePath) );
        if ( isset($appUserIdCache[$firebaseUid]) ) {
            return $appUserIdCache[$firebaseUid];
        }
    }

    return false;
}

/**
 * Saves a firebaseUid value and associated appUserID value to either a SQLite database or a json file, depending on whether
 * SQLite3 is installed.
 */
function cacheAppUserID( $firebaseUid, $appUserID ) {
    // try saving to sqlite db first, then to a json file
    if ( class_exists('SQLite3') ) {
        $db = new SQLite3();
        $db->open( $appUserIdSqliteFilePath );
        // check if the cache table exists and create it if it doesn't
        $db->exec( "CREATE TABLE IF NOT EXISTS cache (id INTEGER PRIMARY KEY AUTOINCREMENT, firebaseUid TEXT, appUserID TEXT)" );
        // add the value to the cache table
        $db->exec( "INSERT INTO cache (firebaseUid, appUserID) VALUES ('$firebaseUid', '$appUserID'" );
        $db->close();
    } else {
        // read the existing file if there is one
        if ( file_exists($appUserIdCacheFilePath) ) {
            $appUserIdCache = json_decode( file_get_contents($appUserIdCacheFilePath) );
        } else {
            $appUserIdCache = array();
        }
        // add the new cache value to the file
        $appUserIdCache[ $firebaseUid ] = $appUserID;
        file_put_contents( $appUserIdCacheFilePath, json_encode($appUserIdCache) );
    }
}


/**
 * Makes a GET request to the salesforce API and returns a Promise. Does not atomatically refresh the access token.
 * Resolves with the salesforce response, or rejects with an error object. Use ->getMessage() to get the error message
 * or ->getResponse() to get the response object.
 */
function salesforceAPIGetAsync( $urlSegment, $data = array() ) {
    global $browser;
    $deferred = new \React\Promise\Deferred();
    $sfAuth = getSFAuth();
    $url = $sfAuth->instance_url . '/services/data/v44.0/' . $urlSegment . '.json?' . http_build_query( $data );
    
    // add access token to header and make the request
    $browser->get( $url, array('Authorization' => 'Bearer ' . $sfAuth->access_token) )->then(
        function( $response ) use ($deferred) {
            $deferred->resolve( getJsonBodyFromResponse($response) );
        },
        function( $e ) use ($deferred) {
            $deferred->reject( $e );
        }
    );

    return $deferred->promise();
}

// performs a SOQL query and returns all records. This may take several requests to the API.
// i.e. getAllSalesforceQueryRecordsAsync( "SELECT Name from Contact WHERE Name LIKE 'S%' OR Name LIKE 'A%' OR Name LIKE 'R%'" )
function getAllSalesforceQueryRecordsAsync( $query ) {
    $deferred = new \React\Promise\Deferred();
    $records = [];
    salesforceAPIGetAsync( 'query/', array('q' => $query) )->then(
        function( $response ) use (&$records) {
            $records = $response->records;
            return getNextRecordsAsync( $response, $records );
        },
        function( $e ) use ($deferred) {
            $deferred->reject( $e );
        }
    )->then( function() use ($deferred, &$records) {
        $deferred->resolve( $records );
    }, function($e) use ($deferred) {
        $deferred->reject( $e );
    });
    return $deferred->promise();
}

/**
 * Recursively gets the next set of records in a query.
 */
function getNextRecordsAsync( $response, &$records ) {
    $deferred = new \React\Promise\Deferred();
    if ( $response->done ) {
        $deferred->resolve();
    } else {
        // get the segment of the url after /vXX.X/
        $nextRecordsUrl = $response->nextRecordsUrl;
        $urlSegment = substr( $nextRecordsUrl, strpos($nextRecordsUrl, 'query/') );
        // make request for the next batch
        return salesforceAPIGetAsync( $urlSegment )->then(
            function( $nextResponse ) use (&$records) {
                // concat the new records with the ones we have so far
                $records = array_merge( $records, $nextResponse->records );
                return getNextRecordsAsync( $nextResponse, $records );
            }
        )->then( function() use ($deferred) {
            $deferred->resolve();
        }, function($e) use ($deferred) {
            $deferred->reject();
        });
    }
    return $deferred->promise();
}

/**
 * Attempts to refresh the salesforce access token. Returns a promise. Resolves with true on success,
 * or rejects with an error object, which has ->getMessage() and ->getResponse()
 */
function refreshSalesforceTokenAsync() {
    global $browser;
	$deferred = new \React\Promise\Deferred();
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
        function( $refreshResponse ) use ($deferred) {
            // save the new access token to disk and to the global variable
            global $sfAuth;
            $refreshBody = getJsonBodyFromResponse( $refreshResponse );
            $sfAuth->access_token = $refreshBody->access_token;
            file_put_contents( __DIR__ . '/sf-auth.json', json_encode($sfAuth) );
            $deferred->resolve( true );
        }, function( $e ) use ($deferred) {
            // the refresh token didn't work
            $deferred->reject( $e );
        }
    );
    return $deferred->promise();
}


function getJsonBodyFromResponse( $response ) {
	$json = json_decode( (string)$response->getBody() );
	if ( $json === null ) throw new Exception( 'Malformed json.' );
	return $json;
}