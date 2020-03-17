<?php

class Storage
{
    private $BP;


  //  var $itemCounter = 0;
     
    public function __construct($BP, $sid)
    {
		$this->BP = $BP;
        $this->now = time();
        $this->sid = $sid;

        $this->Totals['backup_mb_free'] = 0;
        $this->Totals['backup_mb_size'] = 0;
        $this->Totals['total_mb_free'] = 0;
        $this->Totals['total_mb_size'] = 0;

        include_once 'function.lib.php';
        $this->functions = new Functions($BP);

        $this->EXPAND_STORAGE_BLOCK = "Storage cannot be added while jobs are queued or running.  Please wait, or cancel active jobs.";
    }

    public function get($which, $filter, $sid, $systems)
    {
        $data = false;
        if ($which && $which !== null) {
            if (($storageID = $this->getStorageID($which[0])) !== false) {
                //$storageID passed in, must specify $sid
                if ($sid !== false) {
                    $systemName = $this->functions->getSystemNameFromID($sid);
                    $storage = $this->BP->rest_get_storage_info($storageID, $sid);
                    $data['storage'] = $this->buildOutput($storage, $sid, $systemName);
                } else {
                    $data = "System ID must be specified.";
                }
            } elseif (is_string($which[0])) {
                switch ($which[0]) {
                    case 'targets':
                        if (isset($which[1])) {
                            $target = $which[1];
                            if ($sid !== false) {
                                $data = $this->getStorageLuns($target, $filter, $sid);
                            } else {
                                $data = "System ID must be specified.";
                            }
                        } else {
                            if ($sid !== false) {
                                $data = $this->getStorageTargets($filter, $sid);
                            } else {
                                $data = "System ID must be specified.";
                            }
                        }
                        break;
                    case 'iqn':
                        // if system ID is not set, consider it to be the local system
                        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
                        $result = $this->BP->get_iqn($sid);
                        if ($result !== false) {
                            $data['iqn'] = $result;
                        }
                        break;
                    case 'wwn':
                        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
                        $result = $this->BP->get_wwn($sid);
                        if ($result !== false) {
                            $data['wwn'] = $result;
                        }
                        break;
                    case 'chap':
                        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
                        $result = $this->BP->get_chap($sid);
                        if ($result !== false) {
                            $data['username'] = $result;
                        }
                        break;
                    case "available-disks":
                        if($sid !== false) {
                            $disks = $this->BP->rest_get_available_disks($sid);
                            if ($disks !== false) {
                                if (!empty($disks)) {
                                    $data['attached_disks'] = $this->buildDisksOutput($disks, $sid);
                                } else {
                                    $data['attached_disks'] = array();
                                }
                            }
                        } else {
                            $systems = $this->functions->selectSystems();
                            foreach($systems as $sid => $sname){
                                $disks = $this->BP->rest_get_available_disks($sid);
                                if ($disks !== false) {
                                    if(!empty($disks)){
                                        $data['attached_disks'][] = $this->buildDisksOutput($disks, $sid);
                                    } else {
                                        $data['attached_disks'][] = array();
                                    }
                                }
                            }
                        }
                        break;
                    case "d2d":
                        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
                        $d2d = $this->BP->get_d2d($sid);
                        if($d2d !== false){
                            $data['d2d storage'] = array(
                                'low' => isset($d2d['low']) ? $d2d['low'] : "",
                                'high' => ((string)($d2d['high']) == "INF") ? "INF" : $d2d['high'],
                                'allowed' => ((string)($d2d['allowed']) == "INF") ? "INF" : $d2d['allowed'],
                                'existing' => ((string)($d2d['existing']) == "INF") ? "INF" : $d2d['existing'],
                                'licensed' => ((string)($d2d['licensed']) == "INF") ? "INF" : $d2d['licensed'],
                                'hardware' => isset($d2d['hardware']) ? $d2d['hardware'] : ""
                            );
                        }
                        break;
                    case "ir":
                        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
                        $virtual_clients_supported = $this->BP->virtual_clients_supported($sid);
                        $bVF = $this->functions->isWIROnApplianceSupported($virtual_clients_supported);
                        if ($bVF !== true) {
                            $bVF = ($this->BP->vmware_ir_supported($sid) || $this->BP->hyperv_ir_supported($sid));
                        }
                        if ($bVF == true) {
                            $vf = $this->BP->get_virtual_failover($sid);
                            if($vf !== false){
                                $data['ir storage'] = array(
                                    'low' => isset($vf['low']) ? $vf['low'] : "",
                                    'high' => ((string)($vf['high']) == "INF") ? "INF" : $vf['high'],
                                    'allowed' => ((string)($vf['allowed']) == "INF") ? "INF" : $vf['allowed'],
                                    'licensed' => ((string)($vf['licensed']) == "INF") ? "INF" : $vf['licensed'],
                                    'hardware' => isset($vf['hardware']) ? $vf['hardware'] : ""
                                );
                            }
                        }
                        break;
                    case "id":
                        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
                        if($which[1]) {
                            $data = $this->BP->get_storage_id($which[1], $sid);
                            if($data !== false) {
                                $data = array("id" => $data);
                            }
                        } else {
                            $data['error'] = 500;
                            $data['message'] = "Name is required";
                        }
                        break;
                    case "stateless":
                        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
                        $result = $this->BP->use_stateless( $sid );
                        if($result === 1){
                            $data['stateless'] = true;
                        }
                        else{
                            $data['stateless'] = false;
                        }
                }
            }
        } else {
            // See if the user has specified a usage filter.
            $usageFilter = isset($_GET['usage']) ? $_GET['usage'] : false;
            if(isset($filter['usage'])) {
                $usageFilter = $filter['usage'];
                unset($filter['usage']);
            }
            if($usageFilter !== false) {
                $usageFilter = explode(",", $usageFilter);
            }
            // Get a list of one or more systems on which to operate.
            $data = array();

            $data['storage'] = array();
            $data['totals'] = array();
            foreach ($systems as $sid => $systemName) {
                $storage = $this->BP->get_storage_list($sid);
                if ($storage !== false) {
                    $data['storage'] = array_merge($data['storage'], $this->getStorageInfo($storage, $sid, $systemName, $usageFilter));

                    $sort = array();
                    foreach($data['storage'] as $k => $v){
                        $sort['sid'][$k] = $v['sid'];
                        $sort['id'][$k] = $v['id'];
                    }

                    array_multisort($sort['sid'], SORT_ASC, SORT_NUMERIC, $sort['id'], SORT_ASC, SORT_NUMERIC, $data["storage"]);

                    $data['totals'] = $this->Totals;
                }
            }
        }

        return ($data);
    }


    function buildDisksOutput($disks, $sid){
           foreach($disks as $disk) {
               $data[] = array(
                   'system_id' => $sid,
                   'sname' => $this->functions->getSystemNameFromID($sid),
                   'name' => isset($disk['name']) ? $disk['name'] : "",
                   'uuid' => isset($disk['uuid']) ? $disk['uuid'] : "",
                   'partitioned' => isset($disk['partitioned']) ? $disk['partitioned'] : "",
                   'filesystem' => isset($disk['filesystem']) ? $disk['filesystem'] : "",
                   'mb_size' => isset($disk['mb_size']) ? $disk['mb_size'] : "",
               );
           }

        return $data;
    }
    public function add($data, $sid){

        if ($data['usage'] == "stateless") {
            // We will be doing an LVM expand; ensure no jobs are active.
            if (isset($data['properties']['internal_name'])) {
                $activeJobs = $this->checkForActiveJobs($sid);
                if ($activeJobs == true) {
                    $result = array();
                    $result['error'] = 500;
                    $result['message'] = $this->EXPAND_STORAGE_BLOCK;
                } else {
                    $disableUser = $this->disableUser($sid);
                    if ($disableUser !== false) {
                        $result = $this->BP->rest_save_storage_info($data, $sid);
                        if($result === false){
                            $result['error'] = 500;
                            $message = $this->BP->getError();
                            if(is_numeric(substr($message,0,1))){
                                $messageArray = explode("<:>", $message, 2);
                                // skip the code, of form nn<:> if found.
                                if (count($messageArray) > 1) {
                                    $message = $messageArray[1];
                                }
                            }
                            $result['message'] = $message;
                        }
                    }
                    if ($disableUser) {
                        $enableUser = $this->BP->reenable_user_login($sid);
                    }
                }
            } else {
                $result = $this->BP->rest_save_storage_info($data, $sid);
                if($result === false){
                    $result['error'] = 500;
                    $message = $this->BP->getError();
                    if(is_numeric(substr($message,0,1))){
                        $message = substr($message,4);
                    }
                    $result['message'] = $message;
                }
            }
        } else {
            $result = $this->BP->rest_save_storage_info($data, $sid);

            if($result !== false and $data['usage'] == Constants::STORAGE_USAGE_SOURCE and $data['type'] == Constants::STORAGE_TYPE_ID_NAS and ($data['properties']['protocol'] == 'nfs' or $data['properties']['protocol'] == 'cifs')) {
                //NAS source, add to host file and add as client
                $storageName = $data['name'];
                $clientName = $storageName . Constants::NAS_POSTFIX;
                require_once("clients.php");
                $clients = new Clients($this->BP);
                $valid = $clients->addHostAlias($this->BP, "localhost", $clientName, $sid);
                if($valid) {
                    //add as a client
                    $clientCreated = $this->createAliasedClient($clientName, $sid);
                    if($clientCreated == false) {
                        $hostRemoved = $this->deleteHostAlias($clientName, $sid);
                        $storageID = $this->BP->get_storage_id($storageName, $sid);
                        $deleted = false;
                        if($storageID !== false) {
                            $deleted = $this->BP->rest_delete_storage($storageID, $sid);
                        }
                        if($hostRemoved == false and $deleted == false) {
                            $result['error'] = 500;
                            $result['message'] = "Unable to successfully create the asset. Unable to rollback and remove storage and host-file entry.
                                                    Please go to the " . Constants::FLASH_UI_NAME . " to remove the storage manually from the Storage page, and to remove the alias " . $clientName . " from the hosts file.";
                        } else if($hostRemoved == false) {

                        } else if($deleted == false) {
                            $result['error'] = 500;
                            $result['message'] = "Storage added successfully, asset creation failed. Unable to rollback and remove the storage.
                                                    Please go to the Storage page in the " . Constants::FLASH_UI_NAME . " to remove the storage manually.";
                        } else {
                            $result['error'] = 500;
                            $result['message'] = "Failed to create the asset associated with the NAS. Please try again.";
                        }
                    }
                } else {
                    $storageID = $this->BP->get_storage_id($storageName, $sid);
                    $deleted = false;
                    if($storageID !== false) {
                        $deleted = $this->BP->rest_delete_storage($storageID, $sid);
                    }
                    if($deleted) {
                        $result['error'] = 500;
                        $result['message'] = "Unable to add host entry for the NAS. Please try again.";
                    } else {
                        $result['error'] = 500;
                        $result['message'] = "Unable to add host entry for the NAS. Unable to rollback and remove the storage.
                                                Please go to the Storage page in the " . Constants::FLASH_UI_NAME . " to remove the storage manually.";
                    }
                }
            }

            //if cloud, we check for and import sets
            if($result !== false && $data['usage'] == "archive") {
                $result = array("storage" => array());
                $result['storage']['created'] = true;
                //import sets if there are any
                $importResult = $this->functions->importSets($data['name'], $sid);
                $result['storage'] = array_merge($result['storage'], $importResult['storage']);
            }

            if($result === true and $data['type'] === 4){
                $storageName = $data['name'];
                $storageID = $this->BP->get_storage_id($storageName, $sid);
                $output['id'] = $storageID;
                $result = array('result' => $output );
            }
        }

        return $result;
    }

    function checkForActiveJobs($sid){

        $result = false;

        $jobs = $this->BP->get_job_list($sid);
        if ($jobs !== false and $jobs !== null) {
            foreach ($jobs as $id => $name) {
                $jobInfo = $this->BP->get_job_info($id, $sid);
                $status = $jobInfo['status'];
                switch ($status) {
                    case 'QUEUED':
                    case 'CONNECTING':
                    case ' *ACTIVE*':
                    case 'ACTIVE':
                    case 'DB. UPDATING':
                        $result = true;
                        break;
                }
            }
        }

        return $result;
    }

    function createAliasedClient($clientName, $systemID) {
        $result = true;
        $clientID = $this->getClientID($clientName, $systemID);
        if($clientID == -1) {
            $clientInfo = array();
            $clientInfo['name'] = $clientName;
            $clientInfo['is_enabled'] = true;
            $clientInfo['is_synchable'] = false;
            $clientInfo['is_encrypted'] = false;
            $result = $this->BP->save_client_info($clientInfo, $systemID);
        }
        return $result;
    }

    function getClientID($clientName, $systemID) {
        $clientID = -1;
        $clients = $this->BP->get_client_list($systemID);
        foreach ($clients as $id => $name) {
            if ($clientName == $name) {
                $clientID = $id;
                break;
            }
        }
        return $clientID;
    }

    function disableUser($sid){
        $ret = $this->BP->disable_user_login("The appliance's storage is being initialized. Please wait a few minutes, then try logging in again.");
        return $ret;
    }

    public function put($which, $data, $sid){
        if (isset($data['properties']) && isset($data['properties']['internal_name'])) {
            // We will be doing an LVM expand; ensure no jobs are active.
            $activeJobs = $this->checkForActiveJobs($sid);
            if ($activeJobs == true) {
                $result = array();
                $result['error'] = 500;
                $result['message'] = $this->EXPAND_STORAGE_BLOCK;
            }
            else{
                $data['id'] = $this->getStorageID($which);
                $result = $this->BP->rest_save_storage_info($data, $sid);
            }
        } else {
            $data['id'] = $this->getStorageID($which);
            $result = $this->BP->rest_save_storage_info($data, $sid);
        }
        return $result;
    }

    public function allocate_ir($data, $sid){
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        $result = $this->BP->set_virtual_failover($data['allowed'], $sid);
        return $result;
    }

    public function allocate_d2d($data, $sid){
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        $result = $this->BP->set_d2d($data['allowed'], $sid);
        return $result;
    }

    public function allocate_vc($data, $sid){
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        $result = $this->BP->set_vc($data['allowed'], $sid);
        return $result;
    }

    public function online_offline($action, $which, $data, $sid)
    {
        $result = false;
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        
        if ($which != -1) {
            $storageID = $this->getStorageID($which);
            switch ($action) {
                case "offline":
                    $result = $this->BP->disconnect_storage($storageID, $sid);
                    break;
                case "online":
                    $result = $this->BP->connect_storage($storageID, $sid);
                    break;
                default:
                    $result = "Bad action specified.  Storage can only be set online or offline.";
                    break;
            }

        } else {
            switch ($action) {
                case 'chap':
                    $username = NULL;
                    if (isset($data['username'])) {
                        // bp_get_iqn, often used for the username, has a trailing newline; trim it if present.
                        $username = trim($data['username']);
                    }
                    $password = NULL;
                    if (isset($data['password'])) {
                        $password = $data['password'];
                    }
                    $result = $this->BP->set_chap($username, $password, $sid);
                    break;
            }
        }
        return $result;
    }

    public function delete($which, $sid){
        $result = false;
        if ($which) {
            $storageID = $this->getStorageID($which);
            $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();

            $deleteStorage = true;
            $removeSets = false;
            $removeNAS = false;
            $storageInfo = $this->BP->rest_get_storage_info($storageID, $sid);
            if($storageInfo !== false) {

                //get media name in case of cloud being removed and need to remove sets
                if($storageInfo['usage'] == "archive" && strstr($storageInfo['properties']['protocol'], "cloud")) {
                    $removeSets = true;
                }

                //if this is a NAS source, then we need to remove the aliased client and host file entry
                if($storageInfo['usage'] == Constants::STORAGE_USAGE_SOURCE && $storageInfo['type'] == Constants::STORAGE_TYPE_NAME_NAS) {
                    $removeNAS = true;
                    $aliasedClientName = $storageInfo['name'] . Constants::NAS_POSTFIX;
                    $removeClient = $this->deleteAliasedClient($aliasedClientName, $sid);
                    if($removeClient == false) {
                        $deleteStorage = false;
                        $result = array();
                        $result['error'] = 500;
                        $result['message'] = "Unable to remove asset: " . $this->BP->getError();
                    } else {
                        $removeHost = $this->deleteHostAlias($aliasedClientName, $sid);
                        if($removeHost == false) {
                            $deleteStorage = false;
                            $reAddClient = $this->createAliasedClient($aliasedClientName, $sid);
                            $result = array();
                            $result['error'] = 500;
                            if($reAddClient == false) {
                                $result['message'] = "Unable to remove host info. Unable to rollback and re-add asset.
                                                        Please go to the " . Constants::FLASH_UI_NAME . " to remove the storage manually from the Storage page, and to remove the alias " . $aliasedClientName . " from the hosts file.";
                            } else {
                                $result['message'] = "Unable to remove the host file entry. Please try again." . $this->BP->getError();
                            }
                        }
                    }
                }
                $storageName = $storageInfo['name'];

                if($deleteStorage) {
                    $result = $this->BP->rest_delete_storage($storageID, $sid);
                }

                if($result !== false && $removeSets == true) {
                    //get sets  to find sets from that media
                    $sets = $this->BP->get_archive_sets(array("target" => $storageName), $sid);
                    //purge those sets and none others
                    foreach($sets as $set) {
                        $this->BP->purge_archive_catalog($set['archive_set_id'], $sid);
                    }
                }

                if($result == false && $removeNAS == true) {
                    //rollback, storage deletion failed
                    $result = array();
                    $result['error'] = 500;

                    require_once("clients.php");
                    $clients = new Clients($this->BP);
                    $reAddHostInfo = $clients->addHostAlias($this->BP, 'localhost', $aliasedClientName, $sid);
                    if($reAddHostInfo == false) {
                        $result['message'] = "Unable to remove NAS storage. Please go to the Storage  page in the " . Constants::FLASH_UI_NAME . " to remove the storage manually.";
                    } else {
                        $reAddClient = $this->createAliasedClient($aliasedClientName, $sid);
                        if($reAddClient == false) {
                            $result['message'] = "Unable to remove NAS storage and unable to rollback.
                                            Please go to the " . Constants::FLASH_UI_NAME . " to remove the storage manually from the Storage page, and to remove the alias " . $aliasedClientName . " from the hosts file.";
                        } else {
                            $result['message'] = $this->BP->getError();
                        }
                    }
                }
            } else {
                $result = $storageInfo;
            }
        }
        return $result;
    }

    function deleteAliasedClient($clientName, $sid) {
        $result = true;
        $clientID = $this->getClientID($clientName, $sid);
        if($clientID != -1) {
            $result = $this->BP->delete_client($clientID, $sid);
        }
        return $result;
    }

    function deleteHostAlias($aliasName, $sid) {
        $valid = true;
        $hostInfo = $this->BP->get_host_info($aliasName, $sid);
        if ($hostInfo !== false) {
            // The alias is there, remove from host entry for host name.
            // Update existing entry, add the alias if not already present.
            $hostInfo['original_ip'] = $hostInfo['ip'];
            $aliases = isset($hostInfo['aliases']) ? $hostInfo['aliases'] : array();
            if (in_array($aliasName, $aliases)) {
                $newAliases = array();
                foreach ($aliases as $alias) {
                    if ($alias != $aliasName) {
                        $newAliases[] = $alias;
                    }
                }
                $hostInfo['aliases'] = $newAliases;
                $result = $this->BP->save_host_info($hostInfo, $sid);
                $valid = $result;
            }
        }
        return $valid;
    }

    function getStorageInfo($storage, $sid, $systemName, $usageFilter)
    {
        $data = false;
        $usage = "";
        global $Log;

        if ($storage !== false) {
            $data = array();
            foreach ($storage as $id => $name) {
                $storageInfo = $this->BP->rest_get_storage_info($id, $sid);
                if ($storageInfo === false) {
                    $Log->writeError("Error getting storage info for id " . $id . ":" . $this->BP->getError(), true);
                    continue;
                }

                // usage of storage is for backup or backup copies (fka archives).
                switch($storageInfo['usage']){
                    case 'backup':
                    case 'stateless':
                    case '':
                        $usage = "backup";
                        break;
                    case "archive":
                        $usage = "archive";
                        break;
                    case 'source':
                        $usage = "source";
                        break;
                }

                if ($usageFilter !== false && !in_array($usage, $usageFilter)) {
                    continue;
                }

                $data[] = $this->buildOutput($storageInfo, $sid, $systemName);

                //get all the 'backup' storage info
                if($usage == "backup"){
                    $this->addToBackupsTotals($storageInfo['mb_size'], $storageInfo['mb_free']);
                }

                //get all storage info, regardless of usage:
                $this->addToTotalTotals($storageInfo['mb_size'], $storageInfo['mb_free']);

               /* if(isset($storageInfo['dedup']) && $storageInfo['dedup'] !== "N/A"){
                    $dedup = $storageInfo['dedup'];
                    $this->avgDedup($dedup);
                }

                if(isset($storageInfo['daily_growth_rate']) && $storageInfo['daily_growth_rate'] !== "N/A"){
                    $dailyGrowthRate = $storageInfo['daily_growth_rate'];
                    $this->avgDailyGrowthRate($dailyGrowthRate);
                }*/


            }
        }
        return $data;
    }

    function addToBackupsTotals($size, $free){
        $this->Totals['backup_mb_size'] += $size;
        $this->Totals['backup_mb_free'] += $free;
    }

    function addToTotalTotals($size, $free){
        $this->Totals['total_mb_size'] += $size;
        $this->Totals['total_mb_free'] += $free;
    }

    function avgDedup($dedup){
        $this->itemCounter +=1;
        $dedup = $dedup += $dedup / $this->itemCounter;
        $this->Totals['count'] = $this->itemCounter;
        $this->Totals['avg_dedup'] = $dedup;
    }

    function avgDailyGrowthRate($dailyGrowthRate){
        $this->itemCounter +=1;
        $dailyGrowthRate = $dailyGrowthRate += $dailyGrowthRate / $this->itemCounter;
        $this->Totals['count'] = $this->itemCounter;
        $this->Totals['avg_daily_growth_rate'] = $dailyGrowthRate;
    }

    function buildOutput($storage, $sid, $sysName) {

        $id = isset($storage['id']) ? $storage['id'] : null;
        $name = isset($storage['name']) ? $storage['name'] : null;
     //   $type = isset($storage['type']) ? $this->mapStorageType($storage['type']) : null;
        $type = isset($storage['type']) ? $storage['type'] : null;
        $statelessType = isset($storage['stateless_type']) ? $storage['stateless_type'] : null;
        $usage = isset($storage['usage']) ? $storage['usage'] : null;
        $status = isset($storage['status']) ? $storage['status'] : "needs core work";
        $dedup = isset($storage['dedup']) ? $storage['dedup'] : "N/A";
        $compression = isset($storage['compression']) ? $storage['compression'] : true;
        $maxConcurrentBackups = isset($storage['max_concurrent_backups']) ? $storage['max_concurrent_backups'] : "N/A";
        $isDefault = isset($storage['default']) ? $storage['default'] : "N/A";
        $avgWriteSpeed = isset($storage['average_write_speed']) ? $storage['average_write_speed'] : "N/A";
        $mbSize = isset($storage['mb_size']) ? $storage['mb_size'] : 0;
        $mbFree = isset($storage['mb_free']) ? $storage['mb_free'] : 0;
        $isInfinite = isset($storage['is_infinite']) ? $storage['is_infinite'] : false;
        $isPurging = isset($storage['is_purging']) ? $storage['is_purging'] : "N/A";
        $isExpandable = isset($storage['is_expandable']) ? $storage['is_expandable'] : false;
        $mbToPurge = isset($storage['mb_to_purge']) ? $storage['mb_to_purge'] : "N/A";
        $percentUsed = isset($storage['percent_used']) ? $storage['percent_used'] : "N/A";
//        $dailyChangeRate = isset($storage['daily_change_rate']) ? $storage['daily_change_rate'] : 0;
        $dailyGrowthRate = isset($storage['daily_growth_rate']) ? $storage['daily_growth_rate'] : 0;
        $warnThreshold = isset($storage['warn_threshold']) ? $storage['warn_threshold'] : "N/A";
 //       $stopThreshold = isset($storage['stop_threshold']) ? $storage['stop_threshold'] : 0;
        $properties = isset($storage['properties'])? $storage['properties'] : null;
        if ($properties) {
            if (isset($properties['protocol'])) {
                if (strstr($properties['protocol'], 'cloud')) {
                    // See if special cloud hostname with region included.
                    // e.g., s3://region-name/
                    if (isset($properties['hostname'])) {
                        $hostname = $properties['hostname'];
                        $hostnameArray = explode('://', $hostname);
                        if (count($hostnameArray) > 1) {
                            $properties['hostname'] = $hostnameArray[0];
                            $region = $hostnameArray[1];
                            if(substr($region, -1) == "/") {
                                // remove trailing slash from region name if present.
                                $region = substr($region, 0, -1);
                            }
                            $properties['region'] = $region;
                        }
                    }
                }
            }
        }
        $hasAlert = isset($storage['has_alert'])? $storage['has_alert'] : "N/A";
        $alerts = isset($storage['alerts'])? $storage['alerts'] : "N/A";
        $storageHistory = isset($storage['storage_history']) ? $storage['storage_history'] : "N/A";



        $data = array(
            'id' => $id,
            'name' => $name,
            'sid' => $sid,
            'system_name' => $sysName,
            'type' => $type,
            'stateless_type' => $statelessType,
            'usage' => $usage,
            'properties' => $properties,
            'dedup' => $dedup,
            'average_write_speed' => $avgWriteSpeed,
            'status' => $status,
            'daily_growth_rate' => $dailyGrowthRate,
            'mb_size' => $mbSize,
            'mb_free' => $mbFree,
            'is_purging' => $isPurging,
            'is_expandable' => $isExpandable,
            'mb_to_purge' => $mbToPurge,
            'percent_used' => $percentUsed,
            'warn_threshold' => $warnThreshold,
            'max_concurrent_backups' => $maxConcurrentBackups,
            'is_default' => $isDefault,
            'is_infinite' => $isInfinite,
            'compression' => $compression,
            'has_alert' => $hasAlert,
            'alerts' => $alerts,
            'storage_history' => $storageHistory
        );


        return $data;
    }

    function mapStorageType( $intType) {

        $strType = "";
        switch ($intType){
            case '0':
            case "disk" :
                $strType = 'disk';
                break;
            case '1':
            case "iscsi" :
                $strType = 'iscsi';
                break;
            case '2':
            case "fc":
                $strType = 'fc';
                break;
            case '3':
            case "aoe" :
                $strType = 'aoe';
                break;
            case '4':
            case "nas":
                $strType = 'nas';
                break;
            case '5':
            case "added_disk":
                $strType = 'added_disk';
                break;
            default:
                break;
        }
        return $strType;
    }

    private function getStorageTargets($filter, $sid)
    {
        $storageTargets = array();

        if (array_key_exists('host', $filter) || array_key_exists('port', $filter)) {
            $host = $filter['host'];
            $port = $filter['port'];

            $iscsiTargets = $this->BP->list_iscsi_targets($host, $port, $sid);
            if ($iscsiTargets !== false) {
                $storageTargets['type'] = 'iSCSI';
                $storageTargets['targets'] = $iscsiTargets;
            } else {
                $storageTargets = false;
            }
        } else {
            $fcTargets = $this->BP->list_fc_targets($sid);
            if ($fcTargets !== false) {
                $storageTargets['type'] = 'FC';
                $storageTargets['targets'] = $fcTargets;
            } else {
                $storageTargets = false;
            }
        }

        return $storageTargets;
    }

    private function getStorageLuns($target, $filter, $sid)
    {
        $targetLuns = array();

        if (array_key_exists('host', $filter) || array_key_exists('port', $filter)) {
            $host = $filter['host'];
            $port = $filter['port'];

            $iscsiLuns = $this->BP->list_iscsi_luns($host, $port, $target, $sid);
            if ($iscsiLuns !== false) {
                $targetLuns['type'] = 'iSCSI';
                $targetLuns['luns'] = $iscsiLuns;
            } else {
                $targetLuns = false;
            }
        } else {
            $fcLuns = $this->BP->list_fc_luns($target, $sid);
            if ($fcLuns !== false) {
                $targetLuns['type'] = 'FC';
                $targetLuns['luns'] = $fcLuns;
            } else {
                $targetLuns = false;
            }
        }

        return $targetLuns;
    }

    /*
     * Returns a storage ID if the field contains one, or false if not.
     */
    function getStorageID($field) {
        $id = false;
        // id's are typically an integer.
        if (is_numeric($field)) {
            $id = (int)$field;
        } else if (is_string($field) && strlen($field) > 0 && $field[0] == 'a') {
            // OR id's could be of the form 'a' followed by an integer.
            $field = substr($field,1);
            if (is_numeric($field)) {
                $id = (int)$field;
            };
        }
        return $id;
    }


} // end Storage

?>
