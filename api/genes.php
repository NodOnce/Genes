<?php
/*!
 * genes.php v2020.02.15
 * (c) 2021 NodOnce OÜ
 * All rights reserved.
 */

// genes is a closure, an array of anonymous functions.
// which can be set or changed any time. anywhere.
// all is set to a private related arrays..
// and that can be called via a single g function
// wrapped inside a simple class.
// ::set sets a value
// ::get gets that value
// ::del deletes the key
// ::def defines a callable function
// ::run runs that callable function
// ::key removes the key of the callable function
// ::log prints the value

class g
{
    // defined static variable used inside
    private static $app = array();
    // defined static variable used inside
    private static $fns = array();
    // these are basic functions to manipulate or use the variables
    public static function set($key, $value)
    {
        $ref = &self::find($key);
        $ref = $value;
    }
    public static function get($key)
    {
        return self::find($key);
    }
    public static function del($key)
    {
        self::find($key, true);
    }
    public static function def($key, $callback)
    {
        $ref = &self::find($key, false, true);
        $ref = $callback;
    }
    public static function ret($key)
    {
        return self::find($key, false, true);
    }
    public static function run()
    {
        $args = func_get_args();
        $key = array_shift($args);
        $ref = &self::find($key, false, true);
        if (is_callable($ref)) {
            return call_user_func_array($ref, $args);
        }
    }
    public static function kill($key)
    {
        self::find($key, true, true);
    }
    public static function log($key)
    {
        if ($key === 0) {
            print_r(self::$app);
        } else if ($key === 1) {
            print_r(self::$fns);
        } else {
            $val = self::find($key);
            if (is_array($val)) {
                print_r($val);
            } else {
                echo $val;
            }
        }
    }
    // Private functions
    private static function &find($key, $remove = false, $fns_mode = false)
    {
        if ($fns_mode) {
            $ref = &static::$fns;
        } else {
            $ref = &static::$app;
        }

        if (strpos($key, ".") > -1) {
            $ps = explode('.', $key);
            $c = count($ps);
            foreach ($ps as $part) {
                $c--;
                if ($remove && $c === 0) {
                    unset($ref[$part]);
                } else {
                    $ref = &$ref[$part];
                }
            }
            return $ref;
        } else {
            if ($remove) {
                unset($ref[$key]);
            } else {
                return $ref[$key];
            }
        }
    }
}

//* CONSTANTS ****************************************************************/
//. Some basic constant settings to begin with .............................../
defined('V') or define('V', DIRECTORY_SEPARATOR);
defined('__DIR__') or define('__DIR__', dirname(__FILE__));
defined('C') or define('C', __DIR__);

defined('GENES_ROOT_FOLDER') or define('GENES_ROOT_FOLDER', dirname(C) . V);
defined('GENES_API_FOLDER') or define('GENES_API_FOLDER', GENES_ROOT_FOLDER . "api" . V);
defined('GENES_CORE_FOLDER') or define('GENES_CORE_FOLDER', GENES_API_FOLDER . "core" . V);
defined('GENES_MODS_FOLDER') or define('GENES_MODS_FOLDER', GENES_API_FOLDER . "mods" . V); // folder: mod folder genes includes during runtime
defined('GENES_UI_FOLDER') or define('GENES_UI_FOLDER', GENES_ROOT_FOLDER . "ui" . V); // folder: frontend for mods theme folder genes includes during runtime
defined('GENES_UI_TMPLS_FOLDER') or define('GENES_UI_TMPLS_FOLDER', GENES_UI_FOLDER . "tmpls" . V . "basic" . V); // folder: frontend for mods theme folder genes includes during runtime
defined('GENES_UI_HTML') or define('GENES_UI_HTML', GENES_UI_TMPLS_FOLDER . "root.html"); // root frontend html for mods theme genes includes during runtime

defined('CLONE_FOLDER') or define('CLONE_FOLDER', getcwd() . V);
defined('CLONE_CACHE_FOLDER') or define('CLONE_CACHE_FOLDER', CLONE_FOLDER . "cache" . V); // folder: clone's cached outputs, reachable via url
defined('CLONE_UI_FOLDER') or define('CLONE_UI_FOLDER', CLONE_FOLDER . "ui" . V); // folder: clone's ui related files, css, js, img
defined('CLONE_UI_HTML') or define('CLONE_UI_HTML', CLONE_UI_FOLDER . "app.html"); // clone frontend html for a genes clone includes during runtime

defined('CLONE_DATA_FOLDER') or define('CLONE_DATA_FOLDER', CLONE_FOLDER . "data" . V); // folder: clone's logs / lang files, not reachable via url
defined('CLONE_LOG_FILE') or define('CLONE_LOG_FILE', CLONE_DATA_FOLDER . "sys.log"); // system log file name
defined('CLONE_CONFIG_FILE') or define('CLONE_CONFIG_FILE', CLONE_DATA_FOLDER . "config.json"); // file: default configuration file.
defined('CLONE_VIEWS_FILE') or define('CLONE_VIEWS_FILE', ""); // file: configuration file part for view and querystring details.
defined('CLONE_MODS_FILE') or define('CLONE_MODS_FILE', ""); // file: configuration file part for view and querystring details.
defined('CLONE_TMPLS_FILE') or define('CLONE_TMPLS_FILE', ""); // file: configuration file part for template related settings.
defined('CLONE_BITS_FILE') or define('CLONE_BITS_FILE', ""); // file: configuration file part for static data.
defined('CLONE_BASE_FILE') or define('CLONE_BASE_FILE', ""); // file: configuration file part for static database.

defined('GENES_UI_URL') or define('GENES_UI_URL', ""); // default :: https://ui.genes.one/
defined('CLONE_UI_URL') or define('CLONE_UI_URL', ""); // folder: clone's ui related url file path, css, js, img

require GENES_CORE_FOLDER . "genes.core.php";
require GENES_CORE_FOLDER . "genes.crypt.php";
require GENES_CORE_FOLDER . "genes.db.php";
require GENES_CORE_FOLDER . "genes.tools.php";
require GENES_CORE_FOLDER . "genes.ui.php";

g::run("core.Init");
