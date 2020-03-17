<?php

define("UNITRENDS_CLOUD", "Unitrends_cloud");

class DRTest
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;
        $this->now = time();
    }


    public function get($sid){
        $sid = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        $result = false;

        $targets = $this->BP->get_replication_targets($sid);
        if ($targets !== false){
            foreach ($targets as $target){
                if (isset($target['target_type']) && $target['target_type'] == UNITRENDS_CLOUD){
                    $result = true;
                }
            }
        }
        $drtest['data'] = $result;

        return $drtest;
    }

    public function send_drtest_request($data, $sid)
    {
        global $Log;

        $senderName = $data['name'];
        $senderCompany = $data['company'];
        $senderEmail = $data['email'];
        $senderPhone = $data['phone'];

        $output = $this->BP->run_command("System Information", "");
        $output = strtr($output, "\n", "\r\n");

        $assetTag = $this->BP->get_asset_tag();
        $assetTag = "\nAsset Tag:  $assetTag";

        $sendTo = "cloudops@unitrends.com";
        //$sendTo = "sbarton@unitrends.com, mbeach@unitrends.com";
        $subject = "DR Test Request From $senderName";
        $headers = "From: $senderEmail";

        $date = date('r', $this->now);

        $message = ("$output\n$assetTag\n$date\n$senderName\n$senderCompany\n$senderPhone\n$senderEmail\n");
        $message = wordwrap($message, 80);

        $logMessage = $Log->getTimestamp() . " Sending Mail.";
        $Log->writeVariable($logMessage);

        $result = mail($sendTo, $subject, $message, $headers);

        $logMessage = $Log->getTimestamp() . " Mail sent.";
        $Log->writeVariable($logMessage);

        return $result;
    }
}

?>
