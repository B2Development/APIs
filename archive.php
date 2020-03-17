<?php

class Archive
{
    private $BP;
    const MORE_FILES = "";
    const MORE_FILES_ID = "...";

    public function __construct($BP, $sid = null, $Roles = null)
    {
        $this->BP = $BP;
        $this->sid = $sid;
        $this->FUNCTIONS = new Functions($BP);
        $this->Roles = $Roles;
    }

    public function get($which, $data, $sid, $systems) {
        switch($which[0]) {
            case 'media':
                switch($which[1]) {
                    case 'connected':
                        $status = $this->BP->get_connected_archive_media($sid);
                        if($status !== false) {
                            $status = array('data' => $status);
                        }
                        break;
                    case 'candidates':
                        $status = $this->BP->get_possible_archive_media($sid);
                        if($status !== false) {
                            $status = array('data' => $status);
                        }
                        break;
                    case 'sets':
                        $slots = "";
                        if(isset($data['slots'])) {
                            $slots = $data['slots'];
                        }
                        $sets = $this->BP->get_media_archive_sets(urldecode($which[2]), $slots, $sid);
                        $status['sets'] = array();
                        if($sets !== false) {
                            foreach($sets as $set) {
                                $outSet['date'] = $this->FUNCTIONS->formatDateTime($set['timestamp']);
                                $outSet['description'] = $set['description'];
                                $outSet['needs_import'] = $set['needs_import'];
                                $status['sets'][] = $outSet;
                            }
                        } else {
                            $status = $sets;
                        }
                        break;
                    case 'configurable':
                        $status = array();
                        $mediaArray = array();
                        foreach($systems as $id => $sname) {
                            $media = $this->BP->get_configurable_archive_media($id);
                            foreach($media as $item) {
                                $mediaItem = array();
                                $mediaItem['sid'] = $id;
                                $mediaItem['system_name'] = $sname;
                                $mediaItem['name'] = $item['name'];
                                $mediaItem['vendor'] = $item['vendor'];
                                $mediaItem['model'] = $item['model'];
                                $mediaItem['serial'] = $item['serial'];
                                $mediaItem['type'] = $item['type'];
                                $mediaItem['is_available'] = $item['is_available'];

                                $mediaArray[] =  $mediaItem;
                            }
                        }
                        $status['data'] =  $mediaArray;
                        break;

                    case 'settings':
                        $devName = isset($which[2]) ? urldecode($which[2]) : "";
                        $status['data'] = $this->BP->get_archive_media_settings($devName, $sid);
                        break;

                    case 'library':
                        if(isset($which[2])) {
                            $mediaName = urldecode($which[2]);
                            $tapeInfo = $this->getTapeLibraryInfo($mediaName, $sid);
                            if(isset($tapeInfo['error'])) {
                                $status = $tapeInfo;
                            } else {
                                $status['data'] = $tapeInfo;
                            }
                        } else {
                            $status['error'] = 500;
                            $status['message'] = 'Device name must be specified.';
                        }
                        break;
                }
                break;

            case 'sets':
                $allSets = array();
                if(!isset($which[1])) {
                    foreach($systems as $sid => $name) {
                        $sets = $this->getSets($data, $sid);
                        for($i = 0; $i < count($sets); $i++) {
                            $sets[$i]['id'] = $sets[$i]['archive_set_id'];
                            unset($sets[$i]['archive_set_id']);
                            $sets[$i]['date'] = $this->FUNCTIONS->formatDateTime($sets[$i]['timestamp']);
                            unset($sets[$i]['timestamp']);
                            $sets[$i]['sid'] = $sid;
                            $sets[$i]['system_name'] = $name;
                        }
                        $allSets = array_merge($allSets, $sets);
                    }
                } else {
                    $sets = $this->getSets($data, $sid);
                    if($sets != false) {
                        foreach($sets as $set) {
                            if(is_numeric($which[1]) and $which[1] != $set['archive_set_id']) {
                                continue;
                            } else {
                                $setInfo = $this->BP->get_archive_set_info($set['archive_set_id'], $sid);
                                $setInfo['id'] = $set['archive_set_id'];
                                $setInfo['elapsed_time'] = $this->FUNCTIONS->formatTimeDelta($setInfo['elapsed_secs']);
                                unset($setInfo['elapsed_secs']);
                                $setInfo['date'] = $this->FUNCTIONS->formatDateTime($setInfo['timestamp']);

                                //profile info
                                if($setInfo['profile']['range_end'] == 0) {
                                    if($setInfo['profile']['range_size'] != 0) {
                                        $setInfo['profile']['start_date'] = $this->FUNCTIONS->formatDateTime($setInfo['timestamp'] - $setInfo['profile']['range_size']);
                                    } else {
                                        $setInfo['profile']['start_date'] = 0;
                                    }
                                    $setInfo['profile']['end_date'] = 0;
                                } else {
                                    if($setInfo['profile']['range_size'] != 0) {
                                        $setInfo['profile']['start_date'] = $this->FUNCTIONS->formatDateTime($setInfo['profile']['range_end'] - $setInfo['profile']['range_size']);
                                    } else {
                                        $setInfo['profile']['start_date'] = 0;
                                    }
                                    $setInfo['profile']['end_date'] = $this->FUNCTIONS->formatDateTime($setInfo['profile']['range_end']);
                                }
                                unset($setInfo['profile']['range_end']);
                                unset($setInfo['profile']['range_size']);
                                unset($setInfo['timestamp']);
                                if(isset($setInfo['profile']['clients'])) {
                                    $clients = array();
                                    foreach($setInfo['profile']['clients'] as $client) {
                                        $clientArr = array();
                                        $clientArr['id'] = $client;
                                        $clientInfo = $this->BP->get_client_info($client, $sid);
                                        $clientArr['name'] = $clientInfo['name'];
                                        $clients[] = $clientArr;
                                    }
                                    $setInfo['profile']['clients'] = $clients;
                                }
                                if(isset($setInfo['profile']['instances'])) {
                                    $instances = array();
                                    foreach($setInfo['profile']['instances'] as $instance) {
                                        $instanceArr = array();
                                        $instanceArr['id'] = $instance;
                                        $instanceInfo = $this->BP->get_appinst_info($instance, $sid);
                                        $instanceArr['primary_name'] = $instanceInfo[$instance]['primary_name'];
                                        if(isset($instanceInfo[$instance]['secondary_name'])) {
                                            $instanceArr['secondary_name'] = $instanceInfo[$instance]['secondary_name'];
                                        }
                                        $instances[] = $instanceArr;
                                    }
                                    $setInfo['profile']['instances'] = $instances;
                                }
                                if(isset($setInfo['profile']['client_objects'])) {
                                    $setInfo['profile']['objects'] = $setInfo['profile']['client_objects'];
                                    unset($setInfo['profile']['client_objects']);
                                }
                                $typeArr = array();
                                foreach($setInfo['profile']['types'] as $type) {
                                    $displayType = $this->FUNCTIONS->getBackupTypeDisplayName($type);
                                    if(!in_array($displayType, $typeArr)) {
                                        $typeArr[] = $displayType;
                                    }
                                }
                                $setInfo['profile']['types'] = $typeArr;
                                $allSets[] = $setInfo;
                            }
                        }
                    }
                }
                if($allSets !== false) {
                    $status = array('sets' => $allSets);
                } else {
                    $status = $allSets;
                }
                break;
            case 'catalog':
                //get archive status first
                $cid = isset($_GET['cid']) ? (int)$_GET['cid'] : false;
                $iid = isset($_GET['iid']) ? (int)$_GET['iid'] : false;
                $startDate = isset($_GET['start_date']) ? strtotime($_GET['start_date']) : strtotime(Constants::DATE_ONE_WEEK_AGO);
                $endDate = isset($_GET['end_date']) ? $this->FUNCTIONS->formatEndDate($_GET['end_date']) : strtotime('now');
                $startBackupDate = isset($_GET['start_backup_date']) ? strtotime($_GET['start_backup_date']) : false;
                $endBackupDate = isset($_GET['end_backup_date']) ? strtotime($_GET['start_backup_date']) : false;
                $view = isset($_GET['view']) ? $_GET['view'] : "system";

                $result_format['job_start_time'] = $startDate;
                $result_format['job_end_time'] = $endDate;

                if($cid){
                    $result_format['client_id'] = $cid;
                }
                if($iid) {
                    $result_format['instance_id'] = $iid;
                }
                if($startBackupDate) {
                    $result_format['backup_start_time'] = $startBackupDate;
                }
                if($endBackupDate) {
                    $result_format['backup_end_time'] = $endBackupDate;
                }

                if ($sid == false) {
                    $systems = $this->FUNCTIONS->selectSystems();
                    $data["catalog"] = array();
                    foreach ($systems as $sid => $name) {
                        $result_format['system_id'] = $sid;
                        if ($view !== false){
                            $data["catalog"] = $this->merge_view($data["catalog"], $this->getArchivesByView($view, $result_format, $sid), $view);
                        }
                    }
                } else {
                    $result_format['system_id'] = $sid;
                    if ($view !== false){
                        $data["catalog"] = $this->getArchivesByView($view, $result_format, $sid);
                    }
                }
                if ($view == 'day' && !empty($data['catalog'])) {
                    // If day view, sort all days in the catalog.
                    foreach ($data["catalog"] as $key => $row) {
                        $orderByDate[$key] = strtotime($row['day']);
                    }
                    array_multisort($orderByDate, SORT_DESC, $data["catalog"]);
                }
                $status = $data;
                break;
            case 'files':
                $start = isset($_GET['dir']) ? $_GET['dir'] : "";
                $last = isset($_GET['last']) ? $_GET['last'] : "";
                $count = isset($_GET['count']) ? (int)$_GET['count'] : 0;
                $files = $this->BP->get_archive_files((int)$which[1], $start, $last, $count, $sid);
                if($files != false) {
                    for($i = 0; $i < count($files); $i++) {
                        unset($files[$i]['parent']);
                        unset($files[$i]['size']);
                        unset($files[$i]['date']);
                        if(array_key_exists('has_children', $files[$i])) {
                            $files[$i]['branch'] = $files[$i]['has_children'];
                            unset($files[$i]['has_children']);
                        }
                        if($files[$i]['type'] == "directory" or $files[$i]['type'] == "volume") {
                            $files[$i]['id'] .= "/";
                        }
                    }
                    if ($count > 0 && ($count === count($files))) {
                        $directory = $files[$count - 1]['directory'];
                        $lastFile = $files[$count - 1]['name'];
                        $files[] = $this->addFilePlaceholder($directory, $lastFile);
                    }
                    $status = array();
                    $status['data'] = $files;
                    $status['count'] = $count;
                    $status['total'] = count($files);
                } else {
                    $status = $files;
                }
                break;
            case 'related':
                if(is_numeric($which[1])) {
                    if(isset($_GET['instance'])) {
                        $pitArchiveID = array();
                        $pitArchiveID[] = (int)$which[1];
                        $instance = $_GET['instance'];
                        if(is_numeric($instance)) {
                            $filter = array('instance_id' => (int)$instance, 'archive_ids' => $pitArchiveID);
                        } else {
                            $clientID = isset($_GET['clientID']) ? (int)$_GET['clientID'] : 0;
                            if ($clientID !== 0) {
                                $filter = array('client_id' => $clientID, 'archive_ids' => $pitArchiveID);
                            } else {
                                $filter = array('archive_ids' => $pitArchiveID);
                            }
                        }
                        //get status for first archive id - backup time is needed
                        $archiveInfo = $this->BP->get_archive_status($filter, $sid);
                        if($archiveInfo !== false) {
                            if(count($archiveInfo) > 0) {
                                if($archiveInfo[0]['type'] !== "localdir") {
                                    $instanceName = "";
                                    $instanceDesc = "";
                                    if(!is_numeric($instance) and $instance !== $archiveInfo[0]['client_name']) {
                                        //if we were given the instance name, then see if it matches the return.
                                        $instanceDesc = $archiveInfo[0]['instance_description'];
                                        $pieces = explode('"', $instanceDesc);
                                        if(count($pieces) > 1) {
                                            if($archiveInfo[0]['app_name'] == "VMware"){
                                                $instanceName = $pieces[1] ."|" . $pieces[3];
                                            } else {
                                                $instanceName = $pieces[1] ."|" . $pieces[3];
                                            }
                                        }
                                    } else {
                                        $instanceName = $instance;
                                    }
                                    //check to make sure the instance name matches the description of the returned instance
                                    if(is_numeric($instance) or $instanceName == $instance or ($archiveInfo[0]['app_name'] == 'file-level' and $instanceName == $archiveInfo[0]['client_name'])) {
                                        //if a full, then that's all we need and can send back just this id as the group
                                        if(strtolower($this->FUNCTIONS->getBackupTypeDisplayName($archiveInfo[0]['type'])) == "full" or strtolower($this->FUNCTIONS->getBackupTypeDisplayName($archiveInfo[0]['type'])) == "selective") {
                                            $status = array("ids" => (string)$which[1]);
                                        } else {
                                            //else use instance and backup time to get archives prior to point in time
                                            unset($filter['archive_ids']);
                                            $backupEndTime = $archiveInfo[0]['backup_time'];
                                            $archiveSetId = $archiveInfo[0]['archive_set_id'];
                                            $filter['backup_end_time'] = $backupEndTime;
                                            if (in_array('serial_no',$archiveInfo[0])) {
                                                $archiveSerial = $archiveInfo[0]['serial_no'];
                                            } else {
                                                $archiveSerial = null;
                                            }

                                            $target = $this->getArchiveInfoTarget($archiveInfo[0], $sid);
                                            if ($target !== false) {
                                                // Only get archives from the current media target.
                                                $filter['target'] = $target;
                                            }
                                            $allArchiveStatus = $this->BP->get_archive_status($filter, $sid);
                                            if($allArchiveStatus !== false) {
                                                //find most recent full
                                                $mostRecentFullID = -1;
                                                $mostRecentFullBackupTime = 0;
                                                for($i = 0; $i < count($allArchiveStatus); $i++) {
                                                    if($allArchiveStatus[$i]['backup_time'] <= $backupEndTime) {
                                                        if(strtolower($this->FUNCTIONS->getBackupTypeDisplayName($allArchiveStatus[$i]['type'])) == "full" and $allArchiveStatus[$i]['backup_time'] >= $mostRecentFullBackupTime) {
                                                            if(is_numeric($instance) or $instanceDesc == $allArchiveStatus[$i]['instance_description'] or ($allArchiveStatus[$i]['app_name'] == 'file-level' and $instanceName == $allArchiveStatus[$i]['client_name'])) {
                                                                $mostRecentFullID = $allArchiveStatus[$i]['archive_id'];
                                                                $mostRecentFullBackupTime = $allArchiveStatus[$i]['backup_time'];
                                                                $mostRecentFullSet = $allArchiveStatus[$i]['archive_set_id'];
                                                                // first check for archives in same set or same serial_no
                                                                if ($archiveSetId == $mostRecentFullSet) {
                                                                   break;
                                                                }
                                                                if (in_array('serial_no',$allArchiveStatus[$i])) {
                                                                   if($archiveSerial == $allArchiveStatus[$i]['serial_no']) {
                                                                     break;
                                                                   }
                                                                } 
                                                            }
                                                        }
                                                    }
                                                }
                                                if($mostRecentFullID != -1) {
                                                    //get all archive ids between the given id and the most recent full
                                                    $archiveIDTime = array($which[1] => $backupEndTime, $mostRecentFullID => $mostRecentFullBackupTime);
                                                    //if the chosen id is a differential, then all we need is the previous full
                                                    if(strtolower($this->FUNCTIONS->getBackupTypeDisplayName($archiveInfo[0]['type'])) == "differential") {
                                                        $archiveIDTime[$archiveInfo[0]['archive_id']] = $archiveInfo[0]['backup_time'];
                                                    } else {
                                                        for($i = 0; $i < count($allArchiveStatus); $i++) {
                                                            if($allArchiveStatus[$i]['backup_time'] > $mostRecentFullBackupTime and $allArchiveStatus[$i]['backup_time'] < $backupEndTime) {
                                                                if(is_numeric($instance) or $instanceDesc == $allArchiveStatus[$i]['instance_description'] or ($allArchiveStatus[$i]['app_name'] == 'file-level' and $instanceName == $allArchiveStatus[$i]['client_name'])) {
                                                                    $archiveIDTime[$allArchiveStatus[$i]['archive_id']] = $allArchiveStatus[$i]['backup_time'];
                                                                }
                                                            }
                                                        }
                                                    }
                                                    //order archive ids from earliest to latest
                                                    asort($archiveIDTime);
                                                    $status = "";
                                                    foreach($archiveIDTime as $id => $time) {
                                                        $status .= (string)$id . ", ";
                                                    }
                                                    $status = array("ids" => substr($status, 0, -2));
                                                } else {
                                                    //no full found
                                                    $status = array();
                                                    $status['error'] = 500;
                                                    $status['message'] = "No 'Full' backup copy found for instance " . $instance;
                                                }
                                            } else {
                                                $status = $allArchiveStatus;
                                            }
                                        }
                                    } else {
                                        $status = array();
                                        $status['error'] = 500;
                                        $status['message'] = "No backup copy was found with id " . $which[1] . " and instance " . $instance;
                                    }
                                } else {
                                    $status = array();
                                    $status['error'] = 500;
                                    $status['message'] = "Import of Local Directory backup copies is unsupported";
                                }
                            } else {
                                $status = array();
                                $status['error'] = 500;
                                $status['message'] = "No backup copy was found with id " . $which[1] . " and instance " . $instance;
                            }
                        } else {
                            $status = $archiveInfo;
                        }
                    } else {
                        $status = array();
                        $status['error'] = 500;
                        $status['message'] = "An instance id or instance name must be provided";
                    }
                } else {
                    $status = array();
                    $status['error'] = 500;
                    $status['message'] = "A backup copy id must be provided";
                }
                break;
        }
        return $status;
    }

    private function addFilePlaceholder($directory, $lastFile) {
        return array('id' => $directory . self::MORE_FILES_ID,
            'directory' => $directory,
            'branch' => false,
            'name' => self::MORE_FILES,
            'last' => $lastFile);
    }

    private function getTapeLibraryInfo($media, $sid) {
        $tapeLibraryInfo = array();
        $tapeInfo = $this->BP->get_tape_library_info($media, $sid);
        if($tapeInfo != false) {
            if (isset($tapeInfo['barcodes_available'])) {
                $tapeLibraryInfo['barcodes_available'] = $tapeInfo['barcodes_available'];
            }
            if (isset($tapeInfo['drives'])) {
                $drives = $tapeInfo['drives'];
                if ($drives !== false && is_array($drives) && count($drives) > 0 ) {
                    foreach ($drives as $key => $drive) {
                        $tempDriveArray = array('index' => $key);

                        if (isset($drive['loaded_slot'])) {
                            $tempDriveArray['loaded_slot'] = $drive['loaded_slot'];
                        }
                        if (isset($drive['status'])) {
                            $tempDriveArray['status'] = $drive['status'];
                        }
                        if (isset($drive['barcode'])) {
                            $tempDriveArray['barcode'] = $drive['barcode'];
                        }
                        $tapeLibraryInfo['drives'][] = $tempDriveArray;
                    }
                }
            }
            if (isset($tapeInfo['slots'])) {
                $slots = $tapeInfo['slots'];
                if ($slots !== false && is_array($slots) && count($slots) > 0) {
                    foreach ($slots as $key => $slot) {
                        $tempSlotsArray = array('index' => $key);

                        if (isset($slot['status'])) {
                            $tempSlotsArray['status'] = $slot['status'];
                        }
                        if (isset($slot['barcode'])) {
                            $tempSlotsArray['barcode'] = $slot['barcode'];
                        }
                        $tapeLibraryInfo['slots'][] = $tempSlotsArray;
                    }
                }
            }
        } else {
            $tapeLibraryInfo['error'] = 500;
            $tapeLibraryInfo['message'] = $this->BP->getError();
        }
        return $tapeLibraryInfo;
    }

    private function getSets($data, $sid) {
        $inputParams = array();
        $start = false;
        $end = false;
        if(isset($data['start_day'])) {
            $inputParams['job_start_time'] = $this->FUNCTIONS->dateToTimestamp($data['start_day']);
            $start = true;
        }
        if(isset($data['end_day'])) {
            $inputParams['job_end_time'] = $this->FUNCTIONS->dateToTimestamp($data['end_day']);
            $end = true;
        }
        if($start and $end and $data['start_day'] === $data['end_day']) {
            $inputParams['job_end_time'] += 86400;
        }
        if(isset($data['media'])) {
            $inputParams['target'] = $data['media'];
        }
        $sets = $this->BP->get_archive_sets($inputParams, $sid);
        return $sets;
    }

    public function delete($which, $sid) {
        switch($which[0]) {
            case 'catalog':
                $status = $this->BP->purge_archive_catalog((int)$which[1], $sid);
                break;
        }
        return $status;
    }

    public function put($which, $data, $sid) {
        if(!is_array($which) and ($which == "" or $which == "check")) {
            //dealing with archive now or archive check
            $inputParams = array();

            if(isset($data['description'])) {
                $inputParams['description'] = $data['description'];
            } else {
                $inputParams['description'] = "Archive Now";
            }

            $inputParams['target'] = $data['target'];

            if (isset($data['range_end'])) {
                $inputParams['range_end'] = $data['range_end'];
            } else {
                if($data['end_date'] == 0) {
                    $inputParams['range_end'] = 0;
                } else {
                    $inputParams['range_end'] = $this->FUNCTIONS->formatEndDate($data['end_date']);
                }             
            }
            if (isset($data['range_size'])) {
                $inputParams['range_size'] = $data['range_size'];
            } else {
                if($data['start_date'] == 0) {
                    $inputParams['range_size'] = 0;
                } else {
                    if($inputParams['range_end'] == 0) {
                        $inputParams['range_size'] = time() - $this->FUNCTIONS->dateToTimestamp($data['start_date']);
                    } else {
                        $inputParams['range_size'] = $inputParams['range_end'] - $this->FUNCTIONS->dateToTimestamp($data['start_date']);
                    }
                }              
            }

            if(isset($data['clients'])) {
                $inputParams['clients'] = $data['clients'];
                $appType = "file-level";
                $types = array();
                foreach($data['types'] as $type) {
                    $types[] = $this->FUNCTIONS->getBackupTypeCoreNameBackupNow($type, $appType);
                }
                $inputParams['types'] = $types;
            } elseif(isset($data['instances'])) {
                $inputParams['instances'] = $data['instances'];
                $appTypes = array();
                $instances = implode(",", $data['instances']);
                $instanceInfo = $this->BP->get_appinst_info($instances, $sid);
                //get unique app types
                foreach($instanceInfo as $info) {
                    $appType = $info['app_type'];
                    if(!in_array($appType, $appTypes)) {
                        $appTypes[] = $appType;
                    }
                }
                //get all backup types based on user-input types and unique app types
                $types = array();
                foreach($data['types'] as $type) {
                    foreach($appTypes as $appType) {
                        if ($appType != Constants::APPLICATION_TYPE_NAME_FILE_LEVEL && $type == Constants::BACKUP_DISPLAY_TYPE_BAREMETAL) {
                            //bare metal is applicable only in case of file-level
                            continue;
                        } else if ($appType != Constants::APPLICATION_TYPE_NAME_SQL_SERVER && $type == Constants::BACKUP_DISPLAY_TYPE_TRANSACTION) {
                            // transaction backup is applicable only in case of SQL server
                            continue;
                        } else {
                            $types[] = $this->FUNCTIONS->getBackupTypeCoreNameBackupNow($type, $appType);
                        }
                    }
                }
                $inputParams['types'] = $types;
            } elseif(isset($data['localdirs'])) {
                $inputParams['localdirs'] = $data['localdirs'];
            }

            if(isset($data['slots']) and !empty($data['slots'])) {
                $inputParams['target_slots'] = $data['slots'];
            }

            $options = array();

            $options['append'] = isset($data['options']['append']) ? $data['options']['append'] : true;
            $options['purge'] = isset($data['options']['purge']) ? $data['options']['purge'] : false;
            $options['compress'] = true;
            $options['encrypt'] = isset($data['options']['encrypt']) ? $data['options']['encrypt'] : false;
            $options['deduplicate'] = false;
            $options['email_report'] = isset($data['options']['email_report']) ? $data['options']['email_report'] : false;
            $options['retention_days'] = isset($data['options']['retention_days']) ? (int)$data['options']['retention_days'] : 0;
            $options['fastseed'] = isset($data['options']['fastseed']) ? $data['options']['fastseed'] : false;

            $inputParams['options'] = $options;

            if($which == "check") {
                $status = $this->BP->check_archive_space($inputParams, $sid);
                if ($status !== false) {
                    //get "fit" paramater
                    $mediaInfo = $this->BP->get_connected_archive_media($sid);
                    foreach($mediaInfo as $media) {
                        if($media['name'] == $inputParams['target']) {
                            if($media['is_infinite']) {
                                $fitString = "The backup copy will fit as the media is cloud storage and can have infinite size";
                            } else {
                                $sizeFree = $media['mb_free'];
                                $freeGB = round($sizeFree/1024, 2);
                                if((int)$sizeFree - (int)$status['likely'] > 0) {
                                    $fitString = "The backup copy will fit as the media has " . $freeGB . " GB remaining";
                                } elseif((int)$sizeFree - (int)$status['likely'] < 0) {
                                    $fitString = "The backup copy will not fit as the media has only " . $freeGB . " GB remaining";
                                } else {
                                    $fitString = "The backup copy might fit as the media has " . $freeGB . " GB remaining";
                                }
                            }
                            $status['fit'] = $fitString;
                        }
                    }
                    $status = array("result" => array($status));
                }
            } else {
                $id = $this->BP->archive_now($inputParams, $sid);
                if($id !== false) {
                    $status['id'] = $id;
                    $status = array("result" => array($status));
                } else {
                    $status = $id;
                }

            }
        } else {
            switch($which[0]) {
                case 'media':
                    switch($which[1]) {
                        case 'mount':
                            $status = $this->BP->mount_archive_media(urldecode($which[2]), $sid);
                            // default to import for non-storage media, but can override
                            $import_sets = $this->isAttached(urldecode($which[2]));
                            if (isset($data['import_sets'])) {
                                $import_sets = $data['import_sets'];
                            }
                            if ($status !== false && $import_sets) {
                		        //import sets after successful mount if the user requests.
                		        $importResult = $this->FUNCTIONS->importSets(urldecode($which[2]), $sid);
                                $status = array();
                                $status['sets'] = $importResult['storage'];
                            }
                            break;
                        case 'unmount':
                            // force the unmount, 2nd parameter.
                            $status = $this->BP->unmount_archive_media(urldecode($which[2]), true, $sid);
                            break;
                        case 'prepare':
                            $label = "";
                            $slots = "";
                            if(isset($data['label'])) {
                                $label = $data['label'];
                            }
                            if(isset($data['slots'])) {
                                $slots = $data['slots'];
                            }
                            if (isset($data['mount'])) {
                                $mountMedia = $data['mount'];
                            }
                            $status = $this->BP->prepare_archive_media(urldecode($which[2]), $label, $slots, $sid);
                            if (isset($mountMedia) && $mountMedia) {
                                if ($status !== false) {
                                    $status = $this->BP->mount_archive_media(urldecode($which[2]), $sid);
                                }
                            }
                            break;
                        case 'settings':
                            $devName = isset($which[2]) ? urldecode($which[2]) : "";
                            $status = $this->BP->save_archive_media_settings($devName, $data['settings'], $sid);
                            break;
                    }
                    break;
            }
        }
        return $status;
    }

    public function post($which, $data, $sid) {
        switch($which[0]) {
            case 'catalog':
                $slots = "";
                $force = false;
                $name = $data['name'];
                if(isset($data['slots'])) {
                    $slots = $data['slots'];
                }
                if(isset($data['force'])) {
                    $force = $data['force'];
                }
                $status = $this->BP->import_archive_catalog($name, $slots, $force, $sid);
                break;
            case 'search':
                $clientName = $data['client_name'];
                unset($data['client_name']);
                if(isset($data['max_count'])) {
                    $max = $data['max_count'];
                    unset($data['max_count']);
                } else {
                    $max = 1000;
                }
                if(isset($data['start_date'])) {
                    $data['start_date'] = $this->FUNCTIONS->dateToTimestamp($data['start_date']);
                }
                if(isset($data['end_date'])) {
                    $data['end_date'] = $this->FUNCTIONS->formatEndDate($data['end_date']);
                }
                $status = $this->BP->search_archive_files($data, $clientName, $max, $sid);
                if($status !== false) {
                    for($i = 0; $i < count($status); $i++) {
                        //get archive time using archive id
                        $archiveStatus = $this->BP->get_archive_status(array('archive_ids' => array($status[$i]['archive_id'])), $sid);
                        if($archiveStatus !== false) {
                            $status[$i]['archive_date'] = $this->FUNCTIONS->formatDateTime($archiveStatus[0]['archive_time']);
                        } else {
                            $status[$i]['archive_date'] = "";
                        }
                        $status[$i]['id'] = $status[$i]['archive_id'];
                        unset($status[$i]['archive_id']);
                        $status[$i]['set_id'] = $status[$i]['archive_set_id'];
                        unset($status[$i]['archive_set_id']);
                        $status[$i]['date'] = $this->FUNCTIONS->formatDateTime($status[$i]['date']);
                    }
                    $status = array("files" => $status);
                }
                break;
            case 'status':
                $inputParams = array();
                if(isset($which[1]) and is_numeric($which[1])) {
                    $inputParams['archive_set_id'] = (int)$which[1];
                }
                if(isset($data['client_id'])) {
                    $inputParams['client_id'] = $data['client_id'];
                    $appType = "file-level";
                }
                if(isset($data['instance_id'])) {
                    $instanceInfo = $this->BP->get_appinst_info($data['instance_id'], $sid);
                    $appType = $instanceInfo[$data['instance_id']]['app_type'];
                    if(!isset($inputParams['client_id'])) {
                        $inputParams['client_id'] = $instanceInfo[$data['instance_id']]['client_id'];
                    }
                }
                if(isset($data['type'])) {
                    $typeArr = array();
                    foreach($data['type'] as $type) {
                        if(isset($appType)) {
                            $typeArr[] = $this->FUNCTIONS->getBackupTypeCoreNameBackupNow($type, $appType);
                        } else {
                            $typeArr = array_merge($typeArr, $this->FUNCTIONS->getArchiveTypesByDisplayType($type));
                        }
                    }
                    $inputParams['type'] = $typeArr;
                }
                if(isset($data['start_time_archive'])) {
                    $inputParams['job_start_time'] = $this->FUNCTIONS->dateToTimestamp($data['start_time_archive']);
                } else if (isset($data['start_timestamp_archive_set'])) {
                    $inputParams['job_start_time'] = $data['start_timestamp_archive_set'];
                    $sets = $this->BP->get_archive_sets($inputParams);
                    if ($sets !== false && count($sets) > 0) {
                        $theSet = $sets[0];
                        $inputParams = array();
                        $inputParams['archive_set_id'] = $theSet['archive_set_id'];
                    }
                }
                if(isset($data['end_time_archive'])) {
                    $timestamp = $this->FUNCTIONS->dateToTimestamp($data['end_time_archive']);
                    if(isset($inputParams['job_start_time']) and $inputParams['job_start_time'] === $timestamp) {
                        $timestamp += 86400;
                    }
                    $inputParams['job_end_time'] = $timestamp;
                }
                if(isset($data['start_time_backup'])) {
                    $inputParams['backup_start_time'] = $this->FUNCTIONS->dateToTimestamp($data['start_time_backup']);
                }
                if(isset($data['end_time_backup'])) {
                    $timestamp = $this->FUNCTIONS->dateToTimestamp($data['end_time_backup']);
                    if(isset($inputParams['backup_start_time']) and $inputParams['backup_start_time'] === $timestamp) {
                        $timestamp += 86400;
                    }
                    $inputParams['backup_end_time'] = $timestamp;
                }
                if(isset($data['archive_ids'])) {
                    $inputParams['archive_ids'] = $data['archive_ids'];
                }
                $archive = $this->BP->get_archive_status($inputParams, $sid);
                if($archive !== false) {
                    $status = array();
                    for($i = 0; $i < count($archive); $i++) {
                        if(!isset($data['instance_id']) or (isset($data['instance_id']) and $data['instance_id'] === $archive[$i]['instance_id'])) {
                            $archive[$i]['id'] = $archive[$i]['archive_id'];
                            unset($archive[$i]['archive_id']);
                            $archive[$i]['set_id'] = $archive[$i]['archive_set_id'];
                            $archive[$i]['date'] = $this->FUNCTIONS->formatDateTime($archive[$i]['archive_time']);
                            unset($archive[$i]['archive_time']);
                            $archive[$i]['elapsed'] = $this->FUNCTIONS->formatTimeDelta($archive[$i]['elapsed_secs']);
                            unset($archive[$i]['elapsed_secs']);
                            $archive[$i]['type'] = $this->FUNCTIONS->getBackupTypeDisplayName($archive[$i]['type']);
                            $archive[$i]['backup_date'] = $this->FUNCTIONS->formatDateTime($archive[$i]['backup_time']);
                            unset($archive[$i]['backup_time']);
                            $archive[$i]['storage'] = $this->getStorage($archive[$i]['set_id'], $sid);
                            $archive[$i]['media_label'] = $this->getMediaLabel($archive[$i]['set_id'], $sid);
                            if ($archive[$i]['instance_description'] == Constants::APPLICATION_NAME_FILE_LEVEL || $archive[$i]['instance_description'] == "File-level") {
                                $archive[$i]['instance_description'] = $archive[$i]['client_name'];
                            }
                            $status[] = $archive[$i];
                        }
                    }
                    $status = array("status" => $status);
                } else {
                    $status = $archive;
                }
                break;
        }
        return $status;
    }

    public function restore($which, $data, $sid) {
        $inputParams = array();
        $archiveIDs = explode(", ", $data['archive_ids']);
        $mediaConnected = $data['check_media'] == true ? $this->checkMedia($archiveIDs, $sid) : array('FOUND' => true, 'bp_error' => false);
        if(!$mediaConnected['FOUND'] AND $mediaConnected['bp_error'] == false) {
            // media offline, try to mount it with mount_archive_media
            if(isset($mediaConnected['target'])) {
                $result = $this->BP->mount_archive_media($mediaConnected['target'], $sid);
                if ($result == true) {
                    $mediaConnected = array('FOUND' => true, 'bp_error' => false);
                }
            }
        }
        if($mediaConnected['FOUND']) {
            if(isset($data['storage'])) {
                $inputParams['device_id'] = $data['storage'];
            }
            $inputParams['association'] = $data['association'];
            if(isset($data['instance_id'])) {
                if($data['association'] == "client") {
                    $instanceInfo = $this->BP->get_appinst_info($data['instance_id']);
                    $inputParams['association_id'] = $instanceInfo[$data['instance_id']]['client_id'];
                } elseif($data['association'] == "instance") {
                    $inputParams['association_id'] = $data['instance_id'];
                }
            } else {
                //restore to original location
                if($data['association'] == "client" or $data['association'] == "instance") {
                    $inputParams['association'] = "dr";
                }
            }
            if($which[0] == "files") {
                //check for inclusions and exclusions
                if(isset($data['inclusions'])) {
                    $inputParams['inclusions'] = $data['inclusions'];
                    for($i = 0; $i < count($inputParams['inclusions']); $i++) {
                        //if last character is '/' then add '*' to include all files within directory
                        if(substr($inputParams['inclusions'][$i], -1) == "/") {
                            $inputParams['inclusions'][$i] .= "*";
                        }
                    }
                }
                if(isset($data['exclusions'])) {
                    $inputParams['exclusions'] = $data['exclusions'];
                    for($i = 0; $i < count($inputParams['exclusions']); $i++) {
                        //if last character is '/' then add '*' to include all files within directory
                        if(substr($inputParams['exclusions'][$i], -1) == "/") {
                            $inputParams['exclusions'][$i] .= "*";
                        }
                    }
                }
            }
            $jobIDs = array();
            $messages = array();
            foreach($archiveIDs as $archiveID) {
                $ID = $this->BP->restore_archived_files($archiveID, $inputParams, $sid);
                //need to put message if fails
                if($ID == false) {
                    $messages[] = "The backup copy with ID " . $archiveID . " failed to import because: " . $this->BP->getError();
                } else {
                    $jobIDs[] = $ID;
                }

            }
            $status = array();
            $status['id'] = implode(", ", $jobIDs);
            if(count($messages) > 0) {
                $status['messages'] = $messages;
            }
        } else {
            if($mediaConnected['bp_error'] == true) {
                $status = false;
            } else {
                $status['error'] = 500;
                if(isset($mediaConnected['target'])) {
                    $status['message'] = "The media " . $mediaConnected['target'] . " that contains the backup copies to be imported is either disconnected or offline. Please reconnect or bring online and try again.";

                    if ( isset($mediaConnected['barcodes']) and count( $mediaConnected['barcodes'] ) > 0 ) {

                        $barcode_message = "\n\nBarcode";
                        if ( count( $mediaConnected['barcodes'] ) == 1 ) {
                            $barcode_message .= ": ";
                        } else {
                            $barcode_message .= "s: ";
                        }
                        foreach ( $mediaConnected['barcodes'] as $barcode )
                        {
                            $barcode_message .= $barcode.", ";
                        }
                        $barcode_message = rtrim($barcode_message,", ");

                        $status['message'] .= $barcode_message;

                    } elseif ( isset($mediaConnected['media_serials']) ) {

                        $status['message'] .= "\n\nMedia Serials: ".$mediaConnected['media_serials'];

                    }
                } else {
                    $status['message'] = "The media containing the backup copies to be imported is either disconnected or offline. Please reconnect or bring online and try again.";
                }
            }
        }
        return $status;
    }

    function checkMedia($archiveIDs, $dpuID) {
        $matchMedia = false;

        // See what media was used for this archive.  Pick the first id.
        $result_format = array('archive_ids' => array((int)$archiveIDs[0]));
        $statusArray = $this->BP->get_archive_status($result_format, $dpuID);
        if (is_array($statusArray) && count($statusArray) > 0) {
            $status = $statusArray[0];
            $setID = $status['archive_set_id'];

            // Get the set information, then see if the serials are connected.
            // If a tape, don't check serials.
            $setInfo = $this->BP->get_archive_set_info($setID, $dpuID);
            if ($setInfo !== false) {
                $setSerials = $setInfo['media_serials'];
                $mediaTarget = isset($setInfo['profile']) && isset($setInfo['profile']['target']) ? $setInfo['profile']['target'] : "";
                $matchMedia = array('media_serials' => $setSerials,
                    'FOUND' => false,
                    'target' => $mediaTarget,
                    'bp_error' => false);

                if ( isset($setInfo['barcodes']) ) {
                    $matchMedia['barcodes'] = $setInfo['barcodes'];
                }

                $mediaList = $this->BP->get_connected_archive_media($dpuID);
                if ($mediaList !== false) {
                    if ($this->mediaSetOnTape($setInfo, $mediaList)) {
                        // If on tape, assume found.
                        $matchMedia = array("FOUND" => true);
                    } else {
                        foreach ($mediaList as $media) {
                            if ($this->serialsMatch($media['media_serials'], $setSerials)) {
                                $matchMedia = $media;
                                if ($media['is_mounted'] == false) {
                                    // The media is not mounted, so attempt to mount it before the restore.
                                    $result = $this->BP->mount_archive_media($media['name'], $dpuID);
                                    if ($result !== true) {
                                        $matchMedia['FOUND'] = false;
                                        $matchMedia['bp_error'] = true;
                                    }
                                }
                                $matchMedia['FOUND'] = true;
                                break;
                            }
                        }
                    }
                } else {
                    $matchMedia['bp_error'] = true;
                }
            } else {
                $matchMedia = array('FOUND' => false, 'bp_error' => true);
            }
        } else {
            $matchMedia = array('FOUND' => false, 'bp_error' => true);
        }
        return $matchMedia;
    }

    //
// Returns true if the media serial numbers match that of the archive set, otherwise returns false.
//
// lmc test with comma-separated serials that match, that are in diff order, that do not match.
//	$mediaSerials = "123456789,987654321,111111111,222222222";
//	$setSerials = "111111111,222222222,987654321,123456789";
    function serialsMatch($mediaSerials, $setSerials) {
        $bFound = false;
        if ($mediaSerials == $setSerials) {
            $bFound = true;
        } else {
            // See if we have a series of comma-separated serials.  Split and check each serial for a match.
            if (strstr($mediaSerials, ',') && strstr($setSerials, ',')) {
                $mediaSerialArray = explode(',', $mediaSerials);
                $setSerialArray = explode(',', $setSerials);
                // Make sure we have the same number of serials number tokens first, otherwise it is not a match.
                if (count($mediaSerialArray) == count($setSerialArray)) {
                    $bFound = true;
                    foreach ($mediaSerialArray as $serial) {
                        if (!in_array($serial, $setSerialArray)) {
                            $bFound = false;
                            break;
                        }
                    }
                }
            }
        }
        return $bFound;
    }

//
// This function returns true if the media is connected and is a tape, false if not.
//
    function mediaSetOnTape($setInfo, $mediaList) {
        $bTape = false;

        $setProfile = isset($setInfo['profile']) ? $setInfo['profile'] : array();
        if (isset($setProfile['target'])) {
            // Get archive profile target media name.
            $mediaName = $setProfile['target'];
            foreach ($mediaList as $media) {
                // Search the connected media for this name and when found, see if tape or changer.
                if ($media['name'] == $mediaName) {
                    if ($media['type'] == "tape" || $media['type'] == "changer") {
                        $bTape = true;
                    }
                    break;
                }
            }
        }
        return $bTape;
    }

    function merge_view($a1, $a2, $view){
        $result = array();
        if (is_array($a1) && count($a1) > 0 && is_array($a2) && count($a2) > 0) {
            if ($view != 'day') {
                $result = array_merge($a1, $a2);
            } else {
                // merge days that overlap, and add others that do not.
                foreach ($a1 as $a1_item) {
                    $day1 = $a1_item['day'];
                    foreach ($a2 as $a2_item) {
                        $day2 = $a2_item['day'];
                        if (strcmp($day1, $day2) == 0) {
                            $merged = array_merge($a1_item['instances'], $a2_item['instances']);
                            $result[] = array('day' => $day2, 'instances' => $merged);
                        } else {
                            $result[] = array('day' => $day2, 'instances' => $a2_item['instances']);
                        }
                    }
                    $result[] = array('day' => $day1, 'instances' => $a1_item['instances']);
                }
            }
        } elseif (is_array($a1) && count($a1) > 0) {
            $result = $a1;
        } elseif (is_array($a2) && count($a2) > 0) {
            $result = $a2;
        }
        return ($result);
    }

    function getArchivesByView($view, $result_format, $sid){

        switch($view){
            case "day":
                $data = $this->catalogByDay($result_format, $sid);
                break;
            case "instance":
                $data = $this->catalogByInstance($result_format, $sid);
                break;
            case "storage":
                $data = $this->catalogByStorage($result_format, $sid);
                break;
            case "system" :
                $data = $this->catalogBySystem($result_format, $sid);
                break;

        }
        return $data;
    }

    function catalogByDay($result_format, $sid){
        $data = false;

        $archives = $this->BP->get_archive_status($result_format, $sid);

        if ($archives !== false) {
            $data = array();
            $items = array();
            foreach ($archives as $archive) {
                if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                    if (!$this->Roles->backup_is_in_scope($archive, $sid)) {
                        //global $Log;
                        //$msg = "Archive " . $archive['archive_id'] . " is NOT in restore scope.";
                        //$Log->writeVariable($msg);
                        continue;
                    }
                }
                if(!isset($result_format['instance_id']) or (isset($result_format['instance_id']) and $result_format['instance_id'] === $archive['instance_id'])) {
                    $day = isset($archive['backup_time']) ? $this->FUNCTIONS->formatDate($archive['backup_time']) : null;
                    $appName = isset($archive['app_name']) ? $archive['app_name'] : "";
                    $clientID = isset($archive['client_id']) ? $archive['client_id'] : null;
                    $clientName = isset($archive['client_name']) ? $archive['client_name'] : null;
                    $instanceID = isset($archive['instance_id']) ? $archive['instance_id'] : null;
                    $instanceName = $this->FUNCTIONS->getInstanceName($archive, $sid, $clientName);

                    if ($instanceName == "") {
                        $names = $this->getNamesFromInstanceDescription($appName, $archive['instance_description']);
                        $instanceName = $names['instance_name'];
                        $dbName = $names['database_name'];
                    } else {
                        $dbName = $this->getDBFromInstanceName($appName, $instanceName);
                    }

                    if ($appName === Constants::APPLICATION_TYPE_NAME_FILE_LEVEL){
                        $dbName = $instanceName = $clientName;
                    }

                    $archiveItem = $archive;
                    $archiveItem['storage'] = $archiveItem['target'];
                    unset($archiveItem['target']);
                    $archiveItem['id'] = $archiveItem['archive_id'];
                    unset($archiveItem['archive_id']);
                    $archiveItem['set_id'] = $archiveItem['archive_set_id'];
                    unset($archiveItem['archive_set_id']);
                    $archiveItem['label'] = $this->getMediaLabel($archiveItem['set_id'], $sid);
                    $archiveItem['elapsed'] = $this->FUNCTIONS->formatTimeDelta($archiveItem['elapsed_secs']);
                    unset($archiveItem['elapsed_secs']);
                    $archiveItem['start_date'] = $this->FUNCTIONS->formatDateTime($archiveItem['backup_time']);
                    unset($archiveItem['backup_time']);
                    $archiveItem['archive_start_date'] = $this->FUNCTIONS->formatDateTime($archiveItem['archive_time']);
                    unset($archiveItem['archive_time']);
                    $archiveItem['type'] = $this->FUNCTIONS->getBackupTypeDisplayName($archiveItem['type']);
                    if($archiveItem['success']) {
                        $archiveItem['error_string'] = "";
                    }

                    $index = $this->encode_index($appName, $clientID, $clientName, $dbName, $instanceID, $instanceName, $sid);
                    $items[$day][$index][] = $archiveItem;
                }
            }

            foreach ($items as $day => $indices) {
                $instances = array();
                foreach ($indices as $index => $archives) {
                    $instance = $this->create_instance($index);
                    $instance['archives'] = $archives;
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

    function catalogByInstance($result_format, $sid){
        $data = false;

        $archives = $this->BP->get_archive_status($result_format, $sid);

        if ($archives !== false) {
            $items = array();
            foreach ($archives as $archive) {
                if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                    if (!$this->Roles->backup_is_in_scope($archive, $sid)) {
                        //global $Log;
                        //$msg = "Archive " . $archive['archive_id'] . " is NOT in restore scope.";
                        //$Log->writeVariable($msg);
                        continue;
                    }
                }

                if(!isset($result_format['instance_id']) or (isset($result_format['instance_id']) and $result_format['instance_id'] === $archive['instance_id'])) {
                    $day = isset($archive['archive_time']) ? $this->FUNCTIONS->formatDate($archive['archive_time']) : null;
                    $appName = isset($archive['app_name']) ? $archive['app_name'] : "";
                    $clientID = isset($archive['client_id']) ? $archive['client_id'] : null;
                    $clientName = isset($archive['client_name']) ? $archive['client_name'] : null;
                    $instanceID = isset($archive['instance_id']) ? $archive['instance_id'] : null;
                    $instanceName = $this->FUNCTIONS->getInstanceName($archive, $sid, $clientName);
                    if ($instanceName == "") {
                        $names = $this->getNamesFromInstanceDescription($appName, $archive['instance_description']);
                        $instanceName = $names['instance_name'];
                        $dbName = $names['database_name'];
                    } else {
                        $dbName = $this->getDBFromInstanceName($appName, $instanceName);
                    }

                    if ($appName === Constants::APPLICATION_TYPE_NAME_FILE_LEVEL){
                        $dbName = $instanceName = $clientName;
                    }

                $archiveItem = $archive;

                    $archiveItem['storage'] = $archiveItem['target'];
                    unset($archiveItem['target']);
                    $archiveItem['id'] = $archiveItem['archive_id'];
                    unset($archiveItem['archive_id']);
                    $archiveItem['set_id'] = $archiveItem['archive_set_id'];
                    unset($archiveItem['archive_set_id']);
                    $archiveItem['label'] = $this->getMediaLabel($archiveItem['set_id'], $sid);
                    $archiveItem['elapsed'] = isset($archiveItem['elapsed_secs']) ? $this->FUNCTIONS->formatTimeDelta($archiveItem['elapsed_secs']) : 'N/A';
                    unset($archiveItem['elapsed_secs']);
                    $archiveItem['start_date'] = $this->FUNCTIONS->formatDateTime($archiveItem['backup_time']);
                    unset($archiveItem['backup_time']);
                    $archiveItem['archive_start_date'] = $this->FUNCTIONS->formatDateTime($archiveItem['archive_time']);
                    unset($archiveItem['archive_time']);
                    $archiveItem['type'] = $this->FUNCTIONS->getBackupTypeDisplayName($archiveItem['type']);
                    if($archiveItem['success']) {
                        $archiveItem['error_string'] = "";
                    }
                    $index = $this->encode_index($appName, $clientID, $clientName, $dbName, $instanceID, $instanceName, $sid);
                    $items[$instanceName][$index][] = $archiveItem;

                }
            }

            $data = array();
            foreach ($items as $instanceID => $indices) {
                $instances = array();
                foreach ($indices as $index => $archives) {
                    $instance = $this->create_instance($index);
                    $instance['archives'] = $archives;
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

        $archives = $this->BP->get_archive_status($result_format, $sid);

        if ($archives !== false) {
            $data = array();
            $items = array();
            foreach ($archives as $archive) {
                if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                    if (!$this->Roles->backup_is_in_scope($archive, $sid)) {
                        //global $Log;
                        //$msg = "Archive " . $archive['archive_id'] . " is NOT in restore scope.";
                        //$Log->writeVariable($msg);
                        continue;
                    }
                }
                if(!isset($result_format['instance_id']) or (isset($result_format['instance_id']) and $result_format['instance_id'] === $archive['instance_id'])) {
                    // $day = isset($backup['start_time']) ? $this->FUNCTIONS->formatDate($backup['start_time']) : null;
                    $appName = isset($archive['app_name']) ? $archive['app_name'] : "";
                    $clientID = isset($archive['client_id']) ? $archive['client_id'] : null;
                    $clientName = isset($archive['client_name']) ? $archive['client_name'] : null;
                    $setid = isset($archive['archive_set_id']) ? $archive['archive_set_id'] : null;
                    $storage = $this->getStorage($setid, $sid);
                    $instanceID = isset($archive['instance_id']) ? $archive['instance_id'] : null;
                    $instanceName = $this->FUNCTIONS->getInstanceName($archive, $sid, $clientName);

                    $index = $this->encode_index($appName, $clientID, $clientName, $instanceName, $instanceID, $instanceName, $sid);
                    $archiveItem = $archive;
                    $archiveItem['id'] = $archiveItem['archive_id'];
                    unset($archiveItem['archive_id']);
                    $archiveItem['set_id'] = $archiveItem['archive_set_id'];
                    unset($archiveItem['archive_set_id']);
                    $archiveItem['elapsed'] = $this->FUNCTIONS->formatTimeDelta($archiveItem['elapsed_secs']);
                    unset($archiveItem['elapsed_secs']);
                    $archiveItem['start_date'] = $this->FUNCTIONS->formatDateTime($archiveItem['backup_time']);
                    unset($archiveItem['backup_time']);
                    $archiveItem['archive_start_date'] = $this->FUNCTIONS->formatDateTime($archiveItem['archive_time']);
                    unset($archiveItem['archive_time']);
                    $archiveItem['type'] = $this->FUNCTIONS->getBackupTypeDisplayName($archiveItem['type']);
                    if($archiveItem['success']) {
                        $archiveItem['error_string'] = "";
                    }
                    $items[$storage][$index][] = $archiveItem;
                }
            }

            foreach ($items as $storage => $indices) {
                $instances = array();
                foreach ($indices as $index => $archives) {
                    $instance = $this->create_instance($index);
                    $instance['archives'] = $archives;
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

        $archives = $this->BP->get_archive_status($result_format, $sid);
        $systemName = $this->getSystemName($sid);

        if ($archives !== false) {
            $data = array();
            $items = array();
            foreach ($archives as $archive) {
                if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                    if (!$this->Roles->backup_is_in_scope($archive, $sid)) {
                        //global $Log;
                        //$msg = "Archive " . $archive['archive_id'] . " is NOT in restore scope.";
                        //$Log->writeVariable($msg);
                        continue;
                    }
                }
                if(!isset($result_format['instance_id']) or (isset($result_format['instance_id']) and $result_format['instance_id'] === $archive['instance_id'])) {
                    // $day = isset($archive['archive_time']) ? $this->FUNCTIONS->formatDate($archive['archive_time']) : null;
                    $appName = isset($archive['app_name']) ? $archive['app_name'] : "";
                    $clientID = isset($archive['client_id']) ? $archive['client_id'] : null;
                    $clientName = isset($archive['client_name']) ? $archive['client_name'] : null;
                    $instanceID = isset($archive['instance_id']) ? $archive['instance_id'] : null;
                    $instanceName = $this->FUNCTIONS->getInstanceName($archive, $sid, $clientName);
                    if ($instanceName == "") {
                        $names = $this->getNamesFromInstanceDescription($appName, $archive['instance_description']);
                        $instanceName = $names['instance_name'];
                        $dbName = $names['database_name'];
                    } else {
                        $dbName = $this->getDBFromInstanceName($appName, $instanceName);
                    }

                    $archiveItem = $archive;
                    $archiveItem['id'] = $archiveItem['archive_id'];
                    unset($archiveItem['archive_id']);
                    $archiveItem['set_id'] = $archiveItem['archive_set_id'];
                    unset($archiveItem['archive_set_id']);
                    $archiveItem['elapsed'] = $this->FUNCTIONS->formatTimeDelta($archiveItem['elapsed_secs']);
                    unset($archiveItem['elapsed_secs']);
                    $archiveItem['start_date'] = $this->FUNCTIONS->formatDateTime($archiveItem['backup_time']);
                    unset($archiveItem['backup_time']);
                    $archiveItem['archive_start_date'] = $this->FUNCTIONS->formatDateTime($archiveItem['archive_time']);
                    unset($archiveItem['archive_time']);
                    $archiveItem['type'] = $this->FUNCTIONS->getBackupTypeDisplayName($archiveItem['type']);
                    if($archiveItem['success']) {
                        $archiveItem['error_string'] = "";
                    }
                    $index = $this->encode_index($appName, $clientID, $clientName, $dbName, $instanceID, $instanceName, $sid);
                    $items[$systemName][$index][] = $archiveItem;
                }
            }

            foreach ($items as $systemName => $indices) {
                $instances = array();
                foreach ($indices as $index => $archives) {
                    $instance = $this->create_instance($index);
                    $instance['archives'] = $archives;
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

    function encode_index($appName, $clientID, $clientName, $databaseName, $instanceID, $instanceName, $sid) {
        $result = $appName . '^xyzxyz^' . $clientName . '^xyzxyz^' . $databaseName . '^xyzxyz^' . $instanceName . '^xyzxyz^' . $sid;

        if ($clientID != NULL) {
            $result .=  '^xyzxyz^' . $clientID;
        }
        if ($instanceID != NULL) {
            $result .=  '^xyzxyz^' . $instanceID ;
        }

        return $result;
    }

    function create_instance($index) {
        $instance = array();
        $a = explode('^xyzxyz^', $index);
        $instance = array(
            'app_name' => $a[0],
            'client_name' => $a[1],
            'database_name' => $a[2],
            'instance_name' => $a[3],
            'system_id' => $a[4],
            'system_name' => $this->getSystemName($a[4])
        );
        $clientID = isset($a[5]) ? $a[5] : null;
        $instanceID = isset($a[6]) ? $a[6] : null;
        $instance['client_id'] = $clientID;
        $instance['instance_id'] = $instanceID;
        return $instance;
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

    function getStorage($setid, $sid){
        $storageName = "";
        $setInfo = $this->BP->get_archive_set_info($setid, $sid);
        if($setInfo !== false) {
            $storageName = isset($setInfo['profile']['target']) ? $setInfo['profile']['target'] : "";
        }
        return $storageName;
    }

    function getMediaLabel($setID, $sid){
        $mediaLabel = "";
        $setInfo = $this->BP->get_archive_set_info($setID, $sid);
        if($setInfo !== false) {
            $mediaLabel = isset($setInfo['media_label']) ? $setInfo['media_label'] : "";
        }
        return $mediaLabel;
    }


    function isAttached($name){
        return $name === 'Recovery Archive' || $name === 'USB' || $name === 'eSATA';
    }

    /*
     * Given an archive, return its target name.
     */
    function getArchiveInfoTarget($archive, $sid) {
        $target = false;
        if (isset($archive['archive_set_id'])) {
            $setID = $archive['archive_set_id'];
            $setInfo = $this->BP->get_archive_set_info($setID, $sid);
            if ($setInfo !== false) {
                $profile = $setInfo['profile'];
                $target = $profile['target'];
            }
        }
        return $target;
    }

    /*
     * Given the application and instance description, parse the description to find the instance and database names.
     * Some apps return database name differently than others.
     */
    function getNamesFromInstanceDescription($appName, $newDesc) {
        $names = array('instance_name' => '', 'database_name' => '');
        $pieces = explode('"', $newDesc);
        if(count($pieces) > 1) {
            $names['instance_name'] = $pieces[1] ."|" . $pieces[3];
            if($appName == "VMware" || strstr($appName, 'SQL Server') || $appName == "Xen"){
                $names['database_name'] = $pieces[3];
            } else {
                $names['database_name'] = $pieces[1];
            }
        }
        return $names;
    }

    /*
     * Given the application and instance name, parse the name to find the database name.
     * Some apps return database name differently than others.
     */
    function getDBFromInstanceName($appName, $instanceName) {
        $dbName = '';
        $pieces = explode("|", $instanceName);
        if (count($pieces) > 0) {
            if($appName == "VMware" || strstr($appName, 'SQL Server') || $appName == "Xen") {
                $dbName = $pieces[1];
            } else {
                $dbName = $pieces[0];
            }
        }
        return $dbName;
    }

}
?>
