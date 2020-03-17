<?php

class AuditHistory
{
    private $BP;

    public function __construct($BP, $sid)
    {
        $this->BP = $BP;
        $this->now = time();
        $this->sid = $sid;

        require_once('function.lib.php');
        $this->functions = new Functions($this->BP);
    }


    public function get($which)
    {
        $data = array();
        $startDate = isset($_GET['start_date']) ? strtotime($_GET['start_date']) : strtotime(Constants::DATE_ONE_WEEK_AGO);
        $endDate = isset($_GET['end_date']) ? $this->functions->formatEndDate($_GET['end_date']) : strtotime('now');
        $sort = (isset($_GET['sort'])) ? $_GET['sort'] : false;
        $limit = (isset($_GET['limit']) && (int)$_GET['limit'] > 0) ? (int)$_GET['limit'] : false;
        $includeLogins = (isset($_GET['include_logins']) ? true : false);
        $limitHit = false;

        if ($this->sid !== false) {
            $filter = array(
                'start_time' => $startDate,
                'end_time' => $endDate
            );

            $audits = $this->BP->get_audit_history($filter, $this->sid);
            $data["data"] = $this->processAudits($audits, $which, $includeLogins);
        }else {
            $systems = $this->functions->getSystems();
            $data['data'] = array();
            foreach ($systems as $sid => $name) {
                $filter = array(
                    'start_time' => $startDate,
                    'end_time' => $endDate
                );

                $audits = $this->BP->get_audit_history($filter, $sid);
                $data["data"] = array_merge($data['data'], $this->processAudits($audits, $which, $includeLogins));
            }
        }

        if ($sort !== false) {
            $this->sort($data, $sort);

            if ($limit > 0 && count($data['data']) > $limit) {
                $limitHit = true;
                array_splice($data['data'], $limit, count($data['data']) - $limit);
            }
        }

        $data['count'] = count($data['data']);
        $data['limited'] = $limitHit;

        return $data;
    }

        function processAudits($audits, $which, $includeLogins)
        {
            $data = array();
            foreach ($audits as $audit) {
                if(isset($audit['event_time'])){
                    $dateString = strtotime($audit['event_time']);

                    $tempTime = $this->functions->formatDateTime($dateString);
                    $audit['event_time'] = $tempTime;
                    $audit['sort_start_time'] = $dateString;
                    $notificationID = $audit['notification_id'];
                }

                if (!$includeLogins && $notificationID == 124){
                    continue;
                }
                if (is_numeric($which) and $which > 0 and $audit['notification_id'] != $which) {
                    continue;
                }
                if (is_string($which) and $which != $audit['username']) {
                    if ($which == "history") {
                        $user = $audit['username'];
                        $skip = strpos($user, "bypass") !== false || $notificationID == 124;
                        if ($skip) {
                            continue;
                        }
                    } else {
                        continue;
                    }
                }
                if ($which) {
                    $data[] = $audit;
                }
            }
            return $data;
        }
    // Sorts the Array by timestamp
        protected function sort(&$Array, $dir) {
            $direction = ($dir == "asc") ? SORT_ASC : SORT_DESC;
            $sortKey = array();
            foreach ($Array as $row) {
                $sortKey[] = isset($row['sort_start_time']) ? $row['sort_start_time'] : "";
            }
            array_multisort($sortKey, $direction, $Array);
        }
    }
?>
