<?php

/**
 * The high-level code for the getTeamCoordinators API call.
 * See index.php for usage details.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

$accountId = $_GET['accountId'];

addToLog( 'command: getTeamCoordinators. GET params:', $_GET );

makeSalesforceRequestWithTokenExpirationCheck( function() use ($accountId) {
    return getTeamCoordinators( $accountId );
})->then( function($coordinators) {
    echo json_encode( $coordinators );
})->otherwise(
    $handleRequestFailure
);

$loop->run();
