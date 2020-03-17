<?php
/**
 * Created by PhpStorm.
 * User: Sonja12
 * Date: 4/10/2015
 * Time: 8:30 PM
 */

class Catalogs
{
    private $BP;

    public function __construct($BP = NULL)
    {
        $this->BP = $BP;
        $this->localID = -1;

        $this->showLegalHoldInCatalog = true;       // By default, return legal hold status

        $this->Roles = null;
        if (Functions::supportsRoles()) {
            $this->Roles = new Roles($this->BP);
        }

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);

    }

    private function getLocalSystemID() {
        if ($this->localID == -1) {
            $this->localID = $this->BP->get_local_system_id();
        }
        return $this->localID;
    }


    public function get_catalog($type, $filter, $sid, $systems)
    {
        $allCatalogs = array();

        switch ($type[0]) {
            case 'backups':
                $allCatalogs = $this->getBackupCatalog($filter, $sid, $systems);
                break;
            case 'archives':
                $allCatalogs = $this->getArchiveCatalog($filter, $sid, $systems);
                break;
            case 'replication':
                $filter = 'all';  //needed for multiple targets
                $allCatalogs = $this->getReplicationCatalog($filter, $sid, $systems);
                break;
            default:
                $backupCatalog = $archiveCatalog = $replicationCatalog = array();
                $b = $a = $r = array();
                $typeToken = $type[0];
                if ($typeToken !== NULL && $typeToken !== '' && $typeToken[0] !== '?') {
                    if ($typeToken === 'all' || strpos($typeToken, 'b') !== false) {
                        $backupCatalog = $this->getBackupCatalog($filter, $sid, $systems);
                    }
                    if ($typeToken === 'all' || strpos($typeToken, 'a') !== false) {
                        $archiveCatalog = $this->getArchiveCatalog($filter, $sid, $systems);
                    }
                    if ($typeToken === 'all' || strpos($typeToken, 'r') !== false) {
                        $filter = 'all';
                        $replicationCatalog = $this->getReplicationCatalog($filter, $sid, $systems);
                    }
                } else {
                    // default is backups only.
                    $backupCatalog = $this->getBackupCatalog($filter, $sid, $systems);
                }

                if (array_key_exists('catalog', $backupCatalog)) {
                    $b = $backupCatalog['catalog'];
                }
                if (array_key_exists('catalog', $archiveCatalog)) {
                    $a = $archiveCatalog['catalog'];
                }
                if (array_key_exists('catalog', $replicationCatalog)) {
                    $r = $replicationCatalog['catalog'];
                }
                $view = isset($_GET['view']) ? $_GET['view'] : "system";

                $combined = array();

                if ($view == "day"){
                    foreach ($b as $backup){
                        $backup = $this->mergeDayView($backup, $a);
                        $combined[] = $this->mergeDayView($backup, $r, true, false);
                    }
                    foreach($a as $archive) {
                        $catalogInstance = $this->findArchiveDayNoBackups($archive, $b);
                        if($catalogInstance !== false && $catalogInstance !== NULL) {
                            $combined[] = $catalogInstance;
                        }
                    }
                    foreach ($r as $replica) {
                        $catalogInstance = $this->findArchiveDayNoBackups($replica, $b, true, false);
                        if($catalogInstance !== false && $catalogInstance !== NULL) {
                            $combined[] = $catalogInstance;
                        }
                    }
                    $combined = $this->sortCombined($combined);

                }
                if ($view == "instance") {
                    foreach ($b as $backup){
                        $combined[] = $this->mergeInstanceView($backup, $a, $r);
                    }
                    foreach($a as $archive) {
                        $catalogInstance = $this->findArchiveInstanceNoBackups($archive, $b);
                        if($catalogInstance !== false && $catalogInstance !== NULL) {
                            $combined[] = $catalogInstance;
                        }
                    }
                    foreach ($r as $replica) {
                        $catalogInstance = $this->findArchiveInstanceNoBackups($replica, $b, true, false);
                        if($catalogInstance !== false && $catalogInstance !== NULL) {
                            $combined[] = $catalogInstance;
                        }
                    }

                    $combined = $this->sortCatalogByInstance($combined);
                }
                if ($view == "system") {
                  //  var_dump($view);
                    foreach ($a as $backup){
                        $combined[] = $this->mergeSystemView($backup, $b);


                    }
                    foreach($b as $archive) {
                        $catalogInstance = $this->findArchiveInstanceNoBackups($archive, $a);
                        if($catalogInstance !== false) {
                            $combined[] = $catalogInstance;
                        }
                    }
                }
                $allCatalogs['catalog'] = $combined;
                break;

        }
        if ($this->showLegalHoldInCatalog === false) {
            $allCatalogs['legal_hold'] = false;
        }
        return $allCatalogs;
    }

    function getBackupCatalog($filter, $sid, $systems)
    {
        require_once('backups.php');
        $backups = new Backups($this->BP, $sid, $this->Roles);
        $backupCatalog = $backups->get("catalog", $filter, $sid, $systems);
        $this->showLegalHoldInCatalog = $backups->includeLegalHoldStatus();

        return $backupCatalog;
    }

    function getArchiveCatalog($filter, $sid, $systems)
    {
        $which = array();
        $which[] = "catalog";

        require_once('archive.php');
        $archive = new Archive($this->BP, $sid, $this->Roles);
        $archiveCatalog = $archive->get($which, $filter, $sid, $systems);

        return $archiveCatalog;
    }

    function getReplicationCatalog($filter, $sid, $systems)
    {
        $which = array();
        $which[] = "catalog";

        require_once('replication.php');
        $replication = new Replication($this->BP, $this->Roles);
        $replicationCatalog = $replication->get_catalog($filter, $sid, $systems);

        return $replicationCatalog;
    }

    function mergeDayView($backup, $archives, $areBackups = false, $matchSID = true){
        $itemsProperty = $areBackups ? 'backups' : 'archives';
        $localID = $this->getLocalSystemID();
        foreach($archives as $archive){
            if(array_key_exists("day", $archive)){
                $archiveDay = $archive['day'];
            }
            if(isset($archiveDay) && ($archiveDay == $backup['day'])){
                $backupInstances = $backup['instances'];
                $archiveInstances = $archive['instances'];
                for ($i=0; $i < count($backupInstances); $i++){
                    for ($j=0; $j < count($archiveInstances); $j++){
                        if(($matchSID == false || ($matchSID == true && $backupInstances[$i]['system_id'] == $archiveInstances[$j]['system_id'])) &&
                            ($backupInstances[$i]['instance_name'] == $archiveInstances[$j]['instance_name'])){
                            if (isset($archiveInstances[$j]['client_id']) && $archiveInstances[$j]['client_id'] !== null &&
                                $backupInstances[$i]['client_id'] !== $archiveInstances[$j]['client_id']){
                                continue;
                            }
                            if (isset($archiveInstances[$j]['instance_id']) && $archiveInstances[$j]['instance_id'] !== null &&
                                $backupInstances[$i]['instance_id'] !== $archiveInstances[$j]['instance_id']){
                                continue;
                            }
                            if (isset($archiveInstances[$j]['app_name']) && isset($backupInstances[$i]['app_name']) &&
                                $backupInstances[$i]['app_name'] !== $archiveInstances[$j]['app_name']){
                                continue;
                            }
                            $archives = $archiveInstances[$j][$itemsProperty];
                            $backupsArray = array_merge($backup['instances'][$i]['backups'], $archives);
                            $backups = $this->sortBackups($backupsArray);
                            $backup['instances'][$i]['backups'] = $backups;
                        }
                    }
                }
            }
        }
        return $backup;
    }


    function findArchiveDayNoBackups($archive, $backups, $areBackups = false, $matchSID = true)
    {
        $result = false;
        $hasMatch = false;
        $itemsProperty = $areBackups ? 'backups' : 'archives';
        $localID = $this->getLocalSystemID();

        $count = count($backups);
        for ($i = 0; $i < $count && !$hasMatch; $i++) {
            $backup = $backups[$i];
            if (isset($archive['day']) && ($archive['day'] == $backup['day'])) {
                $backupInstances = $backup['instances'];
                $archiveInstances = $archive['instances'];
                for ($n=0; $n < count($backupInstances); $n++){
                    for ($j=0; $j < count($archiveInstances); $j++){
                        if ($matchSID && ($backupInstances[$n]['system_id'] != $archiveInstances[$j]['system_id'])) {
                            continue;
                        } else if (!$matchSID && ($backupInstances[$n]['system_id'] != $localID)) {
                            continue;
                        }
                        if($archiveInstances[$j]['instance_name'] == $backupInstances[$n]['instance_name']){
                             $hasMatch = true;
                            break;
                         }
                     }
                }
            }
        }
        if (!$hasMatch && isset($archive['instances'])) {
            $instances = $archive['instances'];
            $count = count($instances);
            $new = 0;
            for ($i = 0; $i < $count; $i++) {
                $app = $archive['instances'][$i]['app_name'];
                if ($app != 'System Metadata') {
                    $archive['instances'][$new] = $archive['instances'][$i];
                    $archive['instances'][$new]['backups'] = $archive['instances'][$i][$itemsProperty];
                    if ($itemsProperty != 'backups') {
                        unset($archive['instances'][$i][$itemsProperty]);
                    }
                    if ($i != $new) {
                        unset($archive['instances'][$i]);
                    }
                    $new++;
                } else {
                    unset($archive['instances'][$i]);
                }
            }
            if ($new == 0) {
                $archive = false;
            }
            $result = $archive;
        }


        return $result;

    }

    function mergeInstanceView($backup, $archives, $replicas)
    {
        foreach ($archives as $archive) {
            if ($backup['system_id'] == $archive['system_id'] && $backup['instance_name'] == $archive['instance_name']
                && $archive['app_name'] !== "System Metadata" ) {
                $instanceArchives = $archive['archives'];
                if (isset($archive['client_id']) && $archive['client_id'] !== null && $backup['client_id'] !== $archive['client_id']){
                    continue;
                }
                if (isset($archive['instance_id']) && $archive['instance_id'] !== null && $backup['instance_id'] !== $archive['instance_id']){
                    continue;
                }
                if (isset($archive['app_name']) && isset($backup['app_name']) && $backup['app_name'] !== $archive['app_name']){
                    continue;
                }
                $backupsArray = array_merge($backup['backups'], $instanceArchives);
                $backups = $this->sortBackups($backupsArray);
                $backup['backups'] = $backups;
                break;
            }
        }
        $localID = $this->getLocalSystemID();
        if ($backup['system_id'] == $localID) {
            foreach ($replicas as $replica) {
                if ($backup['instance_name'] == $replica['instance_name']) {
                    $instanceBackups = $replica['backups'];
                    if ($backup['instance_name'] == "file-level"){
                        if ($backup['client_id'] !== $replica['client_id']){
                            continue;
                        }
                    } else if ($backup['instance_name'] == "Farm"){
                        if ($backup['client_name'] !== $replica['client_name']){
                            continue;
                        }
                    }
                    if (isset($backup['app_name']) && isset($replica['app_name']) && $backup['app_name'] !== $replica['app_name']){
                        continue;
                    }
                    $backupsArray = array_merge($backup['backups'], $instanceBackups);
                    $backups = $this->sortBackups($backupsArray);
                    $backup['backups'] = $backups;
                    break;
                }
            }
        }
        return $backup;
    }

    function findArchiveInstanceNoBackups($archive, $backups, $areBackups = false, $matchSID = true) {

        $result = false;
        $itemsProperty = $areBackups ? 'backups' : 'archives';
        $localID = $this->getLocalSystemID();

        if ($archive["app_name"] !== "System Metadata") {
            $hasMatch = false;
            if ($this->Roles !== null && $this->Roles->hasRoleScope()) {
                if (!$this->Roles->remote_backups_are_in_scope($archive, $localID)) {
                    global $Log;
                    $msg = "Remote archives for " . $archive['instance_name'] . " are NOT in restore scope.";
                    $Log->writeVariable($msg);
                    $result = false;
                    return $result;
                }
            }
            foreach($backups as $backup) {
                if ($matchSID && ($backup['system_id'] != $archive['system_id'])) {
                    continue;
                } else if (!$matchSID && ($backup['system_id'] != $localID)) {
                    continue;
                }
                if ($archive['instance_name'] == $backup['instance_name']) {
                    $hasMatch = true;
                    break;
                }
            }

            if($hasMatch == false) {
                $archives = $archive[$itemsProperty];
                $archives = $this->sortBackups($archives);
                unset($archive[$itemsProperty]);
                $archive['backups'] = $archives;
                $result = $archive;
            } else {
                $result = false;
            }
        }
        return $result;
    }

    function mergeSystemView($backup, $archives)
    {
      // var_dump($archives);
        foreach ($archives as $archive) {
           // var_dump("bu instance:  " . $backup['instance_name'] . "archive  instance:  " . $archive['instance_name']);
            if ($backup['instance_name'] == $archive['instance_name']) {

                $archives = $archive['archives'];
                $backupsArray = array_merge($backup['backups'], $archives);
                $backups = $this->sortBackups($backupsArray);
                $backup['backups'] = $backups;

            }
        }
        return $backup;
    }

    function sortBackups($backupArr) {
        $backups = $backupArr;
        $orderByStartDate = array();
        foreach ($backups as $key => $row) {
            $orderByStartDate[$key] = $this->functions->dateTimeToTimestamp($row['start_date']);
        }
        array_multisort($orderByStartDate, SORT_DESC, $backups);

        return $backups;
    }

    function sortCombined($combined){
        $orderByDay = array();
        foreach($combined as $key => $row){
            $orderByDay[$key] = $row['day'];
        }
        array_multisort($orderByDay, SORT_DESC, $combined);

        return $combined;
    }

    function sortCatalogByInstance($combined){
        $orderByInstance = array();
        foreach($combined as $key => $row){
            $orderByInstance[$key] = strtolower($row['database_name']);
        }
        array_multisort($orderByInstance, SORT_ASC, SORT_STRING, $combined);

        return $combined;
    }
}

