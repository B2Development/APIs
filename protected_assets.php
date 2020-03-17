<?php

class Assets
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);

        require_once('quiesce.php');
        $this->quiesce = new Quiesce($this->BP);

        $this->localID = $this->BP->get_local_system_id();
        $this->grandClientView = false;

        $this->Roles = null;
    }

    public function get($which)
    {
        $bValid = true;

        $sid = isset($_GET['sid']) ? (int)$_GET['sid'] : false;
        $grandClientView = isset($_GET['grandclient']) && $_GET['grandclient'] === "true";
        $this->grandClientView = $grandClientView;
        $includeVCenter = isset($_GET['include_vmware_client']) && $_GET['include_vmware_client'] === "true";
        $includeRetention = isset($_GET['include_retention']) && $_GET['include_retention'] === "true";
        $getProtectedVmware = true;
        $getProtectedHyperv = true;
        $getProtectedBlock = true;
        if (isset($_GET['type'])){
            $types = explode("," , $_GET['type']);
            $getProtectedVmware = in_array("vmware",$types);
            $getProtectedHyperv = in_array("hyperv",$types);
            $getProtectedBlock = in_array("block",$types);
        }

        if (isset($_GET['use_role_scope']) && $_GET['use_role_scope'] === 'true') {
            // If for search, consider role scope
            if (Functions::supportsRoles()) {
                $this->Roles = new Roles($this->BP);
            }
        }

        // Do not get non-managed system's assets in case of Protected Assets
        $systems = $grandClientView ? $this->functions->selectSystems($sid) : $this->functions->selectSystems($sid, false);

        $allClients = array();
        if ($sid !== false) {
            foreach ($systems as $id => $name) {
                if ($id === $sid) {
                    $system_id = $id;
                    $system_name = $name;
                    break;
                }
            }
            switch ($which){
                case 'protected' :
                    $allClients = $this->getProtectedClientsForSystem($system_id, $system_name, $grandClientView, $getProtectedVmware, $getProtectedHyperv, $getProtectedBlock);
                    break;
                default :
                    $allClients = $this->getClientsForSystem($which, $system_id, $system_name, $grandClientView, $includeVCenter, $includeRetention);
            }
        } else {
            foreach ($systems as $id => $name) {
                switch ($which){
                    case 'protected':
                        $clients = $this->getProtectedClientsForSystem($id, $name, $grandClientView, $getProtectedVmware, $getProtectedHyperv, $getProtectedBlock);
                        break;
                    default :
                        $clients = $this->getClientsForSystem($which, $id, $name, $grandClientView, $includeVCenter, $includeRetention);
                }
                $allClients = array_merge($allClients, $clients);
            }
        }
        $allClients = $this->sort($allClients);
        $allClients = array('data' => $allClients);
        return $allClients;
    }

    // Currently this API should just be called for VMware VMs and Xen VMs.
    // Currently this API just saves credentials and/or quiesce settings.
    public function put_assets($which, $data, $sid) {

        $status = false;
        global $Log;

        if ($data !== NULL and array_key_exists('instance_ids', $data)) {
            $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();

            $instance_ids_string = $data['instance_ids'];
            $instance_ids = json_decode('[' . $instance_ids_string . ']', true); // This quickly changes the $instance_ids_string into an array of integers
            if ( count($instance_ids) > 0  ) {
                $instances_info = $this->BP->get_appinst_info($instance_ids_string, $sid);
                if ( $instances_info !== false ) {
                    $instances_support_app_aware = true;
                    $instances_support_credentials = true;
                    $instances_support_quiesce = $this->BP->is_quiesce_supported($sid);
                    if ( $instances_support_quiesce === -1 )  {
                        // An error occurred checking to see if quiesce is supported on that system
                        // Set quiesce supported to false (this will default to the way that is supported on all systems) and log the error
                        $Log->writeError("cannot determine whether quiesce is supported: " . $this->BP->getError(), true);
                        $instances_support_quiesce = false;
                    }

                    $quiesce_setting = false;
                    if ( array_key_exists('quiesce_setting', $data) ) {
                        $quiesce_setting = $this->quiesce->getQuiesceSettingfromDisplayName($data['quiesce_setting']);
                    }

                    foreach ($instances_info as $instance) {
                        switch ($instance['app_type']) {
                            case Constants::APPLICATION_TYPE_NAME_VMWARE:
                                break;
                            case Constants::APPLICATION_TYPE_NAME_XEN:
                            case Constants::APPLICATION_TYPE_NAME_AHV:
                                $instances_support_app_aware = false;
                                $instances_support_credentials = false;
                                break;
                            case Constants::APPLICATION_TYPE_NAME_ORACLE:
                            case Constants::APPLICATION_TYPE_NAME_SHAREPOINT:
                                $instances_support_app_aware = false;
                                $instances_support_quiesce = false;
                                break;
                            default:
                                $instances_support_app_aware = false;
                                $instances_support_credentials = false;
                                $instances_support_quiesce = false;
                                break;
                        }
                    }

                    $credential_id = false;
                    if ( $instances_support_credentials ) {
                        if ( array_key_exists('credential_id', $data) ) {
                            $credential_id = $data['credential_id'];
                        } elseif ( array_key_exists('new_credential', $data) ) {
                            $new_credential = $data['new_credential'];
                            $new_credential_info = array();
                            if(isset($new_credential['display_name']) && ($new_credential['display_name']) !== "" ){
                                $new_credential_info['display_name'] = $new_credential['display_name'];
                            }
                            if(!isset($new_credential['is_default'])){
                                $new_credential_info['is_default'] = false;
                            } else {
                                $new_credential_info['is_default'] = $new_credential['is_default'];
                            }
                            $new_credential_info['username'] = $new_credential['username'];
                            $new_credential_info['password'] = $new_credential['password'];

                            if (  isset( $new_credential_info['domain'] ) ) {
                                $new_credential_info['domain'] = $new_credential['domain'];
                            }
                            $save_credential = $this->BP->save_credentials($new_credential_info,$sid);
                            if($save_credential === false){
                                return false;//$this->BP->getError();
                            }
                            else{
                                $credential_id = $save_credential;
                            }
                        }

                        if ( $credential_id !== false and !($instances_support_quiesce and $quiesce_setting === Constants::QUIESCE_SETTING_APPLICATION_AWARE) ) {
                            // If $instances_support_quiesce and $quiesce_setting === Constants::QUIESCE_SETTING_APPLICATION_AWARE, then credentials are set down below using bp_save_quiesce_settings
                            $set_credential_info_array = array();
                            foreach ($instance_ids as $set_credential_instance_id) {
                                $set_credential_info_array[] = array('instance_id' => $set_credential_instance_id,
                                    'credential_id' => $credential_id);
                            }
                            $status = $this->BP->bind_instance_credentials($set_credential_info_array, $sid);
                            if ( $status === false ) {
                                // There was an error, exit out and let the parent call $this->BP->getError();
                                return $status;
                            }
                        }
                    }

                    if ( $quiesce_setting !== false ) {
                        if ($instances_support_quiesce) {
                            if ($quiesce_setting === Constants::QUIESCE_SETTING_APPLICATION_AWARE) {
                                if ($instances_support_app_aware !== true) {
                                    $status['error'] = 500;
                                    $status['message'] = "The selected assets do not support a quiesce setting of Application Aware.";
                                    return $status;
                                } elseif ($credential_id === false) {
                                    $status['error'] = 500;
                                    $status['message'] = "Credentials are required for a quiesce setting of Application Aware.";
                                    return $status;
                                } else {
                                    $save_quiesce_info_array = array();
                                    foreach ($instance_ids as $app_aware_quiesce_instance) {
                                        $save_quiesce_info_array[] = array('instance_id' => $app_aware_quiesce_instance,
                                            'quiesce' => Constants::QUIESCE_SETTING_APPLICATION_AWARE,  // In this for loop, $quiesce_setting is always Constants::QUIESCE_SETTING_APPLICATION_AWARE
                                            'credential_id' => $credential_id);
                                    }
                                    $status = $this->BP->save_quiesce_settings($save_quiesce_info_array, $sid);
                                    if ( $status === false ) {
                                        // There was an error, exit out and let the parent call $this->BP->getError();
                                        return $status;
                                    }
                                }
                            } else {
                                $save_quiesce_info_array = array();
                                foreach ($instance_ids as $quiesce_instance) {
                                    $save_quiesce_info_array[] = array('instance_id' => $quiesce_instance,
                                        'quiesce' => $quiesce_setting );
                                }
                                $status = $this->BP->save_quiesce_settings($save_quiesce_info_array, $sid);
                                if ( $status === false ) {
                                    // There was an error, exit out and let the parent call $this->BP->getError();
                                    return $status;
                                }
                            }
                        } elseif  ( $instances_support_app_aware and $quiesce_setting !== Constants::QUIESCE_SETTING_CRASH_CONSISTENT ) {
                            $use_app_aware = ($quiesce_setting === Constants::QUIESCE_SETTING_APPLICATION_AWARE);
                            $save_app_aware_legacy_method_info_array = array();
                            foreach ($instance_ids as $app_aware_legacy_method_instance) {
                                $save_app_aware_legacy_method_info_array[] = array('instance_id' => $app_aware_legacy_method_instance,
                                    //'credential_id' => $credential_id, // $credential_id has already been set, so just go with that.
                                    'app_aware' => $use_app_aware);
                            }
                            $status = $this->BP->save_app_credentials_info($save_app_aware_legacy_method_info_array, $sid);
                            if ( $status === false ) {
                                // There was an error, exit out and let the parent call $this->BP->getError();
                                return $status;
                            }
                        } else {
                            $status['error'] = 500;
                            $status['message'] = "The selected instances do not support the selected quiesce setting.";
                            return $status;
                        }
                    }

                    if ( array_key_exists('no_credentials', $data) and $data['no_credentials'] === true ) {
                        $status = $this->BP->unbind_instance_credentials($instance_ids_string, $sid);
                        if ( $status === false ) {
                            // There was an error, exit out and let the parent call $this->BP->getError();
                            return $status;
                        }
                    }

                    if ( $status === false ) {
                        $status['error'] = 500;
                        $status['message'] = "Nothing was saved.  Please provide a valid input for quiesce settings and credentials.";
                        return $status;
                    }
                }
            } else {
                $status['error'] = 500;
                $status['message'] = "Missing inputs: 'instance_ids' is a required input";
                return $status;
            }
        } else {
            $status['error'] = 500;
            $status['message'] = "Missing inputs: 'instance_ids' is a required input";
            return $status;
        }

        return $status;
    }

    function getClientsForSystem($which, $system_id, $system_name, $grandClientView, $includeVCenter, $includeRetention)
    {

        global $Log;
        $noESX = isset($_GET['noESX']) ? $_GET['noESX'] : false;
        $allClients = array();
        $dpuID = $grandClientView ? $this->localID : $system_id;

        // Determine whether or not the system supports quiesce
        $is_quiesce_supported = $this->BP->is_quiesce_supported($system_id);
        if ( $is_quiesce_supported === -1 )  {
            // An error occurred checking to see if quiesce is supported on that system
            // Set quiesce supported to false (this will default to the way that is supported on all systems) and log the error
            $Log->writeError("cannot determine whether quiesce is supported: " . $this->BP->getError(), true);
            $is_quiesce_supported = false;
        }

        $isBlockSupported = $this->BP->block_backup_supported($system_id);
        if ( $isBlockSupported === -1 )
        {
            global $Log;
            $Log->writeError("cannot determine whether block is supported: " .$this->BP->getError(), true);

            // Users should be able to see clients for backup even if the call fails
            $isBlockSupported = true;
        }

        if($which !== -1 and $which == "archive") {
            $clients = $grandClientView ? $this->BP->get_archive_grandclients($system_id) : $this->BP->get_archive_client($system_id);
            if($clients !== false) {
                //convert the array to the same format as the get_client_list format to leverage code already written
                $tempClients = array();
                foreach($clients as $client) {
                    if($grandClientView) {
                        $tempClients[$client['client_id']] = $client['gcname'];
                    } else {
                        $tempClients[$client['id']] = $client['name'];
                    }
                }
                $clients = $tempClients;
            }
        } else {
            $clients = $grandClientView ? $this->BP->get_grandclient_list($system_id) : $this->BP->get_client_list($system_id);
        }

        $forSearch = isset($_GET['searchFiles']) ? $_GET['searchFiles'] === 'true' : false;
        if ($clients !== false) {
            foreach ($clients as $id => $name) {
                if ($which > 0 and $id != $which) {
                    // The user specified an ID, but not this one.
                    continue;
                }
                if ($system_name == $name) {
                    // Skip the "System Client".
                    continue;
                }
                if (!$includeVCenter && $name === VCENTER_RRC) {
                    // Skip the VCenter-RRC client.
                    if (!$grandClientView || $noESX) {
                        continue;
                    }
                }
                if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                    if (!$this->Roles->client_is_in_scope($id, $system_id)) {
                        continue;
                    }
                }
                $clientInfo = $this->BP->get_client_info($id, $dpuID);

                if ($clientInfo !== false) {

                    if ($forSearch) {
                        $osType = $clientInfo['os_type'];
                        if ($osType === Constants::ADD_CLIENT_DISPLAY_NAME_XEN ||
                            $osType === Constants::ADD_CLIENT_DISPLAY_NAME_AHV) {
                            // If returning clients for file search, skip special ones that will have no file-level backups.
                            continue;
                        }
                    }

                    $hostInfo = $grandClientView ? array("ip" => "N/A") : $this->BP->get_host_info($clientInfo['name'], $system_id);
                    $clientName = $clientInfo['name'];
                    $associations = $this->getRetention(array('instance_id' => $clientInfo['file_level_instance_id']), $dpuID);
                    $this->getRetentionFromAssociations($associations, $clientInfo['file_level_instance_id'],
                                                        $clientInfo['retention'], $clientInfo['gfs_policy'], $clientInfo['min_max_policy']);;
                    $credentials = isset($clientInfo['credentials']) ? $clientInfo['credentials'] : null;
                    $clientInfo['credential_display'] = $this->getCredentialsDisplay($credentials, true);
                    if ($this->functions->isClientWindows($clientInfo['os_type_id'])) {
                        $clientInfo['supports_agent_push'] = true;
                    } else {
                        $clientInfo['supports_agent_push'] = false;
                    }
                    if($grandClientView && $clientInfo['name'] === Constants::CLIENT_NAME_VCENTER_RRC){
                        $clientInfo['asset_type'] = Constants::ASSET_TYPE_VMWARE_HOST;
                        $clientInfo['type'] = $clientInfo['os_family'];
                    }
                    else{
                        if($clientInfo['os_type'] === Constants::ADD_CLIENT_DISPLAY_NAME_XEN){
                            $clientInfo['asset_type'] = Constants::ADD_CLIENT_DISPLAY_NAME_XEN;
                            $clientInfo['type'] = $clientInfo['os_family'];
                        } else if($clientInfo['os_type'] === Constants::ADD_CLIENT_DISPLAY_NAME_AHV) {
                            $clientInfo['asset_type'] = Constants::ADD_CLIENT_DISPLAY_NAME_AHV;
                            $clientInfo['type'] = $clientInfo['os_family'];
                        }
                        else{
                            $clientInfo['asset_type'] = Constants::ASSET_TYPE_PHYSICAL;
                            $clientInfo['type'] = $clientInfo['os_family'];
                        }
                    }
                    // Passing in local system's ID in case of grandclient
                    if ($grandClientView) {
                        $clientInfo['local_system_id'] = $this->localID;
                    }
                    $clientInfo['system_id'] = $system_id;
                    $clientInfo['system_name'] = $system_name;
                    if(isset($clientInfo['remote_address']) && $clientInfo['remote_address'] !== null){
                        $clientInfo['ip'] = $clientInfo['remote_address'];
                    }
                    else{
                        $clientInfo['ip'] = $hostInfo['ip'];
                    }

                    $clientInfo['block_supported'] = false;
                    if ( $isBlockSupported === true
                        and isset($clientInfo['blk_support'])
                        and $clientInfo['blk_support'] !== Constants::BLK_SUPPORT_BLOCK_NOT_SUPPORTED ) {
                        // If block_supported is true, then the appliance supports block agent, and the asset current supports block agent.
                        $clientInfo['block_supported'] = true;
                    }

                    $applications = $clientInfo['applications'];
                    if (count($applications) > 0) {
                        $hasFileLevel = false;
                        foreach ($applications as $appID => $appInfo) {
                            if ($appInfo['name'] == Constants::APPLICATION_NAME_FILE_LEVEL) {
                                $hasFileLevel = true;
                                // skip file level
                                continue;
                            }
                            if ($appInfo['visible'] === false) {
                                // skip invisible
                                continue;
                            }
                            if ($grandClientView && $appInfo['name'] == Constants::APPLICATION_NAME_VMWARE) {
                                if (isset($appInfo['servers'])) {
                                    $servers = $appInfo['servers'];
                                    $server = $clientInfo;
                                    $server['asset_type'] = Constants::ASSET_TYPE_VMWARE_HOST;
                                    if (isset($server['os_type'])) {
                                        unset($server['os_type']);
                                    }
                                    foreach ($servers as $host) {
                                        $results = $this->BP->get_grandclient_vm_info($host, $clientInfo['id']);
                                        if ($results !== false) {
                                            $vms = $this->gather($results, $includeRetention, $system_id, $system_name, $clientInfo['id'], $clientInfo['name'],
                                                Constants::APPLICATION_ID_VMWARE, Constants::ASSET_TYPE_VMWARE_VM);
                                            $server['children'] = $vms;
                                        }
                                        $server['type'] = Constants::APPLICATION_TYPE_NAME_VMWARE;
                                        $server['name'] = $host;
                                        $allClients[] = $server;
                                    }
                                }
                            } else {
                                $assets = $this->getAssets($id, $clientName, $appID, $appInfo, $grandClientView, $includeRetention, $system_id, $system_name, $is_quiesce_supported);
                                $appInfo = array ('id' => $appID, 'name' => $appInfo['name'], 'description' => $appInfo['name'], 'type' => $appInfo['type'], 'system_id' => $system_id);
                                // Passing in local system's ID in case of grandclient
                                if ($grandClientView) {
                                    $appInfo['local_system_id'] = $this->localID;
                                }

                                // Handle AHV and block-level in a special way.  Only one application per registered "client", so skip app node.
                                if ($appID === Constants::APPLICATION_ID_AHV) {
                                    $clientInfo['children'] = $assets;
                                } else if ($appID === Constants::APPLICATION_ID_BLOCK_LEVEL) {
                                    if ($hasFileLevel && count($applications) === 2) {
                                        // only file and block level.
                                        $clientInfo['children'] = $assets;
                                    } else {
                                        // other apps, so add block info asset to child list.
                                        $clientInfo['children'][] = $assets[0];
                                    }
                                } else {
                                    $appInfo['children'] = $assets;
                                    $clientInfo['children'][] = $appInfo;
                                }
                            }
                        }
                    }

                    $isNAS = (($temp = strlen($clientInfo['name']) - strlen(Constants::NAS_POSTFIX)) >= 0 && strpos($clientInfo['name'], Constants::NAS_POSTFIX, $temp) !== FALSE);
                    if ($isNAS) {
                        if (!$grandClientView) {
                            $storageName = substr($clientInfo['name'], 0, $temp);
                            $storageID = $this->BP->get_storage_id($storageName, $system_id);
                            $storageInfo = $this->BP->rest_get_storage_info($storageID, $system_id);
                            if ($storageInfo === false) {
                                $bValid = false;
                                continue;
                            } else {
                                $clientInfo['ip'] = $storageInfo['properties']['hostname'];
                                $clientInfo['nas_properties'] = $storageInfo['properties'];
                                $clientInfo['nas_properties']['nas_id'] = $storageInfo['id'];
                            }
                        } else {
                            // This information is not on the target, so fill out a dummy template.
                            $clientInfo['ip'] = 'N/A';
                            $clientInfo['nas_properties'] = array('hostname' => 'N/A', 'nas_id' => -1, 'port' => 0,
                                                                  'protocol' => 'N/A', 'share_name' => "", 'username' => "");
                        }
                    }
                    if ($grandClientView && $appInfo['name'] == Constants::APPLICATION_NAME_VMWARE) {
                        ;
                    } else {
                        $allClients[] = $clientInfo;
                    }
                }
            }

            // add vCenters if present, and not for file search.
            if (!$forSearch) {
                $noESX = isset($_GET['noESX']) ? $_GET['noESX'] : false;
            } else {
                $noESX = true;
            }
            if (!$grandClientView && $noESX === false) {
                $result = $this->BP->get_vcenter_list($system_id);
                if ($result !== false) {
                    foreach ($result as $vcenter_id => $name) {
                        if ($vcenter_id != $which and $which > 0) {
                            continue;
                        }
                        $allVMs = array();
                        $appInfo = $this->BP->get_vcenter_info($vcenter_id, $system_id);
                        $appInfo['asset_type'] = Constants::ASSET_TYPE_VMWARE_HOST;
                        $appInfo['system_id'] = $system_id;
                        $appInfo['system_name'] = $system_name;
                        $appInfo['credential_display'] = '(Unnamed)';
                        $appInfo['type'] = Constants::APPLICATION_TYPE_NAME_VMWARE;
                        $vmList = $this->BP->get_vm_info($vcenter_id, NULL, true, false, $system_id);
                        if ($vmList === false) {
                            global $Log;
                            $Log->writeVariable("error: " . $this->BP->getError(), true);
                            continue;
                        }
                        $vmList = $this->sort($vmList);
                        if (!empty($vmList)) {
                            $associations = $this->getRetention(array('uuid' => $vcenter_id), $system_id);
                            foreach ($vmList as $vm) {
                                $val = array('id' => $vm['instance_id'], 'name' => $vm['name'], 'is_encrypted' => $vm['is_encrypted']);
                                $this->getRetentionFromAssociations($associations, $val['id'], $val['retention'], $val['gfs_policy'], $val['min_max_policy']);;
                                $val['parent_id'] = $vcenter_id;
                                $val['parent_name'] = $name;
                                $val['asset_type'] = $val['description'] = Constants::ASSET_TYPE_VMWARE_VM;
                                $val['type'] = Constants::ASSET_TYPE_VMWARE_VM . ' Instance';
                                $val['system_id'] = $system_id;
                                $val['system_name'] = $system_name;
                                $val['version'] = "None";
                                $credentials = isset($vm['credentials']) ? $vm['credentials'] : null;
                                $val['credential_display'] = $this->getCredentialsDisplay($credentials, false);
                                $val['credentials'] = $credentials;
                                $val['app_aware'] = $vm['app_aware'];
                                if ( isset($vm['quiesce']) ) {
                                    $val['quiesce'] = $this->quiesce->getQuiesceSettingDisplayName($vm['quiesce']);
                                }
                                $val['is_quiesce_supported'] = $is_quiesce_supported;
                                $allVMs[] = $val;
                            }
                        }
                        $appInfo['children'] = $allVMs;
                        $allClients[] = $appInfo;
                    }
                } else {
                    $Log->writeError("Cannot get vcenter list", true);
                }
            }
        } else {
            $Log->writeError("Cannot get client list", true);
        }

        return $allClients;
    }

    function getProtectedClientsForSystem($system_id, $system_name, $grandClientView, $getProtectedVmware, $getProtectedHyperv, $getProtectedBlock)
    {
        $vmwareClients = array();
        $hypervClients = array();
        $blockClients = array();
        $allClients = array();
        if($getProtectedVmware){
            $vmwareClients = $this->BP->get_protected_vmware_vms($grandClientView, $system_id);
        }
        if($getProtectedHyperv){
            $hypervClients = $this->BP->get_protected_hyperv_vms($grandClientView, $system_id);
        }
        if($getProtectedBlock){
            $blockClients = $this->BP->get_protected_block_assets($grandClientView, $system_id);
        }
        $clients = array_merge($vmwareClients,$hypervClients, $blockClients);
        foreach ($clients as $client){
            $client['source_id'] = $system_id;
            $client['source_name'] = $system_name;
            $allClients[] = $client;
        }
        return $allClients;
    }

    function getAssets($clientID, $clientName, $appID, $appInfo, $grandClientView, $includeRetention, $system_id, $system_name, $is_quiesce_supported = false)
    {
        $assets = array();
        global $Log;
        switch ($appID) {
            case Constants::APPLICATION_ID_FILE_LEVEL:
                break;

            case Constants::APPLICATION_ID_BLOCK_LEVEL:
                $blockInfo = !$grandClientView ? $this->BP->get_block_info($clientID, $system_id) : $this->BP->get_grandclient_block_info($clientID);
                if ($blockInfo !== false) {
                    $blockInfo['name'] = $blockInfo['client_name'];
                    $assets = $this->gather(array($blockInfo), $includeRetention, $system_id, $system_name, $clientID, $clientName, $appID, Constants::APPLICATION_TYPE_DISPLAY_NAME_BLOCK_LEVEL, true, $is_quiesce_supported);
                } else {
                    $Log->writeError("Cannot load ahv info", true);
                }
                break;

            case Constants::APPLICATION_ID_EXCHANGE_2003:
            case Constants::APPLICATION_ID_EXCHANGE_2007:
            case Constants::APPLICATION_ID_EXCHANGE_2010:
            case Constants::APPLICATION_ID_EXCHANGE_2013:
            case Constants::APPLICATION_ID_EXCHANGE_2016:
                $results = !$grandClientView ? $this->BP->get_exchange_info($clientID, true, $system_id) : $this->BP->get_grandclient_exchange_info($clientID);
                if ($results !== false) {
                    $assets = $this->gather($results, $includeRetention, $system_id, $system_name, $clientID, $clientName, $appID, Constants::APPLICATION_TYPE_NAME_EXCHANGE);
                } else {
                    $Log->writeError("Cannot load exchange info", true);
                }
                break;

            case Constants::APPLICATION_ID_SQL_SERVER_2005:
            case Constants::APPLICATION_ID_SQL_SERVER_2008:
            case Constants::APPLICATION_ID_SQL_SERVER_2008_R2:
            case Constants::APPLICATION_ID_SQL_SERVER_2012:
            case Constants::APPLICATION_ID_SQL_SERVER_2014:
            case Constants::APPLICATION_ID_SQL_SERVER_2016:
            case Constants::APPLICATION_ID_SQL_SERVER_2017:
                $results = !$grandClientView ? $this->BP->get_sql_info($clientID, $appID, true, $system_id) : $this->BP->get_grandclient_sql_info($clientID, $appID);
                if ($results !== false) {
                    $assets = $this->gather($results, $includeRetention, $system_id, $system_name, $clientID, $clientName, $appID, Constants::APPLICATION_TYPE_NAME_SQL_SERVER);
                } else {
                    $Log->writeError("Cannot load hyperv info", true);
                }
                break;

            case Constants::APPLICATION_ID_VMWARE:
                if ($grandClientView) {
                    if (isset($appInfo['servers'])) {
                        $servers = $appInfo['servers'];
                        foreach ($servers as $host) {
                            $results = $this->BP->get_grandclient_vm_info($host, $clientID);
                            if ($results !== false) {
                                $assets = array_merge($assets,
                                    $this->gather($results, $includeRetention, $system_id, $system_name, $clientID, $clientName, $appID, Constants::ASSET_TYPE_VMWARE_VM));
                            } else {
                                $Log->writeError("Cannot load vm info", true);
                            }
                        }
                    }
                }
                break;

            case Constants::APPLICATION_ID_HYPER_V_2008_R2:
            case Constants::APPLICATION_ID_HYPER_V_2012:
            case Constants::APPLICATION_ID_HYPER_V_2016:
                $results = !$grandClientView ? $this->BP->get_hyperv_info($clientID, true, $system_id) : $this->BP->get_grandclient_hyperv_info($clientID);
                if ($results !== false) {
                    $assets = $this->gather($results, $includeRetention, $system_id, $system_name, $clientID, $clientName, $appID, Constants::ASSET_TYPE_HYPER_V_VM);
                } else {
                    $Log->writeError("Cannot load hyperv info", true);
                }
                break;

            case Constants::APPLICATION_ID_ORACLE_10:
            case Constants::APPLICATION_ID_ORACLE_11:
            case Constants::APPLICATION_ID_ORACLE_12:
                $results = !$grandClientView ? $this->BP->get_oracle_info($clientID, $appID, true, $system_id) : $this->BP->get_grandclient_oracle_info($clientID, $appID);
                if ($results !== false) {
                    $assets = $this->gather($results, $includeRetention, $system_id, $system_name, $clientID, $clientName, $appID, Constants::APPLICATION_TYPE_NAME_ORACLE);
                } else {
                    $Log->writeError("Cannot load oracle info", true);
                }
                break;

            case Constants::APPLICATION_ID_SHAREPOINT_2007:
            case Constants::APPLICATION_ID_SHAREPOINT_2010:
            case Constants::APPLICATION_ID_SHAREPOINT_2013:
            case Constants::APPLICATION_ID_SHAREPOINT_2016:
                $results = !$grandClientView ? $this->BP->get_sharepoint_info($clientID, $appID, true, $system_id) : $this->BP->get_grandclient_sharepoint_info($clientID, $appID);
                if ($results !== false) {
                    $assets = $this->gather($results, $includeRetention, $system_id, $system_name, $clientID, $clientName, $appID, Constants::APPLICATION_TYPE_NAME_SHAREPOINT);
                } else {
                    $Log->writeError("Cannot load sharepoint info", true);
                }
                break;

            case Constants::APPLICATION_ID_UCS_SERVICE_PROFILE:
                $results = !$grandClientView ? $this->BP->get_ucssp_info($clientID, $appID, true, $system_id) : false;
                if ($results !== false) {
                    $assets = $this->gather($results, $includeRetention, $system_id, $system_name, $clientID, $clientName, $appID, Constants::APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE);
                } else {
                    $Log->writeError("Cannot load ucs info", true);
                }
                break;

            case Constants::APPLICATION_ID_VOLUME:
                $results = !$grandClientView ? $this->BP->get_ndmpvolume_info($clientID, true, $system_id) : $this->BP->get_grandclient_ndmpvolume_info($clientID);
                if ($results !== false) {
                    $assets = $this->gather($results, $includeRetention, $system_id, $system_name, $clientID, $clientName, $appID, Constants::APPLICATION_TYPE_NAME_NDMP_DEVICE);
                } else {
                    $Log->writeError("Cannot load ucs info", true);
                }
                break;

            case Constants::APPLICATION_ID_XEN:
                $results = !$grandClientView ? $this->BP->get_xen_vm_info($clientID, true, true, $system_id) : $this->BP->get_grandclient_xen_vm_info($clientID);
                if ($results !== false) {
                    $assets = $this->gather($results, $includeRetention, $system_id, $system_name, $clientID, $clientName, $appID, Constants::ASSET_TYPE_XEN_VM, true, $is_quiesce_supported);
                } else {
                    $Log->writeError("Cannot load xen info", true);
                }
                break;

            case Constants::APPLICATION_ID_AHV:
                $results = !$grandClientView ? $this->BP->get_ahv_vm_info($clientID, true, true, $system_id) : $this->BP->get_grandclient_ahv_vm_info($clientID);
                if ($results !== false) {
                    $assets = $this->gather($results, $includeRetention, $system_id, $system_name, $clientID, $clientName, $appID, Constants::ASSET_TYPE_AHV_VM, true, $is_quiesce_supported);
                } else {
                    $Log->writeError("Cannot load ahv info", true);
                }
                break;
        }

        return $assets;
    }

    function gather($results, $includeRetention, $system_id, $system_name, $client_id, $clientName, $app_id, $asset_type, $return_quiesce_info = false, $is_quiesce_supported=false)
    {
        $dpuID = $this->grandClientView ? $this->localID : $system_id;
        $assets = array();
        $associations = $this->getRetention(array('client_id' => $client_id, 'app_id' => $app_id), $dpuID);
        $results = $this->sort($results);
        foreach ($results as $result) {
            $name = isset($result['name']) ? $result['name'] :
            		(isset($result['instance']) && isset($result['database']) ? $result['instance'] . '\\' . $result['database'] : 'unknown');
            $val = array('id' => $result['instance_id'], 'name' => $name, 'is_encrypted' => $result['is_encrypted']);
            $this->getRetentionFromAssociations($associations, $val['id'], $val['retention'], $val['gfs_policy'], $val['min_max_policy']);

            if ($asset_type === Constants::APPLICATION_TYPE_NAME_SHAREPOINT) {
                $val['num_app_servers'] = $result['num_app_servers'];
            }

            if ( $return_quiesce_info === true and isset($result['quiesce']) ) {
                $val['quiesce'] = $this->quiesce->getQuiesceSettingDisplayName($result['quiesce']);
                $val['is_quiesce_supported'] = $is_quiesce_supported;
            }

            $val['parent_id'] = $client_id;
            $val['parent_name'] = $clientName;
            $val['asset_type'] = $val['description'] = $asset_type;
            $val['type'] = $asset_type . ' Instance';
            // Passing in local system's ID in case of grandclient
            if ($this->grandClientView) {
                $val['local_system_id'] = $this->localID;
            }
            $val['system_id'] = $system_id;
            $val['system_name'] = $system_name;
            // If a block/image asset, don't set version to none, as the agent version is the same as the main (file-level) instance.
            if ($asset_type === Constants::APPLICATION_TYPE_DISPLAY_NAME_BLOCK_LEVEL) {
                $val['version'] = "";
            } else {
                $val['version'] = "None";
            }
            $credentials = isset($result['credentials']) ? $result['credentials'] : null;
            $val['credential_display'] = $this->getCredentialsDisplay($credentials, false);
            $val['credentials'] = $credentials;
            $assets[] = $val;
        }
        return $assets;
    }

    function getRetention($filter, $system_id)
    {
        global $Log;
        $allAssociations = array();


        $associations = $this->BP->get_gfs_retention($filter, $system_id);
       // print_r($associations);
        if ($associations === false) {
            $Log->writeError("cannot get GFS retention settings: " . $this->BP->getError(), true);
            $associations = array();
        }
        $allAssociations['gfs'] = $associations;


        $retention = $this->BP->get_retention_settings($filter, $system_id);
        if ($retention === false) {
            $Log->writeError("cannot get min-max retention settings: " . $this->BP->getError(), true);
            $retention = array();
        }

        $allAssociations['min_max'] = $retention;

        return $allAssociations;
    }

    function getRetentionFromAssociations($associations, $instance_id, &$retention_name, &$gfs_policy, &$min_max_policy)
    {
        $retention_name = 'None';
        $gfs_policy = array();
        if ($associations !== false) {

            foreach ($associations['min_max'] as $association) {
                if ($association['instance_id'] == $instance_id) {
                    $retention_name = $this->buildMinMaxPolicyName($association);
                    $min_max_policy = $association;
                    break;
                }
            }

            foreach ($associations['gfs'] as $association) {
                if (isset($association['instances'])) {
                    foreach ($association['instances'] as $instance) {
                        if ($instance['instance_id'] == $instance_id) {
                            $retention_name = isset($association['policy_name']) && $association['policy_name'] !== "" ? $association['policy_name'] : $retention_name;
                            $gfs_policy = $association;
                            break;
                        }
                    }
                }
            }
        }
    }

    private function buildMinMaxPolicyName($setting) {
        $name = 'None';
        $showDays = true;
        $a = array();
        if ($setting['retention_min'] > 0) {
            $a[] = 'Min ' . $setting['retention_min'];
        }
        if ($setting['retention_max'] > 0) {
            $a[] .= 'Max ' . $setting['retention_max'];
        }
        if ($setting['legal_hold'] > 0) {
            $a[] .= 'Hold ' . $setting['legal_hold'];
        } else if ($setting['legal_hold'] === -1) {
            $a[] .= 'Hold Forever';
            $showDays = false;
        }
        if (count($a) > 0) {
            $name = implode(',', $a);
            if ($showDays) {
                $name .= ' Days';               
            }
        }
        return $name;
    }

    function getCredentialsDisplay($credentials, $applicableIfNotSet)
    {
        $displayName = "None";

        if ($credentials !== null) {
            if (!empty($credentials)) {
                if (isset($credentials['display_name'])) {
                    $displayName = $credentials['display_name'];
                } else {
                    $displayName = "(Unnamed)";
                }
            }
        } /*else if (!$applicableIfNotSet) {
            $displayName = "N/A";
        }*/

        return $displayName;
    }

    // Sorts the Array alphabetically
    private function sort($Array) {
        $Info = $Array;
        if (count($Array) > 0) {
            $orderByName = array();
            foreach ($Info as $key => $row) {
                $orderByName[$key] = isset($row['name']) ? strtolower($row['name']) : "";
            }
            array_multisort($orderByName, SORT_STRING, $Info);
        }
        return $Info;
    }
}
?>
