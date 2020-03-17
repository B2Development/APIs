<?php

class Systems
{
    private $BP;

    public function __construct($BP, $sid)
    {
        $this->BP = $BP;
        $this->now = time();
        $this->sid = $sid;

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);

        define("UNKNOWN", "unknown");

        define("PROCESSOR_INFORMATION", "Processor Information");
        define("MEMORY_INFORMATION", "Memory Usage");
        define("MAX_BACKUPS", "Maximum Backups");
        define("SYSTEM_INFORMATION", "System Information");

        define("DPU", "Unitrends DPU");
        define("RECOVERY", "Unitrends Recovery");
        define("SFF", "Unitrends Small Form Factor");
        define("UEB_HYPER_V", "VM-Hyper-V");
        define("UEB_HYPERV", "VM-HyperV");
        define("UEB_VMWARE", "VM-VMware");
        define("VERSION", "Version");
        define("INSTALL_DATE", "Installed on");
        define("OS_KERNEL", "Unitrends' DPU Kernel");
        define("ON_METAL", "Unitrends Metal-Platform");
        define("ON_METAL_OS_TYPE", "On Metal OS Type");

    }

    public function get($which, $filter, $data, $sid)
    {
        global $Log;
        $result = array();
        switch($which){
            case "details":
                $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
                $systemInfo = $this->BP->get_system_info($sid);
                if ($systemInfo !== false){
                    $systemOutput =  $this->buildOutput($systemInfo, $sid);
                    $processorOutput = $this->processDetails($systemInfo, $sid);
                    $result['appliance'] = array_merge($systemOutput, $processorOutput);
                } else {
                    $result = false;
                }
                break;
            case "target_configuration":
                $result['data'] = $this->get_target_configuration();
                break;
            case "short":
                $systemList = $this->BP->get_system_list();
                $shortSystemList = array();
                if ($systemList !== false){
                    foreach ($systemList as $id => $name){
                        $tempSystem = array('id' => $id, 'name' => $name);
                        $shortSystemList[] = $tempSystem;
                    }
                    $result['system_list'] = $shortSystemList;
                } else {
                    $message = "Error retrieving system list.";
                    $message = $this->BP->getError() . ":" . $message;
                    $Log->writeError($message, true);
                }
                break;
            default:
                $include_pending = !( $data !== NULL and array_key_exists('include_pending', $data) and (int)$data['include_pending'] === 0 );

                $include_quiesce_settings = ( $data !== NULL and array_key_exists('get_quiesce_settings', $data) and (int)$data['get_quiesce_settings'] === 1 );

                $systems = $sid == false ? $this->functions->getSystems(true) : $this->functions->getSystems(true, $sid);
                foreach ($systems as $system)
                {
                    if ( strstr($system['name'], '.dpu') === false )  // Do not show the .dpu personality in the get systems output
                    {
                        $result["appliance"][] = $this->buildOutput($system, $sid, $include_quiesce_settings);
                    }
                }
                if ( $include_pending === true )
                {
                    $include_rejected = ( $data !== NULL and array_key_exists('include_rejected', $data) and (int)$data['include_rejected'] === 1 );

                    require_once('replication.php');
                    $replication = new Replication($this->BP);
                    $pending_systems = $replication->getReplicationPending($include_rejected, true);
                    if ( $pending_systems !== false and is_array($pending_systems) )
                    {
                        for($i=0; $i < count($pending_systems); $i++) {
                            for($j=0; $j < count($result['appliance']); $j++) {
                                if($result['appliance'][$j]['id'] == $pending_systems[$i]['id']) {
                                    //duplicate systems found, need to merge or remove one
                                    unset($result['appliance'][$j]);
                                    break;
                                }
                            }
                        }
                        $result["appliance"] = array_merge($result["appliance"], $pending_systems);
                    }
                }
                break;
        }
        return $result;
    }

    function processDetails($system, $sid){
        global $Log;
        
        $Appliance = UNKNOWN;
        $Version = UNKNOWN;
        $Kernel = UNKNOWN;
        $OS = UNKNOWN;
        $InstallDate = UNKNOWN;
        $MACAddress = UNKNOWN;
        /*
        $ApplianceType = UNKNOWN;
        $LicensedCapacity = UNKNOWN;
        $Clients = "Unlimited";
        */
        $AssetTag = UNKNOWN;
        $ProcessorType = UNKNOWN;
        $ProcessorCores = 1;
        $ProcessorCache = UNKNOWN;
        $ProcessorFrequency = UNKNOWN;
        $MemorySize = UNKNOWN;
        $MemoryMB = 0;
        $memory = 0;

        $commandName = SYSTEM_INFORMATION;
        $commandOutput = $this->BP->run_command($commandName, "", $sid);
        if ($commandOutput !== false) {
            $this->parseDPUCommandOutput($commandOutput, $Appliance, $Version, $Kernel, $InstallDate, $OS);
        } else {
            $message = "Error running System information command for system info.";
            $message = $this->BP->getError() . ":" . $message;
            $Log->writeError($message, true);
        }
        
	    $this->determineOSVersion($OS);
        
        $licenseInfo = $this->BP->get_license_info($sid);
        if ($licenseInfo !== false) {
            if (array_key_exists('feature_string', $licenseInfo)) {
                //$nRC = $nVC = 0;
                //$this->parseLicenseString($licenseInfo['feature_string'], $ApplianceType, $LicensedCapacity, $Clients, $nRC, $nVC);
                if (array_key_exists('daemon_host_id', $licenseInfo)){
                    $MACAddress = $licenseInfo['daemon_host_id'];
                }
            } else {
                if (array_key_exists('license_type', $licenseInfo) && $licenseInfo['license_type'] == "DEMO") {
                    $LicensedCapacity = "N/A (Demo License)";
                }
            }
        } else {
            $message = "Error retrieving license information for system info.";
            $message = $this->BP->getError() . ":" . $message;
            $Log->writeError($message, true);
        }

        $commandName = PROCESSOR_INFORMATION;
        $commandOutput = $this->BP->run_command($commandName, "", $sid);
        $bVirtual = $this->BP->is_virtual($sid);
        if ($commandOutput !== false) {
            $cores = $this->parseProcessorCommandOutput($commandOutput, $bVirtual, $ProcessorType, $ProcessorCores, $ProcessorCache, $ProcessorFrequency);
        }

        $commandName = MEMORY_INFORMATION;
        $commandOutput = $this->BP->run_command($commandName, "", $sid);
        if ($commandOutput !== false) {
            $memory = $this->parseMemoryCommandOutput($commandOutput, $MemorySize, $MemoryMB);
        }
        $commandName = MAX_BACKUPS;
        $maxBackups = $this->BP->run_command($commandName, "", $sid);
        if(strstr($maxBackups, 'The Maximum backup value for this system is not available.')) {
            $maxBackups = 'Not Available';
        }


        $data = array(
            'name' => isset($system['name']) ? $system['name'] : "",
            'asset_tag' => $assetTag = $this->BP->get_asset_tag($sid),
            'version' => isset($system['version']) ? $system['version'] : "",
            'os_version' => $OS,
            'processors' => $ProcessorCores,
            'processor_type' => $ProcessorType,
            'processor_cache' => $ProcessorCache,
            'processor_frequency' => $ProcessorFrequency,
            'memory' => $memory,
            'max_backup' => $maxBackups,
            'type' => $Appliance,
            'kernel' => $Kernel,
            'install_date' => $InstallDate,
            'mac_address' => $MACAddress
        );

        return $data;
    }

    //
    // This function parses the output of the DPU command to get appliance information.
    //
    // This data is returned in the following format:
    //
    //	Unitrends DPU Recovery-300 - Thu Jan 15 15:42:59 EST 2009
    //	Unitrends' DPU Kernel 2.6.26.3-2.RecoveryOS-smp
    //	Version 4.0.2-1.CentOS
    //	Installed on Thu 15 Jan 2009 03:44:17 PM EST
    //
    //
    function parseDPUCommandOutput($output, &$Appliance, &$Version, &$Kernel, &$InstallDate, &$CoreOS)
    {
        //global $xml;
        $outputArray = split("\n", $output);
        for ($i = 0; $i < count($outputArray); $i++) {
            $line = $outputArray[$i];
            //$xml->element('line', $line);
            if (strstr($line, DPU) && !( strstr( $line, UEB_HYPERV ) && !( strstr( $line, UEB_HYPER_V )) ) && !( strstr( $line, UEB_VMWARE ) ) ) {
                $lineArray = split(" ", $line);
                $Appliance = $lineArray[2];
            } else if (strstr($line, RECOVERY)) {
                $lineArray = split(" ", $line);
                $Appliance = $lineArray[1];
            } else if (strstr($line, SFF)) {
                $lineArray = split(" ", $line);
                $Appliance = "SFF";  // was $lineArray[5];
            } else if (strstr($line, UEB_HYPERV) || strstr($line, UEB_HYPER_V)) {
                $Appliance = "Unitrends Backup (Hyper-V)";
            } else if (strstr($line, UEB_VMWARE)) {
                $Appliance = "Unitrends Backup (VMware)";
            } else if (strstr($line, VERSION)) {
                $lineArray = split(" ", $line);
                $fullVersion = $lineArray[1];
                if ($location = strrpos($fullVersion, '.')) {
                    $Version = substr($fullVersion, 0, $location);
                    if ( $CoreOS !== ON_METAL_OS_TYPE ) {
                        $CoreOS = substr($fullVersion, $location + 1);
                    }
                }
            } else if (strstr($line, INSTALL_DATE)) {
                $location = strlen(INSTALL_DATE) + 1;
                $InstallDate = substr($line, $location);
            } else if (strstr($line, OS_KERNEL)) {
                $location = strlen(OS_KERNEL) + 1;
                $Kernel = substr($line, $location);
            } else if (strstr($line, ON_METAL)) {
                $Appliance = "Unitrends Backup Installable Software";
                $CoreOS = ON_METAL_OS_TYPE;
            }
        }
    }

    function parseProcessorCommandOutput($output, $bVirtual, &$ProcessorType, &$ProcessorCores, &$ProcessorCache, &$ProcessorFrequency) {
        $outputArray = explode("\n", $output);

        $cores = 0;
        for ($i = 0; $i < count($outputArray); $i++) {
            $line = $outputArray[$i];
            $processorArray = explode(':', $line);
            if (count($processorArray) > 1) {
                if (strstr($processorArray[0], "model name")) {
                    $ProcessorType = $processorArray[1];
                } else if (strstr($processorArray[0], "cpu cores")) {
                    $ProcessorCores = $processorArray[1];
                } else if (strstr($processorArray[0], "cache size")) {
                    $ProcessorCache = $processorArray[1];
                } else if (strstr($processorArray[0], "cpu MHz")) {
                    $frequency = (float)$processorArray[1] / 1000.0;		// convert MHz to GHz
                    $ProcessorFrequency = $frequency . " GHz";
                } else if (strstr($processorArray[0], "processor")) {
                    $cores++;
                }
            }
        }
        // Number of processors is total core count.
        $ProcessorCores = $cores;

        return $ProcessorCores;
    }

    function parseMemoryCommandOutput($output, &$MemorySize, &$MemoryMB)
    {
        $outputArray = explode("\n", $output);
        for ($i = 0; $i < count($outputArray); $i++) {
            $line = $outputArray[$i];
            $processorArray = explode(' ', $line);
            if (count($processorArray) > 2) {
                if (strstr($processorArray[0], "Mem:")) {
                    for ($j = 1; $j < count($processorArray); $j++) {
                        if ($processorArray[$j] != " " && $processorArray[$j] != "") {
                            $MemoryMB = (float)$processorArray[$j]; 			// size in MB.
                            $memory = $MemoryMB / 1000.0; 						// convert to GB
                            $MemorySize = $memory . " GB";
                            break;
                        }
                    }
                }
            }
        }
        return $MemorySize;
    }
    
    public function getSystemRoleDisplayNameFromCoreSystemRole($coreSystemRole)
    {
        $systemRoleDisplayName = NULL;
        switch($coreSystemRole){
            case Constants::SYSTEM_ROLE_DPU:
                $systemRoleDisplayName = Constants::SYSTEM_ROLE_DISPLAY_NAME_BACKUP_SYSTEM;
                break;
            case Constants::SYSTEM_ROLE_MANAGER:
                $systemRoleDisplayName = Constants::SYSTEM_ROLE_DISPLAY_NAME_MANAGER;
                break;
            case Constants::SYSTEM_ROLE_VAULT:
                $systemRoleDisplayName = Constants::SYSTEM_ROLE_DISPLAY_NAME_TARGET;
                break;
            case Constants::SYSTEM_ROLE_MANAGED_DPU:
                $systemRoleDisplayName = Constants::SYSTEM_ROLE_DISPLAY_NAME_MANAGED_DPU;
                break;
            case Constants::SYSTEM_ROLE_DPU_CONFIGURED_FOR_VAULTING:
                $systemRoleDisplayName = Constants::SYSTEM_ROLE_DISPLAY_NAME_DPU_CONFIGURED_FOR_VAULTING;
                break;
            case Constants::SYSTEM_ROLE_REPLICATION_SOURCE:
                $systemRoleDisplayName = Constants::SYSTEM_ROLE_DISPLAY_NAME_REPLICATION_SOURCE;
                break;
            case Constants::SYSTEM_ROLE_NON_MANAGED_REPLICATION_SOURCE:
                $systemRoleDisplayName = Constants::SYSTEM_ROLE_DISPLAY_NAME_NON_MANAGED_REPLICATION_SOURCE;
                break;
        }
        return $systemRoleDisplayName;
    }

    function buildOutput($system, $sid, $getQuiesceSetting = false)
    {
        global $Log;
        //$sid should not be used for this API.  It should only query the local system, not remote systems

        $host = "";
        $name = isset($system['name']) ? $system['name'] : false;

        $hostInfo = $this->BP->get_host_info($name);
        if ($hostInfo !== false) {
            $host = isset($hostInfo['ip']) ? $hostInfo['ip'] : false;
        }
        $id = isset($system['id']) ? $system['id'] : false;
        $local = ( $id !== false and $id == $this->BP->get_local_system_id() );
        $hostname = isset($system['host']) ? $system['host'] : "";  //need localhost available
        $role = isset($system['role']) ? $this->getSystemRoleDisplayNameFromCoreSystemRole($system['role']) : false;
        $version = isset($system['version']) ? $system['version'] : false;
        $version_status = isset($system['version_status']) ? $system['version_status'] : "Unknown";
        $online = isset($system['online']) ? $system['online'] : "n/a";
        $customer_id = isset($system['customer_id']) ? $system['customer_id'] : false;
        $customer_name = isset($system['customer_name']) ? $system['customer_name'] : false;
        $location_id = isset($system['location_id']) ? $system['location_id'] : false;
        $location_name = isset($system['location_name']) ? $system['location_name'] : false;
        $registered_assets = isset($system['registered_assets']) ? $system['registered_assets'] : 0;
        $replicating = $this->getReplicatingStatus($hostname, $role);
        $archiving = $this->getArchivingStatus($id);
        $mb_size = isset($system['total_mb_size']) ? $system['total_mb_size'] : 0;
        $mb_free = isset($system['total_mb_free']) ? $system['total_mb_free'] : 0;
        $device_id = isset($system['device_id']) ? $system['device_id'] : false;

        //get storage name from device id
        $storage_name = "";
        if($device_id !== false and $id !== false) {
            $device_info = $this->BP->get_device_info($device_id);
            if($device_info !== false) {
                $device_name = $device_info['dev_name'];
                $storage = $this->BP->get_storage_for_device($device_name);
                if($storage !== false) {
                    $storage_name = $storage;
                }
            }
        }


        $status = Constants::SYSTEM_STATUS_NOT_AVAILABLE;
        if ($role == Constants::SYSTEM_ROLE_DISPLAY_NAME_NON_MANAGED_REPLICATION_SOURCE)
        {
            if (isset($system['is_replication_suspended']) && $system['is_replication_suspended']) {
                $status = Constants::SYSTEM_STATUS_SUSPENDED;
            }
        }
        if ( $online === true )
        {
            if ( $local === true
                or ( $role !== Constants::SYSTEM_ROLE_DISPLAY_NAME_REPLICATION_SOURCE and $role !== Constants::SYSTEM_ROLE_DISPLAY_NAME_NON_MANAGED_REPLICATION_SOURCE )
                or $this->BP->is_replication_suspended( $hostname ) !== true )
            {  //Not quite sure what to do if is_replication_suspended comes back as -1, for now, default it to available
                $status = Constants::SYSTEM_STATUS_AVAILABLE;
            }
            else
            {
                $status = Constants::SYSTEM_STATUS_SUSPENDED;
            }
        }

        $data = array(
            'id' => $id,
            'host' => $host,
            'name' => $name,
            'role' => $role,
            'version' => $version,
            'version_status' => $version_status,
            'online' => $online,
            'status' => $status,
            'customer_id' => $customer_id,
            'customer_name' => $customer_name,
            'location_id' => $location_id,
            'location_name' => $location_name,
            'registered_assets' => $registered_assets,
            'replicating' => $replicating,
            'archiving' => $archiving,
            'local'=> $local,
            'total_mb_size' => $mb_size,
            'total_mb_free' => $mb_free,
            'storage_name' => $storage_name
        );

        if ($getQuiesceSetting === true)
        {
            $is_quiesce_supported = $this->BP->is_quiesce_supported($id);
            $quiesce_setting = '';
            if ( $is_quiesce_supported === -1 )
            {
                // An error occurred checking to see if quiesce is supported on that system
                // Set quiesce supported to false (this will default to the way that is supported on all systems) and log the error
                $Log->writeError("cannot determine whether quiesce is supported: " . $this->BP->getError(), true);
                $is_quiesce_supported = false;
            }
            elseif ( $is_quiesce_supported === true )
            {
                $quiesce_setting = $this->BP->get_default_quiesce_setting($id);
                require_once('quiesce.php');
                $quiesce = new Quiesce($this->BP);
                $quiesce_setting = $quiesce->getQuiesceSettingDisplayName($quiesce_setting);
            }

            $data['is_quiesce_supported'] = $is_quiesce_supported;
            $data['quiesce_setting'] = $quiesce_setting;
        }
        return $data;
    }

    function getArchivingStatus($sid)
    {
        $status = false;
        $archiveTest = $this->BP->get_archive_schedule_list($sid);
        if ($archiveTest !== false) {
            foreach ($archiveTest as $archiveSched) {
                $enabled = isset($archiveSched['enabled']) ? $archiveSched['enabled'] : "not found";
                if ($enabled == 1) {
                    $status = true;
                    continue;
                }
            }
        }
        return $status;
    }

    function getReplicatingStatus($host, $role)
    {
        $status = false;
        if ($host == "localhost" ) {
            $status = $this->BP->local_system_is_replicating();
        } else {
            $status = $role == "Replication Source" ? true : false;
        }
        return $status;
    }

    //the GET/systems/target_configuration function
    function get_target_configuration()
    {
        $status = false;
        $configured = $this->BP->is_openvpn_server_configured();
        $nvpArray = $this->BP->get_nvp_list(Constants::NVP_TYPE_RRC, Constants::NVP_NAME_CONFIGURATION);
        if ( $configured !== -1  and $nvpArray !== false )
        {
            if ( $configured === true
                 and array_key_exists( Constants::NVP_ITEM_NAME_IDENTITY, $nvpArray )
                 and ( $nvpArray[Constants::NVP_ITEM_NAME_IDENTITY] === Constants::SYSTEM_IDENTITY_CROSS_VAULT
                       or $nvpArray[Constants::NVP_ITEM_NAME_IDENTITY] === Constants::SYSTEM_IDENTITY_VAULT ) )
            {
                $configuration = $this->BP->get_openvpn_server_info();
                if ( $configuration !== false )
                {
                    $status = $configuration;
                    $status['is_configured'] = true;
                }
            }
            else
            {
                $status = array( 'is_configured' => false );
            }
        }
        // We don't need an else here, because if $configured is -1, then there was a problem in the core that a $status of false will show the user
        return $status;
    }

    //the POST/systems/make_target function
    function make_target($data){
        global $Log;
        $status = false;
        $alreadyFailed = false;
        $configured = $this->BP->is_openvpn_server_configured();
        if ( $configured !== -1  )
        {
            if ( $configured === false )
            {
                if ($data !== NULL and array_key_exists('network', $data))
                {
                    $mask = array_key_exists('mask', $data) ? $data['mask'] : "255.255.255.0";
                    $port = array_key_exists('port', $data) ? (int)$data['port'] : 1194;
                    $this->BP->configure_openvpn_server($data['network'], $mask, $port);
                }
                else
                {
                    $alreadyFailed = true;
                    $status = array();
                    $status['error'] = 500;
                    $status['message']= 'Missing inputs: \'network\' is a required input.';
                }
            }
            //Commenting out so that this API will not fail if the request is sent over and over.
            /*elseif ($data !== NULL and array_key_exists('network', $data))
            {
                // 'network' should not be an input if the OpenVPN server has already been configured.
            }*/

            if ($alreadyFailed !== true) {
                $status = $this->BP->save_nvp_list(Constants::NVP_TYPE_RRC, Constants::NVP_NAME_CONFIGURATION, array(Constants::NVP_ITEM_NAME_IDENTITY => Constants::SYSTEM_IDENTITY_CROSS_VAULT) );
                $Log->writeVariable('Enabling Footprint Report '.(shell_exec('sudo /usr/bp/bin/footprintReportUtil --enable yes 2>&1')));
            }

        }
        // We don't need an else here, because if $configured is -1, then there was a problem in the core that a $status of false will show the user
        return $status;
    }

    function update($action, $which, $data){
        switch ($action) {
            case 'add-management':
                if (is_numeric($which) && $which > 0) {
                    $data['id'] = (int)$which;
                }
                $system = $this->BP->get_system_info($data['id']);
                if ($system !== false) {
                    //$credentialCheck = $this->hasValidCredentials($system['name'], $data['credentials']['username'], $data['credentials']['password']);
                    $credentialCheck = $this->functions->grantManagementToLocalSystem($system['name'], $data['credentials']['username'], $data['credentials']['password']);
                    if ($credentialCheck === true) {
                        $status = $this->BP->add_mgmt_to_replication_source($system['id']);
                    } else {
                        $status = array('error' => 500, 'message' => $credentialCheck);
                    }
                } else {
                    $status = array('error' => 500, 'message' => 'System with this id was not found.');;
                }
                break;
            case 'identity':
                $nvp = $this->BP->get_nvp_list(Constants::NVP_TYPE_RRC, Constants::NVP_NAME_CONFIGURATION);
                $forceIdentity = isset($data['force']) ? $data['force'] == 1 : false;
                if (!isset($nvp['Identity']) || $nvp['Identity'] == "" || $forceIdentity) {
                    $identity = isset($data['Identity']) ? $data['Identity'] : null;
                    if($identity !== null && $identity !== ""){
                        if((strtoupper($identity) === Constants::SYSTEM_IDENTITY_BACKUP_SYSTEM) || (strtoupper($identity) === Constants::SYSTEM_IDENTITY_CROSS_VAULT)
                            || (strtoupper($identity) === Constants::SYSTEM_IDENTITY_MANAGED_SYSTEM) || (strtoupper($identity) === Constants::SYSTEM_IDENTITY_VAULT)){
                            $status = $this->BP->save_nvp_list(Constants::NVP_TYPE_RRC, Constants::NVP_NAME_CONFIGURATION, array(Constants::NVP_ITEM_NAME_IDENTITY => strtoupper($identity)) );
                        }
                    }
                } else {
                    $status = array('error' => 500,
                                    'message' => 'Identity for this system is already set as: '.$nvp['Identity'].'. To change the identity you have to force the change.');
                }
                break;
            case 'shutdown':
                if (isset($data['password'])) {
                    $rootPassword = $data['password'];
                    $restart = isset($data['restart']) ? $data['restart'] : true;
                    $message = "";
                    $result = $this->shutdown_system($rootPassword, $restart, $message);
                    if ($result == 0) {
                        $status = true;
                    } else {
                        $status = array('error' => 500,
                            'message' => $message);
                    }
                } else {
                    $status = array('error' => 500,
                        'message' => 'You must provide the root OS password to shutdown or reboot the appliance.');
                }
                break;
            case 'os-password':
                if (isset($data['root_password']) && isset($data['password'])) {
                    $user = isset($data['user']) ? $data['user'] : 'root';
                    $currentRootPassword = $data['root_password'];
                    $newPassword = $data['password'];
                    $message = "";
                    $result = $this->change_system_password($user, $currentRootPassword, $newPassword, $message);
                    if ($result == 0) {
                        $status = true;
                    } else {
                        $status = array('error' => 500,
                            'message' => $message);
                    }
                } else {
                    $status = array('error' => 500,
                        'message' => 'You must provide current and new passwords.');
                }
                break;
            default:
                if (is_numeric($which) && $which > 0) {
                    $data['id'] = (int)$which;
                }
                $status = $this->BP->save_system_info($data);
                break;
        }
        return $status;
    }
    //the POST/systems function
    function add($data){
        $data['location_id'] = 1;
        $status = array();
        // get current list to ensure no conflicts.
        $systems =  $this->functions->getSystems(true);

        $status = $this->resolveHost($data, $systems, false);
        if ($status === true) {

            //$credentialCheck = $this->hasValidCredentials($data['ip'], $data['credentials']['username'], $data['credentials']['password']);
            $credentialCheck = $this->functions->grantManagementToLocalSystem($data['ip'], $data['credentials']['username'], $data['credentials']['password']);
            if ($credentialCheck === true) {
                $this->resolveHost($data, $systems, true);
                $status = $this->BP->save_system_info($data);
                if ($status !== false) {
                    $newCredentials = array(
                        'username' => $data['credentials']['username'],
                        'password' => $data['credentials']['password'],
                        'is_default' => false,
                        'display_name' => 'Managed ' . $data['host']
                    );
                    $this->BP->save_credentials($newCredentials);
                }
            } else {
                $status = array('error' => 500, 'message' => $credentialCheck);
            }
        }

        return $status;
    }

    function resolveHost(&$data, $systems, $modifyHostsFile) {

        $result = true;
        $nonManaged = false;
        $system = $this->systemExists($data, $systems, $nonManaged);
        if ($system !== false) {
            if ($nonManaged) {
                // promote system to a managed appliance.
                $result = array('error' => 500, 'message' => 'TBD. Promote non-managed replication source to a managed appliance');
            } else {
                $result = array('error' => 500, 'message' => 'An managed appliance already exists with this name or address.');
            }
        } else {
            if(isset($data['ip']) && ($data['ip'] != "")) {
                if ($modifyHostsFile) {
                    $result = $this->BP->get_host_info($data['host']);
                    if ($result !== false) {  //host is in hosts file
                        //is name or ip being changed?
                        $originalIP = $result['ip'];
                        $input = array(
                            'original_ip' => $originalIP,
                            'ip' => $data['ip'],
                            'name' => $data['name']
                        );
                        $result = $this->BP->save_host_info($input);
                        // One other case, the IP is used by another name, delete original entry and add as alias.
                        if ($result === false) {
                            if ($host = $this->functions->findHostByValue($data['ip'])) {
                                $aliases = isset($host['aliases']) ? array_merge($host['aliases'], $data['name']) : array($data['name']);
                                $input = array(
                                    'original_ip' => $data['ip'],
                                    'ip' => $data['ip'],
                                    'name' => $host['name'],
                                    'aliases' => $aliases
                                );
                                $result = $this->BP->remove_host_info($data['name']);
                                if ($result !== false) {
                                    $result = $this->BP->save_host_info($input);
                                };
                            }
                        }
                    } else { //host is not in hosts file
                        $input = array(
                            'name' => $data['name'],
                            'ip' => $data['ip']
                        );
                        $result = $this->BP->save_host_info($input);
                        if ($result === false) {
                            $input['original_ip'] = $data['ip'];
                            $result = $this->BP->save_host_info($input);
                        }
                    }
                }
            } else {
                $result = $this->BP->get_host_info($data['host']);
                if ($result !== false) {
                    $data['ip'] = $result['ip'];
                    $result = true;
                } else {
                    // not in host file, hopefully it is resolvable.
                    $data['ip'] = $data['name'];
                    $result = true;
                }
            }
        }
        return $result;
    }

    function systemExists($data, $systems, &$nonManaged) {
        $found = false;
        foreach ($systems as $system) {
            if ($system['name'] == $data['host']) {
                $nonManaged = ($system['role'] == SYSTEM_ROLE_NON_MANAGED_REPLICATION_SOURCE);
                $found = $system;
                break;
            } else if (isset($data['ip']) && ($data['ip'] !== "") && ($data['ip'] == $system['host'])) {
                $nonManaged = ($system['role'] == SYSTEM_ROLE_NON_MANAGED_REPLICATION_SOURCE);
                $found = $system;
                break;
            }
        }
        return $found;
    }

    function hasValidCredentials($ip, $username, $password) {
        global $Log;
        $curl = curl_init();
        $url = "https://" . $ip . "/api/login";
        $auth = array("username" => $username, "password" => $password);
        $auth_string = json_encode($auth);
        $Log->writeVariable("authenticating, url is $url");
        //$Log->writeVariable("authenticating, auth is $auth_string");
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $auth_string);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json')
        );
        $result = curl_exec($curl);
        if ($result == false) {
            $Log->writeVariable("the curl request to authenticate failed: ");
            $Log->writeVariable(curl_error($curl));
            $result = "Attempt to connect to managed appliance failed.  Please ensure the appliance is powered on and its network address is resolvable.";
        } else {
            // return as a string
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $Log->writeVariable('curl request: http code');
            $Log->writeVariable($httpcode);
            $result = json_decode($result, true);
            $Log->writeVariableDBG('curl request: result');
            $Log->writeVariableDBG($result);
            if ($httpcode == 201) {
                if ($result['superuser'] == true || $result['administrator'] == true) {
                    $result = true;
                } else {
                    $result = "The username and password provided do not have administrative permissions on the managed appliance.";
                }
                $this->functions->remoteLogout($ip, $result['auth_token']);
            } else {
                $result = $result['result'];
                if (is_array($result[0]) && isset($result[0]['message'])) {
                    $result = $result[0]['message'];
                } else {
                    $result = "Attempt to connect to managed appliance failed (error code: " . $httpcode . ").";
                }
            }
        }
        curl_close($curl);

        return $result;
    }

    //the delete function
    function delete_systems($which){
        $systemList = $this->functions->getSystems( true, $which );

        $sid = 1;
        require_once('jobs/jobs-active.php');
        $jobsActive = new JobsActive($this->BP, $sid, $this->functions);

        require_once('jobs.php');
        $jobs = new Jobs($this->BP, $sid);

        $FLRJobs = $jobsActive->getFLRJobs($sid);
        $FLRJobsPerSrc = $jobsActive->getTargetFLRJobs($FLRJobs, $systemList, $which);
        foreach($FLRJobsPerSrc as $FLRJob) {
            $data = array();
            $deleteFLRs = $jobs->delete($FLRJob['id'], $data);
        }

        if ( $systemList !== false and ($systemList[0]['role'] === Constants::SYSTEM_ROLE_REPLICATION_SOURCE or $systemList[0]['role'] === Constants::SYSTEM_ROLE_NON_MANAGED_REPLICATION_SOURCE)  )
        {
            $grandclients = $this->BP->get_grandclient_list($which);
            if ( $grandclients !== false )
            {
                foreach ( $grandclients as $grandclientID => $grandclientName )
                {
                    $deletionResult = $this->BP->delete_client($grandclientID);
                }
            }
        }

        $systemInfo = $this->BP->get_system_info($which);
        $systemName = $systemInfo['host'];
        $credentialsList = $this->BP->get_credentials_list();
        foreach ($credentialsList as $credentials) {
            if ($credentials['display_name'] === "Managed " . $systemName){
                $this->BP->delete_credentials($credentials['credential_id']);
                break;
            }
        }

        $status = $this->BP->remove_system($which);
        return $status;
    }

    /* function to shutdown and optionally restart system.  There must
    * be an entry for apache for /sbin/shutdown (with PASSWORD) in
    * /etc/sudoers.
    */
    private function shutdown_system($rootPassword, $bRestart, &$msg) {
        $status = 0;
        $res = trim(shell_exec('echo $UID'));
        // we're not running as root
        if (!$this->login_is_valid('root', $rootPassword)) {
            $msg = "Invalid password for root user.";
            return 1;
        }
        $haltOrRestart = $bRestart ? "-r" : "-h";
        $stringRest = $bRestart ? " and restarted." : ".";
        $command = sprintf("sudo -S /sbin/shutdown %s now", $haltOrRestart);
        $handle = popen($command, 'w');
        fwrite($handle, "$rootPassword\n");
        pclose($handle);
        $msg = "The appliance will be shutdown.". $stringRest;
        return $status;
    }

    /* checks for valid login credentials, returns 1 if valid, 0 otherwise
     *
     * @param $username - user to check
     * @param $password - password for this user
     * @return - 1 for valid login, 0 otherwise
     */
    private function login_is_valid($username, $password) {
        $passencoded = "'" . str_replace("'", "'\''", $password) . "'";
        $userencoded = "'" . str_replace("'", "'\''", $username) . "'";
        $cmd = "echo -e $passencoded | sudo -u $userencoded -S whoami 2>/dev/null";
        $res = trim(shell_exec($cmd));
        if ($res == $username) {
            return 1;
        } else {
            return 0;
        }
    }

    // functions to update system user passwords given the current root password
    // example: change root's password from unitrends1 to mypassword
    //   $msg = '';
    //   $status = change_system_password('root', 'unitrends1', 'mypassword', $msg);
    //   echo "Status was $status: $msg\n";

    /* attempts to reset a user's password using user login credentials
     *
     * @param $user - name of user for which password should be changed
     * @param $current_root_pass - current root password
     * @param $new_user_pass - new password for user
     * @param &$msg - string to update with human-readable command status
     * @return - 0 on success, nonzero otherwise
     */
    private function change_system_password($user, $current_root_pass, $new_user_pass, &$msg) {
        $status = 0;
        $res = trim(shell_exec('echo $UID'));
        if ($res == 0) {
            // running as root, no sudo needed for passwd command
            $handle = popen("/usr/bin/passwd --stdin $user", 'w');
            fwrite($handle, "$new_user_pass\n");
            pclose($handle);
        } else {
            // we're not running as root
            if (!$this->login_is_valid('root', $current_root_pass)) {
                $msg = "Invalid password for user root.";
                return 1;
            }
            $handle = popen("/usr/bin/sudo -S /usr/bin/passwd --stdin $user", 'w');
            $ret = fwrite($handle, "$current_root_pass\n$new_user_pass\n");
            pclose($handle);
        }
        $msg = "The system password for $user was successfully updated.";
        return $status;
    }

    //
    // This function extracts the CentOS version number from the system.
    //
    // output format:
    //
    //	RecoveryOS release 5 (Final)
    //
    function determineOSVersion(&$OS)
    {
        ob_start();
        $result = shell_exec('cat /etc/redhat-release');
        if ( $OS === ON_METAL_OS_TYPE ) {
            $OS = $result;
        } else {
            if (!is_null($result)) {
                $resultArray = explode(' ', $result);
                if (count($resultArray) > 2) {
                    $OS .=  ' ' . $resultArray[2];
                }
            }
        }
        ob_end_clean();
    }

} // end Systems class
?>
