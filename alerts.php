<?php

class Alerts
{
    private $BP;

    public function __construct($BP, $sid)
    {
        $this->BP = $BP;
        $this->now = time();
        $this->sid = $sid; //!== false ? $sid : $this->BP->get_local_system_id();

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);
    }


    public function get($which, $filter, $sid)
    {
        $input = array();
        $data = array();
        $days = isset($_GET['days']) ? $_GET['days'] : "6"; // today -6 days returns seven days of data
        $startDate = strtotime('today -'  . $days .'days');
        $endDate = false;
        if ( isset($_GET['end_date']) )
        {
            $endDate = $this->functions->formatEndDate($_GET['end_date']);
        }

        $sortOrder = isset($_GET['order']) ? $_GET['order'] : "descending";
        $sortBy = "timestamp";

        if ($sortOrder == "asc") {
            $sortOrder = "ascending";
        }

        $input['start_time'] = isset($_GET['start_date']) ? strtotime($_GET['start_date']) : $startDate;
        if ( $endDate !== false )
        {
            $input['end_time'] = $endDate;
        }

        if(isset($which[0]) && $which[0] == "?"){$which = null;}  //don't consider $which if it's after the ? on the url
        if(isset($filter[0]) && $filter[0] == "?"){$filter = null;}  //don't consider $filter if it's after the ? on the url

        if ($which == "open" || $which == "closed"){
            $input['resolved'] = $which == "open" ? false : true;
        }

        $maxItems = isset($_GET['max_items']) ? (int)($_GET['max_items']) : INF;
        $apiCount = isset($_GET['count']) ? (int)($_GET['count']) : 0;
        if ($apiCount > 0) {
            $input['count'] = $apiCount;
        }
        $overflow = false;

        if ($sid !== false){
            $input['dpu'] = $sid;
            $name = $this->functions->getSystemNameFromID($sid);
            $alerts = $this->BP->get_alerts($sortOrder, $sortBy, $input);
            if (!is_infinite($maxItems) && count($alerts) > $maxItems) {
                $alerts = array_slice($alerts, 0, $maxItems);
                $overflow = true;
            }
            $data["data"] = $this->processAlerts($alerts, $name, $sid, $which, $filter);

        } else {
            $systems = $this->functions->selectSystems();
            $data["data"] = array();
            $items = 0;
            foreach($systems as $sid => $name){
                if ($items < $maxItems) {
                    $input['dpu'] = $sid;
                    $alerts = $this->BP->get_alerts($sortOrder, $sortBy, $input);
                    if ($alerts !== false) {
                        if (!is_infinite($maxItems) && count($alerts) > ($maxItems - $items)) {
                            $alerts = array_slice($alerts, 0, $maxItems - $items);
                            $overflow = true;
                        }
                        $items += count($alerts);
                        $data["data"] = array_merge($data["data"], $this->processAlerts($alerts, $name, $sid, $which, $filter));
                    }
                }
            }
        }

        if ($overflow) {
            $data['more'] = true;
        }
        return $data;
    }


    public function close($which, $sid){
        $data = $this->BP->close_alert($which, $sid);
        return $data;
    }

    // This function is also used by reports/storage-status-report.php
    public function processAlerts($alerts, $name, $sid, $which = null, $filter = null){
        $data = array();
        foreach ($alerts as $alert) {
            if (is_numeric($which) and $which > 0 and $alert['id'] != $which) {
                continue;
            }
            if ($which && is_string($which) and $which !== $alert['severity'] && $which !== "open" && $which !== "closed"){
                continue;
            }
            if ($filter && is_string($filter) and $filter !== $alert['severity']){
                continue;
            }
            $data[] = $this->buildOutput($alert, $name, $sid);
        }

        return $data;
    }
    function buildOutput($alert, $sysName, $sid){
        $data = array(
            'created' => $this->functions->reportDateTime($alert['timestamp']),
            'id' => $alert['id'],
            'message' => $alert['text'],
            'resolved' => $alert['resolved'],
            'severity' => $alert['severity'],
            'system_id' => $sid,
            'sname' => $sysName,
            'source_id' => $alert['source_id'],
            'source_name' => $alert['source_name'],
            'updated' => $this->functions->reportDateTime($alert['updated'])
            );

        return $data;

    }

} // end Alerts class

?>
