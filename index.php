<?php
require_once( 'functions.php' );

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
		authenticate with a separate service (Firebase), and the proxy filters data from Salesforce so that each
		user can only access his/her data.
	</p>
	<p>
		This gives the benefit of only requiring one Salesforce account for all users of the app, and keeps
		app user management separate from Salesforce user management (for TAT staff).
	</p>
	<p>
		Users of the app authenticate with Firebase. The app passes their Firebase ID token to the proxy, and the proxy
		confirms with Firebase that the user is logged in, before giving the user their Salesforce data.
	</p>
	<hr>

	<h1>Status</h1>

	<section>
		<header><img src="assets/salesforce-icon.png"> Salesforce authentication</header>
		<div>
			<?php $sfStatus = getSalesforceStatus() ?>
			<?php if ( $sfStatus['error'] ) : ?>
				<p class="status error">Failed to connect to Salesforce.<br><i><?php echo $sfStatus['error'] ?></i></p>
				<p><?php echo $sfStatus['instructions'] ?></p>
				<?php if ( isset($sfStatus['errorDetails']) ): ?>
					<pre><?php echo htmlspecialchars( json_encode($sfStatus['errorDetails']) ) ?></pre>
				<?php endif; ?>
			<?php else: ?>
				<p class="status ok">Connected.</p>
			<?php endif; ?>

			<?php if ( $sfStatus['code'] === 3 || $sfStatus['code'] === 4 ): ?>
				<?php
				/**
				 * Show a button to authenticate with a salesforce user. Upon successful auth, we get a token
				 * that this proxy can use to authenticate itself whenever it needs to.
				 * This uses the "Web Server OAuth" flow:
				 * https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/intro_understanding_web_server_oauth_flow.htm
				 */
				
				$config = getConfig();
				$url = 'https://login.salesforce.com/services/oauth2/authorize?' . http_build_query( array(
					'response_type' => 'code',
					'client_id'		=> $config->salesforce->consumerKey,
					'redirect_uri'  => $config->salesforce->authSuccessURL
				));
				?>

				<a href="<?php echo $url ?>" class="button">Authenticate this proxy</a>

			<?php endif; ?>
		</div>
	</section>

	<section>
		<header><img src="assets/firebase-icon.png"> Firebase connection</header>
		<div>
			<?php $fbStatus = getFirebaseStatus() ?>
			<?php if ( $fbStatus['error'] ) : ?>
				<p class="status error">Failed to connect to Firebase.<br><i><?php echo $fbStatus['error'] ?></i></p>
				<p><?php echo $fbStatus['instructions'] ?></p>
				<?php if ( isset($fbStatus['errorDetails']) ): ?>
					<pre><?php echo htmlspecialchars( json_encode($fbStatus['errorDetails']) ) ?></pre>
				<?php endif; ?>
			<?php else: ?>
				<p class="status ok">Connected.</p>
			<?php endif; ?>
		</div>
	</section>

	<hr>
	<h1>Usage</h1>
	<p>Add API details here.</p>
</main>
</body>
</html>