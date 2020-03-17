<?php

class Users
{
    const UPDATE_ROLES_USER = 0;

    private $BP;
     
    public function __construct($BP)
    {
		$this->BP = $BP;
        $this->supportsRoles = Functions::supportsRoles();
        $this->Roles = null;
    }

    public function get($which)
    {
		$allUsers = array();

		$users = $this->BP->get_user_list();
		if ($users !== false) {
			foreach ($users as $id => $name) {
				if ($which > 0 and $id != $which) {
					continue;
				}
				$userInfo = $this->BP->get_user_info($id);
				if ($userInfo !== false) {
                    $eachUser = array ( 'id'=>$userInfo['id'],
                                        'name'=>$userInfo['name'],
                                        'superuser'=>$userInfo['superuser'],
                                        'vault_user'=>$userInfo['vault_user'],
                                        'self_service'=>isset($userInfo['self_service']) ? $userInfo['self_service'] : null,
                                        'source_id'=>isset($userInfo['source_id']) ? $userInfo['source_id'] : null,
                                        'customers'=>isset($userInfo['customers']) ? $userInfo['customers'] : null,
                                        'locations'=>isset($userInfo['locations']) ? $userInfo['locations'] : null,
                                        'systems'=>isset($userInfo['systems']) ? $userInfo['systems'] : null
                                        );
                    if (isset($userInfo['administrator'])) {
                        $eachUser['administrator'] = $userInfo['administrator'];
                    }
                    if ($this->supportsRoles) {
                        $this->Roles = new Roles($this->BP, $userInfo['name']);
                        $roleInfo = $this->Roles->get();
                        if (count($roleInfo) > 0) {
                            $eachUser['user_role'] = $roleInfo;
                        }
                    }
                    $allUsers['data'][] = $eachUser;
				}
			}
            if ($which <= 0 && $this->Roles !== null) {
                // Only show AD users if a specific user ID is not specified (AD users have no real ID).
                // If no ID is requested, check to see if called by user with adequate privilege to include AD user roles.
                $showADUsers = false;
                if ($this->BP->isBypassCookie()) {
                    $showADUsers = true;
                } else {
                    $currentUser = $this->BP->getUser();
                    if ($currentUser !== false) {
                        $showADUsers = (isset($currentUser['superuser']) && $currentUser['superuser'] == true) ||
                            (isset($currentUser['administrator']) && $currentUser['administrator'] == true);
                    }
                }
                if ($showADUsers) {
                    $adUsers = $this->getADUserRoles();
                    if (count($adUsers) > 0) {
                        $allUsers['data'] = array_merge($allUsers['data'], $adUsers);
                    }
                }
            }
			$allUsers['supports_roles'] = $this->supportsRoles;
		}
		return($allUsers);
    }

    private function getADUserRoles() {
        $adUsers = array();
        $roleUsers = $this->Roles->getADUsers();
        $adID = 0;
        foreach ($roleUsers as $userName) {
            $adID--;
            $roleInfo = $this->Roles->get($userName, true);
            $customers = $locations = $systems = array('privilege_level' => Roles::MANAGE);
            $userInfo = array('id' => $adID,
                                'name' => $userName,
                                'ADuser' => true,
                                'customers' => array($customers),
                                'locations' => array($locations),
                                'systems' => array($systems),
                                'user_role' => $roleInfo
            );
            $adUsers[] = $userInfo;
        }
        return $adUsers;
    }

    public function put($which, $data)
    {
        $status = false;
        $userArray = array();
        if ($which === self::UPDATE_ROLES_USER) {
            if ($this->supportsRoles && isset($data['name']) && isset($data['user_role'])) {
                $ADuser = isset($data['ADuser']) && ($data['ADuser'] == true);
                $status = $this->saveRoleInfo($data['name'], $data['user_role'], $ADuser);
            }
        } else if ($which != -1) {
            $userArray['id'] = $which;
            if (isset($data['name'])) {
                $userArray['name'] = $data['name'];
            }
            if (isset($data['superuser'])) {
                $userArray['superuser'] = $data['superuser'];
            }
            $userInfo = $this->BP->get_user_info($which);
            if ($userInfo !== false) {
                $userName = $userInfo['name'];
                $superUser = $userInfo['superuser'];
                if (isset($data['password'])) {
                     if (isset($data['current_password'])) {
                        $valid = $this->BP->authenticate($userName, $data['current_password']);
                        if ($valid !== -1) {
                            $userArray['password'] = $data['password'];
                        } else {
                            $msg = "Incorrect password.";
                        }
                    } else {
                        $msg = "Specify the current password.";
                    }
                }
                if ($userName == "root" && (isset($data['customers']) || isset($data['locations']) || isset($data['systems']))) {
                    $msg = "Root user privileges cannot be changed.";
                } else {
                    if (isset($data['customers'])) {
                        $userArray['customers'] = $data['customers'];
                    }
                    if (isset($data['locations'])) {
                        $userArray['locations'] = $data['locations'];
                    }
                    if (isset($data['systems'])) {
                        $userArray['systems'] = $data['systems'];
                    }
                }
            }
            if (isset($msg)) {
                $status['error'] = 500;
                $status['message'] = $msg;
            } else {
                $status = $this->BP->save_user_info($userArray);
                if ($status !== false && isset($userName)) {
                    if ($this->supportsRoles && isset($data['user_role'])) {
                        $ADuser = isset($data['ADuser']) && ($data['ADuser'] == true);
                        $status = $this->saveRoleInfo($userName, $data['user_role'], $ADuser);
                    }
                }
            }
        }
        return $status;
    }

    private function saveRoleInfo($userName, $role_info, $ADuser)
    {
        $status = true;
        if ($this->Roles === null) {
            $this->Roles = new Roles($this->BP, $userName, $ADuser);
        }
        $result = $this->Roles->save(null, $role_info);
        if ($result === false) {
            $status = array('error' => 500, 'message' => 'Error saving user role information.');
            global $Log;
            $Log->writeVariable($status['message']);
        }
        return $status;
    }

    private function deleteRoleInfo($userName, $ADuser)
    {
        $status = true;
        if ($this->Roles === null) {
            $this->Roles = new Roles($this->BP, $userName, $ADuser);
        }
        $result = $this->Roles->delete();
        if ($result === false) {
            $status = array('error' => 500, 'message' => 'Error deleting user role information.');
            global $Log;
            $Log->writeVariable($status['message']);
        }
        return $status;
    }

    public function post($data)
    {
        $result = array();
        $userArray = array();
        if (isset($data['name'])) {
            $userArray['name'] = $data['name'];
        }
        if (isset($data['superuser'])) {
            $userArray['superuser'] = $data['superuser'];
        }
        if (isset($data['password'])) {
            $userArray['password'] = $data['password'];
        }
        if (isset($data['vault_user'])) {
            $userArray['vault_user'] = $data['vault_user'];
        }
        if (isset($data['source_id'])) {
            $userArray['source_id'] = $data['source_id'];
        }
        if (isset($data['self_service'])) {
            $userArray['self_service'] = $data['self_service'];
        }
        if (isset($data['customers'])) {
            $userArray['customers'] = $data['customers'];
        }
        if (isset($data['locations'])) {
            $userArray['locations'] = $data['locations'];
        }
        if (isset($data['systems'])) {
            $userArray['systems'] = $data['systems'];
        }
        if (isset($data['vault_user'])) {
            $userArray['vault_user'] = $data['vault_user'];
            if (isset($data['source_id'])) {
                $userArray['source_id'] = $data['source_id'];
            }
        }
        $output = $this->BP->save_user_info($userArray);

        if ($output !== false) {
            $users = $this->BP->get_user_list();
            if ($users !== false) {
                // save any role info, if specified.
                if ($this->supportsRoles && isset($userArray['name']) && isset($data['user_role'])) {
                    $status = $this->saveRoleInfo($userArray['name'], $data['user_role'], false);
                }
                // Some extra logic to get the new id if the request
                // was for a self-service user, which uses a built-in name.
                if (isset($data['self_service'])) {
                    foreach ($users as $id => $value){
                        $userInfo = $this->BP->get_user_info($id);
                        if ($userInfo !== false) {
                            foreach ($userInfo as $key => $value) {
                                if (($userInfo[$key] === $data['source_id']) ||
                                    ($userInfo[$key] === $data['name'])) {
                                    $newUser['id'] = $id;
                                    break;
                                }
                            }
                        }
                    }
                } else {
                    foreach ($users as $id => $name) {
                        if ($name === $data['name']) {
                            $newUser['id'] = $id;
                        }
                    }
                }
            }
            $result['result'][] = $newUser;
        } else {
            $result = $output;
        }
        return $result;
    }


    public function delete($which)
    {
        $result = false;
        if ($which === self::UPDATE_ROLES_USER) {
            if ($this->supportsRoles && isset($_GET['ADuserName'])) {
                $ADuserName = $_GET['ADuserName'];
                $result = $this->deleteRoleInfo($ADuserName, true);
            } else {
                $result = array('error' => 500, 'message' => 'Missing Active Directory role information for deletion');
            }
        } else if ($which !== -1) {
            $userInfo = $this->BP->get_user_info($which);
            if ($userInfo !== false) {
                $userName = $userInfo['name'];
                // do not remove root user
                if ($userName !== Constants::NVP_NAME_ROOT) {
                    $result = $this->BP->remove_user($which);
                    if ($this->supportsRoles && $result !== false) {
                        $status = $this->deleteRoleInfo($userName, false);
                    }
                } else {
                    $result['error'] = 500;
                    $result['message'] = "Root user cannot be removed";
                }
            } else {
                $result['error'] = 500;
                $result['message'] = "Cannot get user information.";
            }
        } else {
            $result['error'] = 500;
            $result['message'] = "User ID must be specified";
        }
        return $result;
    }

}

?>
