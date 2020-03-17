<?php

class Hostname
{
    private $BP;

    public function __construct($BP, $sid)
    {
        $this->BP = $BP;
        $this->now = time();
        $this->sid = $sid;

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);

    }


    public function get($which, $sid)
    {
        $data = array();
        switch ($which){
            case "-1":  //never pass in request[1]?
                if ($sid !== false) {
                    $data["data"] = $this->buildOutput($sid);
                } else {
                    $systems = $this->BP->get_systems();
                    foreach($systems as $sid => $name){
                        $data["data"][] = $this->buildOutput($sid);
                    }
                }
            break;
        }

        return $data;
    }

    public function update($which, $inputArray){
 ;
    }

    public function put($which, $data, $sid) {
        global $Log;
        //check if the system is replicating
        $result = ($sid == $this->BP->get_local_system_id()) ? $this->BP->local_system_is_replicating() : $this->BP->is_replication_configured($sid);
        if($result === true or is_string($result)) {
            $status = array();
            $status['error'] = 405;
            $status['message'] = "Because this appliance is copying backups to a hot target,  its hostname cannot be changed";
        } else {
            if(isset($data['name'])) {
                //first verify that the name provided does not contain only numbers
                if(!ctype_digit($data['name'])) {
                    $inputParams = array();
                    if(isset($data['name'])) {
                        $name = $data['name'];
                    }
                    if(isset($data['long_name'])) {
                        $inputParams['long_name'] = $data['long_name'];
                    }
                    if(isset($data['keep_alias'])) {
                        $inputParams['keep_alias'] = $data['keep_alias'];
                    }
                    $status = $this->BP->change_hostname($name, $inputParams, $sid);
                } else {
                    $status = array();
                    $status['error'] = 500;
                    $status['message'] = "Hostname cannot contain only numbers";
                }
            } else {
                $status = array();
                $status['error'] = 500;
                $status['message'] = "Name is required";
            }

        }
        return $status;
    }

    function buildOutput($sid){

        $result = $this->BP->get_hostname($sid);

        if ($result !== false){
            $name = isset($result['name']) ? $result['name'] : "";
            $longName = isset($result['long_name']) ? $result['long_name'] : "";
            $sname = $this->functions->getSystemNameFromID($sid);

            $data = array(
                'name' => $name,
                'long_name' => $longName,
                'system_name' => $sname,
                'system_id' => $sid
            );
        } else {
            $data = false;
        }

        return $data;
    }
}

?>
