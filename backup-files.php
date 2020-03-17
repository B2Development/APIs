<?php

class BackupFiles
{
	private $BP;

	// The maximum number of files in a single directory to retrieve at once.
	const MAX_FILES = 1000;
	// The maximum number of items (files, dirs) to return at once.
	const MAX_ITEMS = 5000;
    // Linux volume preface
	const LINUX_VOLUME = '@@@:';

	public function __construct($BP, $toCSV = false)
	{
		$this->BP = $BP;
        //
        // How many items to skip, and the maximum number to return.
        //
        $this->skip = 0;
		$this->maxItems = 0;
		$this->toCSV = $toCSV;
	}



	public function get($sid)
	{
		$backupID = isset($_GET['bid']) ? (int)$_GET['bid'] : 0;
		$dpuID = $sid;

		$localID = $this->BP->get_local_system_id();
		$grandClientView = isset($_GET['grandclient']) && $_GET['grandclient'] === "true";
		$sysID = $grandClientView ? $localID : $dpuID;

        // set skip and items.
		$this->skip = isset($_GET['skip']) ? (int)$_GET['skip'] : 0;
        $this->maxItems = isset($_GET['max']) ? (int)$_GET['max'] + $this->skip : BackupFiles::MAX_ITEMS + $this->skip;
		$itemCount = 0;

        // see if we are downloading entire file to server.
		$this->saveAll = ($this->skip == 0 && $this->maxItems <= 0);
        // see if we are writing to a file directly or return via json.
		$bToDisk = ($this->maxItems == -1);

        // Initialize the last file in the directory to an empty string (no last file).  This is used
        // there are so many entries in a directory that they can only be partially loaded at one time.
        //
		$lastFile = "";

		$result = array();

        //
        // Start at the top level, getting volumes, so name, id, startDir are all blank strings.
        //
		$volumes = $this->BP->get_backup_files($backupID, "", "", $lastFile, BackupFiles::MAX_FILES, $sysID);
		if ($volumes !== false) {
			foreach ($volumes as $volume) {
				if ($volume['date'] == 0) {
					; // Skip it, it is a shell only.
				} else {
					if ($this->saveAll || $itemCount >= $this->skip) {
						// It is a volume.
						$file = $volume['name'];
						$obj = array();
						if (!$this->toCSV) {
							$obj['type'] = 'file';
						}
						$obj['name'] = str_replace(BackupFiles::LINUX_VOLUME, "", $file);
						$obj['size'] = round($volume['size'], 1);
						$result[] = $obj;
					}
					$itemCount++;
				}
				$this->getFiles($this->BP, $result, $backupID, $volume['name'], $volume['id'], $itemCount, $lastFile, $sysID);
			}
		}

        if ($this->toCSV) {
            require_once('./includes/csv.php');
            $CSV = new CSV();
            $header = array('Filename' => 'name', 'Size(KB)' => 'size');
            $csv = $CSV->toCSV($header, $result);
            $result = $csv;
        }

		return $result;
	}

    //
    // Get files based on id.  If a directory, recursively get files.
    // Skip "skip" files before outputting data.
    // Stop at "maxItems".
    //
	function getFiles($BP, &$result, $bid, $name, $id, &$items, &$lastFile, $sysID) {

		$dirs = $this->BP->get_backup_files($bid, $name, $id, $lastFile, BackupFiles::MAX_FILES, $sysID);
		$bDone = false;
		while (!$bDone) {
			$dir = "";
			if ($dirs !== false) {
				foreach ($dirs as $dir) {
					if (!$this->saveAll && $items >= $this->maxItems) {
						// see if more is added already
						if (count($result) > 0 && !$this->toCSV) {
							$last = $result[count($result) - 1];
							if (!isset($last['more'])) {
								$obj = array();
								$obj['more'] = 1;
								$result[] = $obj;
							}
						}
						$bDone = true;
						break;
					} else {
						$file = $name . '/' . $dir['name'];
						if ($dir['type'] == 'directory' && $dir['date'] == 0) {
							; // Skip it, it is a shell only.
						} else {
							if ($this->saveAll || $items >= $this->skip) {
								$obj = array();
								if (!$this->toCSV) {
									$obj['type'] = 'file';
								}
								$obj['name'] = str_replace(BackupFiles::LINUX_VOLUME, "", $file);
								$obj['size'] = round($dir['size'], 1);
								$result[] = $obj;
							}
							$items++;
						}
						if ($dir['type'] == 'directory') {
							// Recursively call function with the directory.
							$this->getFiles($this->BP, $result, $bid, $file, $dir['id'], $items, $lastFile, $sysID);
						}
					}
				}
				//
				// See if there are more items in this subdirectory by checking count of items returned.
				// If there are more, call the API again, specifying the lastFile as the last one in the prior return array.
				// Otherwise, set done to true and exit the loop.
				//
				if (BackupFiles::MAX_FILES == 0 || count($dirs) < BackupFiles::MAX_FILES) {
					$bDone = true;
					$lastFile = "";
				} else if (!$bDone) {
					$lastFile = $dir['name'];
					$dirs = $this->BP->get_backup_files($bid, $name, $dir['id'], $lastFile, BackupFiles::MAX_FILES, $sysID);
					if ($dirs === false) {
						$bDone = true;
					}
				}
			} else {
				$bDone = true;
			}
		}
	}
}

?>