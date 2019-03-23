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
		This proxy is given authorization to read any user's data from Salesforce. Users of the TAT app
		authenticate with a separate service (Firebase), and the proxy filters data from Salesforce so that each
		user can only access his/her data.
	</p>
	<p>
		This gives the benefit of only requiring one Salesforce account for all users of the app, and keeps
		app user management separate from Salesforce user management.
	</p>
	<p>
		Users of the app authenticate with Firebase. The app passes the user's Firebase ID token to the proxy, and the proxy
		confirms with Firebase that the token is valid and retrieves the user's FirebaseUid from Firebase. The proxy then
		retrieves the Salesforce data that is associated with that FirebaseUid, and passes it on to the user.
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

	<div class="api-docs">
		<h1>API</h1>

		<h2>contactSearch</h2>
		<p>
			<b>contactSearch</b> searches for Salesforce Contact objects by email address or phone number. If any Contact object has either the given
			email address or phone number, then that Contact's Id is returned.
		</p>

		<h3>Make a GET request to:</h3>
		<pre>/api/contactSearch?email=[EMAIL_ADDRESS]&phone=[PHONE_NUMBER]</pre>

		<h3>GET parameters</h3>
		<section>
			<div>
				<p><code>email</code> {string} (required)</p>
				<p class="api-def">An email address. For example, <code>joe.somebody@example.com</code></p>
				<p><code>phone</code> {string} (required)</p>
				<p class="api-def">A phone number in any format. The format doesn't matter. For example, <code>5551234567</code> or <code>(555) 123-4567</code>.</p>
			</div>
		</section>

		<h3>Response payload</h3>
		<p>The API returns a JSON object containing a matching Salesforce Contact Id.</p>
		<pre>{
    salesforceId: {string}
}</pre>
		<p>If there is no matching Contact in Salesforce, the API returns this error:</p>
		<pre>{
    errorCode: "NO_MATCHING_ENTRY",
    message: "There is no Contact that has the specified email address or phone number."
}</pre>

		<p>If there is a matching Contact, but it already has an associated Firebase user account, the API returns this error:</p>
		<pre>{
    errorCode: "ENTRY_ALREADY_HAS_ACCOUNT",
    message: "There is already a user account associated with this Contact entry."
}</pre>

		<h3>Example request</h3>
		<p>URL:</p>
		<pre>GET /api/contactSearch?email=joe.blow@example.com&phone=5559092332</pre>


		<h2>getUserData</h2>
		<p><b>getUserData</b> provides a way for the TAT mobile app to get a user's data.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/getUserData?parts=[LIST_OF_PARTS]</pre>

		<h3>Required headers</h3>
		<p>One of the following headers must be present in the request:</p>
		<pre>Content-Type: application/x-www-form-urlencoded</pre>
		<pre>Content-Type: application/json</pre>

		<h3>GET parameters</h3>
		<section>
			<div>
				<p><code>parts</code> {string} (required)</p>
				<p class="api-def">
					A comma-separated list of values. These values define what data will be returned.<br>
					Acceptable values are: <code>basic</code>, <code>hoursLogs</code>, <code>unfinishedOutreachTargets</code>.<br>
				</p>
			</div>
		</section>

		<h3>POST request body payload parameters</h3>
		<section>
			<div>
				<p><code>firebaseIdToken</code> {string} (required)</p>
				<p class="api-def">
					<a href="https://firebase.google.com/docs/auth/admin/verify-id-tokens" target="_blank">A token retireved
					from Firebase after the user authenticates</a>, which can be used to identify the user, verify his
					login state, and access various Firebase resources.<br>
				</p>
			</div>
		</section>
		
		<h3>Response payload</h3>
		<p>The API returns a JSON object containing data on the user.</p>

		<p>
			If <code>basic</code> is in the list of parts, the API will return basic info on the user.<br>
			The following properties will be included in the returned object:
		</p>
		<pre>{
    salesforceId: {string}, // the identifier of the object in Salesforce representing the user
    firstName: {string},
    lastName: {string},
    volunteerType: {string},
    hasWatchedTrainingVideo: {boolean},
    address: {string},
    city: {string},
    state: {string},
    zip: {string}
}</pre>

		<p>
			If <code>hoursLogs</code> is in the list of parts, the API will return an array of hours log entries that the
			user has previously submitted.<br>
			The following properties will be included in the returned object:
		</p>
		<pre>{
    hoursLogs: [
        {
            taskDescription: {string},
            date: {string},
            numHours: {number}
        }, {
            ...
        }
    ]
}</pre>

		<p>
			If <code>unfinishedOutreachTargets</code> is in the list of parts, the API will return a list of outreach targets
			(locations identified in pre-outreach form submissions) which the user has either not followed up with, or plans to 
			do additional follow-ups for. Additional planned follow-up dates (identified by post-outreach surveys) are included
			in the response.<br>
			The following properties will be included in the returned object:
		</p>
		<pre>{
    unfinishedOutreachTargets: [
        {
            id: {string}, // the identifier of the Salesforce object representing the outreach target
            name: {string},
            type: {string},
            address: {string},
            city: {string},
            state: {string},
            zip: {string},
            postReports: [
                {
                    followUpDate: {string | null}
                }, {
                    ...
                }
            ]
        }, {
            ...
        }
    ]
}</pre>

		<p>
			If the user defined by the firebaseIdToken does not have an entry in Salesforce, the API will return the following
			response with a 400 HTTP response code:
		</p>
		<pre>{
    errorCode: "FIREBASE_USER_NOT_IN_SALESFORCE",
    message: "The specified Firebase user does not have an associated Contact entry in Salesforce."
}</pre>

		<h3>Example request</h3>
		<p>URL:</p>
		<pre>POST /api/getUserData?parts=basic,hoursLogs,unfinishedOutreachTargets</pre>
		<p>Headers:</p>
		<pre>Content-Type: application/json</pre>
		<p>Request body:</p>
		<pre>{ "firebaseIdToken": "abcd1234" }</pre>

	</div>
	
</main>
</body>
</html>