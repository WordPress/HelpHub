<?php
/**
 * Gets the view state of UI elements to remember its viewable state
 * 
 * Standard: PSR-2
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package Duplicator
 * @subpackage classes/ui
 * @copyright (c) 2017, Snapcreek LLC
 * @since 1.1.0
 * 
 */

// Exit if accessed directly
if (!defined('DUPLICATOR_VERSION')) {
    exit;
}

class DUP_UI_ViewState
{
    /**
     * The key used in the wp_options table
     *
     * @var string 
     */
    private static $optionsViewStateKey = 'duplicator_ui_view_state';

    /**
     * Save the view state of UI elements
     *
     * @param string $key A unique key to define the ui element
     * @param string $value A generic value to use for the view state
     *
     * @return bool Returns true if the value was succesfully saved
     */
    public static function save($key, $value)
    {
        $view_state       = array();
        $view_state       = get_option(self::$optionsViewStateKey);
        $view_state[$key] = $value;
        $success          = update_option(self::$optionsViewStateKey, $view_state);
        return $success;
    }

    /**
     * 	Gets all the values from the settings array
     * 
     *  @return array Returns and array of all the values stored in the settings array
     */
    public static function getArray()
    {
        return get_option(self::$optionsViewStateKey);
    }

    /**
     * Return the value of the of view state item
     * 
     * @param type $searchKey The key to search on
     * @return string Returns the value of the key searched or null if key is not found
     */
    public static function getValue($searchKey)
    {
        $view_state = get_option(self::$optionsViewStateKey);
        if (is_array($view_state)) {
            foreach ($view_state as $key => $value) {
                if ($key == $searchKey) {
                    return $value;
                }
            }
        }
        return null;
    }
}