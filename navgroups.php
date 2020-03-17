<?php

/*
for standalone execution.

require_once('/var/www/html/api/includes/bp.php');
require_once('/var/www/html/api/includes/logger.php');
require_once('/var/www/html/api/includes/function.lib.php');
require_once('/var/www/html/api/includes/constants.lib.php');
*/

class NavigationGroups
{
    private $BP;

    public function __construct($BP, $uid = NULL, $loadGroups = true)
    {
        $this->BP = $BP;
        $this->functions = new Functions($this->BP);
        $this->groupsAreLinked = false;

        $this->user = $this->BP->getUser();
        // Fall-back to root if all fails
        if ($this->user === false) {
            $this->user = array('id' => 1, 'name' => 'root', 'superuser' => true);
        }

        $this->navgroup = array();
        if ($loadGroups) {
            $this->showGroups = $this->getGroupPreference($this->user);
            if ($this->showGroups) {
                $this->navgroup = $this->load();
            }
        }
    }

    public function get($id = NULL)
    {
        $results = $this->navgroup;
        return $results;
    }

    public function showGroups()
    {
        return $this->showGroups;
    }

    /*
     * Returns group preferences for the specified user.
     */
    private function getGroupPreference($user)
    {
        $showGroups = false;
        $userName = $user['name'];
        if ($user['ADuser']) {
            global $AD;
            $section = $this->BP->get_ini_section(ActiveDirectory::ACTIVE_DIRECTORY);
            if ($section !== false) {
                $fqdn = $AD->getValue($section, 'AD_DomainName');
                $userName .= '@' . $fqdn;
            }
        }
        $preferences = $this->BP->get_nvp_list("preferences", $userName);
        
        // try other preference formats if an AD user.
        if ($user['ADuser'] && isset($fqdn) && count($preferences) == 0) {
            // Try domain name before suffix
            $a = explode(".", $fqdn);
            $userName = $user['name'] . '@' . $a[0];
            $preferences = $this->BP->get_nvp_list("preferences", $userName);
            if (count($preferences) == 0) {
                // Try logged in as domain\user (encoded \)
                $userName = $a[0] . urlencode('\\') . $user['name'];
                $preferences = $this->BP->get_nvp_list("preferences", $userName);
                if (count($preferences) == 0) {
                    // Try logged in as domain\user (raw \)
                    $userName = $a[0] . '\\' . $user['name'];
                    $preferences = $this->BP->get_nvp_list("preferences", $userName);
                }
            }
        }
        if (isset($preferences['ShowGroups'])) {
            $value = strtolower($preferences['ShowGroups']);
            $showGroups = $value === 'true';
        }
        return $showGroups;

    }

    /*
     *  Loads the navigation groups as an encoded string from the NVP table.
     *  String data must first be urldecoded, then converted to an array using JSON functions.
     */
    private function load($id = NULL)
    {
        // empty template if no groups.
        $results = array('navgroup' => array());
        $output = $this->BP->get_nvp_list("navGroups", "users");
        if ($output !== false) {
            if (isset($output['NavGroups'])) {
                $groupXML = urldecode($output['NavGroups']);

                $xml = simplexml_load_string($groupXML);
                if ($xml !== false) {
                    $array = json_decode(json_encode((array)$xml), 1);
                    $results = array($xml->getName() => $array);
                }
            }
        } else {
            $results = false;
        }
        return $results;
    }

    public function getAllGroups()
    {
        return $this->getGroupsByParentID();
    }

    /*
     *  Searches through groups to add matches to the specified node by id.
     */
    public function addChildren(&$groupsArray, &$nodesArray, $id)
    {
        $found = false;
        foreach ($groupsArray as $groupArray) {
            // Check to see if this group should be shown.
            if ($this->showGroupForUser($groupArray)) {
                if ($groupArray['treeParentID'] == $id) {
                    $nodesArray['nodes'][] = $groupArray;
                    $found = true;
                }
            }
        }
        return $found;
    }

    /*
     *  Adds the specified node to the group for which it is a member.
     */
    public function addToGroup($nodeArray, &$groupsArray, $thisID)
    {
        $found = false;
        foreach ($groupsArray as &$groupArray) {
            if ($this->isMember($groupArray['group'], $thisID)) {
                $groupArray['nodes'][] = $nodeArray;
                $found = true;
                break;
            }
        }
        return $found;

    }

    /*
     * Returns true if the given node ID has already been added to the group, false otherwise.
     */
    public function replaceOrAddNode($nodeArray, &$groupsArray, $thisID)
    {
        //global $Log;
        $found = false;
        foreach ($groupsArray as &$groupArray) {
            foreach ($groupArray['nodes'] as $i => $node) {
                if ($node['id'] === $nodeArray['id']) {
                    //$Log->writeVariable("Node In Group, Replace");
                    //$Log->writeVariable($node['name']);
                    $groupArray['nodes'][$i] = $nodeArray;
                    $found = true;
                    break;
                }
            }
        }
        if (!$found) {
            $found = $this->addToGroup($nodeArray, $groupsArray, $thisID);
        }
        return $found;
    }

    /*
     * Walks through the groups and creates linkages between parent and child, based on treeParentID.
     */
    public function linkGroups(&$groupsArray)
    {
        if (!$this->groupsAreLinked) {
            foreach ($groupsArray as &$parentGroup) {
                $a = explode('_', $parentGroup['id']);
                $parentID = $a[count($a) - 1];
                foreach ($groupsArray as &$childGroup) {
                    // Check to see if this group should be shown.
                    if ($this->showGroupForUser($childGroup)) {
                        if (!isset($childGroup['linked'])) {
                            if ($childGroup['treeParentID'] == $parentID) {
                                $parentGroup['nodes'][] = &$childGroup;
                                $childGroup['linked'] = true;
                            }
                        }
                    }
                }
            }
            $this->groupsAreLinked = true;
        }
    }
    
    /*
     * Creates a "group-friendly" ID based on various inputs.
     */
    public function makeID($systemID, $clientID = NULL, $appID = NULL, $uuid = NULL, $instanceID = NULL)
    {
        $id = $systemID;
        if ($clientID !== NULL) {
            $id .= '.' . $clientID;
            if ($appID !== NULL) {
                $id .= '.' . $appID;
                if ($uuid !== NULL) {
                    $id .= '.' . $uuid;
                    if ($instanceID !== NULL) {
                        $id .= '.' . $instanceID;
                    }
                } else if ($instanceID != NULL) {
                    $id .= '.' . $instanceID;
                }
            }
        }
        $id = (string)$id;
        return $id;
    }

    /*
     * Converts the inventory ID from to a group-friendly ID.
     */
    public function convertID($underscoreID)
    {
        $dotID = str_replace('_', '.', $underscoreID);
        return $dotID;
    }

    /*
     * Given a parent, get child groups, or get all groups if no parent is specified.
     */
    public function getGroupsByParentID($parentID = NULL)
    {
        $returnGroups = array();

        $groups = isset($this->navgroup['navgroup']['group']) ? $this->navgroup['navgroup']['group'] : array();
        if (isset($groups['@attributes'])) {
            // Only one group, so encapsulate in an array.
            $groups = array($groups);
        }
        if (is_array($groups)) {
            foreach ($groups as $group) {
                $parent = $group['@attributes']['treeParentID'];
                if ($parentID == NULL || $parentID == $parent) {
                    $returnGroups[] = $group;
                }
            }
        }

        return $returnGroups;
    }

    /*
     * Returns true if the parent ID given is the parent of the specified group, or false if not.
     */
    public function parentOf($group, $parentID)
    {
        $isParent = false;

        $parent = $group['@attributes']['treeParentID'];
        if ($parentID == NULL || $parentID == $parent) {
            $isParent = true;
        }

        return $isParent;
    }

    /*
     * Returns true id is a child member of the specified group, or false if not.
     */
    public function isMember($group, $thisID)
    {
        $found = false;
        $childIDs = $group['childIDs'];
        if (isset($childIDs['child'])) {
            $children = $childIDs['child'];
            if (is_array($children)) {
                foreach ($children as $child) {
                    if ($child === $thisID) {
                        $found = true;
                        break;
                    }
                }
            } else {
                $child = $children;
                if ($child === $thisID) {
                    $found = true;
                }
            }
        }
        return $found;
    }

    public function put($data)
    {
        return $this->save($data);
    }

    public function post($data, $sid)
    {
        $sid = $sid !== false ? $sid : $this->BP->get_local_system_id();
    }

    /*
     *  Saves the navigation groups as an encoded string the NVP table.
     *  The "raw" version of the encoder must be used as we do not want ' ' converted to '+'.
     */
    private function save($arrayData)
    {
        $results = false;
        if (count($arrayData) > 0) {
            $xml = new SimpleXMLElement('<navgroup></navgroup>');
            $this->array_to_xml($arrayData['navgroup'], $xml);
            $xmlString = $xml->asXML();
            $xmlString = str_replace("<?xml version=\"1.0\"?>\n", '', $xmlString);
            $encoded = rawurlencode($xmlString);
            $results = $this->BP->save_nvp_list("navGroups", "users", array("NavGroups" => $encoded));
        }
        return $results;
    }

    /*
     *  Converts the navigational groups array list to an XML representation.
     */
    private function array_to_xml($data, &$xml, $parent = NULL)
    {
        foreach ($data as $key => $value) {
            //printf("Key = %s, value = %s\n", $key, $value);
            if (is_array($value)) {
                if ($key == 'group') {
                    $this->buildGroupXML($value, $xml);
                } else {
                    $subNode = $xml->addChild($key);
                    $this->array_to_xml($value, $subNode);
                }
            } else {
                //$xml->addChild("$key", htmlspecialchars("$value"));
                // HTML special chars converts &, ", ', <, >, but the caller calls "asXML()" which does this.
                // So this step is unnecessary and creates extra characters in the xml string.
                $xml->addChild("$key", "$value");
            }
        }
    }

    /*
     *  Converts each of the group array entres to their XML representations.
     */
    private function buildGroupXML($groupData, &$xml)
    {
        foreach ($groupData as $key => $value) {
            $groupNode = $xml->addChild('group');
            //printf("Group Key = %s, value = %s\n", $key, $value);
            if (is_array($value)) {

                if (isset($value['@attributes'])) {
                    foreach ($value['@attributes'] as $keyattr => $valattr) {
                        //printf("attr = %s, value = %s\n", $keyattr, $valattr);
                        //$groupNode->addAttribute($keyattr, htmlspecialchars("$valattr"));
                        // HTML special chars converts &, ", ', <, >, but the caller calls "asXML()" which does this.
                        // So this step is unnecessary and creates extra characters in the xml string.
                        $groupNode->addAttribute($keyattr, $valattr);
                    }
                    unset($value['@attributes']);
                }

                if (isset($value['childIDs'])) {
                    $subIDs = $groupNode->addChild('childIDs');
                    $ids = $value['childIDs'];
                    if (isset($ids['child'])) {
                        $children = $ids['child'];
                        if (!is_array($children)) {
                            $children = array($children);
                        }
                        foreach ($children as $child) {
                            //printf("child =  %s\n", $child);
                            $subIDs->addChild('child', $child);
                        }
                    }
                }

            }
        }
    }

    /*
     * Given the user info and the group user definition, return true if the group should be displayed and false otherwise.
     * Superusers and admins see all groups.
     */
    public function showGroupForUser($groupArray)
    {
        if ($this->user['superuser']) {
            return true;
        }
        if ($this->user['administrator']) {
            return true;
        }
        if (isset($groupArray['group'])) {
            $group = $groupArray['group'];
            if (isset($group['@attributes'])) {
                $attr = $group['@attributes'];
                if (isset($attr['users'])) {
                    $userids = $attr['users'];
                    $userToMatch = $this->user['ADuser'] ? $this->user['name'] : $this->user['id'];
                    $a = explode(',', $userids);
                    foreach ($a as $id) {
                        if ($id == $userToMatch) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }
}


/*
for standalone execution

$Log = new Logger();
$Constants = new Constants();
$BP = new BP();
$nav = new NavigationGroups($BP);
$result = $nav->get(1);
var_dump($result);
*/

?>
