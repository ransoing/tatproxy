<?php

require_once( __DIR__ . '/../functions.php' );

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
    <h1>Send test email</h1>
    <?php
    if ( !isset($_GET['to']) ) {
        // show a form to input an email address
        ?>
        <form method="get">
            <p>
                <input name="to" placeholder="email address">
            </p>
            <button type="submit" class="button">Submit</button>
        </form>
        <?php
    } else {
        ?><pre><?php
        // send the email
        sendMail( $_GET['to'], 'Test email from TAT Salesforce proxy', 'The emailer works!', true );
        ?></pre><?php
    }
    ?>

    <p><hr></p>
    <p><a href="../">Go back home</a></p>
</main>
</body>
</html>
