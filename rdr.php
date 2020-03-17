<?php

// Default ip/port RDR daemon listens on
define("DEFAULT_RDR_ENDPOINT", "localhost:8085");

// Class to handle RDR rest calls
class RDR 
{
    private $BP;
    // Define RDR endpoint or default to DEFAULT_RDR_ENDPOINT
    function __construct($BP,$rdrHostAddress=DEFAULT_RDR_ENDPOINT)
    {
        $this->BP = $BP;
        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);
        $this->endpoint = $rdrHostAddress;
    }


    public function get_rdr_supported($which, $data, $sid, $systems)
    {
        $returnArray = array();

        global $Log;
        $Log->writeVariable("sid for get_rdr_supported is $sid");

        $returnArray = array('supported' => $this->BP->rdr_supported($sid));
        return($returnArray);
    }

    // Gets list of jobs from RDR
    function get_jobs() {
        $ret = $this->restGet("/api/jobs");
        return $ret;
    }

    // Get specific job from RDR
    function get_job($jobId) {
        $ret = $this->restGet("/api/jobs/" . $jobId);
        return $ret;
    }

    // Get list of schedules from RDR
    function get_schedule_info() {
        $ret = $this->restGet("/api/jobs/scheduledtasks");
        return $ret;
    }

    // Get list of history for all jobs from RDR
    function get_schedule_histories() {
        $ret = $this->restGet("/api/sessions");
        return $ret;
    }

    // Get specific history
    function get_schedule_history($jobId) {
        $ret = $this->restGet("/api/sessions/" . $jobId);
        return $ret;
    }

    // Get list of history for specific job from RDR
    function get_all_schedule_history($scheduleId){
        $ret = $this->restGet("/api/sessions/job/" . $scheduleId);
        return $ret;
    }

    // Get latest history for specific job from RDR
    function get_last_schedule_history($scheduleId) {
        $ret = $this->restGet("/api/sessions/job/" . $scheduleId . "/last");
        return $ret;
    }

    // Get list of service profiles from RDR
    function get_service_profiles() {
        $ret = $this->restGet("/api/profile");
        return $ret;
    }

    // Get specific service profile from RDR
    function get_service_profile($profileId) {
        $ret = $this->restGet("/api/profile/" . $profileId);
        return $ret;
    }

    // Post new job to RDR
    function new_job($data) {
        $ret = $this->restPost("/api/jobs",$data);
        return $ret;
    }

    // Post new service profile to RDR
    function new_service_profile($data) {
        $ret = $this->restPost("/api/profile/", $data);
        return $ret;
    }

    // Edit job in RDR
    //  RDR expects the same minimally filled out job that
    //  it does for a new job
    function edit_job($data) {
        $ret = $this->restPut("/api/jobs",$data);
        return $ret;
    }

    // Edit service profile in RDR
    function edit_service_profile($data) {
        $ret = $this->restPut("/api/profile/", $data);
        return $ret;
    }

    // Delete job from RDR
    function delete_job($jobId) {
        $ret = $this->restDelete("/api/jobs/" . $jobId);
        return $ret;
    }

    // Delete service profile from RDR
    function delete_service_profile($profileId) {
        $ret = $this->restDelete("/api/profile/" . $profileId);
        return $ret;
    }

    // Enable job in RDR
    function enable_job($jobId) {
        $ret = $this->restGet("/api/jobs/" . $jobId . "/enable");
        return $ret;
    }

    // Disable job in RDR
    function disable_job($jobId) {
        $ret = $this->restGet("/api/jobs/" . $jobId . "/disable");
        return $ret;
    }

    // Invoke failover job in RDR
    function failover_job($jobId,$mode,$data) {
        $ret = $this->restPost("/api/jobs/" . $jobId . "/failover/" . $mode, $data);
        return $ret;
    }

    // Commit failover job
    function commit_job($jobId) {
        $ret = $this->restGet("/api/jobs/" . $jobId . "/commit");
        return $ret;
    }

    // Discard failover job
    function discard_job($jobId) {
        $ret = $this->restGet("/api/jobs/" . $jobId . "/discard");
        return $ret;
    }

    // test job
    function test_job($jobId) {
        $ret = $this->restGet("/api/jobs/" . $jobId . "/test");
        return $ret;
    }

    // get list of active jobs from RDR
    function get_active_jobs() {
        $ret = $this->restGet("/api/jobs/active");
        return $ret;
    }

    // pause active job
    function pause_active_job($jobId) {
        $ret = $this->restGet("/api/jobs/active/" . $jobId . "/pause");
        return $ret;
    }

    // resume active job
    function resume_active_job($jobId) {
        $ret = $this->restGet("/api/jobs/active/" . $jobId . "/resume");
        return $ret;
    }

    // cancel active job 
    function cancel_active_job($jobId) {
        $ret = $this->restGet("/api/jobs/active/" . $jobId . "/cancel");
        return $ret;
    }

    // get status of active job from RDR
    function monitor_active_job($jobId) {
        $ret = $this->restGet("/api/jobs/active/" . $jobId . "/monitor");
        return $ret;
    }
   
    // get stored tests from RDR
    function get_tests($guest_os) {
        $ret = $this->restGet("/api/apptests/os/". $guest_os);
        return $ret;
    } 

    // get recovery assurance stats for the last $days days
    function get_teststats($days) {
        $ret = $this->restGet("/api/sessions/teststats/" . $days);
        return $ret;
    }

    // get compliance stats
    function get_compliance_summary() {
        $ret = $this->restGet("/api/jobs/compliance/summary");
        return $ret;
    }

    function convert_date_format_for_rdr($date) {
        $split_date = explode("/", $date);
        $date_arr = array($split_date[2], $split_date[0], $split_date[1]);
        $date_with_dashes = implode('-', $date_arr);
        return $date_with_dashes;
    }

    function get_report_info_local($api) {
        global $Log;
        $Log->writeVariable("Local call");
        $report_info = $this->restGet($api);
        if (!is_array($report_info) || isset($report_info['error'])){
            return $report_info;
        }

        // Format RDR datetime to satori datetime
        foreach($report_info as $lineNum => $line){
            $report_info[$lineNum]['start_time'] = $this->functions->formatDateTime(strtotime($line['start_time']));
            $report_info[$lineNum]['end_time'] = $this->functions->formatDateTime(strtotime($line['end_time']));
        }

        $number_of_elements = count($report_info);
        $Log->writeVariable("Number of entries in the report = $number_of_elements");
        $Log->writeVariable("Returning report");
        $ret['count'] = $number_of_elements;
        $ret['total'] = $number_of_elements;
        $ret['data'] = $report_info;

        return $ret;
    }

    function get_report_info_remote($api, $sid) {
        global $Log;
        $Log->writeVariable("Remote call");
        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        // We just want the remote system's reports not the reports for all
        //  the systems it manages
        $api .= "/?sid=1";
        $report_info = $this->functions->remoteRequestRDR($url, 'GET', $api, "", null);
        return $report_info;
    }


    // Get Recovery Assurance report
    function get_rdr_report($data, $sid) {

        global $Log;
        $Log->writeVariable("Calling RDR REST");

        $validDate = 1;
        $err = false;
        $locApi = "/api/sessions/";
        $remApi = "/api/reports/recovery_assurance";

        $start_time = false;
        if (array_key_exists('start_date', $data)) {
           $start_time = strtotime($data['start_date']);
        } else {
            $validDate = 0;
        }
        $end_time = false;
        if (array_key_exists('end_date', $data)) {
            $end_time = strtotime($data['end_date']);
        }

        if ($validDate) {
            $isValidDateRange = false;
            $isValidDateRange = $this->functions->isValidDateRange($start_time, $end_time, false);

            if($isValidDateRange) {
                $Log->writeVariable("Valid date range");
                # The date format passed from UI is "mm/dd/yyyy"
                # RDR needs it in the format "yyyy-mm-dd"
                # So, convert both the dates

                $start_date_with_dashes = $this->convert_date_format_for_rdr($data['start_date']);
                $locApi .= "?start_date=" .$start_date_with_dashes;
                $remApi .= "?start_date=" .$data['start_date'];
                if ($end_time){
                    $end_date_with_dashes = $this->convert_date_format_for_rdr($data['end_date']);
                    $locApi .= "&end_date=" .$end_date_with_dashes;
                    $remApi .= "&end_date=" . $data['end_date'];
                }
            } else {
                $err = true;
                $ret['error'] = 500;
                $ret['message'] = "Invalid date range provided";
            }
        } else {
            # No dates passed. Return all information for all jobs that have ran until now
            # NOTE that this call can be time consuming
            $Log->writeVariable("No dates or invalid date combination was passed. Retrieving all information.");
        }

        if ($err === false) {
            $localSystemID = $this->BP->get_local_system_id();
            $localSystemRDRSupport = $this->BP->rdr_supported($localSystemID);      //If local system supports RDR then get managed systems recovery assurance report.
            if(!$sid || $sid === null){                                                  //Get information for all managed appliances
                $systems = $this->functions->selectSystems( $sid, false );

                $reports = array(
                    'data'=>array(),
                    'alreadyRunning'=>0,
                    'failures'=>0,
                    'successes'=>0,
                    'warnings'=>0,
                    'count'=>0,
                    'total'=>0);
                if($localSystemRDRSupport === true){
                    foreach ( $systems as $systemID => $systemName ){
                        $rdrSupported = $this->BP->rdr_supported($systemID);
                        if ($systemID == $localSystemID) {
                            $report = $this->get_report_info_local($locApi);
                            if(is_array($report)){
                                $report = $this->countSuccesses($report, $systemName);
                            }
                        }
                        else{
                            if($rdrSupported === true){
                                $report = $this->get_report_info_remote($remApi, $systemID);
                            }
                        }

                        if (!is_array($report) || isset($report['error'])){
                            continue;
                        }
                        $reports['data'] = array_merge($reports['data'],$report['data']);
                        $reports['alreadyRunning'] += isset($report['alreadyRunning']) ? $report['alreadyRunning'] :0;
                        $reports['failures'] += isset($report['failures']) ? $report['failures'] :0;
                        $reports['successes'] += isset($report['successes']) ? $report['successes'] :0;
                        $reports['warnings'] += isset($report['warnings']) ? $report['warnings'] :0;
                        $reports['count'] += isset($report['count']) ? $report['count'] :0;
                        $reports['total'] += isset($report['total']) ? $report['total'] :0;
                    }
                    $ret = $reports;
                }
            }
            else if ($sid == $localSystemID && $localSystemRDRSupport === true) {
                $ret = $this->get_report_info_local($locApi);
                if (is_array($ret)){
                    $systemName = $this->functions->getSystemNameFromID($sid);
                    $ret = $this->countSuccesses($ret, $systemName);
                }
            } else if($this->BP->rdr_supported($sid) === true){
                $ret = $this->get_report_info_remote($remApi, $sid);
            }
            else{
                $ret['error'] = 500;
                $ret['message'] = "Recovery Assurance report is not supported for this system";
            }
        }

        return $ret;

    }

    function countSuccesses(&$ret, $systemName){
        if(!$ret || $ret !== null){
            $ret['failures'] =  $ret['successes'] =  $ret['warnings'] = $ret['alreadyRunning'] =  0;
            foreach ( $ret['data'] as &$data ){
                $data['system_name'] = $systemName;
                if($data['result'] === 'Failed' ){
                    $ret['failures']++;
                }
                else if( $data['result'] === 'Cancelled' ){
                    $ret['failures']++;
                    $data['result'] = 'Failed';             //change result of "Cancelled" DCA job to "Failed"
                }
                elseif($data['result'] === 'Successful'){
                    $ret['successes']++;
                }
                elseif($data['result'] === 'Warning'){
                    $ret['warnings']++;
                }
                elseif($data['result'] === 'AlreadyRunning'){
                    $ret['alreadyRunning']++;
                }
            }
        }
        return$ret;
    }

    function get_detailed_info_local($api) {
        global $Log;
        $Log->writeVariable("Local call to get detailed job information.");
        $job_report = $this->restGet($api);

        if (empty($job_report)) {
            // RDR usually sends an error back. But, just in case no error is sent,
            // send back an appropriate error message
            $job_report['error'] = 500;
            $job_report['message'] = "Invalid ID passed";
        } else if(array_key_exists('detail',$job_report)) {
            # The 'detail' element is a JSON string and needs to be
            # converted to a JSON object
            $detail_arr = json_decode($job_report['detail'], true);
            $job_report['detail'] = $detail_arr;
        }

        return $job_report;
    }

    function get_detailed_info_remote($api, $sid) {
        global $Log;
        $Log->writeVariable("Remote call to get detailed job information.");
        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        $job_report = $this->functions->remoteRequestRDR($url, 'GET', $api, "", null);
        return $job_report;
    }

    function get_detailed_job_report($data, $sid) {

        global $Log;
        $Log->writeVariable("Entering get_detailed_job_report");

        $execution_id = $data['id'];

        if (($sid == null) || ($sid == $this->BP->get_local_system_id())) {
            $api = "/api/sessions/" . $execution_id;
            $ret = $this->get_detailed_info_local($api);
        } else {
	    $api = "/api/reports/recovery_assurance/?id=" . $execution_id;
            $ret = $this->get_detailed_info_remote($api, $sid);
        }

        return $ret;

    }

    function get_compliance_report_local($api) {

        global $Log;
        $Log->writeVariable("Local call to get compliance report");

        $report_info = $this->restGet($api);
        if (!is_array($report_info) || isset($report_info['error'])){
            return $report_info;
        }
        $number_of_elements = count($report_info);
        $Log->writeVariable("Number of entries in the report = $number_of_elements");
        $Log->writeVariable("Returning compliance report");

        $sid = $this->BP->get_local_system_id();
        $sysName = $this->functions->getSystemNameFromId($sid);
        foreach($report_info as $key => $value){
            $report_info[$key]['system_name'] = $sysName;
        }

        $ret['count'] = $number_of_elements;
        $ret['total'] = $number_of_elements;
        $ret['data'] = $report_info;

        return $ret;

    }

    function get_compliance_report_remote($api, $sid) {

        global $Log;
        $Log->writeVariable("Remote call to get compliance report");

        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        // We just want the remote system's reports not the reports for all
        //  the systems it manages
        $api .= "/?sid=1";
        $job_report = $this->functions->remoteRequestRDR($url, 'GET', $api, "", null);

        return $job_report;

    }

    // Get Compliance report
    // This gets the compliance report for ALL jobs
    function get_compliance_report($data, $sid) {

        global $Log;
        $Log->writeVariable("Entering get_compliance_report");
        if ($sid == null) {
            $systems = array_keys($this->BP->get_system_list());
        } else if (is_array($sid)) {
            $systems = $sid;
        } else if (is_numeric($sid)){
            $systems = array($sid);
        } else {
            return "SID must be numeric, array, or null";
        }

        $localSystemID = $this->BP->get_local_system_id();
        $localSystemRDRSupport = $this->BP->rdr_supported($localSystemID);      //If local system supports RDR then get managed systems compliance report.

        $reports = array('data'=>array());
        if($localSystemRDRSupport === true){
            foreach($systems as $sys){
                $rdrSupported = $this->BP->rdr_supported($sys);
                $report = array('data'=>array());
                if ($sys == $localSystemID) {
                    $api = "/api/jobs/compliance";
                    $report = $this->get_compliance_report_local($api);
                } else{
                    if($sid !== null && !$rdrSupported){
                        $report['error'] = 500;
                        $report['message'] = "Compliance report is not supported for this system";
                    }
                    else if($rdrSupported === true){
                        $api = "/api/reports/compliance/";
                        $report = $this->get_compliance_report_remote($api, $sys);
                    }
                }
                // Return the remote error if getting single system
                // When getting multiple systems, don't return the error
                //  this way, we can still return the reports for systems that
                //  are not in error
                if (is_numeric($sid) && (!is_array($report) || isset($report['error']))){
                    return $report;
                } else if (is_array($report) && !isset($report['error'])){
                    $reports['data'] = array_merge($reports['data'],$report['data']);
                    $count = isset($report['count']) ? $report['count'] : 0;
                    $oldcount = isset($reports['count']) ? $reports['count'] : 0;
                    $reports['count'] = $oldcount + $count;
                }
            }
        }
        $ret = $reports;

        $ret = $this->calculateRpoRto($ret, $sid);
        if($ret['count'] > 0){
            $ret['rpo'] = round($ret['rpo']/$ret['count']*100, 2);;
            $ret['rto'] = round($ret['rto']/$ret['count']*100, 2);
            $ret['compliance'] = round($ret['compliance']/$ret['count']*100,2);
        }
        else{
            $ret['rpo'] = $ret['rto'] = $ret['compliance'] = 0;
        }
        return $ret;

    }

    function calculateRpoRto(&$ret, $sid){
        if(!$ret || $ret !== null){
            $ret['rpo'] =  $ret['rto'] =  $ret['compliance'] = 0;
            foreach ( $ret['data'] as &$data ){
                if($data['rpo_status'] === 'Successful'){
                    $ret['rpo']++;
                }
                if($data['rto_status'] === 'Successful'){
                    $ret['rto']++;
                }
                if(isset($data['compliance_status']) && $data['compliance_status'] === 'Successful'){
                    $ret['compliance']++;
                }
            }
        }
        return $ret;
    }

    function get_detailed_compliance_info_local($api) {

        global $Log;
        $Log->writeVariable("Local call to get detailed compliance information.");

        $detailed_compliance_report = $this->restGet($api);

        // RDR usually sends an error back. But, just in case no error is sent,
        // send back an appropriate error message
        if (empty($detailed_compliance_report)) {
            $detailed_compliance_report['error'] = 500;
            $detailed_compliance_report['message'] = "Invalid ID passed for compliance report";
        }

        return $detailed_compliance_report;

    }

    function get_detailed_compliance_info_remote($api, $sid) {

        global $Log;
        $Log->writeVariable("Remote call to get detailed job information.");

        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        $job_report = $this->functions->remoteRequestRDR($url, 'GET', $api, "", null);

        return $job_report;

    }

    // Get detailed compliance information for a single job
    function get_detailed_compliance_report($data, $sid) {

        global $Log;
        $Log->writeVariable("Entering get_detailed_compliance_report");

        // The 'id' is the parameter is from when the complete compliance report is retrieved
        $id = $data['id'];

        if (($sid == null) || ($sid == $this->BP->get_local_system_id())) {
            $api = "/api/jobs/compliance/" . $id;
            $ret = $this->get_detailed_compliance_info_local($api);
        } else {
            $api = "/api/reports/compliance/?id=" . $id;
            $ret = $this->get_detailed_compliance_info_remote($api, $sid);
        }

        return $ret;

    }

    function get_compliance_mail($email, $job_name)
    {
        $job_name = rawurlencode($job_name);
        $ret = $this->restGet("/api/jobs/compliance/send_mail/" . $email . "/" . $job_name);
        return $ret;
    }

    // Internal function to set options for and execute a restful GET via cURL
    private function restGet($api) {
        global $Log;
        $reqPriv = constants::PRIV_MONITOR;
        $curPriv = $this->functions->getCurrentPrivileges();
        if ($curPriv >= $reqPriv){
            $url = $this->endpoint . $api;
            $functionName = "restGet";
            $Log->enterFunction("RDR: " . $functionName,$api);

            // Configure curl options for GET call
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url );
            curl_setopt($ch, CURLOPT_FAILONERROR, 0);
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
            // Don't include header in output
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_VERBOSE, 0);
            // Execute REST call
            $ret = curl_exec($ch);
            if(json_decode($ret,true) !== null){
                $ret = json_decode($ret,true);
            }
            if($ret == "") $ret = array();

            // Catch and Log errors
            $retCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            // Error response
            if($retCode >= 400){
                // RDR doesn't give a message on 404, so make one.
                if($retCode == 404) {
                    $errStr = "Not Found";
                } else {
                    // Gather all errors into one string
                    $errStr = $this->recursiveBuildMessage($ret);
                }
            }
            // No response
            if($ret === false) {
                $errStr = "No response from RDR";
            }
            if(!empty($errStr)){
                $Log->writeError("RDR: " . $errStr,false,false,true);
                $Log->writeVariable("Attempted to reach endpoint: " . $url);
                $ret = array('error' => 500,
                             'message' => $errStr);
            } else {
                // If no error, encapsulate string into array,
                // since the webservices layer treats strings
                // as errors
                if(is_string($ret)){
                    $ret = array('message' => $ret);
                }
            }
            $Log->exitFunction("RDR: " . $functionName,$ret);
        } else {
            $Log->writeError("Privilege lvl ($curPriv) below required privilege ($reqPriv)",false,false,true);
            $ret = array();
            $ret['error'] = 500;
            $ret['message'] = "Insufficient Privileges";
        }

        return $ret;
    }

    // Set options for POST call via cURL and execute
    private function restPost($api, $data) {
        global $Log;
        $reqPriv = constants::PRIV_MANAGE;
        $curPriv = $this->functions->getCurrentPrivileges();
        if ($curPriv >= $reqPriv){
            $url = $this->endpoint . $api;
            $jsonData = json_encode($data);
            $functionName = "restPost";
            $Log->enterFunction("RDR: " . $functionName,$api,$jsonData);

            // configure curl options for POST call
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FAILONERROR, 0);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            // Don't include header in output
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_VERBOSE, 0);
            // Execute REST call
            $ret = curl_exec($ch);
            if(json_decode($ret,true) !== null){
                $ret = json_decode($ret,true);
            }

            // Catch and Log errors
            $retCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            // Error response
            if($retCode >= 400){
                // RDR doesn't give a message on 404, so make one.
                if($retCode == 404) {
                    $errStr = "Not Found";
                } else {
                    // Gather all errors into one string
                    $errStr = $this->recursiveBuildMessage($ret);
                }
            }
            // No response
            if($ret === false){
                $errStr = "No response from RDR";
            }
            if(!empty($errStr)){
                $Log->writeError("RDR: " . $errStr,false,false,true);
                $Log->writeVariable("Attempted to POST to: " . $url);
                $ret = array('error' => 500,
                             'message' => $errStr);
            } else {
                // If no error, encapsulate string into array,
                // since the webservices layer treats strings
                // as errors
                if(is_string($ret)){
                    $ret = array('message' => $ret);
                }
            }
            $Log->exitFunction("RDR: " . $functionName,$ret);
        } else {
            $Log->writeError("Privilege lvl ($curPriv) below required privilege ($reqPriv)",false,false,true);
            $ret = array();
            $ret['error'] = 500;
            $ret['message'] = "Insufficient Privileges";
        }

        return $ret;
    }

    // Set options for and execute a restful PUT via cURL
    private function restPut($api, $data) {
        global $Log;
        $reqPriv = constants::PRIV_MANAGE;
        $curPriv = $this->functions->getCurrentPrivileges();
        if ($curPriv >= $reqPriv){
            $url = $this->endpoint . $api;
            $jsonData = json_encode($data);
            $functionName = "restPut";
            $Log->enterFunction("RDR: " . $functionName,$api,$jsonData);

            // configure curl options for POST call
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url );
            curl_setopt($ch, CURLOPT_FAILONERROR, 0);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            // Don't include header in output
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_VERBOSE, 0);
            // Execute REST call
            $ret = curl_exec($ch);
            if(json_decode($ret,true) !== null){
                $ret = json_decode($ret,true);
            }

            // Catch and Log errors
            $retCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            // Error response
            if($retCode >= 400){
                // RDR doesn't give a message on 404, so make one.
                if($retCode == 404) {
                    $errStr = "Not Found";
                } else {
                    // Gather all errors into one string
                    $errStr = $this->recursiveBuildMessage($ret);
                }
            }
            // No response
            if($ret === false){
                $errStr = "No response from RDR";
            }
            if(!empty($errStr)){
                $Log->writeError("RDR: " . $errStr,false,false,true);
                $Log->writeVariable("Attempted to PUT to: " . $url);
                $ret = array('error' => 500,
                             'message' => $errStr);
            }

            // Don't return a message on successful PUT.
            //  RDR returns a single string on successful PUT.
            //  The webservices layer interprets a string as an error.
            if($retCode == 200) {
                $ret = null;
            }
            $Log->exitFunction("RDR: " . $functionName, $ret);
        } else {
            $Log->writeError("Privilege lvl ($curPriv) below required privilege ($reqPriv)",false,false,true);
            $ret = array();
            $ret['error'] = 500;
            $ret['message'] = "Insufficient Privileges";
        }

        return $ret;
    }

    // Set options for and execute a restful DELETE via cURL
    private function restDelete($api) {
        global $Log;
        $reqPriv = constants::PRIV_MANAGE;
        $curPriv = $this->functions->getCurrentPrivileges();
        if ($curPriv >= $reqPriv){
            $url = $this->endpoint . $api;
            $functionName = "restDelete";
            $Log->enterFunction("RDR: " . $functionName,$api);

            // Configure curl options for DELETE call
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url );
            curl_setopt($ch, CURLOPT_FAILONERROR, 0);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            // Don't include header in output
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_VERBOSE, 0);
            // Execute REST call
            $ret = curl_exec($ch);
            if(json_decode($ret,true) !== null){
                $ret = json_decode($ret,true);
            }

            // Catch and Log errors
            $retCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            // Error response
            if($retCode >= 400){
                // RDR doesn't give a message on 404, so make one.
                if($retCode == 404) {
                    $errStr = "Not Found";
                } else {
                    // Gather all errors into one string
                    $errStr = $this->recursiveBuildMessage($ret);
                }
            }
            // No response
            if($ret === false){
                $errStr = "No response from RDR";
            }
            if(!empty($errStr)){
                $Log->writeError("RDR: " . $errStr,false,false,true);
                $Log->writeVariable("Attempted to DELETE to: " . $url);
                $ret = array('error' => 500,
                             'message' => $errStr);
            }
     
            // Don't return a message on successful DELETE.
            //  RDR returns a single string on successful DELETE.
            //  The webservices layer interprets a string as an error.
            if($retCode == 200) {
                $ret = null;
            }
            $Log->exitFunction("RDR: " . $functionName,$ret);
        } else {
            $Log->writeError("Privilege lvl ($curPriv) below required privilege ($reqPriv)",false,false,true);
            $ret = array();
            $ret['error'] = 500;
            $ret['message'] = "Insufficient Privileges";
        }
 
        return $ret;
    }

    public function get($which,$id = null,$sid = null) {
        switch ($which) {
        case "serviceprofile":
            $response = $this->get_serviceprofile($id,$sid);
            break;
        case "tests":
            $response = $this->get_tests_private($sid);
            break;
        case "history":
            $response = $this->get_schedule_history_private($id,$sid);
            break;
        default:
            global $Log;
            $Log->writeError("Undefined GET call: $which",false,true);
            $response = array();
            $response['error'] = 500;
            $response['message'] = "Undefined Call";
        }
        return $response;
    }

    private function get_serviceprofile($id,$sid){
        if (($sid == null) || ($sid == $this->BP->get_local_system_id())){
            $response = $this->get_serviceprofile_local($id);
        } else {
            $response = $this->get_serviceprofile_remote($id,$sid);
        }
        return $response;
    }

    private function get_tests_private($sid){
        $guest_os = isset($_GET['guest_os']) ? $_GET['guest_os'] : null;
        if (($sid == null) || ($sid == $this->BP->get_local_system_id())){
            $response = $this->get_tests_local($guest_os);
        } else {
            $response = $this->get_tests_remote($sid, $guest_os);
        }
        return $response;
    }

    private function get_schedule_history_private($historyId,$sid){
        if (is_numeric($historyId)){
            if (($sid == null) || ($sid == $this->BP->get_local_system_id())){
                $response = $this->get_schedule_history_local($historyId);
            } else {
                $response = $this->get_schedule_history_remote($sid, $historyId);
            }
        } else {
            $response = array();
            $response['error'] = 500;
            $response['message'] = "Invalid ID";
        }
        return $response;
    }

    private function get_serviceprofile_remote($id,$sid){
        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        if ($id === null){
            $api = "/api/certification/serviceprofile/";
        } else {
            $api = "/api/certification/serviceprofile/" . $id . "/";
        }

        $remoteRet = $this->functions->remoteRequestRDR($url, 'GET', $api, "", null);

        if(isset($remoteRet['service_profiles'])){
            $ret['service_profiles'] = $remoteRet['service_profiles'];
        } else if(isset($remoteRet['result']) && isset($remoteRet['result']['message'])){
            $ret = array();
            $ret['error'] = 500;
            $ret['message'] = $remoteRet['result']['message'];
        } else {
            $ret = $remoteRet;
        }
        return $ret;
    }

    private function get_tests_remote($sid, $guest_os){
        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        $api = "/api/certification/tests/";
        if (!empty($guest_os)){
            $api = $api . "?guest_os=" . $guest_os;
        }
        $remoteRet = $this->functions->remoteRequestRDR($url, 'GET', $api, "", null);

        if(isset($remoteRet['tests'])){
            $ret['tests'] = $remoteRet['tests'];
        } else if(isset($remoteRet['result']) && isset($remoteRet['result']['message'])){
            $ret = array();
            $ret['error'] = 500;
            $ret['message'] = $remoteRet['result']['message'];
        } else {
            $ret = $remoteRet;
        }
        return $ret;
    }

    private function get_schedule_history_remote($sid,$historyId){
        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        $api = "/api/certification/history/" . $historyId . "/";
        $remoteRet = $this->functions->remoteRequestRDR($url, 'GET', $api, "", null);

        if(isset($remoteRet['history'])){
            $ret['history'] = $remoteRet['history'];
        } else if(isset($remoteRet['result']) && isset($remoteRet['result']['message'])){
            $ret = array();
            $ret['error'] = 500;
            $ret['message'] = $remoteRet['result']['message'];
        } else {
            $ret = $remoteRet;
        }
        return $ret;
    }

    private function get_serviceprofile_local($id){
        if(isset($id)){
            $response = $this->get_service_profile($id);
        } else {
            $response = $this->get_service_profiles();
        }
        if(is_array($response) && !isset($response['error'])){
            $response = array('service_profiles'=> $response);
        }
        return $response;
    }

    private function get_tests_local($guest_os){
        $tests = $this->get_tests($guest_os);
        if (empty($guest_os)){
            $response = array();
            $response['error'] = 500;
            $response['message'] = "Guest OS should be specified";
        } else {
            if (array_key_exists('message',$tests)){
                $response = $tests['message'] . " " . $tests['messageDetail'];
            } else {
                $response = array('tests'=>$tests);
            }
        }
        return $response;
    }

    private function get_schedule_history_local($historyId){
        $history = $this->get_schedule_history($historyId);
        if (is_array($history) && isset($history['detail'])){
            // 'detail' is a string and needs to be a json object
            $history['detail'] = json_decode($history['detail'], true);
            $formatted_history = $this->build_substep($history);
            $response = array('history'=>$formatted_history);
        } else {
            $response = $history;
        }
        return $response;
    }

    // returns a summary of compliance and recovery assurance stats
    public function get_rdr_summary($sid = null) {
        if (($sid == null) || ($sid == $this->BP->get_local_system_id())){
            $response = $this->get_rdr_summary_local();
        } else {
            $response = $this->get_rdr_summary_remote($sid);
        }
        return $response;
    }

    private function get_rdr_summary_remote($sid){
        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        $api = "/api/reports/rdr_summary/";
        $remoteRet = $this->functions->remoteRequestRDR($url, 'GET', $api, "", null);
        return $remoteRet;
    }

    private function get_rdr_summary_local(){
        $RAstats = $this->get_teststats(7);
        // aggreate all the individual job reports to get summary
        $RAsum = array();
        if (is_array($RAstats)){
            $totSucc = $totFail = $totWarn = 0;
            foreach($RAstats as $stat) {
                $totSucc += $stat['successful'];
                $totFail += $stat['failed'];
                $totWarn += $stat['warning'];
            }
            $tot = $totSucc + $totFail + $totWarn;
            if ($tot == 0) {
                $percentSucc = null;
            } else {
                $percentSucc = round($totSucc / $tot * 100,2);
            }
            $RAsum['success_percent'] = $percentSucc;
            $RAsum['successful'] = $totSucc;
            $RAsum['failed'] = $totFail;
            $RAsum['warning'] = $totWarn;
        }
        $Cstats = $this->get_compliance_summary();
        $tot = $Cstats['compliance_failed'] + $Cstats['compliance_ok'];
        if ($tot == 0) {
            $percentSucc = null;
        } else {
            $percentSucc = round($Cstats['compliance_ok'] / $tot * 100,2);
        }
        $Cstats['ok_percent'] = $percentSucc;
        $ret = array ( 
            "recovery_assurance" => $RAsum,
            "compliance" => $Cstats
        );
        return $ret;
    }


    public function post($which,$data,$sid = null) {
        switch($which) {
        case 'serviceprofile':
            $response = $this->post_serviceprofile($data,$sid);	
            break;
        default:
            global $Log;
            $Log->writeError("Undefined POST call: $which",false,true);
            $response = array();
            $response['error'] = 500;
            $response['message'] = "Undefined Call";
        }
        return $response;
    }

    private function post_serviceprofile($data,$sid){
        // UNIBP-13571 prepend dataype constant
        $data = array('$type' => Constants::RDR_PROFILE) + $data;

        if (($sid == null) || ($sid == $this->BP->get_local_system_id())){
            $response = $this->post_serviceprofile_local($data);
        } else {
            $response = $this->post_serviceprofile_remote($data,$sid);
        }
        return $response;
    }

    private function post_serviceprofile_remote($data,$sid){
        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        $api = "/api/certification/serviceprofile/";
        $remoteRet = $this->functions->remoteRequestRDR($url, 'POST', $api, "", $data);
        if (isset($remoteRet['result'])){
            $ret['result'] = $remoteRet['result'];
        } else if (isset($remoteRet['message'])){
            $ret = array();
            $ret['error'] = 500;
            $ret['message'] = $remoteRet['message'];
        } else {
            $ret = $remoteRet;
        }
        return $ret;
    }

    private function post_serviceprofile_local($data){
        $profile = $this->new_service_profile($data);
        if (isset($profile['id']) && is_int($profile['id'])){
            $response = array('result'=>array('id'=>$profile['id']));
        } else {
            $response = $profile;
        }
        return $response;
    }

    public function put($which,$data,$id,$sid = null) {
        switch($which) {
        case 'serviceprofile':
            if (empty($id)){
                $response = "Profile id required";
                break;
            }
            $response = $this->put_serviceprofile($data,$id,$sid);
            break;
        default:
            global $Log;
            $Log->writeError("Undefined PUT call: $which",false,true);
            $response = array();
            $response['error'] = 500;
            $response['message'] = "Undefined Call";
            break;
        }
        return $response;
    }

    private function put_serviceprofile($data,$id,$sid){
        if (($sid == null) || ($sid == $this->BP->get_local_system_id())){
            $response = $this->put_serviceprofile_local($data,$id);
        } else {
            $response = $this->put_serviceprofile_remote($data,$id,$sid);
        }
        return $response;
    }

    private function put_serviceprofile_remote($data,$id,$sid){
        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        $api = "/api/certification/serviceprofile/" . $id . "/";
        $remoteRet = $this->functions->remoteRequestRDR($url, 'PUT', $api, "", $data);

        if (isset($remoteRet['result'])){
            $ret['result'] = $remoteRet['result'];
        } else if (isset($remoteRet['message'])){
            $ret = array();
            $ret['error'] = 500;
            $ret['message'] = $remoteRet['message'];
        } else {
            $ret = $remoteRet;
        }
        return $ret;
    }

    private function put_serviceprofile_local($data,$id){
        // RDR expects a complete service profile, not just the
        // bits to be modified, so we retrieve the  profile to
        // be modified, modify it here, and pass the full, 
        // modified job to RDR
        $oldProfile = $this->get_service_profile($id);
        // If we get an error string, pass it on
        if (is_string($oldProfile)){
            return $oldProfile;
        }
        $newProfile = $data;
        foreach($oldProfile as $key => $value) {
            if (!isset($newProfile[$key]) && isset($oldProfile[$key])){
                $newProfile[$key] = $oldProfile[$key];
            }
        }
        $response = $this->edit_service_profile($newProfile);
        return $response;
    }
        

    public function delete($which,$id,$sid = null) {
        switch($which) {
        case 'serviceprofile':
            if (empty($id)){
                $response = "Profile id required";
                break;
            }
            $response = $this->delete_serviceprofile($id, $sid);
            break;
        default:
            global $Log;
            $Log->writeError("Undefined DELETE call: $which",false,true);
            $response = array();
            $response['error'] = 500;
            $response['message'] = "Undefined Call";
        }
        return $response;
    }

    private function delete_serviceprofile($id, $sid){
        if (($sid == null) || ($sid == $this->BP->get_local_system_id())){
            $response = $this->delete_serviceprofile_local($id);
        } else {
            $response = $this->delete_serviceprofile_remote($id,$sid);
        }
        return $response;
    }

    private function delete_serviceprofile_remote($id,$sid){
        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        $api = "/api/certification/serviceprofile/" . $id . "/";
        $remoteRet = $this->functions->remoteRequestRDR($url, 'DELETE', $api, "");

        if (isset($remoteRet['result'])){
            $ret['result'] = $remoteRet['result'];
        } else if (isset($remoteRet['message'])){
            $ret = array();
            $ret['error'] = 500;
            $ret['message'] = $remoteRet['message'];
        } else {
            $ret = $remoteRet;
        }
        return $ret;
    }

    private function delete_serviceprofile_local($id){
        $response = $this->delete_service_profile($id);
        return $response;
    }

    // Recursively formats the steps in a rdr job.    
    // RDR still returns camelCase fields. We need to convert to underscore_case
    // and format the DateTimes.
    public function build_substep($step){
        if (empty($step)){
            return $step;
        }
        $newStep = array();
        foreach ($step as $key => $value){
            switch($key){
                case 'startTime':
                case 'start_time':
                    $newStep['start_date'] = $this->functions->formatDateTime(strtotime($value));
                    break;
                case 'endTime':
                case 'end_time':
                    // in-progress steps have a null endTime, don't convert to default dateTime
                    if (isset($value)){
                        $newStep['end_date'] = $this->functions->formatDateTime(strtotime($value));
                    } else {
                        $newStep['end_date'] = null;
                    }
                    break;
                case 'stepAction':
                    $newStep['step_action'] = $value;
                    break;
                case 'percent_complete':
                case 'progress':
                    $newStep['percent_complete'] = $value;
                    break;
                case 'status':
                case 'result':
                    $newStep['status'] = $value;
                    break;
                case 'subSteps':
                case 'sub_steps':
                    $newStep['children'] = array();
                    foreach( $value as $subValue){
                        $newStep['children'][] = $this->build_substep($subValue);
                    }
                    break;
                case 'detail':
                    $newStep['detail'] = $this->build_substep($value);
                    break;
                default:
                    $newStep[$key] = $value;
            }
        }
        return $newStep;
    }

    // iterates over array to build a string of Messages
    // (or other specified key) delimited by a \n
    function recursiveBuildMessage($array,$key = 'Message'){
        if (!is_array($array)){
            return $array;
        }
        $iterator = new RecursiveIteratorIterator( new RecursiveArrayIterator($array), RecursiveIteratorIterator::SELF_FIRST);
        $ret = "";
        foreach($iterator as $ikey=>$value){
            if (stripos($ikey,$key) !== false){
                $ret .= $value . "\n";
            }
        }
        return rtrim($ret);
    }
}

