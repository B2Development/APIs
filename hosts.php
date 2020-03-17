<?php

class Hosts
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;
        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);
    }

    public function get($which, $sid)
    {
        $data = false;

        $localID = $this->BP->get_local_system_id();
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $localID;
        $hostName = $this->BP->get_hostname($localID);

        if ($which !== -1) {
            $host = $this->BP->get_host_info($which, $sid);
            if ($host !== false) {
                $host['local'] = ($hostName !== false) && ($host['name'] == $hostName['name']);
                $data = $host;
            }
        } else {
            $hosts = $this->BP->get_host_list($systemID);
            if ($hosts !== false) {
                $data = array();
                foreach ($hosts as $host) {
                    $host = $this->BP->get_host_info($host, $systemID);
                    if ($host !== false) {
                        $host['local'] = ($hostName !== false) && ($host['name'] == $hostName['name']);
                        if (isset($host['aliases'])) {
                            $host['aliases_str'] = implode(",", $host['aliases']);
                        }
                        $data[] = $host;
                    } else {
                        $data = false;
                        break;
                    }
                }
            }
            if ($data !== false) {
                $data = array('data' => $data);
            }
        }
        return $data;
    }

    public function put($data, $sid){
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();

        if (!isset($data['original_ip'])) {
            $return = array('error' => 500, 'message' => 'Original IP address must be specified.');
        } else {
            if (isset($data['aliases_str'])) {
                $data['aliases'] = explode(",", $data['aliases_str']);
            }
            $payloadCheck = $this->functions->isValidPayload($data, "PUT hosts");
            if ($payloadCheck === true) {
                $return = $this->BP->save_host_info($data, $systemID);
            } else {
                $return = array('error' => 500, 'message' => $payloadCheck);
            }
        }

        return $return;
    }

    public function post($data, $sid){
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();

        if (isset($data['ip'])) {
            if (isset($data['name'])) {
                if (isset($data['aliases_str'])) {
                    $data['aliases'] = explode(",", $data['aliases_str']);
                }
                $payloadCheck = $this->functions->isValidPayload($data, "POST hosts");
                if ($payloadCheck === true) {
                    $return = $this->BP->save_host_info($data, $systemID);
                } else {
                    $return = array('error' => 500, 'message' => $payloadCheck);
                }
            } else {
                $return = array('error' => 500, 'message' => 'Host name must be specified.');
            }
        } else {
            $return = array('error' => 500, 'message' => 'IP address must be specified.');
        }

        return $return;
    }

    public function delete($which, $sid){
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();

        $return = false;
        if ($which !== -1) {
            $return = $this->BP->remove_host_info($which, $systemID);
        } else {
            $return = array('error' => 500, 'message' => 'Host name to delete must be specified.');
        }

        return $return;
    }

} //End Hosts

?>