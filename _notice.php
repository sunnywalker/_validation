<?php
/*
File: _notice

This library adds a session-based global notification message system for information text.
- Assumes you have a *define('SESSION_NOTICE','your_site_var');* in your page config file.
- Uses *$notice* variable, so do not override in your code.
- Uses *class="notice"* for CSS display.

Usage:
> <?php
> define('SESSION_NOTICE','myappname_notice');
> require_once('/path/to/_notice.php');
> ?>

Copyright:
Sunny Walker, University of Hawaii at Hilo, 2006-2011
<http://hilo.hawaii.edu/>

License:
MIT

Variable: notice
Holds the message to be printed by <printNotice>.

Variable: notice_class
Holds the extra class(es) to be printed with <printNotice>.
*/

if (isset($_SESSION[SESSION_NOTICE]) && $_SESSION[SESSION_NOTICE]!='') {
	$notice = $_SESSION[SESSION_NOTICE];
	$notice_class = isset($_SESSION[SESSION_NOTICE.'_class']) ? $_SESSION[SESSION_NOTICE.'_class'] : '';
	unset($_SESSION[SESSION_NOTICE]);
	unset($_SESSION[SESSION_NOTICE.'_class']);
} else {
	$notice = '';
	$notice_class = '';
}

/*
Function: setNotice
Sets the global notification message.

Parameters:
message - The message.
extra_class - Optional extra class(es) to apply to the notice.

Example:
(start code)
<?php
if (isset($_POST['Submit'])) {
	setNotice('Your form has been submitted successfully.');
	header('Location: ./');
	exit();
}
?>
(end)

See Also:
<appendNotice>
*/
function setNotice($message, $extra_class='') {
	global $notice, $notice_class;
	$notice = $message;
	$notice_class = $extra_class;
	$_SESSION[SESSION_NOTICE] = $message;
	$_SESSION[SESSION_NOTICE.'_class'] = $notice_class;
} //setNotice()

/*
Function: setNoticeToErrors
Sets the global notification message with a list of all errors generated from the *_validation* library. This also works with the Validation static class.

Parameters:
extra_class - Optional extra class(es) to apply to the notice. Note that this will override any previous extra class settings made by <setNotice>.

Example:
(start code)
<?php
if (isErrors()) {
	setNoticeToErrors();
	header('Location: ./');
	exit();
}
?>
(end)
*/
function setNoticeToErrors($extra_class='') {
	global $notice, $notice_class, $validation;
	if (class_exists('Validation')) $notice = Validation::getErrorMessage();
	elseif (isset($validation) && is_array($validation)) $notice = "There were errors with your submission:<br />\n&bull; ".implode("<br />\n&bull; ",$validation);
	else $notice = 'There were errors with your submission.';
	$notice_class = $extra_class;
	$_SESSION[SESSION_NOTICE] = $message;
	$_SESSION[SESSION_NOTICE.'_class'] = $extra_class;
} //setNoticeToErrors()

/*
Function: printNotice
Prints and clears the global notification message.

Parameters:
id - Sets the id value of the notice. For example, id=45 sets the notice element to id="45".

Example:
> <?php printNotice(); ?>
*/

function printNotice($id='') {
	global $notice_class;
	$notice = readNotice();
	if ($notice!='') {
		echo '		<p class="notice'.($notice_class!=''?" $notice_class":'').'" id="'.$id.'">';
		echo $notice."</p>\n";
	}
} //printNotice()

/**
 * Print the notice in Twitter Bootstrap markup
 *
 * @param  boolean $with_close=true  Include the close alert link?
 * @param  boolean $block=false      Also add the alert-block class?
 * @return null
 * @version 2012-05-17.00
 * @author Sunny Walker <swalker@hawaii.edu>
 */
function printBootstrapNotice($with_close=true, $block=false) {
	global $notice_class;
	$notice = readNotice();
	if ($notice!='') {
		echo '<div class="alert'.($notice_class!=''?" $notice_class":'').($block?' alert-block':'').'">';
		if ($with_close) {
			echo '<a href="#" class="close" data-dismiss="alert">&times;</a>';
		}
		echo $notice.'</div>';
	}
} //printBoostrapNotice()

/*
Function: appendNotice
Appends (rather than sets) a message to the global notification message if that message is not blank.

Parameters:
message - The message to append.
glue - The glue to prepend the appended message.

Example:
(start code)
<?php
if ($_POST['name']=='' || $_POST['email']=='') {
	setNotice('There were errors with your submission.');
	if ($_POST['name']=='') appendNotice('Name cannot be blank.');
	if ($_POST['email']=='') appendNotice('Email cannot be blank.');
}
?>
(end)

See Also:
<setNotice>
*/

function appendNotice($message, $glue="<br />\n") {
	global $notice;
	if (trim($message)!='') {
		$notice .= $glue.$message;
		$_SESSION[SESSION_NOTICE] .= $glue.$message;
	}
} //appendNotice()

/**
 * Read and clear any notice that may exist.
 *
 * @return string  Any existing notice or empty string ''
 * @version 2012-05-15.00
 * @author Sunny Walker <swalker@hawaii.edu>
 */
function readNotice() {
	global $notice;
	$return = $notice;
	$notice = '';
	unset($_SESSION[SESSION_NOTICE]);
	return $return;
} //readNotice();

//end of _notice.php