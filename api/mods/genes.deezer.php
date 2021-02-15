<?php
g::set("void.deezer", array(
    "views" => array(
        // Configuration functions: config.json
        "DeezerChannel" => array("urls" => array("en" => "deezer=channel")),
        // Configuration functions: config.json
        "DeezerConfig" => array("urls" => array("en" => "deezer=config")),
        "DeezerLogin" => array("urls" => array("en" => "deezer=login")),
        "DeezerLogout" => array("urls" => array("en" => "deezer=logout")),
        "DeezerAuth" => array("urls" => array("en" => "deezer=auth")),
        "DeezerUser" => array("urls" => array("en" => "deezer=user")),
        // Content management functions
        "DeezerAlbums" => array(
            "urls" => array("en" => "deezer=albums"),
        ),
        "DeezerPlaylists" => array(
            "urls" => array("en" => "deezer=playlists"),
        ),
        // Tag related functions
        "DeezerTags" => array(
            "urls" => array("en" => "deezer=tags"),
        ),
        // Event related functions
        "DeezerEvents" => array(
            "urls" => array("en" => "deezer=events"),
        ),
        // Setting related functions
        "DeezerSettings" => array(
            "urls" => array("en" => "deezer=settings"),
        ),
        // General admin page
        "Deezer" => array(
            "urls" => array("en" => array("deezer", "deezer=dashboard")),
        ),
    ),
    "bits" => array(
        "deezer_dashboard_title" => array("en" => "Deezer Connections"),
        "deezer_albums_title" => array("en" => "Albums"),
        "deezer_playlists_title" => array("en" => "Playlists"),
    ),
    "tmpls" => array(),
    "opts" => array(),
    "rules" => array(
        "no" => array(
            "guest" => array("Deezer", "DeezerConfig", "DeezerAlbums", "DeezerPlaylists"),
        ),
    ),
));

g::def("mods.deezer", array(
    "Init" => function () {
        g::set("op.meta.url.mod", "Deezer");
    },
    "DeezerChannel" => function () {
        $cdzjs = g::get("config.deezer.script");
        $cache_expire = 60 * 60 * 24 * 365;
        header("Pragma: public");
        header("Cache-Control: maxage=" . $cache_expire);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cache_expire) . ' GMT');
        echo '<script src="' . $cdzjs . '"></script>';
        die;
    },
    "DeezerLogin" => function () {
        ProcessDeezerLogin();
    },
    "DeezerLogout" => function () {
        g::run("core.SessionEnd");
        g::run("tools.Redirect", "./");
    },
    "DeezerAuth" => function () {
        ProcessDeezerAuth();
    },
    "DeezerUser" => function () {
        ProcessDeezerUserInfo(true);
    },
    "Deezer" => function () {
        g::run("mods.deezer.Init");
        $args = g::get("op.meta.url.args");
        $get = g::get("op.meta.url.args");
        $post = g::get("post");

        $folder_path = g::get("config.paths.clone_data_folder") . "dz" . V;
        if (!is_dir($folder_path)) {
            g::run("tools.CreateFolder", $folder_path);
        }

        $du = g::get("op.data.deezer.user");
        $cdz = g::get("config.deezer");
        $dzid = $cdz["user_id"];

        $albums_mode = $playlists_mode = false;
        if (!empty($args["albums"])) {
            $albums_mode = $args["albums"];
            if ($albums_mode == "get") {
                GetDeezerAlbums($dzid);
                return;
            } else if ($albums_mode == "sync") {
                DeezerSyncAlbums($dzid);
                g::run("ui.LoadViewHtml");
                return;
            } else if ($albums_mode == "view") {
                if (!empty($args["file"])) {
                    $file = $args["file"];
                    $path = $cdz["cache"]["albums"]["list"][$file];
                    $dataset = g::run("tools.ReadFileJD", $path);
                    g::set("op.data", $dataset);
                    g::run("tools.ExitWithOpDataResponse");
                }
            } else if ($albums_mode == "set") {
                DeezerUpdateAlbum($args, $get, $post);
                return;
            }
        }
        if (!empty($args["playlists"])) {
            $playlists_mode = $args["playlists"];
            if ($playlists_mode == "get") {
                GetDeezerPlaylists($dzid);
                g::run("ui.LoadViewHtml");
                return;
            } else if ($playlists_mode == "sync") {
                DeezerSyncPlaylists($dzid);
                g::run("ui.LoadViewHtml");
                return;
            } else if ($playlists_mode == "view") {
                if (!empty($args["file"])) {
                    $file = $args["file"];
                    $path = $cdz["cache"]["playlists"]["list"][$file];
                    $dataset = g::run("tools.ReadFileJD", $path);
                    g::set("op.data", $dataset);
                    g::run("tools.ExitWithOpDataResponse");
                }
            } else if ($playlists_mode == "set") {
                DeezerUpdatePlaylist($args, $get, $post);
                return;
            }
        }

        g::set("op.meta.url.args.deezer", "dashboard");

        $inv = array();
        if (empty($cdz["cache"])) {
            // not locally cached albums.
            $inv["albums"] = 0;
            // not locally cached playlists.
            $inv["playlists"] = 0;
        } else {
            $cdzinv = $cdz["cache"];
            $cdzinvalb = (!empty($cdzinv["albums"])) ? $cdzinv["albums"] : "";
            $cdzinvpla = (!empty($cdzinv["playlists"])) ? $cdzinv["playlists"] : "";
            if (empty($cdzinvalb)) {
                // not locally cached albums.
                $inv["albums"] = 0;
            } else {
                $inv["albums"] = array();
                $inv["albums"]["date"] = $cdzinvalb["date"];
                $inv["albums"]["sync"] = $cdzinvalb["sync"];
                $inv["albums"]["list"] = array_keys($cdzinvalb["list"]);
            }
            if (empty($cdzinvpla)) {
                // not locally cached playlists.
                $inv["playlists"] = 0;
            } else {
                $inv["playlists"] = array();
                $inv["playlists"]["date"] = $cdzinvpla["date"];
                $inv["playlists"]["sync"] = $cdzinvpla["sync"];
                $inv["playlists"]["list"] = array_keys($cdzinvpla["list"]);
            }
        }
        g::set("op.data.deezer.inventory", $inv);

        if (empty($du)) {
            $user = GetDeezerUserStuff($dzid);
            $user_info = $user["body"];

            $albums = GetDeezerUserStuff($dzid, "albums");
            $user_info["album_count"] = $albums["body"]["total"];

            $playlists = GetDeezerUserStuff($dzid, "playlists");
            $user_info["playlist_count"] = $playlists["body"]["total"];

            g::set("op.data.deezer.user", $user_info);
            g::run("core.SessionSet", "op.data.deezer.user", $user_info);
        }

        g::run("ui.LoadViewHtml");
    },
    "DeezerAlbums" => function () {
        g::run("mods.deezer.Init");
        $args = g::get("op.meta.url.args");
        $post = g::get("post");

        $args["seq"] = (!empty($args["seq"])) ? $args["seq"] : "albums";

        $mixed_query = array(
            "cols" => "*",
            "table" => "items",
            "sort" => array("tsc" => "DESC"),
            "filter" => array("g_type" => "dal"),
            "args" => $args,
            "post" => $post,
        );

        $dataset = g::run("db.GetWithSessionPostedQueryArgs", $mixed_query);

        foreach ($dataset["data"] as $key => $value) {
            $dataset["data"][$key]["g_bits"] = g::run("tools.JD", $dataset["data"][$key]["g_bits"]);
            $dataset["data"][$key]["g_labels"] = str_replace(array('[', ']', ',', '"', 'all'), "", $dataset["data"][$key]["g_labels"]);
        }

        $states = g::run("db.Get", array("g_key", "labels", "g_type='item_states'"));
        $sa = array_column($states["data"], "g_key");
        g::set("op.data.states", $sa);

        g::set("op.data.albums", $dataset);
        g::run("ui.LoadViewHtml");
    },
    "DeezerPlaylists" => function () {
        g::run("mods.deezer.Init");
        $args = g::get("op.meta.url.args");
        $post = g::get("post");

        $args["seq"] = (!empty($args["seq"])) ? $args["seq"] : "playlists";

        $mixed_query = array(
            "cols" => "*",
            "table" => "items",
            "sort" => array("tsc" => "DESC"),
            "filter" => array("g_type" => "dpl"),
            "args" => $args,
            "post" => $post,
        );

        $dataset = g::run("db.GetWithSessionPostedQueryArgs", $mixed_query);

        foreach ($dataset["data"] as $key => $value) {
            $dataset["data"][$key]["g_bits"] = g::run("tools.JD", $dataset["data"][$key]["g_bits"]);
            $dataset["data"][$key]["g_labels"] = str_replace(array('[', ']', ',', '"', 'all'), "", $dataset["data"][$key]["g_labels"]);

            $tracks = $dataset["data"][$key]["g_bits"]["tracks"];
            if (!empty($tracks)) {
                $tracks_html = RenderTracks($tracks);
                $dataset["data"][$key]["g_bits"]["tracks"] = $tracks_html;
            }
        }

        $states = g::run("db.Get", array("g_key", "labels", "g_type='item_states'"));
        $sa = array_column($states["data"], "g_key");
        g::set("op.data.states", $sa);

        g::set("op.data.playlists", $dataset);
        g::run("ui.LoadViewHtml");
    },
));

function ProcessDeezerLogin()
{
    $get = g::get("op.meta.url.args");
    $session = g::get("session");

    if (!empty($get["state"]) && $get["state"] !== $session["dz_state"]) {
        echo "State mismatch. Can not proceed with login.";
        exit;
    } else {
        ProcessDeezerLoginRedirection();
    }
}

function ProcessDeezerLoginRedirection()
{
    $dz = g::get("config.deezer");
    $hash = md5(uniqid(rand(), true)); //CSRF protection
    g::run("core.SessionSet", "dz_state", $hash);

    $login_dialog_url = $dz["account_url"] . "/oauth/auth.php?"
    . "app_id=" . $dz["application_id"]
    . "&redirect_uri=" . urlencode($dz["application_domain"] . "/deezer=auth")
        . "&perms=email,basic_access,offline_access"
        . "&state=" . $hash;
    header("Location: " . $login_dialog_url);
    // echo '<META HTTP-EQUIV="refresh" CONTENT="0;URL=' . $login_dialog_url . '">';
    exit;
}

function ProcessDeezerAuth()
{
    $get = g::get("op.meta.url.args");
    if (!empty($get["code"])) {
        ProcessDeezerCode($get["code"]);
    }
}

function ProcessDeezerCode($code)
{
    $dz = g::get("config.deezer");
    $token_url = $dz["account_url"] . "/oauth/access_token.php?"
        . "app_id=" . $dz["application_id"]
        . "&secret=" . $dz["secret_key"]
        . "&code=" . $code;

    $response = g::run("tools.LoadPathSafe", $token_url);

    // responds something like "wrong code" is code is wrong, very clear :)
    if ($response == "wrong code") {
        g::run("tools.Say", "need-to-login-again-wrong-code", 5);
        g::run("tools.RedirectNow", "./");
    } else {
        $params = null;
        parse_str($response, $params);
        $timestamp = g::run("tools.DTS");

        if (!empty($params["access_token"])) {
            $dzc = array(
                "at" => $params["access_token"],
                "exp" => $params["expires"],
                "ts" => $timestamp,
            );
            g::run("core.SessionSet", "dz", $dzc);
            g::run("core.CookieSet", "dz", $dzc, $params["expires"]);
            unset($_SESSION["code"]);

            $deezer_get_url = $dz["application_domain"] . "/deezer=user";
            header("Location: " . $deezer_get_url);
            exit;
        }
    }
}

function ProcessDeezerUserInfo($redir = false)
{
    $get = g::get("op.meta.url.args");
    $session = g::get("session");
    $cookie = g::get("cookie");

    if (empty($session["dz"])) {
        if (!empty($session["redir"])) {
            $redir = $session["redir"];
        } else {
            $redir = true;
        }

        if (!empty($cookie["dz"])) {
            $session["dz"] = $cookie["dz"][0];
        }
    }

    $cache_dz = array();
    if (!empty($session["dz"])) {
        $cache_dz = $session["dz"];
    }
    if (!empty($cache_dz["at"])) {
        $dz = g::get("config.deezer");

        $type = "GET";
        $url = $dz["api_url"] . '/user/me';
        $headers = array();
        $parameters = array(
            "access_token" => $cache_dz["at"],
        );
        $resp = SendDeezerRequest($type, $url, $parameters, $headers);
        $session["dz"]["user"] = $resp["body"];
        g::run("core.SessionSet", "dz", $session["dz"]);
        $user = array(
            "type" => "user",
            "login_date" => g::run("tools.Now"),
            "dz" => $session["dz"]["user"],
        );
        g::run("core.SessionSet", "op.meta.user", $user);

        if ($redir !== false) {
            if ($redir === true) {
                g::run("tools.RedirectNow", "/");
            } else if (!empty($redir)) {
                g::run("tools.RedirectNow", $redir);
            }
        }
    } else {
        // g::run("tools.RedirectNow", "./deezer=login");
    }
}

function RenderTracks($tracks)
{
    $tracks_html = "";

    foreach ($tracks as $t) {
        $tracks_html .= "<li><a target='_blank' href='https://www.deezer.com/track/" . $t["d"] . "'>" . $t["a"] . " - " . $t["t"] . "</a></li>";
    }

    return $tracks_html;
}

function GetDeezerUserStuff($user_id, $mode = "")
{
    $get = g::get("op.meta.url.args");
    $session = g::get("session");
    $dz = g::get("config.deezer");

    $type = "GET";
    $url = $dz["api_url"] . '/user/' . $user_id . "/$mode";
    // $url = $dz["api_url"] . '/user/282565005/playlists';
    // $url = $dz["api_url"] . '/user/282565005/albums';
    $headers = array();
    $parameters = array(
        //"access_token" => $session["dz_at"],
    );
    $resp = SendDeezerRequest($type, $url, $parameters, $headers);
    // g::run("core.SessionSet", "dz_user", $resp["body"]);
    // g::run("tools.RedirectNow", "./");
    return $resp;
}

function SendDeezerRequest($type, $url, $parameters = array(), $headers = array())
{
    $options = array(
        CURLOPT_HEADER => true,
        CURLOPT_RETURNTRANSFER => true,
    );
    $url = rtrim($url, '/');
    $options[CURLOPT_CUSTOMREQUEST] = 'GET';
    switch ($type) {
        case 'POST':
            $parameters['request_method'] = 'post';
            break;
        case 'DELETE':
            $parameters['request_method'] = 'delete';
            break;
        default:
            break;
    }
    $parameters = http_build_query($parameters);
    if ($parameters) {
        $url .= '/?' . $parameters;
    }

    // Say($url);

    $options[CURLOPT_URL] = $url;
    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    list($headers, $body) = explode("\r\n\r\n", $response, 2);
    $body = json_decode($body, true);

    if (isset($body->error)) {
        $error = $body->error;

        // These properties only exist on API calls, not auth calls
        if (isset($error->message) && isset($error->code) && isset($error->type)) {
            $exception = 'RequestException\\' . $error->type;
            g::run("tools.Say", g::run("tools.JE", $exception($error->message, $error->code)), 5);
        } elseif (isset($error->message) && isset($error->code)) {
            g::run("tools.Say", g::run("tools.JE", array($body->message, $error->code)), 5);
        } elseif (isset($error->message)) {
            g::run("tools.Say", $error->message, 5);
        }
        die;
        return false;
    } else if (!empty($body["error"])) {
        g::run("tools.Say", g::run("tools.JE", $body), 5);
        return false;
    }

    return array(
        'body' => $body,
        'headers' => $headers,
        'status' => $status,
    );
}

function GetDeezerAlbums($user_id)
{
    $dz = g::get("config.deezer");

    $type = "GET";
    $url = $dz["api_url"] . '/user/' . $user_id . "/albums";
    $headers = array();
    $parameters = array();

    set_time_limit(0);
    $resp = SendDeezerRequest($type, $url, $parameters, $headers);
    $data = $resp["body"]["data"];
    $total = $resp["body"]["total"];
    $next = (!empty($resp["body"]["next"])) ? $resp["body"]["next"] : "";

    //$dataset = ParseDeezerListPaging($data, $total, $next, 50);
    //($dataset);die;

    $start = 0;
    $limit = 100;
    $end = $start + $limit;
    $dz_cache_albums = array();
    $user_name = g::get("op.data.deezer.user.name");
    // $total = 2;
    while ($end <= $total) {
        $dataset = ParseDeezerListPaging($data, $total, $next, $limit);
        $data_json = g::run("tools.JE", $dataset);
        $file_path = g::get("config.paths.clone_data_folder") . "dz" . V . $user_name . "_deezer_albums_$start-$end.json";

        $dz_cache_albums["$start-$end"] = $file_path;

        g::run("tools.WriteFile", $data_json, $file_path);

        $s = $start;
        $l = $limit;
        $start = $end;
        $end = $start + $limit;
        if ($end > $total) {
            $end = $total;
            $limit = $total - $start;
        }
        if ($start == $total) {
            $end++;
        } else {
            if ($start > 0) {
                $na = explode("=", $next);
                $url = $na[0] . "=" . $start;
                $resp = SendDeezerRequest($type, $url, $parameters, $headers);
                $data = $resp["body"]["data"];
                $total = $resp["body"]["total"];
                $next = (!empty($resp["body"]["next"])) ? $resp["body"]["next"] : "";
            }
        }

        g::run("tools.Say", "Saved albums to file. Started from $s, and got $l.", 5);
    }

    // Write not-avaliable with alternatives albums
    $alts = g::get("void.albums.alternative");
    if (!empty($alts)) {
        $file_path = g::get("config.paths.clone_data_folder") . "dz" . V . $user_name . "_deezer_albums_alts.json";
        $dz_cache_albums["alts"] = $file_path;
        $alts_json = g::run("tools.JE", $alts);
        g::run("tools.WriteFile", $alts_json, $file_path);
    }
    // Write not-avaliable with NO alternatives albums
    $nas = g::get("void.albums.not_available");
    if (!empty($nas)) {
        $file_path = g::get("config.paths.clone_data_folder") . "dz" . V . $user_name . "_deezer_albums_nas.json";
        $dz_cache_albums["nas"] = $file_path;
        $nas_json = g::run("tools.JE", $nas);
        g::run("tools.WriteFile", $nas_json, $file_path);
    }

    set_time_limit(30);
    $dz_cache = array(
        "date" => g::run("tools.DTS", 5),
        "list" => $dz_cache_albums,
    );
    g::run("tools.UpdateConfigFiles", "deezer.cache.albums", $dz_cache, true);
    g::run("tools.Redirect", "deezer");
}

function GetDeezerPlaylists($user_id)
{
    $dz = g::get("config.deezer");

    $type = "GET";
    $url = $dz["api_url"] . '/user/' . $user_id . "/playlists";
    $headers = array();
    $parameters = array();

    set_time_limit(0);
    $resp = SendDeezerRequest($type, $url, $parameters, $headers);
    $data = $resp["body"]["data"];
    $total = $resp["body"]["total"];
    $next = (!empty($resp["body"]["next"])) ? $resp["body"]["next"] : "";
    // print_r($resp);die;
    $start = 0;
    $limit = 100;
    $end = $start + $limit;
    $dz_cache_playlists = array();
    $user_name = g::get("op.data.deezer.user.name");

    while ($end <= $total) {
        $dataset = ParseDeezerListPaging($data, $total, $next, $limit);
        $data_json = g::run("tools.JE", $dataset);
        $file_path = g::get("config.paths.clone_data_folder") . "dz" . V . $user_name . "_deezer_playlists_$start-$end.json";

        $dz_cache_playlists["$start-$end"] = $file_path;

        g::run("tools.WriteFile", $data_json, $file_path);

        $s = $start;
        $l = $limit;
        $start = $end;
        $end = $start + $limit;
        if ($end > $total) {
            $end = $total;
            $limit = $total - $start;
        }
        if ($start == $total) {
            $end++;
        } else {
            if ($start > 0) {
                $na = explode("=", $next);
                $url = $na[0] . "=" . $start;
                $resp = SendDeezerRequest($type, $url, $parameters, $headers);
                $data = $resp["body"]["data"];
                $total = $resp["body"]["total"];
                $next = (!empty($resp["body"]["next"])) ? $resp["body"]["next"] : "";
            }
        }

        g::run("tools.Say", "Saved playlists to file. Started from $s, and got $l.", 5);
    }
    set_time_limit(30);
    $dz_cache = array(
        "date" => g::run("tools.DTS", 5),
        "list" => $dz_cache_playlists,
    );
    g::run("tools.UpdateConfigFiles", "deezer.cache.playlists", $dz_cache, true);
    g::run("tools.Redirect", "deezer");
}

function ParseDeezerListPaging($data, $total, $next, $limit)
{
    if ($next) {
        $data = DeezerRequestNextListPage($data, $next, $limit);
    } else {
        if ($limit < 25) {
            $data = GetDeezerTracks($data);
        }
    }

    $dataset = array(
        "data" => $data,
        "total" => $total,
    );

    return $dataset;
}

function DeezerRequestNextListPage($data, $url, $limit = 0, $got = 25)
{
    if ($got == 25) {
        $data = GetDeezerTracks($data);
    }
    if ($limit != 25) {
        $type = "GET";
        // usleep(500000);
        $resp = SendDeezerRequest($type, $url);
        $data_next = $resp["body"]["data"];
        $data_next = GetDeezerTracks($data_next);
        $data = array_merge($data, $data_next);
        $url_next = (!empty($resp["body"]["next"])) ? $resp["body"]["next"] : "";
        $got += 25;

        if (!empty($url_next) && $got < ($limit - 24)) {
            $data = DeezerRequestNextListPage($data, $url_next, $limit, $got);
        }
    }
    return $data;
}

function GetDeezerTracks($data)
{
    $session = g::get("session");

    $dc = count($data);
    $tracklist_url = "";

    for ($i = 0; $i < $dc; $i++) {
        usleep(200000);
        $stop = false;

        if (!empty($data[$i]["record_type"]) && $data[$i]["record_type"] != "playlist") {
            if ($data[$i]["available"] != 1 && !empty($data[$i]["alternative"])) {
                // $data[$i] = $data[$i]["alternative"];
                $vaa = g::get("void.albums.alternative");
                $alt = (!empty($vaa)) ? g::get("void.albums.alternative") : array();
                $alt[] = $data[$i];
                g::set("void.albums.alternative", $alt);
                unset($data[$i]);
                continue;
            } else if ($data[$i]["available"] != 1 && empty($data[$i]["alternative"])) {
                $vana = g::get("void.albums.not_available");
                $na = (!empty($vana)) ? g::get("void.albums.not_available") : array();
                $na[] = $data[$i];
                g::set("void.albums.not_available", $na);
                unset($data[$i]);
                continue;
            }
        }

        $tracklist_url = $data[$i]["tracklist"];

        $type = "GET";
        $headers = array();
        $parameters = array(
            // "access_token" => $session["dz_at"],
        );
        $track_resp = SendDeezerRequest($type, $tracklist_url, $parameters, $headers);
        if ($stop) {
            print_r($track_resp);
            die;
        }
        $track_data = (!empty($track_resp["body"]["data"])) ? $track_resp["body"]["data"] : array();
        $track_total = (!empty($track_resp["body"]["total"])) ? $track_resp["body"]["total"] : 0;
        $track_next = (!empty($track_resp["body"]["next"])) ? $track_resp["body"]["next"] : "";

        $track_data = ParseDeezerTrackPaging($track_data, $track_total, $track_next);
        $track_data = ParseDeezerTracks($track_data);
        $data[$i]["tracks"] = $track_data;
        if (!empty($data[$i]["artist"])) {
            $artist_name = $data[$i]["artist"]["name"];
            $artist_id = $data[$i]["artist"]["id"];
        }
        if (!empty($data[$i]["creator"])) {
            $artist_name = $data[$i]["creator"]["name"];
            $artist_id = $data[$i]["creator"]["id"];
            unset($data[$i]["creator"]);
        }
        $data[$i]["artist"] = $artist_name;
        $data[$i]["artist_id"] = $artist_id;

        unset($data[$i]["cover"]);
        unset($data[$i]["cover_small"]);
        unset($data[$i]["cover_medium"]);
        unset($data[$i]["cover_big"]);
        unset($data[$i]["picture"]);
        unset($data[$i]["picture_small"]);
        unset($data[$i]["picture_medium"]);
        unset($data[$i]["picture_big"]);
        unset($data[$i]["checksum"]);
        unset($data[$i]["explicit_lyrics"]);
        unset($data[$i]["md5_image"]);
        unset($data[$i]["share"]);
    }

    return $data;
}

function ParseDeezerTracks($data)
{
    $track_extract = array();
    $dc = count($data);

    for ($i = 0; $i < $dc; $i++) {
        $track_this = $data[$i];
        $artist = $title = $link = $tmp_track = "";

        if (!empty($track_this["title"])) {
            $title = $track_this["title"];
        }

        if (!empty($track_this["artist"])) {
            $artist = $track_this["artist"]["name"];
        }

        if (!empty($track_this["link"])) {
            $link = $track_this["id"];
        }

        if (empty($track_this["title"]) || empty($track_this["artist"]) || empty($track_this["link"])) {
            error_log($track_this);
        }

        $tmp_track = "$artist;$title;$link";
        // $tmp_track = array($artist,$title,$link);
        $track_extract[] = $tmp_track;
    }
    return $track_extract;
}

function ParseDeezerTrackPaging($data, $total, $next)
{
    if ($next) {
        $data = RequestNextDeezerTrackPage($data, $next);
    }

    return $data;
}

function RequestNextDeezerTrackPage($data, $url, $got = 25)
{
    $type = "GET";
    $resp = SendDeezerRequest($type, $url);

    $data_next = $resp["body"]["data"];
    $got += 25;

    $data = array_merge($data, $data_next);
    $url_next = (!empty($resp["body"]["next"])) ? $resp["body"]["next"] : "";

    if (!empty($url_next)) {
        $data = RequestNextDeezerTrackPage($data, $url_next, $got);
    }
    return $data;
}

function GetDeezerPlaylist($dz_id)
{
    $dz = g::get("config.deezer");

    $type = "GET";
    $url = $dz["api_url"] . '/playlist/' . $dz_id;
    $headers = array();
    $parameters = array();
    $resp = SendDeezerRequest($type, $url, $parameters, $headers);

    $data = $resp["body"];
    $tracks = ParseDeezerTracks($data["tracks"]["data"]);

    if (!empty($data["artist"])) {
        $artist_name = $data["artist"]["name"];
        $artist_id = $data["artist"]["id"];
    }
    if (!empty($data["creator"])) {
        $artist_name = $data["creator"]["name"];
        $artist_id = $data["creator"]["id"];
        unset($data["creator"]);
    }
    $data["artist"] = $artist_name;
    $data["artist_id"] = $artist_id;
    $data["tracks"] = $tracks;

    $data["cover"] = $data["picture_xl"];
    $data["dz_id"] = $data["id"];
    $data["date_created"] = $data["creation_date"];
    unset($data["picture_xl"]);
    unset($data["id"]);
    unset($data["creation_date"]);

    unset($data["cover_small"]);
    unset($data["cover_medium"]);
    unset($data["cover_big"]);
    unset($data["picture"]);
    unset($data["picture_small"]);
    unset($data["picture_medium"]);
    unset($data["picture_big"]);
    unset($data["checksum"]);
    unset($data["explicit_lyrics"]);
    unset($data["md5_image"]);
    unset($data["share"]);

    g::set("op.data.cdzd", $data);
    g::run("tools.Say", "Got latest playlist data from from Deezer: " . $data["title"], 5);
}

function GetDeezerAlbum($dz_id)
{
    $dz = g::get("config.deezer");

    $type = "GET";
    $url = $dz["api_url"] . '/album/' . $dz_id;
    $headers = array();
    $parameters = array();
    $resp = SendDeezerRequest($type, $url, $parameters, $headers);

    $data = $resp["body"];
    $tracks = ParseDeezerTracks($data["tracks"]["data"]);

    if (!empty($data["artist"])) {
        $artist_name = $data["artist"]["name"];
        $artist_id = $data["artist"]["id"];
    }
    if (!empty($data["creator"])) {
        $artist_name = $data["creator"]["name"];
        $artist_id = $data["creator"]["id"];
        unset($data["creator"]);
    }
    $data["artist"] = $artist_name;
    $data["artist_id"] = $artist_id;
    $data["tracks"] = $tracks;

    $data["cover"] = $data["cover_xl"];
    $data["dz_id"] = $data["id"];
    $data["date_created"] = $data["release_date"];
    unset($data["cover_xl"]);
    unset($data["id"]);
    unset($data["release_date"]);

    unset($data["cover_small"]);
    unset($data["cover_medium"]);
    unset($data["cover_big"]);
    unset($data["picture"]);
    unset($data["picture_small"]);
    unset($data["picture_medium"]);
    unset($data["picture_big"]);
    unset($data["checksum"]);
    unset($data["explicit_lyrics"]);
    unset($data["explicit_content_lyrics"]);
    unset($data["explicit_content_cover"]);
    unset($data["md5_image"]);
    unset($data["share"]);
    unset($data["upc"]);
    unset($data["genres"]);
    unset($data["label"]);
    unset($data["genre_id"]);
    unset($data["rating"]);
    unset($data["contributors"]);

    g::set("op.data.cdzd", $data);
    g::run("tools.Say", "Got latest album data from from Deezer: " . $data["title"], 5);
}

function DeezerUpdateAlbum($args, $get, $post)
{
    // $args
    $g_state = $args["state"];
    $id = $args["id"];
    $sql = array("update", "items", array("g_state" => $g_state), "id=$id");
    $resp = g::run("db.Prepare", array($sql));
    if (empty($resp["error"])) {
        echo "ok";
    } else {
        echo "no";
    }
}

function DeezerUpdatePlaylist($args, $get, $post)
{
    // $args
    $g_state = $args["state"];
    $id = $args["id"];
    $sql = array("update", "items", array("g_state" => $g_state), "id=$id");
    $resp = g::run("db.Prepare", array($sql));
    if (empty($resp["error"])) {
        echo "ok";
    } else {
        echo "no";
    }
}

function DeezerSyncAlbums()
{
    $dz = g::get("config.deezer");
    $dz_cache_list = $dz["cache"]["albums"]["list"];
    $sql_rows = array();
    foreach ($dz_cache_list as $key => $file) {
        $dataset = g::run("tools.ReadFileJD", $file);
        if (!empty($dataset["data"])) {
            foreach ($dataset["data"] as $row) {
                unset($row["md5_image"]);
                $row["cover"] = $row["cover_xl"];
                $row["dz_id"] = $row["id"];
                $row["date_created"] = $row["release_date"];
                unset($row["cover_xl"]);
                unset($row["id"]);
                unset($row["release_date"]);

                $row["date_added"] = g::run("tools.DTS", 5, $row["time_add"]);

                $name = $row["artist"] . " - " . $row["title"];
                // $name = $row["title"];
                $alias = g::run("tools.SafeUrl", $name);

                $exists = g::run("db.Get", array("id, g_hash", "items", "g_bits->>'$.dz_id'=" . $row["dz_id"]));
                $c = $exists["count"];
                if ($c > 0) {
                    //-- if exists
                    //--- update
                    // name
                    // alias
                    // link
                    // tsc
                    // g_bits
                    $id = $exists["list"][0]["id"];
                    $hash = $exists["list"][0]["g_hash"];
                    // $tss = g::run("tools.DTS", 5);

                    $row_data = array(
                        "g_link" => $row["link"],
                        "g_alias" => "$alias",
                        "g_name" => "$name",
                        "g_bits" => g::run("tools.JE", $row),
                        "tss" => $row["date_added"],
                        "tsc" => $row["date_created"],
                    );

                    $event_data = array(
                        "g_type" => "item_update",
                        "g_hash" => "$hash",
                        "g_key" => $row["type"],
                        "g_value" => $name,
                    );

                    $sql_rows[] = array("update", "items", $row_data, "id='$id'");
                    $sql_rows[] = array("insert", "events", $event_data);
                } else {
                    //-- if not
                    //--- insert
                    // state
                    // type
                    // hash?
                    // name
                    // alias
                    // link
                    // tsc
                    // g_bits
                    $hash = g::run("tools.DTS", 7);
                    // $tss = g::run("tools.DTS", 5);
                    $row_data = array(
                        "g_state" => "draft",
                        "g_type" => $row["type"],
                        "g_link" => $row["link"],
                        "g_hash" => "$hash",
                        "g_alias" => "$alias",
                        "g_name" => "$name",
                        "g_bits" => g::run("tools.JE", $row),
                        "tss" => $row["date_added"],
                        "tsc" => $row["date_created"],
                    );

                    $event_data = array(
                        "g_type" => "item_create",
                        "g_hash" => $hash,
                        "g_key" => $row["type"],
                        "g_value" => $name,
                    );

                    $sql_rows[] = array("insert", "items", $row_data);
                    $sql_rows[] = array("insert", "events", $event_data);
                }
                //print_r($sql_rows);
                //g::run("db.Prepare", $sql_rows);
                //die;
            }
            //print_r($sql_rows);
            //g::run("db.Prepare", $sql_rows);
            //die;
        }
    }
    // print_r($sql_rows);
    g::run("db.Prepare", $sql_rows);
    $sync_date = g::run("tools.DTS", 5);
    g::run("tools.UpdateConfigFiles", "deezer.cache.albums.sync", $sync_date, true);
    g::run("tools.Say", "Updated all albums on DB with latest json backups from Deezer.", 5);
}

function DeezerSyncPlaylists()
{
    $dz = g::get("config.deezer");
    $dz_cache_list = $dz["cache"]["playlists"]["list"];
    $sql_rows = array();
    foreach ($dz_cache_list as $key => $file) {
        $dataset = g::run("tools.ReadFileJD", $file);
        foreach ($dataset["data"] as $row) {
            unset($row["md5_image"]);
            $row["cover"] = $row["picture_xl"];
            $row["dz_id"] = $row["id"];
            $row["date_created"] = $row["creation_date"];
            unset($row["picture_xl"]);
            unset($row["id"]);
            unset($row["creation_date"]);

            $row["date_added"] = g::run("tools.DTS", 5, $row["time_add"]);
            $row["date_updated"] = g::run("tools.DTS", 5, $row["time_mod"]);

            $name = $row["title"];
            $alias = g::run("tools.SafeUrl", $name);

            $exists = g::run("db.Get", array("id, g_hash", "items", "g_bits->>'$.dz_id'=" . $row["dz_id"]));
            $c = $exists["count"];
            if ($c > 0) {
                //-- if exists
                //--- update
                // name
                // alias
                // link
                // tsc
                // g_bits
                $id = $exists["list"][0]["id"];
                $hash = $exists["list"][0]["g_hash"];
                // $tss = g::run("tools.DTS", 5);

                $row_data = array(
                    "g_link" => $row["link"],
                    "g_alias" => "$alias",
                    "g_name" => "$name",
                    "g_bits" => g::run("tools.JE", $row),
                    "tss" => $row["date_added"],
                    "tsc" => $row["date_created"],
                    "tsu" => $row["date_updated"],
                );

                $event_data = array(
                    "g_type" => "item_update",
                    "g_hash" => "$hash",
                    "g_key" => $row["type"],
                    "g_value" => $name,
                );

                $sql_rows[] = array("update", "items", $row_data, "id='$id'");
                $sql_rows[] = array("insert", "events", $event_data);
            } else {
                //-- if not
                //--- insert
                // state
                // type
                // hash?
                // name
                // alias
                // link
                // tsc
                // g_bits
                $hash = g::run("tools.DTS", 7);
                // $tss = g::run("tools.DTS", 5);

                $row_data = array(
                    "g_state" => "draft",
                    "g_type" => $row["type"],
                    "g_link" => $row["link"],
                    "g_hash" => "$hash",
                    "g_alias" => "$alias",
                    "g_name" => "$name",
                    "g_bits" => g::run("tools.JE", $row),
                    "tss" => $row["date_added"],
                    "tsc" => $row["date_created"],
                    "tsu" => $row["date_updated"],
                );

                $event_data = array(
                    "g_type" => "item_create",
                    "g_hash" => $hash,
                    "g_key" => $row["type"],
                    "g_value" => $name,
                );

                $sql_rows[] = array("insert", "items", $row_data);
                $sql_rows[] = array("insert", "events", $event_data);
            }
            //print_r($sql_rows);
            //g::run("db.Prepare", $sql_rows);
            //die;
        }
        //print_r($sql_rows);
        //g::run("db.Prepare", $sql_rows);
        //die;
    }
    // print_r($sql_rows);
    g::run("db.Prepare", $sql_rows);
    $sync_date = g::run("tools.DTS", 5);
    g::run("tools.UpdateConfigFiles", "deezer.cache.playlists.sync", $sync_date, true);
    g::run("tools.Say", "Updated all playlists on DB with latest json backups from Deezer.", 5);
}
