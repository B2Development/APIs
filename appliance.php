<?php

$dir = '/api/includes/';

class Appliance
{
    private $BP;
     
    public function __construct($BP)
    {
	$this->BP = $BP;

    }
 
    public function get_clients($which, $filter, $data, $sid, $systems)
    {
	require_once('clients.php');
	$clients = new Clients($this->BP);
	return $clients->get($which, $filter, $data, $sid, $systems);
    }

    public function add_clients($which, $data, $sid)
    {
        require_once('clients.php');
        $clients = new Clients($this->BP);
        return $clients->add($which, $data, $sid);
    }

    public function delete_clients($which,$sid)
    {
        require_once('clients.php');
        $clients = new Clients($this->BP);
        return $clients->delete($which,$sid);
    }

    public function put_clients($which, $data, $sid)
    {
        require_once('clients.php');
        $clients = new Clients($this->BP);
        return $clients->put($which, $data, $sid);
    }

    public function get_users($which)
    {
		require_once('users.php');
		$users = new Users($this->BP);
		return $users->get($which);
    }

    public function delete_users($which)
    {
		require_once('users.php');
		$users = new Users($this->BP);
		return $users->delete($which);
    }

    public function update_users($which, $data)
    {
        require_once('users.php');
        $users = new Users($this->BP);
        return $users->put($which, $data);
    }

    public function add_users($data)
    {
        require_once('users.php');
        $users = new Users($this->BP);
        return $users->post($data);
    }

    public function get_backups($which, $filter, $sid)
    {
        require_once('backups.php');
        $backups = new Backups($this->BP, $sid);
        return $backups->get($which, $filter, $sid);
    }

    public function backup_now($which, $sid)
    {
        require_once('backups.php');
        $backups = new Backups($this->BP, $sid);
        return $backups->add($which);
    }

    public function delete_backups($which, $sid)
    {
        require_once('backups.php');
        $backups = new Backups($this->BP, $sid);
        return $backups->delete($which, $sid);
    }

    public function hold_backup($which, $filter, $sid)
    {
        require_once('backups.php');
        $backups = new Backups($this->BP, $sid);
        return $backups->hold($which, $filter, $sid);
    }

    public function search_backup($data, $sid) {
        require_once('backups.php');
        $backups = new Backups($this->BP, $sid);
        return $backups->search($data, $sid);
    }

    public function get_backups_for_multiple_restore($data, $appType, $sid) {
        require_once('backups.php');
        $backups = new Backups($this->BP, $sid);
        return $backups->getBackupsForMultipleRestore($data, $appType, $sid);
    }

    public function search_backups_on_target($data, $sid) {
        require_once('backups.php');
        $backups = new Backups($this->BP, $sid);
        return $backups->search_on_target($data, $sid);
    }

    public function list_backups($data, $sid) {
        require_once('backups.php');
        $backups = new Backups($this->BP, $sid);
        return $backups->post($data, $sid);
    }
    public function certify_backup($data, $sid) {
        require_once('backups.php');
        $backups = new Backups($this->BP, $sid);
        return $backups->certify($data, $sid);
    }
    public function add_storage($data, $sid)
    {
        require_once('storage.php');
        $storage = new Storage($this->BP, $sid);
        return $storage->add($data, $sid);
    }

    public function get_networks($which, $filter, $data, $sid) {

        require_once('networks.php');
        $networks = new Networks($this->BP, $sid);
        return $networks->get($which, $filter, $data, $sid);
    }

    public function update_networks($which, $filter, $inputarray, $sid){
        require_once('networks.php');
        $networks = new Networks($this->BP, $sid);
        return $networks->update($which, $filter, $inputarray, $sid);
    }

    public function save_network($which, $inputArray, $sid){
        require_once('networks.php');
        $networks = new Networks($this->BP, $sid);
        return $networks->post($which, $inputArray, $sid);
    }

    public function delete_networks($which, $filter, $sid){
        require_once('networks.php');
        $networks = new Networks($this->BP, $sid);
        return $networks->delete($which, $filter, $sid);
    }

    public function get_summary($which, $filter, $sid) {
        require_once('summary.php');
        $summary = new Summary($this->BP, $sid);
        return $summary->get($which, $filter);
    }

    public function put_summary($which, $data, $sid) {
        require_once('summary.php');
        $summary = new Summary($this->BP, $sid);
        return $summary->put($which, $data);
    }

    public function get_storage($which, $data, $sid, $systems) {
        require_once('storage.php');
        $storage = new Storage($this->BP, $sid);
        return $storage->get($which, $data, $sid, $systems);
    }

    public function delete_storage($which, $sid) {
        require_once('storage.php');
        $storage = new Storage($this->BP, $sid);
        return $storage->delete($which, $sid);
    }

    public function login($data){
		require_once('authenticate.php');
        $authenticate = new Authenticate($this->BP);
        return $authenticate->login($data);

		/*$ret = $this->BP->authenticate($data['username'], $data['password']);
        if ($ret !== false) {
                $token = $ret;
                $ret = array();
                $ret['auth_token'] = $token;
        }
        return $ret;*/
    }

    public function logout($data){
        require_once('authenticate.php');
        $authenticate = new Authenticate($this->BP);
        return $authenticate->logout($data);
    }

    public function get_virtual_clients($which, $data, $sid, $systems)
    {
        require_once('virtual-clients.php');
        $virtual_clients = new VirtualClients($this->BP);
        return $virtual_clients->get($which, $data, $sid, $systems);
    }

    public function save_virtual_client($data, $sid)
    {
        require_once('virtual-clients.php');
        $virtual_clients = new VirtualClients($this->BP);
        return $virtual_clients->post($data, $sid);
    }

    public function modify_virtual_client($which, $data, $sid)
    {
        require_once('virtual-clients.php');
        $virtual_clients = new VirtualClients($this->BP);
        return $virtual_clients->put($which, $data, $sid);
    }

    public function delete_virtual_client($which, $data, $sid)
    {
        require_once('virtual-clients.php');
        $virtual_clients = new VirtualClients($this->BP);
        return $virtual_clients->delete($which, $data, $sid);
    }

    public function get_systems($which, $filter, $data, $sid)
    {
        require_once("systems.php");
        $systems = new Systems($this->BP, $sid);
        return $systems->get($which, $filter, $data, $sid);
    }

    public function update_system($action, $which, $data, $sid)
    {
        require_once("systems.php");
        $systems = new Systems($this->BP, $sid);
        return $systems->update($action, $which, $data);
    }
    public function add_system($data, $sid)
    {
        require_once("systems.php");
        $systems = new Systems($this->BP, $sid);
        return $systems->add($data);
    }

    public function make_local_system_into_a_target($data, $sid)
    {
        require_once("systems.php");
        $systems = new Systems($this->BP, $sid);
        return $systems->make_target($data);
    }

    public function online_offline($action, $which, $data, $sid){
        require_once('storage.php');
        $storage = new Storage($this->BP, $sid);
        return $storage->online_offline($action, $which, $data, $sid);
    }
    public function update_storage($which, $data, $sid)
    {
        require_once('storage.php');
        $storage = new Storage($this->BP, $sid);
        return $storage->put($which, $data, $sid);
    }
    public function allocate_ir_space($data, $sid)
    {
        require_once('storage.php');
        $storage = new Storage($this->BP, $sid);
        return $storage->allocate_ir($data, $sid);
    }
    public function allocate_d2d_space($data, $sid)
    {
        require_once('storage.php');
        $storage = new Storage($this->BP, $sid);
        return $storage->allocate_d2d($data, $sid);
    }
    public function allocate_vc_space($data, $sid)
    {
        require_once('storage.php');
        $storage = new Storage($this->BP, $sid);
        return $storage->allocate_vc($data, $sid);
    }
    public function delete_systems($which){
        require_once("systems.php");
        $systems = new Systems($this->BP, $which);
        return $systems->delete_systems($which);
    }
    public function get_restore($which, $data, $sid, $systems)
    {
        require_once('restore.php');
        $restores = new Restores($this->BP);
        return $restores->get($which, $data, $sid, $systems);
    }
    public function post_restore($which, $data, $sid)
    {
        require_once('restore.php');
        $restores = new Restores($this->BP);
        return $restores->post($which, $data, $sid);
    }
    public function get_commands($sid)
    {
        require_once('commands.php');
        $commands = new Commands($this->BP, $sid);
        return $commands->get_commands($sid);
    }
    public function post_commands($data, $sid)
    {
        require_once('commands.php');
        $commands = new Commands($this->BP, $sid);
        return $commands->run_command($data, $sid);
    }

    public function post_feedback($data, $sid)
    {
        require_once('feedback.php');
        $feedback = new Feedback($this->BP, $sid);
        return $feedback->send_feedback($data, $sid);
    }

    public function get_replicas($which, $data, $sid, $systems)
    {
        require_once('replicas.php');
        $replicas = new Replicas($this->BP);
        return $replicas->get($which, $data, $sid, $systems);
    }

    public function save_replicas($data, $sid)
    {
        require_once('replicas.php');
        $replicas = new Replicas($this->BP);
        return $replicas->post($data, $sid);
    }

    public function modify_replicas($which, $data, $sid)
    {
        require_once('replicas.php');
        $replicas = new Replicas($this->BP);
        return $replicas->put($which, $data, $sid);
    }

    public function delete_replicas($which, $data, $sid)
    {
        require_once('replicas.php');
        $replicas = new Replicas($this->BP);
        return $replicas->delete($which, $data, $sid);
    }

}
?>
