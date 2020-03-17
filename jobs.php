<?php

class Jobs
{
//in progress
    private $BP;
    private $RDR;

    const HYPER_V = "Hyper-V";
    const VMWARE = "VMware";
    const XEN = "Xen";
    const AHV = "AHV";

    public function __construct($BP, $sid)
    {
        $this->BP = $BP;
        $this->RDR = new RDR($BP);
        $this->now = time();
		$this->sid = $sid;
	    $this->itemCount = 0;

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);

        require_once('jobs/jobs.functions.php');
        $this->jobsFunctions = new JobsFunctions($this->BP);
    }


    public function get($which, $filter, $jobID, $sid, $systems, $data = null)
    {

// TODO: first parameters, $which, can be a job category, e.g., "active", "history",

// The 2nd paramater, filter, gives a type of job, e.g., active/backup returns all active backup jobs
//														 active returns all active jobs (no filter
//														 active/restore returns all restore jobs.
//														 active/replication returns all active replication "jobs"
//														 (so logic from the operations APIs will need to be used)
//														 active/archive will return all archive jobs

// For a $which of "history", need to build the job information from bp_get_backup_status output.
        $allJobs = array();

        // True if we need to add the "data" array wrapper to the results and false if not.
        $add_data_wrapper = true;
        $special_job_type = explode(".", $which);
        $limits = array();

        switch ($which) {
            case "history" :
                require_once('jobs/jobs-history.php');
                $jobsHistory = new JobsHistory($this->BP, $sid, $this->functions);
                $allJobs = $jobsHistory->get_jobs_history($which, $filter, $sid, $limits);

                if (isset($_GET['format']) && ($_GET['format'] == 'csv')) {
                    $jobs['data'] = $allJobs;
                    $allJobs = $this->jobsFunctions->buildCSVExport($jobs);
                    $add_data_wrapper = false;
                }
                break;

            case "system":
                $filter = "history";
                require_once('audit-history.php');
                $audit = new AuditHistory($this->BP, $sid);
                $allJobs = $audit->get($filter);

                if (isset($_GET['format']) && ($_GET['format'] == 'csv')) {
                    $allJobs = $this->jobsFunctions->buildCSVExport($allJobs);
                }
                $add_data_wrapper = false;
                break;

            case "active" :
            case is_int($which) :
            case is_array($special_job_type) && count($special_job_type) > 1:
                require_once('jobs/jobs-active.php');
                $jobsActive = new JobsActive($this->BP, $sid, $this->functions);
                $allJobs = $jobsActive->get_active_jobs($which, $filter, $jobID, $sid, $systems);
                break;
            case "status-from-ID":
                require_once('jobs/jobs-status-from-ID.php');
                $jobsStatusFromID = new JobsStatusFromID($this->BP, $this->functions);
                $allJobs = $jobsStatusFromID->get_jobs_status_from_ID($data, $sid);

                $add_data_wrapper = false;
                break;
        }

        if ($add_data_wrapper) {
            $data = array('data' => $allJobs);
            if (count($limits)> 0) {
                $data['count'] = $limits['count'];
                $data['limited'] = $limits['limited'];
            }
            return $data;
        } else {
            return($allJobs);
        }

    }

    public function delete($which, $data){
        if ($which) {
            if (is_string($which)) {
                if (strpos($which, '.ir') !== false) {
                    $id = str_replace('.ir', '', $which);
                    $result = $this->deleteIRJobs((int)$id, $this->sid);
                } elseif (strpos($which, '.flr') !== false) {
                    $id = str_replace('.flr', "", $which);
                    if (isset($data['remote']) && $data['remote'] === true) {
                        $result = $this->deleteRemoteFLRJobs((int)$id, $this->sid);
                    } else {
                        $result = $this->deleteFLRJobs((int)$id, $this->sid);
                    }
                } elseif (strpos($which, '.rep') !== false) {
                    $id = str_replace('.rep', '', $which);
                    if (isset($data['remote']) && $data['remote'] === true) {
                        $result = $this->deleteRemoteReplicatedBackup($id, $this->sid);
                    } else {
                        $result = $this->deleteReplicatedBackup($id, $this->sid);
                    }
                } elseif (strpos($which, '.dca') !== false) {
                    $jobs = explode(',',$which);
                    foreach ($jobs as $job) {
                        $id = str_replace('.dca','', $job);
                        $result = $this->deleteCertificationJob((int)$id, $this->sid);
                    }
                } else {
                    $jobs = explode(',', $which);
                    foreach ($jobs as $job) {
                        if (isset($data['remote']) && $data['remote'] === true) {
                            $result = $this->deleteRemoteJob($job, $this->sid);
                        } else {
                            $result = $this->BP->cancel_job($job, $this->sid);
                        }
                    }
                }
            }
        }

        return $result;
    }

    function deleteReplicatedBackup($jobID, $sid)
    {
        $repJob = $this->BP->get_replication_active_job_info(array('system_id' => $sid));
        if ($repJob !== false) {
            require_once('replication.php');
            $replication = new Replication($this->BP);
            $queue = $replication->get("queue", null, null, $sid);

            if (array_key_exists('data', $queue)) {
                $data = $queue['data'];
                foreach ($data as $queuedJobs){
                    //are there queued jobs?
                    if(is_array($queuedJobs['inactive']) && !empty($queuedJobs['inactive'])) {
                        $inactive = $queuedJobs['inactive'];
                        foreach ($inactive as $index => $queueItem) {
                            if ($queueItem['queue_position'] == $jobID) {
                                $backupID = $queueItem['backup_id'];
                                $target = $queueItem['target'];
                                $filter[] = array(
                                    'backup_no' => $backupID,
                                    'target_name' => $target
                                );

                                $deleteFilter=array(
                                    "backup_targets"=>$filter,
                                    "action"=>"abort");
                                $result = $this->BP->delete_from_replication_queue($deleteFilter,$sid);
                            }
                        }
                    }

                    if(is_array($queuedJobs['active'])) {
                        $active = $queuedJobs['active'];
                        foreach ($active as $index => $queueItem) {
                            if ($queueItem['queue_position'] == $jobID) {
                                $backupID = $queueItem['backup_id'];
                                $target = $queueItem['target'];
                                $filter[] = array(
                                    'backup_no' => $backupID,
                                    'target_name' => $target
                                );

                                $deleteFilter=array(
                                    "backup_targets"=>$filter,
                                    "action"=>"abort");
                                $result = $this->BP->delete_from_replication_queue($deleteFilter,$sid);
                            }
                        }
                    }
                }
            }
        }

        return $result;

    }

    function deleteRemoteReplicatedBackup($id, $sid) {
        $request = "DELETE";
        $api = "/api/jobs/" . $id . ".rep/";
        $result = $this->functions->remoteRequest("", $request, $api, "", NULL, 1);
        return $result;
    }

    function deleteRemoteJob($id, $sid) {
        $request = "DELETE";
        $api = "/api/jobs/" . $id . "/";
        $result = $this->functions->remoteRequest("", $request, $api, "", NULL, 1);
        return $result;
    }

    public function suspend_resume($action, $which){
        if (is_string($which)) {
            $jobs = explode(',', $which);
            foreach($jobs as $job){
                if (strpos($job, '.dca') !== false) {
                    $result = $this->suspend_resume_certification($action,$job);
                } else {
                    switch ($action) {
                        case "suspend":
                            $result = $this->BP->suspend_job($job, $this->sid);
                            break;
                        case "resume":
                            $result = $this->BP->resume_job($job, $this->sid);
                            break;
                        default:
                            $result = "Bad action specified.  Jobs can only be suspended or resumed.";
                            break;
                    }
                }
            }
        }
        return $result;
    }

    private function suspend_resume_certification($action,$job){
        if ( ($this->sid !== false) && ($this->sid !== $this->BP->get_local_system_id()) ){
            $ret = $this->suspend_resume_certification_remote($action,$job);
        } else {
            $ret = $this->suspend_resume_certification_local($action,$job);
        }
        return $ret;
    }

    private function suspend_resume_certification_remote($action,$job){
        $remoteSysInfo = $this->BP->get_system_info($this->sid);
        $url = $remoteSysInfo['host'];
        $api = "/api/jobs/" . $action . "/" . $job . "/";

        $remoteRet = $this->functions->remoteRequestRDR($url, 'PUT', $api, "",null);

        $ret = array();
        if (isset($remoteRet['message'])){
            // pass on error message
            $ret = $remoteRet['message'];
        } else {
            $ret = $remoteRet['result'];
        }
        return $ret;
    }

    private function suspend_resume_certification_local($action,$job){
        $jobId = str_replace('.dca', "", $job);
        switch ($action) {
            case "suspend":
                $result = $this->RDR->pause_active_job((int)$job);
                break;
            case "resume":
                $result = $this->RDR->resume_active_job((int)$job);
                break;
            default:
                $result = "Bad action specified.  Jobs can only be suspended or resumed.";
                break;
        }
        $result = ($result['message'] === "OK")? null : $result['message'];
        return $result;
    }

    function deleteIRJobs($id, $sid)
    {
        $appInfo = $this->BP->get_appinst_info($id, $sid);
        if($appInfo !== false) {
            foreach($appInfo as $app) {
                $appName = $app['app_name'];

                //We pass in only one instanceID and are concerned only with that
                break;
            }
        }
        if($appName == 'VMware') {
            $vmwareStatus = $this->BP->get_vm_ir_status($sid);
            if($vmwareStatus !== false) {
                foreach($vmwareStatus as $status) {
                    if($status['id'] === $id) {
                        $audit = isset($status['audit']) ? $status['audit'] : 0;
                    }
                }
            }
            $force = $audit;
            $result = $this->BP->vmware_ir_destroy($id, $force, $sid);
        } elseif ($appName == Constants::APPLICATION_NAME_HYPER_V_2008_R2 || $appName == Constants::APPLICATION_NAME_HYPER_V_2012) {
            $hypervStatus = $this->BP->get_hyperv_ir_status($sid);
            if($hypervStatus !== false) {
                foreach($hypervStatus as $status) {
                    if($status['id'] === $id) {
                        $audit = isset($status['audit']) ? $status['audit'] : 0;
                    }
                }
            }
            $force = $audit;
            $result = $this->BP->hyperv_ir_destroy($id, $force, $sid);
        } else {
            //Qemu IR - Unitrends Appliance Image Level Instant Recovery (Block IR)
            $qemuStatus = $this->BP->get_qemu_ir_status($sid);
            if($qemuStatus !== false) {
                foreach($qemuStatus as $status) {
                    if($status['id'] === $id) {
                        $audit = isset($status['audit']) ? $status['audit'] : 0;
                    }
                }
            }
            $force = $audit;
            $result = $this->BP->qemu_ir_destroy($id, $force, $sid);
        }
        return $result;
    }

    function deleteFLRJobs($id, $sid){
        $instInfo = $this->functions->getInstanceNames($id, $sid);

        $result = false;
        if ($instInfo !== false) {
            if (isset($instInfo['app_type'])) {
                if ($instInfo['app_type'] === Jobs::HYPER_V or
                    $instInfo['app_type'] === Jobs::VMWARE or
                    $instInfo['app_type'] === Jobs::XEN or
                    $instInfo['app_type'] === Constants::APPLICATION_NAME_FILE_LEVEL or
                    $instInfo['app_type'] === Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL or
                    $instInfo['app_type'] === Jobs::AHV
                ) {
                    $result = $this->BP->destroy_disk_image($id, $sid);
                } else if ($instInfo['app_type'] == Constants::APPLICATION_TYPE_NAME_EXCHANGE) {
                    $result = $this->BP->backup_unmount($this->sid);
                } else {
                    $result = $this->BP->delete_application_share($id, $sid);
                }
            } else {
                // Could not determine the application type based on instance ID, which is possible if the host has been deleted.
                // Check each FLR type to see if we have one that should be removed.
                $flrFound = false;
                $images = $this->BP->get_disk_image_status($sid);
                if ($images !== false) {
                    foreach ($images as $image) {
                        if (isset($image['id']) && $image['id'] == $id) {
                            $result = $this->BP->destroy_disk_image($id, $sid);
                            $flrFound = true;
                            break;
                        }
                    }
                }
                if (!$flrFound) {
                    $mountStatus = $this->BP->backup_mount_status($sid);
                    if ($mountStatus !== false && isset($mountStatus['id']) && $mountStatus['id'] == $id) {
                        $result = $this->BP->backup_unmount($this->sid);
                        $flrFound = true;
                    }
                }
                if (!$flrFound) {
                    $shares = $this->BP->get_application_share_status($sid);
                    if ($shares !== false) {
                        foreach ($shares as $share) {
                            if (isset($share['appinst_id']) && $share['appinst_id'] == $id) {
                                $result = $this->BP->delete_application_share($id, $sid);
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }

    function deleteRemoteFLRJobs($id, $sid) {
        // if proxy mount exists, delete that first
        $mount_path = $this->BP->get_proxy_mount_path($id);
        if ($mount_path !== false) {
            $delete_proxy = $this->BP->destroy_proxy_mount($id);
            if ($delete_proxy === false) {
                $result['error'] = 500;
                $result['message'] = $this->BP->getError();
                return $result;
            }
        }
        $request = "DELETE";
        $api = "/api/jobs/" . $id. ".flr/";
        $result = $this->functions->remoteRequest("", $request, $api, "", NULL, 1);
        return $result;
    }

    function deleteCertificationJob($id, $sid) {
        if (($sid == false) || ($sid == $this->BP->get_local_system_id())){
            $result = $this->deleteCertificationJobLocal($id);
        } else {
            $result = $this->deleteCertificationJobRemote($id,$sid);
        }
        return $result;
    }

    private function deleteCertificationJobRemote($id,$sid){
        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        $api = "/api/jobs/" . $id . ".dca/";

        $remoteRet = $this->functions->remoteRequestRDR($url, 'DELETE', $api, "", null);

        if (isset($remoteRet['message'])){
            // pass on error message
            $ret = $remoteRet['message'];
        } else {
            //$ret = $remoteRet['result'];
            $ret = null;
        }
        return $ret;
    }

    private function deleteCertificationJobLocal($id){
        $result = $this->RDR->cancel_active_job($id);

        // If result is not OK, pass along error
        $result = null;
        if (isset($result['message']) && $result['message'] !== "OK"){
            $result = $result['message'];
        }
        return $result;
    }

    public function post($which, $data, $sid)
    {
        $result = array();
        $sid = ($sid !== false) ? $sid : $this->BP->get_local_system_id();

        switch($which[0]) {
            case 'compliance':
                switch($which[1]) {
                    case 'send_mail':
                        $result = $this->send_compliance_mail($data, $sid);
                        break;
                    default:
                        $result = "Invalid request.";
                        break;
                }
                break;
            default:
                $result = "Invalid request.";
                break;
        }
        return $result;
    }

    private function send_compliance_mail($data, $sid)
    {
        $result = array();
        $email = "";
        $job_name = "";

        if (isset($data['email'])) {
            $email = $data['email'];
        } else {
            $msg = "Email address is required.";
        }
        if (isset($data['job_name'])) {
            $job_name = $data['job_name'];
        } else {
            $msg = "Job name is required.";
        }

        if (isset($msg)) {
            $result['error'] = 500;
            $result['message'] = $msg;
            return $result;
        }

        $result = $this->RDR->get_compliance_mail($email, $job_name);

        return $result;
    }


} //end Jobs class


