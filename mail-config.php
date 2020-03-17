<?php

class Mailconfig
{
    private $BP;

    public function __construct($BP){
        $this->BP = $BP;
    }

    public function get($which,$sid)
    {
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();

        if (!isset($sid) || $systemID == $this->BP->get_local_system_id()) {
            $mailInfo = $this->BP->get_mail_info();
        } else {
            $mailInfo = $this->BP->get_mail_info($systemID);
        }
        if ($mailInfo != false) {
            $useHTML = $this->BP->get_ini_value('Unattended Backup Information', 'NotificationsUseHtml', $systemID);
            $sendPDF = $this->BP->get_ini_value('Unattended Backup Information', 'NotificationsSendPDF', $systemID);
            $data=array();
            if (array_key_exists('authinfo', $mailInfo)) $data['authinfo']= $mailInfo['authinfo'];
            if (array_key_exists('user', $mailInfo)) $data['user']=$mailInfo['user'];
            if (array_key_exists('password', $mailInfo)) $data['password']=$mailInfo['password'];
            if (array_key_exists('smtp', $mailInfo)) $data['smtp']=$mailInfo['smtp'];
            if (array_key_exists('bp', $mailInfo)) $data['bp']=$mailInfo['bp'];
            if (array_key_exists('failure', $mailInfo)) $data['failure']=$mailInfo['failure'];
            if (array_key_exists('schedule', $mailInfo)) $data['schedule']=$mailInfo['schedule'];
            if (array_key_exists('disk', $mailInfo)) $data['disk']=$mailInfo['disk'];
            $data['html']= ($useHTML != false && (strtolower($useHTML) == 'no')) ? false : true;
            $data['pdf']= ($sendPDF == false || (strtolower($sendPDF) == 'no')) ? false : true;
            $result = array('data' => $data);
        } else {
            $result = false;
        }

        return $result;
    }

    public function put($which,$data,$sid){
        $mailInfo=$data;
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        if(isset($data['authinfo']) && $data['authinfo']===true){
                $mailInfo['user']=$data['user'];
                $mailInfo['password']=$data['password'];
        }

        if (!isset($sid) || $systemID == $this->BP->get_local_system_id()) {
            $mail = $this->BP->save_mail_info($mailInfo);
        } else {
            $mail = $this->BP->save_mail_info($mailInfo,$systemID);
        }

        if (isset($data['bp'])) {
            $replicationRecipient = $this->BP->set_ini_value("Replication", 'ReportMailTo', $data['bp'], $systemID);
        }

        if (isset($data['html'])) {
            $useHTML = $data['html'];
            $resultHTML = $this->BP->set_ini_value("Unattended Backup Information", 'NotificationsUseHtml', $useHTML, $systemID);
        }

        if (isset($data['pdf'])) {
            $pdfIniValue = $data['pdf'];
            $resultPDF = $this->BP->set_ini_value("Unattended Backup Information", 'NotificationsSendPDF', $pdfIniValue, $systemID);
        }
        return $mail;

    }

    public function delete($which,$sid){
        //$systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        $mailInfo = $this->BP->get_mail_info($sid);
        if (array_key_exists($which, $mailInfo)){
            $mailInfo[$which]="";
        }
        $mail = $this->BP->save_mail_info($mailInfo,$sid);
        return $mail;
    }

} //End Mail config

?>