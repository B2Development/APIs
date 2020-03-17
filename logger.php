<?php
class Logger
{
	var $m_level;
	var $m_file;
	var $m_type;
	var $timezone;

	// Constructor for primary CMC logger function.  Defines the default level
	// and destination for the log information (a file).
	function __construct($level = 0, $logDirectory = "/tmp", $type = 3) {

		// Default debug level is 0.
		$this->m_level = $level;

		// Default to a File Destination (php defines this as type 3).
		$this->m_type = $type;

		// Get the timezone offset for the log file
		$this->timezone = date('O');

		// Constants for building the log file name.
		//$MAX_SIZE = 5 * 1024 * 1024;
		// 5 MB
		//$MAX_SIZE = 5242880;
		//$MAX_FILES = 4;

		// unibp-7681 - log files wrap too fast.  trying 1 more log, 2 more mb
		// 7 MB = 7 * 1024 * 1024

		$MAX_SIZE = 7340032;
		$MAX_FILES = 10;

		// Find the newest file in the log file rotation and use it.
		$newestTime = 0;
		$count = 1;
		for ($i = 1; $i <= $MAX_FILES; $i++) {
			$file = $logDirectory . '/api_' . $i . '.log';
			if (file_exists($file)) {
				$statArray = stat($file);
				$fileTime = $statArray['mtime'];
				if ($newestTime == 0 || $fileTime > $newestTime) {
					$newestTime = $fileTime;
					$count = $i;
				}
			} else {
				break;
			}
		}
		// The log file name.
		$file = $logDirectory . '/api_' . $count . '.log';

		// switch to the next file if this log file size is too large.
		if (file_exists($file) && filesize($file) > $MAX_SIZE) {
			// Go back to the first file if at the last in the rotation.
			if ($count == $MAX_FILES) {
				$count = 1;
			} else {
				$count++;
			}
			$file = $logDirectory . '/api_' . $count . '.log';
			// zero out this file first.
			if (file_exists($file)) {
				$fp = fopen($file, "w+");
				if ($fp) {
					fclose($fp);
				}
			}
		}
		$this->m_file = $file;

	}

	// Set the level of logging.
	function setLevel($level) {
		$this->m_level = $level;
	}

	// Returns the log level.
	function getLevel() {
		return $this->m_level;
	}

	// Gets the current time to stamp log entries.
	function getTimestamp() {
		list($usec, $sec) = explode(' ', microtime());
		$usec = str_replace("0.", ".", $usec);
		$usec = substr($usec, 0, 4);
		$timestr = date('Y-m-d H:i:s', $sec).$usec;

		$fullTimeStamp = $timestr.' '.$this->timezone.' :: api_log :: ';

		$s = sprintf("%s", $fullTimeStamp);
		return $s;
	}

	// Logs errors.  If the message is coming from the
	// bpl layer, note that in the log.
    function writeError($Error, $bFromBPL, $fromRest = false, $fromRDR = false) {
		if ($this->getLevel() >= 0) {
			$message = $this->getTimestamp();
            if(is_array($Error)){
                $message .= " Errors from";
            }
            else{
                $message .= " Error from";
            }

            if ($bFromBPL) {
                $message .= " bpl.so: ";
            } else if($fromRest){
                $message .= " web services: ";
            } else if($fromRDR){
                $message .= " rdr: ";
            } else {
                $message .= " cmc script: ";
            }

            $allMessages = "\n\n";
            if(is_array($Error)){
                $i=1;
                $messageArray = $Error['result'];
                if(is_array($messageArray)){            //In case of updates, push agent there is array of messages
                    foreach($messageArray as $key => $msg){
                        if(is_array($msg)){
                            foreach($msg as $innerKey => $innerMsg){        //Listen to inner message
                                $allMessages .= $innerMsg . "\n\n";
                            }
                        }
                        else{
                            $allMessages .= $i . ") " . $msg . "\n\n";
                            $i++;
                        }
                    }
                }
                else{
                    $allMessages = $messageArray;       //All other cases
                }
                $Error = $allMessages;
            }
			$message .= $Error . "\n\n";
			error_log($message, $this->m_type, $this->m_file);
		}
	}

	// Logs URLs of the CMC scripts.  Does not display the authentication string
	// for security reasons.
	function writeURL($stringURL) {
		if ($this->getLevel() >= 1) {
			$logURL = urldecode($stringURL);
			$logURL = preg_replace('/auth=.*&/U', "auth=x&", $logURL);
			$logURL = preg_replace('/password=*&/U', "password=x&", $logURL);
			$logURL = preg_replace('/passphrase=.*&/U', "passphrase=x&", $logURL);
			$message = $this->getTimestamp() . " URL: " . $logURL . "\n\n";
			error_log($message, $this->m_type, $this->m_file);
		}
	}

	// Logs the entrance to BPL.so function, logging name, argument count and values.
	function enterFunction($stringFunction) {
		if (strstr($stringFunction, "rest_get_summary")){
			; //do nothing
		}else {
            if ($this->getLevel() >= 2) {
                $message = $this->getTimestamp() . " Enter: " . $stringFunction;
                $nArguments = func_num_args();
                if ($nArguments == 1) {
                    $message .= "(no args)\n";
                } else {
                    $message .= "(" . ($nArguments - 1) . " args):\n";
                }
                error_log($message, $this->m_type, $this->m_file);
                for ($i = 1; $i < $nArguments; $i++) {
                    $arg = func_get_arg($i);
                    $this->writeVariable($arg, $i);
                }
                $message = "\n\n";
                error_log($message, $this->m_type, $this->m_file);
            }
        }
	}

	// Logs the exit from BPL.so function, and function result value.
	function exitFunction($stringFunction, $result) {
		if (strstr($stringFunction, "rest_get_summary") && $result !== false){
			; //do nothing
		}else {
            if ($this->getLevel() >= 2) {
                $timestamp = $this->getTimestamp();
                $message = $this->getTimestamp() . " Exit: " . $stringFunction . " result:\n";
                error_log($message, $this->m_type, $this->m_file);
                $this->writeVariable($result);
                $message = "\n\n";
                error_log($message, $this->m_type, $this->m_file);
            }
        }
	}

	// Logs the XML that will be send back to the CMC for processing.
	function writeXML($stringXML) {
		if ($this->getLevel() >= 5) {
			$message = $this->getTimestamp() . " XML:\n";
			$message .= $stringXML . "\n\n";
			error_log($message, $this->m_type, $this->m_file);
		}
	}

	// Logs a stack backtrace.
	function trace($function) {
		$message = $function . "\n";
		ob_start();
		var_dump(debug_backtrace());
		$contents = ob_get_contents();
		ob_end_clean();
		$message .= $contents;
		error_log($message, $this->m_type, $this->m_file);
	}

	// Logs a variable with var_dump().
	function writeVariable($var, $argumentNumber = 1, $forceLog = false) {
		if ($forceLog || $this->API_LEVEL()) {
			ob_start();
			var_dump($var);
			$contents = ob_get_contents();
			ob_end_clean();
			$message = "#" . $argumentNumber . ": " . $contents;
			error_log($message, $this->m_type, $this->m_file);
		}
	}

	// Logs debug data with var_dump().
	function writeVariableDBG($var, $argumentNumber = 1, $forceLog = false) {
		if ($forceLog || $this->DBG_LEVEL()) {
			ob_start();
			var_dump($var);
			$contents = ob_get_contents();
			ob_end_clean();
			$message = "#" . $argumentNumber . ": " . $contents;
			error_log($message, $this->m_type, $this->m_file);
		}
	}

	// Returns the number for the URL Level
	function URL_LEVEL() {
		return $this->getLevel() >= 1;
	}

	// Returns the number for the API Level
	function API_LEVEL() {
		return $this->getLevel() >= 2;
	}

	// Returns the number for the XML Level
	function XML_LEVEL() {
		return $this->getLevel() >= 5;
	}

	// Returns the number for the DEBUG Level
	function DBG_LEVEL() {
		return $this->getLevel() >= 10;
	}

	function enterMethod($http, $method, $data, $sid, $forceLog = false) {
		if ($forceLog || $this->API_LEVEL()) {
			ob_start();
			var_dump($data);
			$contents = ob_get_contents();
			ob_end_clean();
			$message = "HTTP: " . $http . "\n";
			$message .= "  method: " . $method . "\n";
			$message .= "  sid: " . ($sid === false ? "none" : $sid) . "\n" . "\n"; // Adding a second return while data is commented out
			//$message .= "  data: " . $contents . "\n";  // Commenting out data to make sure that passwords are obfuscated for release
			error_log($message, $this->m_type, $this->m_file);
		}
	}

}
?>
