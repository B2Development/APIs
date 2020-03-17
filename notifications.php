<?php

$dir = '/api/includes/';

class Notifications
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;

    }

    public function get_alerts($which, $filter, $sid)
    {
        require_once('alerts.php');
        $alerts = new Alerts($this->BP, $sid);
        return $alerts->get($which, $filter, $sid);
    }

    public function get_audit_history($which, $sid)
    {
        require_once('audit-history.php');
        $audit = new AuditHistory($this->BP, $sid);
        return $audit->get($which);
    }

    public function get_snmp_config($which, $sid)
    {
        require_once('snmp-config.php');
        $snmpConfig = new SNMPConfig($this->BP);
        return $snmpConfig->get_config($which, $sid);
    }

    public function get_snmp_daemon_config($sid)
    {
        require_once('snmp-config.php');
        $snmpConfig = new SNMPConfig($this->BP);
        return $snmpConfig->get_daemon_config($sid);
    }

    public function get_snmp_history($which, $sid)
    {
        require_once('snmp-config.php');
        $snmpConfig = new SNMPConfig($this->BP);
        return $snmpConfig->get_traps($which, $sid);
    }

    public function send_snmp_trap($sid)
    {
        require_once('snmp-config.php');
        $snmpConfig = new SNMPConfig($this->BP);
        return $snmpConfig->send_test_trap($sid);
    }

    public function put_snmp_config($which, $data, $sid)
    {
        require_once('snmp-config.php');
        $snmpConfig = new SNMPConfig($this->BP);
        $data['id'] = (int)$which;
        return $snmpConfig->put_snmp_config($data, $sid);
    }

    public function put_snmp_daemon_config($data, $sid)
    {
        require_once('snmp-config.php');
        $snmpConfig = new SNMPConfig($this->BP);
        return $snmpConfig->put_snmp_daemon_config($data, $sid);
    }

    public function post_snmp_config($data, $sid)
    {
        require_once('snmp-config.php');
        $snmpConfig = new SNMPConfig($this->BP);
        return $snmpConfig->put_snmp_config($data, $sid);
    }

    public function delete_snmp_config($id, $sid)
    {
        require_once('snmp-config.php');
        $snmpConfig = new SNMPConfig($this->BP);
        return $snmpConfig->delete_snmp_config($id, $sid);
    }

    public function get_jobs($which, $filter, $jobID, $sid, $systems, $data)
    {
        require_once('jobs.php');
        $jobs = new Jobs($this->BP, $sid);
        return $jobs->get($which, $filter, $jobID, $sid, $systems, $data);
    }

    public function get_psa_info($which, $sid)
    {
        require_once('psa.php');
        $info = new PSA($this->BP);
        return $info->get($which, $sid);
    }

    public function post_psa_info($which, $data, $sid)
    {
        require_once('psa.php');
        $info = new PSA($this->BP);
        return $info->post($which, $data, $sid);
    }

    public function put_psa_info($which, $data, $sid)
    {
        require_once('psa.php');
        $info = new PSA($this->BP);
        return $info->put($which, $data, $sid);
    }

    public function delete_psa_info($which, $sid)
    {
        require_once('psa.php');
        $info = new PSA($this->BP);
        return $info->delete($which, $sid);
    }

    public function delete_job($which, $data, $sid){
        require_once('jobs.php');
        $jobs = new Jobs($this->BP, $sid);
        return $jobs->delete($which, $data);
    }

    public function suspend_resume($action, $which, $sid){
        require_once('jobs.php');
        $jobs = new Jobs($this->BP, $sid);
        return $jobs->suspend_resume($action, $which);
    }

    public function close_alert($which, $sid){
        require_once('alerts.php');
        $alerts = new Alerts($this->BP, $sid);
        return $alerts->close($which, $sid);
    }

    public function post_jobs($which, $data, $sid)
    {
        require_once('jobs.php');
        $jobs = new Jobs($this->BP, $sid);
        return $jobs->post($which, $data, $sid);
    }
}

?>
