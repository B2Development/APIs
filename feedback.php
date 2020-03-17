<?php

class Feedback
{
    private $BP;

    public function __construct($BP, $sid)
    {
        $this->BP = $BP;
        $this->now = time();
        $this->sid = $sid;

    }


    public function send_feedback($data, $sid)
    {
        $sid = $sid == false ? $this->BP->get_local_system_id() : $sid;

        global $Log;

        $senderName = $data['name'];
        $senderEmail = $data['email'];
        $version = $data['version'];
        $senderFeedback = $data['message'];

        $output = $this->BP->run_command("System Information", "");
        $output = strtr($output, "\n", "\r\n");

        $assetTag = $this->BP->get_asset_tag();
        $assetTag = "\nAsset Tag:  $assetTag";

        $sendTo = "UIFeedback@unitrends.com";
        //$sendTo = "sbarton@unitrends.com, mbeach@unitrends.com";
        $subject = "$version Feedback From $senderName";
        $headers = "From: $senderEmail";

        $message = ("$output\n$assetTag\n\n$senderEmail\n\n$senderFeedback\n");
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
