<?php

class Encryption
{
    private $BP;

    const OFF          = "off";
    const PERSIST      = "persist";
    const ON           = "on";

    public function __construct($BP)
    {
        $this->BP = $BP;
    }

    public function get($which, $systems)
    {
        $encryption = array();

        if($which == -1) {
            $encryption = $this->get_encryption($systems);
        } else {
            if ($which === "support") {
                foreach ($systems as $systemID => $systemName) {
                    $cryptSupport = $this->BP->encryption_supported($systemID);
                    if($cryptSupport !== -1) {
                        $temp_support = array();
                        $temp_support['system_id'] = $systemID;
                        $temp_support['system_name'] = $systemName;
                        $temp_support['EncryptionSupported'] = $cryptSupport === true ? "1" : "0";

                        $encryption['data'][] = $temp_support;
                    }
                }
            }
        }

        return $encryption;
    }

    private function get_encryption($systems)
    {
        $cryptInfo = array();
        foreach ($systems as $systemID => $systemName) {
            //first make sure the crypto daemon is running
            $this->BP->save_crypt_info(array('active' => true), $systemID);
            $info = $this->BP->get_crypt_info($systemID);
            if($info !== false) {
                $temp_encryption = array();
                $temp_encryption['system_id'] = $systemID;
                $temp_encryption['system_name'] = $systemName;
                if($info['active'] && $info['enabled'] && !$info['persistent']) {
                    $temp_encryption['state'] = Encryption::ON;
                } elseif($info['active'] && $info['enabled'] && $info['persistent']) {
                    $temp_encryption['state'] = Encryption::PERSIST;
                } elseif(!$info['enabled']) {
                    $temp_encryption['state'] = Encryption::OFF;
                }
                $temp_encryption['has_passphrase'] = $info['passphrase_set'];

                $cryptInfo['data'][] = $temp_encryption;
            }
            else{
                $cryptInfo['error'] = 500;
                $cryptInfo['message'] = $this->BP->getError();;
            }
        }
        return $cryptInfo;
    }

    public function put($which, $data, $sid)
    {
        $result = array();
        if (is_string($which[0])) {
            switch ($which[0]) {
		case 'enable-instance':
            $defaultUI = $this->BP->get_default_ui($sid);
            if($defaultUI === "satori"){
                if(array_key_exists('instance_id', $data)) {
                    $iid = $data['instance_id'];
                    $result = $this->BP->save_instance_crypt_setting($iid, true, $sid);
                } else {
                    $result['error'] = 500;
                    $result['message'] = "Instance-id list must be specified";
                }
            }
            else {
                $result['error'] = 500;
                $result['message'] = "Encryption cannot be configured while the default interface is set to legacy. Please use the legacy interface or change your default.";
            }
            break;
		case 'disable-instance':
                    if(array_key_exists('instance_id', $data)) {
		         $iid = $data['instance_id'];
                         $result = $this->BP->save_instance_crypt_setting($iid, false, $sid);
                    } else {
                         $result['error'] = 500;
                         $result['message'] = "Instance-id list must be specified";
                    }
                    break;
                case 'encrypt-application':
                    if(array_key_exists('client_id', $data) and array_key_exists('application_id', $data)) {
                        $client_id = $data['client_id'];
                        $application_id = $data['application_id'];
                        $encrypt_boolean = isset($data['encrypt']) ? $data['encrypt'] : true;
                        $result = $this->encryptAllAssetsForClient($client_id, $sid, $encrypt_boolean, $application_id);
                    } else {
                        $result['error'] = 500;
                        $result['message'] = "Both client_id and application_id must be specified";
                    }
                    break;
                case 'enable':
                    $currentState = $this->getEncryptionState($sid);
                    if($currentState !== "") {
                        if(array_key_exists('passphrase', $data)) {
                            $cryptInfo = array('active' => true,
                                                'current_passphrase' => $data['passphrase']);
                            $result = $this->BP->save_crypt_info($cryptInfo, $sid);
                        } else {
                            $result['error'] = 500;
                            $result['message'] = "Passphrase must be specified";
                        }
                    }
                    break;
                case 'disable':
                    $cryptInfo = array('active' => false);
                    $result = $this->BP->save_crypt_info($cryptInfo, $sid);
                    break;
                case 'persistent':
                    $currentState = $this->getEncryptionState($sid);
                    if($currentState !== "") {
                        if (array_key_exists('passphrase', $data)) {
                            $cryptInfo = array('persistent' => true,
                                'current_passphrase' => $data['passphrase']);
                            $result = $this->BP->save_crypt_info($cryptInfo, $sid);
                        } else {
                            $result['error'] = 500;
                            $result['message'] = "Passphrase must be specified";
                       }
                    }
                    break;
                case 'not-persistent':
                    $cryptInfo = array('persistent' => false);
                    $result = $this->BP->save_crypt_info($cryptInfo, $sid);
                    break;
                case 'passphrase':
                    if (array_key_exists('current_passphrase', $data) && array_key_exists('new_passphrase', $data)) {
                        $cryptInfo = array('current_passphrase' => $data['current_passphrase'],
                            'new_passphrase' => $data['new_passphrase']);
                        $result = $this->BP->save_crypt_info($cryptInfo, $sid);
                    }
                    break;
            }
        }
        return $result;
    }

    private function getEncryptionState($sid)
    {
        $state = "";
        $cryptInfo = $this->BP->get_crypt_info($sid);
        if($cryptInfo !== false) {
            if($cryptInfo['active'] && $cryptInfo['enabled'] && !$cryptInfo['persistent']) {
                $state = Encryption::ON;
            } elseif($cryptInfo['active'] && $cryptInfo['enabled'] && $cryptInfo['persistent']) {
                $state = Encryption::PERSIST;
            } elseif(!$cryptInfo['enabled']) {
                $state = Encryption::OFF;
            }
        }
        return $state;
    }

    public function post($which, $data, $sid)
    {
        $result = array();
        switch($which) {
            case 'keys':
                $result = $this->BP->backup_crypt_keyfile();
                if($result !== false) {
                    $result = array("data" => array("message" => $result));
                }
                break;
            case 'passphrase':
                if (array_key_exists('new_passphrase', $data)) {
                    $cryptInfo = array('new_passphrase' => $data['new_passphrase']);
                    $result = $this->BP->save_crypt_info($cryptInfo, $sid);
                }
                break;
        }
        return $result;
    }

    // Set Encryption for all of the Assets for a given client (does not encrypt agent-level)
    // $specified_application_id is given to only set one of the applications for a specific client
    // $encryptBoolean is used to decide whether to set or unset encryption
    // Do not use for Oracle, SharePoint, or VMware
    // Future: include better error handling
    public function encryptAllAssetsForClient( $client_id, $system_id, $encryptBoolean = true, $specified_application_id = null )
    {
        $status = false;
        $application_ids_array = array();

        if ( $specified_application_id !== null )
        {
            $application_ids_array[] = $specified_application_id;
        }
        else
        {
            $clientInfo = $this->BP->get_client_info($client_id, $system_id);
            if ( $clientInfo !== false )
            {
                foreach ( $clientInfo['applications'] as $applicationID => $applicationArray )
                {
                    $application_ids_array[] = $applicationID;
                }
            }

        }

        if ( count( $application_ids_array ) > 0 )
        {
            $instance_ids_csv = '';
            foreach ( $application_ids_array as $application_id )
            {
                switch ($application_id)
                {
                    case Constants::APPLICATION_ID_FILE_LEVEL:
                        break;  //Do nothing for File-Level
                    case Constants::APPLICATION_ID_BLOCK_LEVEL:
                        $blockInstance = $this->BP->get_block_info($client_id, true, $system_id);
                        if ($blockInstance !== false) {
                            $instance_ids_csv .= $blockInstance['instance_id'].',';
                        }
                        break;
                    case Constants::APPLICATION_ID_EXCHANGE_2003:
                    case Constants::APPLICATION_ID_EXCHANGE_2007:
                    case Constants::APPLICATION_ID_EXCHANGE_2010:
                    case Constants::APPLICATION_ID_EXCHANGE_2013:
                    case Constants::APPLICATION_ID_EXCHANGE_2016:
                        $exchangeInfo = $this->BP->get_exchange_info($client_id, true, $system_id);
                        if ( $exchangeInfo !== false )
                        {
                            foreach ($exchangeInfo as $exchangeInstance)
                            {
                                $instance_ids_csv .= $exchangeInstance['instance_id'].',';
                            }
                        }
                        break;
                    case Constants::APPLICATION_ID_SQL_SERVER_2005:
                    case Constants::APPLICATION_ID_SQL_SERVER_2008:
                    case Constants::APPLICATION_ID_SQL_SERVER_2008_R2:
                    case Constants::APPLICATION_ID_SQL_SERVER_2012:
                    case Constants::APPLICATION_ID_SQL_SERVER_2014:
                    case Constants::APPLICATION_ID_SQL_SERVER_2016:
                    case Constants::APPLICATION_ID_SQL_SERVER_2017:
                        $sqlInfo = $this->BP->get_sql_info($client_id, $application_id, true, $system_id);
                        if ( $sqlInfo !== false )
                        {
                            foreach ($sqlInfo as $sqlInstance)
                            {
                                $instance_ids_csv .= $sqlInstance['instance_id'].',';
                            }
                        }
                        break;
                    case Constants::APPLICATION_ID_VMWARE:
                        // Do not use this function to set VMware
                        break;
                    case Constants::APPLICATION_ID_HYPER_V_2008_R2:
                    case Constants::APPLICATION_ID_HYPER_V_2012:
                    case Constants::APPLICATION_ID_HYPER_V_2016:
                        $hyperVInfo = $this->BP->get_hyperv_info($client_id, true, $system_id);
                        if ( $hyperVInfo !== false )
                        {
                            foreach ($hyperVInfo as $hyperVInstance)
                            {
                                $instance_ids_csv .= $hyperVInstance['instance_id'].',';
                            }
                        }
                        break;

                    case Constants::APPLICATION_ID_ORACLE_10:
                    case Constants::APPLICATION_ID_ORACLE_11:
                    case Constants::APPLICATION_ID_ORACLE_12:
                    case Constants::APPLICATION_ID_SHAREPOINT_2007:
                    case Constants::APPLICATION_ID_SHAREPOINT_2010:
                    case Constants::APPLICATION_ID_SHAREPOINT_2013:
                    case Constants::APPLICATION_ID_SHAREPOINT_2016:
                        //SharePoint and Oracle do not currently support encryption
                        break;
                    case Constants::APPLICATION_ID_UCS_SERVICE_PROFILE:
                        $ciscoUCSInfo = $this->BP->get_ucssp_info($client_id, $application_id, true, $system_id);
                        if ( $ciscoUCSInfo !== false )
                        {
                            foreach ($ciscoUCSInfo as $ciscoUCSInstance)
                            {
                                $instance_ids_csv .= $ciscoUCSInstance['instance_id'].',';
                            }
                        }
                        break;
                    case Constants::APPLICATION_ID_VOLUME: //NDMP
                        $NDMPVolumeInfo = $this->BP->get_ndmpvolume_info($client_id, true, $system_id);
                        if ( $NDMPVolumeInfo !== false )
                        {
                            foreach ($NDMPVolumeInfo as $NDMPVolume)
                            {
                                $instance_ids_csv .= $NDMPVolume['instance_id'].',';
                            }
                        }
                        break;
                    case Constants::APPLICATION_ID_XEN:
                        $xenInfo = $this->BP->get_xen_vm_info($client_id, true, false, $system_id);
                        if ( $xenInfo !== false )
                        {
                            foreach ($xenInfo as $xenInstance)
                            {
                                $instance_ids_csv .= $xenInstance['instance_id'].',';
                            }
                        }
                        break;
                    case Constants::APPLICATION_ID_AHV:
                        $ahvInfo = $this->BP->get_ahv_vm_info($client_id, true, false, $system_id);
                        if ( $ahvInfo !== false )
                        {
                            foreach ($ahvInfo as $vm)
                            {
                                $instance_ids_csv .= $vm['instance_id'].',';
                            }
                        }
                        break;
                }
            }
            if ( $instance_ids_csv !== '' )
            {
                $instance_ids_csv = rtrim($instance_ids_csv, ",");
                $status = $this->BP->save_instance_crypt_setting($instance_ids_csv, $encryptBoolean, $system_id);

            }
        }
        return $status;
    }



}

?>
