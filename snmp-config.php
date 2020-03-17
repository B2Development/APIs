<?php

class SNMPConfig
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;
        $this->functions = new Functions($this->BP);
    }

    public function get_traps($oid, $sid)
    {
        global $Log;
        $results = array();

        // See if any start and/or end dates were passed in.
        $data = $_GET;
        $start_time = false;
        if (array_key_exists('start_date', $data)) {
            $start_time = strtotime($data['start_date']);
        }
        $end_time = false;
        if (array_key_exists('end_date', $data)) {
            $end_time = $this->functions->formatEndDate($data['end_date']);
        }
        $isValidDate = $this->functions->isValidDateRange($start_time, $end_time, false);
        if($isValidDate) {
            if ($sid !== false) {
                $name = $this->functions->getSystemNameFromID($sid);
                $traps = $this->BP->get_snmp_history($sid);
                if ($traps !== false) {
                    $results["data"] = $this->processTraps($traps, $name, $sid, $oid, $start_time, $end_time);
                } else {
                    $Log->writeError($this->BP->getError(), true);
                    $results = false;
                }
            } else {
                $systems = $this->functions->selectSystems();
                $allTraps = array();
                foreach ($systems as $sid => $name) {
                    $traps = $this->BP->get_snmp_history($sid);
                    if ($traps !== false) {
                        $allTraps = array_merge($allTraps, $traps);
                    } else {
                        $Log->writeError($this->BP->getError(), true);
                    }
                }
                $results["data"] = $this->processTraps($allTraps, $name, $sid, -1, $start_time, $end_time);
            }
        } else {
            $results['error'] = 500;
            $results['message'] = "Invalid date range provided";           
        }
        return $results;
    }

    private function processTraps($traps, $system_name, $system_id, $trapID, $start_time, $end_time)
    {
        $allTraps = array();
        foreach ($traps as $trap) {
            if ($trapID != -1 && $trap['oid'] != $trapID) {
                continue;
            }
            // trap time format is YYYY-MM-DD hh:mm:ss.ms, converted to a timestamp using strtotime.
            $trap_time = isset($trap['time_sent']) ? strtotime($trap['time_sent']) : 0;
            if ($start_time !== false && $trap_time < $start_time) {
                continue;
            }
            if ($end_time !== false && $trap_time > $end_time) {
                continue;
            }

            $trap['system_id'] = $system_id;
            $trap['system_name'] = $system_name;
            $allTraps[] = $trap;
        }

        return($allTraps);
    }

    public function get_config($which, $sid)
    {
        $id = (int)$which;
        if ($sid == false) {
            $sid = $this->BP->get_local_system_id();
        }
        $results = array();
        $config = $this->BP->get_snmp_config($sid);
        if ($config !== false) {
            $snmp = array();
            foreach ($config as $dest) {
                if ($id != -1 && $dest['id'] !== $id) {
                    continue;
                }
                $snmp[] = $dest;
            }
            $results = array("data" => $snmp);
        } else {
            $results = false;
        }
        return $results;
    }

    public function get_daemon_config($sid)
    {
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        $results = false;
        $snmpd = $this->BP->get_snmpd_config($sid);
        if ($snmpd !== false) {
            $results = array("data" => $snmpd);
        }
        return $results;
    }

    public function send_test_trap($sid)
    {
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        return $this->BP->send_test_snmptrap($sid);
    }

    public function put_snmp_config($config, $sid)
    {
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        return $this->BP->save_snmp_config($config, $sid);
    }

    public function put_snmp_daemon_config($config, $sid)
    {
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        return $this->BP->save_snmpd_config($config, $sid);
    }

    public function post_snmp_config($config, $sid)
    {
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        return $this->BP->save_snmp_config($config, $sid);
    }

    public function delete_snmp_config($id, $sid)
    {
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        return $this->BP->delete_snmp_config($id, $sid);
    }
}

?>
