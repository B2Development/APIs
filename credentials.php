<?php

require_once('./includes/clients.php');

class Credentials
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;
    }

    public function get($which, $credId, $data, $sid, $systems) {
        $allCreds = array();

        if((is_numeric($which) and $which == -1) or $which == "") {
            if($systems === null or $systems === "" ){
                $systems = $this->BP->get_system_list();
            }
            foreach($systems as $sysid=>$sname) {
                $creds = $this->BP->get_credentials_list($sysid);
                if($creds !== false){
                    for($i = 0; $i < count($creds); $i++) {
                        if (isset($creds[$i]['self-service']) && $creds[$i]['self-service'] !== null) {
                            // javascript isn't accepting a '-' in the array name so, changing it to '_'
                            $creds[$i]['self_service'] = $creds[$i]['self-service'];
                            unset($creds[$i]['self-service']);
                        }
                        $creds[$i]['sid'] = $sysid;
                        $creds[$i]['system_name'] = $sname;
                    }
                    $allCreds = array_merge($allCreds, $creds);
                }

            }
        } else if(is_numeric($which) and $which != -1) {
            $allCreds = $this->BP->get_credentials($which, $sid);
        } else {
            switch($which) {
                case 'default':
                    foreach($systems as $sysid=>$sname) {
                        $creds[] = $this->BP->get_default_credentials($sysid);
                        for($i = 0; $i < count($creds); $i++) {
                            $creds[$i]['sid'] = $sysid;
                            $creds[$i]['system_name'] = $sname;
                        }
                        $allCreds = array_merge($allCreds, $creds);
                    }
                    break;
                case 'named':
                    foreach($systems as $sysid=>$sname) {
                        $creds = $this->BP->get_named_credentials($sysid);
                        for($i = 0; $i < count($creds); $i++) {
                            $creds[$i]['sid'] = $sysid;
                            $creds[$i]['system_name'] = $sname;
                        }
                        $allCreds = array_merge($allCreds, $creds);
                    }
                    break;
                case 'application':
                    $clientID = isset($_GET['cid']) ? (int)$_GET['cid'] : false;
                    $appID = isset($_GET['aid']) ? (int)$_GET['aid'] : false;
                    if($clientID === false || $appID === false) {
                        $allCreds['error'] = 406;
                        $allCreds['message'] = "Both the client id and the app id must be specified";
                    } else {
                        $allCreds = $this->BP->get_client_app_credentials($clientID, $appID, $sid);
                    }
                    break;
                case 'vmware':
                    $uuid = isset($_GET['uuid']) ? $_GET['uuid'] : false;
                    if($uuid === false) {
                        $allCreds['error'] = 406;
                        $allCreds['message'] = "vCenter/ESX uuid must be specified";
                    } else {
                        $allCreds = $this->BP->get_vmware_credentials($uuid);
                    }
                    break;
                case 'full':
                    if(!is_numeric($credId)) {
                        $allCreds['error'] = 406;
                        $allCreds['message'] = "Credential id must be specificed";
                    } else {
                        $allCreds = $this->BP->get_credentials_for_rdr($credId);
                    }
                    break;
            }
        }
        if (is_array($allCreds) and !isset($allCreds['error'])) {
            $allCreds = array("data" => $allCreds);
        }
        return $allCreds;
    }

    public function save_credential($data, $sid) {
        if($sid === false) {
            if(isset($data['systemID'])) {
                $dpuID = $data;
                unset($data['systemID']);
            } else {
                $dpuID = $this->BP->get_local_system_id();
            }
        } else {
            $dpuID = $sid;
        }
        if (isset($data['target_name'])) {
            return $this->BP->save_target_credentials($data['target_name'], $data, $dpuID);
        } else {
            return $this->BP->save_credentials($data, $dpuID);
        }
    }

    public function modify_credential($which, $data, $sid) {
        $status = false;

        if(is_numeric($which) and $which != -1) {
            if($sid === false) {
                if(isset($data['systemID'])) {
                    $dpuID = $data;
                    unset($data['systemID']);
                } else {
                    $dpuID = $this->BP->get_local_system_id();
                }
            } else {
                $dpuID = $sid;
            }
            $data['credential_id'] = $which;
            if(!isset($data['is_default'])) {
                $data['is_default'] = false;
            }
            if(!isset($data['domain']) or !isset($data['display_name'])) {
                $credentialInfo = $this->BP->get_credentials($which, $dpuID);
                if(!isset($data['domain']) and isset($credentialInfo['domain'])) {
                    $data['domain'] = $credentialInfo['domain'];
                }
                if(!isset($data['display_name']) and isset($credentialInfo['display_name'])) {
                    $data['display_name'] = $credentialInfo['display_name'];
                }
            }
            return $this->BP->save_credentials($data, $dpuID);
        }

        return $status;
    }

    public function delete_credential($which, $sid) {
        if($which) {
            if(is_numeric($which) and $which > 0) {
                if($sid === false) {
                    $dpuID = $this->BP->get_local_system_id();
                } else {
                    $dpuID = $sid;
                }
                return $this->BP->delete_credentials($which, $dpuID);
            }
        }
        return false;
    }

    public function bind_credential($which, $data, $sid) {
        $status = false;
        if($which[0]) {
            if($sid === false) {
                $dpuID = $this->BP->get_local_system_id();
            } else {
                $dpuID = $sid;
            }

            switch($which[0]) {
                case 'instance':
                    // UNIBP-9683 protected_assets.php put_assets is now the preferred method for saving app_aware and credentials for VMware
                    if(isset($data['instance_ids'])) {
                        if(isset($which[1]) and is_numeric($which[1])) {
                            $instances = explode(',', $data['instance_ids']);
                            $info = array();
                            $app_aware_set = isset( $data['app_aware'] );
                            foreach($instances as $instance) {
                                if ( $app_aware_set ) {
                                    if ($which[1] === "-1") {
                                        $info[] = array('instance_id' => (int)$instance, "no_credentials" => "true", "app_aware" => $data['app_aware']);
                                    } else {
                                        $info[] = array('instance_id' => (int)$instance, "credential_id" => $which[1], "app_aware" => $data['app_aware']);
                                    }
                                } else  {
                                    if ($which[1] === "-1") {
                                        $info[] = array('instance_id' => (int)$instance, "no_credentials" => "true");
                                    } else {
                                        $info[] = array('instance_id' => (int)$instance, "credential_id" => $which[1]);
                                    }
                                }

                            }
                            $status = $this->BP->save_app_credentials_info($info, $dpuID);
                        } else {
                            $status = array();
                            $status['error'] = 500;
                            $status['message'] = "A credential id is required";
                        }
                    } else {
                        $status = array();
                        $status['error'] = 500;
                        $status['message'] = "At least one instance id is required";
                    }
                    break;
                case 'client':
                    if(isset($data['clients']) and count($data['clients']) > 0) {
                        if(isset($which[1]) and is_numeric($which[1])) {
                            $messages = array();
                            foreach($data['clients'] as $clientInfo) {
                                if(isset($clientInfo['appID'])) {
                                    $status = $this->BP->save_client_app_credentials((int)$clientInfo['clientID'], (int)$clientInfo['appID'], (int)$which[1], $dpuID);
                                    if($status == false) {
                                        $messages[] = "Unable to associate credential " . $clientInfo['credential_display'] . " with application " . $clientInfo['name'] . " because: " . $this->BP->getError() . "|";
                                    }
                                } else {
                                    $client = new Clients($this->BP);
                                    $credentialInfo['credential_id'] = (int)$which[1];
                                    $status = $client->put((int)$clientInfo['clientID'], $credentialInfo, $sid);
                                    if($status == false) {
                                        $messages[] = "Unable to associate credential " . $clientInfo['credential_display'] . " with asset " . $clientInfo['name'] . " because: " . $this->BP->getError() . "|";
                                    }
                                }
                            }
                            if(count($messages) > 0) {
                                $status['error'] = 500;
                                $status['messages'] = $messages;
                            }
                        } else {
                            $status = array();
                            $status['error'] = 500;
                            $status['message'] = "A credential id is required";
                        }
                    } else {
                        $status = array();
                        $status['error'] = 500;
                        $status['message'] = "Clients must be specified for association";
                    }
                    break;
                case 'psa':
                    if(isset($which[1]) and is_numeric($which[1])) {
                        if(isset($data['psa_id'])) {
                            $status = $this->BP->save_psa_credentials((int)$data['psa_id'], (int)$which[1], $dpuID);
                        } else {
                            $status = array();
                            $status['error'] = 500;
                            $status['message'] = "PSA id is required and must be a number";
                        }
                    } else {
                        $status = array();
                        $status['error'] = 500;
                        $status['message'] = "Credential id is required and must be a number";
                    }
                    break;
            }
        }
        return $status;
    }

    public function unbind_credential($which, $data, $sid) {
        $status = false;
        if($which[0]) {
            if($sid === false) {
                $dpuID = $this->BP->get_local_system_id();
            } else {
                $dpuID = $sid;
            }

            switch($which[0]) {
                case 'instance':
                    if(isset($data['instance_ids'])) {
                        $instances = explode(',', $data['instance_ids']);
                        $info = array();
                        foreach($instances as $instance) {
                            $info[] = array('instance_id' => (int)$instance, "no_credentials" => true);
                        }
                        $status = $this->BP->save_app_credentials_info($info, $dpuID);
                    } else {
                        $status = array();
                        $status['error'] = 500;
                        $status['message'] = "At least one instance id is required";
                    }
                    break;
                case 'client':
                    if(isset($data['clients']) and count($data['clients']) > 0) {
                        $messages = array();
                        foreach($data['clients'] as $clientInfo) {
                            if(isset($clientInfo['clientID']) and isset($clientInfo['appID'])) {
                                $status = $this->BP->delete_client_app_credentials($clientInfo['clientID'], $clientInfo['appID'], $dpuID);
                                if($status == false) {
                                    $messages[] = "Unable to unbind credential from client " . $clientInfo['clientID'] . " and application " . $clientInfo['appID'] . " because: " . $this->BP->getError();
                                }
                            } else if(isset($clientInfo['clientID']) and !isset($clientInfo['appID'])) {
                                $client = new Clients($this->BP);
                                $credentialInfo['credential_id'] = -1;
                                $status = $client->put((int)$clientInfo['clientID'], $credentialInfo, $sid);
                                if($status == false) {
                                    $messages[] = "Unable to unbind credential from client " . $clientInfo['clientID'] . " because: " . $this->BP->getError();
                                }
                            }
                        }
                        if(count($messages) > 0) {
                            $status['messages'] = $messages;
                        }
                    } else {
                        $status = array();
                        $status['error'] = 500;
                        $status['message'] = "No clients specified";
                    }
                    break;
                case 'psa':
                    $status = array();
                    $status['error'] = 501;
                    break;
                case 'target':
                    if(isset($data['target_name'])) {
                        $status = $this->BP->delete_target_credentials($data['target_name'], $dpuID);
                    } else {
                        $status = array();
                        $status['error'] = 500;
                        $status['message'] = "One target_name is required";
                    }
                    break;
            }
        }
        return $status;
    }

}
?>
