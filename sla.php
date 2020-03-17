<?php
/*
 *
 * sle.php: SLAPolicy Class used to create and manage SLA Policies vs Jobs.
 * /
 */

class SLAPolicy
{
    private $BP;
    const NVP_TYPE_SLA = 'sla_policies';
    const POLICY = 'policy';
    const BACKUP_JOB_PREFIX = '_SLA:';

    const BACKUP_DISPLAY_NAME = 'Backup';
    const COLD_BACKUP_COPY_DISPLAY_NAME = 'Cold';
    const HOT_BACKUP_COPY_DISPLAY_NAME = 'Hot';

    const MAX_SLA_POLICY_NAME_LENGTH = 20;  // max archive job is 32, 12 characters used for prefix and suffix.

    public function __construct($BP)
    {
        $this->BP = $BP;
        $this->functions = new Functions($this->BP);
        $this->localID = $this->BP->get_local_system_id();
        global $Log;
        $this->Log = $Log;

        // Keep a tally of the jobs created
        $this->created = array();
    }


    /*
     * Gets the SLA policies for the given system.
     */
    public function get($which = -1, $sid, $systems) {
        if ($sid === false) {
            $sid = $this->localID;
        }
        $result = array();
        if (is_numeric($which) && $which == -1) {
            foreach($systems as $sid => $sname) {
                if ($sid !== $this->localID) {
                    // Until this is coded with real BPL APIs, only supports local system.
                    continue;
                }
                $policies = $this->bp_get_sla_policies($sid);
                if ($policies !== false) {
                    foreach ($policies as &$policy) {
                        $policy['rpo_formatted'] = $this->createRPOString($policy['rpo']);
                        $policy['calendar'] = array($this->createCalendar($policy['rpo']));
                        $policy['instances'] = $this->createInstances($policy, $sid);
                        if (isset($policy['app_id'])) {
                            $policy['application_name'] =  $this->functions->getApplicationTypeFromApplictionID($policy['app_id']);
                        }
                        unset($policy['rpo']);
                        unset($policy['instance_ids']);
                        $policy['sid'] = $sid;
                        $policy['system'] = $sname;
                    }
                    $result = array_merge($result, $policies);
                }
            }
        } else if ($which == 'assets') {
            $result = $this->bp_get_sla_policy_assets($sid);
            if ($result !== false) {
                // Add system_name to the returned elements.
                array_walk($result, function(&$value, $key, $data) { $value['system_name'] = $data; }, $systems[$sid]);
            }
        } else {
            $policy = $this->bp_get_sla_policy_info($which, $sid);
            if ($policy !== false) {
                $policy['rpo_formatted'] = $this->createRPOString($policy['rpo']);
                $policy['calendar'] = array($this->createCalendar($policy['rpo']));
                $policy['instances'] = $this->createInstances($policy, $sid);
                unset($policy['rpo']);
                unset($policy['instance_ids']);
                $policy['sid'] = $sid;
                $policy['system'] = $this->functions->getSystemNameFromID($sid);
                $result[] = $policy;
            } else {
                // No match.  Not an error.
                //$result = false;
                $noMatch = true;
            }
        }

        if (is_array($result)) {
            $result = array('data' => $result);
            if (isset($noMatch) && $noMatch === true) {
                // Add extra message.
                $result['message'] = 'Policy ' . $which . ' does not exist.';
            }
        }

        return $result;
    }

    private function createRPOString($rpo) {
        $formatted = "";
        if (is_array($rpo)) {
            if (isset($rpo['recurrence'])) {
                $minutes = $rpo['recurrence'];
                if ($minutes == 60) {
                    $formatted = "1 Hour";
                } else if ($minutes > 60 && ($minutes % 60 == 0)) {
                    $hours = $minutes / 60;
                    $formatted = $hours . ' Hours';
                } else {
                    $formatted = $minutes . ' Minutes';
                }
            }
        }
        return $formatted;
    }

    private function createCalendar($rpo) {
        $calendar = $rpo;
        $calendar['start_date'] = $this->functions->formatDate($rpo['start']);
        $calendar['start_time'] = date(Constants::TIME_FORMAT_12H, $rpo['start']);
        return $calendar;
    }

    /*
     * Given the SLA instance_ids, create a list of assets in the policy.
     */
    private function createInstances($policy, $sid) {
        $instances = array();
        if (isset($policy['instance_ids']) && count($policy['instance_ids']) > 0) {
            $id_string = implode(',', $policy['instance_ids']);
            $appInstInfo = $this->functions->getInstancesNames($id_string, $sid);
            if ($appInstInfo !== false) {
                $instances = $appInstInfo;
                // Add name to the returned elements.
                array_walk($instances, function(&$value) { $value['name'] = $value['asset_name']; });
            } else {
                $this->Log->writeError("cannot get instance information", true);
            }
            $schedule = $this->getExistingJob('backup', $policy, $policy, $sid);
            // If there is a schedule, use it to get items.
            $showClients = false;
            if ($schedule !== false) {
                if (isset($schedule['clients'])) {
                    $scheduleItems = $schedule['clients'];
                    $showClients = true;
                } else {
                    $scheduleItems = $schedule['instances'];
                }
                foreach ($scheduleItems as &$new) {
                    $match = false;
                    foreach ($instances as $instance) {
                        if ($showClients && $new['id'] == $instance['client_id']) {
                            $new['client_id'] = $instance['client_id'];
                            $new['id'] = $instance['id'];
                            $new['asset_name'] = $instance['asset_name'];
                            $match = true;
                            break;
                        }
                    }
                    if ($showClients && !$match) {
                        // If it were an added client, update the information properly.
                        $info = $this->BP->get_client_info($new['id']);
                        if ($info !== false) {
                            $new['client_id'] = $new['id'];
                            $new['id'] = $info['file_level_instance_id'];
                            $new['asset_name'] = $new['name'];
                        }
                    }
                }
                $instances = $scheduleItems;
            }
        }
        return $instances;
    }

    /*
     * Given an array of role info, saves it for the provided username.  If no username
     * is provided, uses the member user (defined during object construction).
     */
    public function add($policyInfo, $sid = null) {
        if ($this->policyNameTooLong($policyInfo['name'])) {
            return $this->createPolicyNameTooLongError($policyInfo['name']);
        }
        if (!$this->policyNameIsUnique($policyInfo, $sid)) {
            return $this->createPolicyNameNotUniqueError($policyInfo['name']);
        }
        $metAssetConstraints = $this->instancesAreUnique($policyInfo, $sid);
        if ($metAssetConstraints !== true) {
            return $this->createAssetAlreadyinPolicyError($metAssetConstraints, $policyInfo);
        }
        if ($policyInfo['archive'] === true && $policyInfo['archive_encryption'] === true) {
            $encryptionEnabled = $this->isEncryptionEnabled($sid);
            if (!$encryptionEnabled) {
                return $this->createEncryptionMustBeEnabledError($policyInfo['name']);
            }
        }

        $addInfo = $this->buildPolicyObject($policyInfo);
        $result = $this->createAssociatedInformation($policyInfo, $addInfo, $sid);
        $associatedInformationResult = $result;

        // result is either true or an array on success.
        if ($result === true || (is_array($result) && !isset($result['error']))) {
            $addInfo = array_merge($addInfo, $this->getJobIDs());
            $result = $this->bp_save_sla_policy_info($addInfo, $sid);
        } else if ($result === false) {
            $result = array('error' => 500,
                'message' => 'Error creating SLA policy. ' . $this->BP->getError());
        } else if (is_array($result) && isset($result['error']) && isset($result['message'])) {
            $result['message'] = 'Error creating jobs for SLA policy: ' . $result['message'];
        }

        if ($result === true || (is_array($result) && !isset($result['error']))) {
            $result = $this->buildSuccess($associatedInformationResult);
        }
        return $result;
    }

    /*
     * If the jobs cannot all be created, remove the ones that were created during
     * the save or update function, effectively rolling back the state of the system.
     *
     * This function is given the list of jobIDs that have been created.
     */
    private function rollback($jobIds, $sid) {
        global $schedules;
        $result = true;
        foreach ($jobIds as $jobId) {
            $this->Log->writeVariable("In: removeCreated job: " . $jobId);
            $result = $schedules->delete($jobId, $sid);
            if ($result === false) {
                $this->Log->writeVariable("Error deleting created job " . $jobId);
                break;
            }
        }
        return $result;
    }

    private function buildSuccess($priorResult) {
        $result = array();
        if (isset($this->created['backup_job_id'])) {
            $result['backup_job'] = $this->created['backup_job'];
        }
        if (isset($this->created['replication_job_id'])) {
            $result['replication_job'] = $this->created['replication_job'];
        }
        if (isset($this->created['archive_job_id'])) {
            $result['archive_job'] = $this->created['archive_job'];
        }
        if (isset($priorResult['replication_job_error'])) {
            $result['replication_job_error'] = $priorResult['replication_job_error'];
        }
        if (isset($priorResult['archive_job_error'])) {
            $result['archive_job_error'] = $priorResult['archive_job_error'];
        }
        if (count($result) == 0) {
            $result = true;
        } else {
            $result = array('result' => $result);
        }
        return $result;
    }

    /*
     * Create and return an error array that will propagate the message to the user that this asset
     * is already part of another policy.
     */
    private function createAssetAlreadyinPolicyError($metAssetConstraints, $policyInfo) {
        return array('error' => 500,
            'message' =>
                'Assets can be protected with only one SLA policy, and ' . $metAssetConstraints['duplicate_asset'] .
                ' is already covered by policy ' . $metAssetConstraints['duplicate_policy'] . '.  ' .
                'Please remove asset ' . $metAssetConstraints['duplicate_asset'] . ' from policy "' . $metAssetConstraints['duplicate_policy'] . '"' .
                ' if you want to assocate it with the policy "' . $policyInfo['name'] . '".');
    }

    private function createPolicyNameNotUniqueError($policyName) {
        return array('error' => 500,
            'message' =>
                'Error: an SLA Policy by the name ' . $policyName . ' already exists.');
    }

    private function createPolicyNameTooLongError($policyName) {
        return array('error' => 500,
            'message' =>
                'Error: the Policy name ' . $policyName . ' is too long; the maximum name length is ' .
                self::MAX_SLA_POLICY_NAME_LENGTH . ' characters.');
    }

    private function createEncryptionMustBeEnabledError($policyName) {
        return array('error' => 500,
            'message' =>
                'Error: the policy ' . $policyName . ' will encrypt backups that are copied to the Cold Backup Copy Target ' .
                'but encryption is not enabled on the appliance. Enable encryption ' .
                'using the Configure page, Edit Appliance dialog\'s Advanced tab.');
    }

    /*
 * Given the proposed policy name, returns true if the name is unique and false if not.
 */
    private function policyNameTooLong($policyName) {
        $tooLong = false;
        if (strlen($policyName) > self::MAX_SLA_POLICY_NAME_LENGTH) {
            $tooLong = true;
        }
        return $tooLong;
    }

    /*
     * Given the proposed policy name, returns true if the name is unique and false if not.
     */
    private function policyNameIsUnique($policyInfo, $sid) {
        $unique = true;
        $policyName = $policyInfo['name'];
        $policyId = isset($policyInfo['id']) ? $policyInfo['id'] : null;
        $policies = $this->bp_get_sla_policies($sid);
        if ($policies !== false) {
            foreach ($policies as $policy) {
                // See if it maches and the ID is not my ID (if a modify).
                if ($policyName === $policy['name'] && $policyId !== $policy['id']) {
                    $unique = false;
                    break;
                }
            }
        }
        return $unique;
    }

    /*
     * Validate that the instances are not in any other policy.
     */
    private function instancesAreUnique($policyInfo, $sid, $policyId = null) {
        $unique = true;
        $policyName = $policyInfo['name'];
        if (isset($policyInfo['instances'])) {
            // Get the instances for the policy.
            $instances = $policyInfo['instances'];
            $filter = array('system_id' => $sid);
            // Get the list of assets/instances that are already in policies.
            $currentAssets = $this->bp_get_sla_policy_assets($filter);
            foreach ($instances as $instance) {
                foreach ($currentAssets as $asset) {
                    if ($policyId != null && $asset['policy_id'] == $policyId) {
                        // If the policy exists (has an ID), and if the asset is already in it, okay.
                        continue;
                    }
                    // same asset ID, but in different policies, flag it.
                    if ($instance['id'] == $asset['id'] && $policyName !== $asset['policy_name']) {
                        $unique = array('duplicate_asset' => $asset['name'], 'duplicate_policy' => $asset['policy_name']);
                        break;
                    }
                }
            }
        }
        return $unique;
    }

    /*
     * This function takes a list of instances and returns 2 lists.  Those instances that are not in a policy, and those that are.
     * The instances passed in are expected to have arrays of instance objects, with an "instance_id" property.
     * The result is an array of in_policy = array of assets with 'name', 'id', 'policy_name'
     * And array of not_in_policy = an array of $instances like the form passed into the function.
     * If 'in_policy' is empty, then all of the assets are not members of an SLA Policy.
     */
    public function instancesInPolicy($instances, $sid) {
        $instancePolicyInfo = array('in_policy' => array(), 'not_in_policy' => array());
        if ($sid === $this->localID) {
            // policies only support local systems.
            $filter = array('system_id' => $sid);
            $assets = $this->bp_get_sla_policy_assets($filter);
            foreach ($instances as $instance) {
                $policyInstance = null;
                for ($i = 0; $i < count($assets); $i++) {
                    $asset = $assets[$i];
                    // same asset ID, but in different policies, flag it as in a policy
                    // remove this asset from the array (subsequent searches)
                    if ($instance['instance_id'] == $asset['id']) {
                        $policyInstance = array('id' => $asset['id'], 'name' => $asset['name'], 'policy' => $asset['policy_name']);
                        array_splice($assets, $i, 1);
                        break;
                    }
                }
                if ($policyInstance !== null) {
                    $instancePolicyInfo['in_policy'][] = $policyInstance;
                } else {
                    $instancePolicyInfo['not_in_policy'][] = $instance;
                }
            }
        }
        //global $Log;
        //$Log->writeVariable("INPOLICY");
        //$Log->writeVariable($instancePolicyInfo);
        return $instancePolicyInfo;
    }

    private function isEncryptionEnabled($sid) {
        $info = $this->BP->get_crypt_info($sid);
        $enabled = false;
        if ($info !== false) {
            if ($info['active'] && $info['enabled']) {
                $enabled = true;
            }
        }
        return $enabled;
    }

    /*
     * Build the schedules for this policy.
     * Build the retention settings for this policy.
     */
    private function createAssociatedInformation($data, &$policy, $sid) {

        $result = $this->createdAssociatedBackupJob($data, $policy, $sid);
        $warningArray = array();

        if ($result === true || (is_array($result) && !isset($result['error']))) {
            if ($policy['replication'] === 'true') {
                $hotResult = $this->createAssociatedHotBackupCopyJob($data, $policy, $sid);
            } else {
                $hotResult = $this->deleteAssociatedHotBackupCopyJob($data, $policy, $sid);
            }
            if ($hotResult === false) {
                $warningArray['replication_job_error'] = $this->BP->getError();
                $policy['replication'] = 'false';
            }
        }

        if ($result === true || (is_array($result) && !isset($result['error']))) {
            if ($policy['archive'] === 'true') {
                $coldResult = $this->createAssociatedColdBackupCopyJob($data, $policy, $sid);
            } else {
                $coldResult = $this->deleteAssociatedColdBackupCopyJob($data, $policy, $sid);
            }

            if ($coldResult === false) {
                $warningArray['archive_job_error'] = $this->BP->getError();
                $policy['archive'] = 'false';
            }
        }

        if ($result === true || (is_array($result) && !isset($result['error']))) {
            $retentionResult = $this->createAssociatedRetention($data, $policy, $sid);
            if ($retentionResult === false) {
                $warningArray['retention_error'] = $this->BP->getError();
            }
        }

        if (count($warningArray) > 0) {
            if (is_array($result)) {
                $result = array_merge($result, $warningArray);
            } else {
                $result = $warningArray;
            }
        }

        return $result;
    }

    /*
     * Given the policy information, either create or update a backup job.
     */
    private function createdAssociatedBackupJob($data, $policy, $sid) {
        global $schedules;
        $backupJob = $policy;
        $calendarArray = $data['calendar'];

        // SLA Policy Jobs only support one strategy.
        $firstCalendar = $calendarArray[0];
        // Backup each day of the week
        $firstCalendar['run_on'] = array(0, 1, 2, 3, 4, 5, 6);
        // Backup each week
        $firstCalendar['schedule_run'] = 0;
        // Backup Incremental Forever
        $firstCalendar['backup_type'] = "Incremental";
        // The calendar describes the start, end_hour and recurrence period (RPO).
        $backupJob['calendar'] = array($firstCalendar);
        // Include these jobs in the reports
        $backupJob['email_report'] = true;
        $backupJob['failure_report'] = true;
        $backupJob['type'] = 'backup';
        $this->created['backup_job'] = $backupJob['name'] = $this->createPolicyJobName($backupJob['type'], $policy['name']);
        $backupJob['description'] =  $this->createAssociatedJobDescription($policy['name']);

        $schedule = $this->getExistingJob('backup', $backupJob, $data, $sid);

        $result = true;
        if ($backupJob['app_id'] == Constants::APPLICATION_ID_FILE_LEVEL) {
            $clients = array();
            $instances = $policy['instance_ids'];
            $appInstArray = $this->BP->get_appinst_info($instances, $sid);
            if ($appInstArray !== false) {
                foreach ($appInstArray as $instance_id => $appinst) {
                    $clients[] = $this->createClient($instance_id, $appinst, $data['instances']);
                }
            }
            $backupJob['clients'] = $clients;
            unset($backupJob['instances']);
            if ($schedule !== false) {
                // don't overwrite the id with the policy id
                unset($backupJob['id']);
                // overwrite the clients with the olicy clients.
                unset($schedule['clients']);
                $schedule = array_merge($schedule, $backupJob);
            }
        } else if ($backupJob['app_id'] == Constants::APPLICATION_ID_VMWARE ||
                 $backupJob['app_id'] == Constants::APPLICATION_ID_AHV ||
                ($backupJob['app_id'] >= Constants::APPLICATION_ID_HYPER_V_2008_R2 && $backupJob['app_id'] <= Constants::APPLICATION_ID_HYPER_V_2016)) {
            $instances = $policy['instance_ids'];
            $appInstArray = $this->BP->get_appinst_info($instances, $sid);
            $jobInstances = array();
            if ($appInstArray !== false) {
                foreach ($appInstArray as $instance_id => $appinst) {
                    $jobInstances[] = $this->createInstance($instance_id, $appinst, $data['instances']);
                }
            }
            $backupJob['instances'] = $jobInstances;
            if ($schedule !== false) {
                // don't overwrite the id with the policy id
                unset($backupJob['id']);
                // overwrite the instances with the policy instances.
                unset($schedule['instances']);
                $schedule = array_merge($schedule, $backupJob);
            }
        } else {
            $result = array('error' => 500,
                'message' => 'This application type is not supported by SLA Policies');
        }
        if (!is_array($result)) {
            if ($schedule !== false) {
                // modify
                $result = $schedules->put(array($schedule['id']), $schedule, $sid);
            } else {
                // create - get id.
                $result = $schedules->save_schedule(-1, $backupJob, $sid);
                if (is_array($result) && !isset($result['error'])) {
                    $this->created['backup_job_id'] = $this->getIDFromJoborderCreation($result);
                }
            }
        }
        return $result;
    }

    /*
     * When a joborder is successfuly created, the API returns a result array with a defined format and an 'id' property.
     * Returns that id.
     */
    private function getIDFromJoborderCreation($result) {
        $resultArray = $result['result'];
        return $resultArray[0]['id'];
    }

    /*
     * Deletes any associated backup job.
     */
    private function deleteAssociatedBackupJob($data, $policy, $sid) {
        return $this->deleteAssociatedJob('backup', $policy, $sid);
    }

    /*
     * Deletes any associated hot backup copy job
     */
    private function deleteAssociatedHotBackupCopyJob($data, $policy, $sid) {
        return $this->deleteAssociatedJob('replication', $policy, $sid);
    }

    /*
     * Deletes any associated hot backup copy job
     */
    private function deleteAssociatedColdBackupCopyJob($data, $policy, $sid) {
        return $this->deleteAssociatedJob('archive', $policy, $sid);
    }

    /*
     * Given the type of job and the policy, gets the existing job associated with the policy and deletes it.
     */
    private function deleteAssociatedJob($jobType, $policy, $sid) {
        $result = true;
        // See if the job exists and needs update, or should be created.
        global $schedules;
        $this->Log->writeVariable("In: deleteAssociatedJob: " . $jobType . ", " . $policy['name'] . ", " . $sid);
        $schedule = $this->getExistingJob($jobType, $policy, $policy, $sid);
        if ($schedule !== false) {
            $this->Log->writeVariable("Existing job found; deleting id " . $schedule['id']);
            $result = $schedules->delete($schedule['id'], $sid, true);
        }
        return $result;
    }

    /*
     * Given the policy information, either create or update a hot backup copy job.
     */
    private function createAssociatedHotBackupCopyJob($data, $policy, $sid) {

        $this->Log->writeVariable("In createAssociatedHotBackupCopyJob:, policy");
        $this->Log->writeVariable($policy);
        // See if there is a replication target.
        global $replication;
        $targets = $replication->get('targets', array(), array(), $this->localID);
        if ($targets !== false && count($targets) > 0) {
            $targets = $targets['targets'];
            if (count($targets) > 0) {
                $target_id = $targets[0]['target_id'];
            }
        }

        if (isset($target_id)) {
            $jobInfo = array();
            $jobInfo['type'] = 'replication';
            $this->created['replication_job'] = $jobInfo['name'] = $this->createPolicyJobName($jobInfo['type'], $policy['name']);
            $jobInfo['description'] = $this->createAssociatedJobDescription($policy['name']);
            $jobInfo['target_id'] = $target_id;
            // For a replication job, the instances need to be passed in as an array of ints.
            $jobInfo['instances'] = array_map('intval', explode(',', $policy['instance_ids']));

            // See if the job exists and needs update, or should be created.
            global $schedules;
            if (isset($policy['id'])) {
                $jobInfo['id'] = $policy['id'];
            }
            global $Log;
            $Log->writeVariable("REPLICATION POLICY");
            $Log->writeVariable($policy);
            $schedule = $this->getExistingJob('replication', $jobInfo, $data, $sid);
            if ($schedule !== false) {
                // The only thing to replace in a replication job are the instances nad name.
                $schedule['instances'] = $jobInfo['instances'];
                $schedule['name'] = $jobInfo['name'];
                $result = $schedules->put(array($schedule['id']), $schedule, $sid);
            } else {
                // not found, unset the ID for job creation
                if (isset($jobInfo['id'])) {
                    unset($jobInfo['id']);
                }
                $result = $schedules->save_schedule(-1, $jobInfo, $sid);
                if (is_array($result) && !isset($result['error'])) {
                    $this->created['replication_job_id'] = $this->getIDFromJoborderCreation($result);
                }
            }
        } else {
            $result = array('error' => 500,
                            'message' => 'Cannot determine identification of hot backup copy target.');
        }
        return $result;
    }

    /*
     * Given the policy information, either create or update a cold backup copy job.
     */
    private function createAssociatedColdBackupCopyJob($data, $policy, $sid) {

        // See if there is a replication target.
        global $appliance;
        $systems = array($sid => 'dummy');
        $targets = $appliance->get_storage(null, array('usage' => 'archive'), $this->localID, $systems);
        if ($targets !== false && count($targets) > 0) {
            $targets = $targets['storage'];
            if (count($targets) > 0) {
                $targetName = $targets[0]['name'];
            }
        }

        if (isset($targetName)) {

            $calendarArray = $data['calendar'];

            // SLA Policy Jobs only support one strategy.
            $firstCalendar = $calendarArray[0];
            // Archive each day of the week.
            $firstCalendar['run_on'] = array(0, 1, 2, 3, 4, 5, 6);
            // Archive each week
            $firstCalendar['schedule_run'] = 0;
            // Archive type
            $firstCalendar['backup_type'] = "Archive";
            // Remove recurrence as only run daily
            if (isset($firstCalendar['recurrence'])) {
                unset($firstCalendar['recurrence']);
            }

            $jobInfo = array();
            // The calendar describes the start, end_hour and recurrence period (RPO).
            $jobInfo['calendar'] = array($firstCalendar);
            // Include these jobs in the reports
            $jobInfo['email_report'] = true;
            // set purge option
            $jobInfo['purge'] = true;
            // Encrypt backups
            $jobInfo['encrypt'] = isset($data['archive_encryption']) ? $data['archive_encryption'] : false;
            $jobInfo['type'] = 'archive';
            $this->created['archive_job'] = $jobInfo['name'] = $this->createPolicyJobName($jobInfo['type'], $policy['name']);
            $jobInfo['description'] = $this->createAssociatedJobDescription($policy['name']);
            $jobInfo['storage'] = $targetName;
            // For an archive job, the instances need to be passed in as an array of ints.
            $jobInfo['instances'] = array_map('intval', explode(',', $policy['instance_ids']));
            // For incremental forever backup strategy, only need to archive Fulls, Incrementals, and Differentials.
            $jobInfo['types'] = array('Full', 'Differential', 'Incremental', 'Selective', 'Bare Metal', 'Transaction');
            // Get retention if set.
            if (isset($data['archive_retention_days'])) {
                $jobInfo['retention_days'] = $data['archive_retention_days'];
            }

            // See if the job exists and needs update, or should be created.
            global $schedules;
            if (isset($policy['id'])) {
                $jobInfo['id'] = $policy['id'];
            }
            $schedule = $this->getExistingJob('archive', $jobInfo, $data, $sid);
            if ($schedule !== false) {
                // don't overwrite the id with the policy id
                unset($jobInfo['id']);
                $schedule = array_merge($schedule, $jobInfo);
                $result = $schedules->put(array($schedule['id']), $schedule, $sid);
            } else {
                $result = $schedules->save_schedule(-1, $jobInfo, $sid);
                if (is_array($result) && !isset($result['error'])) {
                    $this->created['archive_job_id'] = $this->getIDFromJoborderCreation($result);
                }
            }
        } else {
            $result = array('error' => 500,
                'message' => 'Cannot determine identification of cold backup copy target.');
        }
        return $result;
    }

    /*
     * Given the policy information about the assets, save the retention settings.
     */
    private function createAssociatedRetention($data, $policy, $sid) {
        $result = true;
        if (isset($data['min_max_policy'])) {
            $instanceArray = $data['instances'];
            $retentionSettings = array();
            $minMax = $data['min_max_policy'];
            foreach ($instanceArray as $instance) {
                $retention['instance_id'] = $instance['id'];
                $retention['retention_min'] = $minMax['retention_min'];
                $retention['retention_max'] = $minMax['retention_max'];
                $retention['legal_hold'] = $minMax['legal_hold'];
                $retentionSettings[] = $retention;
            }
            if (count($retentionSettings) > 0) {
                $result = $this->BP->save_retention_settings($retentionSettings, $sid);
            }
        }
        return $result;
    }

    /*
     * Given a set of instances that are file-level, build and return a corresponding client structure.
     * This is required for saving a backup joborder.
     */
    private function createClient($instance_id, $appinst, $instances) {
        $client = array('id' => $appinst['client_id'], 'name' => $appinst['client_name']);
        foreach ($instances as $instance) {
            if ($instance['id'] == $instance_id) {
                if (isset($instance['incl_list'])) {
                    $client['incl_list'] = $instance['incl_list'];
                }
                if (isset($instance['excl_list'])) {
                    $client['excl_list'] = $instance['excl_list'];
                }
                if (isset($instance['metanames'])) {
                    $client['metanames'] = $instance['metanames'];
                }
                if (isset($instance['before_command'])) {
                    $client['before_command'] = $instance['before_command'];
                }
                if (isset($instance['after_command'])) {
                    $client['after_command'] = $instance['after_command'];
                }
            }
        }

        return $client;
    }

    /*
     * Given a set of instances, creates an instance structure that can be saved as a joborder.
     */
    private function createInstance($instance_id, $appinst, $instances) {
        $thisInstance = array('id' => $instance_id, 'client_name' => $appinst['client_name']);
        foreach ($instances as $instance) {
            if ($instance['id'] == $instance_id) {
                if (isset($instance['incl_list'])) {
                    $thisInstance['incl_list'] = $instance['incl_list'];
                }
                if (isset($instance['excl_list'])) {
                    $thisInstance['excl_list'] = $instance['excl_list'];
                }
                if (isset($instance['metanames'])) {
                    $thisInstance['metanames'] = $instance['metanames'];
                }
                if (isset($instance['before_command'])) {
                    $thisInstance['before_command'] = $instance['before_command'];
                }
                if (isset($instance['after_command'])) {
                    $thisInstance['after_command'] = $instance['after_command'];
                }
            }
        }

        return $thisInstance;
    }

    /*
     * Given a job type and a policy name, create the encoded name for the associated jobs.
     */
    private function createPolicyJobName($jobType, $policyName) {
        $jobName = $policyName;
        $displayType = $this->getJobNameForJobType($jobType);
        if (strstr($jobName, self::BACKUP_JOB_PREFIX) === false) {
            $jobName = self::BACKUP_JOB_PREFIX . $jobName . ' (' . $displayType . ')';
        }
        return $jobName;
    }

    /*
     * Create a job description for jobs associated with the policy.
     */
    private function createAssociatedJobDescription($policyName) {
        return 'Job for SLA Policy "' . $policyName . '"; update by modifying the policy.';
    }

    private function getExistingJob($jobType, $backupJob, $policy, $sid) {
        global $schedules;

        global $Log;
        $result = false;
        $systems = array($sid => 'dummy');

        $existingJobID = $this->getPolicyJobID($jobType, $policy);
        //$Log->writeVariable("IN GET EXISTING JOB, type " . $jobType . " and existing ID " . $existingJobID);

        // If we have an existing job ID, see if we can find the job
        if ($existingJobID !== "") {
            $detailedData = $schedules->get($existingJobID, $systems);
            $detailedScheduleList = $detailedData['data'];
            if (count($detailedScheduleList) > 0) {
                $Log->writeVariable("Found Existing " . $jobType . " Job by ID " . $existingJobID);
                $detailedSchedule = $detailedScheduleList[0];
                $result = $detailedSchedule;
            }
        }

        // Fall back to looking for the job by name.
        if ($result == false) {
            $scheduleList = $schedules->get($jobType, $systems);
            $scheduleList = $scheduleList['data'];
            foreach ($scheduleList as $schedule) {
                if ($this->isForThisPolicy($jobType, $schedule, $backupJob)) {
                    $Log->writeVariable("Found Existing " . $jobType . " Job by Name " . $schedule['name']);
                    $detailedData = $schedules->get($schedule['id'], $systems);
                    $detailedScheduleList = $detailedData['data'];
                    if (count($detailedScheduleList) > 0) {
                        $detailedSchedule = $detailedScheduleList[0];
                        $result = $detailedSchedule;
                    }
                    break;
                }
            }
        }

        return $result;
    }

    /*
     * Given a job type of "backup", "replication", or "archive", returns the associated job ID as a string.
     * Returns an empty string if the job cannot be found.
     */
    private function getPolicyJobID($jobType, $policy) {
        $id = "";
        if (isset($policy['backup_job_id']) && $jobType == 'backup') {
            $id = $policy['backup_job_id'];
        } else if (isset($policy['replication_job_id']) && $jobType == 'replication') {
            $id = $policy['replication_job_id'];
        } else if (isset($policy['archive_job_id']) && $jobType == 'archive') {
            $id = $policy['archive_job_id'];
        }
        return $id;
    }

    /*
     * Given a job type (backup, archive, replication) and a job ID string (e.g., 210e.1)
     * return a policy ID if this job is referenced by an SLA policy and false if not.
     */
    public function isJobForAPolicy($jobType, $jobID) {
        $found = false;
        //global $Log;
        $a = explode('.', $jobID);
        $sid = $this->localID;
        // future see if policy exists on a managed system.
        if (count($a) > 1) {
            $sid = $a[1];
            if ($sid != $this->localID) {
                return $found;
            }
        }
        if ($this->functions->showSLAPolicies($sid)) {
            $jobKey = 'backup_job_id';
            if ($jobType === 'archive') {
                $jobKey = 'archive_job_id';
            } else if ($jobType === 'replication') {
                $jobKey = 'replication_job_id';
            }
            //$Log->writeVariable("looking for job " . $jobID . " in policies, key " . $jobKey);
            $policies = $this->bp_get_sla_policies($sid);
            if ($policies !== false) {
                foreach ($policies as $policy) {
                    if (isset($policy[$jobKey])) {
                        if ($policy[$jobKey] == $jobID) {
                            $found = array('id' => $policy['id'], 'name' => $policy['name']);
                            break;
                        }
                    }
                }
            }
        }
        return $found;
    }

    /*
     * Given the policy, delete the associated backup and backup copy jobs.
     * Returns true on success and an error structure and message on failure.
     */
    private function deleteAssociatedInformation($id, $sid) {
        $policy = $this->bp_get_sla_policy_info($id, $sid);

        $data = array();
        $result = $this->deleteAssociatedBackupJob($data, $policy, $sid);
        if ($result === true) {
            $result = $this->deleteAssociatedHotBackupCopyJob($data, $policy, $sid);
            if ($result === true) {
                $result = $this->deleteAssociatedColdBackupCopyJob($data, $policy, $sid);
            }
        }

        return $result;
    }

    /*
     * Checks the name of the job to see if it matches the policy.
     */
    private function isForThisPolicy($jobType, $schedule, $policy) {
        $policyName = $policy['name'];
        if (isset($policy['id'])) {
            // if modifying an existing job, need the old name.
            $existing = $this->bp_get_sla_policy_info($policy['id'], $this->localID);
            $policyName = $existing['name'];
        }
        $scheduleName = $schedule['name'];
        $constructedName = $this->createPolicyJobName($jobType, $policyName);
        return ($scheduleName == $constructedName);
    }

    /*
     * Converts the job type into a displayable name.
     */
    private function getJobNameForJobType($jobType) {
        $name = '';
        switch ($jobType) {
            case 'backup':
                $name = self::BACKUP_DISPLAY_NAME;
                break;
            case 'archive':
                $name = self::COLD_BACKUP_COPY_DISPLAY_NAME;
                break;
            case 'replication':
                $name = self::HOT_BACKUP_COPY_DISPLAY_NAME;
                break;
        }
        return $name;
    }

    /*
     * Given the policy info, creates sub-arrays of policy information.
     */
    private function buildPolicyObject($policyInfo) {
        $info = array();
        isset($policyInfo['name']) ? $info['name'] = $policyInfo['name'] : false;
        isset($policyInfo['description']) ? $info['description'] = $policyInfo['description'] : false;
        isset($policyInfo['app_id']) ? $info['app_id'] = $policyInfo['app_id'] : false;
        // Convert boolean to string for NVP
        $info['replication'] = isset($policyInfo['replication']) && $policyInfo['replication'] === true ? "true" : "false";
        $info['archive'] = isset($policyInfo['archive']) && $policyInfo['archive'] === true ? "true" : "false";
        $info['archive_retention_days'] = isset($policyInfo['archive_retention_days']) ? $policyInfo['archive_retention_days'] : 0;
        $info['archive_encryption'] = isset($policyInfo['archive_encryption']) && $policyInfo['archive_encryption'] === true ? "true" : "false";
        // Convert arrays to series of values for NVP
        if (isset($policyInfo['instances'])) {
            $info['instance_ids'] = $this->instancesToInstance_ids($policyInfo['instances']);
        }
        if (isset($policyInfo['min_max_policy'])) {
            $minMax = $policyInfo['min_max_policy'];
            isset($minMax['retention_min']) ? $info['retention_min'] = $minMax['retention_min'] : false;
            isset($minMax['retention_max']) ? $info['retention_max'] = $minMax['retention_max'] : false;
            isset($minMax['legal_hold']) ? $info['legal_hold'] = $minMax['legal_hold'] : false;
        }
        if (isset($policyInfo['calendar'])) {
            $calendarArray = $policyInfo['calendar'];
            if (count($calendarArray) > 0) {
                $calendar = $calendarArray[0];
                isset($calendar['recurrence']) ? $info['recurrence'] = $calendar['recurrence'] : false;
                isset($calendar['begin_hour']) ? $info['begin_hour'] = $calendar['begin_hour'] : false;
                isset($calendar['end_hour']) ? $info['end_hour'] = $calendar['end_hour'] : false;
                if (isset($calendar['start_date']) && isset($calendar['start_time'])) {
                    $timestamp = $this->functions->dateToTimestamp($calendar['start_date'] . " " . $calendar['start_time']);
                    if ($timestamp !== false) {
                        $info['start'] = $timestamp;
                    } else {
                        ; // error.  But schedule would have failed to save, too.
                    }
                }
            }
        }
        return $info;
    }

    /*
     * Converts an array of instance objects ot a string of comma-separated IDs.
     */
    private function instancesToInstance_ids($instances) {
        $ids = array();
        foreach ($instances as $instance) {
            $ids[] = $instance['id'];
        }
        return implode(',', $ids);
    }

    /*
     * Given an array of role info, saves it for the provided username.  If no username
     * is provided, uses the member user (defined during object construction).
     *
     *  policy info = must have 'id' for update
     *  name
     *  description
     *  instance_ids = array of instance ids
     *  min_max_policy = object with retention_min, retention_max, legal_hold
     *  start = start date and time
     *  end_hour = end hour
     *  recurrence in minutes
     */
    public function update($id, $policyInfo, $sid = null) {
        if ($this->policyNameTooLong($policyInfo['name'])) {
            return $this->createPolicyNameTooLongError($policyInfo['name']);
        }
        if (!$this->policyNameIsUnique($policyInfo, $sid)) {
            return $this->createPolicyNameNotUniqueError($policyInfo['name']);
        }
        $metAssetConstraints = $this->instancesAreUnique($policyInfo, $sid, $id);
        if ($metAssetConstraints !== true) {
            return $this->createAssetAlreadyinPolicyError($metAssetConstraints, $policyInfo);
        }
        if ($policyInfo['archive'] === true && $policyInfo['archive_encryption'] === true) {
            $encryptionEnabled = $this->isEncryptionEnabled($sid);
            if (!$encryptionEnabled) {
                return $this->createEncryptionMustBeEnabledError($policyInfo['name']);
            }
        }

        $updateInfo = $this->buildPolicyObject($policyInfo);
        $updateInfo['id'] = $id;
        $result = $this->createAssociatedInformation($policyInfo, $updateInfo, $sid);
        $associatedInformationResult = $result;

        // result is either true or an array.
        if ($result === true || (is_array($result) && !isset($result['error']))) {
            $updateInfo = array_merge($updateInfo, $this->getJobIDs());
            $result = $this->bp_save_sla_policy_info($updateInfo, $sid);
        } else if ($result === false) {
            $result = array('error' => 500,
                'message' => 'Error updating SLA policy. ' . $this->BP->getError());
        } else if (is_array($result) && isset($result['error']) && isset($result['message'])) {
            $result['message'] = 'Error updating jobs for SLA policy: ' . $result['message'];
        }

        if ($result === true || (is_array($result) && !isset($result['error']))) {
            $result = $this->buildSuccess($associatedInformationResult);
        }

        return $result;
    }

    /*
     * Gets the created job IDs and returns them in an object.
     */
    private function getJobIDs() {
        $created = array();
        if (isset($this->created['backup_job_id'])) {
            $created['backup_job_id'] = $this->created['backup_job_id'];
        }
        if (isset($this->created['archive_job_id'])) {
            $created['archive_job_id'] = $this->created['archive_job_id'];
        }
        if (isset($this->created['replication_job_id'])) {
            $created['replication_job_id'] = $this->created['replication_job_id'];
        }
        return $created;
    }

    /*
     * Deletes the specified role information for the user.
     */
    public function delete($id, $data, $sid = null) {
        $result = true;
        $this->Log->writeVariable("in delete, data");
        $this->Log->writeVariable($data);
        if (isset($data['purge_all']) && $data['purge_all'] == true) {
            $result = $this->deleteAssociatedInformation($id, $sid);
        }
        if ($result === true) {
            $result = $this->bp_delete_sla_policy($id, $sid);         
        }
        return $result;
    }

    /*
     * bp_get_sla_policies()
     *
     * Description: Returns an array of usernames who have role based access, or false on failure.
     *
     */
    private function bp_get_sla_policies($sid = null)
    {
        $policies = array();
        $policyIds = $this->BP->get_nvp_names(self::NVP_TYPE_SLA);
        if ($policyIds !== false) {
            foreach ($policyIds as $policyId) {
                $policy = $this->createPolicyObject($policyId, $sid);
                if ($policy !== false) {
                    $policies[] = $policy;
                }
            }
        }
        return $policies;
    }

    /*
     * Creates an rpo structure for the policy.   Once GFS is in place, more information about
     * how many backups to keep over alternate periods can be saved and returned.
     */
    private function createRPO(&$policy) {
        // Convert to rpo array, for case where we support multiple
        // retention points in the future (GFS).
        $rpo = array();
        if (isset($policy['recurrence'])) {
            $rpo['recurrence'] = $policy['recurrence'];
            unset($policy['recurrence']);
        }
        if (isset($policy['start'])) {
            $rpo['start'] = $policy['start'];
            unset($policy['start']);
        }
        if (isset($policy['begin_hour'])) {
            $rpo['begin_hour'] = $policy['begin_hour'];
            unset($policy['begin_hour']);
        }
        if (isset($policy['end_hour'])) {
            $rpo['end_hour'] = $policy['end_hour'];
            unset($policy['end_hour']);
        }
        /* for the future with GFS
        if (isset($policy['rpo_days'])) {
            $rpo['days'] = $policy['rpo_days'];
            unset($policy['rpo_days']);
        }
        if (isset($policy['rpo_months'])) {
            $rpo['months'] = $policy['rpo_months'];
            unset($policy['rpo_months']);
        }
        if (isset($policy['rpo_years'])) {
            $rpo['years'] = $policy['rpo_years'];
            unset($policy['rpo_years']);
        } */
        return $rpo;
    }

    private function createMinMaxRetention(&$policy) {
        // Convert to retention array, for case where we support multiple
        // retention points in the future (GFS).
        $retention = array(
            'retention_min' => 0,
            'retention_max' => 0,
            'legal_hold' => 0);
        if (isset($policy['retention_min'])) {
            $retention['retention_min'] = $policy['retention_min'];
            unset($policy['retention_min']);
        }
        if (isset($policy['retention_max'])) {
            $retention['retention_max'] = $policy['retention_max'];
            unset($policy['retention_max']);
        }
        if (isset($policy['legal_hold'])) {
            $retention['legal_hold'] = $policy['legal_hold'];
            unset($policy['legal_hold']);
        }
        return $retention;
    }

    /*
     * bp_get_sla_policy_info($id)
     *
     * Description: Given a policy ID, returns the information about the policy
     *
     * Policy information returned includes
     *
     * name - policy name
     * rpo -  rpo array
     * retention - retention array
     * instances - list of instances associated with this policy
     *
     *
     */
    private function bp_get_sla_policy_info($id, $sid = null) {

        $policy = array();
        $found = false;
        $policyIds = $this->BP->get_nvp_names(self::NVP_TYPE_SLA);
        if ($policyIds !== false) {
            foreach ($policyIds as $policyId) {
                if ($id == $policyId) {
                    $policy = $this->createPolicyObject($id, $sid);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $policy = false;
            }
        }
        return $policy;
    }

    /*
     * Creates a policy object for the policy, given it's ID.
     *
     * Note: As the NVP table is local-only, $sid is not used.  Once mvoed to BPL, $sid will be used.
     */
    private function createPolicyObject($id, $sid) {
        $policy = $this->BP->get_nvp_list(self::NVP_TYPE_SLA, $id);
        if ($policy !== false) {
            foreach ($policy as $property => $value) {
                if ($property == 'instance_ids') {
                    $integerIDs = array_map('intval', array_filter(explode(',', $value), 'is_numeric'));
                    $policy[$property] = $integerIDs;
                } else if ($property == 'name' || $property == 'description') {
                    $policy[$property] = $value;
                } else if (is_numeric($value)) {
                    $policy[$property] = (int)$value;
                } else if ($value === 'true') {
                    $policy[$property] = true;
                } else if ($value === 'false') {
                    $policy[$property] = false;
                }
            }
            $policy['rpo'] = $this->createRPO($policy);
            $policy['min_max_policy'] = $this->createMinMaxRetention($policy);
            $policy['retention'] = $this->buildMinMaxPolicyName($policy['min_max_policy']);

            // Ensure replication and archiving are returned.  If not previously set, they are false.
            if (!isset($policy['replication'])) {
                $policy['replication'] = false;
            }
            if (!isset($policy['archive'])) {
                $policy['archive'] = false;
            }
            if (!isset($policy['archive_retention_days'])) {
                $policy['archive_retention_days'] = 0;
            }
            if (!isset($policy['archive_encryption'])) {
                $policy['archive_encryption'] = false;
            }

            $policy['id'] = $id;
            $policy['type'] = self::POLICY;
        }
        return $policy;
    }

    /*
     * Given the retention setting, creates and returns a string describing it.
     */
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

    /*
     * bp_save_sla_policy_info(array $policyInfo, [optional] in $system_id)
     *
     * Description: Given policy information, update it (if an 'id' is provided), or add to it, if not.
     *
     * Returns true on success or false on failure.
     *
     *  name
     *  description
     *  instance_ids = array of instance ids
     *  min_max_policy = object with retention_min, retention_max, legal_hold
     *  start = start date and time
     *  end_hour = end hour
     *  recurrence in minutes
     */
    private function bp_save_sla_policy_info($policyInfo, $sid = null) {
        $nvpArray = $policyInfo;
        if (isset($policyInfo['id'])) {
            $nvpName = $policyInfo['id'];
            unset($nvpArray['id']);
            $result = $this->BP->save_nvp_list(self::NVP_TYPE_SLA, $nvpName, $nvpArray);
        } else {
            // If no Id, creating a new policy.
            $policyId = $this->getNewPolicyId();
            if ($policyId !== false) {
                $nvpName = $policyId;
                $result = $this->BP->save_nvp_list(self::NVP_TYPE_SLA, $nvpName, $nvpArray);
            } else {
                $result = array('error' => 500, 'message' => 'error creating new SLA policy id');
            }
        }
        return $result;
    }

    /*
     * This function gets the "next" SLA Policy ID, given that the nvp_name field is really the policy ID.
     */
    private function getNewPolicyid() {
        $newId = false;
        $policyIds = $this->BP->get_nvp_names(self::NVP_TYPE_SLA);
        if ($policyIds !== false) {
            // Get the largest policy if there are policies.  Otherwise, the largest is 0.
            $highest = count($policyIds) > 0 ? max($policyIds) : 0;
            $newId = $highest + 1;
        }
        return $newId;
    }

    /*
     * bp_delete_sla_policy(int $id, [optional] $system_id)
     *
     * Description: Delete a policy by id.
     *
     * Returns true on success or false on failure.
     *
     */
    private function bp_delete_sla_policy($id, $sid = null) {
        $nvpName = $id;
        return $this->BP->delete_nvp_list(self::NVP_TYPE_SLA, $nvpName);
    }

    /*
     * bp_get_sla_policy_assets(array $filter)
     *
     * Description: Returns an indexed array of associative arrays of asset information, which includes
     *              its ID, name, and its associated policy ID and name, if it exists.
     *
     *  Filter = policy_only = true/false = return assets with policies or all seets
     *              system_id
     *              instance_id
     *
     * Returns true on success or false on failure.
     *
     */
    private function bp_get_sla_policy_assets($filter) {
        $policyOnly = true;
        $sid = $this->localID;
        if (is_array($filter)) {
            if (isset($filter['policy_only'])) {
                $policyOnly = $filter['policy_only'];
            } else {
                $policyOnly = false;
            }
        }
        $allInstances = array();
        $policies = $this->bp_get_sla_policies();
        if ($policies !== false) {
            foreach ($policies as $policy) {
                $instances = $this->createInstances($policy, $sid);
                $policyName = $policy['name'];
                $policyId = $policy['id'];
                foreach ($instances as &$instance) {
                    $instance['policy_name'] = $policyName;
                    $instance['policy_id'] = $policyId;
                }
                $allInstances = array_merge($allInstances, $instances);
            }
        }
        return $allInstances;
    }
}

?>
