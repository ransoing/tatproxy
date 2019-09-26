<?php

// use this class when throwing an error which is expected to happen during normal app usage.
// I.e. a user entering an incorrect registration code.
// When encountering errors which are not part of normal app usage (unexpected errors), use the Exception class.
// ExpectedExceptions typically have an 'errorCode' in all caps, and 'message'
class ExpectedException extends Exception {}

function errorExit( $httpCode, $errorMessage ) {
    http_response_code( $httpCode );
    echo $errorMessage;
    exit;
}

// Use this for the highest level failure handler (after determining that the access token desn't need to be refreshed)
$handleRequestFailure = function( $e ) {
    if ( method_exists($e, 'getResponse') ) {
        $message = $e->getResponse()->getBody();
    } else {
        $message = $e->getMessage();
    }
    if ( !($e instanceof ExpectedException) ) {
        // this is an unexpected exception. send an email to somebody who wants to know about these
        $emailBody = str_replace("\n", "\n<br>", getLog()) . "\n\n<br><br>" . $message;
        sendMail( getConfig()->sendErrorsTo, 'Error during tatproxy execution', $emailBody );
    }
    errorExit( 400, $message );
};


$log = '';
function addToLog( $description, $thing = false ) {
    global $log;
    $log .= $description . "\n";
    if ( !empty($thing) ) {
        // @@ replacd with var_dump but capture the output to a string
        $log .= print_r( $thing, true ) . "\n";
    }
    $log .= "\n";
}
function getLog() {
    global $log;
    return $log;
}
