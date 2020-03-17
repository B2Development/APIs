<?php

class Forum
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;
        $this->exec_path = '/var/www/html/api/includes/';
    }

    public function get_forum($which, $data, $sid, $systems)
    {
        $result = false;
        if (is_string($which[0])) {
            switch($which[0]) {
                case 'users':
                    if ($which[1] && is_numeric($which[1])) {
                        $userID = (int)$which[1];
                        if ($sid !== false) {
                            $result['data'] = $this->get_credential_info($userID, $sid);
                        } else {
                            // System ID should be specified when user ID is passed
                            $result = "System ID must be specified.";
                        }
                    } else {
                        if ($sid !== false) {
                            $result['data'] = $this->get_credential_info(NULL, $sid);
                        } else {
                            $result['data'] = array();
                            foreach ($systems as $sid => $systemName) {
                                $result['data'] = array_merge($result['data'], $this->get_credential_info(NULL, $sid));
                            }
                        }
                    }
                    break;
                case 'posts':
                    $result = $this->get_forum_posts($which, $data);
                    break;
                default:
                    $result = "Invalid request.";
            }
        }
        return $result;
    }

    private function get_credential_info($userID, $sid)
    {
        $credentialInfo = array();
        $creds = $this->BP->get_forum_user_credentials($userID, $sid);
        if ($creds !== false) {
            foreach($creds as $cred) {
                $temp_cred_array = array();
                $temp_cred_array['id'] = $cred['user_id'];
                $temp_cred_array['username'] = $cred['appliance_user'];

                $credArray = array();
                $credArray['credential_id'] = $cred['credential_id'];
                $credArray['username'] = $cred['username'];
                if (isset($cred['domain'])) {
                    $credArray['domain'] = $cred['domain'];
                }
                if (isset($cred['display_name'])) {
                    $credArray['display_name'] = $cred['display_name'];
                }
                $credArray['salesforce_id'] = $cred['salesforce_id'];
                $credArray['is_default'] = $cred['is_default'];
                $temp_cred_array['credentials'] = $credArray;

                $credentialInfo[] = $temp_cred_array;
            }
        } else {
            $credentialInfo = $creds;
        }
        return $credentialInfo;
    }

    /*
	Pulls forum posts from the Unitrends webce server. Valid filters are:


	?count={n}  ---> return at the most n posts, sorted by Created Date descending
	?type=likes  ---> return posts ordered by most likes
	?type=date  ---> return posts ordered by date (default)
	?count={n}&type=likes  ---> return at the most n posts, sorted by most likes
     */
    private function get_forum_posts($which, $data)
    {
        $count = isset($data['count']) ? (" -c" . $data['count']) : "";
        $type = isset($data['type']) ? (" -t" . $data['type']) : "";
        $forum = " -fce";
        if (isset($which[1])) {
            $forum = $which[1] == "ueb" ? "ueb" : "ce";
            $forum = " -f" . $forum;
        }
        $cmd = 'php ' . $this->exec_path . 'forumweb.php -mget' . $count . $type . $forum;
        global $Log;
        $Log->writeVariable('Running cmd:');
        $Log->writeVariable($cmd);
        $str_result = shell_exec($cmd);
        $result = json_decode($str_result, true);
        $result = array('posts' => $result);
        return $result;
    }

    public function post_forum($which, $data, $sid)
    {
        $result = false;
        if ($which === 'users') {
            $userID = $data['user_id'];
            $forum_result = $this->create_forum_account($data, $sid);

            if (is_array($forum_result)) {
                $salesforce_id = $forum_result['ReturnId'];
                $credentialID = $this->create_forum_credentials($userID, $data, $salesforce_id, $sid);
                if ($credentialID !== false) {
                    $result['result'][]['forum_id'] = $credentialID;
                } else {
                    $result = $credentialID;
                }
            } else {
                $result = $forum_result;
            }
        } else if ($which == 'authenticate') {
            $userID = $data['user_id'];
            $credentialInfo = $this->BP->get_forum_user_credentials($userID, $sid);
            $credentialID = NULL;

            if($credentialInfo !== false) {
                foreach($credentialInfo as $credential) {
                    $credentialID = $credential['credential_id'];
                }
            }
            $forum_result = $this->authenticate_forum_account($data, $sid);
            if (is_array($forum_result)) {
                $salesforce_id = $forum_result['ReturnId'];
                // If the credential exists, modify it, or create a new one.
                if ($credentialID === NULL) {
                    // Create a new credential for this forum user.
                    $result = $this->create_forum_credentials($userID, $data, $salesforce_id, $sid);
                } else {
                    // Modify the existing credential, then associate with forums.
                    $update_creds = array();
                    $update_creds['credential_id'] = $credentialID;
                    $update_creds['username'] = $data['email'];
                    $update_creds['password'] = $data['password'];
                    $update_creds['is_default'] = false;
                    $result = $this->BP->save_credentials($update_creds);
                    if ($result === false) {
                        return $this->BP->getError();
                    }
                    $credentialInfo = array();
                    $credentialInfo['credential_id'] = $credentialID;
                    $result = $this->BP->save_forum_user_credentials($userID, $salesforce_id, $credentialInfo, $sid);
                }
            } else {
                $result = $forum_result;
            }
        } else {
            $result = "Invalid request.";
        }
        return $result;
    }

    function create_forum_account($data, $sid)
    {
        $valid = true;
        $result = array();
        $option = array();
        if (isset($data['email'])) {
            $option['Email'] = $data['email'];
            if (isset($data['password'])) {
                $option['Password'] = $option['Confirmpassword'] = $data['password'];
                if (isset($data['nickname'])) {
                    $option['Nickname'] = $data['nickname'];
                    if (isset($data['first_name'])) {
                        $option['Firstname'] = $data['first_name'];
                    }
                    if (isset($data['last_name'])) {
                        $option['Lastname'] = $data['last_name'];
                        if (isset($data['company_name'])) {
                            $option['Companyname'] = $data['company_name'];
                        }
                    } else {
                        $result = "Last name must be specified.";
                        $valid = false;
                    }
                } else {
                    $result = "Nickname must be specified.";
                    $valid = false;
                }
            } else {
                $result = "Password must be specified.";
                $valid = false;
            }
        } else {
            $result = "An E-mail address ust be specified.";
            $valid = false;
        }
        if ($valid) {
            global $Log;
            $option['Token'] = $this->getToken();
            $option['Assettag'] = $this->BP->get_asset_tag($sid);
            // Determine our platform type, default is not Google. (TODO - other platforms?)
            $isGoogle = false;
            $capabilities = $this->BP->get_capabilities(NULL);
            if ($capabilities !== -1) {
                $isGoogle = $capabilities['google'] ? true : false;
                //$isCE = $capabilities['CE'] && !$isGoogle? 1 : 0;
            }
            $option['Edition'] = $isGoogle ? "google edition" : "unitrends free";
            $options_string = http_build_query($option);
            $cmd = 'php ' . $this->exec_path . "forumweb.php -mregister -s'" . $options_string . "'";
            $Log->writeVariable('Running cmd:');
            $Log->writeVariable($cmd);
            $str_result = shell_exec($cmd);
            $result = json_decode($str_result, true);
            if (!$result['Success']) {
                $result = isset($result['Message']) ? $result['Message'] : 'Error creating forum account';
            }
        }
        return $result;
    }

    function getToken() {
        global $Log;
        $contents = file_get_contents('/var/www/html/api/.token');
        $token = base64_decode($contents);
        //$Log->writeVariable("temporary: token is " . $token);
        return $token;
    }

    public function put_forum($which, $data, $sid)
    {
        $result = false;
        if (is_string($which[0])) {
            switch($which[0]) {
                case 'users':
                    if ($which[1] && is_numeric($which[1])) {
                        $userID = (int)$which[1];
                        if ($sid !== false) {
                            $credentialInfo = array();
                            $credentialInfo['credential_id'] = $data['forum_id'];
                            $salesforce_id = isset($data['salesforce_id']) ? $data['salesforce_id'] : '';
                            $result = $this->BP->save_forum_user_credentials($userID, $salesforce_id, $credentialInfo, $sid);
                        } else {
                            // System ID should be specified when user ID is passed
                            $result = "System ID must be specified.";
                        }
                    }
                    break;
                default:
                    $result = "Invalid request.";
            }
        }
        return $result;
    }

    function authenticate_forum_account($data, $sid)
    {
        $result = array();
        if (isset($data['email'])) {
            $option = array();
            $option['Email'] = $data['email'];
            if (isset($data['password'])) {
                $option['Password'] = $data['password'];
                global $Log;
                $option['Token'] = $this->getToken();
                $option['Assettag'] = $this->BP->get_asset_tag($sid);
                $options_string = http_build_query($option);
                $cmd = 'php ' . $this->exec_path . "forumweb.php -mauth -s'" . $options_string . "'";
                $Log->writeVariable('Running cmd:');
                $Log->writeVariable($cmd);
                $str_result = shell_exec($cmd);
                $result = json_decode($str_result, true);
                if (!$result['Success']) {
                    $result = isset($result['Message']) ? $result['Message'] : 'Error authenticating user';
                }
            } else {
                $result = "Forum password must be specified";
            }
        } else {
            $result = "Forum email address must be specified";
        }
        return $result;
    }

    public function delete_forum($which, $data, $sid)
    {
        $result = false;
        if (is_string($which[0])) {
            switch($which[0]) {
                case 'users':
                    if ($which[1] && is_numeric($which[1])) {
                        $userID = (int)$which[1];
                        if ($sid !== false) {
                            $delete_credential = $data['delete_credential'];
                            $result = $this->BP->delete_forum_user_credentials($userID, $delete_credential, $sid);
                        } else {
                            // System ID should be specified when user ID is passed
                            $result = "System ID must be specified.";
                        }
                    }
                    break;
                default:
                    $result = "Invalid request.";
            }
        }
        return $result;
    }

    private function create_forum_credentials($userID, $data, $salesforce_id, $sid)
    {
        $data['forum_username'] = $data['email'];
        $data['forum_password'] = $data['password'];

        $credentialInfo = array();
        $credentialInfo['username'] = $data['forum_username'];
        $credentialInfo['password'] = $data['forum_password'];
        if (array_key_exists('display_name', $data)) {
            $credentialInfo['display_name'] = $data['display_name'];
        }
        if (array_key_exists('domain', $data)) {
            $credentialInfo['domain'] = $data['domain'];
        }
        $credentialID = $this->BP->save_forum_user_credentials($userID, $salesforce_id, $credentialInfo, $sid);
        return $credentialID;
    }


}

?>
