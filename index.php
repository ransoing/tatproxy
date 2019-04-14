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
			<li><a href="#createNewUser">createNewUser</a></li>
			<li><a href="#updateUser">updateUser</a></li>
			<li><a href="#getUserData">getUserData</a></li>
			<li><a href="#createHoursLogEntry">createHoursLogEntry</a></li>
			<li><a href="#createTestimonial">createTestimonial</a></li>
			<li><a href="#createTrainingVideoFeedback">createTrainingVideoFeedback</a></li>
			<li><a href="#createPreOutreachSurvey">createPreOutreachSurvey</a></li>
			<li><a href="#createPostOutreachReport">createPostOutreachReport</a></li>
		</ul>

		<!-- ==================================== -->
		<a name="checkRegistrationCode"></a>
		<h2>checkRegistrationCode</h2>
		<p>
			<b>checkRegistrationCode</b> verifies whether a registration code is valid.
		</p>

		<h3>Make a GET request to:</h3>
		<pre>/api/checkRegistrationCode?code=[CODE]</pre>

		<h3>GET parameters</h3>
		<section>
			<div>
				<p><code>code</code> {string} (required)</p>
				<p>The code (password) required to create a new user account.</p>
			</div>
		</section>

		<h3>Response payload</h3>
		<pre>{
    success: true
}</pre>

		<h3>Error codes <a class="help" href="#error-format">?</a></h3>
		<section>
			<div>
				<p><code>INCORRECT_REGISTRATION_CODE</code></p>
				<p>The provided registration code was incorrect.</p>
			</div>
		</section>

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

		<h3>Response payload</h3>
		<pre>{
    salesforceId: {string}
}</pre>

		<h3>Error codes <a class="help" href="#error-format">?</a></h3>
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

		<h3>Example request</h3>
		<pre>GET /api/contactSearch?email=joe.blow@example.com&phone=5559092332</pre>


		<!-- ==================================== -->
		<a name="createNewUser"></a>
		<h2>createNewUser</h2>
		<p><b>createNewUser</b> creates a new app user by associating a firebase uid with a Contact entry in Salesforce, creating a new Contact if needed.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/createNewUser</pre>

		<h3>POST request body payload parameters</h3>
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
				<p><code>volunteerType</code> {string} (required)</p>
				<p>The type of volunteer. Valid values are <code>truckStopVolunteer</code>, <code>freedomDriversVolunteer</code>, and <code>ambassadorVolunteer</code>.</p>
			</div>
			<div>
				<p><code>mailingAddress</code> {string}</p>
				<p>The street address part of the user's mailing address.</p>
			</div>
			<div>
				<p><code>mailingCity</code> {string}</p>
				<p>The city of the user's mailing address.</p>
			</div>
			<div>
				<p><code>mailingState</code> {string}</p>
				<p>The state of the user's mailing address.</p>
			</div>
			<div>
				<p><code>mailingZip</code> {string}</p>
				<p>The zip code of the user's mailing address.</p>
			</div>
			<div>
				<p><code>partOfTeam</code> {boolean}</p>
				<p>Whether the user is part of a volunteer team.</p>
			</div>
			<div>
				<p><code>isCoordinator</code> {boolean}</p>
				<p>Whether the user is a team coordinator.</p>
			</div>
			<div>
				<p><code>coordinatorName</code> {string}</p>
				<p>The name of the user's volunteer team coordinator.</p>
			</div>
		</section>
		
		<h3>Response payload</h3>
		<pre>{
    contactId: {string} // the ID of the updated or newly created Contact object in Salesforce
}</pre>
		
		<h3>Error codes <a class="help" href="#error-format">?</a></h3>
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
    "volunteerType": "truckStopVolunteer",
    "partOfTeam": true,
    "isCoordinator": true
}</pre>


		<!-- ==================================== -->
		<a name="updateUser"></a>
		<h2>updateUser</h2>
		<p><b>updateUser</b> updates fields on an existing user account.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/updateUser</pre>

		<h3>POST request body payload parameters</h3>
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
				<p><code>volunteerType</code> {string} (required)</p>
				<p>The type of volunteer. Valid values are <code>truckStopVolunteer</code>, <code>freedomDriversVolunteer</code>, and <code>ambassadorVolunteer</code>.</p>
			</div>
			<div>
				<p><code>mailingAddress</code> {string}</p>
				<p>The street address part of the user's mailing address.</p>
			</div>
			<div>
				<p><code>mailingCity</code> {string}</p>
				<p>The city of the user's mailing address.</p>
			</div>
			<div>
				<p><code>mailingState</code> {string}</p>
				<p>The state of the user's mailing address.</p>
			</div>
			<div>
				<p><code>mailingZip</code> {string}</p>
				<p>The zip code of the user's mailing address.</p>
			</div>
		</section>
		
		<h3>Response payload</h3>
		<pre>{
    success: true
}</pre>
		
		<h3>Example request</h3>
		<pre>// URL:
POST /api/createNewUser

// Headers:
Content-Type: application/json

// Request body:
{
    "firebaseIdToken": "abcd1234",
    "volunteerType": "truckStopVolunteer",
    "mailingAddress": "1234 example st."
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
		<section>
			<div>
				<p><code>parts</code> {string} (required)</p>
				<p>
					A comma-separated list of values. These values define what data will be returned.<br>
					Acceptable values are: <code>basic</code>, <code>hoursLogs</code>, <code>unfinishedOutreachTargets</code>.
				</p>
			</div>
		</section>

		<h3>POST request body payload parameters</h3>
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
    unfinishedActivities: [
        {
            id: {string}, // the identifier of the Salesforce object representing the outreach target
            name: {string},
            type: {'cdlSchool' | 'truckingCompany' | 'truckStop' | 'EVENT'},
            address: {string},
            city: {string},
            state: {string},
            zip: {string},
			date?: {string (ISO-6801 or YYYY-MM-DD)},
            postReports: [
                {
                    followUpDate: {string (ISO-8601 or YYYY-MM-DD) | null}
                }, {
                    ...
                }
            ]
        }, {
            ...
        }
    ]
}</pre>

		<h3>Error codes <a class="help" href="#error-format">?</a></h3>
		<section>
			<div>
				<p><code>FIREBASE_USER_NOT_IN_SALESFORCE</code></p>
				<p>The user defined by the firebaseIdToken does not have an entry in Salesforce.</p>
			</div>
		</section>

		<h3>Example request</h3>
		<pre>// URL:
POST /api/getUserData?parts=basic,hoursLogs,unfinishedOutreachTargets

// Headers:
Content-Type: application/json

// Request body:
{ "firebaseIdToken": "abcd1234" }</pre>



		<!-- ==================================== -->
		<a name="createHoursLogEntry"></a>
		<h2>createHoursLogEntry</h2>
		<p><b>createHoursLogEntry</b> adds a log entry associated with a user, about the volunteer tasks that the user has performed.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/createHoursLogEntry</pre>

		<h3>POST request body payload parameters</h3>
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
				<p><code>description</code> {string} (required)</p>
				<p>A description of the task performed.</p>
			</div>
			<div>
				<p><code>date</code> {string, YYYY-MM-DD} (required)</p>
				<p>The date of the task.</p>
			</div>
			<div>
				<p><code>numHours</code> {number}</p>
				<p>The number of hours spent volunteering.</p>
			</div>
		</section>
		
		<h3>Response payload</h3>
		<pre>{
    success: true
}</pre>

		<h3>Example request</h3>
		<pre>// URL:
POST /api/createHoursLogEntry

// Headers:
Content-Type: application/json

// Request body:
{
	"firebaseIdToken": "abcd1234",
	"description": "Visited a truck stop to distribute TAT materials.",
    "date": "2018-05-02",
    "numHours": 5.2
}</pre>


		<!-- ==================================== -->
		<a name="createTestimonial"></a>
		<h2>createTestimonial</h2>
		<p><b>createTestimonial</b> adds the data from a testimonial/feedback survey to Salesforce.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/createTestimonial</pre>

		<h3>POST request body payload parameters</h3>
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
		<a name="createTrainingVideoFeedback"></a>
		<h2>createTrainingVideoFeedback</h2>
		<p><b>createTrainingVideoFeedback</b> adds info to Salesforce regarding feedback on training videos, and marks that the user has watched the videos.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/createTrainingVideoFeedback</pre>

		<h3>POST request body payload parameters</h3>
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
				<p><code>feelsPrepared</code> {boolean} (required)</p>
				<p>Whether the user feels prepared to volunteer after watching the training videos.</p>
			</div>
			<div>
				<p><code>questions</code> {string}</p>
				<p>Additional questions that the user has after watching the videos.</p>
			</div>
		</section>
		
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
    "feelsPrepared": false,
    "questions": "How do I properly ask people to donate to TAT?"
}</pre>


		<!-- ==================================== -->
		<a name="createPreOutreachSurvey"></a>
		<h2>createPreOutreachSurvey</h2>
		<p><b>createPreOutreachSurvey</b> adds the data from a pre-outreach survey to Salesforce. If needed, a new Account object is created in Salesforce.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/createPreOutreachSurvey</pre>

		<h3>POST request body payload parameters</h3>
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
				<p><code>locationName</code> {string} (required)</p>
				<p>A friendly name of the location to be visited.</p>
			</div>
			<div>
				<p><code>locationType</code> {string} (required)</p>
				<p>The type of location. Valid values are <code>cdlSchool</code>, <code>truckingCompany</code>, and <code>truckStop</code>.</p>
			</div>
			<div>
				<p><code>locationAddress</code> {string} (required)</p>
				<p>The street address of the location to be visited.</p>
			</div>
			<div>
				<p><code>locationCity</code> {string} (required)</p>
				<p>The city of the location to be visited.</p>
			</div>
			<div>
				<p><code>locationState</code> {string} (required)</p>
				<p>The state of the location to be visited.</p>
			</div>
			<div>
				<p><code>locationZip</code> {string} (required)</p>
				<p>The zip code of the location to be visited.</p>
			</div>
			<div>
				<p><code>hasContactedManager</code> {boolean} (required)</p>
				<p>Whether the user has contacted the manager of the location.</p>
			</div>
			<div>
				<p><code>isReadyToReceive</code> {boolean}</p>
				<p>Whether the user is ready to receive TAT materials.</p>
			</div>
			<div>
				<p><code>mailingAddress</code> {string}</p>
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
		</section>
		
		<h3>Response payload</h3>
		<pre>{
    success: true
}</pre>

		<h3>Example request</h3>
		<pre>// URL:
POST /api/createPreOutreachSurvey

// Headers:
Content-Type: application/json

// Request body:
{
    "firebaseIdToken": "abcd1234",
    "locationName": "Love's",
    "locationAddress": "1234 Wowee St.",
    "locationCity": "Blakefield",
    "locationState": "OK",
    "locationZip": "45454",
    "hasContactedManager": false
}</pre>


		<!-- ==================================== -->
		<a name="createPostOutreachReport"></a>
		<h2>createPostOutreachReport</h2>
		<p><b>createPostOutreachReport</b> adds the data from a post-outreach report to Salesforce. Each report should be considered as a follow-up to a pre-outreach survey.</p>

		<h3>Make a POST request to:</h3>
		<pre>/api/createPostOutreachReport</pre>

		<h3>POST request body payload parameters</h3>
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
				<p><code>preOutreachSurveyId</code> {string} (required)</p>
				<p>The ID of a pre-outreach survey object in Salesforce.</p>
			</div>
			<div>
				<p><code>accomplishments</code> {string} (required)</p>
				<p>A list of the volunteer's accomplishments during his outreach work at the location and time specified by the pre-outreach survey.</p>
			</div>
			<div>
				<p><code>willFollowUp</code> {boolean} (required)</p>
				<p>Whether the user will follow up with management at the location.</p>
			</div>
			<div>
				<p><code>followUpDate</code> {string, YYYY-MM-DD}</p>
				<p>The date of the planned follow-up.</p>
			</div>
		</section>
		
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
    "preOutreachSurveyId": "IOJEHW8nEhehoh",
    "accomplishments": "Ate a sandwich, Turned over a new leaf, Fixed seven cars",
    "willFollowUp": true,
    "followUpDate": "2035-12-22"
}</pre>


	</div>

</main>
</body>
</html>