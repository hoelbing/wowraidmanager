<?php
/***************************************************************************
 *                                index.php
 *                            -------------------
 *   begin                : Saturday, Jan 16, 2005
 *   copyright            : (C) 2007-2008 Douglas Wagner
 *   email                : douglasw@wagnerweb.org
 *
 *   $Id: index.php,v 2.00 2008/03/04 17:15:50 psotfx Exp $
 *
 ***************************************************************************/

/***************************************************************************
*
*    WoW Raid Manager - Raid Management Software for World of Warcraft
*    Copyright (C) 2007-2008 Douglas Wagner
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
*    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
****************************************************************************/
// commons
define("IN_PHPRAID", true);	
require_once('./common.php');

// page authentication
if($phpraid_config['anon_view'] == 1)
	define("PAGE_LVL","anonymous");
else
	define("PAGE_LVL","profile");
	
require_once("includes/authentication.php");

/*************************************************************
 * Setup Record Output Information for Data Table
 *************************************************************/
// Set StartRecord for Page
if(!isset($_GET['Base']) || !is_numeric($_GET['Base']))
	$startRecord = 1;
else
	$startRecord = scrub_input($_GET['Base']);

// Set Sort Field for Page
if(!isset($_GET['Sort'])||$_GET['Sort']=='')
{
	$sortField="";
	$initSort=FALSE;
}
else
{
	$sortField = scrub_input($_GET['Sort']);
	$initSort=TRUE;
}
	
// Set Sort Descending Mark
if(!isset($_GET['SortDescending']) || !is_numeric($_GET['SortDescending']))
	$sortDesc = 0;
else
	$sortDesc = scrub_input($_GET['SortDescending']);
	
$pageURL = 'index.php?mode=view&';
/**************************************************************
 * End Record Output Setup for Data Table
 **************************************************************/

// arrays to hold raid information
$current = array();
$previous = array();
$count = array();
$count2 = array();
$raid_loop_cur = 0;
$raid_loop_prev = 0;

$sql = "SELECT * FROM " . $phpraid_config['db_prefix'] . "raids WHERE old = 1 ORDER BY start_time DESC";
$raids_result = $db_raid->sql_query($sql) or $no_old = TRUE;

while($raids = $db_raid->sql_fetchrow($raids_result, true)) 
{
	// Initialize Count Array and Totals.
	foreach ($wrm_global_classes as $global_class)
	{
		$count[$global_class['class_id']]='0';
		$count2[$global_class['class_id']]='0';
	}		
	foreach ($wrm_global_roles as $global_role)
	{	
		$count[$global_role['role_id']]='0';
		$count2[$global_role['role_id']]='0';
	}
	$total = 0;
	$total2 = 0;
	
	//Get Raid Total Counts
	$count = get_char_count($raids['raid_id'], $type='');
	$count2 = get_char_count($raids['raid_id'], $type='queue');		
	foreach ($wrm_global_classes as $global_class)
		$total += $count[$global_class['class_id']];
	foreach ($wrm_global_classes as $global_class)
		$total2 += $count2[$global_class['class_id']];
	
	$logged_in=scrub_input($_SESSION['session_logged_in']);
	$priv_profile=scrub_input($_SESSION['priv_profile']);
	$profile_id=scrub_input($_SESSION['profile_id']);

	// convert unix timestamp to something readable
	$raid_date = get_date($raids['start_time']);
	$raid_start_time = get_time_full($raids['start_time']);
	$raid_invite_time = get_time_full($raids['invite_time']);

	$ddrivetiptxt = get_raid_tooltip($raids['raid_id']);
	$location = '<a href="raid_view.php?mode=view&amp;raid_id='.$raids['raid_id'].'" onMouseover="ddrivetip('.$ddrivetiptxt.');" onMouseout="hideddrivetip();">'.$raids['location'].'</a>';
	
	// Now that we have the raid data, we need to retrieve limit data based upon Raid ID.
	// Get Class Limits and set Colored Counts
	$raid_class_array = array();
	$class_color_count = array();
	$sql = sprintf("SELECT * FROM " . $phpraid_config['db_prefix'] . "raid_class_lmt WHERE raid_id = %s", quote_smart($raids['raid_id']));
	$result_raid_class = $db_raid->sql_query($sql) or print_error($sql, $db_raid->sql_error(), 1);
	while($raid_class_data = $db_raid->sql_fetchrow($result_raid_class, true))
	{
		$raid_class_array[$raid_class_data['class_id']] = $raid_class_data['lmt'];
		if($phpraid_config['class_as_min'])
			$class_color_count[$raid_class_data['class_id']] = get_coloredcount($raid_class_data['class_id'], $count[$raid_class_data['class_id']], $raid_class_array[$raid_class_data['class_id']], $count2[$raid_class_data['class_id']], true);
		else
			$class_color_count[$raid_class_data['class_id']] = get_coloredcount($raid_class_data['class_id'], $count[$raid_class_data['class_id']], $raid_class_array[$raid_class_data['class_id']], $count2[$raid_class_data['class_id']]);
	}
	// Get Role Limits and set Colored Counts
	$raid_role_array = array();
	$role_color_count = array();
	$sql = sprintf("SELECT * FROM " . $phpraid_config['db_prefix'] . "raid_role_lmt WHERE raid_id = %s", quote_smart($raids['raid_id']));
	$result_raid_role = $db_raid->sql_query($sql) or print_error($sql, $db_raid->sql_error(), 1);
	while($raid_role_data = $db_raid->sql_fetchrow($result_raid_role, true))
	{
		$sql2 = sprintf("SELECT role_name FROM " . $phpraid_config['db_prefix'] . "roles WHERE role_id = %s", quote_smart($raid_role_data['role_id']));
		$result_role_name = $db_raid->sql_query($sql2) or print_error($sql2, $db_raid->sql_error(), 1);
		$role_name = $db_raid->sql_fetchrow($result_role_name, true);
		
		$raid_role_array[$role_name['role_name']] = $raid_role_data['lmt'];
		$role_color_count[$role_name['role_name']] = get_coloredcount($role_name['role_name'], $count[$raid_role_data['role_id']], $raid_role_array[$role_name['role_name']], $count2[$raid_role_data['role_id']]);
	}
	
	if($raids['old'] == 1) {
		array_push($previous,
			array(
				'ID'=>$raids['raid_id'],
				//'Signup'=>$info,
				'Force Name'=>$raids['raid_force_name'],
				'Date'=>$raid_date,
				'Dungeon'=>UBB2($location),
				//'Dungeon'=>$raids['location'],
				'Invite Time'=>$raid_invite_time,
				'Start Time'=>$raid_start_time,
				'Creator'=>$raids['officer'],
				'Totals'=>$total.'/'.$raids['max']  . '(+' . $total2. ')',
			)
		);
		foreach ($class_color_count as $left => $right)
			$previous[$raid_loop_prev][$left]= $right;
		foreach ($role_color_count as $left => $right)
			$previous[$raid_loop_prev][$left]= $right;
		$raid_loop_prev++;
	}
}

	/**************************************************************
	 * Code to setup for a Dynamic Table Create: raids1 View.
	 **************************************************************/
	$viewName = 'raids1';
	
	//Setup Columns
	$raid_headers = array();
	$record_count_array = array();
	$raid_headers = getVisibleColumns($viewName);

	//Get Record Counts
	$prev_record_count_array = getRecordCounts($previous, $raid_headers, $startRecord);
	
	//Get the Jump Menu and pass it down
	$prevJumpMenu = getPageNavigation($previous, $startRecord, $pageURL, $sortField, $sortDesc);
			
	//Setup Default Data Sort from Headers Table
	if (!$initSort)
		foreach ($raid_headers as $column_rec)
			if ($column_rec['default_sort'])
				$sortField = $column_rec['column_name'];
	
	//Setup Data
	$previous = paginateSortAndFormat($previous, $sortField, $sortDesc, $startRecord, $viewName);

	/****************************************************************
	 * Data Assign for Template.
	 ****************************************************************/
	$wrmsmarty->assign('old_data', $previous);
	$wrmsmarty->assign('previous_jump_menu', $prevJumpMenu);
	$wrmsmarty->assign('column_name', $raid_headers);
	$wrmsmarty->assign('prev_record_counts', $prev_record_count_array);
	$wrmsmarty->assign('header_data',
		array(
			'template_name'=>$phpraid_config['template'],
			'raidsarchive_header' => $phprlang['raidsarchive_header'],
			'sort_url_base' => $pageURL,
			'sort_descending' => $sortDesc,
			'sort_text' => $phprlang['sort_text'],
		)
	);
	
	//
	// Start output of the page.
	//
	require_once('includes/page_header.php');
	$wrmsmarty->display('raidsarchive.html');
	require_once('includes/page_footer.php');

?>