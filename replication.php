<?php

class Replication{

    private $BP;

    public function __construct($BP, $Roles = null)
    {
        require_once('function.lib.php');
        $this->BP = $BP;
        $this->functions = new Functions($this->BP);
        $this->Roles = $Roles;
    }

    public function get($which, $filter, $data, $sid) {

        if($sid === false) {
            $systemID = $this->BP->get_local_system_id();
        } else {
            $systemID = (int)$sid;
        }

        switch($which){

            case "config":
                $maxConcurrent=$this->BP->get_ini_value("Replication","MaxConcurrent",$systemID);
                $reportMailto=$this->BP->get_ini_value("Replication","ReportMailTo",$systemID);
                $reportTime=$this->BP->get_ini_value("Replication","ReportTime",$systemID);
                $queueScheme=$this->BP->get_ini_value("Replication","QueueScheme",$systemID);
                $enabled = $this->BP->is_replication_enabled($systemID);
                $strategy = $queueScheme === "0" ? "Manual" :
                    ($queueScheme === "1" ? "Maximum Retention" : "Recency");

                $configData=array(
                    'max_concurrent'=>$maxConcurrent,
                    'report_mail_to'=>$reportMailto,
                    'report_time'=>$reportTime,
                    'queue_scheme'=>$queueScheme,
                    'strategy'=>$strategy,
                    'enabled' => $enabled
                );
                $result=array('config'=>$configData);
                break;

            case "assets":
                $result = $this->get_replicating_assets($systemID);
                break;

            case "throttle":
                $throttle=$this->BP->get_ini_value("Replication","BlockOutPeriods",$systemID);
                $bandwidthMBPS=$this->BP->get_ini_value("CMC", "VaultUpstreamSpeed", $systemID);
                $bandwidth = $bandwidthMBPS * 1024 / 8;
                if($throttle !== false) {
                    if($throttle !== "") {
                        $throttledata= explode(",",$throttle);
                        $max=0;$min=999;
                        foreach($throttledata as $key=>$val){
                            $data2[]=explode(":",$val);
                            $data3[]=explode("=",$data2[$key][1]);
                            $data4[]=(int)$data3[$key][1];
                            if($data4[$key]>=$max){
                                $max=$data4[$key];
                            }
                            if($data4[$key]<=$min){
                                $min=$data4[$key];
                            }
                        }
                        foreach($throttledata as $key=>$val){
                            $percent[$key]=intval(($data4[$key]*100)/$bandwidth);
                        }
                        asort($percent);
                        // var_dump($percent);
                        //$result=$percent;
                        //var_dump($percent);
                        $current=$percent[0];
                        // var_dump($current);
                        $days=array();
                        $data1=array();
                        foreach($percent as $key=> $val){
                            // var_dump($val);
                            if($current !== $val){
                                $current=$val;
                                //asort($days);
                                if($days!=null){
                                    sort($days);
                                    $data1['days']=$days;
                                    $result['throttle'][]=$data1;
                                }
                                // var_dump($days);
                                // $data1['percentage'][]=null;
                                $data1['percentage']=null;
                                // var_dump($data2[$key][0]);

                                $data1=array();
                                $days=array();

                            }

                            $data1['percentage']=$current;
                            // var_dump($data2[$key][0]);
                            // var_dump($data3[$key][0]);

                            $time=explode("-",$data3[$key][0]);
                            $starttime=(int)$time[0];
                            $currentstarttime=$starttime;
                            if(isset($time[1])){
                                $endtime=(int)$time[1];
                            }
                            else{
                                $endtime=(int)$time[0];
                            }
                            if((isset($data1['start']) && $data1['start'] !== $starttime) or
                                (isset($data1['end'])) && $data1['end'] !== $endtime) {
                                if($days!=null){
                                    $data1['days']=$days;
                                    $result['throttle'][]=$data1;
                                }
                                // var_dump($days);
                                // $data1['percentage'][]=null;
                                $data1['percentage']=null;
                                //  var_dump($data2[$key][0]);

                                $data1=array();
                                $days=array();

                                //check to see if there's already a throttle piece that fits these requirements
                                $pieceExists = false;
                                for($i = 0; $i < count($result['throttle']); $i++) {
                                    if($result['throttle'][$i]['percentage'] == $current and $result['throttle'][$i]['start'] == $starttime and $result['throttle'][$i]['end'] == $endtime) {
                                        // print_r("Exists--");
                                        $pieceExists = true;
                                        if(!in_array((int)$data2[$key][0], $result['throttle'][$i]['days'])) {
                                            $result['throttle'][$i]['days'][] = (int)$data2[$key][0];
                                        }
                                        break;
                                    }
                                }
                                if($pieceExists == false) {
                                    //create a new piece
                                    // print_r("New--");
                                    $data1['percentage'] = $current;
                                    $data1['start']=$starttime;
                                    $data1['end']=$endtime;
                                    $days[]=(int)$data2[$key][0];
                                }

                            } else {
                                $data1['start']=$starttime;
                                $data1['end']=$endtime;
                                $days[]=(int)$data2[$key][0];
                            }

                        }
                        if (count($days) > 0) {
                            $data1['days']=$days;
                            $result['throttle'][]=$data1;
                        }
                        for($i = 0; $i < count($result['throttle']); $i++) {
                            sort($result['throttle'][$i]['days']);
                        }
                    } else {
                        $result['throttle'] = array();
                    }
                    $result['BlockoutPeriod']=$throttle;
                    //send the internet speeds back also
                    $bandwidthArray = array();
                    $bandwidthArray[] = array("name"=>"Ethernet (1 Mbps)", "value"=>1, "selected"=>$bandwidthMBPS == 1 ? true : false);
                    $bandwidthArray[] = array("name"=>"Cable (38 Mbps)", "value"=>38, "selected"=>$bandwidthMBPS == 38 ? true : false);
                    $bandwidthArray[] = array("name"=>"Cable (160 Mbps)", "value"=>160, "selected"=>$bandwidthMBPS == 160 ? true : false);
                    $bandwidthArray[] = array("name"=>"DSL (200 Mbps)", "value"=>200, "selected"=>$bandwidthMBPS == 200 ? true : false);
                    $bandwidthArray[] = array("name"=>"Ethernet (1 Gbps)", "value"=>1000, "selected"=>$bandwidthMBPS == 1000 ? true : false);

                    $speedValues = array(1, 38, 160, 200, 1000);
                    $bandwidthArray[] = array("name" => "Custom", "value" => !in_array($bandwidthMBPS, $speedValues) ? $bandwidthMBPS : 0, "selected"=>!in_array($bandwidthMBPS, $speedValues) ? true : false);
                    $result['bandwidths'] = $bandwidthArray;
                    // var_dump($day);
                } else {
                    $result = $throttle;
                }
                break;

            case "pending":

                $include_rejected = false;
                if ($data !== NULL and array_key_exists('include_rejected', $data)) {
                    $include_rejected = ((int)$data['include_rejected'] === 1);
                }

                $result = $this->getReplicationPending($include_rejected, false);
                break;

            case "queue":
                if ( isset($filter) and $filter === "strategy" ) {
                    $queueScheme=$this->BP->get_ini_value("Replication","QueueScheme",$systemID);
                    if($queueScheme==="0"){
                        $result['strategy'] = "Manual";
                    }
                    elseif($queueScheme==="1"){
                        $result['strategy'] = "Maximum Retention";
                    }
                    else $result['strategy'] = "Recency";
                } else {
                    $result = $this->get_replication_queue($filter, $sid);
                }
                break;

            case "targets":
                $result = $this->BP->get_replication_targets($sid);
                $includeIncompleteSystems = ( $data !== NULL and array_key_exists('include_incomplete_systems', $data) and is_bool($data['include_incomplete_systems']) ) ? $data['include_incomplete_systems'] : true;
                $includeTypeOfBackupCopyTarget = ( $data !== NULL and array_key_exists('include_type_of_backup_copy_target', $data) and is_bool($data['include_type_of_backup_copy_target']) ) ? $data['include_type_of_backup_copy_target'] : false;
                $includeNameField = ( $data !== NULL and array_key_exists('include_name_field', $data) and is_bool($data['include_name_field']) ) ? $data['include_name_field'] : false;
                if($result !== false) {
                    $returnTargets = array();
                    $length = count($result);
                    for($i = 0; $i < $length; $i++) {
                        if ( $includeIncompleteSystems === true or $result[$i]['status'] === Constants::SYSTEM_STATUS_COMPLETE ) {
                            $result[$i]['created'] = $this->functions->formatDateTime($result[$i]['created']);
                            if(isset($result[$i]['updated'])) {
                                $result[$i]['updated'] = $this->functions->formatDateTime($result[$i]['updated']);
                            }
                            if(isset($result[$i]['last_poll'])) {
                                $result[$i]['last_poll'] = $this->functions->formatDateTime($result[$i]['last_poll']);
                            }
                            if(isset($result[$i]['suspend_toggled'])) {
                                $result[$i]['suspend_toggled'] = $this->functions->formatDateTime($result[$i]['suspend_toggled']);
                            }
                            if(isset($result[$i]['is_suspended_by_source'])&& $result[i]['is_suspended_by_source'] == true) {
                                $result[$i]['suspend_toggled_by_source'] = $this->functions->formatDateTime($result[$i]['suspend_toggled_by_source']);
                            }
                            if($includeTypeOfBackupCopyTarget === true) {
                                $result[$i]['type'] = 'replication_target';
                            }
                            if($includeNameField === true) {
                                $result[$i]['name'] = $result[$i]['host'];
                            }
                            $returnTargets[] = $result[$i];
                        }
                    }
                    $result = array("targets" => $returnTargets);
                }
                break;

            case "is_in_queue":
                $backupIDs = isset($data['backup_ids']) ? $data['backup_ids'] : false;
                $allQueueStatus = array();
                if ($backupIDs !== false) {
                    $section = $this->BP->get_ini_section("Replication", $sid);
                    if ($section !== false) {
                        $target = $this->getValue($section, "SyncTo");
                        $bidArray = explode(',', $data['backup_ids']);
                        foreach ($bidArray as $bid) {
                            $result = $this->BP->is_backup_in_replication_queue((int)$bid, $target, $sid);
                            if ($result !== false) {
                                $queueStatus = array("backup_id" => $bid, "status" => $result);
                                $allQueueStatus[] = $queueStatus;
                            }
                        }
                        $result = array("QueueStatus" => $allQueueStatus);
                    } else {
                        $result = array();
                        $result['error'] = 500;
                        $result['message'] = 'Missing input: \'target\' is a required input.';
                    }

                } else {
                    $result = array();
                    $result['error'] = 500;
                    $result['message']= 'Missing input: \'backup_ids\' is a required input.';
                }
                break;

            case "one_to_many_supported":
                $supported = $this->BP->one_to_many_supported($sid);
                if ($supported !== false && $supported !== -1) {
                    $result = $supported;
                } else {
                    $result = array();
                    $result['error'] = 500;
                    $result['message']= bp_error();
                }
                break;
        }
    return $result;

    }

    function getValue($section, $field){
        $value = "";
        foreach ($section as $item) {
            if ($item['field'] == $field) {
                $value = $item['value'];
                break;
            }
        }
        return $value;
    }

    public function get_catalog($filter, $sid, $systems) {

        $ip = $filter == 'all' ? 'all' : "";

        $data = array();
        $data['catalog'] = array();

        $view = isset($_GET['view']) ? $_GET['view'] : "system";
        $startURI = isset($_GET['start_date']) ? ("&start_date=" . $_GET['start_date']) : "";
        $endURI = isset($_GET['end_date']) ? ("&end_date=" . $_GET['end_date']) : "";
        // add language if set
        $lang = isset($_GET['lang']) ? ("&lang=" . $_GET['lang']) : "";
        // function remoteRequest($ip, $request, $api, $parameters, $data, $sid, $auth_token)
        $request = "GET";
        $api = "/api/catalog/backups/";
        $parameters = "view=" . $view . $startURI . $endURI . $lang . "&grandclient=true&show_remote=true";
        $result = $this->functions->remoteRequest($ip, $request, $api, $parameters, NULL);
        if (is_array($result)) {
            $data = $result;
        }
        return $data;
    }

    public function post($which, $data, $sid = NULL)
    {
        global $Log;
        require_once('function.lib.php');
        $functions = new Functions($this->BP);

        $result = false;
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        if (is_string($which[0])) {
            switch ($which[0]) {
                case 'source':
                    $result = $this->post_replication_source($data, $sid);
                    break;
                case 'target':
                    $result = $this->post_replication_target($data, $sid);
                    break;
                case 'target-queue':
                    $result = $this->initiateReplicationFromTarget($data, $sid);
                    break;
                case 'queue':
                    if (isset($data['backup_id'])) {
                        $bid = (int)$data['backup_id'];
                        if (isset($data['target'])) {
                            // Note: dest is the source name that originated the request
                            // Note: system_name will be this target name
                            $dest = $data['target'];
                            $system_name = $functions->getLocalSystemName();
                            // Check the replication services on the target first
                            $result = $this->validate_replication_config($dest, $system_name, $sid);
                            if (is_array($result) && isset($result['error'])) {
                                return $result;
                            }
                            // Check the state of the replication queue
                            $result = $this->validate_the_replication_queue($bid, $dest);
                            if (is_array($result) && isset($result['error'])) {
                                return $result;
                            }
                            // Add the backup to the replication queue for importing
                            $result = $this->add_import_to_replication_queue($bid, $dest);
                            if (is_array($result) && isset($result['error'])) {
                                return $result;
                            }
                            // Success, update the audit log.
                            $this->BP->send_notification(213, $dest, $bid, $dest, $system_name);
                        } else {
                            $result = array();
                            $result['error'] = 500;
                            $result['message']= 'Missing input: \'target\' is a required input.';
                        }
                    } else {
                        $result = array();
                        $result['error'] = 500;
                        $result['message']= 'Missing input: \'backup_id\' is a required input.';
                    }
                    break;
                case 'add':
                    if (isset($data['backup_id'])){
                        $bid = (int)$data['backup_id'];
                        if (isset($data['target'])){
                            $target = $data['target'];
                            $result = $this->BP->add_to_replication_queue($bid, $target, $sid );
                        } else {
                            $result = array();
                            $result['error'] = 500;
                            $result['message']= 'Missing input: \'target\' is a required input.';
                        }
                    } else {
                        $result = array();
                        $result['error'] = 500;
                        $result['message']= 'Missing input: \'backup_id\' is a required input.';
                    }
                    break;
            }
        }
        return $result;
    }

    private function post_replication_source($data, $sid = NULL)
    {
        $status = false;
        if ($data !== NULL and array_key_exists('request_id', $data) and array_key_exists('accept', $data))
        {
            $otherInputsArray = array();

            if (array_key_exists('message', $data))
            {
                $otherInputsArray['message'] = $data['message'];
            }
            if (array_key_exists('storage_id', $data))
            {
                $otherInputsArray['storage_id'] = (int)$data['storage_id'];
            }

            $otherInputsArray['accept'] = (bool)$data['accept'];

            $status = $this->BP->post_replication_source((int)$data['request_id'], $otherInputsArray);
        }
        else
        {
            $status = array();
            $status['error'] = 500;
            $status['message']= 'Missing inputs: \'request_id\' and \'accept\' are required inputs.';
        }
        return $status;
    }

    private function post_replication_target($data, $sid = NULL)
    {
        $status = false;
        if ($data !== NULL and array_key_exists('type', $data)) {
            $type = $data['type'];
            $options = array();
            $sid = ( ($sid === NULL or $sid === false) and array_key_exists('system_id', $data) ) ? $data['system_id'] : $sid;
            if ($type === Constants::BACKUP_COPY_TARGET_TYPE_APPLIANCE)
            {
                if (array_key_exists('target', $data))
                {
                    $targetHostname = $data['target'];
                    $targetNameInSystemsTable = $targetHostname;
                    $options['target'] = $targetHostname;
                    $options['insecure'] = ( array_key_exists('insecure', $data) and (int)$data['insecure'] === 1 );
                    if (array_key_exists('storage_id', $data))
                    {
                        $options['storage_id'] =  (int)$data['storage_id'];
                    }

                    $hostnameIsCorrect_applianceToAppliance = false;
                    $targetHostnameHostInfo = $this->BP->get_host_info($targetHostname, $sid);
                    if ( array_key_exists('ip', $data) and $data['ip'] !== '' )
                    {
                        $ipAddress = $data['ip'];
                        $ipAddressHostInfo = $this->BP->get_host_info($ipAddress, $sid);
                    }
                    else
                    {
                        $ipAddress = false;
                        $ipAddressHostInfo = false;
                    }

                    if ( $targetHostnameHostInfo !== false )
                    {
                        if ( $ipAddress === false or $targetHostnameHostInfo === $ipAddressHostInfo )
                        {
                            $targetNameInSystemsTable = $targetHostname;
                            $hostnameIsCorrect_applianceToAppliance = true;
                        }
                        elseif( $ipAddressHostInfo !== false and $targetHostnameHostInfo !== $ipAddressHostInfo )
                        {
                            // The two host entries do not agree
                            $status = array();
                            $status['error'] = 500;
                            $status['message']= 'Incorrect inputs: \'target\' maps to host file entry where the hostname is '.$ipAddressHostInfo['name'].' and the ip address is '.$ipAddressHostInfo['ip'].', but \'ip\' maps to host file entry where the hostname is '.$ipAddressHostInfo['name'].' and the ip address is '.$ipAddressHostInfo['ip'].'.  Please resolve the hosts file issue in /etc/hosts.';
                        }
                        else
                        {
                            // There is not a host entry for the given ip address, but there is already an ip associated with the hostname
                            $status = array();
                            $status['error'] = 500;
                            $status['message']= 'Incorrect inputs: \'target\' maps to host file entry where the hostname is '.$targetHostnameHostInfo['name'].' and the ip address is '.$targetHostnameHostInfo['ip'].', but \'ip\' was given as '.$ipAddress;
                        }
                    }
                    elseif ( $ipAddressHostInfo !== false )
                    {
                        // There is not a host entry for the given hostname, but there is already a hostname associated with the ip
                        $status = array();
                        $status['error'] = 500;
                        $status['message']= 'Incorrect inputs: \'ip\' maps to host file entry where the hostname is '.$ipAddressHostInfo['name'].' and the ip address is '.$ipAddressHostInfo['ip'].', but \'target\' was given as '.$targetHostname;
                    }
                    elseif ($ipAddress !== false)
                    {
                        $saveTargetHostInfoOptionsArray = array('ip' => $ipAddress);
                        if ( strpos( $targetHostname, '.' ) !== false )
                        {
                            $targetHostnameFQDN_array = explode( '.', $targetHostname, 2);
                            $saveTargetHostInfoOptionsArray['long_name'] = $targetHostname;
                            $saveTargetHostInfoOptionsArray['name'] = $targetHostnameFQDN_array[0];
                        }
                        else
                        {
                            $saveTargetHostInfoOptionsArray['name'] = $targetHostname;
                        }
                        $targetNameInSystemsTable = $saveTargetHostInfoOptionsArray['name'];

                        if ( $this->BP->save_host_info( $saveTargetHostInfoOptionsArray , $sid) === true )
                        {
                            $hostnameIsCorrect_applianceToAppliance = true;
                        }
                    }
                    else // No ip address is given and the 'target' is not in the hosts file, $targetNameInSystemsTable is $targetHostname
                    {
                        $hostnameIsCorrect_applianceToAppliance = true;
                    }

                    // If false, the status is false and the failure from bp_save_host_info or the various messages will be populated
                    if ( $hostnameIsCorrect_applianceToAppliance === true )
                    {
                        $continueWithReplicationSetup = true;

                        //Check the various Replication Configurations to see what is allowed
                        $localSystemID = $this->BP->get_local_system_id();
                        $localSystemName = $this->functions->getSystemNameFromID( $localSystemID );
                        if ( $sid === false or $sid === NULL or $sid === $localSystemID )
                        {
                            //This API is being run locally.
                            $systemsArray = $this->functions->getSystems(true, false, false);
                            foreach ( $systemsArray as $system )
                            {
                                if ( $targetNameInSystemsTable === $system['name'] )
                                {
                                    switch ( $system['role'] )
                                    {
                                        case Constants::SYSTEM_ROLE_NON_MANAGED_REPLICATION_SOURCE:
                                            //Do Cross Replication Setup
                                            // Not sure whether to use $targetHostname or $targetNameInSystemsTable
                                            $status = $this->setup_cross_replication($localSystemID, $localSystemName, $system['id'], $targetHostname, $data);
                                            $continueWithReplicationSetup = false;
                                            break;
                                        case Constants::SYSTEM_ROLE_MANAGED_DPU:
                                            // Disallow Regular Replication setup - UNIBP-7715 - The managed system is trying to be the target for the manager.
                                            $status = array();
                                            $status['error'] = 500;
                                            $status['message'] = 'This system is currently managing '.$targetHostname.'.  In order for '.$targetHostname.' to be a backup copy target for this system, first remove '.$targetHostname.' as a managed system, then proceed with backup copy setup.';
                                            $continueWithReplicationSetup = false;
                                            break;
                                        case Constants::SYSTEM_ROLE_REPLICATION_SOURCE:
                                            //Do Cross Replication Setup, but this system already manages the other one
                                            // Proceed with Cross Replicaiton Setup - B manages A (local system is B)
                                            // Not sure whether to use $targetHostname or $targetNameInSystemsTable
                                            $status = $this->setup_cross_replication($localSystemID, $localSystemName, $system['id'], $targetHostname, $data, true);
                                            $continueWithReplicationSetup = false;
                                            break;
                                    }
                                    // Only one system can have a matching name, so break out of the loop
                                    break;
                                }
                            }
                        }
                        else
                        {
                            // This API is being called remotely.  We cannot tell if they are trying to setup Cross-Replication on remote calls, unless they are trying to add the local system as the target
                            $systemsArray = $this->functions->getSystems(true, $sid);  // This is only the local systems table entry for the remote system that this is being called for
                            foreach ( $systemsArray as $system )  //There is only one system in this array
                            {
                                switch ( $system['role'] )
                                {
                                    case Constants::SYSTEM_ROLE_NON_MANAGED_REPLICATION_SOURCE:
                                    case Constants::SYSTEM_ROLE_REPLICATION_SOURCE:
                                        //This system is already replicating, so it will fail because it can only replicate to one place. Let it call post_replication_target anyway to get the failure from the core
                                        break;
                                    case Constants::SYSTEM_ROLE_MANAGED_DPU:
                                        if ( $targetNameInSystemsTable === $localSystemName )
                                        {
                                            // The remote managed system is requesting the local system to be the target
                                            // Check to see if the local system is a replication source of the remote managed system
                                            $replication_targets = $this->BP->get_replication_targets($localSystemID);
                                            if ( $replication_targets !== false )
                                            {
                                                foreach ( $replication_targets as $replication_target )
                                                {
                                                    // If none of the targets match, then proceed regularly with replication setup
                                                    if ( $system['name'] === $replication_target['host'] )
                                                    {
                                                        // They are trying to set the remote system as the Source and the local system as the Target.  Also, the remote system is already being managed by the local system
                                                        // Check to see what the replication status is of the local system replicating to the remote managed system
                                                        switch ( $replication_target['status'] )
                                                        {
                                                            case Constants::SYSTEM_STATUS_ACCEPTED:
                                                            case Constants::SYSTEM_STATUS_PENDING:
                                                                // Disallow Cross Replication setup - UNIBP-8061 - There is a pending replication configuration between the two systems.  Wait for the pending request to resolve before continuing with cross-replication setup.
                                                                $status = array();
                                                                $status['error'] = 500;
                                                                $status['message'] = 'There is currently a request for '.$system['name'].' to be a backup copy target for '.$localSystemName.'.  Please wait for this request to be resolved before trying to setup '.$localSystemName.' as a backup copy target for '.$system['name'].'.';
                                                                $continueWithReplicationSetup = false;
                                                                break;
                                                            case Constants::SYSTEM_STATUS_FAILED:
                                                            case Constants::SYSTEM_STATUS_REJECTED:
                                                                // Replication is not already configured from the local system to the remote system, so proceed with regular replication setup.
                                                                // $continueWithReplicationSetup is true
                                                                break;
                                                            case Constants::SYSTEM_STATUS_COMPLETE:
                                                                // Replication is already configured from the local system to the remote system
                                                                // Proceed with Cross Replication Setup - A manages B (local system is A) - B does not need to managed A - only configure B to replicate to A - could be confusing that B does not manage A
                                                                // Simply use bp_configure_replication to have the remote B replicate to the local A
                                                                //$status = $this->setup_cross_replication();
                                                                //$continueWithReplicationSetup = false;
                                                                break;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        break;
                                }
                            }
                        }

                        if ( $continueWithReplicationSetup === true )
                        {
                            $securityCheckPassed = false;
                            // Fail if the secure option is being used, but a secure connection is not established
                            if ( $options['insecure'] === true )
                            {
                                $securityCheckPassed = true;
                            }
                            else
                            {
                                $ch = curl_init();
                                $url = "https://" . $targetHostname;
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_FAILONERROR, 1);
                                curl_setopt($ch, CURLOPT_POST, 1);
                                curl_setopt($ch, CURLOPT_HEADER, 0);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                curl_setopt($ch, CURLOPT_VERBOSE, 0);

                                $ret = curl_exec($ch);
                                if ($ret == false)
                                {
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

                                    $ret = curl_exec($ch);
                                    if ($ret == false)
                                    {
                                        //Security doesn't matter here, but it will probably still fail.  Proceed to post_replication_target to get the error from the core, so that it easier to diagnose
                                        $securityCheckPassed = true;
                                    }
                                    else
                                    {
                                        // It works insecurely, so the UI should ask the user to retry with the insecure option
                                        $securityCheckPassed = false;
                                    }
                                }
                                else
                                {
                                    $securityCheckPassed = true;
                                }
                                curl_close($ch);
                            }

                            if ( $securityCheckPassed === true )
                            {
                                $status = $this->BP->post_replication_target( $type, $options, $sid );
                            }
                            else
                            {
                                $status = array();
                                $status['error'] = 412;  //Precondition Failed - the user should have setup certificates or used the insecure option
                                $status['error_code'] = Constants::ERROR_CODE_REPLICATION_FAILED_DUE_TO_INSECURE_CONNECTION;
                                $status['message']= 'The security certificate for '.$targetHostname.' is not trusted by your Unitrends Appliance.  This may be caused by a misconfiguration, an imposter intercepting your connection, or the appropriate certificates have not been installed on the target Unitrends Appliance.';
                            }
                        }
                    }
                }
                else
                {
                    $status = array();
                    $status['error'] = 500;
                    $status['message']= 'Missing inputs: when \'type\' is '.Constants::BACKUP_COPY_TARGET_TYPE_APPLIANCE.', \'target\' is a required input.';
                }
            }
            elseif ($type === Constants::BACKUP_COPY_TARGET_TYPE_UNITRENDS_CLOUD)
            {
                if (array_key_exists('auth_code', $data))
                {
                    $authCodeArray = ( explode('^', base64_decode($data['auth_code']) ) );
                    if ( count($authCodeArray) >=5 )
                    {
                        $currentHostnameArray = $this->BP->get_hostname($sid);
                        $assetTag = $this->BP->get_asset_tag($sid);
                        if ( $currentHostnameArray !== false and $assetTag !== false )
                        {
                            $hostnameIsCorrect = true;

                            $lastSegmentOfAssetTag = array_pop( explode('-', $assetTag) );
                            $firstPartOfNewHostname = $currentHostnameArray['name'];
                            if ( strpos($firstPartOfNewHostname, $lastSegmentOfAssetTag) === false )
                            {
                                $changeHostnameOptionsArray = array( 'keep_alias' => true );

                                $currentHostnameLength = strlen($firstPartOfNewHostname);
                                $lastSegmentAndHyphenLength = strlen($lastSegmentOfAssetTag) + 1;
                                $maximumRemainingCharactersInHostname = 31 - ($currentHostnameLength + $lastSegmentAndHyphenLength);

                                if ( array_key_exists( 'long_name', $currentHostnameArray ) )
                                {
                                    $FQDN_array = explode( '.', $currentHostnameArray['long_name'], 2 );
                                    //Maximum of FQDN is 255,  the period is 1, then the new hostname is part, and the rest of the FQDN is the last part.  Ensure that the FQDN is the right size
                                    $maximumRemainingCharactersInHostname = min( $maximumRemainingCharactersInHostname, (254 - ($currentHostnameLength + $lastSegmentAndHyphenLength + strlen($FQDN_array[1]))) );
                                    if ( $maximumRemainingCharactersInHostname < 0 and $maximumRemainingCharactersInHostname > -31 )
                                    {
                                        $firstPartOfNewHostname = substr( $firstPartOfNewHostname, 0, $maximumRemainingCharactersInHostname );
                                        $maximumRemainingCharactersInHostname = 0; //Setting this to 0 prevents the substr from happening twice.
                                    }
                                    $changeHostnameOptionsArray['long_name'] = $firstPartOfNewHostname.'-'.$lastSegmentOfAssetTag.'.'.$FQDN_array[1];
                                }

                                if ( $maximumRemainingCharactersInHostname < 0 and $maximumRemainingCharactersInHostname > -31 )
                                {
                                    $firstPartOfNewHostname = substr( $firstPartOfNewHostname, 0, $maximumRemainingCharactersInHostname );
                                }

                                $hostnameIsCorrect = $this->BP->change_hostname( ($firstPartOfNewHostname.'-'.$lastSegmentOfAssetTag), $changeHostnameOptionsArray, $sid );
                            }

                            if ( $hostnameIsCorrect !== false )
                            {
                                $targetHostname = $authCodeArray[0];
                                $options['target'] = $targetHostname;
                                $options['https_port'] = (int)$authCodeArray[2];
                                $options['authcode'] = $authCodeArray[3];
                                if ($authCodeArray[4] !== '')
                                {
                                    $options['storage_id'] = (int)$authCodeArray[4];
                                }

                                $hostNameAlreadyExists = false;
                                $hosts = $this->BP->get_host_list($sid);
                                if ($hosts !== false)
                                {
                                    foreach ($hosts as $name)
                                    {
                                        if ($name == $authCodeArray[0])
                                        {
                                            $hostNameAlreadyExists = true;
                                            break;
                                        }
                                    }

                                    if ($hostNameAlreadyExists === false)
                                    {
                                        $saveTargetHostInfoOptionsArray = array('ip' => $authCodeArray[1]);
                                        if ( strpos( $targetHostname, '.' ) !== false )
                                        {
                                            $targetHostnameFQDN_array = explode( '.', $targetHostname, 2);
                                            $saveTargetHostInfoOptionsArray['long_name'] = $targetHostname;
                                            $saveTargetHostInfoOptionsArray['name'] = $targetHostnameFQDN_array[0];
                                        }
                                        else
                                        {
                                            $saveTargetHostInfoOptionsArray['name'] = $targetHostname;
                                        }

                                        if ($this->BP->save_host_info($saveTargetHostInfoOptionsArray, $sid) === true)
                                        {
                                            $status = $this->BP->post_replication_target($type, $options, $sid);
                                        }
                                    }
                                    else
                                    {
                                        $status = $this->BP->post_replication_target($type, $options, $sid);
                                    }
                                }
                            }
                        }
                    }
                    else
                    {
                        $status = array();
                        $status['error'] = 500;
                        $status['message']= 'The input \'auth_code\' is not in the correct format.';
                    }
                }
                else
                {
                    $status = array();
                    $status['error'] = 500;
                    $status['message']= 'Missing inputs: when \'type\' is '.Constants::BACKUP_COPY_TARGET_TYPE_UNITRENDS_CLOUD.', \'auth_code\' is a required input.';
                }
            }
            else
            {
                $status = array();
                $status['error'] = 500;
                $status['message']= 'This function only currently supports '.Constants::BACKUP_COPY_TARGET_TYPE_APPLIANCE;
                $status['message'].= ' and '.Constants::BACKUP_COPY_TARGET_TYPE_UNITRENDS_CLOUD.' as inputs for \'type\'.';
            }
        }
        else
        {
            $status = array();
            $status['error'] = 500;
            $status['message']= 'Missing inputs: \'type\' is a required input.';
        }
        return $status;
    }

    // $data should be an array with include_rejected in it
    public function getReplicationPending($include_rejected = false, $returnFormatShouldBeOrthogonalizedToMatchGetSystems = false)
    {
        $result = false;

        $pending_sources = $this->BP->get_replication_pending($include_rejected);
        if ( $pending_sources !== false )
        {
            if ( $returnFormatShouldBeOrthogonalizedToMatchGetSystems === true )
            {
                $result = array();
                foreach ( $pending_sources as $pending_system  )
                {
                    $temp_system_array = array();
                    if ( array_key_exists('system_id', $pending_system) )
                    {
                        $temp_system_array['id'] = $pending_system['system_id'];
                    }
                    else
                    {
                        $temp_system_array['id'] = -( (int) $pending_system['request_id']);
                    }
                    $temp_system_array['is_pending'] = true;
                    $temp_system_array['request_id'] = $pending_system['request_id'];
                    $temp_system_array['name'] = $pending_system['host'];
                    $temp_system_array['asset_tag'] = $pending_system['asset_tag'];
                    $temp_system_array['role'] = Constants::SYSTEM_ROLE_DISPLAY_NAME_PENDING_REPLICATION_SOURCE;
                    $temp_system_array['local'] = false;
                    $temp_system_array['replicating'] = ( $pending_system['status'] === Constants::SYSTEM_STATUS_ACCEPTED );
                    $temp_system_array['total_mb_size'] = 0;
                    $temp_system_array['total_mb_free'] = 0;
                    $temp_system_array['status'] = $pending_system['status'];
                    $temp_system_array['created'] = $pending_system['host'];
                    if ( array_key_exists('updated', $pending_system) )
                    {
                        $temp_system_array['updated'] = $pending_system['updated'];
                    }
                    if ( array_key_exists('message', $pending_system) )
                    {
                        $temp_system_array['message'] = $pending_system['message'];
                    }

                    if ( $pending_system['status'] !== Constants::SYSTEM_STATUS_ACCEPTED )
                    {
                        $result[] = $temp_system_array;
                    }
                }
            }
            else
            {
                $result = array('pending' => array_values($pending_sources));
            }
        }
        return $result;
    }

    private function get_replication_queue($filter, $systemID)
    {
        require_once('function.lib.php');
        $functions = new Functions($this->BP);

        $replicationQueue = array();
        $activeEntries = array();
        $inactiveEntries = array();
        $filterArray = array('start_position' => -1,
            'max_items' => 50,
            'include_failed' => false,
            'include_active' => true
        );
        $queue = $this->BP->get_replication_queue($filterArray, $systemID);

        if ($queue !== false) {
            $tempQueue = array();
            $tempQueue['start_position'] = 1;
            $tempQueue['total_inactive'] = $queue['total_count'];

            $backup_status_result_format = array();
            $backup_status_result_format['system_id'] = $systemID;

            $activeData = $queue['active_entries'];
            foreach ($activeData as $active) {
                $id = 0;
                $finalSize = 0;
                $dataWritten = 0;
                $name = "";
                $phaseStartDate = "";
                $phaseElapsedTime = "";
                $elapsedTime = 0;
                $operations = $this->BP->get_operation_list("", $systemID);
                $operationsList = $this->getFilteredOperation($operations, $active['backup_no']);
                if ($operationsList !== false) {
                    $id = $operationsList['id'];
                    $name = $this->getFormattedName($operationsList['name']);
                    $finalSize = $operationsList['final_size'];
                    $dataWritten = $operationsList['current_size'];
                    $phaseStartDate = date($functions::DATE_TIME_FORMAT_US, $operationsList['start_time']);

                    $elapsedTime = time() - $operationsList['start_time'];
                    $phaseElapsedTime = $this->getFormattedTime($elapsedTime, false);
                }

                $tempActive = array();
                $tempActive['id'] = $id;   //Operation's ID
                $tempActive['queue_position'] = $active['queue_position'];
                $tempActive['client_name'] = $active['client_name'];
                $tempActive['instance_name'] = $active['instance_name'];
                $tempActive['system_name'] = $functions->getSystemNameFromID($systemID);
                $tempActive['type'] = 'replication';
                //Needs to be defined
                $tempActive['schedule_name'] = 'Unknown';
                $tempActive['mode'] = $functions->getBackupTypeDisplayName($active['type']);
                $tempActive['sid'] = $systemID;
                $tempActive['name'] = $name;   //Operation's Name

                $tempActive['target'] = $active['target_name'];
                $tempActive['backup_id'] = $active['backup_no'];
                $tempActive['size'] = $finalSize;
                $tempActive['data_written'] = $dataWritten;
                $tempActive['start_date'] = date($functions::DATE_TIME_FORMAT_US, $active['rep_start']);

                $duration = time() - $active['rep_start'];
                // time since replication started
                $tempActive['duration'] = $this->getFormattedTime($duration, false);

                if ($dataWritten == 0 && $duration > 0) {
                    $tempActive['percent_complete'] = 'In Progress';
                } else {
                    $percentComplete = ($dataWritten / $finalSize) * 100;
                    $percentComplete = round($percentComplete, 2);
                    $tempActive['percent_complete'] = $percentComplete . '%';
                }
                //time since phase started
                $tempActive['phase_start_date'] = $phaseStartDate;

                //data written is 0, phase_completion is returned as 'Calculating...' or 'In Progress'
                if($dataWritten == 0) {
                    if ($duration > 0) {
                        $tempActive['phase_completion'] = 'In Progress';
                    } else {
                        $tempActive['phase_completion'] = 'Calculating...';
                    }
                } else {
                    $totalTime = (intval(($finalSize / $dataWritten) * $elapsedTime));
                    $phaseCompletion = $totalTime - $elapsedTime;
                    $tempActive['phase_completion'] = $this->getFormattedTime($phaseCompletion, true);
                }

                $tempActive['phase_elapsed_time'] = $phaseElapsedTime;
                $tempActive['status'] = 'running';
                $tempActive['suspendable'] = false;
                $tempActive['cancellable'] = true;

                $tempQueue['active'][] = $tempActive;
            }

            $inactiveData = $queue['queued_entries'];
            foreach ($inactiveData as $inactive) {
                $tempInactive = array();
                $tempInactive['queue_position'] = $inactive['queue_position'];
                $tempInactive['target'] = $inactive['target_name'];
                $tempInactive['backup_id'] = $inactive['backup_no'];
                $tempInactive['type'] = $functions->getBackupTypeDisplayName($inactive['type']);
                $tempInactive['name'] = $inactive['client_name'];
                $tempInactive['instance_name'] = $inactive['instance_name'];

                $backup_status_result_format['backup_ids'] = $inactive['backup_no'];
                $backupStatus = $this->BP->get_backup_status($backup_status_result_format);
                if ($backupStatus !== false) {
                    $tempInactive['size'] = $backupStatus[0]['size'];
                    $tempInactive['start_date'] = date($functions::DATE_TIME_FORMAT_US, $backupStatus[0]['start_time']);
                }
                $tempInactive['status'] = $this->getStatusString($inactive['status']);
                $tempQueue['inactive'][] = $tempInactive;
            }

            if ($tempQueue['active'] !== null) {
                $activeEntries = $tempQueue['active'];
            }
            if ($tempQueue['inactive'] !== null) {
                $inactiveEntries = $tempQueue['inactive'];
            }

            switch($filter) {
                case 'active':
                    $replicationQueue['active'][] = $activeEntries;
                    break;
                case 'inactive':
                    $replicationQueue['inactive'][] = $inactiveEntries;
                    break;
                default:
                    $replicationQueue['data'][] = $tempQueue;
                    break;
            }
        }

        return $replicationQueue;
    }

    private function getFilteredOperation($operations, $backupID)
    {
        $newOperations = array();
        foreach ($operations as $operation) {
            if ($operation['backup_id'] === $backupID) {
                $newOperations = $operation;
            }
        }
        return $newOperations;
    }

    private function getFormattedName($field)
    {
        $name = "";
        switch ($field) {
            case "prepare":
                // on source side
                $name = "Prepare (1/4)";
                break;
            case "replicate":
                // on source side
                $name = "Replicate (2/4)";
                break;
            case "wait":
                // on source side
                $name = "Target Processing (4/4)";
                break;
            case "rebuild":
                // on target side
                $name = "Rebuild (4/4)";
                break;
            case "no_activity":
                $name = "No Activity";
                break;
            case "processing":
                // on source side, after the replicate state but before the wait state
                $name = "Processing (3/4)";
                break;
        }

        return $name;
    }

    private function getStatusString($stat)
    {
        switch ($stat) {
            case 1:
                $status = "Needed";
                break;
            case 2:
                $status = "Done";
                break;
            case 4:
                $status = "Failed";
                break;
            case 8:
                $status = "Aborted";
                break;
            case 16:
                $status = "In Progress";
                break;
            case 32:
                $status = "Terminated";
                break;
            default:
                $status = "";
                break;
        }

        return $status;
    }

    /*
     * If 'inWords' is true, returns time in the format of "1d 2h 3m".
     * If false, returns time in the format of "00:00:00" (h:m:s).
     */
    private function getFormattedTime($time, $inWords)
    {
        $compTime = "";

        $days = intval(intval(($time) / (3600*24)));
        if($days > 0 && $inWords === true) {
            $compTime .= $days . 'd ';
        } elseif ($inWords === false) {
            $compTime = "";
        }
        // get the hours
        $hours = (intval(($time) / 3600)) % 24;
        if($hours > 0 && $inWords === true) {
            $compTime .= $hours . 'h ';
        } elseif ($inWords === false) {
            if($hours > 9) {
                $compTime .= $hours . ':';
            } elseif($hours < 10 && $hours > 0) {
                $compTime .= '0' . $hours . ':';
            } else {
                $compTime .= '00:';
            }
        }
        // get the minutes
        $minutes = (intval(($time) / 60)) % 60;
        if($minutes > 0 && $inWords === true) {
                $compTime .= $minutes . 'm';
        } elseif ($inWords === false) {
            if($minutes > 9) {
                $compTime .= $minutes . ':';
            } elseif($minutes < 10 && $minutes > 0) {
                $compTime .= '0' . $minutes . ':';
            } else {
                $compTime .= '00:';
            }
        }
        //less than a minute
        if($inWords && !($days > 0 || $hours > 0 || $minutes > 0)) {
            $compTime .= '<1min';
        } elseif ($inWords === false) {
            $seconds = (intval(($time) / 60));
            if($seconds > 9) {
                $compTime .= $seconds;
            } elseif($seconds < 10 && $seconds > 0) {
                $compTime .= '0' . $seconds;
            } else {
                $compTime .= '00';
            }
        }
        return $compTime;
    }

    public function put($which,$filter, $inputArray, $sid) {
        if($sid === false) {
            $systemID = $this->BP->get_local_system_id();
        } else {
            $systemID = (int)$sid;
        }
        switch($which[0]){
            case "config":
                $total = 0;
                $successes = 0;
                if(isset($inputArray['max_concurrent'])){
                    $maxConcurrent=$this->BP->set_ini_value("Replication","MaxConcurrent",$inputArray['max_concurrent'],$systemID);
                    $total++;
                    if($maxConcurrent !== false) {
                        $successes++;
                    } else {
                        $data['message'] = "Max Concurrent failed to update: " . $this->BP->getError();
                    }
                }
                if(isset($inputArray['report_mail_to'])){
                    $reportMailto=$this->BP->set_ini_value("Replication","ReportMailTo",$inputArray['report_mail_to'],$systemID);
                    $total++;
                    if($reportMailto !== false) {
                        $successes++;
                    } else {
                        if(isset($data['message'])) {
                            $data['message'] .= ", Report Mail To failed to update: " . $this->BP->getError();
                        } else {
                            $data['message'] = "Report Mail To failed to update: " . $this->BP->getError();
                        }
                    }
                }
                if(isset($inputArray['report_time'])){
                    // $reportTime=$this->BP->set_ini_value("Replication","ReportTime",$inputArray['report_time'],$systemID);
                    $time = explode(':', $inputArray['report_time']);
                    $reportTime=$this->BP->save_replication_report_time((int)$time[0], (int)$time[1], $systemID);
                    $total++;
                    if($reportTime !== false) {
                        $successes++;
                    } else {
                        if(isset($data['message'])) {
                            $data['message'] .= ", Report Time failed to update: " . $this->BP->getError();
                        } else {
                            $data['message'] = "Report Time failed to update: " . $this->BP->getError();
                        }
                    }
                }
                if (isset($inputArray['queue_value'])){
                    $queueValue = $inputArray['queue_value'];
                    $queueScheme =$this->BP->set_ini_value("Replication","QueueScheme",$queueValue,$systemID);
                    $total++;
                    if($queueScheme !== false) {
                        $successes++;
                    } else {
                        $data['message'] = "Queue Scheme failed to update: " . $this->BP->getError();
                    }
                }
                if($successes == 0 and $successes != $total) {
                    $data['error'] = 500;
                } else if(isset($data['message'])) {
                    $data = array("result" => $data);
                } else if ($successes == $total) {
                    $data = true;
                }
                break;

            case "suspend":
                switch ($which[1]) {
                    case "source":
                        $strSourceIDs = "";
                        if (!empty($inputArray) && $inputArray !== null) {
                            if (isset($inputArray['source_id'])) {
                                 $strSourceIDs = ((string)$inputArray['source_id']);
                            } else {
                                $strSourceIDs = implode(",", $inputArray);
                            }
                            $data = $this->BP->suspend_replication($strSourceIDs);
                        } else {
                            $data = array();
                            $data['error'] = 500;
                            $data['message'] = 'Inputs: at least one source_id must be specified';
                        }
                        break;
                    case "target":
                        if (isset($inputArray['target_hostname']) ) {
                            // Suspend: Get the current value and save it as the "previous" value.
                            $currentMaximumConcurrentReplications = $this->BP->get_ini_value(Constants::MASTER_INI_SECTION_REPLICATION, Constants::MASTER_INI_REPLICATION_MAXIMUM_CONCURRENT, $sid);
                            if ($currentMaximumConcurrentReplications !== false and $currentMaximumConcurrentReplications != 0) {
                                $result = $this->BP->set_ini_value(Constants::MASTER_INI_SECTION_REPLICATION, Constants::MASTER_INI_REPLICATION_PREVIOUS_MAXIMUM_CONCURRENT, $currentMaximumConcurrentReplications, $sid);
                                if ($result === false) {
                                    $error = $this->BP->getError();
                                }
                            }
                            $data = $this->BP->set_ini_value(Constants::MASTER_INI_SECTION_REPLICATION, Constants::MASTER_INI_REPLICATION_MAXIMUM_CONCURRENT, 0, $sid);
                        } else {
                            $data = array();
                            $data['error'] = 500;
                            $data['message'] = 'Inputs: \'target_hostname\' must be specified';
                        }
                        break;
                }
                break;

            case "restart":
                $data = $this->BP->restart_replication($sid);
                break;

            case "resume":
                switch ($which[1]){
                    case "source":
                        $strSourceIDs = "";
                        if (!empty($inputArray) && $inputArray !== null) {
                            if (isset($inputArray['source_id'])) {
                                $strSourceIDs = ((string)$inputArray['source_id']);
                            } else {
                                $strSourceIDs = implode(",", $inputArray);
                            }
                            $data = $this->BP->resume_replication($strSourceIDs);
                        } else {
                            $data = array();
                            $data['error'] = 500;
                            $data['message'] = 'Inputs: \'source_hostname\' must be specified';
                        }
                        break;
                        case "target":
                            if (isset($inputArray['target_hostname']) ) {
                                // Resume: try to get the previous workers value, use default value if unattainable.
                                $currentMaximumConcurrentReplications = $this->BP->get_ini_value(Constants::MASTER_INI_SECTION_REPLICATION, Constants::MASTER_INI_REPLICATION_MAXIMUM_CONCURRENT, $sid);
                                if ( $currentMaximumConcurrentReplications === false or (int)$currentMaximumConcurrentReplications <= 0 ) {
                                    $previousMaximumConcurrentReplications = $this->BP->get_ini_value(Constants::MASTER_INI_SECTION_REPLICATION, Constants::MASTER_INI_REPLICATION_PREVIOUS_MAXIMUM_CONCURRENT, $sid);
                                    if ($previousMaximumConcurrentReplications !== false and (int)$previousMaximumConcurrentReplications > 0) {
                                        $previousMaximumConcurrentReplications = (int)$previousMaximumConcurrentReplications;
                                    } else {
                                        $previousMaximumConcurrentReplications = Constants::MASTER_INI_REPLICATION_MAXIMUM_CONCURRENT_DEFAULT;
                                    }
                                    $data = $this->BP->set_ini_value(Constants::MASTER_INI_SECTION_REPLICATION, Constants::MASTER_INI_REPLICATION_MAXIMUM_CONCURRENT, $previousMaximumConcurrentReplications, $sid);
                                }
                            } else {
                                $data = array();
                                $data['error'] = 500;
                                $data['message'] = 'Inputs: \'target_hostname\' must be specified';
                            }
                        break;
                }
                break;

            case "queue":
                if ( isset($which[1]) and $which[1] === "strategy" ) {
                    if(isset($inputArray['strategy'])){
                        if($inputArray['strategy']==="Manual"){
                            $queueScheme=$this->BP->set_ini_value("Replication","QueueScheme","0",$systemID);
                            $data=$queueScheme;
                        }
                        elseif($inputArray['strategy']==="Maximum Retention"){
                            $queueScheme=$this->BP->set_ini_value("Replication","QueueScheme","1",$systemID);
                            $data=$queueScheme;
                        }
                        else{
                            $queueScheme=$this->BP->set_ini_value("Replication","QueueScheme","2",$systemID);
                            $data=$queueScheme;
                        }
                    }
                } else {
                    $filter=array('start_position'=> -1,
                        'max_items'=> 1,
                        'include_active'=> 1,
                        'include_failed'=> 1
                    );
                    $queue=$this->BP->get_replication_queue($filter,$systemID);
                    //var_dump($inputArray);
                    if(isset($inputArray['backups']) and $inputArray['action']==="reset" and isset($queue['active_entries'])){
                        $backupTargets=$inputArray["backups"];
                        //var_dump($backupTargets);
                        foreach($queue['active_entries'] as $key=>$val){
                            $activeData=$queue['active_entries'];
                            $clientName[]=$activeData[$key]['client_name'];
                        }
                        $deleteFilter=array("backup_targets"=>$backupTargets,
                            "clients"=>$clientName,
                            "action"=>$inputArray['action']);

                        $update=$this->BP->delete_from_replication_queue($deleteFilter,$systemID);
                        $data=$update;
                    }
                    if(isset($inputArray['clients'])){

                    }
                    if(isset($inputArray['instances'])){

                    }
                }
                break;
            case "throttle":
                if( isset($inputArray['throttle'])
                    and is_array($inputArray['throttle'])
                    and count($inputArray['throttle']) > 0 )
                {
                    if(!isset($inputArray['bandwidth'])) {
                        $VaultUpstreamSpeed = $this->BP->get_ini_value("CMC","VaultUpstreamSpeed",$systemID);
                    } else {
                        $VaultUpstreamSpeed = $inputArray['bandwidth'];
                        //since we got a value from the user, we need to set the ini value to match
                        $this->BP->set_ini_value("CMC", "VaultUpstreamSpeed", $inputArray['bandwidth'], $systemID);
                        $this->BP->set_ini_value("CMC", "VaultDownstreamSpeed", $inputArray['bandwidth'], $systemID);
                    }

                    //$VaultBandwidth = $this->BP->get_ini_value("CMC","VaultBandwidth",$systemID);
                    if ( $VaultUpstreamSpeed !== false and $VaultUpstreamSpeed != null and $VaultUpstreamSpeed != "" )
                    {
                        $maxBandwidthInBits = $VaultUpstreamSpeed * 1024 / 8;  //Possibly include $VaultBandwidth at some point - Sonja, please review the edge cases here from the legacy UI to new UI
                        /*
           {
                "percentage": 50,
                "days": [1 ,2, 3, 4, 5],
                "start": 8,
                "end": 18,
           }*/
                        $dayThrottleArray = array();
                        $dayThrottleArray = array_pad($dayThrottleArray, 24, -1);
                        $weekThrottleArray = array();
                        $weekThrottleArray = array_pad($weekThrottleArray, 7, $dayThrottleArray);

                        $conflictArray = array();
                        foreach ( $inputArray['throttle'] as $throttlePeriod )
                        {
                            if ( isset($throttlePeriod['percentage'])
                                and isset($throttlePeriod['days'])
                                and isset($throttlePeriod['start'])
                                and isset($throttlePeriod['end']) )
                            {
                                foreach ( $throttlePeriod['days'] as $dayNumber )
                                {
                                    for ( $hourNumber = $throttlePeriod['start']; $hourNumber <= $throttlePeriod['end']; $hourNumber++ )
                                    {
                                        if ( $weekThrottleArray[$dayNumber][$hourNumber] === -1)
                                        {
                                            $weekThrottleArray[$dayNumber][$hourNumber] = $throttlePeriod['percentage'];
                                        }
                                        else  // There is a conflict
                                        {
                                            $conflictArrayIndex = (($dayNumber + 1) * 24) + $hourNumber;
                                            if( !array_key_exists($conflictArrayIndex, $conflictArray) )
                                            {
                                                //create an array for this conflict
                                                $conflictArray[$conflictArrayIndex] = array( 0 => $weekThrottleArray[$dayNumber][$hourNumber] );
                                            }
                                            $conflictArray[$conflictArrayIndex][] = $throttlePeriod;
                                            // If there is a conflict, show the user a warning, but enter the smaller of the two
                                            if ( $weekThrottleArray[$dayNumber][$hourNumber] > $throttlePeriod['percentage'] )
                                            {
                                                $weekThrottleArray[$dayNumber][$hourNumber] = $throttlePeriod['percentage'];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        /* Throttle String must be in format: "0:0-7=231K,0:8=47K,0:9-17=231K,0:18-21=116K,0:22-23=231K,1:0=0K,1:1-7=231K,1:8=47K,1:9-17=231K,1:1
                            8-21=116K,1:22-23=231K,2:0=0K,2:1=231K,2:2=70K,2:3-7=231K,2:8=47K,2:9-17=231K,2:18-21=116K,2:22-23=231K,3:0=0K,3:1=
                            231K,3:2=70K,3:3-7=231K,3:8=47K,3:9-17=231K,3:18-21=116K,3:22-23=231K,4:0=0K,4:1-7=231K,4:8=47K,4:9-23=231K,5:0=0K,
                            5:1-7=231K,5:8=47K,5:9-12=231K,5:13=208K,5:14-23=231K,6:0-7=231K,6:8=47K,6:9-13=231K,6:14-17=162K,6:18-23=231K"
                        */
                        $throttleString = "";

                        for($dayNumber=0; $dayNumber<7; $dayNumber++)
                        {
                            $startHourOfPeriod = -1;
                            $endHourOfPeriod = -1;
                            $percentageForPeriod = -1;
                            $dayArray = $weekThrottleArray[$dayNumber];
                            for($hourNumber=0; $hourNumber<24; $hourNumber++)
                            {
                                //Add $throttle string
                                //Check to make sure it is not -1...if -1, no throttling
                                if($percentageForPeriod !== -1) {
                                    if($dayArray[$hourNumber] == $percentageForPeriod and $hourNumber !== 23) {
                                        $endHourOfPeriod = $hourNumber;
                                    } else {
                                        if($hourNumber == 23 and $dayArray[$hourNumber] == $percentageForPeriod) {
                                            $endHourOfPeriod = $hourNumber;
                                        }
                                        //create throttle piece
                                        $throttleString .= "," . $dayNumber . ":";
                                        if($startHourOfPeriod == $endHourOfPeriod) {
                                            $throttleString .= $startHourOfPeriod . "=";
                                        } else {
                                            $throttleString .= $startHourOfPeriod . "-" . $endHourOfPeriod . "=";
                                        }
                                        $throttleString .= (int)ceil($maxBandwidthInBits * 0.01 * $percentageForPeriod) . "K";

                                        if($hourNumber == 23 and $dayArray[$hourNumber] !== $percentageForPeriod and $dayArray[$hourNumber] !== -1) {
                                            //case of last hour of the day having different throttle than previous hour
                                            $throttleString .= "," . $dayNumber . ":" . $hourNumber . "=" . (int)ceil($maxBandwidthInBits * 0.01 * $dayArray[$hourNumber]);
                                        }

                                        $startHourOfPeriod = $endHourOfPeriod = $hourNumber;
                                        $percentageForPeriod = $dayArray[$hourNumber];
                                    }
                                } else {
                                    //first time through for the day
                                    $startHourOfPeriod = $endHourOfPeriod = $hourNumber;
                                    $percentageForPeriod = $dayArray[$hourNumber];
                                }
                            }
                        }
                        //remove the leading comma

                        if($throttleString !== "") {
                            $throttleString = substr($throttleString, 1);
                        }
//                        var_dump($throttleString);
//                        var_dump($conflictArray);
                        //save the throttle string in the ini
                        $saveThrottle = $this->BP->set_ini_value("Replication", "BlockOutPeriods", $throttleString, $systemID);
                        if($saveThrottle !== false) {
                            //Manage conflicts, if any, and then produce an error message
                            if(count($conflictArray) > 0) {
                                $conflictString = "Warning: there were conflicts with the throttling percentages of some of the periods passed in. The lower throttle percentage was used in resolving each conflict.";
 /*                               $conflictString = "";
                                foreach($conflictArray as $hourInWeek=>$throttlePeriods) {
                                    //get the day and hour out of the array index and string it all together

                                }
*/
                                $data = array("message" => $conflictString);
                                $data = array("result" => $data);

                            } else {
                                $data = $saveThrottle;
                            }
                        } else {
                            $data = $saveThrottle;
                        }
                    }
                } else if (isset($inputArray['throttle'])
                            and is_array($inputArray['throttle'])
                            and count($inputArray['throttle']) == 0 ) {
                    //throttle array is empty, so clear out
                    $data = $this->BP->set_ini_value("Replication", "BlockOutPeriods", "", $systemID);
                }
                break;
        }
        return $data;
    }

    public function delete($which, $sid) {


        switch($which[0]){
            case 'queue':
                $sid = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();

                $filterReplication=array('start_position'=> -1,
                    'max_items'=> -1,
                    'include_active'=> 1,
                    'include_failed'=> 1
                );
                $queue=$this->BP->get_replication_queue($filterReplication,$sid);
                foreach($queue['active_entries'] as $key=>$val){
                    $activeData=$queue['active_entries'];
                    if(((int)$which[1]===$activeData[$key]['backup_no']) and $_GET['target']===$activeData[$key]['target_name']){
                        $backup[0]['backup_no']=$activeData[$key]['backup_no'];
                        $backup[0]['target_name']=$activeData[$key]['target_name'];
                    }
                }
                foreach($queue['queued_entries'] as $key=>$val){
                    $activeData=$queue['queued_entries'];
                    if(((int)$which[1]===$activeData[$key]['backup_no']) and $_GET['target']===$activeData[$key]['target_name']){
                        $backup[0]['backup_no']=$activeData[$key]['backup_no'];
                        $backup[0]['target_name']=$activeData[$key]['target_name'];
                    }
                }
                if($backup[0]['backup_no'] === NULL){
                    $result['error'] = 500;
                    $result['message']= "The backup you are trying to remove does not exist.";
                    return $result;
                }
                $deleteFilter=array("backup_targets"=>$backup,
                    "action"=>"terminate");
                $delete=$this->BP->delete_from_replication_queue($deleteFilter,$sid);
                return $delete;
                break;
            case 'target':
                $sid = ($sid === false) ? $this->BP->get_local_system_id() : $sid;
                if( isset($which[1]) )
                {
                    return $this->BP->remove_source_replication( $which[1], $sid );
                }
                else
                {
                    $result = array();
                    $result['error'] = 500;
                    $result['message']= "In order to remove a backup copy target, the target name must be provided.";
                    return $result;
                }
                break;
        }
    }

    public function delete_backup_copy($which, $sid) {


        switch($which[0]) {
            case 'target':
                if( isset($which[1]) )
                {
                    $backup_copy_id = $which[1];
                    $backup_copy_type = $backup_copy_id[0];
                    $backup_copy_id[0] = '0';
                    $backup_copy_id = (int)$backup_copy_id;
                    if ( $backup_copy_type == 'a' ) {
                        require_once('storage.php');
                        $storage = new Storage($this->BP, $sid);
                        return $storage->delete($backup_copy_id, $sid);
                    } elseif ( $backup_copy_type == 'r' ) {
                        $sid = ($sid === false) ? $this->BP->get_local_system_id() : $sid;
                        $targets = $this->BP->get_replication_targets($sid);
                        $backup_copy_target_name = '';
                        if ( $targets !== false ) {
                            foreach ( $targets as $target ) {
                                if ( $target['target_id'] === $backup_copy_id ) {
                                    $backup_copy_target_name = $target['host'];
                                    break;
                                }
                            }
                        }
                        return $this->BP->remove_source_replication( $backup_copy_target_name, $sid );
                    }
                }
                else
                {
                    $result = array();
                    $result['error'] = 500;
                    $result['message']= "In order to remove a backup copy target, the target name must be provided.";
                    return $result;
                }
                break;
        }

    }

    public function get_backup_copy($which, $sid, $systems) {
        require_once('function.lib.php');
        $functions = new Functions($this->BP);

        require_once("storage.php");
        $storage = new Storage($this->BP, $sid);

        if($sid === false) {
            $systemID = $this->BP->get_local_system_id();
        } else {
            $systemID = (int)$sid;
        }

        $status = false;
        switch($which) {
            case 'targets':
                $status = array();
                $system_name = $functions->getSystemNameFromID($systemID);
                $systemTargets = array("name" => $system_name, "id" => $systemID, "targets" => array());

                $archiveTargets = $storage->get(null, array("usage" => "archive"), $systemID, array($systemID => $system_name));

                $replicationTargets = $this->get($which, array(), array(), $systemID);
                $archiveCurrentTargets = $this->BP->get_current_archive_media($systemID);

                //combine the outputs
                if($archiveTargets !== false) {
                    // First the storage targets.
                    foreach($archiveTargets['storage'] as $target) {
                        // Update return target with current media information.
                        // Add "archive" property to archiveCurrentTargets array
                        if ($archiveCurrentTargets !== false) {
                            $archiveTarget = $this->findArchiveTarget($archiveCurrentTargets, $target['name']);
                            if ($archiveTarget !== null) {
                                // set archive to false, use storage APIs for online/offline.
                                $skipKeys = array('type');
                                $target['archive'] = false;
                                $this->updateTargetInformation($archiveTarget, $target, $skipKeys);
                            }
                        }
                        $returnTarget = $this->buildArchiveTargetOutput($target);
                        $systemTargets['targets'][] = $returnTarget;
                    }
                }
                if ($archiveCurrentTargets !== false) {
                    // This list is what is left after storage targets are matched (those have the "matched" property set).
                    // Add any unmatched targets.
                    foreach($archiveCurrentTargets as $target) {
                        if (!isset($target['matched'])) {
                            // set archive to true, use archive media APIs for online/offline.
                            $target['archive'] = true;
                            $returnTarget = $this->buildArchiveTargetOutput($target);
                            $systemTargets['targets'][] = $returnTarget;
                        }
                    }
                }
                if($replicationTargets !== false) {
                    foreach($replicationTargets['targets'] as $target) {
                        $returnTarget = array();
                        $returnTarget['name'] = $target['host'];
                        if($target['is_replication_suspended'] == true) {
                            $returnTarget['status'] = "suspended";
                        } else {
                            $returnTarget['status'] = $target['status'];
                        }
                        $returnTarget['id'] = 'r'.$target['target_id'];
                        $returnTarget['type'] = $target['target_type'];

                        //return N/A until we get a size from core
                        $returnTarget['gb_size'] = "N/A";
                        $returnTarget['gb_free'] = "N/A";
                        $returnTarget['media_label'] = "N/A";

                        $returnTarget['message'] = $target['message'];
                        $returnTarget['created'] = $target['created'];
                        if (isset($target['last_poll'])) {
                            $returnTarget['last_poll'] = $target['last_poll'];
                        }
                        if(isset($target['updated'])) {
                            $returnTarget['updated'] = $target['updated'];
                        }
                        $returnTarget['suspended'] = $target['is_replication_suspended'];
                        if(isset($target['suspend_toggled'])) {
                            $returnTarget['suspend_toggled'] = $target['suspend_toggled'];
                        }

                        $systemTargets['targets'][] = $returnTarget;
                    }
                }

                $status['data'][] = $systemTargets;

                break;

            case 'connected_targets':
                // Whether or not to include archiving targets or return only replication targets.
                $showArchiving = isset($_GET['showArchiving']) && $_GET['showArchiving'] === "false" ? false : true;;
                $replicationTargets = $this->get('targets', array(), array('include_incomplete_systems' => false, 'include_type_of_backup_copy_target' => true, 'include_name_field' => true ), $systemID);
                $archiveConnectedMedia = $showArchiving ? $this->BP->get_connected_archive_media($systemID) : array();
                if($archiveConnectedMedia !== false) {
                    $system_name = $functions->getSystemNameFromID($systemID);
                    $archiveTargets  = $storage->get(null, array("usage" => "archive"), $systemID, array($systemID => $system_name));
                    // Make the return array elements all include a critical identifier for joborders.
                    foreach ($archiveConnectedMedia as &$media) {
                        $media['target_id'] = $media['name'];
                        if ($media['type'] === 'external') {
                            $this->get_storage_info($archiveTargets, $media);
                        } else {
                            $media['id'] = $media['name'];
                        }
                        $media['gb_size'] = (float)number_format($media['mb_size']/1024, 2, '.', '');
                        $media['gb_free'] = (float)number_format($media['mb_free']/1024, 2, '.', '');
                        $media['status'] = $media['is_busy'] ? "busy" :
                            $media['is_mounted'] ? "online" :
                                $media['is_initialized'] ? "available" : "offline";
                    }
                    if ($replicationTargets !== false) {
                        $status = array('data' => array_merge($archiveConnectedMedia, $replicationTargets['targets'] ));
                    } else {
                        // If one subsystem succeeds, return error in defined format as a target.
                        $errorReplication = array('status' => 'error', 'name' => $this->BP->getError(), 'target_id' => -1);
                        $archiveConnectedMedia[] = $errorReplication;
                        $status = array('data' => $archiveConnectedMedia);
                    }
                } else {
                    if ($replicationTargets !== false) {
                        // If one subsystem succeeds, return error in defined format as a target.
                        $errorArchive = array('status' => 'error', 'name' => $this->BP->getError(), 'target_id' => -1);
                        $replicationTargets['targets'][] = $errorArchive;
                        $status = array('data' => $replicationTargets['targets']);
                    }
                }
                break;

            case 'archive_only':
                // Whether or not to include external (storage) archives.
                $includeExternal = isset($_GET['external']) ? true : false;
                $status = array();
                $system_name = $functions->getSystemNameFromID($systemID);
                $systemTargets = array("name" => $system_name, "id" => $systemID, "connected_targets" => array());

                $archiveConnectedMedia = $this->BP->get_connected_archive_media($systemID);

                if($archiveConnectedMedia !== false) {

                    //get configurable archive media to get the tape devices and merge the two arrays to weed out duplicates
                    $archiveConfigurableMedia = $this->BP->get_configurable_archive_media($systemID);

                    if($archiveConfigurableMedia !== false) {

                        // Make the return array elements all include a critical identifier for joborders.target
                        foreach ($archiveConnectedMedia as $media) {
                            if (($media['type'] !== 'external') || $includeExternal) {
                                $media['target_id'] = $media['name'];
                                $media['id'] = $media['name'];
                                if($media['type'] == 'changer' || $media['type'] == 'tape') {
                                    foreach($archiveConfigurableMedia as $configMedia) {
                                        if($media['name'] == $configMedia['name'] && $media['type'] == $configMedia['type']) {
                                            $media['status'] = $configMedia['is_available'] ? "online" : "offline";
                                            $media['is_available'] = $configMedia['is_available'];
                                        }
                                    }
                                    $media['gb_size'] = "N/A";
                                    $media['gb_free'] = "N/A";
                                } else {
                                    $media['gb_size'] = isset($media['mb_size']) ? round($media['mb_size']/1024, 2) : "N/A";
                                    $media['gb_free'] = isset($media['mb_free']) ? round($media['mb_free']/1024, 2) : "N/A";

                                   // $media['gb_size'] = $media['type'] == (float)number_format($media['mb_size'] / 1024, 2, '.', '');
                                  //  $media['gb_free'] = $media['type'] == (float)number_format($media['mb_free'] / 1024, 2, '.', '');
                                    $media['status'] = $media['is_busy'] ? "busy" :
                                        ($media['is_mounted'] ? "online" :
                                            ($media['is_initialized'] ? "ready" : "offline"));
                                }
                                $media['archive'] = true;
                                $systemTargets['connected_targets'][] = $media;
                            }
                        }

                        //merge configurable with connected
                        foreach($archiveConfigurableMedia as $configurableMedia) {
                            $connectedMatch = false;
                            foreach($archiveConnectedMedia as $connectedMedia) {
                                if($configurableMedia['name'] === $connectedMedia['name'] and $configurableMedia['type'] === $connectedMedia['type']) {
                                    $connectedMatch = true;
                                }
                            }
                            if($connectedMatch == false) {
                                //add to connected targets
                                $media = $configurableMedia;
                                $media['target_id'] = $configurableMedia['name'];
                                $media['id'] = $configurableMedia['name'];
                                $media['gb_size'] = "N/A";
                                $media['gb_free'] = "N/A";
                                $media['status'] = $configurableMedia['is_available'] ? "online": "offline";
                                $media['is_available'] = $configurableMedia['is_available'];
                                $media['archive'] = true;
                                $systemTargets['connected_targets'][] = $media;
                            }
                        }
                        $status['data'][] = $systemTargets;
                    } else {
                        global $Log;
                        $message = $this->BP->getError();
                        $Log->writeError("Cannot get archive configurable media: " . $message, true);
                        $status = array();
                        $status['error'] = 500;
                        $status['message'] = $message;
                    }
                } else {
                    global $Log;
                    $message = $this->BP->getError();
                    $Log->writeError("Cannot get archive connected media: " . $message, true);
                    $status = array();
                    $status['error'] = 500;
                    $status['message'] = $message;
                }
                break;
        }
        return $status;
    }

    private function get_storage_info($targets, &$media)
    {
        foreach ($targets['storage'] as $target) {
            if ($target['name'] == $media['name']) {
                if (isset($target['properties']['protocol'])) {
                    $media['type'] = $target['properties']['protocol'];
                }
                $media['max_size'] = isset($target['properties']['max_size']) ? $target['properties']['max_size'] : "N/A";
                $media['id'] = $target['id'];
                break;
            }
        }
    }

    /*
     * Search through the list of archive targets for a specific target name.
     * If found, set 'matched' to true for the target and return it.
     * If not found, return null.
     */
    private function findArchiveTarget(&$archiveList, $targetName) {
        $archiveTarget = null;
        foreach ($archiveList as &$target) {
            if (isset($target['name']) && ($target['name'] === $targetName)) {
                $target['matched'] = true;
                $archiveTarget = $target;
                break;
            }
        }
        return $archiveTarget;
    }

    /*
     * Copy all keys and values from $archiveTarget to $target.
     * Skip any keys that are in the $keysToSkip array.
     */
    private function updateTargetInformation($archiveTarget, &$target, $keysToSkip = array()) {
        foreach ($archiveTarget as $key => $value) {
            if (!in_array($key, $keysToSkip)) {
                $target[$key] = $archiveTarget[$key];
            }
        }
    }

    /*
     * Given a target, create and return $returnTarget to the caller.
     */
    private function buildArchiveTargetOutput($target) {
        $returnTarget = array();

        $returnTarget['name'] = $target['name'];

        if (isset($target['status'])) {
            $returnTarget['status'] = $target['status'];
        } else {
            $returnTarget['status'] = $target['is_busy'] ? "busy" :
                ($target['is_mounted'] ? "online" :
                    ($target['is_initialized'] ? "ready" : "offline"));
            // If a tape or changer archive target, set is_available to true as they are only in the list if online.
            if ($target['type'] === 'changer' || $target['type'] === 'tape') {
                $returnTarget['is_available'] = true;
            }
        }

        if (isset($target['id'])) {
            $returnTarget['id'] = 'a'.$target['id'];
        } else {
            $returnTarget['id'] = $target['name'];
        }

        if (isset($target['properties']['protocol'])) {
            $returnTarget['type'] = $target['properties']['protocol'];
        } else {
            $returnTarget['type'] = $target['type'];
        }
        if (isset($target['media_label'])) {
            if ($target['media_label'] !== "") {
                $returnTarget['media_label'] = $target['media_label'];
            } else {
                $target['media_label'] = null;
            }
        } else {
            $returnTarget['media_label'] = 'Scan For Media to Retrieve';
        }
        $returnTarget['archive'] = $target['archive'];
        $returnTarget['media_serials'] = isset($target['media_serials']) ? $target['media_serials'] : '';
        $returnTarget['is_infinite'] = $target['is_infinite'];
        $returnTarget['is_initialized'] = isset($target['is_initialized']) ? $target['is_initialized'] : true;
        $returnTarget['is_mounted'] = isset($target['is_mounted']) ? $target['is_mounted'] : true;
        $returnTarget['activity'] = isset($target['activity']) ? $target['activity'] : 'N/A';
        $returnTarget['gb_size'] = (float)number_format($target['mb_size']/1024, 2, '.', '');
        $returnTarget['gb_free'] = (float)number_format($target['mb_free']/1024, 2, '.', '');
        $returnTarget['max_size'] = isset($target['properties']['max_size']) ? $target['properties']['max_size'] : "N/A";
        $returnTarget['message'] = "";

        return $returnTarget;
    }

    private function initiateReplicationFromTarget($data, $sid) {
        $request = "POST";
        $api = "/api/replication/queue/";
        $parameters = "";
        $result = $this->functions->remoteRequest("", $request, $api, $parameters, $data, $sid);
        return $result;
    }

    private function setup_cross_replication($first_target_system_id, // System B
                                             $first_target_system_name,
                                             $second_target_system_id, // System A - This is the system id of the second target on the first target's system.  Currently the second target is just a replicating source of the first target
                                             $second_target_name,
                                             $data,
                                             $first_target_manages_second_target = false)  //System B manages A
    {
        $status = false;

        // On System B
        // System A is replicating to System B

        $continueWithCrossReplicationSetup = true;

        // Check to make sure that replication is fully configured between System A to System B and not pending

        $pending_sources = $this->BP->get_replication_pending(false);
        if ( $pending_sources !== false )
        {
            foreach ( $pending_sources as $pending_source )
            {
                if ( isset($pending_source['host']) and ($pending_source['host'] === $second_target_name) )
                {
                    // Disallow Cross Replication setup - UNIBP-8061 - There is a pending replication configuration between the two systems.  Wait for the pending request to resolve before continuing with cross-replication setup.
                    $status = array();
                    $status['error'] = 500;
                    $status['message'] = 'There is currently a request for '.$first_target_system_name.' to be a backup copy target for '.$second_target_name.'.  Please wait for this request to be resolved before trying to setup '.$second_target_name.' as a backup copy target for '.$first_target_system_name.'.';
                    $continueWithCrossReplicationSetup = false;
                    break;
                }
            }
        }

        // Check to make sure that System B is not already replicating to System A

        $replication_targets_of_the_first_target = $this->BP->get_replication_targets($first_target_system_id);
        if ( $replication_targets_of_the_first_target !== false )
        {
            foreach ( $replication_targets_of_the_first_target as $replication_target_of_the_first_target )
            {
                // If none of the targets match, then proceed regularly with cross replication setup
                if ( $second_target_name === $replication_target_of_the_first_target['host'] )
                {
                    // There is already a target entry for this request
                    // Check the status of the first request
                    switch ( $replication_target_of_the_first_target['status'] )
                    {
                        case Constants::SYSTEM_STATUS_ACCEPTED:
                        case Constants::SYSTEM_STATUS_PENDING:
                            // Disallow Cross Replication setup - UNIBP-8061 - There is a pending replication configuration between the two systems.  Wait for the pending request to resolve before continuing with cross-replication setup.
                            $status = array();
                            $status['error'] = 500;
                            $status['message'] = 'There is already a request for '.$second_target_name.' to be a backup copy target for '.$first_target_system_name.'.';
                            $continueWithCrossReplicationSetup = false;
                            break;
                        case Constants::SYSTEM_STATUS_FAILED:
                        case Constants::SYSTEM_STATUS_REJECTED:
                            // Replication is not already configured from the System B to System A
                            // $continueWithCrossReplicationSetup is true
                            break;
                        case Constants::SYSTEM_STATUS_COMPLETE:
                            // Cross-Replication is already configured
                            $status = array();
                            $status['error'] = 500;
                            $status['message'] = $second_target_name.' is already a backup copy target for '.$first_target_system_name;
                            $continueWithCrossReplicationSetup = false;
                            break;
                    }
                }
            }
        }

        if ( $continueWithCrossReplicationSetup === true )
        {
            if ( isset($data['target_username']) and isset($data['target_password']) )
            {
                $second_target_username = $data['target_username'];
                $second_target_password = $data['target_password'];
                $second_target_host_info = $this->BP->get_host_info($second_target_name, $first_target_system_id);
                if ( $second_target_host_info !== false and isset($second_target_host_info['ip']) )
                {
                    $second_target_ip = $second_target_host_info['ip'];
                    $auth_code_array = $this->get_auth_code_using_curl( $second_target_ip, $second_target_username, $second_target_password );
                    if ( $auth_code_array['result'] === true )
                    {
                        $auth_code_string = $auth_code_array['auth_code'];

                        // Check to see if System B already manages System A
                        if ( $first_target_manages_second_target !== true )
                        {
                            // If management is not already configured, System B should start managing System A, use credentials to System A to setup relationship
                            $grant_management_status = $this->functions->grantManagementToLocalSystem($second_target_ip, $second_target_username, $second_target_password);
                            if ($grant_management_status === true) {
                                $grant_management_status = $this->BP->add_mgmt_to_replication_source($second_target_system_id);
                            }
                        }
                        else
                        {
                            $grant_management_status = true;
                        }

                        if ( $grant_management_status === true )
                        {
                            $systems_list_array = $this->get_remote_systems_list_using_curl( $second_target_ip, $auth_code_string );
                            if ( $systems_list_array['result'] === true )
                            {
                                $first_target_system_id_on_the_second_target = false;

                                $second_target_already_manages_first_target = false;
                                $second_target_systems_list = $systems_list_array['systems_list'];
                                foreach ( $second_target_systems_list as $system_attached_to_second_target )
                                {
                                    if ( $system_attached_to_second_target['name'] === $first_target_system_name  and $system_attached_to_second_target['role'] === Constants::SYSTEM_ROLE_DISPLAY_NAME_MANAGED_DPU )
                                    {
                                        $first_target_system_id_on_the_second_target = $system_attached_to_second_target['id'];
                                        $second_target_already_manages_first_target = true;
                                        break;
                                    }
                                }

                                $continueWithAddingReplication = true;
                                if ( $second_target_already_manages_first_target === false )
                                {
                                    $first_target_host_info = $this->BP->get_host_info($first_target_system_name, $first_target_system_id);
                                    if ( $first_target_host_info !== false and isset($first_target_host_info['ip']) )
                                    {
                                        $management_has_been_granted = false;
                                        $list_of_systems_granted_management_access = $this->BP->get_manager_list();
                                        if ( $list_of_systems_granted_management_access !== false )
                                        {
                                            foreach ( $list_of_systems_granted_management_access as $system_granted_management )
                                            {
                                                $system_with_management_permission_host_info = $this->BP->get_host_info($system_granted_management['hostname'], $first_target_system_id);
                                                if ( $first_target_host_info === $system_with_management_permission_host_info )
                                                {
                                                    if ( $system_with_management_permission_host_info['ip'] === $system_granted_management['ip'] )
                                                    {
                                                        $management_has_been_granted = true;
                                                    }
                                                    else
                                                    {
                                                        $revoke_mgr_status = $this->BP->revoke_mgr($second_target_name);
                                                    }
                                                }
                                            }
                                        }

                                        if ( $management_has_been_granted === false )
                                        {
                                            $grant_mgr_status = $this->BP->grant_mgr($second_target_name);
                                        }
                                        $add_management_status = $this->add_remote_management_using_curl( $second_target_ip, $auth_code_string, $first_target_system_name );
                                        if ( $add_management_status === true )
                                    {
                                        $systems_list_array_to_get_system_id = $this->get_remote_systems_list_using_curl( $second_target_ip, $auth_code_string );
                                        if ( $systems_list_array_to_get_system_id['result'] === true )
                                        {
                                            $second_target_systems_list_to_get_system_id = $systems_list_array_to_get_system_id['systems_list'];
                                            foreach ( $second_target_systems_list_to_get_system_id as $system_attached_to_second_target_to_get_system_id )
                                            {
                                                if ( $system_attached_to_second_target_to_get_system_id['name'] === $first_target_system_name )
                                                {
                                                    $first_target_system_id_on_the_second_target = $system_attached_to_second_target_to_get_system_id['id'];
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                        else
                                        {
                                            $continueWithAddingReplication = false;
                                            $status = $add_management_status;
                                        }
                                    }
                                }

                                if ( $continueWithAddingReplication === true )
                                {
                                    $status = $this->add_replication_using_curl( $second_target_ip, $auth_code_string, $first_target_system_id_on_the_second_target );
                                }
                            }
                            else
                            {
                                $status = $systems_list_array['result'];
                            }
                        }
                    }
                    else
                    {
                        $status = $auth_code_array['result'];
                    }
                }
            }
            else
            {
                $status = array();
                $status['error'] = 412;  //Precondition Failed - the user should have setup certificates or used the insecure option
                $status['error_code'] = Constants::ERROR_CODE_CROSS_REPLICATION_FAILED_DUE_TO_A_LACK_CREDENTIALS;
                $status['message'] = 'In order for '.$second_target_name.' to be a backup copy target for '.$first_target_system_name. ' and '.$first_target_system_name.' to be a backup copy target for '.$second_target_name.', management permission must be provided for both systems.  Please provide the credentials for '.$second_target_name.'.';
            }
        }

        return $status;
    }

    // This can be a public function, but I believe that a class is being built for this that can replace this code.  So for now, this will stay a private function.
    private function get_auth_code_using_curl( $remote_system_ip, $remote_system_username, $remote_system_password )
    {

        $remote_system_auth_code = "";

        global $Log;
        $curl_get_auth_code = curl_init();
        $url_get_auth_code = "https://" . $remote_system_ip . "/api/login";
        $remote_system_credentials_array = array("username" => $remote_system_username, "password" => $remote_system_password);
        $credentials_json_string = json_encode($remote_system_credentials_array);
        $Log->writeVariable("authenticating, url is $url_get_auth_code");
        //$Log->writeVariable("authenticating, auth is $auth_string");
        curl_setopt($curl_get_auth_code, CURLOPT_URL, $url_get_auth_code);
        curl_setopt($curl_get_auth_code, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_get_auth_code, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_get_auth_code, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl_get_auth_code, CURLOPT_POSTFIELDS, $credentials_json_string);
        curl_setopt($curl_get_auth_code, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json')
        );
        $authenticationResult = curl_exec($curl_get_auth_code);
        if ($authenticationResult == false) {
            $Log->writeVariable("the curl request to authenticate failed: ");
            $Log->writeVariable(curl_error($curl_get_auth_code));
            $authenticationResult = "Attempt to connect to managed appliance failed.  Please ensure the appliance is powered on and its network address is resolvable.";
        } else {
            // return as a string
            $authenticationHttpCode = curl_getinfo($curl_get_auth_code, CURLINFO_HTTP_CODE);
            $Log->writeVariable('curl request: http code');
            $Log->writeVariable($authenticationHttpCode);
            $authenticationResult = json_decode($authenticationResult, true);
            $Log->writeVariable('curl request: result');
            $Log->writeVariable($authenticationResult);
            if ($authenticationHttpCode == 201) {
                $remote_system_auth_code = $authenticationResult['auth_token'];
                if ($authenticationResult['superuser'] == true || $authenticationResult['administrator'] == true) {
                    $authenticationResult = true;
                } else {
                    $authenticationResult = "The username and password provided do not have administrative permissions on the managed appliance.";
                }
            } else {
                $authenticationResult = $authenticationResult['result'];
                if (is_array($authenticationResult[0]) && isset($authenticationResult[0]['message'])) {
                    $authenticationResult = $authenticationResult[0]['message'];
                } else {
                    $authenticationResult = "Attempt to connect to managed appliance failed (error code: " . $authenticationHttpCode . ").";
                }
            }
        }
        curl_close($curl_get_auth_code);

        return array( 'result' => $authenticationResult, 'auth_code' => $remote_system_auth_code );
    }

    // This can be a public function, but I believe that a class is being built for this that can replace this code.  So for now, this will stay a private function.
    private function get_remote_systems_list_using_curl( $remote_system_ip, $remote_system_auth_code )
    {
        $remote_system_systems_list = false;
        global $Log;
        $curl_get_systems = curl_init();
        $url_get_systems = "https://" . $remote_system_ip . "/api/systems";
        $Log->writeVariable("retrieving system list, url is $url_get_systems");
        //$url_get_systems = $url_get_systems."&auth=".$remote_system_auth_code;
        curl_setopt($curl_get_systems, CURLOPT_URL, $url_get_systems);
        curl_setopt($curl_get_systems, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_get_systems, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_get_systems, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl_get_systems, CURLOPT_HTTPHEADER, array(
                'AuthToken: '.$remote_system_auth_code,'Content-Type: application/json' )
        );
        $getSystemsResult = curl_exec($curl_get_systems);
        if ($getSystemsResult == false) {
            $Log->writeVariable("the curl request to get the remote systems list failed: ");
            $Log->writeVariable(curl_error($curl_get_systems));
            $getSystemsResult = "Attempt to connect to the remote appliance failed.  Please ensure the appliance is powered on and its network address is resolvable.";
        } else {
            $getSystemsHttpCode = curl_getinfo($curl_get_systems, CURLINFO_HTTP_CODE);
            $Log->writeVariable('curl request: http code');
            $Log->writeVariable($getSystemsHttpCode);
            $getSystemsResult = json_decode($getSystemsResult, true);
            $Log->writeVariable('curl request: result');
            $Log->writeVariable($getSystemsResult);
            if ($getSystemsHttpCode == 200) {
                if ( isset($getSystemsResult['appliance']) and is_array($getSystemsResult['appliance']) and count($getSystemsResult['appliance']) > 0 ) {
                    $remote_system_systems_list = $getSystemsResult['appliance'];
                    $getSystemsResult = true;
                } else {
                    $getSystemsResult = "The returned list of systems is in the wrong format.";
                }
            } else {
                $getSystemsResult = $getSystemsResult['result'];
                if (is_array($getSystemsResult[0]) && isset($getSystemsResult[0]['message'])) {
                    $getSystemsResult = $getSystemsResult[0]['message'];
                } else {
                    $getSystemsResult = "Attempt to connect to remote appliance failed (error code: " . $getSystemsHttpCode . ").";
                }
            }
        }
        curl_close($curl_get_systems);

        return array( 'result' => $getSystemsResult, 'systems_list' => $remote_system_systems_list );
    }

    // This can be a public function, but I believe that a class is being built for this that can replace this code.  So for now, this will stay a private function.
    private function add_remote_management_using_curl( $remote_system_ip, $remote_system_auth_code, $new_managed_system_name )
    {
        global $Log;
        $curl_add_system = curl_init();
        $url_add_system = "https://" . $remote_system_ip . "/recoveryconsole/bpl/systems.php?type=add&host=".$new_managed_system_name."&name=".$new_managed_system_name."&location_id=1";
        $Log->writeVariable("adding management, url is $url_add_system");
        $url_add_system = $url_add_system."&auth=".$remote_system_auth_code;
        curl_setopt($curl_add_system, CURLOPT_URL, $url_add_system);
        curl_setopt($curl_add_system, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_add_system, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_add_system, CURLOPT_CUSTOMREQUEST, "POST");
        $addSystemResult = curl_exec($curl_add_system);
        if ($addSystemResult == false) {
            $Log->writeVariable("the curl request to add the system failed: ");
            $Log->writeVariable(curl_error($curl_add_system));
            $addSystemResult = "Attempt to connect to remote appliance failed.  Please ensure the appliance is powered on and its network address is resolvable.";
        } else {
            // return as a string
            $httpcode = curl_getinfo($curl_add_system, CURLINFO_HTTP_CODE);
            $Log->writeVariable('curl request: http code');
            $Log->writeVariable($httpcode);
            $addSystemResult = json_decode(json_encode(simplexml_load_string($addSystemResult)), true);
            $Log->writeVariable('curl request: result');
            $Log->writeVariable($addSystemResult);
            if ($httpcode == 200 and $addSystemResult['Result'] === '1') {
                $addSystemResult = true;
            } else {
                if (isset($addSystemResult['ErrorString'])) {
                    $addSystemResult = $addSystemResult['ErrorString'];
                } elseif (isset($addSystemResult['ErrorCode'])) {
                    $addSystemResult = $addSystemResult['ErrorCode'];
                } else {
                    $addSystemResult = "Attempt to add management to this appliance failed (error code: " . $httpcode . ").";
                }
            }
        }
        curl_close($curl_add_system);

        return $addSystemResult;
    }

    // This can be a public function, but I believe that a class is being built for this that can replace this code.  So for now, this will stay a private function.
    private function add_replication_using_curl( $remote_system_ip, $remote_system_auth_code, $new_replication_source_system_id )
    {
        global $Log;
        $curl_add_replication = curl_init();
        $url_add_replication = "https://" . $remote_system_ip . "/recoveryconsole/bpl/replication/configuration.php?type=add&id=".$new_replication_source_system_id;
        $Log->writeVariable("adding replication, url is $url_add_replication");
        $url_add_replication = $url_add_replication."&auth=".$remote_system_auth_code;
        curl_setopt($curl_add_replication, CURLOPT_URL, $url_add_replication);
        curl_setopt($curl_add_replication, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_add_replication, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_add_replication, CURLOPT_CUSTOMREQUEST, "POST");
        $addReplicationResult = curl_exec($curl_add_replication);
        if ($addReplicationResult == false) {
            $Log->writeVariable("the curl request to add replication failed: ");
            $Log->writeVariable(curl_error($curl_add_replication));
            $addReplicationResult = "Attempt to connect to remote appliance failed.  Please ensure the appliance is powered on and its network address is resolvable.";
        } else {
            // return as a string
            $httpcode = curl_getinfo($curl_add_replication, CURLINFO_HTTP_CODE);
            $Log->writeVariable('curl request: http code');
            $Log->writeVariable($httpcode);
            $addReplicationResult = json_decode(json_encode(simplexml_load_string($addReplicationResult)), true);
            $Log->writeVariable('curl request: result');
            $Log->writeVariable($addReplicationResult);
            if ($httpcode == 200 and $addReplicationResult['Result'] === '1') {
                $addReplicationResult = true;
            } else {
                if (isset($addReplicationResult['ErrorString'])) {
                    $addReplicationResult = $addReplicationResult['ErrorString'];
                } elseif (isset($addReplicationResult['ErrorCode'])) {
                    $addReplicationResult = $addReplicationResult['ErrorCode'];
                } else {
                    $addReplicationResult = "Attempt to add management to this appliance failed (error code: " . $httpcode . ").";
                }
            }
        }
        curl_close($curl_add_replication);

        return $addReplicationResult;
    }

    public function get_replicating_assets($systemID)
    {
        $instances = array();
        $system = $this->BP->get_system_info($systemID);

        global $schedules;
        $replication_jobs = $schedules->get_replication(-1, $systemID, $system['name'], true);
        $instances = array();;
        foreach ($replication_jobs as $job) {
            if (isset($job['instances'])) {
                $jobInstances = $job['instances'];
                if (isset($job['name']) && isset($job['targets'])) {
                    array_walk($jobInstances, array($this, 'add_replication_job_info'), array($job['name'], $job['targets']));
                }
                $instances = array_merge($instances, $jobInstances);
            }
        }

        $orphans = $schedules->get_orphaned_instances($systemID);
        if ($orphans !== false && count($orphans) > 0) {
            $instances = array_merge($instances, $orphans);
        }

        return array("replicating" => $instances);
    }

    /*
     * Adds the job name and target (from the $infoArray) to the item.
     */
    private function add_replication_job_info(&$item, $key, $infoArray) {
        $item['job_name'] = $infoArray[0];
        $item['targets'] = $infoArray[1];
    }

    public function validate_the_replication_queue($backup_no, $dest)
    {
        // Note: Cleanup scheme to allow Satori reverse replication to run multiple times
        // involving the same backup group. The current importing works ok for 1x ask, but subsequent
        // queue requests will notice prior completed or queued replications and no-op the request.
        // Without this temp logic, manual cleanup of the queue is required by the user.

        // Note: when a backup for importing is selected on the source, it is the only bu number sent
        // to the target for reverse replication. If this backup is a non-master, the call to
        // bp_add_to_replication_queue() will pull in the dependencies in order to populate the queue
        // completely.

        global $Log;
        $func = "validate_the_replication_queue: ";
        $Log->writeVariableDBG($func."destination: $dest, backup_no: $backup_no.");
        $result = array();

        // obtain the info from the database and build a cookie
        $localDbConn = pg_Connect("port=5432 dbname=bpdb user=postgres");
        if (!$localDbConn) {
            $Log->writeVariable($func."WARNING, db connection error:");
            return $result;
        }
        // make sure nothing related to this backup's instance is already running or queued to run
        $query_running  = "SELECT COUNT(*) FROM bp.replication_queue WHERE status != 2 AND vault = '$dest' ";
        $query_running .= " AND backup_no in (SELECT backup_no FROM bp.backups WHERE instance_id IN ";
        $query_running .= " (SELECT instance_id FROM bp.backups WHERE backup_no = $backup_no))";
        $Log->writeVariableDBG($func."running query: $query_running");
        $res = pg_query($localDbConn, $query_running);
        if (!$res) {
            $Log->writeVariable($func."WARNING, set query_running error:");
            return $result;
        }
        if (pg_num_rows($res) != 0) {
            $row   = pg_fetch_row($res);
            $count = (int) $row[0];
            if ($count != 0) {
                pg_close($localDbConn);
                $errorMessage = 'Import already queued or in progress for related backups. Please retry when other task completes.';
                $Log->writeVariable($func.$errorMessage);
                $result['error']  = 500;
                $result['message']= $errorMessage;
                return $result;
            }
        }

        // Clear related backups from the replication queue nss table. It is unlikely anything will be in
        // this table given the check above for non-running tasks because when tasks complete, their
        // correpsonding entry in nss is purged.
        $query_clear  = "DELETE FROM bp.replication_queue_nss WHERE status = 1 AND destination = '$dest' ";
        $query_clear .= " AND backup_no in (SELECT backup_no FROM bp.backups WHERE instance_id IN ";
        $query_clear .= " (SELECT instance_id FROM bp.backups WHERE backup_no=$backup_no))";
        $Log->writeVariableDBG($func."nss clear query: $query_clear.");
        $res = pg_query($localDbConn, $query_clear);
        if (!$res) {
            $Log->writeVariable($func."WARNING, set query_clear error:");
        }

        // clear related backups from the replication queue
        $query_clear  = "DELETE FROM bp.replication_queue WHERE status = 2 AND vault = '$dest' ";
        $query_clear .= " AND backup_no in (SELECT backup_no FROM bp.backups WHERE instance_id IN ";
        $query_clear .= " (SELECT instance_id FROM bp.backups WHERE backup_no=$backup_no))";
        $Log->writeVariableDBG($func."clear query: $query_clear.");
        $res = pg_query($localDbConn, $query_clear);
        if (!$res) {
            $Log->writeVariable($func."WARNING, set query_clear error:");
        }
        pg_close($localDbConn);
        return $result;
    }

    public function add_import_to_replication_queue($backup_no, $dest)
    {
        // dest is the source that originated the request which the import will be sent to

        // Note: Using the BP->add_to_replication_queue() API directly updates the backup's sync_status
        // state, which causes all known master.ini Replication::SynTo values to be recipients of this
        // queued request. Therefore calling vcd util directly with the 'no_sync_status' option which
        // queues up requests specifically to the target provided.

        global $Log;
        $func = "add_import_to_replication_queue: ";
        $Log->writeVariableDBG($func."destination: $dest, backup_no: $backup_no.");
        $result = array();
        $keyword = "no_sync_status";

        // find the appliance bin directory
        $bploc = trim(shell_exec('eval `grep BPDIR /etc/default/bp.ini`; echo $BPDIR'));
        if (empty($bploc) === true) {
            $bploc = "/usr/bp";
        }
        $bpbin = "$bploc/bin";

        $cmd = "$bpbin/vcd util enqueue $backup_no $dest $keyword";
        $out = `$cmd`;
        if ($out != NULL) {
            $errorMessage = "Import job failed to queue. Details:".$out;
            $Log->writeVariable($func.$errorMessage);
            $result['error']  = 500;
            $result['message']= $errorMessage;
            return $result;
        }
        return $result;
    }

    public function validate_replication_config($dest, $system_name, $sid)
    {
        // dest is the source that originated the request, and system_name will be this target name

        // Note: Using the BP->is_replication_enabled() and BP->enable_replication() APIs
        // instead of cmc_replication directly because "cmc_replication target enable" fails
        // when trying to update the master.ini value when called from apache.

        global $Log;
        $func = "validate_replication_config: ";
        $Log->writeVariableDBG($func."destination: $dest.");
        $result = array();
        $cfg_alert_obj = $dest."_rep_config";

        // Check if replication is configured on this target, needed for importing backups.
        $enabled = $this->BP->is_replication_enabled();
        if ($enabled == 1) {
            // replication is up and running, clear config alert, return null results, success!
            $this->BP->send_notification(8, $cfg_alert_obj);
            return $result;
        }

        // Replication is not enabled, determine if we can try and configure it
        $x = $this->BP->get_ini_value("SelfService", "AutoEnableReplicationOnTgt", $sid);
        $autoEnable = ($x === 'false' || (strtolower($x) === 'no') || $x === '0') ? false : true;
        if ($autoEnable === false) {
            // Alert 214: A self-service task on %s requested from %s was unabled to start because %.
            $reason = "of the target master.ini SelfService::AutoEnableReplicationOnTgt setting";
            $this->BP->send_notification(214, $cfg_alert_obj, $system_name, $dest, $reason);
            $errorMessage = "Import job failed to queue because the target importing services are not running. (Alert generated)";
            $Log->writeVariable($func.$errorMessage);
            $result['error']  = 500;
            $result['message']= $errorMessage;
            return $result;
        }

        // have ok to try and enable replication on this target
        $config = $this->BP->enable_replication(true);
        if ($config != 1) {
            // Alert 214: A self-service task on %s requested from %s was unabled to start because %.
            $reason = "replication services on the target failed to start";
            $this->BP->send_notification(214, $cfg_alert_obj, $system_name, $dest, $reason);
            $errorMessage = "Import job failed to queue because the target importing setup did not complete correctly. (Alert generated)";
            $Log->writeVariable($func.$errorMessage);
            $result['error']  = 500;
            $result['message']= $errorMessage;
            return $result;
        }

        // replication is now up and running, update the audit log and return null results, success!
        // Audit Log 212: Replication service on %s was enabled for self-service support per %s from %s.
        $action = "an import request";
        $this->BP->send_notification(212, $dest, $system_name, $action, $dest);

        // replication is up and running, clear config alert
        $this->BP->send_notification(8, $cfg_alert_obj);
        $Log->writeVariable($func."replication was configured per an import request");
        return $result;
    }
}

?>
