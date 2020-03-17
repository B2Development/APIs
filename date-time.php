<?php

class Datetimes
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;
    }

    public function get($which, $sid)
    {
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        switch($which) {
            case "timezones":
                $timezones=$this->BP->get_timezone_list($systemID);
                $data=array("timezone"=>$timezones);
                break;
            default:
                $dateinfo = $this->BP->get_date($systemID);
                if($dateinfo===false or $dateinfo===null){
                    return false;
                }
                $tz = $this->BP->get_timezone($systemID);
                if($tz===false or $tz===null){
                    return false;
                }
                $ntp = $this->BP->get_ntp_settings($systemID);
                if($ntp===false or $ntp===null){
                    return false;
                }
                try {
                    $timezone = new DateTimeZone($tz);
                    if ($timezone !== false) {
                        $tzOffset = $timezone->getOffset(new DateTime("now", $timezone));
                    } else {
                        $tzOffset = 0;
                    }
                }
                catch (Exception $e) {
                    global $Log;
                    $Log->writeError("Timezone setting failed: " . $e->getMessage(), false);
                    $tzOffset = 0;
                }

                $data=array('year'=>$dateinfo['year'],
                    'month'=>$dateinfo['month'],
                    'day'=>$dateinfo['day'],
                    'hour'=>$dateinfo['hour'],
                    'minute'=>$dateinfo['minute'],
                    'second'=>$dateinfo['second'],
                    'tz'=>$tz,
                    'tzOffset' => $tzOffset,
                    'ntp'=>array(
                        'enabled'=>$ntp['enabled'],
                        'local'=>$ntp['local'],
                        'sync'=>$ntp['sync'],
                        'servers'=>$ntp['servers']
                    )
                );
                break;

        }
        return $data;
    }

    public  function put($which, $data,$sid){
        $dpuID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        $ntpInfo=$data['ntp'];

        if(isset($data['ntp'])){
            $ntpInfo=$data['ntp'];
            $ntpBool=$this->BP->save_ntp_settings($ntpInfo,$dpuID);
            if($ntpBool === false){
                return false;
            }
        }

        if(isset($data['tz'])){
            $timezoneInfo=$data['tz'];
            $timezoneBool=$this->BP->set_timezone($timezoneInfo,$dpuID);
            if($timezoneBool === false){
                return false;
            }
        }

        if(isset($ntpInfo['enabled']) and $ntpInfo['enabled']===false){
            if(isset($data['year'])&&isset($data['month'])&&isset($data['day'])&&isset($data['hour'])&&isset($data['minute'])&&isset($data['second'])){
                $dateInfo=array('year'=>$data['year'],
                    'month'=>$data['month'],
                    'day'=>$data['day'],
                    'hour'=>$data['hour'],
                    'minute'=>$data['minute'],
                    'second'=>$data['second']
                );
                $dateBool=$this->BP->set_date($dateInfo,$dpuID);
                if($dateBool === false){
                    return false;
                }
            }
        }
    }

    public  function delete($which,$sid){
        $dpuID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        $ntp = $this->BP->get_ntp_settings($dpuID);
        if($ntp['enabled']){
            $ntp['enabled']=false;
        }
        $serverList=$ntp['servers'];
        /*foreach($serverList as $key=>$value){
            unset($serverList[$key]);
        }*/
        $ntp['servers']=array();
        $ntpBool=$this->BP->save_ntp_settings($ntp,$dpuID);
        return $ntpBool;
    }

} //End Datetime

?>
