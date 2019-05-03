<?php

/**
 * The high-level code for the getTeamCoordinators API call.
 * See index.php for usage details.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

makeSalesforceRequestWithTokenExpirationCheck( function() {
    return getAllSalesforceQueryRecordsAsync( "SELECT Id, FirstName, LastName from Contact WHERE TAT_App_Is_Team_Coordinator__c = true" );
})->then( function($records) {
    // convert the results to a pleasant format
    $coordinators = array();
    foreach( $records as $record ) {
        array_push( $coordinators, array(
            // 'name' => "{$record->FirstName} " . ' ' . $record->LastName,
            'name' => "{$record->FirstName} {$record->LastName}",
            'salesforceId' => $record->Id
        ));
    }
    // output as json
    echo json_encode( $coordinators );
})->otherwise(
    $handleRequestFailure
);

$loop->run();
