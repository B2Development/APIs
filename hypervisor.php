<?php

class Hypervisor
{
    private $BP;

    public function __construct($BP){
        $this->BP = $BP;
    }

    public function put($which, $data, $sid){

        $return = array();
        if (!$sid) {
            $sid = $this->BP->get_local_system_id();
        }
        switch($which) {
            case "regex":
                $host = NULL;
                $host_id = isset($data['host']) ? $data['host'] : NULL;
                $regexList = isset($data['regex']) ? $data['regex'] : NULL;
                if ($host_id !== NULL) {
                    $parts = explode('_', $host_id);
                    $host = $parts[count($parts) - 1];
                }
                $return = $this->BP->generate_schedule_regex_list($host, $regexList, false, $sid);
                if ($return !== false) {
                    if (count($return) == 0) {
                        $return = $this->BP->generate_schedule_regex_list($host, $regexList, true, $sid);
                        if ($return !== false) {
                            $return = array('data' => $return);
                        }
                    } else {
                        $return = array('data' => $return);
                    }
                }
                break;
            default:
        }

        return $return;
    }

}   //End Hypervisor

?>
