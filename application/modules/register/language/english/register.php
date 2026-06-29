<?php

/**
 * Note to module developers:
 *  Keeping a module specific language file like this
 *  in this external folder is not a good practise for
 *  portability - I do not advice you to do this for
 *  your own modules since they are non-default.
 *  Instead, simply put your language files in
 *  application/modules/yourModule/language/
 *  You do not need to change any code, the system
 *  will automatically look in that folder too.
 */

$lang['account_creation'] = "Account creation";
$lang['register'] = "Register";
$lang['gameaccount_title'] = "Add game account";
$lang['gameaccount_intro'] = "You are logged in with your Battle.net account. A new game account will be created and linked to it automatically — no extra details needed.";
$lang['gameaccount_bnet'] = "Battle.net Account";
$lang['gameaccount_new'] = "New game account";
$lang['gameaccount_button'] = "Create game account";
$lang['gameaccount_created'] = "Your new game account has been created:";
$lang['gameaccount_back'] = "Back to control panel";
$lang['gameaccount_no_bnet'] = "Your account is not linked to a Battle.net account.";

// SMS verification (Twilio)
$lang['phone'] = "Phone number";
$lang['sms_send'] = "Send code";
$lang['sms_verify'] = "Verify";
$lang['sms_code'] = "SMS code";
$lang['sms_sent'] = "Code sent. Check your phone.";
$lang['sms_ok'] = "Phone verified ✓";
$lang['sms_required'] = "Enter the SMS code.";
$lang['sms_invalid'] = "Invalid or expired code.";
$lang['sms_not_verified'] = "Please verify your phone by SMS first.";
$lang['sms_disabled'] = "SMS verification is not available.";
$lang['sms_bad_phone'] = "Enter a valid phone number (e.g. +34600000000).";
$lang['sms_send_failed'] = "Could not send the SMS. Check the number and try again.";
$lang['sms_phone_in_use'] = "That phone number is already registered to another account.";
$lang['username_limit_length'] = "Username must be between 4 and 24 characters long";
$lang['username_limit'] = "Username may only contain alphabetical and numerical characters";
$lang['username_not_available'] = "Username is not available";
$lang['email_invalid'] = "Email must be a valid email";
$lang['password_short'] = "Password must be longer than 6 characters";
$lang['password_match'] = "Passwords don't match";
$lang['email_not_available'] = "Email is not available";
$lang['confirm_account'] = "Please confirm your account creation";
$lang['created'] = "Your account has been created!";
$lang['activate_account'] = "Activate account";
$lang['created_account_activate'] = "You have created the account, please go here to activate it:";
$lang['invalid_key'] = "Invalid activation key";
$lang['invalid_key_long'] = "The provided activation key appears to be invalid!";
$lang['the_account'] = "The account";
$lang['has_been_created'] = "has been created. Please check your email to activate your account.";
$lang['creating_account_forum'] = "Creating account on the forum, please wait...";
$lang['has_been_created_redirecting'] = "has been created. You are being redirected to the";
$lang['user_panel'] = "User panel";
$lang['username'] = "Username";
$lang['email'] = "Email";
$lang['password'] = "Password";
$lang['confirm'] = "Confirm password";
$lang['expansion'] = "Expansion";
$lang['submit'] = "Create account!";
