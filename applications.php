<?php

class Applications
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);
        $this->localID = $this->BP->get_local_system_id();

    }

    public function get($data, $sid)
    {
        $allClients = array();
        $clientID = isset($data['client_id']) ? (int)$data['client_id'] : -1;
        $grandClientView = (isset($data['grandclient']) && $data['grandclient'] === "true") ? true : false;
        $uuid = isset($data['uuid']) ? $data['uuid'] : "";

        if ($clientID !== -1) {
            $allClients['data'] = $this->getAppInfo($clientID, $grandClientView, $uuid, $sid);
        }
        return $allClients;
    }

    private function getAppInfo($clientID, $grandClientView, $uuid, $system_id)
    {
        $system_name = $this->functions->getSystemNameFromID($system_id);

        $clientInfo = $this->BP->get_client_info($clientID, $system_id);

        if ($clientInfo !== false) {
            if (!$grandClientView && $uuid !== "") {
                $appInfo = array();
                $appInfo = $this->BP->get_vcenter_info($uuid, $system_id);
                $appInfo['asset_type'] = Constants::ASSET_TYPE_VMWARE_HOST;
                $appInfo['system_id'] = $system_id;
                $appInfo['system_name'] = $system_name;
                $vmList = $this->BP->get_vm_info($uuid, NULL, true, false, $system_id);
                $vmList = $this->sort($vmList);
                if (!empty($vmList)) {
                    foreach ($vmList as $vm) {
                        $val = array('id' => $vm['instance_id'], 'name' => $vm['name'], 'is_encrypted' => $vm['is_encrypted']);
                        $val['uuid'] = $uuid;
                        $val['asset_type'] = $val['description'] = Constants::ASSET_TYPE_VMWARE_VM;
                        $val['system_id'] = $system_id;
                        $val['system_name'] = $system_name;
                        $allVMs[] = $val;
                    }
                }
                $appInfo['children'] = $allVMs;
                $allClients[] = $appInfo;

            } else {
                $vmware_gcv = false;
                $clientInfo['asset_type'] = $grandClientView && $clientInfo['name'] == Constants::CLIENT_NAME_VCENTER_RRC ? Constants::ASSET_TYPE_VMWARE_HOST : Constants::ASSET_TYPE_PHYSICAL;

                $applications = $clientInfo['applications'];
                $client_info['client_id'] = $clientInfo['id'];
                $client_info['client_name'] = $clientInfo['name'];
                $client_info['grandclient'] = $clientInfo['grandclient'];
                if (count($applications) > 0) {
                    foreach ($applications as $appID => $appInfo) {
                        if ($grandClientView && $appInfo['name'] == Constants::APPLICATION_NAME_VMWARE) {
                            $vmware_gcv = true;
                            if (isset($appInfo['servers'])) {
                                $servers = $appInfo['servers'];
                                $appInfo = array('id' => $appID, 'name' => $appInfo['name'], 'description' => $appInfo['name']);
                                foreach ($servers as $host) {
                                    $results = $this->BP->get_grandclient_vm_info($host, $clientInfo['id']);
                                    if ($results !== false) {
                                        $vms = $this->gather($results, $system_id, $system_name, $clientInfo['id'],
                                            Constants::APPLICATION_ID_VMWARE, Constants::ASSET_TYPE_VMWARE_VM);
                                        $appInfo['children'] = $vms;
                                    }
                                    $appInfo['name'] = $host;
                                    $client_info['applications'][] = $appInfo;
                                    $allClients[] = $client_info;
                                }
                            }
                        } else {
                            $assets = $this->getAssets($clientID, $appID, $appInfo, $grandClientView, $system_id, $system_name);
                            $appInfo = array('id' => $appID, 'name' => $appInfo['name'], 'description' => $appInfo['name']);
                            if ($appInfo['name'] !== Constants::APPLICATION_TYPE_NAME_FILE_LEVEL) {
                                $appInfo['children'] = $assets;
                            }
                            $client_info['applications'][] = $appInfo;
                        }
                    }
                }

                $isNAS = (($temp = strlen($clientInfo['name']) - strlen(Constants::NAS_POSTFIX)) >= 0 && strpos($clientInfo['name'], Constants::NAS_POSTFIX, $temp) !== FALSE);
                if ($isNAS) {
                    $storageName = substr($clientInfo['name'], 0, $temp);
                    $storageID = $this->BP->get_storage_id($storageName, $system_id);
                    $storageInfo = $this->BP->rest_get_storage_info($storageID, $system_id);
                    if ($storageInfo !== false) {
                        $client_info['client_id'] = $clientInfo['id'];
                        $client_info['client_name'] = $clientInfo['name'];
                        $client_info['grandclient'] = $clientInfo['grandclient'];
                        $client_info['ip'] = $storageInfo['properties']['hostname'];
                        $client_info['nas_properties'] = $storageInfo['properties'];
                        $client_info['nas_properties']['nas_id'] = $storageInfo['id'];
                    }
                }
                if ($vmware_gcv) {
                    ;
                } else {
                    $allClients[] = $client_info;
                }
            }
        }

        return $allClients;
    }

    function getAssets($clientID, $appID, $appInfo, $grandClientView, $system_id, $system_name)
    {
        $assets = array();
        global $Log;
        switch ($appID) {
            case Constants::APPLICATION_ID_FILE_LEVEL:
                break;

            case Constants::APPLICATION_ID_BLOCK_LEVEL:
                $blockInfo = !$grandClientView ? $this->BP->get_block_info($clientID, $system_id) : $this->BP->get_grandclient_block_info($clientID);
                if ($blockInfo !== false) {
                    $assets = $this->gather(array($blockInfo), $system_id, $system_name, $clientID, $appID, Constants::APPLICATION_TYPE_NAME_BLOCK_LEVEL);
                } else {
                    $Log->writeError("Cannot load block info", true);
                }
                break;

            case Constants::APPLICATION_ID_EXCHANGE_2003:
            case Constants::APPLICATION_ID_EXCHANGE_2007:
            case Constants::APPLICATION_ID_EXCHANGE_2010:
            case Constants::APPLICATION_ID_EXCHANGE_2013:
            case Constants::APPLICATION_ID_EXCHANGE_2016:
                $results = !$grandClientView ? $this->BP->get_exchange_info($clientID, true, $system_id) : $this->BP->get_grandclient_exchange_info($clientID);
                if ($results !== false) {
                    $assets = $this->gather($results, $system_id, $system_name, $clientID, $appID, Constants::APPLICATION_TYPE_NAME_EXCHANGE);
                } else {
                    $Log->writeError("Cannot load exchange info", true);
                }
                break;

            case Constants::APPLICATION_ID_SQL_SERVER_2005:
            case Constants::APPLICATION_ID_SQL_SERVER_2008:
            case Constants::APPLICATION_ID_SQL_SERVER_2008_R2:
            case Constants::APPLICATION_ID_SQL_SERVER_2012:
            case Constants::APPLICATION_ID_SQL_SERVER_2014:
            case Constants::APPLICATION_ID_SQL_SERVER_2016:
            case Constants::APPLICATION_ID_SQL_SERVER_2017:
                $results = !$grandClientView ? $this->BP->get_sql_info($clientID, $appID, true, $system_id) : $this->BP->get_grandclient_sql_info($clientID, $appID);
                if ($results !== false) {
                    $assets = $this->gather($results, $system_id, $system_name, $clientID, $appID, Constants::APPLICATION_TYPE_NAME_SQL_SERVER);
                } else {
                    $Log->writeError("Cannot load sql info", true);
                }
                break;

            case Constants::APPLICATION_ID_VMWARE:
                if ($grandClientView) {
                    if (isset($appInfo['servers'])) {
                        $servers = $appInfo['servers'];
                        foreach ($servers as $host) {
                            $results = $this->BP->get_grandclient_vm_info($host, $clientID);
                            if ($results !== false) {
                                $assets = array_merge($assets,
                                    $this->gather($results, $system_id, $system_name, $clientID, $appID, Constants::ASSET_TYPE_VMWARE_VM));
                            } else {
                                $Log->writeError("Cannot load vm info", true);
                            }
                        }
                    }
                }
                break;

            case Constants::APPLICATION_ID_HYPER_V_2008_R2:
            case Constants::APPLICATION_ID_HYPER_V_2012:
            case Constants::APPLICATION_ID_HYPER_V_2016:
                $results = !$grandClientView ? $this->BP->get_hyperv_info($clientID, true, $system_id) : $this->BP->get_grandclient_hyperv_info($clientID);
                if ($results !== false) {
                    $assets = $this->gather($results, $system_id, $system_name, $clientID, $appID, Constants::ASSET_TYPE_HYPER_V_VM);
                } else {
                    $Log->writeError("Cannot load hyperv info", true);
                }
                break;

            case Constants::APPLICATION_ID_ORACLE_10:
            case Constants::APPLICATION_ID_ORACLE_11:
            case Constants::APPLICATION_ID_ORACLE_12:
                $results = !$grandClientView ? $this->BP->get_oracle_info($clientID, $appID, true, $system_id) : $this->BP->get_grandclient_oracle_info($clientID, $appID);
                if ($results !== false) {
                    $assets = $this->gather($results, $system_id, $system_name, $clientID, $appID, Constants::APPLICATION_TYPE_NAME_ORACLE);
                } else {
                    $Log->writeError("Cannot load oracle info", true);
                }
                break;

            case Constants::APPLICATION_ID_SHAREPOINT_2007:
            case Constants::APPLICATION_ID_SHAREPOINT_2010:
            case Constants::APPLICATION_ID_SHAREPOINT_2013:
            case Constants::APPLICATION_ID_SHAREPOINT_2016:
                $results = !$grandClientView ? $this->BP->get_sharepoint_info($clientID, $appID, true, $system_id) : $this->BP->get_grandclient_sharepoint_info($clientID, $appID);
                if ($results !== false) {
                    $assets = $this->gather($results, $system_id, $system_name, $clientID, $appID, Constants::APPLICATION_TYPE_NAME_SHAREPOINT);
                } else {
                    $Log->writeError("Cannot load sharepoint info", true);
                }
                break;

            case Constants::APPLICATION_ID_UCS_SERVICE_PROFILE:
                $results = !$grandClientView ? $this->BP->get_ucssp_info($clientID, $appID, true, $system_id) : false;
                if ($results !== false) {
                    $assets = $this->gather($results, $system_id, $system_name, $clientID, $appID, Constants::APPLICATION_TYPE_NAME_UCS_SERVICE_PROFILE);
                } else {
                    $Log->writeError("Cannot load ucs info", true);
                }
                break;

            case Constants::APPLICATION_ID_VOLUME:
                $results = !$grandClientView ? $this->BP->get_ndmpvolume_info($clientID, true, $system_id) : $this->BP->get_grandclient_ndmpvolume_info($clientID);
                if ($results !== false) {
                    $assets = $this->gather($results, $system_id, $system_name, $clientID, $appID, Constants::APPLICATION_TYPE_NAME_NDMP_DEVICE);
                } else {
                    $Log->writeError("Cannot load ndmp info", true);
                }
                break;

            case Constants::APPLICATION_ID_XEN:
                $results = !$grandClientView ? $this->BP->get_xen_vm_info($clientID, true, true, $system_id) : $this->BP->get_grandclient_xen_vm_info($clientID);
                if ($results !== false) {
                    $assets = $this->gather($results, $system_id, $system_name, $clientID, $appID, Constants::APPLICATION_TYPE_NAME_XEN);
                } else {
                    $Log->writeError("Cannot load xen info", true);
                }
                break;

            case Constants::APPLICATION_ID_AHV:
                $results = !$grandClientView ? $this->BP->get_ahv_vm_info($clientID, true, true, $system_id) : $this->BP->get_grandclient_ahv_vm_info($clientID);
                if ($results !== false) {
                    $assets = $this->gather($results, $system_id, $system_name, $clientID, $appID, Constants::APPLICATION_TYPE_NAME_AHV);
                } else {
                    $Log->writeError("Cannot load ahv info", true);
                }
                break;
        }

        return $assets;
    }

    function gather($results, $system_id, $system_name, $client_id, $app_id, $asset_type)
    {
        $assets = array();
        $results = $this->sort($results);
        foreach ($results as $result) {
            $name = isset($result['name']) ? $result['name'] :
                (isset($result['instance']) && isset($result['database']) ? $result['instance'] . '::' . $result['database'] : 'unknown');
            $val = array('id' => $result['instance_id'], 'name' => $name, 'is_encrypted' => $result['is_encrypted']);

            $val['client_id'] = $client_id;
            $val['asset_type']  = $asset_type;
            if ($asset_type === Constants::APPLICATION_TYPE_NAME_SQL_SERVER) {
                $val['is_system_db'] = $this->BP->is_sql_system_db($result['instance_id'], $system_id);
            }
            if(isset($result['wvf'])) {
                $val['wvf'] = $result['wvf'];
            }
            $val['system_id'] = $system_id;
            $val['system_name'] = $system_name;
            $assets[] = $val;
        }
        return $assets;
    }

    // Sorts the Array alphabetically
    private function sort($Array) {
        $Info = $Array;
        $orderByName = array();
        foreach ($Info as $key => $row) {
            $orderByName[$key] = strtolower($row['name']);
        }
        array_multisort($orderByName, SORT_STRING, $Info);
        return $Info;
    }
}
?>
