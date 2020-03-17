<?php

//IN PROGRESS

$dir = '/api/includes/';
require_once('./includes/csv.php');

class Reports
{
    private $BP;
    private $RDR;

    public function __construct($BP)
    {
        $this->BP = $BP;

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);

        $this->RDR = new RDR($BP);
    }

    public function get($which, $data, $sid, $systems)
    {
        $returnReport = array();

        if ($which == -1)
        {
            $returnReport = $this->get_reports( true, true, true, true, true, true, true, true);
        }
        else if (is_string($which[0]))
        {
            switch ($which[0])
            {
                case 'archive':
                    if (isset($which[1]) and is_string($which[1]))
                    {
                        switch ($which[1])
                        {
                            case "history":
                                require_once('reports/archive-history-report.php');
                                $archiveHistoryReport = new ArchiveHistoryReport( $this->BP, $this->functions );
                                $returnReport = $archiveHistoryReport->get_archive_history_report($systems, $data);
                                break;
                            case 'protection_summary':
                                require_once('reports/protection-summary-report.php');
                                $protectionSummaryReport = new ProtectionSummaryReport( $this->BP, $this->functions );
                                $returnReport = $protectionSummaryReport->get_protection_summary_report($systems, $data);
                                break;
                            case 'list':
                            default:
                                $returnReport = $this->get_reports( true );
                                break;
                        }
                    }
                    else
                    {
                        $returnReport = $this->get_reports( true );
                    }
                    break;
                case "archive_history":
                    require_once('reports/archive-history-report.php');
                    $archiveHistoryReport = new ArchiveHistoryReport( $this->BP, $this->functions );
                    $returnReport = $archiveHistoryReport->get_archive_history_report($systems, $data);
                    break;
                case 'backup':
                    if (isset($which[1]) and is_string($which[1]))
                    {
                        switch ($which[1])
                        {
                            case "history":
                                require_once('reports/bp-get-backup-status-reports.php');
                                $bpGetBackupStatusReports = new bpGetBackupStatusReports( $this->BP, $this->functions );
                                $returnReport = $bpGetBackupStatusReports->get_backup_history_report($systems, $data);
                                break;
                            case "failure":
                                require_once('reports/bp-get-backup-status-reports.php');
                                $bpGetBackupStatusReports = new bpGetBackupStatusReports( $this->BP, $this->functions );
                                $returnReport = $bpGetBackupStatusReports->get_backup_history_report($systems, $data,
                                                        array('status' => Constants::STATUS_FAILURE));
                                break;
                            case 'legal_hold':
                                require_once('reports/legal-hold-report.php');
                                $legalHoldReport = new LegalHoldReport( $this->BP, $this->functions );
                                $returnReport = $legalHoldReport->get_legal_hold_report($systems, $data);
                                break;
                            case 'protection_summary':
                                require_once('reports/protection-summary-report.php');
                                $protectionSummaryReport = new ProtectionSummaryReport( $this->BP, $this->functions );
                                $returnReport = $protectionSummaryReport->get_protection_summary_report($systems, $data);
                                break;
                            case "status":
                                require_once('inventory.php');
                                $allStatus = array();
                                // Get status for all systems passed in.
                                foreach ($systems as $system_id => $system_name) {
                                    $inventory = new Inventory($this->BP, $system_id);
                                    $inventoryStatus = $inventory->getInventoryStatus($data, $system_id, true);
                                    // Add system_name to the returned status elements.
                                    array_walk($inventoryStatus, function(&$value, &$key, $system_name) { $value['system_name'] = $system_name; }, $system_name);
                                    $allStatus = array_merge($allStatus, $inventoryStatus);
                                }
                                $returnReport['data'] = $allStatus;
                                break;
                            case "policies":
                                require_once('reports/policies.php');
                                $policiesReport = new PoliciesReport( $this->BP, $this->functions );
                                $returnReport = $policiesReport->get_jobs_for_all_assets($systems);
                                break;
                            case 'list':
                            default:
                                $returnReport = $this->get_reports( false, true );
                                break;
                        }
                    }
                    else
                    {
                        $returnReport = $this->get_reports( false, true );
                    }
                    break;
                case 'backup_history':
                    require_once('reports/bp-get-backup-status-reports.php');
                    $bpGetBackupStatusReports = new bpGetBackupStatusReports( $this->BP, $this->functions );
                    $returnReport = $bpGetBackupStatusReports->get_backup_history_report($systems, $data);
                    break;
                case 'backup_failure':
                    require_once('reports/bp-get-backup-status-reports.php');
                    $bpGetBackupStatusReports = new bpGetBackupStatusReports( $this->BP, $this->functions );
                    $returnReport = $bpGetBackupStatusReports->get_backup_history_report($systems, $data,
                                            array('status' => Constants::STATUS_FAILURE));
                    break;
                case 'legal_hold':
                    require_once('reports/legal-hold-report.php');
                    $legalHoldReport = new LegalHoldReport( $this->BP, $this->functions );
                    $returnReport = $legalHoldReport->get_legal_hold_report($systems, $data);
                    break;
                case 'protection_summary':
                    require_once('reports/protection-summary-report.php');
                    $protectionSummaryReport = new ProtectionSummaryReport( $this->BP, $this->functions );
                    $returnReport = $protectionSummaryReport->get_protection_summary_report($systems, $data);
                    break;
                case 'replication':
                    if (isset($which[1]) and is_string($which[1]))
                    {
                        switch ($which[1])
                        {
                            case "activity":
                                require_once('reports/replication-activity-report.php');
                                $replicationActivityReport = new ReplicationActivityReport( $this->BP, $this->functions );
                                $returnReport = $replicationActivityReport->get_replication_activity_report($sid, $data);
                                break;
                            case "capacity":
                            case "target-capacity":
                                require_once('reports/replication-capacity-report.php');
                                $replicationCapacityReport = new ReplicationCapacityReport( $this->BP, $this->functions );
                                // This report does not loop over local systems, so $sid is needed instead of $systems
                                $returnReport = $replicationCapacityReport->get_replication_capacity_report($which[1], $sid, $data);
                                break;
                            case "footprint":
                                require_once('reports/replication-footprint-report.php');
                                $replicationFootprintReport = new ReplicationFootprintReport( $this->BP, $this->functions );
                                $returnReport = $replicationFootprintReport->get_replication_footprint_report($sid, $data);
                                break;
                            case "history":
                                require_once('reports/bp-get-backup-status-reports.php');
                                $bpGetBackupStatusReports = new bpGetBackupStatusReports( $this->BP, $this->functions );
                                $returnReport = $bpGetBackupStatusReports->get_replication_history($systems, $data);
                                break;
                            case 'protection_summary':
                                require_once('reports/protection-summary-report.php');
                                $protectionSummaryReport = new ProtectionSummaryReport( $this->BP, $this->functions );
                                $returnReport = $protectionSummaryReport->get_protection_summary_report($systems, $data);
                                break;
                            case "source_history":
                                require_once('reports/bp-get-backup-status-reports.php');
                                $bpGetBackupStatusReports = new bpGetBackupStatusReports( $this->BP, $this->functions );
                                $returnReport = $bpGetBackupStatusReports->get_replication_source_history($systems, $data);
                                break;
                            case "target_history":
                                require_once('reports/bp-get-backup-status-reports.php');
                                $bpGetBackupStatusReports = new bpGetBackupStatusReports( $this->BP, $this->functions );
                                $returnReport = $bpGetBackupStatusReports->get_replication_target_history($systems, $data);
                                break;
                            case 'list':
                            default:
                                $returnReport = $this->get_reports( false, false, true );
                                break;
                        }
                    }
                    else
                    {
                        $returnReport = $this->get_reports( false, false, true );
                    }
                    break;
                case "replication_activity":
                    require_once('reports/replication-activity-report.php');
                    $replicationActivityReport = new ReplicationActivityReport( $this->BP, $this->functions );
                    $returnReport = $replicationActivityReport->get_replication_activity_report($sid, $data);
                    break;
                case "replication_capacity":
                    require_once('reports/replication-capacity-report.php');
                    $replicationCapacityReport = new ReplicationCapacityReport( $this->BP, $this->functions );
                    // This report does not loop over local systems, so $sid is needed instead of $systems
                    $returnReport = $replicationCapacityReport->get_replication_capacity_report($sid, $data);
                    break;
                case "replication_footprint":
                    require_once('reports/replication-footprint-report.php');
                    $replicationFootprintReport = new ReplicationFootprintReport( $this->BP, $this->functions );
                    $returnReport = $replicationFootprintReport->get_replication_footprint_report($sid, $data);
                    break;
                case 'replication_history':
                    require_once('reports/bp-get-backup-status-reports.php');
                    $bpGetBackupStatusReports = new bpGetBackupStatusReports( $this->BP, $this->functions );
                    $returnReport = $bpGetBackupStatusReports->get_replication_history($systems, $data);
                    break;
                case "replication_source_history":
                    require_once('reports/bp-get-backup-status-reports.php');
                    $bpGetBackupStatusReports = new bpGetBackupStatusReports( $this->BP, $this->functions );
                    $returnReport = $bpGetBackupStatusReports->get_replication_source_history($systems, $data);
                    break;
                case "replication_target_history":
                    require_once('reports/bp-get-backup-status-reports.php');
                    $bpGetBackupStatusReports = new bpGetBackupStatusReports( $this->BP, $this->functions );
                    $returnReport = $bpGetBackupStatusReports->get_replication_target_history($systems, $data);
                    break;
                case 'restore':
                    if (isset($which[1]) and is_string($which[1]))
                    {
                        switch ($which[1])
                        {
                            case "history":
                                require_once('reports/bp-get-backup-status-reports.php');
                                $bpGetBackupStatusReports = new bpGetBackupStatusReports( $this->BP, $this->functions );
                                $returnReport = $bpGetBackupStatusReports->get_restore_history_report($systems, $data);
                                break;
                            case 'list':
                            default:
                                $returnReport = $this->get_reports( false, false, false, true );
                                break;
                        }
                    }
                    else
                    {
                        $returnReport = $this->get_reports( false, false, false, true );
                    }
                    break;
                case "restore_history":
                    require_once('reports/bp-get-backup-status-reports.php');
                    $bpGetBackupStatusReports = new bpGetBackupStatusReports( $this->BP, $this->functions );
                    $returnReport = $bpGetBackupStatusReports->get_restore_history_report($systems, $data);
                    break;
                case "retention":
                    if (isset($which[1]) and is_string($which[1]))
                    {
                        switch ($which[1])
                        {
                            case "ltr":
                                require_once('reports/retention-report.php');
                                $retention = new RetentionReport( $this->BP, $this->functions );
                                $returnReport = $retention->get_retention($systems);
                                break;
                            case "minmax":
                                require_once('reports/retention-report.php');
                                $retention = new RetentionReport( $this->BP, $this->functions );
                                $returnReport = $retention->get_minmax_retention($systems);
                                break;
                            case 'list':
                            default:
                                $returnReport = $this->get_reports( false, false, false, false, false, false, false, true );
                                break;
                        }
                    }
                    else
                    {
                        $returnReport = $this->get_reports( false, false, false, false, false, false, false, true );
                    }
                    break;
                case 'storage':
                    if (isset($which[1]) and is_string($which[1]))
                    {
                        switch ($which[1])
                        {
                            case "data_reduction":
                                require_once('reports/data-reduction-report.php');
                                $dataReductionReport = new DataReductionReport( $this->BP, $this->functions );
                                $returnReport = $dataReductionReport->get_data_reduction_report($systems, $data);
                                break;
                            case "status":
                                require_once('reports/storage-status-report.php');
                                require_once('alerts.php');
                                $alerts = new Alerts($this->BP, $sid);
                                $storageStatusReport = new StorageStatusReport( $this->BP, $this->functions, $alerts );
                                $returnReport = $storageStatusReport->get_storage_status_report($systems, $data);
                                break;
                            case 'list':
                            default:
                                $returnReport = $this->get_reports( false, false, false, false, true );
                                break;
                        }
                    }
                    else
                    {
                        $returnReport = $this->get_reports( false, false, false, false, true );
                    }
                    break;
                case "storage_data_reduction":
                    require_once('reports/data-reduction-report.php');
                    $dataReductionReport = new DataReductionReport( $this->BP, $this->functions );
                    $returnReport = $dataReductionReport->get_data_reduction_report($systems, $data);
                    break;
                case "storage_status":
                    require_once('reports/storage-status-report.php');
                    require_once('alerts.php');
                    $alerts = new Alerts($this->BP, $sid);
                    $storageStatusReport = new StorageStatusReport( $this->BP, $this->functions, $alerts );
                    $returnReport = $storageStatusReport->get_storage_status_report($systems, $data);
                    break;
                case 'system':
                    if (isset($which[1]) and is_string($which[1]))
                    {
                        switch ($which[1])
                        {
                            case "alert":
                            case "alerts":
                                require_once('alerts.php');
                                $alerts = new Alerts($this->BP, $sid);
                                $returnReport = $alerts->get(null, null, $sid);
                                break;
                            case "capacity":
                                require_once('reports/system-capacity-report.php');
                                $systemCapacityReport = new SystemCapacityReport( $this->BP, $this->functions );
                                $returnReport = $systemCapacityReport->get_system_capacity_report($systems);
                                break;
                            case "notifications":
                                require_once('reports/audit-history-report.php');
                                $auditHistoryReport = new AuditHistoryReport( $this->BP, $this->functions );
                                $returnReport = $auditHistoryReport->get_audit_history($systems, $data);
                                break;
                            case "trap_history":
                                require_once('snmp-config.php');
                                $snmp = new SNMPConfig($this->BP);
                                $returnReport = $snmp->get_traps(-1, $sid);
                                break;
                            case "update_history":
                                require_once('reports/update-history-report.php');
                                $updateHistoryReport = new UpdateHistoryReport( $this->BP, $this->functions );
                                $returnReport = $updateHistoryReport->get_system_update_history($systems, $data);
                                break;
                            case "load_history":
                                require_once('reports/load-history-report.php');
                                $loadHistoryReport = new LoadHistoryReport( $this->BP, $this->functions );
                                $returnReport = $loadHistoryReport->get_system_load_history($systems, $data);
                                break;
                            case 'list':
                            default:
                                $returnReport = $this->get_reports( false, false, false, false, false, true );
                                break;
                        }
                    }
                    else
                    {
                        $returnReport = $this->get_reports( false, false, false, false, false, true );
                    }
                    break;
                case "alert":
                case "alerts":
                    require_once('alerts.php');
                    $alerts = new Alerts($this->BP, $sid);
                    $returnReport = $alerts->get(null, null, $sid);
                    break;
                case 'system_capacity':
                    require_once('reports/system-capacity-report.php');
                    $systemCapacityReport = new SystemCapacityReport( $this->BP, $this->functions );
                    $returnReport = $systemCapacityReport->get_system_capacity_report($systems);
                    break;
                case "audit_history":
                case "system_notifications":
                    require_once('reports/audit-history-report.php');
                    $auditHistoryReport = new AuditHistoryReport( $this->BP, $this->functions );
                    $returnReport = $auditHistoryReport->get_audit_history($systems, $data);
                    break;
                case "trap_history":
                    require_once('snmp-config.php');
                    $snmp = new SNMPConfig($this->BP);
                    $returnReport = $snmp->get_traps(-1, $sid);
                    break;
                case 'update_history':
                    require_once('reports/update-history-report.php');
                    $updateHistoryReport = new UpdateHistoryReport( $this->BP, $this->functions );
                    $returnReport = $updateHistoryReport->get_system_update_history($systems, $data);
                    break;
                case 'load_history':
                    require_once('reports/load-history-report.php');
                    $loadHistoryReport = new LoadHistoryReport( $this->BP, $this->functions );
                    $returnReport = $loadHistoryReport->get_system_load_history($systems, $data);
                    break;
		case 'recovery_assurance':
                    global $Log;
                    require_once('rdr.php');
                    if (array_key_exists('id', $data)) {
                        // api/reports/recovery_assurance/?id=<execution_id>&sid=<system_id>
                        $Log->writeVariable("Getting detailed report for a single job");
                        $returnReport = $this->RDR->get_detailed_job_report($data, $sid);
                    } else {
                        // api/reports/recovery_assurance/?end_date=<date>&start_date=<date>&sid=<system_id>
                        $Log->writeVariable("Getting Recovery Assurance report");
                        $returnReport = $this->RDR->get_rdr_report($data, $sid);
                    }
                    break;
                case 'compliance':
                    global $Log;
                    require_once('rdr.php');
                    if (array_key_exists('id', $data)) {
                        // api/reports/compliance/?id=<execution_id>&sid=<system_id>
                        $Log->writeVariable("Getting detailed compliance report for a single job");
                        $returnReport = $this->RDR->get_detailed_compliance_report($data, $sid);
                    } else {
                        // api/reports/compliance/?sid=<system_id>
                        $Log->writeVariable("Getting Compliance report");
                        $returnReport = $this->RDR->get_compliance_report($data, $sid);
                    }
                    break;
                case 'rdr_summary':
                    global $Log;
                    require_once('rdr.php');
                    $Log->writeVariable("Getting rdr summary report for system with id=" . $sid);
                    $returnReport = $this->RDR->get_rdr_summary($sid);
                    break;
                case 'list':
                default:
                    $returnReport = $this->get_reports( true, true, true, true, true, true, true, true );
                    break;
            }
        }
        if (array_key_exists('format', $data)) {
            if ($data['format'] == 'csv') {
                $CSV = new CSV();
                $reportData = $returnReport['data'];
                $header = $reportData[0];
                $csv = $CSV->toCSV($header, $reportData);
                $returnReport['data'] = $csv;
                $returnReport['csv'] = true;
            } else if ($data['format'] == 'pdf') {
                require_once('reports/pdf.php');
                $pdf = new PDF( $this->BP, $this->functions );
                $reportData = $returnReport['data'];
                $pdf_location = $pdf->toPDF($reportData, "tempName");
                if ( $pdf_location === false )
                {
                    $returnReport = false;
                }
                else
                {
                    //$returnReport['pdf_location'] = $pdf_location;
                    // Using the substr ignores /var/www/html
                    $returnReport['pdf_url'] = ($_SERVER['SERVER_ADDR']).( substr($pdf_location, 13) );
                }
            }
        }
        return($returnReport);
    }

    // Hard-coded list of all of the reports - may move to core in the future
    // Inputs are a series of booleans that tell which report groups to return
    private function get_reports($return_archive_reports = false,
                                 $return_backup_reports = false,
                                 $return_replication_reports = false,
                                 $return_restore_reports = false,
                                 $return_storage_reports = false,
                                 $return_system_reports = false,
                                 $return_rdr_reports = false,
                                 $return_retention_reports = false)
    {
        $report = array();

        $protection_summary_definition = array();
        $protection_policies_definition = array();

        if ( $return_archive_reports === true
            or $return_backup_reports === true
            or $return_replication_reports === true )
        {
            // Protection Summary Report
            $protection_summary_definition['name'] = "protection_summary";
            $protection_summary_definition['title'] = "Protection Summary";
            $protection_summary_definition['desc'] = "A summary of protection details and lists of all protected and unprotected instances within the selected date range.";
            $protection_summary_definition['cols'] = array();
            $protection_summary_definition['extra_cols'] = array();

            $protection_summary_definition['cols']['protected_assets'] = "long";
            $protection_summary_definition['cols']['unprotected_assets'] = "long";
            $protection_summary_definition['cols']['assets_protected_by_backup_copy'] = "long";
            $protection_summary_definition['cols']['assets_not_protected_by_backup_copy'] = "long";

            $protection_summary_definition['cols']['assets'] = array();
            $protection_summary_definition['cols']['assets']['system_name'] = "string";
            $protection_summary_definition['cols']['assets']['client_name'] = "string";
            $protection_summary_definition['cols']['assets']['instance_name'] = "string";
            $protection_summary_definition['cols']['assets']['asset_type'] = "string";
            $protection_summary_definition['cols']['assets']['protected'] = "boolean";
            $protection_summary_definition['cols']['assets']['protected_by_backup_copy'] = "boolean";
            $protection_summary_definition['cols']['assets']['last_backup'] = "string";

            $protection_summary_definition['cols']['backup'] = array();
            $protection_summary_definition['cols']['backup']['successes'] = "long";
            $protection_summary_definition['cols']['backup']['warnings'] = "long";
            $protection_summary_definition['cols']['backup']['failures'] = "long";

            $protection_summary_definition['cols']['backup']['assets'] = array();
            $protection_summary_definition['cols']['backup']['assets']['system_name'] = "string";
            $protection_summary_definition['cols']['backup']['assets']['client_name'] = "string";
            $protection_summary_definition['cols']['backup']['assets']['instance_name'] = "string";
            $protection_summary_definition['cols']['backup']['assets']['asset_type'] = "string";
            $protection_summary_definition['cols']['backup']['assets']['backup_types'] = "array";
            $protection_summary_definition['cols']['backup']['assets']['successes'] = "long";
            $protection_summary_definition['cols']['backup']['assets']['warnings'] = "long";
            $protection_summary_definition['cols']['backup']['assets']['failures'] = "long";
            $protection_summary_definition['cols']['backup']['assets']['last_backup'] = "string";
            $protection_summary_definition['cols']['backup']['assets']['next_backup'] = "string";
            $protection_summary_definition['cols']['backup']['assets']['next_backup_joborder'] = "string";

            $protection_summary_definition['cols']['backup_copies'] = array();
            $protection_summary_definition['cols']['backup_copies']['successes'] = "long";
            $protection_summary_definition['cols']['backup_copies']['warnings'] = "long";
            $protection_summary_definition['cols']['backup_copies']['failures'] = "long";

            $protection_summary_definition['cols']['backup_copies']['assets'] = array();
            $protection_summary_definition['cols']['backup_copies']['assets']['successes'] = "long";
            $protection_summary_definition['cols']['backup_copies']['assets']['warnings'] = "long";
            $protection_summary_definition['cols']['backup_copies']['assets']['failures'] = "long";

            $protection_summary_definition['cols']['backup_copies']['assets']['assets'] = array();
            $protection_summary_definition['cols']['backup_copies']['assets']['assets']['system_name'] = "string";
            $protection_summary_definition['cols']['backup_copies']['assets']['assets']['client_name'] = "string";
            $protection_summary_definition['cols']['backup_copies']['assets']['assets']['instance_name'] = "string";
            $protection_summary_definition['cols']['backup_copies']['assets']['assets']['asset_type'] = "string";
            $protection_summary_definition['cols']['backup_copies']['assets']['assets']['backup_copy_types'] = "array";
            $protection_summary_definition['cols']['backup_copies']['assets']['assets']['backup_copy_successes'] = "long";
            $protection_summary_definition['cols']['backup_copies']['assets']['assets']['backup_copy_warnings'] = "long";
            $protection_summary_definition['cols']['backup_copies']['assets']['assets']['backup_copy_failures'] = "long";
            $protection_summary_definition['cols']['backup_copies']['assets']['assets']['last_backup_copy'] = "string";
        }

        if ( $return_archive_reports === true
            or $return_backup_reports === true
            or $return_replication_reports === true )
        {
            // Protection Policies
            $protection_policies_definition['name'] = "protection_policies";
            $protection_policies_definition['title'] = "Protection Policies";
            $protection_policies_definition['desc'] = "Details for all protected assets, including the associated jobs, retention, inclusions, and exclusions.";
            $protection_policies_definition['cols'] = array();
            $protection_policies_definition['extra_cols'] = array();
            
            $protection_policies_definition['cols']['name'] = "string";
            $protection_policies_definition['cols']['client_name'] = "string";
            $protection_policies_definition['cols']['app_name'] = "string";
            $protection_policies_definition['cols']['policy_name'] = "string";
            $protection_policies_definition['cols']['backup_job_name'] = "string";
            $protection_policies_definition['cols']['replication_job_name'] = "string";
            $protection_policies_definition['cols']['archive_job_name'] = "string";
            $protection_policies_definition['cols']['types'] = "string";
            $protection_policies_definition['cols']['system_name'] = "string";

        }

        if ( $return_archive_reports === true )
        {
            $report['reports']['archive'] = array();

            // Archive History Report
            $archive_history_definition = array();
            $archive_history_definition['name'] = "history";
            $archive_history_definition['title'] = "Archive History";
            $archive_history_definition['desc'] = "All archives within the specified date range.";
            $archive_history_definition['cols'] = array();
            $archive_history_definition['extra_cols'] = array();

            $archive_history_definition['cols']['archive_set_id'] = "long";
            $archive_history_definition['cols']['available'] = "boolean";
            $archive_history_definition['cols']['date'] = "string";
            $archive_history_definition['cols']['description'] = "string";
            $archive_history_definition['cols']['is_imported'] = "boolean";
            $archive_history_definition['cols']['status'] = "string";
            $archive_history_definition['cols']['target'] = "string";
            $archive_history_definition['cols']['app_name'] = "string";
            $archive_history_definition['cols']['archive_id'] = "long";
            $archive_history_definition['cols']['archive_time'] = "string";
            $archive_history_definition['cols']['backup_time'] = "string";
            $archive_history_definition['cols']['client_name'] = "string";
            $archive_history_definition['cols']['compressed'] = "boolean";
            $archive_history_definition['cols']['deduped'] = "boolean";
            $archive_history_definition['cols']['elapsed_time'] = "string";
            $archive_history_definition['cols']['encrypted'] = "boolean";
            $archive_history_definition['cols']['fastseed'] = "boolean";
            $archive_history_definition['cols']['files'] = "long";
            $archive_history_definition['cols']['instance_description'] = "string";
            $archive_history_definition['cols']['orig_backup_id'] = "long";
            $archive_history_definition['cols']['os_type'] = "string";
            $archive_history_definition['cols']['size'] = "long";
            $archive_history_definition['cols']['type'] = "string";
            $archive_history_definition['cols']['serials'] = "string";
            $archive_history_definition['cols']['label'] = "string";
            $archive_history_definition['cols']['barcodes'] = "string";
            $archive_history_definition['cols']['retention_days'] = "long";
            $archive_history_definition['cols']['system_name'] = "string";

            $report['reports']['archive'][] = $archive_history_definition;
            $report['reports']['archive'][] = $protection_summary_definition;
            $report['reports']['archive'][] = $protection_policies_definition;
        }

        if ( $return_backup_reports === true )
        {
            $report['reports']['backup'] = array();

            // Backup History Report
            $backup_history_definition = array();
            $backup_history_definition['name'] = "history";
            $backup_history_definition['title'] = "Backup History";
            $backup_history_definition['desc'] = "All backup results by instance within the specified date range.";
            $backup_history_definition['cols'] = array();
            $backup_history_definition['extra_cols'] = array();

            $backup_history_definition['cols']['app_name'] = "string";
            $backup_history_definition['cols']['client_name'] = "string";
            $backup_history_definition['cols']['complete'] = "boolean";
            $backup_history_definition['cols']['database_name'] = "string";
            $backup_history_definition['cols']['encrypted'] = "boolean";
            $backup_history_definition['cols']['start_date'] = "string";
            $backup_history_definition['cols']['end_date'] = "string";
            $backup_history_definition['cols']['elapsed_time'] = "string";
            $backup_history_definition['cols']['files'] = "long";
            $backup_history_definition['cols']['instance_name'] = "string";
            $backup_history_definition['cols']['joborder_name'] = "string";
            $backup_history_definition['cols']['replication_status'] = "string";
            $backup_history_definition['cols']['size'] = "long";
            $backup_history_definition['cols']['status'] = "string";
            $backup_history_definition['cols']['system_name'] = "string";
            $backup_history_definition['cols']['type'] = "string";

            $backup_history_definition['extra_cols']['certify_status'] = "string";
            $backup_history_definition['extra_cols']['id'] = "long";
            $backup_history_definition['extra_cols']['verify_status'] = "string";
            $backup_history_definition['extra_cols']['speed'] = "float";
          //  $backup_history_definition['extra_cols']['risk_level'] = "int";
            $backup_history_definition['extra_cols']['at_risk'] = "boolean";

            // Failures Report
            $backup_failure_definition = array();
            $backup_failure_definition['name'] = "failure";
            $backup_failure_definition['title'] = "Failure";
            $backup_failure_definition['desc'] = "All backup, restore, and verify failures.";
            $backup_failure_definition['cols'] = array();
            $backup_failure_definition['extra_cols'] = array();

            $backup_failure_definition['cols']['app_name'] = "string";
            $backup_failure_definition['cols']['client_name'] = "string";
            $backup_failure_definition['cols']['complete'] = "boolean";
            $backup_failure_definition['cols']['database_name'] = "string";
            $backup_failure_definition['cols']['elapsed_time'] = "string";
            $backup_failure_definition['cols']['encrypted'] = "boolean";
            $backup_failure_definition['cols']['end_date'] = "string";
            $backup_failure_definition['cols']['joborder_name'] = "string";
            $backup_failure_definition['cols']['instance_name'] = "string";
            $backup_failure_definition['cols']['replication_status'] = "string";
            $backup_failure_definition['cols']['size'] = "long";
            $backup_failure_definition['cols']['start_date'] = "string";
            $backup_failure_definition['cols']['status'] = "string";
            $backup_failure_definition['cols']['system_name'] = "string";
            $backup_failure_definition['cols']['type'] = "string";

            $backup_failure_definition['extra_cols']['certify_status'] = "string";
            $backup_failure_definition['extra_cols']['id'] = "long";
            $backup_failure_definition['extra_cols']['verify_status'] = "string";

            $weekly_status_definition = array();
            $weekly_status_definition['name'] = "status";
            $weekly_status_definition['title'] = "Weekly Status";
            $weekly_status_definition['desc'] = "Weekly backup and backup copy status";
            $weekly_status_definition['cols'] = array();
            $weekly_status_definition['cols']['id'] = "string";
            $weekly_status_definition['cols']['name'] = "string";
            $weekly_status_definition['cols']['system_name'] = "string";
            $weekly_status_definition['cols']['last_backups'] = "array";
            $weekly_status_definition['cols']['last_backup_copies'] = "array";


            $report['reports']['backup'][] = $backup_failure_definition;
            $report['reports']['backup'][] = $backup_history_definition;
            $report['reports']['backup'][] = $protection_summary_definition;
            $report['reports']['backup'][] = $weekly_status_definition;
            $report['reports']['backup'][] = $protection_policies_definition;
        }

        if ( $return_replication_reports === true )
        {
            $report['reports']['replication'] = array();

            // Replication Activity Report
            $replication_activity_definition = array();
            $replication_activity_definition['name'] = "activity";
            $replication_activity_definition['title'] = "Replication Activity";
            $replication_activity_definition['desc'] = "Successful, active, and queued replication jobs in the last 24 hours.";
            $replication_activity_definition['cols'] = array();
            $replication_activity_definition['extra_cols'] = array();

            $replication_activity_definition['cols']['type'] = "string";
            $replication_activity_definition['cols']['completed'] = "string";
            $replication_activity_definition['cols']['elapsed'] = "string";
            $replication_activity_definition['cols']['MBs'] = "long";
            $replication_activity_definition['cols']['files'] = "long";
            $replication_activity_definition['cols']['encrypted'] = "boolean";

            // Replication Capacity Report
            $replication_capacity_definition = array();
            $replication_capacity_definition['name'] = "capacity";
            $replication_capacity_definition['title'] = "Replication Capacity";
            $replication_capacity_definition['desc'] = "Amount of data protected by the backups on a replication target.";
            $replication_capacity_definition['cols'] = array();
            $replication_capacity_definition['extra_cols'] = array();

            $replication_capacity_definition['cols']['app_name'] = "string";
            $replication_capacity_definition['cols']['client_name'] = "string";
            $replication_capacity_definition['cols']['instance_name'] = "string";
            //$replication_capacity_definition['cols']['database_name'] = "string";
            $replication_capacity_definition['cols']['size'] = "long";
            $replication_capacity_definition['cols']['system_name'] = "string";

            // Replication Footprint Report
            $replication_footprint_definition = array();
            $replication_footprint_definition['name'] = "footprint";
            $replication_footprint_definition['title'] = "Storage Footprint";
            $replication_footprint_definition['desc'] = "Amount of space replicating sources are using on a target.";
            $replication_footprint_definition['cols'] = array();
            $replication_footprint_definition['extra_cols'] = array();

            $replication_footprint_definition['cols']['local_system_name'] = "string";
            $replication_footprint_definition['cols']['local_system_version'] = "string";
            $replication_footprint_definition['cols']['report_date'] = "string";
            $replication_footprint_definition['cols']['system_name'] = "string";
            $replication_footprint_definition['cols']['data_size'] = "string";

            // Replication History Report
            $replication_history_definition = array();
            $replication_history_definition['name'] = "history";
            $replication_history_definition['title'] = "Replication History";
            $replication_history_definition['desc'] = "Backups replicated to the selected target.";
            $replication_history_definition['cols'] = array();
            $replication_history_definition['extra_cols'] = array();

            $replication_history_definition['cols']['app_name'] = "string";
            $replication_history_definition['cols']['client_name'] = "string";
            $replication_history_definition['cols']['complete'] = "boolean";
            $replication_history_definition['cols']['database_name'] = "string";
            $replication_history_definition['cols']['elapsed_time'] = "string";
            $replication_history_definition['cols']['encrypted'] = "boolean";
            $replication_history_definition['cols']['end_date'] = "string";
            $replication_history_definition['cols']['instance_name'] = "string";
            $replication_history_definition['cols']['replication_status'] = "string";
            $replication_history_definition['cols']['size'] = "long";
            $replication_history_definition['cols']['replicated_size'] = "long";
            $replication_history_definition['cols']['start_date'] = "string";
            $replication_history_definition['cols']['replication_start_date'] = "string";
            $replication_history_definition['cols']['replication_end_date'] = "string";
            $replication_history_definition['cols']['replication_elapsed_time'] = "string";
            $replication_history_definition['cols']['status'] = "string";
            $replication_history_definition['cols']['system_name'] = "string";
            $replication_history_definition['cols']['type'] = "string";

            $replication_history_definition['extra_cols']['certify_status'] = "string";
            $replication_history_definition['extra_cols']['id'] = "long";
            $replication_history_definition['extra_cols']['verify_status'] = "string";
            $replication_history_definition['extra_cols']['speed'] = "float";

            // Replication Target History Report
            $replication_target_history_definition = array();
            $replication_target_history_definition['name'] = "target_history";
            $replication_target_history_definition['title'] = "Replication Target History";
            $replication_target_history_definition['desc'] = "Backups replicated from the selected source to the local system (target).";
            $replication_target_history_definition['cols'] = array();

            $replication_target_history_definition['cols']['app_name'] = "string";
            $replication_target_history_definition['cols']['client_name'] = "string";
            $replication_target_history_definition['cols']['database_name'] = "string";
            $replication_target_history_definition['cols']['encrypted'] = "boolean";
            $replication_target_history_definition['cols']['instance_name'] = "string";
            $replication_target_history_definition['cols']['replication_status'] = "string";
            $replication_target_history_definition['cols']['replicated_size'] = "long";
            $replication_target_history_definition['cols']['replication_start_date'] = "string";
            $replication_target_history_definition['cols']['replication_end_date'] = "string";
            $replication_target_history_definition['cols']['replication_elapsed_time'] = "string";
            $replication_target_history_definition['cols']['source_system_name'] = "string";
            $replication_target_history_definition['cols']['type'] = "string";
            $replication_target_history_definition['cols']['id'] = "long";

            $report['reports']['replication'][] = $replication_activity_definition;
            $report['reports']['replication'][] = $replication_capacity_definition;
            $report['reports']['replication'][] = $replication_footprint_definition;
            $report['reports']['replication'][] = $replication_history_definition;
            $report['reports']['replication'][] = $replication_target_history_definition;
            $report['reports']['replication'][] = $protection_summary_definition;
            $report['reports']['replication'][] = $protection_policies_definition;
        }

        if ( $return_restore_reports === true )
        {
            $report['reports']['restore'] = array();

            // Restore History Report
            $restore_history_definition = array();
            $restore_history_definition['name'] = "history";
            $restore_history_definition['title'] = "Restore History";
            $restore_history_definition['desc'] = "Restore operations over a specified date range";
            $restore_history_definition['cols'] = array();
            $restore_history_definition['extra_cols'] = array();

            $restore_history_definition['cols']['app_name'] = "string";
            $restore_history_definition['cols']['client_name'] = "string";
            $restore_history_definition['cols']['complete'] = "boolean";
            $restore_history_definition['cols']['database_name'] = "string";
            $restore_history_definition['cols']['start_date'] = "string";
            $restore_history_definition['cols']['end_date'] = "string";
            $restore_history_definition['cols']['elapsed_time'] = "string";
            $restore_history_definition['cols']['files'] = "long";
            $restore_history_definition['cols']['id'] = "long";
            $restore_history_definition['cols']['instance_name'] = "string";
            $restore_history_definition['cols']['size'] = "long";
            $restore_history_definition['cols']['status'] = "string";
            $restore_history_definition['cols']['system_name'] = "string";
            $restore_history_definition['cols']['type'] = "string";

            $report['reports']['restore'][] = $restore_history_definition;
        }

        if ( $return_storage_reports === true )
        {
            $report['reports']['storage'] = array();

            // Storage Report
            $storage_status_definition = array();
            $storage_status_definition['name'] = "storage";
            $storage_status_definition['title'] = "Storage";
            $storage_status_definition['desc'] = "Storage details for all configured backup data stores.";
            $storage_status_definition['cols'] = array();
            $storage_status_definition['extra_cols'] = array();

            $storage_status_definition['cols']['system_id'] = "long";
            $storage_status_definition['cols']['system_name'] = "string";
            $storage_status_definition['cols']['id'] = "long";
            $storage_status_definition['cols']['name'] = "string";
            //$storage_status_definition['cols']['is_default'] = "string";
            $storage_status_definition['cols']['type'] = "string";
            $storage_status_definition['cols']['protocol'] = "string";
            $storage_status_definition['cols']['usage'] = "string";
            $storage_status_definition['cols']['online'] = "boolean";
            $storage_status_definition['cols']['status'] = "string";
            $storage_status_definition['cols']['mb_size'] = "long";
            $storage_status_definition['cols']['mb_free'] = "long";
            $storage_status_definition['cols']['mb_used'] = "long";
            //$storage_status_definition['cols']['average_write_speed'] = "string";
            //$storage_status_definition['cols']['average_written_daily'] = "string";
            //$storage_status_definition['cols']['daily_change_rate'] = "string";
            $storage_status_definition['cols']['daily_growth_rate'] = "string";
            $storage_status_definition['cols']['dedup'] = "string";
            //$storage_status_definition['cols']['effective_size'] = "string";
            //$storage_status_definition['cols']['alerts'] = "array";
            $storage_status_definition['cols']['size_history'] = "array";

            // Data Reduction Report
            $data_reduction_definition = array();
            $data_reduction_definition['name'] = "data_reduction";
            $data_reduction_definition['title'] = "Data Reduction";
            $data_reduction_definition['desc'] = "Space saved through deduplication and data reduction.";
            $data_reduction_definition['cols'] = array();
            $data_reduction_definition['extra_cols'] = array();

            $data_reduction_definition['cols']['date'] = "string";
            $data_reduction_definition['cols']['dedup'] = "long";
            $data_reduction_definition['cols']['data_reduction'] = "long";
            $data_reduction_definition['cols']['system_name'] = "string";

            $report['reports']['storage'][] = $data_reduction_definition;
            $report['reports']['storage'][] = $storage_status_definition;
        }

        if ( $return_system_reports === true )
        {
            $report['reports']['system'] = array();

            // System Capacity Report
            $system_capacity_definition = array();
            $system_capacity_definition['name'] = "capacity";
            $system_capacity_definition['title'] = "System Capacity";
            $system_capacity_definition['desc'] = "Maximum capacity the system can use to store new backups prior to purging.";
            $system_capacity_definition['cols'] = array();
            $system_capacity_definition['extra_cols'] = array();

            $system_capacity_definition['cols']['system_name'] = "string";
            // Commenting these values out since the UI is using them incorrectly (the UI is trying to display them for every asset - UNIBP-9955).  They are still be returned, but for only one system at a time and will not be included in the definition.
            //$system_capacity_definition['cols']['system_id'] = "long";
            //$system_capacity_definition['cols']['used'] = "long";
            //$system_capacity_definition['cols']['available'] = "long";
            //$system_capacity_definition['cols']['message'] = "string";
            $system_capacity_definition['cols']['name'] = "string";
            $system_capacity_definition['cols']['last_full'] = "long";
            $system_capacity_definition['cols']['num_fulls'] = "long";
            $system_capacity_definition['cols']['host_name'] = "string";
            //$system_capacity_definition['cols']['apps'] = "array";
            $system_capacity_definition['cols']['hypervisor'] = "string";
            $system_capacity_definition['cols']['application'] = "string";


            // System Notifications Audit Report
            $system_notification_definition = array();
            $system_notification_definition['name'] = "notifications";
            $system_notification_definition['title'] = "System Notifications";
            $system_notification_definition['desc'] = "System notifications from the audit log.";
            $system_notification_definition['cols'] = array();
            $system_notification_definition['extra_cols'] = array();

            $system_notification_definition['cols']['notification_id'] = "long";
            $system_notification_definition['cols']['event_time'] = "string";
            $system_notification_definition['cols']['username'] = "string";
            $system_notification_definition['cols']['system'] = "string";
            $system_notification_definition['cols']['message'] = "string";
            $system_notification_definition['cols']['category'] = "string";

            // Update History Report
            $update_history_definition = array();
            $update_history_definition['name'] = "update";
            $update_history_definition['title'] = "Update History";
            $update_history_definition['desc'] = "Updates for all systems based on the selected date range.";
            $update_history_definition['cols'] = array();
            $update_history_definition['extra_cols'] = array();

            $update_history_definition['cols']['start_date'] = "string";
            $update_history_definition['cols']['end_date'] = "string";
            $update_history_definition['cols']['status'] = "string";
            $update_history_definition['cols']['system_name'] = "string";
            $update_history_definition['cols']['version_from'] = "string";
            $update_history_definition['cols']['version_to'] = "string";

            // Load History Report
            $load_history_definition = array();
            $load_history_definition['name'] = "load";
            $load_history_definition['title'] = "Load History";
            $load_history_definition['desc'] = "System load over time.";
            $load_history_definition['cols'] = array();
            $load_history_definition['extra_cols'] = array();

            $load_history_definition['cols']['day'] = "string";
            $load_history_definition['cols']['load'] = "long";
            $load_history_definition['cols']['time'] = "string";

            // Trap History Report
            $trap_history_definition = array();
            $trap_history_definition['name'] = "trap history";
            $trap_history_definition['title'] = "Trap History";
            $trap_history_definition['desc'] = "SNMP trap history, which is by default retained for one month.";
            $trap_history_definition['cols'] = array();
            $trap_history_definition['extra_cols'] = array();

            $trap_history_definition['cols']['system_id'] = "long";
            $trap_history_definition['cols']['system_name'] = "string";
            $trap_history_definition['cols']['severity'] = "long";
            $trap_history_definition['cols']['traptype'] = "string";
            $trap_history_definition['cols']['status'] = "long";
            $trap_history_definition['cols']['time_sent'] = "string";
            $trap_history_definition['cols']['destination'] = "string";
            $trap_history_definition['cols']['description'] = "string";
            $trap_history_definition['cols']['oid'] = "string";
            $trap_history_definition['cols']['object'] = "string";
            $trap_history_definition['cols']['community'] = "string";

            $report['reports']['system'][] = $system_capacity_definition;
            $report['reports']['system'][] = $system_notification_definition;
            $report['reports']['system'][] = $load_history_definition;
            $report['reports']['system'][] = $update_history_definition;
            $report['reports']['system'][] = $trap_history_definition;
        }

        if ( $return_rdr_reports === true )
        {
            $report['reports']['compliance'] = array();

            // Restore History Report
            $restore_history_definition = array();
            $restore_history_definition['name'] = "rdr";
            $restore_history_definition['title'] = "Compliance";
            $restore_history_definition['cols'] = array();
            $restore_history_definition['extra_cols'] = array();


            $report['reports']['compliance'][] = $restore_history_definition;
        }

        if ( $return_retention_reports === true ) {
            $report['reports']['retention'] = array();

            // Legal Hold Report
            $legal_hold_definition = array();
            $legal_hold_definition['name'] = "legal_hold";
            $legal_hold_definition['title'] = "Legal Hold";
            $legal_hold_definition['desc'] = "All backups currently on legal hold.";
            $legal_hold_definition['cols'] = array();
            $legal_hold_definition['extra_cols'] = array();

            $legal_hold_definition['cols']['id'] = "long";
            $legal_hold_definition['cols']['app_name'] = "string";
            $legal_hold_definition['cols']['client_name'] = "string";
            $legal_hold_definition['cols']['database_name'] = "string";
            $legal_hold_definition['cols']['instance_name'] = "string";
            $legal_hold_definition['cols']['system_name'] = "string";
            $legal_hold_definition['cols']['type'] = "string";
            $legal_hold_definition['cols']['size'] = "long";
            $legal_hold_definition['cols']['start_date'] = "string";
            $legal_hold_definition['cols']['hold_end_date'] = "string";
            $legal_hold_definition['cols']['hold_days'] = "long";
            $report['reports']['retention'][] = $legal_hold_definition;

            // LTR Retention Report
            $report_definition = array();
            $report_definition['name'] = "retention";
            $report_definition['title'] = "Long-Term Retention Report";
            $report_definition['cols'] = array();
            $report_definition['cols']['asset_name'] = "string";
            $report_definition['cols']['client_name'] = "string";
            $report_definition['cols']['instance_id'] = "long";
            $report_definition['cols']['app_name'] = "string";
            $report_definition['cols']['policy_id'] = "long";
            $report_definition['cols']['policy_name'] = "string";
            $report_definition['cols']['policy_description'] = "string";
            $report_definition['cols']['years'] = "long";
            $report_definition['cols']['months'] = "long";
            $report_definition['cols']['weeks'] = "long";
            $report_definition['cols']['days'] = "long";
            $report_definition['cols']['compliant'] = "boolean";
            $report_definition['cols']['system_id'] = "long";
            $report_definition['cols']['system_name'] = "string";
            $report_definition['extra_cols'] = array();

            $report['reports']['retention'][] = $report_definition;

            // MinMax Retention Report
            $report_definition = array();
            $report_definition['name'] = "min-max retention";
            $report_definition['title'] = "Min-Max Retention Report";
            $report_definition['cols'] = array();
            $report_definition['cols']['asset_name'] = "string";
            $report_definition['cols']['client_name'] = "string";
            $report_definition['cols']['instance_id'] = "long";
            $report_definition['cols']['app_name'] = "string";
            $report_definition['cols']['retention_min'] = "long";
            $report_definition['cols']['retention_max'] = "long";
            $report_definition['cols']['legal_hold'] = "long";
            $report_definition['cols']['days'] = "long";
            $report_definition['cols']['replicated'] = "boolean";
            $report_definition['cols']['system_name'] = "string";
            $report_definition['extra_cols'] = array();

            $report['reports']['retention'][] = $report_definition;
        }

        return $report;
    }

}
