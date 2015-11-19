<?php
/* 
 * Copyright (C) 2014 delcroip <delcroip@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
define('TIMESHEET_COLOR_PENDING', '#f0f0ff');
define('TIMESHEET_COLOR_APPROVED', '#f0fff0');
define('TIMESHEET_COLOR_REJECTED', '#ff0000');
define('TIMESHEET_COLOR_ERROR', '#f0ffff');

/*Class to handle a line of timesheet*/
#require_once('mysql.class.php');
require_once DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php";
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
//dol_include_once('/timesheet/class/projectTimesheet.class.php');
//require_once './projectTimesheet.class.php';
class tasktime
{
    var $id=0;
    var $date;
    var $hour;
    var $duration=0;
    //var $status;
//    var $fk_user_approuver;
        public function __construct($taskId) 
	{
		$this->id=$taskId;
	}
}


class timesheet extends Task 
{
        private $ProjectTitle		=	"Not defined";
        private $status = NULL;
        private $yearWeek;
        //private $taskTimeId = array(0=>0,0,0,0,0,0,0);
        var $taskTimeList=array();
        //private $weekWorkLoad  = array(0=>0,0,0,0,0,0,0);
        private $fk_project2;
        private $taskParentDesc;
        private $companyName;
        private $companyId;
        private $hidden; // in the whitelist 
	

    public function __construct($db,$taskId) 
	{
		$this->db=$db;
		$this->id=$taskId;
		$this->date_end=strtotime('now -1 year');
		$this->date_start=strtotime('now -1 year');
	}

        /*public function initTimeSheet($weekWorkLoad,$taskTimeId) 
    {
            $this->weekWorkLoad=$weekWorkLoad;
            $this->taskTimeId=$taskTimeId;

    }*/
    public function getTaskInfo()
    {
        $Company=strpos(TIMESHEET_HEADERS, 'Company')===0;
        $taskParent=strpos(TIMESHEET_HEADERS, 'TaskParent')>0;
        $sql ='SELECT p.rowid,pt.dateo,pt.datee, pt.planned_workload, pt.duration_effective';
        if(TIMESHEET_HIDE_REF==1){
            $sql .= ',p.title as title, pt.label as label';
            if($taskParent)$sql .= ',pt.fk_task_parent,ptp.label as taskParentLabel';	        	
        }else{
            $sql .= ",CONCAT(p.`ref`,' - ',p.title) as title";
            $sql .= ",CONCAT(pt.`ref`,' - ',pt.label) as label";
            if($taskParent)$sql .= ",pt.fk_task_parent,CONCAT(ptp.`ref`,' - ',ptp.label) as taskParentLabel";	
        }
        if($Company)$sql .= ',p.fk_soc as companyId,s.nom as companyName';

        $sql .=" FROM ".MAIN_DB_PREFIX."projet_task AS pt";
        $sql .=" LEFT JOIN ".MAIN_DB_PREFIX."projet as p";
        $sql .=" ON pt.fk_projet=p.rowid";
        if($taskParent){
            $sql .=" LEFT JOIN ".MAIN_DB_PREFIX."projet_task as ptp";
            $sql .=" ON pt.fk_task_parent=ptp.rowid";
        }
        if($Company){
            $sql .=" LEFT JOIN ".MAIN_DB_PREFIX."societe as s";
            $sql .=" ON p.fk_soc=s.rowid";
        }
        $sql .=" WHERE pt.rowid ='".$this->id."'";
        #$sql .= "WHERE pt.rowid ='1'";
        dol_syslog(get_class($this)."::fetchtasks sql=".$sql, LOG_DEBUG);


        $resql=$this->db->query($sql);
        if ($resql)
        {

                if ($this->db->num_rows($resql))
                {

                        $obj = $this->db->fetch_object($resql);

                        $this->description			= $obj->label;
                        $this->fk_project2                      = $obj->rowid;
                        $this->ProjectTitle			= $obj->title;
                        #$this->date_start			= strtotime($obj->dateo.' +0 day');
                        #$this->date_end			= strtotime($obj->datee.' +0 day');
                        $this->date_start			= $this->db->jdate($obj->dateo);
                        $this->date_end			= $this->db->jdate($obj->datee);
                        $this->duration_effective           = $obj->duration_effective;		// total of time spent on this task
                        $this->planned_workload             = $obj->planned_workload;
                        if($taskParent){
                            $this->fk_task_parent               = $obj->fk_task_parent;
                            $this->taskParentDesc               =$obj->taskParentLabel;
                        }
                        if($Company){
                            $this->companyName                  =$obj->companyName;
                            $this->companyId                    =$obj->companyId;
                        }
                }
                $this->db->free($resql);
                return 1;
        }
        else
        {
                $this->error="Error ".$this->db->lasterror();
                dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);

                return -1;
        }	
    }
 /*
    * function to check the approval status of a ts
    * 
     *  @return     string     Status of the   
    */ 

    public function getApprovalSatus(){
        $this->status=NULL;
        $sql='SELECT status';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'timesheet_approval';
        $sql .= ' WHERE fk_user="'.$this->timespent_fk_user.'"';
        $sql .= ' AND fk_project_task="'.$this->id.'"';
        $sql .= ' AND yearweek="'.str_replace('W', '', $this->yearWeek).'"';
        $resql=$this->db->query($sql);
        if ($resql)
        {

                $num = $this->db->num_rows($resql);
                if($num)
                {
                        $error=0;
                        $obj = $this->db->fetch_object($resql);
                        $this->status=$obj->status;
                }
                    
                
                $this->db->free($resql);
                return 1;
         }
        else
        {
                $this->error="Error ".$this->db->lasterror();
                dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);

                return -1;
        }
    }
    /*
    * function to send a request for approval status of a ts
    * 
     *  @return     string     Status of the   
    */ 

    public function askApproval(){
        if(($this->status==NULL ||$this->status== 'REJECTED' )){
            $sql='INSERT INTO '.MAIN_DB_PREFIX.'timesheet_approval';
            $sql .= ' (fk_user,fk_project_task,status,yearweek) ';
            $sql .= ' VALUES ('.$this->timespent_fk_user.','.$this->id.',\'PENDING\',\''.str_replace('W', '', $this->yearWeek).'\')';
            $resql=$this->db->query($sql);
            if ($resql)
            {

                    return 1;
             }
            else
            {
                    $this->error="Error ".$this->db->lasterror();
                    dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);

                    return -1;
            }
        }else
            return -1;
    }
      public function getActuals( $yearWeek,$userid)
    {
        $this->taskTimeList=array();
        $this->yearWeek=$yearWeek;
        $this->timespent_fk_user=$userid;
        $this->getApprovalSatus();
        for($i=0;$i<7;$i++){
            $this->taskTimeList[$i]=new tasktime(0);
        }
               
        $sql = 'SELECT ptt.rowid, ptt.task_duration, ptt.task_date';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'projet_task_time AS ptt';
        $sql .= " WHERE ptt.fk_task='".$this->id."' ";
        $sql .= " AND (ptt.fk_user='".$userid."') ";
       # $sql .= "AND WEEKOFYEAR(ptt.task_date)='".date('W',strtotime($yearWeek))."';";
        #$sql .= "AND YEAR(ptt.task_date)='".date('Y',strtotime($yearWeek))."';";
        $sql .= " AND (ptt.task_date>=FROM_UNIXTIME('".strtotime($yearWeek)."')) ";
        $sql .= " AND (ptt.task_date<FROM_UNIXTIME('".strtotime($yearWeek.' + 7 days')."'))";

        dol_syslog(get_class($this)."::fetchActuals sql=".$sql, LOG_DEBUG);
        
        $resql=$this->db->query($sql);
        if ($resql)
        {

                $num = $this->db->num_rows($resql);
                $i = 0;
                // Loop on each record found, so each couple (project id, task id)
                 while ($i < $num)
                {
                        $error=0;
                        $obj = $this->db->fetch_object($resql);
                        $day=intval(date('N',strtotime($obj->task_date)))-1;
                        //$day=(intval(date('w',strtotime($obj->task_date)))+1)%6;
                        // if several tasktime in one day then only the last is used
                            
                        $this->taskTimeList[$day]->id=$obj->rowid;
                        $this->taskTimeList[$day]->duration=  $obj->task_duration;
                        $this->taskTimeList[$day]->status=$obj->status;
                        //$this->taskTimeList[$day]->date=$->task_date;
                        $i++;
                }
                
                $this->db->free($resql);
                return 1;
         }
        else
        {
                $this->error="Error ".$this->db->lasterror();
                dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);

                return -1;
        }
    }	 
    
    
    
 /*
 * function to form a HTMLform line for this timesheet
 * 
 *  @param    string              	$yearWeek            year week like 2015W09
 *  @param     int              	$line number         used in the form processing
 *  @param    string              	$headers             header to shows
 *  @param     int              	$whitelistemode           0-whiteliste,1-blackliste,2-non impact
 *  @return     string                                        HTML result containing the timesheet info
 */
       public function getFormLine( $yearWeek,$lineNumber,$headers,$whitelistemode)
    {
       if(empty($yearWeek)||empty($headers))
           return '<tr>ERROR: wrong parameters for getFormLine'.empty($yearWeek).'|'.empty($headers).'</tr>';
        
    $timetype=TIMESHEET_TIME_TYPE;
    $dayshours=TIMESHEET_DAY_DURATION;
    $hidezeros=TIMESHEET_HIDE_ZEROS;
    $hidden=(($whitelistemode==0 && !$this->listed)||($whitelistemode==1 && $this->listed));
    
    if(!$hidden){
        $html= '<tr class="'.(($lineNumber%2=='0')?'pair':'impair').'">'."\n"; 
        //title section
         foreach ($headers as $key => $title){
             $html.="\t<th align=\"left\">";
             switch($title){
                 case 'Project':
                     if(file_exists("../projet/card.php")||file_exists("../../projet/card.php")){
                        $html.='<a href="'.DOL_URL_ROOT.'/projet/card.php?id='.$this->fk_project2.'">'.$this->ProjectTitle.'</a>';
                     }else{
                        $html.='<a href="'.DOL_URL_ROOT.'/projet/fiche.php?id='.$this->fk_project2.'">'.$this->ProjectTitle.'</a>';

                     }
                     break;
                 case 'TaskParent':
                     $html.='<a href="'.DOL_URL_ROOT.'/projet/tasks/task.php?id='.$this->fk_task_parent.'&withproject='.$this->fk_project2.'">'.$this->taskParentDesc.'</a>';
                     break;
                 case 'Tasks':
                     $html.='<a href="'.DOL_URL_ROOT.'/projet/tasks/task.php?id='.$this->id.'&withproject='.$this->fk_project2.'">'.$this->description.'</a>';
                     break;
                 case 'DateStart':
                     $html.=$this->date_start?date('d/m/y',$this->date_start):'';
                     break;
                 case 'DateEnd':
                     $html.=$this->date_end?date('d/m/y',$this->date_end):'';
                     break;
                 case 'Company':
                     $html.='<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$this->companyId.'">'.$this->companyName.'</a>';
                     break;
                 case 'Progress':
                     $html .=$this->parseTaskTime($this->duration_effective).'/';
                    if($this->planned_workload)
                    {
                         $html .= $this->parseTaskTime($this->planned_workload).'('.floor($this->duration_effective/$this->planned_workload*100).'%)';
                    }else{
                        $html .= "-:--(-%)";
                    }
                     break;
             }

             $html.="</th>\n";
         }
    }
    
  // day section
        foreach ($this->taskTimeList  as $dayOfWeek => $taskTime)
        {
                $color='';
                $today= strtotime($this->yearWeek.' +'.($dayOfWeek).' day  ');
                $isOpened=((empty($this->date_start) || ($this->date_start <= $today +86399)) && (empty($this->date_end) ||($this->date_end >= $today )));
                # to avoid editing if the task is closed 
                
                if ($timetype=="days")
                {
                    $dayWorkLoad=$taskTime->duration/3600/$dayshours;
                }else {
                    $dayWorkLoad=date('H:i',mktime(0,0,$taskTime->duration));
                }
                if($this->status=='REJECTED'){
                    $color=' background:'.TIMESHEET_COLOR_REJECTED.'; ';
                }else if($this->status=='APPROVED'){
                    $color=' background:'.TIMESHEET_COLOR_APPROVED.'; ';
                    $isOpened=false;
                }else if($this->status=='PENDING'){
                    $color=' background:'.TIMESHEET_COLOR_PENDING.'; ';
                    $isOpened=false;
                }else if($this->duration!=0){
                    $color=' background:#00ffff; ';
                }

                //if($hidden){
                //    $html .= ' <input type="hidden" id="task['.$lineNumber.']['.$dayOfWeek.']" value="'.$dayWorkLoad.'" ';
                 //   $html .= 'name="task['.$this->id.']['.$dayOfWeek.']" />'."\n";
                //}else //if($isOpened)
                //{             
                    $html .= '<th '.(($hidden)?'style="display:none"':'');
                    $html .='><input type="text" id="task['.$lineNumber.']['.$dayOfWeek.']"';
                    if(!$isOpened)$html .=' disabled="disabled"';
                    $html .= ' name="task['.$this->id.']['.$dayOfWeek.']" ';
                    $html .=' value="'.((($hidezeros==1) && ($taskTime->duration==0))?"":$dayWorkLoad);
                    $html .='" maxlength="5" style="width: 90%;'.$color;
                    $html .=' " onkeypress="return regexEvent(this,event,\'timeChar\')" ';
                    $html .= 'onblur="regexEvent(this,event,\''.$timetype.'\');updateTotal('.$dayOfWeek.',\''.$timetype.'\')" />';
                    $html .= "</th>\n";                    
                /*}else
                {
                    $html .= '<th style="'.$color.'"> ';
                    $html .= ' <input type="hidden" id="task['.$lineNumber.']['.$dayOfWeek.']" value="'.$dayWorkLoad.'" ';
                    $html .= 'name="task['.$this->id.']['.$dayOfWeek.']" />'."\n";
                    $html .='<div id="task['.$this->id.']['.$dayOfWeek.']"  >'.$dayWorkLoad."</div></th>\n";
                    
                }*/
        }
        if(!$hidden)$html .= "</tr>\n";
        return $html;

    }	


    public function test(){
            $Result=$this->id.' / ';
            $Result.=$this->description.' / ';		
            $Result.=$this->ProjectTitle.' / ';		
            $Result.=$this->date_start.' / ';
            $Result.=$this->date_end.' / ';
            //$Result.=$this->$weekWorkLoad.' / '; 
            return $Result;
}
/*
 * function to form a XML for this timesheet
 * 
 *  @param    string              	$yearWeek            year week like 2015W09
 *  @param     int              	$line number         used in the form processing
 *  @param    string              	$headers             header to shows
 *  @return     string                                         XML result containing the timesheet info
 */
    public function getXML( $yearWeek,$lineNumber)
    {
    $timetype=TIMESHEET_TIME_TYPE;
    $dayshours=TIMESHEET_DAY_DURATION;
    $hidezeros=TIMESHEET_HIDE_ZEROS;
    $xml= "\t\t<task line=\"{$lineNumber}\" id=\"{$this->id}\" name=\"{$this->description}\">\n"; 
    //title section
    $xml.="\t\t\t<project id=\"{$this->fk_project2}\">{$this->ProjectTitle}</project>\n";
    $xml.="\t\t\t<parenttask id=\"{$this->fk_task_parent}\">{$this->taskParentDesc}</parenttask>\n";
    //$xml.="<task id=\"{$this->id}\" name=\"{$this->description}\">\n";
    $xml.="\t\t\t<datestart unix=\"$this->date_start\">";
    if($this->date_start)
        $xml.=date('d/m/y',$this->date_start);
    $xml.="</datestart>\n";
    $xml.="\t\t\t<dateend unix=\"$this->date_end\">";
    if($this->date_end)
        $xml.=date('d/m/y',$this->date_end);
    $xml.="</dateend>\n";
     $xml.="\t\t\t<company id=\"{$this->companyId}\">{$this->companyName}</company>\n";
    $xml.="\t\t\t<taskprogress id=\"{$this->companyId}\">";
    if($this->planned_workload)
    {
        $xml .= $this->parseTaskTime($this->planned_workload).'('.floor($this->duration_effective/$this->planned_workload*100).'%)';
    }else{
        $xml .= "-:--(-%)";
    }
    $xml.="</taskprogress>\n";

        
  // day section
        foreach ($this->taskTimeList  as $dayOfWeek => $taskTime)
        {
                $today= strtotime($this->yearWeek.' +'.($dayOfWeek).' day  ');
                # to avoid editing if the task is closed 
                if ($timetype=="days")
                {
                    $dayWorkLoad=$taskTime->duration/3600/$dayshours;
                }else {
                    $dayWorkLoad=date('H:i',mktime(0,0,$taskTime->duration));
                }
                $open='0';
                if($this->status== 'REJECTED' || $this->status== NULL ){
                    $open=((empty($this->date_start) || ($this->date_start <= $today +86399)) && (empty($this->date_end) ||($this->date_end >= $today )));
                }
                $xml .= "\t\t\t<day col=\"{$dayOfWeek}\" open=\"{$open}\" status=\"{$taskTime->status}\"> {$dayWorkLoad}</day>\n";
                
        } 
        $xml.="\t\t</task>\n"; 
        return $xml;

    }	



/*
    public function isOpenThisWeek($yearWeek)
    {
            $yearWeekMonday=strtotime($yearWeek.' +0 days');
            $yearWeekSunday=strtotime($yearWeek.' +6 day');
 
            $projectstatic=new ProjectTimesheet($this->db);
	    $projectstatic->fetch($this->fk_project2);
            if((empty($this->date_start) || ($this->date_start <= $yearWeekSunday)) 
                    && (empty($this->date_end) ||($this->date_end >= $yearWeekMonday )) 
                    && ($projectstatic->isOpen($yearWeekMonday, $yearWeekSunday)))
            {	
                    return true;
            }else
            {	
                    #return true;
                    return FALSE;

            }
    }
 * */

    
public function updateTimeUsed()
    {
          $sql ="UPDATE ".MAIN_DB_PREFIX."projet_task AS pt "
               ."SET pt.duration_effective=(SELECT SUM(ptt.task_duration) "
               ."FROM ".MAIN_DB_PREFIX."projet_task_time AS ptt "
               ."WHERE ptt.fk_task ='".$this->id."') "
               ."WHERE pt.rowid='".$this->id."' ";
   
            dol_syslog(get_class($this)."::UpdateTimeUsed sql=".$sql, LOG_DEBUG);


            $resql=$this->db->query($sql);
            if ($resql)
            {
                    return 1;
            }
            else
            {
                    $this->error="Error ".$this->db->lasterror();
                    dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);

                    return -1;
            }	

    }
    function parseTaskTime($taskTime){
        
        $ret=floor($taskTime/3600).":".str_pad (floor($taskTime%3600/60),2,"0",STR_PAD_LEFT);
        
        return $ret;
        //return '00:00';
          
    }
    
 /*
 * function to genegate the timesheet tab
 * 
 *  @param    int              	$user                   user id to fetch the timesheets
 *  @param     int              	$updateTab      array with the new 
 *  @param     int              	$approval      0- don't ask, 1- ask for an approval 
 *  @return     array(string)                                             array of timesheet (serialized)
 */
 function updateTimesheet($user,$updateTab,$approval=0){  
     $ret=0;
     if(isset($updateTab) && is_array($updateTab)){
        foreach($this->taskTimeList  as $day => $taskTime){
            
            if(TIMESHEET_TIME_TYPE=="days")
            {
                $duration=$updateTab[$day]*TIMESHEET_DAY_DURATION*3600;
            }else
            {
              $durationTab=date_parse($updateTab[$day]);
              $duration=$durationTab['minute']*60+$durationTab['hour']*3600;
            }
            $update=($duration==$taskTime->duration)?false:true;   
            $new=($taskTime->id==0)?true:false;      
            $date=strtotime($this->yearWeek.' +'.$day.' day  +8 hours');
                //$this->timespent_old_duration= $this->timespent_duration;
            $this->timespent_duration=$duration; 
            if($update){
                $this->timespent_id=$taskTime->id;
                $this->timespent_date=$date;
                if(isset(  $this->timespent_datehour))
                {
                     $this->timespent_datehour=$date;
                }
                if($new){// equi duration >0
                    if( $this->addTimeSpent($user,0)>=0)
                    {
                        $ret++;
                        $_SESSION['timeSpendCreated']++;
                     }

                }else if($duration==0){
                        if( $this->delTimeSpent($user,0)>=0)
                        {
                            $ret++;
                            $_SESSION['timeSpendDeleted']++;
                        }
                }else{
                    if( $this->updateTimeSpent($user,0)>=0)
                    {
                        $ret++; 
                        $_SESSION['timeSpendModified']++;
                    }
                }
            }
        }
        if ($approval){
            $ret+=$this->askApproval();
            $_SESSION['approvalSent']++;
        }
        return $ret;
      }  else {
          return -1;
      }
      
 }	
 /*
 * function to genegate the timesheet tab
 * 
 *  @param    array(string)           $headers            array of the header to show
 *  @param    int              	$user                   user id to fetch the timesheets
 *  @param     int              	$yearWeek           timesheetweek
 *  @param    array(int)              	$whiteList    array defining the header width
 *  @param     int              	$timestamp         timestamp
 *  @return     array(string)                                             array of timesheet (serialized)
 */
 function timesheetTab($headers,$userid,$yearWeek,$timestamp){     
    // get the whitelist
    $whiteList=array();
    $staticWhiteList=new Timesheetwhitelist($this->db);
    $datestart=strtotime($yearWeek.' +0 day');
    $datestop=strtotime($yearWeek.' +6 day');
    $whiteList=$staticWhiteList->fetchUserList($userid, $datestart, $datestop);
     // Save the param in the SeSSION
     $tasksList=array();
     $whiteListNumber=count($whiteList);
     
    $sql ="SELECT DISTINCT element_id";
    if($whiteListNumber){
        $sql.=', (CASE WHEN tsk.rowid IN ('.implode(",",  $whiteList).') THEN \'1\' ';
        $sql.=' ELSE \'0\' END ) AS listed';
    }
    $sql.=" FROM ".MAIN_DB_PREFIX."element_contact "; 
    $sql.=' JOIN '.MAIN_DB_PREFIX.'projet_task as tsk ON tsk.rowid=element_id ';
    $sql.=' JOIN '.MAIN_DB_PREFIX.'projet as prj ON prj.rowid= tsk.fk_projet ';
    $sql.=" WHERE (fk_c_type_contact='181' OR fk_c_type_contact='180') AND fk_socpeople='".$userid."' ";
    if(TIMESHEET_HIDE_DRAFT=='1'){
         $sql.=' AND prj.fk_statut="1" ';
    }
    $sql.=' AND (prj.datee>=FROM_UNIXTIME("'.$datestart.'") OR prj.datee IS NULL)';
    $sql.=' AND (prj.dateo<=FROM_UNIXTIME("'.$datestop.'") OR prj.dateo IS NULL)';
    $sql.=' AND (tsk.datee>=FROM_UNIXTIME("'.$datestart.'") OR tsk.datee IS NULL)';
    $sql.=' AND (tsk.dateo<=FROM_UNIXTIME("'.$datestop.'") OR tsk.dateo IS NULL)';
    $sql.='  ORDER BY '.($whiteListNumber?'listed,':'').'prj.fk_soc,tsk.fk_projet,tsk.fk_task_parent,tsk.rowid ';

    dol_syslog("timesheet::getTasksTimesheet sql=".$sql, LOG_DEBUG);
    $resql=$this->db->query($sql);
    if ($resql)
    {
            $num = $this->db->num_rows($resql);
            $i = 0;
            // Loop on each record found, so each couple (project id, task id)
            while ($i < $num)
            {
                    $error=0;
                    $obj = $this->db->fetch_object($resql);
                    $tasksList[$i] = NEW timesheet($this->db, $obj->element_id);
                    $tasksList[$i]->listed=$obj->listed;
                   /* if((is_array($whiteList) && in_array($obj->element_id, $whiteList)) OR $whiteList==$obj->element_id ){
                        $tasksList[$i]->listed=true;
                    }       */       

                    //$tasksList[$i]->getTaskInfo();
                    //$tasksList[$i]->getActuals($yearWeek,$userid); 
                    $i++;
            }
            $this->db->free($resql);
             $i = 0;
             $resArray=array();
             foreach($tasksList as $row)
            {
                    dol_syslog("Timesheet::timesheet.class.php task=".$row->id, LOG_DEBUG);
                    $row->getTaskInfo();
                    $row->getActuals($yearWeek,$userid); 
                    $row->db=NULL;
                    $resArray[]=  serialize($row);
                    
            }
            // form hiden param
    }else
    {
            dol_print_error($this->db);
    }
    return $resArray;
     
 }
	
}

?>
