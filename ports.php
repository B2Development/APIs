<?php

class Ports
{
    private $BP;

    //
    // security level definitions and scripts
    //
    const SECURITY_NONE = "None";
    const SECURITY_LOW = "Low";
    const SECURITY_MEDIUM = "Med";
    const SECURITY_HIGH = "High";

    const LCD_DIR = "/usr/bp/lcdman.dir/";

    const LCD_NONE = "lcd_sec_none.sh";
    const LCD_LOW = "lcd_sec_low.sh";
    const LCD_MEDIUM = "lcd_sec_med.sh";
    const LCD_HIGH = "lcd_sec_hi.sh";
    const LCD_STATUS = "lcd_sec_stat.txt";

    const EXIT_NONE = 13;
    const EXIT_LOW = 12;
    const EXIT_MEDIUM = 11;
    const EXIT_HIGH = 10;

    public function __construct($BP)
    {
        $this->BP = $BP;
    }

    public function get($sid)
    {
        $data = false;
        $errorMessage = "";

        if ($sid !== false && $sid !== $this->BP->get_local_system_id()) {
            $data = array('error' => 500, 'message' => 'The Ports API is supported on the local system only.');
        } else {
            $result = $this->get_port_status($errorMessage);
            if ($result !== false) {
                $data = array();
                $data['security'] = $result;
                $dataport_count = $this->BP->get_ini_value("Configuration Options", "dataport_count");
                if ($dataport_count !== false) {
                    $data['dataport_count'] = (int)$dataport_count;
                }
                $managerport_start = $this->BP->get_ini_value("CMC", "DEMPortStart");
                if ($managerport_start !== false) {
                    $data['managerport_start'] = (int)$managerport_start;
                }
                $managerport_count = $this->BP->get_ini_value("CMC", "DEMPortCount");
                if ($managerport_count !== false) {
                    $data['managerport_count'] = (int)$managerport_count;
                }
            } else {
                $data = array('error' => 500, 'message' => $errorMessage);
            }
        }

        return $data;
    }

    public function put($data, $sid) {

        global $Log;
        $return = false;
        $errorMessage = "";

        if ($sid !== false && $sid !== $this->BP->get_local_system_id()) {
            $return = array('error' => 500, 'message' => 'The Ports API is supported on the local system only.');
        } else {
            if (isset($data['password'])) {
                if (isset($data['security'])) {
                    $rootPassword = $data['password'];
                    $securityLevel = $data['security'];
                    $Log->enterFunction("configure_ports", "root password", $securityLevel);
                    $result = $this->configure_ports($rootPassword, $securityLevel, $errorMessage);
                    $Log->exitFunction("configure_ports", $result);
                    if ($result !== true) {
                        $return = array('error' => 500, 'message' => $errorMessage);
                    } else {
                        $return = true;
                    }
                } else {
                    $return = array('error' => 500, 'message' => 'New port security level must be provided.');
                }
            } else {
                $return = array('error' => 500, 'message' => 'OS root password must be specified to change port security level.');
            }
        }

        return $return;
    }

    /*
     * Function to configure port security.  The OS root password must be secified, along with a valid security level.
     * Return error messages in the $msg variable on non-successful return (true = success).
     */
    private function configure_ports($rootPassword, $securityLevel, &$msg) {

        $status = -1;
        $res = trim(shell_exec('echo $UID'));
        // we're not running as root
        if (!$this->login_is_valid('root', $rootPassword)) {
            $msg = "Invalid root password detected.  A common mistake is to use the UI password instead of the root password of the system.  ";
            $msg .= "Please ensure that you are using the system root password.";
            return false;
        }

        $commandStart = "sudo -u root -S /bin/bash ";
        $command = sprintf("%s %s", $commandStart, Ports::LCD_DIR);

        $exitCode = Ports::EXIT_LOW;
        switch ($securityLevel) {
            case Ports::SECURITY_NONE:
                $command .= Ports::LCD_NONE;
                $exitCode = Ports::EXIT_NONE;
                break;
            case Ports::SECURITY_LOW:
                $command .= Ports::LCD_LOW;
                $exitCode = Ports::EXIT_LOW;
                break;
            case Ports::SECURITY_MEDIUM:
                $command .= Ports::LCD_MEDIUM;
                $exitCode = Ports::EXIT_MEDIUM;
                break;
            case Ports::SECURITY_HIGH:
                $command .= Ports::LCD_HIGH;
                $exitCode = Ports::EXIT_HIGH;
                break;
            default:
                $msg = "Invalid port security level provided. Level must be one of 'None', 'Low', 'Med', or 'High'";
                return false;
        }

        global $Log;
        $Log->writeVariable($command);
        $handle = popen($command, 'w');
        fwrite($handle, "$rootPassword\n");
        $status = pclose($handle);
        $result = false;
        if ($status != $exitCode) {
            $msg = "The port configuration could not be changed to " . $securityLevel . ".";
        } else {
            $result = true;
        }
        return $result;
    }

    /* checks for valid login credentials, returns 1 if valid, 0 otherwise
     *
     * @param $username - user to check
     * @param $password - password for this user
     * @return - 1 for valid login, 0 otherwise
     */
    private function login_is_valid($username, $password) {
        $passencoded = "'" . str_replace("'", "'\''", $password) . "'";
        $userencoded = "'" . str_replace("'", "'\''", $username) . "'";
        $cmd = "echo -e $passencoded | sudo -u $userencoded -S whoami 2>/dev/null";
        $res = trim(shell_exec($cmd));
        if ($res == $username) {
            return 1;
        } else {
            return 0;
        }
    }

    //
    // This function returns the existing port status by looking at the
    // LCD status file.
    //
    private function get_port_status(&$msg)
    {
        ob_start();
        $returnValue = false;
        $command = sprintf("/bin/cat %s%s 2>/dev/null", Ports::LCD_DIR, Ports::LCD_STATUS);
        $result = shell_exec($command);
        if (!is_null($result)) {
            $resultArray = explode('|', $result);
            if (count($resultArray) > 1) {
                $stringValue = $resultArray[1];
                $index = strpos($stringValue, ":");
                if ($index != -1) {
                    $stringValue = substr($stringValue, $index + 2);
                }
                $returnValue = $stringValue;
            }
        } else {
            //$msg = 'Error determining port status';
            // ports have never been set; security is none.
            $returnValue = Ports::SECURITY_NONE;
        }
        return $returnValue;
        ob_end_clean();
    }

} //End Ports
?>
