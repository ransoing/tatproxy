<?php

/**
 * The high-level code for the createPostOutreachReport API call.
 * See index.php for usage details.
 * 
 * Modifies a Volunteer Activity object associated with the user's Contact entry --
 * marks it as complete.
 * Also creates an Activity on the user's Contact object.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// @@