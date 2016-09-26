<?php
//$scenario->skip();

$I = new AcceptanceTester( $scenario );

$I->wantTo( 'Test the file upload field in the user input step' );

// Submit the form
$I->amOnPage( '/file-upload-user-input' );

$I->see( 'File Upload User Input' );
$I->scrollTo( [ 'css' => '.gform_title' ], 20, 50 ); // needed for chromedriver
$I->fillField( 'Single Line Text', 'testing' );
$I->scrollTo( [ 'css' => 'input[type=submit]' ], 20, 50 );
$I->click( 'Submit' );

$I->see( 'Thanks for contacting us! We will get in touch with you shortly.' );

// Login to wp-admin
$I->loginAsAdmin();
$I->seeInCurrentUrl( '/wp-admin/' );

// Go to Status
$I->click( 'Workflow' );
$I->click( 'Inbox' );
$I->click( 'File Upload User Input' );
$I->attachFile( 'input[name=input_2]', 'gravityflow-logo.png' );
$I->click( 'Update' );
$I->seeElement( 'div.gravityflow_validation_error' );

$I->selectOption( 'input[name=input_3]', 'Second Choice' );
$I->click( 'Update' );
$I->seeLink( 'gravityflow-logo.png' );



