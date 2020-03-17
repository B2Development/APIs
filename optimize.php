<?php

/**
 * Created by PhpStorm.
 * User: Michelle
 * Date: 5/29/2016
 * Time: 2:34 PM
 */
class Optimize
{
    private $BP;

    public function __construct($BP)
    {
        $this->BP = $BP;
    }

    function get_optimize($type, $sid)
    {
        $sid = ($sid !== false) ? $sid : $this->BP->get_local_system_id();

        $multipleTypes = false;
        
        if (strpos($type, ',')){
            $multipleTypes = true;
            $types = explode(",", $type);
        }

    $optimizeSettings = array();
        if ($multipleTypes){
            foreach ($types as $index=>$type){
                $optimize = $this->BP->get_optimize((int)$type, $sid);
                if ($optimize !== false){
                    $tempOptimizeSettings = $this->buildOutput($optimize, $type, $sid);
                    $optimizeSettings = array_merge($optimizeSettings, $tempOptimizeSettings);
                } else {
                    $data['error'] = 500;
                    $data['message'] = $this->BP->getError();
                }
            }
        } else {
            $type = $type == -1 ? 1 : $type;

            $optimize = $this->BP->get_optimize((int)$type, $sid);
            if ($optimize !== false) {
                $optimizeSettings = $this->buildOutput($optimize, $type, $sid);
            } else {
                $data['error'] = 500;
                $data['message'] = $this->BP->getError();
            }    
        }
        
        $data['data'] = $optimizeSettings;

        return $data;
    }

    public function update($inputArray, $sid)
    {
        $sid = ($sid !== false) ? $sid : $this->BP->get_local_system_id();

        $data = $this->BP->set_optimize($inputArray, $sid);
        if ($data === false){
            $data['error'] = 500;
            $data['message'] = $this->BP->getError();
        }

        return $data;
    }

    function set_database($sid)
    {
        $devname = isset($_GET['devname']) ? $_GET['devname'] : null;
        $sid = ($sid !== false && $sid !== null) ? $sid : $this->BP->get_local_system_id();

        $data = $this->BP->move_database($devname, $sid);
        if ($data == false){
            $data['error'] = 500;
            $data['message'] = $this->BP->getError();
        }

        return $data;
    }


    function buildOutput($data, $type, $sid)
    {
        $asset = isset($data['asset']) ? $data['asset'] : null;
        $iops = isset($data['iops']) ? $data['iops'] : null;
        $movedb = isset($data['movedb']) ? $data['movedb'] : null;
        $dedup_level = isset($data['dedup_level']) ? $data['dedup_level'] : null;
        $mux = isset($data['mux']) ? $data['mux'] : null;
        $mux_max = isset($data['mux_max']) ? $data['mux_max'] : null;
        $mux_min = isset($data['mux_min']) ? $data['mux_min'] : null;
        $compression = isset($data['compr']) ? $data['compr'] : null;
        $comment = isset($data['comment']) ? $data['comment'] : false;

        switch ($type) {
            case 1: //current
                $data = array(
                    'current_asset' => $asset,
                    'current_iops' => $iops,
                    'current_movedb' => $movedb,
                    'current_dedup_level' => $dedup_level,
                    'current_mux' => $mux,
                    'current_mux_max' => $mux_max,
                    'current_mux_min' => $mux_min,
                    'current_compr' => $compression
                );
                break;
            case 2: //trial
                $data = array(
                    'trial_asset' => $asset,
                    'trial_iops' => $iops,
                    'trial_movedb' => $movedb,
                    'trial_dedup_level' => $dedup_level,
                    'trial_mux' => $mux,
                    'trial_mux_max' => $mux_max,
                    'trial_mux_min' => $mux_min,
                    'trial_compr' => $compression
                );
                break;
            case 3: //recommended
                $data = array(
                    'recommended_asset' => $asset,
                    'recommended_iops' => $iops,
                    'recommended_movedb' => $movedb,
                    'recommended_dedup_level' => $dedup_level,
                    'recommended_mux' => $mux,
                    'recommended_mux_max' => $mux_max,
                    'recommended_mux_min' => $mux_min,
                    'recommended_compr' => $compression,
                    'comment' => $comment
                );
                break;
        }

        return $data;
    }
}
?>