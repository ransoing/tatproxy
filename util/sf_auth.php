<?php
// After the user has authenticated the proxy app with salesforce,
// salesforce redirects the user to this page, with a GET parameter
// in the request named 'code'.
// We can include 'code' in a request to salesforce to retrieve an
// access token (which is used as authentication) and a refresh token
// (which is used to get new access tokens)
// This page follows the flow on
// https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/intro_understanding_web_server_oauth_flow.htm

require_once( __DIR__ . '/../functions.php' );

/** Hides the loading thinger, shows a message, closes out the HTML, and stops execution of the PHP script. */
function doneExit( $message = '' ) {
    ?>
    <script>document.querySelector('.loading').style.display = 'none';</script>
    <p><?php echo $message ?></p>
    </main></body></html>
    <?php
	exit;
}

?><!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>TAT mobile app / Salesforce communication proxy</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" type="text/css" media="screen" href="../main.css" />
</head>

<body>
<header>TAT mobile app / Salesforce communication proxy</header>
<main>
    <div class="loading">
        <p>Connecting to Salesforce...</p>
        <div class="lds-circle"><div></div></div>
    </div>

    <?php
    ob_flush();
    flush();

    if ( !isset($_GET['code']) ) {
        doneExit( 'Error: GET parameter \'code\' expected but not present' );
    }
    
    // The 'code' expires after 15 minutes. Use it now to get access and refresh tokens.
    $config = getConfig();
    $_GET['code'];
    $response = post( 'https://login.salesforce.com/services/oauth2/token', array(
        'grant_type'    => 'authorization_code',
        'client_secret' => $config->salesforce->consumerSecret,
        'client_id'     => $config->salesforce->consumerKey,
        'redirect_uri'  => $config->salesforce->authSuccessURL,
        'code'          => $_GET['code']
    ));

    if ( $response['error'] ) {
        doneExit( 'Error: Could not retrieve access token from Salesforce. ' . $response['error'] );
    }
    if ( $response['httpCode'] !== 200 ) {
        // Show the error from Salesforce
        ?>
        <p>Could not retrieve access token from Salesforce.</p><p>Response from Salesforce:</p>
        <pre>HTTP response code: <?php echo $response['httpCode'] ?></pre>
        <pre><?php echo htmlspecialchars( $response['content'] ) ?></pre>
        <?php
        doneExit();
    }

    // Check the response for the expected parameters.
    $sfResponse = json_decode( $response['content'] );
    if (
        !isset($sfResponse->access_token) || empty($sfResponse->access_token) ||
        // I think the refresh_token is only needed if "Configure ID Token" is checked in the Salesforce Connected App settings?
        // !isset($sfResponse->refresh_token) || empty($sfResponse->refresh_token) ||
        !isset($sfResponse->instance_url) || empty($sfResponse->instance_url)
    ) {
        // Show the response from Salesforce
        ?>
        <p>Error: The response from Salesforce did not contain access_token<!--, refresh_token, --> and instance_url.</p><p>Response from Salesforce:</p>
        <pre><?php echo htmlspecialchars( $response['content'] ) ?></pre>
        <?php
        doneExit();
    }

    // Now we have the needed data to be able to connect. Save the response as-is to a file.
    if ( !file_put_contents('../sf-auth.json', $response['content']) ) {
        doneExit( 'Error: Could not write authentication data to disk. Check folder permissions on the server.' );
    }
    // set the sfAuth content so it's set to the new data when the API is called
    $sfAuth = json_decode( $response['content'] );

    // Test the credentials in a request to the API
    $apiResponse = apiGet( '' );
    if ( $apiResponse['error'] ) {
        doneExit( 'Error: failed to make a requst to the API. ' . $apiResponse['error'] );
    }
    if ( $apiResponse['httpCode'] !== 200 ) {
        // Show the response from Salesforce
        ?>
        <p>Error making an API call to Salesforce.</p><p>Response from Salesforce:</p>
        <pre>HTTP response code: <?php echo $apiResponse['httpCode'] ?></pre>
        <pre><?php echo htmlspecialchars( json_encode($apiResponse['content']) ) ?></pre>
        <?php
        doneExit();
    }

    // Show a success message
    ?><script>setTimeout( function() { window.location = window.location.pathname + '/../../' }, 5000 )</script><?php
    doneExit( 'Successfully connected to Salesforce. Redirecting you shortly...' );
    ?>
</main>
</body>
</html>
