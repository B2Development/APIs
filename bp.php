<?php
//
// Define backup status field possible values.
//
define("X_OUT", '(not displayed)');

// when a cookie is invalidated - setting to this
define("INVALID_COOKIE", -1 );

// class to wrap BPL functions.
class BP
{
    var $m_cookie;
    var $m_bVault;
    var $m_bBypassCookie;

    function __construct($savedcookie = NULL)
    {
        global $Log;
        $this->m_bBypassCookie = false;
        $this->m_cookie        = INVALID_COOKIE;
        $this->m_bVault        = -1;
        $this->m_bLogResults   = true;

        // determine access scheme based on request origin.
        $savedcookie = $this->filterAuthCookieForLocalHost( $savedcookie );

        $arguments = func_num_args();
        if ($this->m_bBypassCookie === true) {
            $this->bypass_cookie(3);
        }
        else if ($arguments == 1 or $arguments == 2) {
            $this->set_cookie($savedcookie, true);
        }
    }

    // return the state of authentication, representing eith a supplied cookie or bypass request
    function isBypassCookie( ) {
        //sab:08.27.14:  temp set to true until we figure out how to test new APIs during development
        //return true;
        return $this->m_bBypassCookie;
    }

    // for special cases (e.g. localhosts), just return and let things work under the restrictions of bypass_cookie
    function filterAuthCookieForLocalHost( $auth_cookie ) {
        // called from command line
        global $Log;
        $this->m_bBypassCookie = false;

        // Checking to see if the update script ( list or update ) is the one being called

        if ( php_sapi_name() === 'cli' ||
            ('127.0.0.1' === $_SERVER['SERVER_ADDR'] && ($_SERVER['SERVER_PORT'] === '80' || $_SERVER['SERVER_PORT'] === '443'))) {
            // bypass if called from the CLI or localhost with standard ports.
            $this->m_bBypassCookie = true;
        }
        return $auth_cookie;
    }

    //
    // This function parses the license information to see if the local system
    // is a licensed vault.
    //
    function setLicensedVault() {
        global $Log;

        $returnValue = false;
        $functionName = "setLicensedVault";

        //$this->set_cookie($this->getCookie());
        // Make sure view is accurate for all users.
        $this->bypass_cookie(2);
        $licenseInfo = $this->get_license_info($this->get_local_system_id());
        if ($licenseInfo !== false) {
            if (array_key_exists('feature_string', $licenseInfo)) {
                $LicenseString = $licenseInfo['feature_string'];
                $ApplianceType = Constants::SYSTEM_IDENTITY_BACKUP_SYSTEM;
                $VCposition = strpos($LicenseString, "VC=");
                if ($VCposition !== false) {
                    $VCindex = $VCposition + 3;		// get past VC=
                    if (strlen($LicenseString) > $VCindex) {
                        if ($LicenseString[$VCindex] != '0') {
                            $ApplianceType = Constants::SYSTEM_IDENTITY_VAULT;
                        }
                    }
                }
                if ($ApplianceType == Constants::SYSTEM_IDENTITY_VAULT) {
                    $strExtra = "";
                    // Check identity
                    $nvp = $this->get_nvp_list('RRC', 'configuration');
                    if ($nvp !== false && count($nvp) > 0) {
                        if ($nvp['Identity'] == "DPU") {
                            $returnValue = false;
                            $strExtra = " Identity is DPU.";
                        } else {
                            $returnValue = true;
                            $strExtra = " Identity is DPV.";
                        }
                    } else {
                        $returnValue = true;
                    }
                    $Log->writeVariable("$functionName: is licensed vault." . $strExtra);
                } else {
                    $Log->writeVariable("$functionName: is not licensed vault.");
                    $returnValue = false;
                }
            } else {
                $Log->writeVariable("$functionName: Error reading license string; assume not a licensed vault.");
            }
        } else {
            $message = " $functionName: bpl.so error reading system license; assume not a licensed vault.";
            $message = $this->getError() . ":" . $message;
            $Log->writeError($message, true);
            $returnValue = false;
        }
        $this->set_cookie($this->getCookie(), true);
        return $returnValue;
    }

    function validVersion($swfVersion, $scriptVersion) {
        return (strcmp($swfVersion, $scriptVersion) >= 0);
    }

    function bpl_save_no_passwd($functionName, $infoArray, $dpuID)
    {
        global $Log;

        $this->set_cookie($this->getCookie());
        // For security reasons, don't save the password to the logfile.
        $logInfo = $infoArray;
        if (array_key_exists('password', $infoArray)) {
            $logInfo['password'] = X_OUT;
        } else if (array_key_exists('properties', $infoArray)) {
            if (array_key_exists('password', $infoArray['properties'])) {
                $logInfo['properties']['password'] = X_OUT;
            }
        }
        if (array_key_exists('confirm_password', $infoArray)) {
            $logInfo['confirm_password'] = X_OUT;
        }
        $arguments = func_num_args();
        if ($arguments == 3) {
            $Log->enterFunction($functionName, $logInfo, $dpuID);
            $result = $functionName($infoArray, $dpuID);
        } else {
            $Log->enterFunction($functionName, $logInfo);
            $result = $functionName($infoArray);
        }
        $Log->exitFunction($functionName, $result);
        $this->destroy_cookie();

        return $result;
    }

    /*
    Note: bpl_call can make function calls that have 10 arguments or less
    */
    function bpl_call() {
        global $Log;

        $this->set_cookie($this->getCookie());

        $functionName = func_get_arg(0);
        $arguments = func_get_args();
        array_shift($arguments);
        $dpuID = func_get_arg(func_num_args() - 1);
        if ($dpuID === NULL) {
            array_pop($arguments);
        }
        switch (count($arguments)) {
            case 0:
                $Log->enterFunction($functionName);
                $result = call_user_func($functionName);
                break;
            case 1:
                $Log->enterFunction($functionName, $arguments[0]);
                $result = call_user_func($functionName, $arguments[0]);
                break;
            case 2:
                $Log->enterFunction($functionName, $arguments[0], $arguments[1]);
                $result = call_user_func($functionName, $arguments[0], $arguments[1]);
                break;
            case 3:
                $Log->enterFunction($functionName, $arguments[0], $arguments[1], $arguments[2]);
                $result = call_user_func($functionName, $arguments[0], $arguments[1], $arguments[2]);
                break;
            case 4:
                $Log->enterFunction($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3]);
                $result = call_user_func($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3]);
                break;
            case 5:
                $Log->enterFunction($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4]);
                $result = call_user_func($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4]);
                break;
            case 6:
                $Log->enterFunction($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4], $arguments[5]);
                $result = call_user_func($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4], $arguments[5]);
                break;
            case 7:
                $Log->enterFunction($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4], $arguments[5], $arguments[6]);
                $result = call_user_func($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4], $arguments[5], $arguments[6]);
                break;
            case 8:
                $Log->enterFunction($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4], $arguments[5], $arguments[6], $arguments[7]);
                $result = call_user_func($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4], $arguments[5], $arguments[6], $arguments[7]);
                break;
            case 9:
                $Log->enterFunction($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4], $arguments[5], $arguments[6], $arguments[7], $arguments[8]);
                $result = call_user_func($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4], $arguments[5], $arguments[6], $arguments[7], $arguments[8]);
                break;
            case 10:
                $Log->enterFunction($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4], $arguments[5], $arguments[6], $arguments[7], $arguments[8], $arguments[9]);
                $result = call_user_func($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4], $arguments[5], $arguments[6], $arguments[7], $arguments[8], $arguments[9]);
                break;
            case 11:
                $Log->enterFunction($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4], $arguments[5], $arguments[6], $arguments[7], $arguments[8], $arguments[9], $arguments[10]);
                $result = call_user_func($functionName, $arguments[0], $arguments[1], $arguments[2], $arguments[3], $arguments[4], $arguments[5], $arguments[6], $arguments[7], $arguments[8], $arguments[9], $arguments[10]);
                break;
        }

        if ($this->m_bLogResults) {
            $Log->exitFunction($functionName, $result);
        }
        else {
            $Log->exitFunction($functionName, X_OUT);
        }
        $this->destroy_cookie();

        return $result;
    }

    //
    // This function parses the license information to see if the local system
    // is a licensed vault.
    //
    function isLicensedVault() {
        global $Log;
        $returnValue = false;
        if ($this->m_bVault === -1) {
            $this->m_bVault = $this->setLicensedVault();
        }
        $returnValue = $this->m_bVault;
        $msg = "isLicensedVault: ";
        $msg .= ($returnValue == true) ? "true" : "false";
        $Log->writeVariable($msg);
        return $returnValue;
    }

    //
    // This function parses the license information to see if the local system
    // is a licensed vault or has a vault 'role'.
    //
    function LocalSystemIsVault() {
        return $this->local_system_is_vault() || $this->isLicensedVault();
    }

    //
    // This function returns true if the local system has multiple
    // "sub"systems (e.g., managed systems, either through management or
    // vaulting.)
    function local_system_is_vault_or_manager() {
        return $this->local_system_is_vault() || $this->local_system_is_manager();
    }

    // Authenticate must always return false when there is a failed authentication.
    // If NULL is returned, the remaining attempts to log in by the other login paths
    // will not be attempted and some users will be locked out.
    function authenticate($username, $password)
    {
        global $Log;
        $functionName = 'bp_authenticate';
        $Log->enterFunction($functionName, $username, X_OUT);
        $this->m_cookie = INVALID_COOKIE;
        $bplCookie = bp_authenticate($username, $password);
        if ( $bplCookie !== false ) {
            $this->m_cookie = $bplCookie;
        }
        $Log->exitFunction($functionName, $username);
        return $this->m_cookie;
    }

    function bypass_cookie($privilegeLevel)
    {
        global $Log;
        $functionName = 'bp_bypass_cookie';
        $Log->enterFunction($functionName, $privilegeLevel);
        $result = bp_bypass_cookie($privilegeLevel);
        $Log->exitFunction($functionName, $result);
        return $result;
    }

    // Only call the BPL layer if 'forced'.  Otherwise, just save the cookie in this class.
    function set_cookie($mycookie, $bForce = false)
    {
        global $Log;
        $this->m_cookie = $mycookie;
        if ($bForce) {
            $functionName = 'bp_set_cookie';
            $Log->enterFunction($functionName, X_OUT);
            $result = bp_set_cookie($mycookie);
            $Log->exitFunction($functionName, $result);
            if ($result === false) {
                $Log->writeVariable("Retry bp_set_cookie as it failed.");
                $Log->enterFunction($functionName, X_OUT);
                $result = bp_set_cookie($mycookie);
                $Log->exitFunction($functionName, $result);
            }
            if ($result === false) {
                $this->m_cookie = INVALID_COOKIE;
            }
            return $result;
        }
    }

    // Only call the BPL layer if 'forced'.  Otherwise, just save the cookie in this class.
    function validCookie()
    {
        return ($this->m_cookie !== INVALID_COOKIE);
    }

    function getCookie()
    {
        return $this->m_cookie;
    }

    function destroyCookie()
    {
        global $Log;
        $functionName = 'bp_destroy_cookie';

        $Log->enterFunction($functionName);
        $result = bp_destroy_cookie();
        $Log->exitFunction($functionName, $result);
        return $result;
    }

    function destroy_cookie()
    {
        // don't do this; need cookie to set later.
        //$this->m_cookie = NULL;

        /*
            Don't set for each API.  Instead, we set in
            login.php and destroy upon logout.
        return bp_destroy_cookie();
        */
    }

    function local_system_is_vault() {
        return $this->bpl_call('bp_local_system_is_vault');
    }

    function local_system_is_mgr() {
        return $this->bpl_call('bp_local_system_is_mgr');
    }

    function local_system_is_vaulting() {
        return $this->bpl_call('bp_local_system_is_vaulting');
    }

    function get_local_system_id() {
        return $this->bpl_call('bp_get_local_system_id');
    }

    function buildResult($xml, $returnValue, $errorString = NULL, $warningString = NULL) {
        global $Log;
        $xml->push("root");
        if (is_array($returnValue)) {
            $xml->element("Result", 0);
            $returnValue = false;
        } else {
            $xml->element("Result", $returnValue === false ? 0 : ($returnValue === true ? 1 : $returnValue));
            if ($warningString != NULL) {
                $this->setWarning($xml, $warningString);
            }
        }
        if (is_null($errorString) && ($returnValue == false)) {
            $errorString = bp_error();
            $bFromBPL = true;
        } else {
            $bFromBPL = false;
        }
        if (!is_null($errorString)) {
            $xml->element("ErrorString", $errorString);
            $Log->writeError($errorString, $bFromBPL);
        }
        $xml->pop();
    }

    // Call this prior to buildResult(true) to get a warning message.
    function setWarning($xml, $msg) {
        $xml->lastTag('ResultArray', 'Result', array('Message' => $msg));
    }

    function get_user_list() {
        return $this->bpl_call('bp_get_user_list');
    }

    function get_user_info($userID) {
        return $this->bpl_call('bp_get_user_info', $userID);
    }

    function save_user_info($userInfo)
    {
        global $Log;
        $functionName = 'bp_save_user_info';

        // For security reasons, don't save the password to the logfile.
        $logUserInfo = $userInfo;
        if (array_key_exists('password', $logUserInfo)) {
            $logUserInfo['password'] = X_OUT;
        }
        if (array_key_exists('current_password', $logUserInfo)) {
            $logUserInfo['current_password'] = X_OUT;
        }
        if (array_key_exists('confirm_password', $logUserInfo)) {
            $logUserInfo['confirm_password'] = X_OUT;
        }
        $this->set_cookie($this->getCookie());
        $Log->enterFunction($functionName, $logUserInfo);
        $result = bp_save_user_info($userInfo);
        $Log->exitFunction($functionName, $result);
        $this->destroy_cookie();

        return $result;
    }

    function remove_user($userID) {
        return $this->bpl_call('bp_remove_user', $userID);
    }

    function get_customer_list() {
        return $this->bpl_call('bp_get_customer_list');
    }

    function get_customer_info($customerID) {
        return $this->bpl_call('bp_get_customer_info', $customerID);
    }

    function save_customer_info($customerInfo) {
        return $this->bpl_call('bp_save_customer_info', $customerInfo);
    }

    function remove_customer($customerID) {
        return $this->bpl_call('bp_remove_customer', $customerID);
    }

    function get_location_list($customerID = NULL)
    {
        global $Log;
        $functionName = 'bp_get_location_list';

        $this->set_cookie($this->getCookie());
        $arguments = func_num_args();
        if ($arguments == 0) {
            $Log->enterFunction($functionName);
            $locationList = bp_get_location_list();
        } else {
            $Log->enterFunction($functionName, $customerID);
            $locationList = bp_get_location_list($customerID);
        }
        $Log->exitFunction($functionName, $locationList);
        $this->destroy_cookie();
        return $locationList;
    }

    function get_location_info($locationID) {
        return $this->bpl_call('bp_get_location_info', $locationID);
    }

    function save_location_info($locationInfo) {
        return $this->bpl_call('bp_save_location_info', $locationInfo);
    }

    function remove_location($locationID) {
        return $this->bpl_call('bp_remove_location', $locationID);
    }

    function get_system_list($customerID = NULL, $locationID = NULL)
    {
        global $Log;
        $functionName = 'bp_get_system_list';

        $this->set_cookie($this->getCookie());
        $arguments = func_num_args();
        if ($arguments == 0) {
            $Log->enterFunction($functionName);
            $systemList = bp_get_system_list();
        } else if ($arguments == 1) {
            $Log->enterFunction($functionName, $customerID);
            $systemList = bp_get_system_list($customerID);
        } else {
            $Log->enterFunction($functionName, $customerID, $locationID);
            $systemList = bp_get_system_list($customerID, $locationID);
        }
        $Log->exitFunction($functionName, $systemList);
        $this->destroy_cookie();
        return $systemList;
    }

    function get_system_info($systemID) {
        return $this->bpl_call('bp_get_system_info', $systemID);
    }

    function save_system_info($systemInfo) {
        return $this->bpl_call('bp_save_system_info', $systemInfo);
    }

    function remove_system($systemID) {
        return $this->bpl_call('bp_remove_system', $systemID);
    }

    function add_mgmt_to_replication_source($src_id) {
        return $this->bpl_call('bp_add_mgmt_to_replication_source', $src_id);
    }

    function getError()
    {
        return bp_error();
    }

    function isSuperUser()
    {
        $user = $this->getUser();
        return ($user['superuser']);
    }

    function isAdmin()
    {
        // if superuser, they have admin rights.
        $bAdmin = false;
        $user = $this->getUser();
        if ($this->isSuperUser() || $user['administrator'] == true) {
            $bAdmin = true;
        }
        return $bAdmin;

    }

    function getUser() {
        return $this->bpl_call('bp_get_current_user');
    }

    function get_alerts($order, $sortBy, $arrayFilter = NULL)
    {
        global $Log;
        $functionName = 'bp_get_alerts';

        $this->set_cookie($this->getCookie());
        $arguments = func_num_args();
        if ($arguments == 3) {
            $Log->enterFunction($functionName, $order, $sortBy, $arrayFilter);
            $result = bp_get_alerts($order, $sortBy, $arrayFilter);
        } else {
            $Log->enterFunction($functionName, $order, $sortBy);
            $result = bp_get_alerts($order, $sortBy);
        }
        $Log->exitFunction($functionName, $result);
        $this->destroy_cookie();
        return $result;
    }

    function get_backup_status($result_format) {
        return $this->bpl_call('bp_get_backup_status', $result_format);
    }

    function get_backup_summary($result_format, $dpuID = NULL) {
        return $this->bpl_call('bp_get_backup_summary', $result_format, $dpuID);
    }

    function get_backup_info($result_format) {
        return $this->bpl_call('bp_get_backup_info', $result_format);
    }

    function get_backup_device_info($deviceIDArray, $dpuID = NULL) {
        return $this->bpl_call('bp_get_backup_device_info', $deviceIDArray, $dpuID);
    }

    function get_backups_per_device($result_format, $dpuID = NULL) {
        return $this->bpl_call('bp_get_backups_per_device', $result_format, $dpuID);
    }

    function get_client_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_client_list', $dpuID);
    }

    function get_grandclient_list($sourceID = NULL) {
        return $this->bpl_call('bp_get_grandclient_list', $sourceID);
    }

    function get_client_id($name, $dpuID = NULL) {
        return $this->bpl_call('bp_get_client_id', $name, $dpuID);
    }

    function get_client_info($clientID, $dpuID = NULL) {
        //return $this->bpl_call('bp_get_client_info', $clientID, $dpuID);
        return $this->obfuscatePasswordOutput('bp_get_client_info', $clientID, $dpuID);
    }

    function save_client_info($clientInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_client_info', $clientInfo, $dpuID);
    }

    function save_rae_client_info($clientInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_rae_client_info', $clientInfo, $dpuID);
    }

    function delete_client($clientID, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_client', $clientID, $dpuID);
    }

    function get_device_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_device_list', $dpuID);
    }

    function get_device_id($deviceName, $dpuID = NULL) {
        return $this->bpl_call('bp_get_device_id', $deviceName, $dpuID);
    }

    function get_device_info($deviceID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_device_info', $deviceID, $dpuID);
    }

    function save_device_info($deviceInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_device_info', $deviceInfo, $dpuID);
    }

    function delete_device($deviceID, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_device', $deviceID, $dpuID);
    }

    function default_device_is_d2d($dpuID = NULL) {
        return $this->bpl_call('bp_default_device_is_d2d', $dpuID);
    }

    function get_workspace_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_workspace_list', $dpuID);
    }

    function get_workspace_info($workspaceID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_workspace_info', $workspaceID, $dpuID);
    }

    function save_workspace_info($workspaceInfo, $dpuID = NULL)
    {
        global $Log;
        $functionName = 'bp_save_workspace_info';

        // For security reasons, don't save the password to the logfile.
        $logWorkspaceInfo = $workspaceInfo;
        if (array_key_exists('password', $logWorkspaceInfo)) {
            $logWorkspaceInfo['password'] = X_OUT;
        }
        if (array_key_exists('samba_password', $logWorkspaceInfo)) {
            $logWorkspaceInfo['samba_password'] = X_OUT;
        }
        $this->set_cookie($this->getCookie());
        $arguments = func_num_args();
        if ($arguments == 2) {
            $Log->enterFunction($functionName, $logWorkspaceInfo, $dpuID);
            $result = bp_save_workspace_info($workspaceInfo, $dpuID);
        } else {
            $Log->enterFunction($functionName, $workspaceInfo);
            $result = bp_save_workspace_info($workspaceInfo);
        }
        $Log->exitFunction($functionName, $result);
        $this->destroy_cookie();

        return $result;
    }

    function remove_workspace($workspaceID, $dpuID = NULL) {
        return $this->bpl_call('bp_remove_workspace', $workspaceID, $dpuID);
    }

    function get_samba_shares($dpuID = NULL) {
        return $this->bpl_call('bp_get_samba_shares', $dpuID);
    }

    function get_workspace_log($workspaceID, $logType, $maxEntries, $dpuID = NULL) {
        return $this->bpl_call('bp_get_workspace_log', $workspaceID, $logType, $maxEntries, $dpuID);
    }

    function get_exchange_log($logType, $maxEntries, $dpuID = NULL) {
        return $this->bpl_call('bp_get_exchange_log', $logType, $maxEntries, $dpuID);
    }

    function get_network_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_network_list', $dpuID);
    }

    function get_network_info($networkID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_network_info', $networkID, $dpuID);
    }

    function save_network_info($networkInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_network_info', $networkInfo, $dpuID);
    }

    function stop_network_restore($dpuID = NULL) {
        return $this->bpl_call('bp_stop_network_restore', $dpuID);
    }

    function get_dns_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_dns_list', $dpuID);
    }

    function save_dns_list($dnsList, $dpuID = NULL) {
        return $this->bpl_call('bp_save_dns_list', $dnsList, $dpuID);
    }

    function get_dns_search_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_dns_search_list', $dpuID);
    }

    function save_dns_search_list($dnsList, $dpuID = NULL) {
        return $this->bpl_call('bp_save_dns_search_list', $dnsList, $dpuID);
    }

    function get_update_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_update_list', $dpuID);
    }

    function get_update_info($packageList, $dpuID = NULL) {
        return $this->bpl_call('bp_get_update_info', $packageList, $dpuID);
    }

    function install_updates($packageList, $dpuID = NULL) {
        return $this->bpl_call('bp_install_updates', $packageList, $dpuID);
    }

    function show_update_history($dpuID = NULL) {
        return $this->bpl_call('bp_show_update_history', $dpuID);
    }

    function precheck_install($unitrendsVersion, $dpuID = NULL) {
        return $this->bpl_call('bp_precheck_install', $unitrendsVersion, $dpuID);
    }

    function get_ini_sections($dpuID = NULL) {
        return $this->bpl_call('bp_get_ini_sections', $dpuID);
    }

    function get_ini_section($stringSection, $dpuID = NULL) {
        return $this->bpl_call('bp_get_ini_section', $stringSection, $dpuID);
    }

    function get_ini_value($section, $field, $dpuID = NULL) {
        return $this->bpl_call('bp_get_ini_value', $section, $field, $dpuID);
    }

    function set_ini_value($section, $field, $value, $dpuID = NULL) {
        return $this->bpl_call('bp_set_ini_value', $section, $field, $value, $dpuID);
    }

    function set_ini_section($section, $fvArray, $dpuID = NULL) {
        return $this->bpl_call('bp_set_ini_section', $section, $fvArray, $dpuID);
    }

    function get_license_info($dpuID = NULL) {
        return $this->bpl_call('bp_get_license_info', $dpuID);
    }

    function get_asset_tag($dpuID = NULL) {
        return $this->bpl_call('bp_get_asset_tag', $dpuID);
    }

    function set_asset_tag($assetTag, $dpuID = NULL) {
        return $this->bpl_call('bp_set_asset_tag', $assetTag, $dpuID);
    }

    function request_license($requestInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_request_license', $requestInfo, $dpuID);
    }

    function save_license_info($licenseInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_license_info', $licenseInfo, $dpuID);
    }

    function get_mail_info($dpuID = NULL) {
        return $this->bpl_call('bp_get_mail_info', $dpuID);
    }

    function save_mail_info($mailInfo, $dpuID = NULL) {
        global $Log;
        $functionName = 'bp_save_mail_info';

        $this->set_cookie($this->getCookie());

        // For security reasons, don't save the passphrase to the logfile.
        $logMailInfo = $mailInfo;
        if (array_key_exists('password', $logMailInfo)) {
            $logMailInfo['password'] = X_OUT;
        }

        $arguments = func_num_args();
        if ($arguments == 2) {
            $Log->enterFunction($functionName, $logMailInfo, $dpuID);
            $result = bp_save_mail_info($mailInfo, $dpuID);
        } else {
            $Log->enterFunction($functionName, $logMailInfo);
            $result = bp_save_mail_info($mailInfo);
        }
        $Log->exitFunction($functionName, $result);
        $this->destroy_cookie();

        return $result;
    }

    function get_backup_files($backupID, $startDirectory, $startFileID, $lastFile, $count, $dpuID = NULL) {
        return $this->bpl_call('bp_get_backup_files', $backupID, $startDirectory, $startFileID, $lastFile, $count, $dpuID);
    }

    function get_synthesized_files($synthID, $endBackupID, $startDirectory, $lastFile, $count, $dpuID = NULL) {
        return $this->bpl_call('bp_get_synthesized_files', $synthID, $endBackupID, $startDirectory, $lastFile, $count, $dpuID);
    }

    function start_file_search($searchOptions, $clientID, $dpuID = NULL) {
        return $this->bpl_call('bp_start_file_search', $searchOptions, $clientID, $dpuID);
    }

    function get_file_search_results($searchArray, $dpuID = NULL) {
        return $this->bpl_call('bp_get_file_search_results', $searchArray, $dpuID);
    }

    function restore_files($backupID, $inclusionList, $exclusionList, $restoreOptions, $dpuID = NULL) {
        return $this->bpl_call('bp_restore_files', $backupID, $inclusionList, $exclusionList, $restoreOptions, $dpuID);
    }

    function restore_synthesized_files($group, $endID, $inclusionList, $exclusionList, $restoreOptions, $dpuID = NULL) {
        return $this->bpl_call('bp_restore_synthesized_files', $group, $endID, $inclusionList, $exclusionList, $restoreOptions, $dpuID);
    }

    function restore_vaulted_files($backupID, $inclusionList, $exclusionList, $restoreOptions, $dpuID = NULL) {
        return $this->bpl_call('bp_restore_vaulted_files', $backupID, $inclusionList, $exclusionList, $restoreOptions, $dpuID);
    }

    function get_job_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_job_list', $dpuID);
    }

    function get_job_info($jobID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_job_info', $jobID, $dpuID);
    }

    function cancel_job($jobID, $dpuID = NULL) {
        return $this->bpl_call('bp_cancel_job', $jobID, $dpuID);
    }

    function suspend_job($jobID, $dpuID = NULL) {
        return $this->bpl_call('bp_suspend_job', $jobID, $dpuID);
    }

    function resume_job($jobID, $dpuID = NULL) {
        return $this->bpl_call('bp_resume_job', $jobID, $dpuID);
    }

    function get_tasker_status($dpuID = NULL) {
        return $this->bpl_call('bp_get_tasker_status', $dpuID);
    }

    function start_tasker($dpuID = NULL) {
        return $this->bpl_call('bp_start_tasker', $dpuID);
    }

    function stop_tasker($dpuID = NULL) {
        return $this->bpl_call('bp_stop_tasker', $dpuID);
    }

    function get_processes($dpuID = NULL) {
        return $this->bpl_call('bp_get_processes', $dpuID);
    }

    function terminate_process($pid, $dpuID = NULL) {
        return $this->bpl_call('bp_terminate_process', $pid, $dpuID);
    }

    function get_command_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_command_list', $dpuID);
    }

    function get_command_info($cid, $dpuID = NULL) {
        return $this->bpl_call('bp_get_command_info', $cid, $dpuID);
    }

    function run_command($name, $override = NULL, $dpuID = NULL) {
        return $this->bpl_call('bp_run_command', $name, $override, $dpuID);
    }

    function get_host_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_host_list', $dpuID);
    }

    function get_host_info($hostID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_host_info', $hostID, $dpuID);
    }

    function save_host_info($hostInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_host_info', $hostInfo, $dpuID);
    }

    function remove_host_info($hostName, $dpuID = NULL) {
        return $this->bpl_call('bp_remove_host_info', $hostName, $dpuID);
    }

    function get_hostname($dpuID = NULL) {
        return $this->bpl_call('bp_get_hostname', $dpuID);
    }

    function change_hostname($newname, $options, $dpuID = NULL) {
        return $this->bpl_call('bp_change_hostname', $newname, $options, $dpuID);
    }

    function get_nvp_list($strType, $strName) {
        return $this->bpl_call('bp_get_nvp_list', $strType, $strName);
    }

    function save_nvp_list($strType, $strName, $nvpList) {
        return $this->bpl_call('bp_save_nvp_list', $strType, $strName, $nvpList);
    }

    function delete_nvp_list($strType, $strName) {
        return $this->bpl_call('bp_delete_nvp_list', $strType, $strName);
    }

    function get_nvp_names($stringType = NULL)
    {
        global $Log;
        $functionName = 'bp_get_nvp_names';

        $this->set_cookie($this->getCookie());
        $arguments = func_num_args();
        if ($arguments == 1) {
            $Log->enterFunction($functionName, $stringType);
            $nameList = bp_get_nvp_names($stringType);
        } else {
            $Log->enterFunction($functionName);
            $nameList = bp_get_nvp_names();
        }
        $Log->exitFunction($functionName, $nameList);
        $this->destroy_cookie();
        return $nameList;
    }

    function get_nvp_types() {
        return $this->bpl_call('bp_get_nvp_types');
    }

    function encryption_supported($dpuID = NULL) {
        return $this->bpl_call('bp_encryption_supported', $dpuID);
    }

    function get_crypt_info($dpuID = NULL) {
        return $this->bpl_call('bp_get_crypt_info', $dpuID);
    }

    function save_instance_crypt_setting( $instance_id, $setting, $dpuID ) {
        return $this->bpl_call('bp_save_instance_crypt_setting', $instance_id, $setting, $dpuID );
    }

    function save_crypt_info($cryptInfo, $dpuID = NULL)
    {
        global $Log;
        $functionName = 'bp_save_crypt_info';

        $this->set_cookie($this->getCookie());

        // For security reasons, don't save the passphrase to the logfile.
        $logCryptInfo = $cryptInfo;
        if (array_key_exists('current_passphrase', $logCryptInfo)) {
            $logCryptInfo['current_passphrase'] = X_OUT;
        }
        if (array_key_exists('new_passphrase', $logCryptInfo)) {
            $logCryptInfo['new_passphrase'] = X_OUT;
        }

        $arguments = func_num_args();
        if ($arguments == 2) {
            $Log->enterFunction($functionName, $logCryptInfo, $dpuID);
            $result = bp_save_crypt_info($cryptInfo, $dpuID);
        } else {
            $Log->enterFunction($functionName, $logCryptInfo);
            $result = bp_save_crypt_info($cryptInfo);
        }
        $Log->exitFunction($functionName, $result);
        $this->destroy_cookie();

        return $result;
    }

    function backup_crypt_keyfile() {
        return $this->bpl_call('bp_backup_crypt_keyfile');
    }

    function get_calendar_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_calendar_list', $dpuID);
    }

    function get_calendar($id, $dpuID = NULL) {
        return $this->bpl_call('bp_get_calendar', $id, $dpuID);
    }

    function save_calendar($calendarInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_calendar', $calendarInfo, $dpuID);
    }

    function delete_calendar($id, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_calendar', $id, $dpuID);
    }

    function get_strategy_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_strategy_list', $dpuID);
    }

    function get_strategy($name, $dpuID = NULL) {
        return $this->bpl_call('bp_get_strategy', $name, $dpuID);
    }

    function get_selection_lists($dpuID = NULL) {
        return $this->bpl_call('bp_get_selection_lists', $dpuID);
    }

    function get_selection_list($id, $dpuID = NULL) {
        return $this->bpl_call('bp_get_selection_list', $id, $dpuID);
    }

    function save_selection_list($selectionListInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_selection_list', $selectionListInfo, $dpuID);
    }

    function delete_selection_list($id, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_selection_list', $id, $dpuID);
    }

    function get_option_lists($dpuID = NULL) {
        return $this->bpl_call('bp_get_option_lists', $dpuID);
    }

    function get_options($id, $dpuID = NULL) {
        return $this->bpl_call('bp_get_options', $id, $dpuID);
    }

    function save_options($optionListInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_options', $optionListInfo, $dpuID);
    }

    function delete_options($id, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_options', $id, $dpuID);
    }

    function get_schedule_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_schedule_list', $dpuID);
    }

    function get_schedule_info($id, $dpuID = NULL) {
        return $this->bpl_call('bp_get_schedule_info', $id, $dpuID);
    }

    function save_schedule_info($scheduleInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_schedule_info', $scheduleInfo, $dpuID);
    }

    function delete_schedule($id, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_schedule', $id, $dpuID);
    }

    function enable_schedule($id, $dpuID = NULL) {
        return $this->bpl_call('bp_enable_schedule', $id, $dpuID);
    }

    function disable_schedule($id, $dpuID = NULL) {
        return $this->bpl_call('bp_disable_schedule', $id, $dpuID);
    }

    function schedule_now($id, $dpuID = NULL) {
        return $this->bpl_call('bp_schedule_now', $id, $dpuID);
    }

    function get_app_schedule_list($cID, $aID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_app_schedule_list', $cID, $aID, $dpuID);
    }

    function get_app_schedule_info($id, $dpuID = NULL) {
        return $this->bpl_call('bp_get_app_schedule_info', $id, $dpuID);
    }

    function save_app_schedule_info($scheduleInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_app_schedule_info', $scheduleInfo, $dpuID);
    }

    function delete_app_schedule($id, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_app_schedule', $id, $dpuID);
    }

    function enable_app_schedule($id, $dpuID = NULL) {
        return $this->bpl_call('bp_enable_app_schedule', $id, $dpuID);
    }

    function disable_app_schedule($id, $dpuID = NULL) {
        return $this->bpl_call('bp_disable_app_schedule', $id, $dpuID);
    }

    function backup_now($backupType, $clientInfoArray, $dpuID = NULL) {
        return $this->bpl_call('bp_backup_now', $backupType, $clientInfoArray, $dpuID);
    }

    function get_client_files($clientID, $startDirectory, $lastFile, $count, $dpuID = NULL) {
        return $this->bpl_call('bp_get_client_files', $clientID,  $startDirectory, $lastFile, $count, $dpuID);
    }

    // wrapper that has same signature of bp_backup_files, ignores id as not used in BPL
    function get_client_files_ext($clientID, $startDirectory, $id, $lastFile, $count, $dpuID = NULL) {
        return $this->bpl_call('bp_get_client_files', $clientID,  $startDirectory, $lastFile, $count, $dpuID);
    }

    function delete_backup($backupID, $legalHoldBackups, $dpuID = NULL) {
        // if no legalHoldBackups, must pass in "none"
        if ($legalHoldBackups === "") {
            $legalHoldBackups = "none";
        }

        return $this->bpl_call('bp_delete_backup', $backupID, $legalHoldBackups, $dpuID);
    }

    // gets related backups, returns an error if any backups are pending vaulting/replication
    function get_related_backups($backupID, $dpuID) {
        return $this->bpl_call('bp_get_related_backups', $backupID, $dpuID);
    }

    // gets dependent backups, same as related but it always returns the related backups
    // whereas related backups returns an error if any backups are pending vaulting or replication
    function get_dependent_backups($backupID, $dpuID) {
        return $this->bpl_call('bp_get_dependent_backups', $backupID, $dpuID);
    }

    function get_reports($dpuID = NULL) {
        return $this->bpl_call('bp_get_reports', $dpuID);
    }

    function save_report_info($reportInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_report_info', $reportInfo, $dpuID);
    }

    function delete_report($id, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_report', $id, $dpuID);
    }

    function is_bm_media_tested($clientList, $dpuID = NULL) {
        return $this->bpl_call('bp_is_bm_media_tested', $clientList, $dpuID);
    }

    function get_operation_list($operationName, $dpuID = NULL) {
        return $this->bpl_call('bp_get_operation_list', $operationName, $dpuID);
    }

    function get_retention_info($dpuID = NULL) {
        return $this->bpl_call('bp_get_retention_info', $dpuID);
    }

    function get_date($dpuID = NULL) {
        return $this->bpl_call('bp_get_date', $dpuID);
    }

    function set_date($date, $dpuID = NULL) {
        return $this->bpl_call('bp_set_date', $date, $dpuID);
    }

    function get_timezone_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_timezone_list', $dpuID);
    }

    function get_timezone($dpuID = NULL) {
        return $this->bpl_call('bp_get_timezone', $dpuID);
    }

    function set_timezone($tz, $dpuID = NULL) {
        return $this->bpl_call('bp_set_timezone', $tz, $dpuID);
    }

    function get_ntp_settings($dpuID = NULL) {
        return $this->bpl_call('bp_get_ntp_settings', $dpuID);
    }

    function save_ntp_settings($ntp, $dpuID = NULL) {
        return $this->bpl_call('bp_save_ntp_settings', $ntp, $dpuID);
    }

    function grant_mgr($ip) {
        return $this->bpl_call('bp_grant_mgr', $ip);
    }

    function revoke_mgr($ip) {
        return $this->bpl_call('bp_revoke_mgr', $ip);
    }

    function get_manager_list() {
        return $this->bpl_call('bp_get_manager_list');
    }

    function configure_replication($sourceID, $deviceID) {
        return $this->bpl_call('bp_configure_replication', $sourceID, $deviceID);
    }

    function set_replication_device($sourceID, $deviceID) {
        return $this->bpl_call('bp_set_replication_device', $sourceID, $deviceID);
    }

    function configure_source_replication($target, $strategy, $dpuID = NULL) {
        return $this->bpl_call('bp_configure_source_replication', $target, $strategy, $dpuID);
    }

    function remove_replication($sourceID) {
        return $this->bpl_call('bp_remove_replication', $sourceID);
    }

    function remove_source_replication($target, $dpuID = NULL) {
        return $this->bpl_call('bp_remove_source_replication', $target, $dpuID);
    }

    function is_replication_configured($sourceID) {
        return $this->bpl_call('bp_is_replication_configured', $sourceID);
    }

    function is_replication_enabled($dpuID = NULL) {
        return $this->bpl_call('bp_is_replication_enabled', $dpuID);
    }

    function enable_replication($enable, $dpuID = NULL) {
        return $this->bpl_call('bp_enable_replication', $enable, $dpuID);
    }

    function restart_replication($dpuID = NULL) {
        return $this->bpl_call('bp_restart_replication', $dpuID);
    }

    function local_system_is_replicating() {
        //return $this->bpl_call('bp_local_system_is_replicating');
        global $Log;
        $functionName = 'local_system_is_replicating';
        $Log->enterFunction($functionName);

        $result = $this->get_ini_value('Replication', 'Enabled');
        // No failure, and Replication is enabled.
        if ($result !== false && strcasecmp($result, "Yes") == 0) {
            $syncTo = $this->get_ini_value('Replication', 'SyncTo');
            // Cannot get target name, set result to false.
            if ($syncTo !== "") {
                $result = $syncTo;
            } else {
                $result = false;
            }
        } else {
            $result = false;
        }

        /*
        $result = $this->get_ini_value('Replication', 'SyncTo');
        // it's not replicating anywhere.
        if ($result === "") {
            $result = false;
        } else {
            $command = 'ping -c1 -w 1 ' . $result . ' >/dev/null 2>&1';
            $Log->writeVariable($command);
            system($command, $pingResult);
            $Log->writeVariable($pingResult);
            if ($pingResult == 0 || $pingResult == 1) {
                ;
            } else {
                $result = false;
            }
        }
        */
        $Log->exitFunction($functionName, $result);
        return $result;
    }

    function configure_vaulting($dpuID, $storageID, $dpuUser) {
        return $this->bpl_call('bp_configure_vaulting', $dpuID, $storageID, $dpuUser);
    }

    function remove_vaulting($dpuID) {
        return $this->bpl_call('bp_remove_vaulting', $dpuID);
    }

    function is_vaulting_configured($dpuID) {
        return $this->bpl_call('bp_is_vaulting_configured', $dpuID);
    }

    function modify_sync_user($dpuID, $dpuUser) {
        return $this->bpl_call('bp_modify_sync_user', $dpuID, $dpuUser);
    }

    function get_securesync_config($dpuID = NULL) {
        return $this->bpl_call('bp_get_securesync_config', $dpuID);
    }

    function save_securesync_config($settings, $dpuID = NULL) {
        return $this->bpl_call('bp_save_securesync_config', $settings, $dpuID);
    }

    function enable_securesync($bEnable, $dpuID = NULL) {
        return $this->bpl_call('bp_enable_securesync', $bEnable, $dpuID);
    }

    function restart_securesync($dpuID) {
        return $this->bpl_call('bp_restart_securesync', $dpuID);
    }

    function is_securesync_enabled($dpuID) {
        return $this->bpl_call('bp_is_securesync_enabled', $dpuID);
    }

    function get_last_backups($dpuID) {
        return $this->bpl_call('bp_get_last_backups', $dpuID);
    }

    function get_snmp_config($dpuID = NULL) {
        return $this->bpl_call('bp_get_snmp_config', $dpuID);
    }

    function save_snmp_config($snmp, $dpuID = NULL) {
        return $this->bpl_call('bp_save_snmp_config', $snmp, $dpuID);
    }

    function delete_snmp_config($id, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_snmp_config', $id, $dpuID);
    }

    function send_test_snmptrap($dpuID = NULL) {
        return $this->bpl_call('bp_send_test_snmptrap', $dpuID);
    }

    function get_snmp_history($dpuID = NULL) {
        return $this->bpl_call('bp_get_snmp_history', $dpuID);
    }

    function get_snmpd_config($dpuID = NULL) {
        return $this->bpl_call('bp_get_snmpd_config', $dpuID);
    }

    function save_snmpd_config($snmpdConfig, $dpuID = NULL) {
        return $this->bpl_call('bp_save_snmpd_config', $snmpdConfig, $dpuID);
    }

    function get_backlog($dpuID = NULL) {
        return $this->bpl_call('bp_get_backlog', $dpuID);
    }

    function get_application_clients($clientID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_application_clients', $clientID, $dpuID);
    }

    function get_restore_group($backupID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_restore_group', $backupID, $dpuID);
    }

    function get_synthesis_group($backupID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_synthesis_group', $backupID, $dpuID);
    }

    function get_appinst_name($appInstID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_appinst_name', $appInstID, $dpuID);
    }

    function restore_application($ids, $options, $dpuID = NULL) {
        return $this->bpl_call('bp_restore_application', $ids, $options, $dpuID);
    }

    function get_exchange_info($clientID, $use_cache, $dpuID = NULL) {
        return $this->bpl_call('bp_get_exchange_info', $clientID, $use_cache, $dpuID);
    }

    function get_grandclient_exchange_info($grandclientID) {
        return $this->bpl_call('bp_get_grandclient_exchange_info', $grandclientID);
    }

    function get_exchange_restore_targets($clientID, $database, $bAll, $dpuID = NULL) {
        return $this->bpl_call('bp_get_exchange_restore_targets', $clientID, $database, $bAll, $dpuID);
    }

    function get_sql_info($clientID, $appID, $use_cache, $dpuID = NULL) {
        return $this->bpl_call('bp_get_sql_info', $clientID, $appID, $use_cache, $dpuID);
    }

    function get_grandclient_sql_info($grandclientID, $appID) {
        return $this->bpl_call('bp_get_grandclient_sql_info', $grandclientID, $appID);
    }

    function get_sql_server_recovery_clients($appInstID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_sql_server_recovery_clients', $appInstID, $dpuID);
    }

    function get_sql_server_recovery_targets($clientID, $appInstID, $backupID = NULL, $dpuID = NULL) {
        return $this->bpl_call('bp_get_sql_server_recovery_targets', $clientID, $appInstID, $backupID, $dpuID);
    }

    function is_sql_system_db($instance, $dpuID = NULL) {
        return $this->bpl_call('bp_is_sql_system_db', $instance, $dpuID);
    }

    function get_vm_info( $appUUID, $filter_string, $use_cache, $get_disks = false, $dpuID = NULL ) {
        return $this->bpl_call('bp_get_vm_info', $appUUID, $filter_string, $use_cache, $get_disks, $dpuID);
    }

    function get_grandclient_vm_info( $esx_server, $grandclient_id, $instanceIDs = null ) {
        return $this->bpl_call('bp_get_grandclient_vm_info', $esx_server, $grandclient_id, $instanceIDs);
    }

    function get_resource_pool_info( $uuid, $dpuID = NULL ){
        return $this->bpl_call( 'bp_get_resource_pool_info', $uuid, $dpuID );
    }

    function get_vApp_info( $uuid, $dpuID = NULL ){
        return $this->bpl_call( 'bp_get_vapp_info', $uuid, $dpuID );
    }

    function vmware_ir_supported($dpuID = NULL) {
        return $this->bpl_call('bp_vmware_ir_supported', $dpuID);
    }

    function vmware_ir_destroy($appID, $force, $dpuID = NULL) {
        return $this->bpl_call('bp_vmware_ir_destroy', $appID, $force, $dpuID);
    }

    function get_vm_ir_status($dpuID = NULL) {
        return $this->bpl_call('bp_vmware_ir_status', $dpuID);
    }

    function vmware_ir_start($esxUID, $vmName, $datastore, $restoreAddress, $audit, $backups, $power, $rkey = NULL, $dpuID = NULL) {
        return $this->bpl_call('bp_vmware_ir_start', $esxUID, $vmName, $datastore, $restoreAddress, $audit, $backups, $power, $rkey, $dpuID);
    }

    // retrieves disks for a given VM
    function get_vm_disks($instanceID, $use_cache, $dpuID = NULL) {
        return $this->bpl_call('bp_get_vm_disks', $instanceID, $use_cache, $dpuID);
    }

    // sets disk exclusions for a given VM if any
    function set_vm_disks( $diskExcludeSettings, $dpuID = NULL ) {
        return $this->bpl_call('bp_set_vm_disks', $diskExcludeSettings, $dpuID );
    }

    function save_app_vaulting_info($instanceInfo = NULL, $dpuID) {
        return $this->bpl_call('bp_save_app_vaulting_info', $instanceInfo, $dpuID);
    }

    function get_vm_restore_targets($appInstID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_vm_restore_targets', $appInstID, $dpuID);
    }

    function get_vcenter_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_vcenter_list', $dpuID);
    }

    function get_vcenter_info($id, $dpuID = NULL) {
        return $this->bpl_call('bp_get_vcenter_info', $id, $dpuID);
    }

    function save_vcenter_info($vcenterInfo, $dpuID = NULL) {
        return $this->bpl_save_no_passwd('bp_save_vcenter_info', $vcenterInfo, $dpuID);
    }

    function delete_vcenter_info($id, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_vcenter_info', $id, $dpuID);
    }

    function get_d2d($dpuID = NULL) {
        return $this->bpl_call('bp_get_d2d', $dpuID);
    }

    function set_d2d($allowed, $dpuID = NULL) {
        return $this->bpl_call('bp_set_d2d', $allowed, $dpuID);
    }

    function get_vc($dpuID = NULL) {
        return $this->bpl_call('bp_get_vc', $dpuID);
    }

    function set_vc($allowed, $dpuID = NULL) {
        return $this->bpl_call('bp_set_vc', $allowed, $dpuID);
    }

    function get_virtual_failover($dpuID = NULL) {
        return $this->bpl_call('bp_get_virtual_failover', $dpuID);
    }

    function set_virtual_failover($allowed, $dpuID = NULL) {
        return $this->bpl_call('bp_set_virtual_failover', $allowed, $dpuID);
    }

    function get_customer_reserve($dpuID = NULL) {
        return $this->bpl_call('bp_get_customer_reserve', $dpuID);
    }

    function set_customer_reserve($allowed, $dpuID = NULL) {
        return $this->bpl_call('bp_set_customer_reserve', $allowed, $dpuID);
    }

    function start_uarchive($dpuID = NULL) {
        return $this->bpl_call('bp_start_uarchive', $dpuID);
    }

    function stop_uarchive($dpuID = NULL) {
        return $this->bpl_call('bp_stop_uarchive', $dpuID);
    }

    function get_uarchive_status($dpuID = NULL) {
        return $this->bpl_call('bp_get_uarchive_status', $dpuID);
    }

    function get_possible_archive_media($dpuID = NULL) {
        return $this->bpl_call('bp_get_possible_archive_media', $dpuID);
    }

    function get_connected_archive_media($dpuID = NULL) {
        return $this->bpl_call('bp_get_connected_archive_media', $dpuID);
    }

    function get_current_archive_media($dpuID = NULL) {
        return $this->bpl_call('bp_get_current_archive_media', $dpuID);
    }

    function mount_archive_media($mediaName, $dpuID = NULL) {
        return $this->bpl_call('bp_mount_archive_media', $mediaName, $dpuID);
    }

    function unmount_archive_media($mediaName, $bForce, $dpuID = NULL) {
        return $this->bpl_call('bp_unmount_archive_media', $mediaName, $bForce, $dpuID);
    }

    function prepare_archive_media($mediaName, $mediaLabel, $slots, $dpuID = NULL) {
        return $this->bpl_call('bp_prepare_archive_media', $mediaName, $mediaLabel, $slots, $dpuID);
    }

    function archive_now($profile, $dpuID = NULL) {
        return $this->bpl_call('bp_archive_now', $profile, $dpuID);
    }

    function check_archive_space($profile, $dpuID = NULL) {
        return $this->bpl_call('bp_check_archive_space', $profile, $dpuID);
    }

    function get_archive_schedule_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_archive_schedule_list', $dpuID);
    }

    function get_archive_schedule_info($id, $dpuID = NULL) {
        return $this->bpl_call('bp_get_archive_schedule_info', $id, $dpuID);
    }

    function save_archive_schedule_info($scheduleInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_archive_schedule_info', $scheduleInfo, $dpuID);
    }

    function delete_archive_schedule($id, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_archive_schedule', $id, $dpuID);
    }

    function enable_archive_schedule($id, $dpuID = NULL) {
        return $this->bpl_call('bp_enable_archive_schedule', $id, $dpuID);
    }

    function disable_archive_schedule($id, $dpuID = NULL) {
        return $this->bpl_call('bp_disable_archive_schedule', $id, $dpuID);
    }

    function get_media_archive_sets($mediaName, $slots, $dpuID = NULL) {
        return $this->bpl_call('bp_get_media_archive_sets', $mediaName, $slots, $dpuID);
    }

    function import_archive_catalog($mediaName, $slots, $force, $dpuID = NULL) {
        return $this->bpl_call('bp_import_archive_catalog', $mediaName, $slots, $force, $dpuID);
    }

    function purge_archive_catalog($id, $dpuID = NULL) {
        return $this->bpl_call('bp_purge_archive_catalog', $id, $dpuID);
    }

    function get_archive_sets($result_format, $dpuID = NULL) {
        return $this->bpl_call('bp_get_archive_sets', $result_format, $dpuID);
    }

    function get_archive_set_info($id, $dpuID = NULL) {
        return $this->bpl_call('bp_get_archive_set_info', $id, $dpuID);
    }

    function get_archive_status($result_format, $dpuID = NULL) {
        return $this->bpl_call('bp_get_archive_status', $result_format, $dpuID);
    }

    function get_archive_files($archiveID, $startDirectory, $lastFile, $count, $dpuID = NULL) {
        return $this->bpl_call('bp_get_archive_files', $archiveID,  $startDirectory, $lastFile, $count, $dpuID);
    }

    function get_configurable_archive_media($dpuID = NULL) {
        return $this->bpl_call('bp_get_configurable_archive_media', $dpuID);
    }

    function get_archive_media_settings($deviceName, $dpuID = NULL) {
        return $this->bpl_call('bp_get_archive_media_settings', $deviceName, $dpuID);
    }

    function save_archive_media_settings($deviceName, $settings, $dpuID = NULL) {
        return $this->bpl_call('bp_save_archive_media_settings', $deviceName, $settings, $dpuID);
    }

    // wrapper that has same signature of bp_backup_files, ignores id as not used in BPL
    function get_archive_files_ext($archiveID, $startDirectory, $id, $lastFile, $count, $dpuID = NULL) {
        return $this->bpl_call('bp_get_archive_files', $archiveID,  $startDirectory, $lastFile, $count, $dpuID);
    }

    function restore_archived_files($archiveID, $options, $dpuID = NULL) {
        return $this->bpl_call('bp_restore_archived_files', $archiveID, $options, $dpuID);
    }

    function get_legacy_archive_schedule_info($dpuID = NULL) {
        return $this->bpl_call('bp_get_legacy_archive_schedule_info', $dpuID);
    }

    function disable_legacy_archive_schedules($type, $dpuID = NULL) {
        return $this->bpl_call('bp_disable_legacy_archive_schedules', $type, $dpuID);
    }

    function dedup_supported($dpuID = NULL) {
        return $this->bpl_call('bp_dedup_supported', $dpuID);
    }

    function get_data_reduction_stats($dpuID = NULL) {
        return $this->bpl_call('bp_get_data_reduction_stats', $dpuID);
    }

    function get_audit_history($arrayFilter, $dpuID = NULL) {
        return $this->bpl_call('bp_get_audit_history', $arrayFilter, $dpuID);
    }

    function kill_vaulting_operation($id, $clearSyncNeeded, $dpuID = NULL) {
        return $this->bpl_call('bp_kill_vaulting_operation', $id, $clearSyncNeeded, $dpuID);
    }

    function reset_vaulting_operation($backupID, $clientName, $backupType, $dpuID = NULL) {
        return $this->bpl_call('bp_reset_vaulting_operation', $backupID, $clientName, $backupType, $dpuID);
    }

    function is_eligible_for_reset($backupID, $clientName, $backupType, $dpuID = NULL) {
        return $this->bpl_call('bp_is_eligible_for_reset', $backupID, $clientName, $backupType, $dpuID);
    }

    function create_disk_image($ids, $dpuID = NULL) {
        return $this->bpl_call('bp_create_disk_image', $ids, $dpuID);
    }

    function get_disk_image_status($dpuID = NULL) {
        return $this->bpl_call('bp_get_disk_image_status', $dpuID);
    }

    function destroy_disk_image($instanceID, $dpuID = NULL) {
        return $this->bpl_call('bp_destroy_disk_image', $instanceID, $dpuID);
    }

    function cancel_disk_image($dpuID = NULL) {
        return $this->bpl_call('bp_cancel_disk_image', $dpuID);
    }

    function backup_mount($ids, $dpuID = NULL) {
        return $this->bpl_call('bp_backup_mount', $ids, $dpuID);
    }

    function backup_mount_status($dpuID = NULL) {
        return $this->bpl_call('bp_backup_mount_status', $dpuID);
    }

    function backup_unmount($dpuID = NULL) {
        return $this->bpl_call('bp_backup_unmount', $dpuID);
    }

    function is_virtual($dpuID = NULL) {
        return $this->bpl_call('bp_is_virtual', $dpuID);
    }

    function is_demo($dpuID = NULL) {
        return $this->bpl_call('bp_is_demo', $dpuID);
    }

    function is_version_supported($remoteVersion, $wantVersion) {
        return $this->bpl_call('bp_is_version_supported', $remoteVersion, $wantVersion);
    }

    function get_iqn( $dpuID = NULL ) {

        return $this->bpl_call( 'bp_get_iqn', $dpuID);

    }

    function get_wwn($dpuID = NULL) {
        return $this->bpl_call('bp_get_wwn', $dpuID);
    }

    function get_storage_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_storage_list', $dpuID);
    }

    function get_storage_info($storageID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_storage_info', $storageID, $dpuID);
    }

    function save_storage_info($storageInfo, $dpuID = NULL) {
        return $this->bpl_save_no_passwd('bp_save_storage_info', $storageInfo, $dpuID);
    }

    function delete_storage($storageID, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_storage', $storageID, $dpuID);
    }

    function get_storage_id($storageName, $dpuID = NULL) {
        return $this->bpl_call('bp_get_storage_id', $storageName, $dpuID);
    }

    function disconnect_storage($storageID, $dpuID = NULL) {
        return $this->bpl_call('bp_disconnect_storage', $storageID, $dpuID);
    }

    function connect_storage($storageID, $dpuID = NULL) {
        return $this->bpl_call('bp_connect_storage', $storageID, $dpuID);
    }

    function get_internal_storage_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_internal_storage_list', $dpuID);
    }

    function list_iscsi_targets($host, $port, $dpuID = NULL ) {
        return $this->bpl_call('bp_list_iscsi_targets', $host, $port, $dpuID );
    }

    function list_iscsi_luns($host, $port, $target, $dpuID = NULL ) {
        return $this->bpl_call('bp_list_iscsi_luns', $host, $port, $target, $dpuID );
    }

    function list_fc_targets($dpuID = NULL) {
        return $this->bpl_call('bp_list_fc_targets', $dpuID);
    }

    function list_fc_luns($target, $dpuID = NULL) {
        return $this->bpl_call('bp_list_fc_luns', $target, $dpuID);
    }

    function set_chap( $username, $password, $dpuID = NULL ) {
        //return $this->bpl_call('bp_set_chap', $username, $password, $dpuID );
        // obfuscate password by changing parameters to enterFunction().
        global $Log;
        $functionName = 'bp_set_chap';
        $this->set_cookie($this->getCookie());
        $Log->enterFunction($functionName, $username, X_OUT);
        $result = $functionName($username, $password, $dpuID);
        $Log->exitFunction($functionName, $result);
        $this->destroy_cookie();
        return $result;
    }

    function get_chap( $dpuID = NULL ) {
        return $this->bpl_call('bp_get_chap', $dpuID );
    }

    function get_archive_state_path($media_name, $dpuID = NULL){
        return $this->bpl_call('bp_get_archive_state_path', $media_name, $dpuID);
    }

    function dpu_restore_archive_state($media_name, $state_path = NULL){
        return $this->bpl_call('bp_dpu_restore_archive_state', $media_name, $state_path);
    }

    function get_vaulted_dpus(){
        return $this->bpl_call('bp_get_vaulted_dpus');
    }

    function get_dpu_name($syncUser){
        return $this->bpl_call('bp_get_dpu_name', $syncUser);
    }

    function get_dpu_clients($syncUser, $dpuName){
        return $this->bpl_call('bp_get_dpu_clients', $syncUser, $dpuName);
    }

    function get_target_storage($ip = NULL){
        return $this->bpl_call('bp_get_target_storage', $ip);
    }

    function save_storage_preference($replaceStorage, $syncUser, $dpuName){
        return $this->bpl_call('bp_save_storage_preference', $replaceStorage, $syncUser, $dpuName);
    }

    function rewrite_client_metadata($clientDevices, $syncUser, $dpuName){
        return $this->bpl_call('bp_rewrite_client_metadata', $clientDevices, $syncUser, $dpuName);
    }

    function dpu_restore_vault_state($syncUser, $dpuName, $ip){
        return $this->bpl_call('bp_dpu_restore_vault_state', $syncUser, $dpuName, $ip);
    }

    function dpu_restore_clients($syncUser, $dpuName, $ip, $clients){
        return $this->bpl_call('bp_dpu_restore_clients', $syncUser, $dpuName, $ip, $clients);
    }

    function is_vault_dpu_encryption_enabled($syncUser, $dpuName){
        return $this->bpl_call('bp_is_vault_dpu_encryption_enabled', $syncUser, $dpuName);
    }

    function is_archive_dpu_encryption_enabled($statePath){
        return $this->bpl_call('bp_is_archive_dpu_encryption_enabled', $statePath);
    }

    function is_openvpn_server_configured() {
        return $this->bpl_call('bp_is_openvpn_server_configured');
    }

    function get_openvpn_server_info() {
        return $this->bpl_call('bp_get_openvpn_server_info');
    }

    function configure_openvpn_server($ip, $mask, $port) {
        return $this->bpl_call('bp_configure_openvpn_server', $ip, $mask, $port);
    }

    function request_openvpn_cert() {
        return $this->bpl_call('bp_request_openvpn_cert');
    }

    function sign_openvpn_cert($client, $csr, $revoke) {
        return $this->bpl_call('bp_sign_openvpn_cert', $client, $csr, $revoke);
    }

    function configure_openvpn_client($ca, $cert, $server, $port) {
        return $this->bpl_call('bp_configure_openvpn_client', $ca, $cert, $server, $port);
    }

    function get_schedule_history($returnArray, $dpuID){
        return $this->bpl_call('bp_get_schedule_history', $returnArray, $dpuID);
    }

    function dpu_restore_vault_check_version($syncUser, $dpuName, $ip){
        return $this->bpl_call('bp_dpu_restore_vault_check_version', $syncUser, $dpuName, $ip);
    }

    function dpu_restore_archive_check_version($statePath){
        return $this->bpl_call('bp_dpu_restore_archive_check_version', $statePath);
    }

    function archive_validate_devices($targetSelected, $devices){
        return $this->bpl_call('bp_archive_validate_devices', $targetSelected, $devices);
    }

    function add_client_to_default_schedule($clientName, $dpuID) {
        return $this->bpl_call('bp_add_client_to_default_schedule', $clientName, $dpuID);
    }

    function save_archive_storage_preference($replaceStorage) {
        return $this->bpl_call('bp_save_archive_storage_preference', $replaceStorage);
    }

    function get_retention_settings($filter, $dpuID = NULL) {
        return $this->bpl_call('bp_get_retention_settings', $filter, $dpuID);
    }

    function save_retention_settings($settings, $dpuID = NULL) {
        return $this->bpl_call('bp_save_retention_settings', $settings, $dpuID);
    }

    function get_oldest_backup($instanceIDs, $dpuID = NULL) {
        return $this->bpl_call('bp_get_oldest_backup', $instanceIDs, $dpuID);
    }

    function is_appconfig_supported($dpuID = NULL) {
        return $this->bpl_call('bp_is_appconfig_supported', $dpuID);
    }

    function dr_in_progress($syncUser, $ip) {
        return $this->bpl_call('bp_dr_in_progress', $syncUser, $ip);
    }

    function save_auto_dr_preference($automatic, $syncUser, $dpu_name) {
        return $this->bpl_call('bp_save_auto_dr_preference', $automatic, $syncUser, $dpu_name);
    }

    function save_auto_dr_profile($profileArray) {
        return $this->bpl_call('bp_save_auto_dr_profile', $profileArray);
    }

    function remove_auto_dr_profile($syncUser, $ip) {
        return $this->bpl_call('bp_remove_auto_dr_profile', $syncUser, $ip);
    }

    function load_auto_dr_profile($syncUser, $ip) {
        return $this->bpl_call('bp_load_auto_dr_profile', $syncUser, $ip);
    }

    function vault_validate_devices($syncUser, $dpuName, $ip, $clients) {
        return $this->bpl_call('bp_vault_validate_devices', $syncUser, $dpuName, $ip, $clients);
    }

    function is_client_vaulting($clientID, $dpuID = NULL) {
        return $this->bpl_call('bp_is_client_vaulting', $clientID, $dpuID);
    }

    function get_client_config($clientID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_client_config', $clientID, $dpuID);
    }

    function get_client_config_from_backup($backupID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_client_config_from_backup', $backupID, $dpuID);
    }

    function get_virtual_candidates($dpuID = NULL) {
        return $this->bpl_call('bp_get_virtual_candidates', $dpuID);
    }

    function virtual_clients_supported($dpuID = NULL) {
        return $this->bpl_call('bp_virtual_clients_supported', $dpuID);
    }

    function get_virtual_client_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_virtual_client_list', $dpuID);
    }

    function get_virtual_client($vid, $dpuID = NULL) {
        return $this->bpl_call('bp_get_virtual_client', $vid, $dpuID);
    }

    function get_virtual_client_state($vid, $dpuID = NULL) {
        return $this->bpl_call('bp_get_virtual_client_state', $vid, $dpuID);
    }

    function save_virtual_client($clientInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_virtual_client', $clientInfo, $dpuID);
    }

    function audit_virtual_client($vid, $bStart, $bPowerOn = true) {
        return $this->bpl_call('bp_audit_virtual_client', $vid, $bStart, $bPowerOn);
    }

    function run_virtual_client($vid, $bStart) {
        return $this->bpl_call('bp_run_virtual_client', $vid, $bStart);
    }

    function disable_virtual_restores($vid, $bDisable, $dpuID = NULL) {
        return $this->bpl_call('bp_disable_virtual_restores', $vid, $bDisable, $dpuID);
    }

    function delete_virtual_client($vid, $deleteFromHypervisor, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_virtual_client', $vid, $deleteFromHypervisor, $dpuID);
    }

    function get_virtual_restore_backlog($vid, $dpuID = NULL) {
        return $this->bpl_call('bp_get_virtual_restore_backlog', $vid, $dpuID);
    }

    function get_last_virtual_restore($vid, $dpuID = NULL) {
        return $this->bpl_call('bp_get_last_virtual_restore', $vid, $dpuID);
    }

    function get_wir_vm_snapshot_info($hypervisor_type, $vids, $dpuId) {
        return $this->bpl_call('bp_get_wir_vm_snapshot_info', $hypervisor_type, $vids, $dpuId);
    }

    function get_is_san_direct( $clientID, $appID, $dpuID = NULL ) {
        return $this->bpl_call('bp_get_is_san_direct', $clientID, $appID, $dpuID );
    }

    function set_is_san_direct( $clientID, $appID, $sanDirectSupported, $dpuID = NULL ) {
        return $this->bpl_call('bp_set_is_san_direct', $clientID, $appID, $sanDirectSupported, $dpuID );
    }

    function get_hyperv_info($clientID, $use_cache, $dpuID = NULL) {
        return $this->bpl_call('bp_get_hyperv_info', $clientID, $use_cache, $dpuID);
    }

    function get_grandclient_hyperv_info($grandclientID) {
        return $this->bpl_call('bp_get_grandclient_hyperv_info', $grandclientID);
    }

    function get_grandclient_oracle_info($grandclientID, $appID) {
        return $this->bpl_call('bp_get_grandclient_oracle_info', $grandclientID, $appID);
    }

    function get_grandclient_sharepoint_info($grandclientID, $appID) {
        return $this->bpl_call('bp_get_grandclient_sharepoint_info', $grandclientID, $appID);
    }

    function get_hyperv_recovery_state($clientID, $instanceID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_hyperv_recovery_state', $clientID, $instanceID, $dpuID);
    }

    function get_hyperv_fl_recovery_clients($backupID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_hyperv_fl_recovery_clients', $backupID, $dpuID);
    }

    function get_hyperv_recovery_clients($instanceID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_hyperv_recovery_clients', $instanceID, $dpuID);
    }

    function get_virtual_bridge_network($dpuID = NULL) {
        return $this->bpl_call('bp_get_virtual_bridge_network', $dpuID);
    }

    function get_virtual_bridge($dpuID = NULL) {
        return $this->bpl_call('bp_get_virtual_bridge', $dpuID);
    }

    function get_nic_bridge($sNic,$dpuID = NULL) {
        return $this->bpl_call('bp_get_nic_bridge', $sNic, $dpuID);
    }

    function set_virtual_bridge($sNic, $dpuID = NULL) {
        return $this->bpl_call('bp_set_virtual_bridge_network', $sNic, $dpuID);
    }

    function add_virtual_bridge($sNic, $dpuID = NULL) {
        return $this->bpl_call('bp_add_virtual_bridge', $sNic, $dpuID);
    }

    function delete_virtual_bridge($sNic, $sBridge, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_virtual_bridge', $sNic, $sBridge, $dpuID);
    }

    function get_vf_space_available($dpuID = NULL) {
        return $this->bpl_call('bp_get_vf_space_available', $dpuID);
    }

    function get_vf_space_in_use($vfid, $vfType, $dpuID = NULL) {
        return $this->bpl_call('bp_get_vf_space_in_use', $vfid, $vfType, $dpuID);
    }

    function get_ad_cookie($username, $system_privileges) {
        return $this->bpl_call('bp_get_ad_cookie', $username, $system_privileges);
    }

    function send_notification(){ //$notification_id, $target, ...$args1-5)
        // Note: func_get_arg(0)   required: has to be the notification_id
        // Note: func_get_arg(1)   required: has to be the target (not in source/target, but the object affected by this event)
        // Note: func_get_arg(2-6) optional: upto 5 substitution parameters used as message format specifiers 
	$n=func_num_args(); 
	if ($n == 1) return $this->bpl_call('bp_send_notification', func_get_arg(0)); // should fail!!
	if ($n == 2) return $this->bpl_call('bp_send_notification', func_get_arg(0), func_get_arg(1));
	if ($n == 3) return $this->bpl_call('bp_send_notification', func_get_arg(0), func_get_arg(1), func_get_arg(2));
	if ($n == 4) return $this->bpl_call('bp_send_notification', func_get_arg(0), func_get_arg(1), func_get_arg(2), func_get_arg(3));
	if ($n == 5) return $this->bpl_call('bp_send_notification', func_get_arg(0), func_get_arg(1), func_get_arg(2), func_get_arg(3), func_get_arg(4));
	if ($n == 6) return $this->bpl_call('bp_send_notification', func_get_arg(0), func_get_arg(1), func_get_arg(2), func_get_arg(3), func_get_arg(4), func_get_arg(5));
	if ($n == 7) return $this->bpl_call('bp_send_notification', func_get_arg(0), func_get_arg(1), func_get_arg(2), func_get_arg(3), func_get_arg(4), func_get_arg(5), func_get_arg(6));
    }

    function search_archive_files($searchOptions, $clientName, $maxCount, $dpuID = NULL) {
        return $this->bpl_call('bp_search_archive_files', $searchOptions, $clientName, $maxCount, $dpuID);
    }

    function get_resource_license_limit($dpuID = NULL) {
        return $this->bpl_call('bp_get_resource_license_limit', $dpuID);
    }

    function get_resource_license_usage($dpuID = NULL) {
        return $this->bpl_call('bp_get_resource_license_usage', $dpuID);
    }

    function get_archive_client($dpuID = NULL) {
        return $this->bpl_call('bp_get_archive_client', $dpuID);
    }

    function get_archive_grandclients($dpuID) {
        return $this->bpl_call('bp_get_archive_grandclients', $dpuID);
    }

    function get_lvm_partition_info() {
        return $this->bpl_call('bp_get_lvm_partition_info' );
    }

    function get_lvm_info() {
        return $this->bpl_call('bp_get_lvm_info');
    }

    function get_lvm_disks() {
        return $this->bpl_call('bp_get_lvm_disks');
    }

    function grow_lvm_partitions($disk) {
        return $this->bpl_call('bp_grow_lvm_partitions', $disk);
    }

    function get_sharepoint_info($clientID, $appID, $use_cache, $dpuID = NULL) {
        return $this->bpl_call('bp_get_sharepoint_info', $clientID, $appID, $use_cache, $dpuID);
    }

    function get_oracle_info($clientID, $appID, $use_cache, $dpuID = NULL) {
        return $this->bpl_call('bp_get_oracle_info', $clientID, $appID, $use_cache, $dpuID);
    }

    function save_app_credentials_info($info_array, $dpuID = NULL) {
        return $this->bpl_call('bp_save_app_credentials_info', $info_array, $dpuID);
    }

    /* Credentials */
    function save_client_app_credentials( $clientID, $appID, $credentialID , $dpuID = NULL) {
        return $this->bpl_call('bp_save_client_app_credentials', $clientID, $appID, $credentialID, $dpuID);
    }

    function get_client_app_credentials( $clientID, $appID, $dpuID = NULL) {
        return $this->bpl_call('bp_get_client_app_credentials', $clientID, $appID, $dpuID);
    }

    function delete_client_app_credentials( $clientID, $appID, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_client_app_credentials', $clientID, $appID, $dpuID);
    }

    function delete_target_credentials( $target_name, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_target_credentials', $target_name, $dpuID);
    }

    function rae_backup_now($backupType, $client_info_array, $dpuID = NULL) {
        return $this->bpl_call('bp_rae_backup_now', $backupType, $client_info_array, $dpuID);
    }

    function rae_restore_application($backupIDs, $options_array, $dpuID = NULL) {
        $result = $this->bpl_call( 'bp_rae_restore_application', $backupIDs, $options_array, $dpuID );
        return $result;
    }


    function get_rae_app_schedule_info($id, $dpuID = NULL) {
        return $this->bpl_call('bp_get_rae_app_schedule_info', $id, $dpuID);
    }

    function save_rae_app_schedule_info($scheduleInfo, $dpuID = NULL) {
        return $this->bpl_call('bp_save_rae_app_schedule_info', $scheduleInfo, $dpuID);
    }


    function create_application_share($backupIDs, $path_name, $dpuID = NULL) {
        return $this->bpl_call('bp_create_application_share', $backupIDs, $path_name, $dpuID);
    }

    function get_application_share_status($dpuID = NULL) {
        return $this->bpl_call('bp_get_application_share_status', $dpuID);
    }

    function delete_application_share($instanceID, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_application_share', $instanceID, $dpuID);
    }

    function get_credentials_list($dpuID = NULL) {
        return $this->bpl_call('bp_get_credentials_list', $dpuID);
    }

    function get_credentials($cred_id, $dpuID = NULL) {
        //return $this->bpl_call('bp_get_credentials', $cred_id, $dpuID);
        return $this->obfuscatePasswordOutput('bp_get_credentials', $cred_id, $dpuID);
    }

    function get_named_credentials($dpuID = NULL) {
        return $this->bpl_call('bp_get_named_credentials', $dpuID);
    }

    function obfuscatePasswordOutput($functionName, $param, $dpuID) {
        global $Log;
        $this->set_cookie($this->getCookie());
        if ($param !== NULL) {
            $Log->enterFunction($functionName, $param, $dpuID);
            $result = $functionName($param, $dpuID);
        } else {
            $Log->enterFunction($functionName, $dpuID);
            $result = $functionName($dpuID);
        }
        // For security reasons, don't save the password in the log file.
        $logResult = $result;
        if ($result !== false) {
            if (array_key_exists('password', $logResult)) {
                $logResult['password'] = X_OUT;
            }
            if (isset($result['credentials'])) {
                if (array_key_exists('password', $result['credentials'])) {
                    $logResult['credentials']['password'] = X_OUT;
                }
            }
        }
        $Log->exitFunction($functionName, $logResult);
        $this->destroy_cookie();
        return $result;
    }

    function delete_credentials($cred_id, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_credentials', $cred_id, $dpuID);
    }

    // For security reasons, don't save the password to the logfile.
    function save_credentials($credentialInfo, $dpuID = NULL) {
        //return $this->bpl_call('bp_save_credentials', $credentialInfo, $dpuID);
        global $Log;
        $functionName = 'bp_save_credentials';
        $logInfo = $credentialInfo;
        if (array_key_exists('password', $logInfo)) {
            $logInfo['password'] = X_OUT;
        }
        $this->set_cookie($this->getCookie());
        if ( $dpuID === NULL ) {
            $Log->enterFunction($functionName, $logInfo);
        } else {
            $Log->enterFunction($functionName, $logInfo, $dpuID);
        }
        $result = $functionName($credentialInfo, $dpuID);
        $Log->exitFunction($functionName, $result);
        $this->destroy_cookie();
        return $result;
    }

    // For security reasons, don't save the password to the logfile.
    function save_target_credentials($target_name, $credentialInfo, $dpuID = NULL) {
        //return $this->bpl_call('bp_save_credentials', $credentialInfo, $dpuID);
        global $Log;
        $functionName = 'bp_save_target_credentials';
        $logInfo = $credentialInfo;
        if (array_key_exists('password', $logInfo)) {
            $logInfo['password'] = X_OUT;
        }
        $this->set_cookie($this->getCookie());
        $Log->enterFunction($functionName, $logInfo);
        $result = $functionName($target_name, $credentialInfo, $dpuID);
        $Log->exitFunction($functionName, $result);
        $this->destroy_cookie();
        return $result;
    }

    function get_default_credentials($dpuID = NULL) {
        //return $this->bpl_call('bp_get_default_credentials', $dpuID);
        return $this->obfuscatePasswordOutput('bp_get_default_credentials', NULL, $dpuID);
    }

    function get_vmware_credentials($uuid, $dpuID = NULL) {
        return $this->bpl_call('bp_get_vmware_credentials', $uuid, $dpuID);
    }

    function agent_update_available($cid, $sysID = NULL) {
        return $this->bpl_call('bp_agent_update_available', $cid, $sysID);

    }

    function push_agent($cid, $address, $osType, $credID, $dpuID = NULL) {
        return $this->bpl_call('bp_push_agent', $cid, $address, $osType, $credID, $dpuID);
    }

    function replication_supported($dpuID = NULL) {
        return $this->bpl_call('bp_replication_supported', $dpuID);
    }

    function get_replication_queue($filter, $dpuID = NULL) {
        return $this->bpl_call('bp_get_replication_queue', $filter, $dpuID);
    }

    function set_replication_queue_strategy($strategy, $dpuID = NULL) {
        return $this->bpl_call('bp_set_replication_queue_strategy', $strategy, $dpuID);
    }

    function delete_from_replication_queue($backups, $dpuID = NULL) {
        return $this->bpl_call('bp_delete_from_replication_queue', $backups, $dpuID);
    }

    function add_to_replication_queue($backup, $target, $dpuID = NULL) {
        return $this->bpl_call('bp_add_to_replication_queue', $backup, $target, $dpuID);
    }

    function is_backup_in_replication_queue($backup, $target, $dpuID = NULL) {
        return $this->bpl_call('bp_is_backup_in_replication_queue', $backup, $target, $dpuID);
    }

    function update_replication_queue($backupOrder, $count, $dpuID = NULL) {
        return $this->bpl_call('bp_update_replication_queue', $backupOrder, $count, $dpuID);
    }

    function get_replicated_identities($dpuID = NULL) {
        return $this->bpl_call('bp_get_replicated_identities', $dpuID);
    }

    function get_all_last_backups($clientList, $dpuID = NULL) {
        return $this->bpl_call('bp_get_all_last_backups', $clientList, $dpuID);
    }

    function restore_appliance_metadata($filter) {
        return $this->bpl_call('bp_restore_appliance_metadata', $filter);
    }

    function setup_dr_target($targetName) {
        return $this->bpl_call('bp_setup_dr_target', $targetName);
    }

    function get_dr_from_archive_mode($systemMetadataPath, $dpuID = NULL) {
        return $this->bpl_call('bp_get_dr_from_archive_mode', $systemMetadataPath, $dpuID);
    }

    function get_grandclient_retention_info($filter) {
        return $this->bpl_call('bp_get_grandclient_retention_info', $filter);
    }

    function get_replicated_lastfull_info($filter) {
        return $this->bpl_call('bp_get_replicated_lastfull_info', $filter);
    }

    function chown_backup($fromClientID, $toClientID, $backupID) {
        return $this->bpl_call('bp_chown_backup', $fromClientID, $toClientID, $backupID);
    }

    function get_grandclient_restores_list() {
        return $this->bpl_call('bp_get_grandclient_restores_list');
    }

    function get_grandclient_restores($clientID) {
        return $this->bpl_call('bp_get_grandclient_restores', $clientID);
    }

    function save_replication_report_time($hour, $minute, $dpuID) {
        return $this->bpl_call('bp_save_replication_report_time', $hour, $minute, $dpuID);
    }

    function get_psa_tools($dpuID=NULL) {
        return $this->bpl_call('bp_get_psa_tools', $dpuID);
    }

    function send_test_psa_ticket($id, $dpuID=NULL) {
        return $this->bpl_call('bp_send_test_psa_ticket', $id, $dpuID);
    }

    function get_psa_history($dpuID=NULL) {
        return $this->bpl_call('bp_get_psa_history', $dpuID);
    }

    function get_psa_config($dpuID=NULL) {
        return $this->bpl_call('bp_get_psa_config', $dpuID);
    }

    function save_psa_config($psaConfig, $dpuID=NULL) {
        return $this->bpl_call('bp_save_psa_config', $psaConfig, $dpuID);
    }

    function delete_psa_config($psaID, $dpuID=NULL) {
        return $this->bpl_call('bp_delete_psa_config', $psaID, $dpuID);
    }

    function save_psa_credentials($psaID, $credentialID, $dpuID=NULL) {
        return $this->bpl_call('bp_save_psa_credentials', $psaID, $credentialID, $dpuID);
    }

    function get_tape_library_info($mediaName, $dpuID=NULL) {
        return $this->bpl_call('bp_get_tape_library_info', $mediaName, $dpuID);
    }

    function save_legalhold_per_backup($backupID, $legal_hold_days, $dpuID=NULL) {
        return $this->bpl_call('bp_save_legalhold_per_backup', $backupID, $legal_hold_days, $dpuID);
    }

    function get_legalhold_backup_info($backupIDs, $dpuID=NULL) {
        return $this->bpl_call('bp_get_legalhold_backup_info', $backupIDs, $dpuID);
    }

    function get_legalhold_backups($filter, $dpuID=NULL) {
        return $this->bpl_call('bp_get_legalhold_backups', $filter, $dpuID);
    }

    function legalhold_supported($dpuID=NULL) {
        return $this->bpl_call('bp_legalhold_supported', $dpuID);
    }

    function get_ucssp_info($clientID, $appID, $use_cache, $dpuID = NULL) {
        return $this->bpl_call('bp_get_ucssp_info', $clientID, $appID, $use_cache, $dpuID);
    }

    function get_grandclient_ucssp_info($grandclientID, $appID) {
        return $this->bpl_call('bp_get_grandclient_ucssp_info', $grandclientID, $appID);
    }

    function get_ndmpvolume_info($clientID, $use_cache, $dpuID = NULL) {
        return $this->bpl_call('bp_get_ndmpvolume_info', $clientID, $use_cache, $dpuID);
    }

    function get_grandclient_ndmpvolume_info( $grandclientID ) {
        return $this->bpl_call('bp_get_grandclient_ndmpvolume_info', $grandclientID);
    }

    function get_ndmp_restore_targets($backupID, $systemID = NULL) {
        return $this->bpl_call('bp_get_ndmp_restore_targets', $backupID, $systemID);
    }

    function get_ndmp_restore_target_volumes($NASID, $backupID, $systemID = NULL) {
        return $this->bpl_call('bp_get_ndmp_restore_target_volumes', $NASID, $backupID, $systemID);
    }

    function get_hypervisor_network_switches($hypervisor_type, $hypervisor, $dpuID = NULL) {
        return $this->bpl_call('bp_get_hypervisor_network_switches', $hypervisor_type, $hypervisor, $dpuID);
    }

    function get_hypervisor_network_switches_info($hypervisor_type, $hypervisor, $dpuID = NULL) {
        return $this->bpl_call('bp_get_hypervisor_network_switches_info', $hypervisor_type, $hypervisor, $dpuID);
    }

    function get_last_backup_per_instance($instances, $systemID = NULL) {
        return $this->bpl_call('bp_get_last_backup_per_instance', $instances, $systemID);
    }

    function get_protected_vmware_vms($grandclients, $systemID) {
        return $this->bpl_call('bp_get_protected_vmware_vms', $grandclients, $systemID);
    }

    function get_protected_hyperv_vms($grandclients, $systemID) {
        return $this->bpl_call('bp_get_protected_hyperv_vms', $grandclients, $systemID);
    }

    function get_protected_block_assets($grandclients, $systemID) {
        return $this->bpl_call('bp_get_protected_block_assets', $grandclients, $systemID);
    }

    function set_certification_status($cert_status, $backup_ids) {
        return $this->bpl_call('bp_set_certification_status', $cert_status, $backup_ids);
    }

    function get_restorable_backups_per_instance($instances, $interval, $systemID = NULL) {
        return $this->bpl_call('bp_get_restorable_backups_per_instance', $instances, $interval, $systemID);
    }
    function add_client($json) {
        return $this->bpl_call('rest_add_client', $json);
    }
    function get_backups($json) {
        return $this->bpl_call('rest_get_backups', $json);
    }
    function get_summary_current($sid = NULL){
        return $this->bpl_call('rest_get_summary_current', $sid);
    }
    function get_summary_counts($subsystem, $sid = NULL){
        return $this->bpl_call('rest_get_summary_counts', $subsystem, $sid);
    }
    function get_summary_days($subsystem, $days, $sid = NULL){
        return $this->bpl_call('rest_get_summary_days', $subsystem, $days, $sid);
    }
    function get_target_summary_days($subsystem, $days, $sid = NULL){
        return $this->bpl_call('rest_get_target_summary_days', $subsystem, $days, $sid);
    }
    function logout($cookie){
        return $this->bpl_call('bp_logout', $cookie);
    }
    function get_preferences($nvpName = NULL, $itemName = NULL){
        return $this->bpl_call('rest_get_preferences', $nvpName, $itemName);
    }
    function save_preferences($nvpName, $nvpArray){
        return $this->bpl_call('rest_save_preferences', $nvpName, $nvpArray);
    }
    function close_alert($alert_id, $sid = NULL){
        return $this->bpl_call('bp_close_alert', $alert_id, $sid);
    }
    function get_hyperv_servers_for_ir($instanceID, $sid = NULL) {
        return $this->bpl_call('bp_get_hyperv_servers_for_ir', $instanceID, $sid);
    }
    function get_hyperv_ir_status($dpuID = NULL) {
        return $this->bpl_call('bp_hyperv_ir_status', $dpuID);
    }
    function hyperv_ir_destroy($instanceID, $force, $dpuID = NULL) {
        return $this->bpl_call('bp_hyperv_ir_destroy', $instanceID, $force, $dpuID);
    }
    function hyperv_ir_start($HVclientName, $vmName, $storage_location, $restoreAddress, $audit, $backups, $poweron, $switch, $dpuID = NULL) {
        return $this->bpl_call('bp_hyperv_ir_start', $HVclientName, $vmName, $storage_location, $restoreAddress, $audit, $backups,
            $poweron, $switch, $dpuID);
    }
    function put_inventory($filter = NULL, $systemID = NULL) {
        return $this->bpl_call('rest_put_inventory', $filter, $systemID);
    }

    function get_systems($verbose = false, $filter = NULL){
        $systemList = false;
        if ( $filter === NULL ) {
            $systemList = $this->bpl_call('rest_get_systems', $verbose);
        } else {
            $systemList = $this->bpl_call('rest_get_systems', $verbose, $filter);
        }
        return $systemList;
    }
    function get_file_level_info($client_id, $systemID = NULL){
        return $this->bpl_call('bp_get_file_level_info', $client_id, $systemID);
    }
    function get_update_history($filter = NULL) {
        return $this->bpl_call('rest_get_update_history_report', $filter);
    }
    function get_load_history($filter = NULL) {
        return $this->bpl_call('rest_get_load_report', $filter);
    }
    function get_appinst_info($instance_ids, $sid = NULL) {
        return $this->bpl_call('bp_get_appinst_info', $instance_ids, $sid);
    }
    function rest_get_storage_info($storageID, $sid = NULL) {
        return $this->bpl_call('rest_get_storage_info', $storageID, $sid);
    }
    function rest_delete_storage($storageID, $sid = NULL) {
        return $this->bpl_call('rest_delete_storage', $storageID, $sid);
    }
    function rest_save_storage_info($data, $sid = NULL) {
        return $this->bpl_save_no_passwd('rest_save_storage_info', $data, $sid);
    }

    function get_hyperv_servers_for_wir($os_id, $systemID = NULL) {
        return $this->bpl_call('bp_get_hyperv_servers_for_wir', $os_id, $systemID);
    }

    function get_hyperv_storage($clientID, $systemID = NULL) {
        return $this->bpl_call('bp_get_hyperv_storage', $clientID, $systemID);
    }
    function rest_get_available_disks($systemID = NULL) {
        return $this->bpl_call('rest_get_available_disks', $systemID);
    }

    function get_capabilities($capability, $systemID = NULL){
        return $this->bpl_call('bp_get_capabilities', $capability, $systemID);
    }

    function get_forum_user_credentials($userID, $systemID = NULL) {
        return $this->bpl_call('bp_get_forum_user_credentials', $userID, $systemID);
    }

    function save_forum_user_credentials($userID, $salesforceID, $credential_info, $systemID = NULL) {
        return $this->bpl_call('bp_save_forum_user_credentials', $userID, $salesforceID, $credential_info, $systemID);
    }

    function delete_forum_user_credentials($userID, $delete_credential, $systemID = NULL) {
        return $this->bpl_call('bp_delete_forum_user_credentials', $userID, $delete_credential, $systemID);
    }

    function disable_user_login($message, $systemID = NULL) {
        return $this->bpl_call('bp_disable_user_login', $message,$systemID);
    }
    function reenable_user_login($systemID = NULL) {
        return $this->bpl_call('bp_reenable_user_login', $systemID);
    }
    function get_replication_active_job_info($filter = NULL) {
        return $this->bpl_call('bp_get_replication_active_job_info', $filter);
    }
    function get_replication_job_history_info($filter = NULL) {
        return $this->bpl_call('bp_get_replication_job_history_info', $filter);
    }

    function post_replication_source($request_id, $inputsArray) {
        return $this->bpl_call('post_replication_source', $request_id, $inputsArray);
    }

    function post_replication_target($type, $optionsArray, $systemID = NULL) {
        return $this->bpl_call('post_replication_target', $type, $optionsArray, $systemID);
    }


    function is_gfs_supported($systemID = NULL) {
        return $this->bpl_call('bp_is_gfs_supported', $systemID);
    }

    function get_gfs_policy($filter, $systemID = NULL) {
        return $this->bpl_call('bp_get_gfs_policy', $filter, $systemID);
    }

    function save_gfs_policy($gfs_settings, $systemID = NULL) {
        return $this->bpl_call('bp_save_gfs_policy', $gfs_settings, $systemID);
    }

    function delete_gfs_policy($policyID, $systemID = NULL) {
        return $this->bpl_call('bp_delete_gfs_policy', $policyID, $systemID);
    }

    function get_retention_strategy($systemID = NULL) {
        return $this->bpl_call('bp_get_retention_strategy', $systemID);
    }

    function set_retention_strategy($strategy, $systemID = NULL) {
        return $this->bpl_call('bp_set_retention_strategy', $strategy, $systemID);
    }

    function get_gfs_retention($filter, $systemID = NULL) {
        return $this->bpl_call('bp_get_gfs_retention', $filter, $systemID);
    }

    function apply_gfs_retention($settings, $systemID = NULL) {
        return $this->bpl_call('bp_apply_gfs_retention', $settings, $systemID);
    }

    function get_gfs_affected_backups($settings, $systemID = NULL ) {
        return $this->bpl_call('bp_get_gfs_affected_backups', $settings, $systemID);
    }

    function get_replication_targets($systemID = NULL) {
        return $this->bpl_call('get_replication_targets', $systemID);
    }
    function suspend_replication($sourceID) { //Suspension from the target
        return $this->bpl_call('bp_suspend_replication', $sourceID);
    }
    function resume_replication($sourceID) { //Resume from the target
        return $this->bpl_call('bp_resume_replication', $sourceID);
    }
    function is_replication_suspended($sourceHostname, $systemID = NULL) { //Suspension from the target
        return $this->bpl_call('bp_is_replication_suspended', $sourceHostname, $systemID);
    }
    function get_replication_pending($include_rejected = false) { //Get the list of systems that are currently trying to setup replication to that system
        return $this->bpl_call('get_replication_pending', $include_rejected);
    }
    function get_backup_storage_name($backup_ids, $sid){
        return $this->bpl_call('bp_get_backup_storage_name', $backup_ids, $sid);
    }

    function get_storage_for_device($device_name, $systemID = NULL) {
        return $this->bpl_call('bp_get_storage_for_device', $device_name, $systemID);
    }
    function get_device_for_storage($storage_name, $systemID = NULL) {
        return $this->bpl_call('bp_get_device_for_storage', $storage_name, $systemID);
    }

    function save_replication_joborder_info($jobOrderInfo, $sid = NULL){
        return $this->bpl_call('bp_save_replication_joborder_info', $jobOrderInfo, $sid);
    }
    function get_replication_joborder_list($sid = NULL){
        return $this->bpl_call('bp_get_replication_joborder_list', $sid);
    }
    function get_replication_joborder_info($replicationJoborderID, $sid = NULL){
        return $this->bpl_call('bp_get_replication_joborder_info', $replicationJoborderID, $sid);
    }
    function delete_replication_joborder($replicationJoborderID, $sid = NULL){
        return $this->bpl_call('bp_delete_replication_joborder', $replicationJoborderID, $sid);
    }
    function disable_replication_joborder($replicationJoborderID, $sid = NULL){
        return $this->bpl_call('bp_disable_replication_joborder', $replicationJoborderID, $sid);
    }
    function enable_replication_joborder($replicationJoborderID, $sid = NULL){
        return $this->bpl_call('bp_enable_replication_joborder', $replicationJoborderID, $sid);
    }
    function get_replicating_instances_not_in_joborders($sid = NULL){
        return $this->bpl_call('bp_get_replicating_instances_not_in_joborders', $sid);
    }
    function use_stateless($sid = NULL){
        return $this->bpl_call('bp_need_stateless', $sid);
    }
    function get_xen_vm_info($clientID, $use_cache, $get_disks, $sid = NULL){
        return $this->bpl_call('bp_get_xen_vm_info', $clientID, $use_cache, $get_disks, $sid);
    }
    function get_grandclient_xen_vm_info($grandclientID) {
        return $this->bpl_call('bp_get_grandclient_xen_vm_info', $grandclientID);
    }
    // retrieves disks for a given VM
    function get_xen_vm_disks( $instanceID, $use_cache, $dpuID = NULL ) {
        return $this->bpl_call('bp_get_xen_vm_disks', $instanceID, $use_cache, $dpuID );
    }
    // sets disk exclusions for a given VM if any
    function set_xen_vm_disks( $diskExcludeSettings, $dpuID = NULL ) {
        return $this->bpl_call('bp_set_xen_vm_disks', $diskExcludeSettings, $dpuID );
    }
    function get_xen_restore_targets($sid = NULL){
        return $this->bpl_call('bp_get_xen_restore_targets', $sid);
    }
    function get_ahv_vm_info($clientID, $use_cache, $get_disks, $sid = NULL){
        return $this->bpl_call('bp_get_ahv_vm_info', $clientID, $use_cache, $get_disks, $sid);
    }
    function get_grandclient_ahv_vm_info($grandclientID) {
        return $this->bpl_call('bp_get_grandclient_ahv_vm_info', $grandclientID);
    }
    function get_block_info($clientID, $sid = NULL) {
        return $this->bpl_call('bp_get_block_info', $clientID, $sid);
    }
    function get_grandclient_block_info($grandclientID) {
        return $this->bpl_call('bp_get_grandclient_block_info', $grandclientID);
    }
    // retrieves disks for a given VM
    function get_ahv_vm_disks($instanceID, $use_cache, $dpuID = NULL ) {
        return $this->bpl_call('bp_get_ahv_vm_disks', $instanceID, $use_cache, $dpuID );
    }
    // sets disk exclusions for a given VM if any
    function set_ahv_vm_disks( $diskExcludeSettings, $dpuID = NULL ) {
        return $this->bpl_call('bp_set_ahv_vm_disks', $diskExcludeSettings, $dpuID );
    }
    function get_ahv_restore_targets($sid = NULL){
        return $this->bpl_call('bp_get_ahv_restore_targets', $sid);
    }
    function generate_schedule_regex_list($host, $regexList, $useCache, $dpuID = NULL) {
        return $this->bpl_call('bp_generate_schedule_regex_list', $host, $regexList, $useCache, $dpuID);
    }
    function get_default_ui($dpuID = NULL) {
        return $this->bpl_call('bp_get_default_ui', $dpuID);
    }
    function set_default_ui($ui, $dpuID = NULL) {
        return $this->bpl_call('bp_set_default_ui', $ui, $dpuID);
    }

    function refresh_summary_counts($days, $sid = NULL) {
        return $this->bpl_call('rest_refresh_summary_counts', $days, $sid);
    }

    function create_zipfile($startDir, $fileList, $deleteFiles) {
        return $this->bpl_call('bp_create_zipfile', $startDir, $fileList, $deleteFiles);
    }

    function get_target_credentials($targetName, $sid = NULL){
        return $this->bpl_call('bp_get_target_credentials', $targetName, $sid);
    }

    function get_credentials_for_rdr($credentialID){
        return $this->bpl_call('bp_get_credentials_for_rdr',$credentialID);
    }

    function configure_remote_rdr_manager() {
        return $this->bpl_call('bp_configure_remote_rdr_manager');
    }

    function configure_remote_rdr_managee($remote_sid, $managerName, $pass = NULL) {
        return $this->bpl_call('bp_configure_remote_rdr_managee', $managerName, $pass, $remote_sid);
    }

    function get_ir_vm_network_info($hypervisortype, $instanceID, $sid){
        return $this->bpl_call('bp_get_ir_vm_network_info', $hypervisortype, $instanceID, $sid);
    }

    function get_target_token($targetName, $sid = NULL){
        $PrevLogResults      = $this->m_bLogResults;
        $this->m_bLogResults = false;
        $results             = $this->bpl_call('bp_get_target_token', $targetName, $sid);
        $this->m_bLogResults = $PrevLogResults;
        return $results;
    }

    function save_target_token($targetName, $token, $remote_sid, $sid = NULL){
        return $this->bpl_call('bp_save_target_token', $targetName, $token, $remote_sid, $sid);
    }

    //Quiesce APIs

    function is_quiesce_supported( $sid = NULL){
        return $this->bpl_call('bp_quiesce_supported', $sid);
    }

    function get_default_quiesce_setting($sid = NULL){
        return $this->bpl_call('bp_get_default_quiesce_setting',$sid);
    }

    function set_default_quiesce_setting($quiesceSetting, $updateVMs , $sid = NULL){
        return $this->bpl_call('bp_set_default_quiesce_setting', $quiesceSetting, $updateVMs, $sid);
    }

    function set_quiesce_for_hypervisor_vms($app_type, $hypervisor, $quiesceSetting, $dpuID = NULL) {
        return $this->bpl_call('bp_set_quiesce_for_hypervisor_vms', $app_type, $hypervisor, $quiesceSetting, $dpuID);
    }

    function save_quiesce_settings($quiesce_info, $sid = NULL){
        return $this->bpl_call('bp_save_quiesce_settings', $quiesce_info, $sid);
    }

    function bind_instance_credentials($credential_instance_info, $sid = NULL){
        return $this->bpl_call('bp_bind_instance_credentials', $credential_instance_info, $sid);
    }

    function unbind_instance_credentials($instancesString, $sid = NULL){
        return $this->bpl_call('bp_unbind_instance_credentials', $instancesString, $sid);
    }

    function get_optimize($type, $sid = NULL) {
        return $this->bpl_call('bp_get_optimize', $type, $sid);
    }
    function set_optimize($inputArray, $sid = NULL) {
        return $this->bpl_call('bp_set_optimize', $inputArray, $sid);
    }
    function move_database($devname = NULL, $sid = NULL) {
        return $this->bpl_call('bp_move_database', $devname, $sid);
    }

    function create_proxy_mount($instanceID, $path) {
        return $this->bpl_call('bp_create_proxy_mount', $instanceID, $path);
    }

    function get_proxy_mount_status() {
        return $this->bpl_call('bp_get_proxy_mount_status');
    }

    function get_proxy_mount_path($instanceID) {
        return $this->bpl_call('bp_get_proxy_mount_path', $instanceID);
    }

    function destroy_proxy_mount($instanceID) {
        return $this->bpl_call('bp_destroy_proxy_mount', $instanceID);
    }

    // Qemu Instant Recovery APIs
    function qemu_ir_supported($dpuID = NULL) {
        return $this->bpl_call('bp_qemu_ir_supported', $dpuID);
    }

    function qemu_ir_start($vmName, $audit, $power, $backups, $dpuID = NULL) {
        return $this->bpl_call('bp_qemu_ir_start', $vmName, $audit, $power, $backups, $dpuID);
    }

    function get_qemu_ir_status($dpuID = NULL) {
        return $this->bpl_call('bp_qemu_ir_status', $dpuID);
    }

    function qemu_ir_destroy($appID, $force, $dpuID = NULL) {
        return $this->bpl_call('bp_qemu_ir_destroy', $appID, $force, $dpuID);
    }


    function get_rflr_job_list($sourceSystemID, $sid = NULL) {
        return $this->bpl_call('bp_get_rflr_job_list', $sourceSystemID, $sid);
    }

    function get_rflr_job_info($jobID, $sid = NULL) {
        return $this->bpl_call('bp_get_rflr_job_info', $jobID, $sid);
    }

    function set_rflr_job_info($rflrJobInfo, $sid = NULL) {
        return $this->bpl_call('bp_set_rflr_job_info', $rflrJobInfo, $sid);
    }

    function get_rflr_device_info($sourceSystemID, $sid = NULL) {
        return $this->bpl_call('bp_get_rflr_device_info', $sourceSystemID, $sid);
    }

    function get_proxy_tunnel_path($sourceSystemID) {
        return $this->bpl_call('bp_get_proxy_tunnel_path', $sourceSystemID);
    }

    function create_proxy_tunnel($sourceSystemID, $path, $src_is_true, $tgt_name = NULL) {
        return $this->bpl_call('bp_create_proxy_tunnel', $sourceSystemID, $path, $src_is_true, $tgt_name);
    }

    function destroy_proxy_tunnel($sourceSystemID) {
        return $this->bpl_call('bp_destroy_proxy_tunnel', $sourceSystemID);
    }

    function get_proxy_tunnel_url($sourceSystemID) {
        return $this->bpl_call('bp_get_proxy_tunnel_url', $sourceSystemID);
    }

    function get_dl_url($sourceSystemID) {
        return $this->bpl_call('bp_get_dl_url', $sourceSystemID);
    }

    function create_dl_url($sourceSystemID, $path) {
        return $this->bpl_call('bp_create_dl_url', $sourceSystemID, $path);
    }

    function destroy_dl_url($sourceSystemID) {
        return $this->bpl_call('bp_destroy_dl_url', $sourceSystemID);
    }

    function block_backup_supported($dpuID = NULL) {
        return $this->bpl_call('bp_block_backup_supported', $dpuID);
    }

    //$clientIDs is a comma separated string
    function get_app_aware_flag($clientIDs, $dpuID = NULL) {
        return $this->bpl_call('bp_get_app_aware_flag', $clientIDs, $dpuID);
    }

    function save_app_aware_flag($appAwareConfig, $dpuID = NULL) {
        return $this->bpl_call('bp_save_app_aware_flag', $appAwareConfig, $dpuID);
    }

    // RDR
    function rdr_supported($sid = NULL) {
        return $this->bpl_call('bp_rdr_supported', $sid);
    }

    function replica_vms_supported($sid = NULL) {
        return $this->bpl_call('bp_replica_vms_supported', $sid);
    }

    function get_replica_candidates($filter, $grandClients, $sid) {
        return $this->bpl_call('bp_get_replica_candidates', $filter, $grandClients, $sid);
    }

    function get_replica_vm_list($hypervisorType, $sid = NULL) {
        return $this->bpl_call('bp_get_replica_vm_list', $hypervisorType, $sid);
    }

    function get_replica_vm_info($replicaID, $sid = NULL) {
        return $this->bpl_call('bp_get_replica_vm', $replicaID, $sid);
    }

    function get_replica_vm_state($replicaID, $sid = NULL) {
        return $this->bpl_call('bp_get_replica_vm_state', $replicaID, $sid);
    }

    function get_replica_snapshots($replicaID, $sid = NULL) {
        return $this->bpl_call('bp_get_replica_snapshots', $replicaID, $sid);
    }

    function save_replica_vm($replicaInfo, $sid = NULL) {
        return $this->bpl_call('bp_save_replica_vm', $replicaInfo, $sid);
    }

    function audit_replica_vm($replicaID, $bStart, $pit_snap, $powerOn = true) {
        return $this->bpl_call('bp_audit_replica_vm', $replicaID, $bStart, $pit_snap, $powerOn);
    }

    function run_replica_vm($replicaID, $bStart, $pit_snap, $sid = NULL) {
        return $this->bpl_call('bp_run_replica_vm', $replicaID, $bStart, $pit_snap, $sid);
    }

    function delete_replica_vm($replicaID, $bDeleteFromHypervisor, $sid = NULL) {
        return $this->bpl_call('bp_delete_replica_vm', $replicaID, $bDeleteFromHypervisor, $sid);
    }

    function get_replica_restore_backlog($replicaID, $sid = NULL) {
        return $this->bpl_call('bp_get_replica_restore_backlog', $replicaID, $sid);
    }

    function get_max_replica_snapshots($sid = NULL) {
        return $this->bpl_call('bp_get_max_replica_snapshots', $sid);
    }

    function save_max_replica_snapshots($hypervisorType, $max_snaps, $sid = NULL) {
        return $this->bpl_call('bp_save_max_replica_snapshots', $hypervisorType, $max_snaps, $sid);
    }

    function disable_replica_restores($replicaID, $bDisable, $sid = NULL) {
        return $this->bpl_call('bp_disable_replica_restores', $replicaID, $bDisable, $sid);
    }

    function get_last_replica_restore($replicaID, $sid = NULL) {
        return $this->bpl_call('bp_get_last_replica_restore', $replicaID, $sid);
    }

    function get_guest_os_from_instance_ids($instanceIDs, $sid = NULL){
        return $this->bpl_call('bp_get_guest_os_from_instance_ids', $instanceIDs, $sid);
    }
    function get_backups_risk_level($backup_ids, $sid = NULL){
        return $this->bpl_call('bp_get_backups_risk_level', $backup_ids, $sid);
    }
    function get_gfs_retention_points($instance_id, $sid = NULL){
        return $this->bpl_call('bp_get_gfs_retention_points', $instance_id, $sid);
    }
    function one_to_many_supported($sid = NULL){
        return $this->bpl_call('bp_one_to_many_supported', $sid);
    }

}
?>
