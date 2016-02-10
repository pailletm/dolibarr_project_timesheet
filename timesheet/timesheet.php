<?php
/* Copyright (C) 2007-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2014 delcroip <delcroip@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *   	\file       dev/skeletons/skeleton_page.php
 *		\ingroup    mymodule othermodule1 othermodule2
 *		\brief      This file is an example of a php page
 *					Put here some comments
 */


// hide left menu
//$_POST['dol_hide_leftmenu']=1;
// Change this following line to use the correct relative path (../, ../../, etc)
include 'lib/includeMain.lib.php';

//to get the form funciton
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
//to get the timesheet lib
require_once 'lib/timesheet.lib.php';


$action             = GETPOST('action');
$yearWeek           = GETPOST('yearweek');
//should return the XMLDoc
$ajax               = GETPOST('ajax');
$xml               = GETPOST('xml');
$optioncss = GETPOST('optioncss','alpha');


//$toDate                 = GETPOST('toDate');
$toDate                 = GETPOST('toDate');
if (!empty($toDate) && $action=='goToDate')
{
    $yearWeek =date('Y\WW',strtotime(str_replace('/', '-',$toDate)));  
    $_SESSION["yearWeek"]=$yearWeek ;      
}else if ($yearWeek!=0) {
        $_SESSION["yearWeek"]=$yearWeek;
}else if(isset($_SESSION["yearWeek"])){
        $yearWeek=$_SESSION["yearWeek"];
}else
{
        $yearWeek=date('Y\WW');
        $_SESSION["yearWeek"]=$yearWeek;
}
$timestamp=GETPOST('timestamp');
$whitelistmode=GETPOST('wlm','int');
if(!is_numeric($whitelistmode))$whitelistmode=TIMESHEET_WHITELIST_MODE;
$userid=  is_object($user)?$user->id:$user;



// Load traductions files requiredby by page
//$langs->load("companies");
$langs->load("main");
$langs->load("projects");
$langs->load('timesheet@timesheet');

/*
// Get parameters

$id			= GETPOST('id','int');
$action		= GETPOST('action','alpha');
$myparam	= GETPOST('myparam','alpha');

// Protection if external user
if ($user->societe_id > 0)
{
	//accessforbidden();
}

*/

/*******************************************************************
* ACTIONS
*
* Put here all code to do according to value of "action" parameter
********************************************************************/
if($action== 'submit'){
        if (isset($_SESSION['timestamps'][$timestamp]))
        {
            if (!empty($_POST['task']))
            {
				$ret =postActuals($db,$user,$_POST['task'],$timestamp);
                    if($ret>0)
                    {
                        if($_SESSION['timeSpendCreated'])setEventMessage($langs->transnoentitiesnoconv("NumberOfTimeSpendCreated").$_SESSION['timeSpendCreated']);
                        if($_SESSION['timeSpendModified'])setEventMessage($langs->transnoentitiesnoconv("NumberOfTimeSpendModified").$_SESSION['timeSpendModified']);
                        if($_SESSION['timeSpendDeleted'])setEventMessage($langs->transnoentitiesnoconv("NumberOfTimeSpendDeleted").$_SESSION['timeSpendDeleted']);
                    }
                    else
                    {
                        if($ret==0){
                            setEventMessage($langs->transnoentitiesnoconv("NothingChanged"),'errors');
                        }else {
                            setEventMessage( $langs->transnoentitiesnoconv("InternalError").':'.$ret,'errors');
                        }
                    }        
            }else
                    setEventMessage( $langs->transnoentitiesnoconv("NoTaskToUpdate"),'errors');
        }else
                setEventMessage( $langs->transnoentitiesnoconv("InternalError"),'errors');
	

}


if(!empty($timestamp)){
       unset($_SESSION["timestamps"][$timestamp]);
}

/***************************************************
* VIEW
*
* Put here all code to build page
****************************************************/
if($xml){
    //renew timestqmp
   header("Content-type: text/xml; charset=utf-8");
    echo GetTimeSheetXML($userid,$yearWeek,$whitelistmode);
    exit;
}
$morejs=array("/timesheet/js/timesheetAjax.js","/timesheet/js/timesheet.js");
llxHeader('',$langs->trans('Timesheet'),'','','','',$morejs);
//calculate the week days

$tmstp=time();

 $form = new Form($db);

// navigation form 	
 $Form ='<div id="navigation">';
print pintNavigationHeader($yearWeek,$whitelistmode,$optioncss,$form);
$Form .='</div>';

//timesheet
$Form .='<form id="timesheetForm" name="timesheet" action="?action=submit&wlm='.$whitelistmode.'" method="POST"  onsubmit=" return submitTimesheet(0);">'; 
$Form .="\n<table id=\"timesheetTable\" class=\"noborder\" width=\"100%\">\n";
//headers

$headers=explode('||', TIMESHEET_HEADERS);
//$headersWidth=explode('||', TIMESHEET_HEADERS_WIDTH);
$headersWidth=explode('||', '');
for ($i=0;$i<7;$i++)
{
   $weekDays[$i]=date('d-m-Y',strtotime( $yearWeek.' +'.$i.' day'));
}
$_SESSION["timestamps"][$tmstp]["YearWeek"]=$yearWeek;
$_SESSION["timestamps"][$tmstp]["weekDays"]=$weekDays;
$Form .=timesheetHeader($headers,$headersWidth, $yearWeek, $tmstp );

//total top

$num=count($headers);
$Form .="<tr id='totalT'>\n";
$Form .='<th colspan="'.$num.'" align="right" > TOTAL </th>';
for ($i=0;$i<7;$i++)
{
   $Form .='<th><div id="totalDay['.$i.']">&nbsp;</div></th>';
 }
$Form .='</tr>';

//line


//$Form .=timesheetList($db,$headers,$userid,$yearWeek,$tmstp,$whitelistmode);

//total Bot
$Form .="<tr id='totalB'>\n";
$Form .='<th colspan="'.$num.'" align="right" > TOTAL </th>';
for ($i=0;$i<7;$i++)
{
   $Form .='<th><div id="totalDayb['.$i.']">&nbsp;</div></th>';
 }
$Form .='</tr>';
$Form .="</table >\n";

//form button
$Form .= '<input type="submit" value="'.$langs->trans('Save')."\" />\n";
//$Form .= '<input type="button" value="'.$langs->trans('Submit');
//$Form .= '" onClick="document.location.href=\'?action=submit&yearweek='.$yearWeek."'\"/>\n"; /*FIXME*/
$Form .= '<input type="button" value="'.$langs->trans('Cancel');
$Form .= '" onClick="document.location.href=\'?action=list&yearweek='.$yearWeek."'\"/>\n";
$Form .= "</form>\n";
//Javascript
$timetype=TIMESHEET_TIME_TYPE;
//$Form .= ' <script type="text/javascript" src="timesheet.js"></script>'."\n";
$Form .= '<script type="text/javascript">'."\n\t";

$Form .='updateAll(\''.$timetype.'\');';

$Form .='loadXMLTimesheet("'.$yearWeek.'",'.$userid.');';
$Form .= "\n\t".'</script>'."\n";

print $Form;
$Params ='<param id="nextWeek" value="'.$langs->trans("NextWeek").'">';
$Params .='<param id="prevWeek" value="'.$langs->trans("PreviousWeek").'">';

//$Params .='<param id="nextWeek" value="'.$langs->trans("NextWeek").'">';
print $Params;
// End of page
llxFooter();
$db->close();
?>
