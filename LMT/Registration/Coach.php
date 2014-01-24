<?php
/*
 * LMT/Registration/Coach.php
 * LHS Math Club Website
 */


$path_to_lmt_root = '../';
require_once $path_to_lmt_root . '../lib/lmt-functions.php';
lmt_reg_restrict_access('X');

if (isSet($_POST['lmt_do_reg_coach']))
	process_form();
else
	show_form('', 'school_name');





function show_form($err, $selected_field) {
	// A little javascript to put the cursor in the first field when the form loads;
	// page_header() looks at the $body_onload variable and inserts it into the code.
	global $body_onload;
	$body_onload = 'document.forms[\'lmtRegCoach\'].' . $selected_field . '.focus();';
	
	// If an error message is given, put it inside this div
	if ($err != '')
		$err = "\n        <div class=\"error\">$err</div><br />\n";
	
	// Get the code for reCAPTCHA
	global $RECAPTCHA_PUBLIC_KEY;
	require_once '../../lib/recaptchalib.php';
	$recaptcha_code = recaptcha_get_html($RECAPTCHA_PUBLIC_KEY);
	
	global $school_name, $email;
	
	// Assemble the page, and send.
	lmt_page_header('Coach Registration');
	echo <<<HEREDOC
      <h1>Coach Registration</h1>
      
      <div class="instruction">
      In order to register teams for the Lexington Math Tournament, coaches must first create an account.
      Only one account per school or organization is required.<br />
      <br />
      If you have already registered, use the link in the confirmation email to access your school's information. For
      assistance, please <a href="../Contact">contact us</a>.
      </div>
      <br />
      $err
      <form id="lmtRegCoach" method="post" action="{$_SERVER['REQUEST_URI']}">
        <table>
          <tr>
            <td>School/Organization:&nbsp;</td>
            <td>
              <input type="text" name="school_name" size="25" maxlength="35" value="$school_name" />
              <br /><br />
            </td>
          </tr><tr>
            <td>Coach's Email Address:</td>
            <td><input id="email" type="text" name="email" size="25" maxlength="320" value="$email" /></td>
          </tr><tr>
            <td>Security Check:</td>
            <td>$recaptcha_code</td>
          </tr><tr>
            <td></td>
            <td>
              <input type="submit" name="lmt_do_reg_coach" value="Create Account" />
              &nbsp;<a href="Home">Cancel</a>
            </td>
          </tr>
        </table>
      </form>
HEREDOC;
	lmt_page_footer('');
	die;
}





function process_form() {
	// INITIAL DATA FETCHING
	global $school_name, $email;	// so that the show_form function can use these values later
	
	$school_name = htmlentities(trim($_POST['school_name']));
	$email = htmlentities($_POST['email']);
	
	
	$name_msg = validate_school_name($school_name);
	if ($name_msg !== true)
		show_form($name_msg, 'school_name');
	
	$recaptcha_msg = validate_recaptcha();
	if ($recaptcha_msg !== true)
		show_form($recaptcha_msg, 'recaptcha_response_field');
	
	$email_msg = validate_coach_email($email);
	if ($email_msg !== true)
		show_form($email_msg, 'email');
	
	// ** All information has been validated at this point **
	
	$access_code = generate_code(5);
	
	// Create database entry
	lmt_query('INSERT INTO schools (name, coach_email, access_code) VALUES ("'
		. mysql_real_escape_string($school_name)
		. '", "' . mysql_real_escape_string($email)
		. '", "' . mysql_real_escape_string($access_code)
		. '")');
	
	// Get user id (which is automatically generated by MySQL)
	$row = lmt_query('SELECT school_id FROM schools WHERE coach_email="' . mysql_real_escape_string($email) . '"', true);
	$id = $row['school_id'];
	
	// Start outputting the top part of the page, to make it seem responsive while we send the email
	lmt_page_header('Coach Registration');
	
	// Send the email
	$url = get_site_url() . '/LMT/Registration/Signin?ID=' . $id . '&Code=' . $access_code;
	
	$subject = 'LMT Account';
	$body = <<<HEREDOC
To: $school_name

You may register teams for the LMT by clicking the link below. This link will
also enable you to modify teams as long as registration is open.

$url
HEREDOC;
	lmt_send_email(array($email=>$school_name), $subject, $body);
	
	// Show the post-registration message
	echo <<<HEREDOC
      <h1>Coach Registration</h1>
      
      <div class="text-centered">
        Your account was created. Please check your email inbox for a confirmation email.
      </div>
HEREDOC;
	
	lmt_page_footer('');
}

?>