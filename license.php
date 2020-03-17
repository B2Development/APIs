<?php


define("DPU", "Unitrends DPU");
define("RECOVERY", "Unitrends Recovery");
define("SFF", "Unitrends Small Form Factor");
define("UEB_HYPER_V", "VM-Hyper-V");
define("UEB_HYPERV", "VM-HyperV");
define("UEB_VMWARE", "VM-VMware");
define("VERSION", "Version");
define("INSTALL_DATE", "Installed on");
define("OS_KERNEL", "Unitrends' DPU Kernel");
define("HOT_BACKUP", "Hot Backup Copy");

define("UNKNOWN", "unknown");

//
// Amount of memory to reserve for base functionality when virtualizing clients.
//
// master.ini section/setting that defines the Appliance's Memory Reserve percentage.
define("INI_CMC", "CMC");
define("INI_MEMORY_RESERVE", "MemoryReserve");
// Default reserve percentage if master.ini value not found.
define("DEFAULT_MEMORY_RESERVE", 0.2);
// Minimum Reserve size in MB.
define("MINIMUM_RESERVE", 1024);

class License
{
    private $BP;

    public function __construct($BP){
        $this->BP = $BP;
    }

    public function get($which){


        $Appliance = UNKNOWN;
        $Version = UNKNOWN;
        $Kernel = UNKNOWN;
        $OS = UNKNOWN;
        $InstallDate = UNKNOWN;
        $ProcessorType = UNKNOWN;
        $ProcessorCores = 1;
        $ProcessorCache = UNKNOWN;
        $ProcessorFrequency = UNKNOWN;
        $MemorySize = UNKNOWN;
        $MemoryGB = 0;
        $ApplianceType = "DPU";
        $LicensedCapacity = NULL;
        $Clients = "Unlimited";

        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();

        $licenseInfo = $this->BP->get_license_info($systemID);
        $nRC= $nVC=0;
       $this-> parseLicenseString($licenseInfo['feature_string'],$FriendlyFeature, $ApplianceType, $LicensedCapacity, $Clients, $nRC, $nVC);


        switch ($which){
            // GET license/resources
            case "resources":
                $systems = $this->BP->get_system_list();
                $data=array();
                foreach ($systems as $key=>$val) {
                    if (isset($_GET['sid']) and $key != $systemID) {
                        continue;
                    }
                    $resourceLimits = $this->BP->get_resource_license_limit($key);
                    $resourceUsage = $this->BP->get_resource_license_usage($key);
                    if ($resourceLimits !== false && $resourceUsage !== false) {
                        $sInfo = $this->BP->get_system_info($key);
                        $sName = $sInfo['name'];
                        $resourceData=array(
                            'limits' => $this->buildResourceData($resourceLimits),
                            'usage' => $this->buildResourceData($resourceUsage)
                            );
                        $allResources=array('resources'=>$resourceData,
                                            'sid'=> $key,
                                            'license' => $sName
                                        );
                        $data['license'][]=$allResources;

                        $allResources=null;
                    }else {
                        $data = false;
                    }

                }

                break;

            case "request":


                $commandOutput = $this->BP->run_command("Processor Information", "", $systemID);
                $bVirtual = $this->BP->is_virtual($systemID);

                if ($commandOutput !== false) {
                    parseProcessorCommandOutput($commandOutput, $bVirtual, $ProcessorType, $ProcessorCores, $ProcessorCache, $ProcessorFrequency);
                }
                $commandOutput = $this->BP->run_command("System Information", "", $systemID);
                if ($commandOutput !== false) {
                    parseDPUCommandOutput($commandOutput, $Appliance, $Version, $Kernel, $InstallDate, $OS);
                }
                $commandOutput = $this->BP->run_command("Memory Usage", "", $systemID);
                if ($commandOutput !== false) {
                    parseMemoryCommandOutput($commandOutput, $MemorySize, $MemoryGB);
                }
                $sInfo = $this->BP->get_system_info($systemID);
                $sName = $sInfo['name'];
                $description=$this->mapLicenseDescription($licenseInfo['class']);
                $assetTag=$this->BP->get_asset_tag($systemID);


                $hostInfo = $this->BP->get_hostname($systemID);
                if ($hostInfo !== false) {
                    $Name = $hostInfo['name'];
                    $hostInfo = $this->BP->get_host_info($Name, $systemID);
                    if ($hostInfo !== false) {
                        $IP = $hostInfo['ip'];
                    }
                }

                $fReservePercent = 0;
                $result = $this->BP->get_ini_value(INI_CMC, INI_MEMORY_RESERVE, $systemID);
                if ($result !== false) {
                    $fReservePercent = (float)$result;
                }
                if ($fReservePercent <= 0 || $fReservePercent >= 1) {
                    $fReservePercent = DEFAULT_MEMORY_RESERVE;
                }
                // calculate reserve based on percentage
                $Reserve = (int)($MemoryGB*1000 * $fReservePercent);
                // Make sure we are over a minimum reserve.
                if ($Reserve < MINIMUM_RESERVE) {
                    $Reserve = MINIMUM_RESERVE;
                }

                $vf = $this->BP->get_virtual_failover($systemID);
                $registration = array(
                    'Appliance'=>$Appliance,
                    'ApplianceType'=>$ApplianceType,
                    'AssetTag' =>  $assetTag ,
                    'Class' => isset( $licenseInfo['class']) ? $licenseInfo['class']  : null ,
                    'ClientLock' => isset( $licenseInfo['client_lock']) ? $licenseInfo['client_lock']  : null ,
                    'Clients'=>$Clients,
                    'Comment' => isset( $licenseInfo['comment']) ? $licenseInfo['comment']  : null ,
                    'DaemonHost' => isset( $licenseInfo['daemon_host']) ? $licenseInfo['daemon_host']  : null ,
                    'DaemonHostID' => isset( $licenseInfo['daemon_host_id']) ? $licenseInfo['daemon_host_id']  : null ,
                    'DaemonIP' => isset( $licenseInfo['daemon_ip_port']) ? $licenseInfo['daemon_ip_port']  : null ,
                    'Description'=>isset($licenseInfo['class'])? $description:null,
                    'ExpirationDate' => isset( $licenseInfo['expiration_date']) ? $licenseInfo['expiration_date'] : null,
                    'FeatureString' => isset( $licenseInfo['feature_string']) ? $licenseInfo['feature_string'] : null,
                    'FriendlyFeature' => isset( $licenseInfo['feature_string']) ? $FriendlyFeature : null,
                    'IP'=>$IP,
                    'InstallDate'=>$InstallDate,
                    'Key' => isset( $licenseInfo['key']) ?  $licenseInfo['key'] : null,
                    'LicensedCapacity'=>isset($LicensedCapacity) ? $LicensedCapacity : 'The Maximum backup value for this system is not available.',
                    'Memory'=>$MemorySize,
                    'MemoryGB'=>$MemoryGB,
                    'Name' => $sName ,
                    'OS'=>$OS,
                    'ProcessorCache' => $ProcessorCache,
                    'ProcessorCores' => $ProcessorCores,
                    'ProcessorFrequency'=> $ProcessorFrequency,
                    'ProcessorType' => $ProcessorType,
                    'Product' => isset( $licenseInfo['product']) ? $licenseInfo['product']  : null ,
                    'Reserve'=>$Reserve,
                    'SerialNumber' => isset( $licenseInfo['serial_number']) ? $licenseInfo['serial_number']  : null ,
                    'Type' => isset( $licenseInfo['license_type']) ? $licenseInfo['license_type']  : null ,
                    'UserString' => isset( $licenseInfo['user_string']) ? $licenseInfo['user_string']  : null ,
                    'Users' => isset( $licenseInfo['users']) ? $licenseInfo['users']  : null ,
                    'Vendor information' => isset( $licenseInfo['vendor_information']) ? $licenseInfo['vendor_information']  : null ,
                    'Version' => isset( $licenseInfo['version']) ? $licenseInfo['version']  : null ,
                    'Version'=>$Version,
                    'VF'=>$vf['allowed'],
                );

                $data=array(
                    'request'=>array(
                                        'link'=>"http://registration.unitrends.com",
                                        'registration'=>$registration
                                    )
                );
                break;

            //GET license/summary
            case 'summary':
                if($licenseInfo['class'] === "PHY"){
                    $commandOutput = $this->BP->run_command("System Information", "", $systemID);
                    if ($commandOutput !== false) {
                        parseDPUCommandOutput($commandOutput, $Appliance, $Version, $Kernel, $InstallDate, $OS);
                    }
                    $title = $Appliance;
                }
                else
                $title = isset( $licenseInfo['class']) ? $licenseInfo['class']  : null ;
                $data['title'] = $title;
                break;

            //GET license
            default:
                $class=$this->mapLicenseDescription($licenseInfo['class']);
                $assetTag=$this->BP->get_asset_tag($systemID);
                $nVC = $nRC = 0;
                $data = array(  'name' => isset( $licenseInfo['class']) ? $class  : null ,
                    'install_date' => isset( $licenseInfo['install_date']) ? $licenseInfo['install_date']  : null ,
                    'expiration_date' => isset( $licenseInfo['expiration_date']) ? $licenseInfo['expiration_date'] : null,
                    'feature_string' => isset( $licenseInfo['feature_string']) ? $licenseInfo['feature_string'] : null,
                    'feature_string_description' => isset( $licenseInfo['feature_string']) ? $FriendlyFeature : null,
                    'key' => isset( $licenseInfo['key']) ?  $licenseInfo['key'] : null,
                    'class' => isset($licenseInfo['class']) ? $licenseInfo['class'] : null,
                    'asset_tag' =>  $assetTag ,
                    'mkt_name' => isset($licenseInfo['mkt_name']) ? $licenseInfo['mkt_name'] : null
                );
                break;
        }
        return $data;
    }

    //PUT license
    public function update($which, $inputArray){
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        if(!$inputArray) {
            $data = false;
        }
        else{
            $licenseInfo['version']= "6.3.0";
            $licenseInfo['serial_number']= "D15000";
            $licenseInfo['user_string'] = "150";
            $licenseInfo['feature_string'] = $inputArray['feature_string'];
            $licenseInfo['key'] = $inputArray['key'];
            $licenseInfo['expiration_date'] = isset($inputArray['expiration_date']) ? $inputArray['expiration_date'] : "never";
            $data = $this->BP->save_license_info( $licenseInfo ,$systemID);
        }
        return $data;
    }

    function buildResourceData($resource){

        $data = array(
                                    'applications' => isset($resource['applications']) ? $resource['applications'] : null,
                                    'servers' => isset($resource['servers']) ? $resource['servers'] : null,
                                    'sockets' => isset($resource['sockets']) ? $resource['sockets'] : null,
                                    'vms' => isset($resource['vms']) ? $resource['vms'] : null,
                                    'workstations' => isset($resource['workstations']) ? $resource['workstations'] : null,

        );


    return $data;
    }

    function mapLicenseDescription($class){
        switch ($class) {
            case "UNREG":
                $description = "Unregistered";
                break;
            case "FREE":
                $description = "Free Edition";
                break;
            case "NFR":
                $description = "Not For Resale";
                break;
            case "TRIAL":
                $description = "Trial Edition";
                break;
            case "ENTRB":
                //$description = "Enterprise Edition (per TB)";
                $description = "Enterprise Edition";
                break;
            case "ENTRR":
                //$description = "Enterprise Edition (per Resource)";
                $description = "Enterprise Edition";
                break;
            case "PHY":
                $description = "Enterprise Edition with Physical Unit";
                break;
            default:
                $description = "No Description";
                break;
        }
        return $description;
    }

    /* formatFeatureString() is the function copied from bpl/license.php. It is used to get 'feature_string_description' by parsing 'feature_string'*/

    function parseLicenseString($FeatureString,&$FriendlyFeature, &$ApplianceType, &$TotalCapacity, &$Clients, &$nRC, &$nVC, &$bG = false) {
        //global $Log;
              $stringArray = explode(",", $FeatureString);
        for ($i = 0; $i < count($stringArray); $i++) {
            $LicenseElement = $stringArray[$i];
            $tokenArray = explode('=', $LicenseElement);
            if (count($tokenArray) > 0) {
                if ($tokenArray[0] == "J") {
                    //$Log->writeVariable("token" . $tokenArray[0]);
                    $this->formatPhysicalLicense($stringArray,$FriendlyFeature,$ApplianceType,$TotalCapacity,$Clients, $nRC, $nVC);
                } else {
                    //$Log->writeVariable("token" . $tokenArray[0]);
                     $this->formatVirtualLicense($stringArray,$FriendlyFeature,$ApplianceType,$TotalCapacity,$Clients);

                }

            }
        }
    }


//Physical appliance (no license class) J=120,CSS,MUX=10,VC=0,RC=300G,D2D=300G,ENC,ADX
    function formatPhysicalLicense($stringArray,&$FriendlyFeature,&$ApplianceType, &$TotalCapacity, &$Clients, &$nRC, &$nVC,&$bG = false)
    {
        global $Log;
//	$Log->writeVariable("physical");
        $Size = "";
        $D2Dsize = "";
        $RCsize = "";
        $VCsize = "";
        $Type = "";
        $ENC = "";
        $EXT = "";
        $ADX = "";
        $RDR = "";

        $nCapacity = 0;
        $hasRC = $hasVC = false;

        for ($i = 0; $i < count($stringArray); $i++) {
            $LicenseElement = $stringArray[$i];
            $tokenArray = explode('=', $LicenseElement);
            if (count($tokenArray) > 0) {
                //$Log->writeVariable($tokenArray);
                switch ($tokenArray[0]) {
                    case "D2D":
                        $temp = explode("G", $tokenArray[1]);
                        $D2Dsize = (int)$temp[0];
                        break;
                    case "RC":
                        $temp = explode("G", $tokenArray[1]);
                        $RCsize = (int)$temp[0];
                        $nRC = $RCsize;
                        $n = formatCapacity($tokenArray[1]);
                        if (hasSize($n)) {
                            $hasRC = true;
                            $nRC = $n;
                        }
                        $nCapacity += $n;
                        break;
                    case "VC":
                        $temp = explode("G", $tokenArray[1]);
                        $VCsize = (int)$temp[0];
                        $nVC = $VCsize;
                        $n = formatCapacity($tokenArray[1], $bG);
                        if (hasSize($n)) {
                            $ApplianceType = Constants::SYSTEM_IDENTITY_VAULT;
                            $hasVC = true;
                            $nVC = $n;
                        }
                        $nCapacity += $n;
                        break;
                    case "ENC":
                        $ENC = ", Encryption";
                        break;
                    case "EXT":
                        $EXT = ", External Storage";
                        break;
                    case "ADX":
                        $ADX = ", Cold Backup Copy";
                        break;
                    case "CNI":
                        $Clients = $tokenArray[1];
                        if ($Clients == '0') {
                            $Clients = "None";
                        }
                        break;
                    case "RDR":
                        $RDR = ", Copy Data Management";
                        break;
                    case "MKT":
                        if ($tokenArray[1] == '4') {
                            $RDR = ", Copy Data Management";
                        }
                        break;
                }
            }

            if (($RCsize > 0) && ($VCsize <= 0)) {
                $Type = "System";
                $Size = $RCsize;
            }

            if (($RCsize <= 0) && ($VCsize > 0)) {
                $Type = "Hot Backup Copy";
                $Size = $VCsize;
            }

            if (($RCsize > 0) && ($VCsize > 0)) {
                $Type = "Hot Backup Copy, Backup";
                $Size = $D2Dsize;
            }

            if ($nCapacity > 0) {
                $TotalCapacity = $nCapacity . ' GB';
            }

            if ($hasRC && $hasVC) {
                $ApplianceType = "DPU/DPV";
            }
	    }
        if (empty($FriendlyFeature)) {
            $FriendlyFeature = $Size . "G " . $Type . $ENC . $EXT . $ADX . $RDR;
        }
    }


//Free License							FREE,MUX=10,VM=4,VC=0,RC=inf,D2D=inf,ENC,ADX
//TRAIL LICENSE(WITH NEVER EXPIRE) 		TRAIL,MUX=10,VC=inf,RC=inf,D2D=inf,ENC,ADX
//NOT FOR RESELLER (NFR) LICENSE:		NFR,MUX=10,VM=4,VC=inf,RC=inf,D2D=inf,ENC,ADX
//ENTERPRISE RESOURCE LICENSE			ENTRR,MUX=10,VC=inf,D2D=inf,ENC,ADX,WRK=500,SOC=2,SRV=1,APP=1
//ENTERPRISE BYTE LICENSE				ENTRB,MUX=10,VC=inf,RC=300G,D2D=inf, ENC, ADX

    function formatVirtualLicense($stringArray,&$FriendlyFeature,&$ApplianceType, &$TotalCapacity, &$Clients,&$bG = false) {

        global $Log;
        //$Log->writeVariable("vRecovery");
        $Size = "";
        $Type = "";
        $ENC = "";
        $EXT = "";
        $ADX = "";
        $D2Dsize = "";
        $RCsize = "";
        $VCsize = "";
        $RDR = "";

        $nCapacity = 0;

        $hasRC = $hasVC = false;

        for ($i = 0; $i < count($stringArray); $i++) {
            $LicenseElement = $stringArray[$i];
            //	$Log->writeVariable($LicenseElement);
            $LicenseType = $LicenseElement[0];
            $tokenArray = explode('=', $LicenseElement);

            if (count($tokenArray) > 0) {
                //$Log->writeVariable($tokenArray);

                switch($tokenArray[0]) {
                    case "D2D":
                        $temp = explode ("G", $tokenArray[1]);
                        $D2Dsize = $this-> getSize($temp[0]);
                        break;
                    case "RC":
                        $temp = explode ("G", $tokenArray[1]);
                        $RCsize = $this->getSize($temp[0]);
                        $n = formatCapacity($tokenArray[1]);
                        if (hasSize($n)) {
                            $hasRC = true;
                            $nRC = $n;
                        }
                        $nCapacity += $n;
                        break;
                    case "VC":
                        $temp = explode ("G", $tokenArray[1]);
                        $VCsize = $this-> getSize($temp[0]);
                        $n = formatCapacity($tokenArray[1], $bG);
                        if (hasSize($n)) {
                            $ApplianceType = Constants::SYSTEM_IDENTITY_VAULT;
                            $hasVC = true;
                            $nVC = $n;
                        }
                        $nCapacity += $n;
                        break;
                    case "VM":
                        $VMs = $tokenArray[1];
                    case "ENC":
                        $ENC =  ", Encryption";
                        break;
                    case "EXT":
                        $EXT = ", External Storage";
                        break;
                    case "ADX":
                        $ADX = ", Cold Backup Copy";
                        break;
                    case "CNI":
                        $Clients = $tokenArray[1];
                        if ($Clients == '0') {
                            $Clients = "None";
                        }
                        break;
                    case "RDR":
                        $RDR = ", Copy Data Management";
                        break;
                    case "MKT":
                        if ($tokenArray[1] == '4') {
                            $RDR = ", Copy Data Management";
                        }
                        break;
                }
            }
        }

        if ($VCsize == "INF" && ($RCsize == "INF" || $D2Dsize == "INF")) {
            // All are infinite; backups size is either RC or D2D.
            $Type = 'Unlimited Hot Backup Copies and Backups';
        } else if ($VCsize == "INF" && $RCsize !== "INF" && $D2Dsize !== "INF") {
            $Type = 'Unlimited Hot Backup Copies';
        } else if (($RCsize == "INF" || $D2Dsize == "INF") && $VCsize !== "INF") {
            $Type = 'Unlimited Backups';
        } else {
            // None are infinite, get size.
            $fixedSizeType = $this-> calcFixedSize($D2Dsize, $RCsize, $VCsize);
            if (empty($FriendlyFeature)) {
                $FriendlyFeature = $fixedSizeType;
            }
        }

        if ($nCapacity > 0) {
            $TotalCapacity = $nCapacity . ' GB';
        }

        if ($hasRC && $hasVC) {
            $ApplianceType = "DPU/DPV";
        }
        if (empty($FriendlyFeature)) {
            $FriendlyFeature .= $Type . $ENC . $ADX . $RDR;
        }

    }

    function getSize($element) {
        if (strtoupper($element) == 'INF') {
            return strtoupper($element);
        } else {
            return (float)$element . "G";
        }
    }

    function calcFixedSize($D2Dsize, $RCsize, $VCsize) {
        if(($RCsize > 0) && ($VCsize <= 0)) {
            $Type = " Backup";
            $Size = $RCsize;
        }

        if(($RCsize <= 0) && ($VCsize > 0)) {
            $Type = "Hot Backup Copy";
            $Size = $VCsize;
        }

        if(($RCsize > 0) && ($VCsize > 0)) {
            $Type = "Hot Backup Copy, Backup";
            $Size = $D2Dsize;
        }
        return $Size . $Type;
    }

}

//
// This function parses the output of the DPU command to get appliance information.
//
// This data is returned in the following format:
//
//	Unitrends DPU Recovery-300 - Thu Jan 15 15:42:59 EST 2009
//	Unitrends' DPU Kernel 2.6.26.3-2.RecoveryOS-smp
//	Version 4.0.2-1.CentOS
//	Installed on Thu 15 Jan 2009 03:44:17 PM EST
//
//
function parseDPUCommandOutput($output, &$Appliance, &$Version, &$Kernel, &$InstallDate, &$CoreOS)
{
    //global $xml;
    $outputArray = split("\n", $output);
    for ($i = 0; $i < count($outputArray); $i++) {
        $line = $outputArray[$i];
        //$xml->element('line', $line);
        if (strstr($line, DPU) && !( strstr( $line, UEB_HYPERV ) && !( strstr( $line, UEB_HYPER_V)) ) && !( strstr( $line, UEB_VMWARE ) ) ) {
            $lineArray = split(" ", $line);
            $Appliance = $lineArray[2];
        } else if (strstr($line, RECOVERY)) {
            $lineArray = split(" ", $line);
            $Appliance = $lineArray[1];
        } else if (strstr($line, SFF)) {
            $lineArray = split(" ", $line);
            $Appliance = "SFF";  // was $lineArray[5];
        } else if (strstr($line, UEB_HYPERV) || strstr($line, UEB_HYPER_V)) {
            $Appliance = "Unitrends Backup (Hyper-V)";
        } else if (strstr($line, UEB_VMWARE)) {
            $Appliance = "Unitrends Backup (VMware)";
        } else if (strstr($line, VERSION)) {
            $lineArray = split(" ", $line);
            $fullVersion = $lineArray[1];
            if ($location = strrpos($fullVersion, '.')) {
                $Version = substr($fullVersion, 0, $location);
                $CoreOS = substr($fullVersion, $location + 1);
            }
        } else if (strstr($line, INSTALL_DATE)) {
            $location = strlen(INSTALL_DATE) + 1;
            $InstallDate = substr($line, $location);
        } else if (strstr($line, OS_KERNEL)) {
            $location = strlen(OS_KERNEL) + 1;
            $Kernel = substr($line, $location);
        }
    }
}


function parseProcessorCommandOutput($output, $bVirtual, &$ProcessorType, &$ProcessorCores, &$ProcessorCache, &$ProcessorFrequency) {
    $outputArray = split("\n", $output);
    $cores = 0;
    for ($i = 0; $i < count($outputArray); $i++) {
        $line = $outputArray[$i];
        $processorArray = explode(':', $line);
        if (count($processorArray) > 1) {
            if (strstr($processorArray[0], "model name")) {
                $ProcessorType = $processorArray[1];
            } else if (strstr($processorArray[0], "cpu cores")) {
                $ProcessorCores = $processorArray[1];
            } else if (strstr($processorArray[0], "cache size")) {
                $ProcessorCache = $processorArray[1];
            } else if (strstr($processorArray[0], "cpu MHz")) {
                $frequency = (float)$processorArray[1] / 1000.0;		// convert MHz to GHz
                $ProcessorFrequency = $frequency . " GHz";
            } else if (strstr($processorArray[0], "processor")) {
                $cores++;
            }
        }
    }
    // Number of processors is total core count.
    $ProcessorCores = $cores;
}

function parseMemoryCommandOutput($output, &$MemorySize, &$MemoryGB)
{
    $outputArray = split("\n", $output);
    for ($i = 0; $i < count($outputArray); $i++) {
        $line = $outputArray[$i];
        $processorArray = explode(' ', $line);
        if (count($processorArray) > 2) {
            if (strstr($processorArray[0], "Mem:")) {
                for ($j = 1; $j < count($processorArray); $j++) {
                    if ($processorArray[$j] != " " && $processorArray[$j] != "") {
                        $MemoryGB = (float)$processorArray[$j]; 			// size in MB.
                        $MemoryGB = $MemoryGB / 1000.0; 						// convert to GB
                        $MemorySize = $MemoryGB . " GB";
                        break;
                    }
                }
            }
        }
    }
}

function formatCapacity($capacity, &$bG = false)
{
    $nCapacity = 0;
    // Handle infinite
    if (strtoupper($capacity) == "INF") {
        $nCapacity = "INF";
    } else {
        $GBLocation = strpos($capacity, "G");
        if ($GBLocation !== false) {
            $bG = true;
            $nCapacity = (int)substr($capacity, 0, $GBLocation);
        } else {
            $bG = false;
            $strCapacity = "";
            if (strlen($capacity) > 0) {
                $capacity = str_split($capacity);
                for ($i = 0; $i < count($capacity); $i++) {
                    if ($capacity[$i] >= '0' && $capacity[$i] <= '9') {
                        $strCapacity .= $capacity[$i];
                    } else {
                        break;
                    }
                }
                if ($strCapacity != "") {
                    $nCapacity = (int)$strCapacity;
                }
            }
        }
    }
    return $nCapacity;
}

function hasSize($n) {
    return $n > 0 || strtoupper($n) == "INF";
}

  //End License

?>