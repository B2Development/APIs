<?php

class Settings
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;
    }

    public function get($sectionName, $sid)
    {
        $data = false;
        $showFlat = isset($_GET['showFlat']);

        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();
        if ($sectionName !== -1) {
            $sectionName = urldecode($sectionName);
            $sectionName = str_replace('^', ' ', $sectionName);
            $section = $this->BP->get_ini_section($sectionName, $systemID);
            if ($section !== false) {
                $data[$sectionName] = $section;
            }
        } else {
            $sections = $this->BP->get_ini_sections($systemID);
            if ($sections !== false) {
                $data = array();
                foreach ($sections as $sectionName) {
                    $section = $this->BP->get_ini_section($sectionName, $systemID);
                    if ($section !== false) {
                        if ($showFlat) {
                            // Include section name in each item rather than parent section, children items.
                            foreach ($section as $item) {
                                $item['section'] = $sectionName;
                                $data[] = $item;
                            }
                        } else {
                            $data[$sectionName] = $section;
                        }
                    } else {
                        $badLoad = array('field' => 'error loading settings', 'value' => $this->BP->getError());
                        $data[$sectionName] = array($badLoad);
                    }
                }
                if ($showFlat) {
                    $data = array('data' => $data);
                }
            }
        }
        return $data;
    }

    public function put($data, $sid){
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();

        $return = false;
        $errors = false;
        $configuration = array();
        $sectionName = "";
        if (isset($data['section'])) {
            $sectionName = $data['section'];
            if (!isset($data['data'])) {
                // associative array - one entry
                $return = $this->buildEntry($data, $errors);
                if (!$errors) {
                    $configuration[] = $return;
                }
            } else {
                $data = $data['data'];
                // non-associative array, multiple entries
                foreach ($data as $item) {
                    $return = $this->buildEntry($item, $errors);
                    if (!$errors) {
                        $configuration[] = $return;
                    } else {
                        break;
                    }
                }
            }
        } else {
            $errors = true;
            $entry = array('status' => 500, 'message' => 'Master.ini section name must be specified.');
        }
        if (!$errors) {
            $return = $this->BP->set_ini_section($sectionName, $configuration, $systemID);
        }

        return $return;
    }

    private function buildEntry($item, &$errors) {
        if (isset($item['field'])) {
            $field = $item['field'];
            if (isset($item['value'])) {
                $value = $item['value'];
                $entry = array('field' => $field, 'value' => $value, 'description' => '');
            } else {
                $errors = true;
                $entry = array('status' => 500, 'message' => 'Master.ini field value must be specified.');
            }
        } else {
            $errors = true;
            $entry = array('status' => 500, 'message' => 'Master.ini field name must be specified.');
        }
        return $entry;
    }

} //End Settings

?>
