<?php
require_once( __DIR__ . '../functions.php' );

// if there are no database access credentials, or the credentials are invalid,
// change the credentials to those in the POST request.
$dbStatus = getDBStatus();
if ( $dbStatus === 2 || $dbStatus === 3 ) {
    

} else {
    echo 'Credentials already provided.';
}