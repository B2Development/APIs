<?php

class PSA
{
    private $BP;

    const HTTP = 'http://';
    const HTTPS = 'https://';

    public function __construct($BP)
    {
        $this->BP = $BP;

        require_once('./includes/credentials.php');
        $this->credentials = new Credentials($this->BP);

        require_once('./includes/function.lib.php');
        $this->functions = new Functions($this->BP);
    }

    public function get($which, $sid)
    {
        $checkSeverity = false;
        $severity = -1;
        $sid = ($sid !== false) ? $sid : $this->BP->get_local_system_id();

        if (is_string($which[0])) {
            switch ($which[0]) {
                case 'tools':
                    $psaInfo = $this->get_psa_tools($sid);
                    break;
                case 'config':
                    $psaInfo = $this->get_psa_config($sid);
                    break;
                case 'tickets':
                    $psaInfo = $this->get_psa_ticket_history($sid);
                    break;
                default:
                    return false;
            }
        } else if (is_numeric($which) and $which < 0) {
            $psaInfo = $this->BP->get_psa_history($sid);
        } else {
            // I don't believe we'll ever reach this else, but added just in case.
            return false;
        }
        $allPsa = array();
        //if error exists, return the error
        if (array_key_exists('error', $psaInfo)) {
           $allPsa = $psaInfo;
        } else {
            if (empty($psaInfo)) {
                $allPsa['data'] = $psaInfo;
            } else {
                foreach ($psaInfo as $info) {
                    if ($checkSeverity and $severity != $info['severity']) {
                        continue;
                    }
                    if ($which) {
                        $allPsa['data'][] = $info;
                    }
                }
            }
        }
        return($allPsa);
    }

    public function post($which, $data, $sid)
    {
        $result = array();
        $sid = ($sid !== false) ? $sid : $this->BP->get_local_system_id();

        if (is_string($which[0])) {
            switch ($which[0]) {
                case 'config':
                    $result = $this->post_psa_config($data, $sid);
                    break;
            }
        }
        return $result;
    }

    public function put($which, $data, $sid)
    {
        $result = array();
        $sid = ($sid !== false) ? $sid : $this->BP->get_local_system_id();

        if (is_string($which[0])) {
            switch ($which[0]) {
                case 'config':
                    if (isset($which[1]) && $which[1] != -1) {
                        $config_id = (int)$which[1];
                        $result = $this->put_psa_config($config_id, $data, $sid);
                    } else {
                        $result['error'] = 500;
                        $result['message'] = "PSA Configuration ID is required.";
                    }
                    break;
                case 'test_ticket':
                    if (isset($which[1]) && $which[1] != -1) {
                        $config_id = (int)$which[1];
                        $send_ticket = $this->BP->send_test_psa_ticket($config_id, $sid);
                        if ($send_ticket !== false) {
                            // replacing the carriage returns
                            $send_ticket = str_replace("\n",'', $send_ticket);
                            $send_ticket = str_replace("\t", '', $send_ticket);
                            $send_ticket = str_replace("\r", '', $send_ticket);
                            $result['ticket_id'] = $send_ticket;
                        } else {
                            $result = $send_ticket;
                        }
                    } else {
                        $result['error'] = 500;
                        $result['message'] = "PSA Configuration ID is required.";
                    }
                    break;

            }
        }
        return $result;
    }

    public function delete($which, $sid)
    {
        $result = array();
        $sid = ($sid !== false) ? $sid : $this->BP->get_local_system_id();

        if (is_string($which[0])) {
            switch ($which[0]) {
                case 'config':
                    if (isset($which[1]) && $which[1] != -1) {
                        $config_id = (int)$which[1];
                        $result = $this->BP->delete_psa_config($config_id, $sid);
                    } else {
                        $result['error'] = 500;
                        $result['message'] = "PSA Configuration ID is required.";
                    }
                    break;
            }
        }

        return $result;
    }

    private function get_psa_tools($sid)
    {
        $data = array();
        $psaTools = $this->BP->get_psa_tools($sid);
        if ($psaTools !== false && !empty($psaTools)) {
            foreach ($psaTools as $tools) {
                $tool_data = array();
                if (array_key_exists('psa_tool_id', $tools)) {
                    $tool_data['id'] =  $tools['psa_tool_id'];
                }
                if (array_key_exists('psa_tool_name', $tools)) {
                    $tool_data['tool_name'] = $tools['psa_tool_name'];
                }
                // getting the tool's configuration
                $psaConfigInfo = $this->BP->get_psa_config($sid);
                if ($psaConfigInfo !== false && !empty($psaConfigInfo)) {
                    foreach($psaConfigInfo as $configInfo) {
                        $configData = array();
                        if ($tool_data['id'] === $configInfo['psa_tool']['psa_tool_id']) {
                            if (isset($configInfo['id'])) {
                                $configData['config_id'] = $configInfo['id'];
                            }
                            if (array_key_exists('url', $configInfo)) {
                                $configData['url'] = $configInfo['url'];
                            }
                            if (array_key_exists('company_id', $configInfo)) {
                                $configData['company_id'] = $configInfo['company_id'];
                            }
                            if (array_key_exists('is_default', $configInfo)) {
                                $configData['is_default_tool'] = $configInfo['is_default'];
                            }
                            if (array_key_exists('credentials', $configInfo) ) {
                                $configData['credentials_name'] = $configInfo['credentials']['display_name'];
                                $configData['credentials_id'] = $configInfo['credentials']['credential_id'];
                            }
                        }
                        $tool_data['config'] = $configData;
                    }
                }
                $data[] = $tool_data;
            }
        } else if (empty($psaTools)) {
            $data['error'] = 500;
            $data['message'] = "PSA tools are not supported.";
        } else {
            $data['error'] = 500;
            $data['message'] = $this->BP->getError();
        }
        return $data;
    }

    private function get_psa_config($sid)
    {
        $data = array();
        $psaConfig = $this->BP->get_psa_config($sid);
        if ($psaConfig !== false) {
            foreach($psaConfig as $config) {
                $config_data = array();
                if (array_key_exists('psa_tool', $config)) {
                    $config_data['psa_tool'] = $config['psa_tool'];
                    if (isset($config['id'])) {
                        $config_data['id'] = $config['id'];
                    }
                    if (array_key_exists('url', $config)) {
                        $config_data['url'] = $config['url'];
                    }
                    if (array_key_exists('company_id', $config)) {
                        $config_data['company_id'] = $config['company_id'];
                    }
                    if (array_key_exists('is_default', $config)) {
                        $config_data['is_default_tool'] = $config['is_default'];
                    }
                    if (array_key_exists('credentials', $config) ) {
                        $config_data['credentials'] = $config['credentials'];
                    }
                }
                $data[] = $config_data;
            }
        } else {
            $data['error'] = 500;
            $data['message'] = $this->BP->getError();
        }
        return $data;
    }

    private function get_psa_ticket_history($sid)
    {
        $data = array();
        $psaHistory = $this->BP->get_psa_history($sid);
        if ($psaHistory !== false && !empty($psaHistory)) {
            foreach ($psaHistory as $history) {
                $history_data = array();
                $history_data['id'] = $history['ticket_id'];
                $history_data['tool_name'] = $history['tool_name'];
                $history_data['time_sent'] = $this->functions->formatDateTime(strtotime($history['time_sent']));

                $severity = '';
                switch ($history['severity']) {
                    case 1:
                        $severity = 'critical';
                        break;
                    case 2:
                        $severity = 'warning';
                        break;
                    case 3:
                        $severity = 'notice';
                        break;
                }
                $history_data['severity'] = $severity;
                $history_data['description'] = $history['description'];

                $data[] = $history_data;
            }
        } else if (empty($psaHistory)){
            $data = array();
        } else {
            $data['error'] = 500;
            $data['message'] = $this->BP->getError();
        }
        return $data;
    }

    private function post_psa_config($data, $sid)
    {
        $result = false;
        $modify = false;
        $psaConfig = $this->get_config_array($data);

        // if credential ID is present the existing credential is associated otherwise, a new credential is created
        if (isset($data['credential_id']) && $data['credential_id'] != -1) {
            $psaConfig['credential_id'] = $data['credential_id'];
            if (array_key_exists('error', $psaConfig)) {
                return $psaConfig;
            }
        } else {
            $creds = $this->get_creds_array($data, $modify);
            if (array_key_exists('error', $creds)) {
               return $creds;
            } else {
                $credID = $this->credentials->save_credential($creds, $sid);
                if ($credID != false) {
                    $psaConfig['credential_id'] = $credID;
                } else {
                    $error['error'] = 500;
                    $error['message'] = $this->BP->getError();
                    return $error;
                }
            }
        }

        $result = $this->BP->save_psa_config($psaConfig, $sid);
        if ($result == false) {
            $result['error'] = 500;
            $result['message'] = $this->BP->getError();
        }

        return $result;
    }

    private function put_psa_config($config_id, $data, $sid)
    {
        $result = false;
        $modify = true;
        $psaConfig = $this->get_config_array($data);

        $psaConfig['id'] = $config_id;
        // if credential ID is present the existing credential is associated; if credential details are provided, new ones are created; otherwise the existing ones are used without any change
        if (isset($data['credential_id']) && $data['credential_id'] != -1) {
            $psaConfig['credential_id'] = $data['credential_id'];
            if (array_key_exists('error', $psaConfig)) {
                return $psaConfig;
            }
        } else if (array_key_exists('credentials', $data)) {
            $creds = $this->get_creds_array($data, $modify);
            if (array_key_exists('error', $creds)) {
                return $creds;
            } else {
                $credID = $this->credentials->save_credential($creds, $sid);
                if ($credID != false) {
                    $psaConfig['credential_id'] = $credID;
                } else {
                    $error['error'] = 500;
                    $error['message'] = $this->BP->getError();
                    return $error;
                }
            }
        } else {
            $psaConfig['credential_id'] = $this->get_credential_id($config_id, $sid);
        }

        $result = $this->BP->save_psa_config($psaConfig, $sid);
        if ($result == false) {
            $result['error'] = 500;
            $result['message'] = $this->BP->getError();
        }
        return $result;
    }

    private function get_config_array($data)
    {
        $psaConfig = array();
        if (isset($data['psa_tool_id'])) {
            $psaConfig['psa_tool_id'] = $data['psa_tool_id'];
        } else {
            $error['error'] = 500;
            $error['message'] = "PSA Tool ID is required.";
            return $error;
        }
        if (isset($data['url']) && $data['url'] != "") {
            // if url contains 'http://', 'HTTP://', 'https://' or 'HTTPS://' , trim that
            if (strpos($data['url'], PSA::HTTP) === 0 || strpos($data['url'], strtoupper(PSA::HTTP)) === 0) {
                $data['url'] = substr_replace($data['url'], "", 0, strlen(PSA::HTTP));
            } elseif (strpos($data['url'], PSA::HTTPS) === 0 || strpos($data['url'], strtoupper(PSA::HTTPS)) === 0) {
                $data['url'] = substr_replace($data['url'], "", 0, strlen(PSA::HTTPS));
            }

            $psaConfig['url'] = $data['url'];
        } else {
            $error['error'] = 500;
            $error['message'] = "URL is required.";
            return $error;
        }
        if (isset($data['company_id']) && $data['company_id'] != "") {
            $psaConfig['company_id'] = $data['company_id'];
        } else {
            $error['error'] = 500;
            $error['message'] = "Company ID is required.";
            return $error;
        }
        $psaConfig['is_default'] = isset($data['is_default_tool']) ? $data['is_default_tool'] : false;

        return $psaConfig;
    }

    private function get_creds_array($data, $modify)
    {
        $creds = array();
        if (isset($data['credentials']) && !empty($data['credentials'])) {
            $cred_data = $data['credentials'];
            if (isset($cred_data['username']) && $cred_data['username'] != "") {
                $creds['username'] = $cred_data['username'];
            } else {
                $error['error'] = 500;
                $error['message'] = "Username is required.";
                return $error;
            }
            if (isset($cred_data['password']) && $cred_data['password'] != "") {
                $creds['password'] = $cred_data['password'];
            } else {
                $error['error'] = 500;
                $error['message'] = "Password is required.";
                return $error;
            }
            if (isset($cred_data['domain'])) {
                $creds['domain'] = $cred_data['domain'];
            }
            $creds['is_default'] = isset($cred_data['is_default_creds']) ? $cred_data['is_default_creds'] : false;
            $creds['display_name'] = (isset($cred_data['display_name']) && $cred_data['display_name'] != "") ? $cred_data['display_name'] : "psa-cred";
        } else if (!$modify){
            // in case of POST, credentials are required; for PUT, exisiting ones can be used without any change
            $error['error'] = 500;
            $error['message'] = "Credentials details are required.";
            return $error;
        }
        return $creds;
    }

    private function get_credential_id($config_id, $sid)
    {
        $cred_id = -1;
        $psaDetails = $this->BP->get_psa_config($sid);
        if ($psaDetails !== false) {
            foreach($psaDetails as $config) {
                if ($config['id'] == $config_id) {
                    $cred_id = $config['credentials']['credential_id'];
                }
            }
        }
        return $cred_id;
    }

}

?>
