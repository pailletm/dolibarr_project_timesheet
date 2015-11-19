<?php
/*
 * Copyright (C) 2014	   Patrick DELCROIX     <pmpdelcroix@gmail.com>
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
//global $db;     
global $langs;
// to get the whitlist object
require_once 'class/timesheetwhitelist.class.php';
require_once 'class/timesheet.class.php';

function get_subordinate($db,$userid, $depth=5,$ecludeduserid=array(),$entity='1'){
    if($userid=="")
    {
        return array();
    }
    
    $sql="SELECT usr.rowid FROM ".MAIN_DB_PREFIX.'user AS usr WHERE';
    if(is_array($userid)){
        $ecludeduserid=array_merge($userid,$ecludeduserid);
        $sql.=' usr.fk_user in (';
        foreach($userid as $id)
        {
            $sql.='"'.$id.'",';
        }
        $sql.='-999)';
    }else{
        $ecludeduserid[]=$userid;
        $sql.=' usr.fk_user ="'.$userid.'"';
    }
    if(is_array($ecludeduserid)){
        $sql.=' AND usr.rowid not in (';
        foreach($ecludeduserid as $id)
        {
            $sql.='"'.$id.'",';
        }
        $sql.='0)';
    }else if (!empty($ecludeduserid)){
        $sql.=' AND usr.rowid <>"'.$ecludeduserid.'"';
    } 
       
    dol_syslog("form::get_subordinate sql=".$sql, LOG_DEBUG);
    $list=array();
    $resql=$db->query($sql);
    
    if ($resql)
    {
        $i=0;
        $num = $db->num_rows($resql);
        while ( $i<$num)
        {
            $obj = $db->fetch_object($resql);
            
            if ($obj)
            {
                $list[]=$obj->rowid;        
            }
            $i++;
        }
        if(count($list)>0 && $depth>1){
            //this will get the same result plus the subordinate of the subordinate
            $result=get_subordinate($db,$list,$depth-1,$ecludeduserid, $entity);
            if(is_array($result))
            {
                $list=array_merge($list,$result);
            }
        }
        if(is_array($userid))
        {
            
            $list=array_merge($list,$userid);
        }else
        {
            $list[]=$userid;
        }
        
    }
    else
    {
        $error++;
        dol_print_error($db);
        $list= array();
    }
      //$select.="\n";
      return $list;
 }

 
 /*
 * function to genegate the timesheet table header
 * 
  *  @param    array(string)           $headers            array of the header to show
 *  @param    array(int)              	$headersWidth    array defining the header width
 *  @param     int              	$yearWeek           timesheetweek
  *  @return     string                                                   html code
 */
 function timesheetHeader($headers,$headersWidth , $weekDays){
     global $langs;
     if(!is_array($weekDays )){
            setEventMessage($langs->trans("InternalError2"),'errors');
            return '';
     }

     $html='<tr class="liste_titre" >'."\n";
     
     foreach ($headers as $key => $value){
         $html.="\t<th ";
         if ($headersWidth[$key]){
                $html.='width="'.$headersWidth[$key].'"';
         }
         $html.=">".$langs->trans($value)."</th>\n";
     }
    
    for ($i=0;$i<7;$i++)
    {
         $html.="\t".'<th width="60px">'.$langs->trans(date('l',strtotime($weekDays[$i]))).'<br>'.date('d/m/y',strtotime($weekDays[$i]))."</th>\n";
    }

     
     $html.="</tr>\n";
     return $html;
     
 }
 
  /*
 * function to genegate the timesheet list
 * 
 *  @param    object             	$db                 db Object to do the querry
 *  @param    array(string)           $headers            array of the header to show
 *  @param    int              	$user                   user id to fetch the timesheets
 *  @param     int              	$yearWeek           timesheetweek
 *  @param    array(int)              	$whiteList    array defining the header width
 *  @param     int              	$timestamp         timestamp
 *  @param     int              	$whitelistemode           0-whiteliste,1-blackliste,2-non impact
 *  @return     string                                                   html code
 */
 function timesheetList($db,$headers,$userid,$yearWeek,$timestamp,$whitelistmode=0){
        $Lines='';
        //FIXME unset timestamp
        $staticTimesheet=New timesheet($db,0);
        
        $tab=$staticTimesheet->timesheetTab($headers,$userid,$yearWeek,$timestamp);
        $i=0;
        foreach ($tab as $timesheet) {
            $row=unserialize($timesheet);
            $Lines.=$row->getFormLine( $yearWeek,$i,$headers,$whitelistmode);
            $_SESSION["timestamps"][$timestamp]['tasks'][$row->id]=  $timesheet;
            //$_SESSION["timestamps"][$timestamp]['tasks'][$row->id]=$row->getTaskTab(); 
            $i++;
        }
        $Lines .= '<tr style="display:none"><td><input type="hidden" name="timestamp" value="'.$timestamp."\"/>\n";
        $Lines .= '<input type="hidden" id="numberOfLines" name="numberOfLines" value="'.count($tab)."\"/>\n";
        $Lines .= '<input type="hidden" id="tsApproval" name="tsApproval" value="0'."\"/>\n";
        $Lines .= '<input type="hidden" name="yearWeek" value="'.$yearWeek.'" /></td></tr>'; 
        return $Lines;
 }    
  /*
 * function to post the all actual submitted
 * 
 *  @param    object             	$db                      db Object to do the querry
 *  @param    int              	$user                   user id to fetch the timesheets
 *  @param    array(int)              	$tabPost               array sent by POST with all info about the task
 *  @param     int              	$timestamp          timestamp
 *  @return     int                                                        number of tasktime creatd/changed
 */
 function postActuals($db,$user,$tabPost,$timestamp,$tsAproval)
{

    $ret=0;
    $tmpRet=0;
    $_SESSION['timeSpendCreated']=0;
    $_SESSION['timeSpendDeleted']=0;
    $_SESSION['timeSpendModified']=0;
        /*
         * For each task store in matching the session timestamp
         */
    $userid=  is_object($user)?$user->id:$user;
  foreach($_SESSION["timestamps"][$timestamp]['tasks'] as  $taskId => $serializedtaskItem)
    {
        if(isset($tabPost[$taskId])){
            $taskItem= unserialize($serializedtaskItem);
            $taskItem->db=$db;
          //  $taskId=$taskItem["id"];

            $ret+=$taskItem->updateTimesheet($user,$tabPost[$taskId],$tsAproval);
            if($ret!=$tmpRet){ // something changed so need to updae the total duration
                $taskItem->updateTimeUsed();
            }
            $tmpRet=$ret;
        }
    } 
    unset($_SESSION["timestamps"][$timestamp]);
    return $ret;
}



/*
 * function to post on task_time
 * 
 *  @param    object              	$db                  database object
 *  @param    int                       $userid              timesheet object, (task)
 *  @param    string              	$yearWeek            year week like 2015W09
 *  @param     int              	$whitelistmode        whitelist mode, shows favoite o not 0-whiteliste,1-blackliste,2-non impact
 *  @return     string                                         XML result containing the timesheet info
 */
function GetTimeSheetXML($userid,$yearWeek,$whitelistmode)
{
    global $langs;
    global $db;
    
    $xml = '<?xml version="1.0" encoding="ISO-8859-1" ?>'."\n";
    $timestamp=time();
    
    
    
    $xml.= "<timesheet yearweek=\"{$yearWeek}\" timestamp=\"{$timestamp}\" >\n";
    $headers=explode('||', TIMESHEET_HEADERS);
    //header
    $i=0;
    $xmlheaders='';      
    foreach($headers as $header){
        $xmlheaders.= "\t\t<header col=\"{$i}\" name=\"{$header}\" link=\"FIXME\">{$langs->trans($header)}</header>\n";;
        $i++;
    }
    $xml.= "\t<headers>\n{$xmlheaders}\t</headers>\n";
        //days
    $xmldays='';
    for ($i=0;$i<7;$i++)
    {
       $weekDays[$i]=date('d-m-Y',strtotime( $yearWeek.' +'.$i.' day'));
       $xmldays.="\t\t<day col=\"{$i}\">{$weekDays[$i]}</day>\n";
    }
    $xml.= "\t<days>\n{$xmldays}\t</days>\n";
        //FIXME unset timestamp
    $staticTimesheet=New timesheet($db,0);
    $tab=$staticTimesheet->timesheetTab($headers,$userid,$yearWeek,$timestamp,$whitelistmode);
    $i=0;
    $xml.="\t<tasks count=\"".count($tab)."\">\n";
    foreach ($tab as $timesheet) {
        $row=unserialize($timesheet);
        $xml.= $row->getXML($yearWeek,$i);//FIXME
       // $Lines.=$row->getFormLine( $yearWeek,$i,$headers);
        //$_SESSION["timestamps"][$timestamp]['tasks'][$row->id]=array();
        $_SESSION["timestamps"][$timestamp]['tasks'][$row->id]=  $timesheet;
        //$_SESSION["timestamps"][$timestamp]['tasks'][$row->id]=$row->getTaskTab(); 
        $i++;
    }  
    $xml.="\t</tasks>\n</timesheet>\n";
   





    
    return $xml;

}



if (!is_callable(setEventMessages)){
    // function from /htdocs/core/lib/function.lib.php in Dolibarr 3.8
    function setEventMessages($mesg, $mesgs, $style='mesgs')
    {
            if (! in_array((string) $style, array('mesgs','warnings','errors'))) dol_print_error('','Bad parameter for setEventMessage');
            if (empty($mesgs)) setEventMessage($mesg, $style);
            else
            {
                    if (! empty($mesg) && ! in_array($mesg, $mesgs)) setEventMessage($mesg, $style);	// Add message string if not already into array
                    setEventMessage($mesgs, $style);

            }
    }
}
