<?php

/**
 * The high-level code for the createPostEventReport API call.
 * See index.php for usage details.
 * 
 * Modifies a Campaign/Event object, marking it as complete and adding some details.
 */

require_once( '../functions.php' );
require_once( '../api-support-functions.php' );

// verify the firebase login and get the user's firebase uid.
$firebaseUid = verifyFirebaseLogin();
$postData = getPOSTData();

addToLog( 'command: createPostEventReport. POST data received:', $postData );

// sanitize eventId by removing quotes
$postData->eventId = str_replace( array("'", '"'), "", $postData->eventId );

\React\Promise\all( array(
    // get the existing Campaign/Event object so we can append to the Notes_and_Quotes__c field
    salesforceAPIGetAsync( 'sobjects/Campaign/' . $postData->eventId, array('fields' => 'Name,Notes_and_Quotes__c,TAT_App_Report_Completed__c') ),
    // get the owner of the event
    promiseToGetCampaignOwner( $postData->eventId )
))->then( function($promiseResults) use ($postData) {
    $event = $promiseResults[0];
    $campaignOwner = $promiseResults[1];

    if ( $event->TAT_App_Report_Completed__c ) {
        // the report was already completed. Don't allow further execution
        throw new Exception( 'Post-event report already submitted for this event' );
    }

    $fieldsToUpdate = array( 'TAT_App_Report_Completed__c' => true );

    if ( !empty($postData->numAttendedPresentation) ) {
        $fieldsToUpdate['presented_to__c'] = intval( $postData->numAttendedPresentation );
    }
    if ( !empty($postData->numAttendedEvent) ) {
        // this field in salesforce is a string textarea, not a number -- keep it as a string
        $fieldsToUpdate['Total_of_people_at_event__c'] = $postData->numAttendedEvent;
    }

    // event_participation__c in salesforce is only a single picklist, but the user has the option in the app to select multiple items
    // If 'gaveSpeech' is selected, use that value, otherwise use 'hostedTable' if it is selected, otherwise no value in event_participation__c.
    // Everything that the user selected other than what is recorded by event_participation__c is recorded in Notes_and_Quotes__c
    $otherWays = array();
    if ( in_array('gaveSpeech', $postData->howEngaged) ) {
        $fieldsToUpdate['event_participation__c'] = 'eventParticipationKeynote';
        if ( in_array('hostedTable', $postData->howEngaged) ) array_push( $otherWays, 'Hosted a table' );
    } else if ( in_array('hostedTable', $postData->howEngaged) ) {
        $fieldsToUpdate['event_participation__c'] = 'eventParticipationTable';
    }
    if ( in_array('interviewedByMedia', $postData->howEngaged) ) array_push( $otherWays, 'Interviewed by the media' );
    if ( !empty($postData->howEngagedOther) ) array_push( $otherWays, $postData->howEngagedOther );

    $miscAnswers = formatQAs(
        array( 'Other ways you engaged at this event:', join("\n",$otherWays) ),
        array( 'Were there any audience members that were moved by your presentation?', $postData->movedAudience ),
        array( 'Were you able to collect any quotes from the attendees about the presentation or topic that demonstrate impact?', $postData->attendeeQuotes ),
        array( 'How confident were you in delivering the presentation?', (empty($postData->confidence) ? '' : $postData->confidence . ' confidence') ),
        array( 'Is there anything we could do to increase your confidence?', $postData->confidenceHelp ),
        array( 'What made you prepared for this event?', $postData->confidencePrepared ),
        array( 'What went well for you at this event?', $postData->whatWentWell ),
        array( 'What could have gone more smoothly for you at this event?', $postData->whatDidntGoWell ),
        array( 'Were there any members of the press at the event? If so, do you know what station they were from?', $postData->pressMembers ),
        array( 'Is there anything additional you would like us to know?', $postData->other ),
        array( 'Quote from the Ambassador about their experience:', $postData->volunteerQuote )
    );

    // append to Notes_and_Quotes__c if it has data in it already
    if ( empty($event->Notes_and_Quotes__c) ) {
        $fieldsToUpdate['Notes_and_Quotes__c'] = $miscAnswers;
    } else {
        $fieldsToUpdate['Notes_and_Quotes__c'] .= "\n\n" . $miscAnswers;
    }

    // @@ test appending to the Notes_and_Quotes__c field
    // @@ implement notifications for events
    // @@ add extra resources for ambassadors in Firebase

    logSection( 'Sending email to campaign owner' );
    $instanceUrl = getSFAuth()->instance_url;
    $emailContent = "<p>Post-event report completed for {$event->Name}. "
        . "<a href='{$instanceUrl}/lightning/r/Campaign/{$postData->eventId}/view'>View the event in Salesforce</a> to see the responses.";
    sendMail( $campaignOwner->Username, 'Post-event report completed for ' . $event->Name, $emailContent );

    logSection( 'Updating the Account to add info regarding the primary contact' );
    return salesforceAPIPatchAsync( 'sobjects/Campaign/' . $postData->eventId, $fieldsToUpdate );
})->then( function() {
    echo '{"success": true}';
})->otherwise(
    $handleRequestFailure
);

$loop->run();
