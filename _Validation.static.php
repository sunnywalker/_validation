<?php
/**
 * Validation class for managing form (POST) data and HTML form element tags.
 *
 * @package _Validation
 * @license MIT
 * @author  Sunny Walker <swalker@hawaii.edu>
 * @version 2012-09-07.00
 */
class Validation {
	/**
	 * array of error messages with field names as indexes
	 * @access private
	 * @var array
	 */
	private static $validation = array(); //array of error messages with field name indexes
	/**
	 * array of form data with field names as indexes
	 * @access private
	 * @var array
	 */
	private static $form = array();
	/**
	 * list of fields to operate with in the form space
	 * @access private
	 * @var array
	 */
	private static $fields = array();
	/**
	 * subset list of {@link $fields} which are required fields (used for {@link validate()})
	 * @access private
	 * @var array
	 */
	private static $required_fields = array();
	/**
	 * subset list of {@link $fields} which are ignored in {@link buildInsertSQL()} and {@link buildUpdateSQL}
	 * @access private
	 * @var array
	 */
	private static $ignored_fields = array();
	/**
	 * message storage for the internal debug/logging system
	 * @access private
	 * @var array
	 */
	private static $console = array();
	/**
	 * indentation tracking for the internal debug/logging system
	 * @access private
	 * @var integer
	 */
	private static $console_indent = 0;

////////////////////////////////////////
// Group: Public variables
////////////////////////////////////////

	public static $error_class = ''; //class to apply to fields which failed validation
	public static $valid_class = ''; //class to apply to fields which pass validation
	public static $validation_error_icon = '/images/silk/error.png'; //path to icon to display error on validation checking
	public static $validation_error_icon_size = 16; //display size of validation error icon
	public static $has_errors_preface = 'There were errors with your submission:'; //the preface for the list of error messages
	public static $has_errors_glue = '<br />&bull; '; //the glue to bind together each error message
	public static $date_format = 'm/d/Y'; //format for displaying dates
	/**
	 * Name of the function passwords are encoded with via {@link quote()} and {@link quoteF()}.
	 * If the function is not callable, md5() is used.
	 * @var string
	 * @version 2012-09-07.00
	 */
	public static $password_encoder = 'md5';

////////////////////////////////////////
// Group: Public methods
////////////////////////////////////////

	/**
	 * Private constructor to prevent class instantiation
	 * @access private
	 */
	private function __construct() {}

	/**
	 * Output the validation error notice if there are any errors (similar to {@link getErrorMessage()}).
	 *
	 * @return string
	 */
	public function __toString() {
		//output the validation error notice if there are any errors
		if (self::hasErrors()) return self::$has_errors_preface.self::$has_errors_glue.implode(self::$has_errors_glue, self::$validation);
		else return '';
	} //toString() magic method

	/**
	 * Sets up the list of fields used in form processing. All three parameters can be strings: "my_field", CSV strings: "my_field, my_other_field", or arrays: array("my_field","my_other_field")
	 *
	 * @param string|array $fields              The list of fields used in {@link getPost()} and {@link getRs()}.
	 * @param string|array $required_fields=''  The list of fields that must validate as not empty or >0 for numeric.
	 * @param string|array $ignored_fields=''   The list of fields that are used in form processing but ignored when building insert and update SQL statements via the {@link buildInsertSQL()} and {@link buildUpdateSQL()} methods.
	 */
	public static function setupFields($fields, $required_fields='', $ignored_fields='') {
		self::log("args(fields=".self::pp($fields).", required_fields=".self::pp($required_fields).", ignored_fields=".self::pp($ignored_fields).")");
		//convert all params to arrays if not already
		self::$fields = self::str2Array($fields);
		self::$required_fields = self::str2Array($required_fields);
		self::$ignored_fields = self::str2Array($ignored_fields);
	} //setupFields()

	/**
	 * Get an array of either the validation or form data. Used for debugging purposes
	 *
	 * @param string $what  Specify what data to get ('validation' or 'form')
	 * @return mixed
	 */
	public static function getData($what='validation') {
		if ($what!='validation' && $what!='form') return false;
		if (count(self::$$what)==0) return '';
		return self::$$what;
	} //getData()

	/**
	 * Get an SQL INSERT statement based on form data
	 *
	 * @param string $table_name    Specify the name of the table into which the INSERT is made
	 * @param string|array $fields  The list of fields for which to build the INSERT statement. If not supplied, all fields in the form data are used.
	 * @return INSERT statement string
	 */
	public static function buildInsertSQL($table_name, $fields='') {
		self::log("args(table_name=".self::pp($table_name).", fields=".self::pp($fields).")",1);
		$insertSQL = "INSERT INTO $table_name";
		$field_list = self::str2Array($fields); //turn the field names into an array
		//now add the keys and values to the statement
		$insertSQL .= ' ('.implode(', ', $field_list).') VALUES (';
		$inserts = array();
		foreach($field_list as $key) {
			$key = trim($key);
			$inserts[] = self::quoteF($key, self::guessFieldType($key));
		}
		$insertSQL .= implode(', ', $inserts);
		$insertSQL .= ')';
		self::log(self::pp($insertSQL),-1);
		return $insertSQL;
	} //buildInsertSQL()

	/**
	 * Get an SQL UPDATE statement based on form data
	 *
	 * @param string $table_name       Specify the name of the table for which the UPDATE is made
	 * @param string $update_key       Field name of the key for the UPDATE
	 * @param string $update_id        Value of key name
	 * @param string|array $fields=''  The list of fields for which to build the UPDATE statement. If not supplied, all fields in the form data are used.
	 * @return UPDATE statement string
	 */
	public static function buildUpdateSQL($table_name, $update_key, $update_id, $fields='') {
		self::log("args(table_name=".self::pp($table_name).", update_key=".self::pp($update_key).", update_id=".self::pp($update_id).", fields=".self::pp($fields).")",1);
		$updateSQL = "UPDATE $table_name SET ";
		$field_list = self::str2Array($fields); //turn the field names into an array
		//now add the keys and values to the statement
		$updates = array();
		foreach($field_list as $key) {
			$key = trim($key);
			$updates[] = $key.'='.self::quoteF($key, self::guessFieldType($key));
		}
		$updateSQL .= implode(', ', $updates);
		//append the update primary key
		$updateSQL .= ' WHERE '.$update_key.'='.self::quote($update_id, self::guessFieldType($update_key));
		self::log(self::pp($updateSQL),-1);
		return $updateSQL;
	} //buildUpdateSQL()

	/**
	 * Get the list of errors for the selected fields (or all fields if none were specified).
	 *
	 * @param  string $fields=''  Optional whitelist of fields
	 * @return string
	 */
	public static function getErrors($fields='') {
		self::log("args(fields=".self::pp($fields).")",1);
		$return = '';
		if ($fields=='') $return = implode(self::$has_errors_glue, self::$validation);
		else {
			$fields = self::str2Array($fields);
			foreach ($fields as $field) {
				if (isset(self::$validation[$field]) && self::$validation[$field]!='') $return .= ($return!=''?self::$has_errors_glue:'').self::$validation[$field];
			}
		}
		self::log(self::pp($return),-1);
		return $return;
	} //getErrors()

	/**
	 * Get the combined error messages along with the {@link $has_errors_preface}.
	 *
	 * @return string
	 */
	public static function getErrorMessage() {
		self::log('args()');
		$return = self::hasErrors() ? self::$has_errors_preface.self::$has_errors_glue.implode(self::$has_errors_glue, self::$validation) : '';
		return $return;
	} //getErrorMessage()

	/**
	 * Have any errors been found by {@link validate()} or {@link validateNotSpam()}?
	 *
	 * @return boolean
	 */
	public static function hasErrors() {
		self::log("args()",1);
		$return = count(self::$validation)>0;
		self::log(self::pp($return),-1);
		return $return;
	} //hasErrors()

	/**
	 * Get the $_POST data for the selected field(s) and {@link convert()} them based on the specified type.
	 *
	 * @param  string|array $field_names  List of fields
	 * @param  string       $type='text'  Field type; see {@link convert()} for valid types.
	 * @return null
	 */
	public static function getPost($field_names='', $type='text') {
		self::log("args(field_names=".self::pp($field_names).", type=".self::pp($type).")",1);
		if ($field_names=='' && count(self::$fields)>0) {
			//field_names wasn't specified, but the class knows about them already
			foreach(self::$files as $field) self::getPost($field, $type);
			self::$console_indent--;
		} elseif (is_array($field_names)) {
			//field_names is already an array
			foreach($field_names as $name) self::getPost($name, $type);
			self::$console_indent--;
		} elseif (strpos($field_names,',')!==false) {
			//field_names is comma-delimited
			$field_names = explode(',',$field_names);
			foreach($field_names as $name) self::getPost(trim($name), $type);
			self::$console_indent--;
		} elseif ($field_names!='') {
			//assume field_names is one field
			$return = self::convert(isset($_POST[$field_names]) ? $_POST[$field_names] : '', $type);
			self::$form[$field_names] = $return;
			self::log(self::pp($return),-1);
		}
	} //getPost()

	/**
	 * Get the raw contents of the specified field.
	 *
	 * While this can be done with {@link field()} without any parameters, field() will sanitize the value with htmlspecialchars().
	 *
	 * @param  string $field_name  Name of the field
	 * @return mixed
	 * @version 2012-07-25.00
	 */
	public static function getField($field_name) {
		self::log("args(field_name=".self::pp($field_name).")");
		if ($field_name!=='' && array_key_exists($field_name, self::$form)) {
			return self::$form[$field_name];
		}
		return null;
	} //getField()

	/**
	 * Set the value of the specified field(s).
	 *
	 * @param string|array $field_names   List of field names to set their values
	 * @param mixed        $new_value=''  New value for the fields
	 * @param string       $type='text'   Field type; see {@link convert()} for valid types.
	 */
	public static function setField($field_names, $new_value='', $type='text') {
		self::log("args(field_names=".self::pp($field_names).", new_value=".self::pp($new_value).", type=".self::pp($type).")",1);
		if (is_array($field_names)) {
			//field_names is already an array
			foreach($field_names as $name) self::setField($name, $new_value, $type);
			self::$console_indent--;
		} elseif (strpos($field_names,',')!==false) {
			//field_names is comma-delimited
			$field_names = explode(',',$field_names);
			foreach($field_names as $name) self::setField(trim($name), $new_value, $type);
			self::$console_indent--;
		} elseif ($field_names!='') {
			//assume field_names is one field
			$value = self::convert($new_value, $type);
			self::$form[$field_names] = $value;
			self::log(self::pp($value),-1);
		}
	} //setField()

	/**
	 * Unset the field(s) effectively removing them from the known field data space.
	 *
	 * @param  string|array $field_names  Field(s) to unset
	 * @return null
	 */
	public static function unsetField($field_names) {
		self::log("args(field_names=".self::pp($field_names).")",1);
		if (is_array($field_names)) {
			//field_names is already an array
			foreach($field_names as $name) self::unsetField($name);
			self::$console_indent--;
		} elseif (strpos($field_names,',')!==false) {
			//field_names is comma-delimited
			$field_names = explode(',',$field_names);
			foreach($field_names as $name) self::unsetField(trim($name));
			self::$console_indent--;
		} elseif ($field_names!='') {
			//assume field_names is one field
			unset(self::$form[$field_names]);
			self::$console_indent--;
		}
	} //unsetField()

	/**
	 * Grab data for the specified field(s) from the associative array $row_rs (usually a database recordset row).
	 *
	 * @param  string|array $field_names  Field name(s) to fetch
	 * @param  array        $row_rs       Associative array of field data with keys corresponding to field names
	 * @param  string       $type         Field type; see {@link convert()} for valid types.
	 * @return null
	 */
	public static function getRs($field_names, $row_rs, $type='text') {
		self::log("args(field_names=".self::pp($field_names).", row_rs=".self::pp($row_rs).", type=".self::pp($type).")",1);
		if (is_array($field_names)) {
			//field_names is already an array
			foreach($field_names as $name) self::getRs($name, $row_rs, $type);
			self::$console_indent--;
		} elseif (strpos($field_names,',')!==false) {
			//field_names is comma-delimited
			$field_names = explode(',',$field_names);
			foreach($field_names as $name) self::getRs(trim($name), $row_rs, $type);
			self::$console_indent--;
		} elseif ($field_names!='') {
			//assume field_names is one field
			$value = self::convert($row_rs[$field_names], $type);
			self::$form[$field_names] = $value;
			self::log(self::pp($value),-1);
		}
	} //getRs()

	/**
	 * Manually set an error for the field(s).
	 *
	 * @param string|array $field_names    Field name(s)
	 * @param string       $error_message  Error message which is passed through {@link filterErrorMessage()}
	 * @return null
	 */
	public static function setError($field_names, $error_message) {
		self::log("args(field_names=".self::pp($field_names).", error_message=".self::pp($error_message).")", 1);
		if (is_array($field_names)) {
			//field_names is already an array
			foreach($field_names as $name) self::setError($name, $error_message);
		} elseif (strpos($field_names, ',')!==false) {
			//field_names is comma-delimited
			$field_names = explode(',', $field_names);
			foreach($field_names as $name) self::setError(trim($name), $error_message);
		} elseif ($field_names!='') {
			//assume field_names is one field
			self::$validation[$field_names] = (isset(self::$validation[$field_names])?' ':'').self::filterErrorMessage($field_names, $error_message);
		}
		self::$console_indent--;
	} //setError()

	/**
	 * Append an error message to the field(s).
	 *
	 * @param  string|array $field_names    Field name(s)
	 * @param  string       $error_message  Error message which is passed through {@link filterErrorMessage()}
	 * @param  string       $glue=' '       Use this between the existing and new messages.
	 * @return null
	 */
	public static function appendError($field_names, $error_message, $glue=' ') {
		self::log("args(field_names=".self::pp($field_names).", error_message=".self::pp($error_message).")",1);
		if (is_array($field_names)) {
			//field_names is already an array
			foreach($field_names as $name) self::appendError($name, $error_message);
		} elseif (strpos($field_names, ',')!==false) {
			//field_names is comma-delimited
			$field_names = explode(',', $field_names);
			foreach($field_names as $name) self::appendError(trim($name), $error_message);
		} elseif ($field_names!='') {
			//assume field_names is one field
			self::$validation[$field_names] .= $glue.self::filterErrorMessage($field_names, $error_message);
		}
		self::$console_indent--;
	} //appendError()

	/**
	 * Validate the contents of the field based on its type.
	 *
	 * Types:
	 * - text      Tests if not empty string
	 * - email     Tests against {@link isValidEmail()}
	 * - int       Tests if integer value is >= min_int option (see below)
	 * - password  Tests if length>=5 and is not easy-to-guess
	 *
	 * Options:
	 * - string type     Type of test (as above), default: 'text'
	 * - int    min_int  Minimum value for integers
	 *
	 * @param string|array $field_names     Name of field(s) as string, CSV, or array of field names
	 * @param string       $error_message   Error message if test fails {@link setError()}
	 * @param array        $options=array() Optional settings (see above)
	 * @return bool
	 * @version 2012-03-09.00
	 */
	public static function validate($field_names, $error_message, $options=array()) {
		self::log("args(field_names=".self::pp($field_names).", error_message=".self::pp($error_message).", options=".self::pp($options).")",1);
		$return = true;
		if (is_array($field_names)) {
			//names is already an array
			foreach($field_names as $name) $return = self::validate($name, $error_message, $options) && $return;
		} elseif (strpos($field_names, ',')!==false) {
			//names is comma-delimited
			$field_names = explode(',', $field_names);
			foreach($field_names as $name) $return = self::validate(trim($name), $error_message, $options) && $return;
		} elseif ($field_names!='') {
			//assume the name is one field
			$options = self::extend($options, array(
				'min_int'=>1, //default minimum integer value
				'type'=>'text' //default field type to text
			));
			if ($options['type']=='int') $return = intval(self::$form[$field_names])>=$options['min_int'];
			elseif ($options['type']=='email') $return = self::isValidEmail(self::$form[$field_names]);
			elseif ($options['type']=='password') {
				if (strlen(self::$form[$field_names])<5) $return = false;
				if (in_array(strtoupper(self::$form[$field_names]),array('SECRET','PASSWORD','QWERTY','12345','123456','1234567','12345678','123456789','1234567890','ABCDE'))) $return = false;
			} else {
				//all other type just test not empty
				if (substr($field_names, -1)==']') {
					//sub-index of an array
					$sub_array = self::splitArrayKey($field_names);
					$return = (isset(self::$form[$sub_array['field']][$sub_array['key']]) && self::$form[$sub_array['field']][$sub_array['key']]!='');
				} else $return = isset(self::$form[$field_names]) && (self::$form[$field_names]!='');
			}

			if (!$return) self::setError($field_names, $error_message);
		}
		self::log(self::pp($return),-1);
		return $return;
	} //validate()

	/**
	 * Use {@link isSpam()} to determine if the field contents look like typical spam.
	 *
	 * @param  string|array $field_names    Field name(s)
	 * @param  string       $error_message  Error message which is passed through {@link filterErrorMessage()}
	 * @return null
	 */
	public static function validateNotSpam($field_names, $error_message) {
		self::log("args(field_names=".self::pp($field_names).", error_message=".self::pp($error_message)."",1);
		if (is_array($field_names)) {
			//field_names is already an array
			foreach($field_names as $name) self::validateNotSpam($name, $error_message);
		} elseif (strpos($field_names, ',')!==false) {
			//field_names is comma-delimited
			$field_names = explode(',', $field_names);
			foreach($field_names as $name) self::validateNotSpam(trim($name), $error_message);
		} elseif ($field_names!='') {
			//assume field_names is one field
			$spam = self::isSpam($field_names);
			if ($spam) self::$validation[$field_names] = self::filterErrorMessage($field_names, $error_message);
		}
		self::log(self::pp($spam),-1);
	} //validateNotSpam()

	/**
	 * Return a value or the specified blank if the value is empty (or falsey).
	 *
	 * @param  mixed   $what            The original value to test
	 * @param  string  $blank='&nbsp;'  The returned value if $what is empty/falsey
	 * @param  boolean $filter=true     Also filter the original value throught hmtmlspecialchars()?
	 * @return mixed
	 */
	public static function blankVal($what, $blank='&nbsp;', $filter=true) {
		self::log("args(what=".self::pp($what).", blank=".self::pp($blank).", filter=".self::pp($filter).")");
		$return = ($what=='') ? $blank : ($filter ? htmlspecialchars($what) : $what);
		self::log(self::pp($return),-1);
		return $return;
	} //blankVal()

	/**
	 * If the specified field has an error (through {@link validate()} or {@link setError()}), get an error marker.
	 *
	 * If the {@link $validation_error_icon} is set, the icon is returned with the error in the title attribute;
	 * otherwise, just the error message is returned.
	 *
	 * @param  string $field_name   Field name
	 * @param  string $wrap_tag=''  Optional tag to wrap around the icon (e.g., 'div')
	 * @return string
	 * @version 2012-09-07.00
	 */
	public static function checkValidation($field_name, $wrap_tag='') {
		self::log("args(field_name=".self::pp($field_name).", wrap_tag=".self::pp($wrap_tag).")",1);
		$return = '';
		if (!self::isValid($field_name)) {
			if (self::$validation_error_icon!='') {
				//if there is a global error icon
				if ($wrap_tag!='') {
					$return .= '<'.$wrap_tag.' title="'.htmlspecialchars(strip_tags(self::$validation[$field_name])).'"';
					if (self::$error_class!='') $return .= ' class="'.self::$error_class.'"';
					$return .= '>';
				}
				$return .= '<img src="'.self::$validation_error_icon.'" alt="'.htmlspecialchars(strip_tags(self::$validation[$field_name])).'"';
				if ($wrap_tag=='') { //add title and error class to image if not already in wrapping tag
					$return .= ' title="'.htmlspecialchars(strip_tags(self::$validation[$field_name])).'"';
					if (self::$error_class!='') $return .= ' class="'.self::$error_class.'"';
				}
				$return .= ' width="16" height="16" border="0" />';
				if ($wrap_tag!='') $return .= "</$wrap_tag>";
				$return .= ' ';
			} else {
				//no global error icon so just print the message
				if (self::$error_class!='') $return .= '<span class="'.self::$error_class.'">';
				$return .= self::$validation[$field_name]; //unfiltered
				if (self::$error_class!='') $return .= '</span>';
			}
		}
		self::log(self::pp($return),-1);
		return $return;
	} //checkValidation()

	/**
	 * Generate the HTML for a checkbox and label tag.
	 *
	 * If the value of the field (from {@link getPost()}, {@link getRs()},
	 * or {@link setField()}) is the $value passed, the checkbox will have
	 * its checked attribute set.
	 *
	 * Options:
	 * - string  extra_attributes        Any extra tag attributes (must be in 'attribute="value"' format)
	 * - boolean filter_image            Apply htmlspecialchars() to the optional image? Default: false
	 * - boolean filter_label            Apply htmlspecialchars() the label? Default: true
	 * - boolean filter_value            Apply //htmlspecialchars() the value? Default: true
	 * - string  id                      Use this for the id attribute instead of the auto-generated one
	 * - string  image                   Include this image in the label (use HTML code)
	 * - string  input_class             Class attribute value for the checkbox
	 * - string  label_class             Class attribute value for the label
	 * - string  label_extra_attributes  Any extra attributes for the label (must be in 'attribute="value"' format)
	 * - string  label_style             Style attribute value for the label
	 * - boolean no_label                Don't print the label tag (bad for usability)? Default: false
	 * - string  rel                     Rel attribute value for the checkbox
	 * - boolean required                Is this a required field (for the HTML5 required attribute)? Default: false
	 * - string  style                   Style attribute value for the checkbox
	 * - boolean with_validation         Include the results of {@link checkValidation()} before the checkbox? Default: false
	 *
	 * @param  string $field_name  Field name (append '[]' for a field array)
	 * @param  mixed  $value       Value for the checkbox
	 * @param  string $label=''    Text for the label ($value is used if this isn't specified)
	 * @param  array  $options     Optional settings (see above)
	 * @return string
	 */
	public static function checkbox($field_name, $value, $label='', $options=array()) {
		self::log("args(field_name=".self::pp($field_name).", value=".self::pp($value).", label=".self::pp($label).", options=".self::pp($options).")",1);
		//options defaults
		$options = self::extend($options, array(
			'extra_attributes'=>'', //any extra attributes (must be in 'attribute="value"' format)
			'filter_image'=>false, //htmlspecialchars() the image?
			'filter_label'=>true, //htmlspecialchars() the label?
			'filter_value'=>true, //htmlspecialchars() the value?
			'id'=>'', //override the id
			'image'=>'', //include this image in the label (use HTML code)
			'input_class'=>'', //class for the checkbox input
			'label_class'=>'', //class for the label
			'label_extra_attributes'=>'', //any extra attributes for the label (must be in 'attribute="value"' format)
			'label_style'=>'', //style attribute for the label
			'no_label'=>false, //don't print any label? (bad for usability)
			'rel'=>'', //rel attribute for the checkbox input
			'required'=>false, //is required? (HTML5)
			'style'=>'', //style attribute for the input
			'with_validation'=>false //check the field validation?
		));
		$return = '';
		if ($label=='') $label=$value; //if no label, use value as the label
		$use_arrays = substr($field_name, -2)=='[]'; //detect whether the name is an array
		$base_field_name = $use_arrays ? substr($field_name, 0, -2) : $field_name; //trim the field name if using an array
		if ($options['with_validation']) $return .= self::checkValidation($base_field_name); //validate if asked
		$return .= '<input';
		$return .= ' type="checkbox"';
		$return .= ' name="'.htmlspecialchars($field_name).'"';
		$id = self::sanitize($options['id']=='' ? $base_field_name.'_'.$value : $options['id']);
		$return .= ' id="'.$id.'"';
		$return .= ' value="'.($options['filter_value']?htmlspecialchars($value):$value).'"';
		if ((!$use_arrays && isset(self::$form[$field_name]) && self::$form[$field_name]==$value) || ($use_arrays && isset(self::$form[$base_field_name]) && is_array(self::$form[$base_field_name]) && in_array($value, self::$form[$base_field_name]))) $return .= ' checked="checked"'; //check the box if it should be set
		if ($options['required']) $return .= ' required';
		if ($options['input_class']!='') $return .= ' class="'.$options['input_class'].'"'; //add optional class
		if ($options['rel']!='') $return .= ' rel="'.$options['rel'].'"';
		if ($options['style']!='') $return .= ' style="'.$options['style'].'"';
		if ($options['extra_attributes']!='') $return .= ' '.$options['extra_attributes'];
		$return .= ' />';
		if (!$options['no_label'] && ($label!='' || $options['image']!='')) {
			$return .= ' <label for="'.$id.'"'.($options['label_class']!=''?' class="'.$options['label_class'].'"':'');
			if ($options['label_style']!='') $return .= ' style="'.$options['label_style'].'"';
			if ($options['label_extra_attributes']!='') $return .= ' '.$options['label_extra_attributes'];
			$return .= '>';
			$return .= trim(($options['filter_image']?htmlspecialchars($options['image']):$options['image']).' ');
			$return .= ($options['filter_label']?htmlspecialchars($label):$label);
			$return .= '</label>';
		}
		self::log(self::pp($return),-1);
		return $return;
	} //checkbox()

	/**
	 * Print the field contents or the HTML tag for the field.
	 *
	 * Note, if only the field contents are printed, they are first filtered through htmlspecialchars().
	 * Use {@link getField()} for the raw field contents.
	 *
	 * Options:
	 * - boolean autofocus            Include the HTML5 autofocus attribute? Default: false
	 * - string  extra_attributes     Any extra attributes (must be in 'attribute="value"' format)
	 * - string  id                   Use this for the id attribute instead of the auto-generated one
	 * - string  max                  HTML5 max attribute value
	 * - string  min                  HTML5 min attribute value
	 * - string  placeholder          HTML5 placeholder attribute value
	 * - string  rel                  Rel attribute value
	 * - boolean required             Is this a required field (for the HTML5 required attribute)? Default: false
	 * - string  step                 HTML5 setup attribute value
	 * - boolean strip_slashes        Apply stripslashes() to the value? Default: true
	 * - string  style                Style attribute value
	 * - string  tabindex             Tabindex attribute value
	 * - string  type                 Type attribute value; Default: 'text'
	 * - string  with_class           Additional class(es) for the input
	 * - boolean with_validation      Apply the {@link $error_class} and {@link $valid_class} based on field validation? Default: true
	 * - boolean without_valid_class  Don't include the {@link $valid_class} even when it's valid (doesn't affect errors)? Default: false
	 *
	 * @param  string  $field_name    Field name (append '[]' for a field array)
	 * @param  integer $size=0        Size of the input field; if 0, the value of the field will be printed instead
	 * @param  integer $max_size=255  Maxlength value of the field; 0 is treated the same as 255
	 * @param  array   $options       Optional settings (see above)
	 * @return string
	 */
	public static function field($field_name, $size=0, $max_size=255, $options=array()) {
		self::log("args(field_name=".self::pp($field_name).", size=".self::pp($size).", max_size=".self::pp($max_size).", options=".self::pp($options).")",1);
		if (substr($field_name,-1)==']') {
			list($field,$index) = explode('[',$field_name);
			$index = rtrim($index,']');
			$value = (array_key_exists($field, self::$form) && is_array(self::$form[$field]) && isset(self::$form[$field][$index])) ? self::$form[$field][$index] : '';
		} else $value = isset(self::$form[$field_name]) ? self::$form[$field_name] : '';
		//options defaults
		$options = self::extend($options, array(
			'autofocus'=>false, //is autofocus? (HTML5)
			'extra_attributes'=>'', //any extra attributes (must be in 'attribute="value"' format)
			'id'=>'', //override computed id
			'max'=>'', //maximum value (HTML5)
			'min'=>'', //minimum value (HTML5)
			'placeholder'=>'', //placeholder text (HTML5)
			'rel'=>'', //rel attribute for the input
			'required'=>false, //is required? (HTML5)
			'step'=>'', //step value (HTML5)
			'strip_slashes'=>true, //strip slashes from the value before printing
			'style'=>'', //style attribute for the input
			'tabindex'=>'', //tab index
			'type'=>'text',	//input type
			'with_class'=>'', //additional class for input
			'with_validation'=>true, //include validation checking?
			'without_valid_class'=>false //hide the valid class (but don't hide the error class if not valid)
		));
		$return = '';
		switch ($options['type']) {
			//case 'int': $value=intval($value); break; //already handled?
			//case 'float': $value=floatval($value); break; //already handled?
			case 'float2': $value=number_format($value,2); break;
		}
		if ($size>0) {
			if ($options['with_validation']) $return .= self::checkValidation($field_name);
			$return .= '<input';
			$return .= ' type="'.$options['type'].'"';
			$return .= ' name="'.htmlspecialchars($field_name).'"';
			$return .= ' id="'.($options['id']!=''?$options['id']:self::sanitize($field_name)).'"';
			$return .= ' value="'.($options['type']!='password'?htmlspecialchars($options['strip_slashes']?stripslashes($value):$value):'').'"';
			if ($options['tabindex']!='') $return .= ' tabindex="'.$options['tabindex'].'"';
			if ($options['type']=='number' || $options['type']=='range') {
				if ($options['min']!=='') $return .= ' min="'.$options['min'].'"';
				if ($options['max']!=='') $return .= ' max="'.$options['max'].'"';
				if ($options['step']!=='') $return .= ' step="'.$options['step'].'"';
			}
			$return .= ' size="'.$size.'"';
			if ($max_size>0 && $max_size<255) $return .= ' maxlength="'.$max_size.'"';
			if ($options['rel']!='') $return .= ' rel="'.$options['rel'].'"';
			if ($options['style']!='') $return .= ' style="'.$options['style'].'"';
			if ($options['extra_attributes']!='') $return .= ' '.$options['extra_attributes'];
			if ($options['placeholder']!='') $return .= ' placeholder="'.$options['placeholder'].'"';
			if ($options['autofocus']) $return .= ' autofocus';
			if ($options['required']) $return .= ' required';
			//build list of classes
			$classes = array();
			if ($options['with_class']!='') $classes[] = $options['with_class'];
			if ($options['with_validation'] && !self::isValid($field_name) && self::$error_class!='') $classes[] = self::$error_class;
			elseif ($options['with_validation'] && !$options['without_valid_class'] && isset(self::$form[$field_name]) && self::$form[$field_name]!='' && isset($_POST['Submit']) && self::$valid_class!='') $classes[] = self::$valid_class;
			if (count($classes)>0) $return .= ' class="'.implode(' ',$classes).'"';
			$return .= ' />';
		} elseif ($options['type']=='hidden') {
			$return .= '<input type="hidden" name="'.htmlspecialchars($field_name).'" id="'.($options['id']!=''?$options['id']:self::sanitize($field_name)).'" value="'.htmlspecialchars($options['strip_slashes']?stripslashes($value):$value).'" />';
		} elseif ($options['type']!='password') { //just return the value of the field in the form array (if it's not a password field)
			if (is_array($value)) $return = $value;
			else $return .= htmlspecialchars($value);
		}
		self::log(self::pp($return), -1);
		return $return;
	} //field()

	/**
	 * Convert an integer into an array of powers of 2 whose sum equals the integer.
	 * Max integer bit value converted is 2^31.
	 *
	 * For example: Validation::int2BitArray(11) will return [1, 2, 8]
	 *
	 * @param  integer $value  The integer to convert into the array of bit values
	 * @return array
	 */
	public static function int2BitArray($value) {
		//convert an integer into an array of powers of 2 (max at 2^31)
		$return = array();
		$b = 0;
		while ($value>0 && $b<32) {
			$p = pow(2,$b);
			if ($value & $p) {
				$return[] = $p;
				$value -= $p;
			}
			$b++;
		}
		return $return;
	} //int2BitArray()

	/**
	 * Generate the HTML for a radio button and label tag.
	 *
	 * If the value of the field (from {@link getPost()}, {@link getRs()},
	 * or {@link setField()}) is the $value passed, the radio will have
	 * its checked attribute set.
	 *
	 * Options:
	 * - string  extra_attributes        Any extra tag attributes (must be in 'attribute="value"' format)
	 * - boolean filter_image            Apply htmlspecialchars() to the optional image? Default: false
	 * - boolean filter_label            Apply htmlspecialchars() the label? Default: true
	 * - boolean filter_value            Apply htmlspecialchars() the value? Default: true
	 * - string  id                      Use this for the id attribute instead of the auto-generated one
	 * - string  image                   Include this image in the label (use HTML code)
	 * - string  input_class             Class attribute value for the radio
	 * - string  label_class             Class attribute value for the label
	 * - string  label_extra_attributes  Any extra attributes for the label (must be in 'attribute="value"' format)
	 * - string  label_style             Style attribute value for the label
	 * - boolean no_label                Don't print the label tag (bad for usability)? Default: false
	 * - string  rel                     Rel attribute value for the radio
	 * - boolean required                Is this a required field (for the HTML5 required attribute)? Default: false
	 * - string  style                   Style attribute value for the radio
	 * - boolean with_validation         Include the results of {@link checkValidation()} before the radio? Default: false
	 *
	 * @param  string $field_name  Field name (append '[]' for a field array)
	 * @param  mixed  $value       Value for the radio
	 * @param  string $label=''    Text for the label ($value is used if this isn't specified)
	 * @param  array  $options     Optional settings (see above)
	 * @return string
	 */
	public static function radio($field_name, $value, $label='', $options=array()) {
		self::log("args(field_name=".self::pp($field_name).", value=".self::pp($value).", label=".self::pp($value).", options=".self::pp($options).")",1);
		$options = self::extend($options, array( //default settings
			'extra_attributes'=>'', //any extra attributes (must be in 'attribute="value"' format)
			'filter_image'=>false, //htmlspecialchars() the image?
			'filter_label'=>true, //htmlspecialchars() the label?
			'filter_value'=>true, //htmlspecialchars() the value?
			'id'=>'', //override the id
			'image'=>'', //include this image in the label (use HTML code)
			'input_class'=>'', //class for the radio input
			'label_class'=>'', //class for the label
			'label_style'=>'', //style attribute for the label
			'label_extra_attributes'=>'', //any extra attributes for the label (must be in 'attribute="value"' format)
			'no_label'=>false, //don't print any label? (bad for usability)
			'rel'=>'', //rel attribute for the radio input
			'required'=>false, //is required? (HTML5)
			'style'=>'', //style attribute for the input
			'with_validation'=>false //check the field validation?
		));
		if ($label=='') $label=$value; //if no label, use value as the label
		$return = '';
		if ($options['with_validation']) $return .= self::checkValidation($field_name); //validate if asked
		$return .= '<input';
		$return .= ' type="radio"';
		$return .= ' name="'.htmlspecialchars($field_name).'"';
		$id = self::sanitize($options['id']==''?$field_name.'_'.$value:$options['id']);
		$return .= ' id="'.$id.'"';
		$return .= ' value="'.($options['filter_value']?htmlspecialchars($value):$value).'"';
		if (isset(self::$form[$field_name]) && self::$form[$field_name]==$value) $return .= ' checked'; //check the radio if it should be set
		if ($options['required']) $return .= ' required';
		if ($options['input_class']!='') $return .= ' class="'.$options['input_class'].'"'; //optional class
		if ($options['rel']!='') $return .= ' rel="'.$options['rel'].'"';
		if ($options['style']!='') $return .= ' style="'.$options['style'].'"';
		if ($options['extra_attributes']!='') $return .= ' '.$options['extra_attributes'];
		$return .= ' />';
		if (!$options['no_label'] && ($label!='' || $options['image']!='')) {
			$return .= '<label for="'.$id.'"'.($options['label_class']!=''?' class="'.$options['label_class'].'"':'');
			if ($options['label_style']!='') $return .= ' style="'.$options['label_style'].'"';
			if ($options['label_extra_attributes']!='') $return .= ' '.$options['label_extra_attributes'];
			$return .= '>';
			$return .= trim(($options['filter_image']?htmlspecialchars($options['image']):$options['image']).' '.($options['filter_label']?htmlspecialchars($label):$label));
			$return .= '</label>';
		}
		self::log(self::pp($return),-1);
		return $return;
	} //radio()

	/**
	 * Print the HTML textarea tag for the field.
	 *
	 * Options:
	 * - boolean autofocus         Include the HTML5 autofocus attribute? Default: false
	 * - string  extra_attributes  Any extra attributes (must be in 'attribute="value"' format)
	 * - integer cols              Cols attribute value; Default: 0
	 * - string  id                Use this for the id attribute instead of the auto-generated one
	 * - string  placeholder       HTML5 placeholder attribute value
	 * - string  rel               Rel attribute value
	 * - boolean required          Is this a required field (for the HTML5 required attribute)? Default: false
	 * - boolean strip_slashes     Apply stripslashes() to the value? Default: true
	 * - string  style             Style attribute value
	 * - string  tabindex          Tabindex attribute value
	 * - string  with_class        Additional class(es) for the input
	 * - boolean with_validation   Apply the {@link $error_class} and {@link $valid_class} based on field validation? Default: true
	 * - string  wrap              Wrap attribute value; Default: 'virtual'
	 * - string  zero_cols_class   Class to apply to the tag if the cols attribute value is 0; Default: 'alt'
	 *
	 * @param  string  $field_name  Field name
	 * @param  integer $rows=3      Rows attribute value
	 * @param  array   $options     Optional settings (see above)
	 * @return string
	 */
	public static function textarea($field_name, $rows=3, $options=array()) {
		self::log("args(field_name=".self::pp($field_name).", rows=".self::pp($rows).", options=".self::pp($options).")",1);
		$value = isset(self::$form[$field_name]) ? self::$form[$field_name] : '';
		$options = self::extend($options, array( //default settings
			'autofocus'=>false, //is autofocus? (HTML5)
			'extra_attributes'=>'', //any extra attributes (must be in 'attribute="value"' format)
			'cols'=>0,
			'id'=>'',
			'placeholder'=>'', //placeholder text (HTML5)
			'rel'=>'', //rel attribute for the textarea
			'required'=>false, //is required? (HTML5)
			'strip_slashes'=>true,
			'style'=>'', //style attribute for the input
			'tabindex'=>'', //tab index
			'with_class'=>'',
			'with_validation'=>true,
			'wrap'=>'virtual',
			'zero_cols_class'=>'alt'
		));
		$return = '';
		if ($options['with_validation']) $return .= self::checkValidation($field_name);
		$return .= '<textarea';
		$return .= ' name="'.htmlspecialchars($field_name).'"';
		$return .= ' id="'.($options['id']!=''?$options['id']:htmlspecialchars($field_name)).'"';
		$return .= ' rows="'.$rows.'"';
		if ($options['cols']>0) $return .= ' cols="'.$options['cols'].'"';
		$return .= ' wrap="'.$options['wrap'].'"';
		if ($options['tabindex']!='') $return .= ' tabindex="'.$options['tabindex'].'"';
		if ($options['autofocus']) $return .= ' autofocus';
		if ($options['required']) $return .= ' required';
		if ($options['placeholder']!='') $return .= ' placeholder="'.$options['placeholder'].'"';
		if ($options['rel']!='') $return .= ' rel="'.$options['rel'].'"';
		if ($options['style']!='') $return .= ' style="'.$options['style'].'"';
		if ($options['extra_attributes']!='') $return .= ' '.$options['extra_attributes'];
		//build the list of classes
		$classes = array();
		if ($options['with_class']!='') $classes[] = $options['with_class'];
		if ($options['cols']==0 && $options['zero_cols_class']!=='') $classes[] = $options['zero_cols_class'];
		if ($options['with_validation'] && !self::isValid($field_name) && self::$error_class!='') $classes[] = self::$error_class;
		elseif ($options['with_validation'] && $value!='' && isset($_POST['Submit']) && self::$valid_class!='') $classes[] = self::$valid_class;
		if (count($classes)>0) $return .= ' class="'.implode(' ',$classes).'"'; //add the classes if there are any
		$return .= '>'.htmlspecialchars($options['strip_slashes']?stripslashes($value):$value).'</textarea>';
		self::log(self::pp($return),1);
		return $return;
	} //textarea()

	/**
	 * Filter $what based on the $type for SQL statements.
	 *
	 * Data types:
	 * - text     (empty strings are converted to NULL)
	 * - int      (value is converted to int; 0 value is not converted to NULL)
	 * - posint   (value is converted to int; 0 value is converted to NULL)
	 * - float    (value is converted to float)
	 * - float2   (value is converted to float; value is rounded to 2 decimal places)
	 * - date     (value is converted to ANSI date YYYY-MM-DD via strtotime())
	 * - datetime (value is converted to ANSI date time YYYY-MM-DD HH:MM:SS via strtotime())
	 * - year     (value is converted to int; falsey values are converted to NULL)
	 * - time     (value is converted to HH:MM:SS via strtotime())
	 * - defined  (if value is truthy, $true_value is used, otherwise $false_value is used--Warning: unquoted!)
	 * - password (value has {@link $password_encoder()} applied or NULL if empty)
	 *
	 * @param  mixed  $what         Value to filter
	 * @param  string $type='text'  Data type for determining the filtering method (see above)
	 * @param  string $true_value   Truthy value used for 'defined' type
	 * @param  string $false_value  Falsey value used for 'defined' type
	 * @return string
	 * @version 2012-09-07.00
	 */
	public static function quote($what, $type='text', $true_value='', $false_value='') {
		self::log("args(what=".self::pp($what).", type=".self::pp($type).", true_value=".self::pp($true_value).", false_value=".self::pp($false_value).")",1);
		//if (function_exists('mysqli::real_escape_string')) $efn = 'mysqli::real_escape_string';
		//if (function_exists('mysql_real_escape_string')) $efn = 'mysql_real_escape_string';
		//else
		$efn = 'addslashes';
		self::log("using escape function: $efn");
		$return = $efn(get_magic_quotes_gpc() ? stripslashes((string)$what) : (string)$what);
		//self::log("escaped = ".self::pp($return)."");

		switch ($type) {
			case 'text': $return = ($return != '') ? "'".trim($return)."'" : 'NULL'; break; //regular text
			case 'int': $return = ($return!=''||$return>=0) ? intval($return) : 'NULL'; break; //integer
			case 'posint': //positive integer > 0
				$return = intval($return);
				if ($return<1) $return = 'NULL';
			break;
			case 'float': $return = ($return != '') ? "'".floatval($return)."'" : 'NULL'; break;
			case 'float2': $return = ($return != '') ? "'".round(floatval($return),2)."'" : 'NULL'; break;
			case 'date': $return = ($return != '') ? "'".date('Y-m-d', strtotime($return))."'" : 'NULL'; break;
			case 'datetime': $return = ($return != '') ? "'".date('Y-m-d H:i:s', strtotime($return))."'" : 'NULL'; break;
			case 'year': $return = intval($return>0) ? intval($return) : 'NULL'; break;
			case 'time': $return = ($return != '') ? "'".date('H:i:s', strtotime($return))."'" : 'NULL'; break;
			case 'defined': $return = ($return != '') ? $true_value : $false_value; break;
			case 'password':
				if ($return!='' && is_callable(self::$password_encoder)) {
					$return = "'".self::$password_encoder($return)."'";
				} elseif ($return!='') { //fall back to md5()
					$return = "'".md5($return)."'";
				} else {
					$return = 'NULL';
				}
			break;
			default: $return = ($return != '') ? "'".trim($return)."'" : 'NULL'; break;
		}
		self::log(self::pp($return),-1);
		return $return;
	} //quote()

	/**
	 * Filter the contents of the specified field based on the $type for SQL statements.
	 *
	 * See {@link quote()} for allowable data types.
	 *
	 * @param  string $field        Field name
	 * @param  string $type='text'  Data type for determining the filtering method (see above)
	 * @param  string $true_value   Truthy value used for 'defined' type
	 * @param  string $false_value  Falsey value used for 'defined' type
	 * @return string
	 */
	public static function quoteF($field, $type='text', $true_value='', $false_value='') {
		self::log("args(field=".self::pp($field).", type=".self::pp($type).", true_value=".self::pp($true_value).", false_value=".self::pp($false_value).")",1);
		$return = self::quote(self::$form[$field], $type, $true_value, $false_value);
		self::$console_indent--;
		return $return;
	} //quoteF()

	/**
	 * Compare two values and print selected or checked depending on the type.
	 *
	 * @param  mixed  $field_name     The name of the field in the form array (from {@link getPost()}, {@link getRs()}, {@link setField()})
	 * @param  mixed  $compare_value  The value with which to compare the form value
	 * @param  string $type           Type of field (select, checkbox, or radio)
	 * @return string
	 * @version 2011-09-02.00
	 */
	public static function selectIf($field_name, $compare_value, $type) {
		self::log("args(field_name=".self::pp($field_name).", compare_value=".self::pp($compare_value).", type=".self::pp($type).")",1);
		$return = '';
		$form_value = array_key_exists($field_name, self::$form) ? self::$form[$field_name] : '';
		if (is_int($form_value) && !is_int($compare_value)) $compare_value = intval($compare_value); //convert compare value to integer if the form value is an integer
		if (substr($field_name,-1)==']') {
			self::log('array detected');
			list($field, $index) = explode('[',$field_name); //break on [
			$index = rtrim($index,']'); //remove trailing ]
			if (is_array(self::$form[$field])) $selected = self::$form[$field][$index]==$compare_value;
			else {
				self::log("$field is not an array, not matching value");
				$selected = false;
			}
		} elseif (is_array($form_value)) {
			$selected = in_array($compare_value, self::$form[$field_name]);
		} else {
			$selected = $form_value==$compare_value;
		}
		switch ($type) {
			case 'select':
				if ($selected) $return = ' selected="selected"';
				break;
			case 'checkbox':
			case 'radio':
			case 'check':
				if ($selected) $return = ' checked="checked"';
				break;
		}
		self::log(self::pp($return),-1);
		return $return;
	} //selectIf()

////////////////////////////////////////
// Group: type checking
////////////////////////////////////////

	/**
	 * Do the field contents look like spam?
	 *
	 * Positive indicators:
	 * - contains 'http://'
	 * - contains '</a>'
	 * - contains '[/url]'
	 *
	 * @param  string $field_name  Field name
	 * @return boolean
	 * @version 2012-09-07.00
	 */
	public static function isSpam($field_name) {
		self::log("args(field_name=".self::pp($field_name).")",1);
		$return = false;
		if (strpos(self::$form[$field_name],'http://')!==false) $return = true;
		elseif (strpos(self::$form[$field_name],'</a>')!==false) $return = true;
		elseif (strpos(self::$form[$field_name],'[/url]')!==false) $return = true;
		self::log(self::pp($return), -1);
		return $return;
	} //isSpam()

	/**
	 * Is the supplied email a valid address?
	 *
	 * @param  string $email  Email address to test
	 * @return boolean
	 * @version 2012-06-14.00
	 */
	public static function isValidEmail($email) {
		self::log("args(email=".self::pp($email).")",1);
		if (function_exists('filter_var')) {
			$return = filter_var($email, FILTER_VALIDATE_EMAIL);
		} else {
			$return = preg_match('/^([0-9a-zA-Z\_\+\/\=]+\.?)+[0-9a-zA-Z\_\+\/\=\-]@([-0-9a-zA-Z]+[.])+[a-zA-Z]{2,6}$/', $email)==1;
		}
		self::log(self::pp($return),-1);
		return $return;
	} //isValidEmail()

	/**
	 * Are there no validation errors for the specified field?
	 *
	 * @param  string $field_name  Field name
	 * @return boolean
	 */
	public static function isValid($field_name) {
		self::log("args(field_name=".self::pp($field_name).")", 1);
		$return = !isset(self::$validation[$field_name]) || self::$validation[$field_name]=='';
		self::log(self::pp($return), -1);
		return $return;
	} //isValid()


////////////////////////////////////////
// Group: Private methods
////////////////////////////////////////

	/**
	 * Convert the value to the specified type.
	 *
	 * Data types:
	 * - array   (trim() is applied to all values of the array)
	 * - int
	 * - posint  (if the value isn't >0, the value becomes NULL)
	 * - float
	 * - float2  (value is rounded to 2 decimal places)
	 * - date    (value is converted to {@link $date_format} via strtotime())
	 * - year
	 * - time    (value is converted to 'g:i a' via strtotime() and date())
	 *
	 * @access private
	 * @param  mixed  $value  Value to convert
	 * @param  string $type   Resulting data type (see above)
	 * @return mixed
	 */
	private static function convert($value, $type) {
		$return = false;
		switch ($type) {
			case 'array':
				if (is_array($value)) $return = $value;
				elseif ($value!='') $return = array($value);
				else $return = array();
				$return = array_map('trim', $return);
				break;
			case 'int': $return = intval($value); break;
			case 'posint':
				$return = intval($value);
				if ($return<1) $return = NULL;
				break;
			case 'float': $return = floatval($value); break;
			case 'float2': $return = number_format(round(floatval($value), 2), 2, '.', ''); break;
			case 'date': $return = $value!='' ? date(self::$date_format, strtotime($value)) : ''; break;
			case 'year': $return = intval($value>0) ? intval($value) : ''; break;
			case 'time': $return = $value!='' ? date('g:i a', strtotime($value)) : ''; break;
			default:
				if (is_array($value)) {
					$backtrace = debug_backtrace();
					$error = 'Validation warning: convert(value) is an array in '.$backtrace[1]['file'].' line '.$backtrace[1]['line'];
					if (isset($backtrace[2])) $error .= '; '.$backtrace[2]['file'].' line '.$backtrace[2]['line'];
					trigger_error($error, E_USER_WARNING);
				} else $return = trim($value);
		}
		return $return;
	} //convert()

	/**
	 * Take a set of default options and extend them with any specified options.
	 *
	 * There is a lot of error checking in this due to changes made to
	 * methods over the course of the project. Arrays are now expected
	 * for both parameters.
	 *
	 * @access private
	 * @param  mixed $options   Options to merge
	 * @param  mixed $defaults  Defaults for any unset options
	 * @return array
	 */
	private static function extend($options, $defaults) {
		self::log("args(options=".self::pp($options).', defaults='.self::pp($defaults).')',1);
		$backtrace = debug_backtrace();
		if (!is_array($options)) {
			self::log('- warning: options is not an array');
			$error = 'Validation warning: extend(options) not an array in '.$backtrace[1]['file'].' line '.$backtrace[1]['line'];
			if (isset($backtrace[2])) $error .= '; '.$backtrace[2]['file'].' line '.$backtrace[2]['line'];
			trigger_error($error, E_USER_WARNING);
		}
		if (!is_array($defaults)) {
			self::log('- warning: defaults is not an array');
			$error = 'Validation warning: extend(defaults) not an array in '.$backtrace[1]['file'].' line '.$backtrace[1]['line'];
			if (isset($backtrace[2])) $error .= '; '.$backtrace[2]['file'].' line '.$backtrace[2]['line'];
			trigger_error($error, E_USER_WARNING);
		}
		self::$console_indent--;
		return array_merge($defaults, $options);
	} //extend()

	/**
	 * Filter the error message for placeholder codes.
	 *
	 * Placeholder codes:
	 * - '<!FIELD_NAME!>'  Replaced by the filtered field name via {@link filterFieldName()}
	 * - '<!#'             Replaced by an a tag which links to the field name id
	 * - '#>'              Replaced by a closing a tag
	 *
	 * These can be combined. For example:
	 * filterErrorMessage('full_name', 'Enter your <!#<!FIELD_NAME!>#>.') will
	 * both inject the Full Name text and link to the full_name field.
	 *
	 * @access private
	 * @param  string $field_name     Field name
	 * @param  string $error_message  Error message
	 * @return string
	 */
	private static function filterErrorMessage($field_name, $error_message) {
		self::log("args(field_name=$field_name, error_message=$error_message)",1);
		$return = str_replace('<!FIELD_NAME!>', self::filterFieldName($field_name), $error_message);
		$return = str_replace('<!#', '<a href="#'.$field_name.'">', $return);
		$return = str_replace('#>', '</a>', $return);
		self::log(self::pp($return),-1);
		return $return;
	} //filterErrorMessage()

	/**
	 * Filter the name of the field to be more human friendly.
	 *
	 * @access private
	 * @param  string $field_name   Name of the field
	 * @param  string $capitalizer  Name of the capitalize function (Default: 'ucfirst')
	 * @return string
	 */
	private static function filterFieldName($field_name, $capitalizer='ucfirst') {
		self::log("args(field_name=".self::pp($field_name).", capitalizer=".self::pp($capitalizer).")", 1);
		$return = trim(str_replace('_', ' ', $field_name));
		if (function_exists($capitalizer) && is_callable($capitalizer)) $return = $capitalizer($return);
		self::log(self::pp($return), -1);
		return $return;
	} //filterFieldName()

	/**
	 * Guess the data type of the field based on the field name.
	 *
	 * @access private
	 * @param  string $field_name  Name of the field
	 * @return string
	 * @version 2012-03-09.00
	 */
	private static function guessFieldType($field_name) {
		self::log("args(field_name=".self::pp($field_name).")",1);
		$type = 'text'; //assume text
		if ($field_name=='id') $type = 'int';
		elseif (substr($field_name,-3)=='_id') $type = 'int';
		elseif (substr($field_name,-3)=='_on') $type = 'date';
		elseif (substr($field_name,-5)=='_date') $type = 'date';
		elseif (substr($field_name,-3)=='_at') $type = 'datetime';
		elseif (substr($field_name,-5)=='_year') $type = 'year';
		elseif (substr($field_name,0,5)=='year_') $type = 'year';
		elseif (substr($field_name,-5)=='_time') $type = 'time';
		elseif (substr($field_name,0,5)=='time_') $type = 'time';
		elseif (substr($field_name,0,3)=='is_') $type = 'int';
		elseif (preg_match('/^is[A-Z]/', $field_name, $matches)) $type = 'int'; //match "isSomething" but not "issomething"
		elseif (substr($field_name,0,4)=='num_') $type = 'int';
		elseif (strtolower($field_name)=='password') $type = 'password';
		self::log(self::pp($type),-1);
		return $type;
	} //guessFieldType()

	/**
	 * Distill the string down to a-Z, A-Z, 0-9, _ and - characters.
	 * All other characters are converted to _
	 *
	 * @access private
	 * @param  string $string  String to santize
	 * @return string
	 */
	private static function sanitize($string) {
		self::log("args(string=".self::pp($string).")", 1);
		$return = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $string);
		self::log(self::pp($return), -1);
		return $return;
	} //sanitize()

	/**
	 * Convert a list of field names into an array of field names.
	 *
	 * If the field name list is empty '', a list will be generated
	 * from the all of the existing field names in the {@link $form}
	 * array.
	 *
	 * This allows for field names to be passed as any of:
	 * - a single field name
	 * - an array of field names
	 * - a comma separated string of field names
	 *
	 * Field names are filtered with trim()
	 *
	 * @access private
	 * @param  string|array $string  Field name(s)
	 * @return string
	 */
	private static function str2Array($string) {
		self::log("args(string=".self::pp($string).")",1);
		//turn the field names into an array
		if (is_array($string)) $return = $string; //already an array, do nothing
		elseif (strpos($string,',')!==false) $return = explode(',', $string); //CSV to array
		elseif ($string!='') $return = array($string); //only one field, so turn it into an array
		else $return = array_keys(self::$form); //just grab everything from $form
		$return = array_map('trim',$return); //trim the names of each element
		self::log(self::pp($return), -1);
		return $return;
	} //str2Array()

	/**
	 * Split an array key such as "values[14]" into its field name and
	 * index.
	 *
	 * Note that if a key in an invalid format is used, an array with
	 * just the key as the field name is returned.
	 *
	 * @access private
	 * @param string $key  Key to split
	 * @return array Array with field=>field_name and key=>key_value
	 * @version 2011-10-28.00
	 */
	private static function splitArrayKey($key) {
		self::log("args(key=$key)", 1);
		$return = array('field'=>'', 'key'=>'');
		if (substr($key, -1)==']') {
			list($return['field'], $return['key']) = explode('[', $key);
			$return['key'] = rtrim($return['key'], ']');
		} else {
			$return['field'] = $key;
		}
		self::log(self::pp($return), -1);
		return $return;
	} //splitArrayKey()

	/* console logging/debugging */

	/**
	 * Convert a standard data type (int, float, boolean, array, string) into a string.
	 *
	 * It cannot handle Objects which can't be iterated over via foreach.
	 *
	 * @access private
	 * @param  mixed $expression  Data to stringify
	 * @return string
	 */
	public static function pp($expression) {
		//compressed array serialization (handles all data-types except Objects)
		if (is_bool($expression)) return $expression?'true':'false';
		elseif (!is_array($expression)) return $expression;
		$return = '';
		if (count($expression)>0) foreach ($expression as $key=>$val) if ($key!='') $return .= $key.': '.(is_bool($val)?($val?'true':'false'):$val).', ';
		$return = rtrim($return, ', '); //remove last ,
		return '{'.$return.'}';
	} //pp()

	/**
	 * Log a message to the class' internal debugger.
	 *
	 * @param  string  $message       Message to log
	 * @param  integer $indent_mod=0  Modify the indent by this much ()
	 * @return null
	 */
	public static function log($message, $indent_mod=0) {
		if ($message!='') {
			$backtrace = debug_backtrace();
			$caller = ($backtrace[1]['class']?$backtrace[1]['class'].'.':'').$backtrace[1]['function'].'(): ';
			if ($indent_mod==-1) $caller = '&lt;= ';
			elseif (substr($message,0,1)=='-') $caller = '';
			$message = ($caller!=''?"<strong>$caller</strong>":'').htmlspecialchars($message);
			$indent = self::$console_indent>0 ? str_repeat('  ', self::$console_indent) : '';
			self::$console[] = $indent.$message;
		}
		if ($indent_mod!=0) {
			self::$console_indent = max(self::$console_indent+$indent_mod,0); //0 is lowest final indent
		}
	} //log()

	/**
	 * Get the contents of the internal debugger.
	 *
	 * @param  string $wrapper="\n<!-- ~~ -->\n"  Wrap the contents in this (~~ delimiter)
	 * @param  string $glue="\n"                  Join all the messages with this
	 * @return string
	 */
	public static function getLog($wrapper="\n<!-- ~~ -->\n", $glue="\n") {
		list($pre, $post) = explode('~~',$wrapper);
		return $pre.implode($glue, self::$console).$post;
	} //getLog()

	/**
	 * Clear all messages from the internal debugger.
	 *
	 * @return null
	 */
	public static function clearLog() {
		self::$console = array();
		self::$console_indent = 0;
	} //clearLog()

	/**
	 * Print the debugger log.
	 *
	 * @param  string $mode='comment'  Print mode ('comment', 'pre', 'ul', 'ol')
	 * @param  string $class='debug'   Add this class to the tag
	 * @return null
	 */
	public static function printLog($mode='comment', $class='debug') {
		$o = '';
		if ($class!='') $class=' class="'.$class.'"';
		switch ($mode) {
			case 'comment': $o = self::getLog(); break;
			case 'pre': $o = self::getLog("<pre{$class}>~~</pre>"); break;
			case 'ul':
				$o = str_replace('@@',"</li>\n<li>",self::getLog('~~','@@'));
				$o = str_replace("\t",'&mdash;',"<ul{$class}>\n<li>$o</li>\n</ul>\n");
				break;
			case 'ol':
				$o = str_replace('@@',"</li>\n<li>",self::getLog('~~','@@'));
				$o = str_replace("\t",'&mdash;',"<ol{$class}>\n<li>$o</li>\n</ol>\n");
				break;
		}
		echo $o;
	} //printLog()

} //class definition

// end of _Validation.static.php