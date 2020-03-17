<?php

class ActiveDirectory
{
    private $BP;
    const ACTIVE_DIRECTORY = "Active Directory";

    public function __construct($BP)
    {
        $this->BP = $BP;
    }

    public function get($sid)
    {
        $data = false;
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        $section = $this->BP->get_ini_section(ActiveDirectory::ACTIVE_DIRECTORY, $systemID);
        if ($section !== false) {
            $server = $this->getValue($section, 'AD_ServerName');
            $fqdn = $this->getValue($section, 'AD_DomainName');
            $adIP = $this->getADIP($this->BP, $server, $fqdn);
            if ($adIP != "0.0.0.0") {
                // IP was not found in the hosts file
                // potentially check if fqdn can resolved
                // return the server as set in master.ini
                $ipObj = array('field' => 'AD_IP', 'value' => $adIP);
                $section[] = $ipObj;
            }

            $data = array('data' => $section);
        }
        return $data;
    }

    public function put($data, $sid){
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();

        $return = false;
        $errors = false;
        $configuration = array();
        if ($data !== array_values($data)) {
            // associative array - one entry
            $return = $this->buildEntry($data, $errors);
            if (!$errors) {
                $configuration[] = $return;
            }
        } else {
            // non-associative array, multiple entries
            foreach ($data as $item) {
                $return = $this->buildEntry($item, $errors);
                if (!$errors) {
                    $configuration[] = $return;
                } else {
                    break;
                }
            }
        }
        if (!$errors) {
            $return = $this->BP->set_ini_section(ActiveDirectory::ACTIVE_DIRECTORY, $configuration, $systemID);
        }

        return $return;
    }

    private function buildEntry($item, &$errors) {
        if (isset($item['field'])) {
            $field = $item['field'];
            if (isset($item['value'])) {
                $value = $item['value'];
                $entry = array('field' => $field, 'value' => $value, 'description' => '');
            } else {
                $errors = true;
                $entry = array('status' => 500, 'message' => 'Active Directory field value must be specified.');
            }
        } else {
            $errors = true;
            $entry = array('status' => 500, 'message' => 'Active Directory field must be specified.');
        }
        return $entry;
    }

    public function delete($sid){
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        $errors = false;
        $configuration = array();
        // Set values to original master.ini defaults.
        $configuration[] = $this->buildEntry(array('field' => 'AD_AuthenticationEnabled', 'value' => 'No'), $errors);
        $configuration[] = $this->buildEntry(array('field' => 'AD_UseSSL', 'value' => 'No'), $errors);
        $configuration[] = $this->buildEntry(array('field' => 'AD_Superuser', 'value' => 'Unitrends-Superuser'), $errors);
        $configuration[] = $this->buildEntry(array('field' => 'AD_Admin', 'value' => 'Unitrends-Admin'), $errors);
        $configuration[] = $this->buildEntry(array('field' => 'AD_Manage', 'value' => 'Unitrends-Manage'), $errors);
        $configuration[] = $this->buildEntry(array('field' => 'AD_Monitor', 'value' => 'Unitrends-Monitor'), $errors);
        $configuration[] = $this->buildEntry(array('field' => 'AD_ServerName', 'value' => ''), $errors);
        $configuration[] = $this->buildEntry(array('field' => 'AD_DomainName', 'value' => ''), $errors);
        $return = $this->BP->set_ini_section(ActiveDirectory::ACTIVE_DIRECTORY, $configuration, $systemID);

        return $return;
    }

    public function getValue($section, $field)
    {
        $value = "";
        foreach ($section as $item) {
            if ($item['field'] == $field) {
                $value = $item['value'];
                break;
            }
        }
        return $value;
    }

    //make sure server is in hosts file
    //also need to check for hosts['long_name']
    private function getADIP($BP, $server, $fqdn){
        $adIP = "0.0.0.0";

        $hostInfo = $BP->get_host_info($server);
        if ($hostInfo !==false) {
            if(isset($hostInfo['ip'])) {
                $adIP = $hostInfo['ip'];
            }
        } else {
            $hostInfo = $BP->get_host_info($fqdn);
            if($hostInfo !==false) {
                if(isset($hostInfo['ip'])) {
                    $adIP = $hostInfo['ip'];
                }
            }
        }
        return $adIP;
    }

} //End ActiveDirectory

?>
