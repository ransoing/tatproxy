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
				$url = "${salesforceOAuthBase}/authorize?" . http_build_query( array(
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

	<section>
		<header><img src="assets/mail-icon.png"> Mailer</header>
		<div>
			<?php if ( !$mailerSetUp ) : ?>
				<p class="status error">Mailer not configured.</p>
				<p>Edit <code>config.json</code> on the server to configure the mailer.</p>
			<?php else: ?>
				<a href="./util/test-email.php" class="button" style="float:right">Send test email</a>
				<p class="status ok">Configured.</p>
			<?php endif; ?>
		</div>
	</section>

	<hr>

	<div class="api-docs">
		<h1>API</h1>

		<a name="required-post-headers"></a>
		<h3 style="margin-top:2em">Required POST headers</h3>
		<p>All POST requests to the API must include the following header:</p>
		<pre>Content-Type: application/json</pre>
		<p>The POST payload must be JSON-formatted.</p>

		<a name="error-format"></a>
		<h3 style="margin-top: 2em">Error responses</h3>
		<p>If a request results in an error, the API will return a 400 http response code and return a JSON-formatted object like the following:</p>
		<pre>{
    errorCode: "SOME_ERROR_CODE",
    message: "A short description of the error."
}</pre>
		<p>The documentation for each method lists the different possible error codes.</p>

		<hr style="margin: 4em 0">
		
		<h3>Methods</h3>
		<ul class="api-links">
			<li><a href="#checkRegistrationCode">checkRegistrationCode</a></li>
			<li><a href="#contactSearch">contactSearch</a></li>
			<li><a href="#getTeamCoordinators">getTeamCoordinators</a></li>
			<li><a href="#createNewUser">createNewUser</a></li>
			<li><a href="#updateUser">updateUser</a></li>
			<li><a href="#getUserData">getUserData</a></li>
			<li><a href="#getCampaigns">getCampaigns</a></li>
			<li><a href="#createTestimonial">createTestimonial</a></li>
			<li><a href="#createPreOutreachSurvey">createPreOutreachSurvey</a></li>
			<li><a href="#createPostOutreachReport">createPostOutreachReport</a></li>
			<li><a href="#deleteOutreachLocation">deleteOutreachLocation</a></li>
		</ul>

		<!-- ==================================== -->
		<a name="checkRegistrationCode"></a>
		<h2>checkRegistrationCode</h2>
		<p>
			<b>checkRegistrationCode</b> verifies whether a registration code is valid. If it is, it returns the related volunteer type and any other related information (like campaign ID).
		</p>

		<h3>Make a GET request to:</h3>
		<pre>/api/checkRegistrationCode?code=[CODE]</pre>

		<h3>GET parameters</h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>code</code> {string} (required)</p>
					<p>The code (password) required to create a new user account.</p>
				</div>
			</section>
		</div>

		<h3>Response payload</h3>
		<pre>{
    success: true,
	volunteerType: 'volunteerDistributor' | 'ambassadorVolunteer',
	// for volunteerDistributor users:
	accountId: {string}, // representing one or more teams of volunteers
	isIndividualDistributor: {boolean},
	teamCoordinators: {
		name: {string},
		salesforceId: {string}
	}[]
}</pre>

		<h3>Error codes <a class="help" href="#error-format">?</a></h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>INCORRECT_REGISTRATION_CODE</code></p>
					<p>The provided registration code was incorrect.</p>
				</div>
			</section>
		</div>

		<h3>Example request</h3>
		<pre>GET /api/checkRegistrationCode?code=correct-horse-battery-staple</pre>


		<!-- ==================================== -->
		<a name="contactSearch"></a>
		<h2>contactSearch</h2>
		<p>
			<b>contactSearch</b> searches via email address or phone number for Salesforce Contact objects which are
			not associated with Firebase user accounts. If any unassociated Contact object has either the given email
			address or phone number, then that Contact's Id is returned.
		</p>

		<h3>Make a GET request to:</h3>
		<pre>/api/contactSearch?email=[EMAIL_ADDRESS]&phone=[PHONE_NUMBER]</pre>

		<h3>GET parameters</h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>email</code> {string} (required)</p>
					<p>An email address. For example, <code>joe.somebody@example.com</code></p>
				</div>
				<div>
					<p><code>phone</code> {string} (required)</p>
					<p>A phone number in any format. The format doesn't matter. For example, <code>5551234567</code> or <code>(555) 123-4567</code>.</p>
				</div>
			</section>
		</div>

		<h3>Response payload</h3>
		<pre>{
    salesforceId: {string}
}</pre>

		<h3>Error codes <a class="help" href="#error-format">?</a></h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>NO_MATCHING_ENTRY</code></p>
					<p>There is no matching Contact in Salesforce.</p>
				</div>
				<div>
					<p><code>ENTRY_ALREADY_HAS_ACCOUNT</code></p>
					<p>There is a matching Contact, but it already has an associated Firebase user account.</p>
				</div>
			</section>
		</div>

		<h3>Example request</h3>
		<pre>GET /api/contactSearch?email=joe.blow@example.com&phone=5559092332</pre>


		<!-- ==================================== -->
		<a name="getTeamCoordinators"></a>
		<h2>getTeamCoordinators</h2>
		<p>
			<b>getTeamCoordinators</b> returns the Salesforce Contact IDs and names for all users who are listed as volunteer team coordinators for a given Account.
		</p>

		<h3>Make a GET request to:</h3>
		<pre>/api/getTeamCoordinators</pre>

		<h3>GET parameters</h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>accountId</code> {string} (required)</p>
					<p>The Salesforce ID of an Account.</p>
				</div>
			</section>
		</div>

		<h3>Response payload</h3>
		<pre>[
    {
        name: {string},
        salesforceId: {string}
    }, {
        name: {string},
        salesforceId: {string}
    },
    ...
]</pre>

		<h3>Example request</h3>
		<pre>GET /api/getTeamCoordinators?accountId=j9KeT4</pre>


		<!-- ==================================== -->
		<a name="createNewUser"></a>
		<h2>createNewUser</h2>
		<p><b>createNewUser</b> creates a new app user by associating a firebase uid with a Contact entry in Salesforce, creating a new Contact if needed.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/createNewUser</pre>

		<h3>POST request body payload parameters</h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>firebaseIdToken</code> {string} (required)</p>
					<p>
						<a href="https://firebase.google.com/docs/auth/admin/verify-id-tokens" target="_blank">A token retrieved
						from Firebase after the user authenticates</a>, which can be used to identify the user, verify his
						login state, and access various Firebase resources.
					</p>
				</div>
				<div>
					<p><code>registrationCode</code> {string} (required)</p>
					<p>A code (password) required to create a new user account. This is <b>not</b> the user's password.</p>
				</div>
				<div>
					<p><code>salesforceId</code> {string}</p>
					<p>The ID of a Contact object in Salesforce. If provided, the Contact object will be updated with the app user's data.
						If not provided, a new Contact object will be created.
					</p>
				</div>
				<div>
					<p><code>email</code> {string}</p>
					<p>The user's email address. Required if <code>salesforceId</code> is not provided.</p>
				</div>
				<div>
					<p><code>phone</code> {string}</p>
					<p>The user's phone number. Required if <code>salesforceId</code> is not provided.</p>
				</div>
				<div>
					<p><code>firstName</code> {string}</p>
					<p>The user's first name. Required if <code>salesforceId</code> is not provided.</p>
				</div>
				<div>
					<p><code>lastName</code> {string}</p>
					<p>The user's last name. Required if <code>salesforceId</code> is not provided.</p>
				</div>
				<div>
					<p><code>isCoordinator</code> {boolean}</p>
					<p>Whether the user is a team coordinator. This should also be set to true if the user is an individual distributor volunteer.</p>
				</div>
				<div>
					<p><code>coordinatorId</code> {string}</p>
					<p>The Salesforce Contact ID of the user's volunteer team coordinator (if the user is not the team coordinator).</p>
				</div>
			</section>
		</div>
		
		<h3>Response payload</h3>
		<pre>{
    contactId: {string} // the ID of the updated or newly created Contact object in Salesforce
}</pre>
		
		<h3>Error codes <a class="help" href="#error-format">?</a></h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>INCORRECT_REGISTRATION_CODE</code></p>
					<p>The provided registration code was incorrect.</p>
				</div>
				<div>
					<p><code>FIREBASE_USER_ALREADY_IN_SALESFORCE</code></p>
					<p>There is already a Contact entry in Salesforce associated with the Firebase user.</code></p>
				</div>
			</section>
		</div>

		<h3>Example request</h3>
		<pre>// URL:
POST /api/createNewUser

// Headers:
Content-Type: application/json

// Request body:
{
    "firebaseIdToken": "abcd1234",
    "registrationCode": "correct-horse-battery-staple",
    "salesforceId": "JOF7EK0enoejMOE8",
    "isCoordinator": true
}</pre>


		<!-- ==================================== -->
		<a name="updateUser"></a>
		<h2>updateUser</h2>
		<p><b>updateUser</b> updates fields on an existing user account.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/updateUser</pre>

		<h3>POST request body payload parameters</h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>firebaseIdToken</code> {string} (required)</p>
					<p>
						<a href="https://firebase.google.com/docs/auth/admin/verify-id-tokens" target="_blank">A token retrieved
						from Firebase after the user authenticates</a>, which can be used to identify the user, verify his
						login state, and access various Firebase resources.
					</p>
				</div>
				<div>
					<p><code>coordinatorId</code> {string}</p>
					<p>The Contact ID of the user's team coordinator.</p>
				</div>
				<div>
					<p><code>hasWatchedTrainingVideo</code> {boolean}</p>
					<p>Whether the user has watched the training video.</p>
				</div>
			</section>
		</div>
		
		<h3>Response payload</h3>
		<pre>{
    success: true
}</pre>
		
		<h3>Example request</h3>
		<pre>// URL:
POST /api/updateUser

// Headers:
Content-Type: application/json

// Request body:
{
    "firebaseIdToken": "abcd1234",
    "coordinatorId": "KJD94JFR"
}</pre>


		<!-- ==================================== -->
		<a name="getUserData"></a>
		<h2>getUserData</h2>
		<p><b>getUserData</b> provides a way for the TAT mobile app to get a user's data.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/getUserData?parts=[LIST_OF_PARTS]</pre>

		<h3>Required headers</h3>
		<p>See the section on <a href="#required-post-headers">required POST headers</a>.</p>

		<h3>GET parameters</h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>parts</code> {string} (required)</p>
					<p>
						A comma-separated list of values. These values define what data will be returned.<br>
						Acceptable values are: <code>basic</code>, <code>unfinishedActivities</code>.
					</p>
				</div>
			</section>
		</div>

		<h3>POST request body payload parameters</h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>firebaseIdToken</code> {string} (required)</p>
					<p>
						<a href="https://firebase.google.com/docs/auth/admin/verify-id-tokens" target="_blank">A token retrieved
						from Firebase after the user authenticates</a>, which can be used to identify the user, verify his
						login state, and access various Firebase resources.
					</p>
				</div>
			</section>
		</div>
		
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
	accountId: {string},
    hasWatchedTrainingVideo: {boolean},
    street: {string},
    city: {string},
    state: {string},
    zip: {string},
	country: {string},
    isTeamCoordinator: {boolean},
    teamCoordinatorId: {string} // ContactID of the salesforce object representing the user's team coordinator
}</pre>

		<p>
			If <code>unfinishedActivities</code> is in the list of parts, the API will return a list of outreach
			activities (locations identified in pre-outreach form submissions) or events which the user has not
			completed a post-report for.<br>
			The following properties will be included in the returned object, if the user is a Volunteer Distributor:
		</p>
		<pre>{
    outreachLocations: [
        {
            id: {string}, // the identifier of the Salesforce object representing the outreach activity or event activity
            name: {string},
            type: {'cdlSchool' | 'truckingCompany' | 'truckStop' | 'event'},
            street: {string},
            city: {string},
            state: {string},
            zip: {string},
			country: {string},
            date: {string (ISO-8601 or YYYY-MM-DD)}, // planned date of outreach, or date of event
            contact: { // the person to be contacted at the defined location
                name: {string},
                title: {string},
                email: {string},
                phone: {string}
            }
        }, {
            ...
        }
    ]
}</pre>

		<h3>Error codes <a class="help" href="#error-format">?</a></h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>FIREBASE_USER_NOT_IN_SALESFORCE</code></p>
					<p>The user defined by the firebaseIdToken does not have an entry in Salesforce.</p>
				</div>
			</section>
		</div>

		<h3>Example request</h3>
		<pre>// URL:
POST /api/getUserData?parts=basic,unfinishedActivities

// Headers:
Content-Type: application/json

// Request body:
{ "firebaseIdToken": "abcd1234" }</pre>


		<!-- ==================================== -->
		<a name="getCampaigns"></a>
		<h2>getCampaigns</h2>
		<p>
			<b>getCampaigns</b> returns a list of campaigns associated with a user.
		</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/getCampaigns</pre>

		<h3>POST request body payload parameters</h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>firebaseIdToken</code> {string} (required)</p>
					<p>
						<a href="https://firebase.google.com/docs/auth/admin/verify-id-tokens" target="_blank">A token retrieved
						from Firebase after the user authenticates</a>, which can be used to identify the user, verify his
						login state, and access various Firebase resources.
					</p>
				</div>
			</section>
		</div>

		<h3>Response payload</h3>
		<pre>[
    {
        name: {string},
        salesforceId: {string},
		daysSinceCreated: {number}
    },
    ...
]</pre>

		<h3>Example request</h3>
		<pre>// URL:
POST /api/getCampaigns

// Headers:
Content-Type: application/json

// Request body:
{
    "firebaseIdToken": "abcd1234"
}</pre>


		<!-- ==================================== -->
		<a name="createTestimonial"></a>
		<h2>createTestimonial</h2>
		<p><b>createTestimonial</b> adds the data from a testimonial/feedback survey to Salesforce.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/createTestimonial</pre>

		<h3>POST request body payload parameters</h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>firebaseIdToken</code> {string} (required)</p>
					<p>
						<a href="https://firebase.google.com/docs/auth/admin/verify-id-tokens" target="_blank">A token retrieved
						from Firebase after the user authenticates</a>, which can be used to identify the user, verify his
						login state, and access various Firebase resources.
					</p>
				</div>
				<div>
					<p><code>advice</code> {string}</p>
					<p>Advice that the user would give other volunteers.</p>
				</div>
				<div>
					<p><code>bestPart</code> {string}</p>
					<p>The best part of the volunteer's experience.</p>
				</div>
				<div>
					<p><code>improvements</code> {string}</p>
					<p>Suggestions on how the volunteer experience could be improved.</p>
				</div>
				<div>
					<p><code>givesAnonPermission</code> {boolean} (required)</p>
					<p>Whether the user gives TAT permission to quote him anonymously.</p>
				</div>
				<div>
					<p><code>givesNamePermission</code> {boolean} (required)</p>
					<p>Whether the user gives TAT permission to use his name/organization in quotes and on social media.</p>
				</div>
			</section>
		</div>
			
		<h3>Response payload</h3>
		<pre>{
    success: true
}</pre>

		<h3>Example request</h3>
		<pre>// URL:
POST /api/createTestimonial

// Headers:
Content-Type: application/json

// Request body:
{
    "firebaseIdToken": "abcd1234",
    "advice": "Show your passion for volunteering",
    "givesAnonPermission": true,
    "givesNamePermission": false
}</pre>


		<!-- ==================================== -->
		<a name="createPreOutreachSurvey"></a>
		<h2>createPreOutreachSurvey</h2>
		<p><b>createPreOutreachSurvey</b> adds the data from a pre-outreach survey to Salesforce. Creates multiple Outreach Location objects associated with the submitter's campaign.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/createPreOutreachSurvey</pre>

		<h3>POST request body payload parameters</h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>firebaseIdToken</code> {string} (required)</p>
					<p>
						<a href="https://firebase.google.com/docs/auth/admin/verify-id-tokens" target="_blank">A token retrieved
						from Firebase after the user authenticates</a>, which can be used to identify the user, verify his
						login state, and access various Firebase resources.
					</p>
				</div>
				<div>
					<p><code>campaignId</code> {string} (required)</p>
					<p>The salesforce ID of the campaign related to the outreach locations submitted in this survey.</p>
				</div>
				<div>
					<p><code>isReadyToReceive</code> {boolean} (required)</p>
					<p>Whether the user is ready to receive TAT materials.</p>
				</div>
				<div>
					<p><code>mailingStreet</code> {string}</p>
					<p>The street address to send TAT materials to.</p>
				</div>
				<div>
					<p><code>mailingCity</code> {string}</p>
					<p>The city to send TAT materials to.</p>
				</div>
				<div>
					<p><code>mailingState</code> {string}</p>
					<p>The state to send TAT materials to.</p>
				</div>
				<div>
					<p><code>mailingZip</code> {string}</p>
					<p>The zip code to send TAT materials to.</p>
				</div>
				<div>
					<p><code>mailingCountry</code> {string}</p>
					<p>The country to send TAT materials to.</p>
				</div>
				<div>
					<p><code>feelsPrepared</code> {boolean}</p>
					<p>Whether the user feels prepared to perform outreach duties.</p>
				</div>
				<div>
					<p><code>questions</code> {string}</p>
					<p>Questions the user may have for TAT staff.</p>
				</div>
				<div>
					<p><code>locations</code> {object[]} (required)</p>
					<p>An array of locations that the team will perform outreach activities at. This array may not be longer than 200 items.</p>
				</div>
				<div>
					<p><code>locations[].name</code> {string} (required)</p>
					<p>A friendly name of the location to be visited.</p>
				</div>
				<div>
					<p><code>locations[].type</code> {string} (required)</p>
					<p>The type of location. Valid values are <code>cdlSchool</code>, <code>truckingCompany</code>, and <code>truckStop</code>.</p>
				</div>
				<div>
					<p><code>locations[].street</code> {string} (required)</p>
					<p>The street address of the location to be visited.</p>
				</div>
				<div>
					<p><code>locations[].city</code> {string} (required)</p>
					<p>The city of the location to be visited.</p>
				</div>
				<div>
					<p><code>locations[].state</code> {string} (required)</p>
					<p>The state of the location to be visited.</p>
				</div>
				<div>
					<p><code>locations[].zip</code> {string} (required)</p>
					<p>The zip code of the location to be visited.</p>
				</div>
				<div>
					<p><code>locations[].country</code> {string} (required)</p>
					<p>The country of the location to be visited.</p>
				</div>
				<div>
					<p><code>locations[].date</code> {string, YYYY-MM-DD} (required)</p>
					<p>The date when the volunteer is planning on visiting the location.</p>
				</div>
				<div>
					<p><code>locations[].hasContactedManager</code> {boolean} (required)</p>
					<p>Whether the user has contacted the manager (or some other employee) of the location.</p>
				</div>
				<div>
					<p><code>locations[].contactFirstName</code> {string}</p>
					<p>The first name of the contacted individual.</p>
				</div>
				<div>
					<p><code>locations[].contactLastName</code> {string}</p>
					<p>The last name of the contacted individual.</p>
				</div>
				<div>
					<p><code>locations[].contactTitle</code> {string}</p>
					<p>The professional title of the contacted individual.</p>
				</div>
				<div>
					<p><code>locations[].contactEmail</code> {string}</p>
					<p>The email address of the contacted individual.</p>
				</div>
				<div>
					<p><code>locations[].contactPhone</code> {string}</p>
					<p>The phone number of the contacted individual.</p>
				</div>
				
			</section>
		</div>
		
		<h3>Response payload</h3>
		<pre>{
    success: true
}</pre>

		<h3>Error codes <a class="help" href="#error-format">?</a></h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>TOO_MANY_LOCATIONS</code></p>
					<p>The locations array has more than 200 items.</p>
				</div>
			</section>
		</div>

		<h3>Example request</h3>
		<pre>// URL:
POST /api/createPreOutreachSurvey

// Headers:
Content-Type: application/json

// Request body:
{
    "firebaseIdToken": "abcd1234",
	"campaignId": "qrst4321",
	"isReadyToReceive": false,
	"locations": [
		{
			"name": "Love's",
			"street": "1234 Wowee St.",
			"city": "Blakefield",
			"state": "Oklahoma",
			"zip": "45454",
			"country": "United States",
			"hasContactedManager": false
		}
	]
}</pre>


		<!-- ==================================== -->
		<a name="createPostOutreachReport"></a>
		<h2>createPostOutreachReport</h2>
		<p><b>createPostOutreachReport</b> adds the data from a post-outreach report to Salesforce. Each report should be considered as a follow-up to a pre-outreach survey.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/createPostOutreachReport</pre>

		<h3>POST request body payload parameters</h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>firebaseIdToken</code> {string} (required)</p>
					<p>
						<a href="https://firebase.google.com/docs/auth/admin/verify-id-tokens" target="_blank">A token retrieved
						from Firebase after the user authenticates</a>, which can be used to identify the user, verify his
						login state, and access various Firebase resources.
					</p>
				</div>
				<div>
					<p><code>outreachLocationId</code> {string} (required)</p>
					<p>The ID of an Outreach Location object in Salesforce.</p>
				</div>
				<div>
					<p><code>totalHours</code> {number} (required)</p>
					<p>The total number of man-hours spent by the volunteer team on this outreach effort.</p>
				</div>
				<div>
					<p><code>completionDate</code> {string, YYYY-MM-DD} (required)</p>
					<p>The date on which the outreach activity was performed.</p>
				</div>
				<div>
					<p><code>accomplishments</code> {string[]} (required)</p>
					<p>
						An array containing descriptions of the accomplishments made by the volunteer team during their outreach effort.<br>
						For a location type of <code>truckStop</code>, the values should include zero or more of: <code>'willDistributeMaterials', 'willTrainEmployees'</code><br>
						For a location type of <code>cdlSchool</code>, the values should include zero or more of: <code>'willUseTatTraining', 'willPassOnInfo'</code><br>
						For a location type of <code>truckingCompany</code>, the values should include zero or more of: <code>'willTrainDrivers'</code>
					</p>
				</div>
				<div>
					<p><code>otherAccomplishments</code> {string}</p>
					<p>A description of accomplishments other than those specified by the <code>accomplishments</code> field.</p>
				</div>
				<div>
					<p><code>contactFirstName</code> {string} (required)</p>
					<p>The first name of the contacted individual.</p>
				</div>
				<div>
					<p><code>contactLastName</code> {string} (required)</p>
					<p>The last name of the contacted individual.</p>
				</div>
				<div>
					<p><code>contactTitle</code> {string} (required)</p>
					<p>The professional title of the contacted individual.</p>
				</div>
				<div>
					<p><code>contactEmail</code> {string}</p>
					<p>The email address of the contacted individual.</p>
				</div>
				<div>
					<p><code>contactPhone</code> {string}</p>
					<p>The phone number of the contacted individual.</p>
				</div>
				<div>
					<p><code>willFollowUp</code> {boolean} (required)</p>
					<p>Whether the volunteer team will follow up with additional outreach.</p>
				</div>
				<div>
					<p><code>followUpDate</code> {string, YYYY-MM-DD}</p>
					<p>When the additional follow-up will be performed.</p>
				</div>
			</section>
		</div>
		
		<h3>Response payload</h3>
		<pre>{
    success: true
}</pre>

		<h3>Example request</h3>
		<pre>// URL:
POST /api/createPostOutreachReport

// Headers:
Content-Type: application/json

// Request body:
{
    "firebaseIdToken": "abcd1234",
    "outreachLocationId": "IOJEHW8nEhehoh",
    "totalHours": 14,
    "completionDate": "2019-03-12",
    "accomplishments": ["willUseTatTraining", "willPassOnInfo"],
	"otherAccomplishments": ["Distributed some posters"],
    "contactFirstName": "Stan",
    "contactLastName": "Francisco",
    "contactTitle": "Asst manager",
    "contactEmail": "stanfran@example.com",
    "willFollowUp": false
}</pre>

		<!-- ==================================== -->
		<a name="deleteOutreachLocation"></a>
		<h2>deleteOutreachLocation</h2>
		<p><b>deleteOutreachLocation</b> removes an Outreach Location, typically because the volunteers no longer intend to visit it.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/deleteOutreachLocation</pre>

		<h3>POST request body payload parameters</h3>
		<div class="section-wrap">
			<section>
				<div>
					<p><code>firebaseIdToken</code> {string} (required)</p>
					<p>
						<a href="https://firebase.google.com/docs/auth/admin/verify-id-tokens" target="_blank">A token retrieved
						from Firebase after the user authenticates</a>, which can be used to identify the user, verify his
						login state, and access various Firebase resources.
					</p>
				</div>
				<div>
					<p><code>outreachLocationId</code> {string} (required)</p>
					<p>The ID of an Outreach Location object in Salesforce.</p>
				</div>
			</section>
		</div>
		
		<h3>Response payload</h3>
		<pre>{
    success: true
}</pre>

		<h3>Example request</h3>
		<pre>// URL:
POST /api/deleteOutreachLocation

// Headers:
Content-Type: application/json

// Request body:
{
    "firebaseIdToken": "abcd1234",
    "outreachLocationId": "IOJEHW8nEhehoh"
}</pre>


	</div>

</main>
</body>
</html>