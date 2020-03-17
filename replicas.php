<?php

class Replicas
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;
        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);
        $this->localID = $this->BP->get_local_system_id();

        $this->Roles = null;
        if (Functions::supportsRoles()) {
            $this->Roles = new Roles($this->BP);
        }
        require_once('virtual-clients.php');
        $this->virtual_clients = new VirtualClients($this->BP);
    }

    public function get($which, $data, $sid, $systems)
    {
        $replicas = array();
        if ($sid === false) {
            $sid = $this->localID;
        }
        $systemName = $this->functions->getSystemNameFromID($sid);

        if ($which == -1) {
            // GET /api/replicas/?sid={sid}
            $replicas = $this->get_replicas($data, $systems);
        } else {

            $filter = is_array($which) ? implode("", $which) : $which;

            switch ($filter) {
                case 'supported':
                    // GET /api/replicas/supported/?sid={sid}
                    $replicas = $this->get_replicas_supported($sid, $systemName);
                    break;
                case 'candidates':
                    //GET /api/replicas/candidates/?grandClients/app_type=Hyper-V&sid
                    if ($sid == false && $sid !== null) {
                        $replicas['error'] = 500;
                        $replicas['message'] = "System ID is required.";
                    } else {
                        $grandClients = isset($_GET['grandClients']) ? true : false;
                        $sid = isset($_GET['sid']) ?$_GET['sid'] : null;
                        //temp - will get app_type from UI
                        $inputArray = array('app_type' => 'VMware');
                        if ($grandClients || $sid === null) {
                            foreach ($systems as $sourceID => $sname) {
                                $candidates = $this->BP->get_replica_candidates($inputArray, true,
                                    $sourceID);
                                if ($candidates !== false) {
                                    foreach ($candidates as $candidate) {
                                      $replicas['candidates'][] = $candidate;
                                    }
                                }
                            }
                        }
                        if (!$grandClients && $sid === null) {
                            if ($sid === null) {$sid = $this->localID;}
                            $candidates = $this->BP->get_replica_candidates($inputArray, false, $sid);
                        } else {
                            $candidates = $this->BP->get_replica_candidates($inputArray, false, $sid);
                        }
                        if ($candidates !== false) {
                            foreach ($candidates as $candidate) {
                                if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                                    if (!$this->Roles->instance_is_in_scope($candidate['instance_id'], $sid)) {
                                        continue;
                                    }
                                }
                                $replicas['candidates'][] = $candidate;
                            }
                        }
                    }
                     break;
                case 'max_recovery_points':
                    $replicas = $this->getMaxRecoveryPoints($sid);
                    break;
                case 'guest_os':
                    $instances = isset($_GET['iid']) ? $_GET['iid'] : NULL;
                    $systemId = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
                    $replicas['data'] = $this->BP->get_guest_os_from_instance_ids($instances, $systemId);
                    break;
                default:
                    // GET /api/replicas/{id}
                    //{id} can be comma separated string for recovery_points
                    $replicas = explode(",", $which[0]);
                    if(sizeof($replicas) > 1){
                        $replicaID = $replicas;
                    }
                    else{
                        $replicaID = (int)$which[0];
                    }
                    if ($replicaID !== null) {
                        if (isset($which[1]) && is_string($which[1])) {
                            switch ($which[1]) {
                                case 'details':
                                    // GET /api/replicas/{id}/details/?sid={sid}
                                    $replicas = $this->get_replicas_details($replicaID, $sid);
                                    break;
                                case 'state':
                                    // GET /api/replicas/{id}/state
                                    $replicas = $this->get_replicas_state($replicaID, $sid);
                                    break;
                                case 'recovery_points':
                                    // GET /api/replicas/{id}/recovery_points
                                    $replicas = $this->get_replicas_recovery_points($replicaID, $sid);
                                    break;
                                case 'backup_history':
                                    $replicas = $this->get_replicas_backup_history($replicaID, $sid);
                                    break;
                                case 'last_restore':
                                    $lastRestores = $this->BP->get_last_replica_restore($replicaID, $sid);
                                    if($lastRestores !== false){
                                        $replicas = $lastRestores;
                                    }
                                    break;
                            }
                        }
                        break;
                    } else {
                        $replicas['error'] = 500;
                        $replicas['message'] = "Replica ID is required.";
                    }
            }
        }

        return $replicas;

    }

    function getWIRClients($sid){
        $data = array('type' => 'wir');
        $result = $this->virtual_clients->get_wir_clients($sid);

        return $result;
    }

    private function get_replicas_supported($sid, $systemName)
    {
        $supported = array();
        $replicasSupported = $this->BP->replica_vms_supported($sid);
        if ($replicasSupported !== -1 && $replicasSupported !== false) {
            $temp_supported_array = array();
            $temp_supported_array['system_id'] = $sid;
            $temp_supported_array['system_name'] = $systemName;
            $temp_supported_array['supported'] = $replicasSupported;

            $supported['replicas'][] = $temp_supported_array;
        } else {
            $supported['error'] = 500;
            $supported['message'] = $this->BP->getError();
        }
        return $supported;
    }

    private function get_replicas($data, $systems)
    {
        $replicasList = array();

        foreach ($systems as $systemID => $systemName) {
            $temp_replicas_array = array();
            $temp_replicas_array['system_id'] = $systemID;
            $temp_replicas_array['system_name'] = $systemName;
            $temp_replicas_array['local_sid'] = $this->localID;

            $windowsReplicas = $this->getWIRClients($systemID);
            if ($windowsReplicas !== false) {
                $temp_replicas_array['wir'] = $windowsReplicas;
            }

            $supported = $this->BP->replica_vms_supported($systemID);
            if ($supported !== -1 && $supported !== false) {
                if (array_key_exists('hypervisor_type', $data)) {
                    $hypervisorType = $data['hypervisor_type'];
                } else {
                    $hypervisorType = NULL;
                }
                $replicaVMsArray = $this->BP->get_replica_vm_list($hypervisorType, $systemID);

                if ($replicaVMsArray !== false) {
                    if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                        foreach ($replicaVMsArray as $replica) {
                            if (!$this->Roles->instance_is_in_scope($replica['instance_id'], $systemID)) {
                                continue;
                            }
                            $replicaVMs[] = $replica;
                        }
                    } else {
                        $replicaVMs = $replicaVMsArray;
                    }
                    $temp_replicas_array['replicas'] = $replicaVMs;
                }
            }
            $replicasList['data'][] = $temp_replicas_array;
        }

        return $replicasList;
    }

    private function get_replicas_details($replicaID, $sid)
    {
        if (strstr($replicaID, "wir")){
            $replicaDetails = $this->virtual_clients->get($replicaID, "wir", $sid );
        } else {
            $replicaDetails = array();
            $systemName = $this->functions->getSystemNameFromID($sid);
            $details = $this->BP->get_replica_vm_info($replicaID, $sid);

            if ($details !== false) {
                $replicaDetails['system_id'] = $sid;
                $replicaDetails['system_name'] = $systemName;
                $replicaDetails['type'] = "replica";
                $replicaDetails['details'] = $details;
            } else {
                $replicaDetails['error'] = 500;
                $replicaDetails['message'] = $this->BP->getError();
            }
        }

        return $replicaDetails;
    }

    private function get_replicas_state($replicaID, $sid)
    {
        $state = $this->BP->get_replica_vm_state($replicaID, $sid);
        if ($state === false) {
            $state['error'] = 500;
            $state['message'] = $this->BP->getError();
        }
        return $state;
    }

    private function get_replicas_recovery_points($replicaID, $sid){
        $recoveryPoints = array();
        if (is_array($replicaID)) {
            foreach ($replicaID as $replica) {
                $tempRecoveryPoints = array();
                $replica = is_string($replica) ? (int)$replica : $replica;
                $snapshots = $this->BP->get_replica_snapshots($replica, $sid);
                if ($snapshots !== false) {
                    foreach ($snapshots as $snapshot) {
                        $temp_snapshot_array = $snapshot;
                        $temp_snapshot_array['date'] = $this->functions->formatDate($snapshot['data_timestamp']);
                        $temp_snapshot_array['time'] = $this->functions->formatTime($snapshot['data_timestamp']);
                        $temp_snapshot_array['timestamp'] = $snapshot['data_timestamp'];
                        $temp_snapshot_array['backup_type'] = $this->functions->getBackupTypeDisplayName($snapshot['backup_type']);
                        unset($temp_snapshot_array['data_timestamp']);
                        $tempRecoveryPoints[] = $temp_snapshot_array;
                    }
                    $recoveryPoints['recovery_points'][$replica] = $tempRecoveryPoints;
                }
            }
        } else {
            $snapshots = $this->BP->get_replica_snapshots($replicaID, $sid);
            if ($snapshots !== false) {
                $tempRecoveryPoints = array();
                foreach ($snapshots as $snapshot) {
                    $temp_snapshot_array = $snapshot;
                    $temp_snapshot_array['date'] = $this->functions->formatDate($snapshot['data_timestamp']);
                    $temp_snapshot_array['time'] = $this->functions->formatTime($snapshot['data_timestamp']);
                    $temp_snapshot_array['timestamp'] = $snapshot['data_timestamp'];
                    $temp_snapshot_array['backup_type'] = $this->functions->getBackupTypeDisplayName($snapshot['backup_type']);
                    unset($temp_snapshot_array['data_timestamp']);
                    if (isset($snapshot['certified'])) {
                        $temp_snapshot_array['certified'] = $this->mapCertifiedString($snapshot['certified']);
                    } else {
                        $temp_snapshot_array['certified'] = "None";
                    }
                    $tempRecoveryPoints[] = $temp_snapshot_array;
                }

                $recoveryPoints['recovery_points'] = $tempRecoveryPoints;

            } else {
                $recoveryPoints['error'] = 500;
                $recoveryPoints['message'] = $this->BP->getError();
            }
        }
        return $recoveryPoints;
    }

    private function getMaxRecoveryPoints($sid){
        $result = array();

        if ($sid === false) {
            $sid = $this->localID;
        }
        $snapshots = $this->BP->get_max_replica_snapshots($sid);

        if ($snapshots !== false) {

            $result['system_id'] = $sid;
            $result['system_name'] = $this->functions->getSystemNameFromID($sid);

            foreach($snapshots as $key=>$value){
                $result['recovery_points'][] = array('hypervisor_type' => $key,
                                                     'max_recovery_points' => $value);
            }
        } else {
            $result['error'] = 500;
            $result['message'] = $this->BP->getError();
        }

        return $result;
    }

    public function post($data, $sid)
    {
        $result = array();
        $replicaInfo = array();
        if ($sid === false) {
            $sid = $this->localID;
        }

        if (isset($data['replicas']['multiple']) && $data['replicas']['multiple'] === true){
            $replicasInfoArray = $data['replicas'];
            $snaps = isset($replicasInfoArray['number_of_snaps']) ? $replicasInfoArray['number_of_snaps'] : false;
            $hypervisorInfo = isset($replicasInfoArray['hypervisor_info']) ? $replicasInfoArray['hypervisor_info'] : false;

            $replicasSupported = $this->BP->replica_vms_supported($sid);
            if ($replicasSupported !== -1 && $replicasSupported !== false){
                if (isset($replicasInfoArray['replica'])){
                    foreach ($replicasInfoArray['replica'] as $replica){
                        $replicaInfo['instance_id'] = intval($replica['instance_id']);
                        $replicaInfo['replica_name'] = $replica['name'];
                        if ($snaps){
                            $replicaInfo['number_of_snaps'] = intval($snaps);
                        }
                        if ($hypervisorInfo){
                            if (isset($hypervisorInfo['esx_host'])){
                                $replicaInfo['hypervisor_info']['esx_host'] = $hypervisorInfo['esx_host'];
                            }
                            if (isset($hypervisorInfo['hyperv_host'])){
                                $replicaInfo['hypervisor_info']['hyperv_host'] = $hypervisorInfo['hyperv_host'];
                            }
                            if (isset($hypervisorInfo['vm_location'])){
                                $replicaInfo['hypervisor_info']['vm_location'] = $hypervisorInfo['vm_location'];
                            }
                            if (isset($hypervisorInfo['container'])){
                                $replicaInfo['hypervisor_info']['container'] = $hypervisorInfo['container'];
                            }
                        }

                        $created  = $this->BP->save_replica_vm($replicaInfo, $sid);

                        if ($created !== false) {
                            $tempResult['replica_id'] = $created['replica_id'];
                            $tempResult['create_job_id'] = $created['create_job_id'];
                            $tempResult['restore_job_id'] = $created['first_restore_job_id'];
                            $result['result'][] = $tempResult;
                        } else {
                            $error['error'] = 500;
                            $error['message'] = $this->BP->getError();
                        }
                    }
                }
            } else {
                $error['error'] = 500;
                $error['message'] = $this->BP->getError();
            }
        } else {
            if (array_key_exists('replica', $data)) {
                $replicaData = $data['replica'];
                $replicaInfo['instance_id'] = intval($replicaData['instance_id']);
                $replicaInfo['replica_name'] = $replicaData['replica_name'];
                if (array_key_exists('number_of_snaps', $replicaData)) {
                    $replicaInfo['number_of_snaps'] = intval($replicaData['number_of_snaps']);
                }
                if (array_key_exists('hypervisor_info', $replicaData)) {
                    $replicaInfo['hypervisor_info'] = $replicaData['hypervisor_info'];
                    $hypervisorInfo = $replicaData['hypervisor_info'];
                    if (array_key_exists('hyperv_host', $hypervisorInfo)) {
                        $replicaInfo['hypervisor_info']['hyperv_host'] = intval($hypervisorInfo['hyperv_host']);
                    }
                }
            }

            $support = $this->BP->replica_vms_supported($sid);
            if ($support !== -1 && $support !== false) {
                $replicaArray = false;
                if (isset($replicaInfo['hypervisor_info'])) {
                    if (array_key_exists('esx_host', $replicaInfo['hypervisor_info'])) {
                        if (in_array('VMware host', $support)) {
                            $replicaArray = $this->BP->save_replica_vm($replicaInfo, $sid);
                        } else {
                            $error['error'] = 500;
                            $error['message'] = "Replicas is not supported on VMware host.";
                            return $error;
                        }
                    } elseif (array_key_exists('hyperv_host', $replicaInfo['hypervisor_info'])) {
                        if (in_array('Hyper-V host', $support)) {
                            $replicaArray = $this->BP->save_replica_vm($replicaInfo, $sid);
                        } else {
                            $error['error'] = 500;
                            $error['message'] = "Replicas is not supported on Hyper-V host.";
                            return $error;
                        }
                    }
                }
                if ($replicaArray !== false) {
                    $tempResult['replica_id'] = $replicaArray['replica_id'];
                    $tempResult['create_job_id'] = $replicaArray['create_job_id'];
                    $tempResult['restore_job_id'] = $replicaArray['first_restore_job_id'];
                    $result['result'][] = $tempResult;
                } else {
                    $error['error'] = 500;
                    $error['message'] = $this->BP->getError();
                }
            } else {
                $error['error'] = 500;
                $error['message'] = $this->BP->getError();
            }
        }
        return (isset($error) ? $error : $result);
    }

    public function put($which, $data, $sid)
    {
        $result = array();
        $replicaInfo = array();
        if ($which == -1) {
            $result['error'] = 500;
            $result['message'] = "Replica ID is required.";
            return $result;
        } else {
            switch ($which[0]) {
                case 'audit':
                    $bPowerOn = isset($data['powerOn']) ? $data['powerOn'] : true;
                    if (isset($which[1]) && is_string($which[1]) ) {
                        switch($which[1]) {
                            case 'start':
                                if (isset($which[2])) {
                                    $replicaID = (int)$which[2];
                                    $pit_snap = $data['recovery_point'];
                                    if (isset($pit_snap) && $pit_snap !== null && $pit_snap !== "") {
                                        if ($this->checkValidState($replicaID, $sid)) {
                                            $result = $this->BP->audit_replica_vm($replicaID, true, $pit_snap, $bPowerOn);
                                            if ($result == false) {
                                                $result['error'] = 500;
                                                $result['message'] = $this->BP->getError();
                                                return $result;
                                            }
                                        }
                                    } else {
                                        $result['error'] = 500;
                                        $result['message'] = "Recovery point is required.";
                                        return $result;
                                    }
                                }
                                break;
                            case 'stop':
                                if (isset($which[2])) {
                                    $replicaID = (int)$which[2];
                                    $pit_snap = NULL;
                                    if ($this->checkValidState($replicaID, $sid)) {
                                        $result = $this->BP->audit_replica_vm($replicaID, false, $pit_snap, $bPowerOn);
                                        if ($result == false) {
                                            $result['error'] = 500;
                                            $result['message'] = $this->BP->getError();
                                            return $result;
                                        }
                                    }
                                }
                                break;
                        }
                    }
                    break;
                case 'live':
                    if (isset($which[1]) && is_string($which[1]) ) {
                        switch($which[1]) {
                            case 'start':
                                if (isset($which[2])) {
                                    $replicaID = (int)$which[2];
                                    $pit_snap = $data['recovery_point'];
                                    if (isset($pit_snap) && $pit_snap !== null && $pit_snap !== "") {
                                        if ($this->checkValidState($replicaID, $sid)) {
                                            $result = $this->BP->run_replica_vm($replicaID, true, $pit_snap);
                                            if ($result == false) {
                                                $result['error'] = 500;
                                                $result['message'] = $this->BP->getError();
                                                return $result;
                                            }
                                        }
                                    } else {
                                        $result['error'] = 500;
                                        $result['message'] = "Recovery point is required.";
                                        return $result;
                                    }
                                }
                                break;
                            case 'stop':
                                if (isset($which[2])) {
                                    $replicaID = (int)$which[2];
                                    $pit_snap = NULL;
                                    if ($this->checkValidState($replicaID, $sid)) {
                                        $result = $this->BP->run_replica_vm($replicaID, false, $pit_snap);
                                        if ($result == false) {
                                            $result['error'] = 500;
                                            $result['message'] = $this->BP->getError();
                                            return $result;
                                        }
                                    }
                                }
                                break;
                        }
                    }
                    break;
                case 'restores':
                    if (isset($which[1]) && is_string($which[1]) ) {
                        switch ($which[1]) {
                            case 'start':
                                if (isset($which[2])) {
                                    $replicaID = (int)$which[2];
                                    $state = $this->BP->get_replica_vm_state($replicaID, $sid);
                                    if ($state !== false){
                                        $validState = $state['valid'];
                                    }
                                    if ($validState) {
                                        $result = $this->BP->disable_replica_restores($replicaID, false, $sid);
                                        if ($result == false) {
                                            $result['error'] = 500;
                                            $result['message'] = $this->BP->getError();
                                            return $result;
                                        }
                                    }
                                }
                                break;
                            case 'stop':
                                if (isset($which[2])) {
                                    $replicaID = (int)$which[2];
                                    $result = $this->BP->disable_replica_restores($replicaID, true, $sid);
                                    if ($result == false) {
                                        $result['error'] = 500;
                                        $result['message'] = $this->BP->getError();
                                        return $result;
                                    }
                                }
                                break;
                        }
                    }

                    break;
                case 'max_recovery_points':
                    if ($data !== null) {
                        $hypervisorType = isset($data['hypervisor_type']) ? $data['hypervisor_type'] : false;
                        $maxSnaps = isset($data['max_recovery_points']) ? $data['max_recovery_points'] : false;

                        //$result = $this->BP->save_max_replica_snapshots($hypervisorType, $maxSnaps , $sid);

                        $result = $this->BP->save_max_replica_snapshots($hypervisorType, $maxSnaps , $sid);
                    } else {
                        $error['error'] = 500;
                        $error['message'] = $this->BP->getError();
                    }

                    return (isset($error) ? $error : $result);
                    break;

                default:
                    if (array_key_exists('replica', $data)) {
                        $replicaData = $data['replica'];
                        $replicaInfo['id'] = (int)$which[0];
                        if (array_key_exists('processors', $replicaData)) {
                            $replicaInfo['processors'] = intval($replicaData['processors']);
                        }
                        if (array_key_exists('memory', $replicaData)) {
                            $replicaInfo['memory'] = intval($replicaData['memory']);
                        }
                        if (array_key_exists('number_of_snaps', $replicaData)) {
                            $replicaInfo['number_of_snaps'] = intval($replicaData['number_of_snaps']);
                        }
                        $result = $this->BP->save_replica_vm($replicaInfo, $sid);
                        if ($result == false) {
                            $result['error'] = 500;
                            $result['message'] = $this->BP->getError();
                            return $result;
                        }
                    }
                    break;
            }
        }
        return $result;
    }

    public function delete($which, $data, $sid = null){
        $result = false;
        $deleteFromHypervisor = isset($data['deleteFromHypervisor']) ? $data['deleteFromHypervisor'] : false;
        if ($deleteFromHypervisor !== false) {
            if ($which) {
                $replicaID = $which;
                $result = $this->BP->delete_replica_vm($replicaID, $deleteFromHypervisor, $sid);
            } else {
                $result['error'] = 500;
                $result['message'] = $this->BP->getError();
            }
        } else {
            $result['error'] = 500;
            $result['message'] = "deleteFromHypervisor is required.";
        }
        return $result;
    }
    private function checkValidState($replicaID, $sid)
    {
        $valid = true;
        $state = $this->BP->get_replica_vm_state($replicaID, $sid);
        if ($state !== false) {
            $currentState = $state['current_state'];
            if ($currentState === Constants::REPLICAS_STATE_NEW || $currentState === Constants::REPLICAS_STATE_HALTED ||
                    $currentState === Constants::REPLICAS_STATE_CREATE) {
                $valid = false;
            }
        } else {
            $valid = false;
        }
        return $valid;
    }

    private function get_replicas_backup_history($replicaID, $sid)
    {
        $backup_history = array();

        $result = $this->BP->get_last_replica_restore($replicaID, $sid);
        if ($result !== false) {
            if (count($result) > 0) {
                foreach ($result as $backup) {
                    $last_backup = $this->displayBackupItem($backup);
                    $backup_history['last'][] = $last_backup;
                }
            }
            $result = $this->BP->get_replica_restore_backlog($replicaID, $sid);
            if ($result !== false) {
                foreach ($result as $backup) {
                    $pending_backup = $this->displayBackupItem($backup);
                    $backup_history['pending'][] = $pending_backup;
                }
            }
        } else {
            $backup_history = $result;
        }
        return $backup_history;
    }

    private function displayBackupItem($backup)
    {
        $backup_item = array();

        $day = $this->functions->formatDate($backup['start_time']);
        $time = $this->functions->formatTime($backup['start_time']);

        // Check to see if type is set, because if the backup is already purged, it will not be.
        if (isset($backup['type'])) {
            $backupType	= $this->functions->getBackupTypeString($backup['type']);
        } else {
            $backupType = "unknown";
        }

        $backup_item['type'] = $backupType;
        $backup_item['id'] = $backup['id'];
        $backup_item['client_name'] = $backup['replica_name'];
        $backup_item['date'] = $day;
        $backup_item['time'] = $time;

        if (isset($backup['size'])) {
            $backup_item['size'] = $backup['size'] . ' MB';
        }

        if (isset($backup['elapsed_time'])){
            $backup_item['elapsed_time'] = $backup['elapsed_time'];
        }

        // If the backup has file count, note this.
        // if type is 'verify', show "N/A" rather than 0
        if (isset($backup['files'])) {
            if($backup['type'] == 'verify'){
                $backup_item['files'] =  "N/A";
            } else {
                $backup_item['files'] = $backup['files'];
            }
        }

        // See if backup is currently being restored (for backlog API).
        if (isset($backup['currently_running'])) {
            $backup_item['running'] = $backup['currently_running'] == true ? '1' : '0';
        }
        if (isset($backup['grandclient'])) {
            $backup_item['grandclient'] = $backup['grandclient'];
        }
        if (isset($backup['system_id'])) {
            $backup_item['system_id'] = $backup['system_id'];
        }
        if (isset($backup['system_name'])) {
            $backup_item['system_name'] = $backup['system_name'];
        }

        return $backup_item;
    }

    function mapCertifiedString($status){
        $certifiedString = "";
        switch($status){
            case 0:
            case 2:
                $certifiedString ="Not Certified";
                break;
            case 1:
                $certifiedString ="Error";
                break;
            case 3:
                $certifiedString = "Certified";
                break;
            case 4:
                $certifiedString = "Certified with warning";
                break;
        }

        return $certifiedString;
    }
}
?>
