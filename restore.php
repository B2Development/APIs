<?php

class Restores
{
    private $BP;

    // this limit comes from the legacy UI's restorefiles.php
    const CHAR_LIMIT = 8191;

    const DEFAULT_MAX_DOWNLOAD_SIZE_MB = 500;

    public function __construct($BP)
    {
        $this->BP = $BP;

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);

        $this->Roles = null;
    }

    public function get($which, $data, $sid, $systems)
    {
        if (is_string($which[0]))
        {
            switch ($which[0])
            {
                case 'targets':
                    return $this->get_restore_targets($data, $sid);
                    break;
                case 'group':
                    return $this->get_restore_group($data, $sid);
                    break;
                case 'target-group':
                    return $this->getRestoreGroupOnTarget($data, $sid);
                    break;
            }
        }
    }

    public function get_restore_targets($data, $sid)
    {
        $targets = array();
        if ( $sid === false )
        {
            $sid = $this->BP->get_local_system_id();
        }

        $application_type = false;
        $instance_id = false;
        $return_all_servers = false;
        $backup_type = '';

        $replicated = (isset($data['replicated']) && $data['replicated'] == 1) ? true : false;

        // If both an instance_id and backup_id are passed in, ignore the backup_id
        if ( array_key_exists('instance_id', $data) )
        {
            $instance_id = (int)$data['instance_id'];
            if ( $instance_id === 0 )
            {
                $return_all_servers = true;
            }
            else
            {
                $appinst_info_array = $this->BP->get_appinst_info( $instance_id, $sid );
                if ( $appinst_info_array !== false and count($appinst_info_array) > 0 )
                {
                    foreach ( $appinst_info_array as $appinst_info )
                    {
                        $application_type = $appinst_info['app_type'];

                        //  There should only be one instance id, so no reason to go through a loop.  Throw out additional results from the array.
                        break;
                    }
                }
            }
        }
        else if ( array_key_exists('backup_id', $data) )
        {
            $backup_types = $this->getBackupDetails( array( (int)$data['backup_id'] ), $sid );
            if ( $backup_types !== false and count($backup_types) > 0 )
            {
                foreach ( $backup_types as $backup_type_array )
                {
                    $application_type = $this->functions->getApplicationTypeFromApplictionID( $backup_type_array['app_id'] );
                    $instance_id = $backup_type_array['instance_id'];
                    $backup_type = $backup_type_array['type'];
                    $instance_name = $backup_type_array['instance_name'] !== false ? $backup_type_array['instance_name'] : "";

                    //  There should only be one backup id, so no reason to go through a loop.  Throw out additional results from the array.
                    break;

                }
            }
        }
        else if (array_key_exists('app_type', $data)) {
            // Allow user to specify an app_type when retrieving targets.
            $application_type = $data['app_type'];
            $instance_id = 0;
        }
        else
        {
            $return_all_servers = true;
        }

        if ( $return_all_servers === true )
        {
            $hyper_v_recovery_clients = array();

            // For Hyper-V, get all of the servers using bp_get_hyperv_servers_for_wir, then pass in those results to get_Hyper_V_restore_targets_array
            // Using DOS as a fill in
            $hyper_v_servers = $this->BP->get_hyperv_servers_for_wir( Constants::OS_DOS, $sid );

            if ( $hyper_v_servers !== false and count($hyper_v_servers) > 0 )
            {
                foreach ( $hyper_v_servers as $hyper_v_server )
                {
                    $hyper_v_recovery_clients[$hyper_v_server['client_id']] = $hyper_v_server['name'];
                }
            }

            //Get the Hyper-V targets
            $targets = $this->get_Hyper_V_restore_targets_array( $sid, false, $hyper_v_recovery_clients, $hyper_v_servers );

            // Now merge in the VMware results
            $targets = array_merge( $targets, $this->get_VM_restore_targets_array( 0, $sid ) );
        }
        elseif ( $application_type !== false and $instance_id !== false )
        {
            switch ( $application_type )
            {
                case Constants::APPLICATION_TYPE_NAME_HYPER_V:
                    $targets = $this->get_Hyper_V_restore_targets_array( $sid, $instance_id, false, false, $replicated, $data );
                    break;
                case Constants::APPLICATION_TYPE_NAME_VMWARE:
                    $targets = $this->get_VM_restore_targets_array( $instance_id, $sid );
                    break;
                case Constants::APPLICATION_TYPE_NAME_AHV:
                    $targets = $this->get_ahv_restore_targets_array($sid);
                    break;
                case Constants::APPLICATION_TYPE_NAME_EXCHANGE:
                    if(isset($data['client_id'])) {
                       $clientID = (int)$data['client_id'];
                    } else {
                        $clientID = 0;
                        $clients = $this->getClientIDFromBackup(array((int)$data['backup_id']), $sid);
                        if ($clients !== false) {
                            foreach ($clients as $client) {
                                $clientID = $client;
                                break;
                            }
                        }
                    }

                    $db = $this->getExchangeDBFromBackup(array((int)$data['backup_id']), $sid);

                    $targets = $this->get_exchange_restore_targets_array( $clientID, $db, 1, $sid );
                    break;
                case Constants::APPLICATION_TYPE_NAME_ORACLE:
                    if ($replicated === true) {
                        $targets = array();
                    } else {
                        // restore to original server
                        $clientID = 0;
                        $clients = $this->getClientIDFromBackup(array((int)$data['backup_id']), $sid);
                        if ($clients !== false) {
                            foreach ($clients as $client) {
                                $clientID = $client;
                                break;
                            }
                        }
                        $targets = $this->get_oracle_restore_targets((int)$instance_id, $clientID, $sid);
                    }
                    break;
                case Constants::APPLICATION_TYPE_NAME_SHAREPOINT:
                    if ($replicated === true) {
                        $targets = array();
                    } else {
                        // restore to original server
                        $targets = $this->get_sharepoint_restore_targets((int)$instance_id, $sid);
                    }
                    break;
                case Constants::APPLICATION_TYPE_NAME_SQL_SERVER:
                    $targets = $this->get_sql_restore_targets((int)$data['backup_id'], (int)$instance_id, $instance_name, $replicated, $sid);
                    break;
                case Constants::APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE:
                    $systemName = $this->functions->getSystemNameFromID($sid);
                    $targets = $this->get_cisco_ucs_restore_targets($sid, $systemName);
                    break;
                case Constants::APPLICATION_TYPE_NAME_XEN:
                    $targets = $this->get_xen_restore_targets($sid);
                    break;
                case Constants::APPLICATION_TYPE_NAME_NDMP_DEVICE:
                    if(isset($data['ndmp_target_client_id'])) {
                        $targets = array_values($this->BP->get_ndmp_restore_target_volumes((int)$data['ndmp_target_client_id'], (int)$data['backup_id'], $sid));
                    } else {
                        $targets = $this->get_ndmp_restore_targets((int)$data['backup_id'], $sid);
                    }
                    break;
                case Constants::APPLICATION_TYPE_NAME_FILE_LEVEL:
                    $systemName = $this->functions->getSystemNameFromID($sid);
                    $targets = $this->get_file_level_restore_targets($sid, $systemName);
                    break;
            }
        }
        elseif ($backup_type !== '' && $this->functions->isBackupFileLevel($backup_type))
        {
            $systemName = $this->functions->getSystemNameFromID($sid);
            $targets = $this->get_file_level_restore_targets($sid, $systemName);
            if (Functions::supportsRoles()) {
                if (isset($backup_types) && count($backup_types) > 0) {
                    $this->Roles = new Roles($this->BP);
                    if ($this->Roles->originalOnly()) {
                        $targets = $this->get_original_server($targets, $backup_types[0]);
                    }
                }
            }
        }
        else
        {
            $targets = false;
        }


        return ( $targets !== false ? array( 'targets' => $targets) : false );
    }

    /*
     * Given a backup, return the original client from the list of targets.
     */
    private function get_original_server($targets, $backup)
    {
        $filteredTargets = array();
        if (isset($backup['client_id'])) {
            $clientID = $backup['client_id'];
            foreach ($targets as $target) {
                if (isset($target['client_id']) && $target['client_id'] != $clientID) {
                    continue;
                } else {
                    $filteredTargets[] = $target;
                }
            }
        }
        return $filteredTargets;
    }

    private function get_Hyper_V_restore_targets_array( $sid, $instance_id = false, $hyper_v_recovery_clients = false, $hyper_v_servers = false, $replicated, $data = null )
    {
        $hyper_v_targets_array = array();

        // If $instance_id is false, then $hyper_v_recovery_clients must not be equal to false
        if ( $instance_id !== false )
        {
            // this returns the original client on which we are trying to restore
            $hyper_v_application_clients = array();
            $temp_application_clients = $this->BP->get_application_clients( $instance_id, $sid );
            if ($temp_application_clients !== false) {
                if (array_key_exists('clients', $temp_application_clients)) {
                    $temp_clients = $temp_application_clients['clients'];
                    foreach ($temp_clients as $temp_client_id => $temp_client_name) {
                        $hyper_v_application_clients[$temp_client_id] = $temp_client_name;
                    }
                }
            }
            $recovery_clients = $this->BP->get_hyperv_recovery_clients( $instance_id, $sid ) ;
            $hyper_v_recovery_clients =  $hyper_v_application_clients + $recovery_clients;
            if (array_key_exists('backup_id', $data) && array_key_exists('target', $data)) {
                if ($data['target'] === 'original') {
                    $hyper_v_recovery_clients = array();
                    $clientID = 0;
                    $clients = $this->getClientIDFromBackup(array((int)$data['backup_id']), $sid);
                    if ($clients !== false) {
                        foreach ($clients as $client) {
                            $clientID = $client;
                            break;
                        }
                    }
                    foreach ($hyper_v_application_clients as $client_id => $client_name) {
                        if ($client_id == $clientID) {
                            $hyper_v_recovery_clients[$client_id] = $client_name;
                        }
                    }
                }
            } elseif (array_key_exists('type', $data)) {
                // Return only the servers that are eligible for Instant Recovery
                if ($data['type'] === 'ir') {
                    $hyper_v_recovery_clients = array();
                    $hyper_v_ir_clients = $this->BP->get_hyperv_servers_for_ir($instance_id, $sid);
                    if ($hyper_v_ir_clients !== false) {
                        foreach ($hyper_v_ir_clients as $hyper_v_ir_client) {
                            $hyper_v_recovery_clients[$hyper_v_ir_client['client_id']] = $hyper_v_ir_client['name'];
                        }
                    }
                } elseif ($data['type'] === 'restore') {
                    $hyper_v_recovery_clients = array();
                    $hyper_v_original_client = array();
                    $clientID = 0;
                    $clients = $this->getClientIDFromBackup(array((int)$data['backup_id']), $sid);
                    if ($clients !== false) {
                        foreach ($clients as $client) {
                            $clientID = $client;
                            break;
                        }
                    }
                    foreach ($hyper_v_application_clients as $client_id => $client_name) {
                        if ($client_id == $clientID) {
                            $hyper_v_original_client[$client_id] = $client_name;
                        }
                    }
                    // for replicated backups, restore to original server isn't supported
                    if ($replicated === true) {
                        $hyper_v_recovery_clients = $recovery_clients;
                    } else {
                        $hyper_v_recovery_clients = $hyper_v_original_client + $recovery_clients;
                    }
                }
            }
        }
        // This function call might have already been made, so no need to make it twice
        if ( $hyper_v_servers === false )
        {
            // Using DOS as a fill in
            $hyper_v_servers = $this->BP->get_hyperv_servers_for_wir( Constants::OS_DOS, $sid );
        }

        if ( $hyper_v_recovery_clients !== false and count($hyper_v_recovery_clients) > 0 )
        {
            $hyper_v_servers_info_array = array();

            if ( $hyper_v_servers !== false and count($hyper_v_servers) > 0 )
            {
                foreach ( $hyper_v_servers as $hyper_v_server )
                {
                    $hyper_v_servers_info_array[$hyper_v_server['client_id']] = $hyper_v_server['capabilities'];
                }
            }

            foreach ( $hyper_v_recovery_clients as $hyper_v_client_id => $hyper_v_client_name )
            {
                $temp_restore_target = array();
                $temp_restore_target['id'] = $hyper_v_client_id;
                $temp_restore_target['name'] = $hyper_v_client_name;

                $hyper_v_storage = $this->BP->get_hyperv_storage( $hyper_v_client_id, $sid );
                if ( $hyper_v_storage !== false and count($hyper_v_storage) > 0 )
                {
                    foreach ( $hyper_v_storage as $hyperv_storage )
                    {
                        $temp_storage = array();
                        $temp_storage['name'] = $hyperv_storage['name'];
                        // converting sizes in MB to GB
                        $temp_storage['total_size_gb'] = $this->functions->formatNumber($hyperv_storage['mb_size'], 1024, 1);
                        $temp_storage['free_size_gb'] = $this->functions->formatNumber($hyperv_storage['mb_free'], 1024, 1);

                        $temp_restore_target['datastores'][] = $temp_storage;
                    }
                }

                if ( array_key_exists( $hyper_v_client_id, $hyper_v_servers_info_array ) )
                {
                    $temp_restore_target['capabilities'] = $hyper_v_servers_info_array[$hyper_v_client_id];
                }

                $hyper_v_targets_array[] = $temp_restore_target;
            }
        }
        return $hyper_v_targets_array;
    }

    private function get_VM_restore_targets_array( $instance_id, $sid )
    {
        $vm_targets_array = array();
        $vm_restore_targets = $this->BP->get_vm_restore_targets( $instance_id, $sid );
        if ( $vm_restore_targets !== false and count($vm_restore_targets) > 0 )
        {
            $vm_servers = $vm_restore_targets['servers'];
            foreach ( $vm_servers as $vm_restore_target )
            {
                $temp_restore_target = array();
                $temp_restore_target['id'] = $vm_restore_target['uuid'];
                $temp_restore_target['name'] = $vm_restore_target['name'];
                $temp_restore_target['parent_id'] = $vm_restore_target['parent_uuid'];

                if ( array_key_exists('datastores', $vm_restore_target) and count($vm_restore_target['datastores']) > 0 )
                {
                    foreach ( $vm_restore_target['datastores'] as $vm_data_store )
                    {
                        $temp_storage = array();
                        $temp_storage['name'] = $vm_data_store['name'];
                        // converting sizes in MB to GB
                        $temp_storage['total_size_gb'] = $this->functions->formatNumber($vm_data_store['mb_size'], 1024, 1);
                        $temp_storage['free_size_gb'] = $this->functions->formatNumber($vm_data_store['mb_free'], 1024, 1);
                        $temp_storage['identifier'] = $vm_data_store['datastore_identifier'];

                        $temp_restore_target['datastores'][] = $temp_storage;
                    }
                }
                $temp_restore_target['groups'] = $this->get_vm_groups( $vm_restore_target['uuid'], $vm_restore_target['name'], $sid);
                $temp_restore_target['capabilities'] = $vm_restore_target['capabilities'];
                $vm_targets_array[] = $temp_restore_target;
            }
        } else {
            $vm_targets_array = false;
        }
        return $vm_targets_array;
    }

    private function get_ahv_restore_targets_array($sid, $systemName){
        $ahv_targets_array = array(); // return this
        $ahv_restore_targets = $this->BP->get_ahv_restore_targets($sid);

        // data store
        if ($ahv_restore_targets !== false) {
            foreach ($ahv_restore_targets as $ahv_host) {
                $temp_target_clients = array();
                $temp_target_clients['client_id'] = $ahv_host['client_id'];
                $temp_target_clients['id'] = $ahv_host['client_id'];
                $temp_target_clients['name'] = $ahv_host['client_name'];
                $temp_target_clients['version'] = $ahv_host['ahv_version'];

                if (isset($ahv_host['storage_containers']) && count($ahv_host['storage_containers']) > 0) {
                    foreach ($ahv_host['storage_containers'] as $ahv_storage) {
                        $temp_storage = array();
                        $temp_storage['id'] = $ahv_storage['uuid'];
                        $temp_storage['name'] = $ahv_storage['name'];
                        $temp_storage['total_size_gb'] = $ahv_storage['gb_total'];
                        $temp_storage['free_size_gb'] = $ahv_storage['gb_free'];

                        $temp_target_clients['datastores'][] = $temp_storage;
                    }
                }
                $ahv_targets_array[] = $temp_target_clients;
            }
        } else {
            $ahv_targets_array = false;
        }
        return $ahv_targets_array;
    }

    private function get_exchange_restore_targets_array( $node_id, $dbname, $ball, $sid )
    {
        $vm_targets_array = array();

        $vm_restore_targets = $this->BP->get_exchange_restore_targets( $node_id, $dbname, $ball, $sid );
        if ( $vm_restore_targets !== false and count($vm_restore_targets) > 0 )
        {
            foreach ( $vm_restore_targets as $vm )
            {
                $temp_restore_target = array();
                if ($vm['name'] === $dbname) {
                   if (!$vm['mounted'] && $vm['writable']) {
                       $temp_restore_target['mounted'] = $vm['mounted'];
                       $temp_restore_target['name'] = $vm['name'];
                       $temp_restore_target['writable'] = $vm['writable'];
                       $temp_restore_target['recovery'] = $vm['recovery'];
                       $vm_targets_array[] = $temp_restore_target;
                   }
                } else {
                    if ($vm['recovery'] && !$vm['mounted'] && $vm['writable']) {
                        $temp_restore_target['mounted'] = $vm['mounted'];
                        $temp_restore_target['name'] = $vm['name'];
                        $temp_restore_target['writable'] = $vm['writable'];
                        $temp_restore_target['recovery'] = $vm['recovery'];
                        $vm_targets_array[] = $temp_restore_target;
                    }
                }
            }
        }
        return $vm_targets_array;
    }

    private function get_vm_groups( $uuid, $name, $sid )
    {
        $groups_info = array();
        $resourcePools = $this->BP->get_resource_pool_info( $uuid, $sid );
        $vapps         = $this->BP->get_vApp_info( $uuid, $sid );

        $include_vGroups = ( $resourcePools!== false || $vapps!== false  );

        if ( $include_vGroups ) {
            $groups_info['Name'] = $name;
        }

        if ( $resourcePools!== false && $resourcePools != NULL ) {
            foreach( $resourcePools as $rp ){
                $rpKey = $rp['key'];
                $rpName = $rp['name'];
                $rpParentType = $rp['parentType'];
                $rpParentKey = $rp['parentKey'];
                $temp_groups_info = array( 'Key' => $rpKey,
                    'Name' => $rpName,
                    'ParentType' => $rpParentType,
                    'ParentKey' => $rpParentKey,
                    'Type'=>"0" );
                $groups_info['VG'][] = $temp_groups_info;
            }
        }

        if ( $vapps!== false && $vapps != NULL ) {
            foreach( $vapps as $va ){
                $vaKey = $va['key'];
                $vaName = $va['name'];
                $vaParentType = $va['parentType'];
                $vaParentKey = $va['parentKey'];
                $temp_groups_info = array( 'Key' => $vaKey,
                    'Name' => $vaName,
                    'ParentType' => $vaParentType,
                    'ParentKey' => $vaParentKey,
                    'Type'=> "1" );
                $groups_info['VG'][] = $temp_groups_info;
            }
        }

        if (isset($groups_info['VG'])) {
            // If we have any VM groups, order them by Name.
            $groups_info['VG'] =  $this->functions->sortByKey($groups_info['VG'], 'Name');
        }

        return $groups_info;
    }

    private function get_oracle_restore_targets($instance_id, $clientID, $sid)
    {
        $oracle_targets = array();
        $oracle_client_name = $this->getClientNameFromID($clientID, $sid);
        if ($oracle_client_name !== false) {
            $temp_oracle_targets['instance_id'] = $instance_id;
            $temp_oracle_targets['client_id'] = $clientID;
            $temp_oracle_targets['client_name'] = $oracle_client_name;

            $oracle_targets[] = $temp_oracle_targets;
        }
        return $oracle_targets;
    }

    private function get_sharepoint_restore_targets($instance_id, $sid)
    {
        $sharepoint_targets = array();
        $app_info = array();
        $sharepoint_app_info = $this->BP->get_appinst_info($instance_id, $sid);
        if ($sharepoint_app_info !== false) {
            foreach ($sharepoint_app_info as $inst_id => $inst_info) {
                $app_info['instance_id'] = $inst_id;
                $app_info['client_id'] = $inst_info['client_id'];
                $app_info['client_name'] = $inst_info['client_name'];
                $app_info['app_id'] = $inst_info['app_id'];
                $app_info['app_name'] = $inst_info['app_name'];

                break;
            }
        }
        $numOfServers = -1;
        $sharepoint_info = $this->BP->get_sharepoint_info($app_info['client_id'], $app_info['app_id'], true, $sid);
        if ($sharepoint_info !== false) {
            foreach($sharepoint_info as $sharepoint) {
                $numOfServers = $sharepoint['num_app_servers'];
            }
        }
        $app_info['num_servers'] = $numOfServers;
        $sharepoint_targets[] = $app_info;
        return $sharepoint_targets;
    }

    private function get_sql_restore_targets($backup_id, $instance_id, $instance_name, $replicated, $sid)
    {
        $sql_targets = array();
        $backup_type = '';
        $database = '';
        $isSQLAlwayson = false;
        $backup_type_array = $this->getBackupDetails( array($backup_id), $sid );
        if ( $backup_type_array !== false and count($backup_type_array) > 0 )
        {
            $backup_type = $backup_type_array[0]['type'];
            $database = $backup_type_array[0]['database'];
            $isSQLAlwayson = $backup_type_array[0]['is_sql_alwayson'];
            // there should just be one backup ID
        }
        $appinst_info = $this->BP->get_appinst_info($instance_id, $sid);
        foreach ($appinst_info as $inst_id => $inst_info) {
            $client_id = $inst_info['client_id'];
            $client_name = $inst_info['client_name'];

            break;
        }
        switch ($backup_type) {
            case Constants::BACKUP_TYPE_MSSQL_FULL:
            case Constants::BACKUP_TYPE_MSSQL_FULL_ALT:
                $isSystemDB = $this->isSystemDB($instance_id, $sid);
                if ($isSystemDB !== false && $replicated === false) {
                    // if system database, restore to original server
                    $temp_sql_targets['client_id'] = $client_id;
                    $temp_sql_targets['client_name'] = $client_name;
                    $temp_sql_targets['database'] = $database;
                    $temp_sql_targets['system_db'] = true;
                    // list of applicable instances on the server
                    $temp_sql_targets['instances'] = $this->get_sql_target_instances($client_id, $instance_id, $backup_id, $sid);
                } else {
                    // get applicable servers and instances
                    $temp_sql_targets = $this->get_sql_targets($instance_id, $database, $backup_id, $sid, $isSystemDB);
                }
                $sql_targets = $temp_sql_targets;
                break;
            case Constants::BACKUP_TYPE_MSSQL_DIFFERENTIAL:
            case Constants::BACKUP_TYPE_MSSQL_DIFFERENTIAL_ALT:
            case Constants::BACKUP_TYPE_MSSQL_TRANSACTION:
            case Constants::BACKUP_TYPE_MSSQL_TRANSACTION_ALT:
                if ($isSQLAlwayson) {
                    $temp_sql_targets = $this->get_sql_targets($instance_id, $database, $backup_id, $sid);

                    // handling for PIT
                    foreach ($temp_sql_targets as $temp_sql_target) {
                        if ($backup_type == Constants::BACKUP_TYPE_MSSQL_TRANSACTION) {
                            $group = $this->BP->get_restore_group($backup_id, $sid);
                            if ($group !== false and count($group) > 1) {
                                $earliestBackup = $group[count($group) - 2]['start_time'];
                                $latestBackup = $group[count($group) - 1]['start_time'];
                                $temp_sql_target['pit_start_time'] = $earliestBackup;
                                $temp_sql_target['pit_end_time'] = $latestBackup;
                            }
                        }
                        $sql_targets[] = $temp_sql_target;
                    }
                } else if ($replicated === true) {
                    // for replicated backups, cannot set target path
                    $temp_sql_targets = $this->get_sql_targets($instance_id, $database, $backup_id, $sid);
                    foreach ($temp_sql_targets as $temp_sql_target) {
                        $temp_sql_target['target_path'] = false;
                        $instance_array = $temp_sql_target['instances'];
                        foreach ($instance_array as $instance) {
                            if ($instance['instance_name'] === $instance_name) {
                                $sql_targets[] = $temp_sql_target;
                            }
                        }
                    }
                } else {
                    // differential and transaction backups allow restore to original server
                    $sql_targets['client_id'] = $client_id;
                    $sql_targets['client_name'] = $client_name;
                    $sql_targets['database'] = $database;
                    $sql_targets['system_db'] = $this->isSystemDB($instance_id, $sid);
                    // list of applicable instances on the server
                    $instanceArray = $this->get_sql_target_instances($client_id, $instance_id, $backup_id, $sid);
                    foreach ($instanceArray as $instance){
                        if ($instance['instance_name'] === $instance_name){
                            $sql_targets['instances'] = array($instance);
                        }
                    }
                    // for 'model' database, restore to alternate location not allowed - cannot set target path
                    if ($sql_targets['system_db'] && $database == "model") {
                        $sql_targets['target_path'] = false;
                    }

                    // handling for PIT
                    if ($backup_type == Constants::BACKUP_TYPE_MSSQL_TRANSACTION){
                        $group = $this->BP->get_restore_group($backup_id, $sid);
                        if($group !== false and count($group) > 1){
                            $earliestBackup = $group[count($group) - 2]['start_time'];
                            $latestBackup = $group[count($group) - 1]['start_time'];
                            $sql_targets['pit_start_time'] = $earliestBackup;
                            $sql_targets['pit_end_time'] = $latestBackup;
                        }
                    }
                }
                break;
        }

        return $sql_targets;
    }

    private function get_ndmp_restore_targets($backup_id, $sid) {
        $ndmp_target_array = false;

        $ndmpTargets = $this->BP->get_ndmp_restore_targets($backup_id, $sid);

        if($ndmpTargets !== false) {
            //get restore volumes for each restore target
            $ndmp_target_array = array();
            foreach($ndmpTargets as $ndmpTargetID => $ndmpTargetName) {
                $ndmp_target_array[] = array('client_id' => $ndmpTargetID, 'client_name' => $ndmpTargetName);
                /*$ndmpTargetVolumes = $this->BP->get_ndmp_restore_target_volumes($ndmpTarget['client_id'], $backup_id, $sid);
                if($ndmpTargetVolumes !== false) {
                    $ndmpTarget['volumes'] = $ndmpTargetVolumes;
                    $ndmp_target_array[] = $ndmpTarget;
                }*/
            }
        }
        return $ndmp_target_array;
    }

    private function isSystemDB($instance_id, $sid)
    {
        $isSysDB = $this->BP->is_sql_system_db($instance_id, $sid);
        if ($isSysDB == -1) {
            $isSysDB = true;
        }
        return $isSysDB;
    }

    private function get_sql_target_instances($client_id, $instance_id, $backup_id, $sid)
    {
        $target_instances = array();
        $recovery_targets = $this->BP->get_sql_server_recovery_targets($client_id, $instance_id, $backup_id, $sid);
        if ($recovery_targets !== false && count($recovery_targets) > 0) {
            foreach ($recovery_targets as $recovery_target) {
                $temp_target_instances = array();
                $temp_target_instances['instance_name'] = $recovery_target['name'];
                $temp_target_instances['is_running'] = $recovery_target['is_running'];

                $target_instances[] = $temp_target_instances;
            }
        }
        return $target_instances;
    }

    private function get_sql_targets($instance_id, $database, $backup_id, $sid, $isSystemDB = false)
    {
        $target_clients = array();
        $recovery_targets = $this->BP->get_sql_server_recovery_clients($instance_id, $sid);
        if ($recovery_targets !== false) {
            $target_clients = array();
            if (isset($recovery_targets['clients']) && count($recovery_targets['clients']) > 0) {
                foreach($recovery_targets['clients'] as $client_id => $client_name) {
                    $temp_target_clients = array();
                    $temp_target_clients['client_id'] = $client_id;
                    $temp_target_clients['client_name'] = $client_name;
                    $temp_target_clients['database'] = $database;
                    $temp_target_clients['system_db'] = $isSystemDB;

                    $temp_target_clients['instances'] = $this->get_sql_target_instances($client_id, $instance_id, $backup_id, $sid);

                    if (isset($temp_target_clients['instances']) && count($temp_target_clients['instances']) > 0) {
                        $target_clients[] = $temp_target_clients;
                    }
                }
            }
        }
        return $target_clients;
    }

    private function get_cisco_ucs_restore_targets($sid, $systemName)
    {
        $target_clients = array();
        $clients = $this->BP->get_client_list($sid);
        if ($clients !== false && count($clients) > 0) {
            foreach ($clients as $clientID => $clientName) {
                $temp_targets = array();
                $temp_targets['client_id'] = $clientID;
                $temp_targets['client_name'] = $clientName;

                // filtering out system client and vCenter-RRC
                if ($clientName !== Constants::CLIENT_NAME_VCENTER_RRC && $clientName !== $systemName){
                    $client_info = $this->BP->get_client_info($clientID, $sid);
                    $osTypeID = $client_info['os_type_id'];
                    $temp_targets['client_os'] = $client_info['os_type'];
                    //filtering out NDMP and CiscoUCS clients
                    if ($osTypeID === Constants::OS_GENERIC and $client_info['generic_property'] === Constants::GENERIC_PROPERTY_CISCO_UCS_MANAGER) {
                        $target_clients[] = $temp_targets;
                    }
                }

            }
        }
        return $target_clients;
    }

    private function get_xen_restore_targets($sid)
    {
        $target_clients = array();
        $restore_targets = $this->BP->get_xen_restore_targets($sid);
        if ($restore_targets !== false) {
            if (isset($restore_targets['xen_hosts']) && count($restore_targets['xen_hosts']) > 0) {
                foreach($restore_targets['xen_hosts'] as $xen_host) {
                    $temp_target_clients = array();
                    $temp_target_clients['client_id'] = $restore_targets['client_id'];
                    $temp_target_clients['id'] = $xen_host['uuid'];
                    $temp_target_clients['name'] = $xen_host['name'];

                    if (isset($xen_host['storage_repositories']) && count($xen_host['storage_repositories']) > 0) {
                        foreach($xen_host['storage_repositories'] as $xen_storage) {
                            $temp_storage = array();
                            $temp_storage['id'] = $xen_storage['uuid'];
                            $temp_storage['name'] = $xen_storage['name'];
                            $temp_storage['total_size_gb'] = $xen_storage['gb_total'];
                            $temp_storage['free_size_gb'] = $xen_storage['gb_free'];

                            $temp_target_clients['datastores'][] = $temp_storage;
                        }
                    }
                    $target_clients[] = $temp_target_clients;
                }
            }
        } else {
            $target_clients = false;
        }
        return $target_clients;
    }

    private function get_file_level_restore_targets($sid, $systemName)
    {
        $target_clients = array();
        $clients = $this->BP->get_client_list($sid);
        if ($clients !== false && count($clients) > 0) {
            foreach ($clients as $clientID => $clientName) {
                $temp_targets = array();
                $temp_targets['client_id'] = $clientID;
                $temp_targets['client_name'] = $clientName;

                // filtering out system client and vCenter-RRC
                if ($clientName !== Constants::CLIENT_NAME_VCENTER_RRC && $clientName !== $systemName){
                    $client_info = $this->BP->get_client_info($clientID, $sid);
                    $osTypeID = $client_info['os_type_id'];
                    $isSQLCluster = (isset($client_info['is_sql_cluster']) && $client_info['is_sql_cluster'] == true) ? true : false;
                    $isSQLAlwaysOn = (isset($client_info['is_sql_alwayson']) && $client_info['is_sql_alwayson'] == true) ? true : false;
                    $temp_targets['client_os'] = $client_info['os_type'];
                    //filtering out NDMP and CiscoUCS clients
                    if ($osTypeID !== Constants::OS_GENERIC && !$isSQLAlwaysOn && !$isSQLCluster) {
                        $target_clients[] = $temp_targets;
                    }
                }

            }
        }
        return $target_clients;
    }

    function get_restore_group($data, $sid) {
        if ( $sid === false )
        {
            $sid = $this->BP->get_local_system_id();
        }
        $backupIDs = $this->getRestoreGroup($data['backup_id'], $sid);

        // include input backups in return array.
        $bidArray = array_map('intval', explode(',', $backupIDs));
        $inputArray = array(
            'system_id' => $sid,
            'backup_ids' => $bidArray
        );
        $group = array();
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
                $group[] = $this->buildGroupOutput($backup, $sid);
            }
        }
        return array('data' => $group);
    }

    private function buildGroupOutput($backup, $sid) {
        $id = isset($backup['id']) ? $backup['id'] : null;

        $type = isset($backup['type']) ? $this->functions->getBackupTypeString($backup['type']) : null;
        $clientID = isset($backup['client_id']) ? $backup['client_id'] : null;
        $startTime = isset($backup['start_time']) ? $this->functions->formatDateTime($backup['start_time']) : null;
        $elapsedTime = isset($backup['elapsed_time']) ? $backup['elapsed_time'] : null;
        $complete = isset($backup['complete']) ? $backup['complete'] : null;
        // Backup Status: 0 = Successful, 1 = Warning, 2 = Failed.
        if ($complete == true) {
            $status = ($backup['status'] === 0) ? 'Successful' : (($backup['status'] === 1) ? 'Warning' : 'Failed');
        } else {
            $status = null;
        }
        $size = isset($backup['size']) ? $backup['size'] : null;
        $encrypted = isset($backup['encrypted']) ? $backup['encrypted'] : null;
        $verified = isset($backup['verified']) ? $backup['verified'] : null;

        if (isset($backup['instance_id'])) {
            $instanceID = $backup['instance_id'];
            $instanceInfo = $this->BP->get_appinst_info($instanceID, $sid);
            if ($instanceInfo !== false) {
                $instance = $instanceInfo[$instanceID];
                $appName = $instance['app_name'];
                $primaryName = $instance['primary_name'];
                $secondaryName = isset($instance['secondary_name']) ? $instance['secondary_name'] : '';
                $clientName = $instanceInfo[$instanceID]['client_name'];
                if ($secondaryName !== '') {
                    $instanceDescription = $primaryName . ' | ' . $secondaryName;
                } else {
                    $instanceDescription = $primaryName;
                }
            }
        } else {
            $appName = 'file-level';
            if ($clientID != null) {
                $info = $this->BP->get_client_info($clientID, $sid);
                if ($info !== false) {
                    $clientName = $info['name'];
                }
            } else {
                $clientName = "N/A";
            }
            $primaryName = $clientName;
            $secondaryName = "";
            $instanceDescription = $clientName;
        }

        $synthesized = isset($backup['synthesized']) ? $backup['synthesized'] : null;
        $replicated = isset($backup['replicated']) ? $backup['replicated'] : null;
        $vmWareTemplate = isset($backup['vmware_template']) ? $backup['vmware_template'] : "n/a";
        $xenTemplate = isset($backup['xen_template']) ? $backup['xen_template'] : "n/a";
        $certified = isset($backup['certified']) ? $backup['certified'] : "n/a";

        $alwaysReturned = array(
            'client_id' => $clientID,
            'client_name' => $clientName,
            'instance_description' => $instanceDescription,
            'app_name' => $appName,
            'type' => $type,
            'start_time' => $startTime,
            'synthesized' => $synthesized,
            'replicated' => $replicated,
            'duration' => $elapsedTime,
            'complete' => $complete,
            'status' => $status,
            'size' => $size,
            'encrypted' => $encrypted,
            'verified' => $verified,
            'vmware_template' => $vmWareTemplate,
            'xen_template' => $xenTemplate,
            'certified' => $certified,
            'primary_name' => $primaryName,
            'secondary_name' => $secondaryName,
            'id' => $id

        );

        $data = array_merge($alwaysReturned);

        return $data;
    }

    public function post($which, $data, $sid)
    {
        global $Log;

        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
        $result = array();
        if (is_string($which[0])) {
            switch ($which[0]) {
                case 'instant':
                    if ( isset($data['image_level']) and $data['image_level'] === true) {
                        if (array_key_exists('target', $data)) {
                            $target = $data['target'];
                            if (array_key_exists('host', $target)) {
                                $host = $target['host'];
                            }
                            if (array_key_exists('datastore', $target)) {
                                $dataStore = $target['datastore'];
                            }
                            if (array_key_exists('directory', $target)) {
                                $directory = $target['directory'];
                            }
                            $name = $target['name'];
                            if (array_key_exists('group', $target)) {
                                $group = $target['group'] == "" ? NULL : $target['group'];
                            }
                            if (array_key_exists('switch', $target)) {
                                $switch = $target['switch'] == "" ? NULL : $target['switch'];
                            }
                        }
                        if ($data['address'] != "" && $data['address'] != null) {
                            $address = $data['address'];
                        } elseif ($data['hypervisor_type'] !== Constants::HYPERVISOR_TYPE_APPLIANCE) {
                            $result['error'] = 500;
                            $result['message'] = "Target Appliance's IP address is required.";
                            break;
                        }
                        $audit = (int)$data['audit'];
                        $poweron = isset($data['poweron']) ? $data['poweron'] : 1;
                        if ($data['hypervisor_type'] === Constants::APPLICATION_TYPE_NAME_VMWARE) {
                            $result = $this->BP->vmware_ir_start($host, $name, $dataStore, $address, $audit,
                                $data['backup_id'], $poweron, $group, $sid);
                        } elseif ($data['hypervisor_type'] === Constants::APPLICATION_TYPE_NAME_HYPER_V) {
                            $result = $this->BP->hyperv_ir_start($host, $name, $directory, $address, $audit,
                                $data['backup_id'], $poweron, $switch, $sid);

                        } elseif ($data['hypervisor_type'] === Constants::HYPERVISOR_TYPE_APPLIANCE) {
                            $result = $this->BP->qemu_ir_start($name, $audit, $poweron, $data['backup_id'], $sid);

                        } else {
                            $result['error'] = 500;
                            $result['message'] = "Hypervisor type is unsupported.";
                        }

                    } else {
                        $backups = $this->getRestoreGroup($data['backup_id'], $sid);
                        if (array_key_exists('error', $backups)) {
                            $result = $backups['error'];
                        } else {
                            $backupArrStr = explode(",", $backups);
                            $backupArray = array();
                            $i = 0;
                            foreach ($backupArrStr as $backup) {
                                $backupArray[$i++] = (int)$backup;
                            }
                            $backupType = $this->getBackupDetails($backupArray, $sid);
                            if (array_key_exists('target', $data)) {
                                $target = $data['target'];
                                $host = $target['host'];
                                if (array_key_exists('datastore', $target)) {
                                    $dataStore = $target['datastore'];
                                }
                                if (array_key_exists('directory', $target)) {
                                    $directory = $target['directory'];
                                }
                                $name = $target['name'];
                                if (array_key_exists('group', $target)) {
                                    $group = $target['group'] == "" ? NULL : $target['group'];
                                }
                                if (array_key_exists('switch', $target)) {
                                    $switch = $target['switch'] == "" ? NULL : $target['switch'];
                                }
                            }
                            if ($data['address'] != "" && $data['address'] != null) {
                                $address = $data['address'];
                            } else {
                                $result['error'] = 500;
                                $result['message'] = "Target Appliance's IP address is required.";
                                break;
                            }
                            $audit = (int)$data['audit'];
                            $poweron = isset($data['poweron']) ? $data['poweron'] : 1;
                            if ($this->functions->isBackupVMWare($backupType[0]['type'])) {
                                $result = $this->BP->vmware_ir_start($host, $name, $dataStore, $address, $audit,
                                    $backups, $poweron, $group, $sid);
                            } elseif ($this->functions->isBackupHyperV($backupType[0]['type'])) {
                                $result = $this->BP->hyperv_ir_start($host, $name, $directory, $address, $audit,
                                    $backups, $poweron, $switch, $sid);

                            } else {
                                $result['error'] = 500;
                                $result['message'] = "Backup id " .$data['backup_id']. " is not supported.";
                            }

                        }
                    }
                    break;

                case 'full':
                    $backups = $this->getRestoreGroup($data['backup_id'], $sid);
                    if (is_array($backups) && array_key_exists('error', $backups)) {
                        $result = $backups['error'];
                    } else {
                        $backupArrStr = explode(",", $backups);
                        $backupArray = array();
                        $i = 0;
                        foreach ($backupArrStr as $backup) {
                            $backupArray[$i++] = (int)$backup;
                        }
                        $backupType = $this->getBackupDetails($backupArray, $sid);
                        $replicated = isset($_GET['replicated']) && ($_GET['replicated'] == 1) ? true : false;
                        //if this is a vmware replicated backup, set client_id to non-replicating client
                        if ($replicated && $this->functions->isBackupVMWare($backupType[0]['type'])) {
                            $client_id = $this->getVMwareClient($sid);
                        } elseif (array_key_exists('client_id', $data)) {
                            $client_id = (int)$data['client_id'];
                        }
                        $includes = $data['includes'];
                        $includesArray = array();
                        if ($includes !== "") {
                            $i = 0;
                            foreach ($includes as $include) {
                                if (substr($include, strlen($include) - 1) == ':') {
                                    $include = $include . '/';
                                }
                                $includesArray[$i++] = $include;
                            }
                        }
                        $excludes = $data['excludes'];
                        $excludesArray = array();
                        if ($excludes !== "") {
                            $j = 0;
                            foreach ($excludes as $exclude) {
                                if (strpos($exclude, ':') === false) {
                                    foreach ($this->getVolumeNames($includesArray) as $volume) {
                                        $excludesArray[$j++] = $volume . $exclude;
                                    }
                                } elseif (substr($exclude, strlen($exclude) - 1) == ':') {
                                    $excludesArray[$j++] = $exclude . '/';
                                } else {
                                    $excludesArray[$j++] = $exclude;
                                }
                            }
                        }
                        if (array_key_exists('synthesis', $data)) {
                            $synthesis = $data['synthesis'];
                        }
                        $target = $data['target'];
                        $database = "";
                        $instance = "";
                        $volume = "";
                        $host = "";
                        $datastore = "";
                        $name = "";
                        $group = "";
                        $metadata = "";
                        $template = "";
                        $storageRepository = "";

                        if (array_key_exists('flat', $target)) {
                            $flat = $target['flat'];
                        }
                        if (array_key_exists('non_destructive', $target)) {
                            $non_destructive = $target['non_destructive'];
                        }
                        if (array_key_exists('newer', $target)) {
                            $newer = $target['newer'];
                        }
                        if (array_key_exists('today', $target)) {
                            $today = $target['today'];
                        }
                        if (array_key_exists('unix', $target)) {
                            $unix = $target['unix'];
                        }
                        if (array_key_exists('database', $target)) {
                            $database = $target['database'];
                        }
                        if (array_key_exists('instance', $target)) {
                            $instance = $target['instance'];
                        }
                        if (array_key_exists('volume', $target)) {
                            $volume = $target['volume'];
                        }
                        if (array_key_exists('point_in_time', $target)) {
                            $point_in_time = $target['point_in_time'];
                        }
                        if (array_key_exists('host', $target)) {
                            $host = $target['host'];
                        }
                        if (array_key_exists('datastore', $target)) {
                            $datastore = $target['datastore'];
                        }
                        if (array_key_exists('name', $target)) {
                            $name = $target['name'];
                        }
                        if (array_key_exists('group', $target)) {
                            $group = $target['group'];
                        }
                        if (array_key_exists('metadata', $target)) {
                            $metadata = $target['metadata'] ? 1 : 0;
                        }
                        if (array_key_exists('template', $target)) {
                            $template = $target['template'] ? 1 : 0;
                        }
                        if (array_key_exists('directory', $target) && $target['directory'] !== "") {
                            $directory = $target['directory'];
                        }
                        if (array_key_exists('storage_repository', $target)) {
                            $storageRepository = $target['storage_repository'];
                        }

                        // A restore initiated from the source, set the path.
                        if (isset($data['remote']) && $data['remote'] === true) {
                            if (!isset($_GET['source_id'])) {
                                $result['error'] = 500;
                                $result['message'] = "Error creating target directory for file recovery, no source id specified.";
                                return $result;
                            }
                            // verify space availability, device state, and get the path
                            $ftdResults = $this->findDeviceTempDir($_GET['source_id'], $data);
                            if (!isset($ftdResults['device_temp_dir'])) {
                                $result['error']   = isset($ftdResults['error'])   ? $ftdResults['error'] : 500;
                                $result['message'] = isset($ftdResults['message']) ? $ftdResults['message'] :
                                    "Error creating target directory for file recovery.";
                                return $result;
                            }
                            $targetDevPathDir = $ftdResults['device_temp_dir'];
                            if (!file_exists($targetDevPathDir)) {
                                // mkdir dir using the sudo based script to get the permissions correct.
                                $cmd = "sudo /var/www/html/grid/portal/rflr_manage.php --create_dir $targetDevPathDir";
                                $Log->writeVariableDBG("mkdir cmd:".$cmd);
                                $mkdirResults = array();
                                $flatRes = shell_exec($cmd);
                                $Log->writeVariableDBG("mkdir flatRes:".$flatRes);
                                $mkdirResults = json_decode($flatRes, true);
                                if (isset($mkdirResults['error'])) {
                                    $result['error']   = $mkdirResults['error'];
                                    $result['message'] = isset($mkdirResults['message']) ? $mkdirResults['message'] :
                                        "Error creating target directory for remote file recovery.";
                                    return $result;
                                } else {
                                    $directory = $targetDevPathDir;
                                }
                            } else {
                                $Log->writeVariable($targetDevPathDir . " exists");
                            }
                        } // end of a restore initiated from the source....

                        $bcmd = $data['before_cmd'];
                        $acmd = $data['after_cmd'];

                        if (isset($flat) && isset($non_destructive) && isset($newer) && isset($today) && isset($unix)) {
                            $backup = (int)$data['backup_id'];
                            $options = array();
                            if (isset($client_id)) {
                                $options['client_id'] = $client_id;
                            }
                            if (isset($directory) && $directory !== "") {
                                $options['directory'] = $directory;
                            }
                            $options['flat'] = $flat;
                            if ($bcmd !== "") {
                                $options['before_command'] = $bcmd;
                            }
                            if ($acmd !== "") {
                                $options['after_command'] = $acmd;
                            }
                            $options['non_destructive'] = $non_destructive;
                            $options['newer'] = $newer;
                            $options['today'] = $today;
                            $options['unix'] = $unix;

                            $incChar = 0;
                            foreach($includesArray as $str){
                                $incChar += strlen($str);
                            }

                            if ($incChar <= self::CHAR_LIMIT) {
                                if (isset($synthesis) && $synthesis === true) {
                                    $groupID = $this->BP->get_synthesis_group($data['backup_id'], $sid);
                                    if ($groupID !== false) {
                                        $restore = $this->BP->restore_synthesized_files($groupID, $data['backup_id'], $includesArray, $excludesArray, $options, $sid);
                                        if ($restore !== false) {
                                            $result['id'] = $restore;
                                        } else {
                                            $result['error'] = 500;
                                            $result['message'] = $this->BP->getError();
                                        }
                                    } else {
                                        $result['error'] = 500;
                                        $result['message'] = $this->BP->getError();
                                    }
                                } else {
                                    $restore = $this->BP->restore_files($backup, $includesArray, $excludesArray, $options, $sid);
                                    if ($restore !== false) {
                                        $result['id'] = $restore;
                                    } else {
                                        $result['error'] = 500;
                                        $result['message'] = $this->BP->getError();
                                    }
                                }
                            } else {
                                $result['error'] = 500;
                                $result['message'] = "Too many individual files selected. Please hit 'OK' to quit or 'Retry' to go back and select fewer individual files.";
                            }
                        } else {
                            if ($this->functions->isBackupHyperV($backupType[0]['type']) || $this->functions->isBackupVMWare($backupType[0]['type']) ||
                                $this->functions->isBackupExchange($backupType[0]['type']) || $this->functions->isBackupSQL($backupType[0]['type']) ||
                                $this->functions->isBackupXen($backupType[0]['type']) || $this->functions->isBackupNDMP($backupType[0]['type']) ||
                                $this->functions->isBackupAHV($backupType[0]['type'])){
                                $destination = "";
                                if ($this->functions->isBackupExchange($backupType[0]['type'])) {
                                    $destination = $database;
                                } elseif ($this->functions->isBackupSQL($backupType[0]['type'])) {
                                    $destination = $instance . '|' . $database;
                                } elseif ($this->functions->isBackupVMWare($backupType[0]['type'])) {
                                    $destination = $host . '|' . $datastore . '|' . $name . '|' . $group . '|' . $metadata . '|' . $template;
                                } elseif ($this->functions->isBackupXen($backupType[0]['type'])) {
                                    $destination = $host . '|' . $storageRepository . '|' . $name . '|' . $metadata;
                                } elseif ($this->functions->isBackupNDMP($backupType[0]['type'])) {
                                    $destination = $volume;
                                } elseif ($this->functions->isBackupAHV($backupType[0]['type'])){
                                    $destination = $name . '|' . $datastore . '|' . $metadata;
                                }
                                $options = array();
                                if (isset($client_id)) {
                                    $options['client_id'] = $client_id;
                                }
                                if (isset($target)) {
                                    $options['target'] = $destination;
                                }
                                if (isset($directory)) {
                                    $options['directory'] = $directory;
                                }
                                if(is_array($includesArray) and count($includesArray) != 0) { //believe only NDMP
                                    if($this->functions->isBackupNDMP($backupType[0]['type'])) {
                                        //remove @@@: from file names
                                        for($i = 0; $i < count($includesArray); $i++) {
                                            if(substr($includesArray[$i], 0, 4) === "@@@:") {
                                                $includesArray[$i] = substr($includesArray[$i], 4);
                                            }
                                        }
                                    }
                                    $options['includes'] = $includesArray;
                                }
                                if (isset($point_in_time)) {
                                    $options['point_in_time'] = $point_in_time;
                                }
                                if ($bcmd !== "") {
                                    $options['before_command'] = $bcmd;
                                }
                                if ($acmd !== "") {
                                    $options['after_command'] = $acmd;
                                }
                                $resultArray = $this->BP->restore_application($backups, $options, $sid);
                                if ($resultArray !== false) {
                                    $tempRes = "";
                                    $delim = "";
                                    $msg = "";
                                    $msg_delim = "";
                                    foreach ($resultArray as $resArr) {
                                        $tempRes = $tempRes . $delim . $resArr['job_id'];
                                        $delim = ",";

                                        if (array_key_exists('msg', $resArr)) {
                                            $backupType =  isset($resArr['type']) ? $this->functions->getBackupTypeString($resArr['type']) : "";
                                            $msg = $msg . $msg_delim . $backupType . " restore failed. " . $resArr['msg'];
                                            $msg_delim = "\n";
                                        }
                                    }
                                    if ($msg !== "") {
                                        $result['error'] = 500;
                                        $result['message'] = $msg;
                                    } else {
                                        $result['id'] = $tempRes;
                                    }
                                } else {
                                    $result = $resultArray;
                                }
                            } elseif ($this->isBackupSharePoint($backupType[0]['type']) || $this->isBackupOracle($backupType[0]['type'])) {
                                $options = array();
                                if (isset($client_id)) {
                                    $options['client_id'] = $client_id;
                                }
                                $resultArray = $this->BP->rae_restore_application($backups, $options, $sid);
                                if ($resultArray !== false) {
                                    $tempRes = "";
                                    $delim = "";
                                    $msg = "";
                                    $msg_delim = "";
                                    foreach ($resultArray as $resArr) {
                                        $tempRes = $tempRes . $delim . $resArr['job_id'];
                                        $delim = ",";

                                        if (array_key_exists('msg', $resArr)) {
                                            $backupType =  isset($resArr['type']) ? $this->functions->getBackupTypeString($resArr['type']) : "";
                                            $msg = $msg . $msg_delim . $backupType . " restore failed. " . $resArr['msg'];
                                            $msg_delim = "\n";
                                        }
                                    }
                                    if ($msg !== "") {
                                        $result['error'] = 500;
                                        $result['message'] = $msg;
                                    } else {
                                        $result['id'] = $tempRes;
                                    }
                                } else {
                                    $result = $resultArray;
                                }
                            } elseif ( $this->functions->isBackupCiscoUCS($backupType[0]['type']) ) {
                                $options = array();
                                if (isset($client_id)) {
                                    $options['client_id'] = $client_id;
                                    $options['includes'] = $includesArray;
                                }
                                $resultArray = $this->BP->rae_restore_application($backups, $options, $sid);
                                if ($resultArray !== false) {
                                    $tempRes = "";
                                    $delim = "";
                                    $msg = "";
                                    $msg_delim = "";
                                    foreach ($resultArray as $resArr) {
                                        $tempRes = $tempRes . $delim . $resArr['job_id'];
                                        $delim = ",";

                                        if (array_key_exists('msg', $resArr)) {
                                            $backupType =  isset($resArr['type']) ? $this->functions->getBackupTypeString($resArr['type']) : "";
                                            $msg = $msg . $msg_delim . $backupType . " restore failed. " . $resArr['msg'];
                                            $msg_delim = "\n";
                                        }
                                    }
                                    if ($msg !== "") {
                                        $result['error'] = 500;
                                        $result['message'] = $msg;
                                    } else {
                                        $result['id'] = $tempRes;
                                    }
                                } else {
                                    $result['error'] = 500;
                                    $result['message'] = $this->BP->getError();
                                }
                            } else {
                                $result['error'] = 500;
                                $result['message'] = "Backup id " .$data['backup_id']. " is not supported.";
                            }
                        }
                        // Process complete. If this was a remote task for which the source initiated restore job
                        // and the job submitted correctly on this target, then update the db with linkage between
                        // the restore job id and the unique dir where the files will be extracted to. 
                        if (isset($data['remote']) && ($data['remote'] === true) && isset($result['id'])) {
                            $Log->writeVariable("size_KB being inserted in /restore/full " . $data['size_KB'] . " and id is " . $result['id']);
                            $utResults = $this->insertRFLRJobRecord($result['id'], $targetDevPathDir, $_GET['source_id'], $data['size_KB']);
                            if (isset($utResults['error'])) {
                                 $result['error']   = $utResults['error'];
                                 $result['message'] = isset($utResults['message']) ? $utResults['message'] : "Error updating RFLR job db values.";
                                 return $result;
                            }
                        }
                    }
                    break;

                case 'target-full':
                    // This case is source-side call for a search file/get request of a remote file for an agent-based client.
                    // The target-side call is restore-full, with remote:true set.
                    // This effectively is part 1 of the request which initiates the target-side restore (file extraction).
                    // Part 2, is the target-download-files request.
                    // restoreFilesOnTarget() checks source-side download limits, mostly for in-memory constraints.
                    return $this->restoreFilesOnTarget($data, $sid);
                    break;

                case 'files':
                    if (isset($data['remote']) && isset($data['sourceID'])) {
                        // Unset remote so that it won't be treated as a remote request from the target.
                        unset($data['remote']);
                        return $this->restoreFLRFromTarget($data, $sid);
                    }
                    if (isset($data['related'])) {
                        $related = $data['related'];
                    } else {
                        $related = 1;
                    }
                    if ($related === 1) {
                        $backups = $this->getRestoreGroup($data['backup_id'], $sid);
                    } else {
                        $backups = $data['backup_id'];
                    }
                    if (is_array($backups) && array_key_exists('error', $backups)) {
                        $result = $backups['error'];
                    } else {
                        $backupArrStr = explode(",", $backups);
                        $backupArray = array();
                        $i = 0;
                        foreach ($backupArrStr as $backup) {
                            $backupArray[$i++] = (int)$backup;
                        }
                        $backupType = $this->getBackupDetails($backupArray, $sid);

                        // Check recovery limits on the target before continuing.
                        if (isset($_GET['source_id'])) {
                            $checkLimitsRes = $this->checkFLRLimits($_GET['source_id']);
                            if (is_array($checkLimitsRes) && isset($checkLimitsRes['error'])) {
                                $result['error']   = $checkLimitsRes['error'];
                                $result['message'] = isset($checkLimitsRes['message']) ? $checkLimitsRes['message'] :
                                    "Maximum number of allowable recovery jobs have been reached. Task will not start.";
                                return $result;
                            }
                        }

                        $path = "";
                        if (array_key_exists('path', $data)) {
                            $path = $data['path'];
                        }
                        if ($this->functions->isBackupExchange($backupType[0]['type'])) {
                            $result = $this->BP->backup_mount($backups, $sid);
                        } elseif ($this->functions->isBackupVMWare($backupType[0]['type']) ||
                            $this->functions->isBackupHyperV($backupType[0]['type']) ||
                            $this->functions->isBackupBlockLevel($backupType[0]['type']) ||
                            $this->functions->isBackupXen($backupType[0]['type']) ||
                            $this->functions->isBackupAHV($backupType[0]['type'])) {
                            $result = $this->BP->create_disk_image($backups, $sid);
                        } elseif ($this->isBackupOracle($backupType[0]['type']) || $this->isBackupSharePoint($backupType[0]['type'])) {
                            $result = $this->BP->create_application_share($backups, $path, $sid);
                        } else {
                            $result['error'] = 500;
                            $result['message'] = "Backup id " .$data['backup_id']. " is not supported.";
                        }
                    }
                    break;
                case 'download':
                    return $this->downloadFile($data, $sid);
                    break;
                case 'download-files':
                    if (isset($data['id']) && $data['id'] !== false) {
                        return $this->downloadFilesTargetDir($data, $sid);
                    } else {
                        return $this->downloadFiles($data, $sid);
                    }
                    break;
                case 'target-download':
                    return $this->downloadFileFromTarget($data, $sid);
                    break;
                case 'target-download-files':
                    // This case is source-side call for downloading selected remote files. This case is called under several
                    // requests including a Search Files/Get request and Browse/Download for an FLR spun-up on a target.
                    // For the Search Files/Get, the source-side part 1 that initiates the target-side restore (aka file extraction)
                    // is 'target-full'. The downloadFilesFromTarget() invokes the target-side 'download-files' case with id:NNNN set.
                    // For Browse/Download, the spin-up is done with 'files' request and the downloadFilesFromTarget() without id set
                    // which therefore invokes 'download-files' to take the downloadFiles() path. What a nested mess!
                    return $this->downloadFilesFromTarget($data, $sid);
                    break;
                case 'multiple':
                    if (isset($which[1]) && is_string($which[1])) {
                        $appType = rawurldecode($which[1]);
                        switch ($appType) {
                            case Constants::APPLICATION_TYPE_NAME_SQL_SERVER:
                            case 'sql':
                                return $this->sqlMultiRestore($data, $sid);
                                break;
                            default:
                                $result['error'] = 500;
                                $result['message'] = $appType ." is not supported for multiple restore.";
                                break;
                        }
                    } else {
                        $result['error'] = 500;
                        $result['message'] = "Application type is required.";
                    }
            }
        }
        return $result;
    }

    /*
     * Passing a string of backupIDs will return an array of backup IDs and type of backups
     */
    private function getBackupDetails($backupIDs, $sid)
    {
        $backupDetails = array();
        $result_format = array();
        $result_format['system_id'] = $sid;
        $result_format['backup_ids'] = $backupIDs;

        $resultArray = $this->BP->get_backup_status($result_format);
        if ($resultArray !== false) {
            foreach ($resultArray as $result) {
                $backupDetails[] = array( 'id' => $result['id'],
                                    'type' => $result['type'],
                                    'app_id' => ( array_key_exists('app_id', $result) ? $result['app_id'] : false ),
                                    'instance_id' => ( array_key_exists('instance_id', $result) ? $result['instance_id'] : false ),
                                    'instance_name' => ( array_key_exists('server_instance_name', $result) ? $result['server_instance_name'] : false ),
                                    'database' => ( array_key_exists('database_name', $result) ? $result['database_name'] : ''),
                                    'replicated' => ( array_key_exists('replicated', $result) ? $result['replicated'] : ''),
                                    'client_id' => (array_key_exists('client_id', $result) ? $result['client_id'] : false ),
                                    'is_sql_alwayson' => (array_key_exists('is_sql_alwayson', $result) ? $result['is_sql_alwayson'] : false));
            }
        }
        return $backupDetails;
    }

    private function getRestoreGroup($backupID, $sid)
    {
        $backups = "";
        $delim = "";
        $backupDetails = $this->getBackupDetails(array((int)$backupID), $sid);
        foreach ($backupDetails as $details) {
            if ($details['type'] === Constants::BACKUP_TYPE_INCREMENTAL || $details['type'] === Constants::BACKUP_TYPE_DIFFERENTIAL ||
                $details['type'] === Constants::BACKUP_TYPE_BLOCK_INCREMENTAL || $details['type'] === Constants::BACKUP_TYPE_BLOCK_DIFFERENTIAL || $details['type'] === Constants::BACKUP_TYPE_BLOCK_FULL) {
                // For block backups, we want to go down the synthesis path, even if a full backup.
                $groupArray = $this->BP->get_synthesis_group($backupID, $sid);
                if ($groupArray !== false) {
                    $backups = $groupArray;
                } else {
                    $backups['error'] = $groupArray;
                }
            } else {
                $groupArray = $this->BP->get_restore_group($backupID, $sid);
                if ($groupArray !== false) {
                    foreach ($groupArray as $group) {
                        $backups = $backups . $delim . $group['id'];
                        $delim = ",";
                    }
                } else {
                    $backups['error'] = $groupArray;
                }
            }
            break; //Only one backup array is returned
        }
        return $backups;
    }

    private function isBackupSharePoint($backupType)
    {
        $isSharePoint = false;

        switch($backupType){
            case Constants::BACKUP_TYPE_SHAREPOINT_FULL:
            case Constants::BACKUP_TYPE_SHAREPOINT_DIFF:

                $isSharePoint = true;
                break;
        }
        return $isSharePoint;
    }

    private function isBackupOracle($backupType)
    {
        $isOracle = false;

        switch($backupType){
            case Constants::BACKUP_TYPE_ORACLE_FULL:
            case Constants::BACKUP_TYPE_ORACLE_INCR:

                $isOracle = true;
                break;
        }
        return $isOracle;
    }

    private function getClientIDFromBackup($backupID, $sid)
    {
        $clientID = array();
        $result_format = array();
        $result_format['system_id'] = $sid;
        $result_format['backup_ids'] = $backupID;

        $resultArray = $this->BP->get_backup_status($result_format);
        if ($resultArray !== false) {
            foreach ($resultArray as $result) {
                $clientID[] = $result['client_id'];
            }
        }
        return $clientID;
    }

    private function getExchangeDBFromBackup($backupID, $sid)
    {
        $dbNames = array();
        $result_format = array();
        $result_format['system_id'] = $sid;
        $result_format['backup_ids'] = $backupID;

        $resultArray = $this->BP->get_backup_status($result_format);
        if ($resultArray !== false) {
            foreach ($resultArray as $result) {
                $dbNames[] = $result['database_name'];
            }
        }
        foreach ($dbNames as $db) {
            $dbNameRet = $db;
            break;
	}
        return $dbNameRet;
    }

    private function getClientNameFromID($clientID, $sid)
    {
        $clientName = false;
        $clientInfo = $this->BP->get_client_info($clientID, $sid);
        if ($clientInfo !== false) {
            $clientName = isset($clientInfo['name']) ? $clientInfo['name'] : false;
        }
        return $clientName;
    }

    private function getVolumeNames($includesArray)
    {
        $volumeNames = array();
        $i = 0;
        foreach ($includesArray as $includes) {
            $index = strpos($includes, ':');
            $volume = substr($includes, 0, $index + 1);
            if (!(in_array($volume, $volumeNames))) {
                $volumeNames[$i++] = $volume;
            }
        }
        return $volumeNames;
    }

    // checks if the file names contain back ticks '`'
    private function invalid_names($value)
    {
        $invalidValues = '';
        $rval = true;
        if (is_array($value)) {
            // if an array traverse through it and check for back ticks
            $delim = '';
            foreach ($value as $val) {
                if (strpos($val, '`') !== false) {
                    $invalidValues = $invalidValues . $delim . $val;
                    $delim = "\n";
                    $rval = false;
                }
            }
        } else {
            if (strpos($value, '`') !== false) {
                $invalidValues = $value;
                $rval = false;
            }
        }
	    if ($rval == false) {
		    $msg = date('Y-m-d H:i:s')." restore.php: detected invalid parameter\n";
		    file_put_contents('/usr/bp/logs.dir/gui_root.log', $msg, FILE_APPEND);
	    }
	    return $invalidValues;
    }

    function getVMwareClient($sid) {
        $clientID = -1;
        global $BP;
        $cid = $BP->get_client_id(Constants::CLIENT_NAME_VCENTER_RRC, $sid);
        if ($cid !== false) {
            $clientID = $cid;
        }
        return $clientID;
    }

    function restoreFLRFromTarget($data, $sid) {
        $request = "POST";
        $api = "/api/restore/files/";
        $parameters = "";
        $result = $this->functions->remoteRequest("", $request, $api, $parameters, $data, $sid);
        return $result;
    }

    function getRestoreGroupOnTarget($param, $sid) {
        $data = array();
        $request = "GET";
        $api = "/api/restore/group/";
        $lang = isset($_GET['lang']) ? ("&lang=" . $_GET['lang']) : "";
        $parameters = "backup_id=" . $param['backup_id'] . $lang;
        $result = $this->functions->remoteRequest("", $request, $api, $parameters, NULL, 1);
        if (is_array($result)) {
            $target = $result['target_name'];
            $results = $result['data'];
            foreach ($results as $backup) {
                $backup['remote'] = true;
                if (isset($backup['status'])) {
                    if ($backup['status'] == 'Successful') {
                        $backup['success'] = true;
                    } else if ($backup['status'] == 'Failed') {
                        $backup['success'] = false;
                    }
                }
                $backup['date'] = $backup['start_time'];
                $backup['storage'] = $target;
                $data[] = $backup;
            }
        }
        return array('data' => $data);
    }

    function downloadFile($data, $sid) {
        global $Log;
        $filename = $data['filename'];
        $invalidNames = $this->invalid_names($filename);
        if ($invalidNames != '') {
            $result['error']       = 500;
            $result['message']     = "Invalid filenames."; //. $invalidNames . ".\n\n Please select other files to proceed with download.";
            $result['passthrough'] = false;
            return $result;
        }
        $rawPassThrough = isset($data['RawPassthrough']);

        $Log->writeVariable("Downloading file:" . $filename . " with passthrough = " . $rawPassThrough);
        // Check file size here.  Don't allow over some max.
        $data = file_get_contents($filename);
        $result = array();
        if ($data === false) {
            $result['error'] = 500;
            $result['message'] = "Error downloading " . $filename . ".";
        } else {
            $result['data'] = $data;
            $result['passthrough'] = $rawPassThrough;
            $result['raw'] = true;
        }
        return $result;
    }

    function downloadFileFromTarget($data, $sid) {
        $filename = $data['filename'];
        $invalidNames = $this->invalid_names($filename);
        if ($invalidNames != '') {
            $result['error']       = 500;
            $result['message']     = "Invalid filenames.";// . $invalidNames . ". \n\nPlease select other files to proceed with download.";
            $result['passthrough'] = false;
            return $result;
        }
        $request = "POST";
        $api = "/api/restore/download/";
        $parameters = "";
        $postData = array('RawPassthrough' => 1,
                          'filename' => $filename);
        $resultData = $this->functions->remoteRequest("", $request, $api, $parameters, $postData, $sid, NULL, true, true);
        if (is_array($resultData)) {
            $result = $resultData;
        } else {
            $result = array();
            $result['data'] = $resultData;
            $result['raw'] = true;
        }
        return $result;
    }

    function downloadFiles($data, $sid) {
        global $Log;
        $func = "downloadFiles: ";

        $rawPassThrough = isset($data['RawPassthrough']) ? $data['RawPassthrough'] : false;
        $zipName = "";
        $errorMessage = "";
        $result = array();

        if (!isset($data['filenames'])) {
            $result['error']       = 500;
            $result['message']     = 'Download zip archive creation failed: no filenames were specified.';
            $result['passthrough'] = false;
            return $result;
        }
        $filenames = $data['filenames'];
        $invalidNames = $this->invalid_names($filenames);
        if ($invalidNames != '') {
            $result['error']       = 500;
            $result['message']     = "Invalid filenames."; //. $invalidNames . ". \n\nPlease select other files to proceed with download."; // need to handle UI changes to display the invalid file names
            $result['passthrough'] = false;
            return $result;
        }
        $restore_path = $data['restore_path'];
        $invalidNames = $this->invalid_names($restore_path);
        if ($invalidNames != '') {
            $result['error']       = 500;
            $result['message']     = "Invalid restore path.";
            $result['passthrough'] = false;
            return $result;
        }

        // If source_id is present, this was initiated remotely, therefore leverage resources relative to it.
        $remoteRequest = (isset($_GET['source_id']) ? true : false );
        $sid = isset($_GET['source_id']) ? $_GET['source_id'] : $sid;

        // get the download size
        $filenames_str = implode(",", $filenames);
        $cmd = "sudo /var/www/html/grid/portal/rflr_manage.php --get_file_size --filenames '$filenames_str'";
        $downloadSizeBytes = shell_exec($cmd);

        $tmpData = array();
        $tmpData['size_KB'] = $downloadSizeBytes / 1024;
        // add the source's size and tunnel availability
        if (isset($data['srcMaxDownloadMB'])) {
            $tmpData['srcMaxDownloadMB'] = $data['srcMaxDownloadMB'];
        }
        if (isset($data['srcCreateDownloadTunnels'])) {
            $tmpData['srcCreateDownloadTunnels'] = $data['srcCreateDownloadTunnels'];
        }

        // verify space availability, device state, and get the path
        // Note: findDeviceTempDir checks download size limits
        $ftdResults = $this->findDeviceTempDir($sid, $tmpData);
        if (!isset($ftdResults['device_temp_dir'])) {
            $result['error']   = isset($ftdResults['error'])   ? $ftdResults['error'] : 500;
            $result['message'] = isset($ftdResults['message']) ? $ftdResults['message'] :
                "Error creating target directory for file recovery.";
            $result['passthrough'] = false;
            return $result;
        }
        $localDevPathDir = $ftdResults['device_temp_dir'];
        // creating the zip file and adding to it
        $cmd = "sudo /var/www/html/grid/portal/rflr_manage.php --create_zip_files --zip_dir '$localDevPathDir' --start_dir '$restore_path' --filenames '$filenames_str'";
        $flatRes = shell_exec($cmd);
        $Log->writeVariableDBG("mkdir flatRes:".$flatRes);
        $Log->writeVariableDBG("filenames str:" .$filenames_str);
        $mkdirResults = json_decode($flatRes, true);
        $errorMessage = "";
        if (is_array($flatRes) && isset($flatRes['error']) && $flatRes['error'] == true) {
            $errorMessage = isset($flatRes['message']) ? $flatRes['message'] : "Error creating zip file for file recovery.";
        } else if (isset($mkdirResults['error'])) {
            $errorMessage = isset($mkdirResults['message']) ? $mkdirResults['message'] : "Error creating zip file for file recovery.";
        } else if (!isset($mkdirResults['zipname'])) {
            $errorMessage = "Error creating zip archive of recovered files, no zipName created.";
        } else {
            $zipName = $mkdirResults['zipname'];
        }

        if ($errorMessage !== "") {
            // an error occurred, cleanup the temp zip file, let the watchdog cleanup the path.
            if ($zipName != "") {
               $cleanupResult = exec("rm -rf " . $zipName);
               $Log->writeVariableDBG("clean up " . $zipName . ", result is " . $cleanupResult);
            }
            $result['error']       = 500;
            $result['message']     = $errorMessage;
            $result['passthrough'] = false;
            return $result;
        }

        // Generate a job_no for an RFLR record used to keep track of tunnel dependencies and tracking.
        // Use a negative timestamp to avoid collisions with real job no's.
        $job_no = -(time());
        // Check the technique for obtaining the zip file, either stream or download, all based on the size of the zip file
        if (isset($ftdResults['sid_rflr_dir']) && isset($ftdResults['create_dl_tunnels']) && $ftdResults['create_dl_tunnels'] === true){
            if ($remoteRequest) {
                // create a record in the rflr jobs table
                $this->insertRFLRJobRecord($job_no, $localDevPathDir, $sid, $tmpData['size_KB'], 'manual-download');
                // request is running on the target, get the target tunnel information and return it back to the source
                return $this->setupTargetDownloadTunnel($sid, $ftdResults['sid_rflr_dir'], $zipName);
            } else {
                // create a record in the rflr jobs table
                $this->insertRFLRJobRecord($job_no, $localDevPathDir, $sid, $tmpData['size_KB'], 'local-download');
                // request is running on the source, therefore, this is just a local file recovery, return the URL message
                return $this->setupSourceDownloadSymlink($sid, $ftdResults['sid_rflr_dir'], $zipName);
            }
        } else {
            // create a record in the rflr jobs table
            $this->insertRFLRJobRecord($job_no, $localDevPathDir, $sid, $tmpData['size_KB'], 'downloading');
            $result['data']        = '';
            $result['passthrough'] = $rawPassThrough;
            $result['zip']         = $zipName;
        }
        return $result;
    }

    function downloadFilesTargetDir($data, $sid) {
        global $Log;
        $func = "downloadFilesTargetDir: ";

        // This function responds to a remote request to get the extracted files and puts the entire content into memory on the tgt.
        // Therefore bump up memory. master.ini SelfService settings limit the file size allowed during the restore request.
        // Also, as a precaution, bump up the processing time from the default of 2 minutes (/etc/php.ini) to 1 hr. 
        ini_set("memory_limit","-1");
        $mgu = memory_get_usage();
        $Log->writeVariableDBG($func." memory_usage: $mgu");
        set_time_limit(3600);

        $result = array();

        $rawPassThrough = isset($data['RawPassthrough']) ? $data['RawPassthrough'] : false;

        // Need id (job no), will get the directory path from the db, and all the files located there
        if (!isset($data['id'])) {
            $errorMessage          = 'Download zip archive creation failed: no job id specified.';
            $result['error']       = 500;
            $result['message']     = $errorMessage;
            $result['passthrough'] = false;
            return $result;
        }
        $job_no = $data['id'];

        // Make sure the job is not still running
        if ($this->isRFLRJobRunning($job_no)) {
            $errorMessage          = 'Download zip archive creation failed: job ' .$job_no. ' still in progress.';
            $result['error']       = 500;
            $result['message']     = $errorMessage;
            $result['passthrough'] = false;
            return $result;
        }

        // Get the path from the database per the job no
        $fullPathDir = $this->findStartingRFLRDirectory($job_no);
        if ($fullPathDir == false) {
            $errorMessage          = 'Download zip archive creation failed: no base directory found for job ' .$job_no;
            $result['error']       = 500;
            $result['message']     = $errorMessage;
            $result['passthrough'] = false;
            return $result;
        }

        // Create, get the zip file
        $deleteDir = "";
        if (isset($data['deleteDir']) && $data['deleteDir'] != false) {
            $deleteDir = "-d";
        }
        $cmd = "sudo /var/www/html/grid/portal/rflr_manage.php --create_zip $fullPathDir $deleteDir";
        $Log->writeVariableDBG("create_zip cmd:".$cmd);
        $zipName = "";
        $zipName = shell_exec($cmd);
        $errorMessage = "";
        if ($zipName !== false) {
           if ($zipName === "") {
              $errorMessage = "Error creating zip archive of recovered files, no zipName created.";
           } else {
              $Log->writeVariable("zip file is " . $zipName);
           }
        } else {
            $errorMessage = "Error creating zip archive of recovered files, invalid zipName created.";
        }

        // retrieve the size of the zip file in the directory and then update the rflr_jobs table
        $cmd = "sudo /var/www/html/grid/portal/rflr_manage.php --get_file_size --filenames '$zipName'";
        $size_bytes = shell_exec($cmd);
        $size_KB = $size_bytes / 1024;
        $Log->writeVariableDBG("zip file is " . $zipName . " and size is: ".$size_KB);
        if ($size_KB > 0) {
            // update the db
            $this->updateRFLRJobSize($job_no,$size_KB);
        }

        // If size_KB is greater than in-stream allowable, then create the download tunnel
        $maxDownloadMB = $this->BP->get_ini_value("SelfService", "MaxDownloadMB");
        if ($maxDownloadMB === false) {
            $maxDownloadMB = Restores::DEFAULT_MAX_DOWNLOAD_SIZE_MB;
        }
        $createDLTunnels = $this->get_ini_bool_value("SelfService", "CreateDownloadTunnels");
        if ( ($size_KB > $maxDownloadMB * 1024) && ($createDLTunnels === true) ) {
            // request is running on the target, get the target tunnel information and return it back to the source
            // But first need the sid id and directory, based on the job_no
            $rflrJobInfoRes = $this->getRFLRJobInfo($job_no);
            // check results error or content
            if (!isset($rflrJobInfoRes['source_id']) || !isset($rflrJobInfoRes['sid_rflr_dir'])) {
                $result['error']   = isset($rflrJobInfoRes['error'])   ? $rflrJobInfoRes['error'] : 500;
                $result['message'] = isset($rflrJobInfoRes['message']) ? $rflrJobInfoRes['message'] :
                    "Error obtaining target directory information for file recovery.";
                $result['passthrough'] = isset($rflrJobInfoRes['passthrough']) ? $rflrJobInfoRes['passthrough'] : false;
                return $result;
            }

            // successful task, update the RFLR jobs table and setup the tunnel
            $this->updateRFLRJobStatus($job_no,'manual-download'); // Note: 'manual-download' is parsed by rflr_manage.php (wd)
            return $this->setupTargetDownloadTunnel($rflrJobInfoRes['source_id'], $rflrJobInfoRes['sid_rflr_dir'], $zipName);
        }

        // Set the return data
        if ($errorMessage !== "") {
            $result['error'] = 500;
            $result['message'] = $errorMessage;
            $result['passthrough'] = false;
        } else {
            $result['data'] = '';
            $result['passthrough'] = $rawPassThrough;
            $result['zip'] = $zipName;

            // successful task, update the RFLR jobs table
            $this->updateRFLRJobStatus($job_no,'downloading'); // Note: 'downloading' is parsed by rflr_manage.php (wd)
        }
        return $result;
    }

    function downloadFilesFromTarget($data, $sid) {
        global $Log;
        $func = "downloadFilesFromTarget: ";

        // This function makes a remote request to get the extracted files and puts the entire content into memory on the src.
        // Therefore bump up the memory. master.ini SelfService settings limit the file size allowed during the restore request.
        // Also, as a precaution, bump up the processing time from the default of 2 minutes (/etc/php.ini) to 1 hr. 
        ini_set("memory_limit","-1");
        $mgu = memory_get_usage();
        $Log->writeVariableDBG($func." memory_usage: $mgu");
        set_time_limit(3600);

        // get the source's max size limit and let the target verify it in downloadFiles() once it adds up all the file sizes.
        $srcMaxDownloadMB = $this->BP->get_ini_value("SelfService", "MaxDownloadMB");
        if ($srcMaxDownloadMB === false) {
            $srcMaxDownloadMB = Restores::DEFAULT_MAX_DOWNLOAD_SIZE_MB;
        }
        // get the source's create download tunnel setting in case the downlad size is not supported for streaming.
        $srcCreateDLTunnels = $this->get_ini_bool_value("SelfService", "CreateDownloadTunnels");

        $request = "POST";
        $api = "/api/restore/download-files/";
        $parameters = "";
        $filenames = isset($data['filenames']) ? $data['filenames'] : false;
        $invalidNames = $this->invalid_names($filenames);
        if ($invalidNames != '') {
            $result['error']       = 500;
            $result['message']     = "Invalid filenames.";// . $invalidNames . ". Please select other files to proceed with download.";
            $result['passthrough'] = false;
            return $result;
        }
        $targetDir = isset($data['targetDir']) ? $data['targetDir'] : false;
        $deleteDir = isset($data['deleteDir']) ? $data['deleteDir'] : false;
        $id        = isset($data['id'])        ? $data['id']        : false;
        $restore_path   = isset($data['restore_path']) ? $data['restore_path'] : false;
        $postData = array('RawPassthrough' => 1,
            'targetDir'        => $targetDir,
            'deleteDir'        => $deleteDir,
            'id'               => $id,
            'srcMaxDownloadMB' => $srcMaxDownloadMB,
            'srcCreateDownloadTunnels' => $srcCreateDLTunnels,
            'filenames'        => $filenames,
            'restore_path'     => $restore_path);
        $resultData = $this->functions->remoteRequest("", $request, $api, $parameters, $postData, $sid, NULL, true, true);
        if (is_array($resultData)) {
            $result = $resultData;
        } else {
            $decodedResults = json_decode($resultData, true);
            if (is_array($decodedResults)) {
                if (isset($decodedResults['share_details'])) {
                    // setup the source download tunnel (no RFLR job on the source)
                    $result = $this->setupSourceDownloadTunnel($sid, $decodedResults);
                } else {
                    $result = $decodedResults;
                }
            } else {
                $result = array();
                $result['data'] = $resultData;
                $result['zip'] = 'data';
            }
        }
        return $result;
    }

    function restoreFilesOnTarget($data, $sid) {
        global $Log;
        $func = "restoreFilesOnTarget: ";

        if (!isset($data['size_KB'])) {
            $result = array();
            $errorMessage = 'Not able to determine size of files to be recovered.';
            $Log->writeVariable($func.$errorMessage);
            $result['error']   = 500;
            $result['message'] = $errorMessage;
            return $result;
        }

        // in case any directories are present, consider the backup size
        if (isset($data['dir_list']) && is_array($data['dir_list']) && !empty($data['dir_list'])) {
            if (isset($data['backup_size'])) {
                $size_KB = (int)($data['backup_size'] * 1024);
                $data['size_KB'] = $size_KB;
            }
        } else {
            // Check file size locally before initiating remote restore. Don't allow over some max due to memory limitations.
            $size_KB = (int)$data['size_KB'];
        }
        $Log->writeVariable($func."download filesize request:".$size_KB." (KB)");
        $maxDownloadMB = $this->BP->get_ini_value("SelfService", "MaxDownloadMB");
        if ($maxDownloadMB === false) {
            $maxDownloadMB = Restores::DEFAULT_MAX_DOWNLOAD_SIZE_MB;
        }
        $createDLTunnels = $this->get_ini_bool_value("SelfService", "CreateDownloadTunnels");
        // Note: source size and tunnel values are pass to the target so it can validate all settings

        $request = "POST";
        $api = "/api/restore/full/";
        $parameters = "";
        $postData = $data;
        $postData['remote'] = true;
        $postData['srcMaxDownloadMB'] = $maxDownloadMB ;
        $postData['srcCreateDownloadTunnels'] = $createDLTunnels ;
        $result = $this->functions->remoteRequest("", $request, $api, $parameters, $postData, $sid, NULL, true);
        $Log->writeVariableDBG('restore on target result');
        $Log->writeVariableDBG($result);
        return $result;
    }

    function findDeviceTempDir($sid, $data)
    {
        global $Log;
        $func = "findDeviceTempDir: ";

        // This method returns:
        // - valid results should include 'sid_rflr_dir', 'device_temp_dir', 'create_dl_tunnels'
        // - invalid results should include 'error', and 'message'

        $result = array();
        $remoteRequest = (isset($_GET['source_id']) ? true : false);

        // Make sure the size asked for is provided
        if (!isset($data['size_KB'])) {
            // no space requirements, log and return an error
            $errorMessage = 'Not able to determine size of files to be recovered.';
            $Log->writeVariable($func . $errorMessage);
            $result['error'] = 500;
            $result['message'] = $errorMessage;
            return $result;
        }
        $size_KB = $data['size_KB'];
        $Log->writeVariable($func."download filesize request:".$size_KB." (KB)");

        // Check file size (could be a local or remote download check).
        // Don't allow over some max unless download tunnels are allowed.
        $maxDownloadMB   = $this->BP->get_ini_value("SelfService", "MaxDownloadMB");
        if ($maxDownloadMB === false) {
            $maxDownloadMB = Restores::DEFAULT_MAX_DOWNLOAD_SIZE_MB;
        }

        // Obtain any size or tunnel limitations passed in from the source
        if (isset($data['srcMaxDownloadMB'])) {
            $srcMaxDownloadMB = $data['srcMaxDownloadMB'];
            $Log->writeVariable($func."the source's max download filesize is:".$srcMaxDownloadMB." (MB)");
            if ($srcMaxDownloadMB < $maxDownloadMB) {
                $Log->writeVariable($func."comparing against the source's max download filesize of:".$srcMaxDownloadMB." (MB)");
                $maxDownloadMB = $srcMaxDownloadMB;
            }
        }
        $srcCreateDLTunnels = true;
        if (isset($data['srcCreateDownloadTunnels'])) {
            $srcCreateDLTunnels = $data['srcCreateDownloadTunnels'];
        }

        $needDLTunnels = false; // will be set only if size_KB exceeds limits
        if (($size_KB > $maxDownloadMB * 1024) || ($maxDownloadMB == 0)) {
            $createDLTunnels = $this->get_ini_bool_value("SelfService", "CreateDownloadTunnels");
            if ($createDLTunnels === false || $srcCreateDLTunnels === false) {
                $result = array();
                $size_MB = round(($size_KB / 1024));
                if ($maxDownloadMB == 0) {
                    $errorMessage  = "Downloading service is disabled. Using the General Configuration (Advanced) dialog, ";
                    $errorMessage .= "please adjust the SelfService maximum download and/or the download tunnel values.";
                }
                else {
                    $errorMessage  = "Request size of ".$size_MB ." (MB) exceeds download size limit of " . $maxDownloadMB . " (MB). ";
                    $errorMessage .= "Please select fewer and/or smaller files or directories to recover.";
                }
                $Log->writeVariable($func.$errorMessage);
                $result['error']   = 500;
                $result['message'] = $errorMessage;
                return $result;
            }
            $needDLTunnels = true;
        }

        // Get allowable RFLR free space, a percentage of actual free space.
        $flrPercent = (int) $this->BP->get_ini_value("SelfService", "MaxFLRDownloadSizePCT");
        if ($flrPercent === false || $flrPercent < 1) {
            $flrPercent = 10;
        }
        if ($flrPercent > 50) {
            $flrPercent = 50;
        }
        $flrPercent = $flrPercent / 100;

        // Get Device info (name and free space)
        $retval = $this->BP->get_rflr_device_info((int)$sid);

        if (!$retval) {
            // no device, not much we can do, log and return an error
            $errorMessage = $this->BP->getError();
            $Log->writeVariable($func.$errorMessage);
            $result['error']   = 500;
            $result['message'] = $errorMessage;
            return $result;
        }
        // e.g. /backups/D2DBackups/_rflr/sid_118/4560988824/
        // e.g. /backups/device2/_rflr/sid_118/4560988824/
        $device_temp_dir = $retval['device_temp_dir'];

        // e.g. /backups/D2DBackups/_rflr/sid_4/
        $sid_rflr_dir = $retval['sid_rflr_dir'];
        $mb_free = $retval['freeSpaceMB']; // considers the queued space as well

        // Get the results and check free space
        $kb_flr_free = ($mb_free * 1024 * $flrPercent);
        $Log->writeVariable($func."device free space:".$kb_flr_free." (KB)");

        // Have enough free space?
        if ($size_KB > $kb_flr_free) {
            // insufficient space, log and return an error
            if ($remoteRequest == true) {
                $errorMessage  = "The target does not have enough reserved scratch free space (".$kb_flr_free." KB) for the request (".$size_KB." KB). ";
                $errorMessage .= "This could mean there are too many FLR tasks queued. If so, please wait for some tasks to complete. It could also mean the target appliance backup partition is too full or the percentage allocated for remote FLRs is too small. If the file is too large, an alternative is to import the backup.";
            }
            else {
                $errorMessage = "The disk device does not have enough reserved scratch free space (".$kb_flr_free." KB) for the request (".$size_KB." KB)";
            }
            $Log->writeVariable($func.$errorMessage);
            $result['error']   = 500;
            $result['message'] = $errorMessage;
            return $result;
        }

        $Log->writeVariable($func."download temp dir:".$device_temp_dir);

        // valid results include 'device_temp_dir'
        $result['sid_rflr_dir']      = $sid_rflr_dir; // needed for tunnels
        $result['device_temp_dir']   = $device_temp_dir;
        $result['create_dl_tunnels'] = $needDLTunnels;
        return $result;
    }

    function insertRFLRJobRecord($job_no, $directory, $src_id, $size_KB, $status='start')
    {
        global $Log;
        $func = "insertRFLRJobRecord: ";

        // This database information will be used to locate the files to zip for the full restore, and
        // also used by the watchdog to cleanup stale data.

        // Add a record to link the job and the unique directory
        $rflrJobInfo = array();
        $rflrJobInfo['id'] = $job_no;
        $rflrJobInfo['system_id'] = (int)$src_id;
        $rflrJobInfo['directory'] = (string)$directory;
        $rflrJobInfo['size_kb'] = $size_KB;
        $rflrJobInfo['status'] = $status;

        $retval = $this->BP->set_rflr_job_info($rflrJobInfo);
        if (!$retval) {
            $Log->writeVariable($retval);
            $result['error']   = 500;
            $result['message'] = $retval;
            return $result;
        }

        $Log->writeVariableDBG("Query: insert successful for job no: $job_no");
    }

    function isRFLRJobRunning($job_no)
    {
        global $Log;
        $func = "isRFLRJobRunning: ";

        // return a boolean: true -> running, false -> not running.
        $not_running = false;

        // Check the state of the job in the database

        // Find the job and its status. If not found, then done.
        $retval = $this->BP->get_job_info($job_no);

        if (!$retval) {
            return $not_running;
        }
        $comment = $retval['comment'];
        $percent_complete = $retval['percent_complete'];

        // Get the results and check for 'Task completed' or 100% completed
        $status = true;
        $Log->writeVariableDBG("Results: job_no: $job_no, comment: $comment, percent_done: $percent_complete");
        if ($comment == 'Task completed' || $percent_complete == 100) {
           $status = $not_running;
        }

        return $status;
    }

    function findStartingRFLRDirectory($job_no)
    {
        global $Log;
        $func = "findStartingRFLRDirectory: ";

        // return the file direction extraction pathname or false if not found, or an error occurred.

        // Find the job and its status. If not found, then done.
        $retval = $this->BP->get_rflr_job_info($job_no);

        if (!$retval) {
            // log an error, and return
            $errorMessage = "Error: Unable to locate any path for the RFLR job no ".$job_no;
            $Log->writeVariable($func.$errorMessage);
            return false;
        }

        // Get the results and return the dir
        $status = true;
        $fullPathDir = $retval['directory'];
        $Log->writeVariableDBG("Results: job_no: $job_no, directory: $fullPathDir");

        return $fullPathDir;
    }

    function updateRFLRJobStatus($job_no, $status_msg)
    {
        global $Log;
        $func = "updateRFLRJobStatus: ";

        // void return, update status and timestamp of rflr job either based on job no or directory

        // Update the job's status
        $rflrJobInfo = array();
        $rflrJobInfo['id'] = $job_no;
        $rflrJobInfo['status'] = $status_msg;

        $retval = $this->BP->set_rflr_job_info($rflrJobInfo);
        if (!$retval) {
            // log an error, and return
            $errorMessage = "Error: Unable to update RFLR job no ".$job_no;
            $Log->writeVariable($func.$errorMessage);
        }

        return;
    }

    function updateRFLRJobSize($job_no, $sizeKB)
    {
        global $Log;
        $func = "updateRFLRJobSize: ";

        // void return, update size_kb of rflr job

        // Update the job's size
        $rflrJobInfo = array();
        $rflrJobInfo['id'] = $job_no;
        $rflrJobInfo['size_kb'] = $sizeKB;

        $retval = $this->BP->set_rflr_job_info($rflrJobInfo);
        if (!$retval) {
            // log an error, and return
            $errorMessage = "Error: Unable to update RFLR job no ".$job_no;
            $Log->writeVariable($func.$errorMessage);
        }

        return;
    }

    function getRFLRJobInfo($job_no)
    {
        global $Log;
        $func = "getRFLRJobInfo: ";

        // return an array with 'source_id', and 'sid_rflr_dir'
        $result = array();

        // Find the job system id and directory. If not found, then done.
        $retval = $this->BP->get_rflr_job_info($job_no);
        if (!$retval) {
            // return an error
            $errorMessage = "Warning: unable to complete download request, RFLR job no ".$job_no. "not found.";
            $Log->writeVariable($func.$errorMessage);
            $result['error']       = 500;
            $result['message']     = $errorMessage;
            $result['passthrough'] = false;
            return $result;
        }

        $source_id = $retval['system_id'];
        $directory = $retval['directory'];
        $Log->writeVariableDBG("Results: job_no: $job_no, system_id: $source_id, dir: $directory");

        // Example results: 3 | /backups/D2DBackups/_rflr/sid_3/58091c4c05cdf
        // Want the dir to be upto the sid_3/
        $result['source_id'] = $source_id;
        $needle = "sid_".$source_id."/";
        $result['sid_rflr_dir'] = strstr($directory, $needle, true).$needle;

        return $result;
    }

    function checkFLRLimits($src_id)
    {
        global $Log;
        $func = "checkFLRLimits: ";

        // This method checks for total and source specific FLR sessions running.

        // This method returns:
        // - true, implies SUCCESS
        // - an array with 'error', and 'message', implies FAILURE

        // if src_id is not provided, no limit checks are performed.
        if ($src_id == NULL || $src_id == false) {
            $Log->writeVariable($func."No limit checks performed, source id was NULL");
            return true;
        }

        // Get allowable individual and overall limits.
        $maxSrcSessions = (int) $this->BP->get_ini_value("SelfService", "MaxNumOfConcurrentFLRPerSrc");
        if ($maxSrcSessions === false || $maxSrcSessions < 1) {
            $maxSrcSessions = 5;
        }
        $maxTotalSessions = (int) $this->BP->get_ini_value("SelfService", "MaxTotalNumOfConccurrentFLRs");
        if ($maxTotalSessions === false || $maxTotalSessions < 1) {
            $maxTotalSessions = 50;
        }

        // Get grace period for FLRs to expire
        $gracePeriodPerFLRHrs = (int) $this->BP->get_ini_value("SelfService", "GracePeriodPerFLRHrs");
        if ($gracePeriodPerFLRHrs === false || $gracePeriodPerFLRHrs < 1) {
            $gracePeriodPerFLRHrs = 24;
        }
        $maxRunningTimePerFLRHrs = (int) $this->BP->get_ini_value("SelfService", "MaxRunningTimePerFLRHrs");
        if ($maxRunningTimePerFLRHrs === false || $maxRunningTimePerFLRHrs < 24) {
            $maxRunningTimePerFLRDays = 1;
        } else {
            $maxRunningTimePerFLRDays = ceil($maxRunningTimePerFLRHrs / 24);
        }

        $sid = 1;
        require_once('jobs/jobs-active.php');
        $jobsActive = new JobsActive($this->BP, $sid, $this->functions);

        $FLRJobs = $jobsActive->getFLRJobs($sid);
        $total_running = count($FLRJobs);      // total FLR sessions running on the target

        $systems = $this->functions->selectSystems($sid);
        $FLRJobsPerSrc = $jobsActive->getTargetFLRJobs($FLRJobs, $systems, (int)$src_id);
        $total_src_running = count($FLRJobsPerSrc);      // FLR sessions running per source

        // check the results
        $Log->writeVariable($func." total_src_running: $total_src_running, total_running: $total_running");
        if ($total_src_running >= $maxSrcSessions) {
            $sName = " id ".$src_id;
            $sInfo = $this->BP->get_system_info($src_id);
            if ($sInfo !== false) {
                $sName = $sInfo['name'];
            }
            $errorMessage = "Maximum number of recovery jobs for the appliance ".$sName." have been reached per target restrictions. ";
            if ($total_src_running > 0) {
                $errorMessage .= "To continue, please complete recovery of the files and remove the file level recovery image before retrying this operation.";
            }
            $Log->writeVariable($func.$errorMessage);
            $result['error']   = 500;
            $result['message'] = $errorMessage;
            return $result;
        }
        if ($total_running >= $maxTotalSessions) {
            $errorMessage = "Maximum number of allowable recovery jobs have been reached. Please wait ";
            if ($gracePeriodPerFLRHrs == 1) {
                $errorMessage .= $gracePeriodPerFLRHrs . " hour ";
            } else {
                $errorMessage .= $gracePeriodPerFLRHrs . " hours ";
            }
            $errorMessage .= "before retrying this operation. If you cannot continue after ";
            if ($maxRunningTimePerFLRDays == 1) {
                $errorMessage .= $maxRunningTimePerFLRDays . " day, ";
            } else {
                $errorMessage .= $maxRunningTimePerFLRDays . " days, ";
            }
            $errorMessage .= "please contact your cloud service provider.";
            $Log->writeVariable($func.$errorMessage);
            $result['error']   = 500;
            $result['message'] = $errorMessage;
            return $result;
        }

        // Success
        return True;
    }

    function sqlMultiRestore($data, $sid)
    {
        $result = array();
        $backupIDs = array(); //array with all the unique backup IDs
        $backups_array = $data['backups'];
        $jobIDs = "";
        $job_delim = "";
        $error_msg = "";
        $atleastOneError = false;
        $error_delim = "";

        foreach ($backups_array as $backup_arr) {
            $backups = "";
            if (array_key_exists('backup_id', $backup_arr)) {
                $backup_id = $backup_arr['backup_id'];
            } else {
                $result['error'] = 500;
                $result['message'] = "Backup ID is required";
                return $result;
            }

            if (array_key_exists('type', $backup_arr)) {
                $type = $backup_arr['type'];
            } else {
                $result['error'] = 500;
                $result['message'] = "Backup type is required";
                return $result;
            }
            if (array_key_exists('client_id', $backup_arr)) {
                $client_id = $backup_arr['client_id'];
            } else {
                $result['error'] = 500;
                $result['message'] = "Client ID is required";
                return $result;
            }
            if (array_key_exists('database_name', $backup_arr)) {
                $database = $backup_arr['database_name'];
            } else {
                $result['error'] = 500;
                $result['message'] = "Database name is required";
                return $result;
            }
            if (array_key_exists('instance_name', $backup_arr)) {
                $instance = $backup_arr['instance_name'];
            } else {
                $result['error'] = 500;
                $result['message'] = "Instance name is required";
                return $result;
            }
            $app_type = $backup_arr['app_type'];

            // in case of differential and transaction backups, get the restore group
            if ($app_type === Constants::APPLICATION_TYPE_NAME_SQL_SERVER && ($type === Constants::BACKUP_DISPLAY_TYPE_DIFFERENTIAL ||
                    $type === Constants::BACKUP_DISPLAY_TYPE_TRANSACTION)) {
                $restoreGroup = $this->getRestoreGroup($backup_id, $sid);

                // getting the unique backups IDs and adding them to an array
                $backups = $this->uniqueBackups($restoreGroup, $backupIDs);
                $array_backups = explode(",", $backups);
                foreach ($array_backups as $arr_backups) {
                    $backupIDs[] = (int)$arr_backups;
                }
            } else {
                if (!in_array($backup_id, $backupIDs)) {
                    $backups = $backup_id;
                    $backupIDs[] = (int)$backups;
                }
            }
            // continue only if there are any unique backups to restore
            if (isset($backups) && $backups !== null && $backups !== "") {
                $destination = $instance . '|' . $database;
                $options = array();
                $options['client_id'] = $client_id;
                $options['target'] = $destination;
                $options['directory'] = null;
                $options['point_in_time'] = null;
                $options['before_command'] = null;
                $options['after_command'] = null;

                $resultArray = $this->BP->restore_application($backups, $options, $sid);
                if ($resultArray !== false) {
                    $tempRes = "";
                    $delim = "";
                    $msg = "";
                    $msg_delim = "";

                    foreach ($resultArray as $resArr) {
                        $tempRes = $tempRes . $delim . $resArr['job_id'];
                        $delim = ", ";

                        // creating an applicable error message
                        if (array_key_exists('msg', $resArr)) {
                            $backupType = isset($resArr['type']) ? $this->functions->getBackupTypeString($resArr['type']) : "";
                            $msg = $msg . $msg_delim . $backupType . " restore failed. " . $resArr['msg'];
                            $msg_delim = "\n";
                            $atleastOneError = true;
                        }
                    }

                    if ($msg !== "" && $atleastOneError) {
                        // concatenating the error messages
                        if ($error_msg === "") {
                            $error_msg = $msg;
                            $error_delim = "\n";
                        } else {
                            $error_msg = $error_msg . $error_delim . $msg;
                        }
                        $result['error'] = 500;
                        $result['message'] = $error_msg;
                    } else {
                        // concatenating all the job IDs
                        if ($jobIDs === "") {
                            $jobIDs = $tempRes;
                            $job_delim = ", ";
                        } else {
                            $jobIDs = $jobIDs . $job_delim . $tempRes;
                        }
                        $result['id'] = $jobIDs;
                    }
                } else {
                    $result = $resultArray;
                }
            }
        }
        return $result;
    }

    function uniqueBackups($backups, $backups_list)
    {
        $uniqueBackups = "";
        $delim = "";

        // backups list is empty, return all the backups as they're unique
        if (count($backups_list) === 0) {
            return $backups;
        }
        $backups_array = explode(",", $backups);

        // check for backups not in backups_list and return those
        for ($i = 0; $i < count($backups_array); $i++) {
            if (!in_array((int)$backups_array[$i], $backups_list)) {
                $uniqueBackups = $uniqueBackups . $delim . $backups_array[$i];
                $delim = ",";
            }
        }

        return $uniqueBackups;
    }

    function get_ini_bool_value($section, $field)
    {
        global $Log;
        $func = "get_ini_bool_value: ";

        $v = $this->BP->get_ini_value($section, $field);
        $Log->writeVariableDBG($func."value:".$v);
        if (strcasecmp($v, "True") == 0) { return true; }
        if (strcasecmp($v, "Yes") == 0) { return true; }
        if ($v == 1) { return true; }

        return false;
    }

    function setupTargetDownloadTunnel($source_id, $sid_rflr_dir, $tgt_zip_name)
    {
        global $Log;
        $func = "setupTargetDownloadTunnel: ";

        // This method creates as needed the target side portion of a proxy pipe allowing the source
        // to advertize a URL that a source administrator can access to copy the target extracted files
        // to a location of their choice. The proxy pipe is created using CIFS (over OpenVPN if configured)
        // for each source/target pair and is internal use only.

        // Need the target-side source_id, which should of been part of the session
        if ($source_id == "") {
            $errorMessage = "Unable to create download tunnel, no source system id provided.";
            $Log->writeVariable($func.$errorMessage);
            $result['error']   = 500;
            $result['message'] = $errorMessage;
            return $result;
        }

        // Get the download target tunnel, if non-existent, create it
        // e.g. path returned: /backups/D2DBackups/_rflr/sid_3/
        $tgt_download_path = $this->BP->get_proxy_tunnel_path($source_id);
        if ($tgt_download_path === false) {
            // create the proxy download mount, target side
            $source_side = false;
            $create_download_tunnel_res = $this->BP->create_proxy_tunnel($source_id, $sid_rflr_dir, $source_side);
            if ($create_download_tunnel_res !== false) {
                $tgt_download_path = $this->BP->get_proxy_tunnel_path($source_id);
            } else {
                $errorMessage  = "Download facility temporarily unavailable. Once available, your restored files can be ";
                $errorMessage .= "retrieved from the URL https://src_appliance_ip/downloads/. This could take up to an hour.";
                $Log->writeVariable($func.$errorMessage);
                $result['error']   = 500;
                $result['message'] = $errorMessage;
                return $result;
            }
        }
        if ($tgt_download_path === false) {
            $errorMessage  = "Download facility temporarily unavailable. Once available, your restored files can be ";
            $errorMessage .= "retrieved from the URL https://src_appliance_ip/downloads/. This could take up to an hour.";
            $Log->writeVariable($func.$errorMessage);
            $result['error']   = 500;
            $result['message'] = $errorMessage;
            return $result;
        }

        // return source-side style zipName (just the tempdir and zip)
        // sample tgt_zip_name: /backups/D2DBackups/_rflr/sid_3/58174bf79c3b3/Unitrends-Restore240.zip
        // want: 58174bf79c3b3/Unitrends-Restore240.zip
        $needle = "sid_".$source_id."/";
        $index  = strpos($tgt_zip_name, $needle) + strlen($needle);
        $urlZip = substr($tgt_zip_name, $index);

        // make sure the tunnel link path has a trailing slash
        $tgt_download_path .= (substr($tgt_download_path, -1,1) == '/' ? '' : '/');

        $result['share_details']     = $tgt_download_path; // triggers the src to create src dl piece, and report it!!!
        $result['urlzip']            = $urlZip;
        $result['available_targets'] = "smb";
        $result['passthrough']       = false;
        return $result;
    }

    function setupSourceDownloadSymlink($source_id, $sid_rflr_dir, $zipName)
    {
        global $Log;
        $func = "setupSourceDownloadSymlink: ";

        // This method runs on a source to handle a local file restore. Creates as needed the URL symlink
        // to the local sources restore root directory. This is not a tunnel, just a URL link.

        // Need the source-sid source_id
        if ($source_id == "") {
            $errorMessage = "Unable to create download URL, no system id provided.";
            $Log->writeVariable($func.$errorMessage);
            $result['error']   = 500;
            $result['message'] = $errorMessage;
            return $result;
        }

        // Get the src url, if non-existent, create it
        $src_download_url = $this->BP->get_dl_url($source_id);
        if ($src_download_url === false) {
            if ($sid_rflr_dir == "") {
                $errorMessage = "Unable to create download URL, no local directory path was provided.";
                $Log->writeVariable($func.$errorMessage);
                $result['error']   = 500;
                $result['message'] = $errorMessage;
                return $result;
            }

            // create the download url
            $create_download_url_res = $this->BP->create_dl_url($source_id, $sid_rflr_dir);
            if ($create_download_url_res !== false) {
                $src_download_url = $this->BP->get_dl_url($source_id);
            } else {
                $errorMessage  = "Download facility temporarily unavailable. Once available, your restored files can be ";
                $errorMessage .= "retrieved from the URL https://src_appliance_ip/downloads/. This could take up to an hour.";
                $Log->writeVariable($func.$errorMessage);
                $result['error']   = 500;
                $result['message'] = $errorMessage;
                return $result;
            }
        }
        if ($src_download_url === false) {
            $errorMessage  = "Download facility temporarily unavailable. Once available, your restored files can be ";
            $errorMessage .= "retrieved from the URL https://src_appliance_ip/downloads/. This could take up to an hour.";
            $Log->writeVariable($func.$errorMessage);
            $result['error']   = 500;
            $result['message'] = $errorMessage;
            return $result;
        }

        // success, return a message
        $msg = "The restored files can be downloaded from the URL ".$src_download_url;
        $Log->writeVariable($func.$msg);

        // return source-side style zipName (just the tempdir and zip)
        // sample zipName: /backups/D2DBackups/_rflr/sid_3/58174bf79c3b3/Unitrends-Restore240.zip
        // want: 58174bf79c3b3/Unitrends-Restore240.zip
        $needle = "sid_".$source_id."/";
        $index  = strpos($zipName, $needle) + strlen($needle);
        $urlZip = substr($zipName, $index);

        // make sure the tunnel link path has a trailing slash
        $src_download_url .= (substr($src_download_url, -1,1) == '/' ? '' : '/');

        $result['link']        = $src_download_url;
        $result['urlzip']      = $urlZip;
        $result['passthrough'] = false;
        return $result;
    }

    function setupSourceDownloadTunnel($source_id, $resultData)
    {
        global $Log;
        $func = "setupSourceDownloadTunnel: ";

        // This method creates as needed the source side portion of a proxy pipe allowing the source
        // to advertize a URL that a source administrator can access to copy the target extracted files
        // to a location of their choice. The src-target proxy pipe, is created using CIFS (over OpenVPN
        // if configured) for each source/target pair.

        // Need the source-sid source_id
        if ($source_id == "") {
            $errorMessage = "Unable to create download facility, no system id provided.";
            $Log->writeVariable($func.$errorMessage);
            $result['error']   = 500;
            $result['message'] = $errorMessage;
            return $result;
        }

        // Get the src tunnel url, if non-existent, create it
        $src_tunnel_url = $this->BP->get_proxy_tunnel_url($source_id);
        if ($src_tunnel_url === false) {
            // Need the share_details, which was returned from the target during their setupTargetDownloadTunnel() call.
            if (!isset($resultData['share_details'])) {
                $errorMessage = "Unable to create download facility, no share_details path was provided.";
                $Log->writeVariable($func.$errorMessage);
                $result['error']   = 500;
                $result['message'] = $errorMessage;
                return $result;
            }

            // get the tgt hostname from the results, needed to distinquished the URL src-tgt links, and src-local links
            $tgt_hostname = $this->functions->getTargetName();
            if ($tgt_hostname === false) { $tgt_hostmane = time(); }

            // create the proxy download mount, source side
            $source_side = true;
            $create_tunnel_res = $this->BP->create_proxy_tunnel($source_id, $resultData['share_details'], $source_side, $tgt_hostname);
            if ($create_tunnel_res !== false) {
                $src_tunnel_url = $this->BP->get_proxy_tunnel_url($source_id);
            } else {
                $errorMessage  = "Download facility temporarily unavailable. Once available, your restored files can be ";
                $errorMessage .= "retrieved from the URL https://src_appliance_ip/downloads/. This could take up to an hour.";
                $Log->writeVariable($func.$errorMessage);
                $result['error']   = 500;
                $result['message'] = $errorMessage;
                return $result;
            }
        }
        if ($src_tunnel_url === false) {
            $errorMessage  = "Download facility temporarily unavailable. Once available, your restored files can be ";
            $errorMessage .= "retrieved from the URL https://src_appliance_ip/downloads/. This could take up to an hour.";
            $Log->writeVariable($func.$errorMessage);
            $result['error']   = 500;
            $result['message'] = $errorMessage;
            return $result;
        }

        // success, return a message
        $msg = "The restored files can be downloaded from the URL ".$src_tunnel_url;
        $Log->writeVariable($func.$msg);

        // make sure the tunnel link path has a trailing slash
        $src_tunnel_url .= (substr($src_tunnel_url, -1,1) == '/' ? '' : '/');

        $result['link'] = $src_tunnel_url;
        if (isset($resultData['urlzip'])) { $result['urlzip'] = $resultData['urlzip']; }
        $result['passthrough'] = false;
        return $result;
    }
}
?>
