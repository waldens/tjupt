<?php
/**
 * Created by PhpStorm.
 * User: tongyifan
 * Date: 18-7-22
 * Time: 下午9:18
 */
require "include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
global $CURUSER;

$action = isset ($_POST ['action']) ? htmlspecialchars($_POST ['action']) : (isset ($_GET ['action']) ? htmlspecialchars($_GET ['action']) : '');

if ($action == '') {
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
    check_permission();
    $res = sql_query("SELECT id, tid, status, seeding_time FROM transmit WHERE status != 'complete'") or sqlerr();
    $torrents = array();
    while ($row = mysql_fetch_assoc($res)) {
        $torrent = new TorrentAction();
        if ($row['status'] == "pending") {
            $torrent->setAction("start");
        } else if ($row['status'] == "seeding") {
            if (strtotime('+7 day', strtotime($row['seeding_time'])) < strtotime("now")) {
                $torrent->setAction("stop");
            } else {
                continue;
            }
        } else {
            continue;
        }
        $torrent->setId($row['id']);
        $torrent->setTid($row['tid']);
        $torrents[] = $torrent;
    }
    echo json_encode($torrents);
} else if ($action == 'start') {
    check_permission();
    // url just like https://tjupt.org/transmit.php?action=start&id=1,2,3,4,5,6,7,8
    $ids = "(" . $_GET['id'] . ")";
    $torrents = array();
    sql_query("UPDATE transmit SET status = 'downloading' WHERE id IN " . $ids);
    echo 'ok';
} else if ($action == 'seeding') {
    check_permission();
    $ids = "(" . $_GET['id'] . ")";
    $torrents = array();
    sql_query("UPDATE transmit SET status = 'seeding', seeding_time = NOW() WHERE id IN " . $ids) or sqlerr();
    echo 'ok';
} else if ($action == 'stop') {
    check_permission();
    $ids = "(" . $_GET['id'] . ")";
    $torrents = array();
    sql_query("UPDATE transmit SET status = 'complete' WHERE id IN " . $ids) or sqlerr();
    echo 'ok';
} else if ($action == 'request') {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $id = $_POST['id'];
        if (!is_numeric($id)) {
            stderr($lang_transmit['std_error'], $lang_transmit['std_invalid_id'] . $id);
        }
        $torrent = mysql_fetch_assoc(sql_query("SELECT name, size FROM torrents WHERE id = $id")) or sqlerr();
        $cost = calculate_cost($torrent['size']);
        if ($CURUSER['seedbonus'] < $cost) {
            stderr($lang_transmit['std_error'], $lang_transmit['std_not_enough_bonus']);
        }
        sql_query("INSERT INTO transmit (uid, tid) VALUES (" . $CURUSER['id'] . ", $id)");
        sql_query("UPDATE users SET seedbonus = seedbonus - $cost WHERE id = " . $CURUSER['id']);
        write_log($CURUSER['username'] . $lang_transmit['text_use'] . $cost . $lang_transmit['text_bonus_to_request_transmit'] . "($id)" . $torrent['name']);
        stderr($lang_transmit['std_success'], $lang_transmit['text_success_note']);
    } else {
        $id = $_GET['id'];
        if (!is_numeric($id)) {
            stderr($lang_transmit['std_error'], $lang_transmit['std_invalid_id'] . $id);
        }
        $torrent = mysql_fetch_assoc(sql_query("SELECT name, size FROM torrents WHERE id = $id")) or sqlerr();
        $transmitted = mysql_fetch_assoc(sql_query("SELECT * FROM transmit WHERE tid = $id AND status != 'complete'")) or sqlerr();
        if ($transmitted) {
            stderr($lang_transmit['std_error'], $lang_transmit['std_already_transmit']);
        }
        $cost = calculate_cost($torrent['size']);
        if ($CURUSER['seedbonus'] < $cost) {
            stderr($lang_transmit['std_error'], $lang_transmit['std_not_enough_bonus']);
        }
        $confirm = $lang_transmit['text_you_are_using'] . $cost . $lang_transmit['text_bonus_to_request_transmit'] . "<a href='details.php?id=$id&hit=1' title='" . $torrent['name'] . "'>" . $torrent['name'] . "</a>";
        stderr($lang_transmit['std_be_sure'], "<form action=\"?action=request\" method=\"post\"><input type=\"hidden\" name=\"id\" value=" . $id . " />" . $confirm . "<br /><input type=submit value=\"确定\"> &nbsp;<input type=button value=\"返回\" onclick=\"location.href='javascript:history.go(-1)'\" /></form>", 0);
    }
}

function check_permission()
{
    global $CURUSER;
    if ($CURUSER['id'] != 10 && get_user_class() < UC_SYSOP) {
        permissiondenied();
    }
}

function calculate_cost($size)
{
    return $cost = round($size / 2000000, 1);
}

class TorrentAction
{
    public $id;
    public $tid;
    public $action;
    
    public function setId($id)
    {
        $this->id = $id;
    }
    
    public function setAction($action)
    {
        $this->action = $action;
    }
    
    public function setTid($tid)
    {
        $this->tid = $tid;
    }
    
    public function getTid()
    {
        return $this->tid;
    }
    
    public function getId()
    {
        return $this->id;
    }
    
    public function getAction()
    {
        return $this->action;
    }
}