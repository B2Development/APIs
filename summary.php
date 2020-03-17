<?php

class Summary
{
    private $BP;

    const CRITICAL = 'critical';
    const WARNING = 'warning';
    const NOTICE = 'notice';

    public function __construct($BP, $sid) {
        $this->BP = $BP;
        $this->now = time();
        $this->sid = $sid;

        $this->totalBackupErrors = 0;
        $this->totalBackupNotProtected = 0;
        $this->totalBackupProtected = 0;
        $this->totalBackupProtected_RPO = 0;
        $this->totalBackupRPODays = 0;

        $this->totalReplicationErrors = 0;
        $this->totalReplicationProtected = 0;
        $this->totalReplicatedBackupErrors = 0;
        $this->totalReplicatedBackupProtected = 0;
        $this->totalReplicatedBackupNotProtected = 0;

        $this->totalRestoreActive = 0;
        $this->totalRestoreActiveFLR = 0;
        $this->totalRestoreActiveIR = 0;
        $this->totalRestoreRecent = 0;

        $this->totalArchiveErrors = 0;
        $this->totalArchiveProtected = 0;

        $this->totalWIRErrors = 0;
        $this->totalWIRProtected = 0;

        $this->totalReplicaErrors = 0;
        $this->totalReplicaProtected = 0;

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);
    }
 
    public function get($which, $filter) {
        $sid = $this->sid;
        $data = array();
        switch ($which){
            case "current":
                if ($sid != false) {
                    $summaryCurrent = $this->BP->get_summary_current($sid);
                    if ($summaryCurrent !== false) {
                        $data = $this->buildOutput($summaryCurrent);
                    } else {
                        $data = false;
                    }
                } else {
                    $systems = $this->functions->selectSystems( false, false);
                    $alertCount = 0;

                    foreach ($systems as $sid => $name) {
                        $summaryCurrent = $this->BP->get_summary_current($sid);
                        if ($summaryCurrent !== false) {
                            $sev[] = isset($summaryCurrent['max_severity']) ? $summaryCurrent['max_severity'] : null;
                            $risk[] = isset($summaryCurrent['at_risk_alert']) ? $summaryCurrent['at_risk_alert'] : null;
                            $alertCount += $summaryCurrent['alert_count'];
                            $data = $this->buildOutput($summaryCurrent);
                            $data['alert_count'] = $alertCount;
                        } else {
                            continue;
                        }
                    }
                    if($data != false and $alertCount > 0){
                        $data['max_severity'] = $this->getMostMaxSeverity($sev);
                        $data['at_risk_alert'] = $this->getRiskAlert($risk);
                    }

                }
                break;
            case "counts" :
                if ($sid !== false) {
                    $summaryCounts = $this->BP->get_summary_counts($filter, $sid);
                    // If the call fails, return false so that buildResult will get the error message.
                    if ($summaryCounts !== false) {
                        $data = $this->buildCountsOutput($summaryCounts, $filter, $sid);
                    } else {
                        $data = false;
                    }
                } else {
                    $systems = $this->functions->selectSystems();
                    // return rpo days of the local appliance in a management grid
                    $localID = $this->BP->get_local_system_id();
                    foreach ($systems as $sid => $name) {
                        $summaryCounts = $this->BP->get_summary_counts($filter, $sid);
                        if ($sid === $localID) {
                            $rpoDays = isset($summaryCounts['rpo_days']) ? $summaryCounts['rpo_days'] : null;
                        } else {
                            $rpoDays = null;
                        }
                        if ($summaryCounts !== false) {
                            $data = $this->buildCountsOutput($summaryCounts, $filter, $sid, $rpoDays);
                        } else {
                            continue;
                        }
                    }
                }
				break;
            case "days" :
                $subsystem = $filter;
                $days = isset($_GET['days']) ? $_GET['days'] : 7;
                $summaryDays = $this->BP->get_summary_days($subsystem, $days, $sid);
                if ($summaryDays !== false) {
                    foreach ($summaryDays as $id => $day){
                        $data['data'][] = $this->buildDaysOutput($id, $day);
                    }
                } else {
                    $data = false;
                }
                break;

            case "target-days" :
                $subsystem = $filter;
                $days = isset($_GET['days']) ? $_GET['days'] : 7;
                $summaryDays = $this->BP->get_target_summary_days($subsystem, $days, $sid);
                if ($summaryDays !== false) {
                    $i = 1;
                    foreach ($summaryDays as $replicatingSources){
                        $daysData = array();
                        $sourceKey = "source." . $i++;
                        foreach ($replicatingSources as $id => $day) {
                            if ($id === 'source') {
                                $sourceKey = $day;
                            } else {
                                $daysData[] = $this->buildDaysOutput($id, $day);
                            }
                        }
                        $data['data'][$sourceKey] = $daysData;
                    }
                } else {
                    $data = false;
                }
                break;

            case "status":
                /*
                 *  Get summary status information for backups or hot backup copies.  Options:
                 *      # days (optional), defaults to 7
                 *      end time epoch (optional), default to now
                 *      sid for remote execution, default is local
                 *      cid, client ID filter (all if not specified, must be specified if iid is)
                 *      iid, instance ID filter (not used by default)
                 *      sync, 1 for replication (hot copy) status, 0 (default) for backup status.
                 *      grandclient, true to get grandclient (target) backup status, default is false
                 */
                $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
                $endTime = isset($_GET['end']) ? (int)$_GET['end'] : time();
                $totalsOnly = isset($_GET['totals']) ? ($_GET['totals'] == '1') : false;
                $intervals = $this->generateIntervals($days, $endTime, $totalsOnly);

                $localID = $this->BP->get_local_system_id();
                $sid = isset($_GET['sid']) ? (int)$_GET['sid'] : $localID;
                $cid = isset($_GET['cid']) ? (int)$_GET['cid'] : -1;
                $iid = isset($_GET['iid']) ? (int)$_GET['iid'] : -1;
                $sync = isset($_GET['sync']) ? ($_GET['sync'] == '1') : false;
                $grandClientView = isset($_GET['grandclient']) && $_GET['grandclient'] === "true";
                if ($grandClientView) {
                    $sid = $localID;
                }
                $result = $this->getStatusSummary($intervals, $sid, $grandClientView, $localID, $cid, $iid, $sync);
                if ($result !== false) {
                    // For replicated backups, we need to get the grandclient list, otherwise, get the client list.
                    // This allows us to associate client names with the summaries.
                    // If an instance were specified, get its name.
                    $clients = $grandClientView ? $this->BP->get_grandclient_list($sid) : $this->BP->get_client_list($sid);
                    $instances = ($iid != -1) ? $this->functions->getInstanceNames($iid, $sid) : array();
                    $data = array();
                    $i = 0;
                    foreach ($result as $day) {
                        $startTime = $intervals[$i++]['start_time'];
                        if ($totalsOnly) {
                            $day = $this->buildDayTotals($day);
                        } else {
                            $this->buildDayStatus($clients, $instances, $day);
                        }
                        $obj = array('start_time' => $startTime, 'date' => date('M d Y', $startTime));
                        $obj['day'] = $day;
                        $data['days'][] = $obj;
                    }
                } else {
                    $data = false;
                }
                break;
        }
        return $data;
    }

    function buildOutput($summary) {
        $alerts = isset($summary['alert_count']) ? $summary['alert_count'] : 0;
        // Don't return time of a sync unless there has been one (sync_last > 0)).
        $syncLast = isset($summary['sync_last']) && $summary['sync_last'] > 0 ? $this->functions->formatDateTime($summary['sync_last']) : null;
        $syncProgress = isset($summary['sync_progress']) ? $summary['sync_progress'] : 0;
        $syncRunning = isset($summary['sync_running']) ? $summary['sync_running'] : false;
        $syncStatus = isset($summary['sync_status']) ? $summary['sync_status'] : 'n/a';
        $maxSeverity = isset($summary['max_severity']) ? $summary['max_severity'] : false;
        $atRiskAlert = isset($summary['at_risk_alert'])? $summary['at_risk_alert']: NULL;
        // Only return a message if there is one.
        $message = isset($summary['message']) && $summary['message'] != '(No message available.)' ? $summary['message'] : false;

        $data = array(
            'alert_count' => $alerts,
            'sync_last' => $syncLast,
            'sync_progress' => $syncProgress,
            'sync_running' => $syncRunning,
            'sync_status' => $syncStatus,
            'max_severity' => $maxSeverity,
            'message' => $message,
            'at_risk_alert' => $atRiskAlert
        );

        return $data;
    }

    function getMostMaxSeverity($sev) {
        $values = array();
        //map strings to ints
        foreach($sev as $item => $severity){
            switch($severity){
                case 'critical':
                    $values[] = $value = 3;
                    break;
                case 'warning':
                    $values[] = $value = 2;
                    break;
                case 'notice':
                    $values[] = $value = 1;
                    break;
            }
        }

        //get the max and map the int back to a string
        $max = max($values);

        switch($max){
            case 3:
                $result = 'critical';
                break;
            case 2:
                $result = 'warning';
                break;
            case 1:
                $result = 'notice';
                break;
        }

        return $result;
    }

    function getRiskAlert($risk){
        $result = false;
        foreach($risk as $riskAlert ){
            if ($riskAlert == true){
                $result = true;
                break;
            }
        }
        return $result;
    }

    function buildCountsOutput($summary, $filter, $sid, $rpoDays = null) {
        require_once('function.lib.php');
        $functions = new Functions($this->BP);

        // if the API failed, $data is false.
        if ($summary === false) {
            $data = $summary;
            return;
        }

        $backupErrors = isset($summary['backup_errors']) ? $summary['backup_errors'] : null;
        $backupNotProtected = isset($summary['backup_not_protected']) ? $summary['backup_not_protected'] : null;
        $backupProtected = isset($summary['backup_protected']) ? $summary['backup_protected'] : null;
        $backupProtected_RPO = isset($summary['rpo_protected']) ? $summary['rpo_protected'] : 0;  //recovery point objective - the instances with backups in the last $backupRPODays
        $backupRPODays = isset($summary['rpo_days']) ? $summary['rpo_days'] : null;
        $replicationErrors = isset($summary['replication_errors']) ? $summary['replication_errors'] : null;
        $replicationProtected = isset($summary['replication_protected']) ? $summary['replication_protected'] : null;
        $replicatedBackupErrors = isset($summary['replicated_backup_errors']) ? $summary['replicated_backup_errors'] : null;
        $replicatedBackupProtected = isset($summary['replicated_backup_protected']) ? $summary['replicated_backup_protected'] : null;
        $replicatedBackupNotProtected = isset($summary['replicated_backup_not_protected']) ? $summary['replicated_backup_not_protected'] : null;
        $restoreActive = isset($summary['restore_active']) ? $summary['restore_active'] : null;
        $restoreActiveFLR = isset($summary['restore_active_flr']) ? $summary['restore_active_flr'] : null;
        $restoreActiveIR = isset($summary['restore_active_ir']) ? $summary['restore_active_ir'] : null;
        $restoreRecent = isset($summary['restore_recent']) ? $summary['restore_recent'] : null;
        $archiveErrors = isset($summary['archive_errors']) ? $summary['archive_errors'] : null;
        $archiveProtected = isset($summary['archive_protected']) ? $summary['archive_protected'] : null;
        $WIRErrors = isset($summary['wir_errors']) ? $summary['wir_errors'] : null;
        $WIRProtected = isset($summary['wir_protected']) ? $summary['wir_protected'] : null;
        $ReplicaErrors = isset($summary['replica_errors']) ? $summary['replica_errors'] : null;
        $ReplicaProtected = isset($summary['replica_protected']) ? $summary['replica_protected'] : null;

        $last_update = isset($summary['last_update']) ? date($functions::DATE_TIME_FORMAT_US, $summary['last_update']) : null;

        $data = array();

        $this->addToBackupCounts($backupErrors, $backupNotProtected, $backupProtected, $backupProtected_RPO);
        $this->addToReplicationCounts($replicationErrors, $replicationProtected, $replicatedBackupErrors, $replicatedBackupProtected, $replicatedBackupNotProtected);
        $this->addToRestoreCounts($restoreActive, $restoreActiveFLR, $restoreActiveIR, $restoreRecent);
        $this->addToArchiveCounts($archiveErrors, $archiveProtected);
        $this->addToWIRCounts($WIRErrors, $WIRProtected);
        $this->addToReplicaCounts($ReplicaErrors, $ReplicaProtected);

        if ($rpoDays !== null) {
            $this->totalBackupRPODays = $rpoDays;
        } else {
            $this->totalBackupRPODays = $backupRPODays;
        }

        $backup = array(
            'backup_errors' => $this->totalBackupErrors, //$backupErrors,
            'backup_not_protected' =>($this->totalBackupNotProtected+$this->totalBackupProtected-$this->totalBackupProtected_RPO), //UNIBP-6122 - Changing numbers until core work associated with UNIBP-6219 is in
            'backup_protected' => $this->totalBackupProtected_RPO,  //UNIBP-6122 - Changing numbers until core work associated with UNIBP-6219 is in
            'rpo_protected' => $this->totalBackupProtected_RPO,
            'rpo_days' => $this->totalBackupRPODays,
            'backup_not_protected-not_adjusted' =>$this->totalBackupNotProtected, //UNIBP-6122 - Changing numbers until core work associated with UNIBP-6219 is in
            'backup_protected-not_adjusted' => $this->totalBackupProtected //UNIBP-6122 - Changing numbers until core work associated with UNIBP-6219 is in
        );
        $replication = array(
            'replication_errors' => $this->totalReplicationErrors,
            'replication_protected' => $this->totalReplicationProtected
        );
        $replicatedBackup = array(
            'replicated_backup_errors' => $this->totalReplicatedBackupErrors,
            'replicated_backup_protected' => $this->totalReplicatedBackupProtected,
            'replicated_backup_not_protected' => $this->totalReplicatedBackupNotProtected
        );
        $restore = array(
            'restore_active' => $this->totalRestoreActive,
            'restore_active_flr' => $this->totalRestoreActiveFLR,
            'restore_active_ir' => $this->totalRestoreActiveIR,
            'restore_recent' => $this->totalRestoreRecent
        );
        $archive = array(
            'archive_errors' => $this->totalArchiveErrors,
            'archive_protected' => $this->totalArchiveProtected
        );
        $wir = array(
            'wir_errors' => $this->totalWIRErrors,
            'wir_protected' => $this->totalWIRProtected
        );
        $replica = array(
            'replica_errors' => $this->totalReplicaErrors,
            'replica_protected' => $this->totalReplicaProtected
        );

        if ($last_update != null) {
            $lastUpdated = array(
                'last_updated' => $last_update
            );
        } else {
            $lastUpdated = array();
        }

        switch($filter){
            case 'backup':
                $data = array_merge($backup, $lastUpdated);
                break;
            case 'replication':
                $data = array_merge($replication, $lastUpdated);
                break;
            case 'replicated_backup':
                $data = array_merge($replicatedBackup, $lastUpdated);
                break;
            case 'restore':
                $data = array_merge($restore, $lastUpdated);
                break;
            case 'archive':
                $data = array_merge($archive, $lastUpdated);
                break;
            case 'wir':
                $data = array_merge($wir, $lastUpdated);
                break;
            case 'replicas':
                $data = array_merge($replica, $lastUpdated);
                break;
            case null:
                $data = array_merge($backup, $replication, $replicatedBackup, $restore, $archive, $wir, $replica, $lastUpdated);
                break;
            default:
                $data = "The filter '" . $filter . "' is not valid.";
                break;
        }

        return $data;
    }

    function buildDaysOutput($id, $day){
        $data = array();

        if ($id === false) {
            $data = $id;
            return $data;
        }

        if (is_array($day)) {
            foreach ($day as $d) {
                $avgSpeed = isset($d['avg_speed']) ? round($d['avg_speed'],2) : null;
                $units = isset($d['units']) ? $d['units'] : null;
                $avgTime = isset($d['avg_time']) ? $d['avg_time'] : null;
                $singleDay = isset($d['day']) ? $d['day'] : null;
                $read = isset($d['read']) ? round($d['read'],2) : null;
                $write = isset($d['write']) ? round($d['write'], 2) : null;
                $compress = isset($d['compress']) ? round($d['compress'], 2) : null;
                $avgStartLag = isset($d['avg_start_lag']) ? round($d['avg_start_lag'], 2) : null;
                $avgProtectionGap = isset($d['avg_prot_gap']) ? round($d['avg_prot_gap'], 2) : null;
                $successes = isset($d['successes']) ? $d['successes'] : null;
                $retries = isset($d['retries']) ? $d['retries'] : null;

                $backupSpeed = array(
                    'avg_speed' => $avgSpeed,
                    'units' => $units,
                    'avg_time' => $avgTime,
                    'day' => $singleDay
                );

                $backupSize = array(
                    'read' => $read,
                    'write' => $write,
                    'compress' => $compress,
                    'units' => $units,
                    'day' => $singleDay
                );

                $backupDataSpeed = array(
                    'read' => $read,
                    'write' => $write,
                    'compress' => $compress,
                    'units' => $units,
                    'day' => $singleDay
                );

                $replicationSpeed = array(
                    'read' => $read,
                    'write' => $write,
                    'units' => $units,
                    'day' => $singleDay
                );

                $replicationSize = array(
                    'read' => $read,
                    'write' => $write,
                    'units' => $units,
                    'day' => $singleDay
                );

                $replicationMetrics = array(
                    'start_lag' => $avgStartLag,
                    'protection_gap' => $avgProtectionGap,
                    'units' => $units,
                    'day' => $singleDay
                );

                $replicationStatistics = array(
                    'successes' => $successes,
                    'retries' => $retries,
                    'day' => $singleDay
                );

                $restoreSpeed = array(
                    'avg_speed' => $avgSpeed,
                    'units' => $units,
                    'avg_time' => $avgTime,
                    'day' => $singleDay
                );

                $archiveSpeed = array(
                    'avg_speed' => $avgSpeed,
                    'units' => $units,
                    'avg_time' => $avgTime,
                    'day' => $singleDay
                );

                $archiveSize = array(
                    'write' => $write,
                    'units' => $units,
                    'day' => $singleDay
                );

                switch ($id) {
                    case 'backup_speed':
                        $data[$id][] = $backupSpeed;
                        break;
                    case 'backup_size':
                        $data[$id][] = $backupSize;
                        break;
                    case 'backup_data_speed':
                        $data[$id][] = $backupDataSpeed;
                        break;
                    case 'replication_speed':
                    case 'replicating_speed':
                        $data[$id][] = $replicationSpeed;
                        break;
                    case 'replication_size':
                    case 'replicating_size':
                        $data[$id][] = $replicationSize;
                        break;
                    case 'replication_metrics':
                    case 'replicating_metrics':
                        $data[$id][] = $replicationMetrics;
                        break;
                    case 'replication_stats':
                    case 'replicating_stats':
                        $data[$id][] = $replicationStatistics;
                        break;
                    case 'restore_speed':
                        $data[$id][] = $restoreSpeed;
                        break;
                    case 'archive_speed':
                        $data[$id][] = $archiveSpeed;
                        break;
                    case 'archive_size':
                        $data[$id][] = $archiveSize;
                        break;
                }
            }
        }

        return $data;
    }

    public function put($which, $data)
    {
        $sid = $this->sid;
        $result = array();
        switch($which) {
            case "counts":
                $result = $this->refresh_counts($data, $sid);
                break;
        }
        return $result;
    }

    private function refresh_counts($data, $sid)
    {
        $time_last_updated = isset($data['time_updated']) ? strtotime($data['time_updated']) : null;
        $summary_counts = $this->BP->get_summary_counts(null, $sid);
        if ($summary_counts != false) {
            // if last updated time from rest_summary_counts is more recent than the 'time_updates' passed, do not refresh
            if (isset($summary_counts['last_update']) && $time_last_updated && ($summary_counts['last_update'] > $time_last_updated)) {
                // don't refresh
                $refresh = true;
            } else {
                $refresh = $this->BP->refresh_summary_counts(NULL, $sid);
            }
        }
        return $refresh;
    }

    function addToBackupCounts($backupErrors, $backupNotProtected, $backupProtected, $backupProtected_RPO){
        $this->totalBackupErrors += $backupErrors;
        $this->totalBackupNotProtected += $backupNotProtected;
        $this->totalBackupProtected += $backupProtected;
        $this->totalBackupProtected_RPO += $backupProtected_RPO;
    }

    function addToReplicationCounts($replicationErrors, $replicationProtected, $replicatedBackupErrors, $replicatedBackupProtected, $replicatedBackupNotProtected){
        $this->totalReplicationErrors += $replicationErrors;
        $this->totalReplicationProtected += $replicationProtected;
        $this->totalReplicatedBackupErrors += $replicatedBackupErrors;
        $this->totalReplicatedBackupProtected += $replicatedBackupProtected;
        $this->totalReplicatedBackupNotProtected += $replicatedBackupNotProtected;
    }

    function addToRestoreCounts($restoreActive, $restoreActiveFLR, $restoreActiveIR, $restoreRecent){
        $this->totalRestoreActive += $restoreActive;
        $this->totalRestoreActiveFLR += $restoreActiveFLR;
        $this->totalRestoreActiveIR += $restoreActiveIR;
        $this->totalRestoreRecent += $restoreRecent;
    }

    function addToArchiveCounts($archiveErrors, $archiveProtected){
        $this->totalArchiveErrors += $archiveErrors;
        $this->totalArchiveProtected += $archiveProtected;
    }

    function addToWIRCounts($WIRErrors, $WIRProtected){
        $this->totalWIRErrors += $WIRErrors;
        $this->totalWIRProtected += $WIRProtected;
    }

    function addToReplicaCounts($replicaErrors, $replicaProtected){
        $this->totalReplicaErrors += $replicaErrors;
        $this->totalReplicaProtected += $replicaProtected;
    }

    /*
     * Given a number of total days and an end time, generates the intervals to be passed to bp_get_backup_summary.
     */
    function generateIntervals($totalDays, $endTime, $totalsOnly = false) {
        $intervals = array();
        if ($totalsOnly) {
            // a single interval
            $intervals[0]["end_time"] = $endTime;
            $intervals[0]['start_time'] = $endTime - ($totalDays * 86400);
        } else {
            // an interval per day
            $endDate = strtotime(date("Y-m-d", $endTime));
            for ($i = 0, $days = $totalDays - 1; $i < $totalDays; $i++, $days--) {
                $start = strtotime("-".$days." day", $endDate);
                $intervals[$i] = array("start_time" => $start);
                if ($i != 0) {
                    $intervals[$i - 1]["end_time"] = $start - 1;
                }
            }
            $intervals[$totalDays - 1]["end_time"] = $endTime;
        }
        return($intervals);
    }

    /*
     * returns the output of bp_get_backup_summary for the specified days and intervals.
     * If sync is true, gets replicated backups (hot backup copies)
     * If grandclientView is true, gets target status information.
     */
    function getStatusSummary($intervals, $sid, $grandClientView, $localID, $cid = -1, $iid = -1, $sync = false) {
        $result_format = array();
        $result_format['intervals'] = $intervals;
        $result_format['grandclients'] = $grandClientView;
        $result_format['sync_types'] = $sync;
        if ($cid != -1) {
            // Optional client ID filter
            $result_format['client_id'] = $cid;
        }
        if ($iid != -1) {
            // Optional instance id filter
            $result_format['instance_id'] = $iid;
        }
        $result = $this->BP->get_backup_summary($result_format, $sid);
        return $result;
    }

    function buildDayStatus($clients, $instances, &$day) {
        foreach ($day as &$client) {
            $client['client_name'] = $clients[$client['client_id']];
            if (is_array($instances) && count($instances) > 0) {
                $client['asset_name'] = $instances['asset_name'];
            }
            $client['id'] = $client['client_id'];
            unset($client['client_id']);
        }
    }

    function buildDayTotals($day) {
        $successes = $warnings = $failures = $inprogress = 0;
        foreach ($day as $client) {
            $successes += $client['successes'];
            $warnings += $client['warnings'];
            $failures += $client['failures'];
            $inprogress += $client['in_progress'];
        }
        return array('successes' => $successes, 'warnings' => $warnings, 'failures' => $failures, 'in_progress' => $inprogress);
    }

} // end Summary

?>
