<?php
ob_start(); // Do not delete this line
require_once("include/bittorrent.php");
require_once("include/tjuip_helper.php");
dbconn();
require_once(get_langfile_path());
global $showextinfo, $CURUSER, $torrentmanage_class, $seebanned_class, $viewanonymous_class, $smalldescription_main,
       $submanage_class, $enablenfo_main, $uploadsub_class, $viewnfo_class, $Advertisement, $delownsub_class,
       $updateextinfo_class, $torrentstructure_class, $lang_details, $askreseed_class, $max_dead_torrent_time, $torrentnameprefix;

if ($showextinfo ['imdb'] == 'yes'){
    require_once("douban/douban.class.php");
}

loggedinorreturn();

$id = 0 + $_GET ["id"];

int_check($id);
if (!isset ($id) || !$id)
    die ();

if (get_user_class() < UC_MODERATOR) {
    $addicond = " AND torrents.pulling_out = '0'";
}

$res = sql_query("SELECT torrents.cache_stamp, torrents.sp_state, torrents.sp_time, torrents.promotion_time_type, torrents.promotion_until, torrents.pos_state, torrents.picktype, torrents.category, torrents.url, torrents.small_descr, torrents.seeders, torrents.banned, torrents.leechers, torrents.info_hash, torrents.filename, nfo, LENGTH(torrents.nfo) AS nfosz, torrents.last_action, torrents.last_seed, torrents.needkeepseed, torrents.seedkeeper, torrents.name, torrents.owner, torrents.save_as, torrents.descr, torrents.visible, torrents.size, torrents.added, torrents.views, torrents.hits, torrents.times_completed, torrents.id, torrents.type, torrents.numfiles, torrents.anonymous, torrents.pulling_out, torrents.imdb_rating, categories.name AS cat_name, sources.name AS source_name, media.name AS medium_name, codecs.name AS codec_name, standards.name AS standard_name, processings.name AS processing_name, teams.name AS team_name, audiocodecs.name AS audiocodec_name, torrents.pulling_out AS pulling_out, torrents.pos_state_until as pos_state_until FROM torrents LEFT JOIN categories ON torrents.category = categories.id LEFT JOIN sources ON torrents.source = sources.id LEFT JOIN media ON torrents.medium = media.id LEFT JOIN codecs ON torrents.codec = codecs.id LEFT JOIN standards ON torrents.standard = standards.id LEFT JOIN processings ON torrents.processing = processings.id LEFT JOIN teams ON torrents.team = teams.id LEFT JOIN audiocodecs ON torrents.audiocodec = audiocodecs.id WHERE torrents.id = $id $addicond LIMIT 1") or sqlerr();
$row = mysql_fetch_array($res);

if (get_user_class() >= $torrentmanage_class || $CURUSER ["id"] == $row ["owner"])
    $owned = 1;
else
    $owned = 0;

if (!$row)
    stderr($lang_details ['std_error'], $lang_details ['std_no_torrent_id']);
elseif ($row ['banned'] == 'yes' && get_user_class() < $seebanned_class && $row ['owner'] != $CURUSER ["id"])
    permissiondenied();
else {
    if ($_GET ["hit"]) {
        sql_query("UPDATE torrents SET views = views + 1 WHERE id = $id");
    }

    if (!isset ($_GET ["cmtpage"])) {
        stdhead($lang_details ['head_details_for_torrent'] . "\"" . $row ["name"] . "\"");

        if ($_GET ["uploaded"]) {
            print ("<h1 align=\"center\">" . $lang_details ['text_successfully_uploaded'] . "</h1>");
            print ("<p>" . $lang_details ['text_redownload_torrent_note'] . "</p>");
            header("refresh: 1; url=download.php?id=$id");
            // header("refresh: 1; url=getimdb.php?id=$id&type=1");
        } elseif ($_GET ["edited"]) {
            print ("<h1 align=\"center\">" . $lang_details ['text_successfully_edited'] . "</h1>");
            if (isset ($_GET ["returnto"]))
                print ("<p><b>" . $lang_details ['text_go_back'] . "<a href=\"" . htmlspecialchars($_GET ["returnto"]) . "\">" . $lang_details ['text_whence_you_came'] . "</a></b></p>");
        }
        $sp_torrent = get_torrent_promotion_append($row ['sp_state'], 'word');

        $s = htmlspecialchars($row ["name"]) . ($sp_torrent ? "&nbsp;&nbsp;&nbsp;" . $sp_torrent : "");
        if ($row ["pulling_out"] == 1) {
            $s = "<font color=red>@回收站</font>-" . $s;
        }
        print ("<h1 align=\"center\" id=\"top\" class=\"pagetitle\" style=\"width: 920px\">" . $s . "</h1>\n");
        print ("<table width=\"100%\" cellspacing=\"0\" cellpadding=\"5\">\n");

        $url = "edit.php?id=" . $row ["id"];
        if (isset ($_GET ["returnto"])) {
            $url .= "&returnto=" . rawurlencode($_GET ["returnto"]);
        }
        $editlink = "a title=\"" . $lang_details ['title_edit_torrent'] . "\" href=\"$url\"";

        // ------------- start upped by block ------------------//

        if ($row ['anonymous'] == 'yes') {
            if (get_user_class() < $viewanonymous_class)
                $uprow = "<i>" . $lang_details ['text_anonymous'] . "</i>";
            else
                $uprow = "<i>" . $lang_details ['text_anonymous'] . "</i> (" . get_username($row ['owner'], false, true, true, false, false, true) . ")";
        } else {
            $uprow = (isset ($row ['owner']) ? get_username($row ['owner'], false, true, true, false, false, true) : "<i>" . $lang_details ['text_unknown'] . "</i>");
        }

        if ($CURUSER ["id"] == $row ["owner"])
            $CURUSER ["downloadpos"] = "yes";
        if ($CURUSER ["downloadpos"] != "no") {
            print ("<tr><td class=\"rowhead\" width=\"13%\">" . $lang_details ['row_download'] . "</td><td class=\"rowfollow\" width=\"87%\" align=\"left\">");
            if ($CURUSER ['timetype'] != 'timealive')
                $uploadtime = $lang_details ['text_at'] . $row ['added'];
            else
                $uploadtime = $lang_details ['text_blank'] . gettime($row ['added'], true, false);
            print ("<a class=\"index\" href=\"download.php?id=$id\">" . htmlspecialchars($torrentnameprefix . "." . $row ["save_as"]) . ".torrent</a>&nbsp;&nbsp;<a id=\"bookmark0\" href=\"javascript: bookmark(" . $row ['id'] . ",0);\">" . get_torrent_bookmark_state($CURUSER ['id'], $row ['id'], false) . "</a>&nbsp;&nbsp;&nbsp;" . $lang_details ['row_upped_by'] . "&nbsp;" . $uprow . $uploadtime);
            print ("</td></tr>");

            // download_direct_link
            global $BASEURL;
            $download_direct_link = get_protocol_prefix() . $BASEURL . "/download.php?id=$id&passkey=" . $CURUSER['passkey'];
            $download_link_button = "<a href='' onclick='return false' id='direct_link' data-clipboard-text=\"$download_direct_link\">{$lang_details['text_press_to_copy']}</a>";
            tr($lang_details['row_download_direct_link'], $download_link_button . "&nbsp;&nbsp;&nbsp;&nbsp;" . $lang_details['text_direct_link_warning'], 1);

        } else
            tr($lang_details ['row_download'], $lang_details ['text_downloading_not_allowed']);
        if ($smalldescription_main == 'yes')
            tr($lang_details ['row_small_description'], htmlspecialchars(trim($row ["small_descr"])), true);

        $size_info = "<b>" . $lang_details ['text_size'] . "</b>" . mksize($row ["size"]);
        $type_info = "&nbsp;&nbsp;&nbsp;<b>" . $lang_details ['row_type'] . ":</b>&nbsp;" . $row ["cat_name"];
        if (isset ($row ["source_name"]))
            $source_info = "&nbsp;&nbsp;&nbsp;<b>" . $lang_details ['text_source'] . "&nbsp;</b>" . $row ['source_name'];
        if (isset ($row ["medium_name"]))
            $medium_info = "&nbsp;&nbsp;&nbsp;<b>" . $lang_details ['text_medium'] . "&nbsp;</b>" . $row ['medium_name'];
        if (isset ($row ["codec_name"]))
            $codec_info = "&nbsp;&nbsp;&nbsp;<b>" . $lang_details ['text_codec'] . "&nbsp;</b>" . $row ['codec_name'];
        if (isset ($row ["standard_name"]))
            $standard_info = "&nbsp;&nbsp;&nbsp;<b>" . $lang_details ['text_stardard'] . "&nbsp;</b>" . $row ['standard_name'];
        if (isset ($row ["processing_name"]))
            $processing_info = "&nbsp;&nbsp;&nbsp;<b>" . $lang_details ['text_processing'] . "&nbsp;</b>" . $row ['processing_name'];
        if (isset ($row ["team_name"]))
            $team_info = "&nbsp;&nbsp;&nbsp;<b>" . $lang_details ['text_team'] . "&nbsp;</b>" . $row ['team_name'];
        if (isset ($row ["audiocodec_name"]))
            $audiocodec_info = "&nbsp;&nbsp;&nbsp;<b>" . $lang_details ['text_audio_codec'] . "&nbsp;</b>" . $row ['audiocodec_name'];

        tr($lang_details ['row_basic_info'], $size_info . $type_info . $source_info . $medium_info . $codec_info . $audiocodec_info . $standard_info . $processing_info . $team_info, 1);

        /**
         * ********************************************显示种子详细信息*************************************************
         */
        $ret = sql_query("SELECT * FROM torrentsinfo WHERE torid = " . $id . " LIMIT 1") or sqlerr();
        $row_ = mysql_fetch_array($ret);
        $catid = $row_ ['category'];

        if ($catid != "407" && $catid != "410") {
            if ($row_ ["cname"] != "")
                $cname = $lang_details ['cname'] . $row_ ['cname'] . "<br /><br />";
            if ($row_ ["ename"] != "")
                $ename = $lang_details ['ename'] . $row_ ['ename'] . "<br /><br />";
            if ($row_ ["issuedate"] != "")
                $issuedate = $lang_details ['issuedate'] . $row_ ['issuedate'] . "<br /><br />";
            if ($row_ ["subsinfo"] != 0) {
                $result = sql_query("SELECT * FROM subsinfo WHERE id = " . $row_ ["subsinfo"]);
                $result_ = mysql_fetch_array($result);
                $subsinfo = $lang_details ['subsinfo'] . $result_ ['name'] . "<br /><br />";
                // if (strpos ( $row ['name'], "[暂无字幕]" ) !== false && $result_
                // [name] !== "暂无字幕") {
                // $new_name = preg_replace ( '/\]\[[^\[]+字幕\]$/', ']['.$result_
                // [name].']', $row ['name'] );
                // sql_query ( 'UPDATE torrents SET name = ' . sqlesc (
                // $new_name ) . ' WHERE id = ' . $id ) or sqlerr ();
                // }
            }
        }

        if ($catid == 401) {
            // if($row_["specificcat"]!="")$specificcat.
            // $specificcat = $lang_details['catmovie'].$row_[specificcat]."<br
            // /><br />";
            if ($row_ ["district"] != "")
                $district = $lang_details ['districtmovie'] . $row_ ['district'] . "<br /><br />";
            if ($row_ ["format"] != "")
                $format = $lang_details ['formatmovie'] . $row_ ['format'] . "<br /><br />";
            if ($row_ ["language"] != "")
                $language = $lang_details ['langmovie'] . $row_ ['language'] . "<br /><br />";
            // if($row_["imdbnum"]!="").$imdbnum
            // $imdbnum = $lang_details['imdbnum'].$row_['imdbnum']."<br /><br
            // />";
            tr($lang_details ['detailsinfo'], $cname . $ename . $issuedate . $subsinfo . $district . $format . $language, 1);
        }
        if ($catid == 402) {
            if ($row_ ["tvalias"] != "")
                $tvalias = $lang_details ['tvalias'] . $row_ ['tvalias'] . "<br /><br />";
            if ($row_ ["format"] != "")
                $format = $lang_details ['formattvseries'] . $row_ ['format'] . "<br /><br />";
            if ($row_ ["specificcat"] != "")
                $specificcat = $lang_details ['cattvseries'] . $row_ ['specificcat'] . "<br /><br />";
            if ($row_ ["language"] != "")
                $language = $lang_details ['langtvseries'] . $row_ ['language'] . "<br /><br />";
            if ($row_ ["tvseasoninfo"] != "")
                $tvseasoninfo = $lang_details ['tvseasoninfo'] . $row_ ['tvseasoninfo'] . "<br /><br />";
            tr($lang_details ['detailsinfo'], $cname . $ename . $issuedate . $specificcat . $format . $language . $subsinfo . $tvalias . $tvseasoninfo, 1);
        }
        if ($catid == 403) {
            if ($row_ ["district"] != "")
                $district = $lang_details ['districttvshows'] . $row_ ['district'] . "<br /><br />";
            if ($row_ ["tvshowscontent"] != "")
                $tvshowscontent = $lang_details ['tvshowscontent'] . $row_ ['tvshowscontent'] . "<br /><br />";
            if ($row_ ["tvshowsguest"] != "")
                $tvshowsguest = $lang_details ['tvshowsguest'] . $row_ ['tvshowsguest'] . "<br /><br />";
            if ($row_ ["language"] != "")
                $language = $lang_details ['langtvshows'] . $row_ ['language'] . "<br /><br />";
            if ($row_ ["tvshowsremarks"] != "")
                $tvshowsremarks = $lang_details ['tvshowsremarks'] . $row_ ['tvshowsremarks'] . "<br /><br />";
            if ($row_ ["format"] != "")
                $format = $lang_details ['formattvshows'] . $row_ ['format'] . "<br /><br />";
            tr($lang_details ['detailsinfo'], $cname . $ename . $issuedate . $subsinfo . $district . $tvshowscontent . $tvshowsguest . $language . $format . $tvshowsremarks, 1);
        }
        if ($catid == 404) {
            if ($row_ ["specificcat"] != "")
                $specificcat = $lang_details ['catdocum'] . $row_ ['specificcat'] . "<br /><br />";
            if ($row_ ["format"] != "")
                $format = $lang_details ['formatdocum'] . $row_ ['format'] . "<br /><br />";
            if ($row_ ["version"] != "")
                $version = $lang_details ['version'] . $row_ ['version'] . "<br /><br />";
            tr($lang_details ['detailsinfo'], $cname . $ename . $issuedate . $specificcat . $format . $version, 1);
        }
        if ($catid == 405) {
            if ($row_ ["animenum"] != "")
                $animenum = $lang_details ['animenum'] . $row_ ['animenum'] . "<br /><br />";
            if ($row_ ["substeam"] != "")
                $substeam = $lang_details ['substeam'] . $row_ ['substeam'] . "<br /><br />";
            if ($row_ ["resolution"] != "")
                $resolution = $lang_details ['resolutionanime'] . $row_ ['resolution'] . "<br /><br />";
            if ($row_ ["specificcat"] != "")
                $specificcat = $lang_details ['catanime'] . $row_ ['specificcat'] . "<br /><br />";
            if ($row_ ["district"] != "")
                $district = $lang_details ['districtanime'] . $row_ ['district'] . "<br /><br />";
            if ($row_ ["format"] != "")
                $format = $lang_details ['formatanime'] . $row_ ['format'] . "<br /><br />";
            tr($lang_details ['detailsinfo'], $cname . $ename . $issuedate . $specificcat . $format . $animenum . $substeam . $resolution . $district, 1);
        }
        if ($catid == 406) {
            if ($row_ ["specificcat"] != "")
                $specificcat = $lang_details ['cathq'] . $row_ ['specificcat'] . "<br /><br />";
            if ($row_ ["hqname"] != "")
                $hqname = $lang_details ['hqname'] . $row_ ['hqname'] . "<br /><br />";
            if ($row_ ["artist"] != "")
                $artist = $lang_details ['artist'] . $row_ ['artist'] . "<br /><br />";
            if ($row_ ["hqtone"] != "")
                $hqtone = $lang_details ['hqtone'] . $row_ ['hqtone'] . "<br /><br />";
            if ($row_ ["format"] != "")
                $format = $lang_details ['formathq'] . $row_ ['format'] . "<br /><br />";
            if ($row_ ["language"] != "")
                $language = $lang_details ['langhq'] . $row_ ['language'] . "<br /><br />";
            tr($lang_details ['detailsinfo'], $hqname . $issuedate . $artist . $specificcat . $format . $language . $hqtone, 1);
        }
        if ($catid == 407) {
            if ($row_ ["cname"] != "")
                $cname = $lang_details ['matchcat'] . $row_ ['cname'] . "<br /><br />";
            if ($row_ ["ename"] != "")
                $ename = $lang_details ['versus'] . $row_ ['ename'] . "<br /><br />";
            if ($row_ ["issuedate"] != "")
                $issuedate = $lang_details ['matchdate'] . $row_ ['issuedate'] . "<br /><br />";
            if ($row_ ["specificcat"] != "")
                $specificcat = $lang_details ['catsports'] . $row_ ['specificcat'] . "<br /><br />";
            if ($row_ ["language"] != "")
                $language = $lang_details ['langsports'] . $row_ ['language'] . "<br /><br />";
            if ($row_ ["format"] != "")
                $format = $lang_details ['formatsports'] . $row_ ['format'] . "<br /><br />";
            if ($row_ ["resolution"] != "")
                $resolution = $lang_details ['resolutionsports'] . $row_ ['resolution'] . "<br /><br />";
            tr($lang_details ['detailsinfo'], $cname . $issuedate . $specificcat . $format . $resolution . $language, 1);
        }
        if ($catid == 408) {
            if ($row_ ["specificcat"] != "")
                $specificcat = $lang_details ['catsoftware'] . $row_ ['specificcat'] . "<br /><br />";
            if ($row_ ["language"] != "")
                $language = $lang_details ['langsoftware'] . $row_ ['language'] . "<br /><br />";
            if ($row_ ["format"] != "")
                $format = $lang_details ['formatsoftware'] . $row_ ['format'] . "<br /><br />";
            if ($row_ ["version"] != "")
                $version = $lang_details ['version'] . $row_ ['version'] . "<br /><br />";
            tr($lang_details ['detailsinfo'], $cname . $ename . $issuedate . $specificcat . $format . $language . $version, 1);
        }
        if ($catid == 409) {
            if ($row_ ["specificcat"] != "")
                $specificcat = $lang_details ['catgame'] . $row_ ['specificcat'] . "<br /><br />";
            if ($row_ ["language"] != "")
                $language = $lang_details ['langgame'] . $row_ ['language'] . "<br /><br />";
            if ($row_ ["format"] != "")
                $format = $lang_details ['formatgame'] . $row_ ['format'] . "<br /><br />";
            if ($row_ ["platform"] != "")
                $platform = $lang_details ['platform'] . $row_ ['platform'] . "<br /><br />";
            if ($row_ ["company"] != "")
                $company = $lang_details ['company'] . $row_ ['company'] . "<br /><br />";
            if ($row_ ["tvshowsremarks"] != "")
                $tvshowsremarks = $lang_details ['tvshowsremarks'] . $row_ ['tvshowsremarks'] . "<br /><br />";
            tr($lang_details ['detailsinfo'], $cname . $ename . $specificcat . $format . $platform . $language . $company . $tvshowsremarks, 1);
        }
        if ($catid == 410) {
            if ($row_ ["specificcat"] != "")
                $specificcat = $lang_details ['catothers'] . $row_ ["specificcat"] . "<br /><br />";
            if ($row_ ["cname"] != "")
                $cname = $lang_details ['nameothers'] . $row_ ["cname"] . "<br /><br />";
            if ($row_ ["format"] != "")
                $format = $lang_details ['formatothers'] . $row_ ["format"] . "<br /><br />";
            if ($row_ ["tvshowremarks"] != "")
                $tvshowremarks = $lang_details ['infoothers'] . $row_ ["tvshowremarks"] . "<br /><br />";
            tr($lang_details ['detailsinfo'], $specificcat . $cname . $format . $tvshowremarks, 1);
        }
        if ($catid == 411) {
            if ($row_ ["format"] != "")
                $format = $lang_details ['formatnewsreel'] . $row_ ['format'] . "<br /><br />";
            if ($row_ ["specificcat"] != "")
                $specificcat = $lang_details ['catnewsreel'] . $row_ ['specificcat'] . "<br /><br />";
            if ($row_ ["language"] != "")
                $language = $lang_details ['langnewsreel'] . $row_ ['language'] . "<br /><br />";
            tr($lang_details ['detailsinfo'], $cname . $ename . $issuedate . $specificcat . $format . $language . $subsinfo, 1);
        }
        if ($catid == 4013) {
            if ($row_ ["cname"] != "") {
                $cname_ = $lang_details ['othertitle'] . $row_ ["cname"] . "<br /><br />";
                tr($lang_details ['detailsinfo'], $cname_, 1);
            }
        }
        if ($catid == 412) {
            if ($row_ ["district"] != "")
                $district = $lang_details ['districtmovie'] . $row_ ['district'] . "<br /><br />";
            if ($row_ ["language"] != "")
                $language = "<b>语言:</b>" . $row_ ['language'] . "<br /><br />";
            tr($lang_details ['detailsinfo'], $cname . $ename . $language . $subsinfo . $district, 1);
        }
        /**
         * *************************************************************************************************************
         */

        if ($CURUSER ["downloadpos"] != "no")
            /*双栈
                        $download = "<a title=\"".$lang_details['title_download_torrent']."\" href=\"download.php?id=".$id."\"><img class=\"dt_download\" src=\"pic/trans.gif\" alt=\"download\" />&nbsp;<b><font class=\"small\">".$lang_details['text_download_torrent']."</font></b></a>&nbsp;|&nbsp;";
            */

            $download = "<a title=\"" . $lang_details ['title_download_torrent'] . "\" href=\"download.php?id=" . $id . "\"><img class=\"dt_download\" src=\"pic/trans.gif\" alt=\"download\" />&nbsp;<b><font class=\"small\">" . $lang_details ['text_download_torrent'] . "</font></b></a>&nbsp;|&nbsp;";
        else
            $download = "";

        if ($row ['pulling_out']) {
            tr($lang_details ['row_action'], "<a href=\"takerestore.php?id=$id\"><b>恢复误删</b></a>", 1);
        } else {
            $share_descr = format_comment($row ["descr"]);
            $share_pic_first = get_first_image($share_descr);
            $share_pic_all = get_all_image($share_descr);
            $share_pic_tqq = get_all_image2($share_descr);

            $share_button_weibo = '<a href="#" onclick="tjuShare(\'weibo\', \'' . ($share_pic_all) . '\');"><img src="styles/btn_weibo_trans.gif" class="weibo" alt=""><font class="small"><b>新浪微博</b></font></a>';
            $share_button_renren = '<a href="#" onclick="tjuShare(\'renren\', \'' . ($share_pic_first) . '\');"><img src="styles/btn_weibo_trans.gif" class="renren" alt=""><font class="small"><b>人人网</b></font></a>';

            tr($lang_details ['row_action'], $download . ($owned == 1 ? "<$editlink><img class=\"dt_edit\" src=\"pic/trans.gif\" alt=\"edit\" />&nbsp;<b><font class=\"small\">" . $lang_details ['text_edit_torrent'] . "</font></b></a>&nbsp;|&nbsp;" : "") . (get_user_class() >= $askreseed_class && $row ['seeders'] == 0 && strtotime($row ["last_seed"]) <= time() - $max_dead_torrent_time ? "<a title=\"" . $lang_details ['title_ask_for_reseed'] . "\" href=\"takereseed.php?reseedid=$id\"><img class=\"dt_reseed\" src=\"pic/trans.gif\" alt=\"reseed\">&nbsp;<b><font class=\"small\">" . $lang_details ['text_ask_for_reseed'] . "</font></b></a>&nbsp;|&nbsp;" : "") . "<a title=\"" . $lang_details ['title_report_torrent'] . "\" href=\"report.php?torrent=$id\"><img class=\"dt_report\" src=\"pic/trans.gif\" alt=\"report\" />&nbsp;<b><font class=\"small\">" . $lang_details ['text_report_torrent'] . "</font></b></a>" . (user_can_upload() && $CURUSER ["uploadpos"] == 'yes' ? "&nbsp;|&nbsp;<a href=\"upsimilartorrent.php?id=$id\"><b>引用该种发布</b></a>" : ""), 1);
        }

        // 保种功能

        if (get_user_class() >= 13) {
            if ($row ["needkeepseed"] == "no") {
                print ("<form method=\"post\" id=\"setkeepseed\" name=\"setkeepseed\" action=\"keepseed.php\" enctype=\"multipart/form-data\" ><input type=\"hidden\" name=\"torrentid\" value=\"$id\" /><input type=\"hidden\" name=\"action\" value=\"needseed\" /></form>");
                $keepseeding = "<b>管理员设置：<a href=\"JAVAscript:document.setkeepseed.submit();\">需要保种</a></b>";
            } else {
                print ("<form method=\"post\" id=\"setkeepseed\" name=\"setkeepseed\" action=\"keepseed.php\" enctype=\"multipart/form-data\" ><input type=\"hidden\" name=\"torrentid\" value=\"$id\" /><input type=\"hidden\" name=\"action\" value=\"unneedseed\" /></form>");
                $keepseeding = "<b>管理员设置：<a href=\"JAVAscript:document.setkeepseed.submit();\">不需要保种</a></b><br/>\n";
            }
        }

        if ($row ["needkeepseed"] == "yes") {
            $keepseeding .= "该资源目前有<b>" . $row ["seedkeeper"] . "</b>人保种，";
            $res = sql_query("SELECT * FROM keepseed WHERE torrentid = " . $id . " AND userid = ' $CURUSER[id] '") or sqlerr();
            if (mysql_num_rows($res) > 0) {
                print ("<form method=\"post\" id=\"keepseed\" name=\"keepseed\" action=\"keepseed.php\" enctype=\"multipart/form-data\" ><input type=\"hidden\" name=\"torrentid\" value=\"$id\" /><input type=\"hidden\" name=\"action\" value=\"idontwantkeepseed\" /></form>\n");
                $keepseeding .= "<a href=\"JAVAscript:document.keepseed.submit();\"><b>我不想对该资源保种了</b></a>。";
            } else {
                print ("<form method=\"post\" id=\"keepseed\" name=\"keepseed\" action=\"keepseed.php\" enctype=\"multipart/form-data\" ><input type=\"hidden\" name=\"torrentid\" value=\"$id\" /><input type=\"hidden\" name=\"action\" value=\"iwantkeepseed\" /></form>");
                $keepseeding .= "<a href=\"JAVAscript:document.keepseed.submit();\"><b>我也要保种</b></a>。";
            }
        }
        if (get_user_class() >= 13 || $row ["needkeepseed"] == "yes")
            tr($lang_details ['text_seeding_torrent'] . "", $keepseeding, 1);

        // ---------------- start subtitle block -------------------//
        $r = sql_query("SELECT subs.*, language.flagpic, language.lang_name FROM subs LEFT JOIN language ON subs.lang_id=language.id WHERE torrent_id = " . sqlesc($row ["id"]) . " ORDER BY subs.lang_id ASC") or sqlerr(__FILE__, __LINE__);
        print ("<tr><td class=\"rowhead\" valign=\"top\">" . $lang_details ['row_subtitles'] . "</td>");
        print ("<td class=\"rowfollow\" align=\"left\" valign=\"top\">");
        print ("<table border=\"0\" cellspacing=\"0\">");
        if (mysql_num_rows($r) > 0) {
            while ($a = mysql_fetch_assoc($r)) {
                $lang = "<tr><td class=\"embedded\"><img border=\"0\" src=\"pic/flag/" . $a ["flagpic"] . "\" alt=\"" . $a ["lang_name"] . "\" title=\"" . $a ["lang_name"] . "\" style=\"padding-bottom: 4px\" /></td>";
                $lang .= "<td class=\"embedded\">&nbsp;&nbsp;<a href=\"downloadsubs.php?torrentid=" . $a ['torrent_id'] . "&subid=" . $a ['id'] . "\"><u>" . $a ["title"] . "</u></a>" . (get_user_class() >= $submanage_class || (get_user_class() >= $delownsub_class && $a ["uppedby"] == $CURUSER ["id"]) ? " <font class=\"small\"><a href=\"subtitles.php?delete=" . $a ['id'] . "\">[" . $lang_details ['text_delete'] . "]</a>&nbsp;&nbsp;<a href=\"subtitles.php?rename=" . $a['id'] . "\">[" . $lang_details ['text_rename'] . "]</a></font>" : "") . "</td><td class=\"embedded\">&nbsp;&nbsp;" . ($a ["anonymous"] == 'yes' ? $lang_details ['text_anonymous'] . (get_user_class() >= $viewanonymous_class ? get_username($a ['uppedby'], false, true, true, false, true) : "") : get_username($a ['uppedby'])) . "  <a href=\"report.php?subtitle=" . $a ['id'] . "\"><img class=\"f_report\" src=\"pic/trans.gif\" alt=\"Report\" title=\"举报该字幕\"></a></td></tr>";
                print ($lang);
            }
        } else {
            print ("<tr><td class=\"embedded\">" . $lang_details ['text_no_subtitles'] . "</td></tr>");
        }
        print ("</table>");
        print ("<table border=\"0\" cellspacing=\"0\"><tr>");
        if ($CURUSER ['id'] == $row ['owner'] || get_user_class() >= $uploadsub_class) {
            print ("<td class=\"embedded\"><form method=\"post\" action=\"subtitles.php\"><input type=\"hidden\" name=\"torrent_name\" value=\"" . $row ["name"] . "\" /><input type=\"hidden\" name=\"detail_torrent_id\" value=\"" . $row ["id"] . "\" /><input type=\"hidden\" name=\"in_detail\" value=\"in_detail\" /><input type=\"submit\" value=\"" . $lang_details ['submit_upload_subtitles'] . "\" /></form></td>");
        }
        $moviename = "";
        $imdb_id = parse_imdb_id($row ["url"]);
        if ($imdb_id && $showextinfo ['imdb'] == 'yes') {
            $thenumbers = $imdb_id;
            if (!$moviename = $Cache->get_value('imdb_id_' . $thenumbers . '_movie_name')) {
                $movie = new Douban ($thenumbers, 'imdb');
                $movie->get_movie();
                $moviename = $movie->get_data('title');
            }
            $douban_rating = $movie->get_data('rating');
            $imdb = new \Imdb\Title($imdb_id);
            $imdb_rating = $imdb->rating();
            if ($row['imdb_rating'] != $imdb_rating) {
                sql_query("UPDATE torrents SET imdb_rating = '{$imdb_rating}' WHERE url = " . sqlesc($row ["url"]));
            }
        }
        //print ("<td class=\"embedded\"><form method=\"get\" action=\"http://shooter.cn/sub/\" target=\"_blank\"><input type=\"text\" name=\"searchword\" id=\"keyword\" style=\"width: 250px\" value=\"" . $moviename . "\" /><input type=\"submit\" value=\"" . $lang_details ['submit_search_at_shooter'] . "\" /></form></td><td class=\"embedded\"><form method=\"get\" action=\"http://www.opensubtitles.org/en/search2/\" target=\"_blank\"><input type=\"hidden\" id=\"moviename\" name=\"MovieName\" /><input type=\"hidden\" name=\"action\" value=\"search\" /><input type=\"hidden\" name=\"SubLanguageID\" value=\"all\" /><input onclick=\"document.getElementById('moviename').value=document.getElementById('keyword').value;\" type=\"submit\" value=\"" . $lang_details ['submit_search_at_opensubtitles'] . "\" /></form></td>\n") ;
        print ("<td class=\"embedded\" />");
        print ("<input type=\"text\" id=\"moviename\" style=\"width: 250px\" value=\"" . $moviename . "\" />");
        print ("<input type=\"button\" onclick=\"window.open('http://www.subhd.com/search/'+document.getElementById('moviename').value)\" value=\"" . $lang_details ['submit_search_at_subhd'] . "\"/>");
        print ("</td>");
        print ("</tr></table>");
        print ("</td></tr>\n");
        // ---------------- end subtitle block -------------------//

        if ($CURUSER ['showdescription'] != 'no' && !empty ($row ["descr"])) {
            $torrentdetailad = $Advertisement->get_ad('torrentdetail');
            tr("<a href=\"javascript: klappe_news('descr')\"><span class=\"nowrap\"><img class=\"minus\" src=\"pic/trans.gif\" alt=\"Show/Hide\" id=\"picdescr\" title=\"" . $lang_details ['title_show_or_hide'] . "\" /> " . $lang_details ['row_description'] . "</span></a>", "<div id='kdescr'>" . ($Advertisement->enable_ad() && $torrentdetailad ? "<div align=\"left\" style=\"margin-bottom: 10px\" id=\"ad_torrentdetail\">" . $torrentdetailad [0] . "</div>" : "") . format_comment($row ["descr"]) . "</div>", 1);
        }

        if (get_user_class() >= $viewnfo_class && $CURUSER ['shownfo'] != 'no' && $row ["nfosz"] > 0) {
            if (!$nfo = $Cache->get_value('nfo_block_torrent_id_' . $id)) {
                $nfo = code($row ["nfo"], $view == "magic");
                $Cache->cache_value('nfo_block_torrent_id_' . $id, $nfo, 604800);
            }
            tr("<a href=\"javascript: klappe_news('nfo')\"><img class=\"plus\" src=\"pic/trans.gif\" alt=\"Show/Hide\" id=\"picnfo\" title=\"" . $lang_details ['title_show_or_hide'] . "\" /> " . $lang_details ['text_nfo'] . "</a><br /><a href=\"viewnfo.php?id=" . $row ['id'] . "\" class=\"sublink\">" . $lang_details ['text_view_nfo'] . "</a>", "<div id='knfo' style=\"display: none;\"><pre style=\"font-size:10pt; font-family: 'Courier New', monospace;\">" . $nfo . "</pre></div>\n", 1);
        }

        if ($imdb_id && $showextinfo ['imdb'] == 'yes' && $CURUSER ['showimdb'] != 'no') {
            $thenumbers = $imdb_id;

            $movie = new Douban ($thenumbers, 'imdb');
            $movie->get_movie();
            reset_cachetimestamp($row ['id']);
            $title = $movie->get_data('title');
            $trans_title = $movie->get_data('transname');
            $country = $movie->get_data('country');
            $director = $movie->get_data('director');
            $creator = $movie->get_data('creator'); // For TV series
            $write = $movie->get_data('writing');
            $cast = $movie->get_data('cast');
            $plot_outline = $movie->get_data('plotoutline');
            $gen = $movie->get_data('genres');
            $douban_id = $movie->get_data('douban_id');
            $year = $movie->get_data('year');

            if (($photo_url = $movie->get_data('photo_localurl')) != FALSE)
                $smallth = "<img src=\"" . $photo_url . "\" width=\"105\" onclick=\"Preview(this);\" alt=\"poster\" />";
            else
                $smallth = "<img src=\"pic/imdb_pic/nophoto.gif\" alt=\"no poster\" />";

            $autodata = "<a href='https://movie.douban.com/subject/" . $douban_id . "/'><img src='pic/douban.png' alt='douban'>" . $title . " ({$year}) [{$douban_rating}/10]</a> <a href='https://www.imdb.com/title/tt" . $thenumbers . "'><img src='pic/imdb.png' height='16px' width='16px' alt='imdb'> {$imdb_rating}/10</a><br />";
            $autodata .= "<strong><font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font><br />\n";
            $autodata .= "<font color=\"darkred\" size=\"3\">" . $lang_details ['text_information'] . "</font><br />\n";
            $autodata .= "<font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font></strong><br />\n";
            $autodata .= "<strong><font color=\"DarkRed\">" . $lang_details ['text_title'] . "</font></strong>" . $title . "<br />\n";
            $autodata .= (($trans_title && $trans_title != $title) ? ("<strong><font color=\"DarkRed\">" . " 译名: " . "</font></strong>" . $trans_title . "<br />\n") : "");
            $runtimes = str_replace("分钟", $lang_details ['text_mins'], $movie->get_data('runtime_all'));
            $autodata .= "<strong><font color=\"DarkRed\">" . $lang_details ['text_year'] . "</font></strong>" . "" . $year . "<br />\n";
            $autodata .= "<strong><font color=\"DarkRed\">" . $lang_details ['text_runtime'] . "</font></strong>" . $runtimes . "<br />\n";
            $autodata .= "<strong><font color=\"DarkRed\">" . $lang_details ['text_votes'] . "</font></strong>" . "" . $movie->get_data('votes') . "<br />\n";
            $autodata .= "<strong><font color=\"DarkRed\">" . $lang_details ['text_rating'] . "</font></strong>" . "" . $movie->get_data('rating') . "<br />\n";
            $autodata .= "<strong><font color=\"DarkRed\">" . $lang_details ['text_language'] . "</font></strong>" . "" . $movie->get_data('language') . "<br />\n";
            $autodata .= "<strong><font color=\"DarkRed\">" . $lang_details ['text_country'] . "</font></strong>";

            $temp = "";
            for ($i = 0; $i < count($country); $i++) {
                $temp .= "$country[$i], ";
            }
            $autodata .= rtrim(trim($temp), ",");

            $autodata .= "<br />\n<strong><font color=\"DarkRed\">" . $lang_details ['text_all_genres'] . "</font></strong>";
            $temp = "";
            for ($i = 0; $i < count($gen); $i++) {
                $temp .= "$gen[$i], ";
            }
            $autodata .= rtrim(trim($temp), ",");

            $autodata .= "<br />\n<strong><font color=\"DarkRed\">" . $lang_details ['text_tagline'] . "</font></strong>" . "" . $movie->get_data('tagline') . "<br />\n";
            if ($director) {
                $autodata .= "<strong><font color=\"DarkRed\">" . $lang_details ['text_director'] . "</font></strong>";
                $temp = "";
                for ($i = 0; $i < count($director); $i++) {
                    // $temp .= "<a target=\"_blank\"
                    // href=\"http://www.imdb.com/Name?" .
                    // "".$director[$i]["imdb"]."" ."\">" .
                    // $director[$i]["name"] . "</a>, ";
                    $temp .= $director [$i] ["name"] . ", ";
                }
                $autodata .= rtrim(trim($temp), ",");
            } elseif ($creator)
                $autodata .= "<strong><font color=\"DarkRed\">" . $lang_details ['text_creator'] . "</font></strong>" . $creator;

            $autodata .= "<br />\n<strong><font color=\"DarkRed\">" . $lang_details ['text_written_by'] . "</font></strong>";
            $temp = "";
            for ($i = 0; $i < count($write); $i++) {
                // $temp .= "<a target=\"_blank\"
                // href=\"http://www.imdb.com/Name?" .
                // "".$write[$i]["imdb"]."" ."\">" .
                // "".$write[$i]["name"]."" . "</a>, ";
                $temp .= $write [$i] . ", ";
            }
            $autodata .= rtrim(trim($temp), ",");

            $autodata .= "<br /><br />\n\n<strong><font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font><br />\n";
            $autodata .= "<font color=\"darkred\" size=\"3\">" . $lang_details ['text_plot_outline'] . "</font><br />\n";
            $autodata .= "<font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font></strong>";
            $autodata .= "<br />\n" . $plot_outline;

            $autodata .= "<br /><br />\n\n<strong><font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font><br />\n";
            $autodata .= "<font color=\"darkred\" size=\"3\">" . $lang_details ['text_cast'] . "</font><br />\n";
            $autodata .= "<font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font></strong><br />\n";

            for ($i = 0; $i < count($cast); $i++) {
                if ($i > 9) {
                    break;
                }
                $autodata .= "<font color=\"DarkRed\">." . $cast[$i] . "</font><br />\n";
            }

            $Cache->new_page('detail_page_imdb_' . $imdb_id);
            if (!$Cache->get_page()) {
                $Cache->add_whole_row();
                print ("<tr>");
                print ("<td class=\"rowhead\"><a href=\"javascript: klappe_ext('imdb')\"><span class=\"nowrap\"><img class=\"minus\" src=\"pic/trans.gif\" alt=\"Show/Hide\" id=\"picimdb\" title=\"" . $lang_details ['title_show_or_hide'] . "\" /> " . $lang_details ['text_imdb'] . $lang_details ['row_info'] . "</span></a><div id=\"posterimdb\">" . $smallth . "</div></td>");
                $Cache->end_whole_row();
                $Cache->add_row();
                $Cache->add_part();
                print ("<td class=\"rowfollow\" align=\"left\"><div id='kimdb'>" . $autodata);
                $Cache->end_part();
                $Cache->end_row();
                $Cache->add_whole_row();
                print ("</div></td></tr>");
                $Cache->end_whole_row();
                $Cache->cache_page();
            }
            echo $Cache->next_row();
            $Cache->next_row();
            echo $Cache->next_part();
            if (get_user_class() >= $updateextinfo_class)
                echo $Cache->next_part();
            echo $Cache->next_row();
        }

        if ($imdb_id) {
            $where_area = " url = " . sqlesc(( int )$imdb_id) . " AND torrents.id != " . sqlesc($id) . " AND pulling_out != '1'";
            $copies_res = sql_query("SELECT torrents.id, torrents.name, torrents.sp_state, torrents.size, torrents.added, torrents.seeders, torrents.leechers, categories.id AS catid, categories.name AS catname, categories.image AS catimage, sources.name AS source_name, media.name AS medium_name, codecs.name AS codec_name, standards.name AS standard_name, processings.name AS processing_name FROM torrents LEFT JOIN categories ON torrents.category=categories.id LEFT JOIN sources ON torrents.source = sources.id LEFT JOIN media ON torrents.medium = media.id  LEFT JOIN codecs ON torrents.codec = codecs.id LEFT JOIN standards ON torrents.standard = standards.id LEFT JOIN processings ON torrents.processing = processings.id WHERE " . $where_area . " ORDER BY torrents.id DESC") or sqlerr(__FILE__, __LINE__);

            $copies_count = mysql_num_rows($copies_res);
            if ($copies_count > 0) {
                $s = "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n";
                $s .= "<tr><td class=\"colhead\" style=\"padding: 0; text-align:center;\">" . $lang_details ['col_type'] . "</td><td class=\"colhead\" align=\"left\">" . $lang_details ['col_name'] . "</td><td class=\"colhead\" align=\"center\">" . $lang_details ['col_quality'] . "</td><td class=\"colhead\" align=\"center\"><img class=\"size\" src=\"pic/trans.gif\" alt=\"size\" title=\"" . $lang_details ['title_size'] . "\" /></td><td class=\"colhead\" align=\"center\"><img class=\"time\" src=\"pic/trans.gif\" alt=\"time added\" title=\"" . $lang_details ['title_time_added'] . "\" /></td><td class=\"colhead\" align=\"center\"><img class=\"seeders\" src=\"pic/trans.gif\" alt=\"seeders\" title=\"" . $lang_details ['title_seeders'] . "\" /></td><td class=\"colhead\" align=\"center\"><img class=\"leechers\" src=\"pic/trans.gif\" alt=\"leechers\" title=\"" . $lang_details ['title_leechers'] . "\" /></td></tr>\n";
                while ($copy_row = mysql_fetch_assoc($copies_res)) {
                    $dispname = htmlspecialchars(trim($copy_row ["name"]));
                    $count_dispname = strlen($dispname);
                    $max_lenght_of_torrent_name = "80"; // maximum lenght
                    if ($count_dispname > $max_lenght_of_torrent_name) {
                        // $dispname = substr ( $dispname, 0,
                        // $max_lenght_of_torrent_name ) . "..";
                        $dispname = mb_strcut($dispname, 0, $max_lenght_of_torrent_name - 2, 'utf-8') . "..";
                    }

                    if (isset ($copy_row ["source_name"]))
                        $other_source_info = $copy_row ['source_name'] . ", ";
                    if (isset ($copy_row ["medium_name"]))
                        $other_medium_info = $copy_row ['medium_name'] . ", ";
                    if (isset ($copy_row ["codec_name"]))
                        $other_codec_info = $copy_row ['codec_name'] . ", ";
                    if (isset ($copy_row ["standard_name"]))
                        $other_standard_info = $copy_row ['standard_name'] . ", ";
                    if (isset ($copy_row ["processing_name"]))
                        $other_processing_info = $copy_row ['processing_name'] . ", ";

                    $sphighlight = get_torrent_bg_color($copy_row ['sp_state']);
                    $sp_info = get_torrent_promotion_append($copy_row ['sp_state']);

                    $s .= "<tr" . $sphighlight . "><td class=\"rowfollow nowrap\" valign=\"middle\" style='padding: 0'>" . return_category_image($copy_row ["catid"], "torrents.php?allsec=1&amp;") . "</td><td class=\"rowfollow\" align=\"left\"><a href=\"" . htmlspecialchars("details.php?id=" . $copy_row ["id"] . "&hit=1") . "\">" . $dispname . "</a>" . $sp_info . "</td>" . "<td class=\"rowfollow\" align=\"left\">" . rtrim(trim($other_source_info . $other_medium_info . $other_codec_info . $other_standard_info . $other_processing_info), ",") . "</td>" . "<td class=\"rowfollow\" align=\"center\">" . mksize($copy_row ["size"]) . "</td>" . "<td class=\"rowfollow nowrap\" align=\"center\">" . str_replace("&nbsp;", "<br />", gettime($copy_row ["added"], false)) . "</td>" . "<td class=\"rowfollow\" align=\"center\">" . $copy_row ["seeders"] . "</td>" . "<td class=\"rowfollow\" align=\"center\">" . $copy_row ["leechers"] . "</td>" . "</tr>\n";
                }
                $s .= "</table>\n";
                tr("<a href=\"javascript: klappe_news('othercopy')\"><span class=\"nowrap\"><img class=\"" . ($copies_count > 5 ? "plus" : "minus") . "\" src=\"pic/trans.gif\" alt=\"Show/Hide\" id=\"picothercopy\" title=\"" . $lang_details ['title_show_or_hide'] . "\" /> " . $lang_details ['row_other_copies'] . "</span></a>", "<b>" . $copies_count . $lang_details ['text_other_copies'] . " </b><br /><div id='kothercopy' style=\"" . ($copies_count > 5 ? "display: none;" : "display: block;") . "\">" . $s . "</div>", 1);
            }
        }
        if ($row ["type"] == "multi") {
            $files_info = "<b>" . $lang_details ['text_num_files'] . "</b>" . $row ["numfiles"] . $lang_details ['text_files'] . "<br />";
            $files_info .= "<span id=\"showfl\"><a href=\"javascript: viewfilelist(" . $id . ")\" >" . $lang_details ['text_see_full_list'] . "</a></span><span id=\"hidefl\" style=\"display: none;\"><a href=\"javascript: hidefilelist()\">" . $lang_details ['text_hide_list'] . "</a></span>";
        }
        if ($enablenfo_main == 'yes')
            tr($lang_details ['row_torrent_info'], "<table><tr>" . ($files_info != "" ? "<td class=\"no_border_wide\">" . $files_info . "</td>" : "") . "<td class=\"no_border_wide\"><b>" . $lang_details ['row_info_hash'] . ":</b>&nbsp;" . bin2hex($row ["info_hash"]) . "</td>" . (get_user_class() >= $torrentstructure_class ? "<td class=\"no_border_wide\"><b>" . $lang_details ['text_torrent_structure'] . "</b><a href=\"torrent_info.php?id=" . $id . "\">" . $lang_details ['text_torrent_info_note'] . "</a></td>" : "") . "</tr></table><span id='filelist'></span>", 1);
        tr($lang_details ['row_hot_meter'], "<table><tr><td class=\"no_border_wide\"><b>" . $lang_details ['text_views'] . "</b>" . $row ["views"] . "</td><td class=\"no_border_wide\"><b>" . $lang_details ['text_hits'] . "</b>" . $row ["hits"] . "</td><td class=\"no_border_wide\"><b>" . $lang_details ['text_snatched'] . "</b><a href=\"viewsnatches.php?id=" . $id . "\"><b>" . $row ["times_completed"] . $lang_details ['text_view_snatches'] . "</td><td class=\"no_border_wide\"><b>" . $lang_details ['row_last_action'] . "</b>" . gettime($row ["last_action"]) . "</td><td class=\"no_border_wide\"><b>" . $lang_details ['row_last_seed'] . "</b>" . gettime($row ["last_seed"]) . "</td></tr></table>", 1);
        $bwres = sql_query("SELECT uploadspeed.name AS upname, downloadspeed.name AS downname, isp.name AS ispname FROM users LEFT JOIN uploadspeed ON users.upload = uploadspeed.id LEFT JOIN downloadspeed ON users.download = downloadspeed.id LEFT JOIN isp ON users.isp = isp.id WHERE users.id=" . $row ['owner']);
        $bwrow = mysql_fetch_array($bwres);
        if ($bwrow ['upname'] && $bwrow ['downname'])
            tr($lang_details ['row_uploader_bandwidth'], "<img class=\"speed_down\" src=\"pic/trans.gif\" alt=\"Downstream Rate\" /> " . $bwrow ['downname'] . "&nbsp;&nbsp;&nbsp;&nbsp;<img class=\"speed_up\" src=\"pic/trans.gif\" alt=\"Upstream Rate\" /> " . $bwrow ['upname'] . "&nbsp;&nbsp;&nbsp;&nbsp;" . $bwrow ['ispname'], 1);
        tr("<span id=\"seeders\"></span><span id=\"leechers\"></span>" . $lang_details ['row_peers'] . "<br /><span id=\"showpeer\"><a href=\"javascript: viewpeerlist(" . $row ['id'] . ");\" class=\"sublink\">" . $lang_details ['text_see_full_list'] . "</a></span><span id=\"hidepeer\" style=\"display: none;\"><a href=\"javascript: hidepeerlist();\" class=\"sublink\">" . $lang_details ['text_hide_list'] . "</a></span>", "<div id=\"peercount\"><b>" . $row ['seeders'] . $lang_details ['text_seeders'] . add_s($row ['seeders']) . "</b> | <b>" . $row ['leechers'] . $lang_details ['text_leechers'] . add_s($row ['leechers']) . "</b></div><div id=\"peerlist\"></div>", 1);
        if ($_GET ['dllist'] == 1) {
            $scronload = "viewpeerlist(" . $row ['id'] . ")";

            echo "<script type=\"text/javascript\">\n";
            echo $scronload;
            echo "</script>";
        }

        // ------------- start thanked-by block--------------//

        $torrentid = $id;
        $thanksby = "";
        $nothanks = "";
        $thanks_said = 0;
        $thanks_all = 0;
        $thanksCount = get_row_count("thanks", "WHERE torrentid=" . sqlesc($torrentid));
        if ($thanksCount) {
            $thanks_sql = sql_query("SELECT userid FROM thanks WHERE torrentid=" . sqlesc($torrentid) . " ORDER BY id DESC LIMIT 20");
            $thanks_all = mysql_num_rows($thanks_sql);

            while ($rows_t = mysql_fetch_array($thanks_sql)) {
                $thanks_userid = $rows_t ["userid"];
                if ($rows_t ["userid"] == $CURUSER ['id']) {
                    $thanks_said = 1;
                } else {
                    $thanksby .= get_username($thanks_userid) . " ";
                }
            }
        } else
            $nothanks = $lang_details ['text_no_thanks_added'];

        if (!$thanks_said) {
            $thanks_said = get_row_count("thanks", "WHERE torrentid=$torrentid AND userid=" . sqlesc($CURUSER ['id']));
        }
        if ($thanks_said == 0) {
            $buttonvalue = " value=\"" . $lang_details ['submit_say_thanks'] . "\"";
        } else {
            $buttonvalue = " value=\"" . $lang_details ['submit_you_said_thanks'] . "\" disabled=\"disabled\"";
            $thanksby = get_username($CURUSER ['id']) . " " . $thanksby;
        }
        $thanksbutton = "<input class=\"btn\" type=\"button\" id=\"saythanks\"  onclick=\"saythanks(" . $torrentid . ");\" " . $buttonvalue . " />";
        tr($lang_details ['row_thanks_by'], "<span id=\"thanksadded\" style=\"display: none;\"><input class=\"btn\" type=\"button\" value=\"" . $lang_details ['text_thanks_added'] . "\" disabled=\"disabled\" /></span><span id=\"curuser\" style=\"display: none;\">" . get_username($CURUSER ['id']) . " </span><span id=\"thanksbutton\">" . $thanksbutton . "</span>&nbsp;&nbsp;<span id=\"nothanks\">" . $nothanks . "</span><span id=\"addcuruser\"></span>" . $thanksby . ($thanks_all < $thanksCount ? $lang_details ['text_and_more'] . $thanksCount . $lang_details ['text_users_in_total'] : ""), 1);
        // ------------- end thanked-by block--------------//

        // ------------- start bonus block--------------//

        $userid = $CURUSER ["id"]; // 当前使用者ID

        $tsql = sql_query("SELECT owner FROM torrents where id=" . $torrentid);
        $arr = mysql_fetch_array($tsql);
        if (!$arr)
            stderr("Error", "Invalid torrent id!");
        $useridgift = $arr ['owner']; // 这里取出来的是一个ID，种子所有者

        // 显示给过bonus的人和给bonus的值
        $givebonusby = "";
        $nogivebonus = "";
        $getbonusnow = 0;
        $givebonus_said = 0;
        $givebonus_all = 0;

        $givebonusCount = get_row_count("givebonus", "WHERE bonustotorrentid=" . sqlesc($torrentid) . " and type=1");

        if ($givebonusCount) {
            $givebonus_sql = sql_query("SELECT bonusfromuserid,bonus FROM givebonus WHERE bonustotorrentid=" . sqlesc($torrentid) . " and type=1 ORDER BY id DESC LIMIT 20");
            $givebonus_sql0 = sql_query("SELECT bonusfromuserid,bonus FROM givebonus WHERE bonustotorrentid=" . sqlesc($torrentid) . " and type=1 ");
            $givebonus_all = mysql_num_rows($givebonus_sql);

            while ($rows_t = mysql_fetch_array($givebonus_sql)) {
                $bonusfrom_userid = $rows_t ["bonusfromuserid"];
                $bonusCnt = $rows_t ["bonus"];
                $bonusfromusername = get_username($bonusfrom_userid);

                $bonusfromusername = $bonusfromusername . "(" . $bonusCnt . ")";
                $givebonusby .= $bonusfromusername . " ";
            }

            while ($rows_t0 = mysql_fetch_array($givebonus_sql0)) {
                $aa = $rows_t0 ["bonus"];
                $getbonusnow += $aa;
            }
        } else {
            $nogivebonus = $lang_details ['text_no_givebonus_added'];
        }

        if ($userid != $useridgift) {
            $bonus0 = $CURUSER ['seedbonus'];

            $thanksbutton50 = "<input class=\"btn\" type=\"button\" id=\"thankbutton50\"  onclick=\"givebonus(" . $torrentid . ",50," . $bonus0 . ");\" value=\"+50\" />";
            $thanksbutton100 = "<input class=\"btn\" type=\"button\" id=\"thankbutton100\"  onclick=\"givebonus(" . $torrentid . ",100," . $bonus0 . ");\" value=\"+100\" />";
            $thanksbutton200 = "<input class=\"btn\" type=\"button\" id=\"thankbutton200\"  onclick=\"givebonus(" . $torrentid . ",200," . $bonus0 . ");\" value=\"+200\" />";
            $thanksbutton500 = "<input class=\"btn\" type=\"button\" id=\"thankbutton500\"  onclick=\"givebonus(" . $torrentid . ",500," . $bonus0 . ");\" value=\"+500\" />";
            $thanksbutton1000 = "<input class=\"btn\" type=\"button\" id=\"thankbutton1000\"  onclick=\"givebonus(" . $torrentid . ",1000," . $bonus0 . ");\" value=\"+1000\" />";
            $thanksbutton3000 = "<input class=\"btn\" type=\"button\" id=\"thankbutton3000\"  onclick=\"givebonus(" . $torrentid . ",3000," . $bonus0 . ");\" value=\"+3000\" />";
            $thanksbutton5000 = "<input class=\"btn\" type=\"button\" id=\"thankbutton5000\"  onclick=\"givebonus(" . $torrentid . ",5000," . $bonus0 . ");\" value=\"+5000\" />";
            $thanksbutton10000 = "<input class=\"btn\" type=\"button\" id=\"thankbutton10000\"  onclick=\"givebonus(" . $torrentid . ",10000," . $bonus0 . ");\" value=\"+10000\" />";

            $tdleft = "<br /><label style=\" font-weight:normal\">" . $lang_details ['text_get_now'] . ":&nbsp;" . $getbonusnow . "</label>";

            tr($lang_details ['row_givebonus'] . $tdleft, "<span id=\"thanksadded\" style=\"display: none;\"><input class=\"btn\"

type=\"button\" value=\"" . $lang_details ['text_thanks_added'] . "\" disabled=\"disabled\" /></span><span id=\"curuser\"

style=\"display: none;\">" . get_username($CURUSER ['id']) . " </span><span id=\"thanksbutton50

\">" . $thanksbutton50 . "&nbsp;&nbsp;" . $thanksbutton100 . "&nbsp;&nbsp;" . $thanksbutton200 . "&nbsp;&nbsp;" . $thanksbutton500 . "&nbsp;&nbsp;" . $thanksbutton1000 . "&nbsp;&nbsp;" . $thanksbutton3000 . "&nbsp;&nbsp;" . $thanksbutton5000 . "&nbsp;&nbsp;" . $thanksbutton10000 . "</span>&nbsp;&nbsp;<br /><span id=\"curuserbonus\" style=\"display: none;\">" . get_username($CURUSER ['id']) . " </span><span id=\"nothanksbonus\">" . $nogivebonus . "</span><span id=\"addcuruserbonus\"></span>" . $givebonusby . ($givebonus_all < $givebonusCount ? $lang_details ['text_and_more'] . $givebonusCount . $lang_details ['text_bonususer_in_total'] : ""), 1);
        } else {

            /*
             * $tdleft="<span id=\"ads\" style=\"font-weight:
             * nomal;\">当前收到:1000</span>";
             */

            $tdleft = "<br /><label style=\" font-weight:normal\">" . $lang_details ['text_get_now'] . ":&nbsp;" . $getbonusnow . "</label>";

            tr($lang_details ['row_givebonus0'] . $tdleft, "<span id=\"curuserbonus\" style=\"display: none;\">" . get_username($CURUSER ['id']) . " </span><span id=\"nothanksbonus\">" . $nogivebonus . "</span><span id=\"addcuruserbonus\"></span>" . $givebonusby . ($givebonus_all < $givebonusCount ? $lang_details ['text_and_more'] . $givebonusCount . $lang_details ['text_bonususer_in_total'] : ""), 1);
        }

        // ------------- end bonus block--------------//
        if (get_user_class() >= $torrentmanage_class && ($row ['category'] != 4013)) {
            global $expirehalfleech_torrent, $expirefree_torrent, $expiretwoup_torrent, $expiretwoupfree_torrent, $expiretwouphalfleech_torrent, $expirethirtypercentleech_torrent, $expirenormal_torrent, $hotdays_torrent, $hotseeder_torrent, $halfleechbecome_torrent, $freebecome_torrent, $twoupbecome_torrent, $twoupfreebecome_torrent, $twouphalfleechbecome_torrent, $thirtypercentleechbecome_torrent, $normalbecome_torrent, $deldeadtorrent_torrent;
            $promotionuntil_torrent = array(
                $expirenormal_torrent,
                $expirefree_torrent,
                $expiretwoup_torrent,
                $expiretwoupfree_torrent,
                $expirehalfleech_torrent,
                $expiretwouphalfleech_torrent,
                $expirethirtypercentleech_torrent
            );
            $promotionuntil_torrent = join(',', $promotionuntil_torrent);

            require_once(get_langfile_path('pickup.php'));
            print ("<form method=\"post\" id=\"pickup\" name=\"pickup\" action=\"pickup.php\" enctype=\"multipart/form-data\" >");
            print ("<input type=\"hidden\" name=\"id\" value=\"$id\" />");
            if (isset ($_GET ["returnto"]))
                print ("<input type=\"hidden\" name=\"returnto\" value=\"" . htmlspecialchars($_GET ["returnto"]) . "\" />");
            $pickcontent = "";
            $pickcontent .= "<b>" . $lang_pickup ['row_special_torrent'] . ":&nbsp;</b>" . "<select id=\"sel_spstate\" name=\"sel_spstate\" style=\"width: 100px;\" onchange=\"getpromotionuntil('$promotionuntil_torrent', 'sel_spstate')\">" . promotion_selection($row ["sp_state"], 0) . "</select>&nbsp;&nbsp;&nbsp;";
            // $pickcontent .= "" . "<select id='promotion_time_type'
            // name=\"promotion_time_type\" style=\"width: 100px;\">" .
            // "<option" . (($row ["promotion_time_type"] == "0") ? "
            // selected=\"selected\"" : "") . " value=\"0\">使用全局设置</option>" .
            // "<option" . (($row ["promotion_time_type"] == "1") ? "
            // selected=\"selected\"" : "") . " value=\"1\">永久</option>" .
            // "<option" . (($row ["promotion_time_type"] == "2") ? "
            // selected=\"selected\"" : "") . " value=\"2\">直到</option>" .
            // "</select>&nbsp;&nbsp;&nbsp;";
            if ($row ["promotion_time_type"] == 0) {
                if ($row ["sp_state"] < 8) {
                    $promotionuntil_time = date("Y-m-d H:i:s", strtotime($row ["sp_time"]) + 2 * 24 * 3600);
                } elseif ($row ["sp_state"] > 7 && $row ["sp_state"] < 14) {
                    $promotionuntil_time = $row ["promotion_until"];
                } else {
                    $promotionuntil_time = date("Y-m-d H:i:s", time() + 2 * 24 * 3600);
                }
            }
            $pickcontent .= '<label id="sp-expire"><b>促销截止:&nbsp;</b><input type="text" name="promotionuntil" id="promotionuntil" value="' . (($row ["promotion_time_type"] == 0) ? $promotionuntil_time : $row ["promotion_until"]) . '" /></label>&nbsp;&nbsp;&nbsp;';
            $pickcontent .= '</br>';
            // 置顶处理 beg
            $pickcontent .= "<b>" . $lang_pickup ['row_torrent_position'] . ":&nbsp;</b>";
            $pickcontent .= "<select name=\"sel_posstate\" style=\"width: 100px;\">";
            $pickcontent .= "<option" . (($row ["pos_state"] == "normal") ? " selected=\"selected\"" : "") . " value=\"0\">" . $lang_pickup ['select_normal'] . "</option>";
            $pickcontent .= "<option" . (($row ["pos_state"] == "sticky") ? " selected=\"selected\"" : "") . " value=\"1\">" . $lang_pickup ['select_sticky'] . "</option>";
            $pickcontent .= "<option" . (($row ["pos_state"] == "double_sticky") ? " selected=\"selected\"" : "") . " value=\"2\">" . $lang_pickup ['select_double_sticky'] . "</option>";
            $pickcontent .= "<option" . (($row ["pos_state"] == "triple_sticky") ? " selected=\"selected\"" : "") . " value=\"3\">" . $lang_pickup ['select_triple_sticky'] . "</option>";
            $pickcontent .= "</select>&nbsp;&nbsp;&nbsp;";
            // 置顶处理 end
            $pickcontent .= '<label id="pos-expire"><b>置顶截止:&nbsp;</b><input type="text" name="posstateuntil" id="posstateuntil" value="' . (($row ["pos_state"] == "normal") ? date("Y-m-d H:i:s", time() + 2 * 24 * 3600) : $row ["pos_state_until"]) . '" /></label>&nbsp;&nbsp;&nbsp;';

            $pickcontent .= "<b>" . $lang_pickup ['row_recommended_movie'] . ":&nbsp;</b>" . "<select name=\"sel_recmovie\" style=\"width: 100px;\">" . "<option" . (($row ["picktype"] == "normal") ? " selected=\"selected\"" : "") . " value=\"0\">" . $lang_pickup ['select_normal'] . "</option>" . "<option" . (($row ["picktype"] == "hot") ? " selected=\"selected\"" : "") . " value=\"1\">" . $lang_pickup ['select_hot'] . "</option>" . "<option" . (($row ["picktype"] == "classic") ? " selected=\"selected\"" : "") . " value=\"2\">" . $lang_pickup ['select_classic'] . "</option>" . "<option" . (($row ["picktype"] == "recommended") ? " selected=\"selected\"" : "") . " value=\"3\">" . $lang_pickup ['select_recommended'] . "</option>" . "<option" . (($row ["picktype"] == "0day") ? " selected=\"selected\"" : "") . " value=\"4\">0day</option>" . "<option" . (($row ["picktype"] == "IMDB") ? " selected=\"selected\"" : "") . " value=\"5\">IMDB TOP 250</option>" . "</select>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . "<a href=\"JAVAscript:document.pickup.submit();\"><b>" . $lang_pickup ['submit_edit_it'] . "</b></a>" . "</form>\n";
            // "</form><input id=\"cx\" type=\"submit\"
            // value=\"".$lang_pickup['submit_edit_it']."\" />";
            tr($lang_pickup ['row_pick'], $pickcontent, 1);
            // print("<tr><td class=\"toolbox\" colspan=\"2\"
            // align=\"center\"><input id=\"qr\" type=\"submit\"
            // value=\"".$lang_pickup['submit_edit_it']."\" /> <input
            // type=\"reset\"
            // value=\"".$lang_pickup['submit_revert_changes']."\"
            // /></td></tr>\n");
            // print("</form>\n");
        }

        // -----------------COMMENT START ---------------------//

        print ("</table>\n");
    } else {
        stdhead($lang_details ['head_comments_for_torrent'] . "\"" . $row ["name"] . "\"");
        print ("<h1 id=\"top\">" . $lang_details ['text_comments_for'] . "<a href=\"details.php?id=" . $id . "\">" . htmlspecialchars($row ["name"]) . "</a></h1>\n");
    }

    // -----------------COMMENT SECTION ---------------------//
    if ($CURUSER ['showcomment'] != 'no') {
        $count = get_row_count("comments", "WHERE torrent=" . sqlesc($id));
        if ($count) {
            print ("<br /><br />");
            print ("<h1 align=\"center\" id=\"startcomments\">" . $lang_details ['h1_user_comments'] . "</h1>\n");
            list ($pagertop, $pagerbottom, $limit) = pager(10, $count, "details.php?id=$id&cmtpage=1&", array(
                'lastpagedefault' => 1
            ), "page");

            $allrows = array();
            // 取出置顶贴
            $subres = sql_query("SELECT id, text, user, added, editedby, editdate, is_sticky FROM comments WHERE torrent = $id AND is_sticky > 0 ORDER BY is_sticky DESC, id ASC") or sqlerr(__FILE__, __LINE__);
            while ($subrow = mysql_fetch_array($subres)) {
                $allrows [] = $subrow;
            }

            // 取出非置顶
            $subres = sql_query("SELECT id, text, user, added, editedby, editdate, is_sticky FROM comments WHERE torrent = $id AND is_sticky = 0 ORDER BY id $limit") or sqlerr(__FILE__, __LINE__);
            while ($subrow = mysql_fetch_array($subres)) {
                $allrows [] = $subrow;
            }
            print ($pagertop);
            commenttable($allrows, "torrent", $id);
            print ($pagerbottom);
        }
    }
    print ("<br /><br /><a name='quickreply' id='quickreply'> </a>");
    print ("<table style='border:1px solid #000000;'><tr><td class=\"text\" align=\"center\"><b>" . $lang_details ['text_quick_comment'] . "</b><br /><br /><form id=\"compose\" name=\"comment\" method=\"post\" action=\"" . htmlspecialchars("comment.php?action=add&type=torrent") . "\" onsubmit=\"return postvalid(this);\"><input type=\"hidden\" name=\"pid\" value=\"" . $id . "\" /><br />");
    quickreply('comment', 'body', $lang_details ['submit_add_comment']);
    print ("</form></td></tr></table>");
    print ("<p align=\"center\"><a class=\"index\" href=\"" . htmlspecialchars("comment.php?action=add&pid=" . $id . "&type=torrent") . "\">" . $lang_details ['text_add_a_comment'] . "</a></p>\n");

    ?>
    <script type="text/javascript">
        $(function () {
            $("#posstateuntil").datetimepicker({
                dateFormat: "yy-mm-dd",
                showSecond: true,
                timeFormat: "hh:mm:ss",
                minDate: new Date()
            });
            $("#promotionuntil").datetimepicker({
                dateFormat: "yy-mm-dd",
                showSecond: true,
                timeFormat: "hh:mm:ss",
                minDate: new Date()
            });
        });

        function getpromotionuntil(arr, selectid) {
            var promotionid = document.getElementById(selectid).value;
            var arr2 = arr.split(",");
            if (promotionid > 0 && promotionid < 8) {
                $("#promotionuntil").datetimepicker("setDate", (new Date(new Date().getTime() + arr2[promotionid - 1] * 86400 * 1000)));
            } else if (promotionid > 7 && promotionid < 14) {
            }
        }

        function quick_reply_to(username) {
            parent.document.getElementById("quickreplytext").focus();
            parent.document.getElementById("quickreplytext").value = "@" + username + " " + parent.document.getElementById("quickreplytext").value;
        }

        var clipboard = new ClipboardJS("#direct_link");
        clipboard.on("success", function (e) {
            alert("复制成功");
        });
        clipboard.on('error', function (e) {
            prompt("复制失败，请手动复制：", e.text);
        });
    </script>
    <?php
}
stdfoot();
