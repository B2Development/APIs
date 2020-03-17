<?php
/*
 *
 * Roles.php: Roles Class used to govern user access to an appliance.
 *
 * Copyright mindAmp Corporation, 2017, All Rights Reserved
 * /
 */

/*
Requires for tandalone execution.

require_once('/var/www/html/api/includes/bp.php');
require_once('/var/www/html/api/includes/logger.php');
require_once('/var/www/html/api/includes/function.lib.php');
require_once('/var/www/html/api/includes/constants.lib.php');
*/

class Roles
{
    private $BP;
    const NVP_TYPE_ROLES = 'roles';
    const NO_SCOPE_VALUE = -1;

    const FILE_LEVEL_APP_ID = 1;
    const MIN_EXCHANGE_APP_ID = 2;
    const MAX_EXCHANGE_APP_ID = 19;

    const RESTORE_OPERATOR = 'restore_operator';
    const BACKUP_OPERATOR = 'backup_operator';
    const OPERATOR = 'operator';

    const AD_USER = 'ADuser';
    const AD_PRIVILEGE = 'ADprivilege_level';
    const MANAGE = 2;

    public function __construct($BP, $name = null, $adUser = false)
    {
        $this->BP = $BP;
        $this->functions = new Functions($this->BP);

        $this->userName = $name;
        $this->ADuser = $adUser;
        if ($this->userName == null) {
            $this->userName = $this->getCurrentUserRoleName();
        }
        if ($this->userName != null) {
            if ($this->ADuser) {
                $this->userName = $this->makeADRoleUser($this->userName);
            }
            $this->roles = $this->bp_get_role_info($this->userName, true);
        } else {
            $this->roles = null;
        }
        $this->scopeNames = null;
        $this->scopeArray = null;
    }

    /*
     * Get the username that will be the roles nvp_name.
     * This will be the current username, pls and ADuser token if an AD user.
     */
    private function getCurrentUserRoleName()
    {
        $userName = null;
        if ($this->BP->isBypassCookie()){
            $user = false;
        } else {
            $user = $this->BP->getUser();
        }
        if ($user !== false) {
            if ($user[self::AD_USER]) {
                if ($user[self::AD_PRIVILEGE] == self::MANAGE) {
                    $userName = $this->makeADRoleUser($user['name']);
                }
            } else {
                $userName = $user['name'];
            }
        }
        return $userName;
    }

    /*
     * Given a username, build the name to be saved in the role for an AD user.
     */
    private function makeADRoleUser($userName)
    {
        $adToken = '|' . self::AD_USER;
        if (strstr($userName, $adToken) === false)  {
            $userName .= $adToken;
        }
        return $userName;
    }

    /*
     * Returns true if this user has any roles, false otherwise.
     */
    public function hasRoles() {
        return !empty($this->roles);
    }

    /*
     * Returns true if this user has any roles with non-empty scope, false otherwise.
     * A scope empty string is equivalent to no scope (i.e., no restrictions).
     */
    public function hasRoleScope() {
        $value = false;
        if ($this->hasRoles()) {
            if (isset($this->roles['scope']) && !empty($this->roles['scope'])) {
                $value = true;
            }
        }
        return $value;
    }


    /*
     * Returns true if this user has any roles with non-empty options, false otherwise.
     */
    public function hasRoleOptions() {
        $value = false;
        if ($this->hasRoles()) {
            if (isset($this->roles['recover_options']) && !empty($this->roles['recover_options'])) {
                $value = true;
            }
        }
        return $value;
    }

    /*
     * Gets the role information for this user in array form.
     */
    public function get($userName = null, $adUser = false) {
        $role = array();
        if ($userName === null) {
            if ($this->roles !== null) {
                $role = $this->roles;
            }
        } else {
            if ($adUser) {
                $userName = $this->makeADRoleUser($userName);
            }
            $this->roles = $this->bp_get_role_info($userName, true);
            $role = $this->roles;
        }
        return $role;
    }

    /*
     * Returns the names of users on the system who have defined roles.
     */
    public function getUsers() {
        $users = array();
        $roleUsers = $this->bp_get_role_users();
        if ($roleUsers !== false) {
            $users = $roleUsers;
        }
        return $users;
    }

    /*
     * Returns the names of Active Directory users who have roles.
     * AD users are identified by their username with the appended AD token (makeADRoleUser).
     */
    public function getADUsers() {
        $roleUsers = $this->getUsers();
        $adToken = '|' . self::AD_USER;
        $adUsers = array();
        foreach ($roleUsers as $userName) {
            if (strstr($userName, $adToken) !== false) {
                $nameArray = explode('|', $userName);
                $adUsers[] = $nameArray[0];
            }
        }
        return $adUsers;
    }

    /*
     * Given an array of role info, saves it for the provided username.  If no username
     * is provided, uses the member user (defined during object construction).
     */
    public function save($userName = null, $roleInfo = null) {
        $result = true;
        $valid = true;
        if ($userName === null) {
            if ($this->userName === null) {
                $result = array('error' => 500, 'message' => 'Cannot save role information for no user.');
                $valid = false;
            } else {
                $userName = $this->userName;
            }
        }
        if ($valid) {
            $deletingRole = false;
            if (isset($roleInfo['level'])) {
                $roleInfo['name'] = $this->mapLevelToName($roleInfo['level']);
                if ($roleInfo['name'] == '') {
                    $deletingRole = true;
                }
            }
            if ($deletingRole) {
                $result = $this->bp_delete_role_info($userName);
            } else {
                $result = $this->bp_save_role_info($userName, $roleInfo);
            }
        }
        return $result;
    }

    /*
     * Deletes the specified role information for the user.
     */
    public function delete($userName = null, $roleInfo = null) {
        $valid = true;
        $result = true;
        if ($userName === null) {
            if ($this->userName === null) {
                $result = array('error' => 500, 'message' => 'Cannot delete role information for no user.');
                $valid = false;
            } else {
                $userName = $this->userName;
            }
        }
        if ($valid) {
            $result = $this->bp_delete_role_info($userName, $roleInfo);
        }
        return $result;
    }

    /*
     * Get the rolename for this user, e.g., 'restore_operator', 'operator', 'backup_operator'.
     * If no role, returns null.
     */
    public function get_name($userName = null)
    {
        $name = null;
        if ($userName === null) {
            if ($this->roles !== null) {
                $role = $this->roles;
            }
        } else {
            $this->roles = $this->bp_get_role_info($userName);
            $role = $this->roles;
        }
        if (isset($role['name'])) {
            $name = $role['name'];
        }
        return $name;
    }

    /*
     * Returns the role scope, or null if no role scope is available.
     */
    public function get_scope($userName = null)
    {
        $scope = null;
        if ($userName === null) {
            if ($this->roles !== null) {
                $role = $this->roles;
            }
        } else {
            $this->roles = $this->bp_get_role_info($userName);
            $role = $this->roles;
        }
        if (isset($role['scope'])) {
            $scope = $role['scope'];
        }
        return $scope;
    }

    /*
     * Returns the recovery options or null if not available.
     */
    public function get_recover_options($userName = null)
    {
        $options = null;
        if ($userName === null) {
            if ($this->roles !== null) {
                $role = $this->roles;
            }
        } else {
            $this->roles = $this->bp_get_role_info($userName);
            $role = $this->roles;
        }
        if (isset($role['recover_options'])) {
            $options = $role['recover_options'];
        }
        return $options;
    }

    public function filesOnly($userName = null) {
        return $this->recoverOptionsToken('files_only', $userName);
    }

    public function originalOnly($userName = null) {
        return $this->recoverOptionsToken('orig_only', $userName);
    }

    public function noDownload($userName = null) {
        return $this->recoverOptionsToken('no_download', $userName);
    }

    public function noInPlace($userName = null) {
        return $this->recoverOptionsToken('no_in_place', $userName);
    }

    /*
     * Creates a suffix list array from the recover option, formatted as:
     * suffix:<suffix1>,<suffix2>,<suffix3>
     */
    public function getSuffixList($userName = null) {
        $suffixList = array();
        $options = $this->get_recover_options($userName);
        if ($options !== null) {
            if (strstr($options, 'suffix') !== false) {
                $optionParts = explode(';', $options);
                foreach ($optionParts as $optionPart) {
                    if (strstr($optionPart, 'suffix') !== false) {
                        $suffixes = explode(':', $optionPart);
                        if (count($suffixes) >= 2) {
                            $items = $suffixes[1];
                            $suffixList = array_map('trim', explode(',', $items));
                        }
                        break;
                    }
                }
            }
        }
        return $suffixList;
    }

    /*
     * A generic function that returns true if the specified token is present in the recovery options.
     */
    public function recoverOptionsToken($token, $userName = null) {
        $hasToken = false;
        $options = $this->get_recover_options($userName);
        if ($options !== null) {
            $hasToken = strstr($options, $token) !== false;
        }
        return $hasToken;
    }

    /*
     * Given a backup object, sees if it is in the users scope.
     * Returns true if it is and false if not.
     */
    public function backup_is_in_scope($backup, $systemID, $userName = null) {
        $result = true;
        $scopeArray = $this->buildScopeArray($userName);
        if (count($scopeArray) > 0) {
            $result = false;
            foreach ($scopeArray as $scope) {
                if (($systemID == $scope['sid']) &&
                    (isset($backup['client_id']) && ($backup['client_id'] == $scope['cid']))) {

                    // Check instance if the backup/archive has it (archives always do, not backups)
                    // AND if the scope definition is initialized
                    if (isset($backup['instance_id']) && ($scope['iid'] != self::NO_SCOPE_VALUE)) {
                        if ($backup['instance_id'] == $scope['iid']) {
                            $result = true;
                            break;
                        }
                    } else {
                        $result = true;
                        break;
                    }
                }
            }
        }
        return $result;
    }

    /*
     * Given an instance ID, ees if it is in the users scope.
     * Returns true if it is and false if not.
     */
    public function instance_is_in_scope($instanceID, $systemID, $userName = null) {
        $result = true;
        $scopeArray = $this->buildScopeArray($userName);
     //   print_r($scopeArray);
        if (count($scopeArray) > 0) {
            $result = false;
            foreach ($scopeArray as $scope) {
                if (($systemID == $scope['sid']) &&
                    (isset($scope['iid']) && $instanceID == $scope['iid'])) {
                    $result = true;
                    break;
                }
            }
        }
        return $result;
    }

    /*
     * Given a client ID, ees if it is in the users scope.
     *  Returns true if it is and false if not.
     */
    public function client_is_in_scope($clientID, $systemID, $userName = null) {
        $result = true;
        $scopeArray = $this->buildScopeArray($userName);
        if (count($scopeArray) > 0) {
            $result = false;
            foreach ($scopeArray as $scope) {
                if (($systemID == $scope['sid']) &&
                    ($clientID == $scope['cid'])) {
                    if (!isset($scope['aid']) || (isset($scope['aid']) && $scope['aid'] == self::FILE_LEVEL_APP_ID)) {
                        $result = true;
                        break;
                    }
                }
            }
        }
        return $result;
    }

    /*
     * Given a backup object, sees if it is in the users scope.
     * Returns true if it is and false if not.
     */
    public function client_name_is_in_scope($clientName, $systemID, $userName = null) {
        $result = true;
        if ($this->scopeNames === null) {
            $this->scopeNames = $this->createScopeNames(null, $userName);
        }
        /*global $Log;
        $Log->writeVariable("checking remote backups for scope");
        $Log->writeVariable($this->scopeNames);
        $Log->writeVariable('backups');
        $Log->writeVariable($backups);*/
        if (count($this->scopeNames) > 0) {
            $result = false;
            foreach ($this->scopeNames as $scope) {
                if ($systemID == $scope['sid']) {
                    if ($clientName == $scope['client_name']) {
                        $result = true;
                        break;
                    }
                }
            }
        }
        return $result;
    }


    /*
     * Given a system ID, returns whether or not a backup mount is in scope.  As we do not have isntance IDs,
     * we can only filter based on application ID of Exchange, as Exchange is the only app using mounted backups.
     * If the scope contains any exchange assets, return true; otherwise, return false.
     */
    public function backup_mount_is_in_scope($systemID, $userName = null) {
        $result = true;
        $scopeArray = $this->buildScopeArray($userName);
        if (count($scopeArray) > 0) {
            $result = false;
            foreach ($scopeArray as $scope) {
                if (($systemID == $scope['sid']) &&
                    (isset($scope['aid']) && $scope['aid'] >= self::MIN_EXCHANGE_APP_ID && $scope['aid'] <= self::MAX_EXCHANGE_APP_ID)) {
                    $result = true;
                }
            }
        }
        return $result;
    }

    /*
     * Given a scope array, build a list of client_name and asset_names for the scope items.
     * If the scope array is not set, build it first, then get the names.
     * Include sid (system ID) in the name array for uniqueness comparisons.
     */
    private function createScopeNames($scopeArray = null, $userName = null) {
        $scopeNamesArray = array();
        if ($scopeArray === null) {
            $scopeArray = $this->buildScopeArray($userName);
        }
        if (count($scopeArray) > 0) {
            foreach ($scopeArray as $scope) {
                if ($scope['iid'] != self::NO_SCOPE_VALUE) {
                    $nameArray = $this->functions->getInstanceNames($scope['iid'], $scope['sid']);
                } else {
                    $info = $this->BP->get_client_info($scope['cid'], $scope['sid']);
                    if ($info !== false) {
                        $nameArray = array('client_name' => $info['name'], 'asset_name' => self::NO_SCOPE_VALUE);
                    }
                }
                $nameArray['sid'] = $scope['sid'];
                $scopeNamesArray[] = $nameArray;
            }
        }
        return $scopeNamesArray;
    }

    /*
     * Given a backup object, sees if it is in the users scope.
     * Returns true if it is and false if not.
     */
    public function remote_backups_are_in_scope($backups, $systemID, $userName = null) {
        $result = true;
        if ($this->scopeNames === null) {
            $this->scopeNames = $this->createScopeNames(null, $userName);
        }
        /*global $Log;
        $Log->writeVariable("checking remote backups for scope");
        $Log->writeVariable($this->scopeNames);
        $Log->writeVariable('backups');
        $Log->writeVariable($backups);*/
        if (count($this->scopeNames) > 0) {
            $result = false;
            foreach ($this->scopeNames as $scope) {
                if ($systemID == $scope['sid']) {
                    if (isset($backups['client_name']) && ($backups['client_name'] == $scope['client_name'])) {

                        if (isset($backups['database_name']) && ($scope['asset_name'] != self::NO_SCOPE_VALUE)) {
                            if ($backups['database_name'] == $scope['asset_name']) {
                                $result = true;
                                break;
                            }
                        } else if ($backups['client_name'] == $backups['database_name'] &&
                            $backups['client_name'] == $backups['instance_name']) {
                            // If names all match, agent-based backup, so in scope.
                            $result = true;
                            break;
                        }
                    }
                }
            }
        }
        return $result;
    }

    /*
     * If the scopeArray member is set, returns it.
     * If not, builds an array of scope items, where each scope item has the following properties:
     *
     *  sid = system ID
     *  cid = client ID
     *  aid = application ID
     *  iid = instance ID
     *
     * The value of each field will be an integer or self::NO_SCOPE_VALUE If not defined.
     */
    private function buildScopeArray($userName = null) {
        if ($this->scopeArray === null) {
            $scopeArray = array();
            $scope = $this->get_scope($userName);
            // If there is no scope (scope is null) all backups are in it.
            if ($scope !== null) {
                $tokens = explode(',', $scope);
                foreach ($tokens as $token) {
                    $item = explode('_', $token);
                    $scopeItem = array('sid' => self::NO_SCOPE_VALUE,
                        'cid' => self::NO_SCOPE_VALUE,
                        'aid' => self::NO_SCOPE_VALUE,
                        'iid' => self::NO_SCOPE_VALUE);
                    if (count($item) > 3) {
                        $scopeItem['aid'] = $item[2];       // app ID
                        $scopeItem['iid'] = $item[3];       // instance ID
                    }
                    if (count($item) > 1) {
                        $scopeItem['sid'] = $item[0];       // system ID
                        $scopeItem['cid'] = $item[1];       // client ID
                    }
                    $scopeArray[] = $scopeItem;
                }
            }
            $this->scopeArray = $scopeArray;
        } else {
            $scopeArray = $this->scopeArray;
        }
        return $scopeArray;
    }

    /*
     * bp_get_role_users()
     *
     * Description: Returns an array of usernames who have role based access, or false on failure.
     *
     */
    private function bp_get_role_users()
    {
        return $this->BP->get_nvp_names(self::NVP_TYPE_ROLES);
    }

    /*
     * bp_get_role_info(string $userName, [optional] boolean $getLevel)
     *
     * Description: Given a user name for an appliance or domain user (e.g., user@domain or domain\user),
     * returns an array of role definitions for that user if applicable.  If no role information is set
     * for this user, an empty array is returned.  If getLevel is true, a numeric representation of the
     * level is returned, where 1 = no restrictions, 2 = backup_operator, 3 = restore_operator, 4 = operator.
     *
     * role information returned includes
     *
     * name - name of role for the user, if set, one of backup_operator, restore_operator, or operator
     *
     * scope - scope of assets accessible by the user, as a comma-separated list of ids of the form
     *              sid_cid_aid_iid,sid_cid_aid_iid
     *
     *              where sid=system id, cid=client id, aid = app id, iid = instance id
     *
     * recover_options - string of comma-separated options for a restore_operator formatted as
     *      option;option; etc., where if option is present, the restriction exists.
     *
     *              orig_only
     *              files_only
     *              no_download
     *              no_in_place
     *              suffix:.doc,.html, .php (only . and letters/numbers are allowed, comma-separated).
     *
     */
    private function bp_get_role_info($userName, $getLevel = false) {
        $roleInfo = array();

        $this->roles = $this->BP->get_nvp_list(self::NVP_TYPE_ROLES, $userName);
        if (isset($this->roles['name'])) {
            $roleInfo['name'] = $this->roles['name'];
            if ($getLevel) {
                $roleInfo['level'] = $this->mapNameToLevel($roleInfo['name']);
            }
        }
        if (isset($this->roles['scope'])) {
            $roleInfo['scope'] = $this->roles['scope'];
        }
        if (isset($this->roles['recover_options'])) {
            $roleInfo['recover_options'] = $this->roles['recover_options'];
        }

        return $roleInfo;
    }

    /*
     * bp_save_role_info(string $userName, array $roleInfo)
     *
     * Description: Given a user name for an appliance or domain user (e.g., user@domain or domain\user),
     * saves an array of role information for the user.
     *
     * Returns true on success or false on failure.
     *
     * If a role name/value pair previously existed, it will be overwritten with the
     * new information.  If not present, the new information will be added.
     *
     */
    private function bp_save_role_info($userName, $roleInfo) {

        $currentInfo = $this->bp_get_role_info($userName, false);
        $newRoleInfo = array_merge($currentInfo, $roleInfo);

        if (isset($newRoleInfo['level'])) {
            unset($newRoleInfo['level']);
        }

        //printf("newInfo\n");
        //var_dump($newRoleInfo);

        $result = $this->BP->save_nvp_list(self::NVP_TYPE_ROLES, $userName, $newRoleInfo);

        return $result;
    }

    /*
     * bp_delete_role_info(string $userName, [optional] string $roleAttribute)
     *
     * Description: Given a user name for an appliance or domain user (e.g., user@domain or domain\user),
     * removes the specified role attribute or all attributes if none is specified.
     *
     * Returns true on success or false on failure.
     *
     */
    private function bp_delete_role_info($userName, $itemToDelete = null) {

        if ($itemToDelete == null) {
            $result = $this->BP->delete_nvp_list(self::NVP_TYPE_ROLES, $userName);
        } else {
            $currentInfo = $this->bp_get_role_info($userName);
            $newRoleInfo = array();
            foreach ($currentInfo as $item => $value) {
                if ($item != $itemToDelete) {
                    $newRoleInfo[$item] = $value;
                }
            }
            $result = $this->BP->delete_nvp_list(self::NVP_TYPE_ROLES, $userName);
            //var_dump($newRoleInfo);
            if ($result !== false) {
                $result = $this->BP->save_nvp_list(self::NVP_TYPE_ROLES, $userName, $newRoleInfo);
            }
        }

        return $result;
    }

    private function mapNameToLevel($name) {
        $level = 1;
        if ($name == self::BACKUP_OPERATOR) {
            $level = 2;
        } else if ($name == self::RESTORE_OPERATOR) {
            $level = 3;
        } else if ($name == self::OPERATOR) {
            $level = 4;
        }
        return $level;
    }

    private function mapLevelToName($level) {
        $name = '';
        if ($level == 2) {
            $name = self::BACKUP_OPERATOR;
        } else if ($level == 3) {
            $name = self::RESTORE_OPERATOR;
        } else if ($level == 4) {
            $name = self::OPERATOR;
        }
        return $name;
    }
}


/*
For standalone execution


if ($argc < 2) {
    printf("must specify user name\n");
    exit(-1);
}

$longOptions = array(
    "get",
    "save",
    "delete",
    "user:",
    "name:",
    "value:",
);


$Log = new Logger();
$Constants = new Constants();
$BP = new BP();
$options = getopt('gsd', $longOptions);
if (isset($options['user'])) {
    $userName = $options['user'];
    $roles = new Roles($BP, $userName);
}

$action = 'get';
if (isset($options['save']) || isset($options['s'])) {
    $action = 'save';
} else if (isset($options['delete']) || isset($options['d'])) {
    $action = 'delete';
}


if (isset($userName)) {
    if ($action == 'get') {
        printf("User = %s, Action is get\n", $userName);
        $result = $roles->get_name();
        printf("role name is %s\n", $result);
        $result = $roles->get_scope();
        printf("role scope is %s\n", $result);
    } else if ($action == 'save') {
        printf("User = %s, Action is save\n", $userName);
        if (!isset($options['name'])) {
            printf("--name 'name' must be specified on save\n");
            exit(-1);
        }
        $name = $options['name'];
        if (!isset($options['value'])) {
            printf("--value 'value' must be specified on save\n");
            exit(-1);
        }
        $value = $options['value'];
        $roleInfo = array($name => $value);
        $result = $roles->save($userName, $roleInfo);
        if ($result) {
            printf("Successful Save\n");
        } else {
            printf("Failed Save\n");
        }
    } else if ($action == 'delete') {
        printf("User = %s, Action is delete\n", $userName);
        if (!isset($options['name'])) {
            printf("--name 'name' must be specified on delete\n");
            exit(-1);
        }
        $name = $options['name'];
        $result = $roles->delete($userName, $name);
        if ($result) {
            printf("Successful Delete\n");
        } else {
            printf("Failed Delete\n");
        }
    } else {
        printf("unrecognized action\n");
    }
} else {
    printf("Error: username must be specified withi -u <name> or --user <name>\n");
}
*/

?>
