<?php

class VirtualClients
{
    private $BP;

    const WIR_FILTER            = "wir";
    const VMWARE_IR_FILTER      = "vm_ir";
    const HYPERV_IR_FILTER      = "hv_ir";
    const APPLIANCE_IR_FILTER   = "appliance_ir";

    const STATE_NEW         = "new";
    const STATE_HALTED      = "halted";

    const SUPPORTS_WIR      = "supports_wir";
    const SUPPORTS_EFI      = "supports_efi";

    const NOT_AVAILABLE     = "not_available";

    public function __construct($BP)
    {
        $this->BP = $BP;
        $this->Roles = null;

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);

        if (Functions::supportsRoles()) {
            $this->Roles = new Roles($this->BP);
        }
    }

    public function get($which, $data, $sid, $systems)
    {
        $virtualClients = array();

        if ($which == -1) {
            //GET /api/virtual_clients/?sid={sid}&type={type}
            $virtualClients = $this->get_virtual_clients($systems, $data);
        }
        else {
            switch ($which[0]) {
                case 'supported':
                    // GET /api/virtual_clients/supported/?sid={sid}
                    $virtualClients = $this->get_virtual_clients_supported($systems);
                    break;
                case 'candidates':
                    // GET /api/virtual_clients/candidates/?sid={sid}
                    $virtualClients = $this->get_virtual_clients_candidates($systems);
                    break;
                case 'targets':
                    $virtualClients = $this->get_virtual_clients_targets($data, $sid);
                    break;
                case 'snapshot_info':
                    // GET /api/virtual_clients/snapshot_info/?sid={sid}&guid={guid_list}
                    // 'guid_list' is a comma-separated list of GUIDs
                    global $Log;
                    $Log->writeVariable("Getting WIR VM snapshot information");

                    if (isset($_GET['type'])) {
                         $type = $_GET['type'];
                    } else {
                         $virtualClients['error'] = 500;
                         $virtualClients['message'] = "Hypervisor type was not found.";
                         break;
                    }

                    if (isset($_GET['guid'])) {
                         $inputArr = explode(',' , $_GET['guid']);
                    } else {
                         $virtualClients['error'] = 500;
                         $virtualClients['message'] = "No identifier for virtual machine was found.";
                         break;
                    }

                    $virtualClients = $this->BP->get_wir_vm_snapshot_info($type, $inputArr, $sid);
                    break;
                default:
                    // GET /api/virtual_clients/{id}
                    $virtualID = $this->convertSpecialID($which[0]);
                    if (isset($which[1]) && is_string($which[1]) ) {
                        switch($which[1]) {
                            case 'details':
                                // GET /api/virtual_clients/{id}/details/?type={type}
                                $type = isset($data['type']) ? $data['type'] : VirtualClients::WIR_FILTER;
                                $virtualClients = $this->get_virtual_clients_details($virtualID, $type, $sid);
                                break;
                            case 'backup_history':
                                // GET /api/virtual_clients/{id}/backup_history/?sid={sid}
                                $virtualClients = $this->get_virtual_clients_backup_history($virtualID, $sid);
                                break;
                            case 'state':
                                //GET /api/virtual_clients/{id}/state
                                $virtualClients = $this->get_virtual_clients_state($virtualID, $sid);
                                break;
                        }
                    }
                    break;
            }
        }

        return $virtualClients;

    }

    private function get_virtual_clients_supported($systems)
    {
        $supported = array();
        foreach ($systems as $systemID => $systemName) {
            $virtualClientsSupported = $this->BP->virtual_clients_supported($systemID);
            if ($virtualClientsSupported !== -1) {
                $temp_supported_array = array();
                $temp_supported_array['system_id'] = $systemID;
                $temp_supported_array['system_name'] = $systemName;
                $temp_supported_array['supported'] = $virtualClientsSupported;

                $supported['wir'][] = $temp_supported_array;
            }
        }
        return $supported;
    }

    private function get_virtual_clients_candidates($systems)
    {
        $virtualCandidates = array();
        foreach ($systems as $systemID => $systemName) {
            $support = $this->BP->virtual_clients_supported($systemID);
            if ($support !== -1) {
                if ($support !== false) {
                    $candidates = $this->BP->get_virtual_candidates($systemID);
                    if ($candidates !== false) {
                        $temp_candidates_array = array();
                        $temp_candidates_array['system_id'] = $systemID;
                        $temp_candidates_array['system_name'] = $systemName;
                        foreach ($candidates as $candidate) {
                            if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                                if (!$this->Roles->client_is_in_scope($candidate['client_id'], $systemID)) {
                                    continue;
                                }
                            }
                            $temp_candidates = array();
                            $temp_candidates['client_id'] = $candidate['client_id'];
                            $temp_candidates['client_name'] = $candidate['client_name'];
                            $temp_candidates['client_os_id'] = $candidate['os_id'];

                            $client_info = $this->BP->get_client_info($candidate['client_id'], $systemID);
                            if($client_info !== false) {
                                $temp_candidates['client_os'] = $client_info['os_type'];
                            }

                            if (isset($candidate['virtual_id'])) {
                                $temp_candidates['virtual_id'] = $candidate['virtual_id'];
                            }
                            if (isset($candidate['backup_id'])) {
                                $temp_candidates['backup_id'] = $candidate['backup_id'];
                            }
                            if (isset($candidate['grandclient'])) {
                                $temp_candidates['grandclient'] = $candidate['grandclient'];
                            }
                            if (isset($candidate['system_id'])) {
                                $temp_candidates['system_id'] = $candidate['system_id'];
                            }
                            if (isset($candidate['system_name'])) {
                                $temp_candidates['system_name'] = $candidate['system_name'];
                            }
                            $temp_candidates_array['candidates'][] = $temp_candidates;
                        }
                        $virtualCandidates['data'][] = $temp_candidates_array;
                    }
                } else {
                    $virtualCandidates['error'] = 500;
                    $virtualCandidates['message'] = "Windows Failover Virtualization is not supported.";
                }
            } else {
                $virtualCandidates['error'] = 500;
                $virtualCandidates['message'] = $this->BP->getError();
            }
        }
        return $virtualCandidates;
    }

    private function get_virtual_clients($systems, $data)
    {
        $virtual_clients = array();

        foreach ($systems as $systemID => $systemName) {
            $temp_virtual_array = array();
            $temp_virtual_array['system_id'] = $systemID;
            $temp_virtual_array['system_name'] = $systemName;

            if (array_key_exists('type', $data)) {
                switch($data['type']) {
                    case VirtualClients::WIR_FILTER:
                        $temp_virtual_array['wir'] = $this->get_wir_clients($systemID);
                        break;
                    case VirtualClients::VMWARE_IR_FILTER:
                        $temp_virtual_array['vm_ir'] = $this->get_vm_ir_clients($systemID);
                        break;
                    case VirtualClients::HYPERV_IR_FILTER:
                        $temp_virtual_array['hv_ir'] = $this->get_hv_ir_clients($systemID);
                        break;
                    case VirtualClients::APPLIANCE_IR_FILTER:
                        $temp_virtual_array['appliance_ir'] = $this->get_appliance_ir_clients($systemID);
                        break;
                }
            }
            else {
                $temp_virtual_array['wir']          = $this->get_wir_clients($systemID);
                $temp_virtual_array['vm_ir']        = $this->get_vm_ir_clients($systemID);
                $temp_virtual_array['hv_ir']        = $this->get_hv_ir_clients($systemID);
                $temp_virtual_array['appliance_ir'] = $this->get_appliance_ir_clients($systemID);
            }
            $virtual_clients['data'][] = $temp_virtual_array;
        }

        return $virtual_clients;
    }

    public function get_wir_clients($systemID)
    {
        $wir_clients = array();

        $support = $this->BP->virtual_clients_supported($systemID);
        if ($support !== -1) {
            if ($support !== false) {
                $clients = $this->BP->get_virtual_client_list($systemID);
                if ($clients !== false) {
                    foreach ($clients as $client) {
                        if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                            if (!$this->Roles->client_is_in_scope($client['real_client_id'], $systemID)) {
                                continue;
                            }
                        }
                        $temp_wir_clients_array = array();
                        // appending '.wir' to make ID unique
                        $temp_wir_clients_array['virtual_id'] = $client['virtual_id'] . ".wir";
                        // int virtual ID
                        $temp_wir_clients_array['id'] = $client['virtual_id'];
                        $temp_wir_clients_array['type'] = VirtualClients::WIR_FILTER;

                        $clientDetail = $this->BP->get_virtual_client($client['virtual_id'], $systemID);
                        if (isset($clientDetail['hypervisor_type'])) {
                            $temp_wir_clients_array['hypervisor_type'] = $clientDetail['hypervisor_type'];
                        }
                        if (isset($clientDetail['vm_name'])) {
                            $temp_wir_clients_array['vm_name'] = $clientDetail['vm_name'];
                        } elseif (isset($client['client_name'])) {
                                $temp_wir_clients_array['vm_name'] = $client['client_name'];
                        }
                        if (isset($clientDetail['hypervisor_name'])) {
                            $temp_wir_clients_array['server_name'] = $clientDetail['hypervisor_name'];
                        }
                        if (isset($clientDetail['last_message'])) {
                            $temp_wir_clients_array['last_message'] = $clientDetail['last_message'];
                        }
                        $temp_wir_clients_array['mode'] = $client['current_state'];
                        if (isset($client['pending_state'])) {
                            $temp_wir_clients_array['pending_state'] = $client['pending_state'];
                        }
                        if (isset($client['valid'])) {
                            $temp_wir_clients_array['valid'] = $client['valid'];
                        }
                        if (isset($client['live_time']) && $client['live_time'] > 0) {
                            $temp_wir_clients_array['live_time'] = date(Constants::DATE_TIME_FORMAT_US, $client['live_time']);
                        }
                        if (isset($client['ip_addr'])) {
                            $temp_wir_clients_array['ip_address'] = $client['ip_addr'];
                        }
                        if (isset($client['vm_guid'])) {
                            $temp_wir_clients_array['vm_guid'] = $client['vm_guid'];
                        }
                        if (isset($client['grandclient'])) {
                            $temp_wir_clients_array['grandclient'] = $client['grandclient'];
                        }
                        if (isset($client['system_id'])) {
                            $temp_wir_clients_array['system_id'] = $client['system_id'];
                        }
                        if (isset($client['system_name'])) {
                            $temp_wir_clients_array['system_name'] = $client['system_name'];
                        }

                        /*
                         * To be implemented in phase 2

                        $temp_wir_clients_array['Status'] = 'NeedsCoreWork';
                        $temp_wir_clients_array['Created'] = 'NeedsCoreWork';
                        $temp_wir_clients_array['Duration'] = 'NeedsCoreWork';

                        */

                        $wir_clients[] = $temp_wir_clients_array;
                    }
                }
            } else {
                $wir_clients['error'] = 500;
                $wir_clients['message'] = "Windows Failover Virtualization is not supported.";
            }
        } else {
            $wir_clients['error'] = 500;
            $wir_clients['message'] = $this->BP->getError();
        }
        return $wir_clients;
    }

    private function get_vm_ir_clients($systemID)
    {
        $vmIR_clients = array();

        $vmIRStatus = $this->BP->get_vm_ir_status($systemID);

        if ($vmIRStatus !== false) {
            foreach ($vmIRStatus as $vm_status_info) {
                if (isset($vm_status_info['id'])) {
                    if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                        if (!$this->Roles->instance_is_in_scope($vm_status_info['id'], $systemID)) {
                            continue;
                        }
                    }
                    $temp_vmIR_clients_array = array();
                    // appending '.vm_ir' to make ID unique
                    $temp_vmIR_clients_array['virtual_id'] = $vm_status_info['id'] . ".vm_ir";
                    // int virtual ID
                    $temp_vmIR_clients_array['id'] = $vm_status_info['id'];
                    $temp_vmIR_clients_array['type'] = VirtualClients::VMWARE_IR_FILTER;

                    // Get the VM name and server name from the instance, replace below with status IR names once available.
                    // Allows user to see asset name in case of IR failure, in which the API returns not_available.
                    $instanceNames = $this->functions->getInstanceNames($vm_status_info['id'], $systemID);
                    if (count($instanceNames) > 0) {
                        $temp_vmIR_clients_array['vm_name'] = $instanceNames['asset_name'];
                        $temp_vmIR_clients_array['server_name'] = $instanceNames['client_name'];
                    }
                }
                if (isset($vm_status_info['vm_name']) && ($vm_status_info['vm_name'] !== self::NOT_AVAILABLE)) {
                    $temp_vmIR_clients_array['vm_name'] = $vm_status_info['vm_name'];
                }
                if (isset($vm_status_info['vm_server']) && ($vm_status_info['vm_server'] !== self::NOT_AVAILABLE)) {
                    $temp_vmIR_clients_array['server_name'] = $vm_status_info['vm_server'];
                }
                if (isset($vm_status_info['status'])) {
                    $temp_vmIR_clients_array['status'] = $vm_status_info['status'];
                }
                if(isset($vm_status_info['audit'])) {
                    $temp_vmIR_clients_array['mode'] = $vm_status_info['audit'] == 1 ? 'Audit' : 'Instant Recovery';
                }
                if (isset($vm_status_info['time'])) {
                    $temp_vmIR_clients_array['created'] = date(Constants::DATE_TIME_FORMAT_US, $vm_status_info['time']);
                    $temp_vmIR_clients_array['duration'] = $this->get_duration($vm_status_info['time']);
                }
                if (isset($vm_status_info['detail'])) {
                    $temp_vmIR_clients_array['comment'] = $vm_status_info['detail'];
                }
                if (isset($vm_status_info['vm_moref'])) {
                    $temp_vmIR_clients_array['moref'] = $vm_status_info['vm_moref'] !== "not_available" ? $vm_status_info['vm_moref'] : null;
                }

                $vmIR_clients[] = $temp_vmIR_clients_array;
            }
        } else {
            $vmIR_clients = $vmIRStatus;
        }
        return $vmIR_clients;
    }

    private function get_hv_ir_clients($systemID)
    {
        $hvIR_clients = array();

        $hvIRStatus = $this->BP->get_hyperv_ir_status($systemID);

        if ($hvIRStatus !== false) {
            foreach ($hvIRStatus as $hv_status_info) {
                if (isset($hv_status_info['id'])) {
                    if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                        if (!$this->Roles->instance_is_in_scope($hv_status_info['id'], $systemID)) {
                            continue;
                        }
                    }
                    $temp_hvIR_clients_array = array();
                    // appending '.hv_ir' to make ID unique
                    $temp_hvIR_clients_array['virtual_id'] = $hv_status_info['id'] . ".hv_ir";
                    // int virtual ID
                    $temp_hvIR_clients_array['id'] = $hv_status_info['id'];
                    $temp_hvIR_clients_array['type'] = VirtualClients::HYPERV_IR_FILTER;

                    // Get the VM name and server name from the instance, replace below with status IR names once available.
                    // Allows user to see asset name in case of IR failure, in which the API returns not_available.
                    // We can't use the common instanceNames function because we need the GUID also.
                    $appinstInfo = $this->BP->get_appinst_info($hv_status_info['id'], $systemID);
                    $AppItem = "";
                    if ($appinstInfo !== false) {
                        $item = $appinstInfo[$hv_status_info['id']];
                        $temp_hvIR_clients_array['vm_name'] = $item['primary_name'];
                        $temp_hvIR_clients_array['server_name'] = $item['client_name'];
                        $AppItem = $item['secondary_name'];
                    }
                    $temp_hvIR_clients_array['GUID'] = $AppItem;
                }
                if (isset($hv_status_info['vm_name']) && ($hv_status_info['vm_name'] !== self::NOT_AVAILABLE)) {
                    $temp_hvIR_clients_array['vm_name'] = $hv_status_info['vm_name'];
                }
                if (isset($hv_status_info['vm_server']) && ($hv_status_info['vm_server'] !== self::NOT_AVAILABLE)) {
                    $temp_hvIR_clients_array['server_name'] = $hv_status_info['vm_server'];
                }
                if (isset($hv_status_info['status'])) {
                    $temp_hvIR_clients_array['status'] = $hv_status_info['status'];
                }
                if (isset($hv_status_info['audit'])) {
                    $temp_hvIR_clients_array['mode'] = $hv_status_info['audit'] == 1 ? 'Audit' : 'Instant Recovery';
                }
                if (isset($hv_status_info['time'])) {
                    $temp_hvIR_clients_array['created'] = date(Constants::DATE_TIME_FORMAT_US, $hv_status_info['time']);
                    $temp_hvIR_clients_array['duration'] = $this->get_duration($hv_status_info['time']);
                }
                if (isset($hv_status_info['detail'])) {
                    $temp_hvIR_clients_array['comment'] = $hv_status_info['detail'];
                }
                if (isset($hv_status_info['vm_moref'])) {
                    $temp_hvIR_clients_array['moref'] = $hv_status_info['vm_moref'] !== "not_available" ? $hv_status_info['vm_moref'] : null;
                }

                $hvIR_clients[] = $temp_hvIR_clients_array;
            }
        } else {
            $hvIR_clients = $hvIRStatus;
        }
        return $hvIR_clients;
    }

    // Image Level Unitrends Appliance Instant Recovery (Block IR) on Qemu status
    private function get_appliance_ir_clients($systemID)
    {
        $qemuIR_clients = array();

        $qemuIRStatus = $this->BP->get_qemu_ir_status($systemID);

        if ($qemuIRStatus !== false) {
            foreach ($qemuIRStatus as $qemu_status_info) {
                if (isset($qemu_status_info['id'])) {
                    $temp_qemuIR_clients_array = array();
                    // appending '.appliance_ir' to make ID unique
                    $temp_qemuIR_clients_array['virtual_id'] = $qemu_status_info['id'] . ".appliance_ir";
                    // int virtual ID
                    $temp_qemuIR_clients_array['id'] = $qemu_status_info['id'];
                    $temp_qemuIR_clients_array['type'] = VirtualClients::APPLIANCE_IR_FILTER;

                    $appInstName = $this->BP->get_appinst_name($qemu_status_info['id'], $systemID);
                    //need to check for existence of "|" if so take it apart
                    $strPipe = "|";
                    if (strlen(strstr($appInstName, $strPipe)) > 0) {
                        $InstPart1 = explode("|", $appInstName);
                        $AppInstance = $InstPart1[0];
                        $AppItem = $InstPart1[1];
                    }
                    $temp_qemuIR_clients_array['GUID'] = $AppItem;
                }
                if (isset($qemu_status_info['vm_name'])) {
                    $temp_qemuIR_clients_array['vm_name'] = $qemu_status_info['vm_name'];
                }
                if (isset($qemu_status_info['vm_server'])) {
                    $temp_qemuIR_clients_array['server_name'] = $qemu_status_info['vm_server'];
                }
                if (isset($qemu_status_info['status'])) {
                    $temp_qemuIR_clients_array['status'] = $qemu_status_info['status'];
                }
                if (isset($qemu_status_info['audit'])) {
                    $temp_qemuIR_clients_array['mode'] = $qemu_status_info['audit'] == 1 ? 'Audit' : 'Instant Recovery';
                }
                if (isset($qemu_status_info['time'])) {
                    $temp_qemuIR_clients_array['created'] = date(Constants::DATE_TIME_FORMAT_US, $qemu_status_info['time']);
                    $temp_qemuIR_clients_array['duration'] = $this->get_duration($qemu_status_info['time']);
                }
                if (isset($qemu_status_info['detail'])) {
                    $temp_qemuIR_clients_array['comment'] = $qemu_status_info['detail'];
                }
                if (isset($qemu_status_info['port'])) {
                    $temp_qemuIR_clients_array['port'] = $qemu_status_info['port'];
                }

                $qemuIR_clients[] = $temp_qemuIR_clients_array;
            }
        } else {
            $qemuIR_clients = $qemuIRStatus;
        }
        return $qemuIR_clients;
    }

    private function get_virtual_clients_targets($data, $sid)
    {
        $targets = array();
        if (array_key_exists('type', $data)) {
            switch ($data['type']) {
                case Constants::APPLICATION_TYPE_NAME_VMWARE:
                    $targets = $this->get_vmware_targets($sid);
                    break;
                case Constants::APPLICATION_TYPE_NAME_HYPER_V:
                    if (array_key_exists('os_id', $data)) {
                        $targets = $this->get_hyperv_targets($data['os_id'], $sid);
                    } else if (array_key_exists('cid', $data)) {
                        $clientInfo = $this->BP->get_client_info($data['cid'], $sid);
                        if ($clientInfo !== false) {
                            $targets = $this->get_hyperv_targets($clientInfo['os_type_id'], $sid);
                        } else {
                            $targets['error'] = 500;
                            $targets['message'] = $this->BP->getError();
                        }
                    } else {
                        $targets['error'] = 500;
                        $targets['message'] = "Specify the OS or client ID.";
                    }
                    break;
                default:
                    $targets['error'] = 500;
                    $targets['message'] = "Specify the appropriate type of location.";
            }
        } else {
            $targets['error'] = 500;
            $targets['message'] = "Specify the type of location.";
        }

        return $targets;
    }

    private function get_vmware_targets($sid)
    {
        $vm_targets_array = array();
        $instance_id = 0;
        $efi = isset($_GET['efi']) ? (int)$_GET['efi'] : 0;
        $vm_restore_targets = $this->BP->get_vm_restore_targets($instance_id, $sid);
        if ($vm_restore_targets !== false and count($vm_restore_targets) > 0)
        {
            $vm_servers = $vm_restore_targets['servers'];
            foreach ($vm_servers as $vm_restore_target)
            {
                $WIRSupport = false;
                $EFISupport = false;
                $temp_restore_target = array();
                $temp_restore_target['id'] = $vm_restore_target['uuid'];
                $temp_restore_target['name'] = $vm_restore_target['name'];
                $temp_restore_target['parent_id'] = $vm_restore_target['parent_uuid'];
                $temp_restore_target['datastores'] = $vm_restore_target['datastores'];
                $temp_restore_target['groups'] = $this->get_vm_groups( $vm_restore_target['uuid'], $vm_restore_target['name'], $sid);
                $temp_restore_target['capabilities'] = $vm_restore_target['capabilities'];
                if ($temp_restore_target['capabilities'] !== false) {
                    foreach ($temp_restore_target['capabilities'] as $capability => $value) {
                        if ($capability === VirtualClients::SUPPORTS_WIR && $value == true) {
                            $WIRSupport = true;
                        } else if ($capability === VirtualClients::SUPPORTS_EFI && $value == true){
                            $EFISupport = true;
                        }
                    }
                    if ($WIRSupport === true){
                        // if user has specified EFI and EFI is supported
                        if ($efi === 1 && $EFISupport === true) {
                            $vm_targets_array['targets'][] = $temp_restore_target;
                        // if user has not specified EFI then EFI support does not matter
                        } else if ($efi === 0){
                            $vm_targets_array['targets'][] = $temp_restore_target;
                        }
                    }
                }
            }
        }
        return $vm_targets_array;
    }

    private function get_hyperv_targets($os_id, $sid)
    {
        $hv_targets_array = array();
        $efi = isset($_GET['efi']) ? (int)$_GET['efi'] : 0;
        $hyperv_servers_for_wir = $this->BP->get_hyperv_servers_for_wir($os_id, $sid);
        if ($hyperv_servers_for_wir !== false && count($hyperv_servers_for_wir) > 0) {
             foreach ($hyperv_servers_for_wir as $hyperv_server) {
                 $WIRSupport = false;
                 $EFISupport = false;
                 $temp_restore_target = array();
                 $temp_restore_target['id'] = $hyperv_server['client_id'];
                 $temp_restore_target['name'] = $hyperv_server['name'];
                 $temp_restore_target['application'] = $hyperv_server['application'];
                 $hyperv_storage = $this->BP->get_hyperv_storage($hyperv_server['client_id'], $sid);
                 if ($hyperv_storage !== false && count($hyperv_storage) > 0) {
                     $temp_restore_target['datastores'] = $hyperv_storage;
                 }
                 $temp_restore_target['capabilities'] = $hyperv_server['capabilities'];
                 if ($temp_restore_target['capabilities'] !== false) {
                     foreach ($temp_restore_target['capabilities'] as $capability => $value) {
                         if ($capability === VirtualClients::SUPPORTS_WIR && $value == true) {
                            $WIRSupport = true;
                         } else if ($capability === VirtualClients::SUPPORTS_EFI && $value == true) {
                             $EFISupport = true;
                         }
                     }
                     if ($WIRSupport === true){
                         // if user has specified EFI and EFI is supported
                         if ($efi === 1 && $EFISupport === true) {
                             $hv_targets_array['targets'][] = $temp_restore_target;
                             // if user has not specified EFI then EFI support does not matter
                         } else if ($efi === 0){
                             $hv_targets_array['targets'][] = $temp_restore_target;
                         }
                     }
                 }
             }
        }
        return $hv_targets_array;
    }

    private function get_vm_groups($uuid, $name, $sid)
    {
        $groups_info = array();
        $resourcePools = $this->BP->get_resource_pool_info( $uuid, $sid );
        $vapps = $this->BP->get_vApp_info( $uuid, $sid );

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

    private function get_virtual_clients_details($virtualID, $type, $sid)
    {
        $details = array();

        switch ($type) {
            case VirtualClients::WIR_FILTER:
                $details = $this->get_wir_clients_details($virtualID, $sid);
                break;
            case VirtualClients::VMWARE_IR_FILTER:
                $temp_vm_details = $this->get_vm_ir_clients($sid);
                $temp_vm_data = $temp_vm_details;
                foreach ($temp_vm_data as $vmwareDetails) {
                    // handling '<id>.vm_ir'
                    $virtual_id = $this->convertSpecialID($vmwareDetails['virtual_id']);
                    if ($virtual_id === $virtualID) {
                        $details = $vmwareDetails;
                    }
                }
                break;
            case VirtualClients::HYPERV_IR_FILTER:
                $temp_hv_details = $this->get_hv_ir_clients($sid);
                $temp_hv_data = $temp_hv_details;
                foreach ($temp_hv_data as $hvDetails) {
                    // handling '<id>.hv_ir'
                    $virtual_id = $this->convertSpecialID($hvDetails['virtual_id']);
                    if ($virtual_id === $virtualID) {
                        $details = $hvDetails;
                    }
                }
                break;
            case VirtualClients::APPLIANCE_IR_FILTER:
                $temp_appliance_details = $this->get_appliance_ir_clients($sid);
                $temp_appliance_data = $temp_appliance_details;
                foreach ($temp_appliance_data as $applianceDetails) {
                    // handling '<id>.hv_ir'
                    $virtual_id = $this->convertSpecialID($applianceDetails['virtual_id']);
                    if ($virtual_id === $virtualID) {
                        $details = $applianceDetails;
                    }
                }
                break;
        }

        return $details;
    }

    private function get_wir_clients_details($virtualID, $sid)
    {
        $wir_details = array();

        $virtual_client_list = $this->BP->get_virtual_client_list($sid);
        if ($virtual_client_list !== false) {
            foreach ($virtual_client_list as $virtual_client) {
                if ($virtual_client['virtual_id'] === $virtualID) {
                    $client = $this->BP->get_virtual_client($virtualID, $sid);
                    if ($client !== false) {
                        $temp_virtual_client_details = array();
                        // appending '.wir' to the virtual client ID
                        $temp_virtual_client_details['virtual_id'] = $virtualID . ".wir";
                        $temp_virtual_client_details['client_id'] = $client['real_client_id'];
                        if (isset($client['vm_name'])) {
                            $temp_virtual_client_details['vm_name'] = $client['vm_name'];
                        } elseif (isset($virtual_client['client_name'])){
                            $temp_virtual_client_details['vm_name'] = $virtual_client['client_name'];
                        }
                        if (isset($client['processors'])) {
                            $temp_virtual_client_details['processors'] = $client['processors'];
                        }
                        if (isset($client['memory'])) {
                            $temp_virtual_client_details['memory'] = $client['memory'];
                        }
                        $temp_virtual_client_details['included_volumes'] = $client['incl_vols'];
                        if (isset($client['excl_vols'])) {
                            $temp_virtual_client_details['excluded_volumes'] = $client['excl_vols'];
                        }
                        if (isset($client['hypervisor_type'])) {
                            $temp_virtual_client_details['hypervisor_type'] = $client['hypervisor_type'];
                        }
                        if (isset($client['last_message'])) {
                            $temp_virtual_client_details['last_message'] = $client['last_message'];
                        }
                        if (isset($virtual_client['ip_addr'])) {
                            $temp_virtual_client_details['ip_address'] = $virtual_client['ip_addr'];
                        }
                        if (isset($virtual_client['port'])) {
                            $temp_virtual_client_details['port'] = $virtual_client['port'];
                        }
                        if (isset($client['audit_verify'])) {
                            $temp_virtual_client_details['audit_verify'] = $client['audit_verify'];
                        }
                        $wir_details['virtual_client'] = $temp_virtual_client_details;
                    } else {
                        $wir_details = $client;
                    }
                }
            }
        }
        return $wir_details;
    }

    private function get_virtual_clients_backup_history($virtualID, $sid)
    {
        $backup_history = array();

        $result = $this->BP->get_last_virtual_restore($virtualID, $sid);
        if ($result !== false) {
            if (count($result) > 0) {
                foreach ($result as $backup) {
                    $last_backup = $this->displayBackupItem($backup);
                    $backup_history['last'][] = $last_backup;
                }
            }
            $result = $this->BP->get_virtual_restore_backlog($virtualID, $sid);
            if ($result !== false) {
                foreach ($result as $backup) {
                    $pending_backup = $this->displayBackupItem($backup);
                    $backup_history['pending'][] = $pending_backup;
                }
            }
        } else {
            $backup_history = $result;
        }
        return $backup_history;
    }

    private function get_virtual_clients_state($virtualID, $sid)
    {
        $state = $this->BP->get_virtual_client_state($virtualID, $sid);
        return $state;
    }

    private function displayBackupItem($backup)
    {
        $backup_item = array();

        $day = $this->functions->formatDate($backup['start_time']);
        $time = $this->functions->formatTime($backup['start_time']);

        // Check to see if type is set, because if the backup is already purged, it will not be.
        if (isset($backup['type'])) {
            $backupType	= $this->functions->getBackupTypeString($backup['type']);
        } else {
            $backupType = "unknown";
        }

        $backup_item['type'] = $backupType;
        $backup_item['id'] = $backup['id'];
        $backup_item['client_name'] = $backup['cname'];
        $backup_item['date'] = $day;
        $backup_item['time'] = $time;

        if (isset($backup['size'])) {
            $backup_item['size'] = $backup['size'] . ' MB';
        }

        // If the backup has file count, note this.
        // if type is 'verify', show "N/A" rather than 0
        if (isset($backup['files'])) {
            if($backup['type'] == 'verify'){
                $backup_item['files'] =  "N/A";
            } else {
                $backup_item['files'] = $backup['files'];
            }
        }

        // See if backup is currently being restored (for backlog API).
        if (isset($backup['currently_running'])) {
            $backup_item['running'] = $backup['currently_running'] == true ? '1' : '0';
        }
        if (isset($backup['grandclient'])) {
            $backup_item['grandclient'] = $backup['grandclient'];
        }
        if (isset($backup['system_id'])) {
            $backup_item['system_id'] = $backup['system_id'];
        }
        if (isset($backup['system_name'])) {
            $backup_item['system_name'] = $backup['system_name'];
        }

        return $backup_item;
    }

    private function get_duration($createdTime)
    {
        $duration = '';
        $elapsedTime = time() - $createdTime;

        // get the days
        $days = intval(intval($elapsedTime) / (3600*24));
        if($days> 0) {
            $duration .= $days. 'd ';
        }

        // get the hours
        $hours = (intval($elapsedTime) / 3600) % 24;
        if($hours > 0) {
            $duration .= $hours . 'h ';
        }

        // get the minutes
        $minutes = (intval($elapsedTime) / 60) % 60;
        if($minutes > 0) {
            $duration .= $minutes . 'm';
        }

        //less than a minute
        if ( !($days > 0 || $hours > 0 || $minutes > 0)) {
            $duration .= '<1m';
        }
        return $duration;
    }

    public function post($data, $sid)
    {
        $result = array();
        $clientInfo = array();
        if (array_key_exists('client', $data)) {
            $clientData = $data['client'];
            $clientInfo['real_client_id'] = intval($clientData['client_id']);
            $clientInfo['processors'] = intval($clientData['processors']);
            $clientInfo['memory'] = intval($clientData['memory']);
            $clientInfo['efi'] = $clientData['efi'];
            $clientInfo['disks'] = $this->getIDs($clientData['disks']);
            $clientInfo['boot_disk'] = intval($clientData['boot']);
            $clientInfo['incl_vols'] = explode(",", $clientData['include']);
            if (array_key_exists('exclude', $clientData)) {
                $clientInfo['excl_vols'] = explode(",", $clientData['exclude']);
            }
            if (array_key_exists('instances', $clientData)) {
                $clientInfo['instances'] = $this->getIDs($clientData['instances']);
            }
            if (array_key_exists('audit_verify', $clientData)) {
                $clientInfo['audit_verify'] = intval($clientData['audit_verify']);
            }
            if (array_key_exists('hypervisor_info', $clientData)) {
                $clientInfo['hypervisor_info'] = $clientData['hypervisor_info'];
                $hypervisorInfo = $clientData['hypervisor_info'];
                if (array_key_exists('hyperv_host', $hypervisorInfo)) {
                    $clientInfo['hypervisor_info']['hyperv_host'] = intval($hypervisorInfo['hyperv_host']);
                }
            }
            if (array_key_exists('network_info', $clientData)) {
                $clientInfo['network_info'] = $clientData['network_info'];
            }
        }
        $support = $this->BP->virtual_clients_supported($sid);
        if ($support !== -1) {
            if ($support !== false) {
                $virtualID = false;
                if (isset($clientInfo['hypervisor_info'])) {
                    if (array_key_exists('hyperv_host', $clientInfo['hypervisor_info'])) {
                        if (in_array('Hyper-V host', $support)) {
                            $virtualID = $this->BP->save_virtual_client($clientInfo, $sid);
                        } else {
                            $error['error'] = 500;
                            $error['message'] = "Windows Failover Virtualization is not supported on Hyper-V host.";
                        }
                    } elseif (array_key_exists('esx_host', $clientInfo['hypervisor_info'])) {
                        if (in_array('VMware host', $support)) {
                            $virtualID = $this->BP->save_virtual_client($clientInfo, $sid);
                        } else {
                            $error['error'] = 500;
                            $error['message'] = "Windows Failover Virtualization is not supported on VMware host.";
                        }
                    }
                } else {
                    if (in_array('Unitrends appliance', $support)) {
                        $virtualID = $this->BP->save_virtual_client($clientInfo, $sid);
                    } else {
                        $error['error'] = 500;
                        $error['message'] = "Windows Failover Virtualization is not supported on Unitrends appliance.";
                    }
                }
                if ($virtualID !== false) {
                    $result['result'][]['id'] = $virtualID;
                } else {
                    $error['error'] = 500;
                    $error['message'] = $this->BP->getError();
                }
            } else {
                $error['error'] = 500;
                $error['message'] = "Windows Failover Virtualization is not supported.";
            }
        }  else {
            $error['error'] = 500;
            $error['message'] = $this->BP->getError();
        }
        return (isset($error) ? $error : $result);
    }

    private function getIDs($ids)
    {
        $idArray = array();
        $stringsArray = explode(",", $ids);
        $i = 0;
        foreach ($stringsArray as &$id) {
            $idArray[$i++] = intval($id);
        }
        return $idArray;
    }

    public function put($which, $data, $sid)
    {
        $result = array();
        $clientInfo = array();
        if ($which == -1) {
            $result = "You must provide a virtual client ID.";
        } else {
            switch ($which[0]) {
                case 'audit':
                    $bPowerOn = isset($data['powerOn']) ? $data['powerOn'] : true;
                    if (isset($which[1]) && is_string($which[1]) ) {
                        switch($which[1]) {
                            case 'start':
                                if (isset($which[2])) {
                                    $virtualID = $this->convertSpecialID($which[2]);
                                    if ($this->checkValidState($virtualID, $sid)) {
                                        $result = $this->BP->audit_virtual_client($virtualID, true, $bPowerOn);
                                    }
                                }
                                break;
                            case 'stop':
                                if (isset($which[2])) {
                                    $virtualID = $this->convertSpecialID($which[2]);
                                    if ($this->checkValidState($virtualID, $sid)) {
                                        $result = $this->BP->audit_virtual_client($virtualID, false, $bPowerOn);
                                    }
                                }
                                break;
                        }
                    }
                    break;
                case 'live':
                    if (isset($which[1]) && is_string($which[1]) ) {
                        switch($which[1]) {
                            case 'start':
                                if (isset($which[2])) {
                                    $virtualID = $this->convertSpecialID($which[2]);
                                    if ($this->checkValidState($virtualID, $sid)) {
                                        $result = $this->createNetworkBridge($virtualID, $sid);
                                        if ($result !== false) {
                                            $result = $this->BP->run_virtual_client($virtualID, true);
                                        }
                                    }
                                }
                                break;
                            case 'stop':
                                if (isset($which[2])) {
                                    $virtualID = $this->convertSpecialID($which[2]);
                                    if ($this->checkValidState($virtualID, $sid)) {
                                        $result = $this->BP->run_virtual_client($virtualID, false);
                                    }
                                }
                                break;
                        }
                    }
                    break;
                case 'restores':
                    if (isset($which[1]) && is_string($which[1]) ) {
                        switch($which[1]) {
                            case 'start':
                                if (isset($which[2])) {
                                    $virtualID = $this->convertSpecialID($which[2]);
                                    if ($this->isValid($virtualID, $sid)) {
                                        $result = $this->BP->disable_virtual_restores($virtualID, false, $sid);
                                    }
                                }
                                break;
                            case 'stop':
                                if (isset($which[2])) {
                                    $virtualID = $this->convertSpecialID($which[2]);
                                    $result = $this->BP->disable_virtual_restores($virtualID, true, $sid);
                                }
                                break;
                        }
                    }
                    break;
                default:
                    if (array_key_exists('client', $data)) {
                        $clientData = $data['client'];
                        $clientInfo['id'] = $this->convertSpecialID($which[0]);
                        if (array_key_exists('processors', $clientData)) {
                            $clientInfo['processors'] = intval($clientData['processors']);
                        }
                        if (array_key_exists('memory', $clientData)) {
                            $clientInfo['memory'] = intval($clientData['memory']);
                        }
                        if (array_key_exists('audit_verify', $clientData)) {
                            $clientInfo['audit_verify'] = intval($clientData['audit_verify']);
                        }
                        if (array_key_exists('instances', $clientData)) {
                            $clientInfo['instances'] = $this->getIDs($clientData['instances']);
                        }
                        $result = $this->BP->save_virtual_client($clientInfo, $sid);
                    }
                    break;
            }
        }
        return $result;
    }

    private function checkValidState($virtualID, $sid)
    {
        $valid = true;
        $state = $this->get_virtual_clients_state($virtualID, $sid);
        $currentState = $state['current_state'];
        if ($currentState === VirtualClients::STATE_NEW) {
            $valid = false;
        } elseif ($currentState === VirtualClients::STATE_HALTED) {
            $valid = false;
        } else {
            $valid = true;
        }
        return $valid;
    }

    /*
     * Given a virtual ID, see if a virtual bridge will be required to go live: A Recovery Series, where the WIR client is on the appliance.
     * If a bridge is required, and does not exist, create it.
     *
     * Returns true on success: either a bridge isn't needed, or already exists, or is created successfully.
     * Returns false on failure.
     */
    private function createNetworkBridge($virtualID, $sid)
    {
        global $Log;
        $result = true;

        // No need for a bridge on a virtual appliance.
        if ($this->BP->is_virtual($sid)) {
            return $result;
        }

        $bridgeRequired = true;
        $virtualClient = $this->BP->get_virtual_client($virtualID, $sid);
        if ($virtualClient !== false) {
            if (isset($virtualClient['hypervisor_type']) && $virtualClient['hypervisor_type'] != 'Unitrends appliance') {
                $bridgeRequired = false;
            }
        }

        if ($bridgeRequired) {
            $bridgeExists = $this->BP->get_virtual_bridge($sid);
            // true if a bridge exists, false otherwise (not false on error).
            if ($bridgeExists === false) {
                // Get the list of NICs so the bridge can be created.
                $nics = $this->BP->get_network_list($sid);
                if ($nics !== false) {
                    if (count($nics) > 0) {
                        // Put the bridge on the last available NIC with an IP address.
                        for ($i = count($nics) - 1; $i >= 0; $i--) {
                            $networkName = $nics[$i];
                            $networkInfo = $this->BP->get_network_info($networkName, $sid);
                            if ($networkInfo !== false) {
                                if (isset($networkInfo['ip']) && $networkInfo['ip'] !== '') {
                                    $bridgeNic = $networkName;
                                    $Log->writeVariable("Found network to bridge: " . $bridgeNic . " at IP " . $networkInfo['ip']);
                                    break;
                                }
                            }
                        }
                        // Will be set if we found a viable NIC.
                        if (isset($bridgeNic)) {
                            $result = $this->BP->add_virtual_bridge($bridgeNic, $sid);
                        } else {
                            $result = false;
                        }
                    } else {
                        $result = false;
                    }
                } else {
                    $result = false;
                }
            }
        }

        return $result;
    }

    private function isValid($virtualID, $sid)
    {
        $state = $this->get_virtual_clients_state($virtualID, $sid);
        $valid = $state['valid'];
        return $valid;
    }

    public function delete($which, $data, $sid)
    {
        $result = array();
        if ($which == null) {
            $result['error'] = 500;
            $result['message'] = "You must provide a virtual client ID.";
        } else {
            $id = $this->convertSpecialID($which);
            if (array_key_exists('type', $data)) {
                switch ($data['type']) {
                    case 'wir':
                        $delete_from_hypervisor = intval($data['deleteFromHypervisor']);
                        $result = $this->BP->delete_virtual_client($id, $delete_from_hypervisor, $sid);
                        break;
                    case 'vm_ir':
                        if (isset($data['force'])) {
                            $force = intval($data['force']);
                        } else {
                            $vmwareStatus = $this->BP->get_vm_ir_status($sid);
                            if ($vmwareStatus !== false) {
                                foreach ($vmwareStatus as $status) {
                                    if ($status['id'] === $id) {
                                        $audit = isset($status['audit']) ? $status['audit'] : 0;
                                    }
                                }
                            }
                            $force = $audit;
                        }
                        $result = $this->BP->vmware_ir_destroy($id, $force, $sid);
                        break;
                    case 'hv_ir':
                        $hypervStatus = $this->BP->get_hyperv_ir_status($sid);
                        if($hypervStatus !== false) {
                            foreach($hypervStatus as $status) {
                                if($status['id'] === $id) {
                                    $audit = isset($status['audit']) ? $status['audit'] : 0;
                                }
                            }
                        }
                        $force = $audit;
                        $result = $this->BP->hyperv_ir_destroy($id, $force, $sid);
                        break;
                    case 'appliance_ir':
                        $qemuStatus = $this->BP->get_qemu_ir_status($sid);
                        if($qemuStatus !== false) {
                            foreach($qemuStatus as $status) {
                                if($status['id'] === $id) {
                                    $audit = isset($status['audit']) ? $status['audit'] : 0;
                                }
                            }
                        }
                        $force = $audit;
                        $result = $this->BP->qemu_ir_destroy($id, $force, $sid);
                        break;
                }
            } else {
                $result['error'] = 500;
                $result['message'] = "Specify the type.";
            }
        }
        return $result;
    }

    // Converting '<id>.wir', '<id>.vm_ir' and '<id>.hv_ir' to <id>
    private function convertSpecialID($val)
    {
        $specialID = explode(".", $val);
        if (count($specialID) > 1) {
            $id = intval($val);
        } else {
            $id = intval($val);
        }
        return $id;
    }
}
?>
