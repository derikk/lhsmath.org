<?php
/*
 * lib/functions.php
 * LHS Math Club Website
 *
 * A library of functions. All pages should require_once this file.
 * Loading this file will also perform the following default actions:
 *  - hide the '.php' extension in the URL
 *  - start a session
 *  - connect to the database
 *  - attach custom_errors as custom error handler if $CATCH_ERRORS is true.
 *  - Initialize a bunch of config variables
 *
 * Dependencies: $path_to_root defined as the relative path to the root directory of the site.
 * For example, the admin page '/Admin/blah.php' needs to have at the top:
 * 		$path_to_root = '../';								//Define the path_to_root to get to the root directory INCLUDING trailing slash
 * 		require_once $path_to_root . 'lib/functions.php';	//require_ONCE the functions.php library
 * 		restrict_access('A');								//Restrict access to admins only.
 
 
 Hints for Debugging:
 debug_print_backtrace()
 register_shutdown_function()
 set_error_handler()
 Google, PHP.net, StackOverflow, past webmasters
 Looking for cautionary comments [e.g. mail config $#%^$%#]
 
 */
 
 /*
 Table of Contents and Descriptions (in progress!)
 Use your IDE's 'find' function to get to these.
 
 custom_errors($errno,$errstr,$errfile,$errline)	Custom error handler that logs error and displays opaque error page
 restrict_access($levels)							Restricts permission levels to those specified, redirecting based on that. Levels: E,R,P,B,R,+,L,X
 set_login_data($id)								Sets the SESSION variables that contain a logged-in user's information
 log_attempt($email,$success)						?
 hash_pass($email,$pass)							?
 generate_code($length)								?
 format_phone_number($num)							?
 trim_email($email)									?
 get_site_url()										?
 form_autocomplete_query($input)					?
 send_email($to,$subject,$body,$reply_to)			?
 BBCode($string)									?
 dirsize($path)										?
 size_readable($size[,$max[,$system[,$retstring]]])	?
 email_obfuscate($address[,$link_text][,$pre_text][,$post_text])	?
 page_header($title)								?
 page_footer($names,$pages)							?
 default_page_footer($page_name)					?
 admin_page_footer($page_name)						?
 go_home_footer()									?
 go_home_admin_footer()								?
 */


require_once $path_to_root . 'lib/CONFIG.php';	// configuration information


/*
 * val()
 *
 * Data validation, with ridiculously overly immense power. Examples:
 *
 * val('i0+',$n,$m) returns true if both $n and $m are nonnegative integers.
 * val('*s',$_POST) returns true if $_POST is an array of strings (and nothing else). (which $_POST should always be)
 * val(array('abc','i'),array($a,$b))
    or val(array('abc','i'),$a,$b)
	or val('abc,i',array($a,$b))
	or val('abc,i',$a,$b)
    will verify that $a is an alphabetic string and $b an int.
 * val('**abc',$board)
    or val('*2abc',$board)
    returns true if $board is a 2D array of alphabetic strings (note: does not check row length alignment)
	Supports any nonnegative integer dimensions (zero is just a plain value, and 1 is just an array), even '*200abc'.
 * val('@@@@abc',$matrix)
    or val('@4abc',$matrix)
    returns true if $matrix is a 4D array of alphabetic strings, with row lengths properly aligned to form a rectangular hyperprism.
    Note that needing this is a sign of bad coding, and probably also indicates a chronic need to get a life, like me.
    Like *N, supports any nonnegative integer dimensions.
 * val('@10*4@3i',$matrix)
    Theoretically, you could do this if you wanted to verify a ten-dimensional aligned array of 
	four-dimensional arrays of three-dimensional aligned arrays of integers. What *are* you doing?
 */
function val($type /*,$x1,$x2,...*/){
	$args=func_get_args();
	array_shift($args);
	if(!count($args)){
		trigger_error('val(): Nothing to validate.',E_USER_ERROR);
		return false;
	}
	
	if(is_string($type)&&strpos($type,',')!==false)$type=explode(',',$type);
	
	if(is_array($type)){//MULTIPLE TYPES, MULTIPLE VALIDATEES
		while(count($type)>1 && count($args)==1 && is_array($args[0]))$args = $args[0];//While, because what if you nest empty arrays for no reason whatsoever?
		if(count($type)!=count($args)){trigger_error('val(): #types != #validatees',E_USER_ERROR);return false;}
		foreach($args as $arg)
			if(!val(array_shift($type),$arg))
				return false;
		return true;
	}
	elseif(is_string($type)&&count($args)>1){//SINGLE TYPE, MULTIPLE VALIDATEES
		foreach($args as $arg)
			if(!val($type,$arg))
				return false;
		return true;
	}
	elseif(!is_string($type)){trigger_error('val(): $type neither string nor array',E_USER_ERROR);return false;}//Invalid $type.
	
	/*********SINGLE VALIDATEE*********/
	
	$x=$args[0];//Already confirmed that it's only one validatee, so let's go do just this one arg.
	
	$firstchar = substr($type,0,1);
	$type_tmp = $type;
	
	//*: N-D ARRAY TYPE
	//@: N-D ALIGNED ARRAY TYPE
	if($firstchar=='*' || $firstchar == '@'){//does it work for '*0abc' and '@0abc'? Does it work for '***'? '@@@'? '*@*'?
		$str_shift = function(&$str){
			if(strlen($str)==0)trigger_error('str_shift(): empty string');
			$chr = substr($str,0,1);
			$str = substr($str,1);
			return $chr;
		};
		$dim=0;
		while(1){//Get how many dimensions are requested in the validatino string
			$chr=$str_shift($morechars);
			while($chr==$firstchar){$dim++;$chr=$str_shift($morechars);}//Increments the dimension as long as there's more of that char.
			
			$tmpnum='0';
			while(val('d',$chr)){$tmpnum.=$chr;$chr=$str_shift($morechars);}//Appends digits to the end of the number as long as there's more digits,
			$dim+=intval($tmpnum);//and then adds them to the dimension number.
			
			if($chr!=$firstchar)break;//If there's no more useful chars (no digits, no */@) then exit.
		}
		$morechars = $chr.$morechars;//Add the detected one back
		
		$dimensions=array();
		if($firstchar == '@'){//Dimensions checking, for @: grab it first into $dimensions
			$tmpx=$x;
			for($i=0;$i<$dim;$i++){//Grab the actual dimensions
				if(!is_array($tmpx))return false;
				array_unshift($dimensions,count($tmpx));//So the deepest will be at the beginning.
				$tmpx=$tmpx[0];
			}
		}
		
		//ok got everything, now recursively check all counts (if @), and at the bottom check the values.
		$rec_check = function($matrix,$n) use ($dimensions,$morechars,&$rec_check){//then use $n to determine where in $dimensions you are.
			if($n==0)return val($morechars,$matrix);
			if($firstchar=='@' && count($matrix)!=$dimensions[$n-1])return false;
			foreach($matrix as $submatrix)
				if(!rec_check($submatrix,$n-1))return false;
			return true;
		};
		return $rec_check($x,$dim);
	}
	
	//SINGLE SIMPLE TYPE
	switch(strtolower($type)){//All of these must begin with an alphabetic character.
		case 's':case 'string':		return is_string($x);	//String
		
		case 'd':case 'digit':		return is_int($x)		//Digit
										&& $x>=0
										&& $x<10;
		
		case 'i-':	return is_int($x) && $x < 0;			//Negative int
		case 'i0-':	return is_int($x) && $x <= 0;			//Nonpositive int
		case 'i':	return is_int($x);						//Int
		case 'i0+':	return is_int($x) && $x >= 0;			//Nonnegative int
		case 'i+':	return is_int($x) && $x > 0;			//Positive int
		
		case 'num':	return is_numeric($x);					//Number (including floats)
		
		case 'aln':	return is_string($x) && ctype_alnum($x);//Alphanumeric
		case 'abc':	return is_string($x) && ctype_alpha($x);//Alphabetic
		
		case 'e':case 'email':		return is_string($x)	//Email
										&& filter_var($x,FILTER_VALIDATE_EMAIL)!==false;
								// !!preg_match('/^([\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+\.)*[\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]'
								// .'+@((((([a-z0-9]{1}[a-z0-9\-]{0,62}[a-z0-9]{1})|[a-z])\.)+[a-z]{2,6})|(\d{1,3}\.){3}\d{1,3}(\:\d{1,5})?)$/i'
								// , $email);
		
		case 'f':case 'file':		return is_string($x)	//Filename
										&& preg_match('/^[A-Za-z0-9]([A-Za-z0-9\_\-\.]+[A-Za-z0-9])?$/i',$x)
										&& !strpos($x,'..');
		
		default:					trigger_error('val(): Invalid validation type.',E_USER_ERROR);return false;
	}
}



/*
 * custom_errors($errno, $errstr, $errfile, $errline)
 *
 * Logs errors and shows an error page
 */
function custom_errors($errno, $errstr, $errfile, $errline) {
	global $path_to_root;
	file_put_contents($path_to_root . '.content/Errors.txt', date(DATE_RFC822) . ' Error [' . $errno . '] on line ' . $errline . ' in ' . $errfile . ': ' . $errstr . "\n", FILE_APPEND);
	
	if (headers_sent())
		echo '<meta http-equiv="refresh" content="0;url=' . $path_to_root . 'Error">';
	
	else if (isSet($_GET['xsrf_token']))
		header('Location: ' . $path_to_root . 'Error');
	
	else {
		header("HTTP/1.1 500 Internal Server Error");
		page_header('Error');
		echo <<<HEREDOC
      <h1>Error</h1>
      
      Whoops! Something went wrong. Try again?
HEREDOC;
		go_home_footer();
	}
	
	die;
}
if ($CATCH_ERRORS) {
	set_error_handler('custom_errors', E_ERROR | E_PARSE | E_USER_ERROR);
	error_reporting(E_ERROR | E_PARSE | E_USER_ERROR);
}
/*else{function a(){debug_print_backtrace();}function b(){global $a;if($a)echo var_dump($a);}
function c(){global $a;if(!$a)$a=array();$a[]=debug_backtrace();}set_error_handler('a',E_ALL&!E_NOTICE);
register_shutdown_function('b');}*/ //Debug backtracing; put c() wherever to output; will also output on program end

require_once $path_to_root . 'lib/meekrodb.2.3.class.php'; //Even better version of class.DB.php, with OO'd database management.
DB::$host = $DB_SERVER; //defaults to localhost if omitted
DB::$user = $DB_USERNAME;
DB::$password = $DB_PASSWORD;
DB::$dbName = $DB_DATABASE;


// add extra include path
set_include_path(get_include_path() . PATH_SEPARATOR . $ADD_INCLUDE_PATH);


// set timezone
date_default_timezone_set($TIMEZONE);

// check IP ban list
if (in_array(strtolower($_SERVER['REMOTE_ADDR']), $BANNED_IPS)) {
	session_name('Session');
	session_start();
	session_destroy();
	
	$_SESSION['permissions'] = 'B';
	require_once $path_to_root . 'Account/Banned.php';
}

// hide .PHP extension (/Home.php -> /Home - this works because of a URL Rewrite in the .htaccess file)
@$url_pieces = parse_url($_SERVER['REQUEST_URI']);
if ($url_pieces != false && basename($url_pieces['path']) != basename($url_pieces['path'], '.php')){
	$url = basename($url_pieces['path'], '.php');
	if (isSet($url_pieces['query']))
		$url .= '?' . $url_pieces['query'];
	header('Location: ' . $url);
}

// start a session
session_name('Session');
session_start();


// all sessions have an XSRF-protection token that should be
// submitted with all forms via invisible field.
//
// Hypothetical scenario: User logs into our site, and sometime later
// accesses EvilSite.com, which loads a form much like
// the one used to send emails, then uses javascript to submit
// it. The action of the form is set to lhsmath/Account/Confirm_Email.
// The user's browser sends the request to us, along with the right
// cookes - so the request looks just like one that came from our site.
// Email is sent. Repeat 500x; spam (and a waste of server time) ensues.
//
// To make sure that the only forms we accept were actually requested by
// the user, we include a secret code in the form, which must match the
// one stored in SESSION (therefore, we had to have generated the form ourselves)
if (!isSet($_SESSION['xsrf_token']))
	$_SESSION['xsrf_token'] = generate_code(20);


// lock session to IP address
if (!isSet($_SESSION['ip_address']))
	$_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];

if ($_SESSION['ip_address'] != $_SERVER['REMOTE_ADDR']) {
	session_destroy();
	session_name('Session');
	session_start();
}


// refresh cached data (name, permissions) 15 sec. for people who are pending email verification or account approval
// and 1 min. for everyone else
if (isSet($_SESSION['user_id'])) {
	if ($_SESSION['permissions'] == 'E' || $_SESSION['permissions'] == 'P') {
		if (time() >= $_SESSION['last_refresh'] + 15)
			set_login_data($_SESSION['user_id']);
	} else if (time() >= $_SESSION['last_refresh'] + 60)
		set_login_data($_SESSION['user_id']);
}

// everyone gets logged out after 8 hours, no matter what
// (this is in case an account is compromised without the password, i.e. left logged
// in somewhere, or via intercepted verification email). Not that that's our most
// significant worry.
if (isSet($_SESSION['user_id']) && time() >= $_SESSION['login_time'] + 28800) {
	session_destroy();
	session_name('Session');
	session_start();
}





/*
 * restrict_access($levels, $forbidden_page)
 *  - $levels: what types of users can access this page:
 *    * 'A': Administrative (Captain and Assistant, and Webmaster)
 *    * 'R': Regular (approved users)
 *    * 'P': Pending approval
 *    * 'B': Banned
 *    * 'E': Email verification pending
 *    * '+': Super-Admin (LHSMATH account)
 *    * 'L': Alumnus
 *    * 'X': Logged-out user
 */
function restrict_access($levels) {
	global $path_to_root;
	
	
	if (!array_key_exists('permissions',$_SESSION))
		$user_level = 'X';
	else
		$user_level = $_SESSION['permissions'];
	
	if (strpos($levels, $user_level) === false) {
		// Access forbidden
		if ($user_level == 'X') {
			// Maybe they have permissions, they're just not logged in
			// Show login page
			require_once $path_to_root . 'Account/Signin.php';
			die();
		}
		else if ($user_level == 'E') {
			// Redirect to the 'Confirm your Email' page
			header('Location: ' . $path_to_root . 'Account/Verify_Email');
			die();
		}
		else if ($user_level == 'P') {
			// Redirect to the 'Get your Account Approved' page
			header('Location: ' . $path_to_root . 'Account/Approve');
			die();
		}
		else if ($user_level == '+') {
			// Redirect to the Super-Admin page
			header('Location: ' . $path_to_root . 'Admin/Super_Admin');
			die();
		}
		else if ($user_level == 'B') {
			// Redirect to the 'You Are Banned' page
			header('Location: ' . $path_to_root . 'Account/Banned');
			die();
		}
		else {
			// Go home
			header('Location: ' . $path_to_root . 'Home');
			die();
		}
	}
}





/*
 * set_login_data($id)
 *  - $row: the result of mysql_fetch_assoc() on the query 'SELECT * FROM users WHERE id="..."'
 *
 * Sets the SESSION variables that contain a logged-in user's information
 * 
 * Note that this is an exceptionally vulnerable function, since calling set_login_data (some random id) will result in login.
 * I actually accidentally hijacked it, since DB query wasn't working, but it still logged in. O_o
 */
function set_login_data($id) {
	if ($_SESSION['permissions'] == '+')	// if you're already logged in as the Super-Admin, this would mess things up cuz it's not in the database
		return;
	
	if (!isSet($_SESSION['user_id'])) {
		// ** THIS IS A LOG-IN, NOT A REFRESH OF EXISTING DATA, SO... ***
		session_destroy();  // clear any stored data
		session_name('Session');
		session_start();
		session_regenerate_id(true);  // change session id to prevent hijacking
	}
	
	$row = DB::queryFirstRow('SELECT id, name, permissions, email, approved, password_reset_code, email_verification FROM users WHERE id=%i LIMIT 1',$id);
	
	if(!$row){
		session_destroy();
		trigger_error("Authentication error",E_USER_ERROR);
	}
	
	$_SESSION['user_name'] = $row['name'];
	$_SESSION['permissions'] = $row['permissions'];
	$_SESSION['email'] = $row['email'];
	
	// SPECIAL PERMISSIONS
	if ($_SESSION['permissions'] == 'C') {	// Captain is a type of Administrator
		$_SESSION['permissions'] = 'A';
		$_SESSION['is_captain'] = true;
	}
	
	if ($row['approved'] == '-1')				// Banned status
		$_SESSION['permissions'] = 'B';
	else if ($row['email_verification'] != '1')	// "Email-Not-Yet-Verified" status
		$_SESSION['permissions'] = 'E';
	else if ($row['approved'] == '0')			// "Not-Yet-Approved" status
		$_SESSION['permissions'] = 'P';
	
	// Admins have an asterisk appended to their name
	if ($_SESSION['permissions'] == 'A')
		$_SESSION['user_name'] .= '*';
	
	// If a password reset has been requested, cancel it -
	// apparently, they remembered their password
	if ($row['password_reset_code'] != '0')
		DB::update('users',array('password_reset_code'=>0),'id=%i',$row['id']);
	
	// REFRESH TIME
	$_SESSION['last_refresh'] = time();
	
	if (!isSet($_SESSION['user_id'])) {
		// ** THIS IS A LOG-IN, NOT A REFRESH OF EXISTING DATA, SO... ***
		$_SESSION['login_time'] = time();
		$_SESSION['user_id'] = $row['id'];	// the actual log-in
	}
}





/*
 * log_attempt($email, $success)
 *  - $email: the email address entered
 *  - $success: if the login attempt succeeded
 *
 * If a user attempts to log in, this is recorded in the database
 */
function log_attempt($email, $success) {
	if ($email == '')return;
	
	if ($success)$success = '1';
	else $success = '0';
	
	DB::insert('login_attempts',array(
		'email'=>strtolower($email),
		'remote_ip'=>strtolower($_SERVER['REMOTE_ADDR']),
		'successful'=>$success
	));
}





/*
 * hash_pass($email, $pass)
 *  - $email: the email address, used to salt the hash
 *  - $pass: the password that was entered
 *  - returns: a 128-character hash that is UNIQUE to this particular
 *      email/password combination
 *
 *  To protect the security of passwords in case the database is
 *  compromised, password are hashed before storage. To validate a
 *  email/password pair, hash them and then compare it to the stored
 *  passhash for that user.
 */
function hash_pass($email, $pass) {
	global $SECRET_SALT;
	$hash = hash('sha512', 'lhsmath $4S5KoOyu\'B5FRrg(*#%@22aM,jBxQjZIRwnY./\\[X2d$MDLGeUD)}:"mlAt9kekTiaET!mcmVQYTJlk;TdYZJS1aqo' . $email);
	$hash = hash('sha512', $hash . ' lhsmath 2BATHJ0G61o23#%zEHEw];.246893QW0SmXA@$#)bcjtPQI%&#RjjANLpyz' . $pass);
	return hash('sha512', $hash . ' lhsmath elEf\\0il(*@#%.*()eVvBO6' . $pass . ';Rjz@um3FbPj#$89WnYViPz\'XwiP7#C7x42M4hUFd' . $SECRET_SALT);
}





/*
 * generate_code($length)
 *  - $length: the length of the code to generate
 *  - returns: a random hexadecimal code of the given length
 */
function generate_code($length) {
	global $SECRET_SALT;
	$hash = hash('sha256', 'lhsmath L)(#%JHI}90LDNlkasjkaglkd08H#()qpowinfs;lidgn'
		. time() . $_SERVER['REMOTE_ADDR'] . $SECRET_SALT . rand());
	return substr($hash, 0, $length);
}





/*
 * format_phone_number($num)
 *  - $num: a ten-digit phone number
 *  - returns: $num in the form "(xxx) xxx-xxxx"
 *
 * Formats a phone number. If $num is not 10 digits long, it is returned
 * unchanged.
 */
function format_phone_number($num) {
	if (strlen($num) != 10)
		return $num;
	return '(' . substr($num, 0, 3) . ') ' . substr($num, 3, 3) . '-' . substr($num, 6, 4);
}





/*
 * trim_email($email)
 *  - $email: an email address
 *  - returns: a shortened email of 30 characters, in the form of bobsm...@example.com
 *      if the email address is longer than 30 characters long
 */
function trim_email($email) {
	$len = strlen($email);
	if ($len <= 30)
		return $email;
	
	$end_of_name = strpos($email, '@');
	
	if ($len - $end_of_name >= 27)	// if omitting the whole name won't be enough, just put the ... after 30 characters
		return substr($email, 0, 27) . '...';
	
	// otherwise, chop the end of the name and put ... at the end
	return substr($email, 0, $end_of_name - ($len - 27)) . '...' . substr($email, $end_of_name);
}





/*
 * get_site_url()
 *  - returns: the url of the site (e.g. 'http://lhsmath.co.cc')
 */
function get_site_url() {
	$protocol = (isSet($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
	return $protocol . '://' . $_SERVER['HTTP_HOST'];
}





/*
 * form_autocomplete_query($input)
 * Parses the input to a User-Autocomplete form and returns an array
 * - ["type"] => "no-entry"
 * 		$input is empty
 * - ["type"] => "none"
 * 		no matches were found
 * - ["type"] => "single"
 * 		a single user matches
 * 		- ["row"] => the database row
 * - ["type"] => "multiple"
 * 		multiple users match
 * 		- ["result"] => the entire query result, with the pointer at the beginning
 * 		- ["exact"] => if this includes an exact match
 */
function form_autocomplete_query($input) {//Restrict to admins, and then move it to JS.
	$query = '';
	$name = '';
	$parts = preg_split('/[\(\)]+/', $input);
	if (count($parts) == 3) {
		$name = $parts[0];
		$grade = $parts[1];
		if (preg_match('/^(\d)*$/', $grade)) {
			$grade = (int)$grade;
			
			if ($grade >= 9 && $grade <= 12) {
				$senior_year = (int)date('Y');
				if ((int)date('n') > 6)
					$senior_year += 1;
				
				$name = str_replace(" ", "%", $name);
				$yog = $senior_year + 12 - $grade;
				
				$query = 'SELECT * FROM users WHERE name LIKE "%' . mysql_real_escape_string($name)//NO WHY ARE YOU DOING THIS AGHHHH it hurts me
					. '%" AND yog="' . mysql_real_escape_string($yog) . '" AND permissions!="T"';
			}
		}
		else if ($grade == 'T') {
			$name = str_replace(" ", "%", $name);
			$query = 'SELECT * FROM users WHERE name LIKE "%' . mysql_real_escape_string($name)
					. '%" AND permissions="T"';
		}
	}
	
	if ($input == '')
		return array("type" => "no-entry");
	
	$input = str_replace(" ", "%", $input);
	if ($query == '')
		$query = 'SELECT id, name, yog FROM users WHERE (name LIKE "%'
			. mysql_real_escape_string($input) . '%" OR id="'
			. mysql_real_escape_string($input) . '") AND permissions!="T"';
	
	$result = mysql_query($query) or trigger_error(mysql_error(), E_USER_ERROR);
	if (mysql_num_rows($result) == 0)
		return array("type" => "none");
		
	$row = mysql_fetch_assoc($result);
	if (mysql_num_rows($result) == 1)
		return array("type" => "single", "row" => $row);
	
	$found_row = null;
	$multiple = false;
	while ($row) {
		$name = str_replace("%", " ", $name);
		$name = trim($name);
		$input = str_replace("%", " ", $input);
		$name = trim($name);
		
		if ($row['name'] == $name || $row['name'] == $input) {
			if (is_null($found_row))
				$found_row = $row;
			else
				$multiple = true;
		}
		$row = mysql_fetch_assoc($result);
	}
	
	mysql_data_seek($result, 0);
	
	if ($multiple)
		return array("type" => "multiple", "result" => $result, "exact" => true);
	if (is_null($found_row))
		return array("type" => "multiple", "result" => $result, "exact" => false);
	return array("type" => "single", "row" => $found_row);
}







function send_list_email($subject, $body, $reply_to){
	$site_url = get_site_url();
	$stripped_url = str_replace(array('http://www.','http://'), '', $site_url);
	send_email(get_bcc_list(), $subject, $body, $reply_to,
		NULL,
		"LHS Math Club\n$site_url\nTo unsubscribe from this list, visit [url]$site_url/Account/My_Profile[/url]",
		array(
			'Precedence' => 'bulk',
			'List-Id' => "<members.$stripped_url>",
			'List-Unsubscribe' => "<$site_url/Account/My_Profile>"
		)
	);
}


/*
 * send_email($to, $subject, $body, $reply_to)
 *  - $to: who to send the email to, as an string array of $email OR $email=>$name pairs
 *  - $subject: the subject line; $prefix is automatically prefixed
 *  - $body: the body of the message
 *  - $reply_to: the email address to send replies to, if different from the TO address
 *
 *  NOTE: THIS FUNCTION REQUIRES THE SWIFT MAIL PACKAGE
 */
function send_email($to, $subject, $body, $reply_to=NULL, $prefix=NULL, $footer=NULL, $headers=NULL) {
	global $EMAIL_ADDRESS, $EMAIL_USERNAME, $EMAIL_PASSWORD,
		$SMTP_SERVER, $SMTP_SERVER_PORT, $SMTP_SERVER_PROTOCOL, $LMT_EMAIL, $path_to_lmt_root;
		
	//Instead of using parameter default values, so we can pass NULL. And it's more readable.
	if(count($to)==0)return;
	//--todo-- reply-to filtering doesn't work, and instead breaks when the empty string reaches SwiftMail.
	if(is_null($reply_to) || !filter_var($reply_to, FILTER_VALIDATE_EMAIL))$reply_to=array($EMAIL_ADDRESS=>'LHS Math Club Mailbot');
	if(is_null($prefix))$prefix='[LHS Math Club]';
	if(is_null($footer))$footer="LHS Math Club\n".get_site_url()."\nTo stop receiving LHSMATH emails, contact [email]webmaster@lhsmath.org[/email].";
	if(is_null($headers))$headers=array();
	
	$body .= "\n\n\n---\n$footer\n"; //Attach footer.
	$html = BBCode($body); //BBCode it.
	
	$subject = htmlentities($prefix.' '.$subject);
	
	if(!is_array($to)||!is_string($subject)||!is_string($body)||(!is_array($reply_to)&&!is_string($reply_to))||!is_string($prefix)||!is_string($footer)||!is_array($headers)){
		//alert("Invalid parameters.");
		return false;
	}
	
	//Ok everything seems to be working, let's go ahead
	require_once __DIR__."/swiftmailer/swift_required.php";
	Swift_Preferences::getInstance()->setCacheType('array'); //Prevents a ton of warnings about SwiftMail's DiskKeyCache, thus actually speeding things up considerably.
	
	//Connect to the super-secret LHS Math Club Mailbot Gmail account
	$transport = Swift_SmtpTransport::newInstance($SMTP_SERVER,$SMTP_SERVER_PORT,$SMTP_SERVER_PROTOCOL)
	  ->setUsername($EMAIL_USERNAME)->setPassword($EMAIL_PASSWORD);
	
	//Make a Mailer that will send through that transport (limiting to 50/send)
	$mailer = Swift_Mailer::newInstance($transport);
	$mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(50));//Max 50 emails per send
	
	try{
		//Mush all info into the Mailer
		$message = Swift_Message::newInstance($subject)
			->setFrom(array($EMAIL_ADDRESS=>'LHS Math Club Mailbot'))
			->setBcc($to)
			->setContentType("text/html")
			->setBody($html)
			->setReplyTo($reply_to);
		
		foreach($headers as $field => $value)//Add custom headers, such as listserv stuff.
			$message->addTextHeader($field,$value);
		
		//Send the message
		if(!$mailer->send($message))trigger_error('Error sending email', E_USER_ERROR);
	}
	catch(Exception $e){
		trigger_error('Email exception: '.$e->getMessage(),E_USER_ERROR);
	}
}


/*
 * get_bcc_list()
 *
 * Gets the list of mailings-enabled people from the database, as an array. Caches.
 */
function get_bcc_list(){
	static $list=0;//Caching, for efficiency.
	if($list === 0)
		$list = DB::queryFirstColumn('SELECT email FROM users WHERE mailings="1" AND permissions!="T" AND email_verification="1"');
		//Doesn't have to be approved. Includes you.
	return $list;
}





/*
 * BBCode($string)
 * - parses a bbCode string
 *
 * Derived from http://www.pixel2life.com/forums/index.php?showtopic=10659
 */
function BBCode ($string, $strip_tags = false) {
	$search = array(
		'@\[b\](.*?)\[/b\]@si',
		'@\[i\](.*?)\[/i\]@si',
		'@\[u\](.*?)\[/u\]@si',
		'@\[img\](.*?)\[/img\]@si',
		'@\[url\](.*?)\[/url\]@si',
		'@\[url=(.*?)\](.*?)\[/url\]@si',
		'@\[email\](.*?)\[/email\]@si',
		'@\[heading\](.*?)\[/heading\]@si',
		'@\[subheading\](.*?)\[/subheading\]@si',
		'@\[bullets\](.*?)\[/bullets\]@si',
		'@\[item\](.*?)\[/item\]@si',
		'@\[pi\]@si',
		'@\[sqrt\]@si'
	);
	$replace = array(
		'<b>\\1</b>',
		'<i>\\1</i>',
		'<u>\\1</u>',
		'<img src="\\1" alt=""/>',
		'<a href="\\1" rel="external">\\1</a>',
		'<a href="\\1" rel="external">\\2</a>',
		'<a href="mailto:\\1" rel="external">\\1</a>',
		'<h2 class="smbottom">\\1</h2>',
		'<h3 class="smbottom">\\1</h3>',
		'<ul>\\1</ul>',
		'<li>\\1</li>',
		'&pi;',
		'&#8730;'
	);
	$strip_tags_replace = array(
		'\\1',
		'\\1',
		'\\1',
		'[\\1]',
		'[\\1]',
		'\\2 [\\1]',
		'[\\1]',
		'\\1',
		'\\1',
		'\\1',
		'\\1',
		'pi',
		'sqrt'
	);
	
	$string = htmlentities(strip_tags($string));
	if($strip_tags){
		$string = preg_replace($search, $strip_tags_replace, $string);
	}else{
		$string = preg_replace($search, $replace, $string);
		// $string = str_replace("</li><br />", "</li>", $string);
		// $string = str_replace("<br />\r\n<ul><br />", "<ul>", $string);
		// $string = str_replace("</ul><br />", "</ul>", $string);
		$string = nl2br($string);
	}
	
	return $string;
}





/*
 * dirsize($path)
 *
 * Calculate the size of a directory by iterating its contents
 *
 * @author      Aidan Lister <aidan@php.net>
 * @version     1.2.0
 * @link        http://aidanlister.com/2004/04/calculating-a-directories-size-in-php/
 * @param       string   $directory    Path to directory
 */
function dirsize($path)
{
    // Init
    $size = 0;

    // Trailing slash
    if (substr($path, -1, 1) !== DIRECTORY_SEPARATOR) {
        $path .= DIRECTORY_SEPARATOR;
    }

    // Sanity check
    if (is_file($path)) {
        return filesize($path);
    } elseif (!is_dir($path)) {
        return false;
    }

    // Iterate queue
    $queue = array($path);
    for ($i = 0, $j = count($queue); $i < $j; ++$i)
    {
        // Open directory
        $parent = $i;
        if (is_dir($queue[$i]) && $dir = @dir($queue[$i])) {
            $subdirs = array();
            while (false !== ($entry = $dir->read())) {
                // Skip pointers
                if ($entry == '.' || $entry == '..') {
                    continue;
                }

                // Get list of directories or filesizes
                $path = $queue[$i] . $entry;
                if (is_dir($path)) {
                    $path .= DIRECTORY_SEPARATOR;
                    $subdirs[] = $path;
                } elseif (is_file($path)) {
                    $size += filesize($path);
                }
            }

            // Add subdirectories to start of queue
            unset($queue[0]);
            $queue = array_merge($subdirs, $queue);

            // Recalculate stack size
            $i = -1;
            $j = count($queue);

            // Clean up
            $dir->close();
            unset($dir);
        }
    }

    return $size;
}





/*
 * size_readable($size)
 *
 * Return human readable sizes
 *
 * @author      Aidan Lister <aidan@php.net>
 * @version     1.3.0
 * @link        http://aidanlister.com/2004/04/human-readable-file-sizes/
 * @param       int     $size        size in bytes
 * @param       string  $max         maximum unit
 * @param       string  $system      'si' for SI, 'bi' for binary prefixes
 * @param       string  $retstring   return string format
 */
function size_readable($size, $max = null, $system = 'si', $retstring = '%01.2f %s')
{
    // Pick units
    $systems['si']['prefix'] = array('B', 'K', 'MB', 'GB', 'TB', 'PB');
    $systems['si']['size']   = 1000;
    $systems['bi']['prefix'] = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');
    $systems['bi']['size']   = 1024;
    $sys = isset($systems[$system]) ? $systems[$system] : $systems['si'];

    // Max unit to display
    $depth = count($sys['prefix']) - 1;
    if ($max && false !== $d = array_search($max, $sys['prefix'])) {
        $depth = $d;
    }

    // Loop
    $i = 0;
    while ($size >= $sys['size'] && $i < $depth) {
        $size /= $sys['size'];
        $i++;
    }

    return sprintf($retstring, $size, $sys['prefix'][$i]);
}





/*
 * email_obfuscate($address)
 * Returns javascript code for obfuscating an email address
 * Optionally, specify link text
 */
function email_obfuscate($address, $link_text=null, $pre_text='', $post_text='')
{
	if(is_null($link_text))$link_text=$address;
	return $pre_text."<a href='mailto:$address'>$link_text</a>".$post_text;
	
	$address = strtolower($address);
	$coded = "";
	$unmixedkey = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789.@";
	$len = strlen($unmixedkey);
	for ($i = 0; $i < $len; $i++)
		$unmixedkey.=$unmixedkey[rand(0,$len-1)];
	$cipher = substr($unmixedkey,$len,2*$len);

	$shift = strlen($address);

	$txt = "<script type=\"text/javascript\">\n" .
		   "          <!-"."-\n" .
		   "          // Email obfuscator script 2.1 by Tim Williams, University of Arizona\n".
		   "          // Random encryption key feature by Andrew Moulden, Site Engineering Ltd\n".
		   "          // PHP version coded by Ross Killen, Celtic Productions Ltd\n".
		   "          // This code is freeware provided these six comment lines remain intact\n".
		   "          // A wizard to generate this code is at http://www.jottings.com/obfuscator/\n".
		   "          // The PHP code may be obtained from http://www.celticproductions.net/\n\n";

	for ($j=0; $j<strlen($address); $j++)
	{
	if (strpos($cipher,$address{$j}) == -1 )
	{
		$chr = $address{$j};
		$coded .= $address{$j};
	}
	else
	{
		$chr = (strpos($cipher,$address{$j}) + $shift) % strlen($cipher);
		$coded .= $cipher{$chr};
	}
	}
	
	if (is_null($link_text)) {
		$js_link_text = '"+link+"';
		$ns_link_text = 'this address';
	}
	else {
		$js_link_text = $link_text;
		$ns_link_text = $link_text;
	}
	
	global $MAILHIDE_PUBLIC_KEY, $MAILHIDE_PRIVATE_KEY, $path_to_root;
	require_once $path_to_root . 'lib/recaptchalib.php';
	$mailhide_url = htmlentities(recaptcha_mailhide_url($MAILHIDE_PUBLIC_KEY, $MAILHIDE_PRIVATE_KEY, $address));
	
	$escaped_pre_text = str_replace('"', '\"', $pre_text);
	$escaped_post_text = str_replace('"', '\"', $post_text);
	
    $txt .= "          coded = \"" . $coded . "\"\n" .
	"            key = \"".$cipher."\"\n".
	"            shift=coded.length\n".
	"            link=\"\"\n".
	"            for (i=0; i<coded.length; i++) {\n" .
	"              if (key.indexOf(coded.charAt(i))==-1) {\n" .
	"                ltr = coded.charAt(i)\n" .
	"                link += (ltr)\n" .
	"              }\n" .
	"              else {     \n".
	"                ltr = (key.indexOf(coded.charAt(i))-shift+key.length) % key.length\n".
	"                link += (key.charAt(ltr))\n".
	"              }\n".
	"            }\n".
	"          document.write(\"<div>$escaped_pre_text<a href='mailto:\"+link+\"' target='_blank'>$js_link_text</a>$escaped_post_text</div>\")\n" .
	"          \n".
	"          //-"."->\n" .
	"          <" . "/script>\n          <noscript><div>$pre_text<a href=\"$mailhide_url\" onclick=\"window.open('$mailhide_url', '', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=500,height=300'); return false;\" title=\"Reveal this e-mail address\">" .
    $ns_link_text . '</a>' .
	"$post_text</div></noscript>";
	return $txt;
}





/*
 * page_header($title)
 *  - $title: the title of the page, which is shown in the browser's
 *      titlebar. The string ' | LHS Math Club' is appended to the end.
 *
 *  Echoes the top half of the page template (that comes before the content).
 */
function page_header($title) {
	global $path_to_root, $body_onload, $use_rel_external_script, $jquery_function, $popup_javascript, $LOCAL_BORDER_COLOR;
	
	$logged_in_header = '';
	if (isSet($_SESSION['user_id']))
		$logged_in_header = <<<HEREDOC

      <div id="user"><span id="username">{$_SESSION['user_name']}</span><span id="bar"> | </span><a href="{$path_to_root}Account/Signout">Sign Out</a></div>
HEREDOC;
	
	$rel_external_script = '';
	if ($use_rel_external_script)
		$rel_external_script = <<<HEREDOC

    <script type="text/javascript" src="{$path_to_root}res/rel_external.js"></script>
HEREDOC;
	
	if ($body_onload != '')
		$body_onload = ' onload="' . $body_onload . '"';
	
	$jquery_code = '';
	if ($jquery_function != '') {
	
	$jquery_code = <<<HEREDOC

    <link rel="stylesheet" href="{$path_to_root}res/jquery/css/smoothness/jquery-ui-1.8.5.custom.css" type="text/css" media="all"/>
    <script type="text/javascript" src="{$path_to_root}res/jquery/js/jquery-1.4.2.min.js"></script>
    <script type="text/javascript" src="{$path_to_root}res/jquery/js/jquery-ui-1.8.5.custom.min.js"></script>
    <script type="text/javascript">
$jquery_function
    </script>
    <style type="text/css">
      .ui-datepicker, .ui-autocomplete {
        font-size: 12px;
      }
    </style>
HEREDOC;
	}
	
	if (isSet($LOCAL_BORDER_COLOR))
		$local_border_code = ' style="border-bottom: 4px solid ' . $LOCAL_BORDER_COLOR . '"';
	
	$popup_code = '';
	if ($popup_javascript)
		$popup_code = "\n" . '    <script type="text/javascript" src="' . $path_to_root . 'res/popup.js"></script>';
	
	echo <<<HEREDOC
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html
     PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <title>$title | LHS Math Club</title>
    <link rel="icon" href="{$path_to_root}favicon.ico" />
    <link rel="stylesheet" href="{$path_to_root}res/default.css" type="text/css" media="all" />
    <link rel="stylesheet" href="{$path_to_root}res/print.css" type="text/css" media="print" />$rel_external_script$jquery_code$popup_code
  </head>
  <body$body_onload>
    <div id="header"$local_border_code>
      <a href="{$path_to_root}Home" id="title">LHS Math Club</a>$logged_in_header
    </div>
    
    <div id="content">

HEREDOC;
}





/*
 * page_footer($names, $pages)
 *  - $names: a numbered array of the link titles to display. A space is ''.
 *  - $pages: a numbered array of the URLs that the corresponding names point
 *    to, relative to the root. The current page should be ''.
 *
 * Echoes the bottom half of the page template, and shows the specified
 * links on the side.
 */
function page_footer($names, $pages) {
	global $path_to_root;
	
	echo <<<HEREDOC

    </div>
    
    <div id="linkbar"><br />
      <div class="linkgroup">

HEREDOC;

	for ($i = 0; $i < count($names); $i++) {
		if ($names[$i] == '')
			echo "      </div>\n      <div class=\"linkgroup\">\n";
		else if ($pages[$i] == '')
			echo "        <span class=\"selected\">{$names[$i]}</span><br />\n";
		else
			echo "        <a href=\"{$path_to_root}{$pages[$i]}\">{$names[$i]}</a><br />\n";
	}
	

	echo <<<HEREDOC
      </div>
    </div>
  </body>
</html>
HEREDOC;
}





/*
 * default_page_footer($page_name)
 *  - $page_name: the $name of the page (made bold in the link list)
 *
 *  This is the footer for most pages on the site, either logged in or logged
 *  out (it shows two different link bars)
 */
function default_page_footer($page_name) {
	if (!isSet($_SESSION['user_id'])) {
		$names[] = 'Home';
		$pages[] = 'Home';
		
		$names[] = 'Calendar';
		$pages[] = 'Calendar';
		
		
		$names[] = 'Contests';
		$pages[] = 'Contests';
		
		
		$names[] = 'Contact';
		$pages[] = 'Contact';
		
		
		if ($page_name == 'About' || $page_name == 'Contact') {
			$names[] = 'About';
			$pages[] = 'About';
			
		}
		
		$names[] = '';
		$pages[] = '';
		
		
		$names[] = 'LMT';
		$pages[] = 'LMT';
		
		
		$names[] = '';
		$pages[] = '';
		
		
		$names[] = 'Member Sign-in';
		$pages[] = 'Account/Signin';
		
		
		$names[] = 'Member Registration';
		$pages[] = 'Account/Register';
		
		
		for ($n = 0; $n < count($names); $n++) {
			if ($names[$n] == $page_name)
				$pages[$n] = '';
		}
		
		page_footer($names, $pages);
	}
	else {
		$names[] = 'Home';
		$pages[] = 'Home';
		
		
		$names[] = 'LMT';
		$pages[] = 'LMT';
		
		
		$names[] = 'Contact';
		$pages[] = 'Contact';
		
		
		if ($page_name == 'About' || $page_name == 'Contact') {
			$names[] = 'About';
			$pages[] = 'About';
			
		}
		
		$names[] = '';
		$pages[] = '';
		
		
		$names[] = 'Messages';
		$pages[] = 'Messages';
		
		
		$names[] = 'Calendar';
		$pages[] = 'Calendar';
		
		
		$names[] = 'Contests';
		$pages[] = 'Contests';
		
		
		$names[] = 'Files';
		$pages[] = 'Files';
		
		
		$names[] = '';
		$pages[] = '';
		
		
		if ($_SESSION['permissions'] != 'L') {
			$names[] = 'My Scores';
			$pages[] = 'My_Scores';
			
		}
		
		$names[] = 'My Profile';
		$pages[] = 'Account/My_Profile';
		
		
		// Link to Admin Control Panel
		if ($_SESSION['permissions'] == 'A') {
			$names[] = '';
			$pages[] = '';
			
			
			$names[] = 'Admin Dashboard';
			$pages[] = 'Admin/Dashboard';
			
		}
		
		for ($n = 0; $n < count($names); $n++) {
			if ($names[$n] == $page_name)
				$pages[$n] = '';
		}
		
		page_footer($names, $pages);
	}
}





/*
 * admin_page_footer()
 */
function admin_page_footer($page_name) {
	$names[] = 'Home';
	$pages[] = 'Home';
	
	
	$names[] = 'Admin Dashboard';
	$pages[] = 'Admin/Dashboard';
	
	
	$names[] = '';
	$pages[] = '';
	
	
	$names[] = 'User List';
	$pages[] = 'Admin/User_List';
	
	
	$names[] = 'Search Members';
	$pages[] = 'Admin/Member_Search';
	
	
	$names[] = 'Invite Members';
	$pages[] = 'Admin/Invite_Members';
	
	
	$names[] = 'Approve Users';
	$pages[] = 'Admin/Approve_Users';
	
	
	$names[] = 'Temporary Users';
	$pages[] = 'Admin/Temporary_Users';
	
	
	$names[] = 'Alumni';
	$pages[] = 'Admin/Alumni';
	
	
	$names[] = '';
	$pages[] = '';
	
	
	$names[] = 'Post a Message';
	$pages[] = 'Admin/Post_Message';
	
	
	$names[] = '';
	$pages[] = '';
	
	
	$names[] = 'Tests';
	$pages[] = 'Admin/Tests';
	
	
	$names[] = 'Calendar';
	$pages[] = 'Calendar';
	
		
	$names[] = 'Files';
	$pages[] = 'Admin/Files';
	
		
	$names[] = '';
	$pages[] = '';
	
		
	$names[] = 'Edit Home Page';
	$pages[] = 'Admin/Edit_Page?Home';
	
		
	$names[] = 'Edit Contests Page';
	$pages[] = 'Admin/Edit_Page?Contests';
	
		
	$names[] = '';
	$pages[] = '';
	
	
	$names[] = 'Uptime Report';
	$pages[] = 'Admin/Uptime';
	
		
	$names[] = 'Login Log';
	$pages[] = 'Admin/Login_Log';
	
		
	$names[] = 'Registration Log';
	$pages[] = 'Admin/Registration_Log';
	
	

	$names[] = 'Database';
	$pages[] = 'Admin/Database';
	
	
	for ($n = 0; $n < count($names); $n++) {
		if ($names[$n] == $page_name)
			$pages[$n] = '';
	}
	
	page_footer($names, $pages);
}





/*
 * go_home_footer()
 *
 * The links bar only shows 'Home'
 */
function go_home_footer() {
	$names[0] = 'Home';
	$pages[0] = 'Home';
	
	page_footer($names, $pages);
}





/*
 * go_home_admin_footer()
 *
 * The links bar only shows 'Admin Dashbaord'
 */
function go_home_admin_footer() {
	$names[0] = 'Admin Dashboard';
	$pages[0] = 'Admin/Dashboard';
	
	page_footer($names, $pages);
}

?>