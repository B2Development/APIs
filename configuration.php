<?php

$dir = '/api/includes/';

class Configuration
{
    private $BP;
     
    public function __construct($BP)
    {
	$this->BP = $BP;

    }
 
    public function get_preferences($which, $filter, $sid){
        require_once('preferences.php');
        $preferences = new Preferences($this->BP, $sid);
        return $preferences->get($which, $filter);
    }

    public function update_preferences($which, $inputArray, $sid){
        require_once('preferences.php');
        $preferences = new Preferences($this->BP, $sid);
        return $preferences->update($which, $inputArray);
    }

    public function get_license($which) {
        require_once('license.php');
        $license = new License($this->BP);
        return $license->get($which);
    }

    public function update_license($which,$data) {
        require_once('license.php');
        $license = new License($this->BP);
        return $license->update($which,$data);
    }

    public function get_updates($which) {
        require_once('updates.php');
        $updates = new Updates($this->BP);
        return $updates->get($which);
    }

    public function post_updates($which,$data) {
        require_once('updates.php');
        $updates = new Updates($this->BP);
        return $updates->post($which,$data);
    }

    public function get_datetime($which, $sid) {
        require_once('date-time.php');
        $datetime = new Datetimes($this->BP);
        return $datetime->get($which, $sid);
    }

    public function put_datetime($which,$data,$sid) {
        require_once('date-time.php');
        $datetime = new Datetimes($this->BP);
        return $datetime->put($which,$data,$sid);
    }

    public function delete_datetime($which,$data,$sid) {
        require_once('date-time.php');
        $datetime = new Datetimes($this->BP);
        return $datetime->delete($which,$data,$sid);
    }

    public function get_mailconfig($which,$sid) {
        require_once('mail-config.php');
        $mailconfig = new Mailconfig($this->BP);
        return $mailconfig->get($which,$sid);
    }

    public function put_mailconfig($which,$data,$sid) {
        require_once('mail-config.php');
        $mailconfig = new Mailconfig($this->BP);
        return $mailconfig->put($which,$data,$sid);
    }

    public function delete_mailconfig($which,$sid) {
        require_once('mail-config.php');
        $mailconfig = new Mailconfig($this->BP);
        return $mailconfig->delete($which,$sid);
    }

    public function get_encryption($which, $systems)
    {
        require_once('encryption.php');
        $encryption = new Encryption($this->BP);
        return $encryption->get($which, $systems);
    }

    public function put_encryption($which, $data, $sid)
    {
        require_once('encryption.php');
        $encryption = new Encryption($this->BP);
        return $encryption->put($which, $data, $sid);
    }

    public function post_encryption($which, $data, $sid)
    {
        require_once('encryption.php');
        $encryption = new Encryption($this->BP);
        return $encryption->post($which, $data, $sid);
    }

    public function get_hostname($which, $sid)
    {
        require_once('hostname.php');
        $hostname = new Hostname($this->BP, $sid);
        return $hostname->get($which, $sid);

    }

    public function put_hostname($which, $data, $sid)
    {
    require_once('hostname.php');
    $hostname = new Hostname($this->BP, $sid);
    return $hostname->put($which, $data, $sid);
    }

    public function get_retention($which, $data, $sid)
    {
        require_once('retention.php');
        $retention = new Retention($this->BP);
        return $retention->get_retention($which, $data, $sid);
    }

    public function put_retention($which, $data, $sid)
    {
        require_once('retention.php');
        $retention = new Retention($this->BP);
        return $retention->put_retention($which, $data, $sid);
    }

    public function post_retention($which, $data, $sid)
    {
        require_once('retention.php');
        $retention = new Retention($this->BP);
        return $retention->post_retention($which, $data, $sid);
    }

    public function delete_retention($which, $sid)
    {
        require_once('retention.php');
        $retention = new Retention($this->BP);
        return $retention->delete_retention($which, $sid);
    }

    public function get_protected_assets($which, $sid)
    {
        require_once('protected_assets.php');
        $assets = new Assets($this->BP);
        return $assets->get($which, $sid);
    }

    public function update_protected_assets($which, $data, $sid)
    {
        require_once('protected_assets.php');
        $assets = new Assets($this->BP);
        return $assets->put_assets($which, $data, $sid);
    }

    public function get_applications($data, $sid)
    {
        require_once('applications.php');
        $applications = new Applications($this->BP);
        return $applications->get($data, $sid);
    }
    public function get_optimization($type, $sid){
        require_once('optimize.php');
        $optimization = new Optimize($this->BP, $type);
        return $optimization->get_optimize($type, $sid);
    }

    public function update_optimization($data, $sid){
        require_once('optimize.php');
        $optimization = new Optimize($this->BP);
        return $optimization->update($data, $sid);
    }

    public function move_db($sid){
        require_once('optimize.php');
        $optimization = new Optimize($this->BP);
        return $optimization->set_database($sid);
    }
    
}
?>
