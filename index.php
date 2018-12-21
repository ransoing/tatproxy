<?php
require_once( 'functions.php' );
$noConfigInstructions = 'Copy <code>config-sample.json</code> as <code>config.json</code> on the server and replace the sample values with real ones.';
/*
https://tatproxy.ransomchristofferson.com/util/sf_auth.php
https://macd3070.lasp.colorado.edu/~christof/util/sf_auth.php
https://localhost/tatproxy/sf_auth.php
*/

?><!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>TAT mobile app / Salesforce communication proxy</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" type="text/css" media="screen" href="main.css" />
</head>
<body>

<header>TAT mobile app / Salesforce communication proxy</header>
<main>
	<h1>About</h1>
	<p>
		The TAT Salesforce account stores data associated with each user of the <strong>TAT mobile app</strong>.
		The mobile app uses this data stored in Salesforce. However, the mobile app does not
		directly communicate with Salesforce&mdash;it communicates with this <em>proxy</em> instead.
		This proxy is given authorization to read any data from Salesforce. Users of the TAT app
		authenticate against this proxy, and the proxy filters data from Salesforce so that each
		user can only access his/her data.
	</p>
	<p>
		This gives the benefit of only requiring one Salesforce account for all users of the app, and keeps
		app user management separate from Salesforce user management.
	</p>
	<p>
		Users of the app authenticate with third-party authentication providers. This proxy stores essential
		data in a MySQL database to enable this.
	</p>
	<hr>

	<h1>Status</h1>

	<section>
		<header><img src="assets/sf-icon.png"> Salesforce authentication</header>
		<div>
			<?php

			$sfConfigInstructions = 'The salesforce connection vlaues must match the values given for the Connected App titled "TAT Mobile App". '
				. 'This can be found in the <a href="https://success.salesforce.com/answers?id=9063A000000DbnVQAS" target="_blank">App Manager</a>.';
			$sfAuthInstructions = 'A read-only account is ideal. The account only needs read-access to the TAT app user data.'
				. '<br><b>Do NOT authenticate with an admin account.</b>';

			$messages = [
				1 => [
					'error' => 'No config file found.',
					'instructions' => $noConfigInstructions . '<br><br>' . $sfConfigInstructions
				],
				2 => [
					'error' => 'Salesforce API access credentials not defined.',
					'instructions' => 'Edit <code>config.json</code> and define a callback URL, consumer secret, and consumer key. ' . $sfConfigInstructions
				],
				3 => [
					'error' => 'Proxy hasn\'t been authenticated.',
					'instructions' => 'Please authenticate using a Salesforce account with restricted privileges. ' . $sfAuthInstructions
				],
				4 => [
					'error' => 'Authentication is invalid or has expired.',
					'instructions' => 'Please re-authenticate using a Salesforce account with restricted privileges. ' . $sfAuthInstructions
				],
				5 => [
					'error' => 'Unexpected error.',
					'instructions' => ''
				]
			];

			$sfStatus = getSFStatus();
			?>

			<?php if ( $sfStatus === 0 ): ?>
			<p class="status ok">Connected</p>
			<?php else: ?>
			<p class="status error">Failed to connect to Salesforce.<br><i><?php echo $messages[$sfStatus]['error'] ?></i></p>
			<p><?php echo $messages[$sfStatus]['instructions'] ?></p>
			<?php endif; ?>

			<?php if ( $sfStatus === 3 || $sfStatus === 4 ): ?>
				<?php
				/**
				 * Show a button to authenticate with a salesforce user. Upon successful auth, we get a token
				 * that this proxy can use to authenticate itself whenever it needs to.
				 * This uses the "Web Server OAuth" flow:
				 * https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/intro_understanding_web_server_oauth_flow.htm
				 */
				
				$config = getConfig();
				$url = 'https://login.salesforce.com/services/oauth2/authorize?'
					. 'response_type=code'
					. '&client_id=' . urlencode( $config->salesforce->consumerKey )
					. '&redirect_uri=' . urlencode( $config->salesforce->authSuccessURL );
				?>

				<a href="<?php echo $url ?>" class="button">Authenticate this proxy</a>

			<?php endif; ?>
		</div>
	</section>

	<section>
		<header><img src="assets/db-icon.png"> App user database</header>
		<div>
			<?php

			$messages = [
				1 => [
					'error' => 'No config file found.',
					'instructions' => $noConfigInstructions
				],
				2 => [
					'error' => 'Database access credentials not defined.',
					'instructions' => 'Edit <code>config.json</code> and define a username, password, and database name for the MySQL connection.'
				],
				3 => [
					'error' => 'MySQL service unavailable.',
					'instructions' => ''
				],
				4 => [
					'error' => 'Database access credentials are invalid.',
					'instructions' => 'Ensure that the MySQL connection credentials in <code>config.json</code> are correct, and that the defined database exists.'
				],
				5 => [
					'error' => 'Unexpected error.',
					'instructions' => ''
				]
			];

			$dbStatus = getDBStatus();
			?>

			<?php if ( $dbStatus === 0 ): ?>
			<p class="status ok">Connected</p>
			<?php else: ?>
			<p class="status error">Failed to connect to database.<br><i><?php echo $messages[$dbStatus]['error'] ?></i></p>
			<p><?php echo $messages[$dbStatus]['instructions'] ?></p>
			<?php endif; ?>

		</div>
	</section>
</main>
</body>
</html>