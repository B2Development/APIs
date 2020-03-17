<?php

class Capabilities
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;
    }

    public function get($which, $sid){

            $systemList = $this->BP->get_system_list();                 //We need to send capabilities data for all systems so get system list

            foreach($systemList as $key => $val) {
                $system_id = $key;
                $system_name = $val;
                $endsWith = substr($system_name, -strlen(".dpu")) == ".dpu";            // Do not include system which ends in ".dpu"
                if (!$endsWith) {
                    if ($_GET['sid']!==NULL and (int)$_GET['sid'] !== $key) {
                        continue;
                    }
                    if( $which != -1 and $which!=="" ){
                        $capabilities = $this->BP->get_capabilities($which, $system_id);
                    }
                    else{
                        $capabilities = $this->BP->get_capabilities(NULL, $system_id);
                    }
                    $systemCapabilities['capabilities'] = $capabilities;
                    $systemCapabilities['system_name'] = $system_name;
                    $systemCapabilities['system_id'] = $system_id;
                    $result['data'][] = $systemCapabilities;
                }
            }

        return $result;
    }

}  //End of Capabilities class
?>