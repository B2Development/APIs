<?php

class Commands
{
    private $BP;

    public function __construct($BP, $sid)
    {
        $this->BP = $BP;
        $this->now = time();
        $this->sid = $sid;

    }

    public function get_commands($sid)
    {
        $sid = $sid == false ? $this->BP->get_local_system_id() : $sid;
        $commands = array();
        $commandList = $this->BP->get_command_list($sid);
        if ($commandList !== false) {
            foreach ($commandList as $command) {
                $info = $this->BP->get_command_info($command, $sid);
                if ($info !== false) {
                    $command = array('name' => $info['name'],
                        'action' => $info['action'],
                        'description' => $info['description']);
                    $commands[] = $command;
                }
            }
            $commands = array('commands' => $commands);
        } else {
            $commands = false;
        }
        return $commands;
    }

    public function run_command($data, $sid)
    {
        $sid = $sid == false ? $this->BP->get_local_system_id() : $sid;
        $command = $data['name'];
        $data = $this->BP->run_command($command, "", $sid );

        return array('data' => $data);
    }
}

?>
