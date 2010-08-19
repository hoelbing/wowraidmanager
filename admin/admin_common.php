<?php
/***************************************************************************
*                              admin_common.php
*                            -------------------
*   begin                : Monday, May 11, 2009
*   copyright            : (C) 2007-2009 Douglas Wagner
*   email                : douglasw@wagnerweb.org
*
***************************************************************************/

/***************************************************************************
*
*    WoW Raid Manager - Raid Management Software for World of Warcraft
*    Copyright (C) 2007-2009 Douglas Wagner
*
*    This program is free software: you can redistribute it and/or modify
*    it under the terms of the GNU General Public License as published by
*    the Free Software Foundation, either version 3 of the License, or
*    (at your option) any later version.
*
*    This program is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU General Public License for more details.
*
*    You should have received a copy of the GNU General Public License
*
****************************************************************************/

/******************************************
 * Hacking Protection Section 
 ******************************************/
if ( !defined('IN_PHPRAID'))
	print_error("Hacking Attempt", "Invalid access detected", 1);

if(isset($_GET['phpraid_dir']) || isset($_POST['phpraid_dir']))
	die("Hacking attempt detected!");

// force reporting
error_reporting(E_ALL ^ E_NOTICE);

// feel free to set this to absolute if necessary
$phpraid_dir = '../';

//FIX FOR OPEN BASEDIR ISSUES.
// No Win here, some people aren't allowed to include files not listed in the include list, 
//     others aren't able to modify their ini variables with INI set.  End result?  Someone
//     blows up on this code.
// Get list of Includes and Add them to ini_set for include path. 
//     - COMMNET THIS OUT IF YOU HAVE ISSUES WITH INI_SET ON YOUR HOST.
$include_list .= $phpraid_dir . "auth/";
$include_list .= ":" . $phpraid_dir . "db/";
$include_list .= ":" . $phpraid_dir . "includes/";
$include_list .= ":" . ini_get('include_path');
ini_set('include_path', $include_list); 

// Class require_onces
require_once($phpraid_dir.'includes/functions_mbwrapper.php');
require_once($phpraid_dir.'version.php');
require_once($phpraid_dir.'config.php');
require_once($phpraid_dir.'includes/functions_mysql.php');
require_once($phpraid_dir.'includes/functions_auth.php');
require_once($phpraid_dir.'includes/functions.php');
require_once($phpraid_dir.'includes/functions_date.php');
require_once($phpraid_dir.'includes/functions_logging.php');
require_once($phpraid_dir.'includes/functions_tables.php');
require_once($phpraid_dir.'includes/functions_users.php');
require_once($phpraid_dir.'includes/ubb.php');

/************************************************
 * Database Connection and phpraid_config Load
 ************************************************/
// database connection
global $db_raid, $errorTitle, $errorMsg, $errorDie;
if ($phpraid_config['persistent_db'] == TRUE)
	$db_raid = &new sql_db($phpraid_config['db_host'],$phpraid_config['db_user'],$phpraid_config['db_pass'],$phpraid_config['db_name'],TRUE,TRUE);
else
	$db_raid = &new sql_db($phpraid_config['db_host'],$phpraid_config['db_user'],$phpraid_config['db_pass'],$phpraid_config['db_name'],TRUE,FALSE);

if(!$db_raid->db_connect_id)
{
	die('<div align="center"><strong>There appears to be a problem with the database server.<br>We should be back up shortly.</strong></div>');
}

// UTF8 Oh how I hate you. - This code SHOULD force a UTF8 Connection between client and server.
//   From this point on, everything sent from the client to the server or returned from
//     the server to the client should now be multi-byte aware.
$sql = "SET NAMES 'utf8'";
$result = $db_raid->sql_query($sql) or print_error($sql, mysql_error(), 1);
$sql = "SET CHARACTER SET 'utf8'";
$result = $db_raid->sql_query($sql) or print_error($sql, mysql_error(), 1);

// unset database password for security reasons
// we won't use it after this point
unset($phpraid_config['db_pass']);

//
// Populate the $phpraid_config array
//
$sql = "SELECT * FROM " . $phpraid_config['db_prefix'] . "config";
$result = $db_raid->sql_query($sql) or print_error($sql, mysql_error(), 1);
while($data = $db_raid->sql_fetchrow($result, true))
{
	$phpraid_config["{$data['0']}"] = $data['1'];
}

/**********************************************************
 * Load Template System Here (Smarty)
 **********************************************************/
//Load Smarty Library
$curr_dir = dirname(__FILE__);
$dir_split_var = array();
preg_match('"(.*)admin"', $curr_dir, $dir_split_var);
$wrm_dir = $dir_split_var[1];
define('SMARTY_DIR', $wrm_dir . '/includes/smarty/libs/');
require(SMARTY_DIR . 'Smarty.class.php');

$wrmadminsmarty = new Smarty();
$wrmadminsmarty->template_dir = $wrm_dir . 'templates/' . $phpraid_config['template'] . '/admin/';
$wrmadminsmarty->compile_dir  = $wrm_dir . 'cache/templates_c/admin/';
$wrmadminsmarty->config_dir   = $wrm_dir . 'includes/smarty/configs/';
$wrmadminsmarty->cache_dir    = $wrm_dir . 'cache/smarty_cache/admin/';
// Turning on Caching will cause many pages not to display dynamic changes properly.
$wrmadminsmarty->caching = false;
$wrmadminsmarty->compile_check = true;
/* Turn on/off Smarty Template Debugging by commenting/uncommenting the lines below. */
//$wrmadminsmarty->debugging = false;
$wrmadminsmarty->debugging = true;

/***************************************************
 * Load Language Files
 ***************************************************/
//FIX FOR OPEN BASEDIR ISSUES.
// No Win here, some people aren't allowed to include files not listed in the include list, 
//     others aren't able to modify their ini variables with INI set.  End result?  Someone
//     blows up on this code.
// Setup the Include for the Language Files.
//     - COMMNET THIS OUT IF YOU HAVE ISSUES WITH INI_SET ON YOUR HOST.
$include_list = $phpraid_dir . "language/lang_" . $phpraid_config['language'] . "/";
$include_list .= ":" . ini_get('include_path');
ini_set('include_path', $include_list); 

// Include Language Files.
if(!is_file($phpraid_dir."language/lang_{$phpraid_config['language']}/lang_main.php"))
{
	die("The language file <i>" . $phpraid_config['language'] . "</i> could not be found!");
	$db_raid->sql_close();
}
else
{
	require_once($phpraid_dir."language/lang_{$phpraid_config['language']}/lang_main.php");
}

/***************************************************
 * Set Authentication Method and Load Auth Files
 ***************************************************/
// get auth type
require_once($phpraid_dir.'auth/auth_' . $phpraid_config['auth_type'] . '.php');
get_permissions();

/****************************************************
 * Maintenance Flag Disable Site
 ****************************************************/
//if($phpraid_config['disable'] == 1 && $_SESSION['priv_configuration'] == 0)
//{
//	$errorTitle = $phprlang['maintenance_header'];
//	$errorMsg = $phprlang['maintenance_message'];
//	$errorDie = 1;
//}

?>