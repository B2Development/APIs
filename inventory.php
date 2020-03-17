<?php
//IN PROGRESS
class Inventory
{
    private $BP;

    const INVENTORY_GET_TYPE_TREE      = 0;
    const INVENTORY_GET_TYPE_SETTINGS  = 1;
    const INVENTORY_GET_TYPE_STATUS    = 2;

    const INVENTORY_NODE_SEPARATOR = "_";

    const INVENTORY_STATUS_SUCCESS          = 0;
    const INVENTORY_STATUS_WARNINGS         = 1;
    const INVENTORY_STATUS_FAILURE          = 2;

    //Change this before release of Block Agent
    const BLOCK_AGENT_RELEASE_VERSION = '10.2.0';

    const INVENTORY_MODEL_SAVED_STATE  = "S:";

    const PLACEHOLDER_COPIES_UUID = "copiesUUID";

    public function __construct($BP)
    {
        $this->BP = $BP;

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);
    }

    public function get($which, $data, $sid, $systems)
    {
        $returnArray = array();
        $returnArray['inventory'] = false;

        if ($which == -1)
        {
            // GET /api/inventory/?sid={sid}
            $returnArray['inventory'] = $this->getInventoryTree( $data, $sid );

        }
        else //if (is_string($which[0]))
        {
            switch ($which[0])
            {
                case 'status':
                    // GET /api/inventory/status/?sid={sid}
                    $returnArray['inventory'] = $this->getInventoryStatus( $data, $sid );
                    break;
                case 'sync':
                    // GET /api/inventory/sync/?sid={sid}
                    $returnArray = $this->getInventorySync( $sid );
                    break;
                case 'instance':
                    // GET /api/inventory/instance/?sid={sid}&instances=(a,b,c,d)
                    $returnArray = $this->getInventoryInstance( $sid );
                    break;
                case 'get_disk_info':
                    // GET /api/inventory/get_disk_info/?sid={sid}&?appid={appid}
                    $returnArray = $this->getDiskInfo( $sid );
                    break;
                case 'nav-groups':
                    // GET /api/inventory/nav-groups
                    require_once('navgroups.php');
                    $nav = new NavigationGroups($this->BP);
                    $returnArray = $nav->get();
                    if ($returnArray !== false) {
                        $returnArray['inventory'] = true;
                    }
                    break;
                case 'rdr':
                    // GET /api/inventory/rdr/?sid={sid}
                    $returnArray['inventory'] = $this->getRdrInventoryTree( $data, $sid );
                    break;
                case 'replicas':
                    // GET /api/inventory/replicas/?sid={sid}
                    $returnArray['inventory'] = $this->getReplicasInventoryTree( $data, $sid );
                    break;
                default:
                    // GET /api/inventory/{id}/...
                    $nodeID = $which[0];
                    if ( count($which) > 1 )
                    {
                        switch ($which[1])
                        {
                            case 'status':
                                // GET /api/inventory/{id}/status/?sid={sid}
                                /*require_once('inventory_status.php');
                                $inventoryStatus = new InventoryStatus($this->BP);
                                $returnArray['inventory'] = $inventoryStatus->get_inventory_status( $systems );*/
                                //$returnArray['inventory'] = $this->getInventoryStatus( $sid, $nodeID, NULL, $data );
                                $returnArray['inventory'] = $this->getInventoryStatus( $data, $sid );
                                break;
                            default:
                                $returnArray['inventory'] = $this->getInventoryNodeDisks( $nodeID );
                                break;
                        }
                    }
                    else
                    {
                        $returnArray['inventory'] = $this->getInventoryNodeDisks( $nodeID );
                    }
                    break;
            }

        }
        if ( $returnArray['inventory'] === false )
        {
            $returnArray = false;
        }
        return($returnArray);
    }

    public function put($which, $data) {
        $status = false;
        $filter = NULL;
        $doInventoryPut = true;
        if ( $which != -1 and is_array($which) )
        {
            switch ( $which[0] )
            {
                case 'VMware':
                case 'Hyper-V':
                case 'AHV':
                    // PUT /api/inventory/{app}/?sid={id}
                    $filter = array( 'applications' => array($which[0]));
                    /* Not needed for first out
                    if (isset($which[1]) and is_numeric($which[1]))
                    {
                        // PUT /api/inventory/{app}/{id}/?sid={id}
                    }*/
                    break;
                case 'nav-groups':
                    // PUT /api/inventory/nav-groups
                    $doInventoryPut = false;
                    require_once('navgroups.php');
                    $nav = new NavigationGroups($this->BP, NULL, false);
                    $status = $nav->put($data);
                    break;
                default:
                    // (PUT) /api/inventory/{node_id}
                    $doInventoryPut = false;
                    if (isset($which[1]))
                    {
                        switch ( $which[1] )
                        {
                            case 'disks':
                                // (PUT) /api/inventory/{node_id}/disks
                                $status = $this->putInventoryDisks( $which[0], $data );
                                break;
                        }
                    }
                    break;
            }
        }
        if ( $doInventoryPut === true )
        {
            if (isset($data['sid']))
            {
                $sysID = $data['sid'];
                if($this->BP->put_inventory($filter, $sysID))
                {
                    $status = true;
                }
            }
            else
            {
                if($this->BP->put_inventory($filter))
                {
                    $status = true;
                }
            }
        }
        return $status;
    }

    private function getInventoryTree( $data, $sid )
    {
        $showSystemClient = false;
        if ($data !== NULL and array_key_exists('showSystemClient', $data))
        {
            $showSystemClient = ($data['showSystemClient'] === '1');
        }

        $getDisks = false;  //whether or not to get info about VM disks for VMware
        if ($data !== NULL and array_key_exists('disks', $data))
        {
            $getDisks = ((int)$data['disks'] === 1);
        }

        $showCopiedAssetsView = false;
        if ($data !== NULL and array_key_exists('grandclient', $data))
        {
            $showCopiedAssetsView = ($data['grandclient'] === '1');
            $localSystemID = $this->BP->get_local_system_id();
        }

        if ( $showCopiedAssetsView === false )
        {
            $systems = $this->functions->selectSystems( $sid, false );
        }
        else
        {
            $systems = $this->functions->selectReplicatingSystems( $sid );
        }

        $inventoryArray = array();


        if (isset($data['uid'])) {
            require_once('navgroups.php');
            $nav = new NavigationGroups($this->BP, $data['uid']);
            $showNavGroups = $nav->showGroups();
        } else if (isset($data['showNavGroups']) && $data['showNavGroups'] == 1) {
            require_once('navgroups.php');
            $nav = new NavigationGroups($this->BP);
            $showNavGroups = true;
        } else {
            $showNavGroups = false;
            $nav = NULL;
            $navGroups = array();
        }

        $navGroupsArray = array();
        if ($showNavGroups) {
            $navGroups = $nav->getAllGroups();
            // sort groups by name.
            $navGroups = $this->sort($navGroups, 'usort', array('Inventory', 'compareGroupNames'));
            foreach ($navGroups as $group) {
                $navGroupArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                    Constants::INVENTORY_TYPE_FAMILY_NAVGROUP,
                    $group['@attributes']['id'],
                    $group['@attributes']['name'],
                    Constants::INVENTORY_ID_NAVGROUP);
                $navGroupArray['treeParentID'] = $group['@attributes']['treeParentID'];
                $navGroupArray['group'] = $group;
                $navGroupsArray[] = $navGroupArray;
            }
            $nav->linkGroups($navGroupsArray);
        }

        foreach ( $systems as $systemID => $systemName )
        {
            if ( $showCopiedAssetsView === false )
            {
                $clients = $this->BP->get_client_list($systemID);
            }
            else
            {
                $clients = $this->BP->get_grandclient_list($systemID);
            }

            $systemNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                Constants::INVENTORY_TYPE_FAMILY_SYSTEM,
                $systemID,
                $systemName,
                Constants::INVENTORY_ID_SYSTEM);

            $isBlockSupported = $this->BP->block_backup_supported($systemID);
            if ( $isBlockSupported === -1 )
            {
                global $Log;
                $Log->writeError("cannot determine whether block is supported: " .$this->BP->getError(), true);

                // Users should be able to see clients for backup even if the call fails
                $isBlockSupported = true;
            }


            if ( $clients !== false )
            {
                // sort clients by name.
                $clients = $this->sort($clients, null);
                foreach ($clients as $clientID => $clientName)
                {
                    if ( $showSystemClient === true or $clientName !== $systemName )
                    {
                        if ( $showCopiedAssetsView === false )
                        {
                            $clientInfo = $this->BP->get_client_info($clientID, $systemID);
                        }
                        else
                        {
                            $clientInfo = $this->BP->get_client_info($clientID, $localSystemID);
                        }
                        if ($clientInfo !== false)
                        {
                            // additional properties for SQL clusters and SQL availability groups
                            $is_sql_cluster = (isset($clientInfo['is_sql_cluster']) && $clientInfo['is_sql_cluster'] !== false) ? true : false;
                            $is_sql_alwayson = (isset($clientInfo['is_sql_alwayson']) && $clientInfo['is_sql_alwayson'] !== false) ? true : false;

                            if ( $clientInfo['os_type_id'] !== Constants::OS_GENERIC and $clientName !== Constants::CLIENT_NAME_VCENTER_RRC )
                            {
                                $clientNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                    Constants::INVENTORY_TYPE_FAMILY_CLIENT,
                                    $this->buildInventoryNodeID($systemID, $clientID, Constants::APPLICATION_ID_FILE_LEVEL, $clientInfo['file_level_instance_id']),
                                    $clientInfo['name'],
                                    $this->getInventoryIDForAClientFromOSFamily($clientInfo['os_family']), false,
                                    $clientInfo['os_type_id'], $is_sql_cluster, $is_sql_alwayson);
                                if ( $isBlockSupported === true and isset($clientInfo['blk_support']) )
                                {
                                    switch($clientInfo['blk_support'])
                                    {
                                        case Constants::BLK_SUPPORT_BLOCK_NOT_SUPPORTED: // 0 = Block backups not supported
                                            $clientNodeArray['supports_image_level'] = false;

                                            if ( !isset($clientInfo['os_family']) or !isset($clientInfo['os_type']) )
                                            {
                                                $clientNodeArray['does_not_support_image_level_tooltip'] = 'This client does not currently support image level backups.  Please change the backup method to file level to backup this client.';
                                            }
                                            elseif ( $clientInfo['os_family'] == Constants::OS_FAMILY_WINDOWS )
                                            {
                                                switch ( $clientInfo['os_type_id'] )
                                                {
                                                    case Constants::OS_WIN_16:
                                                    case Constants::OS_WINDOWS_95:
                                                    case Constants::OS_WINDOWS_2000:
                                                    case Constants::OS_WINDOWS_VISTA:
                                                    case Constants::OS_WINDOWS_XP:
                                                    case Constants::OS_WINDOWS_2008:
                                                        $clientNodeArray['does_not_support_image_level_tooltip'] = $clientInfo['os_type'].' clients do not currently support image level backups.  Please change the backup method to file level to backup this client.';
                                                        break;
                                                    default:
                                                        if ( isset($clientInfo['version']) and version_compare($clientInfo['version'], Inventory::BLOCK_AGENT_RELEASE_VERSION) < 0 )
                                                        {
                                                            $clientNodeArray['does_not_support_image_level_tooltip'] = 'This client is running agent version '.$clientInfo['version'].', which does not currently support image level backups.  To backup this client, please update the agent or change the backup method to file level.';
                                                        }
                                                        else
                                                        {
                                                            $clientNodeArray['does_not_support_image_level_tooltip'] = 'This client does not currently support image level backups.  Please change the backup method to file level to backup this client.';
                                                        }
                                                        break;
                                                }
                                            }
                                            else
                                            {
                                                $clientNodeArray['does_not_support_image_level_tooltip'] = $clientInfo['os_family'].' clients do not currently support image level backups.  Please change the backup method to file level to backup this client.';
                                            }
                                            break;
                                        case Constants::BLK_SUPPORT_DRIVER_VERSION_IS_OLD: // 1 = Driver version is old.
                                            $clientNodeArray['supports_image_level'] = true;
                                            $clientNodeArray['image_level_driver_tooltip'] = 'This client is running an old image level driver and will not take image level incremental backups until the driver is updated.';
                                            break;
                                        case Constants::BLK_SUPPORT_CBT_DRIVER_NOT_INSTALLED: // 2 = CBT driver not installed
                                            $clientNodeArray['supports_image_level'] = true;
                                            $clientNodeArray['image_level_driver_tooltip'] = 'This client is missing the image level driver and will not take image level incremental backups until the driver is installed.';
                                            break;
                                        case Constants::BLK_SUPPORT_DRIVER_IS_INSTALLED_BUT_REBOOT_IS_REQUIRED: // 3 = Driver installed but reboot required
                                            $clientNodeArray['supports_image_level'] = true;
                                            $clientNodeArray['image_level_driver_tooltip'] = 'This client needs to be rebooted in order for image level incremental backups to run.';
                                            break;
                                        case Constants::BLK_SUPPORT_CBT_DRIVER_INSTALLED_AND_REBOOTED: // 4 = CBT installed and rebooted
                                            $clientNodeArray['supports_image_level'] = true;
                                            break;
                                    }
                                }
                                else
                                {
                                    $clientNodeArray['supports_image_level'] = false;
                                    $clientNodeArray['does_not_support_image_level_tooltip'] = 'This appliance, '.$systemName.', does not currently support image level backups.  Please change the backup method to file level to backup this client.';

                                }
                            }

                            foreach ($clientInfo['applications'] as $applicationID => $applicationArray)
                            {
                                if ($applicationArray['visible'] === false) {
                                    continue;
                                }
                                switch ( $applicationArray['type'] )
                                {
                                    case Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL:
                                        $clientNodeArray['nodes'][] = $this->buildBlockInventoryTree($clientID, $clientName, $applicationID, $clientInfo['os_type_id'],
                                            $nav, $navGroupsArray, $showNavGroups,
                                            $showCopiedAssetsView, $systemID);
                                        break;
                                    case Constants::APPLICATION_TYPE_NAME_ARCHIVE:
                                    case Constants::APPLICATION_TYPE_NAME_SYSTEM_METADATA:
                                        break;  // Don't do anything in this inventory for any of these types

                                    case Constants::APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE:
                                        // If Cisco UCS ever has multiple nodes, add back the client node
                                        /*$ciscoUCSNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_CISCO_UCS,
                                            $this->buildInventoryNodeID($systemID, $clientID, $applicationID),
                                            $clientInfo['name'],
                                            Constants::INVENTORY_ID_CISCO_UCS_CLIENT);*/

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $ciscoUCSServiceProfileInfo = $this->BP->get_ucssp_info($clientID, $applicationID, true, $systemID);
                                        }
                                        else
                                        {
                                            $ciscoUCSServiceProfileInfo = $this->BP->get_grandclient_ucssp_info ($clientID, $applicationID);
                                        }
                                        // Sort the Profiles alphabetically
                                        // If ciscoUCS ever has multiple nodes, add back the sort
                                        // $ciscoUCSServiceProfileInfo = $this->sort($ciscoUCSServiceProfileInfo);

                                        foreach ($ciscoUCSServiceProfileInfo as $serviceProfileInstance)
                                        {
                                            // If ciscoUCS ever has multiple nodes, add back the client node
                                            //$ciscoUCSNodeArray['nodes'][] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            $tempNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                Constants::INVENTORY_TYPE_FAMILY_CISCO_UCS,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $serviceProfileInstance['instance_id']),
                                                $clientInfo['name'] . "'s " . $serviceProfileInstance['name'],
                                                Constants::INVENTORY_ID_CISCO_UCS_SERVICE_PROFILE);
                                            $inGroup = $showNavGroups && $this->groupAsset($nav, $tempNode, $navGroupsArray);
                                            if (!$inGroup) {
                                                $systemNodeArray['nodes'][] = $tempNode;
                                            }
                                        }
                                        // If ciscoUCS ever has multiple nodes, add back the client node
                                        // $systemNodeArray['nodes'][] = $ciscoUCSNodeArray;
                                        break;


                                    case Constants::APPLICATION_TYPE_NAME_NDMP_DEVICE:
                                        $NDMPNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_NDMP,
                                            $this->buildInventoryNodeID($systemID, $clientID),
                                            $clientInfo['name'],
                                            Constants::INVENTORY_ID_NDMP_ClIENT);

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $NDMPInfo = $this->BP->get_ndmpvolume_info($clientID, true, $systemID);
                                        }
                                        else
                                        {
                                            $NDMPInfo = $this->BP->get_grandclient_ndmpvolume_info($clientID);
                                        }
                                        // Sort VMs alphabetically
                                        $NDMPInfo = $this->sort($NDMPInfo);

                                        foreach ($NDMPInfo as $NDMPVolume)
                                        {
                                            $NDMPNodeArray['nodes'][] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                Constants::INVENTORY_TYPE_FAMILY_NDMP,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $NDMPVolume['instance_id']),
                                                $NDMPVolume['name'],
                                                Constants::INVENTORY_ID_NDMP_VOLUME);
                                        }
                                        $inGroup = $showNavGroups && $this->groupAsset($nav, $NDMPNodeArray, $navGroupsArray);
                                        if (!$inGroup) {
                                            //$NDMPNodeArray['nodes'][] = $NDMPVolumeNodeArray;
                                            $systemNodeArray['nodes'][] = $NDMPNodeArray;
                                        }
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_EXCHANGE:
                                        $exchangeNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_EXCHANGE,
                                            $this->buildInventoryNodeID($systemID, $clientID, $applicationID),
                                            $clientName . "'s " . $applicationArray['name'],
                                            Constants::INVENTORY_ID_EXCHANGE_APPLICATION);


                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $exchangeInfo = $this->BP->get_exchange_info($clientID, true, $systemID);
                                        }
                                        else
                                        {
                                            $exchangeInfo = $this->BP->get_grandclient_exchange_info ($clientID);
                                        }
                                        // Sort items alphabetically
                                        $exchangeInfo = $this->sort($exchangeInfo);

                                        $app_aware_flag_supports_exchange_backups =  (isset($clientInfo['app_aware_flg']) and $clientInfo['app_aware_flg'] === Constants::APP_AWARE_FLG_NOT_AWARE_OF_APPLICATIONS_VSS_FULL) === false ;

                                        foreach ($exchangeInfo as $exchangeInstance)
                                        {
                                             $exchangeDatabaseNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                Constants::INVENTORY_TYPE_FAMILY_EXCHANGE,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $exchangeInstance['instance_id']),
                                                $exchangeInstance['name'],
                                                Constants::INVENTORY_ID_EXCHANGE_DATABASE);
                                            $exchangeDatabaseNodeArray['app_aware_flag_supports_exchange_backups'] = $app_aware_flag_supports_exchange_backups;
                                            $exchangeDatabaseNodeArray['app_aware_flag_does_not_support_exchange_backups_tooltip'] = "";
                                            if ( $app_aware_flag_supports_exchange_backups === false )
                                            {
                                                $exchangeDatabaseNodeArray['app_aware_flag_does_not_support_exchange_backups_tooltip'] = "The Application Strategy for " . $clientInfo['name'] . " does not allow for this client to have any Exchange jobs enabled.  The Application Strategy can be found by editing this asset in the Protected Assets tab on the Configure page.";
                                            }
                                            $exchangeNodeArray['nodes'][] = $exchangeDatabaseNodeArray;
                                        }
                                        $clientNodeArray['nodes'][] = $exchangeNodeArray;
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_SQL_SERVER:
                                        $sqlServerNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_SQL,
                                            $this->buildInventoryNodeID($systemID, $clientID, $applicationID),
                                            $clientName . "'s " . $applicationArray['name'],
                                            Constants::INVENTORY_ID_SQL_SERVER);

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $sqlInfo = $this->BP->get_sql_info($clientID, $applicationID, true, $systemID);
                                        }
                                        else
                                        {
                                            $sqlInfo = $this->BP->get_grandclient_sql_info($clientID, $applicationID);
                                        }
                                        // Sort items alphabetically
                                        $sqlInfo = $this->sort($sqlInfo, 'database');

                                        $sqlInstanceNodesByInstanceName = array();

                                        foreach ($sqlInfo as $sqlInstance)
                                        {
                                            // 'online' parameter isn't returned from bp_get_grandlcient_sql_info API
                                            $offline = (isset($sqlInstance['online']) && $sqlInstance['online'] == false) ? " (offline)" : null;
                                            // give the SQL instance a unique ID.
                                            $instance_uuid = substr($sqlInstance['instance'], strpos($sqlInstance['instance'], "\\") + 1);
                                            $instance_uuid = str_replace("_", "-underscore-", $instance_uuid);
                                            if ( array_key_exists( $sqlInstance['instance'], $sqlInstanceNodesByInstanceName ) == false )
                                            {
                                                // additional properties for SQL clusters and SQL availability groups
                                                $is_sql_cluster = (isset($sqlInstance['is_sql_cluster']) && $sqlInstance['is_sql_cluster'] !== false) ? true : false;
                                                $is_sql_alwayson = (isset($sqlInstance['is_sql_alwayson']) && $sqlInstance['is_sql_alwayson'] !== false) ? true : false;

                                                $sqlInstanceNodesByInstanceName[ $sqlInstance['instance'] ] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                    Constants::INVENTORY_TYPE_FAMILY_SQL,
                                                    $this->buildInventoryNodeID($systemID, $clientID, $applicationID, NULL, $instance_uuid),
                                                    $sqlInstance['instance'],
                                                    Constants::INVENTORY_ID_SQL_INSTANCE, false, NULL, $is_sql_cluster, $is_sql_alwayson);
                                            }
                                            $tempNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                Constants::INVENTORY_TYPE_FAMILY_SQL,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $sqlInstance['instance_id'], $instance_uuid),
                                                $sqlInstance['database'] . $offline, //Figure out if this should be instance or database
                                                Constants::INVENTORY_ID_SQL_DATABASE);
                                            if ( isset( $sqlInstance['recovery_model'] ) )
                                            {
                                                $tempNode['recovery_model'] = $sqlInstance['recovery_model'];
                                            }
                                            $sqlInstanceNodesByInstanceName[ $sqlInstance['instance'] ]['nodes'][]  = $tempNode;
                                        }
                                        $sqlServerNodeArray['nodes'] = array_values($sqlInstanceNodesByInstanceName);
                                        $clientNodeArray['nodes'][] = $sqlServerNodeArray;
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_HYPER_V:
                                        $hyperVNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_HYPER_V,
                                            $this->buildInventoryNodeID($systemID, $clientID, $applicationID),
                                            $clientName . "'s " . $applicationArray['name'],
                                            Constants::INVENTORY_ID_HYPER_V_SERVER);

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $hyperVInfo = $this->BP->get_hyperv_info($clientID, true, $systemID);
                                        }
                                        else
                                        {
                                            $hyperVInfo = $this->BP->get_grandclient_hyperv_info($clientID);
                                        }
                                        // Sort VMs alphabetically
                                        $hyperVInfo = $this->sort($hyperVInfo);

                                        foreach ($hyperVInfo as $hyperVVM)
                                        {
                                            $tempNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                Constants::INVENTORY_TYPE_FAMILY_HYPER_V,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $hyperVVM['instance_id']),
                                                $hyperVVM['name'],
                                                Constants::INVENTORY_ID_HYPER_V_VM);
                                            if (isset($hyperVVM['is_saved_state'])) {
                                                $tempNode['recovery_model'] = $this->getHVModelString($hyperVVM['is_saved_state'], $hyperVVM['name']);
                                            }
                                            $inGroup = $showNavGroups && $this->groupVM($nav, $tempNode, $navGroupsArray);
                                            if (!$inGroup) {
                                                $hyperVNodeArray['nodes'][] = $tempNode;
                                            }
                                        }
                                        if ($showNavGroups) {
                                            $nav->addChildren($navGroupsArray, $hyperVNodeArray, $nav->makeID($systemID, $clientID, $applicationID));
                                        }
                                        $clientNodeArray['nodes'][] = $hyperVNodeArray;
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_ORACLE:
                                        $oracleNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_ORACLE,
                                            $this->buildInventoryNodeID($systemID, $clientID, $applicationID),
                                            $clientName . "'s " . $applicationArray['name'],
                                            Constants::INVENTORY_ID_ORACLE_APPLICATION);

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $oracleInfo = $this->BP->get_oracle_info($clientID, $applicationID, true, $systemID);
                                        }
                                        else
                                        {
                                            $oracleInfo = $this->BP->get_grandclient_oracle_info($clientID, $applicationID);
                                        }
                                        // Sort dbs alphabetically
                                        $oracleInfo = $this->sort($oracleInfo);

                                        foreach ($oracleInfo as $oracleInstance)
                                        {
                                            $offline = $oracleInstance['online'] == false ? " (offline)" : null;
                                            $oracleNodeArray['nodes'][] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                Constants::INVENTORY_TYPE_FAMILY_ORACLE,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $oracleInstance['instance_id']),
                                                $oracleInstance['name'] . $offline,
                                                Constants::INVENTORY_ID_ORACLE_DATABASE);
                                        }
                                        $clientNodeArray['nodes'][] = $oracleNodeArray;
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_SHAREPOINT:
                                        $sharePointNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_SHAREPOINT,
                                            $this->buildInventoryNodeID($systemID, $clientID, $applicationID),
                                            $clientName . "'s " . $applicationArray['name'],
                                            Constants::INVENTORY_ID_SHAREPOINT_APPLICATION);

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $sharepointInfo = $this->BP->get_sharepoint_info($clientID, $applicationID, true, $systemID);
                                        }
                                        else
                                        {
                                            $sharepointInfo = $this->BP->get_grandclient_sharepoint_info($clientID, $applicationID);
                                        }
                                        // Sort farms alphabetically
                                        $sharepointInfo = $this->sort($sharepointInfo);

                                        foreach ($sharepointInfo as $sharepointInstance)
                                        {
                                            $sharePointNodeArray['nodes'][] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                Constants::INVENTORY_TYPE_FAMILY_SHAREPOINT,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $sharepointInstance['instance_id']),
                                                $sharepointInstance['name'],
                                                Constants::INVENTORY_ID_SHAREPOINT_FARM);
                                        }
                                        $clientNodeArray['nodes'][] = $sharePointNodeArray;
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_XEN:
                                        /*$xenNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_XEN,
                                            $this->buildInventoryNodeID($systemID, $clientID, $applicationID),
                                            $clientName,
                                            Constants::INVENTORY_ID_XEN_POOL_MASTER);*/

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $xenInfo = $this->BP->get_xen_vm_info($clientID, true, $getDisks, $systemID);
                                        }
                                        else
                                        {
                                            $xenInfo = $this->BP->get_grandclient_xen_vm_info($clientID);
                                            $xenReplicatedClient = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                Constants::INVENTORY_TYPE_FAMILY_XEN,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID),
                                                $clientName,
                                                Constants::INVENTORY_ID_XEN_SERVER_IN_A_POOL);
                                        }
                                        // Sort VMs alphabetically
                                        $xenInfo = $this->sort($xenInfo);

                                        $xenServerNodesbyServerUUID = array();

                                        foreach ($xenInfo as $xenVM)
                                        {

                                            if ( $showCopiedAssetsView === false )
                                            {
                                                if ( array_key_exists( $xenVM['server_uuid'], $xenServerNodesbyServerUUID ) == false )
                                                {
                                                    $xenServerNodesbyServerUUID[ $xenVM['server_uuid'] ] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                        Constants::INVENTORY_TYPE_FAMILY_XEN,
                                                        $this->buildInventoryNodeID($systemID, $clientID, $applicationID, NULL, $xenVM['server_uuid']),  //Maybe in the future, give a Xen Host its own Node ID
                                                        $clientName . "'s " . $xenVM['server_name'],
                                                        Constants::INVENTORY_ID_XEN_SERVER_IN_A_POOL);
                                                }
                                                if ( $xenVM['is_template'] === true )
                                                {
                                                    $tempNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                        Constants::INVENTORY_TYPE_FAMILY_XEN,
                                                        $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $xenVM['instance_id'], $xenVM['server_uuid']),
                                                        $xenVM['name'],
                                                        Constants::INVENTORY_ID_XEN_CUSTOM_TEMPLATE);
                                                    $inGroup = $showNavGroups && $this->groupVM($nav, $tempNode, $navGroupsArray);
                                                    if (!$inGroup) {
                                                        $xenServerNodesbyServerUUID[ $xenVM['server_uuid'] ]['nodes'][] = $tempNode;
                                                    }
                                                }
                                                else
                                                {
                                                    $tempNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                        Constants::INVENTORY_TYPE_FAMILY_XEN,
                                                        $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $xenVM['instance_id'], $xenVM['server_uuid']),
                                                        $xenVM['name'],
                                                        Constants::INVENTORY_ID_XEN_VM);
                                                    if ($getDisks === true and array_key_exists('disks', $xenVM) and count($xenVM['disks']) > 0)
                                                    {
                                                        foreach ($xenVM['disks'] as $disk)
                                                        {
                                                            $tempDiskNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                                Constants::INVENTORY_TYPE_FAMILY_XEN,
                                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $xenVM['instance_id'], $xenVM['server_uuid'], NULL, $disk['user_device']),
                                                                $disk['name'],
                                                                Constants::INVENTORY_ID_XEN_VM_DISK);
                                                            $tempDiskNode['is_excluded'] = $disk['is_excluded'];
                                                            $tempNode['nodes'][] = $tempDiskNode;
                                                        }
                                                    }
                                                    $inGroup = $showNavGroups && $this->groupVM($nav, $tempNode, $navGroupsArray);
                                                    if (!$inGroup) {
                                                        $xenServerNodesbyServerUUID[ $xenVM['server_uuid'] ]['nodes'][] = $tempNode;
                                                    }
                                                }
                                            }
                                            else
                                            {
                                                if ( $xenVM['is_template'] === true )
                                                {
                                                    $xenReplicatedClient['nodes'][] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                        Constants::INVENTORY_TYPE_FAMILY_XEN,
                                                        $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $xenVM['instance_id'], $xenVM['server_uuid']),
                                                        $xenVM['name'],
                                                        Constants::INVENTORY_ID_XEN_CUSTOM_TEMPLATE);
                                                }
                                                else
                                                {
                                                    $tempNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                        Constants::INVENTORY_TYPE_FAMILY_XEN,
                                                        $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $xenVM['instance_id'], $xenVM['server_uuid']),
                                                        $xenVM['name'],
                                                        Constants::INVENTORY_ID_XEN_VM);
                                                    if ($getDisks === true and array_key_exists('disks', $xenVM) and count($xenVM['disks']) > 0)
                                                    {
                                                        foreach ($xenVM['disks'] as $disk)
                                                        {
                                                            $tempDiskNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                                Constants::INVENTORY_TYPE_FAMILY_XEN,
                                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $xenVM['instance_id'], $xenVM['server_uuid'], NULL, $disk['user_device']),
                                                                $disk['name'],
                                                                Constants::INVENTORY_ID_XEN_VM_DISK);
                                                            $tempDiskNode['is_excluded'] = $disk['is_excluded'];
                                                            $tempNode['nodes'][] = $tempDiskNode;
                                                        }
                                                    }
                                                    $xenReplicatedClient['nodes'][] = $tempNode;
                                                }
                                            }
                                        }

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            if ( count($xenServerNodesbyServerUUID) === 1 )
                                            {
                                                foreach ( $xenServerNodesbyServerUUID as $xenServerUUID => $tempXenServerNodeArray )
                                                {
                                                    if ($showNavGroups) {
                                                        $nav->addChildren($navGroupsArray, $tempXenServerNodeArray, $nav->convertID($tempXenServerNodeArray['id']));
                                                    }
                                                    $tempXenServerNodeArray['type'] = Constants::INVENTORY_ID_XEN_STANDALONE_SERVER;  // If there are multiple servers, but one server has no VMs on it, then this logic is wrong.  Since it is just for icons, a little inaccuracy is accetable at this time.
                                                    $inGroup = $showNavGroups && $this->groupAsset($nav, $tempXenServerNodeArray, $navGroupsArray);
                                                    if (!$inGroup) {
                                                        $systemNodeArray['nodes'][] = $tempXenServerNodeArray;
                                                    }
                                                }
                                            }
                                            else
                                            {
                                                foreach ( $xenServerNodesbyServerUUID as $xenServerUUID => $tempXenServerNodeArray )
                                                {
                                                    if ($showNavGroups) {
                                                        $nav->addChildren($navGroupsArray, $tempXenServerNodeArray, $nav->convertID($tempXenServerNodeArray['id']));
                                                    }
                                                    $inGroup = $showNavGroups && $this->groupAsset($nav, $tempXenServerNodeArray, $navGroupsArray);
                                                    if (!$inGroup) {
                                                        $systemNodeArray['nodes'][] = $tempXenServerNodeArray;
                                                    }
                                                }
                                            }
                                        }
                                        else
                                        {
                                            $systemNodeArray['nodes'][] = $xenReplicatedClient;
                                        }
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_AHV:
                                        $AHVNodeArray = $this->buildAHVInventoryTree($clientID, $clientName, $applicationID,
                                            $nav, $navGroupsArray, $showNavGroups,
                                            $showCopiedAssetsView, $getDisks, $systemID);
                                        $inGroup = $showNavGroups && $this->groupAHVHost($nav, $AHVNodeArray, $navGroupsArray, $systemID, $clientID, $applicationID);
                                        if (!$inGroup) {
                                            $systemNodeArray['nodes'][] = $AHVNodeArray;
                                        }
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_VMWARE:
                                        if (!isset($applicationArray['servers'])) {
                                            break;
                                        }
                                        foreach ($applicationArray['servers'] as $esxServerUUID => $esxServerName)
                                        {
                                            $esxServer = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, NULL, $esxServerUUID),
                                                $esxServerName,
                                                Constants::INVENTORY_ID_VMWARE_ESX_SERVER);  // This could also be a vCenter, but the UI cannot tell the difference

                                            if ( $showCopiedAssetsView === false )
                                            {
                                                $vmList = $this->BP->get_vm_info($esxServerUUID, NULL, true, $getDisks, $systemID);
                                                $resourcePools = $this->BP->get_resource_pool_info($esxServerUUID, $systemID);
                                                $vApps = $this->BP->get_vApp_info($esxServerUUID, $systemID);
                                            }
                                            else
                                            {
                                                $vmList = $this->BP->get_grandclient_vm_info($esxServerName, $clientID);
                                                $resourcePools = array();
                                                $vApps = array();
                                            }

                                            $resourcePoolAndVAppArray = array();
                                            if (count($resourcePools) > 0)
                                            {
                                                // Sorts Pools alphabetically
                                                $resourcePools = $this->sort($resourcePools);
                                                foreach ($resourcePools as $resourcePool)
                                                {
                                                    $resourcePoolAndVAppArray[$resourcePool['key']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                        Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                        $this->buildInventoryNodeID($systemID, $clientID, $applicationID, NULL, $esxServerUUID, $resourcePool['key']),
                                                        $resourcePool['name'],
                                                        Constants::INVENTORY_ID_VMWARE_RESOURCE_POOL);
                                                    $resourcePoolAndVAppArray[$resourcePool['key']]['parentKey'] = $resourcePool['parentKey'];
                                                    $resourcePoolAndVAppArray[$resourcePool['key']]['parentType'] = $resourcePool['parentType'];
                                                    $resourcePoolAndVAppArray[$resourcePool['key']]['resourcePoolOrVApp'] = array();
                                                }
                                            }
                                            if (count($vApps) > 0)
                                            {
                                                // Sorts vApps alphabetically
                                                $vApps = $this->sort($vApps);
                                                foreach ($vApps as $vApp)
                                                {
                                                    $resourcePoolAndVAppArray[$vApp['key']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                        Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                        $this->buildInventoryNodeID($systemID, $clientID, $applicationID, NULL, $esxServerUUID, $vApp['key']),
                                                        $vApp['name'],
                                                        Constants::INVENTORY_ID_VMWARE_V_APP);
                                                    $resourcePoolAndVAppArray[$vApp['key']]['parentKey'] = $vApp['parentKey'];
                                                    $resourcePoolAndVAppArray[$vApp['key']]['parentType'] = $vApp['parentType'];
                                                    $resourcePoolAndVAppArray[$vApp['key']]['resourcePoolOrVApp'] = array();
                                                }
                                            }
                                            // Sorts VMs alphabetically
                                            $vmList = $this->sort($vmList);
                                            if (count($vmList) > 0)
                                            {
                                                foreach ($vmList as $vm)
                                                {
                                                    if ($vm['template'] === true)
                                                    {
                                                        $vmNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                            Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                            $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $vm['instance_id'], $esxServerUUID),
                                                            $vm['name'],
                                                            Constants::INVENTORY_ID_VMWARE_TEMPLATE);
                                                        if ( isset( $vm['model'] ) )
                                                        {
                                                            $vmNodeArray['recovery_model'] = $vm['model'];
                                                        }
                                                        $inGroup = $showNavGroups && $this->groupVM($nav, $vmNodeArray, $navGroupsArray);
                                                        if (!$inGroup) {
                                                            $esxServer['nodes'][] = $vmNodeArray;
                                                        }
                                                    }
                                                    else
                                                    {
                                                        $tempNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                            Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                            $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $vm['instance_id'], $esxServerUUID),
                                                            $vm['name'],
                                                            Constants::INVENTORY_ID_VMWARE_VM);
                                                        if ( isset( $vm['model'] ) )
                                                        {
                                                            $tempNode['recovery_model'] = $vm['model'];
                                                        }
                                                        if ($getDisks === true and array_key_exists('disks', $vm) and count($vm['disks']) > 0)
                                                        {
                                                            foreach ($vm['disks'] as $disk)
                                                            {
                                                                $tempDiskNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                                    Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                                    $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $vm['instance_id'], $esxServerUUID, NULL, $disk['key']),
                                                                    $disk['name'],
                                                                    Constants::INVENTORY_ID_VMWARE_VM_DISK);
                                                                $tempDiskNode['is_excluded'] = $disk['is_excluded'];
                                                                $tempNode['nodes'][] = $tempDiskNode;
                                                            }
                                                        }
                                                        if ( $showCopiedAssetsView === false )
                                                        {
                                                            switch ($vm['parentType'])
                                                            {
                                                                case 0: //ESXhost
                                                                    $inGroup = $showNavGroups && $this->groupVM($nav, $tempNode, $navGroupsArray);
                                                                    if (!$inGroup) {
                                                                        $esxServer['nodes'][] = $tempNode;
                                                                    }
                                                                    break;
                                                                case 1: //resourcePool
                                                                case 2: //vApp
                                                                    /*$resourcePoolAndVAppArray[$vm['parentKey']]['VMs'][$vm['instance_id']] = $this->buildInventoryNodeArray( Inventory::INVENTORY_GET_TYPE_TREE,
                                                                        Constants::HV_VMWARE,
                                                                        $this->buildInventoryNodeID( $systemID, $VMwareClient['id'], $applicationID, $vm['instance_id'], $esxServerUUID ),
                                                                        $vm['name'],
                                                                        Constants::NODE_TYPE_VM );*/
                                                                    $inGroup = $showNavGroups && $this->groupVM($nav, $tempNode, $navGroupsArray);
                                                                    if (!$inGroup) {
                                                                        $resourcePoolAndVAppArray[$vm['parentKey']]['nodes'][] = $tempNode;
                                                                    }
                                                                    break;
                                                                case (-1): //template
                                                                    // Nothing should ever come here
                                                                    break;
                                                            }
                                                        }
                                                        else  // We do not have information on resource pools in replication view
                                                        {
                                                            $esxServer['nodes'][] = $tempNode;
                                                        }
                                                    }
                                                }

                                                $esxServerResourcePoolOrVApp = array();
                                                if (count($resourcePoolAndVAppArray) > 0)
                                                {
                                                    $nestedResourcePoolOrVAppArray = array();
                                                    foreach ($resourcePoolAndVAppArray as $resourcePoolOrVAppKey => $resourcePoolOrVApp)
                                                    {
                                                        if ($resourcePoolOrVApp['parentType'] === 0) //ESX is the Parent
                                                        {
                                                            unset($resourcePoolOrVApp['parentKey']);
                                                            unset($resourcePoolOrVApp['parentType']);
                                                            $esxServerResourcePoolOrVApp[$resourcePoolOrVAppKey] = $resourcePoolOrVApp;
                                                        }
                                                        else
                                                        {
                                                            unset($resourcePoolOrVApp['parentType']);
                                                            $nestedResourcePoolOrVAppArray[$resourcePoolOrVAppKey] = $resourcePoolOrVApp;
                                                        }
                                                    }
                                                    if (count($nestedResourcePoolOrVAppArray) > 0)
                                                    {
                                                        $returnedArray = $this->recursiveResourcePoolAndVAppAssignment($esxServerResourcePoolOrVApp, $nestedResourcePoolOrVAppArray, $showNavGroups, $nav, $navGroupsArray);
                                                        if ($showNavGroups) {
                                                            $nodeArray = array();
                                                            foreach ($returnedArray['parents'] as $pool => $parents) {
                                                                $inGroup = $this->groupPoolOrVapp($nav, $parents, $navGroupsArray, true);
                                                                if (!$inGroup) {
                                                                    $nodeArray[] = $parents;
                                                                }
                                                            }
                                                            $esxServer['nodes'] = array_merge($nodeArray, $esxServer['nodes']);
                                                        } else {
                                                            $esxServer['nodes'] = array_values(array_merge($returnedArray['parents'], $esxServer['nodes']));
                                                        }
                                                    }
                                                    elseif (count($esxServerResourcePoolOrVApp) > 0)
                                                    {
                                                        $newNodesArray = array();
                                                        foreach ( $esxServerResourcePoolOrVApp as $esxServerNodeArray )
                                                        {
                                                            unset($esxServerNodeArray['resourcePoolOrVApp']);
                                                            if ($showNavGroups) {
                                                                $nav->addChildren($navGroupsArray, $esxServerNodeArray, $nav->convertID($esxServerNodeArray['id']));
                                                            }
                                                            $inGroup = $showNavGroups && $this->groupPoolOrVapp($nav, $esxServerNodeArray, $navGroupsArray);
                                                            if (!$inGroup) {
                                                                $newNodesArray[] = $esxServerNodeArray;
                                                            }
                                                        }
                                                        $esxServer['nodes'] = array_values(array_merge($newNodesArray, $esxServer['nodes']));
                                                    }
                                                }
                                                if ($showNavGroups) {
                                                    $nav->addChildren($navGroupsArray, $esxServer, $nav->makeID($systemID, $clientID, Constants::APPLICATION_ID_VMWARE, $esxServerUUID));
                                                }
                                            }
                                            $inGroup = $showNavGroups && $this->groupESXServer($nav, $esxServer, $navGroupsArray, $systemID, $clientID, $esxServerUUID);
                                            if (!$inGroup) {
                                                $systemNodeArray['nodes'][] = $esxServer;
                                            }
                                        }
                                        break;
                                }
                            }

                            if ( $clientInfo['os_type_id'] !== Constants::OS_GENERIC and $clientName !== Constants::CLIENT_NAME_VCENTER_RRC )
                            {
                                $inGroup = $showNavGroups && $this->addClientToGroup($nav, $clientNodeArray, $navGroupsArray, $systemID, $clientID);
                                if (!$inGroup) {
                                    $systemNodeArray['nodes'][] = $clientNodeArray;
                                }
                            }
                        }
                    }
                }
                if ($showNavGroups) {
                    $nav->addChildren($navGroupsArray, $systemNodeArray, $nav->makeID($systemID));
                }
            }
            $inventoryArray[] = $systemNodeArray;
        }

        return $inventoryArray;
    }

    /*
     * Create and return the node Array for block.
     */
    private function buildBlockInventoryTree($clientID, $clientName, $applicationID, $os_type_id, $nav, $navGroupsArray, $showNavGroups, $showCopiedAssetsView, $systemID) {
        // Get block instance ID.

        if ( $showCopiedAssetsView === false )
        {
            $blockInstance = $this->BP->get_block_info($clientID, $systemID);
        }
        else
        {
            $blockInstance = $this->BP->get_grandclient_block_info($clientID);
        }

        $instanceID = 0;
        if ($blockInstance !== false) {
            $instanceID = $blockInstance['instance_id'];
        }
        $blockNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
            Constants::INVENTORY_TYPE_FAMILY_BLOCK,
            $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $instanceID),
            $clientName, // . ' (' . Constants::APPLICATION_TYPE_DISPLAY_NAME_BLOCK_LEVEL . ')',
            Constants::INVENTORY_ID_BLOCK,
            $showCopiedAssetsView,
            $os_type_id);

        if ($showNavGroups) {
            $nav->addChildren($navGroupsArray, $blockNodeArray, $nav->makeID($systemID, $clientID, $applicationID, $instanceID));
        }
        return $blockNodeArray;
    }

    /*
     * Create and return the node Array for the AHV client.
     */
    private function buildAHVInventoryTree($clientID, $clientName, $applicationID, $nav, $navGroupsArray, $showNavGroups, $showCopiedAssetsView, $getDisks, $systemID) {

        $AHVnodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
            Constants::INVENTORY_TYPE_FAMILY_AHV,
            $this->buildInventoryNodeID($systemID, $clientID, $applicationID),
            $clientName,
            Constants::INVENTORY_ID_AHV_CLUSTER);

        if ( $showCopiedAssetsView === false )
        {
            $ahvInfo = $this->BP->get_ahv_vm_info($clientID, true, $getDisks, $systemID);
        }
        else
        {
            $ahvInfo = $this->BP->get_grandclient_ahv_vm_info($clientID);
        }
        // Sort VMs alphabetically
        $ahvInfo = $this->sort($ahvInfo);

        foreach ($ahvInfo as $vm)
        {
            $tempNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                Constants::INVENTORY_TYPE_FAMILY_AHV,
                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $vm['instance_id']),
                $vm['name'],
                Constants::INVENTORY_ID_AHV_VM);
            if ($getDisks === true and array_key_exists('disks', $vm) and count($vm['disks']) > 0)
            {
                foreach ($vm['disks'] as $disk)
                {
                    $tempDiskNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                        Constants::INVENTORY_TYPE_FAMILY_AHV,
                        $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $vm['instance_id'], NULL, NULL, $disk['disk_uuid']),
                        $disk['name'],
                        Constants::INVENTORY_ID_AHV_VM_DISK);
                    $tempDiskNode['is_excluded'] = $disk['is_excluded'];
                    $tempNode['nodes'][] = $tempDiskNode;
                }
            }
            $inGroup = $showNavGroups && $this->groupVM($nav, $tempNode, $navGroupsArray);
            if (!$inGroup) {
                $AHVnodeArray['nodes'][] = $tempNode;
            }
        }
        if ($showNavGroups) {
            $nav->addChildren($navGroupsArray, $AHVnodeArray, $nav->makeID($systemID, $clientID, $applicationID));
        }
        return $AHVnodeArray;

    }

    private function getInventoryNodeDisks( $nodeID )
    {
        $result = false;

        $nodeArray = $this->getNodeIDArrayFromInventoryNodeID($nodeID);
        if ( array_key_exists('systemID', $nodeArray) )
        {
            if ( array_key_exists('clientID', $nodeArray) )
            {
                if ( array_key_exists('applicationID', $nodeArray) )
                {
                    if ( $nodeArray['applicationID'] == Constants::APPLICATION_ID_VMWARE )
                    {
                        if ( array_key_exists('esxServerUUID', $nodeArray) )
                        {
                            if ( array_key_exists('instanceID', $nodeArray) )
                            {
                                $vmList = $this->BP->get_vm_info($nodeArray['esxServerUUID'], $nodeArray['instanceID'], true, true, $nodeArray['systemID']);
                                if ($vmList !== false)
                                {
                                    foreach ($vmList as $vm)
                                    {
                                        $result = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                            $this->buildInventoryNodeID($nodeArray['systemID'], $nodeArray['clientID'], $nodeArray['applicationID'], $vm['instance_id'], $nodeArray['esxServerUUID']),
                                            $vm['name'],
                                            Constants::INVENTORY_ID_VMWARE_VM);
                                        foreach ($vm['disks'] as $disk)
                                        {
                                            $tempDiskNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                $this->buildInventoryNodeID($nodeArray['systemID'], $nodeArray['clientID'], $nodeArray['applicationID'], $vm['instance_id'], $nodeArray['esxServerUUID'], NULL, $disk['key']),
                                                $disk['name'],
                                                Constants::INVENTORY_ID_VMWARE_VM_DISK);
                                            $tempDiskNode['is_excluded'] = $disk['is_excluded'];
                                            $result['nodes'][] = $tempDiskNode;
                                        }
                                    }
                                }
                            }
                            else
                            {
                                $result = array();
                                $result['error'] = 500;
                                $result['message']= "The inventory id must specify an instance id in order to get the vm disks.";
                                return $result;
                            }
                        }
                        else
                        {
                            $result = array();
                            $result['error'] = 500;
                            $result['message']= "The inventory id must specify a esx server id in order to get the vm disks.";
                            return $result;
                        }
                    }
                    elseif ( $nodeArray['applicationID'] == Constants::APPLICATION_ID_XEN )
                    {
                        if ( array_key_exists('instanceID', $nodeArray) )
                        {
                            $xenVMList = $this->BP->get_xen_vm_info($nodeArray['clientID'], true, true, $nodeArray['systemID']);
                            if ($xenVMList !== false)
                            {
                                foreach ($xenVMList as $xenVM)
                                {
                                    if ($xenVM['instance_id'] != $nodeArray['instanceID']) {
                                        // Skip instances that do not match the node.
                                        continue;
                                    }
                                    $result = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                        Constants::INVENTORY_TYPE_FAMILY_XEN,
                                        $this->buildInventoryNodeID($nodeArray['systemID'], $nodeArray['clientID'], $nodeArray['applicationID'], $xenVM['instance_id'], $xenVM['server_uuid']),
                                        $xenVM['name'],
                                        Constants::INVENTORY_ID_XEN_VM);
                                    foreach ($xenVM['disks'] as $disk)
                                    {
                                        $tempDiskNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_XEN,
                                            $this->buildInventoryNodeID($nodeArray['systemID'], $nodeArray['clientID'], $nodeArray['applicationID'], $xenVM['instance_id'], $xenVM['server_uuid'], NULL, $disk['user_device']),
                                            $disk['name'],
                                            Constants::INVENTORY_ID_XEN_VM_DISK);
                                        $tempDiskNode['is_excluded'] = $disk['is_excluded'];
                                        $result['nodes'][] = $tempDiskNode;
                                    }
                                }
                            }
                        }
                        else
                        {
                            $result = array();
                            $result['error'] = 500;
                            $result['message']= "The inventory id must specify an instance id in order to get the vm disks.";
                            return $result;
                        }
                    }
                    elseif ( $nodeArray['applicationID'] == Constants::APPLICATION_ID_AHV ) {
                        if (array_key_exists('instanceID', $nodeArray)) {
                            $vmList = $this->BP->get_ahv_vm_info($nodeArray['clientID'], true, true, $nodeArray['systemID']);
                            if ($vmList !== false) {
                                foreach ($vmList as $vm) {
                                    if ($vm['instance_id'] != $nodeArray['instanceID']) {
                                        // Skip instances that do not match the node.
                                        continue;
                                    }
                                    $result = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                        Constants::INVENTORY_TYPE_FAMILY_AHV,
                                        $this->buildInventoryNodeID($nodeArray['systemID'], $nodeArray['clientID'], $nodeArray['applicationID'],
                                            $vm['instance_id'], NULL), $vm['name'],
                                        Constants::INVENTORY_ID_AHV_VM);
                                    foreach ($vm['disks'] as $disk) {
                                        $tempDiskNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_AHV,
                                            $this->buildInventoryNodeID($nodeArray['systemID'], $nodeArray['clientID'], $nodeArray['applicationID'],
                                                $vm['instance_id'], NULL, NULL, $disk['disk_uuid']), $disk['name'],
                                            Constants::INVENTORY_ID_AHV_VM_DISK);
                                        $tempDiskNode['is_excluded'] = $disk['is_excluded'];
                                        $result['nodes'][] = $tempDiskNode;
                                    }
                                }
                            }
                        }
                    }
                }
                else
                {
                    $result = array();
                    $result['error'] = 500;
                    $result['message']= "The inventory id must specify an application id in order to get the vm disks.";
                    return $result;
                }
            }
            else
            {
                $result = array();
                $result['error'] = 500;
                $result['message']= "The inventory id must specify a client id in order to get the vm disks.";
                return $result;
            }
        }
        else
        {
            $result = array();
            $result['error'] = 500;
            $result['message']= "The inventory id must specify a system id in order to get the vm disks.";
            return $result;
        }
        return $result;
    }

    private function getRdrInventoryTree( $data, $sid )
    {
        $inventoryArray = array();

        $systems = $this->functions->selectSystems( $sid, false );
        $localSystemID = $this->BP->get_local_system_id();

        foreach ( $systems as $systemID => $systemName )
        {
            $systemNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                Constants::INVENTORY_TYPE_FAMILY_SYSTEM,
                $systemID,
                $systemName,
                Constants::INVENTORY_ID_SYSTEM);
            $systemNodeArray['rdr_supported'] = $this->BP->rdr_supported($systemID);
            if($systemNodeArray['rdr_supported'] === true){
                $protectedVMs = array();
                $BackupVMs = $BackupCopyVMs = $WIRVMs = array();
                $tempVMs = array();
                $tempVMs = $this->BP->get_protected_hyperv_vms(false, $systemID);
                $tempVMs = array_merge($tempVMs, $this->BP->get_protected_vmware_vms(false, $systemID));
                $blockVMs = $this->BP->get_protected_block_assets(false, $systemID);
                if ($blockVMs !== false) {
                    // Add vm_name to the returned elements.
                    array_walk($blockVMs, function(&$value) { $value['vm_name'] = $value['name']; });
                    $tempVMs = array_merge($tempVMs, $blockVMs);
                }
                foreach ( $tempVMs as $protectedVM )
                {
                    $protectedVMs[ $protectedVM['instance_id']] = array("protected" => true, "guest_os" => $protectedVM['guest_os']);
                }
                $strBids = implode(',', array_keys($protectedVMs));
                $instances = $this->BP->get_appinst_info($strBids, $systemID);
                $vcenters = $this->BP->get_vcenter_list($systemID);
                $BackupVMs = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                    Constants::INVENTORY_TYPE_FAMILY_NAVGROUP,
                    $systemID.'_backups',
                    'Backups',
                    Constants::INVENTORY_ID_NAVGROUP);
                if ( $instances !== false )
                {
                    $i = 0;
                    foreach ($instances as $instanceID => $instanceInfo)
                    {
                        switch ( $instanceInfo['app_type'] )
                        {
                            case Constants::APPLICATION_TYPE_NAME_HYPER_V:
                                $BackupVMs['nodes'][$i] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                    Constants::INVENTORY_TYPE_FAMILY_HYPER_V,
                                    $this->buildInventoryNodeID($systemID, $instanceInfo['client_id'], $instanceInfo['app_id'], $instanceID),
                                    $instanceInfo['primary_name'],
                                    Constants::INVENTORY_ID_HYPER_V_VM);
                                $i++;
                                break;

                            case Constants::APPLICATION_TYPE_NAME_VMWARE:
                                foreach($vcenters as $vcenterID => $vcenterName){
                                    if($vcenterName === $instanceInfo['primary_name']){
                                        $esxUUID = $vcenterID;
                                        continue;
                                    }
                                }
                                $BackupVMs['nodes'][$i] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                    Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                    $this->buildInventoryNodeID($systemID, $instanceInfo['client_id'], $instanceInfo['app_id'], $instanceID, $esxUUID),
                                    $instanceInfo['secondary_name'],
                                    Constants::INVENTORY_ID_VMWARE_VM,
                                    false,
                                    null);
                                $BackupVMs['nodes'][$i]['guest_os'] = $protectedVMs[$instanceID]['guest_os'];
                                $i++;
                                break;

                            case Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL:
                                $BackupVMs['nodes'][$i] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                    Constants::INVENTORY_TYPE_FAMILY_BLOCK,
                                    $this->buildInventoryNodeID($systemID, $instanceInfo['client_id'], $instanceInfo['app_id'], $instanceID),
                                    $instanceInfo['primary_name'],
                                    Constants::INVENTORY_ID_BLOCK);
                                $BackupVMs['nodes'][$i]['guest_os'] = $protectedVMs[$instanceID]['guest_os'];
                                $i++;
                                break;
                        }
                    }
                }
                $systemNodeArray['nodes'][] = $BackupVMs;
                if($systemID === $localSystemID){
                    $BackupCopyVMs = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                        Constants::INVENTORY_TYPE_FAMILY_NAVGROUP,
                        $systemID.'_backupcopies',
                        'Backup Copies',
                        Constants::INVENTORY_ID_NAVGROUP);

                    $replicatingSystems = $this->functions->selectReplicatingSystems();     //passing systemID does not give any result

                    foreach($replicatingSystems as $replicatingSystemID => $replicatedSystemName){
                        $replicatedSystemNodeArray = array();
                        $replicatedSystemNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                            Constants::INVENTORY_TYPE_FAMILY_SYSTEM,
                            $systemID.'_backupcopies_'.$replicatingSystemID,
                            $replicatedSystemName,
                            Constants::INVENTORY_ID_SYSTEM);

                        $protectedVMs = array();
                        $tempVMs = array();
                        $tempVMs = $this->BP->get_protected_hyperv_vms(true, $replicatingSystemID);
                        $tempVMs = array_merge($tempVMs, $this->BP->get_protected_vmware_vms(true, $replicatingSystemID));
                        $blockVMs = $this->BP->get_protected_block_assets(true, $replicatingSystemID);
                        if ($blockVMs !== false) {
                            // Add vm_name to the returned elements.
                            array_walk($blockVMs, function(&$value) { $value['vm_name'] = $value['name']; });
                            $tempVMs = array_merge($tempVMs, $blockVMs);
                        }
                        foreach ( $tempVMs as $protectedVM )
                        {
                            $protectedVMs[ $protectedVM['instance_id']] = array("protected" => true, "guest_os" => $protectedVM['guest_os']);
                        }
                        $strBids = implode(',', array_keys($protectedVMs));
                        $instances = $this->BP->get_appinst_info($strBids);

                        if ( $instances !== false )
                        {
                            $i = 0;
                            foreach ($instances as $instanceID => $instanceInfo)
                            {
                                switch ( $instanceInfo['app_type'] )
                                {
                                    case Constants::APPLICATION_TYPE_NAME_HYPER_V:
                                        // Add a placeholder UUID so that this node id does not clash with a node id of a backup with the same system id.
                                        $replicatedSystemNodeArray['nodes'][$i] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_HYPER_V,
                                            $this->buildInventoryNodeID($replicatingSystemID, $instanceInfo['client_id'], $instanceInfo['app_id'], $instanceID, self::PLACEHOLDER_COPIES_UUID),
                                            $instanceInfo['primary_name'],
                                            Constants::INVENTORY_ID_HYPER_V_VM);
                                        $i++;
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_VMWARE:
                                        foreach($vcenters as $vcenterID => $vcenterName){
                                            if($vcenterName === $instanceInfo['primary_name']){
                                                $esxUUID = $vcenterID;
                                                continue;
                                            }
                                        }
                                        $replicatedSystemNodeArray['nodes'][$i] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                            $this->buildInventoryNodeID($replicatingSystemID, $instanceInfo['client_id'], $instanceInfo['app_id'], $instanceID, $esxUUID),
                                            $instanceInfo['secondary_name'],
                                            Constants::INVENTORY_ID_VMWARE_VM,
                                            false,
                                            null);
                                        $replicatedSystemNodeArray['nodes'][$i]['guest_os'] = $protectedVMs[$instanceID]['guest_os'];
                                        $i++;
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL:
                                        // Add a placeholder UUID so that this node id does not clash with a node id of a backup with the same system id.
                                        $replicatedSystemNodeArray['nodes'][$i] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_BLOCK,
                                            $this->buildInventoryNodeID($replicatingSystemID, $instanceInfo['client_id'], $instanceInfo['app_id'], $instanceID, self::PLACEHOLDER_COPIES_UUID),
                                            $instanceInfo['primary_name'],
                                            Constants::INVENTORY_ID_BLOCK);
                                        $replicatedSystemNodeArray['nodes'][$i]['guest_os'] = $protectedVMs[$instanceID]['guest_os'];
                                        $i++;
                                        break;
                                }
                            }
                        }
                        $BackupCopyVMs['nodes'][] = $replicatedSystemNodeArray;
                    }
                    $systemNodeArray['nodes'][] = $BackupCopyVMs;
                }

                $WIRVMs = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                    Constants::INVENTORY_TYPE_FAMILY_NAVGROUP,
                    $systemID.'_wir',
                    'Windows Replicas',
                    Constants::INVENTORY_ID_NAVGROUP);

                $virtualClientsAreSupported = $this->BP->virtual_clients_supported($systemID);
                if ($virtualClientsAreSupported !== -1 and $virtualClientsAreSupported !== false)
                {
                    $virtualClientsList = $this->BP->get_virtual_client_list($systemID);
                    if ( $virtualClientsList !== false )
                    {
                        $i = 0;
                        $hyperVNodeArray = $esxServerWIR = array();
                        foreach ( $virtualClientsList as $virtualClient )
                        {
                            if($this ->functions->isWIRAllowed($virtualClient['current_state'])){
                                $virtualClientDetail = $this->BP->get_virtual_client($virtualClient['virtual_id'], $systemID);
                                if ( $virtualClientDetail !== false )
                                {
                                    switch ( $virtualClientDetail['hypervisor_type'] )
                                    {
                                        case Constants::WIR_SUPPORTED_HYPERVISOR_UNITRENDS_APPLIANCE:
                                            break;
                                        case Constants::WIR_SUPPORTED_HYPERVISOR_HYPER_V_HOST:
                                            $WIRVMs['nodes'][$i] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                Constants::INVENTORY_TYPE_FAMILY_HYPER_V,
                                                $this->buildInventoryNodeID($systemID, Constants::WIR_UNKNOWN_CLIENT_ID, Constants::APPLICATION_ID_HYPER_V_2008_R2, $virtualClient['virtual_id']),  //Constants::APPLICATION_ID_HYPER_V_2008_R2 is put here because it is first.  We do not actually know what type of Hyper-V it is
                                                $virtualClientDetail['vm_name'],
                                                Constants::INVENTORY_ID_HYPER_V_WIR_VM,
                                                false,
                                                null);
                                            $WIRVMs['nodes'][$i]['guest_os'] = 'windows';
                                            $i++;
                                            break;
                                        case Constants::WIR_SUPPORTED_HYPERVISOR_VMWARE_HOST:
                                            $WIRVMs['nodes'][$i] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                $this->buildInventoryNodeID($systemID, Constants::WIR_UNKNOWN_CLIENT_ID, Constants::APPLICATION_ID_VMWARE, $virtualClient['virtual_id']),
                                                $virtualClientDetail['vm_name'],
                                                Constants::INVENTORY_ID_VMWARE_WIR_VM,
                                                false,
                                                null);
                                            $WIRVMs['nodes'][$i]['guest_os'] = 'windows';
                                            $i++;
                                            break;
                                    }
                                }
                            }
                        }
                    }
                    $systemNodeArray['nodes'][] = $WIRVMs;
                }

                $ReplicaVMs = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                    Constants::INVENTORY_TYPE_FAMILY_NAVGROUP,
                    $systemID.'_replica',
                    'VM Replicas',
                    Constants::INVENTORY_ID_NAVGROUP);

                $replicasSupported = $this->BP->replica_vms_supported($systemID);
                if ($replicasSupported !== -1 && $replicasSupported !== false) {
                    if (array_key_exists('hypervisor_type', $data)) {
                        $hypervisorType = $data['hypervisor_type'];
                    } else {
                        $hypervisorType = NULL;
                    }
                    $replicaVMList = $this->BP->get_replica_vm_list($hypervisorType, $systemID);

                    if ( $replicaVMList !== false )
                    {
                        $i = 0;
                        foreach ( $replicaVMList as $replicaVM )
                        {
                            if($this ->functions->isWIRAllowed($replicaVM['current_state'])){
                                switch ( $replicaVM['hypervisor_type'] )
                                {
                                    case Constants::WIR_SUPPORTED_HYPERVISOR_UNITRENDS_APPLIANCE:
                                        break;
                                    case Constants::WIR_SUPPORTED_HYPERVISOR_HYPER_V_HOST:
                                        $ReplicaVMs['nodes'][$i] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_HYPER_V,
                                            $this->buildInventoryNodeID($systemID, Constants::REPLICAS_CLIENT_ID, Constants::APPLICATION_ID_HYPER_V_2008_R2, $replicaVM['instance_id']),  //Constants::APPLICATION_ID_HYPER_V_2008_R2 is put here because it is first.  We do not actually know what type of Hyper-V it is
                                            $replicaVM['replica_name'],
                                            Constants::INVENTORY_ID_HYPER_V_WIR_VM,
                                            false,
                                            null);
                                        $i++;
                                        break;
                                    case Constants::WIR_SUPPORTED_HYPERVISOR_VMWARE_HOST:
                                        if ($replicaVM['valid'] == false){
                                            continue;
                                        }
                                        $ReplicaVMs['nodes'][$i] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                            Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                            $this->buildInventoryNodeID($systemID, Constants::REPLICAS_CLIENT_ID, Constants::APPLICATION_ID_VMWARE, $replicaVM['instance_id']),
                                            $replicaVM['replica_name'],
                                            Constants::INVENTORY_ID_VMWARE_WIR_VM,
                                            false,
                                            null);
                                        $i++;
                                        break;
                                }
                            }
                        }
                    }
                    $systemNodeArray['nodes'][] = $ReplicaVMs;
                }
            }
            $inventoryArray[] = $systemNodeArray;
        }
        return $inventoryArray;
    }

    private function getReplicasInventoryTree( $data, $sid ) {
        $showSystemClient = false;
        if ($data !== NULL and array_key_exists('showSystemClient', $data))
        {
            $showSystemClient = ($data['showSystemClient'] === '1');
        }

        $getDisks = false;  //whether or not to get info about VM disks for VMware
        if ($data !== NULL and array_key_exists('disks', $data))
        {
            $getDisks = ((int)$data['disks'] === 1);
        }

        $inventoryArray = array();
        //loop through x2 - once to get grandclients, once to get local clients
        $n=0;
        do {
            if ($n === 0){
                $showCopiedAssetsView = false;

            } else {
                $showCopiedAssetsView = true;
            }

            $localSystemID = $this->BP->get_local_system_id();

            if ($showCopiedAssetsView === false) {
                $systems = $this->functions->selectSystems($sid, false);
            } else {
                $systems = $this->functions->selectReplicatingSystems($sid);
                foreach ($systems as $id => &$sname) {
                    $sname = $sname . " (hot copy)";
                }
            }

            if (isset($data['uid'])) {
                require_once('navgroups.php');
                $nav = new NavigationGroups($this->BP, $data['uid']);
                $showNavGroups = $nav->showGroups();
            } else if (isset($data['showNavGroups']) && $data['showNavGroups'] == 1) {
                require_once('navgroups.php');
                $nav = new NavigationGroups($this->BP);
                $showNavGroups = true;
            } else {
                $showNavGroups = false;
                $nav = NULL;
                $navGroups = array();
            }

            $navGroupsArray = array();
            if ($showNavGroups) {
                $navGroups = $nav->getAllGroups();
                // sort groups by name.
                $navGroups = $this->sort($navGroups, 'usort', array('Inventory', 'compareGroupNames'));
                foreach ($navGroups as $group) {
                    $navGroupArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                        Constants::INVENTORY_TYPE_FAMILY_NAVGROUP,
                        $group['@attributes']['id'],
                        $group['@attributes']['name'],
                        Constants::INVENTORY_ID_NAVGROUP);
                    $navGroupArray['treeParentID'] = $group['@attributes']['treeParentID'];
                    $navGroupArray['group'] = $group;
                    $navGroupsArray[] = $navGroupArray;
                }
                $nav->linkGroups($navGroupsArray);
            }

            foreach ($systems as $systemID => $systemName) {
                if ($showCopiedAssetsView === false) {
                    $clients = $this->BP->get_client_list($systemID);

                } else {
                    $clients = $this->BP->get_grandclient_list($systemID);
                }

                if ($showCopiedAssetsView === true) {
                    $systemID = $systemID . "0001";  //adding for targets that have both managed and replicated sources of the same id, else jstree gets confused.
                }

                $systemNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                    Constants::INVENTORY_TYPE_FAMILY_SYSTEM,
                    $systemID,
                    $systemName,
                    Constants::INVENTORY_ID_SYSTEM);


                if ($clients !== false) {
                    // sort clients by name.
                    $clients = $this->sort($clients, null);
                    foreach ($clients as $clientID => $clientName) {
                        if ($showSystemClient === true or $clientName !== $systemName) {
                            if ($showCopiedAssetsView === false) {
                                $clientInfo = $this->BP->get_client_info($clientID, $systemID);
                            } else {
                                $clientInfo = $this->BP->get_client_info($clientID, $localSystemID);
                            }
                            if ($clientInfo !== false) {
                                foreach ($clientInfo['applications'] as $applicationID => $applicationArray) {
                                    $replicaFilterStrings = "";
                                    $replicatedReplicaFilterString = "";

                                    switch ($applicationArray['type']) {

                                        case Constants::APPLICATION_TYPE_NAME_VMWARE:
                                            $filters = $this->getReplicaCandidates($systems, $systemID, false);
                                            $replicatedFilters = $this->getReplicaCandidates($systems, $systemID, true);

                                            if ($filters !== false && isset($filters['candidates'])) {
                                                $replicaFilterStrings = $this->getReplicaFilterStrings($filters['candidates'], false);
                                            }
                                            if($replicatedFilters !== false && isset($replicatedFilters['candidates'])){
                                                $replicatedReplicaFilterString = $this->getReplicaFilterStrings($replicatedFilters['candidates'], true);
                                            }

                                            if (!isset($applicationArray['servers'])) {
                                                break;
                                            }
                                            foreach ($applicationArray['servers'] as $esxServerUUID => $esxServerName) {
                                                $esxServer = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                    Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                    $this->buildInventoryNodeID($systemID, $clientID, $applicationID, NULL, $esxServerUUID),

                                                    $esxServerName,
                                                    Constants::INVENTORY_ID_VMWARE_ESX_SERVER);  // This could also be a vCenter, but the UI cannot tell the difference

                                                if ($showCopiedAssetsView === false) {
                                                    $vmList = $this->BP->get_vm_info($esxServerUUID, $replicaFilterStrings['iid'], true, $getDisks, $systemID);
                                                    $resourcePools = $this->BP->get_resource_pool_info($esxServerUUID, $systemID);
                                                    $vApps = $this->BP->get_vApp_info($esxServerUUID, $systemID);
                                                } else {
                                                    $vmList = $this->BP->get_grandclient_vm_info($esxServerName, $clientID, $replicatedReplicaFilterString['iid']);
                                                    $resourcePools = array();
                                                    $vApps = array();
                                                }

                                                $resourcePoolAndVAppArray = array();
                                                if (count($resourcePools) > 0) {
                                                    // Sorts Pools alphabetically
                                                    $resourcePools = $this->sort($resourcePools);
                                                    foreach ($resourcePools as $resourcePool) {
                                                        $resourcePoolAndVAppArray[$resourcePool['key']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                            Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                            $this->buildInventoryNodeID($systemID, $clientID, $applicationID, NULL, $esxServerUUID, $resourcePool['key']),
                                                            $resourcePool['name'],
                                                            Constants::INVENTORY_ID_VMWARE_RESOURCE_POOL);
                                                        $resourcePoolAndVAppArray[$resourcePool['key']]['parentKey'] = $resourcePool['parentKey'];
                                                        $resourcePoolAndVAppArray[$resourcePool['key']]['parentType'] = $resourcePool['parentType'];
                                                        $resourcePoolAndVAppArray[$resourcePool['key']]['resourcePoolOrVApp'] = array();
                                                    }
                                                }
                                                if (count($vApps) > 0) {
                                                    // Sorts vApps alphabetically
                                                    $vApps = $this->sort($vApps);
                                                    foreach ($vApps as $vApp) {
                                                        $resourcePoolAndVAppArray[$vApp['key']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                            Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                            $this->buildInventoryNodeID($systemID, $clientID, $applicationID, NULL, $esxServerUUID, $vApp['key']),
                                                            $vApp['name'],
                                                            Constants::INVENTORY_ID_VMWARE_V_APP);
                                                        $resourcePoolAndVAppArray[$vApp['key']]['parentKey'] = $vApp['parentKey'];
                                                        $resourcePoolAndVAppArray[$vApp['key']]['parentType'] = $vApp['parentType'];
                                                        $resourcePoolAndVAppArray[$vApp['key']]['resourcePoolOrVApp'] = array();
                                                    }
                                                }
                                                // Sorts VMs alphabetically
                                                $vmList = $this->sort($vmList);
                                                if (count($vmList) > 0) {
                                                    foreach ($vmList as $vm) {
                                                        if ($vm['template'] === true) {
                                                            $vmNodeArray = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                                Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $vm['instance_id'], $esxServerUUID),
                                                                $vm['name'],
                                                                Constants::INVENTORY_ID_VMWARE_TEMPLATE);
                                                            if (isset($vm['model'])) {
                                                                $vmNodeArray['recovery_model'] = $vm['model'];
                                                            }
                                                            $inGroup = $showNavGroups && $this->groupVM($nav, $vmNodeArray, $navGroupsArray);
                                                            if (!$inGroup) {
                                                                $esxServer['nodes'][] = $vmNodeArray;
                                                            }
                                                        } else {
                                                            $tempNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                                Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $vm['instance_id'], $esxServerUUID),
                                                                $vm['name'],
                                                                Constants::INVENTORY_ID_VMWARE_VM);
                                                            if (isset($vm['model'])) {
                                                                $tempNode['recovery_model'] = $vm['model'];
                                                            }
                                                            if ($getDisks === true and array_key_exists('disks', $vm) and count($vm['disks']) > 0) {
                                                                foreach ($vm['disks'] as $disk) {
                                                                    $tempDiskNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_TREE,
                                                                        Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                                        $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $vm['instance_id'], $esxServerUUID, NULL, $disk['key']),
                                                                        $disk['name'],
                                                                        Constants::INVENTORY_ID_VMWARE_VM_DISK);
                                                                    $tempDiskNode['is_excluded'] = $disk['is_excluded'];
                                                                    $tempNode['nodes'][] = $tempDiskNode;
                                                                }
                                                            }
                                                            if ($showCopiedAssetsView === false) {
                                                                switch ($vm['parentType']) {
                                                                    case 0: //ESXhost
                                                                        $inGroup = $showNavGroups && $this->groupVM($nav, $tempNode, $navGroupsArray);
                                                                        if (!$inGroup) {
                                                                            $esxServer['nodes'][] = $tempNode;
                                                                        }
                                                                        break;
                                                                    case 1: //resourcePool
                                                                    case 2: //vApp
                                                                        /*$resourcePoolAndVAppArray[$vm['parentKey']]['VMs'][$vm['instance_id']] = $this->buildInventoryNodeArray( Inventory::INVENTORY_GET_TYPE_TREE,
                                                                            Constants::HV_VMWARE,
                                                                            $this->buildInventoryNodeID( $systemID, $VMwareClient['id'], $applicationID, $vm['instance_id'], $esxServerUUID ),
                                                                            $vm['name'],
                                                                            Constants::NODE_TYPE_VM );*/
                                                                        $inGroup = $showNavGroups && $this->groupVM($nav, $tempNode, $navGroupsArray);
                                                                        if (!$inGroup) {
                                                                            $resourcePoolAndVAppArray[$vm['parentKey']]['nodes'][] = $tempNode;
                                                                        }
                                                                        break;
                                                                    case (-1): //template
                                                                        // Nothing should ever come here
                                                                        break;
                                                                }
                                                            } else  // We do not have information on resource pools in replication view
                                                            {
                                                                $tempNode['gc'] = true;
                                                                $esxServer['nodes'][] = $tempNode;
                                                            }
                                                        }
                                                    }

                                                    $esxServerResourcePoolOrVApp = array();
                                                    if (count($resourcePoolAndVAppArray) > 0) {
                                                        $nestedResourcePoolOrVAppArray = array();
                                                        foreach ($resourcePoolAndVAppArray as $resourcePoolOrVAppKey => $resourcePoolOrVApp) {
                                                            if ($resourcePoolOrVApp['parentType'] === 0) //ESX is the Parent
                                                            {
                                                                unset($resourcePoolOrVApp['parentKey']);
                                                                unset($resourcePoolOrVApp['parentType']);
                                                                $esxServerResourcePoolOrVApp[$resourcePoolOrVAppKey] = $resourcePoolOrVApp;
                                                            } else {
                                                                unset($resourcePoolOrVApp['parentType']);
                                                                $nestedResourcePoolOrVAppArray[$resourcePoolOrVAppKey] = $resourcePoolOrVApp;
                                                            }
                                                        }
                                                        if (count($nestedResourcePoolOrVAppArray) > 0) {
                                                            $returnedArray = $this->recursiveResourcePoolAndVAppAssignment($esxServerResourcePoolOrVApp, $nestedResourcePoolOrVAppArray, $showNavGroups, $nav, $navGroupsArray);
                                                            if ($showNavGroups) {
                                                                $nodeArray = array();
                                                                foreach ($returnedArray['parents'] as $pool => $parents) {
                                                                    $inGroup = $this->groupPoolOrVapp($nav, $parents, $navGroupsArray, true);
                                                                    if (!$inGroup) {
                                                                        $nodeArray[] = $parents;
                                                                    }
                                                                }
                                                                $esxServer['nodes'] = array_merge($nodeArray, $esxServer['nodes']);
                                                            } else {
                                                                $esxServer['nodes'] = array_values(array_merge($returnedArray['parents'], $esxServer['nodes']));
                                                            }
                                                        } elseif (count($esxServerResourcePoolOrVApp) > 0) {
                                                            $newNodesArray = array();
                                                            foreach ($esxServerResourcePoolOrVApp as $esxServerNodeArray) {
                                                                unset($esxServerNodeArray['resourcePoolOrVApp']);
                                                                if ($showNavGroups) {
                                                                    $nav->addChildren($navGroupsArray, $esxServerNodeArray, $nav->convertID($esxServerNodeArray['id']));
                                                                }
                                                                $inGroup = $showNavGroups && $this->groupPoolOrVapp($nav, $esxServerNodeArray, $navGroupsArray);
                                                                if (!$inGroup) {
                                                                    $newNodesArray[] = $esxServerNodeArray;
                                                                }
                                                            }
                                                            $esxServer['nodes'] = array_values(array_merge($newNodesArray, $esxServer['nodes']));
                                                        }
                                                    }
                                                    if ($showNavGroups) {
                                                        $nav->addChildren($navGroupsArray, $esxServer, $nav->makeID($systemID, $clientID, Constants::APPLICATION_ID_VMWARE, $esxServerUUID));
                                                    }
                                                }
                                                $inGroup = $showNavGroups && $this->groupESXServer($nav, $esxServer, $navGroupsArray, $systemID, $clientID, $esxServerUUID);
                                                if (!$inGroup) {
                                                    $systemNodeArray['nodes'][] = $esxServer;
                                                }
                                            }
                                            break;
                                        default:
                                            break;
                                    }
                                }
                            }
                        }
                    }
                    if ($showNavGroups) {
                        $nav->addChildren($navGroupsArray, $systemNodeArray, $nav->makeID($systemID));
                    }
                }

                $inventoryArray[] = $systemNodeArray;
            }
            $n++;
        } while ($n <=1);

        return $inventoryArray;
    }

    public function getInventoryStatus($data, $sid, $fromReports = false)
    {
        $showSystemClient = false;
        if ($data !== NULL and array_key_exists('showSystemClient', $data))
        {
            $showSystemClient = ($data['showSystemClient'] === '1');
        }


        $showCopiedAssetsView = false;
        if ($data !== NULL and array_key_exists('grandclient', $data))
        {
            $showCopiedAssetsView = ($data['grandclient'] === '1');
            $localSystemID = $this->BP->get_local_system_id();
        }

        if ( $showCopiedAssetsView === false )
        {
            $systems = $this->functions->selectSystems( $sid, false );
        }
        else
        {
            $systems = $this->functions->selectReplicatingSystems( $sid );
        }

        $inventoryStatusArray = array();
        // date("w",

        foreach ( $systems as $systemID => $systemName )
        {
            $assetsByInstanceID = array(); //instance_id is the key
            $fileLevelInstanceIDByClientID = array();  //client_id is the key and the file-level instance id is the value

            if ( $showCopiedAssetsView === false )
            {
                $clients = $this->BP->get_client_list($systemID);
            }
            else
            {
                $clients = $this->BP->get_grandclient_list($systemID);
            }
            if ( $clients !== false )
            {
                foreach ($clients as $clientID => $clientName)
                {
                    if ( $showSystemClient === true or $clientName !== $systemName )
                    {
                        if ( $showCopiedAssetsView === false )
                        {
                            $clientInfo = $this->BP->get_client_info($clientID, $systemID);
                        }
                        else
                        {
                            $clientInfo = $this->BP->get_client_info($clientID, $localSystemID);
                        }
                        if ($clientInfo !== false)
                        {
                            // additional properties for SQL clusters and SQL availability groups
                            if (isset($clientInfo['is_sql_cluster']) && $clientInfo['is_sql_cluster'] !== false) {
                                $is_sql_cluster = true;
                            } else {
                                $is_sql_cluster = false;
                            }
                            if (isset($clientInfo['is_sql_alwayson']) && $clientInfo['is_sql_alwayson'] !== false) {
                                $is_sql_alwayson = true;
                            } else {
                                $is_sql_alwayson = false;
                            }

                            if ( $clientInfo['os_type_id'] !== Constants::OS_GENERIC and $clientName !== Constants::CLIENT_NAME_VCENTER_RRC )
                            {
                                $file_level_instance_id = $clientInfo['file_level_instance_id'];
                                $fileLevelInstanceIDByClientID[$clientID] = $file_level_instance_id;

                                $assetsByInstanceID[$file_level_instance_id] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_STATUS,
                                    Constants::INVENTORY_TYPE_FAMILY_CLIENT,
                                    $this->buildInventoryNodeID($systemID, $clientID, Constants::APPLICATION_ID_FILE_LEVEL, $file_level_instance_id),
                                    $clientInfo['name'],
                                    $this->getInventoryIDForAClientFromOSFamily($clientInfo['os_family']),
                                    $showCopiedAssetsView, NULL, $is_sql_cluster, $is_sql_alwayson);
                            }

                            foreach ($clientInfo['applications'] as $applicationID => $applicationArray)
                            {
                                switch ( $applicationArray['type'] )
                                {
                                    case Constants::APPLICATION_TYPE_NAME_FILE_LEVEL:
                                    case Constants::APPLICATION_TYPE_NAME_ARCHIVE:
                                    case Constants::APPLICATION_TYPE_NAME_SYSTEM_METADATA:
                                        break;  // Don't do anything in this inventory for any of these types

                                    case Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL:
                                        $blockInstance = $this->buildBlockStatusArray($clientID, $applicationID, $showCopiedAssetsView, $systemID);
                                        $assetsByInstanceID = $assetsByInstanceID + $blockInstance;
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE:

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $ciscoUCSServiceProfileInfo = $this->BP->get_ucssp_info($clientID, $applicationID, true, $systemID);
                                        }
                                        else
                                        {
                                            $ciscoUCSServiceProfileInfo = $this->BP->get_grandclient_ucssp_info ($clientID, $applicationID);
                                        }
                                        // Sort the Profiles alphabetically
                                        // If ciscoUCS ever has multiple nodes, add back the sort
                                        // $ciscoUCSServiceProfileInfo = $this->sort($ciscoUCSServiceProfileInfo);

                                        foreach ($ciscoUCSServiceProfileInfo as $serviceProfileInstance)
                                        {
                                            $assetsByInstanceID[$serviceProfileInstance['instance_id']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_STATUS,
                                                    Constants::INVENTORY_TYPE_FAMILY_CISCO_UCS,
                                                    $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $serviceProfileInstance['instance_id']),
                                                    $clientInfo['name'] . "'s " . $serviceProfileInstance['name'],
                                                    Constants::INVENTORY_ID_CISCO_UCS_SERVICE_PROFILE,
                                                    $showCopiedAssetsView);
                                        }
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_NDMP_DEVICE:
                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $NDMPInfo = $this->BP->get_ndmpvolume_info($clientID, true, $systemID);
                                        }
                                        else
                                        {
                                            $NDMPInfo = $this->BP->get_grandclient_ndmpvolume_info ($clientID);
                                        }
                                        // Sort VMs alphabetically
                                        $NDMPInfo = $this->sort($NDMPInfo);

                                        foreach ($NDMPInfo as $NDMPInstance)
                                        {
                                            $assetsByInstanceID[$NDMPInstance['instance_id']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_STATUS,
                                                Constants::INVENTORY_TYPE_FAMILY_NDMP,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $NDMPInstance['instance_id']),
                                                $NDMPInstance['name'],
                                                Constants::INVENTORY_ID_NDMP_VOLUME,
                                                $showCopiedAssetsView);
                                        }
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_EXCHANGE:

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $exchangeInfo = $this->BP->get_exchange_info($clientID, true, $systemID);
                                        }
                                        else
                                        {
                                            $exchangeInfo = $this->BP->get_grandclient_exchange_info ($clientID);
                                        }
                                        // Sort dbs alphabetically
                                        $exchangeInfo = $this->sort($exchangeInfo);

                                        foreach ($exchangeInfo as $exchangeInstance)
                                        {
                                            $assetsByInstanceID[$exchangeInstance['instance_id']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_STATUS,
                                                Constants::INVENTORY_TYPE_FAMILY_EXCHANGE,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $exchangeInstance['instance_id']),
                                                $exchangeInstance['name'],
                                                Constants::INVENTORY_ID_EXCHANGE_DATABASE,
                                                $showCopiedAssetsView);
                                        }
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_SQL_SERVER:

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $sqlInfo = $this->BP->get_sql_info($clientID, $applicationID, true, $systemID);
                                        }
                                        else
                                        {
                                            $sqlInfo = $this->BP->get_grandclient_sql_info($clientID, $applicationID);
                                        }
                                        // Sort dbs alphabetically
                                        $sqlInfo = $this->sort($sqlInfo, 'database');

                                        foreach ($sqlInfo as $sqlInstance)
                                        {
                                            // give the SQL instance a unique ID.
                                            $instance_uuid = substr($sqlInstance['instance'], strpos($sqlInstance['instance'], "\\") + 1);
                                            $tempNode = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_STATUS,
                                                Constants::INVENTORY_TYPE_FAMILY_SQL,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $sqlInstance['instance_id'], $instance_uuid),
                                                $sqlInstance['database'], //Figure out if this should be instance or database
                                                Constants::INVENTORY_ID_SQL_DATABASE,
                                                $showCopiedAssetsView);
                                            if ( isset( $sqlInstance['recovery_model'] ) )
                                            {
                                                $tempNode['recovery_model'] = $sqlInstance['recovery_model'];
                                            }
                                            $assetsByInstanceID[$sqlInstance['instance_id']] = $tempNode;
                                        }
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_HYPER_V:

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $hyperVInfo = $this->BP->get_hyperv_info($clientID, true, $systemID);
                                        }
                                        else
                                        {
                                            $hyperVInfo = $this->BP->get_grandclient_hyperv_info($clientID);
                                        }
                                        // Sort VMs alphabetically
                                        $hyperVInfo = $this->sort($hyperVInfo);

                                        foreach ($hyperVInfo as $hyperVVM)
                                        {
                                            $assetsByInstanceID[$hyperVVM['instance_id']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_STATUS,
                                                Constants::INVENTORY_TYPE_FAMILY_HYPER_V,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $hyperVVM['instance_id']),
                                                $hyperVVM['name'],
                                                Constants::INVENTORY_ID_HYPER_V_VM,
                                                $showCopiedAssetsView);
                                            if (isset($hyperVVM['is_saved_state'])) {
                                                $assetsByInstanceID[$hyperVVM['instance_id']]['recovery_model'] = $this->getHVModelString($hyperVVM['is_saved_state'], $hyperVVM['name']);
                                            }
                                        }
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_ORACLE:

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $oracleInfo = $this->BP->get_oracle_info($clientID, $applicationID, true, $systemID);
                                        }
                                        else
                                        {
                                            $oracleInfo = $this->BP->get_grandclient_oracle_info($clientID, $applicationID);
                                        }
                                        // Sort VMs alphabetically
                                        $oracleInfo = $this->sort($oracleInfo);

                                        foreach ($oracleInfo as $oracleInstance)
                                        {
                                            $assetsByInstanceID[$oracleInstance['instance_id']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_STATUS,
                                                Constants::INVENTORY_TYPE_FAMILY_ORACLE,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $oracleInstance['instance_id']),
                                                $oracleInstance['name'],
                                                Constants::INVENTORY_ID_ORACLE_DATABASE,
                                                $showCopiedAssetsView);
                                        }
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_SHAREPOINT:

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $sharepointInfo = $this->BP->get_sharepoint_info($clientID, $applicationID, true, $systemID);
                                        }
                                        else
                                        {
                                            $sharepointInfo = $this->BP->get_grandclient_sharepoint_info($clientID, $applicationID);
                                        }
                                        // Sort VMs alphabetically
                                        $sharepointInfo = $this->sort($sharepointInfo);

                                        foreach ($sharepointInfo as $sharepointInstance)
                                        {
                                            $assetsByInstanceID[$sharepointInstance['instance_id']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_STATUS,
                                                Constants::INVENTORY_TYPE_FAMILY_SHAREPOINT,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $sharepointInstance['instance_id']),
                                                $sharepointInstance['name'],
                                                Constants::INVENTORY_ID_SHAREPOINT_FARM,
                                                $showCopiedAssetsView);
                                        }
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_XEN:

                                        if ( $showCopiedAssetsView === false )
                                        {
                                            $xenInfo = $this->BP->get_xen_vm_info($clientID, true, false, $systemID);
                                        }
                                        else
                                        {
                                            $xenInfo = $this->BP->get_grandclient_xen_vm_info($clientID);
                                        }
                                        // Sort VMs alphabetically
                                        $xenInfo = $this->sort($xenInfo);

                                        foreach ($xenInfo as $xenVM)
                                        {
                                            $assetsByInstanceID[$xenVM['instance_id']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_STATUS,
                                                Constants::INVENTORY_TYPE_FAMILY_XEN,
                                                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $xenVM['instance_id'], $xenVM['server_uuid']),
                                                $xenVM['name'],
                                                Constants::INVENTORY_ID_XEN_VM,
                                                $showCopiedAssetsView);
                                        }
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_AHV:
                                        $ahvInstances = $this->buildAHVStatusArray($clientID, $applicationID, $showCopiedAssetsView, $systemID);
                                        $assetsByInstanceID = $assetsByInstanceID + $ahvInstances;
                                        break;

                                    case Constants::APPLICATION_TYPE_NAME_VMWARE:
                                        if (!isset($applicationArray['servers'])) {
                                            break;
                                        }
                                        foreach ($applicationArray['servers'] as $esxServerUUID => $esxServerName)
                                        {
                                            if ( $showCopiedAssetsView === false )
                                            {
                                                $vmList = $this->BP->get_vm_info($esxServerUUID, NULL, true, false, $systemID);
                                            }
                                            else
                                            {
                                                $vmList = $this->BP->get_grandclient_vm_info($esxServerName, $clientID);
                                            }
                                            // Sorts VMs alphabetically
                                            $vmList = $this->sort($vmList);

                                            if ($vmList !== false and count($vmList) > 0)
                                            {
                                                foreach ($vmList as $vm)
                                                {
                                                    if ($vm['template'] === true)
                                                    {
                                                        $assetsByInstanceID[$vm['instance_id']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_STATUS,
                                                            Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                            $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $vm['instance_id'], $esxServerUUID),
                                                            $vm['name'],
                                                            Constants::INVENTORY_ID_VMWARE_TEMPLATE,
                                                            $showCopiedAssetsView);
                                                    }
                                                    else
                                                    {
                                                        $assetsByInstanceID[$vm['instance_id']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_STATUS,
                                                            Constants::INVENTORY_TYPE_FAMILY_VMWARE,
                                                            $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $vm['instance_id'], $esxServerUUID),
                                                            $vm['name'],
                                                            Constants::INVENTORY_ID_VMWARE_VM,
                                                            $showCopiedAssetsView);
                                                    }
                                                    if ( isset( $vm['model'] ) )
                                                    {
                                                        $assetsByInstanceID[$vm['instance_id']]['recovery_model'] = $vm['model'];
                                                    }
                                                }
                                            }
                                        }
                                        break;
                                }
                            }
                        }
                    }
                }
            }
            if ( $showCopiedAssetsView === true )
            {
                $inventoryStatusArray = array_replace( $inventoryStatusArray, $assetsByInstanceID );
                continue;
            }

            // ----Backups Logic----

            $backup_status_result_format = array();
            $backup_status_result_format['system_id'] = $systemID;
            $backup_status_result_format['type'] = array(
                Constants::BACKUP_TYPE_MASTER,
                Constants::BACKUP_TYPE_DIFFERENTIAL,
                Constants::BACKUP_TYPE_INCREMENTAL,
                Constants::BACKUP_TYPE_BAREMETAL,
                Constants::BACKUP_TYPE_SELECTIVE,
                Constants::BACKUP_TYPE_BLOCK_FULL,
                Constants::BACKUP_TYPE_BLOCK_INCREMENTAL,
                Constants::BACKUP_TYPE_MSSQL_FULL,
                Constants::BACKUP_TYPE_MSSQL_DIFFERENTIAL,
                Constants::BACKUP_TYPE_MSSQL_TRANSACTION,
                Constants::BACKUP_TYPE_EXCHANGE_FULL,
                Constants::BACKUP_TYPE_EXCHANGE_DIFFERENTIAL,
                Constants::BACKUP_TYPE_EXCHANGE_INCREMENTAL,
                Constants::BACKUP_TYPE_LEGACY_MSSQL_FULL,
                Constants::BACKUP_TYPE_LEGACY_MSSQL_DIFF,
                Constants::BACKUP_TYPE_LEGACY_MSSQL_TRANS,
                Constants::BACKUP_TYPE_VMWARE_FULL,
                Constants::BACKUP_TYPE_VMWARE_DIFFERENTIAL,
                Constants::BACKUP_TYPE_VMWARE_INCREMENTAL,
                Constants::BACKUP_TYPE_HYPER_V_FULL,
                Constants::BACKUP_TYPE_HYPER_V_INCREMENTAL,
                Constants::BACKUP_TYPE_HYPER_V_DIFFERENTIAL,
                Constants::BACKUP_TYPE_ORACLE_FULL,
                Constants::BACKUP_TYPE_ORACLE_INCR,
                Constants::BACKUP_TYPE_SHAREPOINT_FULL,
                Constants::BACKUP_TYPE_SHAREPOINT_DIFF,
                Constants::BACKUP_TYPE_UCS_SERVICE_PROFILE_FULL,
                Constants::BACKUP_TYPE_XEN_FULL,
                Constants::BACKUP_TYPE_NDMP_FULL,
                Constants::BACKUP_TYPE_NDMP_DIFF,
                Constants::BACKUP_TYPE_NDMP_INCR,
                Constants::BACKUP_TYPE_AHV_FULL,
                Constants::BACKUP_TYPE_AHV_DIFF,
                Constants::BACKUP_TYPE_AHV_INCR);
            //Constants::BACKUP_TYPE_SYSTEM_METADATA,  // Do NOT include system metadata in this report
            $backup_status_result_format['start_time'] = strtotime('today -6 days');
            $backup_status_result_format['grandclients'] = false; //$showCopiedAssetsView; //In this case, it is always false
            /*
            if ( array_key_exists('finish_interval_start', $data) )
            {
                $backup_status_result_format['finish_interval_start'] = $data['finish_interval_start'];
            }
            if ( array_key_exists('Finish_interval_end', $data) )
            {
                $backup_status_result_format['Finish_interval_end'] = $data['Finish_interval_end'];
            }
            */

            $backup_status_result_array = $this->BP->get_backup_status($backup_status_result_format);

            if ($backup_status_result_array !== false)
            {
                foreach ($backup_status_result_array as &$backup)
                {
                    $backupInstanceID = NULL;
                    $day = date("w", $backup['start_time']);

                    switch ( $backup['type'] )
                    {
                        case Constants::BACKUP_TYPE_MASTER:
                        case Constants::BACKUP_TYPE_DIFFERENTIAL:
                        case Constants::BACKUP_TYPE_INCREMENTAL:
                        case Constants::BACKUP_TYPE_BAREMETAL:
                        case Constants::BACKUP_TYPE_SELECTIVE:
                            $backupInstanceID = $fileLevelInstanceIDByClientID[$backup['client_id']];
                            break;
                        default:
                        /*case Constants::BACKUP_TYPE_MSSQL_FULL:
                        case Constants::BACKUP_TYPE_EXCHANGE_FULL:
                        case Constants::BACKUP_TYPE_LEGACY_MSSQL_FULL:
                        case Constants::BACKUP_TYPE_VMWARE_FULL:
                        case Constants::BACKUP_TYPE_HYPER_V_FULL:
                        case Constants::BACKUP_TYPE_ORACLE_FULL:
                        case Constants::BACKUP_TYPE_SHAREPOINT_FULL:
                        case Constants::BACKUP_TYPE_MSSQL_DIFFERENTIAL:
                        case Constants::BACKUP_TYPE_EXCHANGE_DIFFERENTIAL:
                        case Constants::BACKUP_TYPE_LEGACY_MSSQL_DIFF:
                        case Constants::BACKUP_TYPE_VMWARE_DIFFERENTIAL:
                        case Constants::BACKUP_TYPE_HYPER_V_DIFFERENTIAL:
                        case Constants::BACKUP_TYPE_SHAREPOINT_DIFF:
                        case Constants::BACKUP_TYPE_EXCHANGE_INCREMENTAL:
                        case Constants::BACKUP_TYPE_VMWARE_INCREMENTAL:
                        case Constants::BACKUP_TYPE_HYPER_V_INCREMENTAL:
                        case Constants::BACKUP_TYPE_ORACLE_INCR:
                        case Constants::BACKUP_TYPE_MSSQL_TRANSACTION:
                        case Constants::BACKUP_TYPE_LEGACY_MSSQL_TRANS:
                        case Constants::BACKUP_TYPE_UCS_SERVICE_PROFILE_FULL:
                        case Constants::BACKUP_TYPE_XEN_FULL:
                        case Constants::BACKUP_TYPE_NDMP_FULL,
                        case Constants::BACKUP_TYPE_NDMP_DIFF,
                        case Constants::BACKUP_TYPE_NDMP_INCR*/
                            $backupInstanceID = $backup['instance_id'];
                            break;
                    }

                    if ( $backup['complete'] === true )
                    {
                        // cases when clients have been decommissioned by user or are unreachable; inventory sync fails
                        // only applicable for Weekly Status reports; do not show unreachable clients in Protect page's inventory status
                        if($fromReports === true && !array_key_exists('id', $assetsByInstanceID[$backupInstanceID])) {
                            $assetsByInstanceID[$backupInstanceID]['name'] = (array_key_exists('database_name', $backup) ? $backup['database_name'] : "");
                            if ($assetsByInstanceID[$backupInstanceID]['name'] == ""){
                                $instanceInfo = $this->functions->getInstanceNames($backupInstanceID, $systemID);
                                if ($instanceInfo['app_type'] == Constants::APPLICATION_TYPE_NAME_ORACLE){
                                    $assetsByInstanceID[$backupInstanceID]['name'] = $instanceInfo['asset_name'];
                                }
                            }
                            $assetsByInstanceID[$backupInstanceID]['id'] = $this->buildInventoryNodeID($systemID, $backup['client_id'], $backup['app_id'], $backup['instance_id']);

                            $empty_day = array( 'day' => 0, 'successes' => array('count' => 0, 'ids' => array()), 'failures' => array('count' => 0, 'ids' => array()), 'warnings' => array('count' => 0, 'ids' => array()), 'incomplete' => array('count' => 0, 'ids' => array()) );
                            $empty_day_backup_copy = array( 'day' => 0, 'successes' => array('count' => 0, 'ids' => array()), 'failures' => array('count' => 0, 'ids' => array()), 'incomplete' => array('count' => 0, 'ids' => array()), 'backup_copy_targets' => array() );
                            $assetsByInstanceID[$backupInstanceID]['last_backups'] = array( 0=>$empty_day, 1=>$empty_day, 2=>$empty_day, 3=>$empty_day, 4=>$empty_day, 5=>$empty_day, 6=>$empty_day );
                            $assetsByInstanceID[$backupInstanceID]['last_backup_copies'] = array( 0=>$empty_day_backup_copy, 1=>$empty_day_backup_copy, 2=>$empty_day_backup_copy, 3=>$empty_day_backup_copy, 4=>$empty_day_backup_copy, 5=>$empty_day_backup_copy, 6=>$empty_day_backup_copy );
                            for ( $i=1; $i<7; $i++ )
                            {
                                $assetsByInstanceID[$backupInstanceID]['last_backups'][$i]['day'] = $i;
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$i]['day'] = $i;
                            }
                        }

                        switch ( $backup['status'] )
                        {
                            case Constants::BACKUP_STATUS_SUCCESS:
                                $assetsByInstanceID[$backupInstanceID]['last_backups'][$day]['successes']['count'] += 1;
                                $assetsByInstanceID[$backupInstanceID]['last_backups'][$day]['successes']['ids'][] = $backup['id'];
                                break;
                            case Constants::BACKUP_STATUS_WARNINGS:
                                $assetsByInstanceID[$backupInstanceID]['last_backups'][$day]['warnings']['count'] += 1;
                                $assetsByInstanceID[$backupInstanceID]['last_backups'][$day]['warnings']['ids'][] = $backup['id'];
                                break;
                            case Constants::BACKUP_STATUS_FAILURE:
                                $assetsByInstanceID[$backupInstanceID]['last_backups'][$day]['failures']['count'] += 1;
                                $assetsByInstanceID[$backupInstanceID]['last_backups'][$day]['failures']['ids'][] = $backup['id'];
                                break;
                        }

                        $riskLevel = $this->BP->get_backups_risk_level($backup['id'], $systemID);
                        if ($riskLevel !== false && !empty($riskLevel)) {
                            $assetsByInstanceID[$backupInstanceID]['last_backups'][$day]['risk']['count'] += 1;
                            $assetsByInstanceID[$backupInstanceID]['last_backups'][$day]['risk']['ids'][] = $backup['id'];
                        }
                    }
                    else
                    {
                        $assetsByInstanceID[$backupInstanceID]['last_backups'][$day]['incomplete']['count'] += 1;
                        $assetsByInstanceID[$backupInstanceID]['last_backups'][$day]['incomplete']['ids'][] = $backup['id'];
                    }
                }
            }

            // ----Replication Backup Copy Logic----

            $backup_status_result_format['type'] = array(
                Constants::BACKUP_TYPE_SECURESYNC_MASTER,
                Constants::BACKUP_TYPE_SECURESYNC_DIFFERENTIAL,
                Constants::BACKUP_TYPE_SECURESYNC_INCREMENTAL,
                Constants::BACKUP_TYPE_SECURESYNC_BAREMETAL,
                Constants::BACKUP_TYPE_SECURESYNC_BLOCK_FULL,
                Constants::BACKUP_TYPE_SECURESYNC_BLOCK_INCREMENTAL,
                //Constants::BACKUP_TYPE_SECURESYNC_DPU_STATE, // Do NOT include system metadata in this report
                //Constants::BACKUP_TYPE_SECURESYNC_LOCAL_DIRECTORY, // Do NOT include system metadata in this report
                Constants::BACKUP_TYPE_SECURESYNC_MS_SQL,
                Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE,
                Constants::BACKUP_TYPE_SECURESYNC_MSSQL_FULL,
                Constants::BACKUP_TYPE_SECURESYNC_MSSQL_DIFFERENTIAL,
                Constants::BACKUP_TYPE_SECURESYNC_MSSQL_TRANSACTION,
                Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE_FULL,
                Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE_DIFFERENTIAL,
                Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE_INCREMENTAL,
                Constants::BACKUP_TYPE_SECURESYNC_VMWARE_FULL,
                Constants::BACKUP_TYPE_SECURESYNC_VMWARE_DIFFERENTIAL,
                Constants::BACKUP_TYPE_SECURESYNC_VMWARE_INCREMENTAL,
                Constants::BACKUP_TYPE_SECURESYNC_HYPER_V_FULL,
                Constants::BACKUP_TYPE_SECURESYNC_HYPER_V_INCREMENTAL,
                Constants::BACKUP_TYPE_SECURESYNC_HYPER_V_DIFFERENTIAL,
                Constants::BACKUP_TYPE_SECURESYNC_ORACLE_FULL,
                Constants::BACKUP_TYPE_SECURESYNC_ORACLE_INCR,
                Constants::BACKUP_TYPE_SECURESYNC_SHAREPOINT_FULL,
                Constants::BACKUP_TYPE_SECURESYNC_SHAREPOINT_DIFF,
                Constants::BACKUP_TYPE_SECURESYNC_UCS_SERVICE_PROFILE_FULL,
                Constants::BACKUP_TYPE_SECURESYNC_XEN_FULL,
                Constants::BACKUP_TYPE_SECURESYNC_NDMP_FULL,
                Constants::BACKUP_TYPE_SECURESYNC_NDMP_DIFF,
                Constants::BACKUP_TYPE_SECURESYNC_NDMP_INCR,
                Constants::BACKUP_TYPE_SECURESYNC_AHV_FULL,
                Constants::BACKUP_TYPE_SECURESYNC_AHV_DIFF,
                Constants::BACKUP_TYPE_SECURESYNC_AHV_INCR);

            $backup_status_result_array = $this->BP->get_backup_status($backup_status_result_format);

            if ($backup_status_result_array !== false)
            {
                foreach ($backup_status_result_array as &$backup)
                {
                    $backupInstanceID = NULL;
                    $targetName = $backup['destination'];
                    $day = date("w", $backup['start_time']);

                    switch ( $backup['type'] )
                    {
                        case Constants::BACKUP_TYPE_SECURESYNC_MASTER:
                        case Constants::BACKUP_TYPE_SECURESYNC_DIFFERENTIAL:
                        case Constants::BACKUP_TYPE_SECURESYNC_INCREMENTAL:
                        case Constants::BACKUP_TYPE_SECURESYNC_BAREMETAL:
                            $backupInstanceID = $fileLevelInstanceIDByClientID[$backup['client_id']];
                            break;
                        default:
                        /*case Constants::BACKUP_TYPE_SECURESYNC_MS_SQL:
                        case Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE:
                        case Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE_FULL:
                        case Constants::BACKUP_TYPE_SECURESYNC_MSSQL_FULL:
                        case Constants::BACKUP_TYPE_SECURESYNC_VMWARE_FULL:
                        case Constants::BACKUP_TYPE_SECURESYNC_HYPER_V_FULL:
                        case Constants::BACKUP_TYPE_SECURESYNC_ORACLE_FULL:
                        case Constants::BACKUP_TYPE_SECURESYNC_SHAREPOINT_FULL:
                        case Constants::BACKUP_TYPE_SECURESYNC_MSSQL_DIFFERENTIAL:
                        case Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE_DIFFERENTIAL:
                        case Constants::BACKUP_TYPE_SECURESYNC_VMWARE_DIFFERENTIAL:
                        case Constants::BACKUP_TYPE_SECURESYNC_HYPER_V_DIFFERENTIAL:
                        case Constants::BACKUP_TYPE_SECURESYNC_SHAREPOINT_DIFF:
                        case Constants::BACKUP_TYPE_SECURESYNC_EXCHANGE_INCREMENTAL:
                        case Constants::BACKUP_TYPE_SECURESYNC_VMWARE_INCREMENTAL:
                        case Constants::BACKUP_TYPE_SECURESYNC_HYPER_V_INCREMENTAL:
                        case Constants::BACKUP_TYPE_SECURESYNC_ORACLE_INCR:
                        case Constants::BACKUP_TYPE_SECURESYNC_MSSQL_TRANSACTION:
                        case Constants::BACKUP_TYPE_SECURESYNC_UCS_SERVICE_PROFILE_FULL:
                        case Constants::BACKUP_TYPE_SECURESYNC_XEN_FULL:
                        case Constants::BACKUP_TYPE_SECURESYNC_NDMP_FULL,
                        case Constants::BACKUP_TYPE_SECURESYNC_NDMP_DIFF,
                        case Constants::BACKUP_TYPE_SECURESYNC_NDMP_INCR*/
                            $backupInstanceID = $backup['instance_id'];
                            break;
                    }

                    if ( array_key_exists( $targetName, $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets']) === false )
                    {
                        $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets'][$targetName] = array(
                            'successes' => array('count' => 0, 'ids' => array()),
                            'failures' => array('count' => 0, 'ids' => array()),
                            'incomplete' => array('count' => 0, 'ids' => array()) );
                    }

                    if ( $backup['complete'] === true )
                    {
                        switch ( $backup['status'] )
                        {
                            case Constants::BACKUP_STATUS_SUCCESS:
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['successes']['count'] += 1;
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['successes']['ids'][] = $backup['id'];
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets'][$targetName]['successes']['count'] += 1;
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets'][$targetName]['successes']['ids'][] = $backup['id'];
                                break;
                            case Constants::BACKUP_STATUS_FAILURE:
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['failures']['count'] += 1;
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['failures']['ids'][] = $backup['id'];
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets'][$targetName]['failures']['count'] += 1;
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets'][$targetName]['failures']['ids'][] = $backup['id'];
                                break;
                        }
                    }
                    else
                    {
                        $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['incomplete']['count'] += 1;
                        $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['incomplete']['ids'][] = $backup['id'];
                        $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets'][$targetName]['incomplete']['count'] += 1;
                        $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets'][$targetName]['incomplete']['ids'][] = $backup['id'];
                    }
                }
            }


            //----Archiving Backup Copy Logic----


            $archive_status_result_format = array();
            $archive_status_result_format['type'] = array(
                Constants::BACKUP_TYPE_MASTER,
                Constants::BACKUP_TYPE_DIFFERENTIAL,
                Constants::BACKUP_TYPE_INCREMENTAL,
                Constants::BACKUP_TYPE_BAREMETAL,
                Constants::BACKUP_TYPE_SELECTIVE,
                Constants::BACKUP_TYPE_BLOCK_FULL,
                Constants::BACKUP_TYPE_BLOCK_INCREMENTAL,
                Constants::BACKUP_TYPE_MSSQL_FULL,
                Constants::BACKUP_TYPE_MSSQL_DIFFERENTIAL,
                Constants::BACKUP_TYPE_MSSQL_TRANSACTION,
                Constants::BACKUP_TYPE_EXCHANGE_FULL,
                Constants::BACKUP_TYPE_EXCHANGE_DIFFERENTIAL,
                Constants::BACKUP_TYPE_EXCHANGE_INCREMENTAL,
                Constants::BACKUP_TYPE_LEGACY_MSSQL_FULL,  // Not listed in the definition of bp_get_archive_status
                Constants::BACKUP_TYPE_LEGACY_MSSQL_DIFF,  // Not listed in the definition of bp_get_archive_status
                Constants::BACKUP_TYPE_LEGACY_MSSQL_TRANS,  // Not listed in the definition of bp_get_archive_status
                Constants::BACKUP_TYPE_VMWARE_FULL,
                Constants::BACKUP_TYPE_VMWARE_DIFFERENTIAL,
                Constants::BACKUP_TYPE_VMWARE_INCREMENTAL,
                Constants::BACKUP_TYPE_HYPER_V_FULL,
                Constants::BACKUP_TYPE_HYPER_V_INCREMENTAL,
                Constants::BACKUP_TYPE_HYPER_V_DIFFERENTIAL,  // Not listed in the definition of bp_get_archive_status
                Constants::BACKUP_TYPE_ORACLE_FULL,
                Constants::BACKUP_TYPE_ORACLE_INCR,
                Constants::BACKUP_TYPE_SHAREPOINT_FULL,
                Constants::BACKUP_TYPE_SHAREPOINT_DIFF,
                Constants::BACKUP_TYPE_UCS_SERVICE_PROFILE_FULL,
                Constants::BACKUP_TYPE_XEN_FULL,
            //Constants::BACKUP_TYPE_SYSTEM_METADATA,  // Do NOT include system metadata in this report
                Constants::BACKUP_TYPE_NDMP_FULL,
                Constants::BACKUP_TYPE_NDMP_DIFF,
                Constants::BACKUP_TYPE_NDMP_INCR,
                Constants::BACKUP_TYPE_AHV_FULL,
                Constants::BACKUP_TYPE_AHV_DIFF,
                Constants::BACKUP_TYPE_AHV_INCR );

            $archive_status_result_format['job_start_time'] = $backup_status_result_format['start_time'];

            $archive_status_result_format = $this->BP->get_archive_status($archive_status_result_format, $systemID);

            if ($archive_status_result_format !== false)
            {
                foreach ($archive_status_result_format as &$archive_backup)
                {
                    if ( $archive_backup['is_imported'] === false and array_key_exists('client_id', $archive_backup) )
                    {
                        $backupInstanceID = NULL;
                        $targetName = $archive_backup['target'];
                        $day = date("w", $archive_backup['archive_time']);

                        switch ( $archive_backup['type'] )
                        {
                            case Constants::BACKUP_TYPE_MASTER:
                            case Constants::BACKUP_TYPE_DIFFERENTIAL:
                            case Constants::BACKUP_TYPE_INCREMENTAL:
                            case Constants::BACKUP_TYPE_BAREMETAL:
                            case Constants::BACKUP_TYPE_SELECTIVE:
                            case Constants::BACKUP_TYPE_BLOCK_INCREMENTAL:
                                $backupInstanceID = $fileLevelInstanceIDByClientID[$archive_backup['client_id']];
                                break;
                            default:
                            /*case Constants::BACKUP_TYPE_MSSQL_FULL:
                            case Constants::BACKUP_TYPE_EXCHANGE_FULL:
                            case Constants::BACKUP_TYPE_LEGACY_MSSQL_FULL:
                            case Constants::BACKUP_TYPE_VMWARE_FULL:
                            case Constants::BACKUP_TYPE_HYPER_V_FULL:
                            case Constants::BACKUP_TYPE_ORACLE_FULL:
                            case Constants::BACKUP_TYPE_SHAREPOINT_FULL:
                            case Constants::BACKUP_TYPE_MSSQL_DIFFERENTIAL:
                            case Constants::BACKUP_TYPE_EXCHANGE_DIFFERENTIAL:
                            case Constants::BACKUP_TYPE_LEGACY_MSSQL_DIFF:
                            case Constants::BACKUP_TYPE_VMWARE_DIFFERENTIAL:
                            case Constants::BACKUP_TYPE_HYPER_V_DIFFERENTIAL:
                            case Constants::BACKUP_TYPE_SHAREPOINT_DIFF:
                            case Constants::BACKUP_TYPE_EXCHANGE_INCREMENTAL:
                            case Constants::BACKUP_TYPE_VMWARE_INCREMENTAL:
                            case Constants::BACKUP_TYPE_HYPER_V_INCREMENTAL:
                            case Constants::BACKUP_TYPE_ORACLE_INCR:
                            case Constants::BACKUP_TYPE_MSSQL_TRANSACTION:
                            case Constants::BACKUP_TYPE_LEGACY_MSSQL_TRANS:
                            case Constants::BACKUP_TYPE_UCS_SERVICE_PROFILE_FULL:
                            case Constants::BACKUP_TYPE_XEN_FULL:*/
                                $backupInstanceID = $archive_backup['instance_id'];
                                break;
                        }

                        if ( array_key_exists( $targetName, $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets']) === false )
                        {
                            $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets'][$targetName] = array(
                                'successes' => array('count' => 0, 'ids' => array()),
                                'failures' => array('count' => 0, 'ids' => array()),
                                'incomplete' => array('count' => 0, 'ids' => array()) );
                        }
                        if ( $archive_backup['success'] === true )
                        {
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['successes']['count'] += 1;
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['successes']['ids'][] = $archive_backup['archive_id'].'a';
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets'][$targetName]['successes']['count'] += 1;
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets'][$targetName]['successes']['ids'][] = $archive_backup['archive_id'].'a';
                        }
                        else
                        {
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['failures']['count'] += 1;
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['failures']['ids'][] = $archive_backup['archive_id'].'a';
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets'][$targetName]['failures']['count'] += 1;
                                $assetsByInstanceID[$backupInstanceID]['last_backup_copies'][$day]['backup_copy_targets'][$targetName]['failures']['ids'][] = $archive_backup['archive_id'].'a';
                        }
                    }
                }
            }


            $inventoryStatusArray = array_merge( $inventoryStatusArray, array_values($assetsByInstanceID) );
        }


        if ( $showCopiedAssetsView === true )
        {
            // ----Backups Logic----

            $backup_status_result_format = array();
            $backup_status_result_format['system_id'] = $localSystemID;
            $backup_status_result_format['type'] = array(
                Constants::BACKUP_TYPE_MASTER,
                Constants::BACKUP_TYPE_DIFFERENTIAL,
                Constants::BACKUP_TYPE_INCREMENTAL,
                Constants::BACKUP_TYPE_BAREMETAL,
                Constants::BACKUP_TYPE_SELECTIVE,
                Constants::BACKUP_TYPE_BLOCK_FULL,
                Constants::BACKUP_TYPE_BLOCK_INCREMENTAL,
                Constants::BACKUP_TYPE_MSSQL_FULL,
                Constants::BACKUP_TYPE_MSSQL_DIFFERENTIAL,
                Constants::BACKUP_TYPE_MSSQL_TRANSACTION,
                Constants::BACKUP_TYPE_EXCHANGE_FULL,
                Constants::BACKUP_TYPE_EXCHANGE_DIFFERENTIAL,
                Constants::BACKUP_TYPE_EXCHANGE_INCREMENTAL,
                Constants::BACKUP_TYPE_LEGACY_MSSQL_FULL,
                Constants::BACKUP_TYPE_LEGACY_MSSQL_DIFF,
                Constants::BACKUP_TYPE_LEGACY_MSSQL_TRANS,
                Constants::BACKUP_TYPE_VMWARE_FULL,
                Constants::BACKUP_TYPE_VMWARE_DIFFERENTIAL,
                Constants::BACKUP_TYPE_VMWARE_INCREMENTAL,
                Constants::BACKUP_TYPE_HYPER_V_FULL,
                Constants::BACKUP_TYPE_HYPER_V_INCREMENTAL,
                Constants::BACKUP_TYPE_HYPER_V_DIFFERENTIAL,
                Constants::BACKUP_TYPE_ORACLE_FULL,
                Constants::BACKUP_TYPE_ORACLE_INCR,
                Constants::BACKUP_TYPE_SHAREPOINT_FULL,
                Constants::BACKUP_TYPE_SHAREPOINT_DIFF,
                Constants::BACKUP_TYPE_UCS_SERVICE_PROFILE_FULL,
                Constants::BACKUP_TYPE_XEN_FULL,
            //Constants::BACKUP_TYPE_SYSTEM_METADATA,  // Do NOT include system metadata in this report
                Constants::BACKUP_TYPE_NDMP_FULL,
                Constants::BACKUP_TYPE_NDMP_DIFF,
                Constants::BACKUP_TYPE_NDMP_INCR,
                Constants::BACKUP_TYPE_AHV_FULL,
                Constants::BACKUP_TYPE_AHV_DIFF,
                Constants::BACKUP_TYPE_AHV_INCR );

            $backup_status_result_format['start_time'] = strtotime('today -6 days');
            $backup_status_result_format['grandclients'] = true; //$showCopiedAssetsView; //In this case, it is always true
            /*
            if ( array_key_exists('finish_interval_start', $data) )
            {
                $backup_status_result_format['finish_interval_start'] = $data['finish_interval_start'];
            }
            if ( array_key_exists('Finish_interval_end', $data) )
            {
                $backup_status_result_format['Finish_interval_end'] = $data['Finish_interval_end'];
            }
            */

            $backup_status_result_array = $this->BP->get_backup_status($backup_status_result_format);

            if ($backup_status_result_array !== false)
            {
                foreach ($backup_status_result_array as &$backup)
                {
                    $backupInstanceID = NULL;
                    $day = date("w", $backup['start_time']);

                    switch ( $backup['type'] )
                    {
                        case Constants::BACKUP_TYPE_MASTER:
                        case Constants::BACKUP_TYPE_DIFFERENTIAL:
                        case Constants::BACKUP_TYPE_INCREMENTAL:
                        case Constants::BACKUP_TYPE_BAREMETAL:
                        case Constants::BACKUP_TYPE_SELECTIVE:
                            $backupInstanceID = $fileLevelInstanceIDByClientID[$backup['client_id']];
                            break;
                        default:
                            /*case Constants::BACKUP_TYPE_MSSQL_FULL:
                            case Constants::BACKUP_TYPE_EXCHANGE_FULL:
                            case Constants::BACKUP_TYPE_LEGACY_MSSQL_FULL:
                            case Constants::BACKUP_TYPE_VMWARE_FULL:
                            case Constants::BACKUP_TYPE_HYPER_V_FULL:
                            case Constants::BACKUP_TYPE_ORACLE_FULL:
                            case Constants::BACKUP_TYPE_SHAREPOINT_FULL:
                            case Constants::BACKUP_TYPE_MSSQL_DIFFERENTIAL:
                            case Constants::BACKUP_TYPE_EXCHANGE_DIFFERENTIAL:
                            case Constants::BACKUP_TYPE_LEGACY_MSSQL_DIFF:
                            case Constants::BACKUP_TYPE_VMWARE_DIFFERENTIAL:
                            case Constants::BACKUP_TYPE_HYPER_V_DIFFERENTIAL:
                            case Constants::BACKUP_TYPE_SHAREPOINT_DIFF:
                            case Constants::BACKUP_TYPE_EXCHANGE_INCREMENTAL:
                            case Constants::BACKUP_TYPE_VMWARE_INCREMENTAL:
                            case Constants::BACKUP_TYPE_HYPER_V_INCREMENTAL:
                            case Constants::BACKUP_TYPE_ORACLE_INCR:
                            case Constants::BACKUP_TYPE_MSSQL_TRANSACTION:
                            case Constants::BACKUP_TYPE_LEGACY_MSSQL_TRANS:
                            case Constants::BACKUP_TYPE_UCS_SERVICE_PROFILE_FULL:
                            case Constants::BACKUP_TYPE_XEN_FULL:*/
                            $backupInstanceID = $backup['instance_id'];
                            break;
                    }

                    if ( $backup['complete'] === true )
                    {
                        switch ( $backup['status'] )
                        {
                            case Constants::BACKUP_STATUS_SUCCESS:
                                $inventoryStatusArray[$backupInstanceID]['last_backup_copies'][$day]['successes']['count'] += 1;
                                $inventoryStatusArray[$backupInstanceID]['last_backup_copies'][$day]['successes']['ids'][] = $backup['id'];
                                break;
                            case Constants::BACKUP_STATUS_FAILURE:
                                $inventoryStatusArray[$backupInstanceID]['last_backup_copies'][$day]['failures']['count'] += 1;
                                $inventoryStatusArray[$backupInstanceID]['last_backup_copies'][$day]['failures']['ids'][] = $backup['id'];
                                break;
                        }
                    }
                    else
                    {
                        $inventoryStatusArray[$backupInstanceID]['last_backup_copies'][$day]['incomplete']['count'] += 1;
                        $inventoryStatusArray[$backupInstanceID]['last_backup_copies'][$day]['incomplete']['ids'][] = $backup['id'];
                    }
                }
            }

            $inventoryStatusArray = array_values($inventoryStatusArray);
        }

        return $inventoryStatusArray;

    }

    /*
     * Builds and returns an AHV status array for the VMs in the tree.
     */
    private function buildAHVStatusArray($clientID, $applicationID, $showCopiedAssetsView, $systemID) {

        $assetsByInstanceID = array();

        if ( $showCopiedAssetsView === false )
        {
            $ahvInfo = $this->BP->get_ahv_vm_info($clientID, true, false, $systemID);
        }
        else
        {
            $ahvInfo = $this->BP->get_grandclient_ahv_vm_info($clientID);
        }
        // Sort VMs alphabetically
        $ahvInfo = $this->sort($ahvInfo);

        foreach ($ahvInfo as $vm)
        {
            $assetsByInstanceID[$vm['instance_id']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_STATUS,
                Constants::INVENTORY_TYPE_FAMILY_AHV,
                $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $vm['instance_id']),
                $vm['name'],
                Constants::INVENTORY_ID_AHV_VM,
                $showCopiedAssetsView);
        }

        return $assetsByInstanceID;
    }

    /*
     * Builds and returns an block status array for the block instance in the tree.
     */
    private function buildBlockStatusArray($clientID, $applicationID, $showCopiedAssetsView, $systemID) {

        $assetsByInstanceID = array();

        if ( $showCopiedAssetsView === false )
        {
            $blockInfo = $this->BP->get_block_info($clientID, $systemID);
        }
        else
        {
            $blockInfo = $this->BP->get_grandclient_block_info($clientID);
        }

        $assetsByInstanceID[$blockInfo['instance_id']] = $this->buildInventoryNodeArray(Inventory::INVENTORY_GET_TYPE_STATUS,
            Constants::INVENTORY_TYPE_FAMILY_BLOCK,
            $this->buildInventoryNodeID($systemID, $clientID, $applicationID, $blockInfo['instance_id']),
            $blockInfo['client_name'] . ' (' . Constants::APPLICATION_TYPE_DISPLAY_NAME_BLOCK_LEVEL . ')',
            Constants::INVENTORY_ID_BLOCK,
            $showCopiedAssetsView);

        return $assetsByInstanceID;
    }

    public function getInventoryIDForAClientFromOSFamily( $os_family )
    {
        $inventoryID = Constants::INVENTORY_ID_CLIENT_OTHER;
        switch ( $os_family )
        {
            case Constants::OS_FAMILY_DOS:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_DOS;
                break;
            case Constants::OS_FAMILY_OES:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_OES;
                break;
            case Constants::OS_FAMILY_OS2:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_OS2;
                break;
            case Constants::OS_FAMILY_UNIX:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_UNIX;
                break;
            case Constants::OS_FAMILY_SCO:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_SCO;
                break;
            case Constants::OS_FAMILY_SOLARIS:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_SOLARIS;
                break;
            case Constants::OS_FAMILY_AIX:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_AIX;
                break;
            case Constants::OS_FAMILY_SGI:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_SGI;
                break;
            case Constants::OS_FAMILY_HPUX:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_HPUX;
                break;
            case Constants::OS_FAMILY_FREE_BSD:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_FREE_BSD;
                break;
            case Constants::OS_FAMILY_MAC_OS:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_MAC;
                break;
            case Constants::OS_FAMILY_LINUX:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_LINUX;
                break;
            case Constants::OS_FAMILY_I_SERIES:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_I_SERIES;
                break;
            case Constants::OS_FAMILY_NOVELL_NETWARE:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_NETWARE;
                break;
            case Constants::OS_FAMILY_WINDOWS:
                $inventoryID = Constants::INVENTORY_ID_CLIENT_WINDOWS;
                break;
            case Constants::OS_FAMILY_GENERIC:  // There shouldn't be an Inventory Node for a Generic Client.
                $inventoryID = Constants::INVENTORY_ID_CLIENT_OTHER;
                break;
        }
        return $inventoryID;
    }

    private function getInventorySync( $sid )
    {
        require_once('summary.php');
        $summary = new Summary( $this->BP, $sid );
        return $summary->get("current", "");
    }

    private function putInventoryDisks( $node_id, $data = NULL  )
    {
        $status = false;

        $is_excluded = true;  // True excludes the disk, False includes the disk
        if ( array_key_exists('is_excluded', $data) )
        {
            $is_excluded = (bool)$data['is_excluded'];
        }


        if ($node_id !== NULL)
        {
            $inventoryNodeIDArray = $this->getNodeIDArrayFromInventoryNodeID($node_id);
            $specifiedSystemID = NULL;
            if ( array_key_exists('systemID', $inventoryNodeIDArray) )
            {
                $specifiedSystemID = $inventoryNodeIDArray['systemID'];
                if (array_key_exists('instanceID', $inventoryNodeIDArray))
                {
                    $specifiedInstanceID = $inventoryNodeIDArray['instanceID'];
                    if (array_key_exists('diskKey', $inventoryNodeIDArray))
                    {
                        $specifiedDiskKey = $inventoryNodeIDArray['diskKey'];

                        if ( $specifiedDiskKey !== NULL )
                        {
                            if ( array_key_exists('applicationID', $inventoryNodeIDArray) )
                            {
                                $specifiedApplicationID = $inventoryNodeIDArray['applicationID'];
                                if ( $specifiedApplicationID == Constants::APPLICATION_ID_VMWARE )
                                {
                                    $status = $this->BP->set_vm_disks( array( 0=>array('instance_id'=>(int)$specifiedInstanceID, 'key'=>(int)$specifiedDiskKey, 'is_excluded'=>$is_excluded) ), $specifiedSystemID );
                                }
                                else
                                {
                                    $status = $this->BP->set_xen_vm_disks( array( 0=>array('instance_id'=>(int)$specifiedInstanceID, 'key'=>(int)$specifiedDiskKey, 'is_excluded'=>$is_excluded) ), $specifiedSystemID );
                                }
                            }

                        }
                    }
                    else
                    {
                        //A node_id with diskKey needs to be specified
                        var_dump('here');
                    }
                }
                else
                {
                    //A node_id with instanceID needs to be specified
                    var_dump('ouch');
                }
            }
            else
            {
                //incorrect node_id format
                var_dump('there');
            }
        }
        else
        {
            //node_id must be present
            var_dump('where');
        }

        return $status;
    }

    public function buildInventoryNodeArray( $inventoryGetType, $type_family, $id, $name, $type, $showCopiedAssetsView = false, $osTypeID = NULL, $is_sql_cluster = false, $is_sql_alwayson = false)
    {
        $inventoryNodeArray = array();
        $inventoryNodeArray['type_family']  = $type_family;
        $inventoryNodeArray['id']           = $id;
        $inventoryNodeArray['name']         = $name;
        $inventoryNodeArray['type']         = $type;
        if (isset($osTypeID) && $osTypeID != null) {
            $inventoryNodeArray['os_type_id'] = $osTypeID;
        }

        //additional properties for SQL clusters and SQL Availability groups
        if (isset($is_sql_cluster) && $is_sql_cluster !== false) {
            $inventoryNodeArray['is_sql_cluster'] = true;
        }
        if (isset($is_sql_alwayson) && $is_sql_alwayson !==  false) {
            $inventoryNodeArray['is_sql_alwayson'] = true;
        }
        switch ($inventoryGetType)
        {
            case (Inventory::INVENTORY_GET_TYPE_TREE):
                $inventoryNodeArray['nodes']  = array();
                break;
            case (Inventory::INVENTORY_GET_TYPE_SETTINGS):
                $inventoryNodeArray['credentials']  = array();  //TBD = NULL;
                $inventoryNodeArray['is_standalone']  = false;
                $inventoryNodeArray['app_aware']  = false;
                $inventoryNodeArray['template']  = false;
                $inventoryNodeArray['cluster_name'] = "";
                break;
            case (Inventory::INVENTORY_GET_TYPE_STATUS):
                if ( $showCopiedAssetsView === false )
                {
                    $empty_day = array( 'day' => 0, 'successes' => array('count' => 0, 'ids' => array()), 'failures' => array('count' => 0, 'ids' => array()), 'warnings' => array('count' => 0, 'ids' => array()), 'incomplete' => array('count' => 0, 'ids' => array()), 'risk' => array('count' => 0, 'ids' => array()) );
                    $empty_day_backup_copy = array( 'day' => 0, 'successes' => array('count' => 0, 'ids' => array()), 'failures' => array('count' => 0, 'ids' => array()), 'incomplete' => array('count' => 0, 'ids' => array()), 'backup_copy_targets' => array() );
                    $inventoryNodeArray['last_backups'] = array( 0=>$empty_day, 1=>$empty_day, 2=>$empty_day, 3=>$empty_day, 4=>$empty_day, 5=>$empty_day, 6=>$empty_day );
                    $inventoryNodeArray['last_backup_copies'] = array( 0=>$empty_day_backup_copy, 1=>$empty_day_backup_copy, 2=>$empty_day_backup_copy, 3=>$empty_day_backup_copy, 4=>$empty_day_backup_copy, 5=>$empty_day_backup_copy, 6=>$empty_day_backup_copy );
                    for ( $i=1; $i<7; $i++ )
                    {
                        $inventoryNodeArray['last_backups'][$i]['day'] = $i;
                        $inventoryNodeArray['last_backup_copies'][$i]['day'] = $i;
                    }
                }
                else
                {
                    // Copied Assets view - don't need information on archives, targets, or regular backups
                    $empty_day_copied_assets_view = array( 'day' => 0, 'successes' => array('count' => 0, 'ids' => array()), 'failures' => array('count' => 0, 'ids' => array()), 'incomplete' => array('count' => 0, 'ids' => array()) );
                    $inventoryNodeArray['last_backup_copies'] = array( 0=>$empty_day_copied_assets_view, 1=>$empty_day_copied_assets_view, 2=>$empty_day_copied_assets_view, 3=>$empty_day_copied_assets_view, 4=>$empty_day_copied_assets_view, 5=>$empty_day_copied_assets_view, 6=>$empty_day_copied_assets_view );
                    for ( $i=1; $i<7; $i++ )
                    {
                        $inventoryNodeArray['last_backup_copies'][$i]['day'] = $i;
                    }
                }
                break;
        }

        return $inventoryNodeArray;
    }


    //Three options for a NodeID - VMware Resource Pool or vApp, VMware vm, or not VMware
    //Not VMware option: $systemID_$clientID_$applicationID_$instanceID_$diskKey
    //VMware VM option: $systemID_$clientID_$applicationID_$esxServerUUID_$instanceID_$diskKey
    //VMware Resource Pool or vApp option: $systemID_$clientID_$applicationID_$esxServerUUID_$VMwareKey
    //Do NOT give both a $VMwareKey and an $instanceID
    public function getNodeIDArrayFromInventoryNodeID( $inventoryNodeId )
    {
        $returnArray = array();
        if ( $inventoryNodeId !== NULL )
        {
            $tempArray = explode( Inventory::INVENTORY_NODE_SEPARATOR, $inventoryNodeId );
            if ( count($tempArray) > 0 and is_numeric($tempArray[0]) )
            {
                $returnArray['systemID'] = (int)$tempArray[0];
                if ( count($tempArray) > 1 and is_numeric($tempArray[1]) )
                {
                    $returnArray['clientID'] = (int)$tempArray[1];
                    if ( count($tempArray) > 2 and is_numeric($tempArray[2]) )//and (int)$tempArray[2] !== Constants::APPLICATION_ID_FILE_LEVEL )  //Don't show application ID for file-level
                    {
                        $returnArray['applicationID'] = (int)$tempArray[2];
                        if ( count($tempArray) > 3 )
                        {
                            if ( $returnArray['applicationID'] === Constants::APPLICATION_ID_VMWARE ||
                                 $returnArray['applicationID'] === Constants::APPLICATION_ID_XEN)
                            {
                                $returnArray['esxServerUUID'] = (string)$tempArray[3];
                                if ( count($tempArray) > 4 )
                                {
                                    //VMware VM option: $systemID_$clientID_$applicationID_$esxServerUUID_$instanceID_$diskKey
                                    // Xen VM option: $systemID_$clientID_$applicationID_$ServerUUID_$instanceID_$diskKey
                                    if ( is_numeric($tempArray[4]) )
                                    {
                                        $returnArray['instanceID'] = (int)$tempArray[4];
                                        if ( count($tempArray) > 5  and is_numeric($tempArray[5]) )
                                        {
                                            $returnArray['diskKey'] = $tempArray[5];
                                        }
                                    }
                                    //VMware Resource Pool or vApp option: $systemID_$clientID_$applicationID_$esxServerUUID_$VMwareKey
                                    //Make sure that $VMwareKey are never numeric
                                    else
                                    {
                                        $returnArray['VMwareKey'] = (string)$tempArray[4];
                                    }
                                }
                            }
                            //Not VMware option: $systemID_$clientID_$applicationID_$instanceID
                            else
                            {
                                $returnArray['instanceID'] = (int)$tempArray[3];
                                if ( count($tempArray) > 4 )
                                {
                                    $returnArray['diskKey'] = $tempArray[4];
                                }
                            }
                        }
                    }
                }
            }
        }
        return $returnArray;
    }

    //Three options for a NodeID - VMware Resource Pool or vApp, VMware vm, or not VMware
    //Not VMware option: $systemID_$clientID_$applicationID_$instanceID
    //VMware VM option: $systemID_$clientID_$applicationID_$esxServerUUID_$instanceID_$diskKey
    //VMware Resource Pool or vApp option: $systemID_$clientID_$applicationID_$esxServerUUID_$VMwareKey
    //Do NOT give both a $VMwareKey and an $instanceID
    // SQL can have an instanceID that is mapped to serverUUID
    // Xen can also have a server UUID
    protected function buildInventoryNodeID( $systemID,
                                           $clientID = NULL,
                                           $applicationID = NULL,
                                           $instanceID = NULL,
                                           $serverUUID = NULL,
                                           $VMwareKey = NULL ,
                                           $diskKey = NULL )
    {
        $returnID = "";
        if ( $systemID !== NULL )
        {
            $returnID = (string)$systemID;
            if ( $clientID !== NULL )
            {
                $returnID = $returnID.(Inventory::INVENTORY_NODE_SEPARATOR).(string)$clientID;
                if ( $applicationID !== NULL )
                {
                    $returnID = $returnID.(Inventory::INVENTORY_NODE_SEPARATOR).(string)$applicationID;
                    if (((int)$applicationID === Constants::APPLICATION_ID_VMWARE ||
                            (int)$applicationID === Constants::APPLICATION_ID_XEN ||
                            (int)$applicationID === Constants::APPLICATION_ID_AHV ||
                            (int)$applicationID === Constants::APPLICATION_ID_SQL_SERVER_2005 ||
                            (int)$applicationID === Constants::APPLICATION_ID_SQL_SERVER_2008 ||
                            (int)$applicationID === Constants::APPLICATION_ID_SQL_SERVER_2008_R2 ||
                            (int)$applicationID === Constants::APPLICATION_ID_SQL_SERVER_2012 ||
                            (int)$applicationID === Constants::APPLICATION_ID_SQL_SERVER_2014 ||
                            (int)$applicationID === Constants::APPLICATION_ID_SQL_SERVER_2016 ||
                            (int)$applicationID === Constants::APPLICATION_ID_SQL_SERVER_2017 ||
                            $this->functions->isAppHyperV((int)$applicationID)) and $serverUUID !== NULL )
                    {
                        $returnID = $returnID.(Inventory::INVENTORY_NODE_SEPARATOR).(string)$serverUUID;
                        //VMware VM option: $systemID_$clientID_$applicationID_$esxServerUUID_$instanceID_$diskKey
                        if ( $instanceID !== NULL )
                        {
                            $returnID = $returnID.(Inventory::INVENTORY_NODE_SEPARATOR).(string)$instanceID;
                            if ( $diskKey !== NULL )
                            {
                                $returnID = $returnID.(Inventory::INVENTORY_NODE_SEPARATOR).(string)$diskKey;
                            }
                        }
                        //VMware Resource Pool or vApp option: $systemID_$clientID_$applicationID_$esxServerUUID_$VMwareKey
                        elseif ( $VMwareKey !== NULL )
                        {
                            $returnID = $returnID.(Inventory::INVENTORY_NODE_SEPARATOR).(string)$VMwareKey;
                        }
                    }
                    //Not VMware option: $systemID_$clientID_$applicationID_$instanceID
                    elseif ( $instanceID !== NULL )
                    {
                        $returnID = $returnID.(Inventory::INVENTORY_NODE_SEPARATOR).(string)$instanceID;
                        if ( $diskKey !== NULL )
                        {
                            $returnID = $returnID.(Inventory::INVENTORY_NODE_SEPARATOR).(string)$diskKey;
                        }
                    }
                }
            }
        }
        return $returnID;
    }

    //Double check this logic with multi tiers of recursion.
    protected function recursiveResourcePoolAndVAppAssignment($parents, $children, $showNavGroups, $nav, $navGroupsArray)
    {
        $newChildren = array();
        if ( count($children) > 0 )
        {
            foreach ( $children as $childKey => $child )
            {
                if ( array_key_exists($child['parentKey'], $parents) )
                {
                    $parents[$child['parentKey']]['resourcePoolOrVApp'][$childKey] = $child;
                    unset($parents[$child['parentKey']]['resourcePoolOrVApp'][$childKey]['parentKey']);
                }
                else
                {
                    $newChildren[$childKey] = $child;
                }
            }
            if ( count($newChildren) > 0 )
            {
                foreach ( $parents as $parentKey => $parent )
                {
                    if ( count($parent['resourcePoolOrVApp']) > 0 )
                    {
                        $returnedArray = $this->recursiveResourcePoolAndVAppAssignment( $parent['resourcePoolOrVApp'], $newChildren, $showNavGroups, $nav, $navGroupsArray);
                        $parents[$parentKey]['resourcePoolOrVApp'] = $returnedArray['parents'];
                        $newChildren = $returnedArray['children'];
                    }
                    /*else
                    {
                        unset($parents[$parentKey]['resourcePoolOrVApp']);
                    }*/
                }
            }
            else
            {
                foreach ( $parents as $parentKey => $parent )
                {
                    foreach ( $parent['resourcePoolOrVApp'] as $childKey => $child )
                    {
                        $inGroup = $showNavGroups && $this->groupPoolOrVApp($nav, $child, $navGroupsArray);
                        if (!$inGroup) {
                            $parents[$parentKey]['resourcePoolOrVApp'][$childKey]['nodes'] =
                                array_values(array_merge($parents[$parentKey]['resourcePoolOrVApp'][$childKey]['resourcePoolOrVApp'],
                                    $parents[$parentKey]['resourcePoolOrVApp'][$childKey]['nodes']));
                        }
                        unset($parents[$parentKey]['resourcePoolOrVApp'][$childKey]['resourcePoolOrVApp']);
                    }

                    if ($showNavGroups) {
                        foreach ($parent['resourcePoolOrVApp'] as $childKey => $child) {
                            $found = $nav->addChildren($navGroupsArray,
                                $parents[$parentKey]['resourcePoolOrVApp'][$childKey],
                                $nav->convertID($child['id']));
                        }
                    }
                }
            }
        }
        foreach ( $parents as $parentKey => $parent )
        {
            if ($showNavGroups) {
                $nodeArray = array();
                foreach ($parent['resourcePoolOrVApp'] as $pool => $node) {
                    if (!$this->groupPoolOrVapp($nav, $node, $navGroupsArray, true)) {
                        $nodeArray[] = $node;
                    }
                }
                $parents[$parentKey]['nodes'] = array_merge($nodeArray, $parents[$parentKey]['nodes']);
            } else {
                $parents[$parentKey]['nodes'] = array_values(array_merge($parents[$parentKey]['resourcePoolOrVApp'], $parents[$parentKey]['nodes']));
            }
            unset($parents[$parentKey]['resourcePoolOrVApp']);
        }
        if ($showNavGroups) {
            foreach ($parents as $parentKey => $parent) {
                $found = $nav->addChildren($navGroupsArray, $parents[$parentKey], $nav->convertID($parent['id']));
            }
        }


        return( array('parents' => $parents, 'children' => $newChildren) );
    }

    protected function getInventoryInstance( $sid ){
        $instances = $_GET['instances'];
        $instancesArray = explode(",", $instances);
        foreach($instancesArray as $key => $instance){
            $Instanceinfo = $this->BP->get_appinst_info($instance,$sid);
            foreach($Instanceinfo as &$val){
                if($val['app_type'] === "VMware") {
                    $val['client_name'] = $val['primary_name'];
                    $val['vm_name'] = $val['secondary_name'];
                    unset($val['primary_name']);
                    unset($val['secondary_name']);
                }
                elseif($val['app_type'] === "Hyper-V"){
                    $val['vm_name'] = $val['primary_name'];
                    unset($val['primary_name']);
                    unset($val['secondary_name']);
                }
            }
          $info['instances'][] = $Instanceinfo;

        }
        return $info;
    }

    // Get disk exclusion status
    private function getDiskInfo ( $sid ) {
        $appid = $_GET['appid'];

        $instanceInfo = $this->BP->get_appinst_info($appid, $sid);
        if ( $instanceInfo['app_type'] == Constants::APPLICATION_TYPE_NAME_XEN ) {
            $result = $this->BP->get_xen_vm_disks($appid, false, $sid);
        } else if ($instanceInfo['app_type'] == Constants::APPLICATION_TYPE_NAME_AHV) {
            $result = $this->BP->get_ahv_vm_disks($appid, false, $sid);
        } else {
            $result = $this->BP->get_vm_disks($appid, false, $sid);
        }

        $returnArray = array();
        foreach( $result as $diskinfo ) {
            $returnArray['instance_id'] = $result['instance_id'];
            $returnArray['name'] = $result['name'];
            $returnArray['is_excluded'] = $result['is_excluded'];
        }
        return $returnArray;
    }

    // Set disks to be excluded
    //XenServer may need to be added to this, but no one is currently consuming this
    private function setDiskInfo ( $sid ) {
        $appid = $_GET['appid'];
        $key = $_GET['key'];
        $setDiskResult = $this->BP->set_vm_disks( $appid, $key, $sid );
        return $setDiskResult;
    }


    // Sorts the Array alphabetically
    protected function sort($Array, $sortIndex = 'name', $fn = null) {
        $Info = $Array;
        if ($sortIndex === null) {
            asort($Info);
        } else if ($sortIndex === 'usort' && $fn != null) {
            usort($Info, $fn);
        } else {
            $orderByName = array();
            foreach ($Info as $key => $row) {
                $sortVal = $row[$sortIndex];
                $orderByName[$key] = strtolower($sortVal);
            }
            array_multisort($orderByName, SORT_STRING, $Info);
        }
        return $Info;
    }

    private function compareGroupNames($a, $b) {
        $aName = strtolower($a['@attributes']['name']);
        $bName = strtolower($b['@attributes']['name']);
        if ($aName == $bName) {
            $result = 0;
        } else if ($aName > $bName) {
            $result = 1;
        } else {
            $result = -1;
        }
        //printf("In compare group names....%s, %s,result = %d\n", $a['@attributes']['name'], $b['@attributes']['name'], $result);
        return $result;
    }

    private function addClientToGroup($nav, $nodeArray, &$groupsArray, $systemID, $clientID)
    {
        return $nav->addToGroup($nodeArray, $groupsArray, $nav->makeID($systemID, $clientID));
    }

    private function groupESXServer($nav, $esxServer, &$groupsArray, $systemID, $clientID, $esxServerUUID)
    {
        $found = $nav->addToGroup($esxServer, $groupsArray, $nav->makeID($systemID, $clientID));
        if (!$found) {
            $found = $nav->addToGroup($esxServer, $groupsArray, $nav->makeID($systemID, $clientID, Constants::APPLICATION_ID_VMWARE, $esxServerUUID));
        }
        return $found;
    }

    private function groupVM($nav, $nodeArray, &$groupsArray)
    {
        return $this->groupAsset($nav, $nodeArray, $groupsArray);
    }

    private function groupAHVHost($nav, $nodeArray, &$groupsArray, $systemID, $clientID, $appID)
    {
        return $nav->addToGroup($nodeArray, $groupsArray, $nav->makeID($systemID, $clientID, $appID));
    }

    private function groupAsset($nav, $nodeArray, &$groupsArray)
    {
        return $nav->addToGroup($nodeArray, $groupsArray, $nav->convertID($nodeArray['id']));
    }

    private function groupPoolOrVapp($nav, $nodeArray, &$groupsArray, $replace = false)
    {
        if ($replace) {
            $found = $nav->replaceOrAddNode($nodeArray, $groupsArray, $nav->convertID($nodeArray['id']));
        } else {
            $found = $nav->addToGroup($nodeArray, $groupsArray, $nav->convertID($nodeArray['id']));
        }
        return $found;
    }

    private function getHVModelString($isSavedState, $instanceName) {
        $modelString = "";
        if ($isSavedState === true) {
            $modelString = self::INVENTORY_MODEL_SAVED_STATE;
        }
        return $modelString;
    }

    function getReplicaCandidates($systems, $sid, $gclient){
        $result = array();
        $inputArray = array('app_type' => 'VMware');

        if ($gclient) {
            foreach ($systems as $sourceID => $sname) {
                $candidates = $this->BP->get_replica_candidates($inputArray, true,
                    $sourceID);
                if ($candidates !== false) {
                    foreach ($candidates as $candidate) {
                        $result['candidates'][] = $candidate;
                    }
                }
            }
        } else {
            $candidates = $this->BP->get_replica_candidates($inputArray, false, $sid);

            if ($candidates !== false) {
                foreach ($candidates as $candidate) {
                    $result['candidates'][] = $candidate;
                }
            }
        }
        return $result;
    }
    function getReplicaFilterStrings($filters, $gclient){
        $replicaFilters = array();
        $iidFilter = array();
        $parentKeyFilter = array();
        $parentTypeFilter = array();

        foreach($filters as $key => $value) {
            $iidFilter[] = $value['instance_id'];
            if (!$gclient) {
                $parentKeyFilter[] = $value['parentKey'];
                $parentTypeFilter[] = $value['parentType'];
            }
        }
        $replicaFilters['iid'] = implode(",", $iidFilter);
        if (!$gclient) {
            $replicaFilters['parentKey'] = $parentKeyFilter;
            $replicaFilters['parentType'] = $parentTypeFilter;
        }

        return $replicaFilters;
    }
}
?>
