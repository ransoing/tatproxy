<?php
require_once( '../functions.php' );
require_once( '../api-support-functions.php' );
require_once( '../api-core-functions.php' );

$handleRequestSuccess = function( $responses ) {
    $masterArray = array();
    foreach( $responses as $response ) {
        $masterArray = array_merge( $masterArray, $response );
    }
    http_response_code( 200 );
    echo json_encode( (object)$masterArray, JSON_PRETTY_PRINT );
};

$handleRequestFailure = function( $e ) {
    errorExit( 400, (string)$e->getResponse()->getBody() );
};


function makeRequests() {
    //@@ use firebase id, copy documentation from other files, delete other files, take params to determine which funcs to run
    $contactID = '0031N00001tVsAmQAK';
    $promises = array(
        getBasicUserData( $contactID ),
        getHoursLogs( $contactID ),
        getUnfinishedOutreachTargets( $contactID )
    );
    return \React\Promise\all( $promises );
}

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
