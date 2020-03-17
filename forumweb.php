<?php

define("LOG_DIRECTORY", "/usr/bp/logs.dir");
define("LOG_LEVEL", "3");

require_once 'logger.php';
$Log = new Logger(LOG_LEVEL, LOG_DIRECTORY);


class ForumWeb
{
    /*
	Pulls forum posts from the Unitrends webce server. Valid filters are:


	?count={n}  ---> return at the most n posts, sorted by Created Date descending
	?type=likes  ---> return posts ordered by most likes
	?type=date  ---> return posts ordered by date (default)
	?count={n}&type=likes  ---> return at the most n posts, sorted by most likes
     */
    public function get_forum_posts($which, $data)
    {
        global $Log;
        $curl = curl_init();
	    $forum = isset($data['forum']) ? $data['forum'] : 'ce';
        $base_url = "https://webce.unitrends.com/api/" . $forum . "/posts/";
        // determine filter to use, see above
        $sep = "?";
        $count = "";
        $type = "";
        if (isset($data['count'])) {
            $count = $sep . "count=" . $data['count'];
            $sep = "&";
        }
        if (isset($data['type'])) {
            $type = $sep . "type=" . $data['type'];
            $sep = "&";
        }
        $url = $base_url . $count . $type;
        $Log->writeVariable("forumweb: getting forum posts, url is $url");
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        curl_setopt($curl, CURLOPT_DNS_CACHE_TIMEOUT, 2);
        $result = curl_exec($curl);
        //$Log->writeVariable("forumweb: after curl");
        //$Log->writeVariable($result);
        if ($result == false) {
            $Log->writeVariable("forumweb:the curl request to get posts failed ");
            $Log->writeVariable(curl_error($curl));
        } else {
            // return as a string
            //$result = json_decode($result, true);
            //$result = array('posts' => $result);
        }
        curl_close($curl);

        return $result;
    }

    function create_forum_account($options_string)
    {
        global $Log;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://webce.unitrends.com/api/ce/user");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        curl_setopt($curl, CURLOPT_DNS_CACHE_TIMEOUT, 2);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $options_string);
        $Log->writeVariable("forumweb: calling ce server, with options:");
        $Log->writeVariable($options_string);
        $result = curl_exec($curl);
        curl_close($curl);
        if ($result == false) {
            $Log->writeVariable("forumweb:the curl request to create account failed ");
            $Log->writeVariable(curl_error($curl));
        } else {
            $Log->writeVariable('curl request successful');
            $Log->writeVariable($result);
            //$result = json_decode($result, true);
            //if (!$result['Success']) {
            //$result = false;
            //}
        }
        return $result;
    }

    function authenticate_forum_account($options_string)
    {
        global $Log;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://webce.unitrends.com/api/ce/user/authenticate/");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        curl_setopt($curl, CURLOPT_DNS_CACHE_TIMEOUT, 2);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $options_string);
        $Log->writeVariable("forumweb: auth: calling ce server, with options:");
        $Log->writeVariable($options_string);
        $result = curl_exec($curl);
        curl_close($curl);
        if ($result == false) {
            $Log->writeVariable("forumweb:the curl request to authenticate account failed ");
            $Log->writeVariable(curl_error($curl));
        } else {
            $Log->writeVariable('curl request successful');
            $Log->writeVariable($result);
            //$result = json_decode($result, true);
            //if (!$result['Success']) {
            //$result = false;
            //}
        }
        return $result;
    }
}

$WebCE = new ForumWeb();
$options = getopt("m::c::t::s::f::");
$mode = isset($options['m']) ? $options['m'] : "get";
switch ($mode) {
    case 'get':
        $count = isset($options['c']) ? $options['c'] : 5;
        $type = isset($options['t']) ? $options['t'] : 'date';
        $forum = isset($options['f']) ? $options['f'] : 'ce';
        $result = $WebCE->get_forum_posts(null,
                    array('count' => $count,
                        'type' => $type,
                        'forum' => $forum));
        break;

    case 'auth':
        $options_string = isset($options['s']) ? $options['s'] : "";
        $result = $WebCE->authenticate_forum_account($options_string);
        break;

    case 'register':
        $options_string = isset($options['s']) ? $options['s'] : "";
        $result = $WebCE->create_forum_account($options_string);
        break;
}
printf("%s", $result);

?>
