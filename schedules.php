<?php
//IN PROGRESS
class Schedule
{
    private $BP;
    private $FUNCTIONS;
    private $RDR;

    public function __construct($BP)
    {
        $this->BP = $BP;
        $this->FUNCTIONS = new Functions($BP);
        $this->RDR = new RDR($BP);
        $this->include_ical = false;
    }

    // $returnInstances is an internal input that tells the API to return information about the instances and clients
    public function get($which, $systems, $returnInstances = false)
    {

        $allJoborders = array();

        $localSystemID = $this->BP->get_local_system_id();
        $localSystemRDRSupport = $this->BP->rdr_supported($localSystemID);      //If local system supports RDR then get managed systems certification jobs.
        //$this->include_ical = isset($GET['include_ical']) && $_GET['include_ical'] === 'true';
        // the above logic returns ical selectively.  Until UI changes, set it to true.
        // TODO, change this to something like the above check for include_ical.
        $this->include_ical = true;

        foreach($systems as $sid => $sname) {
            if(is_numeric($which) and $which == -1) {
                //getting all
                $backup = $this->get_backup(-1, $sid, $sname, false, $returnInstances);
                $enterprise = $this->get_enterprise(-1, $sid, $sname, false, $returnInstances);
                $archive = $this->get_archive(-1, $sid, $sname, false, $returnInstances);
                $replication = $this->get_replication(-1, $sid, $sname, false, $returnInstances);
                $certification = array();
                if($localSystemRDRSupport === true){
                    if (isset($_GET['source_id']) && $sid !== $localSystemID) {
                        //for remote calls just get the local system's jobs
                    } else {
                        //Check if managed system supports RDR; it's been determined that local system already does
                        $rdrSupported = ($sid === $localSystemID) ? true : $this->BP->rdr_supported($sid);
                        if ($rdrSupported === true) {
                            $certification = $this->get_certification(-1, $sid, $sname, false, $returnInstances);
                        }
                    }
                }
                $jobOrders = array_merge($backup, $enterprise, $archive, $replication, $certification);
            } else {
                //specific type or id entered
                $withInfo = false;
                $idPieces = explode('.', $which);
                if(isset($idPieces[1]) and (int)$idPieces[1] !== $sid) {
                    //skip this iteration as no sid was passed in, but we know the sid from the schedule id
                    continue;
                }
                if(is_numeric($idPieces[0]) and $idPieces[0] == 0) {

                   // $type = 'replication';
                  //  $withInfo = true;
                } elseif(is_numeric(substr($idPieces[0], 0, -1))) {
                    $id = (int)substr($idPieces[0], 0, -1);
                    switch(substr($idPieces[0], -1)) {
                        case 'b':
                            $type = 'backup';
                            break;
                        case 'e':
                            //multi-client backup
                            $type = 'enterprise';
                            break;
                        case 'a':
                            $type = 'archive';
                            break;
                        case 'r':
                            $type = 'restore';
                            break;
                        case 'i':
                            $type = 'ir';
                            break;
                        case 'f':
                            $type = 'flr';
                            break;
                        case 'x':
                            $type = 'replication';
                            break;
                        case 'c':
                            $type = 'certification';
                            break;
                    }
                    $withInfo = true;
                } else {
                    $id = -1;
                    $type = $which;
                }
                switch($type) {
                    case 'backup':
                        $jobOrders = $this->get_backup($id, $sid, $sname, $withInfo);
                        if($id == -1) {
                            $enterprise = $this->get_enterprise($id, $sid, $sname, $withInfo);
                            $jobOrders = array_merge($jobOrders, $enterprise);
                        }
                        break;
                    case 'enterprise':
                        $jobOrders = $this->get_enterprise($id, $sid, $sname, $withInfo);
                        break;
                    case 'archive':
                        $jobOrders = $this->get_archive($id, $sid, $sname, $withInfo);
                        break;
                    case 'replication':
                        $jobOrders = $this->get_replication($id, $sid, $sname, $withInfo);
                        break;
                    case 'orphaned':
                        $jobOrders = $this->get_orphaned_instances($sid);
                        break;
                    case 'certification':
                        if (isset($_GET['source_id']) && $sid !== $localSystemID) {
                            //for remote calls just get the local system's jobs
                            $jobOrders = array();
                        } else {
                            //Check if managed system supports RDR
                            $rdrSupported = $this->BP->rdr_supported($sid);
                            if($rdrSupported === true){
                                $jobOrders = $this->get_certification($id, $sid, $sname, $withInfo);
                            } else{
                                $jobOrders = false;
                            }
                        }
                        break;
                    default:
                        $jobOrders = false;
                        break;
                }
            }
            if(isset($jobOrders['error'])) {
                $allJoborders = $jobOrders;
                break;
            } else {
                $allJoborders = array_merge($allJoborders, $jobOrders);
            }
        }
        if(!isset($allJoborders['error'])) {
            $allJoborders = array('data' => $allJoborders);
        }
        return $allJoborders;
    }

    //
    //creates the list of backup joborders
    //if there are no backup joborders, an empty list is returned
    //
    private function get_backup($id, $sid, $sname, $withInfo = false, $returnInstances = false) {
        $joborders = array();

        $appSchedules = $this->BP->get_app_schedule_list(-1, -1, $sid);

        if ($appSchedules !== false) {
            foreach ($appSchedules as $appSchedule) {
                $joborder = array();
                $joborder['sid'] = $sid;
                if ($id != -1 and $appSchedule['id'] != $id) {
                    continue;
                } else {
                    if (isset($appSchedule['id'])) {
                        $joborder['id'] = $appSchedule['id'] . "b." . $sid;
                    }
                    if (isset($appSchedule['name'])) {
                        $joborder['name'] = $appSchedule['name'];
                    }
                    $joborder['type'] = Constants::JOBORDER_TYPE_BACKUP;
                    $joborder['app_id'] = isset($appSchedule['app_id']) ? $appSchedule['app_id'] : Constants::APPLICATION_ID_FILE_LEVEL;
                    if ($joborder['app_id'] == Constants::APPLICATION_ID_FILE_LEVEL) {
                        // Check to see if a NAS job by looking at the protected client name.
                        $joborder['is_nas'] = false;
                        $clientInfo = $this->BP->get_client_info($appSchedule['client_id'], $sid);
                        if ($clientInfo !== false) {
                            $joborder['is_nas'] = $this->FUNCTIONS->isNAS($clientInfo['name']);
                        }
                        $joborder['single_client'] = !$joborder['is_nas'];
                    }

                    if (!$withInfo) {
                        $joborder['system_name'] = $sname;
                        if (isset($appSchedule['description'])) {
                            $joborder['description'] = $appSchedule['description'];
                        }
                        //TODO: get date created
//                    $joborder['date_created'] = "Waiting on core";
                        if (isset($appSchedule['enabled'])) {
                            $joborder['enabled'] = $appSchedule['enabled'];
                        }
                        if (isset($appSchedule['last_time'])) {
                            $lastTime = $appSchedule['last_time'];
                            if ($lastTime == 0) {
                                $dateTime = "Never";
                                $joborder['last_status'] = 'None';
                            } else if ($lastTime == 77) {
                                $dateTime = "Has Been Reset";
                            } else {
                                $dateTime = $this->FUNCTIONS->formatDateTime($lastTime);
                            }
                            $joborder['last_time'] = $dateTime;
                        }
                        if (isset($appSchedule['next_time'])) {
                            $nextTime = $appSchedule['next_time'];
                            if ($nextTime == 0) {
                                $dateTime = "Never";
                            } else if ($nextTime == 77) {
                                $dateTime = "Has Been Reset";
                            } else {
                                $dateTime = $this->FUNCTIONS->formatDateTime($nextTime);
                            }
                            $joborder['next_time'] = $dateTime;
                        }
                        /*if(!isset($joborder['last_status'])) {
                            $resultFormat = array();
                            $resultFormat['schedule_id'] = $appSchedule['id'];
                            $scheduleHistory = $this->BP->get_schedule_history($resultFormat, $sid);
                            if(count($scheduleHistory) > 0) {
                                $backup = end($scheduleHistory[0]['backups']);
                                switch($backup[0]['description']) {
                                    case 'Successful':
                                        $joborder['last_status'] = 'Success';
                                        break;
                                    case 'Warnings':
                                        $joborder['last_status'] = 'Warnings';
                                        break;
                                    case 'Failed':
                                        $joborder['last_status'] = 'Failure';
                                        break;
                                }
                            }
                            if(!isset($joborder['last_status'])) {
                                $joborder['last_status'] = "Never";
                            }
                        }*/
                        //get current status of schedule 'Idle' or 'Running'
                        $joborder['status'] = $this->getStatus($appSchedule['name'], $sid);
                    }

                    if (!$withInfo and !$returnInstances) {
                        if (isset($appSchedule['calendar'])) {
                            $calendar = $appSchedule['calendar'];
                            $convertedCal = $this->iCalToSchedule($calendar);
                            if ($this->include_ical) {
                                //get the ical data
                                $joborder['include_ical'] = $calendar;
                            }
                            $joborder['calendar_str'] = $this->getShortCalendarArr($convertedCal);
                        }
                        $joborder['application_name'] = $this->FUNCTIONS->getApplicationTypeFromApplictionID($joborder['app_id']);
                    } else {
                        $appScheduleInfo = $this->BP->get_app_schedule_info($appSchedule['id'], $sid);
                        if ($appScheduleInfo === false) {
                            $appScheduleInfo = $this->BP->get_rae_app_schedule_info($appSchedule['id'], $sid);
                        }

                        if ($appScheduleInfo !== false) {
                            if (isset($appScheduleInfo['calendar'])) {
                                $calendar = $appScheduleInfo['calendar'];
                                $convertedCal = $this->iCalToSchedule($calendar);
                                if (!$withInfo) {
                                    if ($this->include_ical) {
                                        //get the ical data
                                        $joborder['include_ical'] = $calendar;
                                    }
                                    $joborder['calendar_str'] = $this->getShortCalendarArr($convertedCal);
                                }
                            }

                            if (!$withInfo) {
                                if (isset($appScheduleInfo['backup_options']['appinst_ids'])) {
                                    $instanceID = $appScheduleInfo['backup_options']['appinst_ids'][0];
                                    $instanceInfo = $this->BP->get_appinst_info($instanceID, $sid);

                                    if ($instanceInfo != false) {
                                        $joborder['application_name'] = $instanceInfo[$instanceID]['app_type'];
                                    }

                                    if ($returnInstances === true) {
                                        $joborder['instance_ids'] = $appScheduleInfo['backup_options']['appinst_ids'];
                                    }
                                } else {
                                    $joborder['application_name'] = "file-level";

                                    if ($returnInstances === true) {
                                        $joborder['client_ids'] = array($appSchedule['client_id']);
                                    }
                                }
                            }

                            if ($withInfo) {
                                $hasInstances = isset($appScheduleInfo['backup_options']['appinst_ids']);
                                if ($hasInstances ||
                                    (isset($joborder['is_nas']) && $joborder['is_nas']) ||
                                    (isset($joborder['single_client']) && $joborder['single_client'])
                                ) {
                                    if ($hasInstances) {
                                        $instances = array();
                                        foreach ($appScheduleInfo['backup_options']['appinst_ids'] as $instanceID) {
                                            $instanceInfo = $this->FUNCTIONS->getInstanceNames($instanceID, $sid);
                                            if ($instanceInfo != false) {
                                                $instance = array();
                                                $instance['id'] = $instanceID;
                                                $instance['primary_name'] = $instance['name'] = $instanceInfo['asset_name'];
                                                $instance['client_name'] = $instanceInfo['client_name'];

                                                if ($instanceInfo['app_type'] == Constants::APPLICATION_TYPE_NAME_VMWARE) {
                                                    $instance['secondary_name'] = $instance['name'] = $instanceInfo['asset_name'];
                                                    $instance['excl_list'] = $this->getExcludedVMDKs($instanceID, $sid, Constants::APPLICATION_TYPE_NAME_VMWARE);
                                                } else if ($instanceInfo['app_type'] == Constants::APPLICATION_TYPE_NAME_XEN) {
                                                    $instance['excl_list'] = $this->getExcludedVMDKs($instanceID, $sid, Constants::APPLICATION_TYPE_NAME_XEN);
                                                    $instance['secondary_name'] = $instance['name'] = $instanceInfo['asset_name'];
                                                    $instance['client_name'] = $instanceInfo['client_name'];
                                                }  else if ($instanceInfo['app_type'] == Constants::APPLICATION_TYPE_NAME_AHV) {
                                                    $instance['excl_list'] = $this->getExcludedVMDKs($instanceID, $sid, Constants::APPLICATION_TYPE_NAME_AHV);
                                                    $instance['secondary_name'] = $instance['name'] = $instanceInfo['asset_name'];
                                                    $instance['client_name'] = $instanceInfo['client_name'];
                                                }
                                                $instances[] = $instance;
                                            }
                                        }
                                        if ($instanceInfo['app_type'] != "Hyper-V") {
                                            $joborder['client_name'] = "";
                                        }
                                        $joborder['client_id'] = $appSchedule['client_id'];
                                        $joborder['instances'] = $instances;
                                    }

                                    if ((isset($joborder['is_nas']) && $joborder['is_nas']) ||
                                        (isset($joborder['single_client']) && $joborder['single_client'])
                                    ) {
                                        // It's a single client.
                                        $joborder['client_name'] = $clientInfo !== false && isset($clientInfo['name']) ? $clientInfo['name'] : "";
                                    };

                                    if (isset($convertedCal)) {
                                        $joborder['calendar'] = $convertedCal;
                                        $joborder['ical'] = $calendar;
                                        $joborder['calendar_str'] = $this->getShortCalendarArr($convertedCal);
                                    }
                                    $joborder['include_new'] = $appScheduleInfo['schedule_options']['include_new'];
                                    $joborder['email_report'] = $appScheduleInfo['schedule_options']['email_report'];
                                    $joborder['failure_report'] = $appScheduleInfo['schedule_options']['failure_report'];
                                    if (isset($appScheduleInfo['backup_options']['dev_name'])) {
                                        $storageName = $this->BP->get_storage_for_device($appScheduleInfo['backup_options']['dev_name'], $sid);
                                        if ($storageName !== false) {
                                            $joborder['storage'] = $storageName;
                                        }
                                    } else {
                                        $joborder['storage'] = "";
                                    }

                                    switch ($appScheduleInfo['backup_options']['verify_level']) {
                                        case 0:
                                            $joborder['verify'] = "none";
                                            break;
                                        case 3:
                                            $joborder['verify'] = "inline";
                                            break;
                                    }
                                    if (isset($appScheduleInfo['backup_options']['excl_list'])) {
                                        $joborder['excl_list'] = $appScheduleInfo['backup_options']['excl_list'];
                                    }
                                    if (isset($appScheduleInfo['backup_options']['incl_list'])) {
                                        $joborder['incl_list'] = $appScheduleInfo['backup_options']['incl_list'];
                                    }
                                    if (isset($appScheduleInfo['backup_options']['metanames'])) {
                                        $joborder['metanames'] = $appScheduleInfo['backup_options']['metanames'];
                                    }

                                    // If a NAS or single client, create return clients array for same structure as multi-client job.
                                    if ((isset($joborder['is_nas']) && $joborder['is_nas']) ||
                                        (isset($joborder['single_client']) && $joborder['single_client'])
                                    ) {
                                        $client = array('id' => $clientInfo['id'], 'name' => $clientInfo['name']);
                                        if (isset($joborder['incl_list'])) {
                                            $client['incl_list'] = $joborder['incl_list'];
                                        }
                                        if (isset($joborder['excl_list'])) {
                                            $client['excl_list'] = $joborder['excl_list'];
                                        }
                                        if (isset($joborder['metanames'])) {
                                            $client['metanames'] = $joborder['metanames'];
                                        }
                                        $joborder['clients'] = array($client);
                                    }

                                    if (isset($appScheduleInfo['regular_expressions'])) {
                                        $joborder['regular_expressions'] = $appScheduleInfo['regular_expressions'];;
                                    }
                                } else {
                                    $status = array();
                                    $status['error'] = 500;
                                    $status['message'] = "This schedule is not editable in Satori. Please switch to the " . Constants::FLASH_UI_NAME . " and edit the schedule there.";
                                    //$clientInfo = $this->BP->get_client_info($appSchedule['client_id'], $sid);
                                    //$joborder['client_name'] = $clientInfo['name'];
                                    //$joborder['client_id'] = $clientInfo['id'];
                                    //$joborder['instances'] = array();
                                }
                                global $SLAPolicy;
                                $joborder['for_sla_policy'] = $SLAPolicy->isJobForAPolicy('backup', $joborder['id']);
                            }
                        }
                    }
                    $joborders[] = $joborder;
                }
            }
        }
        if(isset($status['error'])) {
            return $status;
        }
        return $joborders;
    }

    //
    //creates a list of enterprise (multi-client) joborders
    //if there are no enterprise joborders, an empty list is returned
    //
    private function get_enterprise($id, $sid, $sname, $withInfo = false, $returnInstances = false) {
        $joborders = array();

        $enterpriseSchedules = $this->BP->get_schedule_list($sid);

        if ($enterpriseSchedules !== false) {
            foreach ($enterpriseSchedules as $enterpriseSchedule) {
                $joborder = array();
                $joborder['sid'] = $sid;
                if ($id != -1 and $enterpriseSchedule['id'] != $id) {
                    continue;
                } else {
                    if (isset($enterpriseSchedule['id'])) {
                        $joborder['id'] = $enterpriseSchedule['id'] . "e." . $sid;
                    }
                    if (isset($enterpriseSchedule['name'])) {
                        $joborder['name'] = $enterpriseSchedule['name'];
                    }
                    $joborder['type'] = Constants::JOBORDER_TYPE_BACKUP;
                    $joborder['app_id'] = isset($enterpriseSchedule['app_id']) ? $enterpriseSchedule['app_id'] : 1;

                    if (!$withInfo) {
                        $joborder['system_name'] = $sname;
                        if (isset($enterpriseSchedule['description'])) {
                            $joborder['description'] = $enterpriseSchedule['description'];
                        }
                        //TODO: get date created
//                    $joborder['date_created'] = "Waiting on core";
                        if (isset($enterpriseSchedule['enabled'])) {
                            $joborder['enabled'] = $enterpriseSchedule['enabled'];
                        }
                        if (isset($enterpriseSchedule['last_time'])) {
                            $lastTime = $enterpriseSchedule['last_time'];
                            if ($lastTime == 0) {
                                $dateTime = "Never";
                                $joborder['last_status'] = 'None';
                            } else if ($lastTime == 77) {
                                $dateTime = "Has Been Reset";
                            } else {
                                $dateTime = $this->FUNCTIONS->formatDateTime($lastTime);
                            }
                            $joborder['last_time'] = $dateTime;
                        }
                        if (isset($enterpriseSchedule['next_time'])) {
                            $nextTime = $enterpriseSchedule['next_time'];
                            if ($nextTime == 0) {
                                $dateTime = "Never";
                            } else if ($nextTime == 77) {
                                $dateTime = "Has Been Reset";
                            } else {
                                $dateTime = $this->FUNCTIONS->formatDateTime($nextTime);
                            }
                            $joborder['next_time'] = $dateTime;
                        }

                        //get current status of schedule 'Idle' or 'Running'
                        $joborder['status'] = $this->getStatus($enterpriseSchedule['name'], $sid);
                    }

                    if (!$withInfo and !$returnInstances) {
                        if (isset($enterpriseSchedule['calendar'])) {
                            $calendar = $enterpriseSchedule['calendar'];
                            $convertedCal = $this->iCalToSchedule($calendar);
                            if ($this->include_ical) {
                                //get the ical data
                                $joborder['include_ical'] = $calendar;
                            }
                            $joborder['calendar_str'] = $this->getShortCalendarArr($convertedCal);
                        }
                        $joborder['application_name'] = $this->FUNCTIONS->getApplicationTypeFromApplictionID($joborder['app_id']);
                    } else {
                        $enterpriseScheduleInfo = $this->BP->get_schedule_info($enterpriseSchedule['id'], $sid);

                        if ($enterpriseScheduleInfo !== false) {
                            if (isset($enterpriseScheduleInfo['calendar'])) {
                                $calendarID = $enterpriseScheduleInfo['calendar'];
                                $calendar = $this->BP->get_calendar($calendarID, $sid);
                                $convertedCal = $this->iCalToSchedule($calendar);
                                if (!$withInfo) {
                                    if ($this->include_ical) {
                                        //get the ical data
                                        $joborder['include_ical'] = $calendar;
                                    }
                                    $joborder['calendar_str'] = $this->getShortCalendarArr($convertedCal);
                                }
                            }

                            if (!$withInfo) {
                                $joborder['application_name'] = $this->FUNCTIONS->getApplicationTypeFromApplictionID($joborder['app_id']);

                                if ($returnInstances === true) {
                                    $joborder['client_ids'] = array();
                                    foreach ($enterpriseScheduleInfo['clients'] as $client) {
                                        $joborder['client_ids'][] = $client['id'];
                                    }
                                }
                            }

                            if ($withInfo) {
                                if (isset($convertedCal)) {
                                    $joborder['calendar'] = $convertedCal;
                                    $joborder['ical'] = $calendar;
                                    $joborder['calendar_str'] = $this->getShortCalendarArr($convertedCal);
                                }
                                $joborder['include_new'] = $enterpriseScheduleInfo['options']['include_new'];
                                $joborder['email_report'] = $enterpriseScheduleInfo['options']['email_report'];
                                $joborder['failure_report'] = $enterpriseScheduleInfo['options']['failure_report'];
                                $joborder['clients'] = array();
                                foreach ($enterpriseScheduleInfo['clients'] as $client) {
                                    $clientInfo = array();
                                    $clientDetail = $this->BP->get_client_info($client['id'], $sid);
                                    $clientInfo['name'] = $clientDetail['name'];
                                    $clientInfo['id'] = $client['id'];
                                    if ($joborder['app_id'] === Constants::APPLICATION_ID_BLOCK_LEVEL) {
                                        // if block level enterprise schedule, also return block instance ID.
                                        $blockInfo = $this->BP->get_block_info($client['id'], $sid);
                                        if ($blockInfo !== false) {
                                            $clientInfo['instance_id'] = $blockInfo['instance_id'];
                                        }
                                    }
                                    if (isset($client['inclusions'])) {
                                        if (!is_array($client['inclusions'])) {
                                            $inclListInfo = $this->BP->get_selection_list($client['inclusions'], $sid);
                                            $clientInfo['incl_list'] = $inclListInfo['filenames'];
                                        } else {
                                            //if array, user must switch to legacy UI
                                            $status = array();
                                            $status['error'] = 500;
                                            $status['message'] = "This schedule is not editable in Satori. Please switch to the " . Constants::FLASH_UI_NAME . " and edit the schedule there.";
                                        }
                                    }
                                    if (isset($client['exclusions'])) {
                                        if (!is_array($client['exclusions'])) {
                                            $exclListInfo = $this->BP->get_selection_list($client['exclusions'], $sid);
                                            if (isset($exclListInfo['filenames'])) {
                                                $clientInfo['excl_list'] = $exclListInfo['filenames'];
                                            }
                                            if (isset($exclListInfo['metanames'])) {
                                                $clientInfo['metanames'] = $exclListInfo['metanames'];
                                            }
                                        } else {
                                            //if array, user must switch to legacy UI
                                            $status = array();
                                            $status['error'] = 500;
                                            $status['message'] = "This schedule is not editable in Satori. Please switch to the " . Constants::FLASH_UI_NAME . " and edit the schedule there.";
                                        }
                                    }
                                    if (isset($client['options'])) {
                                        if (!is_array($client['options'])) {
                                            $options = $this->BP->get_options($client['options'], $sid);
                                            if (isset($options['before_command'])) {
                                                //remove the CNT: prefix, if SRV: the user attached it himself
                                                if (strlen($options['before_command']) >= 4) {
                                                    if (substr($options['before_command'], 0, 4) == "CNT:") {
                                                        $options['before_command'] = substr($options['before_command'], 4);
                                                    }
                                                }
                                                $clientInfo['before_command'] = $options['before_command'];
                                            }
                                            if (isset($options['after_command'])) {
                                                //remove the CNT: prefix, if SRV: the user attached it himself.
                                                if (strlen($options['after_command']) >= 4) {
                                                    if (substr($options['after_command'], 0, 4) == "CNT:") {
                                                        $options['after_command'] = substr($options['after_command'], 4);
                                                    }
                                                }
                                                $clientInfo['after_command'] = $options['after_command'];
                                            }
                                            if (!isset($joborder['storage'])) {
                                                if (isset($options['dev_name'])) {
                                                    //use new conversion function
                                                    $storageName = $this->BP->get_storage_for_device($options['dev_name'], $sid);
                                                    if ($storageName !== false and $storageName !== null) {
                                                        $joborder['storage'] = $storageName;
                                                    } else {
                                                        $joborder['storage'] = "";
                                                    }
                                                } else {
                                                    $joborder['storage'] = "";
                                                }
                                            }
                                            if (!isset($joborder['verify'])) {
                                                switch ($options['verify_level']) {
                                                    case 0:
                                                        $joborder['verify'] = "none";
                                                        break;
                                                    case 3:
                                                        $joborder['verify'] = "inline";
                                                        break;
                                                    default:
                                                        //user must switch to legacy UI
                                                        $status = array();
                                                        $status['error'] = 500;
                                                        $status['message'] = "This schedule is not editable in Satori. Please switch to the " . Constants::FLASH_UI_NAME . " and edit the schedule there.";
                                                        break;
                                                }
                                            }
                                        } else {
                                            //if array, user must switch to legacy UI
                                            $status = array();
                                            $status['error'] = 500;
                                            $status['message'] = "This schedule is not editable in Satori. Please switch to the " . Constants::FLASH_UI_NAME . " and edit the schedule there.";
                                        }
                                    }
                                    if (isset($status['error'])) {
                                        //if we've determined this schedule can't be edited in Satori, don't bother getting the rest of the info
                                        break;
                                    }
                                    $joborder['clients'][] = $clientInfo;
                                }
                                global $SLAPolicy;
                                $joborder['for_sla_policy'] = $SLAPolicy->isJobForAPolicy('backup', $joborder['id']);
                            }
                        }
                    }
                    $joborders[] = $joborder;
                }
            }
        }
        if(isset($status['error'])) {
            return $status;
        } else {
            return $joborders;
        }

    }

    //
    //creates the list of archive joborders
    //if there are no archive joborders, an empty list is returned
    //
    private function get_archive($id, $sid, $sname, $withInfo = false, $returnInstances = false) {
        $joborders = array();

        $archiveSchedules = $this->BP->get_archive_schedule_list($sid);

        if ($archiveSchedules !== false) {
            foreach ($archiveSchedules as $archiveSchedule) {
                //TODO: last_status

                $joborder = array();
                $joborder['sid'] = $sid;
                if ($id != -1 and $archiveSchedule['id'] != $id) {
                    continue;
                } else {
                    $joborder['id'] = $archiveSchedule['id'] . "a." . $sid;
                    $joborder['name'] = $archiveSchedule['name'];
                    $joborder['type'] = Constants::JOBORDER_TYPE_ARCHIVE;
                    if (!$withInfo) {
                        $joborder['application_name'] = "";
                        $joborder['description'] = $archiveSchedule['description'];
                        $joborder['system_name'] = $sname;
                        $joborder['enabled'] = $archiveSchedule['enabled'];
                        //TODO: get date created
//                    $joborder['date_created'] = "Waiting on core";

                        if (isset($archiveSchedule['last_time'])) {
                            $lastTime = $archiveSchedule['last_time'];
                            if ($lastTime == 0) {
                                $dateTime = "Never";
                                $joborder['last_status'] = 'None';
                            } else if ($lastTime == 77) {
                                $dateTime = "Has Been Reset";
                            } else {
                                $dateTime = $this->FUNCTIONS->formatDateTime($lastTime);
                            }
                            $joborder['last_time'] = $dateTime;
                        }
                        if (isset($archiveSchedule['next_time'])) {
                            $nextTime = $archiveSchedule['next_time'];
                            if ($nextTime == 0) {
                                $dateTime = "Never";
                            } else if ($nextTime == 77) {
                                $dateTime = "Has Been Reset";
                            } else {
                                $dateTime = $this->FUNCTIONS->formatDateTime($nextTime);
                            }
                            $joborder['next_time'] = $dateTime;
                        }

                        $joborder['status'] = $this->getStatus($archiveSchedule['name'], $sid);
                    }

                    if (!$withInfo and !$returnInstances) {
                        if (isset($archiveSchedule['calendar'])) {
                            $calendar = $archiveSchedule['calendar'];
                            $convertedCal = $this->iCalToSchedule($calendar);
                            if ($this->include_ical) {
                                //get the ical data
                                $joborder['include_ical'] = $calendar;
                            }
                            $joborder['calendar_str'] = $this->getShortCalendarArr($convertedCal);
                        }
                    } else {
                        $archiveScheduleInfo = $this->BP->get_archive_schedule_info($archiveSchedule['id'], $sid);
                        if ($archiveScheduleInfo !== false) {
                            if (isset($archiveScheduleInfo['calendar'])) {
                                $calendar = $archiveScheduleInfo['calendar'];
                                $convertedCal = $this->iCalToSchedule($calendar);
                                if (!$withInfo) {
                                    if ($this->include_ical) {
                                        //get the ical data
                                        $joborder['include_ical'] = $calendar;
                                    }
                                    $joborder['calendar_str'] = $this->getShortCalendarArr($convertedCal);
                                }
                            }
                            if ($withInfo) {
                                if (isset($convertedCal)) {
                                    $joborder['calendar'] = $convertedCal;
                                    $joborder['ical'] = $calendar;
                                    $joborder['calendar_str'] = $this->getShortCalendarArr($convertedCal);
                                }
                                //get backup types that are being archived
                                $types = $archiveScheduleInfo['profile']['types'];
                                $formattedTypes = array();
                                foreach ($types as $type) {
                                    $returnType = $this->FUNCTIONS->getBackupTypeDisplayName($type);
                                    if (!in_array($returnType, $formattedTypes)) {
                                        $formattedTypes[] = $returnType;
                                    }
                                }
                                $joborder['types'] = $formattedTypes;
                                $joborder['email_report'] = $archiveScheduleInfo['email_report'];
                                $joborder['range_size'] = $archiveScheduleInfo['profile']['range_size'];
                                $joborder['range_end'] = $archiveScheduleInfo['profile']['range_end'];
                                if (isset($archiveScheduleInfo['profile']['target_slots'])) {
                                    $joborder['slots'] = $archiveScheduleInfo['profile']['target_slots'];
                                }
                                $joborder['storage'] = $archiveScheduleInfo['profile']['target'];
                                if (count($archiveScheduleInfo['profile']['instances']) > 0) {
                                    $instances = array();
                                    $instancesInfo = $this->FUNCTIONS->getInstancesNames($archiveScheduleInfo['profile']['instances'], $sid);
                                    if ($instancesInfo !== false) {
                                        $localSystemID = $this->BP->get_local_system_id();
                                        foreach ($instancesInfo as $instanceInfo) {
                                            $instance = array();
                                            $instance['id'] = $instanceInfo['id'];
                                            $instance['primary_name'] = $instanceInfo['asset_name'];
                                            $instance['application_name'] = $instanceInfo['app_type'];
                                            $instances[] = $instance;

                                            $joborder['client_name'] = $instanceInfo['client_name'];
                                            $joborder['application_name'] = $instanceInfo['app_type'];
                                            if (!isset($joborder['copied_assets'])) {
                                                // check to see if these are replicated instances, set copied_assets to true.
                                                $joborder['copied_assets'] = $this->isGrandClient($instanceInfo['client_id'], $localSystemID, $sid);
                                            }
                                        }
                                    }
                                    $joborder['instances'] = $instances;
                                }
                                if (count($archiveScheduleInfo['profile']['clients']) > 0) {
                                    $clientInfo = $this->BP->get_client_info($archiveScheduleInfo['profile']['clients'][0], $sid);
                                    $joborder['client_name'] = $clientInfo['name'];
                                    $joborder['clients'] = $archiveScheduleInfo['profile']['clients'];
                                }
                                $joborder['options'] = $archiveScheduleInfo['profile']['options'];
                            } elseif ($returnInstances === true) {
                                $joborder['instance_ids'] = $archiveScheduleInfo['profile']['instances'];
                                $joborder['client_ids'] = $archiveScheduleInfo['profile']['clients'];
                                $joborder['target'] = $archiveScheduleInfo['profile']['target'];
                            }

                            global $SLAPolicy;
                            $joborder['for_sla_policy'] = $SLAPolicy->isJobForAPolicy('archive', $joborder['id']);
                        }
                    }
                }
                $joborders[] = $joborder;
            }
        }
        return $joborders;
    }

    //
    //gets the replication joborder
    //if replication is not configured, an empty array is returned.
    //
    public function get_replication($id, $sid, $sname, $withInfo = false, $returnInstances = false)
    {
        $joborders = array();

        $replicationSchedules = $this->BP->get_replication_joborder_list($sid);
        $joborder = array();

        if ($replicationSchedules !== false) {
            foreach ($replicationSchedules as $replicationSchedule) {
                if ($id != -1 and $replicationSchedule['id'] != $id) {
                    continue;
                } else {
                    if (isset($replicationSchedule['id'])) {
                        $joborder['id'] = $replicationSchedule['id'] . "x." . $sid;
                    }
                }
                if (!$withInfo) {
                    $joborder = array(
                        'type' => Constants::JOBORDER_TYPE_REPLICATION,
                        'id' => isset($replicationSchedule['id']) ? $replicationSchedule['id'] . "x." . $sid : "N/A",
                        'sid' => $sid,
                        'system_name' => isset($sname) ? $sname : "N/A",
                        'name' => isset($replicationSchedule['name']) ? $replicationSchedule['name'] : "N/A",
                        'description' => isset($replicationSchedule['description']) ? $replicationSchedule['description'] : "N/A",
                        'enabled' => isset($replicationSchedule['enabled']) ? $replicationSchedule['enabled'] : "N/A",
                        'targets' => $this->getReplicationTargets($replicationSchedule),
                        'date_created' => isset($replicationSchedule['date_created']) ? $this->FUNCTIONS->formatDateTime($replicationSchedule['date_created']) : "N/A",
                        'calendar_str' => isset($replicationSchedule['date_created']) ? $this->FUNCTIONS->formatDateTime($replicationSchedule['date_created']) : "N/A",
                        'last_time' => "N/A",
                        'next_time' => "N/A"
                    );

                    $joborder['status'] = $this->getStatus($replicationSchedule['name'], $sid);

                    if ( $returnInstances === true ) {
                        $jobInfo = $this->BP->get_replication_joborder_info($replicationSchedule['id'], $sid);
                        if ($jobInfo !== false) {
                            $joborder['instance_ids'] = $jobInfo;
                        }
                    }
                } else {
                    $jobInfo = $this->BP->get_replication_joborder_info($replicationSchedule['id'], $sid);
                    if ($jobInfo !== false) {
                        foreach ($jobInfo as $index => $instanceID) {
                            $info = $this->FUNCTIONS->getInstanceNames($instanceID, $sid);
                            if ($info !== false) {

                                $instances[] = array(
                                    'id' => $instanceID,
                                    'app_id' => isset($info['app_id']) ? $info['app_id'] : "",
                                    'app_name' => isset($info['app_name']) ? $info['app_name'] : "",
                                    'primary_name' => isset($info['asset_name']) ? $info['asset_name'] : "",
                                    //  'secondary_name' => isset($info['asset_name']) ? $info['asset_name'] : "",
                                    'client_id' => isset($info['client_id']) ? $info['client_id'] : "",
                                    'client_name' => isset($info['client_name']) ? $info['client_name'] : "",
                                );

                            }
                        }
                    }
                    $joborder = array(
                        'id' => isset($replicationSchedule['id']) ? $replicationSchedule['id'] . "x." . $sid : "n/a",
                        'name' => isset($replicationSchedule['name']) ? $replicationSchedule['name'] : "n/a",
                        'type' => Constants::JOBORDER_TYPE_REPLICATION,
                        'sid' => $sid,
                        'targets' => $this->getReplicationTargets($replicationSchedule),
                        'instances' => $instances
                    );

                    global $SLAPolicy;
                    $joborder['for_sla_policy'] = $SLAPolicy->isJobForAPolicy('replication', $joborder['id']);
                }

                $joborders[] = $joborder;
            }
        }
        return $joborders;
    }

    function getReplicationTargets($schedule)
    {
        $targetsInfo = isset($schedule['targets']) ? $schedule['targets'] : false;
        if ($targetsInfo) {
            foreach ($targetsInfo as $id => $name) {
                $targets[] = array('target_id' => $id, 'target_name' => $name);
            }
        }
        return $targets;
    }

    public function get_orphaned_instances($sid){

        $instance = array();
        $instances = $this->BP->get_replicating_instances_not_in_joborders($sid);
        if ($instances !== false){
            foreach ($instances as $index => $id){
                $instanceInfo = $this->BP->get_appinst_info($id, $sid);
                foreach($instanceInfo as $id => $info){
                    $info['id'] = $id;
                    $instance[] = $info;
                }
            }
        }

        return $instance;
    }

    
    private function get_certification($id, $sid, $sname, $withInfo = false ){
        $ret = array();
        if($sid == $this->BP->get_local_system_id()){
            $ret = $this->get_certification_local($id, $sid, $sname, $withInfo);
        } else {
            $ret = $this->get_certification_remote($id, $sid);
        }
        return $ret;
    }

    private function get_certification_remote($id, $sid){
        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        if ($id == -1){
            $api = "/api/joborders/certification/";
        } else {
            $api = "/api/joborders/" . $id . "c/";
        }

        $remoteRet = $this->FUNCTIONS->remoteRequestRDR($url, 'GET', $api, "",null);

        $ret = array();
        if(is_array($remoteRet) && isset($remoteRet['data'])){
            foreach($remoteRet['data'] as $joborder){
                $joborder['sid'] = $sid;
                // Since local and remote systems can have a different sid for the
                //  remote system, strip the sid from the remote system and use the
                //  one given locally
                $id = explode(".",$joborder['id']);
                $joborder['id'] = $id[0] . "." . $sid;
                $ret[] = $joborder;
            }
        }
        return $ret;
    }

    private function get_certification_local($id, $sid, $sname, $withInfo = false ){
        $joborders = array();

        // Don't show certification jobs to monitor users
        $reqPriv = constants::PRIV_MONITOR;
        $curPriv = $this->FUNCTIONS->GetCurrentPrivileges();
        if ($curPriv <= $reqPriv){
            return $joborders;
        }

        if($id == -1){
            $certificationSchedules = $this->RDR->get_jobs();
            if(isset($certificationSchedules['error'])){
                return $certificationSchedules;
            }
        } else {
            $certificationSchedules[] = $this->RDR->get_job($id);
            if(isset($certificationSchedules[0]['error'])){
                return $certificationSchedules[0];
            }
        }
        foreach ($certificationSchedules as $certificationSchedule) {
            $joborder = array();
            $joborder['sid'] = $sid;
            if($id != -1 and $certificationSchedule['id'] != $id) {
                continue;
            } else {
                $joborder['id'] = $certificationSchedule['id'] . "c." . $sid;
                $joborder['name'] = $certificationSchedule['name'];
                $joborder['type'] = Constants::JOBORDER_TYPE_CERTIFICATION;
                $joborder['certification_options']['power_on_timeout'] = $certificationSchedule['poweron_timeout'];
                $joborder['certification_options']['service_profile_id'] = $certificationSchedule['profile_id'];
                $joborder['certification_options']['post_custom_script'] = $certificationSchedule['post_script_cmd'];
                $joborder['certification_options']['post_custom_script_arguments'] = $certificationSchedule['post_script_args'];
                $joborder['certification_options']['suffix_name'] = $certificationSchedule['suffix_name'];
                switch($certificationSchedule['status']) {
                    case 'Normal' :
                        $joborder['status'] = 'Idle';
                        break;
                    default:
                        $joborder['status'] = $certificationSchedule['status'];
                }


                if(!$withInfo) {
                    if(isset($certificationSchedule['description'])) {
                        $joborder['description'] = $certificationSchedule['description'];
                    }
                    $joborder['system_name'] = $sname;
                    $joborder['enabled'] = ($certificationSchedule['is_enabled']) ? 1 : 0;

                    if(isset($certificationSchedule['last_run_time'])) {
                        $lastTime = strtotime($certificationSchedule['last_run_time']);
                        $joborder['last_time'] = $this->FUNCTIONS->formatDateTime($lastTime);
                    } else {
                        $joborder['last_time'] = 'Never';
                    }
                    if(isset($certificationSchedule['next_run_time'])) {
                        $nextTime = strtotime($certificationSchedule['next_run_time']);
                        $joborder['next_time'] = $this->FUNCTIONS->formatDateTime($nextTime);
                    } else {
                        $joborder['next_time'] = 'Never';
                    }
                    if(!isset($joborder['last_status'])) {
                        $scheduleHistory = $this->RDR->get_last_schedule_history($certificationSchedule['id']);
                        if (isset($scheduleHistory['job_result'])) {
                            switch($scheduleHistory['job_result']) {
                                case 'Successful':
                                    $joborder['last_status'] = 'Success';
                                    break;
                                case 'Failed':
                                    $joborder['last_status'] = 'Failure';
                                    break;
                                case null :
                                    $joborder['last_status'] = 'Never';
                                    break;
                                default:
                                    $joborder['last_status'] = $scheduleHistory['job_result'];
                            }
                        }
                    }
                    if(isset($certificationSchedule['schedule'])){
                        $calendar = $this->formatRdrScheduleArr($certificationSchedule['schedule']);
                        $calendar['backup_type'] = $joborder['type'];
                        $joborder['calendar_str'] = $this->getShortCalendarArr(array($calendar));
                    }
                } else {
                    if(isset($certificationSchedule['schedule'])){
                        $joborder['calendar'][] = $this->formatRdrScheduleArr($certificationSchedule['schedule']);
                    }
                    $joborder['service_profile'] = $certificationSchedule['profile'];
                    $joborder['certification_target']['instances'] = array();
                    foreach($certificationSchedule['vms'] as $vm){
                        $instance = array();
                        foreach($vm as $key => $value){
                            switch($key){
                                case "id":
                                    $instance['vm_id'] = $value;
                                    break;
                                case "instance_id":
                                    $instance['id'] = $value;
                                    break;
                                case "guest_credential_id":
                                    $instance['credential_id'] = $value;
                                    break;
                                case "guest_user":
                                    $instance['username'] = $value;
                                    break;
                                case "guest_password":
                                    $instance['password'] = $value;
                                    break;
                                case "guest_os":
                                    $instance['os_type'] = $value;
                                    break;
                                case "is_poweron":
                                    $instance['power_on'] = $value;
                                    break;
                                case "vm_ips":
                                    foreach($value as $network){
                                        $newNetwork = array(
                                            "id" => $network['id'],
                                            "name" => $network['name'],
                                            "ip_address" => $network['ip_addr'],
                                            "mask" => $network['mask'],
                                            "gateway" => $network['gateway'],
                                            "dns1" => $network['dns1'],
                                            "dns2" => $network['dns2']
                                        );
                                        $instance['networks'][] = $newNetwork;
                                    } 
                                    break;
                                case "vm_tests":
                                    foreach($value as $test){
                                        $newTest = array(
                                            "id" => $test['id'],
                                            "name" => $test['name'],
                                            "command" => $test['command'],
                                            "is_custom" => $test['is_custom'],
                                            "timeout_min" => $test['timeout_m'],
                                            "priority" => $test['priority'],
                                            "app_test_id" => $test['app_test_id']
                                        );
                                        foreach($test['vm_test_params'] as $param){
                                            $newParam = array(
                                                "id" => $param['id'],
                                                "name" => $param['name'],
                                                "value" => $param['value']
                                            );
                                            $newTest['parameters'][] = $newParam;
                                        }
                                        $instance['application_tests'][] = $newTest;
                                    }
                                    break;
                                default:
                                    $instance[$key] = $value;
                            }
                        }
                        $joborder['certification_target']['instances'][] = $instance;
                    }
                }
                $certificationScheduleInfos = $this->RDR->get_schedule_info();
                // Find the schedule that matches the job
                foreach( $certificationScheduleInfos as $info ){
                    if ($info['jobId'] == $certificationSchedule['id']){
                        $certificationScheduleInfo = $info;
                        break;
                    }
                }
                if(isset($certificationScheduleInfo)) {
                    if(isset($certificationScheduleInfo['description'])) {
                        $joborder['description'] = $certificationScheduleInfo['description'];
                    }
                    if($withInfo) {
                        if(isset($certificationScheduleInfo['email_report'])){
                            $joborder['email_report'] = $certificationScheduleInfo['email_report'];
                        }
                        if(isset($certificationScheduleInfo['failure_report'])){
                            $joborder['failure_report'] = $certificationScheduleInfo['failure_report'];
                        }
                    }
                    unset($certificationScheduleInfo);
                }

                $joborders[] = $joborder;
            }
        }
        return $joborders;
    }

    public function getStatus($scheduleName, $sid)
    {
        $jobs = $this->BP->get_job_list($sid);
        if ($jobs != false) {
            foreach ($jobs as $jobID => $jobName) {
                if (isset($joborder['status'])) {
                    break;
                } else {
                    $jobInfo = $this->BP->get_job_info($jobID, $sid);
                    if (isset($jobInfo['schedule_name']) and $jobInfo['schedule_name'] == $scheduleName) {
                        switch ($jobInfo['status']) {
                            case '*ON HOLD*':
                            case 'QUEUED':
                            case 'CONNECTING':
                            case ' *ACTIVE*':
                            case 'ACTIVE':
                            case 'DB. UPDATING':
                                $status = 'Running';
                                break;
                        }
                    }
                }
            }
        }
        $replicationJobs = $this->BP->get_replication_active_job_info(array('system_id' => $sid));

        if ($replicationJobs !== false) {
            foreach ($replicationJobs as $job) {
                if (isset($job['schedule_name']) and $job['schedule_name'] == $scheduleName) {
                    switch ($job['status']) {
                        case "queued":
                        case "active":
                            $status = "Running";
                            break;
                        default:
                            $status = "Idle";
                            break;
                    }
                }
            }
        }

        if (!isset($status)) {
            $status = 'Idle';
        }
        return $status;
    }

    public function delete($which, $sid, $overrideSLAPolicy = false) {
        $status = false;
        $type = "";
        $id = -1;
        $idPieces = explode('.', $which);
        if(is_numeric($idPieces[0]) and $idPieces[0] == 0) {
            $type = 'replication';
        } elseif(is_numeric(substr($idPieces[0], 0, -1))) {
            $id = (int)substr($idPieces[0], 0, -1);
            switch(substr($idPieces[0], -1)) {
                case 'b':
                    $type = 'backup';
                    break;
                case 'e':
                    $type = 'enterprise';
                    break;
                case 'a':
                    $type = 'archive';
                    break;
                case 'r':
                    $type = 'restore';
                    break;
                case 'i':
                    $type = 'ir';
                    break;
                case 'f':
                    $type = 'flr';
                    break;
                case 'x':
                    $type = 'replication';
                    break;
                case 'c':
                    $type = 'certification';
                    break;
            }
        }
        global $SLAPolicy;
        $associatedPolicy = $SLAPolicy->isJobForAPolicy($type, $id);
        if ($overrideSLAPolicy === false && is_array($associatedPolicy)) {
            $policyName = isset($associatedPolicy['name']) ? " " . $associatedPolicy['name'] : "";
            $status = array('error' => 500,
                'message' => 'This job is associated with an existing SLA policy, so you cannot delete it.  ' .
                            'If you want to remove this job, you should delete the SLA policy' .
                            $policyName . '.');
        } else {
            switch ($type) {
                case 'backup':
                    $status = $this->BP->delete_app_schedule($id, $sid);
                    break;
                case 'enterprise':
                    //get clients in schedule and remove selection lists
                    $scheduleInfo = $this->BP->get_schedule_info($id, $sid);
                    if ($scheduleInfo !== false) {
                        $calendars = $this->BP->get_calendar_list($sid);
                        $satoriSchedule = true;
                        foreach ($calendars as $calendar) {
                            if ($calendar['id'] != $scheduleInfo['calendar']) {
                                continue;
                            } else {
                                if ($calendar['family'] != "Satori-file-level") {
                                    $satoriSchedule = false;
                                    $status = array();
                                    $status['error'] = 500;
                                    $status['message'] = "This schedule is not deletable in Satori. Please switch to the " . Constants::FLASH_UI_NAME . " and delete the schedule there.";
                                }
                            }
                        }
                        if ($satoriSchedule !== false) {
                            //don't delete selection lists until we know the schedule is deleted successfully
                            $status = $this->BP->delete_schedule($id, $sid);
                            if ($status !== false) {
                                $this->BP->delete_calendar($scheduleInfo['calendar'], $sid);
                                $globalID = -1;
                                foreach ($scheduleInfo['clients'] as $client) {
                                    if (isset($client['inclusions'])) {
                                        $this->BP->delete_selection_list($client['inclusions'], $sid);
                                    }
                                    if (isset($client['exclusions'])) {
                                        $this->BP->delete_selection_list($client['exclusions'], $sid);
                                    }
                                    if (isset($client['options']) and $client['options'] !== $globalID) {
                                        $optionsList = $this->BP->get_options($client['options'], $sid);
                                        if (isset($optionsList['before_command']) or isset($optionsList['after_command'])) {
                                            //dealing with single options list
                                            $this->BP->delete_options($client['options'], $sid);
                                        } else {
                                            //dealing with global
                                            $globalID = $client['options'];
                                            $this->BP->delete_options($globalID, $sid);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $status = $scheduleInfo;
                    }
                    break;
                case 'archive':
                    $status = $this->BP->delete_archive_schedule($id, $sid);
                    break;
                case 'replication':
                    $status = $this->BP->delete_replication_joborder($id, $sid);
                    //$status['error'] = 403;
                    // $status['message'] = "Deletion of replication joborder is not allowed. Remove replication to remove joborder";
                    break;
                case 'certification':
                    // check if system is remote
                    if (is_numeric($sid) && $sid != $this->BP->get_local_system_id()) {
                        $remoteSysInfo = $this->BP->get_system_info($sid);
                        $url = $remoteSysInfo['host'];
                        $api = "/api/joborders/" . $id . "c/";

                        $remoteStatus = $this->FUNCTIONS->remoteRequestRDR($url, 'DELETE', $api, "", null);

                        $status = array();
                        if (isset($remoteStatus['message'])) {
                            // pass on error message
                            $status = $remoteStatus['message'];
                        } else {
                            $status['result'] = $remoteStatus['result'];
                        }
                    } else {
                        $status = $this->RDR->delete_job($id);
                    }
                    break;
            }
        }

        return $status;
    }

    public function save_schedule($which, $data, $sid) {
        //because we are creating a schedule, we have to have a system id or we use local
        $status = false;
        if(!$sid) {
            $sid = $this->BP->get_local_system_id();
        }
        $type = "";
        $idPieces = explode('.', $which);
        if(is_numeric(substr($idPieces[0], 0, -1)) and $idPieces[0] != -1) {
            $id = (int)substr($idPieces[0], 0, -1);
            switch(substr($idPieces[0], -1)) {
                case 'b':
                    $type = 'backup';
                    break;
                case 'e':
                    $type = 'enterprise';
                    break;
                case 'a':
                    $type = 'archive';
                    break;
                case 'r':
                    $type = 'restore';
                    break;
                case 'i':
                    $type = 'ir';
                    break;
                case 'f':
                    $type = 'flr';
                    break;
                case 'x':
                    $type = 'replication';
                    break;
                case 'c':
                    $type = 'certification';
                    break;
            }
            switch($type) {
                case 'backup':
                    $appSchedules = $this->BP->get_app_schedule_list(-1, -1, $sid);

                    foreach ($appSchedules as $appSchedule) {
                        if($appSchedule['id'] == $id) {
                            $scheduleInfo = $this->BP->get_app_schedule_info($id, $sid);
                            if($scheduleInfo == false) {
                                $scheduleInfo = $this->BP->get_rae_app_schedule_info($id, $sid);
                            }
                            if($appSchedule['app_id'] == 1 and !isset($scheduleInfo['backup_options']['appinst_ids'])) {
                                //valid copy request
                                unset($appSchedule['last_time']);
                                unset($appSchedule['next_time']);
                                if(isset($appSchedule['esx_uuid'])) {
                                    unset($appSchedule['esx_uuid']);
                                }
                                if(isset($appSchedule['key'])) {
                                    unset($appSchedule['key']);
                                }
                                unset($appSchedule['id']);
                                $appSchedule['name'] = "Copy of " . $appSchedule['name'];
                                $inputParams = array_merge($appSchedule, $scheduleInfo);
                                //add reg expressions due to regex feature
                                $inputParams['regular_expressions'] = array();

                                $result = $this->BP->save_app_schedule_info($inputParams, $sid);
                                if($result !== false) {
                                    if(!is_numeric($result)) {
                                        if (is_array($result)) {
                                            $status = $this->gatherDetailedSaveInfo($result, $sid);
                                        } else {
                                            $status = $result;
                                        }
                                    } else {
                                        $status = array();
                                        $status[]['id'] = $result . "b." . $sid;
                                        $status = array('result' => $status);
                                    }
                                } else {
                                    $status = $result;
                                }
                            } else {
                                $status = array();
                                $status['error'] = 405;
                                $status['message'] = "Joborders can only be copied if they contain no instances.";
                            }
                            break;
                        }
                    }
                    if(!isset($status)) {
                        $status = array();
                        $status['error'] = 404;
                        $status['message'] = "Joborder with id " . $id . " not found.";
                    }
                    break;
                case 'enterprise':
                    $schedules = $this->BP->get_schedule_list($sid);
                    foreach($schedules as $schedule) {
                        if($schedule['id'] == $id) {
                            $scheduleInfo = $this->BP->get_schedule_info($id, $sid);
                            if($scheduleInfo !== false) {
                                unset($schedule['last_time']);
                                unset($schedule['next_time']);
                                unset($schedule['id']);
                                $schedule['name'] = "Copy of " . $schedule['name'];
                                if(isset($scheduleInfo['options']['include_new'])) {
                                    $scheduleInfo['options']['include_new'] = false;
                                }
                                $inputParams = array_merge($schedule, $scheduleInfo);
                                $result = $this->BP->save_schedule_info($inputParams, $sid);
                                if($result !== false) {
                                    if(!is_numeric($result)) {
                                        if(is_array($result)) {
                                            $status = $this->gatherDetailedEnterpriseSaveInfo($result, $sid);
                                        } else {
                                            $status = $result;
                                        }
                                    } else {
                                        //successful save
                                        $status = array();
                                        $status[]['id'] = $result . "e." . $sid;
                                        $status = array('result' => $status);
                                    }
                                } else {
                                    $status = $result;
                                }
                            }
                            break;
                        }
                    }
                    if(!isset($status)) {
                        $status = array();
                        $status['error'] = 404;
                        $status['message'] = "Joborder with id " . $id . " not found.";
                    }
                    break;
                case 'archive':
                    $status = false;
                    $scheduleInfo = $this->BP->get_archive_schedule_info($id, $sid);
                    if($scheduleInfo != false) {
                        unset($scheduleInfo['id']);
                        $scheduleInfo['name'] = "Copy of " . $scheduleInfo['name'];
                        $scheduleInfo['description'] = $scheduleInfo['name'];
                        $scheduleInfo['profile']['description'] = $scheduleInfo['name'];
                        $result = $this->BP->save_archive_schedule_info($scheduleInfo, $sid);
                        if($result !== false) {
                            if(!is_numeric($result)) {
                                $status = $result;
                            } else {
                                $status = array();
                                $status[]['id'] = $result . "a." . $sid;
                            }
                            $status = array('result' => $status);
                        } else {
                            $status = $result;
                        }
                    }
                    break;
                case 'replication':
                    $status = false;
                    $scheduleInfo = $this->BP->get_replication_joborder_info($id, $sid);
                    if($scheduleInfo != false) {
                        unset($scheduleInfo['id']);
                        $scheduleInfo['name'] = "Copy of " . $scheduleInfo['name'];
                        $result = $this->BP->save_replication_joborder_info($scheduleInfo, $sid);
                        if($result !== false) {
                            if(!is_numeric($result)) {
                                $status = $result;
                            } else {
                                $status = array();
                                $status[]['id'] = $result . "x." . $sid;
                            }
                            $status = array('result' => $status);
                        } else {
                            $status = $result;
                        }
                    }
                    break;
                case 'certification':
                    $sname = $this->BP->get_hostname($sid);
                    $scheduleInfoArray = $this->get_certification($id,$sid,$sname,true);
                    $scheduleInfo = $scheduleInfoArray[0];
                    if($scheduleInfo != false) {
                        unset($scheduleInfo['id']);
                        unset($scheduleInfo['last_time']);
                        unset($scheduleInfo['last_status']);
                        unset($scheduleInfo['profile']);
                        $scheduleInfo['name'] = "Copy of " . $scheduleInfo['name'];
                        $result = $this->post_certification($scheduleInfo,$sid);
                        if($result !== false) {
                            if(is_array($result) && array_key_exists('id',$result)){
                                $status = array();
                                $status[]['id'] = $result['id'];
                                $status = array('result' => $status);
                            } else {
                                $status = $result;
                            }
                        } else {
                            $status = array();
                        }
                    }

                    break;
            }
        } else {
            switch($data['type']) {

                case 'Backup':
                case 'backup':
                    //dealing with saving new schedule
                    $backupOptions = array();
                    $storageValid = true;
                    if(isset($data['storage'])) {
                        if($data['storage'] == "" or strtolower($data['storage']) == "internal") {
                            $backupOptions['dev_name'] = "";
                        } else {
                            $deviceName = $this->BP->get_device_for_storage($data['storage'], $sid);
                            if($deviceName !== false) {
                                $backupOptions['dev_name'] = $deviceName;
                            } else {
                                $storageValid = false;
                            }
                        }
                    }
                    if($storageValid !== false) {
                        if(isset($data['verify'])) {
                            switch($data['verify']) {
                                case 'none':
                                    $backupOptions['verify_level'] = 0;
                                    break;
                                case 'inline':
                                    $backupOptions['verify_level'] = 3;
                                    break;
                            }
                        } else {
                            $backupOptions['verify_level'] = 3;
                        }
                        $inputParams = array();
                        $inputParams['name'] = $data['name'];
                        if(isset($data['description'])) {
                            $inputParams['description'] = $data['description'];
                        } else {
                            $inputParams['description'] = $data['name'];
                        }
                        $inputParams['enabled'] = true;

                        if(isset($data['clients']) && !empty($data['clients'])) /*and count($data['clients']) > 1)*/ {
                            //multi-client joborder
                            $appType = (isset($data['app_id']) && ((int)$data['app_id'] === Constants::APPLICATION_ID_BLOCK_LEVEL)) ?
                                Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL : Constants::APPLICATION_NAME_FILE_LEVEL;

                            //convert calender to iCal and then to calender ID
                            $calendarInput = array();
                            if (isset($data['ical']) && !isset($data['calendar'])) {
                                $calendarReturn = $data['ical'];
                            } else {
                                $calendarReturn = $this->scheduleToICal($data['calendar'], $appType);
                            }
                            if(is_array($calendarReturn) and isset($calendarReturn['error'])) {
                                $status = $calendarReturn;
                            } else {
                                $calendarInput['contents'] = $calendarReturn;
                                $calendarInput['name'] = microtime();
                                $calendarInput['description'] = "Satori calendar for schedule " . $data['name'];
                                $calendarInput['family'] = "Satori-file-level";
                                $calendarID = $this->BP->save_calendar($calendarInput, $sid);
                                $inputParams['calendar'] = $calendarID;

                                $globalClientOptions = array();
                                $globalClientOptions['name'] = "";
                                $globalClientOptions['description'] = "Satori options for schedule " . $data['name'];
                                $globalClientOptions['type'] = "backup";
                                $globalClientOptions['family'] = "Satori-file-level";
                                $globalClientOptions['options'] = $backupOptions;

                                $globalInclusionOptions = array();
                                $globalInclusionOptions['name'] = "";
                                $globalInclusionOptions['type'] = "inclusion";
                                $globalInclusionOptions['family'] = "Satori-file-level";

                                $globalExclusionOptions = array();
                                $globalExclusionOptions['name'] = "";
                                $globalExclusionOptions['type'] = "exclusion";
                                $globalExclusionOptions['family'] = "Satori-file-level";

                                $inputParams['clients'] = array();

                                $inclSelectionLists = array();
                                $exclSelectionLists = array();
                                $optionsLists = array();

                                $globalOptionID = -1;
                                //loop through clients array to create individual inclusions, exclusions and options lists
                                foreach($data['clients'] as $client) {
                                    $clientOptions = array();
                                    $clientOptions['id'] = $client['id'];
                                    if(isset($client['incl_list']) and !empty($client['incl_list'])) {
                                        $singleClientInclusions = $globalInclusionOptions;
                                        $singleClientInclusions['name'] = microtime();
                                        $singleClientInclusions['description'] = "Satori inclusion list for client " . $client['id'] . " in schedule " . $data['name'];
                                        $singleClientInclusions['client'] = $client['id'];
                                        $singleClientInclusions['filenames'] = $client['incl_list'];
                                        $inclusionListID = $this->BP->save_selection_list($singleClientInclusions, $sid);
                                        $clientOptions['inclusions'] = $inclusionListID;
                                        $inclSelectionLists[] = array("list_id" => $inclusionListID, "client_id" => $client['id']);
                                    }
                                    if((isset($client['excl_list']) and !empty($client['excl_list'])) or (isset($client['metanames']) and !empty($client['metanames']))) {
                                        $singleClientExclusions = $globalExclusionOptions;
                                        $singleClientExclusions['name'] = microtime();
                                        $singleClientExclusions['description'] = "Satori exclusion list for client " . $client['id'] . " in schedule " . $data['name'];
                                        $singleClientExclusions['client'] = $client['id'];
                                        if(isset($client['excl_list']) and !empty($client['excl_list'])) {
                                            $singleClientExclusions['filenames'] = $client['excl_list'];
                                        }
                                        if(isset($client['metanames']) and !empty($client['metanames'])) {
                                            $singleClientExclusions['metanames'] = $client['metanames'];
                                        }
                                        $exclusionListID = $this->BP->save_selection_list($singleClientExclusions, $sid);
                                        $clientOptions['exclusions'] = $exclusionListID;
                                        $exclSelectionLists[] = array("list_id" => $exclusionListID, "client_id" => $client['id']);
                                    }
                                    if((isset($client['before_command']) or isset($client['after_command'])) and (!empty($client['before_command']) or !empty($client['after_command']))) {
                                        //create an explicit list for this client only
                                        $singleClientOptions = $globalClientOptions;
                                        if(isset($client['before_command']) && !empty($client['before_command'])) {
                                            if(strlen($client['before_command']) >= 4) {
                                                $firstFour = substr($client['before_command'], 0, 4);
                                                if($firstFour !== "CNT:" and $firstFour !== "SRV:") {
                                                    //add the CNT: directive
                                                    $client['before_command'] = "CNT:" . $client['before_command'];
                                                }
                                            } else {
                                                //add the CNT: directive as default
                                                $client['before_command'] = "CNT:" . $client['before_command'];
                                            }
                                            $singleClientOptions['options']['before_command'] = $client['before_command'];
                                        }
                                        if(isset($client['after_command']) && !empty($client['after_command'])) {
                                            if(strlen($client['after_command']) >= 4) {
                                                $firstFour = substr($client['after_command'], 0, 4);
                                                if($firstFour !== "CNT:" and $firstFour !== "SRV:") {
                                                    //add the CNT: directive
                                                    $client['after_command'] = "CNT:" . $client['after_command'];
                                                }
                                            } else {
                                                //add the CNT: directive as default
                                                $client['after_command'] = "CNT:" . $client['after_command'];
                                            }
                                            $singleClientOptions['options']['after_command'] = $client['after_command'];
                                        }
                                        $singleClientOptions['name'] = microtime();
                                        $optionListID = $this->BP->save_options($singleClientOptions, $sid);
                                        $clientOptions['options'] = $optionListID;
                                        $optionsLists[] = array("list_id" => $optionListID, "client_id" => $client['id']);
                                    } else {
                                        if($globalOptionID == -1) {
                                            //create the global list
                                            $globalClientOptions['name'] = microtime();
                                            $globalOptionID = $this->BP->save_options($globalClientOptions, $sid);
                                            $optionsLists[] = array("list_id" => $globalOptionID, "client_id" => -1);
                                        }
                                        //use the existing global list
                                        $clientOptions['options'] = $globalOptionID;
                                    }
                                    $inputParams['clients'][] = $clientOptions;
                                }

                                $options = array();
                                if(isset($data['include_new'])) {
                                    $options['include_new'] = $data['include_new'];
                                }
                                if(isset($data['email_report'])) {
                                    $options['email_report'] = $data['email_report'];
                                }
                                if(isset($data['failure_report'])) {
                                    $options['failure_report'] = $data['failure_report'];
                                }
                                $inputParams['options'] = $options;

                                $result = $this->BP->save_schedule_info($inputParams, $sid);
                                if($result !== false and is_numeric($result)) {
                                    //successful save
                                    $status = array();
                                    $status[]['id'] = $result . "e." . $sid;

                                    //loop through and re-save all lists with unique name
                                    $calendarModify = array();
                                    $calendarModify['id'] = $calendarID;
                                    $calendarModify['name'] = "Cal " . $calendarID . " for JO " . $result;
                                    $this->BP->save_calendar($calendarModify, $sid);

                                    foreach($inclSelectionLists as $selectionList) {
                                        $selectionListInfo = $this->BP->get_selection_list($selectionList['list_id'], $sid);
                                        if($selectionListInfo !== false) {
                                            $selectionListInfo['name'] = "Incl " . $selectionList['list_id'] . " JO " . $result . " client " . $selectionList['client_id'];
                                            $this->BP->save_selection_list($selectionListInfo, $sid);
                                        }
                                    }
                                    foreach($exclSelectionLists as $selectionList) {
                                        $selectionListInfo = $this->BP->get_selection_list($selectionList['list_id'], $sid);
                                        if($selectionListInfo !== false) {
                                            $selectionListInfo['name'] = "Excl " . $selectionList['list_id'] . " JO " . $result . " client " . $selectionList['client_id'];
                                            $this->BP->save_selection_list($selectionListInfo, $sid);
                                        }
                                    }

                                    foreach($optionsLists as $optionList) {
                                        $optionListModify = array();
                                        $optionListModify['id'] = $optionList['list_id'];
                                        if($optionList['client_id'] !== -1) {
                                            $optionListModify['name'] = "Opt " . $optionList['list_id'] . " JO " . $result . " client " . $optionList['client_id'];
                                        } else {
                                            $optionListModify['name'] = "Opt " . $optionList['list_id'] . " JO " . $result;
                                        }
                                        $this->BP->save_options($optionListModify, $sid);
                                    }

                                    $status = array('result' => $status);
                                } else {
                                    //schedule failed to save
                                    $status = $result;
                                    if($result !== false) {
                                        $status = $this->gatherDetailedEnterpriseSaveInfo($result, $sid);
                                    }
                                    //remove all lists
                                    $this->BP->delete_calendar($calendarID, $sid);

                                    foreach($inclSelectionLists as $selectionList) {
                                        $this->BP->delete_selection_list($selectionList['list_id'], $sid);
                                    }
                                    foreach($exclSelectionLists as $selectionList) {
                                        $this->BP->delete_selection_list($selectionList['list_id'], $sid);
                                    }

                                    foreach($optionsLists as $optionList) {
                                        $this->BP->delete_options($optionList['list_id']);
                                    }
                                }
                            }

                        } else {
                            //single client or application
                            $scheduleOptions = array();

                            $appType = "";
                            if(isset($data['client_id'])) {
                                $scheduleOptions['client_id'] = $data['client_id'];
                                $inputParams['app_id'] = 1;
                                $appType = "file-level";
                                if(isset($data['excl_list'])) {
                                    $backupOptions['excl_list'] = $data['excl_list'];
                                }
                                if(isset($data['incl_list'])) {
                                    $backupOptions['incl_list'] = $data['incl_list'];
                                }
                                if(isset($data['metanames'])) {
                                    $backupOptions['metanames'] = $data['metanames'];
                                }
                            } elseif (isset($data['instances'])) {
                                // Handle instances as objects or ids.
                                if (is_array($data['instances'][0])) {
                                    $instanceInfo = $this->BP->get_appinst_info($data['instances'][0]['id'], $sid);
                                    $scheduleOptions['client_id'] = $instanceInfo[$data['instances'][0]['id']]['client_id'];
                                    $backupOptions['appinst_ids'] = array_map(function($o) { return $o['id']; }, $data['instances']);
                                    $inputParams['app_id'] = $instanceInfo[$data['instances'][0]['id']]['app_id'];
                                    $appType = $instanceInfo[$data['instances'][0]['id']]['app_type'];
                                    if ($appType == Constants::APPLICATION_TYPE_NAME_VMWARE ||
                                        $appType == Constants::APPLICATION_TYPE_NAME_XEN ||
                                        $appType == Constants::APPLICATION_TYPE_NAME_AHV) {
                                        $diskInfo = $this->processVMDKs($data['instances'], $sid, $appType);
                                    }
                                } else {
                                    $instanceInfo = $this->BP->get_appinst_info($data['instances'][0], $sid);
                                    $scheduleOptions['client_id'] = $instanceInfo[$data['instances'][0]]['client_id'];
                                    $backupOptions['appinst_ids'] = $data['instances'];
                                    $inputParams['app_id'] = $instanceInfo[$data['instances'][0]]['app_id'];
                                    $appType = $instanceInfo[$data['instances'][0]]['app_type'];
                                }
                            }

                            if (isset($data['ical']) && !isset($data['calendar'])) {
                                $calendarReturn = $data['ical'];
                            } else {
                                $calendarReturn = $this->scheduleToICal($data['calendar'], $appType);
                            }
                            if(is_array($calendarReturn) and isset($calendarReturn['error'])) {
                                $status = $calendarReturn;
                            } else {
                                $inputParams['calendar'] = $calendarReturn;
                                $scheduleOptions['include_new'] = isset($data['include_new']) ? $data['include_new'] : false;
                                $scheduleOptions['email_report'] = isset($data['email_report']) ? $data['email_report'] : false;
                                $scheduleOptions['failure_report'] = isset($data['failure_report']) ? $data['failure_report'] : false;

                                $inputParams['backup_options'] = $backupOptions;
                                $inputParams['schedule_options'] = $scheduleOptions;
                                $inputParams['regular_expressions'] = $this->buildRegex($data);
                                switch($appType) {
                                    case Constants::APPLICATION_TYPE_NAME_ORACLE:
                                    case Constants::APPLICATION_TYPE_NAME_SHAREPOINT:
                                    case Constants::APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE:
                                        //RAE types
                                        $result = $this->BP->save_rae_app_schedule_info($inputParams, $sid);
                                        break;
                                    default:
                                        //Everything else can use this call.  Process disks first, if set.
                                        if (isset($diskInfo)) {
                                            if ($diskInfo !== false) {
                                                $result = $this->saveVMDKs($diskInfo, $sid, $appType);
                                                if ($result !== false) {
                                                    $result = $this->BP->save_app_schedule_info($inputParams, $sid);
                                                }
                                            } else {
                                                $result = false;
                                            }
                                        } else {
                                            $result = $this->BP->save_app_schedule_info($inputParams, $sid);
                                        }
                                        break;
                                }
                                if($result !== false) {
                                    if(!is_numeric($result)) {
                                        $status = $this->gatherDetailedSaveInfo($result, $sid);
                                    } else {
                                        $status = array();
                                        $status[]['id'] = $result . "b." . $sid;
                                        $status = array('result' => $status);
                                    }
                                } else {
                                    $status = $result;
                                }
                            }

                        }
                    } else {
                        $status = $deviceName;
                    }

                    break;
                case 'Archive':
                case 'archive':
                    $inputParams = array();

                    $inputParams['name'] = $data['name'];
                    $inputParams['description'] = $data['name'];

                    $inputParams['enabled'] = true;
                    $inputParams['email_report'] = isset($data['email_report']) ? $data['email_report'] : false;
                    $inputParams['failure_report'] = isset($data['failure_report']) ? $data['failure_report'] : false;
                    $appType = 'Archive';
                    if (isset($data['ical']) && !isset($data['calendar'])) {
                        $calendarReturn = $data['ical'];
                    } else {
                        $calendarReturn = $this->scheduleToICal($data['calendar'], $appType);
                    }
                    if(is_array($calendarReturn) and isset($calendarReturn['error'])) {
                        $status = $calendarReturn;
                    } else {
                        $inputParams['calendar'] = $calendarReturn;
                        $inputParams['profile'] = array();
                        $inputParams['profile']['description'] = $data['name'];
                        $inputParams['profile']['target'] = $data['storage'];
                        if(isset($data['clients'])) {
                            $inputParams['profile']['clients'] = $data['clients'];
                            $appType = "file-level";
                            $types = array();
                            foreach($data['types'] as $type) {
                                $types[] = $this->FUNCTIONS->getBackupTypeCoreNameBackupNow($type, $appType);
                            }
                            $inputParams['profile']['types'] = $types;
                        }
                        if (isset($data['instances'])) {
                            $inputParams['profile']['instances'] = $data['instances'];
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
                            $inputParams['profile']['types'] = $types;
                        }

                        $inputParams['profile']['range_end'] = isset($data['range_end']) ? $data['range_end'] : 0;
                        $inputParams['profile']['range_size'] = isset($data['range_size']) ? $data['range_size'] : 0;

                        if(isset($data['slots']) and !empty($data['slots'])) {
                            $inputParams['profile']['target_slots'] = $data['slots'];
                        }

                        $inputParams['profile']['options'] = array();
                        $inputParams['profile']['options']['append'] = isset($data['append']) ? $data['append'] : true;
                        $inputParams['profile']['options']['purge'] = isset($data['purge']) ? $data['purge'] : false;
                        $inputParams['profile']['options']['compress'] = true;
                        $inputParams['profile']['options']['encrypt'] = isset($data['encrypt']) ? $data['encrypt'] : false;
                        $inputParams['profile']['options']['deduplicate'] = false;
                        $inputParams['profile']['options']['email_report'] = $data['email_report'];
                        $inputParams['profile']['options']['retention_days'] = isset($data['retention_days']) ? (int)$data['retention_days'] : 0;
                        $result = $this->BP->save_archive_schedule_info($inputParams, $sid);
                        if($result !== false) {
                            if(isset($result['id'])) {
                                $status = array();
                                $status[]['id'] = $result['id'] . "a." . $sid;
                            } else {
                                $status = $result;
                            }
                            $status = array('result' => $status);
                        } else {
                            $status = $result;
                        }
                    }
                    break;
                case "replication":
                case "Replication":
                    $input = array();
                    $id = isset($data['id']) ? $data['id'] : false;
                    $name = isset($data['name']) ? $data['name'] : false;
                    $description = isset($data['description']) ? $data['description'] : $name;
                   // $targetID = isset($data['target_id']) ? $data['target_id'] : false;
                    $targets = isset($data['targets']) ? $data['targets'] : false;
                    $instanceIDs = isset($data['instances']) ? $data['instances'] : false;
                    $enabled = isset($data['enabled']) ? $data['enabled'] : true;  //this is ignored for now

                    if ($id) { $input['id'] = $id; }
                    if ($name) { $input['name'] = $name; }
                    if ($description) { $input['description'] = $description; }
                    if ($targets) { $input['targets'] = $targets; }
                    if ($instanceIDs) { $input['instance_ids'] = $instanceIDs; }
                    if ($enabled) { $input['enabled'] = $enabled; }

                    $result = $this->BP->save_replication_joborder_info($input, $sid);

                    if($result !== false) {
                        if(is_int($result)) {
                            $status = array();
                            $status[]['id'] = $result . "x." . $sid;
                        } else {
                            $status = $result;
                        }
                        $status = array('result' => $status);
                    } else {
                        $status = $result;
                    }
                    break;
                case 'certification':
                case 'Certification':
                    $result = $this->post_certification($data,$sid);
                    
                    if($result !== false) {
                        if(is_array($result) && array_key_exists('id',$result)) {
                            $status = array();
                            $status[]['id'] = $result['id'];
                            $status = array('result' => $status);
                        } else {
                            $status = $result;
                        }
                    } else {
                        $status = $result;
                    }
                    break;
            }
        }
        return $status;
    }

    /*
     * Given a selection list ID, loop through the provided iCal to create the selection list array required for the schedule.
     * The selection list info can be either a scalar, the list ID, or an array with keys for backup types, e.g.,
     *      master, differential, selective, baremetal.  (incremental should be converted to differential).
     *      and values of the selection list ID.
     *
     * This function is used for enterprise schedules only, i.e., no application schedules, as they only support scalars.
     */
    private function buildSelection($listID, $iCal) {
        $list = $listID;
        // Break the ical into the lines, and find the SUMMARY line.
        // The SUMMARY is followed by ':' and then the backup type.
        $calendarLines = explode("\r\n", $iCal);
        foreach ($calendarLines as $line) {
            if (strpos($line, 'SUMMARY') !== false) {
                $backupLine = explode(':', $line);
                $backupType = strtolower($backupLine[1]);
                if ($backupType == Constants::BACKUP_TYPE_INCREMENTAL) {
                    // The enerprise schedules API expects differential and not incremental.  It handles both.
                    $backupType = Constants::BACKUP_TYPE_DIFFERENTIAL;
                }
                // If the list is still a scalar (the ID), convert to an array for processing.
                if (!is_array($list)) {
                    $list = array();
                }
                $list[$backupType] = $listID;
                continue;
            }
        }
        return $list;
    }

    private function post_certification($data,$sid){
        $ret = array();

        if($sid == $this->BP->get_local_system_id()){
            $ret = $this->post_certification_local($data,$sid);
        } else {
            $ret = $this->post_certification_remote($data,$sid);
        }
        return $ret;
    }

    private function post_certification_remote($data,$sid){
        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];
        $api = "/api/joborders/";

        $remoteRet = $this->FUNCTIONS->remoteRequestRDR($url, 'POST' , $api, "", $data);

        $ret = array();
        if(isset($remoteRet['message'])){
            // pass on error message
            $ret = $remoteRet['message'];
        } else {
            // Since local and remote systems can have a different sid for the 
            //  remote system, strip the sid from the remote system and use the 
            //  one given locally
            $id = explode(".",$remoteRet['result'][0]['id']);
            $ret['result'][0]['id'] = $id[0] . "." . $sid;
        }
        return $ret;
    }

    private function post_certification_local($data,$sid){
        $inputParams = array();
        // UNIBP-13571 prepend dataype constant
        $inputParams['$type'] = Constants::RDR_JOB;

        $inputParams['name'] = $data['name'];
        if(isset($data['calendar'][0])){
            $inputParams['schedule'] = $this->createRdrScheduleArr($data['calendar'][0]);
        }

        if(isset($data['certification_target'])){
            $target = $data['certification_target'];
        }

        if(isset($data['certification_options'])){
            $options =  $data['certification_options'];
            if(isset($options['power_on_timeout'])){
                $inputParams['poweron_timeout'] = $options['power_on_timeout'];
            }
            if(isset($options['service_profile_id'])){
                $inputParams['profile_id'] = $options['service_profile_id'];
            }
            if(isset($options['post_custom_script'])){
                $inputParams['post_script_cmd'] = $options['post_custom_script'];
            }
            if(isset($options['post_custom_script_arguments'])){
                $inputParams['post_script_args'] = $options['post_custom_script_arguments'];
            }
            if(isset($options['suffix_name'])){
                $inputParams['suffix_name'] = $options['suffix_name'];
            }
        }


        $inputParams['vms'] = array();
        if(isset($target['instances'])){
            foreach($target['instances'] as $instance) {
                $vm = array();
                // UNIBP-13571 prepend dataype constant
                $vm['$type'] = Constants::RDR_VM;

                foreach($instance as $key=>$value){
                    switch($key){
                        case "id":
                            $vm['instance_id'] = $value;
                            break;
                        case "power_on":
                            $vm['is_poweron'] = $value;
                            break;
                        case "credential_id":
                            $vm['guest_credential_id'] = $value;
                            break;
                        case "username":
                            $vm['guest_user'] = $value;
                            break;
                        case "password":
                            $vm['guest_password'] = $value;
                            break;
                        case "os_type":
                            $vm['guest_os'] = $value;
                            break;
                        case "networks":
                            foreach($value as $network){
                                $newNetwork = array(
                                    "name" => $network['name'],
                                    "ip_addr" => $network['ip_address'],
                                    "mask" => $network['mask'],
                                    "gateway" => $network['gateway'],
                                    "dns1" => $network['dns1'],
                                    "dns2" => $network['dns2']
                                );
                                $vm['vm_ips'][] = $newNetwork;
                            }
                            break;
                        case "application_tests":
                            foreach($value as $test){
                                $newTest = array(
                                    "name" => $test['name'],
                                    "command" => $test['command'],
                                    "is_custom" => $test['is_custom'],
                                    "timeout_m" => $test['timeout_min'],
                                    "priority" => $test['priority'],
                                    "vm_test_params" => $test['parameters'],
                                    "app_test_id" => $test['app_test_id']
                                );
                                $vm['vm_tests'][] = $newTest;
                            }
                            break;
                        default:
                            $vm[$key] = $value;
                    }
                }

                $inputParams['vms'][] = $vm;
            }
        }
        $result = $this->RDR->new_job($inputParams);

        if(isset($result['id']) && is_numeric($result['id'])){
            $status = array();
            $status['id'] = $result['id'] . "c." . $sid;
        } else {
            $status = $result;
        }

        return $status;
    }

    public function put($which, $data, $sid) {
        if(!$sid) {
            $sid = $this->BP->get_local_system_id();
        }
        $status = false;
        if(isset($which[1])) {
            //dealing with enable/disable or run
            $type = "";
            $id = -1;
            $ids = explode(',', $which[1]);
            foreach($ids as $tokID) {
                $idPieces = explode('.', $tokID);
                if(is_numeric($idPieces[0]) and $idPieces[0] == 0) {
                    $type = 'replication';
                } elseif(is_numeric(substr($idPieces[0], 0, -1))) {
                    $id = (int)substr($idPieces[0], 0, -1);
                    switch(substr($idPieces[0], -1)) {
                        case 'b':
                            $type = 'backup';
                            break;
                        case 'e':
                            $type = 'enterprise';
                            break;
                        case 'a':
                            $type = 'archive';
                            break;
                        case 'r':
                            $type = 'restore';
                            break;
                        case 'i':
                            $type = 'ir';
                            break;
                        case 'f':
                            $type = 'flr';
                            break;
                        case 'x':
                            $type = 'replication';
                            break;
                        case 'c':
                            $type = 'certification';
                            break;
                    }
                }
                if(isset($idPieces[1]) and is_numeric($idPieces[1])){
                    $sid = $idPieces[1];
                }
                switch($which[0]) {
                    case 'enable':
                        switch($type) {
                            case 'backup':
                                $do_enable = true;
                                $application_schedule_info = $this->BP->get_app_schedule_info($id, $sid);
                                if ( $application_schedule_info !== false
                                    and isset($application_schedule_info['backup_options']['appinst_ids'])
                                    and isset($application_schedule_info['schedule_options']['client_id']) ) {

                                    $client_info = $this->BP->get_client_info($application_schedule_info['schedule_options']['client_id'], $sid);
                                    if ( $client_info !== false ) {
                                        if (isset($client_info['app_aware_flg']) and $client_info['app_aware_flg'] === Constants::APP_AWARE_FLG_NOT_AWARE_OF_APPLICATIONS_VSS_FULL) {

                                            $appInfo = $this->BP->get_appinst_info(implode(',', $application_schedule_info['backup_options']['appinst_ids']), $sid);
                                            if ($appInfo !== false) {
                                                foreach ($appInfo as $app) {
                                                    if ($app['app_type'] === Constants::APPLICATION_TYPE_NAME_EXCHANGE) {

                                                        $do_enable = false;

                                                        global $Log;
                                                        $msg = "The Application Strategy for " . $client_info['name'] . " does not allow for this client to have any Exchange jobs enabled.  The Application Strategy can be found by editing this asset in the Protected Assets tab on the Configure page.";
                                                        $Log->writeError($msg, true);

                                                        $status['error'] = 500;
                                                        $status['message'] = $msg;
                                                        break;
                                                    }
                                                }
                                            } else {
                                                $do_enable = false;
                                                $status = false;
                                                global $Log;
                                                $msg = 'bp_get_appinst_info call failed: ' . $this->BP->getError();
                                                $Log->writeError($msg, true);

                                            }
                                        }
                                        // Else - do the enable because app_aware_flg allows for Exchange backups
                                    } else {
                                        $do_enable = false;
                                        $status = false;
                                        global $Log;
                                        $msg= 'bp_get_client_info call failed: ' . $this->BP->getError();
                                        $Log->writeError($msg, true);
                                    }
                                } else {
                                    $do_enable = false;
                                    $status = false;
                                    global $Log;
                                    $msg= 'bp_get_app_schedule_info call failed: ' . $this->BP->getError();
                                    $Log->writeError($msg, true);
                                }

                                if ($do_enable === true) {
                                    $status = $this->BP->enable_app_schedule($id, $sid);
                                }
                                break;
                            case 'enterprise':
                                $status = $this->BP->enable_schedule($id, $sid);
                                break;
                            case 'archive':
                                $status = $this->BP->enable_archive_schedule($id, $sid);
                                break;
                            case 'replication':
                                $status = $this->BP->enable_replication_joborder($id, $sid);
                                break;
                            case 'certification':
                                $status = $this->put_certification($id, $sid, null, 'enable');
                                //$status = $this->RDR->enable_job($id);
                                break;
                        }
                        break;
                    case 'disable':
                        switch($type) {
                            case 'backup':
                                $status = $this->BP->disable_app_schedule($id, $sid);
                                break;
                            case 'enterprise':
                                $status = $this->BP->disable_schedule($id, $sid);
                                break;
                            case 'archive':
                                $status = $this->BP->disable_archive_schedule($id, $sid);
                                break;
                            case 'replication':
                                $status = $this->BP->disable_replication_joborder($id, $sid);
                                break;
                            case 'certification':
                                $status = $this->put_certification($id, $sid, null, 'disable');
                                //$status = $this->RDR->disable_job($id);
                                break;
                        }
                        break;
                    case 'run':
                        switch($type) {
                            case 'backup':
                            case 'enterprise':
                            case 'archive':
                                $status = $this->BP->schedule_now($id, $sid);
                                break;
                            case 'replication':
                                $status['error'] = 405;
                                $status['message'] = "Replication joborders are constantly running.";
                                break;
                        }
                        break;
                    case 'failover':
                        switch($type) {
                            case 'certification':
                                $status = $this->put_certification($id, $sid, $data, 'failover', $which[2]);
                                break;
                        }
                        break;
                    case 'commit':
                        switch($type) {
                            case 'certification':
                                $status = $this->put_certification($id, $sid, null, 'commit');
                                break;
                        }
                        break;
                    case 'test':
                        switch($type) {
                            case 'certification':
                                $status = $this->put_certification($id, $sid, null, 'test');
                                break;
                        }
                        break;
                    case 'discard':
                        switch($type) {
                            case 'certification':
                                $status = $this->put_certification($id, $sid, null, 'discard');
                                break;
                        }
                }
            }
        } else {
            //dealing with a modify
            if($which[0] == 0) {
                //replication joborder
                $inputParams = array();
                foreach($data['instances'] as $instance) {
                    $instanceInfo = array();
                    $instanceInfo['instance_id'] = $instance['instance_id'];
                    $instanceInfo['priority'] = 500;
                    $instanceInfo['is_synchable'] = $instance['synchable'];
                    $inputParams[] = $instanceInfo;
                }
                $status = $this->BP->save_app_vaulting_info($inputParams, $sid);
            } else {
                $type = "";
                $idPieces = explode('.', $which[0]);
                if(is_numeric(substr($idPieces[0], 0, -1)) and $idPieces[0] != -1) {
                    $id = (int)substr($idPieces[0], 0, -1);
                    switch(substr($idPieces[0], -1)) {
                        case 'b':
                            $type = 'backup';
                            break;
                        case 'e':
                            $type = 'enterprise';
                            break;
                        case 'a':
                            $type = 'archive';
                            break;
                        case 'r':
                            $type = 'restore';
                            break;
                        case 'i':
                            $type = 'ir';
                            break;
                        case 'f':
                            $type = 'flr';
                            break;
                        case 'x':
                            $type = 'replication';
                            break;
                        case 'c':
                            $type = 'certification';
                            break;
                    }
                    switch($type) {
                        case 'backup':
                            $appSchedules = $this->BP->get_app_schedule_list(-1, -1, $sid);

                            foreach ($appSchedules as $appSchedule) {
                                if($appSchedule['id'] == $id) {
                                    $scheduleInfo = $this->BP->get_app_schedule_info($id, $sid);
                                    if($scheduleInfo == false) {
                                        $scheduleInfo = $this->BP->get_rae_app_schedule_info($id, $sid);
                                    }

                                    if(isset($data['name'])) {
                                        $appSchedule['name'] = $data['name'];
                                    }
                                    if($appSchedule['app_id'] == 1) {
                                        $appType = 'file-level';
                                    } else {
                                        $instanceInfo = $this->BP->get_appinst_info($scheduleInfo['backup_options']['appinst_ids'][0], $sid);
                                        $appType = $instanceInfo[$data['instances'][0]]['app_type'];
                                    }
                                    if(isset($data['instances'])) {
                                        // Handle instances as objects or ids.
                                        if (is_array($data['instances'][0])) {
                                            $scheduleInfo['backup_options']['appinst_ids'] = array_map(function($o) { return $o['id']; }, $data['instances']);
                                            $instanceInfo = $this->BP->get_appinst_info($data['instances'][0]['id'], $sid);
                                            $scheduleInfo['schedule_options']['client_id'] = $instanceInfo[$data['instances'][0]['id']]['client_id'];
                                            $appType = $instanceInfo[$data['instances'][0]['id']]['app_type'];

                                            if ($appType == Constants::APPLICATION_TYPE_NAME_VMWARE ||
                                                $appType == Constants::APPLICATION_TYPE_NAME_XEN ||
                                                $appType == Constants::APPLICATION_TYPE_NAME_AHV) {
                                                $diskInfo = $this->processVMDKs($data['instances'], $sid, $appType);
                                            }
                                        } else {
                                            $scheduleInfo['backup_options']['appinst_ids'] = $data['instances'];
                                            $instanceInfo = $this->BP->get_appinst_info($data['instances'][0], $sid);
                                            $scheduleInfo['schedule_options']['client_id'] = $instanceInfo[$data['instances'][0]]['client_id'];
                                            $appType = $instanceInfo[$data['instances'][0]]['app_type'];
                                        }
                                    }
                                    if (isset($data['ical']) && !isset($data['calendar'])) {
                                        $calendarReturn = $data['ical'];
                                    } else {
                                        $calendarReturn = $this->scheduleToICal($data['calendar'], $appType);
                                    }
                                    if(is_array($calendarReturn) and isset($calendarReturn['error'])) {
                                        $status = $calendarReturn;
                                    } else {
                                        $scheduleInfo['calendar'] = $calendarReturn;
                                        if(isset($data['include_new'])) {
                                            $scheduleInfo['schedule_options']['include_new'] = $data['include_new'];
                                        }
                                        if(isset($data['email_report'])) {
                                            $scheduleInfo['schedule_options']['email_report'] = $data['email_report'];
                                        }
                                        if(isset($data['failure_report'])) {
                                            $scheduleInfo['schedule_options']['failure_report'] = $data['failure_report'];
                                        }
                                        $storageValid = true;
                                        if(isset($data['storage'])) {
                                            if(strtolower($data['storage']) == "internal" or $data['storage'] == "") {
                                                $scheduleInfo['backup_options']['dev_name'] = "";
                                            } else {
                                                $deviceName = $this->BP->get_device_for_storage($data['storage'], $sid);
                                                if($deviceName !== false) {
                                                    $scheduleInfo['backup_options']['dev_name'] = $deviceName;
                                                } else {
                                                    $storageValid = false;
                                                }
                                            }
                                        }
                                        if($storageValid !== false) {
                                            if(isset($data['verify'])) {
                                                switch($data['verify']) {
                                                    case 'none':
                                                        $scheduleInfo['backup_options']['verify_level'] = 0;
                                                        break;
                                                    case 'inline':
                                                        $scheduleInfo['backup_options']['verify_level'] = 3;
                                                        break;
                                                }
                                            }
                                            if(isset($data['excl_list'])) {
                                                $scheduleInfo['backup_options']['excl_list'] = $data['excl_list'];
                                            }
                                            if(isset($data['incl_list'])) {
                                                $scheduleInfo['backup_options']['incl_list'] = $data['incl_list'];
                                            }
                                            if(isset($data['metanames'])) {
                                                $scheduleInfo['backup_options']['metanames'] = $data['metanames'];
                                            }
                                            if(isset($data['regular_expressions'])) {
                                                $scheduleInfo['regular_expressions'] = $this->buildRegex($data);
                                            }
                                            unset($appSchedule['client_id']);
                                            unset($appSchedule['last_time']);
                                            unset($appSchedule['next_time']);
                                            if(isset($appSchedule['esx_uuid'])) {
                                                unset($appSchedule['esx_uuid']);
                                            }
                                            if(isset($appSchedule['key'])) {
                                                unset($appSchedule['key']);
                                            }
                                            $inputParams = array_merge($appSchedule, $scheduleInfo);
                                            switch($appType) {
                                                case Constants::APPLICATION_TYPE_NAME_ORACLE:
                                                case Constants::APPLICATION_TYPE_NAME_SHAREPOINT:
                                                case Constants::APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE:
                                                    //RAE types
                                                    $result = $this->BP->save_rae_app_schedule_info($inputParams, $sid);
                                                    break;
                                                default:
                                                    //Everything else can use this call.  Process disks first, if set.
                                                    if (isset($diskInfo)) {
                                                        if ($diskInfo !== false) {
                                                            $result = $this->saveVMDKs($diskInfo, $sid, $appType);
                                                            if ($result !== false) {
                                                                $result = $this->BP->save_app_schedule_info($inputParams, $sid);
                                                            }
                                                        } else {
                                                            $result = false;
                                                        }
                                                    } else {
                                                        $result = $this->BP->save_app_schedule_info($inputParams, $sid);
                                                    }
                                                    break;
                                            }
                                            if($result !== false) {
                                                if(is_array($result)) {
                                                    $status = $this->gatherDetailedSaveInfo($result, $sid);
                                                } else {
                                                    $status = $result;
                                                }
                                            } else {
                                                $status = $result;
                                            }
                                        } else {
                                            $status = $deviceName;
                                        }
                                    }
                                    break;
                                }
                            }
                            break;
                        case 'enterprise':
                            //TODO: if there are any errors saving a selection list, option list, or saving the schedule, create an array structure to pass the partial error
                            //use the same format as backup now for instances

                            //get schedule info for comparison
                            $scheduleInfo = $this->BP->get_schedule_info($id, $sid);
                            if($scheduleInfo !== false) {
                                $storageValid = true;
                                $noErrors = true;
                                $inputParams = array();
                                $inputParams['id'] = $scheduleInfo['id'] = $id;
                                if(isset($data['name'])) {
                                    // enterprise schedule info API does not return the 'name', so set it if passed in.
                                    $inputParams['name'] = $scheduleInfo['name'] = $data['name'];
                                }
                                $calendarValid = true;
                                if(isset($data['calendar']) || isset($data['ical'])) {
                                    if (isset($data['ical']) && !isset($data['calendar'])) {
                                        $iCal = $data['ical'];
                                    } else {
                                        $appType = (isset($data['app_id']) && ((int)$data['app_id'] === Constants::APPLICATION_ID_BLOCK_LEVEL)) ?
                                            Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL : Constants::APPLICATION_NAME_FILE_LEVEL;
                                        $iCal = $this->scheduleToICal($data['calendar'], $appType);
                                    }
                                    if(is_array($iCal) and isset($iCal['error'])) {
                                        $calendarValid = false;
                                        $status = $iCal;
                                    } else {
                                        $calendarInfo = $this->BP->get_calendar($scheduleInfo['calendar'], $sid);
                                        $currentCalendar = $calendarInfo;
                                        // If the calendar contents changed, save it.
                                        if (strcmp($iCal, $currentCalendar) !== 0) {
                                            $calendarParams = array("id" => $scheduleInfo['calendar'], "contents" => $iCal);
                                            $calendarSave = $this->BP->save_calendar($calendarParams, $sid);
                                            if ($calendarSave === false) {
                                                $calendarValid = false;
                                            }
                                        }
                                    }
                                }
                                if($calendarValid) {
                                    //check to see if global options were changed
                                    $globalOptionListID = -1;
                                    $globalOptionsChange = null;
                                    $globalOptions = $this->BP->get_options($scheduleInfo['clients'][0]['options'], $sid);
                                    if(isset($data['storage'])) {
                                        $currentStorage = isset($globalOptions['dev_name']) ? $this->BP->get_storage_for_device($globalOptions['dev_name'], $sid) : "";
                                        if($data['storage'] == $currentStorage) {
                                            //equal
                                        } else {
                                            $globalOptionsChange = true;
                                            $deviceName = $this->BP->get_device_for_storage($data['storage'], $sid);
                                            if($deviceName !== false) {
                                                $globalOptions['dev_name'] = $deviceName;
                                            } else {
                                                $storageValid = false;
                                            }
                                        }
                                    }
                                    if($storageValid != false) {
                                        if(isset($data['verify'])) {
                                            switch($data['verify']) {
                                                case 'none':
                                                    $verify = 0;
                                                    break;
                                                case 'inline':
                                                    $verify = 3;
                                                    break;
                                                default:
                                                    $verify = $globalOptions['verify_level'];
                                                    break;
                                            }
                                            if($verify == $globalOptions['verify_level']) {
                                                //equal
                                            } else {
                                                $globalOptionsChange = true;
                                                $globalOptions['verify_level'] = $verify;
                                            }
                                        }
                                        if ($globalOptionsChange === true) {
                                            // Save the global options if changed.
                                            $this->BP->save_options(array("id" => $scheduleInfo['clients'][0]['options'], "options" => $globalOptions), $sid);
                                        }

                                        if(isset($globalOptions['before_command'])) {
                                            unset($globalOptions['before_command']);
                                        }
                                        if(isset($globalOptions['after_command'])) {
                                            unset($globalOptions['after_command']);
                                        }
                                        //if clients changed, then we have to save lists accordingly
                                        $sameClients = true;
                                        $clients = array();
                                        $selectionListsToDelete = array();
                                        $optionsListsToDelete = array();
                                        if(isset($data['clients'])) {
                                            $numMatches = 0;
                                            $possibleMatches = count($scheduleInfo['clients']);
                                            $removedClients = array();
                                            foreach($data['clients'] as $newClient) {
                                                $hasMatch = false;
                                                foreach($scheduleInfo['clients'] as $oldClient) {
                                                    if($newClient['id'] != $oldClient['id']) {
                                                        continue;
                                                    } else {
                                                        $hasMatch = true;
                                                        $numMatches++;
                                                        $client = $oldClient;
                                                        // If the calendar contents changed, make sure the selection lists are set for all backup types.
                                                        if (isset($calendarSave) && $calendarSave !== false && isset($iCal)) {
                                                            if (isset($client['inclusions'])) {
                                                                $client['inclusions'] = $this->buildSelection($client['inclusions'], $iCal);
                                                                $sameClients = false;
                                                            }
                                                            if (isset($client['exclusions'])) {
                                                                $client['exclusions'] = $this->buildSelection($client['exclusions'], $iCal);
                                                                $sameClients = false;
                                                            }
                                                        }
                                                        //compare inclusions, exclusions and options
                                                        if(isset($oldClient['inclusions'])) {
                                                            if((isset($newClient['incl_list']) and empty($newClient['incl_list'])) or !isset($newClient['incl_list'])) {
                                                                //if the new list is set and is empty, remove the existing list
                                                                //$inclusionDelete = $this->BP->delete_selection_list($oldClient['inclusions']);
                                                                $selectionListsToDelete[] = $client['inclusions'];
                                                                unset($client['inclusions']);
                                                                $sameClients = false;
                                                            } else {
                                                                //if both new and old lists are set, modify the existing if they are not equal
                                                                $currentInclusions = $this->BP->get_selection_list($oldClient['inclusions'], $sid);
                                                                //sort($currentInclusions, SORT_STRING);
                                                                //sort($currentInclusions, SORT_STRING);
                                                                if($currentInclusions['filenames'] == $newClient['incl_list']) {
                                                                    //equal
                                                                } else {
                                                                    $currentInclusions['filenames'] = $newClient['incl_list'];
                                                                    $inclusionSave = $this->BP->save_selection_list($currentInclusions, $sid);
                                                                    if($inclusionSave == false) {
                                                                        $noErrors = false;
                                                                    }
                                                                }
                                                            }
                                                        } else if(isset($newClient['incl_list']) and !empty($newClient['incl_list'])) {
                                                            //no inclusion list currently, but now one is added - create the new list
                                                            $inclusionArray = array();
                                                            $inclusionArray['name'] = microtime();
                                                            $inclusionArray['description'] = "Inclusion list for client " . $newClient['id'] . " in schedule " . $scheduleInfo['name'];
                                                            $inclusionArray['type'] = "inclusion";
                                                            $inclusionArray['family'] = "Satori-file-level";
                                                            $inclusionArray['client'] = $newClient['id'];
                                                            $inclusionArray['filenames'] = $newClient['incl_list'];
                                                            $inclusionSave = $this->BP->save_selection_list($inclusionArray, $sid);
                                                            if($inclusionSave !== false) {
                                                                $sameClients = false;
                                                                $newName = "Incl " . $inclusionSave . " JO " . $scheduleInfo['id'] . " client " . $newClient['id'];
                                                                $inclusionArray['name'] = $newName;
                                                                $inclusionArray['id'] = $inclusionSave;
                                                                $saveResult = $this->BP->save_selection_list($inclusionArray, $sid);
                                                                if ($saveResult === false) {
                                                                    global $Log;
                                                                    $Log->writeError($this->BP->getError(), true);
                                                                }
                                                                $client['inclusions'] = $inclusionSave;
                                                            } else {
                                                                $noErrors = false;
                                                            }
                                                        }

                                                        if(isset($oldClient['exclusions'])) {
                                                            if(((isset($newClient['excl_list']) and empty($newClient['excl_list'])) or !isset($newClient['excl_list'])) and
                                                                ((isset($newClient['metanames']) and empty($newClient['metanames'])) or !isset($newClient['metanames']))) {
                                                                //if the new list is set and is empty, remove the existing list
                                                                //$exclusionDelete = $this->BP->delete_selection_list($oldClient['exclusions']);
                                                                $selectionListsToDelete[] = $client['exclusions'];
                                                                unset($client['exclusions']);
                                                                $sameClients = false;
                                                            } else {
                                                                // if both new and existing are set, compare and update if necessary
                                                                $currentExclusions = $this->BP->get_selection_list($oldClient['exclusions'], $sid);
                                                                if (((isset($currentExclusions['filenames']) && $currentExclusions['filenames'] == $newClient['excl_list']) ||
                                                                    (!isset($currentExclusions['filenames']) && empty($newClient['excl_list'])))
                                                                    and
                                                                    ((isset($currentExclusions['metanames']) && $currentExclusions['metanames'] == $newClient['metanames']) ||
                                                                    !isset($currentExclusions['metanames']) && empty($newClient['metanames']))) {
                                                                    //equal
                                                                } else {
                                                                    if(isset($newClient['excl_list']) and !empty($newClient['excl_list'])) {
                                                                        $currentExclusions['filenames'] = $newClient['excl_list'];
                                                                    } else {
                                                                        if(isset($currentExclusions['filenames'])) {
                                                                            $currentExclusions['filenames'] = array();
                                                                        }
                                                                    }
                                                                    if(isset($newClient['metanames']) and !empty($newClient['metanames'])) {
                                                                        $currentExclusions['metanames'] = $newClient['metanames'];
                                                                    } else {
                                                                        if(isset($currentExclusions['metanames'])) {
                                                                            $currentExclusions['metanames'] = array();
                                                                        }
                                                                    }
                                                                    $exclusionSave = $this->BP->save_selection_list($currentExclusions, $sid);
                                                                    if($exclusionSave == false) {
                                                                        $noErrors = false;
                                                                    }
                                                                }
                                                            }
                                                        } else if((isset($newClient['excl_list']) and !empty($newClient['excl_list'])) or (isset($newClient['metanames']) and !empty($newClient['metanames']))) {
                                                            //no current exclusion list, but now one is added
                                                            $exclusionArray = array();
                                                            $exclusionArray['name'] = microtime();
                                                            $exclusionArray['description'] = "Exclusion list for client " . $newClient['id'] . " in schedule " . $scheduleInfo['name'];
                                                            $exclusionArray['type'] = "exclusion";
                                                            $exclusionArray['family'] = "Satori-file-level";
                                                            $exclusionArray['client'] = $newClient['id'];
                                                            if(isset($newClient['excl_list']) and !empty($newClient['excl_list'])) {
                                                                $exclusionArray['filenames'] = $newClient['excl_list'];
                                                            }
                                                            if(isset($newClient['metanames']) and !empty($newClient['metanames'])) {
                                                               $exclusionArray['metanames'] = $newClient['metanames'];
                                                            }
                                                            $exclusionSave = $this->BP->save_selection_list($exclusionArray, $sid);
                                                            if($exclusionSave !== false) {
                                                                $sameClients = false;
                                                                $newName = "Excl " . $exclusionSave . " JO " . $scheduleInfo['id'] . " client " . $newClient['id'];
                                                                $exclusionArray['name'] = $newName;
                                                                $exclusionArray['id'] = $exclusionSave;
                                                                $saveResult = $this->BP->save_selection_list($exclusionArray, $sid);
                                                                if ($saveResult === false) {
                                                                    global $Log;
                                                                    $Log->writeError($this->BP->getError(), true);
                                                                }
                                                                $client['exclusions'] = $exclusionSave;
                                                            } else {
                                                                $noErrors = false;
                                                            }
                                                        }
                                                        if(isset($oldClient['options'])) {
                                                            if($globalOptionListID != $oldClient['options']) {
                                                                $currentOptions = $this->BP->get_options($oldClient['options'], $sid);
                                                                if($globalOptionListID == -1) {
                                                                    if(!isset($currentOptions['before_command']) and !isset($currentOptions['after_command'])) {
                                                                        $globalOptionListID = $oldClient['options'];
                                                                    }
                                                                }
                                                            } else {
                                                                $currentOptions = $globalOptions;
                                                            }
                                                            $optionsChange = false;
                                                            if(isset($newClient['before_command'])) {
                                                                //add the CNT or SRV directive and then check for equality
                                                                if($newClient['before_command'] == "") {
                                                                    //no directive needs to be added - clearing current command
                                                                } else if(strlen($newClient['before_command']) >= 4) {
                                                                    $firstFour = substr($newClient['before_command'], 0, 4);
                                                                    if($firstFour !== "CNT:" and $firstFour !== "SRV:") {
                                                                        //add the CNT: directive
                                                                        $newClient['before_command'] = "CNT:" . $newClient['before_command'];
                                                                    }
                                                                } else {
                                                                    $newClient['before_command'] = "CNT:" . $newClient['before_command'];
                                                                }
                                                                if ((isset($currentOptions['before_command']) && $currentOptions['before_command'] == $newClient['before_command']) ||
                                                                    (!isset($currentOptions['before_command']) && empty($newClient['before_command']))) {
                                                                    //equal or none set
                                                                } else {
                                                                    $optionsChange = true;
                                                                    $currentOptions['before_command'] = $newClient['before_command'];
                                                                }
                                                            }
                                                            if(isset($newClient['after_command'])) {
                                                                //add the CNT or SRV directive and then check for equality
                                                                if($newClient['after_command'] == "") {
                                                                    //no directive needs to be added - clearing current command
                                                                } else if(strlen($newClient['after_command']) >= 4) {
                                                                    $firstFour = substr($newClient['after_command'], 0, 4);
                                                                    if($firstFour !== "CNT:" and $firstFour !== "SRV:") {
                                                                        //add the CNT: directive
                                                                        $newClient['after_command'] = "CNT:" . $newClient['after_command'];
                                                                    }
                                                                } else {
                                                                    $newClient['after_command'] = "CNT:" . $newClient['after_command'];
                                                                }
                                                                if ((isset($currentOptions['after_command']) && $currentOptions['after_command'] == $newClient['after_command']) ||
                                                                    (!isset($currentOptions['after_command']) && empty($newClient['after_command']))) {
                                                                    //equal or none set
                                                                } else {
                                                                    $optionsChange = true;
                                                                    $currentOptions['after_command'] = $newClient['after_command'];
                                                                }
                                                            }
                                                            if($oldClient['options'] == $globalOptionListID and $optionsChange === true) {
                                                                //client is using global options list, but now has new before or after command, new option list needs to be created
                                                                $options = array();
                                                                $options['name'] = "Opt for client " . $oldClient['id'] . " JO " . $scheduleInfo['id'];
                                                                $options['description'] = "Single client options list for schedule " . $scheduleInfo['name'];
                                                                $options['type'] = "backup";
                                                                $options['family'] = "Satori-file-level";
                                                                $options['options'] = $currentOptions;
                                                                $optionSave = $this->BP->save_options($options, $sid);
                                                                if($optionSave !== false) {
                                                                    $sameClients = false;
                                                                    $client['options'] = $optionSave;
                                                                    $optName = "Opt " . $optionSave . " JO " . $scheduleInfo['id'] . " client " . $oldClient['id'];
                                                                    $optionSave = $this->BP->save_options(array("id" => $optionSave, "name" => $optName), $sid);
                                                                } else {
                                                                    //failed to save - pass up specific error
                                                                    $noErrors = false;

                                                                }
                                                            } else if($oldClient['options'] != $globalOptionListID and $optionsChange === true) {
                                                                //not global and before/after command updated, need to modify current list to contain new options
                                                                if(empty($currentOptions['before_command']) and empty($currentOptions['after_command'])) {
                                                                    //single client options with before/after command, now removed - use global list
                                                                    if($globalOptionListID == -1) {
                                                                        //haven't found the global yet, run through the client list and get it
                                                                        foreach($scheduleInfo['clients'] as $globalClientCheck) {
                                                                            $globalOptionsCheck = $this->BP->get_options($globalClientCheck['options'], $sid);
                                                                            if((!isset($globalOptionsCheck['before_command']) and !isset($globalOptionsCheck['after_command'])) or (empty($globalOptionsCheck['before_command']) and empty($globalOptionsCheck['after_command']))) {
                                                                                $globalOptionListID = $globalClientCheck['options'];
                                                                                break;
                                                                            }
                                                                        }
                                                                        if($globalOptionListID == -1) {
                                                                            //no global - have to create one
                                                                            $options = array();
                                                                            $options['name'] = microtime();
                                                                            $options['description'] = "Global options list for schedule " . $scheduleInfo['name'];
                                                                            $options['type'] = "backup";
                                                                            $options['family'] = "Satori-file-level";
                                                                            $options['options'] = $currentOptions;
                                                                            $optionSave = $this->BP->save_options($options, $sid);
                                                                            if($optionSave != false) {
                                                                                $sameClients = false;
                                                                                $globalOptionListID = $optionSave;
                                                                                $optName = "Opt " . $optionSave . " JO " . $scheduleInfo['id'];
                                                                                $optionSave = $this->BP->save_options(array("id" => $optionSave, "name" => $optName), $sid);
                                                                                $client['options'] = $globalOptionListID;
                                                                                // add to list to delete, delete after schedule is saved.
                                                                                //$this->BP->delete_options($oldClient['options'], $sid);
                                                                                $optionsListsToDelete[] = $oldClient['options'];
                                                                            } else {
                                                                                //failed to save global - pass an error
                                                                                $noErrors = false;
                                                                            }
                                                                        }
                                                                    } else {
                                                                        $sameClients = false;
                                                                        //we have the global, use it for this client
                                                                        $client['options'] = $globalOptionListID;
                                                                        //remove old list as no longer being used
                                                                        // add to list to delete, delete after schedule is saved.
                                                                        //$this->BP->delete_options($oldClient['options'], $sid);
                                                                        $optionsListsToDelete[] = $oldClient['options'];
                                                                    }
                                                                } else {
                                                                    $optionSave = $this->BP->save_options(array("id" => $oldClient['options'], "options" => $currentOptions), $sid);
                                                                    if($optionSave == false) {
                                                                        $noErrors = false;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        $clients[] = $client;
                                                    }
                                                }
                                                if($hasMatch != true) {
                                                    //new client - create selection and option lists
                                                    $sameClients = false;
                                                    $client = array();
                                                    $client['id'] = $newClient['id'];
                                                    //create inclusion list
                                                    if(isset($newClient['incl_list']) and !empty($newClient['incl_list'])) {
                                                        $singleClientInclusions = array();
                                                        $singleClientInclusions['name'] = microtime();
                                                        $singleClientInclusions['description'] = "Satori inclusion list for client " . $newClient['id'] . " in schedule " . $scheduleInfo['name'];
                                                        $singleClientInclusions['type'] = "inclusion" ;
                                                        $singleClientInclusions['family'] = "Satori-file-level";
                                                        $singleClientInclusions['client'] = $newClient['id'];
                                                        $singleClientInclusions['filenames'] = $newClient['incl_list'];
                                                        $inclusionListID = $this->BP->save_selection_list($singleClientInclusions, $sid);
                                                        if($inclusionListID !== false) {
                                                            $client['inclusions'] = $inclusionListID;
                                                            $newName = "Incl " . $inclusionListID . " JO " . $scheduleInfo['id'] . " client " . $newClient['id'];
                                                            $saveResult = $this->BP->save_selection_list(array( "id" => $inclusionListID,
                                                                "name" => $newName,
                                                                "filenames" => $singleClientInclusions['filenames']), $sid);
                                                            if ($saveResult === false) {
                                                                global $Log;
                                                                $Log->writeError($this->BP->getError(), true);
                                                            }
                                                        } else {
                                                            //pass back error informing of failed creation
                                                            $noErrors = false;
                                                        }

                                                    }
                                                    //create exclusion list
                                                    if((isset($newClient['excl_list']) and !empty($newClient['excl_list'])) or (isset($newClient['metanames']) and !empty($newClient['metanames']))) {
                                                        $singleClientExclusions = array();
                                                        $singleClientExclusions['name'] = microtime();
                                                        $singleClientExclusions['description'] = "Satori exclusion list for client " . $newClient['id'] . " in schedule " . $scheduleInfo['name'];
                                                        $singleClientExclusions['type'] = "exclusion";
                                                        $singleClientExclusions['family'] = "Satori-file-level";
                                                        $singleClientExclusions['client'] = $newClient['id'];
                                                        if(isset($newClient['excl_list']) and !empty($newClient['excl_list'])) {
                                                            $singleClientExclusions['filenames'] = $newClient['excl_list'];
                                                        }
                                                        if(isset($newClient['metanames']) and !empty($newClient['metanames'])) {
                                                            $singleClientExclusions['metanames'] = $newClient['metanames'];
                                                        }
                                                        $exclusionListID = $this->BP->save_selection_list($singleClientExclusions, $sid);
                                                        if($exclusionListID !== false) {
                                                            $client['exclusions'] = $exclusionListID;
                                                            $newName = "Excl " . $exclusionListID . " JO " . $scheduleInfo['id'] . " client " . $client['id'];
                                                            $singleClientExclusions['name'] = $newName;
                                                            $singleClientExclusions['id'] = $exclusionListID;
                                                            $this->BP->save_selection_list($singleClientExclusions, $sid);
                                                        } else {
                                                            //pass back error informing of failed creation
                                                            $noErrors = false;
                                                        }
                                                    }
                                                    //create options list
                                                    $canUseGlobal = true;
                                                    $optionsArr = $globalOptions;
                                                    if(isset($newClient['before_command']) and !empty($newClient['before_command'])) {
                                                        $canUseGlobal = false;
                                                        //check for CNT: or SRV:
                                                        if(strlen($newClient['before_command']) >= 4) {
                                                            $firstFour = substr($newClient['before_command'], 0, 4);
                                                            if($firstFour !== "CNT:" and $firstFour !== "SRV:") {
                                                                //add the CNT: directive
                                                                $newClient['before_command'] = "CNT:" . $newClient['before_command'];
                                                            }
                                                        } else {
                                                            $newClient['before_command'] = "CNT:" . $newClient['before_command'];
                                                        }
                                                        $optionsArr['before_command'] = $newClient['before_command'];
                                                    }
                                                    if(isset($newClient['after_command']) and !empty($newClient['after_command'])) {
                                                        $canUseGlobal = false;
                                                        //check for CNT: or SRV:
                                                        if(strlen($newClient['after_command']) >= 4) {
                                                            $firstFour = substr($newClient['after_command'], 0, 4);
                                                            if($firstFour !== "CNT:" and $firstFour !== "SRV:") {
                                                                //add the CNT: directive
                                                                $newClient['after_command'] = "CNT:" . $newClient['after_command'];
                                                            }
                                                        } else {
                                                            $newClient['after_command'] = "CNT:" . $newClient['after_command'];
                                                        }
                                                        $optionsArr['after_command'] = $newClient['after_command'];
                                                    }
                                                    if($canUseGlobal === true) {
                                                        if($globalOptionListID != -1) {
                                                            $client['options'] = $globalOptionListID;
                                                        } else {
                                                            //no global - have to create one
                                                            $options = array();
                                                            $options['name'] = microtime();
                                                            $options['description'] = "Global options list for schedule " . $scheduleInfo['name'];
                                                            $options['type'] = "backup";
                                                            $options['family'] = "Satori-file-level";
                                                            $options['options'] = $globalOptions;
                                                            $optionSave = $this->BP->save_options($options, $sid);
                                                            if($optionSave != false) {
                                                                $sameClients = false;
                                                                $globalOptionListID = $optionSave;
                                                                $optName = "Opt " . $optionSave . " JO " . $scheduleInfo['id'];
                                                                $optionSave = $this->BP->save_options(array("id" => $optionSave, "name" => $optName), $sid);
                                                                $client['options'] = $globalOptionListID;
                                                            } else {
                                                                //failed to save global - pass an error
                                                                $noErrors = false;
                                                            }
                                                        }
                                                    } else {
                                                        //create option list
                                                        $optionListInfo = array();
                                                        $options['name'] = microtime();
                                                        $options['description'] = "Global options list for schedule " . $scheduleInfo['name'];
                                                        $options['type'] = "backup";
                                                        $options['family'] = "Satori-file-level";
                                                        $options['options'] = $optionsArr;
                                                        $optionSave = $this->BP->save_options($options, $sid);
                                                        if($optionSave != false) {
                                                            $client['options'] = $optionSave;
                                                            $optName = "Opt " . $optionSave . " JO " . $scheduleInfo['id'] . " client " . $newClient['id'];
                                                            $optionSave = $this->BP->save_options(array("id" => $optionSave, "name" => $optName), $sid);
                                                        } else {
                                                            //pass back error informing of failed creation
                                                            $noErrors = false;
                                                        }
                                                    }
                                                    $clients[] = $client;
                                                }
                                            }
                                            if($numMatches == $possibleMatches) {
                                                //all clients are the same
                                            } else {
                                                foreach($scheduleInfo['clients'] as $oldClient) {
                                                    $hasMatch = false;
                                                    foreach($data['clients'] as $newClient) {
                                                        if($newClient['id'] == $oldClient['id']) {
                                                            $hasMatch = true;
                                                            break;
                                                        }
                                                    }
                                                    if($hasMatch != true) {
                                                        $sameClients = false;
                                                        //remove all lists that are not global
                                                        if(isset($oldClient['inclusions'])) {
                                                            //$this->BP->delete_selection_list($oldClient['inclusions'], $sid);
                                                            $selectionListsToDelete[] = $oldClient['inclusions'];
                                                        }
                                                        if(isset($oldClient['exclusions'])) {
                                                            //$this->BP->delete_selection_list($oldClient['exclusions'], $sid);
                                                            $selectionListsToDelete[] = $oldClient['exclusions'];
                                                        }
                                                        if(isset($client['options']) and $client['options'] != $globalOptionListID) {
                                                            //$this->BP->delete_options($oldClient['options'], $sid);
                                                            $optionsListsToDelete[] = $oldClient['options'];
                                                        }
                                                    }
                                                }
                                            }
                                        } else if($globalOptionsChange === true) {
                                            //global options changed with no change to clients
                                            //loop through client list and re-save options lists with new parameters
                                            foreach($scheduleInfo['clients'] as $client) {
                                                if($client['options'] != $globalOptionListID) {
                                                    $clientOptions = $this->BP->get_options($client['options'], $sid);
                                                    $clientOptions['dev_name'] = $globalOptions['dev_name'];
                                                    $clientOptions['verify_level'] = $globalOptions['verify_level'];
                                                    $this->BP->save_options(array("id" => $client['options'], "options" => $clientOptions), $sid);
                                                    if($globalOptionListID == -1) {
                                                        //check to see if this is the global
                                                        if(!isset($clientOptions['before_command']) and !isset($clientOptions['after_command'])) {
                                                            //is global
                                                            $globalOptionListID = $client['options'];
                                                        }
                                                    }
                                                } else {
                                                    //already saved the global, so no need to do it again
                                                    continue;
                                                }
                                            }
                                        }
                                        if($sameClients == false) {
                                            $inputParams['clients'] = $clients;
                                        } else {
                                            $inputParams['client'] = $scheduleInfo['clients'];
                                        }
                                        $options = array();
                                        if(isset($data['include_new'])) {
                                            if($scheduleInfo['options']['include_new'] != $data['include_new']) {
                                                $options['include_new'] = $data['include_new'];
                                            }
                                        }
                                        if(isset($data['email_report'])) {
                                            if($scheduleInfo['options']['email_report'] != $data['email_report']) {
                                                $options['email_report'] = $data['email_report'];
                                            }
                                        }
                                        if(isset($data['failure_report'])) {
                                            if($scheduleInfo['options']['failure_report'] != $data['failure_report']) {
                                                $options['failure_report'] = $data['failure_report'];
                                            }
                                        }
                                        if(!empty($options)) {
                                            $inputParams['options'] = $options;
                                        }
                                        if(!empty($inputParams)) {
                                            $result = $this->BP->save_schedule_info($inputParams, $sid);
                                            if($result !== true) {
                                                $status = $this->gatherDetailedEnterpriseSaveInfo($result, $sid);
                                                $noErrors = false;
                                            } else {
                                                //delete any lists if there are any
                                                foreach($selectionListsToDelete as $selectionListID) {
                                                    $this->BP->delete_selection_list($selectionListID, $sid);
                                                }
                                                foreach($optionsListsToDelete as $optionsListID) {
                                                    $this->BP->delete_options($optionsListID, $sid);
                                                }
                                            }
                                        }
                                        if($noErrors == true) {
                                            $status = true;
                                        } else {
                                            //compile all errors
                                        }
                                    } else {
                                        //storage conversion failed for some reason
                                        $status = false;
                                    }
                                }
                            } else {
                                $status = $scheduleInfo;
                            }
                            break;
                        case 'archive':
                            $inputParams = $this->BP->get_archive_schedule_info($id, $sid);
                            if(isset($data['name'])) {
                                $inputParams['name'] = $data['name'];
                                $inputParams['description'] = $inputParams['profile']['description'] = $data['name'];
                            }
                            if(isset($data['clients'])) {
                                $inputParams['profile']['clients'] = $data['clients'];
                            } else {
                                $inputParams['profile']['clients'] = array();
                            }
                            if(isset($data['instances'])) {
                                $inputParams['profile']['instances'] = $data['instances'];
                            } else {
                                $inputParams['profile']['instances'] = array();
                            }
                            if(isset($data['slots'])) {
                                $inputParams['profile']['target_slots'] = $data['slots'];
                            }
                            if(isset($data['types'])) {
                                $inputParams['profile']['types'] = array();
                                if(count($inputParams['profile']['clients']) > 0) {
                                    $appType = "file-level";
                                    $types = array();
                                    foreach($data['types'] as $type) {
                                        $types[] = $this->FUNCTIONS->getBackupTypeCoreNameBackupNow($type, $appType);
                                    }
                                    $inputParams['profile']['types'] = $types;
                                }
                                if (count($inputParams['profile']['instances']) > 0) {
                                    $appTypes = array();
                                    $instances = implode(",", $inputParams['profile']['instances']);
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
                                    $inputParams['profile']['types'] = array_merge($inputParams['profile']['types'], $types);
                                }

                            }
                            $appType = 'Archive';
                            if (isset($data['ical']) && !isset($data['calendar'])) {
                                $calendarReturn = $data['ical'];
                            } else {
                                $calendarReturn = $this->scheduleToICal($data['calendar'], $appType);
                            }
                            if(is_array($calendarReturn) and isset($calendarReturn['error'])) {
                               $status = $calendarReturn;
                            } else {
                                $inputParams['calendar'] = $calendarReturn;
                                if(isset($data['email_report'])) {
                                    $inputParams['email_report'] = $data['email_report'];
                                }
                                if(isset($data['storage'])) {
                                    $inputParams['profile']['target'] = $data['storage'];
                                }

                                $inputParams['profile']['range_end'] = isset($data['range_end']) ? $data['range_end'] : 0;
                                $inputParams['profile']['range_size'] = isset($data['range_size']) ? $data['range_size'] : 0;
                                
                                if(isset($data['append'])) {
                                    $inputParams['profile']['options']['append'] = $data['append'];
                                }
                                if(isset($data['purge'])) {
                                    $inputParams['profile']['options']['purge'] = $data['purge'];
                                }
                                if(isset($data['encrypt'])) {
                                    $inputParams['profile']['options']['encrypt'] = $data['encrypt'];
                                }
                                if(isset($data['retention_days'])) {
                                    $inputParams['profile']['options']['retention_days'] = (int)$data['retention_days'];
                                }
                                $status = $this->BP->save_archive_schedule_info($inputParams, $sid);
                                if($status !== false) {
                                    if(isset($status['id'])) {
                                        $status = true;
                                    }
                                }
                            }
                            break;
                        case "replication":
                            $data['id'] = $id;
                            if ( isset($data['instances']) ) {
                                $data['instance_ids'] = $data['instances'];
                            }
                            unset($data['instances']);
                            $result = $this->BP->save_replication_joborder_info($data, $sid);
                            $status = $result;
                            break;
                        case 'certification':
                            $status = $this->put_certification($id, $sid, $data);

                        break;
                    }
                }
            }
        }
        return $status;
    }

    private function put_certification($id, $sid, $data=null, $action=null, $path=null){
        $ret = array();
        if($sid == $this->BP->get_local_system_id()){
            $ret = $this->put_certification_local($id, $sid, $data, $action, $path);
        } else {
            $ret = $this->put_certification_remote($id, $sid, $data, $action, $path);
        }
        if(isset($ret['message']) && $ret['message'] === "OK"){
            $ret = null;
        }
        return $ret;
    }

    private function put_certification_remote($id, $sid, $data, $action, $path = null){
        $remoteSysInfo = $this->BP->get_system_info($sid);
        $url = $remoteSysInfo['host'];

        // Build api
        $api = "/api/joborders/";
        if ($action != null){
            $api .= $action . "/";
        }
        $api .= $id . "c/";
        if ($path != null){
            $api .= $path . "/";
        }

        $remoteRet = $this->FUNCTIONS->remoteRequestRDR($url, 'PUT', $api, "",$data);

        $ret = array();
        if (isset($remoteRet['message'])){
            // pass on error message
            $ret = $remoteRet['message'];
        } else {
            $ret = $remoteRet['result'];
        }

        return $ret;
    }

    private function put_certification_local($id, $sid, $data, $action, $path = null){
        if ($action !== null) {
            switch($action){
                case 'enable':
                    $status = $this->RDR->enable_job($id);
		    if( $status['message'] == "Enable OK"){
			    $status = 0;
		    }
                    break;
                case 'disable':
                    $status = $this->RDR->disable_job($id);
		    if( $status['message'] == "Disable OK"){
			    $status = 0;
		    }
                    break;
                case 'test':
                    $status = $this->RDR->test_job($id);
                    break;
                case 'failover':
                    // build parameter list for RDR call
                    $params = array();
                    if (isset($data['failovers'])){
                        foreach($data['failovers'] as $failover) {
                            $param = array();
                            $param['id'] = $failover['vm_id'];
                            $param['crpRef'] = $failover['restore_point_ref'];
                            $params[] = $param;
                        }
                    }
                    if($path == "Failover"){
                        $path = "";
                    }
                    else if($path == "InstantLab"){
                        $path = "/?test_network=true";
                    }
                    $status = $this->RDR->failover_job($id,$path,$params); 
                    break;
                case 'commit':
                    $status = $this->RDR->commit_job($id);
                    break;
                case 'discard':
                    $status = $this->RDR->discard_job($id);
                    break;
            }
        } else {
            /*
             * RDR expects a complete job, not just the bits to be modified,
             * so we retrieve the schedule to be modified, modify it here,
             * and pass the full, modified job to RDR
             */

            // get schedule from RDR
            $certificationSchedule = $this->RDR->get_job($id);

            if( !is_array($certificationSchedule) || isset($certificationSchedule['error']) ) {
                // pass along error message
                $status = $certificationSchedule;
            } else if (!isset($data)) {
                $status = "Null or malformed input. Cannot update";
            } else {
                // RDR will create a new service profile if we pass it one,
                // so we unset it here before sending, but leave
                // serviceProfileId
                unset($certificationSchedule['profile']);

                // add updated data to schedule
                if(isset($data['name'])){
                    $certificationSchedule['name'] = $data['name'];
                }
                if(isset($data['certification_target'])) {
                    $virtualMachines = $data['certification_target']['instances'];
                    if(isset($virtualMachines)){
                        $vms = $this->buildRDRSubArray($certificationSchedule['vms'],$virtualMachines,$this->vmMap, true);
                        $certificationSchedule['vms'] = $vms;
                    }
                }

                if( isset($data['certification_options'])) {
                    $options = $data['certification_options'];
                    foreach($options as $key => $value) {
                        switch($key){
                            case "power_on_timeout":
                                $certificationSchedule['poweron_timeout'] = $value;
                                break;
                            case "service_profile_id":
                                $certificationSchedule['profile_id'] = $value;
                                break;
                            case "post_custom_script":
                                $certificationSchedule['post_script_cmd'] = $value;
                                break;
                            case "post_custom_script_arguments":
                                $certificationSchedule['post_script_args'] = $value;
                                break;
                            default:
                                $certificationSchedule[$key] = $value;
                        }
                    }
                }
                if(isset($data['calendar']) && empty($data['calendar'])){
                    $certificationSchedule['schedule'] = null;
                } else if(isset($data['calendar'][0])) {
                    $calendar = $data['calendar'][0];
                    if(!isset($certificationSchedule['schedule'])) {
                        $certificationSchedule['schedule'] = array();
                    }
                    // RDR does not support setting 'enabled' via the edit jobs api
                    unset($calendar['enabled']);
                    // createRdrScheduleArr combines start_date and start_time to get
                    // StartDateTime, but we need to be able to update date and time
                    // separately, so get them directly from $calendar
                    $startTimestamp = $this->FUNCTIONS->dateToTimestamp($certificationSchedule['schedule']['start_time']);
                    $startTime = $this->FUNCTIONS->formatTime($startTimestamp);
                    $startDate = $this->FUNCTIONS->formatDate($startTimestamp);
                    if (isset($calendar['start_time'])){
                        $startTime = $calendar['start_time'];
                    }
                    if (isset($calendar['start_date'])){
                        $startDate = $calendar['start_date'];
                    }
                    $certificationSchedule['schedule']['start_time'] = $startDate . ' ' . $startTime;

                    // createRdrScheduleArr combines start_date and end_time to get
                    // EndDateTime, but we need to be able to update date and time
                    // separately, so get them directly from $calendar
                    $endTimestamp = $this->FUNCTIONS->dateToTimestamp($certificationSchedule['schedule']['end_time']);

                    $endTime = $this->FUNCTIONS->formatTime($endTimestamp);
                    // UEB treats hour=24 as end of day
                    // RDR treats 23:59 as end of day 
                    if (isset($calendar['end_hour'])){
                        if ($calendar['end_hour'] == 24){
                            $endTime = '23:59';
                        } else {
                            $endTime = $calendar['end_hour'] . ':00';
                        }
                    }
                    $certificationSchedule['schedule']['end_time'] = $startDate . ' ' . $endTime;
                    // create RDR schedule array to get remaining calendar details from
                    $calendarArr = $this->createRdrScheduleArr($calendar);
                    if (isset($calendarArr['days_of_week'])) {
                        $certificationSchedule['schedule']['days_of_week'] = $calendarArr['days_of_week'];
                    }
                    if (isset($calendarArr['is_interval'])) {
                        $certificationSchedule['schedule']['is_interval'] = $calendarArr['is_interval'];
                    }
                    if (isset($calendarArr['interval_unit'])) {
                        $certificationSchedule['schedule']['interval_unit'] = $calendarArr['interval_unit'];
                    }
                    if (isset($calendarArr['interval_value'])) {
                        $certificationSchedule['schedule']['interval_value'] = $calendarArr['interval_value'];
                    }
                }

                // send schedule to RDR
                $result = $this->RDR->edit_job($certificationSchedule);
                if( !is_array($result) || isset($result['error'])){
                    // pass along error message
                    $status = $result;
                } else {
                    // pass no message
                    $status = null;
                }
            }
        }
        return $status;
    }

    //{ 
    //  Map arrays used by buildRDRSubArray()
    //  Format:
    //      $map = array(
    //          'UEBkey' => 'RDRkey'
    //      );
    
    private $vmMap = array( 
        'application_tests' =>  'vm_tests',
        'credential_id' => 'guest_credential_id',
        'id' => 'instance_id',
        'networks' => 'vm_ips',
        'os_type' => 'guest_os',
        'password' => 'guest_password',
        'power_on' => 'is_poweron',
        'state' => 'vm_state',
        'username' => 'guest_user',
        'vm_id' => 'id'
    );

    private $vmNetworkMap = array(
        'ip_address' => 'ip_addr'
    );

    private $vmTestMap = array(
        'timeout_min' => 'timeout_m',
        'parameters' => 'vm_test_params'
    );

    private $vmTestParamMap = array(
    );

    // subarray mapping lookup table
    private $rdrJobSubArrays = array(
        'vm_test_params' => 'vmTestParamMap',
        'vm_tests' => 'vmTestMap',
        'vm_ips' => 'vmNetworkMap'
    );
    //}

    // Remaps keys of $array using $mapping
    //
    // Helper function for buildRDRSubArray
    // Only remaps top-level keys (does not recurse)
    private function remapKeys($array,$mapping){
        foreach($array as $oldKey => $value){
            if(isset($mapping[$oldKey])){
                $array[$mapping[$oldKey]] = $value;
                unset($array[$oldKey]);
            }
        }
        return $array;
    }

    // Helper function for updating RDR jobs
    //
    // First, remaps keys from UEB keys to RDR keys,
    //  then updates $oldArray with $newArray, recursing
    //  into member arrays.
    // Assumes that any member arrays are in $rdrJobSubArrays
    private function buildRDRSubArray($oldArray,$newArray,$map, $vmArray = false){
        $array = array();
        foreach($newArray as $newMember){
            // remap $newMember's Keys to what rdr expects
            $newMember = $this->remapKeys($newMember,$map);
            // flag tracks whether member alredy exists
            $memberExists = false;
            foreach($oldArray as $oldMember){
                // VMs match on 'instance_id' 
                // application tests (vm_tests) match on 'id'
                // test parameters (vm_test_params) and networks (vm_ips) match on 'name' 
                // To see what we need to match on, check which $map we're using
                switch($map){
                    case $this->vmMap:
                        $matchKey = 'instance_id';
                        break;
                    case $this->vmTestParamMap:
                    case $this->vmNetworkMap:
                        $matchKey = 'name';
                        break;
                    default:
                        $matchKey = 'id';
                }

                if (!isset($oldMember[$matchKey]) || !isset($newMember[$matchKey])){
                    continue;
                }
                if ($oldMember[$matchKey] == $newMember[$matchKey] && $oldMember['id'] == $newMember['id']) {
                    $member = array_merge($oldMember,$newMember);
                    // check for member arrays
                    foreach($member as $subMemberKey => $subMember){
                        if(is_array($subMember) && !empty($newMember[$subMemberKey])){
                            // Use rdrJobSubArrays to find which subMap to use for $submember
                            if (!isset($this->rdrJobSubArrays[$subMemberKey])){
                                continue;
                            }
                            $subMap = $this->{$this->rdrJobSubArrays[$subMemberKey]};
                            // recurse
                            $member[$subMemberKey] = $this->buildRDRSubArray($oldMember[$subMemberKey],$newMember[$subMemberKey],$subMap);
                        }
                    }
                    // Add $type only to 'vms' not the sub-arrays; if $type is not set, or it isn't set to RDR VM; applicable for DCA jobs already existing before the fix for UNIBP-16778
                    if ($vmArray && (!isset($member['$type']) || $member['$type'] !== Constants::RDR_VM)) {
                        $member['$type'] = Constants::RDR_VM;
                    }
                    $array[] = $member;
                    $memberExists = true;
                    break;
                }
            }
            if(!$memberExists){
                // New member. Remap subarray keys and insert.

                // Add $type only to 'vms' not the sub-arrays; if $type is not set, set it
                if ($vmArray && (!isset($member['$type']) || $member['$type'] !== Constants::RDR_VM)) {
                    $type_array = array('$type' => Constants::RDR_VM);
                    // $type should be the first parameter
                    $newMember = array_merge($type_array, $newMember);
                }

                // check for member arrays
                foreach($newMember as $subMemberKey => $subMember){
                    if(is_array($subMember)){
                        // Use rdrJobSubArrays to find which subMap to use for $subMember
                        if (!isset($this->rdrJobSubArrays[$subMemberKey])){
                            continue;
                        }
                        $subMap = $this->{$this->rdrJobSubArrays[$subMemberKey]};
                        // recurse
                        $newMember[$subMemberKey] = $this->buildRDRSubArray(array(),$newMember[$subMemberKey],$subMap);
                    }
                }
                $array[] = $newMember;
            }
        }
        return $array;
    }

    public function scheduleToICal($schedParams, $appType) {
        //Create initial iCal string
        $iCal = "BEGIN:VCALENDAR";

        foreach($schedParams as $calItem) {
            $iCal .= "\r\nBEGIN:VEVENT";
            $backupType = $this->FUNCTIONS->getBackupTypeCoreName($calItem['backup_type'], $appType);
            $iCal = $iCal . "\r\nSUMMARY:" . $backupType;
            $iCal .= "\r\nDESCRIPTION:";
            //DTSTART
            $iCal .= "\r\nDTSTART:";

            $timestamp = $this->FUNCTIONS->dateToTimestamp($calItem['start_date'] . " " . $calItem['start_time']);
            if($timestamp !== false and (is_numeric($timestamp) and $timestamp >= 0)) {
                $timeStringArr = array();
                $iCal .= $this->createIcalDate($timestamp, $timeStringArr);

                // set DTEND as 1 hour after DTSTART.
                $endTimestamp = $timestamp + 3600;
                $iCal .= "\r\nDTEND:" . $this->createIcalDate($endTimestamp);

                //only make an RRULE if it is recurring event, ie. schedule_run, run_on, or recurrence are set
                if(isset($calItem['schedule_run']) or isset($calItem['run_on']) or isset($calItem['recurrence'])) {
                    //RRULE
                    $iCal = $iCal . "\r\nRRULE:";

                    if($calItem['schedule_run'] == 0) {
                        $iCal .= "FREQ=WEEKLY";
                    } else if ($calItem['schedule_run'] == 1) {
                        $iCal .= "FREQ=MONTHLY";
                    } else if ($calItem['schedule_run'] == 5) {
                        $iCal .= "FREQ=WEEKLY;INTERVAL=2";
                    }
                    if(isset($calItem['run_on']) and count($calItem['run_on']) == 0) {
                        continue;
                    } else {
                        //Days
                        $dayString = ";BYDAY=";
                        $individualDayString = "";
                        foreach($calItem['run_on'] as $day) {
                            switch($day) {
                                case 0:
                                    $individualDayString .= ",SU";
                                    break;
                                case 1:
                                    $individualDayString .= ",MO";
                                    break;
                                case 2:
                                    $individualDayString .= ",TU";
                                    break;
                                case 3:
                                    $individualDayString .= ",WE";
                                    break;
                                case 4:
                                    $individualDayString .= ",TH";
                                    break;
                                case 5:
                                    $individualDayString .= ",FR";
                                    break;
                                case 6:
                                    $individualDayString .= ",SA";
                                    break;
                            }
                        }
                        //Remove the initial comma from the day list
                        $dayString .= substr($individualDayString, 1);
                        $iCal .= $dayString;
                    }
                    if(isset($calItem['recurrence']) and $calItem['recurrence'] != 0) {
                        $recurrence = $calItem['recurrence'];

                        // For less-than-hourly intervals we need BYMINUTE
                        // and BYHOUR ical statements.
                        if($recurrence < 60) {
                            //minutes
                            $iCal .= ";BYMINUTE=";
                            $minutes = '';
                            for($i = 0; $i <= 59/(int)$recurrence; $i++) {
                                $minutes .= ',' . ($i * (int)$recurrence);
                            }
                            $iCal .= substr($minutes, 1);
                            $recurrence = 60;
                        }

                        //hours
                        $iCal .= ";BYHOUR=";
                        if(isset($calItem['begin_hour'])) {
                            if(is_numeric($calItem['begin_hour'])) {
                                $beginHour = $calItem['begin_hour'];
                            } else {
                                $time = strtotime($calItem['begin_hour']);
                                $beginHour = date("G", $time);
                            }
                        } else {
                            $beginHour = $timeStringArr[0];
                        }
                        if(isset($calItem['end_hour'])) {
                            if(is_numeric($calItem['end_hour'])) {
                                $endHour = $calItem['end_hour'];
                            } else {
                                $time = strtotime($calItem['end_hour']);
                                $endHour = date("G", $time);
                            }
                        } else {
                            $endHour = 24;
                        }
                        if($beginHour >= $endHour) {
                            $status = array();
                            $status['error'] = 500;
                            $status['message'] = "Start time is greater than end time.";
                        } else {
                            $hourInterval = (int)$recurrence/60;
                            $hours = '';
                            for($i = 0; $i <= 23/$hourInterval; $i++) {
                                if((($i * $hourInterval) + $beginHour) < $endHour) {
                                    $hours .= ',' . (($i * $hourInterval) + $beginHour);
                                } else {
                                    break;
                                }
                            }
                            if($hours != '') {
                                $iCal .= substr($hours, 1);
                            }
                        }
                    }
                }
                $iCal .= "\r\nEND:VEVENT";
            } else {
                $status = array();
                $status['error'] = 500;
                $status['message'] = "The date provided is invalid. Please verify the date is in the appropriate format and the time is in HH:MM AM/PM format and try again.";
                break;
            }

        }

        //End Calendar
        $iCal .= "\r\nEND:VCALENDAR\r\n";

        if(isset($status['error'])) {
            return $status;
        } else {
            return $iCal;
        }
    }

    /*
     * Given a timestamp (an epoch), convert to a string format suitable for iCal, e.g., "20170404T030000".
     */
    private function createIcalDate($timestamp, &$timeStringArr = array()) {
        $dateTimeString = $this->FUNCTIONS->formatDateTime24Hour($timestamp);
        $dateTimeStringArr = explode(" ", $dateTimeString);
        $dateStringArr = explode("/", $dateTimeStringArr[0]);
        $timeStringArr = explode(":", $dateTimeStringArr[1]);
        $dateString = $dateStringArr[2] . $dateStringArr[0] . $dateStringArr[1];
        $timeString = "T" . $timeStringArr[0] . $timeStringArr[1] . $timeStringArr[2];
        return $dateString . $timeString;
    }

    // Creates an RDR schedule array from Satori's schedule array
    public function createRdrScheduleArr($schedule) {
        $rdrSchedule = array();

        if(isset($schedule['enabled'])){
            $rdrSchedule['is_enabled'] = $schedule['enabled'];
        } else {
            $rdrSchedule['is_enabled'] = "true";
        }

        if(isset($schedule['start_time'])){
            $rdrSchedule['start_time'] = $schedule['start_date'] .' '. $schedule['start_time'];
        } else {
            $rdrSchedule['start_time'] = $schedule['start_date'] . ' 0:00';
        }

        if(isset($schedule['run_on'])) {
            $DaysOfWeek = array();
            foreach($schedule['run_on'] as $day) {
                switch ($day) {
                    case "0":
                        $DaysOfWeek[] = "Sunday";
                        break;
                    case "1":
                        $DaysOfWeek[] = "Monday";
                        break;
                    case "2":
                        $DaysOfWeek[] = "Tuesday";
                        break;
                    case "3":
                        $DaysOfWeek[] = "Wednesday";
                        break;
                    case "4":
                        $DaysOfWeek[] = "Thursday";
                        break;
                    case "5":
                        $DaysOfWeek[] = "Friday";
                        break;
                    case "6":
                        $DaysOfWeek[] = "Saturday";
                        break;
                }
            }
            $rdrSchedule['days_of_week'] = implode(",", $DaysOfWeek);
        }
        if(isset($schedule['recurrence'])) {
            $rdrSchedule['is_interval'] = 'true';
            $rdrSchedule['interval_unit'] = 'Minutes';
            $rdrSchedule['interval_value'] = $schedule['recurrence'];
        } else if( array_key_exists('recurrence', $schedule)){
            // 'recurrence' exists but is not set, so unset in RDr
            $rdrSchedule['is_interval'] = 'false';
        }
        if(!isset($schedule['end_hour']) || $schedule['end_hour'] == 24){
            // RDR doesn't need a date for end_time. Use start_date so the 
            //  end_time isn't before start_time
            // 23:59 is the end of the day as far as RDR is concerned.
            $rdrSchedule['end_time'] = $schedule['start_date'] . ' 23:59';
        } else {
            $rdrSchedule['end_time'] = $schedule['start_date'] .' '. $schedule['end_hour'] . ':00';
        }

        return $rdrSchedule;
    }

    // Formats RDR's schedule array to satori's schedule array
    public function formatRdrScheduleArr($rdrSchedule) {
        $schedule = array();
        $daysOfWeek = explode("," , $rdrSchedule['days_of_week']);
        foreach ($daysOfWeek as $day) {
            switch ($day) {
                case 'Sunday':
                    $schedule['run_on'][] = 0;
                    break;
                case 'Monday':
                    $schedule['run_on'][] = 1;
                    break;
                case 'Tuesday':
                    $schedule['run_on'][] = 2;
                    break;
                case 'Wednesday':
                    $schedule['run_on'][] = 3;
                    break;
                case 'Thursday':
                    $schedule['run_on'][] = 4;
                    break;
                case 'Friday':
                    $schedule['run_on'][] = 5;
                    break;
                case 'Saturday':
                    $schedule['run_on'][] = 6;
                    break;
            }
        }
        $schedule['schedule_run'] = 0;
        $startTimestamp = $this->FUNCTIONS->dateTotimestamp($rdrSchedule['start_time']);
        $schedule['start_date'] = $this->FUNCTIONS->formatDate($startTimestamp);
        $schedule['start_time'] = $this->FUNCTIONS->formatTime($startTimestamp);
        if($rdrSchedule['end_time']) {
            $endTimestamp = $this->FUNCTIONS->dateTotimestamp($rdrSchedule['end_time']);
            $endTime = $this->FUNCTIONS->formatTime($endTimestamp);
            // We only care about time here. Date is just a placeholder for RDR.
            // 23:59 is the end of the day as far as RDR is concerned.
            if ($endTime == "11:59:00 pm") {
                $schedule['end_hour'] = 24;
            } else {
                $schedule['end_hour'] = date('G',$endTimestamp);
            }
        }
        if($rdrSchedule['is_interval']){
            //Minutes or Hours
            switch($rdrSchedule['interval_unit']) {
                case 'Minutes':
                    $schedule['recurrence'] = $rdrSchedule['interval_value'];
                    break;
                case 'Hours':
                    $schedule['recurrence'] = $rdrSchedule['interval_value'] * 60;
                    break;
            }
        }
        return $schedule;
    }

    public function iCalToSchedule($iCalString) {
        $iCalArr = explode("BEGIN:VEVENT", $iCalString);
        for ($i = 0; $i < count($iCalArr); $i++) {
            $iCalArr[$i] = explode("\r\n", $iCalArr[$i]);
            for ($j = 0; $j < count($iCalArr[$i]); $j++) {
                $iCalArr[$i][$j] = explode(":", $iCalArr[$i][$j]);
            }
        }
        $scheduleParams = array();

        //VEVENT Layer (should never be more than 2)
        for($i = 0; $i < count($iCalArr); $i++) {
            $scheduleItem = array();
            foreach($iCalArr[$i] as $iCalPiece) {
                switch($iCalPiece[0]) {
                    case 'SUMMARY':
                        if(!isset($scheduleItem['backup_type'])) {
                            $scheduleItem['backup_type'] = $this->FUNCTIONS->getBackupTypeDisplayName(strtolower($iCalPiece[1]));
                        }
                        break;
                    case 'DTSTART':
//                        $dateTimeString = explode("T", $iCalPiece[1]);

                        //date will be in format 20140521 to be converted to 05/21/2014
//                        $date = substr($dateTimeString[0], 4, 2) . "/" . substr($dateTimeString[0], 6) . "/" . substr($dateTimeString[0], 0, 4);

                        //time will be in format 134500 to be converted to 13:45
//                        $time = substr($dateTimeString[1], 0, 2) . ":" . substr($dateTimeString[1], 2, 2);

                        $timestamp = $this->FUNCTIONS->dateToTimestamp($iCalPiece[1]);
                        $dateTime = $this->FUNCTIONS->formatDateTime($timestamp);
                        $dateTimeArray = explode(" ", $dateTime);

                        if(!isset($scheduleItem['start_date'])) {
                            $scheduleItem['start_date'] = $dateTimeArray[0];
                        }

                        if(!isset($scheduleItem['start_time'])) {
                            $startTime = $dateTimeArray[1];
                            if (isset($dateTimeArray[2])) {
                                $startTime .= " " . $dateTimeArray[2];
                            }
                            $scheduleItem['start_time'] = $startTime;
                        }
                        break;
                    case 'RRULE':
                        $rules = $iCalPiece[1];
                        $weekly = false;
                        //explode the rule string into each separate rule
                        $ruleArr = explode(";", $rules);
                        for($k = 0; $k < count($ruleArr); $k++) {
                            $ruleArr[$k] = explode("=", $ruleArr[$k]);
                            switch($ruleArr[$k][0]) {
                                case 'FREQ':
                                    switch($ruleArr[$k][1]) {
                                        case 'DAILY':
                                            if(!isset($scheduleItem['schedule_run'])) {
                                                $scheduleItem['schedule_run'] = '0';
                                                // If daily, still BY for days to run the schedule.
                                                //$scheduleItem['run_on'] = array('0', '1', '2', '3', '4', '5', '6');
                                            }
                                            break;
                                        case 'WEEKLY':
                                            $weekly = true;
                                            if(!isset($scheduleItem['schedule_run'])) {
                                                $scheduleItem['schedule_run'] = '0';
                                            }
                                            break;
                                        case 'MONTHLY':
                                            if(!isset($scheduleItem['schedule_run'])) {
                                                $scheduleItem['schedule_run'] = '1';
                                            }
                                            break;
                                    }
                                    break;
                                case 'BYMONTHDAY':
                                    $scheduleItem['month_day'] = $ruleArr[$k][1];
                                    $scheduleItem['schedule_run'] = '6';
                                    // A month on a specified day number is custom to the UI.
                                    $scheduleItem['custom'] = true;
                                    break;
                                case 'BYDAY':
                                    $days = explode(",", $ruleArr[$k][1]);
                                    if(!isset($scheduleItem['run_on'])) {
                                        $dayArr = array();
                                        foreach($days as $day) {
                                            switch($day) {
                                                case 'SU':
                                                    $dayArr[] = '0';
                                                    break;
                                                case 'MO':
                                                    $dayArr[] = '1';
                                                    break;
                                                case 'TU':
                                                    $dayArr[] = '2';
                                                    break;
                                                case 'WE':
                                                    $dayArr[] = '3';
                                                    break;
                                                case 'TH':
                                                    $dayArr[] = '4';
                                                    break;
                                                case 'FR':
                                                    $dayArr[] = '5';
                                                    break;
                                                case 'SA':
                                                    $dayArr[] = '6';
                                                    break;
                                            }
                                            $scheduleItem['run_on'] = $dayArr;
                                        }
                                    }
                                    break;
                                case 'BYHOUR':
                                    if(!isset($scheduleItem['recurrence'])) {
                                        $hours = explode(",", $ruleArr[$k][1]);
                                        $scheduleItem['begin_hour'] = (int)$hours[0];
                                        $scheduleItem['end_hour'] = (int)$hours[count($hours)-1];
                                        if(count($hours) == 1) {
                                            //we can't get a recurrence if there is only one hour specified
                                            //start and end hour would be the same in this case, meaning we need to add 1 to the end hour (as that is when it would complete)
                                            $scheduleItem['end_hour'] += 1;
                                            // Hourly with only 1 element is considered custom by the UI.
                                            $scheduleItem['by_hour'] = $hours;
                                            $scheduleItem['custom'] = true;
                                        } else {
                                            $scheduleItem['recurrence'] = (int)(((int)$hours[1] - (int)$hours[0])*60);
                                            if (!$this->sameDeltas($hours)) {
                                                $scheduleItem['by_hour'] = $hours;
                                                // Irregular hourly repetition is considered custom by the UI.
                                                $scheduleItem['custom'] = true;
                                            }
                                            if($scheduleItem['end_hour'] !== 23) {
                                                $scheduleItem['end_hour'] += (int)($scheduleItem['recurrence']/60);
                                                if($scheduleItem['end_hour'] > 24) {
                                                    $scheduleItem['end_hour'] = 24;
                                                }
                                            } else {
                                                $scheduleItem['end_hour'] = 24;
                                            }
                                        }
                                    }
                                    break;
                                case 'BYMINUTE':
                                    if(!isset($scheduleItem['recurrence']) or $scheduleItem['recurrence'] == 60) {
                                        $minutes = explode(",", $ruleArr[$k][1]);
                                        $scheduleItem['recurrence'] = (int)((int)$minutes[1] - (int)$minutes[0]);
                                    }
                                    break;
                                case 'INTERVAL':
                                    if($weekly and $ruleArr[$k][1] == 2) {
                                        $scheduleItem['schedule_run'] = '5';
                                    } else {
                                        continue;
                                    }
                                    break;
                            }
                        }
                        break;
                }
            }
            if($scheduleItem) {
                if (!isset($scheduleItem['schedule_item']) &&
                    !isset($scheduleItem['run_on']) &&
                    !isset($scheduleItem['recurrence'])) {
                    // It has no iterations, handle as custom by the UI/caller.
                    $scheduleItem['custom'] = true;
                }
                $scheduleParams[] = $scheduleItem;
            }
        }
        return $scheduleParams;
    }

    public function getShortCalendarArr($calendarArr) {
        $shortCalArr = array();
        foreach($calendarArr as $calItem) {
            $shortCalString = "";
            if($calItem['backup_type'] != Constants::JOBORDER_TYPE_ARCHIVE && $calItem['backup_type'] != Constants::JOBORDER_TYPE_CERTIFICATION) {
                $shortCalString .= $calItem['backup_type'] . ": ";
            }
            if(isset($calItem['schedule_run'])) {
                switch($calItem['schedule_run']) {
                    case 1:
                        $shortCalString .= "1st ";
                        break;
                    case 2:
                        $shortCalString .= "2nd ";
                        break;
                    case 3:
                        $shortCalString .= "3rd ";
                        break;
                    case 4:
                        $shortCalString .= "4th ";
                        break;
                    case 5:
                        $shortCalString .= "Every other ";
                        break;
                    case 6:
                        $shortCalString .= "Monthly ";
                        break;
                }
            }
            if (isset($calItem['month_day'])) {
                if ($calItem['month_day'] == -1) {
                    $shortCalString .= 'on last day';
                } else {
                    $shortCalString .= 'on day ' . $calItem['month_day'];
                }
            }
            if(isset($calItem['run_on'])) {
                if(count($calItem['run_on']) == 7) {
                    $shortCalString .= "Sun-Sat";
                } elseif(count($calItem['run_on']) == 1) {
                    $shortCalString .= $this->shortCalStringHelper($calItem['run_on'][0]);
                } else {
                    $prevDay = $calItem['run_on'][0] - 1;
                    $inSequence = true;
                    foreach($calItem['run_on'] as $day) {
                        if($day == $prevDay + 1) {
                            $prevDay = $day;
                        } else {
                            $inSequence = false;
                            break;
                        }
                    }
                    if($inSequence) {
                        $firstDay = $calItem['run_on'][0];
                        $lastDay = $calItem['run_on'][count($calItem['run_on']) - 1];
                        $shortCalString .= $this->shortCalStringHelper($firstDay) . "-" . $this->shortCalStringHelper($lastDay);
                    } else {
                        foreach($calItem['run_on'] as $day) {
                            $shortCalString .= $this->shortCalStringHelper($day) . ",";
                        }
                        $shortCalString = substr_replace($shortCalString, "", -1);
                    }
                }
            }
            if(isset($calItem['recurrence'])) {
                if($calItem['recurrence'] < 60) {
                    $shortCalString .= " every " . $calItem['recurrence'] . " minutes";
                } else {
                    if (!isset($calItem['by_hour'])){
                        $shortCalString .= " every " . $calItem['recurrence']/60 . " hours";                       
                    }
                }
                if (isset($calItem['by_hour'])) {
                    $shortCalString .= " at hours " . implode(',', $calItem['by_hour']);
                } else {
                    if (isset($calItem['begin_hour'])) {
                        $start = $calItem['begin_hour'];
                        if ($start == 0) {
                            $shortCalString .= " starting at/near midnight";
                        } else {
                            $ampm = ' am';
                            if ($start > 12) {
                                $start -= 12;
                                $ampm = ' pm';
                            } elseif ($start == 12) {
                                $ampm = ' pm';
                            }
                            $shortCalString .= " starting at " . $start . $ampm;
                        }
                    }                   
                }
            } elseif(!isset($calItem['recurrence']) and !isset($calItem['schedule_run']) and !isset($calItem['run_on'])) {
                $shortCalString .= $calItem['start_date'] . " at " . $this->removeMinutes($calItem['start_time']);
            } elseif (isset($calItem['schedule_run']) && $calItem['schedule_run'] == 6) {
                $shortCalString .= " at " . $this->removeMinutes($calItem['start_time']);
            } else {
                if (isset($calItem['by_hour'])) {
                    $shortCalString .= " on hour " . implode(',', $calItem['by_hour']);
                }
                if(!isset($calItem['run_on'])) {
                    $timestamp = strtotime($calItem['start_date']);
                    $day = date("D", $timestamp);
                    $shortCalString .= " " . $day;
                }
                $shortCalString .= " at " . $this->removeMinutes($calItem['start_time']);
            }
            $shortCalArr[] = $shortCalString;
        }
        return $shortCalArr;
    }
    
    private function removeMinutes($timeString) {
        $returner = false;
        $twentyFourHourTime = $this->FUNCTIONS->twentyFourHourTime();
        if ($twentyFourHourTime === false){
            $date = DateTime::createFromFormat(Constants::TIME_FORMAT_12H, $timeString);
            if ($date !== false){
                $returner = $date->format(Constants::TIME_FORMAT_12H_NO_MINUTES);
            }
        } else if ($twentyFourHourTime === true) {
            $date = DateTime::createFromFormat(Constants::TIME_FORMAT_24H, $timeString);
            if ($date !== false) {
                $returner = $date->format(Constants::TIME_FORMAT_24H_NO_MINUTES);
            }
        }

        return $returner;
    }

    public function shortCalStringHelper($intDay) {
        switch($intDay) {
            case 0:
                $stringDay = "Sun";
                break;
            case 1:
                $stringDay = "Mon";
                break;
            case 2:
                $stringDay = "Tue";
                break;
            case 3:
                $stringDay = "Wed";
                break;
            case 4:
                $stringDay = "Thu";
                break;
            case 5:
                $stringDay = "Fri";
                break;
            case 6:
                $stringDay = "Sat";
                break;
        }
        return $stringDay;
    }

// backup_now_multi
// Called to invoke backup_now for multiple file-level clients
    public function backup_now_multi($data, $sid, $appType) {

        $returnArr = array();

        $storageValid = true;
        if(isset($data['storage'])) {
            if(strtolower($data['storage']) != "internal" and $data['storage'] != "") {
                $deviceName = $this->BP->get_device_for_storage($data['storage'], $sid);
                if($deviceName === false) {
                    $storageValid = false;
                }
            }
        }
        if($storageValid) {
            if ($appType === "") {
                $appType = Constants::APPLICATION_NAME_FILE_LEVEL;
            }
            $backupType = $data['backup_type'];
            $backupType = $this->FUNCTIONS->getBackupTypeCoreNameBackupNow($backupType, $appType);

            if(isset($data['verify'])) {
                switch($data['verify']) {
                    case "none":
                        $verify = 0;
                        break;
                    case "inline":
                        $verify = 3;
                        break;
                }
            } else {
                $verify = 3;
            }

            $clients = $data['clients'];
            foreach($clients as $inputClient) {
                $backupClient = array();

                $backupClient['id'] = $inputClient['id'];

                if(isset($inputClient['priority'])) {
                    $backupClient['priority'] = $inputClient['priority'];
                }
                if(isset($inputClient['excl_list'])) {
                    $backupClient['excl_list'] = $inputClient['excl_list'];
                }
                if(isset($inputClient['incl_list'])) {
                    $backupClient['incl_list'] = $inputClient['incl_list'];
                }
                if(isset($inputClient['metanames'])) {
                    $backupClient['metanames'] = $inputClient['metanames'];
                }
                if(isset($deviceName)) {
                    $backupClient['dev_name'] = $deviceName;
                }
                if(isset($verify)) {
                    $backupClient['verify_level'] = $verify;
                }

                $status = $this->BP->backup_now($backupType, $backupClient, $sid);

                if($status !== false) {
                    $item = array();
                    foreach($status as $returnItem) {
                        $clientInfo = $this->BP->get_client_info($returnItem['client_id'], $sid);
                        if ($clientInfo != false) {
                            $item['client_name'] = $clientInfo['name'];
                        }

                        $item['job_id'] = $returnItem['job_id'];
                        if($item['job_id'] == -1) {
                            $item['message'] = $returnItem['msg'];
                        }
                        $returnArr[] = $item;
                    }
                } else {
                    $item = array();
                    $item['client_name'] = $inputClient['name'];
                    $item['message'] = 'On-Demand Job Failed:  ' . $this->BP->getError();
                    $returnArr[] = $item;
                }
            }
            if (isset($data['instances'])) {
                $instances = $data['instances'];
                foreach ($instances as $instance) {
                    $inputParams = array();
                    $instanceInfo = $this->BP->get_appinst_info($instance['id'], $sid);
                    $info = $instanceInfo[$instance['id']];
                    $inputParams['id'] = $info['client_id'];
                    $inputParams['appinst_ids'] = $instance['id'];

                    if(isset($instance['excl_list'])) {
                        $inputParams['excl_list'] = $instance['excl_list'];
                    }
                    if(isset($instance['incl_list'])) {
                        $inputParams['incl_list'] = $instance['incl_list'];
                    }
                    if(isset($instance['metanames'])) {
                        $inputParams['metanames'] = $instance['metanames'];
                    }
                    if(isset($deviceName)) {
                        $inputParams['dev_name'] = $deviceName;
                    }
                    if(isset($verify)) {
                        $inputParams['verify_level'] = $verify;
                    }

                    $status = $this->BP->backup_now($backupType, $inputParams, $sid);

                    if($status !== false) {
                        $item = array();
                        foreach($status as $returnItem) {
                            $clientInfo = $this->BP->get_client_info($returnItem['client_id'], $sid);
                            if ($clientInfo != false) {
                                $item['client_name'] = $clientInfo['name'];
                            }

                            $item['job_id'] = $returnItem['job_id'];
                            if($item['job_id'] == -1) {
                                $item['message'] = $returnItem['msg'];
                            }
                            $returnArr[] = $item;
                        }
                    } else {
                        $item = array();
                        $item['client_name'] = $instance['name'];
                        $item['message'] = 'On-Demand Job Failed:  ' . $this->BP->getError();
                        $returnArr[] = $item;
                    }
                }
            }
        } else {
            $returnArr = $deviceName;
        }


        return $returnArr;
    }

    public function backup_now($data, $sid) {
        $backupType = $data['backup_type'];
        $inputParams = array();
        $appType = "";
        $multiFileLevel = false;
        $multiBlockLevel = false;
        if(isset($data['client_id'])) {
            $appType = Constants::APPLICATION_NAME_FILE_LEVEL;
            $backupType = $this->FUNCTIONS->getBackupTypeCoreNameBackupNow($backupType, $appType);
            $inputParams['id'] = $data['client_id'];
        } else if (isset($data['clients']) && !empty($data['clients'])) {
            $multiFileLevel = true;
        } else {
            // Handle instances as objects or ids.
            if (is_array($data['instances'][0])) {
                $instanceInfo = $this->BP->get_appinst_info($data['instances'][0]['id'], $sid);
                $info = $instanceInfo[$data['instances'][0]['id']];
                $inputParams['id'] = $info['client_id'];
                $inputParams['appinst_ids'] = array_map(function($o) { return $o['id']; }, $data['instances']);
                $appType = $info['app_type'];
                $backupType = $this->FUNCTIONS->getBackupTypeCoreNameBackupNow($backupType, $appType);

                if ($appType == Constants::APPLICATION_TYPE_NAME_VMWARE ||
                    $appType == Constants::APPLICATION_TYPE_NAME_XEN ||
                    $appType == Constants::APPLICATION_TYPE_NAME_AHV) {
                    $diskInfo = $this->processVMDKs($data['instances'], $sid, $appType);
                } else if ($appType == Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL) {
                    $multiBlockLevel = true;
                }
            } else {
                $instanceInfo = $this->BP->get_appinst_info($data['instances'][0], $sid);
                $info = $instanceInfo[$data['instances'][0]];
                $inputParams['id'] = $info['client_id'];
                $inputParams['appinst_ids'] = $data['instances'];
                $appType = $info['app_type'];
                $backupType = $this->FUNCTIONS->getBackupTypeCoreNameBackupNow($backupType, $appType);
            }
        }

        if ($multiFileLevel !== false || $multiBlockLevel !== false) {
            $returnArr = $this->backup_now_multi($data, $sid, $appType);
            if($returnArr !== false) {
                return array("data" => $returnArr);
            } else {
                return $returnArr;
            }
        }

        if(isset($data['verify'])) {
            switch($data['verify']) {
                case "none":
                    $inputParams['verify_level'] = 0;
                    break;
                case "inline":
                    $inputParams['verify_level'] = 3;
                    break;
            }
        } else {
            $inputParams['verify_level'] = 3;
        }
        $storageValid = true;
        if(isset($data['storage'])) {
            if(strtolower($data['storage']) != "internal" and $data['storage'] != "") {
                $deviceName = $this->BP->get_device_for_storage($data['storage'], $sid);
                if($deviceName !== false) {
                    $inputParams['dev_name'] = $deviceName;
                } else {
                    $storageValid = false;
                }
            }
        }

     
        if($storageValid) {
            if(isset($data['excl_list'])) {
                $inputParams['excl_list'] = $data['excl_list'];
            }
            if(isset($data['incl_list'])) {
                $inputParams['incl_list'] = $data['incl_list'];
            }
            if(isset($data['metanames'])) {
                $inputParams['metanames'] = $data['metanames'];
            }

            switch($appType) {
                case Constants::APPLICATION_TYPE_NAME_ORACLE:
                case Constants::APPLICATION_TYPE_NAME_SHAREPOINT:
                case Constants::APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE:
                    $status = $this->BP->rae_backup_now($backupType, $inputParams, $sid);
                    break;
                default:
                    if (isset($diskInfo)) {
                        if ($diskInfo !== false) {
                            $status = $this->saveVMDKs($diskInfo, $sid, $appType);
                            if ($status !== false) {
                                $status = $this->BP->backup_now($backupType, $inputParams, $sid);
                            }
                        } else {
                            $status = false;
                        }
                    } else {
                        $status = $this->BP->backup_now($backupType, $inputParams, $sid);
                    }
                    break;
            }
            if($status !== false) {
                $returnArr = array();
                foreach($status as $returnItem) {
                    if(isset($returnItem['appinst_id'])) {
                        $item['instance'] = $returnItem['appinst_id'];
                        $instanceInfo = $this->FUNCTIONS->getInstanceNames($item['instance'], $sid);
                        if($instanceInfo != false) {
                            $item['host_name'] = $instanceInfo['client_name'];
                            $item['instance_name'] = $instanceInfo['asset_name'];
                        }
                    } else {
                        $clientInfo = $this->BP->get_client_info($returnItem['client_id'], $sid);
                        if ($clientInfo != false) {
                            $item['client_name'] = $item['instance_name'] = $clientInfo['name'];
                        }
                    }
                    $item['job_id'] = $returnItem['job_id'];
                    if($item['job_id'] == -1) {
                        $item['message'] = $returnItem['msg'];
                    }
                    $returnArr[] = $item;
                }
                $returnArr = array("data" => $returnArr);
            } else {
                $returnArr = array();
                $item = array();
                $item['message'] = 'On-Demand Job Failed:  ' . $this->BP->getError();
                $returnArr[] = $item;
            }
        } else {
            $returnArr = $deviceName;
        }
        return $returnArr;
    }

    private function gatherDetailedSaveInfo($result, $sid)
    {
        $status = array();

        //loop through and collect each error
        $message = "";
        $separator = "";
        $hasDetails = false;
        foreach ($result as $item) {
            $hasDetails = true;
            if(isset($item['appinst_id'])) {
                $returnItem['instance'] = $item['appinst_id'];
                $instanceInfo = $this->BP->get_appinst_info($item['appinst_id'], $sid);
                if($instanceInfo !== false) {
                    $appName = $instanceInfo[$item['appinst_id']]['app_type'];
                    if ($appName == 'Hyper-V') {
                        $returnItem['host_name'] = $instanceInfo[$item['appinst_id']]['client_name'];
                        $returnItem['instance_name'] = $instanceInfo[$item['appinst_id']]['primary_name'];
                    } else {
                        $returnItem['host_name'] = $instanceInfo[$item['appinst_id']]['primary_name'];
                        if(isset($instanceInfo[$item['appinst_id']]['secondary_name'])) {
                            $returnItem['instance_name'] = $instanceInfo[$item['appinst_id']]['secondary_name'];
                        }
                    }
                }
            }
            $message .= $separator . $item['message'];
            $separator = "\n";
        }
        if ($hasDetails === false) {
            $message = $this->BP->getError();
        }
        $status['error'] = 500;
        $status['message'] = $message;

        return $status;
     }

    private function gatherDetailedEnterpriseSaveInfo($result, $sid)
    {
        $status = array();

        //loop through and collect each error
        $message = "";
        $separator = "";
        $hasDetails = false;
        foreach ($result as $item) {
            $hasDetails = true;
            $client = "";
            if (isset($item['client_id'])) {
                $clientInfo = $this->BP->get_client_info($item['client_id'], $sid);
                if ($clientInfo !== false) {
                    $client = "Server " . $clientInfo['name'] . $client . ":  ";
                }
            }
            $message .= $separator . $client . $item['message'];
            $separator = "\n";
        }
        if ($hasDetails === false) {
            $message = $this->BP->getError();
        }
        $status['error'] = 500;
        $status['message'] = $message;

        return $status;
    }

    /*
     * For each of the instances, get the vm disks.  If more than one disk, one could be excluded.
     * This function builds and returns an array of included and excluded disks for these VMs, or false on error.
     */
    private function processVMDKs($instances, $sid, $diskType = Constants::APPLICATION_TYPE_NAME_VMWARE)
    {
        $diskInfo = array();
        foreach ($instances as $obj) {
            if ( $diskType === Constants::APPLICATION_TYPE_NAME_VMWARE )  {
                $currentDisks = $this->BP->get_vm_disks($obj['id'], true, $sid);
            } elseif ( $diskType === Constants::APPLICATION_TYPE_NAME_XEN ) {
                $currentDisks = $this->BP->get_xen_vm_disks($obj['id'], true, $sid);
            } elseif ( $diskType === Constants::APPLICATION_TYPE_NAME_AHV ) {
                $currentDisks = $this->BP->get_ahv_vm_disks($obj['id'], true, $sid);
            } else {
                $currentDisks = false;
            }
            if ($currentDisks !== false) {
                if (count($currentDisks) > 1) {
                    // We only need to process if the VM has > 1 VMDK.
                    foreach ($currentDisks as $disk) {
                        $newDisk = $disk;
                        if ($diskType === Constants::APPLICATION_TYPE_NAME_XEN || $diskType === Constants::APPLICATION_TYPE_NAME_AHV) {
                            $newDisk['instance_id'] = $obj['id'];
                        }
                        $newDisk['is_excluded'] = $this->diskIsExcluded($disk, $obj['excl_list']);
                        $diskInfo[] = $newDisk;
                    }
                }
            } else {
                global $Log;
                $Log->writeVariable("error with disk list:" . $this->BP->getError());
                $diskInfo = false;
            }
        }
        return $diskInfo;
    }

    /*
     * Returns true if the exclusions are set for this disk, and false otherwise.
     */
    private function diskIsExcluded($disk, $exclusions) {
        $excluded = false;
        foreach ($exclusions as $exclude) {
            if ($exclude === $disk['name']) {
                $excluded = true;
                break;
            }
        }
        return $excluded;
    }

    /*
     * Given the disk information, set the VM instance's disk inclusions and exclusions.
     */
    private function saveVMDKs($diskInfo, $sid, $diskType = Constants::APPLICATION_TYPE_NAME_VMWARE)
    {
        $result = true;
        if ($diskInfo !== false && !empty($diskInfo)) {

            $currentInstance = $diskInfo[0]['instance_id'];
            $newDiskInfo = array();
            for($i=0; $i<count($diskInfo); $i++){
                $entry = $diskInfo[$i];
                if ($entry['instance_id'] == $currentInstance){
                    $newDiskInfo[] = $entry;

                    // edge case -- last one in diskInfo array
                    if ($i === count($diskInfo)-1){
                        $result = $this->saveVMDisks($newDiskInfo, $sid, $diskType);
                    }
                } else {
                    $result = $this->saveVMDisks($newDiskInfo, $sid, $diskType);
                    if ($result === false){
                        break;
                    }

                    $newDiskInfo = array(); // clear this out
                    $newDiskInfo[] = $entry; // add the one that didn't match
                    $currentInstance = $entry['instance_id'];
                }
            }
        }
        return $result;
    }

    private function saveVMDisks($diskInfo, $sid, $diskType = Constants::APPLICATION_TYPE_NAME_VMWARE){
        if ( $diskType == Constants::APPLICATION_TYPE_NAME_XEN ) {
            $result = $this->BP->set_xen_vm_disks($diskInfo, $sid);
        } else if ( $diskType == Constants::APPLICATION_TYPE_NAME_AHV ) {
            $result = $this->BP->set_ahv_vm_disks($diskInfo, $sid);
        } else {
            $result = $this->BP->set_vm_disks($diskInfo, $sid);
        }
        if ($result === false) {
            global $Log;
            $Log->writeVariable("error saving disk list:" . $this->BP->getError());
        }
        return $result;
    }

    /*
     * Called when loading a schedule to return any excluded VMDKs.
     */
    private function getExcludedVMDKs($instance_id, $sid, $diskType = Constants::APPLICATION_TYPE_NAME_VMWARE)
    {
        $excludes = array();
        $disks = array();
        if ( $diskType == Constants::APPLICATION_TYPE_NAME_VMWARE ) {
            $disks = $this->BP->get_vm_disks($instance_id, true, $sid);
        } elseif ( $diskType == Constants::APPLICATION_TYPE_NAME_XEN ) {
            $disks = $this->BP->get_xen_vm_disks($instance_id, true, $sid);
        } elseif ( $diskType == Constants::APPLICATION_TYPE_NAME_AHV ) {
            $disks = $this->BP->get_ahv_vm_disks($instance_id, true, $sid);
        }
        if ($disks !== false) {
            foreach ($disks as $disk) {
                if ($disk['is_excluded'] === true) {
                    $excludes[] = $disk['name'];
                }
            }
        } else {
            global $Log;
            $Log->writeVariable("error getting disk list:" . $this->BP->getError());
        }
        return $excludes;
    }

    /*
     * Prepare the regular expression array for a save, which is different that what is received on a get.
     * This function converts between the get to the format required for save.
     * If no regular expressions are specified, an empty array is returned.
     */
    private function buildRegex($data) {
        $return = array();
        if (isset($data['regular_expressions'])) {
            // Convert fields.
            foreach ($data['regular_expressions'] as $regex) {
                if (!isset($regex['id']) && isset($regex['container_id'])) {
                    $regex['id'] = $regex['container_id'];
                    unset($regex['container_id']);
                }
                if (!isset($regex['action']) && isset($regex['action_id'])) {
                    $regex['action'] = $regex['action_id'];
                    unset($regex['action_id']);
                }
                $return[] = $regex;
            }
        }
        return $return;
    }
    
    private function sameDeltas($values) {
        $savedDelta = 0;
        $same = true;
        if (count($values) > 1) {
            for ($i = count($values) - 1; $i > 0; $i--) {
                $thisDelta = $values[$i] - $values[$i - 1];
                if ($savedDelta == 0) {
                    $savedDelta = $thisDelta;
                } else if ($thisDelta != $savedDelta) {
                    $same = false;
                    break;
                } else {
                    $savedDelta = $thisDelta;
                }
            }           
        }
        return $same;
    }

    private function isGrandClient($clientID, $localID, $sid) {
        $result = false;
        if ($sid === $localID) {
            $info = $this->BP->get_client_info($clientID, $localID);
            if ($info !== false) {
                $result = $info['grandclient'];
            } else {
                global $Log;
                $Log->writeError("cannot get client info:", true);
            }
        }
        return $result;
    }
}

?>
