<?php

class Authenticate
{
    private $BP;
     
    public function __construct($BP)
    {
		$this->BP = $BP;
        $this->now = time();

        require_once('activedirectory.php');
        $this->AD = new ActiveDirectory($this->BP);

        $this->Roles = null;

        $rootdir = $_SERVER['DOCUMENT_ROOT'] . "/recoveryconsole/";
        $ldapScript = $rootdir . "system/ldap.php";

        require_once $ldapScript;
        $this->ldap = new LDAPFunctions();
    }

public function login($data)
{
    if (Functions::supportsRoles()) {
        $this->Roles = new Roles($this->BP);
    }

    $this->BP->bypass_cookie(2);

    $localID = $this->BP->get_local_system_id();
    $systemInfo = $this->BP->get_system_info($localID);
    $sysName = $systemInfo['name'];
    $sid = $systemInfo['id'];

    $username = isset($data['username']) ? $data['username'] : null;
    $password = isset($data['password']) ? $data['password'] : null;

    $ADLogin = $this->checkADLogin($username, $password, $sid);
    if ($ADLogin !== false) {
        $login = $ADLogin;
    } else {
        $applianceUserLogin = $this->applianceAuthenticate($username, $password, $sid);
        $login = $applianceUserLogin;
    }
    return $login;
}

    function checkADLogin($username, $password, $sid)
    {
        $login = false;
        $activeDirectory = $this->AD->get($sid);
        if ($activeDirectory !== false) {
            if (array_key_exists("data", $activeDirectory)) {
                $data = $activeDirectory['data'];
            }
            $ADsettings = array();
            foreach ($data as $ADsection) {
                $key = $ADsection['field'];
                $value = $ADsection['value'];
                $result = array($key => $value);
                $ADsettings = array_merge($ADsettings, $result);
            }
            $useAD = $ADsettings['AD_AuthenticationEnabled'] == 'Yes' ? true : false;
            $server = $ADsettings['AD_ServerName'];
            $fqdn = $ADsettings['AD_DomainName'];
            $useADSSL = $ADsettings['AD_UseSSL'] == 'Yes' ? true : false;
            $SuperUserGroup = $ADsettings['AD_Superuser'];
            $AdministratorGroup = $ADsettings['AD_Admin'];
            $manageGroup = $ADsettings['AD_Manage'];
            $monitorGroup = $ADsettings['AD_Monitor'];

            if ($useAD) {
                global $Log;
                $Log->writeVariableDBG("useAD?" . $useAD);
                if ($fqdn == NULL) {
                    $message = "Active Directory Server not specified.";
                    $Log->writeVariable($message);
                    $this->BP->send_notification(125, "login", $message);
                }
                $result = $this->LDAPAuthenticate($username, $password, $fqdn, $useADSSL);

                if (is_array($result) && $result !== false) {
                    $users = $this->getADPermissions($result);
                    if ($users !== false) {
                        $ad_permissions = $this->mapPermissions($users, $SuperUserGroup, $AdministratorGroup, $manageGroup, $monitorGroup);
                    }
                    $ad_cookie = $this->BP->get_ad_cookie($result['rrclogin'], $ad_permissions);

                    if ($ad_cookie !== false) {
                        $result = $this->BP->set_cookie($ad_cookie, true);
                        if ($result !== false) {
                            $login = $this->buildOutput($ad_cookie, $sid);
                        } else {
                            $this->BP->send_notification(125, "login", "Active Directory Authentication Failed.");
                            $login = $this->applianceAuthenticate($username, $password, $sid);
                        }
                    }
                }
            } else {
                $login = $this->applianceAuthenticate($username, $password, $sid);
            }
        }
            return $login;
    }

    function applianceAuthenticate($username, $password, $sid){
        $authCookie = $this->BP->authenticate($username, $password);
        if ($authCookie !== false) {
            $result = $this->BP->set_cookie($authCookie, true);
            if ($result !== false) {
                $login = $this->buildOutput($authCookie, $sid);
            } else {
                $login = false;
            }
        } else {
            $login = false;
        }

        return $login;
    }

    function buildOutput($authCookie, $sid) {
        global $Log;
        $userInfo = $this->BP->getUser();
        $monitor = false;
        if ($userInfo !== false) {
            $vaultUser = isset($userInfo['vault_user']) ? $userInfo['vault_user'] : false;
            if(!$vaultUser){
                $testPrivilege = $this->BP->get_calendar_list();
                if ($testPrivilege == false){
                    $errorString = $this->BP->getError();
                    $Log->writeVariable($errorString);
                } else {
                    $errorString = "";
                }
                if (strstr($errorString, "Insufficient privileges") !== false) {
                    $monitor = true;
                }
                $Log->writeVariableDBG("monitor " . $monitor);
            }

            $id = isset($userInfo['id']) ? $userInfo['id'] : null;
            $adUser = isset($userInfo['ADuser']) ? $userInfo['ADuser'] :  false;
            $superUser = isset($userInfo['superuser']) ? $userInfo['superuser'] : false;
            $administrator = isset($userInfo['administrator']) ? $userInfo['administrator'] : false;
            $manager = !($superUser || $administrator || $monitor);
            $target = isset($userInfo['target']) ? $userInfo['target'] : false;

            if(isset($id) && $id == -1){
                $this->BP->send_notification(124, "login", "Active Directory user");
            } else {
                $this->BP->send_notification(124, "login", "");
            }

            $sourceID = isset($userInfo['source_id']) ? $userInfo['source_id'] : -1;

            $timeout=$this->BP->get_ini_value("CMC", "SessionTimeout", $sid);
            $data = array(
                'id' => $id,
                'superuser' => $superUser,
                'administrator' => $administrator,
                'manager' => $manager,
                'monitor' => $monitor,
                'ad_user' => $adUser,
                'target' => $target,
                'timeout' => intval($timeout),
                'auth_token' => $authCookie,
                'vault_user' => $vaultUser,
                'source_id' => $sourceID
            );

            // add role information if available.
            if ($manager) {
                if ($this->Roles != null) {
                    $roleInfo = $this->Roles->get($userInfo['name'], $adUser);
                    if (isset($roleInfo['name'])) {
                        $data['role_name'] = $roleInfo['name'];
                        $this->BP->send_notification(124, "login", "Role-based user: " . $userInfo['name'] . ", role: " . $roleInfo['name']);
                    }
                    if (isset($roleInfo['scope'])) {
                        $data['role_scope'] = $roleInfo['scope'];
                    }
                    if (isset($roleInfo['recover_options'])) {
                        $data['role_recover_options'] = $roleInfo['recover_options'];
                    }
                }
            }
        } else {
            $data = false;
        }

        return $data;
    }

public function logout($data){
    $cookie = isset($data['cookie']) ? $data['cookie'] : null;
    $result = $this->BP->logout($cookie);
    if ($result !== false){
        $result = $this->BP->destroy_cookie();
    }

    return $result;
}

    function LDAPAuthenticate($username, $password, $fqdn, $ADssl) {
   //     print_r("in ldapauth");
        $result = $this->ldap->authenticate($username, $password, $fqdn, $ADssl);

        if ($result !== false) {
            return $result;
        } else {
            return false;
        }
    }

    function getADPermissions($result){
        global $Log;
        $adUsers = array();

        foreach($result as $key => $value){
            if($key == "memberof" && $value !== NULL) {
                foreach($value as $innerkey => $innerValue) {
                    if(is_array($innerValue)){
                        if(array_key_exists("count", $innerValue)) {
                            foreach($innerValue as $adUser) {
                                array_push($adUsers, $adUser);
                            }
                        }
                    }
                }
            }
        }

        return $adUsers;
    }

    function mapPermissions($users, $SuperUserGroup, $AdministratorGroup, $manageGroup, $monitorGroup) {
        $a = array();
        foreach($users as $x){
            $sArray = explode(",", $x);
            foreach($sArray as $item) {
                if(strstr($item, "CN=")){
                    $s = str_replace("CN=", "", $item);
                    $a[] = $s;
                }
            }
        }

        $permissionLevel = -1;

        if(in_array($SuperUserGroup, $a)) {
            $permissionLevel = 4;
        } else if(in_array($AdministratorGroup, $a)) {
            $permissionLevel = 3;
        } else if(in_array($manageGroup, $a)) {
            $permissionLevel = 2;
        } else if(in_array($monitorGroup, $a)) {
            $permissionLevel = 1;
        } else if(in_array("Administrators", $a)) {
            $permissionLevel = 3;
        }

        return $permissionLevel;

    }
} // end authenticate

?>
