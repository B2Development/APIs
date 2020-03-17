<?php

class Quiesce
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;
    }

    public function getQuiesceSettingDisplayName( $quiesceSetting )
    {
        $quiesceSettingDisplayName = '';
        switch ( $quiesceSetting )
        {
            case Constants::QUIESCE_SETTING_APPLICATION_CONSISTENT:
                $quiesceSettingDisplayName = Constants::QUIESCE_SETTING_DISPLAY_NAME_APPLICATION_CONSISTENT;
                break;
            case Constants::QUIESCE_SETTING_APPLICATION_AWARE:
                $quiesceSettingDisplayName = Constants::QUIESCE_SETTING_DISPLAY_NAME_APPLICATION_AWARE;
                break;
            case Constants::QUIESCE_SETTING_CRASH_CONSISTENT:
                $quiesceSettingDisplayName = Constants::QUIESCE_SETTING_DISPLAY_NAME_CRASH_CONSISTENT;
                break;
        }
        return $quiesceSettingDisplayName;
    }

    public function getQuiesceSettingfromDisplayName( $quiesceSettingDisplayName )
    {
        $quiesceSetting = false;
        switch ( $quiesceSettingDisplayName )
        {
            case Constants::QUIESCE_SETTING_DISPLAY_NAME_APPLICATION_CONSISTENT:
                $quiesceSetting = Constants::QUIESCE_SETTING_APPLICATION_CONSISTENT;
                break;
            case Constants::QUIESCE_SETTING_DISPLAY_NAME_APPLICATION_AWARE:
                $quiesceSetting = Constants::QUIESCE_SETTING_APPLICATION_AWARE;
                break;
            case Constants::QUIESCE_SETTING_DISPLAY_NAME_CRASH_CONSISTENT:
                $quiesceSetting = Constants::QUIESCE_SETTING_CRASH_CONSISTENT;
                break;
        }
        return $quiesceSetting;
    }

    public function get_quiesce($which, $data, $sid, $systems)
    {
        $returnArray = array();

        if ($which == -1)
        {
            // GET /api/quiesce/?sid={sid}
            $returnArray = $this->get_global_quiesce_setting( $data, $systems );

        }
        else //if (is_string($which[0]))
        {
            switch ($which[0])
            {
                case 'global':
                    // GET /api/quiesce/global/?sid={sid}
                    $returnArray = $this->get_global_quiesce_setting( $data, $systems );
                    break;
                default:
                    // GET /api/quiesce/global/?sid={sid}
                    $returnArray = $this->get_global_quiesce_setting( $data, $systems );
                    break;
            }

        }
        return($returnArray);
    }

    private function get_global_quiesce_setting( $data, $systems )
    {
        $global_quiesce_settings_array = array();
        foreach ( $systems as $systemID => $systemName )
        {
            if ( $this->BP->is_quiesce_supported($systemID) === true )
            {
                $global_quiesce_settings_array[] = array(   'system_name' => $systemName, 'system_id' => $systemID,
                                                            'quiesce_setting' => $this->getQuiesceSettingDisplayName($this->BP->get_default_quiesce_setting($systemID)));
            }
        }
        //$global_quiesce_settings_array = array( 'quiesce_settings' => $this->sort($global_quiesce_settings_array, 'system_name') );
        return array( 'quiesce_settings' => $global_quiesce_settings_array);
    }

    public function put_quiesce($which, $data, $sid){
        $systemID = isset($_GET['sid']) ? (int)$_GET['sid'] : $this->BP->get_local_system_id();

        if (!isset($data['quiesce_setting'])) {
            $return = array('error' => 500, 'message' => "Missing inputs: 'quiesce_setting' is a required input");
        } else {
            $quiesce_setting = $this->getQuiesceSettingfromDisplayName($data['quiesce_setting']);
            $overwrite = (isset($data['overwrite']) and $data['overwrite'] === true);
            $return = $this->BP->set_default_quiesce_setting($quiesce_setting, $overwrite, $systemID);
        }

        return $return;
    }

} //End Quiesce

?>