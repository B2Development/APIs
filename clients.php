<?php

define("VCENTER_RRC", "vCenter-RRC");
define("PROTECT_GENERIC_UCSM", "Cisco UCS Manager");
define("PROTECT_GENERIC_UCSC","Cisco UCS Central");
define("PROTECT_GENERIC_NAS","NAS NDMP Client");
define("ALL_OTHER_OS","All other OS");

class Clients
{
    const MORE_FILES = "";

    private $BP;
     
    public function __construct($BP)
    {
		$this->BP = $BP;

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);

        $this->Roles = null;
    }

    public function get($which, $filter, $data, $sid, $systems)
    {
        switch($which){
            case "config":
                $allClients = $this->getConfig();
                break;

            case "files":
                $allClients = $this->getFiles($filter);
                break;

            case "target-files":
                $allClients = $this->getFilesOnTarget($filter);
                break;

            case "filtered":
                $allClients = $this -> getFilteredClients($data, $sid, $systems);
                break;

            case "agent-check":
                $allClients = $this -> AgentCheck($filter);
                break;

            case "target-list":
                $allClients = $this -> getClientsOnTarget();
                break;

            case "system":
                $allClients = $this -> getSystemClient();
                break;

            case "target-system":
                $allClients = $this -> getSystemClientOnTarget();
                break;

            default:
                $allClients = $this -> getClients($which);
                break;
        }

        return($allClients);
    }

    public function put($which, $inputArray, $sid){
        switch($which){
            case 'agent-push':
                $result = $this->agentPush($inputArray);
                break;
            default:
                $result = $this -> putClient($which,$inputArray, $sid);
                break;
        }
        return($result);
    }


    public function delete($which,$sid)
    {
       $result = $this-> deleteClient($which,$sid);
        return $result;
    }

    public function add($which, $inputArray, $sid)
    {
        $result = $this-> addClient($which, $inputArray, $sid);
        return $result;
    }




    function getConfig(){
        $cid = isset($_GET['cid']) ? (int)$_GET['cid'] : false;
        $sid = isset($_GET['sid']) ? (int)$_GET['sid'] : false;
        $bid = isset($_GET['bid']) ? (int)$_GET['bid'] : false;
        $bValid = true;
        $grandClientView = false;
        $localID = 1;
        if(isset($sid)){
            $dpuID=$sid;
        }else{
            $dpuID=1;
        }
        if ($grandClientView) {
            $clients = $this->BP->get_grandclient_list($dpuID);
            // working on the local system.
            $dpuID = $localID;
        } else {
            $clients = $this->BP->get_client_list($dpuID);
        }
        foreach ($clients as $id => $name){
            if($cid!==false and ($id === $cid)){
                $clientConfig=$this->BP->get_client_config($cid,$dpuID);
            }
            if($bid!==false){
                $clientConfig=$this->BP->get_client_config_from_backup($bid,$dpuID);
            }
        }
        if($clientConfig === false){
            $result['error'] = 500;
            $result['message'] = $this->BP->getError();
            return $result;
        }
        $allClients = array('data'=>$clientConfig);
        return $allClients;
    }



    function getFiles($filter){
        if (isset($filter)) {
            $sid = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
            $cid = $filter;
            $directory = isset($_GET['dir']) ?  $_GET['dir'] : "";
            $last = isset($_GET['last']) ?  $_GET['last'] : "";
            $count = isset($_GET['count']) ?  (int)$_GET['count'] : 0;
            $rootlist = $this->BP->get_client_files($cid, $directory, $last, $count, $sid);
            if ($rootlist !== false) {
                $allClients['data'] = array();
                $realcount = 0;
                $data = array();
                foreach($rootlist as $key => $val){
                    $data[$key]['directory'] = $rootlist[$key]['directory'];
                    $data[$key]['name'] = $rootlist[$key]['name'];
                    $data[$key]['id'] = $rootlist[$key]['id'];
                    $data[$key]['type'] = $rootlist[$key]['type'];
                    if($data[$key]['type'] === "file"){
                        $data[$key]['isBranch'] = false;
                    }
                    else $data[$key]['isBranch'] = true;
                    $realcount++;
                }
                if ($count > 0 && ($count === $realcount)) {
                    $directory = $data[$realcount - 1]['directory'];
                    $lastFile = $data[$realcount - 1]['name'];
                    $data[] = $this->addFilePlaceholder($directory, $lastFile);
                }
                $allClients['start'] = 1;
                $allClients['count'] = $realcount;
                $allClients['total'] = $realcount;
                $allClients['data'] = $data;
            } else {
                $allClients['error'] = 500;
                $allClients['message'] = $this->BP->getError();
            }
            return $allClients;
        } else {
            $result = array();
            $result['error'] = 500;
            $result['message'] = 'A client id must be specified when browsing files.';
            return $result;
        }
    }

    private function addFilePlaceholder($directory, $lastFile) {
        return array('id' => $directory . 'id.' . mt_rand(),
            'directory' => $directory,
            'isBranch' => false,
            'type' => 'file',
            'name' => self::MORE_FILES,
            'last' => $lastFile);
    }



    function AgentCheck($filter){
        $cid = isset($_GET['cid']) ? (int)$_GET['cid'] : false;
        $sid = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        $data1 = array();
        $clients = $this->BP->get_client_list($sid);
        foreach($clients as $key => $val){
            if(is_numeric($cid) and $cid !== $key) {
                continue;
            } else {
                $clientInfo = $this->BP->get_client_info($key, $sid);
                $hostInfo = $this->BP->get_host_info($clientInfo['name'], $sid);
                $data['address'] = $hostInfo['ip'];
                $data['id'] = $key;
                $data['name'] = $clientInfo['name'];
                $data['version'] = $clientInfo['version'];
                if($this->functions->getOSFamily($clientInfo['os_type_id']) == Constants::OS_FAMILY_WINDOWS) {
                    $updateAvailable = $this->BP->agent_update_available($key, $sid);
                    if(is_array($updateAvailable)) {
                        $data['update_available'] = $updateAvailable[0]['isUpdateAvailable'];
                    } else {
                        $data['update_available'] = false;
                    }
                } else {
                    $data['update_available'] = false;
                    $data['message'] = "This client does not support agent check and agent push";
                }

                $data1[]=$data;
            }
        }
        $allClients = array('clients'=>$data1);
        return $allClients;
    }

    private function getFilteredClients($data, $sid, $systems) {
        $os_id = NULL;
        $os_family = NULL;
        $app_id = NULL;
        $app_type = NULL;
        $get_all_apps = false;
        $showSystemClient = false;
        if ( $data !== NULL ) {
            if ( array_key_exists('os_id', $data) ) {
                $os_id = (int)$data['os_id'];
            }
            if ( array_key_exists('os_family', $data) ) {
                $os_family = $data['os_family'];
            }
            if ( array_key_exists('app_id', $data) ) {
                $app_id = (int)$data['app_id'];
            }
            if ( array_key_exists('app_type', $data) ) {
                if ( $data['app_type'] === 'SQL' ) {
                    $app_type = Constants::APPLICATION_TYPE_NAME_SQL_SERVER;
                } else {
                    $app_type = $data['app_type'];
                }
            }
            if ( array_key_exists('all_apps', $data) ) { //1 for true, 0 for false
                $get_all_apps = ( (int)$data['all_apps'] === 1 );
            }
            if ( array_key_exists('showSystemClient', $data) )
            {
                $showSystemClient = ($data['showSystemClient'] === '1');
            }
        }

        $returnClients = array();

        foreach ( $systems as $systemID => $systemName ) {

            $clients = $this->BP->get_client_list($systemID);
            if ( $clients !== false ) {
                foreach ($clients as $clientID => $clientName) {
                    $returnThisClient = true;
                    $isSystemClient = ($clientName === $systemName);

                    $clientInfo = $this->BP->get_client_info($clientID, $systemID);
                    if ($clientInfo !== false and ($showSystemClient === true or $isSystemClient === false) ) {  //Always exclude the system client
                        if ($os_id !== NULL and $clientInfo['os_type_id'] !== $os_id) {
                            $returnThisClient = false;
                        }
                        if ($returnThisClient === true and $os_family !== NULL and $clientInfo['os_family'] !== $os_family) {
                            $returnThisClient = false;
                        }
                        $applicationsForThisClient = array();
                        if ( $get_all_apps === true or $app_id !== NULL or $app_type !== NULL ) {
                            foreach ($clientInfo['applications'] as $applicationID => $applicationArray) {
                                if ( $get_all_apps === true or
                                     ( $app_id !== NULL and $applicationID === $app_id  ) or
                                     ( $app_type !== NULL and $applicationArray['type'] === $app_type ) ) {
                                    $applicationsForThisClient[$applicationID] = $applicationArray['name'];
                                }
                            }
                            if ( $get_all_apps === false and count($applicationsForThisClient) === 0 ) {
                                $returnThisClient = false;
                            }
                        }
                        if ( $returnThisClient === true or ($showSystemClient === true and $isSystemClient === true) ) {
                            $returnClients[] = array(
                                'system_id'=>$systemID,
                                'system_name'=>$systemName,
                                'client_id'=>$clientID,
                                'client_name'=>$clientName,
                                'os_type'=>$clientInfo['os_type'],
                                'applications'=>$applicationsForThisClient);
                        }
                    }
                }
            }
        }

        return array( 'data'=>$returnClients);
    }



    function getClients($which){
        $bValid = true;
        $grandClientView = isset($_GET['grandclient']) && $_GET['grandclient'] === "true";
        $forSearch = isset($_GET['searchFiles']) ? $_GET['searchFiles'] === 'true' : false;
        $localID = 1;

        $dpuID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();

        if ($grandClientView) {
            $clients = $this->BP->get_grandclient_list($dpuID);
            // working on the local system.
            $dpuID = $localID;
        } else {
            $clients = $this->BP->get_client_list($dpuID);
        }

        $systemList = $this->BP->get_system_list();
        foreach($systemList as $key => $val){
            if($dpuID===$key){
                $system_id = $key;
                $system_name = $val;
            }
        }
        $includeVCenter = !$forSearch;              // Do not include vCenter client if for file search.
        if(!isset($_GET['include_vmware_client'])){
            $includeVCenter = false;
        }
        else if($_GET['include_vmware_client'] !== "true"){
            $includeVCenter = false;
        }

        $allClients = array();
        if ($clients === false) {
            $bValid = false;
        } else {
            foreach ($clients as $id => $name) {
                $isSystem=false;
                if ($which > 0 and $id != $which) {
                    continue;
                }
                if(!$includeVCenter and $name === VCENTER_RRC){
                    continue;
                }
                $clientInfo = $this->BP->get_client_info($id, $dpuID);
                $hostInfo = $this->BP->get_host_info($clientInfo['name'], $dpuID);
                foreach($systemList as $system){
                    if($system===$name){
                        $isSystem=true;
                        continue;
                    }
                }
                if($isSystem){
                    continue;
                }

                if ($clientInfo === false) {
                    $bValid = false;
                    break;
                } else {
                    if ($forSearch) {
                        $osType = $clientInfo['os_type'];
                        if ($osType === Constants::ADD_CLIENT_DISPLAY_NAME_XEN ||
                            $osType === Constants::ADD_CLIENT_DISPLAY_NAME_AHV) {
                            // If returning clients for file search, skip special ones that will have no file-level backups.
                            continue;
                        }
                    }
                    $networkID = sprintf("%d.%d", $dpuID, $id);
                    $Credentials = NULL;
                    if (   $clientInfo['os_type_id'] == Constants::OS_WIN_16
                        or $clientInfo['os_type_id'] == Constants::OS_WINDOWS_95
                        or $clientInfo['os_type_id'] == Constants::OS_WINDOWS_2000
                        or $clientInfo['os_type_id'] == Constants::OS_WINDOWS_XP
                        or $clientInfo['os_type_id'] == Constants::OS_WINDOWS_2003
                        or $clientInfo['os_type_id'] == Constants::OS_WINDOWS_VISTA
                        or $clientInfo['os_type_id'] == Constants::OS_WINDOWS_2008
                        or $clientInfo['os_type_id'] == Constants::OS_WIN_2008_R2
                        or $clientInfo['os_type_id'] == Constants::OS_WINDOWS_7
                        or $clientInfo['os_type_id'] == Constants::OS_WINDOWS_8
                        or $clientInfo['os_type_id'] == Constants::OS_WINDOWS_2012
                        or $clientInfo['os_type_id'] == Constants::OS_WINDOWS_8_1
                        or $clientInfo['os_type_id'] == Constants::OS_WINDOWS_2012_R2
                        or $clientInfo['os_type_id'] == Constants::OS_WINDOWS_10
                        or $clientInfo['os_type_id'] == Constants::OS_WINDOWS_2016)
                    {
                        $clientInfo['supports_agent_push']=true;
                    }
                    else{
                        $clientInfo['supports_agent_push']=false;
                    }
                    $clientInfo['asset_type'] = Constants::ASSET_TYPE_PHYSICAL;
                    $clientInfo['system_id'] = $system_id;
                    $clientInfo['system_name'] = $system_name;
                    $clientInfo['ip'] = $hostInfo['ip'];
                    $allClients[] = $clientInfo;
                }
            }
            // add vCenters if present

            $result = $this->BP->get_vcenter_list($dpuID);
            if ($result !== false) {
                foreach ($result as $id => $name) {
                    if( $id != $which and $which > 0){
                        continue;
                    }
                    $appInfo = $this->BP->get_vcenter_info($id, $dpuID);
                    $appInfo['asset_type'] = Constants::ASSET_TYPE_VMWARE_HOST;
                    $appInfo['system_id'] = $system_id;
                    $appInfo['system_name'] = $system_name;
                    $allClients[] = $appInfo;
                }
            }
        }
        $allClients = array('data'=>$allClients);
        return $allClients;
    }


    function putClient($which, $inputArray, $sid){
        $cid=$which;
        $do_save_client_info = true;

        if($sid===false) {
            $sid = $this->BP->get_local_system_id();
        }

        if(ctype_digit($cid)) {
            $clientInfo = $this->BP->get_client_info($cid,$sid);
        }
        else{
            $vcenterInfo = $this->BP->get_vcenter_info($cid,$sid);
            if($vcenterInfo === false){
                return false;
            }
            else {

                if( isset($inputArray['quiesce_setting']) ) {

                    require_once('quiesce.php');
                    $quiesce = new Quiesce($this->BP);
                    $quiesce_setting = $quiesce->getQuiesceSettingfromDisplayName($inputArray['quiesce_setting']);

                    $overwrite_quiesce_settings = $this->BP->set_quiesce_for_hypervisor_vms(Constants::APPLICATION_TYPE_NAME_VMWARE, $cid, $quiesce_setting, $sid);

                    if ( $overwrite_quiesce_settings === false ) {
                        // If the queisce settings failed, show that error message and do not try to save the vCenter/ESX Server
                        return $overwrite_quiesce_settings;
                    }
                }

                $hostInfo = $inputArray['host_info'];
                $credentials = $inputArray['credentials'];
                $newvcenterInfo['id'] = $cid;
                $newvcenterInfo['address'] = isset($hostInfo['ip']) ? $hostInfo['ip'] : $vcenterInfo['ip'];
                if ( isset($credentials['username']) and $credentials['username'] !== '' and isset($credentials['password']) and $credentials['password'] !== '' ) {
                    $newvcenterInfo['login'] = $credentials['username'];
                    $newvcenterInfo['password'] = $credentials['password'];
                }
                $result = $this->BP->save_vcenter_info($newvcenterInfo, $sid);
                return $result;
            }
        }

        $newClientInfo['id']= $clientInfo['id'];

        if(isset($inputArray['host_info']) or isset($inputArray['name'])){
            $nameIsAlias = false;
            $oldHostInfo=$this->BP->get_host_info($clientInfo['name'], $sid);
            $newHostInfo = array();
            $newHostInfo['original_ip'] = $oldHostInfo['ip'];
            $newHostInfo['ip'] = $oldHostInfo['ip'];                // New IP will be reset later on line 386
            if (isset($oldHostInfo['aliases'])) {
                $newHostInfo['aliases'] = $oldHostInfo['aliases'];
            }
            if(isset($inputArray['name'])) {
                $newClientInfo['name'] = $inputArray['name'];
                if (isset($newHostInfo['aliases']) && in_array($inputArray['name'], $newHostInfo['aliases'])) {
                    $newHostInfo['name'] = $oldHostInfo['name'];
                    $nameIsAlias = true;
                } else {
                    $newHostInfo['name'] = $inputArray['name'];                   
                }
            }
            if(isset($inputArray['host_info']) && !isset($clientInfo['remote_address'])) {          //special condition for Xen or AHV
                $hostInfo = $inputArray['host_info'];
                if ($hostInfo['ip'] != "") {
                    $newHostInfo['ip'] = $hostInfo['ip'];
                }
            }
            if($newHostInfo['ip'] != NULL and $newHostInfo['ip']!=="127.0.0.1" and ($newHostInfo['name']===$inputArray['name'] || $nameIsAlias)){
                $alias = false;
                $saveHost=$this->BP->save_host_info($newHostInfo, $sid);
            }
            else if($newHostInfo['name'] !== $clientInfo['name']){
                $alias = true;
                $saveHost = $this->addHostAlias($this->BP, $oldHostInfo['name'], $newHostInfo['name'], $sid);       //Add new alias
            }
            else{
                $saveHost = true;
                $alias = false;
            }

            //aliases should be updated in hostname.php
            if($saveHost===false){
                return false;
            }
            else if ($alias && !$nameIsAlias){
                $removeHost = $this->removeHostAlias($this->BP, $newHostInfo['name'], $clientInfo['name'], $sid);      //Remove old alias.
                if($removeHost===false){
                    global $Log;
                    $msg= "could not update host file: ".$this->BP->getError();
                    $Log->writeError($msg, true);
                }
            }
        }
        //credential changes

        if(isset($inputArray['credential_id'])and $inputArray['credential_id'] !== -1){
            $newClientInfo['credential_id']=$inputArray['credential_id'];
        }
        elseif($inputArray['is_auth_enabled']===false or isset($inputArray['credential_id']) && $inputArray['credential_id'] === -1){
            $newClientInfo['no_credentials']=true;
        }
        elseif(isset($inputArray['credentials'])){

            $credInfo = $inputArray['credentials'];

            // Make sure that the username and password are not blank before trying to save a credential
            if ( isset($credInfo['username']) and $credInfo['username'] !== '' and isset($credInfo['password']) and $credInfo['password'] !== '' ) {

                if (isset($credInfo['display_name']) && ($credInfo['display_name']) !== "") {
                    $newCredInfo['display_name'] = $credInfo['display_name'];
                }
                if (!isset($credInfo['is_default'])) {
                    $newCredInfo['is_default'] = false;
                } else {
                    $newCredInfo['is_default'] = $credInfo['is_default'];
                }
                $newCredInfo['username'] = $credInfo['username'];
                $newCredInfo['password'] = $credInfo['password'];
                $newCredInfo['domain'] = $credInfo['domain'];
                $saveCred = $this->BP->save_credentials($newCredInfo, $sid);
                if ($saveCred === false) {
                    return false;//$this->BP->getError();
                } else {
                    $newClientInfo['credential_id'] = $saveCred;
                }
            }
        }

        //priority changes
        $newClientInfo['is_enabled'] = isset($inputArray['is_enabled']) ? $inputArray['is_enabled'] : $clientInfo['is_enabled'];
        $newClientInfo['is_encrypted'] = isset($inputArray['is_encrypted']) ? $inputArray['is_encrypted'] :$clientInfo['is_encrypted'];
        $newClientInfo['priority'] = isset($inputArray['priority']) ? $inputArray['priority'] : $clientInfo['priority'];
        $newClientInfo['use_ssl'] = isset($inputArray['use_ssl']) ? $inputArray['use_ssl'] : $clientInfo['use_ssl'];


        // UNIBP-13744 Only send in is_synchable if it is explicitly set.
        if ( isset($inputArray['is_synchable']) ) {
            $newClientInfo['is_synchable'] = $inputArray['is_synchable'];
        }

        //For NAS NDMP clients remote IP change

        if ( isset ($clientInfo['os_type_id'])
            && $clientInfo['os_type_id'] == Constants::OS_GENERIC
            && isset($clientInfo['generic_property'])
            && $clientInfo['generic_property'] !== Constants::GENERIC_PROPERTY_NDMP_DEVICE
            && $clientInfo['generic_property'] !== Constants::GENERIC_PROPERTY_XEN
            && $clientInfo['generic_property'] !== Constants::GENERIC_PROPERTY_AHV) {
            $useRaeSave = true;
        }
        else{
            $useRaeSave= false;
        }
        if(isset($inputArray['remote_port']) or isset($clientInfo['remote_port'])){         //For NDMP special case
            $newClientInfo['remote_address']=isset($inputArray['remote_address']) ? $inputArray['remote_address'] : $clientInfo['remote_address'];
            $newClientInfo['remote_port'] = isset($inputArray['remote_port']) ? $inputArray['remote_port'] : $clientInfo['remote_port'];
        }
        if(isset($clientInfo['generic_property']) &&
            ($clientInfo['generic_property'] === Constants::GENERIC_PROPERTY_XEN || $clientInfo['generic_property'] === Constants::GENERIC_PROPERTY_AHV)) {         //For Xen special case
            $newClientInfo['remote_address']=isset($inputArray['host_info']['ip']) ? $inputArray['host_info']['ip'] : $clientInfo['remote_address'];

            if ( !isset($newClientInfo['credential_id']) and !isset($newClientInfo['no_credentials']) and isset($clientInfo['credentials']['credential_id']) )
            {
                $newClientInfo['credential_id'] = $clientInfo['credentials']['credential_id'];
            }
            if( isset($inputArray['quiesce_setting']) ) {

                require_once('quiesce.php');
                $quiesce = new Quiesce($this->BP);
                $quiesce_setting = $quiesce->getQuiesceSettingfromDisplayName($inputArray['quiesce_setting']);

                $appType = $clientInfo['generic_property'] === Constants::GENERIC_PROPERTY_XEN ? Constants::APPLICATION_TYPE_NAME_XEN : Constants::APPLICATION_TYPE_NAME_AHV;
                $overwrite_quiesce_settings = $this->BP->set_quiesce_for_hypervisor_vms($appType, $clientInfo['name'], $quiesce_setting, $sid);

                if ( $overwrite_quiesce_settings === false ) {
                    $result = false;
                    $do_save_client_info = false;
                    // If the queisce settings failed, show that error message and do not try to save the Xen client.
                }
            }
        }

        // Block Agent - determines whether or not Exchange backups should quiesce or block backups should quiesce
        if( isset($inputArray['app_aware_flg']) ) {
            $new_app_aware_flag = (int)$inputArray['app_aware_flg'];

            $current_app_aware_flag = null;
            if ( isset($clientInfo['app_aware_flg']) ) {
                $current_app_aware_flag = $clientInfo['app_aware_flg'];
            } else {
                $app_aware_arrays = $this->BP->get_app_aware_flag((int)$cid, $sid);
                if ( $app_aware_arrays !== false ) {
                    foreach ($app_aware_arrays as $app_aware_client_array) {
                        if ($app_aware_client_array['client_id'] === (int)$cid) {
                            $current_app_aware_flag = $app_aware_client_array['app_aware_flg'];
                        }
                    }
                } else {
                    global $Log;
                    $msg = "could not get the app_aware flag; bp_get_app_aware_flag error: " . $this->BP->getError();
                    $Log->writeError($msg, true);
                }
            }

            // Only save $app_aware_flag if it is different fromm what is there or it is impossible to determine what it currently is
            if ( $current_app_aware_flag === null or $current_app_aware_flag !== $new_app_aware_flag ) {
                $do_app_aware_flag_save = true;

                // If $new_app_aware_flag is APP_AWARE_FLG_AWARE_OF_APPLICATIONS_VSS_COPY, then Exchange schedules do not need to be checked.  Thus proceed with save
                // If $new_app_aware_flag is APP_AWARE_FLG_NOT_AWARE_OF_APPLICATIONS_VSS_FULL, then Exchange schedules cannot be run for this client.  Thus check to see if any Exchange schedules are running before proceeding with the save
                if ( $new_app_aware_flag === Constants::APP_AWARE_FLG_NOT_AWARE_OF_APPLICATIONS_VSS_FULL ) {
                    $application_schedules_list = $this->BP->get_app_schedule_list((int)$cid, -1, $sid);
                    foreach ($application_schedules_list as $application_schedule) {
                        if ( $this->functions->getApplicationTypeFromApplictionID($application_schedule['app_id']) === Constants::APPLICATION_TYPE_NAME_EXCHANGE
                            and $application_schedule['enabled'] === 1 ) {
                            // This client has Exchange schedules enabled, so do not allow the save to occur
                            $do_app_aware_flag_save = false;
                            $do_save_client_info = false;

                            global $Log;
                            $msg= 'The selected Application Strategy does not allow for this client to have any Exchange jobs enabled, but '.$application_schedule['name'].' is currently enabled.  Please disable this schedule before attempting to change the Application Strategy.';
                            $Log->writeError($msg, true);

                            $result = array();
                            $result['error'] = 500;
                            $result['message'] = $msg;

                            return $result;
                            // I realize that the break is superfluous, but in case this code is changed in the future, I want to be sure the save does not occur
                            break;
                        }
                    }
                }

                if ( $do_app_aware_flag_save === true ) {
                    $save_app_aware_result = $this->BP->save_app_aware_flag(array(array('client_id' => (int)$cid, 'app_aware_flg' => $new_app_aware_flag)), $sid);

                    if ($save_app_aware_result === false) {
                        $result = false;
                        $do_save_client_info = false;
                        // If the save app aware called failed, show that error message and do not try to save the client.
                    }
                }
            }
        }

        //Works for regular clients if added "os" Info in newClientInfo Array
        if(isset($clientInfo['os_type_id'])){
            $newClientInfo['os']= $clientInfo['os_type_id'];
            $newClientInfo['os_type_id'] = $clientInfo['os_type_id'];
            if (isset($clientInfo['generic_property'])) {
                $newClientInfo['generic_property'] = $clientInfo['generic_property'];
            }
        }
        if ( $do_save_client_info !== false ) {
            // save app visibility if provided.
            if (isset($inputArray['applications'])) {
                $newClientInfo['applications'] = $inputArray['applications'];
            }
            //finally save client info with changes
            if ($useRaeSave) {
                $result = $this->BP->save_rae_client_info($newClientInfo, $sid);
            } else {
                $result = $this->BP->save_client_info($newClientInfo, $sid);
            }
        }

        if ( $result !== false and isset($inputArray['encryption_for_all_assets_for_this_server']) ) {
            require_once('encryption.php');
            $encryption = new Encryption($this->BP);
            $encryptionStatus = $encryption->encryptAllAssetsForClient( $newClientInfo['id'], $sid, $inputArray['encryption_for_all_assets_for_this_server'] === true);
            if($encryptionStatus===false){
                global $Log;
                $msg= "could not save all encryption settings".$this->BP->getError();
                $Log->writeError($msg, true);
            }
        }

        if($result === false &&
            (isset($inputArray['host_info']) or isset($inputArray['name'])) &&
            (!isset($clientInfo['remote_address']) or (isset($clientInfo['generic_property']) &&
                    ($clientInfo['generic_property'] === Constants::GENERIC_PROPERTY_XEN || $clientInfo['generic_property'] === Constants::GENERIC_PROPERTY_AHV)) )){                   //If saving client info fails roll back to old host info.

            $HostInfo = $this->BP->get_host_info($newClientInfo['name'], $sid);
            if($HostInfo['ip']==="127.0.0.1"){
                $saveHost = $this->addHostAlias($this->BP, $newClientInfo['name'], $clientInfo['name'], $sid);       //Add old alias back in alias list
                if($saveHost===false){
                    global $Log;
                    $msg= "could not update host file".$this->BP->getError();
                    $Log->writeError($msg, true);
                }
                else{
                    if ( $clientInfo['name'] !== $newClientInfo['name'] ) {  //If the alias has not changed, don't remove it from the host file.
                        $removeHost = $this->removeHostAlias($this->BP, $clientInfo['name'], $newClientInfo['name'], $sid);      //Remove new alias
                        if ($removeHost === false) {
                            global $Log;
                            $msg = "could not update host file" . $this->BP->getError();
                            $Log->writeError($msg, true);
                        }
                    }
                }
            }
            else{
                $tempHostInfo = $HostInfo;
                $tempHostInfo['original_ip'] = $HostInfo['ip'];
                $tempHostInfo['ip'] = $newHostInfo['original_ip'];
                $saveHost=$this->BP->save_host_info($tempHostInfo, $sid);
                if($saveHost===false){
                    global $Log;
                    $msg= "could not update host file: ".$this->BP->getError();
                    $Log->writeError($msg, true);
                }
            }
        }
        return $result;
    }



    function deleteClient($which, $sid){
        $isVirtual=false;
        if($sid===false) {
            $sid = $this->BP->get_local_system_id();
        }

        $result = $this->BP->get_vcenter_list($sid);

        foreach($result as $id=>$name){
            if($which==$id){
                $isVirtual=true;
                $vClientDeleted= $this->BP->delete_vcenter_info($which, $sid);
                if($vClientDeleted===false){
                    return false;
                }
            }
        }

        //Check if client to be deleted is virtual(VMware or ESX)

        if($isVirtual===false){
            $clientInfo=$this->BP->get_client_info((int)$which,$sid);

            $isNAS = (($temp = strlen($clientInfo['name']) - strlen(Constants::NAS_POSTFIX)) >= 0 && strpos($clientInfo['name'], Constants::NAS_POSTFIX, $temp) !== FALSE);

            if($isNAS) {
                //route through delete storage function
                $storageName = substr($clientInfo['name'], 0, $temp);
                $storageID = $this->BP->get_storage_id($storageName, $sid);
                require_once("storage.php");
                $storage = New Storage($this->BP, $sid);
                return $storage->delete($storageID, $sid);
            } else {
                //Get alias list and check if there is an alias attached to client. If found aliases attached to client display false and display "This is alias for client xyz.
                //Remove alias and then try to delete client "

                $hostInfo=$this->BP->get_host_info($clientInfo['name'],$sid);

                $deleteClientResult=$this->BP->delete_client($which, $sid);
                if($deleteClientResult===false){
                    return false;
                }else{
                    // if the client is an alias, deletion of the alias will result in deletion 
                    // of the client from the hosts file and the inability for the system to reach the client

                    // If the client is an alias, the hostName is not the same as the alias name
                    // and thus the alias should be deleted from the host file - not the main client.
                    // Use a case-insensitive comparison as /etc/hosts is not case-sensitive.
                    $clientIsAlias = strcasecmp($clientInfo['name'], $hostInfo['name']) != 0;
                    
                    if($hostInfo['ip']==="127.0.0.1" || $clientIsAlias){
                        $newAliasList=null;
                        if (isset($hostInfo['aliases'])) {
                            $aliasList=$hostInfo['aliases'];
                            foreach($aliasList as $key=>$val){
                                if($val!==$clientInfo['name']){
                                    $newAliasList[]=$val;
                                }
                            }                          
                        }
                        $hostInfo['original_ip'] = $hostInfo['ip'];
                        $hostInfo['aliases']=$newAliasList;
                        $saveHost=$this->BP->save_host_info($hostInfo,$sid);
                        if($saveHost===false){
                            global $Log;
                            $msg= "could not update host file".$this->BP->getError();
                            $Log->writeError($msg, true);
                        }
                    }
                    else{
                        $removeHost=$this->BP->remove_host_info($clientInfo['name'],$sid);
                        if($removeHost===false){
                            global $Log;
                            $msg = 'Cannot remove host ' . $clientInfo['name'] . ' ' . $this->BP->getError();
                            $Log->writeError($msg, true);
                        }
                    }
                }
            }
        }
    }



    function addClient($which, $inputArray, $sid){
        if($sid===false) {
            $sid = $this->BP->get_local_system_id();
        }
        $hostInfo=$inputArray['host_info'];
        $credentialsInfo= isset($inputArray['credentials']) ? $inputArray['credentials'] : NULL;
        $isValid=$this->isValid($inputArray,$sid);
        if($isValid['valid']) {
            if ($inputArray['os_type'] == "VMware") {
                $vcenterExists=false;
                // VMware path:  add vCenter on a create.
                $bValid = $this->addHostAlias($this->BP, 'localhost', VCENTER_RRC, $sid);
                $clients=$this->BP->get_client_list($sid);
                foreach($clients as $key=>$value){
                    if($value===VCENTER_RRC){
                        $vcenterExists=true;
                    }
                }
                if(!$vcenterExists){
                    $inputArray['os_type']=VCENTER_RRC;
                    if($inputArray['os_type']===VCENTER_RRC){
                        $clientInfo['name']=VCENTER_RRC;
                        $clientInfo['is_enabled'] = true;
                        $clientInfo['is_synchable'] = false;
                        $clientInfo['is_encrypted'] = false;          //Setting "true" not allowed currently at VM/instance level. Check later for modifications.
                        $clientInfo['priority'] = 500;
                        $clientInfo['no_credentials'] =true;
                        $result = $this->BP->save_client_info($clientInfo, $sid);
                        if($result===false){
                            return false;
                        }
                    }
                }

                if($credentialsInfo === NULL){
                    return "Please enter username and password";
                }
                $vcenterInfo['address']=$hostInfo['ip'];
                $vcenterInfo['login']=$credentialsInfo['username'];
                $vcenterInfo['password']=$credentialsInfo['password'];
                $vcenter=$this->BP->save_vcenter_info($vcenterInfo, $sid);
                if($vcenter===false){
                    return false;
                }
                return $result;
            } else if($inputArray['os_type'] === Constants::ADD_CLIENT_DISPLAY_NAME_NAS_NDMP_CLIENT) {
                //add alias to localhost
                $bValid = $this->addHostAlias($this->BP, 'localhost', $inputArray['name'], $sid);
                $credentialsResult  = $this->setCredentials($inputArray,$sid);
                if($credentialsResult === false){
                    $status['error'] = 500;
                    $status['message'] = $this->BP->getError();
                    return $status;
                }
            }
            else {
                if ($hostInfo['ip']!="") {
                    // check if the new client name is unique before proceeding
                    $bValid = $this->isClientNameValid($inputArray['name'], $sid);
                    if ($bValid) {
                        // Regular client.  First add to host file.
                        $clientToHost = $this->addClientToHostFile($inputArray, $sid);
                        if ($clientToHost === false) {
                            $status['error'] = 500;
                            $status['message'] = $this->BP->getError();
                            return $status;
                        }
                    } else {
                        $status['error'] = 500;
                        $status['message'] = "Client with the name " . $inputArray['name'] . " is already registered on the server.";
                        return $status;
                    }
                }
                // Regular client: setCredentials returns true if is_auth_enabled is false.
                $credentialsResult  = $this->setCredentials($inputArray,$sid);
                if($credentialsResult === false){
                    $status['error'] = 500;
                    $status['message'] = $this->BP->getError();
                    return $status;
                }
                else{
                    // Regular client: after set credentials, push agent on user request.
                    if($inputArray["install_agent"]){
                        $pushAgent = $this -> pushSupportedAgents($inputArray, $sid);
                        if($pushAgent === false){
                            $status['error'] = 500;
                            $status['message'] = $this->BP->getError();
                            return $status;
                        }
                    }
                }
            }
        }
        else{
            $status['error'] = 500;
            $status['message'] = $isValid['message'];
            return $status;
        }

        if (isset($inputArray['name'])) {
            $name = $inputArray['name'];
            if(strlen($name)<=31)
                $clientInfo = array ("name" => $name);
        }

        if (isset($inputArray['defaultschedule'])) {
            $addToDefault = $inputArray['defaultschedule'];
        }

        if (isset($inputArray['credential_id'])) {
            $crid = $inputArray['credential_id'];
            if ($crid == -1 or $crid == "-1") {
                $clientInfo["no_credentials"] = true;
            } else {
                $clientInfo["credential_id"] = $crid;
            }
        }
        else{
            if($credentialsInfo['username']==="" and $credentialsInfo['password']===""){
                $clientInfo["no_credentials"] = true;
            }
        }

        $clientInfo['is_enabled'] = $inputArray['is_enabled'];
        $clientInfo['is_synchable'] = $inputArray['is_synchable'];
        $clientInfo['is_encrypted'] = $inputArray['is_encrypted'];
        $clientInfo['priority'] = isset($inputArray['priority']) ? $inputArray['priority'] : 500 ;

        if ( isset ($inputArray['oid']) && $inputArray['oid'] == Constants::OS_GENERIC && isset($inputArray['generic_property']) ) {
            if ( $inputArray['generic_property'] === Constants::GENERIC_PROPERTY_XEN ||
                 $inputArray['generic_property'] === Constants::GENERIC_PROPERTY_AHV ) {
                $useRaeSave = false;
            } else {
                $useRaeSave = true;
            }
            $clientInfo['os'] = $inputArray['oid'];
            $clientInfo['os_type_id'] = isset($inputArray['oid']) ? strval($inputArray['oid']) : false;
            $clientInfo['generic_property']=$inputArray['generic_property'];
        } else {
            $useRaeSave = false;
        }

        global $Log;

        if ( isset( $inputArray['port'] )) { //NDMP only
            $useRaeSave = false;
            $clientInfo['remote_port'] = $inputArray['port'];
            $clientInfo['remote_address'] = $hostInfo['ip']; //can also set the remote address (ip from host info)
        }

        if ( isset( $inputArray['remote_address'] )) { //Xen or AHV only
            $useRaeSave = false;
            $clientInfo['remote_address'] = $inputArray['remote_address'];
        }

        if (isset($inputArray['ssl'])) {
            $clientInfo['use_ssl'] = $inputArray['ssl'] == '1' ? true : false;
        }

        if ($useRaeSave) {
            $result = $this->BP->save_rae_client_info($clientInfo, $sid);
        } else {
            $result = $this->BP->save_client_info($clientInfo, $sid);
        }

        if ($result !== false) {

            $result = array();
            if ( isset($clientInfo['name']) && ($name !== VCENTER_RRC)) {
                // If adding a client ($clientInfoID == NULL) and not client vCenter-RRC, create a schedule.
                // Let the user knows if this step is not successful.
                if (isset($addToDefault) && $addToDefault) {
                    // Comment this out.  This API does not work in Satori, as the selection lists have type 'file-level'.
                    //$result = $BP->add_client_to_default_schedule($clientInfo['name'], $dpuID);
                    $autoIncludeResult = $this->addToDefaultJobIfExists($clientInfo['name'], $sid);
                    if ($autoIncludeResult['status'] === true) {
                        $msg = "Successfully added " . $clientInfo['name'] .
                            ", and it was added to the backup job with the auto-include option set, " .
                            $autoIncludeResult['name'] . '.';
                        $result['message'] = $msg;
                    } elseif ($autoIncludeResult['status'] === false || is_array($autoIncludeResult['status'])) {
                        $msg = "Successfully added " . $clientInfo['name'] .
                            ", but it was not added to the backup job with the auto-include option set, " .
                            $autoIncludeResult['name'];
                        if ($autoIncludeResult['status'] === false) {
                            $msg .= ': ' . $this->BP->getError();                           
                        } else {
                            $msg .= isset($autoIncludeResult['status']['message']) ?
                                        (': ' . $autoIncludeResult['status']['message']) : '';
                        }
                        $result['message'] = $msg;                     
                    }
                }
            }
            $clientID=$this->BP->get_client_id($clientInfo['name'], $sid);
            $output['id'] = $clientID;

            // Do an inventory sync on the newly added client (done automatically for VMware)
            $syncStartStatus = $this->BP->put_inventory(array('client_id'=>$clientID), $sid);
            if ( $syncStartStatus !== true )
            {
                if ( array_key_exists('message', $result) )
                {
                    $Log->writeVariable( "The message is ".$result['message'] );
                    $result['message'] .= '.  An inventory sync was not automatically started for '.$clientInfo['name'].': ' . $this->BP->getError();
                }
                else
                {
                    $result['message'] = 'An inventory sync was not automatically started for '.$clientInfo['name'].': ' . $this->BP->getError();
                }
            }

            $result['result'] = $output ;
            return $result;
        } else {
            // on failure, return error to user.
            // At one time, we were deleting the associated credentialID, but should not as we have a credentials manager.

            $status['message'] = $this->BP->getError();
            $status['error'] = 500;
            $tempHostInfo = $this->BP->get_host_info($clientInfo['name'], $sid);
            if($tempHostInfo['ip']==="127.0.0.1"){
                $newAliasList=null;
                $aliasList=$tempHostInfo['aliases'];
                foreach($aliasList as $key=>$val){
                    if($val!==$clientInfo['name']){
                        $newAliasList[]=$val;
                    }
                }
                $tempHostInfo['original_ip'] = $tempHostInfo['ip'];
                $tempHostInfo['aliases']=$newAliasList;
                $saveHost=$this->BP->save_host_info($tempHostInfo,$sid);
                if($saveHost===false){
                    global $Log;
                    $msg= "could not update host file".$this->BP->getError();
                    $Log->writeError($msg, true);
                }
            }
            else{
                if (isset($clientToHost) && $clientToHost != "Exists") {
                    // If we added the client $clientToHost will be set and will not be the string "Exists".
                    $removeHost=$this->BP->remove_host_info($clientInfo['name'],$sid);
                    if($removeHost===false){
                        global $Log;
                        $msg = 'Cannot remove host ' . $clientInfo['name'] . ' ' . $this->BP->getError();
                        $Log->writeError($msg, true);
                    }                   
                }
            }
            return $status;
        }
    }

    /*
     * This function checks if the name of the new client we're trying is a duplicate
     */
    function isClientNameValid($inputName, $sid)
    {
        $isValid = true;
        $clientList = $this->BP->get_client_list($sid);
        if ($clientList !== false) {
            foreach ($clientList as $clientID => $clientName) {
                // if the name of the new client matches an existing client, return false
                if ($clientName == $inputName) {
                    $isValid = false;
                }
            }
        }
        return $isValid;
    }

    /*
     * This function adds the client to the hosts file if not present.
     * Returns boolean false if the process fails.
     * Returns boolean true if an entry was added to the hosts file.
     * Returns string "Exists" if the entry was already present in the hosts file.
     */
    function addClientToHostFile(&$input, $sid) {
        $hostInfo = $this->BP->get_host_info($input['name'], $sid);
        $hostInfoFromUser = $input['host_info'];
        $ipchange = false;
        if($hostInfo===false and  $input['os_type']  !== PROTECT_GENERIC_NAS){
            $newhostInfo=$input['host_info'];
        }
        else{
            $newhostInfo=$hostInfo;
            if($hostInfo['aliases']!==""){
                $aliases[]=$hostInfo['aliases'];
            }
            if($hostInfoFromUser['ip'] !== $hostInfo['ip']){
               $ipchange = true;
                $newhostInfo['original_ip'] = $hostInfo['ip'];
                $newhostInfo['ip'] = $hostInfoFromUser['ip'];
            }
        }
        if(isset($aliases) && $aliases !== null){
            $newname=strtolower($input['name']);
            foreach($aliases as $alias){
                if(strtolower($alias)===$newname){
                    return "Exists";
                }
            }
        }

        if ($this -> isGenericOS($input['os_type'])) {

            $genericID=0;
            switch($input['os_type']){
                case PROTECT_GENERIC_UCSM:
                    $genericID=Constants::GENERIC_PROPERTY_CISCO_UCS_MANAGER;
                    break;
                case PROTECT_GENERIC_UCSC:
                    $genericID=Constants::GENERIC_PROPERTY_CISCO_UCS_CENTRAL;
                    break;
                case PROTECT_GENERIC_NAS:
                    $genericID=Constants::GENERIC_PROPERTY_NDMP_DEVICE;
                    break;
                case Constants::ADD_CLIENT_DISPLAY_NAME_XEN:
                    $genericID=Constants::GENERIC_PROPERTY_XEN;
                    break;
                case Constants::ADD_CLIENT_DISPLAY_NAME_AHV:
                    $genericID=Constants::GENERIC_PROPERTY_AHV;
                    break;
                default:
                    $genericID=Constants::OS_GENERIC;
                    break;
            }
            $input['oid']=Constants::OS_GENERIC;
            $input['generic_property']=$genericID;

            if ( $input['generic_property'] === Constants::GENERIC_PROPERTY_NDMP_DEVICE or
                 $input['generic_property'] === Constants::GENERIC_PROPERTY_XEN or
                 $input['generic_property'] === Constants::GENERIC_PROPERTY_AHV ) {
                $hostInfo=$input['host_info'];
                $input['remote_address']=$hostInfo['ip'];
                $result = $this->addHostAlias($this->BP, 'localhost', $input['name'], $sid);
            }

        }

        if($ipchange === false and $hostInfo !== false){
            //This means hostinfo for this client is already present and ip is not changed. So continue with the process of addition of client.
            // Return a result to the caller which is different than if teh host were added.
            $result = "Exists";
        }
        else{
            $newhostInfo['name']=$input['name'];
            $result = $this->BP->save_host_info($newhostInfo, $sid);
        }

        return $result;
    }

    function setCredentials(&$input,$sid){
        if (!$input['is_auth_enabled']) {
            return true;
        }
        $credInfo=$input['credentials'];
        if (isset($input['credential_id']) and $input['credential_id'] !== null){
            // The credential exists, check to see if needs to be updated.
            $saveNeeded = false;
            if (isset($credInfo['username']) && $credInfo['username'] !== '') {
                $saveNeeded = true;
            }
            if (isset($credInfo['password']) && $credInfo['password'] !== '') {
                $saveNeeded = true;
            }
            if (isset($credInfo['domain']) && $credInfo['domain'] !== '') {
                $saveNeeded = true;
            }
            if ($saveNeeded) {
                $credInfo['credential_id'] = $input['credential_id'];
                if (!isset($credInfo['is_default'])) {
                    $credInfo['is_default'] = false;
                }
                // will return true on successful update, false on failure.
                $saveCred = $this->BP->save_credentials($credInfo,$sid);
                return $saveCred;
            }
        }
        else{
            if($credInfo['display_name'] === "" or $credInfo['display_name'] === null){
                $newCredInfo['display_name'] = $input['name'] . "-New-Credential";
            }
            else {
                $newCredInfo['display_name'] = $credInfo['display_name'];
            }
            if( $credInfo['is_default'] === "" or $credInfo['is_default'] === NULL){
                $newCredInfo['is_default'] = false;
            } else {
                $newCredInfo['is_default'] = $credInfo['is_default'];
            }
            $newCredInfo['username'] = $credInfo['username'];
            $newCredInfo['password'] = $credInfo['password'];
            $newCredInfo['domain'] = $credInfo['domain'];
            if($newCredInfo['username'] !== "" and $newCredInfo['password'] !== "" ){
                $saveCred = $this->BP->save_credentials($newCredInfo,$sid);
                if($saveCred === false){
                    return false;//$this->BP->getError();
                }
                else{
                    $input['credential_id'] = $saveCred;
                    return true;
                }
            }
        }
    }


    function pushSupportedAgents(&$input,$sid){
        $credInfo=$input['credentials'];
        $hostInfo=$input['host_info'];

        $cid=isset($input['cid']) ? $input['cid'] : "-1";
        // If the IP address is not set, use the resolvable client name.
        $ip = (isset($hostInfo['ip']) && $hostInfo['ip'] != "")  ? $hostInfo['ip'] : $input['name'];
        $ostype=isset($input['os_type']) ? $input['os_type'] : "";
        $credid=isset( $input['credential_id']) ?  $input['credential_id'] : "-1";

        $result = true;
        if($input['os_type'] === "Windows"){
            $result = $this->BP->push_agent($cid, $ip, $ostype, $credid, $sid);
        }
        return $result;

    }

    function isGenericOS($osType){
        if ($osType === PROTECT_GENERIC_UCSM
            or $osType === PROTECT_GENERIC_UCSC
            or $osType === PROTECT_GENERIC_NAS
            or $osType === Constants::ADD_CLIENT_DISPLAY_NAME_XEN
            or $osType === Constants::ADD_CLIENT_DISPLAY_NAME_AHV
            or $osType === ALL_OTHER_OS) {
            return true;
        }
        else{
            return false;
        }
    }



    function addHostAlias($BP, $hostName, $aliasName, $dpuID) {
        $bValid = true;
        $hostInfo = $this->BP->get_host_info($aliasName, $dpuID);
        if ($hostInfo !== false) {
            ; // the alias name is already present, don't need to add it.
        } else {
            // The alias is not there, add to the host entry for host name.
            $hostInfo = $BP->get_host_info($hostName, $dpuID);
            if ($hostInfo !== false) {
                // Update existing entry, add the alias if not already present.
                $hostInfo['original_ip'] = $hostInfo['ip'];
                $aliases = isset($hostInfo['aliases']) ? $hostInfo['aliases'] : array();
                if (!in_array($aliasName, $aliases)) {
                    $aliases[] = $aliasName;
                    $hostInfo['aliases'] = $aliases;
                    $result = $BP->save_host_info($hostInfo, $dpuID);
                    if ($result === false) {
                        global $Log;
                        $Log->writeError($BP->getError(), true);
                    }
                    $bValid = $result;
                } else {
                    $bValid = true;
                }
            } else {
                $bValid = false;
            }
        }
        return $bValid;
    }

    function removeHostAlias($BP, $hostName, $aliasName, $dpuID) {
        global $Log;
        $Log->writeVariableDBG( "RemoveHost alias ".$aliasName) ;
        $bValid = true;
        $newHostInfo = $this->BP->get_host_info($hostName, $dpuID);
        if ($newHostInfo !== false) {
            $newAliasList=null;
            $aliasList=$newHostInfo['aliases'];
            foreach($aliasList as $key=>$val){
                if($val!==$aliasName){
                    $newAliasList[]=$val;
                }
            }
            $newHostInfo['original_ip'] = $newHostInfo['ip'];
            $newHostInfo['aliases']=$newAliasList;
            $saveHost=$this->BP->save_host_info($newHostInfo,$dpuID);
            if($saveHost===false){
                global $Log;
                $msg= "could not update host file".$this->BP->getError();
                $Log->writeError($msg, true);
            }
            $bValid = $saveHost;
        } else {
            $bValid = false;
        }
        return $bValid;
    }


    function createClientSchedule($clientInfo, $dpuID) {
        global $BP;

        // Use Friday Master, Daily Incremental as the default.
        $iCal = <<< EOM
BEGIN:VCALENDAR
BEGIN:VEVENT
SUMMARY:Master
DESCRIPTION:weekly master
DTSTART:20120603T030000
DTEND:20120603T040000
RRULE:FREQ=WEEKLY;BYDAY=SU
END:VEVENT
BEGIN:VEVENT
SUMMARY:Incremental
DESCRIPTION:daily incremental
DTSTART:20120604T030000
DTEND:20120604T040000
RRULE:FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR
END:VEVENT
END:VCALENDAR
EOM;

        $clientID = $BP->get_client_id($clientInfo['name'], $dpuID);

        $defaultName = $clientInfo['name'] . ' Schedule';
        $description = 'Automatically created backup schedule for ' . $clientInfo['name'];

        $backupOptions = 	array(	'verify_level' => 3);			// set verify level to inline by default.
        $scheduleOptions = 	array(	'client_id' => $clientID,
            'include_new' => false,			// cannot be true for file-level schedule
            'email_report' => true,
            'failure_report' => true);

        $scheduleInfo = 	array(	'name' => $defaultName,
            'description' => $description,
            'enabled' => true,				// create as enabled.
            'app_id' => 1,					// app_id 1 is file-level
            'calendar' => $iCal,
            'backup_options' => $backupOptions,
            'schedule_options' => $scheduleOptions);

        $result = $BP->save_app_schedule_info($scheduleInfo, $dpuID);

        return $result;
    }


    function isValid(&$input,$sid){
        $error['valid'] = true;
        $server = isset($input['name']);
        $os = $input['os_type'];
        $hostinfo = $input['host_info'];
        if($os === "VMware") {
            if ($hostinfo['ip'] === "" && ($server === "" or $server === null)) {
                $error['message'] = "Either VMware server name or IP address is required.";
                $error['valid'] = false;
            }
        }
            else{
                if($server === ""){
                    $error['message'] = "Host Name is required.";
                    $error['valid'] = false;
                }
                if($os === "Windows" and (strlen($server) > 15)){
                    $error['message'] = "Host Name is too long.  For Windows, the maximum allowed is 15 characters.";
                    $error['valid'] = false;
                }
                elseif(strlen($server)> 31){
                    $error['message'] = "Host Name is too long.  The maximum allowed is 31 characters.";
                    $error['valid'] = false;
                }
            }

        //Currently using "is_auth_enabled" as input parameter to check if user wants to establish trust

        if ($error['valid'] && $input['is_auth_enabled'] == true) {
            $errorString = $this-> validateCredentials($input, $sid);
            if ($errorString != null) {
                $error['message'] = $errorString;
                $error['valid'] = false;
                return $errorString;
            }
        }
        return $error;
    }

    function validateCredentials(&$input, $sid) {
        $s = NULL;
        if (isset($input['credentials'])) {
            $credInfo = $input['credentials'];
            $credlist = $this->BP->get_credentials_list($sid);
            $exists = false;
            foreach($credlist as $credential){
                if (isset($credential['display_name']) &&
                    ($credential['display_name'] === $credInfo['display_name'] or $credential['display_name'] === $input['name'] . "-New-Credential")){
                    $exists = true;
                    $input['credential_id'] = $credential['credential_id'];
                    $credInfo['display_name'] = $credential['display_name'];
                    $credInfo['is_default'] = $credential['is_default'];
                    $input['credentials'] = $credInfo;
                }
            }

            if(!$exists){
                if (!$credInfo['is_default']) {
                    if($input['credential_id'] === -1 or $input['credential_id'] === "-1"){
                        $s = "No credentials have been selected";
                    }
                    else if($credInfo['username'] === null or $credInfo['username'] === "" ){
                        $s = "Enter username";
                    }
                    else if($credInfo['password'] === null or $credInfo['password'] === "" ){
                        $s = "Enter password";
                    }
                }
                else{
                    $defaultcred = $this->BP->get_default_credentials($sid);
                    $input['credential_id'] = $defaultcred['credential_id'];
                    if($defaultcred === null or $defaultcred === ""){
                        $s="No default credentials exist.  Please create a new credential or select an existing one.";
                    }
                    else if($defaultcred['credential_id'] !== $credInfo['credential_id']){
                        $s = "Not able to use default credentials.  Please enter username and password.";
                    }
                }
            }
        } else if (isset($input['credential_id'])) {
            $id = $input['credential_id'];
            if ($id < 0) {
                $s = "Invalid credential id.";
            }
        } else {
            $s = "No credentials were specified.";
        }
        return $s;
    }

    function getClientsOnTarget() {
        if (Functions::supportsRoles()) {
            $this->Roles = new Roles($this->BP);
        }
        $data = array('data' => array());
        $request = "GET";
        $api = "/api/clients/";
        $parameters = "grandclient=true";
        if (isset($_GET['searchFiles'])) {
            $parameters .= "&searchFiles=" . $_GET['searchFiles'];
        }
        $result = $this->functions->remoteRequest("", $request, $api, $parameters);
        if (is_array($result)) {
            // If roles are defined, find those in scope; otherwise, return all.
            if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                $clients = array();
                $localID = $this->BP->get_local_system_id();
                $remoteList = $result['data'];
                foreach ($remoteList as $client) {
                    if ($this->Roles->client_name_is_in_scope($client['name'], $localID)) {
                        $clients[] = $client;
                    }
                }
                $data = array('data' => $clients);
            } else {
                $data = $result;
            }
        }
        return $data;
    }

    function getFilesOnTarget($filter) {
        $cid = isset($filter) ? $filter : false;

        $directory = "";
        $last = "";
        if (isset($_GET['dir'])) {
            $directory = $_GET['dir'];
            // encode '#' and '&' so that they don't get parsed during remote request
            $directory = str_replace('#', '%23', $directory);
            $directory = str_replace('&', '%26', $directory);
            $directory = "dir=" . $directory;
        }
        if (isset($_GET['last'])) {
            $last = $_GET['last'];
            // encode '#' and '&' so that they don't get parsed during remote request
            $last = str_replace('#', '%23', $last);
            $last = str_replace('&', '%26', $last);
            $last = "&last=" . $last;
        }
        $count = isset($_GET['count']) ?  "&count=" . $_GET['count'] : "";

        $data = array('data' => array());
        $request = "GET";
        $api = "/api/clients/files/" . ($cid ? $cid . "/" : "");
        $parameters = $directory . $last . $count;
        $result = $this->functions->remoteRequest("", $request, $api, $parameters, NULL, 1);
        if (is_array($result)) {
            $data = $result;
        }
        return $data;
    }

    function getSystemClient() {
        $result = array();
        $sid = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        $systemInfo = $this->BP->get_system_info($sid);
        if ($systemInfo !== false) {
            $clients = $this->BP->get_client_list($sid);
            if ($clients !== false) {
                foreach ($clients as $clientID => $clientName) {
                    if ($clientName == $systemInfo['name']) {
                        $item = array();
                        $item['id'] = $clientID;
                        $item['name'] = $clientName;
                        $result['system_client'] = $item;
                        break;
                    }
                }
            } else {
                $result['error'] = 500;
                $result['message'] = $this->BP->getError();
            }
        } else {
            $result['error'] = 500;
            $result['message'] = $this->BP->getError();
        }
        return $result;
    }

    function getSystemClientOnTarget() {
        $data = array('data' => array());
        $request = "GET";
        $api = "/api/clients/system/";
        $parameters = "";
        $result = $this->functions->remoteRequest("", $request, $api, $parameters, NULL, 1);
        if (is_array($result)) {
            $data = $result;
        }
        return $data;
    }

    function agentPush($data) {
        $allClients = array();
        foreach($data['clients'] as $client) {
            $clientReturn = array();

            if(isset($client['client_id'])) {
                $clientID = $client['client_id'];
            } else {
                //log that client id is needed for this client
                $clientReturn['client'] = "";
                $clientReturn['message'] = "Client ID is required.";
                $allClients[] = $clientReturn;
                continue;
            }
            if(isset($client['system_id'])) {
                $sid = $client['system_id'];
            } else {
                //log that system id is needed for this client
                $clientReturn['client'] = $clientID;
                $clientReturn['message'] = "A system id for this client is required.";
                $allClients[] = $clientReturn;
                continue;
            }
            if(isset($client['credential_id'])) {
                $credentialID = $client['credential_id'];
            } else {
                $credentialID = null;
            }
            $clientInfo = $this->BP->get_client_info($clientID, $sid);
            if($clientInfo !== false) {
                $clientName = $clientInfo['name'];
                $osFamily = $this->functions->getOSFamily($clientInfo['os_type_id']);
                if($osFamily == Constants::OS_FAMILY_WINDOWS) {
                    $updateAvailable = false;
                    $updateInfo = $this->BP->agent_update_available($clientID, $sid);
                    if($updateInfo !== false) {
                        foreach($updateInfo as $update) {
                            if($clientID == $update['clientID']) {
                                $updateAvailable = isset($update['isUpdateAvailable']) ? $update['isUpdateAvailable'] : false;
                            }
                        }
                        if($updateAvailable !== false) {
                            $ip = $this->getIP($clientName, $sid);
                            $agentPushResult = $this->BP->push_agent($clientID, $ip, $osFamily, $credentialID, $sid);
                            if($agentPushResult !== false) {
                                $clientReturn['client'] = $clientName;
                                $clientReturn['message'] = "Update successful.";
                            } else {
                                $clientReturn['client'] = $clientName;
                                $clientReturn['message'] = $this->BP->getError();
                            }
                        } else {
                            //log message that agent is up to date
                            $clientReturn['client'] = $clientID;
                            $clientReturn['message'] = "Latest available agent version is installed";
                        }
                    } else {
                        //log message from update available
                        $clientReturn['client'] = $clientID;
                        $clientReturn['message'] = $this->BP->getError();
                    }
                } else {
                    //log error that this is not Window client. Nothing to do
                    $clientReturn['client'] = $clientName;
                    $clientReturn['message'] = "This client is not a Windows client. Agents can only be installed or updated on Windows clients.";
                }
            } else {
                //log unable to retrieve the client information
                $clientReturn['client'] = $clientID;
                $clientReturn['message'] = $this->BP->getError();
            }

            $allClients[] = $clientReturn;
        }
        return $allClients;
    }

/*    function getClientName($clientID, $sid) {
        $clientInfo = $this->BP->get_client_info($clientID, $sid);
        if($clientInfo !== false) {
            return $clientInfo['name'];
        } else {
            return false;
        }
    } */

    function getIP($clientName, $sid) {
        $hostInfo = $this->BP->get_host_info($clientName, $sid);
        if($hostInfo !== false) {
            if(isset($hostInfo['ip'])) {
                return $hostInfo['ip'];
            } else {
                return $clientName;
            }
        } else {
            return $clientName;
        }
    }

    // for use with cloud search/get, which only works with windows clients as of 9.1
    function getSupportedClients($result) {
        $supportedClients = array();
        foreach($result as $item){
            if($item['os_family'] == Constants::OS_FAMILY_WINDOWS
                || $item['os_family'] == Constants::OS_FAMILY_MAC_OS
                || $item['os_family'] == Constants::OS_FAMILY_LINUX) {
                $supportedClients['data'][] = $item;
            }
        }
        return $supportedClients;
    }

    function addToDefaultJobIfExists($name, $sid) {
        $result = array('name' => '', 'status' => '');
        $hasDefault = false;
        $schedules = $this->BP->get_schedule_list($sid);
        if ($schedules !== false) {
            foreach ($schedules as $schedule) {
                $info = $this->BP->get_schedule_info($schedule['id'], $sid);
                if ($info !== false) {

                    $options = $info['options'];
                    if ( $options['include_new'] === true ) {

                        $clientID = $this->BP->get_client_id($name, $sid);
                        $clientInfo = $this->BP->get_client_info($clientID, $sid);

                        $newInfo = array();
                        $newInfo['clients'] = $info['clients'];
                        $newInfo['id'] = $schedule['id'];
                        $newInfo['clients'][] = array("id" => $clientID);
                        $status = false;

                        if ( $clientInfo !== false ) {
                            if (  isset($clientInfo['blk_support']) and $clientInfo['blk_support'] !== Constants::BLK_SUPPORT_BLOCK_NOT_SUPPORTED ) {
                                // Image Level backups are supported
                                $status = $this->BP->save_schedule_info($newInfo, $sid);
                            } elseif (isset($schedule['calendar'])) {
                                // Image level backups are not supported for this client.
                                //Require the Schedules code to determine whether or not this Schedule has Block backups
                                require_once('schedules.php');
                                $schedules = new Schedule($this->BP);
                                $convertedCal = $schedules->iCalToSchedule($schedule['calendar']);
                                if ( $convertedCal['hasImageLevelBackups'] !== true ) {
                                    // Only save this schedule if it doesn't have image level backups
                                    $status = $this->BP->save_schedule_info($newInfo, $sid);
                                } else {
                                    // The schedule has image level backups and the client does not support image level backups.
                                    $status = array( 'message'=>'This asset does not support image level backups and the job with the auto-include option set contains image level backups.'  );

                                }
                            } else {
                                // The calendar did not come back in $schedule
                                $status = array( 'message'=>'Could not determine the backup method of the job with the auto-include option set.'  );
                            }

                        } elseif (isset($schedule['calendar'])) {
                            // The client info did not come back, so it is impossible to determine whether or not Block backups are supported by that client.
                            //Require the Schedules code to determine whether or not this Schedule has Block backups
                            require_once('schedules.php');
                            $schedules = new Schedule($this->BP);
                            $convertedCal = $schedules->iCalToSchedule($schedule['calendar']);
                            if ($convertedCal['hasImageLevelBackups'] !== true) {
                                // Only save this schedule if it doesn't have image level backups
                                $status = $this->BP->save_schedule_info($newInfo, $sid);
                            } else {
                                // The client info is blank
                                $status = array( 'message'=>'Could not determine whether or not this asset supports image level backups.'  );
                            }
                        } else {
                            // The calendar did not come back in $schedule and the client info is blank
                            $status = array( 'message'=>'Could not determine whether or not this asset supports image level backups.'  );
                        }

                        $result = array('name' => $schedule['name'], 'status' => $status);
                        break;
                    }                   
                }
            }
        }
        return $result;
    }
}

?>
