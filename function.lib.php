<?php
/**
 * Class to hold common functions
 * User: Sonja Barton
 * Date: 7/18/14
 */

// in progress
class Functions {
    private $BP;
    private $language; // can be: en-GB, Fr, en-US (or en)

    public function __construct($BP = NULL, $language = NULL){
		$this->BP = $BP;

        // check argument first
        if (isset($language)){
            $this->language = $language;
        // check URL second
        } else if (isset($_GET['lang'])){
            $this->language = $_GET['lang'];
        // check preferences table last
        } else {
            // get the userid so i can check preferences
            // SDMC - LATER IMPROVEMENT: $this->language = $this->bpl_call('rest_get_preferences', $nvpName, 'language');
        }
        // testing
        //$this->language = "fr";
    }

	// Return a complete list of system ids and names for looping.
    public function selectSystems($sid = false, $include_non_managed_replicating_systems = true)
    {
        $filter = array();
        if( $sid !== false )
        {
            $filter['system_ids'] = $sid;
        }
        elseif ( $include_non_managed_replicating_systems === false )
        {
            $filter['system_types'] = array('not_non-managed_replication_source');
        }

        $systems = $this->BP->get_systems( false, $filter );

        // This logic is temporary until the fix can be made in the core
        if ($systems !== false) {
            foreach ($systems as $id => $name) {
                if (strstr($name, '.dpu')) {
                    unset($systems[$id]);
                    break;
                }
            }
        }

        return $systems;
    }

    // Return a list of replicating system ids and names for looping.
    public function selectReplicatingSystems( $sid = false )
    {
        return $this->getSystems( false, $sid, false, false, true, true, false );
    }

    // Returns the name of a given systemID
    public function getSystemNameFromID( $sid )
    {
        $name = false;
        if ( is_numeric($sid) )
        {
            $systems = $this->getSystems( false, $sid );
            $name = $systems[$sid];
        }
        return $name;
    }

    // Returns a list of Systems
    // if getDetails is false, only name and id are returned, else all of the info from bp_get_system_info is returned for each system
    // $systemIDs, $customerIDs, and $locationIDs accept either a single integer or an array of integers
    // $systemNames, $customerNames, and $locationNames accept either a single string or an array of strings
    public function getSystems( $getDetails = false,
                                $systemIDs = false,
                                $includeLocalSystem = true,
                                $includeManagedSystems = true,
                                $includeManagedReplicatingSystems = true,
                                $includeNonManagedReplicatingSystems = true,
                                $includeVaultingSystems = true,
                                $customerIDs = NULL,
                                $locationIDs = NULL,
                                $systemNames = NULL,
                                $customerNames = NULL,
                                $locationNames = NULL )
    {
        $filter = array();
        if( $systemIDs !== false )
        {
            $filter['system_ids'] = $systemIDs;
        }
        if ( $includeLocalSystem !== true or $includeManagedSystems !== true or $includeManagedReplicatingSystems !== true or $includeNonManagedReplicatingSystems !== true or $includeVaultingSystems !== true )
        {
            $systemTypes = array();
            if ( $includeLocalSystem === true )
            {
                $systemTypes[] = 'local';
            }
            if ( $includeManagedSystems === true )
            {
                $systemTypes[] = 'managed';
            }
            if ( $includeManagedReplicatingSystems === true )
            {
                $systemTypes[] = 'replicating_managed';
            }
            if ( $includeNonManagedReplicatingSystems === true )
            {
                $systemTypes[] = 'replicating_non_managed';
            }
            if ( $includeVaultingSystems === true )
            {
                $systemTypes[] = 'vaulting';
            }
            $filter['system_types'] = $systemTypes;
        }
        if ( $systemNames !== NULL )
        {
            $filter['system_names'] = $systemNames;
        }
        if ( $customerIDs !== NULL )
        {
            $filter['customer_ids'] = $customerIDs;
        }
        if ( $customerNames !== NULL )
        {
            $filter['customer_names'] = $customerNames;
        }
        if ( $locationIDs !== NULL )
        {
            $filter['location_ids'] = $locationIDs;
        }
        if ( $locationNames !== NULL )
        {
            $filter['location_names'] = $locationNames;
        }

        return $this->BP->get_systems( $getDetails, $filter );
    }

    function formatTimeDelta($delta)
    {
        $text = '';
        if ($delta > 0) {
            if ($hours = floor($delta/3600)) {
                $delta = $delta % 3600;
            }
            if ($minutes = floor($delta/60)) {
                $delta = $delta % 60;
            }
            $seconds = floor($delta);
            $text = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
        }
        return $text;
    }
	
	const DATE_FORMAT_US = Constants::DATE_FORMAT_US;
	const TIME_FORMAT_12H = Constants::TIME_FORMAT_12H;
	const DATE_TIME_FORMAT_US = Constants::DATE_TIME_FORMAT_US;
    const DATE_TIME_FORMAT_FRANCE = Constants::DATE_TIME_FORMAT_FRANCE;
    const DATE_FORMAT_FRANCE = Constants::DATE_FORMAT_FRANCE;
    const DATE_TIME_FORMAT_UK = Constants::DATE_TIME_FORMAT_UK;
    const DATE_FORMAT_UK = Constants::DATE_FORMAT_UK;

    function formatTime($timestamp, $language = NULL)
    {
        if (!isset($language)){
            $language = $this->getLanguage();
        }

        $returner = NULL;
        switch ($language){
            case "fr":
            case "en-gb":
                $returner = date(Constants::TIME_FORMAT_24H, $timestamp);
                break;
            case "en":
            case "en-us":
            default:
                $returner = date(Constants::TIME_FORMAT_12H, $timestamp);
                break;
        }
        return $returner;
    }
	
    function formatDate($timestamp, $language = NULL)
    {
        if (!isset($language)){
            $language = $this->getLanguage();
        }

        $returner = NULL;
        switch ($language){
            case "fr":
                $returner = date(Constants::DATE_FORMAT_FRANCE, $timestamp);
                break;
            case "en-gb":
                $returner = date(Constants::DATE_FORMAT_UK, $timestamp);
                break;
            case "en-us":
            case "en":
            default:
                $returner = date(Constants::DATE_FORMAT_US, $timestamp);
                break;
        }
        return $returner;
    }

    function formatDateTime($timestamp, $language = NULL)
    {
        if (!isset($language)){
            $language = $this->getLanguage();
        }

        $returner = NULL;
        switch ($language){
            case "fr":
                $returner = date(Constants::DATE_TIME_FORMAT_FRANCE, $timestamp);
                break;
            case "en-gb":
                $returner = date(Constants::DATE_TIME_FORMAT_UK, $timestamp);
                break;
            case "en-us":
            case "en":
            default:
                $returner = date(Constants::DATE_TIME_FORMAT_US, $timestamp);
                break;
        }
        return $returner;
    }

    function formatDateTime24Hour($timestamp, $language = NULL)
    {
        if (!isset($language)){
            $language = $this->getLanguage();
        }

        $returner = NULL;
        switch ($language){
            case "fr":
                $returner = date(Constants::DATE_TIME_FORMAT_FRANCE, $timestamp);
                break;
            case "en-gb":
                $returner = date(Constants::DATE_TIME_FORMAT_UK, $timestamp);
                break;
            case "en":
            case "en-us":
            default:
                $returner = date(Constants::DATE_TIME_FORMAT_24H, $timestamp);
                break;
        }
        return $returner;
    }

    function getLanguage(){
        $returner = "en"; //default
        if (isset($_GET['lang'])){
            $returner = $_GET['lang'];
        } else if (isset($this->lang)){
            $returner = $this->lang;
        }
        return $returner;
    }

    function twentyFourHourTime(){
        $returner = false;
        $language = $this->getLanguage();
        switch ($language) {
            case "en-gb":
            case "fr":
                $returner = true;
                break;
            case "en":
            case "en-us":
                $returner = false;
                break;
        }
        return $returner;
    }

    // function used by various reports
    function reportDateTime($dateTime, $language = NULL){
        if (!isset($language)){
            $language = $this->getLanguage();
        }

        $returner = NULL;
        switch ($language){
            case "en-gb":
            case "fr":
                $returner = date(Constants::DATE_FORMAT_DATE_THEN_24HR_TIME, $dateTime);
                break;
            case "en":
            case "en-us":
            default:
                $returner = date(Constants::DATE_FORMAT_DATE_THEN_12HR_TIME, $dateTime);
                break;
        }

        return $returner;
    }

    function dateTimeToTimestamp($dateTimeString, $language = NULL) {
        if (!isset($language)){
            $language = $this->getLanguage();
        }

        switch ($language){
            case "en-gb":
                $dt = DateTime::createFromFormat(Constants::DATE_TIME_FORMAT_UK, $dateTimeString);
                break;
            case "fr":
                $dt = DateTime::createFromFormat(Constants::DATE_TIME_FORMAT_FRANCE, $dateTimeString);
                break;
            case "en":
            case "en-us":
            default:
                $dt = DateTime::createFromFormat(Constants::DATE_TIME_FORMAT_US, $dateTimeString);
                break;
        }
        // get the timestamp from the date if converted.
        $timeStamp = ($dt !== false) ? $dt->getTimestamp() : 0;

        return $timeStamp;
    }

    // Returns the Application Type of a Given Application ID or false on failure
    public function getApplicationTypeFromApplictionID( $app_id )
    {
        $app_type = false;
        switch ($app_id)
        {
            case Constants::APPLICATION_ID_FILE_LEVEL:
                $app_type = Constants::APPLICATION_TYPE_NAME_FILE_LEVEL;
                break;
            case Constants::APPLICATION_ID_BLOCK_LEVEL:
                $app_type = Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL;
                break;
            case Constants::APPLICATION_ID_EXCHANGE_2003:
            case Constants::APPLICATION_ID_EXCHANGE_2007:
            case Constants::APPLICATION_ID_EXCHANGE_2010:
            case Constants::APPLICATION_ID_EXCHANGE_2013:
            case Constants::APPLICATION_ID_EXCHANGE_2016:
                $app_type = Constants::APPLICATION_TYPE_NAME_EXCHANGE;
                break;
            case Constants::APPLICATION_ID_SQL_SERVER_2005:
            case Constants::APPLICATION_ID_SQL_SERVER_2008:
            case Constants::APPLICATION_ID_SQL_SERVER_2008_R2:
            case Constants::APPLICATION_ID_SQL_SERVER_2012:
            case Constants::APPLICATION_ID_SQL_SERVER_2014:
            case Constants::APPLICATION_ID_SQL_SERVER_2016:
            case Constants::APPLICATION_ID_SQL_SERVER_2017:
                $app_type = Constants::APPLICATION_TYPE_NAME_SQL_SERVER;
                break;
            case Constants::APPLICATION_ID_ARCHIVE:
                $app_type = Constants::APPLICATION_TYPE_NAME_ARCHIVE;
                break;
            case Constants::APPLICATION_ID_VMWARE:
                $app_type = Constants::APPLICATION_TYPE_NAME_VMWARE;
                break;
            case Constants::APPLICATION_ID_HYPER_V_2008_R2:
            case Constants::APPLICATION_ID_HYPER_V_2012:
            case Constants::APPLICATION_ID_HYPER_V_2016:
                $app_type = Constants::APPLICATION_TYPE_NAME_HYPER_V;
                break;
            case Constants::APPLICATION_ID_SYSTEM_METADATA:
                $app_type = Constants::APPLICATION_TYPE_NAME_SYSTEM_METADATA;
                break;
            case Constants::APPLICATION_ID_ORACLE_10:
            case Constants::APPLICATION_ID_ORACLE_11:
            case Constants::APPLICATION_ID_ORACLE_12:
                $app_type = Constants::APPLICATION_TYPE_NAME_ORACLE;
                break;
            case Constants::APPLICATION_ID_SHAREPOINT_2007:
            case Constants::APPLICATION_ID_SHAREPOINT_2010:
            case Constants::APPLICATION_ID_SHAREPOINT_2013:
            case Constants::APPLICATION_ID_SHAREPOINT_2016:
                $app_type = Constants::APPLICATION_TYPE_NAME_SHAREPOINT;
                break;
            case Constants::APPLICATION_ID_UCS_SERVICE_PROFILE:
                $app_type = Constants::APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE;
                break;
            case Constants::APPLICATION_ID_VOLUME:
                $app_type = Constants::APPLICATION_TYPE_NAME_NDMP_DEVICE;
                break;
            case Constants::APPLICATION_ID_XEN:
                $app_type = Constants::APPLICATION_TYPE_NAME_XEN;
                break;
            case Constants::APPLICATION_ID_AHV:
                $app_type = Constants::APPLICATION_TYPE_NAME_AHV;
                break;
        }
        return $app_type;
    }

    public function getAppName($object) {
        $app = '';
        if (isset($object['app_name'])) {
            $app = $object['app_name'];
            if ($app === Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL) {
                $app = Constants::APPLICATION_TYPE_DISPLAY_NAME_BLOCK_LEVEL;
            } else if ($app === Constants::APPLICATION_TYPE_NAME_FILE_LEVEL) {
                $app = Constants::APPLICATION_TYPE_DISPLAY_NAME_FILE_LEVEL;
            }
        }
        return $app;
    }

    // Returns the instance name of the object, or the fallback name if not found.
    public function getInstanceName($object, $sid, $clientName, $fallbackName = '') {
        $name = $fallbackName;
        if (isset($object['instance_id'])) {
            $instanceName = $this->BP->get_appinst_name($object['instance_id'], $sid);
            if ($instanceName === Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL) {
                $name = $clientName . ' (' . Constants::APPLICATION_TYPE_DISPLAY_NAME_BLOCK_LEVEL . ')';
            } else {
                $name = $instanceName;
            }
        }
        return $name;
    }

    public function getBackupTypeCoreName($backupType, $appType) {
        switch($appType) {
            case Constants::APPLICATION_TYPE_NAME_VMWARE:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = "VMware Full";
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                        $backupType = "VMware Incremental";
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                        $backupType = "VMware Differential";
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_HYPER_V:
                    switch($backupType) {
                        case Constants::BACKUP_DISPLAY_TYPE_FULL:
                            $backupType = "Hyper-V Full";
                            break;
                        case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                            $backupType = "Hyper-V Incremental";
                            break;
                        case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                            $backupType = "Hyper-V Differential";
                            break;
                    }
                break;
            case Constants::APPLICATION_TYPE_NAME_ORACLE:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = "Oracle Full";
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                        $backupType = "Oracle Incremental";
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_SQL_SERVER:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = "SQL Full";
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                        $backupType = "SQL Differential";
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_TRANSACTION:
                        $backupType = "SQL Transaction";
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_SHAREPOINT:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = "SharePoint Full";
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                        $backupType = "SharePoint Differential";
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_EXCHANGE:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = "Exchange Full";
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                        $backupType = "Exchange Incremental";
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                        $backupType = "Exchange Differential";
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_FILE_LEVEL:
                if($backupType == Constants::BACKUP_DISPLAY_TYPE_FULL) {
                    $backupType = "Master";
                }
                break;
            case Constants::BACKUP_METHOD_IMAGE_LEVEL:
            case Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = Constants::BACKUP_SCHEDULE_TYPE_BLOCK_FULL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                        $backupType = Constants::BACKUP_SCHEDULE_TYPE_BLOCK_INCREMENTAL;
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_XEN:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = "Xen Full";
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_AHV:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = "AHV Full";
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                        $backupType = "AHV Incremental";
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                        $backupType = "AHV Differential";
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = "UCS Service Profile Full";
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_NDMP_DEVICE:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = "NDMP Full";
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                        $backupType = "NDMP Differential";
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                        $backupType = "NDMP Incremental";
                        break;
                }
                break;
        }
        return $backupType;
    }

    public function getBackupTypeCoreNameBackupNow($backupType, $appType) {
        switch($appType) {
            case Constants::APPLICATION_TYPE_NAME_VMWARE:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = Constants::BACKUP_TYPE_VMWARE_FULL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                        $backupType = Constants::BACKUP_TYPE_VMWARE_INCREMENTAL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                        $backupType = Constants::BACKUP_TYPE_VMWARE_DIFFERENTIAL;
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_HYPER_V:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = Constants::BACKUP_TYPE_HYPER_V_FULL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                        $backupType = Constants::BACKUP_TYPE_HYPER_V_INCREMENTAL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                        $backupType = Constants::BACKUP_TYPE_HYPER_V_DIFFERENTIAL;
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_ORACLE:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = Constants::BACKUP_TYPE_ORACLE_FULL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                        $backupType = Constants::BACKUP_TYPE_ORACLE_INCR;
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_SQL_SERVER:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = Constants::BACKUP_TYPE_MSSQL_FULL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                        $backupType = Constants::BACKUP_TYPE_MSSQL_DIFFERENTIAL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_TRANSACTION:
                        $backupType = Constants::BACKUP_TYPE_MSSQL_TRANSACTION;
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_SHAREPOINT:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = Constants::BACKUP_TYPE_SHAREPOINT_FULL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                        $backupType = Constants::BACKUP_TYPE_SHAREPOINT_DIFF;
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_EXCHANGE:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = Constants::BACKUP_TYPE_EXCHANGE_FULL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                        $backupType = Constants::BACKUP_TYPE_EXCHANGE_INCREMENTAL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                        $backupType = Constants::BACKUP_TYPE_EXCHANGE_DIFFERENTIAL;
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_FILE_LEVEL:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = Constants::BACKUP_TYPE_MASTER;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_BAREMETAL;
                        $backupType = Constants::BACKUP_TYPE_BAREMETAL;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = Constants::BACKUP_TYPE_BLOCK_FULL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                        $backupType = Constants::BACKUP_TYPE_BLOCK_INCREMENTAL;
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_XEN:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = Constants::BACKUP_TYPE_XEN_FULL;
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = Constants::BACKUP_TYPE_UCS_SERVICE_PROFILE_FULL;
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_NDMP_DEVICE:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = Constants::BACKUP_TYPE_NDMP_FULL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                        $backupType = Constants::BACKUP_TYPE_NDMP_DIFF;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                        $backupType = Constants::BACKUP_TYPE_NDMP_INCR;
                        break;
                }
                break;
            case Constants::APPLICATION_TYPE_NAME_AHV:
                switch($backupType) {
                    case Constants::BACKUP_DISPLAY_TYPE_FULL:
                        $backupType = Constants::BACKUP_TYPE_AHV_FULL;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL:
                        $backupType = Constants::BACKUP_TYPE_AHV_DIFF;
                        break;
                    case Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL:
                        $backupType = Constants::BACKUP_TYPE_AHV_INCR;
                        break;
                }
                break;
        }
        return $backupType;
    }

    public function getBackupTypeDisplayName($backupType, $bSynthesized = false)
    {
        $backupDisplayName = '';
        switch($backupType)
        {
            case Constants::BACKUP_TYPE_MASTER:
            case Constants::BACKUP_TYPE_BLOCK_FULL:
            case Constants::BACKUP_TYPE_MSSQL_FULL:
            case Constants::BACKUP_TYPE_MSSQL_FULL_ALT:
            case Constants::BACKUP_TYPE_EXCHANGE_FULL:
            case Constants::BACKUP_TYPE_LEGACY_MSSQL_FULL:
            case Constants::BACKUP_TYPE_VMWARE_FULL:
            case Constants::BACKUP_TYPE_HYPER_V_FULL:
            case Constants::BACKUP_TYPE_HYPER_V_FULL_ALT:
            case Constants::BACKUP_TYPE_ORACLE_FULL:
            case Constants::BACKUP_TYPE_SHAREPOINT_FULL:
            case Constants::BACKUP_TYPE_UCS_SERVICE_PROFILE_FULL:
            case Constants::BACKUP_TYPE_NDMP_FULL:
            case Constants::BACKUP_TYPE_XEN_FULL:
            case Constants::BACKUP_TYPE_AHV_FULL:
            case Constants::BACKUP_TYPE_SECURESYNC_MASTER:
            case Constants::BACKUP_TYPE_SECURESYNC_BLOCK_FULL:
            case Constants::BACKUP_TYPE_SECURESYNC_MSSQL_FULL:
            case Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE_FULL:
            case Constants::BACKUP_TYPE_SECURESYNC_VMWARE_FULL:
            case Constants::BACKUP_TYPE_SECURESYNC_HYPER_V_FULL:
            case Constants::BACKUP_TYPE_SECURESYNC_ORACLE_FULL:
            case Constants::BACKUP_TYPE_SECURESYNC_SHAREPOINT_FULL:
            case Constants::BACKUP_TYPE_SECURESYNC_UCS_SERVICE_PROFILE_FULL:
            case Constants::BACKUP_TYPE_SECURESYNC_NDMP_FULL:
            case Constants::BACKUP_TYPE_SECURESYNC_XEN_FULL:
            case Constants::BACKUP_TYPE_SECURESYNC_AHV_FULL:
            case Constants::BACKUP_TYPE_SATORI_REPLICATION_VMWARE_FULL:
            case Constants::BACKUP_TYPE_SATORI_REPLICATION_HV_FULL:
            case Constants::BACKUP_TYPE_SATORI_REPLICATION_SQL_FULL:
            case Constants::BACKUP_TYPE_SATORI_REPLICATION_EXCHANGE_FULL:
            case Constants::BACKUP_TYPE_SYSTEM_METADATA:
                $backupDisplayName = Constants::BACKUP_DISPLAY_TYPE_FULL;
                break;
            case Constants::BACKUP_TYPE_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_MSSQL_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_MSSQL_DIFFERENTIAL_ALT:
            case Constants::BACKUP_TYPE_EXCHANGE_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_LEGACY_MSSQL_DIFF:
            case Constants::BACKUP_TYPE_VMWARE_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_HYPER_V_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_HYPER_V_DIFFERENTIAL_ALT:
            case Constants::BACKUP_TYPE_SHAREPOINT_DIFF:
            case Constants::BACKUP_TYPE_SHAREPOINT_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_NDMP_DIFF:
            case Constants::BACKUP_TYPE_NDMP_DIFF_ALT:
            case Constants::BACKUP_TYPE_AHV_DIFF:
            case Constants::BACKUP_TYPE_SECURESYNC_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_SECURESYNC_MSSQL_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_SECURESYNC_VMWARE_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_SECURESYNC_HYPER_V_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_SECURESYNC_SHAREPOINT_DIFF:
            case Constants::BACKUP_TYPE_SECURESYNC_NDMP_DIFF:
            case Constants::BACKUP_TYPE_SECURESYNC_AHV_DIFF:
            case Constants::BACKUP_TYPE_SATORI_REPLICATION_VMWARE_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_SATORI_REPLICATION_HV_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_SATORI_REPLICATION_SQL_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_SATORI_REPLICATION_EXCHANGE_DIFFERENTIAL:
            $backupDisplayName = Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL;
                break;
            case Constants::BACKUP_TYPE_INCREMENTAL:
            case Constants::BACKUP_TYPE_BLOCK_INCREMENTAL:
            case Constants::BACKUP_TYPE_EXCHANGE_INCREMENTAL:
            case Constants::BACKUP_TYPE_VMWARE_INCREMENTAL:
            case Constants::BACKUP_TYPE_HYPER_V_INCREMENTAL:
            case Constants::BACKUP_TYPE_HYPER_V_INCREMENTAL_ALT:
            case Constants::BACKUP_TYPE_ORACLE_INCR:
            case Constants::BACKUP_TYPE_ORACLE_INCR_ALT:
            case Constants::BACKUP_TYPE_NDMP_INCR:
            case Constants::BACKUP_TYPE_NDMP_INCR_ALT:
            case Constants::BACKUP_TYPE_AHV_INCR:
            case Constants::BACKUP_TYPE_AHV_INCR_ALT:
            case Constants::BACKUP_TYPE_SECURESYNC_INCREMENTAL:
            case Constants::BACKUP_TYPE_SECURESYNC_BLOCK_INCREMENTAL:
            case Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE_INCREMENTAL:
            case Constants::BACKUP_TYPE_SECURESYNC_VMWARE_INCREMENTAL:
            case Constants::BACKUP_TYPE_SECURESYNC_HYPER_V_INCREMENTAL:
            case Constants::BACKUP_TYPE_SECURESYNC_ORACLE_INCR:
            case Constants::BACKUP_TYPE_SECURESYNC_NDMP_INCR:
            case Constants::BACKUP_TYPE_SECURESYNC_AHV_INCR:
            case Constants::BACKUP_TYPE_SATORI_REPLICATION_VMWARE_INCREMENTAL:
            case Constants::BACKUP_TYPE_SATORI_REPLICATION_HV_INCREMENTAL:
            case Constants::BACKUP_TYPE_SATORI_REPLICATION_SQL_INCREMENTAL:
            case Constants::BACKUP_TYPE_SATORI_REPLICATION_EXCHANGE_INCREMENTAL:
                $backupDisplayName = Constants::BACKUP_DISPLAY_TYPE_INCREMENTAL;
                break;
            case Constants::BACKUP_TYPE_BAREMETAL:
                $backupDisplayName = Constants::BACKUP_DISPLAY_TYPE_BAREMETAL;
                break;
            case Constants::BACKUP_TYPE_SELECTIVE:
                $backupDisplayName = Constants::BACKUP_DISPLAY_TYPE_SELECTIVE;
                break;
            case Constants::BACKUP_TYPE_MSSQL_TRANSACTION:
            case Constants::BACKUP_TYPE_MSSQL_TRANSACTION_ALT:
            case Constants::BACKUP_TYPE_LEGACY_MSSQL_TRANS:
                $backupDisplayName = Constants::BACKUP_DISPLAY_TYPE_TRANSACTION;
                break;
            case Constants::BACKUP_TYPE_RESTORE:
            case Constants::BACKUP_TYPE_INTEGRATED_BM_RESTORE:
                $backupDisplayName = Constants::BACKUP_DISPLAY_TYPE_RESTORE;
                break;
            case Constants::BACKUP_TYPE_VIRTUAL_RESTORE:
                $backupDisplayName = Constants::BACKUP_DISPLAY_TYPE_WIR;
                break;
            case Constants::BACKUP_TYPE_REPLICA_RESTORE:
                $backupDisplayName = Constants::BACKUP_DISPLAY_TYPE_REPLICA_RESTORE;
                break;
            case Constants::BACKUP_COPY_ARCHIVE_RESTORE:
                $backupDisplayName = Constants::BACKUP_COPY_DISPLAY_ARCHIVE_RESTORE;
                break;
            case Constants::BACKUP_COPY_ARCHIVE:
                $backupDisplayName = Constants::BACKUP_COPY_DISPLAY_ARCHIVE;
                break;
            default:
                $backupDisplayName = $backupType;
                break;
        }

        if ($bSynthesized == true){
            $backupDisplayName = "Synthetic " . $backupDisplayName;
        }
        return $backupDisplayName;
    }

    // returns true is $backupType is a securesync type
    public function isBackupTypeSecuresync($backupType)
{
    $isSecuresync = false;
    switch($backupType)
    {
        case Constants::BACKUP_TYPE_SECURESYNC_MASTER:
        case Constants::BACKUP_TYPE_SECURESYNC_DIFFERENTIAL:
        case Constants::BACKUP_TYPE_SECURESYNC_INCREMENTAL:
        case Constants::BACKUP_TYPE_SECURESYNC_BLOCK_FULL:
        case Constants::BACKUP_TYPE_SECURESYNC_BLOCK_INCREMENTAL:
        case Constants::BACKUP_TYPE_SECURESYNC_BAREMETAL:
        case Constants::BACKUP_TYPE_SECURESYNC_DPU_STATE:
        case Constants::BACKUP_TYPE_SECURESYNC_LOCAL_DIRECTORY:
        case Constants::BACKUP_TYPE_SECURESYNC_MS_SQL:
        case Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE:
        case Constants::BACKUP_TYPE_SECURESYNC_MSSQL_FULL:
        case Constants::BACKUP_TYPE_SECURESYNC_MSSQL_DIFFERENTIAL:
        case Constants::BACKUP_TYPE_SECURESYNC_MSSQL_TRANSACTION:
        case Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE_FULL:
        case Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE_DIFFERENTIAL:
        case Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE_INCREMENTAL:
        case Constants::BACKUP_TYPE_SECURESYNC_VMWARE_FULL:
        case Constants::BACKUP_TYPE_SECURESYNC_VMWARE_DIFFERENTIAL:
        case Constants::BACKUP_TYPE_SECURESYNC_VMWARE_INCREMENTAL:
        case Constants::BACKUP_TYPE_SECURESYNC_HYPER_V_FULL:
        case Constants::BACKUP_TYPE_SECURESYNC_HYPER_V_INCREMENTAL:
        case Constants::BACKUP_TYPE_SECURESYNC_HYPER_V_DIFFERENTIAL:
        case Constants::BACKUP_TYPE_SECURESYNC_ORACLE_FULL:
        case Constants::BACKUP_TYPE_SECURESYNC_ORACLE_INCR:
        case Constants::BACKUP_TYPE_SECURESYNC_SHAREPOINT_FULL:
        case Constants::BACKUP_TYPE_SECURESYNC_SHAREPOINT_DIFF:
        case Constants::BACKUP_TYPE_SECURESYNC_SYSTEM_METADATA:
        case Constants::BACKUP_TYPE_SECURESYNC_UCS_SERVICE_PROFILE_FULL:
        case Constants::BACKUP_TYPE_SECURESYNC_NDMP_FULL:
        case Constants::BACKUP_TYPE_SECURESYNC_NDMP_DIFF:
        case Constants::BACKUP_TYPE_SECURESYNC_NDMP_INCR:
        case Constants::BACKUP_TYPE_SECURESYNC_XEN_FULL:
        case Constants::BACKUP_TYPE_SECURESYNC_AHV_FULL:
        case Constants::BACKUP_TYPE_SECURESYNC_AHV_DIFF:
        case Constants::BACKUP_TYPE_SECURESYNC_AHV_INCR:
            $isSecuresync = true;
            break;
    }
    return $isSecuresync;
}

    public function isBackupFileLevel($backupType){
        $isFileLevel = false;

        switch($backupType){
            case Constants::BACKUP_TYPE_MASTER:
            case Constants::BACKUP_TYPE_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_INCREMENTAL:
            case Constants::BACKUP_TYPE_SELECTIVE:

            $isFileLevel = true;
                break;
        }
        return $isFileLevel;
    }

    public function isBackupBlockLevel($backupType){ //Could also be called isBackupImageLevel
        $isBlockLevel = false;

        switch($backupType){
            case Constants::BACKUP_TYPE_BLOCK_FULL:
            case Constants::BACKUP_TYPE_BLOCK_INCREMENTAL:

            $isBlockLevel = true;
                break;
        }
        return $isBlockLevel;
    }

    public function isBackupHyperV($backupType){
        $isHyperV = false;

        switch($backupType){
            case Constants::BACKUP_TYPE_HYPER_V_FULL:
            case Constants::BACKUP_TYPE_HYPER_V_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_HYPER_V_INCREMENTAL:

            $isHyperV = true;
                break;
        }
        return $isHyperV;
    }

    public function isBackupExchange($backupType){
        $isExchange = false;

        switch($backupType){
            case Constants::BACKUP_TYPE_EXCHANGE_FULL:
            case Constants::BACKUP_TYPE_EXCHANGE_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_EXCHANGE_INCREMENTAL:

            $isExchange = true;
                break;
        }
        return $isExchange;
    }

    public function isBackupSQL($backupType){
        $isSQL = false;

        switch($backupType){
            case Constants::BACKUP_TYPE_MSSQL_FULL:
            case Constants::BACKUP_TYPE_MSSQL_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_MSSQL_TRANSACTION:

            $isSQL = true;
                break;
        }
        return $isSQL;
    }

    public function isBackupVMWare($backupType){
        $isVMWare = false;

        switch($backupType){
            case Constants::BACKUP_TYPE_VMWARE_FULL:
            case Constants::BACKUP_TYPE_VMWARE_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_VMWARE_INCREMENTAL:

            $isVMWare = true;
                break;
        }
        return $isVMWare;
    }

    public function isBackupXen($backupType){
        $isXen = false;

        switch($backupType){
            case Constants::BACKUP_TYPE_XEN_FULL:

                $isXen = true;
                break;
        }
        return $isXen;
    }

    public function isBackupAHV($backupType){
        $isAHV = false;

        switch($backupType){
            case Constants::BACKUP_TYPE_AHV_FULL:
            case Constants::BACKUP_TYPE_AHV_DIFF:
            case Constants::BACKUP_TYPE_AHV_INCR:
            case Constants::BACKUP_TYPE_AHV_INCR_ALT:

                $isAHV = true;
                break;
        }
        return $isAHV;
    }

    public function isBackupCiscoUCS($backupType)
    {
        $isCiscoUCS = false;

        switch ($backupType) {
            case Constants::BACKUP_TYPE_UCS_SERVICE_PROFILE_FULL:

                $isCiscoUCS = true;
                break;
        }
        return $isCiscoUCS;
    }

    public function isBackupNDMP($backupType) {
        $isNDMP = false;

        switch($backupType) {
            case Constants::BACKUP_TYPE_NDMP_FULL:
            case Constants::BACKUP_TYPE_NDMP_DIFF:
            case Constants::BACKUP_TYPE_NDMP_INCR:
            case Constants::BACKUP_TYPE_NDMP_DIFF_ALT:
            case Constants::BACKUP_TYPE_NDMP_INCR_ALT:

                $isNDMP = true;
                break;
        }
        return $isNDMP;
    }

    public function isBackupVirtualRestore($backupType){
        $isVirtualRestore = false;

        switch($backupType){
            case Constants::BACKUP_TYPE_VIRTUAL_RESTORE:
            case Constants::BACKUP_TYPE_REPLICA_RESTORE:

            $isVirtualRestore = true;
                break;
        }
        return $isVirtualRestore;
    }

    public function isAppSQL($appID) {
        return ($appID >= Constants::APPLICATION_ID_SQL_SERVER_2005 && $appID <= Constants::APPLICATION_ID_SQL_SERVER_2017);
    }

    public function isAppHyperV($appID) {
        return ($appID >= Constants::APPLICATION_ID_HYPER_V_2008_R2 && $appID <= Constants::APPLICATION_ID_HYPER_V_2016);
    }

    function getBackupTypeString($backupField, $bSynthesized = false)
    {
        $backupType = $backupField;
        switch ($backupField) {
            case Constants::BACKUP_TYPE_MASTER:
            case Constants::BACKUP_TYPE_BLOCK_FULL:
            case "securesync master":
            case Constants::BACKUP_TYPE_SECURESYNC_BLOCK_FULL:
                $backupType = "Full";
                break;
            case Constants::BACKUP_TYPE_DIFFERENTIAL:
            case "securesync differential":
            case Constants::BACKUP_TYPE_SECURESYNC_BLOCK_DIFFERENTIAL:
                $backupType = "Differential";
                break;
            case Constants::BACKUP_TYPE_INCREMENTAL:
            case Constants::BACKUP_TYPE_BLOCK_INCREMENTAL:
            case "securesync incremental":
            case Constants::BACKUP_TYPE_SECURESYNC_BLOCK_INCREMENTAL:
                $backupType = "Incremental";
                break;
            case Constants::BACKUP_TYPE_SELECTIVE:
                $backupType = "Selective";
                break;
            case Constants::BACKUP_TYPE_BAREMETAL:
            case "securesync baremetal":
                $backupType = "BareMetal";
                break;
            case "securesync mssql full":
            case Constants::BACKUP_TYPE_MSSQL_FULL:
                $backupType = "SQL Full";
                break;
            case "securesync mssql diff":
            case "mssql differential":
                $backupType = "SQL Differential";
                break;
            case "securesync mssql trans":
            case "mssql transaction":
                $backupType = "SQL Transaction";
                break;
            case "legacy mssql full":
            case "legacy mssql diff":
            case "legacy mssql trans":
                $backupType = "SQL";
                break;
            case "securesync msexch full":
            case "exchange full":
                $backupType = "Exchange Full";
                break;
            case "securesync msexch diff":
            case "exchange differential":
                $backupType = "Exchange Differential";
                break;
            case "securesync msexch incr":
            case "exchange incremental":
                $backupType = "Exchange Incremental";
                break;
            case "securesync vmware full":
            case "securesync esx vm full":
            case "vmware full":
                $backupType = "Full";
                break;
            case "securesync xen full":
            case "xen full":
                $backupType = "Full";
                break;
            case "securesync vmware diff":
            case "securesync esx vm diff":
            case "vmware differential":
                $backupType = "Differential";
                break;
            case "securesync vmware incr":
            case "securesync esx vm incr":
            case "vmware incremental":
                $backupType = "Incremental";
                break;
            case "vmware configuration":		// only used on restore for first job to restore metadata
                $backupType = "VMware Configuration";
                break;
            case "securesync hyperv full":
            case "securesync hyper-v full":		// operation progress string
            case "hyperv full":
                $backupType = "Full";
                break;
            case "securesync hyperv incr":
            case "securesync hyper-v incr":		// operation progress string
            case "hyperv incremental":
                $backupType = "Incremental";
                break;
            case "securesync hyperv diff":
            case "securesync hyper-v diff":		// operation progress string
            case "hyperv differential":
                $backupType = "Differential";
                break;
            case "restore":
                $backupType = "Restore";
                break;
            case "block restore":
                $backupType = "Block Restore";
                break;
            case "virtual restore":
                $backupType = "Virtual Restore";
                break;
            case "replica restore":
                $backupType = "Replica Restore";
                break;
            case "verify":
                $backupType = "Verify";
                break;
            case "securesync SQL":
            case "securesync sql":
                $backupType = "SQL";
                break;
            case "localdir":
            case "securesync localdir":
                $backupType = "Local Directory";
                break;
            case "securesync exchange":
                $backupType = "Exchange";
                break;
            case "securesync dpustate":
                $backupType = "DPUstate";
                break;
            case "securesync system metadata":
            case "system metadata":
                $backupType = "System Metadata";
                break;
            case "archive":
                $backupType = "Backup Copy";
                break;
            case "archive restore":
                $backupType = "Import";
                break;
            case "securesync oracle full":
            case "oracle full":
                $backupType = "Oracle Full";
                break;
            case "securesync oracle incr":
            case "oracle incr":
                $backupType = "Oracle Incremental";
                break;
            case "securesync sharepoint full":
            case "sharepoint full":
                $backupType = "SharePoint Full";
                break;
            case "securesync sharepoint diff":
            case "sharepoint diff":
                $backupType = "SharePoint Differential";
                break;
            case "securesync ucs service profile full":
            case "ucs service profile full":
                $backupType = "UCS Service Profile Full";
                break;
            case "integrated bm restore":
                $backupType = "Integrated Bare Metal Recovery";
                break;
            case "securesync ndmp full":
            case "ndmp full":
                $backupType = "NDMP Full";
                break;
            case "securesync ndmp diff":
            case "ndmp diff":
                $backupType = "NDMP Differential";
                break;
            case "securesync ndmp incr":
            case "ndmp incr":
                $backupType = "NDMP Incremental";
                break;
            case "securesync ahv full":
            case "ahv full":
                $backupType = "Full";
                break;
            case "securesync ahv diff":
            case "ahv diff":
                $backupType = "Differential";
                break;
            case "securesync ahv incr":
            case "ahv incr":
                $backupType = "Incremental";
                break;
        }

        if ($bSynthesized == true){
            $backupType = "Synthetic " . $backupType;
        }
        return $backupType;
    }

    public function getArchiveTypes() {
        return array("master", "differential", "incremental", "selective", "baremetal", "vmware full", "vmware differential", "vmware incremental",
            "hyperv full", "hyperv incremental", "hyperv differential");
    }

    public function getArchiveTypesByDisplayType($type) {
        switch($type) {
            case 'Full':
                $types = array("master", "vmware full", "hyperv full");
                break;
            case 'Incremental':
                $types = array("incremental", "vmware incremental", "hyperv incremental");
                break;
            case 'Differential':
                $types = array("differential", "vmware differential", "hyperv differential");
                break;
            case 'Selective':
                $types = array("selective");
                break;
            case 'Bare Metal':
                $types = array("baremetal");
                break;
        }
        return $types;
    }

    //takes a date in format mm/dd/yyyy hh:mm:ssAM or mm/dd/yyyy and converts to unix timestamp
    public function dateToTimestamp($formattedDate) {
        return strtotime($formattedDate);

//        $formattedDate = explode(" ", $formattedDate);
//        if(count($formattedDate) == 1) {
//            // mm/dd/yyyy
//            return strtotime($formattedDate[0]);
//        } else {
//            //mm/dd/yyyy hh:mm:ssAM
//            $date = explode("/", $formattedDate[0]);
//            $time = explode(":", $formattedDate[1]);
//            $hour = $time[0];
//            if($hour == 12 and substr($time[2], -2) == "AM") {
//                $hour = 0;
//            } elseif($hour == 12 and substr($time[2], -2) == "PM") {
//                $hour = 12;
//            } elseif(substr($time[2], -2) == "PM") {
//                $hour = ((int)$hour + 12) % 24;
//            }
//            return mktime((int)$hour, (int)$time[1], (int)substr($time[2], 0, 2), (int)$date[0], (int)$date[1], (int)$date[2]);
//        }

    }
    public function isWIROnApplianceSupported($result) {
        $ret = false;

        if ($result == true) {
            foreach ($result as $key=>$supportedType) {
                if ($supportedType != NULL && strlen(strstr( $supportedType, "Unitrends appliance" )) > 0 ) {
                    $ret = true;
                    break;
                }
            }
        }

        return $ret;
    }

    public function isWIRAllowed($state) {
        $isAllowed = false;

        switch($state) {
            case Constants::REPLICAS_STATE_NEW:
            case Constants::REPLICAS_STATE_RESTORE:
            case Constants::REPLICAS_STATE_IDLE:
            case Constants::REPLICAS_STATE_OFF:
            case Constants::REPLICAS_STATE_VERIFY:
            case Constants::REPLICAS_STATE_AUDIT:
            case Constants::REPLICAS_STATE_AUDIT_RDR:
                $isAllowed = true;
                break;
        }
        return $isAllowed;
    }

    // For Reports and other APIs that accept a date range, end_date should default to 11:59:59pm of that day
    // If a time is already specified by the user, then use that time and not 11:59:59pm of that day
    // The caller of this function should still test whether or not $end_time is false.  If it is false, then the user sent in an input that PHP strtotime does not handle
    // *******
    // ******* NOTE: This could behave differently in PHP 5.1 (CentOS5) vs PHP 5.3 (CentOS6) because strtotime changed.  Make sure to test both use cases.
    // *******
    public function formatEndDate($end_date)
    {

        $returner = NULL;
        // Default the end_time to being the end of the day
        $end_time = strtotime($end_date . ',11:59:59pm');
        // If the end time fails being at the end of the day, then time is probably already specified for that day.
        // Thus try exactly what the user already sent in
        if ($end_time === false) {
            $end_time = strtotime($end_date);
        }
        // The caller of this function should still test whether or not $end_time is false.
        $returner = $end_time;

        return $returner;
    }

    // Checks a given time range to see if it is valid.
    // ie. the start time is before the end time
    // Allows caller to pass whether or not future times are accepted
    // It is acceptable to pass a valid start time or end time, with the other being false or null
    public function isValidDateRange($start_time, $end_time, $allowFutureTimes = false) {
        if(is_numeric($start_time) and is_numeric($end_time)) {
            if($allowFutureTimes) {
                $isValid = $start_time <= $end_time;
            } else {
                $isValid = ($start_time <= $end_time and $end_time <= strtotime("11:59:59 pm"));
            }
        } else if(is_numeric($start_time) and !is_numeric($end_time)) {
            if($allowFutureTimes) {
                //nothing to do here
                $isValid = true;
            } else {
                $isValid = ($start_time <= time());
            }
        } else if(!is_numeric($start_time) and is_numeric($end_time)) {
            if($allowFutureTimes) {
                //nothing to do here
                $isValid = true;
            } else {
                $isValid = $end_time <= strtotime("11:59:59 pm");
            }
        } else {
            //if neither start or end time is passed in, it's technically a valid time
            $isValid = true;
        }

        return $isValid;
    }

    // given a value (ip, or host), return the host value
    public function findHostByValue($value, $sid = NULL) {
        $foundHost = NULL;
        $hosts = $this->BP->get_host_list($sid);
        if ($hosts !== false){
            foreach ($hosts as $hostName) {
                $host = $this->BP->get_host_info($hostName, $sid);
                if ($host !== false && $host['ip'] === $value) {
                    $foundHost = $host;
                    break;
                }
                else if ($host !== false && $host['name'] === $value) {
                    $foundHost = $host;
                    break;
                }
            }
        }
        return $foundHost;
    }

    public function isClientWindows($os_id) {
        $is_windows = false;
        switch ($os_id) {
            case Constants::OS_WINDOWS_2016:
            case Constants::OS_WINDOWS_10:
            case Constants::OS_WINDOWS_2012_R2:
            case Constants::OS_WINDOWS_8_1:
            case Constants::OS_WINDOWS_2012:
            case Constants::OS_WINDOWS_8:
            case Constants::OS_WINDOWS_7:
            case Constants::OS_WIN_2008_R2:
            case Constants::OS_WINDOWS_2008:
            case Constants::OS_WINDOWS_VISTA:
            case Constants::OS_WINDOWS_2003:
            case Constants::OS_WINDOWS_XP:
            case Constants::OS_WINDOWS_2000:
            case Constants::OS_WINDOWS_95:
            case Constants::OS_WIN_16:
                $is_windows = true;
                break;
        }
        return $is_windows;
    }

    public function getInstanceNames($iid, $sid)
    {
        $iNames = array();
        $instanceInfo = $this->BP->get_appinst_info($iid, $sid);
        if ($instanceInfo !== false) {
            foreach ($instanceInfo as $info) {
                $iNames = $this->getNamesForType($info);
                return $iNames;
            }
        }
    }


    public function getInstancesNames($iid, $sid)
    {
        if (is_array($iid)) {
            $iid = implode(',', $iid);
        }
        $instances = array();
        $instanceInfo = $this->BP->get_appinst_info($iid, $sid);
        if ($instanceInfo !== false) {
            foreach ($instanceInfo as $id => $info) {
                $iNames = $this->getNamesForType($info);
                $iNames['id'] = $id;
                $iNames['client_id'] = $info['client_id'];
                $instances[] = $iNames;
            }
        }
        return $instances;
    }

    private function getNamesForType($info) {
        $iNames = array();

        $appType = $info['app_type'];
        switch ($appType) {

            case Constants::APPLICATION_TYPE_NAME_HYPER_V:
            case Constants::APPLICATION_TYPE_NAME_EXCHANGE:
            case Constants::APPLICATION_TYPE_NAME_FILE_LEVEL:
            case Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL;
            case Constants::APPLICATION_TYPE_NAME_ORACLE:
            case Constants::APPLICATION_TYPE_NAME_SHAREPOINT:
            case Constants::APPLICATION_TYPE_NAME_NDMP_DEVICE:
            case Constants::APPLICATION_TYPE_NAME_AHV:

                $iNames = array(
                    'app_type' => $appType,
                    'app_name' => $info['app_name'],
                    'app_id' => $info['app_id'],
                    'asset_name' => $info['primary_name'],
                    'client_name' => $info['client_name']
                );
                break;

            case Constants::APPLICATION_TYPE_NAME_SQL_SERVER:
                $iNames = array(
                    'app_type' => $appType,
                    'app_name' => $info['app_name'],
                    'app_id' => $info['app_id'],
                    'asset_name' => $info['secondary_name'],
                    'client_name' => $info['client_name']
                );
                break;

            case Constants::APPLICATION_TYPE_NAME_VMWARE:
                $iNames = array(
                    'app_type' => $appType,
                    'app_name' => $info['app_name'],
                    'app_id' => $info['app_id'],
                    'asset_name' => $info['secondary_name'],
                    'client_name' => $info['primary_name']
                );
                break;

            case Constants::APPLICATION_TYPE_NAME_SYSTEM_METADATA:
                $iNames = array(
                    'app_type' => $appType,
                    'app_name' => $info['app_name'],
                    'app_id' => $info['app_id'],
                    'asset_name' => $info['app_name'],
                    'client_name' => $info['client_name']
                );
                break;

            case Constants::APPLICATION_TYPE_NAME_ARCHIVE:
                $iNames = array(
                    'app_type' => $appType,
                    'app_name' => $info['app_name'],
                    'app_id' => $info['app_id'],
                    'asset_name' => $info['primary_name'],
                    'client_name' => $info['client_name']
                );
                break;

            case Constants::APPLICATION_TYPE_NAME_XEN:
                $iNames = array(
                    'app_type' => $appType,
                    'app_name' => $info['app_name'],
                    'app_id' => $info['app_id'],
                    'asset_name' => $info['secondary_name'],
                    'client_name' => $info['client_name']
                );
                break;

            case Constants::APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE:
                $iNames = array(
                    'app_type' => $appType,
                    'app_name' => $info['app_name'],
                    'app_id' => $info['app_id'],
                    'asset_name' => ($info['client_name'] . "'s " . $info['primary_name']),
                    'client_name' => $info['client_name']
                );
                break;
        }
        return $iNames;
    }

    public function isNAS($name)
    {
        return (($temp = strlen($name) - strlen(Constants::NAS_POSTFIX)) >= 0 && strpos($name, Constants::NAS_POSTFIX, $temp) !== FALSE);
    }

    public function importSets($mediaName, $sid) {
        $result = array();
        $result['storage'] = array();
        $connectedResult = $this->BP->get_connected_archive_media($sid);
        if($connectedResult !== false) {
            foreach($connectedResult as $media) {
                if(strcmp($media['name'], $mediaName) == 0 ) {
                    $mediaSets = $this->BP->get_media_archive_sets($mediaName, "", $sid);
                    if($mediaSets !== false) {
                        $numSets = 0;
                        foreach($mediaSets as $set) {
                            if($set['needs_import'] === true) {
                                $numSets++;
                            }
                        }
                        $result['storage']['sets_existing'] = count($mediaSets);
                        $result['storage']['sets_needing_import'] = $numSets;
                        if($numSets > 0) {
                            $importResult = $this->BP->import_archive_catalog($mediaName, "", false, $sid);
                            if($importResult !== false) {
                                $setsImported = count($importResult['ids']);
                                $result['storage']['sets_imported'] = $setsImported;
                                if($numSets > $setsImported) {
                                    $result['storage']['messages'] = $importResult['messages'];
                                }
                            }
                        }
                    }
                    break;
                }
            }
        } else {
            $result['storage']['messages'] = array("Failed to import sets: " . $this->BP->getError());
//                 print_r($this->BP->getError());
        }
        return $result;
    }

    // converts to a number which is - '$number' divided by '$divisor' with a precision of '$digits'
    public function formatNumber($number, $divisor, $digits = 0) {
        $formattedNumber = 0;
        if ($digits > 0) {
            $formattedNumber = round(($number / $divisor), $digits);
        } else {
            $formattedNumber = $number / $divisor;
        }
        return $formattedNumber;
    }

    /*
     * Authenticate to the remote system using stored credential information per the given target.
     * (Default credentials are created on the fly if non-existent, which may or not authenticate.)
     * Upon success, first cache the remotely generated authentication token and source id locally
     * for subsequent requests, then return them to the caller for use.
     */
    public function remoteAuthenticate($targetName, $remote_type = "target") {
        global $Log;

        if ($remote_type === 'target') {

            $sid = $this->BP->get_local_system_id();

            $targetCreds = array();
            $targetCredsAll = $this->BP->get_target_credentials($targetName, $sid);
            if (is_array($targetCredsAll[0])) {
                $targetCreds = $targetCredsAll[0];
            }

            if ( $targetCreds !== false && !empty($targetCreds) ) {
                $info['username'] = $targetCreds['target_login_name'];
                $info['password'] = trim(base64_decode($targetCreds['password']));
            } else {
                $Log->writeVariable("No credentials obtained for the target appliance $targetName");
                return "Cannot get credentials of target appliance.";
            }
        } else if ($remote_type == "rdr") {
            $sid = $this->BP->get_local_system_id();
            $sysInfo = $this->BP->get_system_info($sid);
            $allCreds = $this->BP->get_credentials_list();
            $rdrCreds = null;
            foreach($allCreds as $cred){
                if($cred['username'] == $sysInfo['name'] . Constants::RDR_SUFFIX){
                    $id = $cred['credential_id'];
                    $rdrCreds = $this->BP->get_credentials_for_rdr($id);
                    break;
                }
            }

            if ($rdrCreds == false){
                // Attempt to create rdr credentials
                $rdrCredsId = $this->BP->configure_remote_rdr_manager();
                if ($rdrCredsId == false){
                    return "Cannot get rdr credentials";
                }
                $rdrCreds = $this->BP->get_credentials_for_rdr($rdrCredsId);

                if ($rdrCreds == false){
                    return "Cannot get rdr credentials";
                }
            }
            $info['username'] = $rdrCreds['username'];
            $info['password'] = trim(base64_decode($rdrCreds['password']));

        }

        $curl = curl_init();
        $url = "https://" . $targetName . "/api/login";
        $credentials = array("username" => $info['username'], "password" => $info['password']);
        $credentials_string = json_encode($credentials);
        $Log->writeVariable("authenticating, url is $url");
        $Log->writeVariableDBG("authenticating, auth is $credentials_string");
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $credentials_string);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json')
        );
        $result = curl_exec($curl);
        if ($result == false) {
            $Log->writeVariable("the curl request to authenticate failed: ");
            $Log->writeVariable(curl_error($curl));
            $result = "Attempt to connect to remote appliance failed.  Please ensure the appliance is powered on and its network address is resolvable.";
        } else {
            // return as a string
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $Log->writeVariable('curl request: http code');
            $Log->writeVariable($httpcode);
            $result = json_decode($result, true);
            if (($remote_type == 'rdr') && ($httpcode != 201)){
                // Attempt to create remote RDR user
                $systems = $this->BP->get_system_list();
                $remoteSID = null;
                foreach($systems as $key=>$sys){
                    if($sys == $targetName){
                        $remoteSID = $key;
                        break;
                    }
                }
                if ($remoteSID != false){
                    if ($this->BP->configure_remote_rdr_managee($remoteSID,$sysInfo['name']) != false) {
                        $Log->writeVariable("RDR user created");
                        // Remote RDR user created, try call again
                        $result = curl_exec($curl);
                        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                        $Log->writeVariable('curl request: http code');
                        $Log->writeVariable($httpcode);
                        $result = json_decode($result, true);
                    } else {
                        $Log->writeVariable("RDR user creation failed.");
                    }
                }
            }
            if ($httpcode == 201) {
                // Successfully logged into the remote appliance!!
                // Set the results, and cache the new token
                // The save cache API can fail if the process owner (e.g. populate_alerts) does not have proper credentials
                $result['remote_token'] = $result['auth_token'];
                $auth_token = $result['auth_token'];
                $source_id  = $result['source_id'];
                if ($remote_type == 'rdr'){
                    // append suffix to rdr tokens to avoid name collision
                    $targetName = $targetName . Constants::RDR_SUFFIX;
                    // rdr doesn't use the source_id, but the tokening system rejects null source_id
                    $source_id = 0;
		}
                $ret = $this->BP->save_target_token($targetName, $auth_token, $source_id);
                // set cached status which will dictate to the caller if a logout is needed
                if ($ret === true) {
                    $result['cached'] = true;
                }
                else {
                    $result['cached'] = false;
                }
            } else {
                $result = $result['result'];
                if (is_array($result[0]) && isset($result[0]['message'])) {
                    $result = $result[0]['message'];
                } else {
                    $result = "Attempt to connect to remote appliance failed (error code: " . $httpcode . ").";
                }
            }
        }
        curl_close($curl);

        return $result;
    }

    /*
     * Given the specified auth_token, log out of the remote session.
     */
    public function remoteLogout($ip, $token) {
        $data = array("cookie" => $token);
        $result = $this->remoteRequest($ip, "POST", "/api/logout", "", $data, NULL, $token);
        return $result;
    }

    /*
     * Returns the name of the backup copy target or an empty string if there is no target.
     * Currently assumes the first target is the one to which we want to communicate.
     * Once we have multiple targets, this will need to be specified with an optional parameter.
     */
    public function getTargetName($all = false) {
        $name = '';
        $targets = $this->BP->get_replication_targets();
        if ($targets !== false) {
            if (!$all) {
                if (count($targets) > 0) {
                    $name = $targets[0]['host'];
                }
            } else {
                for ($i = 0; $i < count($targets); $i++){
                    $name[] = array('target_id' => $targets[$i]['target_id'],
                                    'target_name' => $targets[$i]['host']);
                }
            }
        }
        return $name;
    }

    public function getLocalSystemName() {
        $name = "target";
        $localID = $this->BP->get_local_system_id();
        $systemInfo = $this->BP->get_system_info($localID);
        if ($systemInfo !== false) {
            $name = $systemInfo['name'];
        }
        return $name;
    }

    /*
     * Locate a valid (non-expired) cached token and remote source id per the given target.
     * If non-existent, a remoteAuthenticate (aka login) is required.
     */
    function getLocallyCachedTargetInfo($targetName, $remote_type = "target")
    {
        global $Log;

        $results = array();

        // The rdr token appends suffix to avoid name collision
        if ($remote_type == "rdr"){
            $targetName = $targetName . Constants::RDR_SUFFIX;
        }
        // get the cached token and our source id as seen on the target from our local appliance
        $cached = $this->BP->get_target_token($targetName);

        if ($cached !== false) {
           // set the results, caller is expecting auth_token and source_id
           $results = array("auth_token" => $cached['token'], "source_id" => $cached['remote_src_id']);
        }

        return $results;
    }

    /*
    * Checks if the current user has permissions to perform the operations
    */
    function checkUserPrivileges($request, $api, $sid) {
        $applicable = true;

        // list of APIs that aren't allowed for monitor user
        $apiList = array("GET /api/backups/search/", "GET /api/backups/synthesized-files/", "GET /api/backups/files/", "GET /api/backups/related/", "GET /api/backups/dependent/",
            "GET /api/clients/files/", "GET /api/clients/", "GET /api/clients/system/", "POST /api/replication/queue/",
            "GET /api/restore/group/", "POST /api/restore/full/", "POST /api/restore/download/", "POST /api/restore/download-files/");

        $str = $request . " " . $api;

        if ($this->BP->isBypassCookie()){
            $currentUser = false;
        } else {
            $currentUser = $this->BP->getUser();
        }
        if ($currentUser !== false) {
            foreach($currentUser['systems'] as $sys) {
                if ($sys['id'] = $sid) {
                    // any user with privileges less than manage shouldn't be allowed to run the APIs
                    if ($sys['privilege_level'] < Constants::PRIV_MANAGE) {
                        foreach ($apiList as $apilist) {
                            if (strpos($str, $apilist) !== false) {
                                $applicable = false;
                            }
                        }
                    } else {
                        $applicable = true;
                    }
                }
            }
        }
        return $applicable;
    }

    /*
     * Runs a remote REST API on the IP if specified, with a request type of "GET", "POST", "PUT", or "DELETE".
     * If no auth token or sid specified, look to see if it is cached in our local appliance database.
     * If no db cache, look to see if they are passed in via the header. (future)
     * If not db cached or in the header, authenticate to the target, and get the token and remote sid. Then cache them.
     *
     * Returns either an array of results, or a failure message (a string) which is mostly ignored and just treated as no results.
     */
    function remoteRequest($ip, $request, $api, $parameters, $data = NULL, $sid = false, $auth_token = NULL, $force_authenticate = true, $returnRaw = false, $remote_type = "target") {
        global $Log;

        $need_to_authenticate = false; // also dictates logouts per caching results.
        $retried_failed_login = false;
        $failed_login_message = "Error: Your session has expired or is invalid";
        $source_id = false;

        if ($ip === "") {
            $target = $this->getTargetName();
            $ip = $target;
        } else if ($ip === "all") {
            $all = true;
            //handling 1:N - loop through all the targets
            $target = $this->getTargetName($all);
            $ip = $target;

        } else {
            // per the ip address, get the name, which should be a target if things are going to work
            $host = $this->findHostByValue($ip);
            if (is_array($host) && isset($host['name'])) {
                $target = $host['name'];
                $Log->writeVariableDBG("remote request: found host $target by ip $ip");
            } else {
                $errorMessage = "No host found for the provided IP address $ip, so no remote request will run.";
                $Log->writeVariable("remote request: $errorMessage");
                return $errorMessage;
            }
        }

        if ($ip === "") {
            $errorMessage = "No IP specified or target host found, so no remote request will run.";
            $Log->writeVariable("remote request: $errorMessage");
            return $errorMessage;
        }
        // auth token and sid provided? If not, check the cache.

        if ($auth_token === NULL || $sid === false) {

            // TODO:  this is incomplete and will need to be finished for 1:N.  The cached token/source id will been need to authenticate the source on the target for selfserve.
            if (is_array($target)){
                //handle for multiple targets;
                $cached = array();
                for ($i = 0; $i < count($target); $i++){
                    $cached[] = $this->getLocallyCachedTargetInfo($target[$i]['target_name'], $remote_type);
                }
                $cachedInfo['cached'] = $cached;
                print_r($cachedInfo);

            } else {
                $cachedInfo = $this->getLocallyCachedTargetInfo($target, $remote_type);
            }

            if (is_array($cachedInfo['cached'] && count($cachedInfo['cached']) > 1)){
               print_r(count($cachedInfo['cached']));
            }
            if (is_array($cachedInfo) && isset($cachedInfo['auth_token']) && isset($cachedInfo['source_id'])) {
                $auth_token = $cachedInfo['auth_token'];
                $source_id = $cachedInfo['source_id'];
            }
        } else {
            // the assumption here is if an auth_token was provided, then the incoming sid must be the remote source id, so set it
            $Log->writeVariable("remote request: provided sid $sid being used as the remote source id too.");
            $source_id = $sid;
        }

        // Locally cached tokens expiration are based on source settings but may fail a remote request if the target expiration
        // settings are shorter. The retry loop label below is used for recovering from this situation (or any login failure).

        RETRY_FAILED_LOGIN_1X:

        // auth token and source id cached? If not, check headers, then remote authenticate.
        if ($auth_token === NULL || $source_id === false) {
            if (!$force_authenticate) {
                return "You must first connect to the target appliance in order to run this command";
            }
            $need_to_authenticate = true;
            // NOTE: support for target_token and source_id cached in the request header is a potential future enhancement,
            // but less likely given the database caching scheme introduced in Satori 9.1.
            $request_headers = getallheaders();
            $target_token = isset($request_headers['TargetToken']) ? $request_headers['TargetToken'] : "";
            $source_id = isset($request_headers['SourceID']) ? $request_headers['SourceID'] : "";
            if ($target_token === "" || $source_id === "") {
                $auth_result = $this->remoteAuthenticate($ip,$remote_type);
                if (is_array($auth_result)) {
                    $auth_token = $auth_result['auth_token'];
                    $source_id = $auth_result['source_id'];
                    if (isset($auth_result['cached']) && $auth_result['cached'] === true) {
                        // new token was cached, don't issue a logout to the remote site (which wipes out the token)
                        $need_to_authenticate = false;
                    }
                } else {
                    // failure case
                    return $auth_result;
                }
            } else {
                $auth_token = $target_token;
            }
        }

        // If no local sid provided, use the obtained remote value. This will dictate what systems and client
        // list is generation the target, which isn't really needed for the remote requests. The remote requests
        // are more about the presence of the source_id.
        if ($sid === false) {
            $sid = $source_id;
        }

        // check if the current user has privileges to run the request
        if ($this->checkUserPrivileges($request, $api, $sid)) {

            // After authentication, run the request.
            $curl = curl_init();
            $base_url = "https://" . $ip;
            $url = $base_url . $api;
            $separator = "?";
            if ($sid !== NULL) {
                $url .= $separator . "sid=" . $sid;
                $separator = "&";
            }
            if ($parameters !== "") {
                $url .= $separator . $parameters;
                $separator = "&";
            }
            if (isset($source_id) && $source_id !== '') {
                $url .= $separator . "source_id=" . $source_id;
                $separator = "&";
            }

            // Debug
            $Log->writeVariableDBG("remote request: url is $url");
            $Log->writeVariableDBG("remote request: auth is $auth_token");

            $url = str_replace(' ', '%20', $url);
            $url = str_replace('+', '%2B', $url);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $request);
            if ($request === 'PUT' || $request === 'POST') {
                if ($data !== NULL) {
                    $payload = json_encode($data);
                    $Log->writeVariableDBG("remote request: data is $payload");
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
                }
            };
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'AuthToken: ' . $auth_token
                )
            );
            $result = curl_exec($curl);
            if ($result == false) {
                $Log->writeVariable("the curl request failed: ");
                $Log->writeVariable(curl_error($curl));
                $message = "An unexpected failure occurred while running a request between the source and target. Please try your request again.\n\n
                If this continues to fail, please examine the latest /usr/bp/logs.dir/api_*.log files on the source appliance for more details.";
                $result = array();
                $result['error'] = 500;
                $result['message'] = $message;
            } else {
                // return as a string
                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $Log->writeVariable('curl request: http code');
                $Log->writeVariable($httpcode);
                if (!$returnRaw || ($returnRaw && $httpcode !== 201 && $httpcode !== 200)) {
                    $result = json_decode($result, true);
                }
                if (($request == "POST" && $httpcode == 201) ||
                    (($request == "GET" || $request == "PUT" || $request == "DELETE") && $httpcode == 200)
                ) {
                    // Okay (200) for GET, PUT, DELETE.  Created (201) for POST.
                    if (!$returnRaw) {
                        if (is_array($result) && $need_to_authenticate == true) {
                            // include the token if we had to authenticate.
                            $result['remote_token'] = $auth_token;
                        }
                        if (is_array($result)) {
                            $result['target_name'] = $ip;
                        }
                    }
                } else {
                    $result = $result['result'];
                    if (is_array($result[0]) && isset($result[0]['message'])) {
                        $message = $result[0]['message'];
                        if ($retried_failed_login == false) {
                            // Check for expired tokens results, and retry using a new token
                            if (strstr($message, $failed_login_message)) {
                                $retried_failed_login = true;
                                $auth_token = NULL;
                                curl_close($curl);
                                goto RETRY_FAILED_LOGIN_1X;
                            }
                        }
                    } else {
                        $message = "Attempt to run remote curl command failed (error code: " . $httpcode . ").";
                    }
                    $result = array();
                    $result['error'] = 500;
                    $result['message'] = $message;
                }
            }
            curl_close($curl);

            if ($need_to_authenticate && isset($auth_token)) {
                // Note: the ip value here is most likely a host name
                $this->remoteLogout($ip, $auth_token);
            }
            return $result;
        } else {
            $result = array();
            $result['error'] = 500;
            $result['message'] = "Insufficient privileges";
            return $result;
        }
    }

    /*
     * remoteRequest wrapper for RDR
     */
    function remoteRequestRDR($ip, $request, $api, $parameters, $data = NULL) {
        return $this->remoteRequest($ip, $request, $api, $parameters, $data, null, null, true, null, 'rdr');
    }

    public function getOSFamily($osTypeID) {
        switch($osTypeID) {
            case Constants::OS_DOS:
                $osFamily = Constants::OS_FAMILY_DOS;
                break;
            case Constants::OS_OS_2:
                $osFamily = Constants::OS_FAMILY_OS2;
                break;
            case Constants::OS_UNIX3:
            case Constants::OS_UNIX4:
            case Constants::OS_SUN_OS:
            case Constants::OS_OSF1:
            case Constants::OS_DGUX:
            case Constants::OS_BSDI:
            case Constants::OS_USL:
            case Constants::OS_UNIXWARE:
                $osFamily = Constants::OS_FAMILY_UNIX;
                break;
            case Constants::OS_SOLARIS:
                $osFamily = Constants::OS_FAMILY_SOLARIS;
                break;
            case Constants::OS_OS5:
            case Constants::OS_ODT:
                $osFamily = Constants::OS_FAMILY_SCO;
                break;
            case Constants::OS_AIX:
                $osFamily = Constants::OS_FAMILY_AIX;
                break;
            case Constants::OS_SGI:
                $osFamily = Constants::OS_FAMILY_SGI;
                break;
            case Constants::OS_HP_UX:
                $osFamily = Constants::OS_FAMILY_HPUX;
                break;
            case Constants::OS_LINUX:
                $osFamily = Constants::OS_FAMILY_LINUX;
                break;
            case Constants::OS_FREEBSD:
                $osFamily = Constants::OS_FAMILY_FREE_BSD;
                break;
            case Constants::OS_MACOS:
                $osFamily = Constants::OS_FAMILY_MAC_OS;
                break;
            case Constants::OS_IBMI5:
                $osFamily = Constants::OS_FAMILY_I_SERIES;
                break;
            case Constants::OS_OES:
                $osFamily = Constants::OS_FAMILY_OES;
                break;
            case Constants::OS_NOVELL:
            case Constants::OS_NOVELL_4:
            case Constants::OS_NOVELL_5:
            case Constants::OS_Novell_6:
            case Constants::OS_NOVELL_65:
                $osFamily = Constants::OS_FAMILY_NOVELL_NETWARE;
                break;
            case Constants::OS_WIN_16:
            case Constants::OS_NT:
            case Constants::OS_WINDOWS_95:
            case Constants::OS_NT_SERVER:
            case Constants::OS_WINDOWS_2000:
            case Constants::OS_WINDOWS_XP:
            case Constants::OS_WINDOWS_2003:
            case Constants::OS_WINDOWS_VISTA:
            case Constants::OS_WINDOWS_2008:
            case Constants::OS_WIN_2008_R2:
            case Constants::OS_WINDOWS_7:
            case Constants::OS_WINDOWS_8:
            case Constants::OS_WINDOWS_2012:
            case Constants::OS_WINDOWS_8_1:
            case Constants::OS_WINDOWS_2012_R2:
            case Constants::OS_WINDOWS_10:
            case Constants::OS_WINDOWS_2016:
                $osFamily = Constants::OS_FAMILY_WINDOWS;
                break;
            case Constants::OS_GENERIC:
                $osFamily = Constants::OS_FAMILY_GENERIC;
                break;
            default:
                $osFamily = Constants::OS_FAMILY_OTHER;
                break;
        }
        return $osFamily;
    }

    // Returns the current user's privilege level for the local system
    public function getCurrentPrivileges()
    {
        $sid = $this->BP->get_local_system_id();
        if ($this->BP->isBypassCookie()){
            // local request, grant SU privs
            return Constants::PRIV_SUPERUSER;
        }
        $user = $this->BP->getUser();
        if ($user['superuser']) {
            return Constants::PRIV_SUPERUSER;
        }
        foreach($user['systems'] as $sys){
            if ($sys['id'] = $sid){
                return $sys['privilege_level'];
            }
        }
        //no privilege found for local system
        return Constants::PRIV_NONE;
    }

    /*
     * Checks to see if the roles file is present and includes it (once) if so.
     * Returns true if it is present and false if not.
     */
    public static function supportsRoles() {
        $roles = false;
        $rolesFile = $_SERVER['DOCUMENT_ROOT'] . '/api/includes/roles.php';
        if (file_exists($rolesFile)) {
            include_once($rolesFile);
            $roles = true;
            //global $Log;
            //$Log->writeVariable("Roles SUPPORTED");
        }
        return $roles;
    }

    /*
     * Returns whether or not we want to show Policies for the appliance.
     */
    public function showSLAPolicies($sid) {
        $mode = $this->BP->get_ini_value("CMC", "showSLAPolicies", $sid);
        if ($mode !== false) {
            $mode = strtolower($mode) == 'true' || strtolower($mode) == 'yes';
        }
        return $mode;
    }


    // Grant management access to this appliance on the remote.
    public function grantManagementToLocalSystem($ip, $username, $password) {
        $granted = true;
        $localHostInfo = $this->BP->get_hostname();
        if ($localHostInfo !== false) {
            $host = $localHostInfo['name'];
            $grantCommand = sprintf("/usr/bp/bin/rungrant.php  '%s'  '%s'  '%s'  '%s'", $ip, $username, $password, $host);
          //  global $Log;
          //  $Log->writeVariable("command is " . $grantCommand);
            exec($grantCommand, $outputArray, $returnValue);
            // if $returnValue is 0, okay, leave $granted as true.
            if ($returnValue !== 0) {
                $granted = implode("\n", $outputArray);
            }
        } else {
            $granted = "Could not determine local appliance host name.";
        }
        return $granted;
    }

    /*
     * Checks to see that the input parameter contains no invalid characters.
     *
     * Inputs: $value to be checked, either a scalar or an array,
     *          $caller, string of name of calling API, i.e., "PUT hosts".
     *
     * Returns true if the payload is valid
     * Returns a string with the invalid parameter if the payload is not valid.
     */
    public function isValidPayload($value, $caller) {
        $valid = true;
        global $Log;
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $valid = $this->isValidPayload($v, $caller);
                if ($valid !== true) {
                    break;
                }
            }
        } else {
            $valid = $this->isValidToken($value);
            if ($valid === false) {
                $valid = "Illegal characters detected in value for " . $value;
                $Log->writeError($caller . ": invalid input detected for " . $value, false, true);
            }
        }
        return $valid;
    }

    /*
     * Checks to see that the input token (must be a string) contains no invalid characters.
     *
     * Returns true if the payload is valid
     * Returns false if the payload has invalid characters.
     */
    private static $invalidChars = array("`");
    private function isValidToken($value) {
        $valid = true;
        if (is_string($value)) {
            foreach (self::$invalidChars as $char) {
                if (strpos($value, $char) !== false) {
                    $valid = false;
                    break;
                }
            }
        }
        return $valid;
    }

    /*
     * Sorts the specified $objectArray given a $sortKey.
     * Case-insensitive unless specified otherwise.
     * Sorted by string unless specified otherwise.
     */
    public function sortByKey($objectArray, $sortKey, $caseInsensitive = true, $sortType = SORT_STRING) {
        $Info = $objectArray;
        $orderByKey = array();
        foreach ($Info as $key => $row) {
            $value = $caseInsensitive ? strtolower($row[$sortKey]) : $row[$sortKey];
            $orderByKey[$key] = $value;
        }
        array_multisort($orderByKey, $sortType, $Info);
        return $Info;
    }

}
