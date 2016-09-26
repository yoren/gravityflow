<?php
//$scenario->skip();
$I = new AcceptanceTester( $scenario );

$I->wantTo( 'Test the field conditional logic on the user input step' );


// Submit the form
$I->amOnPage( '/conditional-logic' );

$I->see( 'Conditional Logic' );
$I->scrollTo( [ 'css' => '.gform_title' ], 20, 50 ); // needed for chromedriver
$I->selectOption( 'input[name=input_7]', 'Second Choice' );
$I->dontSeeElement( 'textarea[name=input_15]' );
$I->checkOption( 'input[name=input_13\\.1]' );
$I->seeElement( 'textarea[name=input_15]' );
$I->fillField( 'textarea[name=input_15]', 'Some text' );
$I->scrollTo( [ 'css' => '.gform_page_footer .gform_next_button' ], 20, 50 ); // needed for chromedriver
// Next page
$I->click( '.gform_page_footer .gform_next_button' );
$I->click( 'input[type=submit]' );
$I->see( 'Thanks for contacting us!' );

// Login to wp-admin
$I->loginAsAdmin();
$I->seeInCurrentUrl( '/wp-admin/' );

// Go to Inbox
$I->click( 'Workflow' );
$I->click( 'Inbox' );
//$I->makeScreenshot('inbox');
$I->see( 'Workflow Inbox' );
$I->click( 'Conditional Logic' );

// Approve
$I->seeElement( 'button[value=approved]' );
$I->click( 'button[value=approved]' );

// Check field conditional logic on the user input step
$I->see( 'Some text' );
$I->see( 'Second Section - Second Choice' );

$I->seeElement( 'textarea[name=input_15]' );
$I->uncheckOption( 'input[name=input_13\\.1]' );
$I->dontSeeElement( 'textarea[name=input_15]' );
