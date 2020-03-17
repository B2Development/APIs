<?php

define("UNITRENDS", 'unitrends-rr');
define("UNITRENDSUI", 'unitrends-ui');

class Updates
{
    private $BP;

    public function __construct($BP){
        $this->BP = $BP;
        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);
    }

    public function get($which){

        switch($which){

            case "status":
                $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
                $sInfo = $this->BP->get_system_info($systemID);
                $sName = $sInfo['name'];
                $allData['updates']['sid'] = $systemID;
                $allData['updates']['sname'] = $sName;
                //$installUpdates=$this->BP->get_update_info($systemID);
                $updateList=$this->BP->get_update_list($systemID);
                $i=0;
                foreach($updateList as $update){
                    $i++;
                }
                $allData['updates']['status_string'] = "Updates in progress, ". $i. " packages remaining";
                $allData['updates']['remaining']=$i;
                break;
            default:
                $sid = isset($_GET['sid']) ? (int)$_GET['sid'] : false;
                $systems = $this->functions->selectSystems($sid, false);
                foreach ($systems as $systemID => $sName) {
                    $updateList = $this->BP->get_update_list($systemID);
                    $preCheck = "Yes";
                    $Data = array();
                    $Data['sid'] = $systemID;
                    $Data['sname'] = $sName;

                    if ($updateList !== false) {
                        $list = $sizeArray = array();
                        $Data['count'] = count($updateList);
                        $showRefreshMessage = false;
                        foreach ($updateList as $update) {
                            $name = $update['name'];
                            $version = isset($update['version']) ? $update['version'] : 'unknown';
                            // See if the Unitrends package is in the list.  If so, run pre-check.
                            if ($name === UNITRENDS) {
                                $showRefreshMessage = true;
                                $checkStatus = $this->BP->precheck_install($version, $systemID);
                                if ($checkStatus !== -1) {
                                    if ($checkStatus === -5) {
                                        // -5 is a special status.  In this case, retrieve the last error message for display.
                                        // And allow the user to either confirm or cancel the update.
                                        $preCheck = "Message:" . $this->BP->getError();
                                    } else if ($checkStatus == true) {
                                        $preCheck = "Yes";
                                    } else {
                                        $preCheck = "No:" . $this->BP->getError();
                                    }
                                } else {
                                    $preCheck = "Error:" . $this->BP->getError();
                                }
                            } elseif ($name === UNITRENDSUI) {
                                $showRefreshMessage = true;
                            }
                            $package = null;
                            $packageInfo = array();
                            $package[] = $name;
                            $packageInfo = $this->BP->get_update_info($package, $systemID);
                            $sizeArray[] = $packageInfo[0]['size'];
                            $data = array(
                                'name' => $update['name'],
                                'arch' => $update['arch'],
                                'version' => $update['version']
                            );
                            $list[] = $data;
                        }
                        $size = $this->computeUpdateSize($sizeArray);
                        $Data['size'] = $size;
                        $Data['preCheck'] = $preCheck;
                        $Data['updates'] = $list;
                        $Data['show_refresh_message'] = $showRefreshMessage;
                    } else {
                        $message = 'Error getting update list: ' . $this->BP->getError();
                        global $Log;
                        $Log->writeError($message, true);
                        $Data['error'] = 500;
                        $Data['count'] = 0;
                        $Data['preCheck'] = "No:" . $this->BP->getError();
                        $Data['message'] = $message;
                        $Data['updates'] = array();
                    }
                    $allData['data'][] = $Data;
                }
                break;
        }
        return $allData;
    }

/*Sample Input for Post updates

{
"packages":
    [
        "unitrends-rr",
        "unitrends-baremetal"
    ]
}
 * */
    public function post($which,$data){

        $packageList = null;
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();

        if($data===null){
            $updateList=$this->BP->get_update_list($systemID);
            if ($updateList !== false) {
                foreach($updateList as $updates){
                    $arch = isset($updates['arch']) ? ('.' . $updates['arch']) : "";
                    $packageList[]=$updates['name'] . $arch;
                }
            }
        }
        else{
            $packageList=$data['packages'];
        }


        $returnArray=null;
        $allData = null;
        if($packageList !== null){
            $returnArray = $this->BP->install_updates($packageList, $systemID);
            // testing failure messages
            // $returnArray = $this->test_install_updates($packageList, $systemID);
            if ($returnArray !== false) {
                //
                // The install command did not fail, but the return may not be reporting success.  If not,
                // it could be because the package was installed earlier by dependency (when we call updates
                // individually).  Only check if we are installing packages one at-a-time (list count of 1).
                //
                if ((count($packageList) == 1) && $this->packageNotUpdated($returnArray)) {
                    // Go get the list of updates, then see if any of the names on our list are still
                    // in the available updates.  If they are still in the list, then the package install
                    // truly failed.  If the package name (or names) is not in the list, it was already installed
                    $updateList = $this->BP->get_update_list($systemID);
                    if ($updateList !== false) {
                        $bAlreadyUpdated = true;
                        // Get the package name, the first and only item in the package list, and see if
                        // it is on the available list or already updated due to dependency.
                        $packageName = $packageList[0];
                        foreach ($updateList as $update) {
                            if ($update['name'] == $packageName) {
                                $bAlreadyUpdated = false;
                                break;
                            }
                        }
                        // If package no longer available, it has already been updated, so this is
                        // really successful from the RRC-perspective.  Alter the return array to report success.
                        if ($bAlreadyUpdated) {
                            $returnArray['success'] = true;
                            $commandOutput = "'" . $packageName . "' was already updated and is at the latest version.";
                            $returnArray['output'] = array($commandOutput);
                        }
                    } else {
                        global $Log;
                        $Log->writeError("Error getting update list: " . $this->BP->getError(), true);
                    }
                }
                // Collapse output into a delimited string for display.
                $allData = "<pre>" . implode("\n", $returnArray['output']) . "</pre>";
                $allData = array('error' => 500, 'message' => $allData);
            } else {
                $allData = array('error' => 500, 'message' => 'Error occurred while updating: '. $this->BP->getError());
            }
        }
        return $allData;
    }

    function computeUpdateSize($output)
    {
        $size = 0; $precision = 2;
        foreach ($output as $key => $outputString) {
            $processorArray[$key] = explode(' ', $outputString);
            if($processorArray[$key][1] === "G" || $processorArray[$key][1] === "g"){
                $size += ((int)$processorArray[$key][0]*1024*1024*1024);   // Convert  into bytes
            }
            else if($processorArray[$key][1] === "M" || $processorArray[$key][1] === "m"){
                $size +=((int)$processorArray[$key][0]*1024*1024);   // Convert  into bytes
            }
            else if($processorArray[$key][1] === "K" || $processorArray[$key][1] === "k"){
                $size += ((int)$processorArray[$key][0]*1024);   // Convert  into bytes
            }
            else{
                $size += ((int)$processorArray[$key][0]);
            }
        }
        $base = log($size, 1024);
        $suffixes = array('', 'KB', 'MB', 'GB', 'TB');
        return round(pow(1024, $base - floor($base)), $precision) . ' '. $suffixes[floor($base)];
    }

    function packageNotUpdated($returnArray) {

        $bReturn = false;

        if ($returnArray['success'] == false) {
            $outputArray = $returnArray['output'];
            foreach ($outputArray as $outputString) {
                if (strstr($outputString, 'was not upgraded')) {
                    $bReturn = true;
                    break;
                }
            }
        }

        return $bReturn;
    }

    /*
     * Updates tester.  Requirement. file with error message, can be multiple lines, at /var/www/html/yumerror.out.
     */
    function test_install_updates($logInfo, $dpuID) {
        global $Log;
        $functionName = 'bp_install_updates';
        $Log->enterFunction($functionName, $logInfo, $dpuID);

        // fake data
        $string = file_get_contents("/var/www/html/yumerror.out");
        $result = array();
        $output = explode("\n", $string);
        $result['output'] = $output;
        $result['success'] = false;

        $Log->exitFunction($functionName, $result);

        return $result;
    }

}   //End Updates

?>
