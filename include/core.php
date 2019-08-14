<?php
if (!defined('IN_TRACKER'))
    die('Hacking attempt!');
require $rootpath. 'vendor/autoload.php';
include_once($rootpath . 'classes/class_cache.php'); //Require the caching class
$Cache = NEW CACHE(); //Load the caching class
$Cache->setLanguageFolderArray(get_langfolder_list());
define('TIMENOW', time());
$USERUPDATESET = array();
$query_name = array();

define("UC_PEASANT", 0);
define("UC_USER", 1);
define("UC_POWER_USER", 2);
define("UC_ELITE_USER", 3);
define("UC_CRAZY_USER", 4);
define("UC_INSANE_USER", 5);
define("UC_VETERAN_USER", 6);
define("UC_EXTREME_USER", 7);
define("UC_ULTIMATE_USER", 8);
define("UC_NEXUS_MASTER", 9);
define("UC_VIP", 10);
define("UC_RETIREE", 11);
define("UC_UPLOADER", 12);
//define ("UC_FORUM_MODERATOR", 12);
define("UC_MODERATOR", 13);
define("UC_ADMINISTRATOR", 14);
define("UC_SYSOP", 15);
define("UC_STAFFLEADER", 16);
// production value error_reporting
error_reporting(E_ERROR & ~E_DEPRECATED & ~E_STRICT);
date_default_timezone_set('Asia/Shanghai');
ini_set('display_errors', 1);
ignore_user_abort(1);
@set_time_limit(60);

function get_langfolder_list()
{
    //do not access db for speed up, or for flexibility
    return array("en", "chs", "cht", "ko", "ja");
}
