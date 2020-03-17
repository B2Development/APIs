<?php

class Networks
{
    private $BP;
	private $sid;

    public function __construct($BP, $sid)
    {
		$this->BP = $BP;
		$this->sid = $sid;
    }
 
    public function get($which, $filter, $data, $sid) {
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        $secondFilter = isset($filter[2]) ? $filter[2] : null;
        switch($which){
            case "bridge":
                //check if nic is provided (Eg: eth0,eth1 and so on)
                if($secondFilter[0]=="e"){
                    $Bridge=$this->BP->get_nic_bridge($secondFilter,$systemID);
                    $networkList=array('bridge' => $Bridge ? $Bridge : "");
                }
                //Else if just systemID is provided
                else{
                    $Bridge=$this->BP->get_virtual_bridge($systemID);
                    if(isset($Bridge)){
                        $networkList['bridge']=$Bridge[0];
                        for($i=1;$i<count($Bridge);$i++){
                            $nics[]=$Bridge[$i];
                        }
                        $networkList['nics']=$nics;
                    }
                }
                break;

            case "virtual_bridge":
                $systemID = $this->BP->get_local_system_id();
                $virtualBridge=$this->BP->get_virtual_bridge_network($systemID);
                $bridgedata=array(
                                'network'=>$virtualBridge['NETWORK'],
                                'netmask'=>$virtualBridge['NETMASK'],
                                'gateway'=>$virtualBridge['GATEWAY'],
                                'dhcprange'=>$virtualBridge['DHCPRANGE'],
                                'message'=>"",
                                );
                $networkList=array('bridge'=>$bridgedata);
                break;

            case "hypervisor-switch":
                if (isset($data['hypervisor_type'])) {
                    $hypervisor_type = $data['hypervisor_type'] . ' host';
                    if (isset($data['hypervisor'])) {
                        $hypervisor = $data['hypervisor'];
                        if ($secondFilter == "info"){
                            $switches = $this->BP->get_hypervisor_network_switches_info($hypervisor_type, $hypervisor, $systemID);
                            if ($switches !== false) {
                                foreach ($switches as $switch) {
                                    $networkList['switches'][] = $switch;
                                }
                            } else {
                                $networkList = $switches;
                            }
                        } else {
                            $switches = $this->BP->get_hypervisor_network_switches($hypervisor_type, $hypervisor, $systemID);
                            if ($switches !== false) {
                                natsort($switches);
                                foreach ($switches as $switch) {
                                    $networkList['names'][] = $switch;
                                }
                            } else {
                                $networkList = $switches;
                            }
                        }
                    } else {
                        $networkList['error'] = 500;
                        $networkList['message'] = "The hypervisor name is to be specified.";
                        break;
                    }
                } else {
                    $networkList['error'] = 500;
                    $networkList['message'] = "The type of the hypervisor is to be specified.";
                }
                break;

            case "ir":
                if (isset($data['hypervisor_type'])) {
                    if (isset($data['iid'])) {
                        $iid = $data['iid'];
                        $hypervisorType = $data['hypervisor_type'];
                        $networks = $this->BP->get_ir_vm_network_info($hypervisorType, $iid, $systemID);

                        if ($networks !== false) {
                            foreach ($networks as $network) {
                                $newNetwork['network_adapter'] = $network['label'];
                                $newNetwork['network_connection'] = $network['network_name'];
                                $newNetwork['network_connection_moref'] = $network['network_moref'];
                                $networkList['networks'][] = $newNetwork;
                            }
                        } else {
                            $networkList = $networks;
                        }
                    } else {
                        $networkList['error'] = 500;
                        $networkList['message'] = "The instance id is to be specified.";
                    }
                } else {
                    $networkList['error'] = 500;
                    $networkList['message'] = "The type of the hypervisor is to be specified.";
                }
                break;

            default:
                $networks = $this->BP->get_network_list($this->sid);
                $networkList = array();

                if ($networks === false) {
                    ;  //do what?  message?
                } else {
                    foreach ($networks as $network) {
                        $dns = array();
                        $search = array();
                        $networkName = $network;
                        $networkInfo = $this->BP->get_network_info($networkName, $this->sid);

                        $dnsList = $this->BP->get_dns_list($this->sid);
                        if ($dnsList !== false) {
                            $searchList = $this->BP->get_dns_search_list($this->sid);
                            if ($searchList !== false){
                                foreach ($dnsList as $dnsIP) {
                                    $dns[] = $dnsIP;

                                }

                                foreach ($searchList as $dnsDomain){
                                    $search[] = $dnsDomain;

                                }
                                $search = implode(', ', $search);
                            }
                        }
                        $data = $this->buildOutput($networkName, $networkInfo, $dns, $search);
                        $list[] = $data;
                    }
                    $networkList['networks'] = $list;

                }
                break;
        }


        return $networkList;

    }

    public function update($which,$filter, $inputArray,$sid){
        $result = true;
        $dpuID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();

        switch($which){

            case "virtual_bridge":
                if($inputArray['network']!==NULL){
                    $result=$this->BP->set_virtual_bridge($inputArray['network'], $dpuID);
                }
                break;

            //GET/api/networks/eth0 or eth1 etc.

            default:

                if(!$inputArray) {
                    return false;
                }
                $currentNetworkInfo = $this->BP->get_network_info($which,$dpuID);

                if(isset($inputArray['ip'])){
                    $currentNetworkInfo['ip'] = $inputArray['ip'];
                }
                if(isset($inputArray['netmask'])){
                    $currentNetworkInfo['netmask'] = $inputArray['netmask'];
                }
                if(isset($inputArray['gateway'])){
                    $currentNetworkInfo['gateway'] = $inputArray['gateway'];
                }
                if(isset($inputArray['boot'])){
                    $currentNetworkInfo['boot'] = $inputArray['boot'];
                }
                if(!isset($inputArray['auto_restore'])){
                    $currentNetworkInfo['auto_restore'] = false;
                } else {
                    $currentNetworkInfo['auto_restore'] = $inputArray['auto_restore'];
                    if ($currentNetworkInfo['auto_restore'] == true) {
                        if (isset($inputArray['countdown_time'])) {
                            $currentNetworkInfo['countdown_time'] = $inputArray['countdown_time'];
                        } else {
                            $data = array();
                            $data['error'] = 500;
                            $data['message'] = 'For auto_restore, the countdown_time must be specified.';
                            return $data;
                        }
                    }
                }
                $saveNetworkInfoResult = $this->BP->save_network_info($currentNetworkInfo, $dpuID);
                if($saveNetworkInfoResult === false){
                    return false;
                }


                if (isset($inputArray['dns1'])){
                    $DNSInfo[0] = $inputArray['dns1'];
                    if(isset($inputArray['dns2']) and $inputArray['dns2'] !== ""){
                        $DNSInfo[1] = $inputArray['dns2'];
                    }
                    $saveDNSInfoResult = $this->BP->save_dns_list($DNSInfo,$dpuID);
                } else if(isset($inputArray['dns'])){
                    $DNSInfo = $inputArray['dns'];
                    $saveDNSInfoResult = $this->BP->save_dns_list($DNSInfo,$dpuID);
                }
                if (isset($saveDNSInfoResult) && ($saveDNSInfoResult === false)){
                    $result = array('error' => 500,
                        'message' => 'Network address information saved successfully.  ' .
                            'Error saving DNS list: ' . $this->BP->getError());
                }

                //$searchList=$this->BP->get_dns_search_list($dpuID);
                if(isset($inputArray['search'])){
                    $input = str_replace(" ", "", $inputArray['search']);
                    if ($input === "") {
                        $searchList = array();
                    } else {
                        $searchList = explode(",", $input);
                    }
                    $saveDNSSearchResult = $this->BP->save_dns_search_list($searchList,$dpuID);
                }
                if (isset($saveDNSSearchResult) && ($saveDNSSearchResult === false)){
                    $result = array('error' => 500,
                        'message' => 'Network address information saved successfully.  ' .
                            'Error saving DNS search list: ' . $this->BP->getError());
                }
                break;
        }


        return $result;

    }

    public function post($which, $inputArray, $sid) {
        $dpuID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        if($which=="bridge"){
            $bridge=$this->BP->add_virtual_bridge($inputArray['nic'],$dpuID);
        }
        $output['id'] = $bridge;
        $result = array('result' => $output);
        return $result;
    }

    function validateIP($ip){
        return inet_pton($ip) !== false;
    }

    public function delete($which,$filter,$sid)
    {
        $data = null;
        $sid = $sid === false ? $this->BP->get_local_system_id() : $sid;
        if ($which == "bridge"){
            if (isset($filter[2])) {
                $nic = $filter[2];
                if (isset($_GET['bridge'])) {
                    $bridge = $_GET['bridge'];
                    $data=$this->BP->delete_virtual_bridge($nic, $bridge, $sid);
                } else {
                    $data = array();
                    $data['error'] = 500;
                    $data['message'] = 'Bridge name must be specified when deleting a bridge.';
                }
            } else {
                $data = array();
                $data['error'] = 500;
                $data['message'] = 'Network interface name must be specified when deleting a bridge.';
            }
        } else if ($which == "restore") {
            $data = $this->BP->stop_network_restore($sid);
        }
        return $data;
    }

    function buildOutput($networkName, $networkInfo, $dns, $search) {

        $ip = isset($networkInfo['ip']) ? $networkInfo['ip'] : null;
        $netmask = isset($networkInfo['netmask']) ? $networkInfo['netmask'] : null;
        $gateway = isset($networkInfo['gateway']) ? $networkInfo['gateway'] : null;
        $boot = isset($networkInfo['boot']) ? $networkInfo['boot'] : null;
        $link = isset($networkInfo['link']) ? $networkInfo['link'] : null;
        $speed = isset($networkInfo['speed']) ? $networkInfo['speed'] : null;
        $duplex = isset($networkInfo['duplex']) ? $networkInfo['duplex'] : null;
        $MAC = isset($networkInfo['hwaddr']) ? trim($networkInfo['hwaddr'], '"') : null;

        $dns1 = isset($dns[0]) ? $dns[0] : null;
        $dns2 = isset($dns[1]) ? $dns[1] : null;

        $data = array(
            'id' => $networkName,
            'name' => $networkName,
            'dns' => $dns,
            'dns1' => $dns1,
            'dns2' => $dns2,
            'search' => $search,
            'ip' => $ip,
            'netmask' => $netmask,
            'gateway' => $gateway,
            'mac' => $MAC,
            'boot' => $boot,
            'link' => $link,
            'speed' => $speed,
            'duplex' => $duplex
        );

        return $data;
    }
} // end Networks class

?>
