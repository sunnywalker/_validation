<?php
/**
 * This is a very basic static class for alternate striping with ' class="alt"'.
 *
 * Usage:
 * <tr <?php echo Striper::stripe(); ?>>...</tr>
 * <tr <?php echo Striper::stripe(); ?>>...</tr>
 *
 * If you need to reset the stripe (for a new table, for example), use:
 * <?php Striper::reset(); ?>
 * or
 * <?php Striper::$striped = false; ?>
 *
 * @package Striper
 * @license MIT
 * @author  Sunny Walker <swalker@hawaii.edu>
 * @version 2011-05-11.00
 */
class Striper {
	/**
	 * Class to apply for the stripe
	 * @var string
	 */
	public static $stripe_class = 'alt';

	/**
	 * Current stripe status
	 * @var boolean
	 */
	public static $striped = false;

	/**
	 * Get the stripe class based on the current status and alternate the status.
	 *
	 * @param  string $with_class  Also add this class (regardless of stripe status)
	 * @return string
	 */
	public static function stripe($with_class='') {
		$return = self::$striped ? self::$stripe_class : ''; //striped?
		$return = trim("$return $with_class"); //add additional class(es)
		if ($return!='') {
			$return = ' class="'.$return.'"'; //wrap with class="..." if necessary
		}
		self::$striped = !self::$striped; //alternate the stripe status
		return $return;
	} // stripe()

	/**
	 * Reset the stripe to off.
	 * @return null
	 */
	public static function reset() { self::$striped=false; }

	/**
	 * Reset the stripe to off.
	 *
	 * @return null
	 * @deprecated Deprecated in favor of {@link reset()}
	 */
	public static function resetStripe() { self::reset(); }

} // end of Striper class
