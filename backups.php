<?php

//in progress
class Backups
{
    private $BP;
    const MORE_FILES = "";
    const MORE_FILES_ID = "...";
    const FAILURE_STATUS = 2;
    const MAX_BACKUPS_LEGAL_HOLD_INFO = 2000;       // Threshold over which legal hold backup status is not obtained in the catalog.

    public function __construct($BP, $sid, $Roles = null)
    {
        $this->BP = $BP;
        $this->now = time();
        $this->sid = $sid;
        $this->showReplicatedAsRemote = false;
        $this->targetName = "";

        $this->showLegalHoldInCatalog = true;       // By default, return legal hold status

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);

    	$this->SYSTEM_METADATA = "system metadata";
        $this->DO_NOT_ALLOW_DELETE_ON_TARGET = "You cannot delete a backup that resides on the backup copy target.";

        $this->GLOBAL_BACKUP_TYPES = array(
            "master",
            "differential",
            "incremental",
            "baremetal",
            "selective",
            "mssql full",
            Constants::BACKUP_TYPE_BLOCK_FULL,
            Constants::BACKUP_TYPE_BLOCK_INCREMENTAL,
            "mssql differential",
            "mssql transaction",
            "exchange full",
            "exchange differential",
            "exchange incremental",
            "legacy mssql full",
            "legacy mssql diff",
            "legacy mssql trans",
            "vmware full",
            "vmware differential",
            "vmware incremental",
            "hyperv full",
            "hyperv incremental",
            "hyperv differential",
            "oracle full",
            "oracle incr",
            "sharepoint full",
            "sharepoint diff",
            "ucs service profile full",
            "integrated bm restore",
            "ndmp full",
            "ndmp diff",
            "ndmp incr",
            "xen full",
            "ahv full",
            "ahv diff",
            "ahv incr"
        );

        $this->GLOBAL_SQL_TYPES 		= array (
            "mssql full",
            "mssql differential",
            "mssql transaction",
            "legacy mssql full",
            "legacy mssql diff",
            "legacy mssql trans",
        );

        $this->GLOBAL_SS_TYPES 		= array (
            "securesync master",
            "securesync differential",
            "securesync incremental",
            "securesync baremetal",
            "securesync localdir",
            "securesync dpustate",
            "securesync SQL",
            "securesync exchange",
            "securesync msexch full",
            "securesync msexch incr",
            "securesync msexch diff",
            "securesync mssql full",
            "securesync mssql diff",
            "securesync mssql trans",
            "securesync vmware full",
            "securesync vmware diff",
            "securesync vmware incr",
            "securesync hyperv full",
            "securesync hyperv incr",
            "securesync hyperv diff",
            "securesync oracle full",
            "securesync oracle incr",
            "securesync sharepoint full",
            "securesync sharepoint diff",
            "securesync system metadata",
            "securesync ucs service profile full",
            "securesync ndmp full",
            "securesync ndmp diff",
            "securesync ndmp incr",
            "securesync xen full",
            "securesync ahv full",
            "securesync ahv diff",
            "securesync ahv incr"
        );
        $this->INTEGRATED_BM_RESTORE = "integrated bm restore";
        $this->Roles = $Roles;
        $this->parseImported = false;
    }


    public function get($which, $filter, $sid)
    {
        $data = array();
        switch ($which) {
            case -1:
            case 'browser':
                $cid = isset($_GET['cid']) ? (int)$_GET['cid'] : null;
                $iid = isset($_GET['iid']) ? (int)$_GET['iid'] : null;
                $bid = isset($_GET['bid']) ? $_GET['bid'] : null;
                $copies = isset($_GET['copies']) ? $_GET['copies'] : false; // Set to true to get backup copy information (aka securesync types)
                $this->parseImported = isset($_GET['imported']) ? ((int)$_GET['imported'] === 1) : false;
                $startDate = isset($_GET['start_date']) ? strtotime($_GET['start_date']) : strtotime(Constants::DATE_ONE_WEEK_AGO);
                $endDate = isset($_GET['end_date']) ? $this->functions->formatEndDate($_GET['end_date']) : strtotime('now');
                if (isset($_GET['remote']) && $_GET['remote'] == "true") {
                    return $this->getBackupsOnTarget($cid, $iid, $bid, $copies, $startDate, $endDate, $sid);
                }

                //did they pass in sid, cid, iid, or a bid list?
                if ($sid !== false) {
                    $inputArray = array(
                        'system_id' => $sid,
                        'start_time' => $startDate,
                        'end_time' => $endDate
                    );

                    $name = $this->functions->getSystemNameFromID($sid);

                    if ($cid !== null) {
                        $inputArray = array_merge($inputArray, array('client_id' => (int)$cid));
                    }
                    if ($iid) {
                        $inputArray = array_merge($inputArray, array('instance_id' => $iid));
                    }
                    if ($bid) {
                        $bidArray = array_map('intval', explode(',', $bid));
                        $inputArray = array_merge($inputArray, array('backup_ids' => $bidArray));
                        unset($inputArray['start_time']);
                    }
                    if ($copies) {
                        $inputArray['sync_msg'] = 1;
                    }
                    if ($which == 'browser'){
                        $inputArray['grandclients'] = false;
                    }

                    $backupStatus = $this->BP->get_backup_status($inputArray);
                    if ($backupStatus !== false) {
                        //using this to display risk level in backup browser
                        $backupIDs = $this->getBackupIDs($backupStatus);
                        $atRiskBackups = array();
                        if (count($backupIDs) > 0){
                            $bids = implode(",", $backupIDs);
                            $atRiskBackups = $this->BP->get_backups_risk_level($bids, $sid);

                            $storage = $this->BP->get_backup_storage_name($bids, $sid);
                        }
                        if (count($backupStatus) > 0) {
                            foreach ($backupStatus as $backup) {
                                if (isset($backup['type'])) {
                                    // if copies, skip the non "securesync" backup types.
                                    if ($copies && !strstr($backup['type'], 'securesync')) {
                                        continue;
                                    }
                                    // if not copies, skip the "securesync" backup types.
                                    if (!$copies && strstr($backup['type'], 'securesync')) {
                                        continue;
                                    }
                                }
                                if (isset($backup['type'])) {
                                    if (strstr($backup['type'], 'metadata')) {
                                        continue;
                                    }
                                }
                                if ($which == 'browser') {
                                    if (strstr($backup['type'], 'restore')) {
                                        continue;
                                    }
                                }
                                if ($which == 'browser') {
                                    $data["BackupStatus"][] = $this->buildBrowserOutput($backup, $atRiskBackups, $storage, $name, $sid);
                                } else {
                                    $data["BackupStatus"][] = $this->buildOutput($backup, $sid);
                                }
                            }
                        } else {
                            // No backups were returned, so the backup or backups are no longer present.
                            $result = array(
                                'output' => "This backup is no longer present on the appliance.",
                                'id' => $bid,
                                'start_time' => 'unknown'
                            );
                            $data["BackupStatus"][] = $result;
                        }
                    }
                } else {
                    $systems = $this->functions->selectSystems();
                    foreach ($systems as $id => $name) {
                        $inputArray = array(
                            'system_id' => $id,
                            'start_time' => $startDate,
                            'end_time' => $endDate
                        );
                        if ($cid !== null) {
                            $inputArray = array_merge($inputArray, array('client_id' => (int)$cid));
                        }
                        if ($iid) {
                            $inputArray = array_merge($inputArray, array('instance_id' => $iid));
                        }
                        if ($bid) {
                            $bidArray = array_map('intval', explode(',', $bid));
                            $inputArray = array_merge($inputArray, array('backup_ids' => $bidArray));
                            unset($inputArray['start_time']);
                        }
                        if ($copies) {
                            $inputArray['sync_message'] = 1;
                        }
                        if ($which == 'browser'){
                            $inputArray['grandclients'] = false;
                        }

                        $backupStatus = $this->BP->get_backup_status($inputArray);
                        if ($backupStatus !== false) {

                            //using this to display risk level in backup browser
                            $backupIDs = $this->getBackupIDs($backupStatus);
                            $atRiskBackups = array();
                            if (count($backupIDs) > 0){
                                $bids = implode(",", $backupIDs);
                                $atRiskBackups = $this->BP->get_backups_risk_level($bids, $id);

                                $storage = $this->BP->get_backup_storage_name($bids, $id);
                            }

                            foreach ($backupStatus as $backup) {
                                if ($copies && isset($backup['type'])) {
                                    // if copies, skip the non "securesync" backup types.
                                    if (!strstr($backup['type'], 'securesync')) {
                                        continue;
                                    }
                                }
                                if (isset($backup['type'])) {
                                    if (strstr($backup['type'], 'metadata')) {
                                        continue;
                                    }
                                }
                                if ($which == 'browser'){
                                    $data["BackupStatus"][] = $this->buildBrowserOutput($backup, $atRiskBackups, $storage, $name, $id);
                                } else {
                                    $data["BackupStatus"][$name][] = $this->buildOutput($backup, $id);
                                }
                            }
                        } else {
                            $data['error'] = 500;
                            $data['message'] = $this->BP->getError();
                        }
                    }
                }
                break;

            case "details":
                if (isset($_GET['bid'])) {
                    $bid = $_GET['bid'];
                    $backups = array_map('intval', array_filter(explode(',', $bid), 'is_numeric'));
                    if ($sid == false) {
                        $data = false;
                    } else {
                        $inputArray = array(
                            'system_id' => $sid,
                            'backup_ids' => $backups
                        );
                        $backupInfoArray = $this->BP->get_backup_info($inputArray);
                        if ($backupInfoArray !== false) {
                            foreach ($backupInfoArray as $backupInfo) {
                                $data["BackupInfo"][] = $this->buildInfoOutput($backupInfo, $sid);
                            }
                        } else {
                            $data['error'] = 500;
                            $data['message'] = $this->BP->getError();
                        }
                    }
                }
                break;
            case "catalog" :
                $cid = isset($_GET['cid']) ? (int)$_GET['cid'] : false;
                $iid = isset($_GET['iid']) ? (int)$_GET['iid'] : false;
                $aid = isset($_GET['aid']) ? (int)$_GET['aid'] : false;
                $startDate = isset($_GET['start_date']) ? strtotime($_GET['start_date']) : strtotime(Constants::DATE_ONE_WEEK_AGO);
                $endDate = isset($_GET['end_date']) ? $this->functions->formatEndDate($_GET['end_date']) : strtotime('now');
                $view = isset($_GET['view']) ? $_GET['view'] : "system";
                $gclients = isset($_GET['grandclients']) ? $_GET['grandclients'] : false;
                $this->showReplicatedAsRemote = isset($filter['show_remote']);
                if ($this->showReplicatedAsRemote) {
                    $this->targetName = $this->functions->getLocalSystemName();
                    $gclients = true;
                }
                $metadata = isset($_GET['metadata']) ? $_GET['metadata'] : false;

                $result_format['type'] = $this->GLOBAL_BACKUP_TYPES;
                if ($metadata) {
                    $result_format['type'][] = $this->SYSTEM_METADATA;
                }
                $result_format['start_time'] = $startDate;
                $result_format['end_time'] = $endDate;
                $result_format['grandclients'] = $gclients;

                if($cid){
                    $result_format['client_id'] = $cid;
                }
                if($iid){
                    $result_format['instance_id'] = $iid;
                }
                if($aid){
                    $result_format['application_id'] = $aid;
                }

                if ($sid == false) {
                    $systems = $this->functions->selectSystems();
		            $data["catalog"] = array();
                    foreach ($systems as $sid => $name) {
                        $result_format['system_id'] = $sid;
                        if ($view !== false){
                            $data["catalog"] = $this->merge_view($data["catalog"], $this->getBackupsByView($view, $result_format, $sid), $view);                            }
                       }

                } else {
                    $result_format['system_id'] = $sid;
                    if ($view !== false){
                        $data["catalog"] = $this->getBackupsByView($view, $result_format, $sid);
                    }
                }
                if ($view == 'day') {
                    // If day view, sort all days in the catalog.
                    $orderByDate = array();
                    foreach ($data["catalog"] as $key => $row) {
                        $orderByDate[$key] = strtotime($row['day']);
                    }
                    array_multisort($orderByDate, SORT_DESC, $data["catalog"]);
                }
                break;

            case "search" :
                $data = array();
                $timestr = isset($_GET['timestr']) ? $_GET['timestr'] : "";
                $searchID = array('pid' => (int)$filter, 'timestr' => $timestr);

                $searchResults = $this->BP->get_file_search_results($searchID, $sid);

                if ($searchResults !== false) {
                    //check for string status of 'running'
                    if(is_string($searchResults)) {
                        $data["results"] = $searchResults;
                    } else {
                        foreach ($searchResults as $search) {
                            $fileID = isset($search['id']) ? $search['id'] : "";
                            $bid = isset($search['backup_id']) ? $search['backup_id'] : "";
                            $type = isset($search['type']) ? $search['type'] : "";
                            $directory = isset($search['directory']) ? $search['directory'] : "";
                            $name = isset($search['name']) ? $search['name'] : "";
                            $size = isset($search['size']) ? $search['size'] : "";
                            $date = isset($search['date']) ? $this->functions->formatDateTime($search['date']) : "";

                            //get backup date from backup id
                            $backupStatus = $this->BP->get_backup_status(array('backup_ids' => array($bid), 'system_id' => $sid));
                            if($backupStatus !== false) {
                                $backupDate = $this->functions->formatDateTime($backupStatus[0]['start_time']);
                                $synth_capable = $backupStatus[0]['synth_capable'];
                                $backup_size = $backupStatus[0]['size'];
                            } else {
                                $backupDate = "";
                            }

                            $data["results"][] = array(
                                'id' => $fileID,
                                'backup_id' => $bid,
                                'type' => $type,
                                'directory' => $directory,
                                'name' => $name,
                                'size' => $size,
                                'date_string' => $date,
                                'backup_date' => $backupDate,
                                'synth_capable' => $synth_capable,
                                'backup_size' => $backup_size
                            );
                        }
                    }

                } else {
                    global $Log;
                    $Log->writeError($this->BP->getError(), true);
                    $data = $searchResults;
                }

                break;
            case 'target-search':
                return $this->getSearchResultsOnTarget($filter, $sid);
                break;
            case 'files':
                $bid = (int)$filter;
                $start = isset($_GET['dir']) ? $_GET['dir'] : "";
                $last = isset($_GET['last']) ? $_GET['last'] : "";
                $count = isset($_GET['count']) ? (int)$_GET['count'] : 0;
                $files = $this->BP->get_backup_files($bid, $start, $start, $last, $count, $sid);
                if($files != false) {
                    for($i = 0; $i < count($files); $i++) {
                        unset($files[$i]['parent']);
                        //unset($files[$i]['size']);
                        unset($files[$i]['date']);
                        if(array_key_exists('has_children', $files[$i])) {
                            $files[$i]['branch'] = $files[$i]['has_children'];
                            unset($files[$i]['has_children']);
                        }
                    }
                    if ($count > 0 && ($count === count($files))) {
                        $directory = $files[$count - 1]['directory'];
                        $lastFile = $files[$count - 1]['name'];
                        $files[] = $this->addFilePlaceholder($directory, $lastFile);
                    }
                    $data = array();
                    $data['data'] = $files;
                    $data['count'] = $count;
                    $data['total'] = count($files);
                } else {
                    $data = $files;
                }
                break;

            case "target-files":
                $data = $this->getBackupFilesOnTarget($filter, $sid, false);
                break;

            case "related" :
                if ($which) {
                    if (is_string($filter)){
                        $backupIDs = explode(',', $filter);
                        $related = array();
                        foreach($backupIDs as $backupID){
                            $related = array_merge($related, $this->processRelatedBackups($backupID, $this->sid));
                        }
                        if (isset($_GET['original'])) {
                            // include input backups in return array.
                            $bidArray = array_map('intval', $backupIDs);
                            $inputArray = array(
                                'system_id' => $this->sid,
                                'backup_ids' => $bidArray
                        	);
                            $original = array();
                            $backupStatus = $this->BP->get_backup_status($inputArray);
                            if ($backupStatus !== false) {
                                $idArray = array();
                                foreach ($backupStatus as $backup) {
                                    if (isset($backup['id'])) {
                                        // Do not return duplicate backup IDs
                                        if (!in_array($backup['id'], $idArray)) {
                                            $idArray[] = $backup['id'];
                                        } else {
                                            continue;
                                        }
                                    }
                                    $original[] = $this->processOriginalBackup($backup, $this->sid);
                                }
                            }
                            $related = array_merge($related, $original);
                        }
                        $data['data'] = $related;
                    }
                }
                break;
            case 'target-related':
                $data['data'] = $this->getRelatedBackupsOnTarget($which, $filter, $sid);
                break;
            case "dependent" :
                if ($which) {
                    if (is_string($filter)){
                        $backupIDs = explode(',', $filter);
                        $related = array();
                        foreach($backupIDs as $backupID){
                            $related = array_merge($related, $this->processDependentBackups($backupID, $this->sid));
                        }
                        if (isset($_GET['original'])) {
                            // include input backups in return array.
                            $bidArray = array_map('intval', $backupIDs);
                            $inputArray = array(
                                'system_id' => $this->sid,
                                'backup_ids' => $bidArray
                            );
                            $original = array();
                            $backupStatus = $this->BP->get_backup_status($inputArray);
                            if ($backupStatus !== false) {
                                $idArray = array();
                                foreach ($backupStatus as $backup) {
                                    if (isset($backup['id'])) {
                                        // Do not return duplicate backup IDs, which
                                        if (!in_array($backup['id'], $idArray)) {
                                            $idArray[] = $backup['id'];
                                        } else {
                                            continue;
                                        }
                                    }
                                    $original[] = $this->buildOutput($backup, $this->sid);
                                }
                            }
                            $related = array_merge($related, $original);
                        }
                        $data['data'] = $related;
                    }
                }
                break;
            case 'target-dependent':
                $data['data'] = $this->getDependentBackupsOnTarget($which, $filter, $sid);
                break;
            case 'strategies':
                $data = array();
                if(is_numeric($filter)) {
                    $strategies = array();

                    switch($filter) {
                        case Constants::APPLICATION_ID_FILE_LEVEL:
                            //file-level
                            if(isset($_GET['isNAS']) and $_GET['isNAS'] == 'true') {
                                //file-level NAS
                                $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                                $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_DIFF_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL));
                                $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL, Constants::BACKUP_DISPLAY_TYPE_SELECTIVE);
                            } else {
                                //file-level physical
                                $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                                $strategies[] = array("name" => Constants::BACKUP_STRATEGY_INCR_4EVER_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                                $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_INCR_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                                $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_DIFF_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL));
                                if (isset($_GET['noBareMetal']) && $_GET['noBareMetal'] == 'true') {
                                    $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL,
                                        Constants::BACKUP_DISPLAY_TYPE_SELECTIVE);
                                } else {
                                    $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL,
                                        Constants::BACKUP_DISPLAY_TYPE_SELECTIVE, Constants::BACKUP_DISPLAY_TYPE_BAREMETAL);
                                }
                            }
                            break;
                        case Constants::APPLICATION_ID_BLOCK_LEVEL:
                            //image-level physical
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_INCR_4EVER_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_INCR_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                            $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL);
                            break;
                        case Constants::APPLICATION_ID_EXCHANGE_2003:
                            //Incremental backups not supported for Exchange 2003 - set as entirely separate case to maintain consistent ordering of strategies in the array
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_DIFF_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL));
                            $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL);
                            break;
                        case Constants::APPLICATION_ID_EXCHANGE_2007:
                        case Constants::APPLICATION_ID_EXCHANGE_2010:
                        case Constants::APPLICATION_ID_EXCHANGE_2013:
                        case Constants::APPLICATION_ID_EXCHANGE_2016:
                            //Exchange
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_INCR_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_DIFF_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL));
                            $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL);
                            break;
                        case Constants::APPLICATION_ID_SQL_SERVER_2005:
                        case Constants::APPLICATION_ID_SQL_SERVER_2008:
                        case Constants::APPLICATION_ID_SQL_SERVER_2008_R2:
                        case Constants::APPLICATION_ID_SQL_SERVER_2012:
                        case Constants::APPLICATION_ID_SQL_SERVER_2014:
                        case Constants::APPLICATION_ID_SQL_SERVER_2016:
                        case Constants::APPLICATION_ID_SQL_SERVER_2017:
                            //SQL
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_DIFF_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_TRANS_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_TRANSACTION));
                            $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL, Constants::BACKUP_DISPLAY_TYPE_TRANSACTION);
                            break;
                        case Constants::APPLICATION_ID_VMWARE:
                            //VMware
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_INCR_4EVER_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_INCR_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_DIFF_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL));
                            $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL);
                            break;
                        case Constants::APPLICATION_ID_HYPER_V_2008_R2:
                        case Constants::APPLICATION_ID_HYPER_V_2012:
                        case Constants::APPLICATION_ID_HYPER_V_2016:
                            //Hyper-V
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_INCR_4EVER_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_INCR_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                            $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL);
                            break;
                        case Constants::APPLICATION_ID_ORACLE_11:
                        case Constants::APPLICATION_ID_ORACLE_12:
                        case Constants::APPLICATION_ID_ORACLE_10:
                            //Oracle
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_INCR_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                            $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL);
                            break;
                        case Constants::APPLICATION_ID_SHAREPOINT_2007:
                        case Constants::APPLICATION_ID_SHAREPOINT_2010:
                        case Constants::APPLICATION_ID_SHAREPOINT_2013:
                        case Constants::APPLICATION_ID_SHAREPOINT_2016:
                            //SharePoint
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_DIFF_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL));
                            $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL);
                            break;
                        case Constants::APPLICATION_ID_UCS_SERVICE_PROFILE:
                            //Cisco UCS
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                            $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL);
                            break;
                        case Constants::APPLICATION_ID_VOLUME:
                            //NDMP
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_INCR_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_DIFF_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL));
                            $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL);
                            break;
                        case Constants::APPLICATION_ID_XEN:
                            //Xen
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                            $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL);
                            break;
                        case Constants::APPLICATION_ID_AHV:
                            //AHV
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_INCR_4EVER_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                            $strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_INCR_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                            // Differential - Not supported in first iteration
                            //$strategies[] = array("name" => Constants::BACKUP_STRATEGY_FULL_DIFF_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL));
                            $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL);
                            break;
                        default:
                            $data['error'] = 500;
                            $data['message'] = "The provided application id " . $filter . " is not associated with any supported application";
                            break;
                    }
                    if(isset($data['error'])) {
                        continue;
                    } else {
                        // Add Custom as a valid strategy, with no pre-defined backup_types.
                        $strategies[] = array("name" => "Custom", "backup_types" => array());
                        $data = array('strategies' => $strategies, 'on_demand' => $onDemand);
                    }
                    if (isset($_GET['onlyIncremental']) && $_GET['onlyIncremental'] === 'true') {
                        // If only incremental backups option is passed in (restricted platform), restrict here.
                        // For on deman, only Full and Incremental are allowed.
                        $strategies = array();
                        $strategies[] = array("name" => Constants::BACKUP_STRATEGY_INCR_4EVER_STRING, "backup_types" => array(Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL));
                        $onDemand = array(Constants::BACKUP_DISPLAY_TYPE_FULL, Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL);
                        $data = array('strategies' => $strategies, 'on_demand' => $onDemand);
                    }
                } else {
                    $data['error'] = 500;
                    $data['message'] = "An application id must be specified";
                }
                break;
            case "synthesized-files":
                $bid = (int)$filter;
                $start = isset($_GET['dir']) ? $_GET['dir'] : "";
                $last = isset($_GET['last']) ? $_GET['last'] : "";
                $count = isset($_GET['count']) ? (int)$_GET['count'] : 0;
                $groupID = $this->BP->get_synthesis_group($bid, $sid);
                if ($groupID !== false) {
                    $files = $this->BP->get_synthesized_files($groupID, $bid, $start, $last, $count, $sid);
                    if($files != false) {
                        for($i = 0; $i < count($files); $i++) {
                            unset($files[$i]['parent']);
                            //unset($files[$i]['size']);
                            unset($files[$i]['date']);
                            if(array_key_exists('has_children', $files[$i])) {
                                $files[$i]['branch'] = $files[$i]['has_children'];
                                unset($files[$i]['has_children']);
                            }
                        }
                        if ($count > 0 && ($count === count($files))) {
                            $directory = $files[$count - 1]['directory'];
                            $lastFile = $files[$count - 1]['name'];
                            $files[] = $this->addFilePlaceholder($directory, $lastFile);
                        }
                        $data = array();
                        $data['data'] = $files;
                        $data['count'] = $count;
                        $data['total'] = count($files);
                    } else {
                        $data = $files;
                    }
                } else {
                    $data['error'] = 500;
                    $data['message'] = $this->BP->getError();
                }
                break;
            case "latest":
                if (isset($_GET['iid'])) {
                    $instances = array_map("intval", explode(',' , $_GET['iid']));
                }
                $systemId = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
                $days = isset($_GET['days']) ? (int)$_GET['days'] : 0;

                // Get list of all backup statuses for each instance
                if (isset($_GET['list']) && $_GET['list']){
                    $data['data'] = array();
                    foreach($instances as $instance){
                        $backups = $this->post(array('instance_id'=>$instance),$systemId);
                        $data['data'] = array_merge($data['data'], $backups['data']);
                    }
                    break;
                }

                // Get backup id of latest backup for each instance
                $lastBackups = $this->BP->get_last_backup_per_instance($instances, $systemId);

                if (!empty($lastBackups)) {
                    // Get start_time from get_backup_status
                    $inputArray = array();
                    $inputArray['system_id'] = $systemId;

                    foreach($lastBackups as $lbKey => $lastBackup){
                        $inputArray['backup_ids'][] = $lastBackup['backup_id'];
                    }
                    $statuses = $this->BP->get_backup_status($inputArray);
                    foreach($statuses as $status){
                        if(strstr($status['type'], 'securesync') == false){
                            if($days !== 0){
                                $latestStartTime = $status['start_time'];
                                $earliestStartTime = $latestStartTime - (60*60*24*$days);        //Get backups for last number of "$days" starting from latest backup's "start_time". $earliestStartTime is calculated in seconds.
                                $result_format = array('start_time' => $earliestStartTime,
                                    'end_time' => $latestStartTime,
                                    'system_id' => $systemId,
                                    'instance_id' => $status['instance_id']);
                                $statusArray = $this->BP->get_backup_status($result_format);
                                foreach($statusArray as $individualStatus){
                                    if ($individualStatus['status'] != self::FAILURE_STATUS &&
                                        strstr($individualStatus['type'], 'securesync') == false) {
                                        $newStatus = array();
                                        $newStatus['backup_id'] = $individualStatus['id'];
                                        $newStatus['instance_id'] = $individualStatus['instance_id'];
                                        $newStatus['start_time_string'] = $this->functions->formatDateTime($individualStatus['start_time']);
                                        $newStatus['start_time'] = $individualStatus['start_time'];
                                        $newStatus['certified'] = $individualStatus['certified'];
                                        $data['data'][] = $newStatus;
                                    }
                                }
                            }
                            else{
                                $newStatus = array();
                                $newStatus['backup_id'] = $status['id'];
                                $newStatus['instance_id'] = $status['instance_id'];
                                $newStatus['start_time_string'] = $this->functions->formatDateTime($status['start_time']);
                                $newStatus['start_time'] = $status['start_time'];
                                $newStatus['certified'] = $status['certified'];
                                $data['data'][] = $newStatus;
                            }
                        }
                    }
                } else {
                    $data['error'] = 500;
                    $data['message'] = "No Backups were found!";
                }

                break;
            case "file-list":
                require_once('backup-files.php');
                $toCSV = (isset($_GET['format']) && ($_GET['format'] == 'csv'));
                $fileList = new BackupFiles($this->BP, $toCSV);
                $data = array();
                if ($toCSV) {
                    $data['data'] = $fileList->get($sid);
                    $data['csv'] = true;
                } else {
                    $data['files'] = $fileList->get($sid);
                }
                break;

            case "target-synthesized-files":
                $data = $this->getBackupFilesOnTarget($filter, $sid, true);
                break;

            case "risk_level":
                $data = array();
                $atRisk = array();
                //$filter is bid or string of bids
                if ($filter !== false && $filter !== null && $filter !== "") {
                    $bids = $filter;
                    if ($this->sid == false){
                        $sid = $this->BP->get_local_system_id();
                    }
                     $atRisk = $this->BP->get_backups_risk_level($bids, $sid);
                     if ($atRisk !== false){
                         $data = $atRisk;
                     } else {
                         $data['error'] = 500;
                         $data['message'] = $this->BP->getError();
                     }
                } else {
                    $data['error'] = 500;
                    $data['message'] = "At least one backup id is required";
                }
                break;
        }
        return $data;

    } // end get

    private function addFilePlaceholder($directory, $lastFile) {
        return array('id' => $directory . self::MORE_FILES_ID,
            'directory' => $directory,
            'branch' => false,
            'name' => self::MORE_FILES,
            'last' => $lastFile);
    }

    function processOriginalBackup($backup, $sid) {

        $id = isset($backup['id']) ? $backup['id'] : null;
        $type = isset($backup['type']) ? $this->functions->getBackupTypeString($backup['type']) : "";
        $startTime = isset($backup['start_time']) ? $this->functions->formatDateTime($backup['start_time']) : "";
        $appName  = isset($backup['app_name']) ? $backup['app_name'] : "";
        $status = $this->getBackupCompleteStatus($backup);

        $instanceID  = isset($backup['instance_id']) ? $backup['instance_id'] : null;
        if ($instanceID) {
            $instInfo = $this->functions->getInstanceNames($instanceID, $sid);
            if ($instInfo !== false) {
                $assetName = isset($instInfo['asset_name']) ? $instInfo['asset_name'] : "";
                $clientName = isset($instInfo['client_name']) ? $instInfo['client_name'] : "";
            }
        } else {
            $replicated = isset($backup['replicated']) ? $backup['replicated'] : null;
            $clientName = isset($backup['client_id']) ? $this->getClientName($backup['client_id'], $replicated, $sid) : "";
            $assetName = $clientName;
        }

        //added to check if the backup is on legal hold
        $legalHoldInfo = $this->BP->get_legalhold_backup_info($id, $sid);
        if($legalHoldInfo !== false){
            $legalHoldInfo = current($legalHoldInfo);
            if(isset($legalHoldInfo['is_on_hold'])){
                $legalHold = $legalHoldInfo['is_on_hold'];
            }
        }

        //whether the backup is a GFS recovery point
        $gfs_rp_type = isset($backup['gfs_rp_type']) ? $backup['gfs_rp_type'] : "None";

        $data = array(
            'id' => $id,
            'type' => $type,
            'start_time' => $startTime,
            'application' => $appName,
            'status' => $status,
            'client_name' => $clientName,
            'asset_name' => $assetName,
            'legalhold' => $legalHold,
            'gfs_rp_type' => $gfs_rp_type
        );

        return $data;
    }

    function processRelatedBackups($bid, $sid) {
        return $this->processBackupGroup($bid, $sid, 'get_related_backups');
    }

    function processDependentBackups($bid, $sid) {
        return $this->processBackupGroup($bid, $sid, 'get_dependent_backups');
    }

    function processBackupGroup($bid, $sid, $function){
	    $data = array();

        $relatedBackups = call_user_func_array(array($this->BP, $function), array($bid, $sid));
        if ($relatedBackups !== false and !empty($relatedBackups)) {
            foreach ($relatedBackups as $related) {
                $backup = array(
                    'id' => isset($related['id']) ? $related['id'] : "",
                    'type' => isset($related['type']) ? $this->functions->getBackupTypeDisplayName($related['type']) : "",
                    'start_time' => isset($related['start_time']) ? $this->functions->formatDateTime($related['start_time']) : "",
                    'application' => isset($related['app_name']) ? $related['app_name'] : "",
                    'status' => isset($related['status']) ? $this->getBackupCompleteStatus($related) : 'n/a'
                );
                if (isset($related['instance_id'])) {
                    $instInfo = $this->functions->getInstanceNames($related['instance_id'], $sid);
                    if ($instInfo !== false) {
                        $inst = array(
                            'asset_name' => isset($instInfo['asset_name']) ? $instInfo['asset_name'] : "",
                            'client_name' => isset($instInfo['client_name']) ? $instInfo['client_name'] : ""
                        );
                    }
                } else {
                    $replicated = isset($backup['replicated']) ? $backup['replicated'] : null;
                    $clientName = isset($backup['client_id']) ? $this->getClientName($backup['client_id'], $replicated, $sid) : "";
                    $inst = array(
                        'asset_name' => $clientName,
                        'client_name' => $clientName
                    );
                }

                $legalHoldInfo = $this->BP->get_legalhold_backup_info($bid, $sid);
                if($legalHoldInfo !== false){
                    $legalHoldInfo = current($legalHoldInfo);
                    if(isset($legalHoldInfo['is_on_hold'])){
                        $backup['legalhold'] = $legalHoldInfo['is_on_hold'];
                    }
                }
                $bid_array = array($backup['id']);
                $backup_status = $this->BP->get_backup_status(array('backup_ids' => $bid_array), $sid);
                if ($backup_status !== false) {
                    if (count($backup_status) > 0) {
                        // get the first element.
                        $backup_status = $backup_status[0];
                    }
                    //whether the backup is a GFS recovery point
                    $backup['gfs_rp_type'] = isset($backup_status['gfs_rp_type']) ? $backup_status['gfs_rp_type'] : "None";
                }
                $data[] = array_merge($backup, $inst);
            }
        }

        return $data;
    }

    public function search($data, $sid) {

        $result = array();

        $cid = isset($_GET['cid']) ? (int)$_GET['cid'] : null;
        if($data) {
            $searchResult = $this->BP->start_file_search($data, $cid, $sid);

            if ($searchResult !== false) {
                $pid = isset($searchResult['pid']) ? $searchResult['pid'] : "";
                $timestr = isset($searchResult['timestr']) ? $searchResult['timestr'] : "";

                $result = array(
                    'pid' => $pid,
                    'timestr' => $timestr
                );
            } else {
                $result = false;
            }
        } else {
            $result = "Search parameters are required";
        }
        return $result;
    }

    public function certify($data, $sid)
    {
        if( isset($data['cert_status']) && isset($data['backup_ids'])) {
            $result = $this->BP->set_certification_status($data['cert_status'],$data['backup_ids']);
        } else {
            $result = "cert_status and backup_ids required";
        }
        return $result;
    }

    public function search_on_target($postData, $sid) {
        $data = array();
        $request = "POST";
        $cid = isset($_GET['cid']) ? (int)$_GET['cid'] : null;
        $api = "/api/backups/search/";
        $parameters = "cid=" . $cid;
        $result = $this->functions->remoteRequest("", $request, $api, $parameters, $postData, 1);
        if (is_array($result)) {
            $result['remote'] = true;
            $data = $result;
        }
        return $data;
    }

    function getSearchResultsOnTarget($filter, $sid) {
        $timestr = isset($_GET['timestr']) ? $_GET['timestr'] : "";
        $lang = isset($_GET['lang']) ? ("&lang=" . $_GET['lang']) : "";
        $data = array();
        $request = "GET";
        $api = "/api/backups/search/" . $filter . "/";
        $parameters = "timestr=" . $timestr . $lang;
        $result = $this->functions->remoteRequest("", $request, $api, $parameters, NULL, 1);
        if (is_array($result)) {
            $result['remote'] = true;
            $data = $result;
        }
        return $data;
    }

    public function delete($backupIDs, $sid) {
         if (isset($_GET['remote']) && $_GET['remote'] == "true") {
                $data['error'] = 500;
                $data['message'] = $this->DO_NOT_ALLOW_DELETE_ON_TARGET;
            } else {
                $legalHoldIDs = isset($_GET['legalhold']) ? $_GET['legalhold'] : "none";
                $data = $this->BP->delete_backup($backupIDs, $legalHoldIDs, $sid);
            }

        return $data;
    }

    public function put($which, $inputArray){
        if(!isset($inputArray['systemID'])) {
            $dpuID = $this->BP->get_local_system_id();
        } else {
            $dpuID = $inputArray['systemID'];
        }
        return $this->BP->rest_backup_now($inputArray['backup_type'], $inputArray['client_info'], $dpuID);
    }

    public function hold($which, $filter, $sid){
        $legalHoldDays = (int)$filter;
        $bid = $which;
        $data = $this->BP->save_legalhold_per_backup($bid, $legalHoldDays, $sid);

        return $data;
    }

    public function post($input, $sid) {
        $data = array();
        $legalHoldIDs = "none";  //we don't allow deleting held backups from the browser.  The user needs to 'unhold' first
        if (isset($_GET['isDelete'])) {
            if (is_array($input)) {
                $bids = implode(",", $input);
                $data = $this->BP->delete_backup($bids, $legalHoldIDs, $sid);
            }
        } else if (isset($_GET['isRelated'])) {
            if(is_array($input)){
                $bids = implode(",", $input);
                $data = $this->get('related', $bids, $sid);
            }
        }else{
            $backupStatus = $this->BP->get_backup_status($input);
            foreach ($backupStatus as $backup) {
                $data["data"][] = $this->buildOutput($backup, $sid);
            }
        }

        return $data;
    }

    private function getBackupIDs($statusArray) {
        $backupIDs = array();
        foreach ($statusArray as $status) {
            if (isset($status['id'])) {
                $backupIDs[] = $status['id'];
            }
        }
        return $backupIDs;
    }


    function buildOutput($backup, $sid){
        $id = isset($backup['id']) ? $backup['id'] : null;
        $ids = $id != null ? array($id) : array();
        $inputArray = array(
            'system_id' => $sid,
            'backup_ids' => $ids,
        );
        $backupInfoArray = $this->BP->get_backup_info($inputArray);

        if ($backupInfoArray !== false){
            foreach ($backupInfoArray as $backupInfo){
                $output = isset($backupInfo['output']) ? $backupInfo['output'] : "n/a";
                if (isset($backup['sync_message']) && ($backup['sync_message'] != "")) {
                    $output .= '<br/>Backup Copy Message: ' . $backup['sync_message'];
                }
                $purgeable = isset($backupInfo['purgeable']) ? $backupInfo['purgeable'] : "n/a";
                $last = isset($backupInfo['last']) ? $backupInfo['last'] : "n/a";
                if ($this->parseImported && $backup['imported_from_archive']) {
                    $archiveInfo = $this->parseDetailsOutput($output, $sid);
                    if ($archiveInfo !== false) {
                        $archiveID = $archiveInfo['archive_id'];
                        $originalBID = $archiveInfo['original_bid'];
                        $originalBUStartTime = $archiveInfo['original_backup_start_time'];
                    }
                }
            }
        }

        $synthesized = isset($backup['synthesized']) ? $backup['synthesized'] : null;
        $type = isset($backup['type']) ? $this->functions->getBackupTypeString($backup['type'], $synthesized) : null;
        $restoredBackupType = (isset($backup['restored_bkup_type']) && $backup['type'] == "virtual restore" || $backup['type'] == 'replica restore') ? $this->functions->getBackupTypeString($backup['type']) : null;
        $clientID = isset($backup['client_id']) ? $backup['client_id'] : null;
        $startTime = isset($backup['start_time']) ? $this->functions->formatDateTime($backup['start_time']) : null;
        $elapsedTime = isset($backup['elapsed_time']) ? $backup['elapsed_time'] : null;
        $complete = isset($backup['complete']) ? $backup['complete'] : null;
        $completeTime = $startTime != null && $elapsedTime != null && $complete == true ? $this->functions->formatDateTime($backup['start_time'] + $backup['elapsed_time']) : null;
        $status = $this->getBackupCompleteStatus($backup);
        $size = isset($backup['size']) ? $backup['size'] : null;
        $files = isset($backup['files']) ? $backup['files'] : null;
        $encrypted = isset($backup['encrypted']) ? $backup['encrypted'] : null;
        $syncStatus = isset($backup['sync_status']) ? $backup['sync_status'] : null;
        $verified = isset($backup['verified']) ? $backup['verified'] : null;

        $verifyStatus = $this->getBackupVerifyStatus($backup);

        $presyncSeconds = isset($backup['presync_seconds']) ? $backup['presync_seconds'] : null;
        $syncSeconds = isset($backup['sync_seconds']) ? $backup['sync_seconds'] : null;
        $postsyncSeconds = isset($backup['postsync_seconds']) ? $backup['postsync_seconds'] : null;
        $syncMessage = isset($backup['sync_message']) ? $backup['sync_message'] : null;
        $serverInstanceName = isset($backup['server_instance_name']) ? $backup['server_instance_name'] : null;
        $serverName  = isset($backup['server_name']) ? $backup['server_name'] : null;
        $databaseName = isset($backup['database_name']) ? $backup['database_name'] : null;
        $instanceID  = isset($backup['instance_id']) ? $backup['instance_id'] : null;
        $appID  = isset($backup['app_id']) ? $backup['app_id'] : null;
        $appName  = isset($backup['app_name']) ? $backup['app_name'] : null;
        $synthCapable = isset($backup['synth_capable']) ? $backup['synth_capable'] : null;
        $diskMetadata = isset($backup['disk_metadata']) ? $backup['disk_metadata'] : null;
        $clientConfig = isset($backup['client_config']) ? $backup['client_config'] : null;
        $replicated = isset($backup['replicated']) ? $backup['replicated'] : null;
        $vmWareTemplate = isset($backup['vmware_template']) ? $backup['vmware_template'] : "n/a";
        $xenTemplate = isset($backup['xen_template']) ? $backup['xen_template'] : "n/a";
        $certified = isset($backup['certified']) ? $backup['certified'] : "n/a";

        $clientInfo = isset($backup['client_id']) ? $this->getClientName($backup['client_id'], $replicated, $sid, true) : null;
        $clientName = $clientInfo !== null ? $clientInfo['name'] : null;
        $clientOS = $clientInfo != null ? $clientInfo['os_type'] : null;
        $clientOSID = ($clientInfo != null and isset($clientInfo['os_type_id'])) ? $clientInfo['os_type_id'] : null;

        $asset_name = $databaseName !== null ? $databaseName : $clientName;
        $isCluster = isset($backup['is_cluster']) ? $backup['is_cluster'] : null;
        $guid = isset($backup['guid']) ? $backup['guid'] : null;
        $vmName = isset($backup['vm_name']) ? $backup['vm_name'] : null;

        $isSQLCluster = isset($backup['is_sql_cluster']) ? $backup['is_sql_cluster'] : false;
        $isSQLAlwayson = isset($backup['is_sql_alwayson']) ? $backup['is_sql_alwayson'] : false;

        $backupID = array();

        if($id !== null){
            $backupID = array(
                'id' => $id
            );
        }

        //added to check if the backup is on legal hold
        $legalHoldInfo = $this->BP->get_legalhold_backup_info($id, $sid);
        $legalHold = false;
        if($legalHoldInfo !== false){
            $legalHoldInfo = current($legalHoldInfo);
            if(isset($legalHoldInfo['is_on_hold'])){
                $legalHold = $legalHoldInfo['is_on_hold'];
            }
        }

        //whether the backup is a GFS recovery point
        $gfs_rp_type = isset($backup['gfs_rp_type']) ? $backup['gfs_rp_type'] : "";

        $isSecuresync = $this->functions->isBackupTypeSecuresync($backup['type']);
        $isFileLevel = $this->functions->isBackupFileLevel($backup['type']);
        $isHyperV = $this->functions->isBackupHyperV($backup['type']);
        $isExchange = $this->functions->isBackupExchange($backup['type']);
        $isSQL = $this->functions->isBackupSQL($backup['type']);
        $isVMWare = $this->functions->isBackupVMWare($backup['type']);
        $isXen = $this->functions->isBackupXen($backup['type']);
        $isAHV = $this->functions->isBackupAHV($backup['type']);
        $isVirtualRestore = $this->functions->isBackupVirtualRestore($backup['type']);

        $fileLevel = array();
        $applicationTypes = array();
        $SQLTypes = array();
        $exchangeTypes = array();
        $VMWareTypes = array();
        $syncTypes = array();
        $hyperVTypes = array();
        $XenTypes = array();
        $virtualRestoreTypes = array();

        if ($isFileLevel){
            $fileLevel = array(
                'files' => $files
            );
        }

        $alwaysReturned = array(
            'asset_name' => $asset_name,
            'client_id' => $clientID,
            'client_name' => $clientName,
            'client_os' => $clientOS,
            'client_os_id' => $clientOSID,
            'type' => $type,
            'start_time' => $startTime,
            'complete_time' => $completeTime,
            'synthesized' => $synthesized,
            'synth_capable' => $synthCapable,
            'disk_metadata' => $diskMetadata,
            'client_config' => $clientConfig,
            'replicated' => $replicated,
            'duration' => $elapsedTime,
            'complete' => $complete,
            'status' => $status,
            'size' => $size,
            'encrypted' => $encrypted,
            'verified' => $verified,
            'verify_status' => $verifyStatus,
            'vmware_template' => $vmWareTemplate,
            'xen_template' => $xenTemplate,
            'certified' => $certified,
            'output' => $output,
            'purgeable' => $purgeable,
            'last' => $last,
            'legalhold' => $legalHold,
            'gfs_rp_type' => $gfs_rp_type

        );

        if ($appID !== null) {
            $applicationTypes = array(
                'instance_id' => $instanceID,
                'app_id' => $appID,
                'app_name' => $appName,
            );
        }

        if ($isSQL) {
            $SQLTypes = array(
                'server_instance_name' => $serverInstanceName,
                'database_name' => $databaseName,
                'is_sql_cluster' => $isSQLCluster,
                'is_sql_alwayson' => $isSQLAlwayson
            );
        }

        if ($isExchange) {
            $exchangeTypes = array(
                'database_name' => $databaseName
            );
        }

        if ($isVMWare) {
            $VMWareTypes = array(
                'server_instance_name' => $serverInstanceName,
                'server_name' => $serverName,
                'vm_name' => $vmName
            );
        }

        if ($isXen || $isAHV) {
            $XenTypes = array(
                'server_instance_name' => $serverInstanceName,
                'server_name' => $serverName,
                'database_name' => $databaseName
            );
        }

        if($isSecuresync){
            $syncTypes = array(
                'sync_status' => $syncStatus,
                'presync_seconds' => $presyncSeconds,
                'sync_seconds' => $syncSeconds,
                'postsync_seconds' => $postsyncSeconds,
                'sync_message' => $syncMessage
            );
        }

        if($isHyperV){
            $hyperVTypes = array(
                'is_cluster' => $isCluster,
                'guid' => $guid,
                'vm_name' => $vmName
            );
        }

        if ($isVirtualRestore) {
            $virtualRestoreTypes = array(
                'restored_backup_type' => $restoredBackupType
            );
        }

        $data = array_merge($backupID, $alwaysReturned);

        if ($fileLevel !== null){
            $data = array_merge($data, $fileLevel);
        }
        if ($applicationTypes !== null){
            $data = array_merge($data, $applicationTypes);
        }
         if ($syncTypes !== null){
             $data = array_merge($data, $syncTypes);
         }
        if ($hyperVTypes !== null){
            $data = array_merge($data, $hyperVTypes);
        }
        if ($exchangeTypes !== null){
            $data = array_merge($data, $exchangeTypes);
        }
        if ($SQLTypes !== null){
            $data = array_merge($data, $SQLTypes);
        }
        if ($VMWareTypes !== null){
            $data = array_merge($data, $VMWareTypes);
        }
        if ($XenTypes !== null){
            $data = array_merge($data, $XenTypes);
        }
        if ($virtualRestoreTypes !== null){
            $data = array_merge($data, $virtualRestoreTypes);
        }

        if (isset($archiveID) && isset($originalBID) && isset($originalBUStartTime)) {
            $archiveTypes = array (
                'archive_id' => $archiveID,
                'original_bid' => $originalBID,
                'original_backup_start_time' => $originalBUStartTime
            );
            $data = array_merge($data, $archiveTypes);
        }

        return $data;
    }

    function buildBrowserOutput($backup, $atRiskBackups, $storageArray, $name, $sid){

        $id = isset($backup['id']) ? $backup['id'] : null;
        $type = isset($backup['type']) ? $this->functions->getBackupTypeDisplayName($backup['type']) : null;
        $restoredBackupType = (isset($backup['restored_bkup_type']) && $backup['type'] == "virtual restore" || $backup['type'] == 'replica restore') ? $this->functions->getBackupTypeDisplayName($backup['type']) : null;
        $clientID = isset($backup['client_id']) ? $backup['client_id'] : null;
        $startTime = isset($backup['start_time']) ? $this->functions->formatDateTime($backup['start_time']) : null;
        $elapsedTime = isset($backup['elapsed_time']) ? $this->functions->formatTimeDelta($backup['elapsed_time']) : null;
        $complete = isset($backup['complete']) ? $backup['complete'] : null;
        $completeTime = $startTime != null && $elapsedTime != null && $complete == true ? $this->functions->formatDateTime($backup['start_time'] + $backup['elapsed_time']) : null;
        $status = $this->getBackupCompleteStatus($backup);
        $size = isset($backup['size']) ? $backup['size'] : null;
        $files = isset($backup['files']) ? $backup['files'] : null;
        $encrypted = isset($backup['encrypted']) ? $backup['encrypted'] : null;
        $verified = isset($backup['verified']) ? $backup['verified'] : null;
        $storage = $backup['storage_name'] = $storageArray[$backup['id']];



        $atRisk = false;
        if (array_key_exists($backup['id'], $atRiskBackups)){
            $riskLevel = ($atRiskBackups[$backup['id']]);
            if ($riskLevel > 0) {
                $atRisk = true;
            }
        }

        $verifyStatus = $this->getBackupVerifyStatus($backup);

        $serverInstanceName = isset($backup['server_instance_name']) ? $backup['server_instance_name'] : null;
        $serverName  = isset($backup['server_name']) ? $backup['server_name'] : null;
        $databaseName = isset($backup['database_name']) ? $backup['database_name'] : null;
        $instanceID  = isset($backup['instance_id']) ? $backup['instance_id'] : null;
        $appID  = isset($backup['app_id']) ? $backup['app_id'] : null;
        $appName  = isset($backup['app_name']) ? $backup['app_name'] : null;
        $synthesized = isset($backup['synthesized']) ? $backup['synthesized'] : null;
        $replicated = isset($backup['replicated']) ? $backup['replicated'] : null;
        $certified = isset($backup['certified']) ? $backup['certified'] : "n/a";
        $application_type = $this->getApplicationType($appID, $backup['type']);
        $clientInfo = isset($backup['client_id']) ? $this->getClientName($backup['client_id'], $replicated, $sid, true) : null;
        $clientName = $clientInfo !== null ? $clientInfo['name'] : null;
        $asset_name = $databaseName !== null ? $databaseName : $clientName;
        $isCluster = isset($backup['is_cluster']) ? $backup['is_cluster'] : null;
        $vmName = isset($backup['vm_name']) ? $backup['vm_name'] : isset($backup['database_name']) ? $backup['database_name'] : null;
        $isSQLCluster = isset($backup['is_sql_cluster']) ? $backup['is_sql_cluster'] : false;
        $isSQLAlwayson = isset($backup['is_sql_alwayson']) ? $backup['is_sql_alwayson'] : false;

        $backupID = array();

        if($id !== null){
            $backupID = array(
                'id' => $id
            );
        }

        //added to check if the backup is on legal hold
        $legalHoldInfo = $this->BP->get_legalhold_backup_info($id, $sid);
        $legalHold = false;
        if($legalHoldInfo !== false){
            $legalHoldInfo = current($legalHoldInfo);
            if(isset($legalHoldInfo['is_on_hold'])){
                $legalHold = $legalHoldInfo['is_on_hold'];
            }
        }


        //whether the backup is a GFS recovery point
        $gfs_rp_type = isset($backup['gfs_rp_type']) ? $backup['gfs_rp_type'] : "None";

        $isSecuresync = $this->functions->isBackupTypeSecuresync($backup['type']);
        $isFileLevel = $this->functions->isBackupFileLevel($backup['type']);
        $isHyperV = $this->functions->isBackupHyperV($backup['type']);
        $isExchange = $this->functions->isBackupExchange($backup['type']);
        $isSQL = $this->functions->isBackupSQL($backup['type']);
        $isVMWare = $this->functions->isBackupVMWare($backup['type']);
        $isXen = $this->functions->isBackupXen($backup['type']);
        $isAHV = $this->functions->isBackupAHV($backup['type']);
        $isVirtualRestore = $this->functions->isBackupVirtualRestore($backup['type']);

        $fileLevel = array();
        $applicationTypes = array();
        $SQLTypes = array();
        $exchangeTypes = array();
        $VMWareTypes = array();
        $syncTypes = array();
        $hyperVTypes = array();
        $XenTypes = array();
        $AHVTypes = array();
        $virtualRestoreTypes = array();

        if ($isFileLevel){
            $fileLevel = array(
                'files' => $files
            );
        }

        $alwaysReturned = array(
            'appliance' => $name,
            'asset_name' => $asset_name,
            'client_id' => $clientID,
            'client_name' => $clientName,
            'type' => $type,
            'start_time' => $startTime,
            'complete_time' => $completeTime,
            'synthesized' => $synthesized,
            'replicated' => $replicated,
            'duration' => $elapsedTime,
            'complete' => $complete,
            'status' => $status,
            'size' => $size,
            'encrypted' => $encrypted,
            'verified' => $verified,
            'verify_status' => $verifyStatus,
            'certified' => $certified,
            'legalhold' => $legalHold,
            'gfs_rp_type' => $gfs_rp_type,
            'at_risk' => $atRisk,
            'storage' => $storage,
            'system_id' => $sid,
            'app_type' => $application_type
        );

        if ($appID !== null) {
            $applicationTypes = array(
                'instance_id' => $instanceID,
                'app_id' => $appID,
                'app_name' => $appName,
            );
        }

        if ($isSQL) {
            $SQLTypes = array(
                'server_instance_name' => $serverInstanceName,
                'database_name' => $databaseName,
                'is_sql_cluster' => $isSQLCluster,
                'is_sql_alwayson' => $isSQLAlwayson
            );
        }

        if ($isExchange) {
            $exchangeTypes = array(
                'database_name' => $databaseName
            );
        }

        if ($isVMWare) {
            $VMWareTypes = array(
                'server_instance_name' => $serverInstanceName,
                'server_name' => $serverName,
                'vm_name' => $vmName
            );
        }

        if ($isXen) {
            $XenTypes = array(
                'server_instance_name' => $clientName,
                'server_name' => $serverName,
                'vm_name' => $databaseName
            );
        }

        if ($isAHV) {
            $AHVTypes = array(
                'server_instance_name' => $clientName,
                'server_name' => $serverName,
                'vm_name' => $vmName
            );
        }

        if($isHyperV){
            $hyperVTypes = array(
                'server_instance_name' => $clientName,
                'is_cluster' => $isCluster,
                'vm_name' => $vmName
            );
        }

        if ($isVirtualRestore) {
            $virtualRestoreTypes = array(
                'restored_backup_type' => $restoredBackupType
            );
        }

        $data = array_merge($backupID, $alwaysReturned);

        if ($fileLevel !== null){
            $data = array_merge($data, $fileLevel);
        }
        if ($applicationTypes !== null){
            $data = array_merge($data, $applicationTypes);
        }
        if ($syncTypes !== null){
            $data = array_merge($data, $syncTypes);
        }
        if ($hyperVTypes !== null){
            $data = array_merge($data, $hyperVTypes);
        }
        if ($exchangeTypes !== null){
            $data = array_merge($data, $exchangeTypes);
        }
        if ($SQLTypes !== null){
            $data = array_merge($data, $SQLTypes);
        }
        if ($VMWareTypes !== null){
            $data = array_merge($data, $VMWareTypes);
        }
        if ($XenTypes !== null){
            $data = array_merge($data, $XenTypes);
        }
        if ($AHVTypes !== null){
            $data = array_merge($data, $AHVTypes);
        }
        if ($virtualRestoreTypes !== null){
            $data = array_merge($data, $virtualRestoreTypes);
        }

        return $data;
    }

    function buildInfoOutput($backup, $sid){
        $id = isset($backup['id']) ? $backup['id'] : null;
        $deviceID = isset($backup['device_id']) ? $backup['device_id'] : null;
        $output = isset($backup['output']) ? $backup['output'] : null;
        $comment = isset($backup['comment']) ? $backup['comment'] : null;
        $purgeable = isset($backup['purgeable']) ? $backup['purgeable'] : null;
        $last = isset($backup['last']) ? $backup['last'] : null;

        $archiveInfo = $this->parseDetailsOutput($output, $sid);
        if ($archiveInfo !== false) {
            $archiveID = $archiveInfo['archive_id'];
            $originalBID = $archiveInfo['original_bid'];
            $originalBUStartTime = $archiveInfo['original_backup_start_time'];
        }

        $data = array(
            'id' => $id,
            'device_id' => $deviceID,
            'output' => $output,
            'comment' => $comment,
            'purgeable' => $purgeable,
            'last' => $last
        );

        if (isset($archiveID) && isset($originalBID) && isset($originalBUStartTime)) {
            $archiveTypes = array (
                'archive_id' => $archiveID,
                'original_bid' => $originalBID,
                'original_backup_start_time' => $originalBUStartTime
            );
            $data = array_merge($data, $archiveTypes);
        }

        return $data;
    }

    function parseDetailsOutput($string, $sid) {
        $archiveInfo = false;

        if (strpos($string, 'Backup Copy restore of id') !==false){
            //remove any extra spaces or single newlines between words
            $string = trim(preg_replace('/\s+/', ' ', $string));
            $temp = explode(' ', $string);
            $archive_id = (int)$temp[5];
        }

        if (is_int($archive_id)){

            //we have the original bid!  Get the date from bp_get_archive_status
            $inputArray = array($archive_id);
            $archiveStatusArray = $this->BP->get_archive_status(array('archive_ids' => $inputArray), $sid);

            if ($archiveStatusArray !== false){
                foreach ($archiveStatusArray as $archiveStatus){
                    if ($archiveStatus['archive_id'] == $archive_id){
                        $original_backup_start_time = isset($archiveStatus['backup_time']) ? $this->functions->formatDateTime($archiveStatus['backup_time']) : null;
                        $original_bid = isset($archiveStatus['orig_backup_id']) ? $archiveStatus['orig_backup_id'] : "";
                        $archiveInfo = array('archive_id' => $archive_id,
                                            'original_bid' => $original_bid,
                                            'original_backup_start_time' => $original_backup_start_time);
                        break;
                    }
                }
            }
        }

        return $archiveInfo;
    }

    function getSystemName($sid) {
        $systemName = NULL;
        $systems = $this->BP->get_system_list();
        foreach ($systems as $id => $name) {
            if ($sid == $id) {
                $systemName = $name;
                break;
            }
        }
    return $systemName;
    }

    /*
     * Returns the client name, or full client object to the caller (if $getInfo is true).
     */
    function getClientName($cid, $replicated, $sid, $getInfo = false){

        $sid = $replicated ? $this->BP->get_local_system_id() : $sid;

        $clientInfo = $this->BP->get_client_info($cid, $sid);
        if ($getInfo) {
            $result = $clientInfo;
        } else {
            $result = isset($clientInfo['name']) ? $clientInfo['name'] : false;
        }

        return $result;
    }

    function getBackupsByView($view, $result_format, $sid){

//        $fp = fopen("/tmp/before", "a");
//        $time = date("F j, Y, g:i:s");
//        fprintf($fp, $time . "  -  start" . "\n");

        switch($view){
            case "day":
                if ($this->showReplicatedAsRemote) {
                    $backups = array();
                } else {
                    $backups = $this->catalogByDay($result_format, $sid);
                }
                $replicatedBackups = $this->replicatedBackupsByDay($result_format, $sid);
                $data = $this->merge_view($backups, $replicatedBackups, $view);
                break;
            case "instance":
                if ($this->showReplicatedAsRemote) {
                    $backups = array();
                } else {
                    $backups = $this->catalogByInstance($result_format, $sid);
                }
                $replicatedBackups = $this->replicatedBackupsByInstance($result_format, $sid);

                $data = array();
                if (is_array($backups)){
                    $data = array_merge($data, $backups);
                }
                if (is_array($replicatedBackups)){
                    $data = array_merge($data, $replicatedBackups);
                }
                break;
            case "storage":
                $data = $this->catalogByStorage($result_format, $sid);
                break;
            case "system" :
                $data = $this->catalogBySystem($result_format, $sid);
                break;

        }

  //      $time = date("F j, Y, g:i:s");
  //      fprintf($fp, $time . "  -  end" . "\n");

  //      fflush($fp);
  //      fclose($fp);

        return $data;
    }

    function getBackupCompleteStatus($backup){
        $status = null;
        if (isset($backup['status'])) {
            $status = $backup['status'];
            if (is_int($status)) {
                // Backup Status: 0 = Successful, 1 = Warning, 2 = Failed.
                $status = ($status === 0) ? 'Successful' : (($status === 1) ? 'Warning' : 'Failed');
            }
        }
        return $status;
    }

    function getBackupVerifyStatus($backup){
        $verifyStatus = null;
        if (isset($backup['verify_status'])) {
            $verifyStatus = $backup['verify_status'];
            if (is_int($verifyStatus)) {
                // verify Status: 0 = Not Applicable, 1 = Successful, 2 = Failed.
                $verifyStatus = ($verifyStatus === 0) ? 'Not Applicable' : (($verifyStatus === 1) ? 'Successful' : 'Failed');
            }
        }
        return $verifyStatus;
    }

    function getBackupDeviceInfo($bid, $sid){
       return null;

       /* $data = array();
        $bids[] = $bid;

        $backupInfo = $this->BP->get_backup_info(array('backup_ids' => $bids, 'system_id' => $sid));

        foreach ($backupInfo as $info) {
            $deviceID = isset($info['device_id']) ? $info['device_id'] : false;
            if ($deviceID) {
                $deviceInfo = $this->BP->get_device_info($deviceID);
                if ($deviceInfo) {
                    $data = array(
                        'id' => $deviceID,
                        'name' => $deviceInfo['dev_name'],
                        'mb_size' => $deviceInfo['capacity'],
                        'storage_id' => $deviceInfo['storage_id']
                    );
                }
            } else {
                $data = false;
            }
        }


        return $data;*/
    }

    function replicatedBackupsByDay($result_format, $sid){
        $data = false;
        $replicatedBackups = array();
        $replicatingSystems = $this->functions->selectReplicatingSystems($sid);

        if($replicatingSystems !== false){
            foreach($replicatingSystems as  $sourceID => $name){
                $gclients = $this->BP->get_grandclient_list($sourceID);
                foreach($gclients as $gcID => $gcName){
                    $result_format['type'] = $this->GLOBAL_BACKUP_TYPES;
                    $result_format['client_id'] = $gcID;
                    unset($result_format['system_id']);
                    unset($result_format['grandclients']);
                    $replicatedBackups = array_merge($replicatedBackups, $this->catalogByDay($result_format, $sid, $gclients, true));
                }
            }
            $data = $replicatedBackups;

        }
        return $data;
    }

    function catalogByDay($result_format, $sid, $clientList = false, $replicatedBackups = false){

        $data = false;
        $backup_ids = array();

        $backups = $this->BP->get_backup_status($result_format, $sid);
        $localSystemID = $this->BP->get_local_system_id();
        if($clientList === false){
            $clientList = $this->BP->get_client_list($sid);
        }

        if ($backups !== false) {
            $data = array();
            $items = array();
            foreach ($backups as $id){
                $bids = isset($id['id']) ? $id['id']: false;
                if ($bids) {
                    $backup_ids[] = $bids;

                }
            }
            $strBids = implode(',', $backup_ids);
            if($replicatedBackups){
                //$sid = $this->BP->get_local_system_id() ;
                $storages = $this->BP->get_backup_storage_name($strBids, $localSystemID);
                $legalHoldInfo = $this->includeLegalHoldStatus(count($backup_ids)) ? $this->BP->get_legalhold_backup_info($strBids, $localSystemID) : false;
            }
            else{
                $storages = $this->BP->get_backup_storage_name($strBids, $sid);
                $legalHoldInfo = $this->includeLegalHoldStatus(count($backup_ids)) ? $this->BP->get_legalhold_backup_info($strBids, $sid) : false;
            }
            if($legalHoldInfo !== false){
                foreach($legalHoldInfo as $info){
                    $legalHoldInfoData['holdRemaining'] = '';
                    if($info['is_on_hold'] == true) {
                        if($info['hold_days_backup'] != -1 && $info['hold_days_instance'] != -1) {
                            $remaining = $info['hold_expire_time'] - $info['curr_time'];
                            $days = intval(intval($remaining) / (3600*24));
                            if ($days == 1) {
                                $legalHoldInfoData['holdRemaining'] = $days . ' day remaining';
                            } else if ($days == -1) {
                                $legalHoldInfoData['holdRemaining'] = -$days . ' day past Hold ';
                            } else if ($days <-1){
                                $legalHoldInfoData['holdRemaining'] = -$days . ' days past Hold ';
                            } else {
                                $legalHoldInfoData['holdRemaining'] = $days . ' days remaining';
                            }
                        }
                    }
                    $legalHoldArray[$info['backup_no']] = array( 'legalHold' => $info['is_on_hold'], 'holdRemaining' => $legalHoldInfoData['holdRemaining']);
                }
            }

            foreach ($backups as &$backup) {
                if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                    if (!$this->Roles->backup_is_in_scope($backup, $sid)) {
                        //global $Log;
                        //$msg = "Backup " . $backup['id'] . " is NOT in restore scope.";
                        //$Log->writeVariable($msg);
                        continue;
                    }
                }
                //skip ibmr backups
                if (isset($backup['type']) && $backup['type'] == $this->INTEGRATED_BM_RESTORE) {
                    ;
                } else {
                    $day = isset($backup['start_time']) ? $this->functions->formatDate($backup['start_time']) : null;
                    $synthesized = isset($backup['synthesized']) ? $backup['synthesized'] : "n/a";
                    $type = isset($backup['type']) ? $this->functions->getBackupTypeDisplayName($backup['type'], $synthesized) : null;
                    $appName = isset($backup['app_name']) ? $backup['app_name'] : "";
                    $vmWareTemplate = isset($backup['vmware_template']) ? $backup['vmware_template'] : "n/a";
                    $xenTemplate = isset($backup['xen_template']) ? $backup['xen_template'] : "n/a";
                    $startDate = isset($backup['start_time']) ? $this->functions->formatDateTime($backup['start_time']) : null;
                    $replicated = $replicatedBackups ? true : (isset($backup['replicated']) ? $backup['replicated'] : "");
                    $dbName = isset($backup['database_name']) ? $backup['database_name'] : "";
                    $bid = isset($backup['id']) ? $backup['id'] : null;
                    $clientID = isset($backup['client_id']) ? $backup['client_id'] : null;
                    $clientName = $clientList[$backup['client_id']];
                    $storage = $backup['storage_name'] = $storages[$backup['id']];
                    $instanceID = isset($backup['instance_id']) ? $backup['instance_id'] : null;
                    $instanceName = $replicated ? $this->functions->getInstanceName($backup, $localSystemID, $clientName) : $this->functions->getInstanceName($backup, $sid, $clientName);
                    if ($bid != null) {
                        $legalHoldData = $legalHoldArray[$backup['id']];
                    } else {
                        $legalHoldData['legalHold'] = 'n/a';
                        $legalHoldData['holdRemaining'] = 'n/a';
                    }
                    $size = isset($backup['size']) ? $backup['size'] : "";
                    $status = isset($backup['status']) ? $backup['status'] : "";
                    $importedFromArchive = isset($backup['imported_from_archive']) ? $backup['imported_from_archive'] : "";
                    $encrypted = isset($backup['encrypted']) ? $backup['encrypted'] : "";
                    $complete = $backup['complete'];
                    $synth_capable = isset($backup['synth_capable']) ? $backup['synth_capable'] : "";

                    $app_id = isset($backup['app_id']) ? $backup['app_id'] : null;
                    $application_type = $this->getApplicationType($app_id, $backup['type']);
                    $certified = isset($backup['certified']) ? $backup['certified'] : 'n/a';

                    //oracle and sharepoint don't have db_name field, use instance_name
                    if ($dbName == "") {
                        $dbName = $instanceName;
                    }

                    if ($application_type == "Physical Server" && $instanceID == "") {
                        $appName = "file-level";
                        $dbName = $instanceName = $clientName;
                    }

                    if ($complete === true) {
                        $backup = array(
                            'id' => $bid,
                            'type' => $type,
                            'start_date' => $startDate,
                            //'disks' => $disks,
                            'legal_hold' => $legalHoldData['legalHold'],
                            'hold' => $legalHoldData['holdRemaining'],
                            'status' => $status,
                            'replicated' => $replicated,
                            'encrypted' => $encrypted,
                            'vmware_template' => $vmWareTemplate,
                            'xen_template' => $xenTemplate,
                            'imported_from_archive' => $importedFromArchive,
                            'storage' => $storage,
                            'size' => $size,
                            'synth_capable' => $synth_capable,
                            'app_type' => $application_type,
                            'local_system_id' => $localSystemID,
                            'app_id' => $app_id,
                            'certified' => $certified
                        );
                        if ($replicated && $this->showReplicatedAsRemote) {
                            $backup['remote'] = true;
                            if ($this->targetName !== "") {
                                $backup['storage'] = $this->targetName;
                            }
                        }
                        $index = $this->encode_index($appName, $clientID, $clientName, $dbName, $instanceID, $instanceName, $application_type, $sid, $vmWareTemplate);
                        $items[$day][$index][] = $backup;
                    }
                }
            }

            foreach ($items as $day => $indices) {
                $instances = array();
                foreach ($indices as $index => $backups) {
                    $instance = $this->create_instance($index);
                    //sort backups with the most recent first
                    $backups = $this->sortBackups($backups);
                    $instance['backups'] = $backups;
                    $instances[] = $instance;
                }
                $data[] = array('day' => $day, 'instances' => $instances);
            }

        } else {
            global $Log;
            $Log->writeError($this->BP->getError(), true);
        }
        return $data;
    }

    function replicatedBackupsByInstance($result_format, $sid){
        $data = false;
        $replicatedBackups = array();
        $replicatingSystems = $this->functions->selectReplicatingSystems($sid);

        if($replicatingSystems !== false){
            foreach($replicatingSystems as  $sourceID => $name){
                $gclients = $this->BP->get_grandclient_list($sourceID);
                foreach($gclients as $gcID => $gcName){
                    $result_format['type'] = $this->GLOBAL_BACKUP_TYPES;
                    $result_format['client_id'] = $gcID;
                    unset($result_format['system_id']);
                    unset($result_format['grandclients']);
                    $replicatedBackups = array_merge($replicatedBackups, $this->catalogByInstance($result_format, $sid, $gclients, true));
                }
            }
            $data = $replicatedBackups;

        }
        return $data;
    }
    function catalogByInstance($result_format, $sid, $clientList = false, $replicatedBackups = false){

        $data = false;
        $backup_ids = array();

        $backups = $this->BP->get_backup_status($result_format, $sid);
        $localSystemID = $this->BP->get_local_system_id();
        if($clientList === false){
            $clientList = $this->BP->get_client_list($sid);
        }

        if ($backups !== false) {
            $items = array();
            foreach ($backups as $id){
                $bids = isset($id['id']) ? $id['id']: false;
                if ($bids) {
                    $backup_ids[] = $bids;
                }
            }
            $strBids = implode(',', $backup_ids);
            if($replicatedBackups){
                //$sid = $this->BP->get_local_system_id() ;
                $storages = $this->BP->get_backup_storage_name($strBids, $localSystemID);
                $legalHoldInfo = $this->includeLegalHoldStatus(count($backup_ids)) ? $this->BP->get_legalhold_backup_info($strBids, $localSystemID) : false;
            }
            else{
                $storages = $this->BP->get_backup_storage_name($strBids, $sid);
                $legalHoldInfo = $this->includeLegalHoldStatus(count($backup_ids)) ? $this->BP->get_legalhold_backup_info($strBids, $sid) : false;
            }
            if($legalHoldInfo !== false){
                foreach($legalHoldInfo as $info){
                    $legalHoldInfoData['holdRemaining'] = '';
                    if($info['is_on_hold'] == true) {
                        if($info['hold_days_backup'] != -1 && $info['hold_days_instance'] != -1) {
                            $remaining = $info['hold_expire_time'] - $info['curr_time'];
                            $days = intval(intval($remaining) / (3600*24));
                            if ($days == 1) {
                                $legalHoldInfoData['holdRemaining'] = $days . ' day remaining';
                            } else if ($days == -1) {
                                $legalHoldInfoData['holdRemaining'] = -$days . ' day past Hold ';
                            } else if ($days <-1){
                                $legalHoldInfoData['holdRemaining'] = -$days . ' days past Hold ';
                            } else {
                                $legalHoldInfoData['holdRemaining'] = $days . ' days remaining';
                            }
                        }
                    }
                    $legalHoldArray[$info['backup_no']] = array( 'legalHold' => $info['is_on_hold'], 'holdRemaining' => $legalHoldInfoData['holdRemaining']);
                }
            }

            foreach ($backups as &$backup) {
                $sid = ($this->showReplicatedAsRemote && $replicatedBackups) ? $localSystemID : $sid;
                if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                    if (!$this->Roles->backup_is_in_scope($backup, $sid)) {
                        //global $Log;
                        //$msg = "Backup " . $backup['id'] . " is NOT in restore scope.";
                        //$Log->writeVariable($msg);
                        continue;
                    }
                }

                //skip ibmr backups
                if (isset($backup['type']) && $backup['type'] == $this->INTEGRATED_BM_RESTORE) {
                    ;
                } else {
                    $day = isset($backup['start_time']) ? $this->functions->formatDate($backup['start_time']) : null;
                    $synthesized = isset($backup['synthesized']) ? $backup['synthesized'] : "n/a";
                    $type = isset($backup['type']) ? $this->functions->getBackupTypeDisplayName($backup['type'], $synthesized) : null;
                    $appName = isset($backup['app_name']) ? $backup['app_name'] : "";
                    $vmWareTemplate = isset($backup['vmware_template']) ? $backup['vmware_template'] : "n/a";
                    $xenTemplate = isset($backup['xen_template']) ? $backup['xen_template'] : "n/a";
                    $startDate = isset($backup['start_time']) ? $this->functions->formatDateTime($backup['start_time']) : null;
                    $replicated = $replicatedBackups ? true : (isset($backup['replicated']) ? $backup['replicated'] : "");
                    $encrypted = isset($backup['encrypted']) ? $backup['encrypted'] : "";
                    $dbName = isset($backup['database_name']) ? $backup['database_name'] : "";
                    $bid = isset($backup['id']) ? $backup['id'] : null;
                    $clientID = isset($backup['client_id']) ? $backup['client_id'] : null;
                    $clientName = $clientList[$backup['client_id']];
                    $storage = $backup['storage_name'] = $storages[$backup['id']];
                    $instanceID = isset($backup['instance_id']) ? $backup['instance_id'] : null;
                    $instanceName = $replicated ? $this->functions->getInstanceName($backup, $localSystemID, $clientName) : $this->functions->getInstanceName($backup, $sid, $clientName);
                    //oracle and sharepoint don't have db_name field, use instance_name
                    if ($dbName == "") {
                        $dbName = $instanceName;
                    }

                    if ($bid != null && isset($legalHoldArray[$backup['id']])) {
                        $legalHoldData = $legalHoldArray[$backup['id']];
                    } else {
                        $legalHoldData['legalHold'] = false;
                        $legalHoldData['holdRemaining'] = 'n/a';
                    }
                    $size = isset($backup['size']) ? $backup['size'] : "";
                    $status = isset($backup['status']) ? $backup['status'] : "";
                    $importedFromArchive = isset($backup['imported_from_archive']) ? $backup['imported_from_archive'] : "";
                    $complete = $backup['complete'];
                    $synth_capable = isset($backup['synth_capable']) ? $backup['synth_capable'] : "";
                    $app_id = isset($backup['app_id']) ? $backup['app_id'] : null;
                    $application_type = $this->getApplicationType($app_id, $backup['type']);
                    $certified = isset($backup['certified']) ? $backup['certified'] : 'n/a';

                    if ($application_type == "Physical Server" && $instanceID == "") {
                        $appName = Constants::APPLICATION_NAME_FILE_LEVEL;
                        $dbName = $instanceName = $clientName;
                    }
                    if ($complete === true) {
                        $backup = array(
                            'id' => $bid,
                            'type' => $type,
                            'start_date' => $startDate,
                            //'disks' => $disks,
                            'legal_hold' => $legalHoldData['legalHold'],
                            'hold' => $legalHoldData['holdRemaining'],
                            'status' => $status,
                            'replicated' => $replicated,
                            'encrypted' => $encrypted,
                            'vmware_template' => $vmWareTemplate,
                            'xen_template' => $xenTemplate,
                            'imported_from_archive' => $importedFromArchive,
                            'storage' => $storage,
                            'size' => $size,
                            'synth_capable' => $synth_capable,
                            'app_type' => $application_type,
                            'local_system_id' => $localSystemID,
                            'app_id' => $app_id,
                            'certified' => $certified
                        );
                        if ($replicated && $this->showReplicatedAsRemote) {
                            $backup['remote'] = true;
                            if ($this->targetName !== "") {
                                $backup['storage'] = $this->targetName;
                            }
                        }
                        $index = $this->encode_index($appName, $clientID, $clientName, $dbName, $instanceID, $instanceName, $application_type, $sid, $vmWareTemplate);
                        $items[$instanceName][$index][] = $backup;
                    }

                }
            }
	        $data = array();
            foreach ($items as $instanceID => $indices) {
                $instances = array();
                foreach ($indices as $index => $backups) {
                    $instance = $this->create_instance($index);
                    //sort backups with the most recent first
                    $backups = $this->sortBackups($backups);
                    $instance['backups'] = $backups;
                    $instances[] = $instance;
                }
                $data = array_merge($data, $instances);
            }
        } else {
            global $Log;
            $Log->writeError($this->BP->getError(), true);
        }
        return $data;
    }

    function catalogByStorage($result_format, $sid){
        $data = false;

        $backups = $this->BP->get_backup_status($result_format, $sid);

        if ($backups !== false) {
            $localSystemID = $this->BP->get_local_system_id();
	        $data = array();
            $items = array();
            foreach ($backups as $backup) {
                if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                    if (!$this->Roles->backup_is_in_scope($backup, $sid)) {
                        //global $Log;
                        //$msg = "Backup " . $backup['id'] . " is NOT in restore scope.";
                        //$Log->writeVariable($msg);
                        continue;
                    }
                }
                //skip ibmr backups
                if (isset($backup['type']) && $backup['type'] == $this->INTEGRATED_BM_RESTORE) {
                    ;
                } else {
                    // $day = isset($backup['start_time']) ? $this->functions->formatDate($backup['start_time']) : null;
                    $synthesized = isset($backup['synthesized']) ? $backup['synthesized'] : "n/a";
                    $type = isset($backup['type']) ? $this->functions->getBackupTypeString($backup['type'], $synthesized) : null;
                    $appName = isset($backup['app_name']) ? $backup['app_name'] : "";
                    $vmWareTemplate = isset($backup['vmware_template']) ? $backup['vmware_template'] : "n/a";
                    $xenTemplate = isset($backup['xen_template']) ? $backup['xen_template'] : "n/a";
                    $startDate = isset($backup['start_time']) ? $this->functions->formatDateTime($backup['start_time']) : null;
                    $replicated = isset($backup['replicated']) ? $backup['replicated'] : "";
                    $dbName = isset($backup['database_name']) ? $backup['database_name'] : "";
                    $bid = isset($backup['id']) ? $backup['id'] : null;
                    $clientID = isset($backup['client_id']) ? $backup['client_id'] : null;
                    $clientName = $this->getClientName($clientID, $replicated, $sid);
                    $disks = $this->getBackupDeviceInfo($bid, $sid);
                    $storage = $this->getStorageType($bid, $replicated, $sid);
                    $instanceID = isset($backup['instance_id']) ? $backup['instance_id'] : null;
                    $instanceName = $replicated ? $this->functions->getInstanceName($backup, $localSystemID, $clientName) : $this->functions->getInstanceName($backup, $sid, $clientName);
                    $legalHold = ($bid != null) ? $this->getLegalHoldBackup($bid, $sid) : "n/a";
                    // number of days till the legal hold on the backup expires
                    $holdRemaining = ($bid != null) ? $this->daysRemainingOnHold($bid, $sid) : "n/a";
                    $size = isset($backup['size']) ? $backup['size'] : "";
                    $status = isset($backup['status']) ? $backup['status'] : "";
                    $importedFromArchive = isset($backup['imported_from_archive']) ? $backup['imported_from_archive'] : "";
                    $complete = $backup['complete'];
                    $synth_capable = isset($backup['synth_capable']) ? $backup['synth_capable'] : "";
                    $app_id = isset($backup['app_id']) ? $backup['app_id'] : null;
                    $application_type = $this->getApplicationType($app_id, $backup['type']);
                    $certified = isset($backup['certified']) ? $backup['certified'] : 'n/a';
                    if ($complete === true) {
                        $backup = array(
                            'id' => $bid,
                            'type' => $type,
                            'start_date' => $startDate,
                            'disks' => $disks,
                            'legal_hold' => $legalHold,
                            'hold' => $holdRemaining,
                            'status' => $status,
                            'replicated' => $replicated,
                            'vmware_template' => $vmWareTemplate,
                            'xen_template' => $xenTemplate,
                            'imported_from_archive' => $importedFromArchive,
                            'storage' => $storage,
                            'size' => $size,
                            'synth_capable' => $synth_capable,
                            'app_type' => $application_type,
                            'app_id' => $app_id,
                            'certified' => $certified
                        );
                        if ($replicated && $this->showReplicatedAsRemote) {
                            $backup['remote'] = true;
                            if ($this->targetName !== "") {
                                $backup['storage'] = $this->targetName;
                            }
                        }
                        $index = $this->encode_index($appName, $clientID, $clientName, $dbName, $instanceID, $instanceName, $application_type, $sid, $vmWareTemplate);
                        $items[$storage][$index][] = $backup;
                    }
                }
            }

            foreach ($items as $storage => $indices) {
                $instances = array();
                foreach ($indices as $index => $backups) {
                    $instance = $this->create_instance($index);
                    //sort backups with the most recent first
                    $backups = $this->sortBackups($backups);
                    $instance['backups'] = $backups;
                    $instances[] = $instance;
                }
                $data[] = array('storage' => $storage, 'instances' => $instances);
            }
        } else {
            global $Log;
            $Log->writeError($this->BP->getError(), true);
        }
        return $data;
    }

    function catalogBySystem($result_format, $sid){
        $data = false;

        $backups = $this->BP->get_backup_status($result_format, $sid);
        //get replicated backups, too
        $result_format['grandclients'] = true;
        $replicatedBackups = $this->BP->get_backup_status($result_format, $sid);

        $backups = array_merge($backups, $replicatedBackups);


        $systemName = $this->getSystemName($sid);

        if ($backups !== false) {
            $localSystemID = $this->BP->get_local_system_id();
            $data = array();
            $items = array();
            foreach ($backups as $backup) {
                if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                    if (!$this->Roles->backup_is_in_scope($backup, $sid)) {
                        //global $Log;
                        //$msg = "Backup " . $backup['id'] . " is NOT in restore scope.";
                        //$Log->writeVariable($msg);
                        continue;
                    }
                }
                //skip ibmr backups
                if (isset($backup['type']) && $backup['type'] == $this->INTEGRATED_BM_RESTORE) {
                    ;
                } else {
                    $day = isset($backup['start_time']) ? $this->functions->formatDate($backup['start_time']) : null;
                    $synthesized = isset($backup['synthesized']) ? $backup['synthesized'] : "n/a";
                    $type = isset($backup['type']) ? $this->functions->getBackupTypeString($backup['type'], $synthesized) : null;
                    $appName = isset($backup['app_name']) ? $backup['app_name'] : "";
                    $vmWareTemplate = isset($backup['vmware_template']) ? $backup['vmware_template'] : "n/a";
                    $xenTemplate = isset($backup['xen_template']) ? $backup['xen_template'] : "n/a";
                    $startDate = isset($backup['start_time']) ? $this->functions->formatDateTime($backup['start_time']) : null;
                    $replicated = isset($backup['replicated']) ? $backup['replicated'] : "";
                    $encrypted = isset($backup['encrypted']) ? $backup['encrypted'] : "";
                    $dbName = isset($backup['database_name']) ? $backup['database_name'] : "";
                    $bid = isset($backup['id']) ? $backup['id'] : null;
                    $clientID = isset($backup['client_id']) ? $backup['client_id'] : null;
                    $clientName = $this->getClientName($clientID, $replicated, $sid);
                    $disks = $this->getBackupDeviceInfo($bid, $sid);
                    $storage = $this->getStorageType($bid,$replicated, $sid);
                    $instanceID = isset($backup['instance_id']) ? $backup['instance_id'] : null;
                    $instanceName = $replicated ? $this->functions->getInstanceName($backup, $localSystemID, $clientName) : $this->functions->getInstanceName($backup, $sid, $clientName);
                    $legalHold = ($bid != null) ? $this->getLegalHoldBackup($bid, $sid) : "n/a";
                    // number of days till the legal hold on the backup expires
                    $holdRemaining = ($bid != null) ? $this->daysRemainingOnHold($bid, $sid) : "n/a";
                    $size = isset($backup['size']) ? $backup['size'] : "";
                    $importedFromArchive = isset($backup['imported_from_archive']) ? $backup['imported_from_archive'] : "";
                    $complete = $backup['complete'];
                    $synth_capable = isset($backup['synth_capable']) ? $backup['synth_capable'] : "";
                    $app_id = isset($backup['app_id']) ? $backup['app_id'] : null;
                    $application_type = $this->getApplicationType($app_id, $backup['type']);
                    $certified = isset($backup['certified']) ? $backup['certified'] : 'n/a';
                    if ($complete === true) {
                        $backup = array(
                            'id' => $bid,
                            'type' => $type,
                            'start_date' => $startDate,
                            'disks' => $disks,
                            'legal_hold' => $legalHold,
                            'hold' => $holdRemaining,
                            'replicated' => $replicated,
                            'encrypted' => $encrypted,
                            'vmware_template' => $vmWareTemplate,
                            'xen_template' => $xenTemplate,
                            'imported_from_archive' => $importedFromArchive,
                            'storage' => $storage,
                            'size' => $size,
                            'synth_capable' => $synth_capable,
                            'app_type' => $application_type,
                            'app_id' => $app_id,
                            'certified' => $certified
                        );
                        if ($replicated && $this->showReplicatedAsRemote) {
                            $backup['remote'] = true;
                            if ($this->targetName !== "") {
                                $backup['storage'] = $this->targetName;
                            }
                        }
                        $index = $this->encode_index($appName, $clientID, $clientName, $dbName, $instanceID, $instanceName, $application_type, $sid, $vmWareTemplate);
                        $items[$systemName][$index][] = $backup;
                    }
                }
            }

            foreach ($items as $systemName => $indices) {
                $instances = array();
                foreach ($indices as $index => $backups) {
                    $instance = $this->create_instance($index);
                    //sort backups with the most recent first
                    $backups = $this->sortBackups($backups);
                    $instance['backups'] = $backups;
                    $instances[] = $instance;
                }
                $data[] = array('System' => $systemName, 'instances' => $instances);
            }
        } else {
            global $Log;
            $Log->writeError($this->BP->getError(), true);
        }
        return $data;
    }
    function encode_index($appName, $clientID, $clientName, $dbName, $instanceID, $instanceName, $application_type, $sid, $vmwareTemplate) {
        return $appName . '^xyzxyz^' . $clientID . '^xyzxyz^' . $clientName . '^xyzxyz^' . $dbName . '^xyzxyz^'
                . $instanceID . '^xyzxyz^' . $instanceName . '^xyzxyz^' . $application_type . '^xyzxyz^' . $sid . '^xyzxyz^' . $vmwareTemplate;
    }

    function create_instance($index) {
        $instance = array();

        $a = explode('^xyzxyz^', $index);
      //  print_r($a);
        $instance = array(
            'app_name' => $a[0],
            'client_id' => $a[1],
            'client_name' => $a[2],
            'database_name' => $a[3],
            'instance_id' => $a[4],
            'instance_name' => $a[5],
            'app_type' => $a[6],
            'system_id' => $a[7],
            'system_name' => $this->getSystemName($a[7]),
            'vmware_template' => $a[8]
        );
        return $instance;
    }

    // Sorts the backups in descending order of the 'start_date'
    function sortBackups($backupArr) {
        $backups = $backupArr;
        $orderByStartDate = array();
        foreach ($backups as $key => $row) {
            $orderByStartDate[$key] = $this->functions->dateTimeToTimestamp($row['start_date']);
        }
        array_multisort($orderByStartDate, SORT_DESC, $backups);
        return $backups;
    }

    function getLegalHold($iid, $sid){
        $input = array('instance_id'=>$iid);
        $retentionSettings = $this->BP->get_retention_settings($input, $sid);
        $hold = isset($retentionSettings['legal_hold']) ? $retentionSettings['legal_hold']: null;

        $data = $hold == 0 ? false : true;

        return $data;
    }

    function getLegalHoldBackup($bid, $sid){
        $data = false;
        $bids = (string)$bid;
        $result = $this->BP->get_legalhold_backup_info($bids, $sid);
        if ($result !== false) {
            foreach ($result as $backup) {
                if ($backup['backup_no'] == $bid) {
                    $data['legalHold'] = $backup['is_on_hold'];
                    if($backup['is_on_hold'] == true) {
                        if($backup['hold_days_backup'] != -1) {
                            $remaining = $backup['hold_expire_time'] - $backup['curr_time'];
                            $days = intval(intval($remaining) / (3600*24));
                            if ($days == 1) {
                                $data['holdRemaining'] = $days . ' day remaining';
                            } else if ($days == -1) {
                                $data['holdRemaining'] = -$days . ' day past Hold ';
                            } else if ($days < -1){
                                $data['holdRemaining'] = -$days . ' days past Hold ';
                            } else {
                                $data['holdRemaining'] = $days . ' days remaining';
                            }
                            break;
                        }
                    }
                }
            }
        }
        return $data;
    }

    function daysRemainingOnHold($bid, $sid){
        $data = "";
        $bids = (string)$bid;
        $result = $this->BP->get_legalhold_backup_info($bids, $sid);
        if ($result !== false) {
            foreach ($result as $backup) {
                if ($backup['backup_no'] == $bid) {
                    if($backup['is_on_hold'] == true) {
                        if($backup['hold_days_backup'] != -1 && $backup['hold_days_instance'] != -1) {
                            $remaining = $backup['hold_expire_time'] - $backup['curr_time'];
                            $days = intval(intval($remaining) / (3600*24));
                            if ($days == 1) {
                                $data = $days . ' day remaining';
                            } else if ($days == -1) {
                                $data = -$days . ' day past Hold  ';
                            } else if ($days <-1){
                                $data = -$days . ' days past Hold  ';
                            } else {
                                $data = $days . ' days remaining';
                            }
                            break;
                        }
                    }
                }
            }
        }
        return $data;
    }

    function getStorageType($bid, $replicated, $sid){
        $data = array();

        $sid = $replicated ? $this->BP->get_local_system_id() : $sid;

        $backup_storages = $this->BP->get_backup_storage_name((string)$bid, $sid);
        if ($backup_storages !== false) {
            $data = $backup_storages[$bid];
        }
        return $data;
    }

    function merge_view($a1, $a2, $view){
        $result = array();
        if (is_array($a1) && count($a1) > 0 && is_array($a2) && count($a2) > 0) {
            if ($view != 'day') {
                $result = array_merge($a1, $a2);
            } else {
                // merge days that overlap, and add others that do not.
                $len1 = count($a1);
                for ($i = 0; $i < $len1; $i++) {
                    $day1 = $a1[$i]['day'];
                    $len2 = count($a2);
                    for ($j = 0; $j < $len2; $j++) {
                        $day2 = $a2[$j]['day'];
                        if (strcmp($day1, $day2) == 0) {
                            if ((is_array($a1[$i])) and is_array(($a2[$j]))) {
                                    $merged = array_merge($a1[$i]['instances'], $a2[$j]['instances']);
                                    $result[] = array('day' => $day2, 'instances' => $merged);
                                    unset($a1[$i]);
                                    unset($a2[$j]);
                            }

                        }
                    }
                }
                // the leftovers do not have day matches.
                $result = array_merge($result, $a1);
                $result = array_merge($result, $a2);
            }
        } elseif (is_array($a1) && count($a1) > 0) {
            $result = $a1;
        } elseif (is_array($a2) && count($a2) > 0) {
            $result = $a2;
        }
        return ($result);
    }

    function getApplicationType($appID, $type)
    {
        require_once('function.lib.php');
        $function = new Functions($this->BP);
        $app_type = "";
        if ($appID !== null) {
            $app_type = $function->getApplicationTypeFromApplictionID($appID);
            if ($app_type === Constants::APPLICATION_TYPE_NAME_FILE_LEVEL) {
                $app_type = 'Physical Server';
            } else if ($app_type === Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL) {
                $app_type = 'Physical Server';
            }
        } else {
            if ($type !== null && ($function->isBackupFileLevel($type) || ($type === Constants::BACKUP_TYPE_BAREMETAL))) {
                $app_type = 'Physical Server';
            }
        }
        return $app_type;
    }

    function getRelatedBackupsOnTarget($which, $filter, $sid) {
        $data = array();
        $request = "GET";
        $api = "/api/backups/related/" . $filter . "/";
        $parameters = "original";
        $result = $this->functions->remoteRequest("", $request, $api, $parameters, NULL, 1);
        if (is_array($result)) {
            $results = $result['data'];
            $target = $result['target_name'];
            foreach ($results as $backup) {
                $backup['remote'] = true;
                if (isset($backup['status'])) {
                    if ($backup['status'] == 'Successful') {
                        $backup['success'] = true;
                    } else if ($backup['status'] == 'Failed') {
                        $backup['success'] = false;
                    }
                }
                $backup['instance_description'] = $this->buildInstanceName($backup);
                $backup['backup_date'] = $backup['start_time'];
                $backup['date'] = $this->getCopyDate($backup);
                $backup['storage'] = $target;
                $data[] = $backup;
            }
        }
        return $data;
    }

    function getDependentBackupsOnTarget($which, $filter, $sid) {
        $data = array();
        $request = "GET";
        $api = "/api/backups/dependent/" . $filter . "/";
        $parameters = "original";
        $result = $this->functions->remoteRequest("", $request, $api, $parameters, NULL, 1);
        if (is_array($result)) {
            $results = $result['data'];
            $target = $result['target_name'];
            foreach ($results as $backup) {
                $backup['remote'] = true;
                if (isset($backup['status'])) {
                    if ($backup['status'] == 'Successful') {
                        $backup['success'] = true;
                    } else if ($backup['status'] == 'Failed') {
                        $backup['success'] = false;
                    }
                }
                $backup['instance_description'] = $this->buildInstanceName($backup);
                $backup['backup_date'] = $backup['start_time'];
                $backup['date'] = $this->getCopyDate($backup);
                $backup['storage'] = $target;
                $data[] = $backup;
            }
        }
        return $data;
    }

    function getBackupFilesOnTarget($filter, $sid, $synthesized = true) {

        $start = "";
        $last = "";
        if (isset($_GET['dir'])) {
            $start = $_GET['dir'];
            // encode '#' and '&' so that they don't get parsed during remote request
            $start = str_replace('#', '%23', $start);
            $start = str_replace('&', '%26', $start);
            $start = "dir=" . $start;
        }
        if (isset($_GET['last'])) {
            $last = $_GET['last'];
            // encode '#' and '&' so that they don't get parsed during remote request
            $last = str_replace('#', '%23', $last);
            $last = str_replace('&', '%26', $last);
            $last = "&last=" . $last;
        }
        $count = isset($_GET['count']) ? ("&count=" . $_GET['count']) : "";
        $api = $synthesized ? "/api/backups/synthesized-files/" : "/api/backups/files/";
        // Filter is the backup ID.
        $api .= $filter . "/";

        $data = array();
        $request = "GET";
        $parameters = $start . $last . $count;
        $result = $this->functions->remoteRequest("", $request, $api, $parameters, NULL, 1);
        if (is_array($result)) {
            $result['remote'] = true;
            $data = $result;
        }
        return $data;
    }

    private function buildInstanceName($backup) {
        $clientName = isset($backup['client_name']) ? $backup['client_name'] : '';
        $instanceName = isset($backup['vm_name']) ? $backup['vm_name'] :
                        isset($backup['database_name']) ? $backup['database_name'] : "";

        if ($clientName != "") {
            $instanceName = $clientName . " " . $instanceName;
        }
        return $instanceName;
    }

    private function getCopyDate($backup) {
        $date = null;
        if (isset($backup['output'])) {
            $output = $backup['output'];
            $a = explode(' ', $output);
            if (count($a) > 1) {
                $date = $a[0] . ' ' . $a[1];
            }
        }
        return $date;
    }

    private function getBackupsOnTarget($cid, $iid, $bid, $copies, $startDate, $endDate, $sid) {
        $data = array();
        $request = "GET";
        $api = "/api/backups/";
        $parameters = "";
        $sep = "&";
        if ($cid !== null) {
            $parameters .= $sep . "cid=" . $cid;
            $sep = "&";
        }
        if ($iid !== null) {
            $parameters .= $sep . "iid=" . $iid;
        }
        if ($bid !== null) {
            $parameters .= $sep . "bid=" . $bid;
        }
        $result = $this->functions->remoteRequest("", $request, $api, $parameters, NULL, 1);
        if (is_array($result)) {
            $result['remote'] = true;
            $data = $result;
        }
        return $data;
    }


    /**
     * @param $payload
     * @param $appType
     * @param $sid
     * @return mixed
     */
    public function getBackupsForMultipleRestore($payload, $appType, $sid)
    {
        if ($appType !== null) {
            $appType = rawurldecode($appType);

            switch ($appType) {
                case Constants::APPLICATION_TYPE_NAME_SQL_SERVER:
                case "sql":
                    $data = $this->getBackupsForSQLMultipleRestore($payload, $appType, $sid);
                    break;
                default:
                    $data['error'] = 500;
                    $data['message'] = $appType ." is not supported for multiple restore.";
                    break;
            }
        } else {
            $data['error'] = 500;
            $data['message'] = "Application type is required.";
        }
        return $data;
    }

    function getBackupsForSQLMultipleRestore($payload, $appType, $sid){
        $valid = array();
        $invalid = array();
        $systemDBs = array();
        $alwaysonDBs = array();
        $allValid = array();
        $allInvalid = array();
        $allSystemDBs = array();
        $allAlwaysonDBs = array();
        $remaining = array();

            $sid = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
            $bids = array_map('intval', explode(",", $payload['backup_ids']));

            $result_format = array(
                'system_id' => $sid,
                'backup_ids' => $bids
            );

            $backupStatus = $this->BP->get_backup_status($result_format);

            if ($backupStatus !== false) {
                foreach ($backupStatus as $key => $backup) {
                    $isSQLSystem = $this->BP->is_sql_system_db($backup['instance_id'], $sid);
                    $isSecuresync = $this->functions->isBackupTypeSecuresync($backup['type']);
                    // do not display securesync backups
                    if (!$isSecuresync) {
                        if ($isSQLSystem) {
                            $allSystemDBs[] = array_merge($systemDBs, $this->buildBackupsForMultipleRestoresArrays($backup, $appType));
                            unset($backupStatus[$key]);
                        } else if (isset($backup['is_sql_alwayson']) && $backup['is_sql_alwayson']) {
                            $allAlwaysonDBs[] = array_merge($alwaysonDBs, $this->buildBackupsForMultipleRestoresArrays($backup, $appType));
                            unset($backupStatus[$key]);
                        } else {
                            $remaining[] = $backup;
                        }
                    }
                }

                if (is_array($remaining) && !empty($remaining)) {
                    $remaining = $this->sortBackupsByDatabase($remaining);
                    list($conflicts, $noConflicts) = ($this->getConflictingBackups($remaining));

                    foreach ($conflicts as $conflict) {
                        $allInvalid[] = array_merge($invalid, $this->buildBackupsForMultipleRestoresArrays($conflict, $appType));
                    }

                    foreach ($noConflicts as $noConflict) {
                        $allValid[] = array_merge($valid, $this->buildBackupsForMultipleRestoresArrays($noConflict, $appType));
                    }
                }
            }

            $validReturn['valid'] = $allValid;
            $systemDBReturn['systemDBs'] = $allSystemDBs;
            $systemDBReturn['alwaysonDBs'] = $allAlwaysonDBs;
            $invalidReturn['invalid'] = $allInvalid;

            $data['data'] = array_merge($validReturn, $systemDBReturn, $invalidReturn);

        return $data;
    }


    function sortBackupsByDatabase($backupArr) {
        $backups = $backupArr;
        $orderByDatabase = array();
        $orderByType = array();
        $orderByDate = array();

        foreach ($backups as $key => $row) {
            $orderByDatabase[$key] = $row['database_name'];
            $orderByType[$key] = $row['type'];
            $orderByDate[$key] = $row['start_time'];
        }
        array_multisort($orderByDatabase, SORT_DESC, $orderByType, SORT_ASC, $orderByDate, SORT_DESC, $backups);
        return $backups;
    }

    function getConflictingBackups($backupsArr)
    {
        $conflicts = array();
        $count = count($backupsArr);

        $i = 0;
        while ($i < $count) {
            while (isset($backupsArr[$i]) && isset($backupsArr[$i + 1]) && $backupsArr[$i]['type'] == $backupsArr[$i + 1]['type'] &&
                $backupsArr[$i]['database_name'] == $backupsArr[$i + 1]['database_name'] &&
                $backupsArr[$i]['server_instance_name'] == $backupsArr[$i + 1]['server_instance_name']) {
                $conflicts[] = $backupsArr[$i + 1];
                unset($backupsArr[$i + 1]);
                $backupsArr = array_values($backupsArr);
            }
            $i++;
        }

        return array($conflicts, $backupsArr);
    }

    function buildBackupsForMultipleRestoresArrays($arrayToProcess, $appType) {

        $result = array();
            $backupID = isset($arrayToProcess['id']) ? $arrayToProcess['id'] : null;
            $clientID = isset($arrayToProcess['client_id']) ? $arrayToProcess['client_id'] : null;
            $backupType = isset($arrayToProcess['type']) ? $this->functions->getBackupTypeDisplayName($arrayToProcess['type']) : null;
            $backupDateTime = isset($arrayToProcess['start_time']) ? $this->functions->formatDateTime($arrayToProcess['start_time']) : null;
            $status = isset($arrayToProcess['status']) ? $arrayToProcess['status'] : null;
            $size = isset($arrayToProcess['size']) ? $arrayToProcess['size'] : null;
            $instanceID = isset($arrayToProcess['instance_id']) ? $arrayToProcess['instance_id'] : null;
            $appID = isset($arrayToProcess['app_id']) ? $arrayToProcess['app_id'] : null;
            $appName = isset($arrayToProcess['app_name']) ? $arrayToProcess['app_name'] : null;
            $instanceName = isset($arrayToProcess['server_instance_name']) ? $arrayToProcess['server_instance_name'] : null;
            $dbName = isset($arrayToProcess['database_name']) ? $arrayToProcess['database_name'] : null;

            $processedArray = array(
                'app_type' => $appType,
                'backup_id' => $backupID,
                'client_id' => $clientID,
                'type' => $backupType,
                'date' => $backupDateTime,
                'status' => $status,
                'size' => $size,
                'instance_id' => $instanceID,
                'app_id' => $appID,
                'app_name' => $appName,
                'instance_name' => $instanceName,
                'database_name' => $dbName,
             );

        $data = array_merge($result, $processedArray);

        return $data;
    }

    /*
     * check inclusions of legal hold backup status, turning if off if the number of backups is above a threshold.
     */
    public function includeLegalHoldStatus($backupCount = 0) {
        if ($this->showLegalHoldInCatalog) {
            if ($backupCount >= self::MAX_BACKUPS_LEGAL_HOLD_INFO) {
                $this->showLegalHoldInCatalog = false;
            }
        }
        return $this->showLegalHoldInCatalog;
    }

}   //end backups class

?>
