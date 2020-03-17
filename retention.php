<?php

class Retention
{
    private $BP;

    const MIN_MAX = "MinMax";

    public function __construct($BP)
    {
        $this->BP = $BP;
        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);
    }

    public function get_retention($which, $data, $sid)
    {
        $result = array();

        if ($which !== -1) {
            switch($which[0]) {
                case "policy":
                    if (!isset($which[1])) {
                        $which[1] = -1;
                    }

                   /**************************************************************************************
                        04.06.18.sab:  UNIBP-17605 - new API GET /retention/policy/default/<policy_id
                        need to pass all of '$which' to get_retention_policy
                    ***************************************************************************************/
                    $result = $this->get_retention_policy($which, $sid);
                    break;
                case "strategy":
                    if (isset($which[1])){
                        switch ($which[1]){
                            case "managed":
                                $allLtr = array();
                                $allMinMax = array();
                                $ltr = array();
                                $minmax = array();

                                $systems = $this->functions->getSystems(false, false, true, true, true, false, false);
                                if ($systems !== false) {
                                    list($ltrStrategyArray, $minmaxStrategyArray) = ($this->getManagedSystemsStrategy($systems));

                                    foreach ($ltrStrategyArray as $ltrStrategy) {
                                        $allLtr[] = array_merge($ltr, $this->buildManagedStrategy($ltrStrategy));
                                    }
                                    foreach ($minmaxStrategyArray as $minmaxStrategy) {
                                        $allMinMax[] = array_merge($minmax, $this->buildManagedStrategy($minmaxStrategy));
                                    }
                                }
                                $ltrReturn['LTR'] = $allLtr;
                                $minmaxReturn['MinMax'] = $allMinMax;

                                $result['data'] = array_merge($ltrReturn, $minmaxReturn);
                                break;
                            case "onTarget":
                                $data = array();
                                $systems = $this->functions->getSystems(false, false, true, false, true, true, true );
                                if ($systems !== false){
                                    foreach ($systems as $id => $name) {
                                        $data[] = array('sid' => $id,
                                            'system_name' => $name);
                                    }
                                    $result['data'] = $data;
                                }
                                break;
                        }

                    } else {
                        $result = $this->get_retention_strategy($sid);
                    }
                    break;
                case "switch":
                    $GFSSettings = $this->BP->get_ini_section("GFS", $sid);
                    $GFSLiteEnabled = false;

                    if ($GFSSettings !== false) {
                        $GFSLiteEnabled = $this->getValue($GFSSettings, 'Enabled');
                    }

                    $strategy = $this->BP->get_retention_strategy($sid);
                    if ($strategy !== false){
                        switch($strategy) {
                            case "ltr":
                               $result['data']['strategy'] = "LTR";
                               $result['data']['message'] = "This appliance is already using LTR";
                                break;
                            case $GFSLiteEnabled:
                                $result['data']['strategy'] = "GFS-lite-enabled";
                                $result['data']['message'] = "This appliance is using GFS lite.";
                                break;
                            case "MinMax":
                                $result['data']['strategy'] = "min-max";
                                $result['data']['message'] = "This appliance is using min-max.  LTR policies will need to be created and assigned to the assets";
                            default:
                                $result['data']['strategy'] = $strategy;
                                $result['data']['message'] = "This retention strategy is deprecated";
                                break;
                        }
                    }
                    break;
                case "gfs_supported":
                    $result = $this->BP->is_gfs_supported($sid);
                    break;
                case "retention-points":
                    if (isset($_GET['iid'])) {
                        $instanceID = (int)$_GET['iid'];
                        $points = $this->BP->get_gfs_retention_points($instanceID, $sid);
                        if ($points !== false) {
                            $updatedDate = "Never";
                            if (isset($points['updated'])) {
                                $updatedDate = $this->functions->formatDateTime($points['updated']);
                            }
                            $points['updated_date'] = $updatedDate;
                        } else {
                            global $Log;
                            $Log->writeError('cannot get retention points' . $this->BP->getError(), true);
                        }
                        $result = $points;
                    } else {
                        $result = array();
                        $result['error'] = 500;
                        $result['message'] = "An instance ID must be passed in to return its recovery points.";
                    }
                    break;
            }
        } else {

            $result = $this->get_gfs_retention($data, $sid);
        }
        return $result;
    }

    private function get_retention_policy($which, $sid){
        $policy_list = array();
        $filter = array();
        $policyID = NULL;
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        switch ($which) {
            case $which[1] == "default":
                $policy_list = $this->get_policies_with_default($which[2], $sid); //which[2] here is the policy_id
                break;
            default:
                if ($which[1] !== -1) {
                    $filter['policy_id'] = (int)$which[1];
                }
                if (isset($_GET['source_id'])) {
                    $filter['source_id'] = (int)$_GET['source_id'];
                }

                $strategy = $this->BP->get_retention_strategy($sid);
                if ($strategy !== false) {
                    if ($strategy !== Retention::MIN_MAX) {
                        $policies = $this->BP->get_gfs_policy($filter, $sid);
                        if ($policies !== false) {
                            foreach ($policies as $policy) {

                                $temp_policy_list = array();
                                $temp_policy_list['id'] = $policy['policy_id'];
                                $temp_policy_list['name'] = $policy['policy_name'];
                                $temp_policy_list['description'] = isset($policy['policy_description']) && $policy['policy_description'] !== "" ? $policy['policy_description'] : $policy['policy_name'];
                                $temp_policy_list['days'] = $policy['days'] == -1 ? 'Forever' : $policy['days'];
                                $temp_policy_list['weeks'] = $policy['weeks'] == -1 ? 'Forever' : $policy['weeks'];
                                $temp_policy_list['months'] = $policy['months'] == -1 ? 'Forever' : $policy['months'];
                                $temp_policy_list['years'] = $policy['years'] == -1 ? 'Forever' : $policy['years'];
                                $temp_policy_list['is_global'] = $policy['is_global'];
                                if (isset($policy['systems'])) {
                                    $systems = array();
                                    $systemsWithPolicy = $policy['systems'];
                                    $temp_policy_list['systems'] = array();

                                    foreach ($systemsWithPolicy as $sid => $info) {
                                        $systems = array('system_id' => $sid,
                                            'name' => $info['name'],
                                            'is_default' => $info['is_default']
                                        );
                                        $temp_policy_list['systems'][] = $systems;
                                    }
                                }
                                $policy_list['data'][] = $temp_policy_list;
                            }
                        } else {
                            $policy_list = $policies;
                        }
                    } else {
                        $policy_list['error'] = 500;
                        $policy_list['message'] = "Retention policies cannot be listed as the retention strategy of the system is not GFS.";
                    }
                } else {
                    $policy_list = false;
                }

                break;
        }

        return $policy_list;
    }

    function get_policies_with_default($policy_id, $sid){
        $defaultPolicies = array();
        $filter = array();

        if($policy_id !== null) {
            $filter['policy_id'] = (int)$policy_id;
            $policyArray = $this->BP->get_gfs_policy($filter, $sid);
            if ($policyArray !== false){
                foreach ($policyArray as $policy) {
                    if (isset($policy['systems'])) {
                        $systemsArray = $policy['systems'];
                        $defaultSystems = array();
                        foreach($systemsArray as $source_id => $info){
                            if ($info['is_default']){
                                $defaultSystems[] = array('source_id' => $source_id,
                                                        'name' => $info['name'],
                                                        'is_default' => $info['is_default']);
                            };
                        };

                        $defaultPolicies['data'] = $defaultSystems;
                    }
                }
            }
        } else {
            $defaultPolicies['error'] = 500;
            $defaultPolicies['message'] = "Policy ID is required";
        }
        return $defaultPolicies;
    }

    private function get_retention_strategy($sid)
    {
        $result = false;
        if ($sid === false) {
            $sid = $this->BP->get_local_system_id();
        }
        $strategy = $this->BP->get_retention_strategy($sid);
        if ($strategy !== false) {
            $result['data']['strategy'] = $strategy;
        } else {
            $result = $strategy;
        }
        return $result;
    }

    private function get_gfs_retention($data, $sid)
    {
        $retention = false;
        $filter = array();
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();

        if (isset($_GET['uuid'])) {
            $filter['uuid'] = (int)$_GET['uuid'];
        }
        if (isset($_GET['app_id'])) {
            $filter['app_id'] = (int)$_GET['app_id'];
        }
        if (isset($_GET['client_id'])) {
            $filter['client_id'] = (int)$_GET['client_id'];
        }
        if (isset($_GET['instance_id'])) {
            $filter['instance_id'] = (int)$_GET['instance_id'];
        }

        $strategy = $this->BP->get_retention_strategy($sid);
        if ($strategy !== false && $strategy !== Retention::MIN_MAX) {
            $associations = $this->BP->get_gfs_retention($filter, $sid);
            if ($associations !== false) {
                foreach ($associations as $association) {
                    $temp_retention = array();
                    $temp_retention['policy_id'] = $association['policy_id'];
                    $temp_retention['policy_name'] = $association['policy_name'];
                    $temp_retention['days'] = $association['days'];
                    $temp_retention['weeks'] = $association['weeks'];
                    $temp_retention['months'] = $association['months'];
                    $temp_retention['years'] = $association['years'];
                    $temp_retention['is_global'] = $association['is_global'];
                    if (isset($association['systems'])){
                        $temp_retention['systems'] = array();
                        foreach($association['systems'] as $id => $info){
                            $temp_retention['systems'][] = array('system_id' => $id, 'name' => $info['name'], 'is_default' => $info['is_default']);
                        }
                    }
                    $temp_retention['instances'] = $association['instances'];
                    $retention['data'][] = $temp_retention;
                }
            } else {
                $retention = $associations;
            }
        } else {
            $retention['error'] = 500;
            $retention['message'] = "GFS Retention settings cannot be fetched as the retention strategy of the system is not GFS.";
        }
        return $retention;
    }

    public function post_retention($which, $data, $sid)
    {
        $result = array();
        if ($which !== -1) {
            switch($which) {
                case "policy":
                    $result = $this->post_retention_policy($data, $sid);
                    break;
                case "strategy":
                    $result = $this->post_retention_strategy($data, $sid);
                    break;
                case "affected-backups":
                    $result = $this->get_affected_backups($data, $sid);
                    break;
            }
        } else {
            $result = $this->post_gfs_retention($data, $sid);
        }
        return $result;
    }

    // 1/19/18: sab: updated to match latest bpl apis
    // test successful with postman
    private function post_retention_policy($data, $sid)
    {
        $result = false;
        $gfsSettings = array();

        if (array_key_exists('name', $data)) {
            $gfsSettings['policy_name'] = $data['name'];
        }

        $gfsSettings['policy_description'] = isset($data['description']) ? $data['description'] : $data['name'];

        if (array_key_exists('years', $data)) {
            $gfsSettings['years'] = $data['years'];
        }
        if (array_key_exists('months', $data)) {
            $gfsSettings['months'] = $data['months'];
        }
        if (array_key_exists('weeks', $data)) {
            $gfsSettings['weeks'] = $data['weeks'];
        }
        if (array_key_exists('days', $data)) {
            $gfsSettings['days'] = $data['days'];
        }

        $isDefault = isset($data['is_default']) ? $data['is_default'] : false;

        if (array_key_exists('systems', $data)) {
            $gfsSettings['systems'] = $data['systems'];
        } else {
            $gfsSettings['systems'] = array(array('system_id' => $this->BP->get_local_system_id(),
                'is_default' => $isDefault)
            );
        }

        $gfsSettings['is_global'] = isset($data['is_global']) ? (int)$data['is_global'] : false;

        $strategy = $this->BP->get_retention_strategy($sid);
        if ($strategy !== false && $strategy !== Retention::MIN_MAX) {
            $result = $this->BP->save_gfs_policy($gfsSettings, $sid);
            if ($result == false) {
                $msg = bp_error();
                if (strstr($msg, "The 'systems' array must contain at least one system")) {
                    $result = "You must assign the policy to a source before you can add it.";
                }
            }
        } else {
            $result['error'] = 500;
            $result['message'] = "Retention policy cannot be created as the retention strategy of the system is not GFS.";
        }
        return $result;
    }

    private function post_retention_strategy($data, $sid)
    {
        $result = false;
        $strategy = "";
        if (array_key_exists('strategy', $data)) {
            $strategy = $data['strategy'];
        }
        $result = $this->BP->set_retention_strategy($strategy, $sid);
        return $result;
    }

    private function get_affected_backups($data, $sid)
    {
        $settings = array();
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        if (array_key_exists('retention', $data)) {
            $retentionInput = $data['retention'];
                foreach ($retentionInput as $retention) {
                    $settings[] = array('instance_id' => $retention['instance_id'],
                                        'policy_id' => $retention['policy_id']);
                }

                $backups = $this->BP->get_gfs_affected_backups($settings, $sid);

                $affected_backups = array();
                if ($backups !== false) {
                    foreach ($backups as $backup) {
                        $result_format = array();
                        $backupIDs = explode(",", $backup['backup_ids']);
                        foreach ($backupIDs as $backupID) {
                            $result_format['backup_ids'][] = (int)$backupID;
                        }
                        $result_format['system_id'] = $sid;
                        $backup_status = $this->BP->get_backup_status($result_format);

                        //skip securesync to avoid dup ids
                        foreach ($backup_status as $status) {
                            if (strstr($status['type'], 'securesync')) {
                                continue;
                            }

                            $temp_backup_array = array();
                            $temp_backup_array['id'] = $status['id'];
                            $temp_backup_array['instance_id'] = isset($status['instance_id']) ? $status['instance_id'] : null;
                            $temp_backup_array['client_id'] = isset($status['client_id']) ? $status['client_id'] : null;
                            $temp_backup_array['client_name'] = isset($status['client_id']) ? $this->getClientName($status['client_id'], $sid) : null;
                            $temp_backup_array['type'] = isset($status['type']) ? $this->functions->getBackupTypeString($status['type']) : null;
                            $temp_backup_array['start_time'] = isset($status['start_time']) ? $this->functions->formatDateTime($status['start_time']) : null;
                            if (isset($status['database_name'])) {
                                $temp_backup_array['database_name'] = $status['database_name'];
                            }
                            if (isset($status['vm_name'])) {
                                $temp_backup_array['vm_name'] = $status['vm_name'];
                            }
                            if (isset($status['server_name'])) {
                                $temp_backup_array['server_name'] = $status['server_name'];
                            }
                            $temp_backup_array['replicated'] = $status['replicated'];

                            $affected_backups['backups'][] = $temp_backup_array;
                        }
                    }
                } else {
                    $affected_backups['error'] = 500;
                    $affected_backups['message'] = $this->BP->getError();
                }
            } else {
                $affected_backups['error'] = 500;
                $affected_backups['message'] = "Specify the GFS Retention settings for getting list of backups that might get deleted
                            if retention policy is associated with assets.";
            }

        return $affected_backups;
    }

    private function post_gfs_retention($data, $sid)
    {
        $settings = array();
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        $strategy = $this->BP->get_retention_strategy($sid);
        if ($strategy !== false && $strategy !== Retention::MIN_MAX) {
            if (array_key_exists('retention', $data)) {
                foreach ($data['retention'] as $retention) {
                    $settings[] = $retention;
                }
                $result = $this->BP->apply_gfs_retention($settings, $sid);
            } else {
                $result['error'] = 500;
                $result['message'] = "Specify the GFS Retention settings for associating retention policies with assets.";
            }
        } else {
            $result['error'] = 500;
            $result['message'] = "GFS Retention Policies cannot be associated with assets as the retention strategy of the system is not GFS.";
        }
        return $result;
    }

    public function put_retention($which, $data, $sid)
    {
        $result = array();
        switch($which[0]) {
            case "policy":
                if (!isset($which[1])){
                    $which[1] = -1;
                }
                $result = $this->put_retention_policy($which[1], $data, $sid);
                break;
            case "min-max":
                if(isset($data['client_id'])){
                    switch($data['type']){
                        case Constants::APPLICATION_TYPE_NAME_VMWARE:
                            // setting retention from Copied Assets
                            if (isset($_GET['grandclient']) && ($_GET['grandclient'] == 'true')) {
                                $vmList = $this->BP->get_grandclient_vm_info($data['client_name'], $data['client_id']);
                            } else {
                                $vmList = $this->BP->get_vm_info($data['client_id'], NULL, true, false, $sid);
                            }
                            $result = $this -> addRetentionForAllInstances($vmList, $data, $sid);
                            break;
                        case Constants::APPLICATION_TYPE_NAME_HYPER_V:
                            $instances = $this->BP->get_hyperv_info($data['client_id'], true, $sid);
                            $result = $this -> addRetentionForAllInstances($instances, $data, $sid);
                            break;
                        case Constants::APPLICATION_TYPE_NAME_SQL_SERVER:
                            $instances = $this->BP->get_sql_info($data['client_id'], $data['id'], true, $sid);
                            $result = $this -> addRetentionForAllInstances($instances, $data, $sid);
                            break;
                        case Constants::APPLICATION_TYPE_NAME_EXCHANGE:
                            $instances = $this->BP->get_exchange_info($data['client_id'], true, $sid);
                            $result = $this -> addRetentionForAllInstances($instances, $data, $sid);
                            break;
                        case Constants::APPLICATION_TYPE_NAME_ORACLE:
                            $instances = $this->BP->get_oracle_info($data['client_id'], $data['id'], true, $sid);
                            $result = $this -> addRetentionForAllInstances($instances, $data, $sid);
                            break;
                        case Constants::APPLICATION_TYPE_NAME_SHAREPOINT:
                            $instances = $this->BP->get_sharepoint_info($data['client_id'], $data['id'], true, $sid);
                            $result = $this -> addRetentionForAllInstances($instances, $data, $sid);
                            break;
                        case Constants::APPLICATION_TYPE_NAME_XEN:
                            $instances = $this->BP->get_xen_vm_info($data['client_id'], true, false, $sid);
                            $result = $this -> addRetentionForAllInstances($instances, $data, $sid);
                            break;
                        case Constants::APPLICATION_TYPE_NAME_AHV:
                            $instances = $this->BP->get_ahv_vm_info($data['client_id'], true, false, $sid);
                            $result = $this -> addRetentionForAllInstances($instances, $data, $sid);
                            break;
                        case Constants::APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE:
                            $instances = $this->BP->get_ucssp_info($data['client_id'], $data['id'], true, $sid);
                            $result = $this -> addRetentionForAllInstances($instances, $data, $sid);
                            break;
                        case Constants::APPLICATION_TYPE_NAME_NDMP_DEVICE:
                            $instances = $this->BP->$this->BP->get_ndmpvolume_info($data['client_id'], true, $sid);
                            $result = $this -> addRetentionForAllInstances($instances, $data, $sid);
                            break;
                        case Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL:
                            $blockInstance = $this->BP->get_block_info($data['client_id'], $sid);
                            $result = $this -> addRetentionForAllInstances(array($blockInstance), $data, $sid);
                            break;
                    }
                }
                else{
                    $result = $this->saveRetentionSettings($data, $sid);
                }
                break;
        }
        return $result;
    }

    /*
     * Save retention settings for the items in the data.
     */
    function saveRetentionSettings($data, $sid) {
        global $SLAPolicy;
        // Check to see which instances are in an an SLA Policy and which are not.  There will be 2 array results:
        // not_in_policy will be a list of instances not in a policy, ready to be passed to the API to save retention
        // in_policy will be a list of instances that are in a policy, and which will be skipped and returned to the user.
        $instancePolicyInfo = $SLAPolicy->instancesInPolicy($data, $sid);
        if (isset($instancePolicyInfo['in_policy']) && count($instancePolicyInfo['in_policy']) > 0) {
            $data = $instancePolicyInfo['not_in_policy'];
            if (count($data) > 0) {
                // We have some instances to save that are not in a policy.
                $result = $this->BP->save_retention_settings($data, $sid);
                if ($result !== false) {
                    $resultArray = array('code' => Constants::AJAX_RESULT_PARTIAL_SUCCESS, 'in_policy' => $instancePolicyInfo['in_policy']);
                    $result = array('result' => array($resultArray));
                }
            } else {
                // There are no instances to save that are not in a policy.
                $resultArray = array('code' => Constants::AJAX_RESULT_WARNING, 'in_policy' => $instancePolicyInfo['in_policy']);
                $result = array('result' => array($resultArray));
            }
        } else {
            $result = $this->BP->save_retention_settings($data, $sid);
        }
        return $result;
    }

    function addRetentionForAllInstances($instances, $data, $sid){

        $allInstances = array();
        foreach ($instances as $instance) {
            $val['instance_id'] = $instance['instance_id'];
            $val['legal_hold'] = $data['retentionInfo']['legal_hold'];
            $val['retention_min'] = $data['retentionInfo']['retention_min'];
            $val['retention_max'] = $data['retentionInfo']['retention_max'];
            $allInstances[] = $val;
        }
        if(!empty($allInstances)){
            $result = $this->saveRetentionSettings($allInstances, $sid);
        }
        else{
            $result['error'] = 500;
            $result['message'] = "No instances were found!";
        }
        return $result;
    }

    // 1/19/18: sab: updated to match latest bpl apis
    // test successful with postman
    private function put_retention_policy($which, $data, $sid)
    {
        $result = false;
        $gfsSettings = array();
        if ($which !== -1) {
            $policyID = (int)$which;
            $gfsSettings['policy_id'] = $policyID;
            if (array_key_exists('name', $data)) {
                $gfsSettings['policy_name'] = $data['name'];
            }
            $gfsSettings['policy_description'] = isset($data['description']) ? $data['description'] : $data['name'];
            if (array_key_exists('years', $data)) {
                $gfsSettings['years'] = $data['years'];
            }
            if (array_key_exists('months', $data)) {
                $gfsSettings['months'] = $data['months'];
            }
            if (array_key_exists('weeks', $data)) {
                $gfsSettings['weeks'] = $data['weeks'];
            }
            if (array_key_exists('days', $data)) {
                $gfsSettings['days'] = $data['days'];
            }
            $isDefault = isset($data['is_default']) ? $data['is_default'] : false;

            if (array_key_exists('systems', $data)) {
                $gfsSettings['systems'] = $data['systems'];
            } else {
                $gfsSettings['systems'] = array(array('system_id' => $this->BP->get_local_system_id(),
                                                      'is_default' => $isDefault)
                );
            }

            $gfsSettings['is_global'] = isset($data['is_global']) ? (int)$data['is_global'] : false;

            $strategy = $this->BP->get_retention_strategy($sid);
            if ($strategy !== false && $strategy !== Retention::MIN_MAX) {
                $result = $this->BP->save_gfs_policy($gfsSettings, $sid);
            } else {
                $result['error'] = 500;
                $result['message'] = "Retention policy cannot be modified as the retention strategy of the system is not GFS.";
            }
        } else {
            $result['error'] = 500;
            $result['message'] = "Policy ID must be specified.";
        }
        return $result;
    }

    public function delete_retention($which, $sid)
    {
        $result = array();
        if ($which !== -1) {
            switch ($which[0]) {
                case "policy":
                    if (!isset($which[1])) {
                        $which[1] = -1;
                    }
                    $result = $this->delete_retention_policy($which[1], $sid);
                    break;
                default:
                    $result = $this->delete_gfs_retention($which[0], $sid);
                    break;
            }
        } else {
            $result = $this->delete_gfs_retention($which, $sid);
        }
        return $result;
    }

    private function delete_retention_policy($which, $sid)
    {
        $result = false;
        if ($which !== -1) {
            $policyID = (int)$which;
            $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
            $strategy = $this->BP->get_retention_strategy($sid);
            if ($strategy !== false && $strategy !== Retention::MIN_MAX) {
                $result = $this->BP->delete_gfs_policy($policyID, $sid);
            } else {
                $result['error'] = 500;
                $result['message'] = "Retention policy cannot be deleted as the retention strategy of the system is not GFS.";
            }
        } else {
            $result['error'] = 500;
            $result['message'] = "Policy ID must be specified.";
        }
        return $result;
    }

    private function delete_gfs_retention($which, $sid)
    {
        $result = false;
        if ($which !== -1) {
            $instanceID = (int)$which;
            $policyID = 0;
            $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
            $strategy = $this->BP->get_retention_strategy($sid);
            if ($strategy !== false && $strategy !== Retention::MIN_MAX) {
                $settings[] = array('instance_id'=>$instanceID,
                                  'policy_id'=>$policyID);
                $result = $this->BP->apply_gfs_retention($settings, $sid);
            } else {
                $result['error'] = 500;
                $result['message'] = "Policy association cannot be removed as the retention strategy of the system is not GFS.";
            }
        } else {
            $result['error'] = 500;
            $result['message'] = "Asset ID must be specified.";
        }
        return $result;
    }

    private function getClientName($cid, $sid)
    {
        $clientInfo = $this->BP->get_client_info($cid, $sid);
        $clientName = isset($clientInfo['name']) ? $clientInfo['name'] : null;

        return $clientName;
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

    function getManagedSystemsStrategy($systems){
        $ltr = array();
        $minmax = array();

        foreach ($systems as $id=>$name){
            $strategy = $this->BP->get_retention_strategy($id);
            if ($strategy !== false) {
                switch ($strategy) {
                    case "ltr":
                        $ltr[] = array('sid' => $id, 'name' => $name);
                        break;
                    case "MinMax":
                        $minmax[] = array('sid' => $id, 'name' => $name);
                        break;
                }
            }
        }
        return array($ltr, $minmax);
    }

    function buildManagedStrategy($arrayToProcess){
        $result = array();

        $sid = $arrayToProcess['sid'];
        $systemName = $arrayToProcess['name'];

        $processedArray = array(
            'sid' => $sid,
            'system_name' => $systemName
        );

        $data = array_merge($result, $processedArray);

        return $data;
    }
}
?>