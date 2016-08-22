<?php
/*
 * Test summary: Test Revert to User
 *
 * Test details:
 * - Fill up the fields
 * - Submit form
 * - First approval and send to verify the field number
 * - Modify field number value and click Update
 * - Send back for approval
 * - Revert to verify the field number
 * - Update the field number
 * - Accept field value
 */

$I = new AcceptanceTester( $scenario );

$I->wantTo( 'Test Revert User' );

// Submit the form
$I->amOnPage( '/test-revert-user' );

$I->see( 'Test Revert User' );
$I->scrollTo( [ 'css' => '.gform_title' ], 20, 50 ); // needed for chromedriver

$I->fillField( 'input[name="input_1"]', 'Some text' );
$I->selectOption( 'select[name="input_2"]', 'Second Choise' );
// Next page
$I->click( '.gform_page_footer .gform_next_button' );
$I->fillField( 'input[name="input_4"]', '42' );
$I->scrollTo( [ 'css' => 'input[type=submit]' ], 20, 50 ); // needed for chromedriver
$I->click( 'Submit' );
$I->see( 'Thanks for contacting us! We will get in touch with you shortly.' );

// Login to wp-admin
$I->loginAsAdmin();
$I->amOnPage( '/wp-admin' );

// Go to Inbox
$I->click( 'Workflow' );
$I->click( 'Inbox' );
$I->see( 'Workflow Inbox' );
$I->click( 'Test Revert User' );

// Approve
$I->seeElement( 'button[value=approved]' );
$I->click( 'button[value=approved]' );

// Verify number
$I->seeElement( 'input[name="input_4"]' );
$I->fillField( 'input[name="input_4"]', '40' );
$I->click( 'Update' );

// Revert
$I->seeElement( 'button[value=revert]' );
$I->click( 'button[value=revert]' );

// Update number
$I->seeElement( 'input[name="input_4"]' );
$I->fillField( 'input[name="input_4"]', '41' );
$I->click( 'Update' );

// Approve
$I->seeElement( 'button[value=approved]' );
$I->click( 'button[value=approved]' );