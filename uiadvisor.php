<?php

class UIAdvisor
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;
        $this->sid  = $this->BP->get_local_system_id();
    }

    public function get_advice($which, $data, $sid) {
        $debug = false;
        $sid = ($sid === false) ? $this->sid : $sid;

        // advisor/switch : non-debug running of the advisor.
        // advisor/debug  : debug running of the advisor.
        switch ($which){
            case "debug":
                // local system only.
                if ($sid !== $this->sid) {
                    $data = array();
                    $data['error'] = 500;
                    $data['message'] = 'The advisor runs on the local appliance only.';
                    break;
                }
                $debug = true;
            case "switch":
                // local system only.
                if ($sid !== $this->sid) {
                    $data = array();
                    $data['error'] = 500;
                    $data['message'] = 'The advisor runs on the local appliance only.';
                    break;
                }
                $data = $this->getDefaultUIAdvice($debug, $sid);
                break;
            case 'default-ui':
                $data = $this->BP->get_default_ui($sid);
                if ($data !== false) {
                    $data = array('default' => $data);
                }
                break;
            default:
                $data = false;
                break;
        }

        return $data;
    }

    public function put($which, $ui, $sid) {
        $result = true;

        // advisor/default-ui/satori - change default to satori
        // advisor/default-ui/rrc - change default to rrc
        switch ($which){
            case "default-ui":
                if ($ui == 'satori' || $ui === 'rrc') {
                    $result = $this->BP->set_default_ui($ui, $sid);
                } else {
                    $result = array('status' => 500, 'message' => 'Invalid default-ui parameter.');
                }
                break;
            default:
                $result = array();
                $result['status'] = 500;
                $result['message'] = 'Invalid advisor parameter.';
                break;
        }

        return $result;
    }



function checkCustomerInfo(&$satoriStat, $sysId)
{
	$satoriStat['debug'][] = "====enter checkCustomerInfo====";

	$custList = bp_get_customer_list();

	if ($custList === false || $custList === NULL) {
		$satoriStat['debug'][] = "========bp_get_customer_list returned FALSE. Error is " .bp_error();
		$satoriStat['messages'][] = "Failed to retrieve customer list.  Cannot determine if multiple customers defined.";
		$satoriStat['switch'] = false;
		return $satoriStat;
	}


	if (count($custList) > 1) {
		$satoriStat['messages'][] = "Multiple customers are defined and cannot be displayed.";
		$satoriStat['switch'] = false;
	}

       	foreach ( $custList as $ID => $custName ) {
		$satoriStat['debug'][] = "========found customer '" . $custName ."'";
		$locList = bp_get_location_list( $ID );
		if (count($locList) > 1) {
			$satoriStat['messages'][] = "Found multiple locations for customer ".$custName;
			$satoriStat['switch'] = false;
		}
		foreach($locList as $LID => $location) {
			$satoriStat['debug'][] = "========found location ".$location;
		}
	}

	return;
}

function checkArchiveEnv(&$satoriStat, $sysId)
{
	$satoriStat['debug'][] = "====enter checkArchiveEnv====";

	$mediaList = bp_get_connected_archive_media($sysId);
	if ($mediaList === false || $mediaList === NULL) {
		$satoriStat['debug'][] = "========bp_get_connected_archive_media returned FALSE. Error is " .bp_error();
		$satoriStat['messages'][] = "Failed to retrieve list of connected archive media. Cannot determine if archive tape is in use.";
		$satoriStat['switch'] = false;
		return;
	} 

	foreach($mediaList as $media) {
		$satoriStat['debug'][] = "========archive media name ".$media['name']." labelled as ".$media['media_lable']." is of type ".$media['type'];
		$lcType = strtolower($media['type']);
		if (($lcType == "tape") || ($lcType == "changer")) {	
			$satoriStat['messages'][] = "Found tape archive ".$media['name']." attached. Tape archiving not currently supported.";
			$satoriStat['switch'] = false;
		}
	}
	return;

}


function checkExistingClients(&$satoriStat,$sysId)
{
	$satoriStat['debug'][] = "====enter checkExistingClients====";

	$clList = bp_get_client_list($sysId);

	if ($clList === false || $clList === NULL) {
		$satoriStat['debug'][] = "========bp_get_client_list returned FALSE. Error is " .bp_error();
		$satoriStat['messages'][] = "Failed to retrieve client list. Cannot determine applications in use.";
		$satoriStat['switch'] = false;
		return;
	} 

	foreach($clList as $clId => $clName) {
		$satoriStat['debug'][] = "========client id " .$clId . " : name " . $clName;
		$clInfo = bp_get_client_info($clId,$sysId);
		if ($clInfo !== false && $clInfo !== NULL) {
			// Check for unsupported OS
			// Current unsupported OSs:  iSeries
			if ($clInfo['os_type_id'] == 33) {
				$satoriStat['switch'] = false;
				$satoriStat['messages'][] = "Found client os type ".$clInfo['os_type']." which is not supported.";
			} else {
				// Check for unsupported application types
				foreach($clInfo['applications'] as $appID => $clApp) {
					$satoriStat['debug'][] = "========found appid " . $appID . " : name " . $clApp['name'];
					if ($appID == 130 || $appID == 120) {
						$satoriStat['messages'][] = "Found application " .$clApp['name']. " on client ".$clName." which is not supported.";
						$satoriStat['switch'] = false;
					}
				}
			}
		} else {
			$satoriStat['debug'][] = "========bp_get_client_info returned FALSE. Error is " . bp_error();
			$satoriStat['messages'][] = "Failed to retrieve client info for ".$clName.". Cannot determine applications in use.";
			$satoriStat['switch'] = false;
		}
	}

	return;
}

function checkScheduling(&$satoriStat,$sysId)
{
	$satoriStat['debug'][] = "====enter checkScheduling====";

	//Check application schedules
	$schedList = bp_get_app_schedule_list(-1,-1,$sysId);

	foreach($schedList as $sched) {
		$satoriStat['debug'][] = "Got application sched ".$sched['name']." on client ".$sched['client_id'];
		$schedInfo = bp_get_app_schedule_info($sched['id'], $sysId);
		if ($schedInfo === false || $schedInfo === NULL) {
			$satoriStat['debug'][] = "========bp_get_app_sched_info returned FALSE. Error is " .bp_error();
			$satoriStat['messages'][] = "Failed to retrieve application schedule info. Cannot determine if unsupported schedules exist";
		} else {
			$satoriStat['debug'][] = "========Found sched " . $sched['name'] . " on client " . $sched['client_id'] . " where enabled = " . $sched['enabled'];
			// Are there more than 2 regex expressions defined for this schedule?
			// If so and the sched is enabled, then set the result to false
			// If not enabled then return true but add warning to messages.
			if (isset($schedInfo['regular_expressions']) && count($schedInfo['regular_expressions']) > 2) {
				if ($sched['enabled']) {
					$satoriStat['switch'] = false;
					$satoriStat['messages'][] = "Found enabled schedule '" . $sched['name'] . "' that uses more than 2 regular expressions";
				} else {
					$satoriStat['messages'][] = "Warning: Found disabled schedule '" . $sched['name'] . "' that uses more than 2 regular expressions";
				}
			}	
			// Are there more than 2 backup type entries defined for this schedule?
			// If so and the sched is enabled, then set the result to false
			// If not enabled then return true but add warning to messages.
			if (isset($schedInfo['calendar'])) {
				$CAL = $schedInfo['calendar'];
				if (isset($CAL) && substr_count($CAL,'SUMMARY') > 2) {
					if ($sched['enabled']) {
						$satoriStat['switch'] = false;
						$satoriStat['messages'][] = "Found enabled schedule '" . $sched['name'] . "' that contains more than 2 backup types";
					} else {
						$satoriStat['messages'][] = "Warning: Found disabled schedule '" . $sched['name'] . "' that contains more than 2 backup types";
					}
				}
			}	
		}
	}


	$optionsList = bp_get_option_lists($sysId);
	$selectionsList = bp_get_selection_lists($sysId);

	$schedList = bp_get_schedule_list($sysId);
	foreach($schedList as $sched) {
		$satoriStat['debug'][] = "Got sched ".$sched['name'];
		$schedInfo = bp_get_schedule_info($sched['id'], $sysId);
		if ($schedInfo === false || $schedInfo === NULL) {
			$satoriStat['debug'][] = "========bp_get_sched_info returned FALSE for schedule ".$sched['name'].". Error is " .bp_error();
			$satoriStat['messages'][] = "Failed to retrieve schedule info. Cannot determine if unsupported schedules exist";
		} else {
			foreach( $schedInfo['clients'] as $client) {
				$clInfo = bp_get_client_info($client['id'],$sysId);
				$satoriStat['debug'][] = "========bp_get_sched_info for schedule ".$sched['name'].", client ".$clInfo['name'];
				$complexCnt = 0;
				// Look for any non-Satori created options/inclusions/exclusions in this schedule.
				if (isset($client['options'])) {
					$optionid = $client['options'];	
					$satoriStat['debug'][] = "========looking at option id ".$optionid;
					foreach($optionsList as $option) {
						if ($option['id'] == $optionid) {
							if ($option['family'] !== "Satori-file-level") {
								$complexCnt = $complexCnt + 1;
								$satoriStat['debug'][] = "========found non-Satori option";
							} else {
								$satoriStat['debug'][] = "========found Satori created option ";
							}
							break;
						}
					}
				}
				if (isset($client['inclusions'])) {
					$includeid = $client['inclusions'];
					$satoriStat['debug'][] = "========looking at inclusion id ".$includeid;
					foreach($selectionsList as $selection) {
						if ($selection['id'] == $includeid) {
							if ($selection['family'] !== "Satori-file-level") {
								$complexCnt = $complexCnt + 1;
								$satoriStat['debug'][] = "========found non-Satori inclusion";
							} else {
								$satoriStat['debug'][] = "========found Satori created inclusion ";
							}
							break;
						} 
					}
				}
				if (isset($client['exclusions'])) {
					$excludeid = $client['exclusions'];
					$satoriStat['debug'][] = "========looking at exclusion id ".$excludeid;
					foreach($selectionsList as $selection) {
						if ($selection['id'] == $excludeid) {
							if ($selection['family'] !== "Satori-file-level") {
								$complexCnt = $complexCnt + 1;
								$satoriStat['debug'][] = "========found non-Satori exclusion";
							} else {
								$satoriStat['debug'][] = "========found Satori created exclusion ";
							}
							break;
						} 
					}
				}
				if ($complexCnt > 0) {
					$satoriStat['messages'][] = "Found schedule ".$sched['name']." for client ".$clInfo['name']." that contains custom options/selections lists that are not supported.";
					$satoriStat['switch'] = false;
				}
			}
		}
	}

	return;
}


function checkStorageConfig(&$satoriStat, $sysId) 
{
	$satoriStat['debug'][] = "====enter checkStorageConfig====";

	$storeList = bp_get_storage_list($sysId);
	// Look for any storage that has more than 1 device configured.
	foreach($storeList as $storeID => $storeName) {
		$satoriStat['debug'][] = "========found storage named " . $storeName;
		$storeInfo = bp_get_storage_info($storeID,$sysId);
		if ($storeInfo === false || $storeInfo === NULL) {
			$satoriStat['debug'][] = "========bp_get_storage_info returned FALSE. Error is " .bp_error();
			$satoriStat['messages'][] = "Failed to retrieve information on storage ".$storeName.". Cannot determine state of backup devices";
			$satoriStat['switch'] = false;
		} else {
			$devCnt = count($storeInfo['devices']);
			if ($devCnt > 1) {
				$devCnt = 0;
				// Count 'real' backup devices here.
				// Look at the devices and skip any tape devices found.
				// If the storage is offline then warn but don't return negative.
				// If online, return negative.
				if ($storeInfo['online']) {
					foreach($storeInfo['devices'] as $did => $dev) {
						$dinfo = bp_get_device_info($dev);
						if ($dinfo['dev_name'] == "sctape") {
							//don't count tape devices as backup devs.
							$satoriStat['debug'][] = "========found tape device ".$dinfo['dev_rw_name']." connected to ".$storeName;
						} else {
							$satoriStat['debug'][] = "========found device ".$dinfo['dev_rw_name']." connected to ".$storeName;
							$devCnt = $devCnt + 1;
						}
					}
					if ($devCnt > 1) {
						$satoriStat['messages'][] = 
						"Found storage ".$storeName." configured with multiple backup devices (".$devCnt.") which is not supported.";
						$satoriStat['switch'] = false;
					}
				} else {
					$satoriStat['messages'][] = 
					"Warning: offline storage ".$storeName." is configured with multiple backup devices (".$devCnt.") which is not supported.";

				}
			}
		}
	}
	return;
}

function checkNetworkingConfig(&$satoriStat, $sysId)
{
	$satoriStat['debug'][] = "====enter checkNetworkingConfig====";

	$chapUser = bp_get_chap();
	if ($chapUser === false) {
		$satoriStat['debug'][] = "========found no CHAP credentials.";
	} else {
		$satoriStat['messages'][] = "Found iSCSI CHAP user defined.  CHAP not currently supported.";
		$satoriStat['switch'] = false;
	}
	return;
}

function checkSecuritySettings(&$satoriStat, $sysId)
{
	$satoriStat['debug'][] = "====enter checkSecuritySettings====";

	$bpusers = bp_get_user_list();
	if ($bpusers === false) {
		$satoriStat['messages'][] = "Failed to retrieve user list. Cannot determine if Active Directory users present. Error ".bp_error();
	} else {
		foreach($bpusers as $userId => $userName) {
			$satoriStat['debug'][] = "========found username ".$userName;

			$uInfo = bp_get_user_info($userId);
			if ($uInfo !== false) {
                /*
                    Remove constraint on AD users.
				if ($uInfo['ADuser']) {
					$satoriStat['messages'][] = "Found Active Directory user: ".$userName." AD user management not supported";
					$satoriStat['switch'] = false;
				}
                */
			} 
		}
	}

	return;
}

function checkLegacyUIelements(&$satoriStat, $sysId)
{
	$satoriStat['debug'][] = "====enter checkLegacyUIelements====";
	$output = bp_get_nvp_list("navGroups", "users");
	if (count($output) > 0) {
		$satoriStat['messages'][] = "Found navigation groups in legacy UI which are not supported.";
		$satoriStat['debug'][] = "========found navigation groups :";
		$satoriStat['switch'] = false;
		foreach($output as $id => $name) {
			$dname = urldecode($name);	
			$satoriStat['debug'][] = $dname;
		}
	} else {
		$satoriStat['debug'][] = "========found no legacy UI navigation groups.";
	}
	return;
}

function getDefaultUIAdvice($debug, $sysId)
{
	$satoriStat = array();	
	$satoriStat['switch'] = true;

	$this->checkExistingClients($satoriStat,$sysId);
	$this->checkCustomerInfo($satoriStat,$sysId);
	$this->checkScheduling($satoriStat,$sysId);
	$this->checkArchiveEnv($satoriStat,$sysId);
	$this->checkStorageConfig($satoriStat,$sysId);
	$this->checkNetworkingConfig($satoriStat,$sysId);
	$this->checkLegacyUIelements($satoriStat,$sysId);
	$this->checkSecuritySettings($satoriStat,$sysId);
	$satoriStat['messages'][] = "System check completed";

	$returnData['switch'] = $satoriStat['switch'];
	$returnData['messages'] = $satoriStat['messages'];
	if ($debug) {
		$returnData['debug'] = $satoriStat['debug'];
	} 
	
	return $returnData;
}

} // end UIAdvisor

?>
