<?php
g::def("core", array(
    "Init" => function () {
        g::run("core.PrepConfig");
        g::run("core.SetEnvironment");
        g::run("core.FigureState");
        g::run("core.IncludeMods");
        g::run("core.ProcessRules");
        if (g::run("core.CheckPermissions")) {
            g::run("db.ConnectIfAvailable");
        }
    },
    "Render" => function () {
        if (g::get("op.meta.user.not_allowed") !== true) {
            g::run("core.TriggerFunctions");
        }

        g::run("core.RenderOutput");

        $t = g::run("tools.Now");
        $p = g::run("tools.Performance");
        g::run("tools.Log", "$t|$p");
    },
    "PrepConfig" => function () {
        // THE REST
        // This will be turned into a base.json config file.
        $config = array( // Config data :: (optional) conf.json...
            "paths" => array(
                "genes_api_folder" => GENES_API_FOLDER,
                "genes_core_folder" => GENES_CORE_FOLDER,
                "genes_mods_folder" => GENES_MODS_FOLDER,
                "genes_ui_folder" => GENES_UI_FOLDER,
                "genes_ui_tmpls_folder" => GENES_UI_TMPLS_FOLDER,
                "genes_ui_html" => GENES_UI_HTML,
                "clone_folder" => CLONE_FOLDER,
                "clone_cache_folder" => CLONE_CACHE_FOLDER,
                "clone_ui_folder" => CLONE_UI_FOLDER,
                "clone_ui_html" => CLONE_UI_HTML,
                "clone_data_folder" => CLONE_DATA_FOLDER,
                "clone_log_file" => CLONE_LOG_FILE,
                "clone_config_file" => CLONE_CONFIG_FILE,
                "clone_views_file" => CLONE_VIEWS_FILE,
                "clone_mods_file" => CLONE_MODS_FILE,
                "clone_tmpls_file" => CLONE_TMPLS_FILE,
                "clone_bits_file" => CLONE_BITS_FILE,
                "clone_base_file" => CLONE_BASE_FILE,
            ),
            "urls" => array(
                "genes_ui" => GENES_UI_URL,
                "clone_ui" => CLONE_UI_URL,
            ),
            "settings" => array(
                "allow_setup" => 0, // after setup is completed disable the setup path
                "reset_pwd" => 0, // any time if you want to reset to hardcoded admin password
                "allow_cors" => 0, // cross domain queries needed?
                "render_html_server" => 1, // do you want to render html server-side?
                "render_html_js" => 0, // do you want to render html client-side?
                "api_serves_data" => 0, // do you want rendered bits and labels through json api
                "api_serves_tmpl" => 0, // do you want not rendered template html through json api
                "api_serves_html" => 0, // do you want rendered html through json api
                "routing_server" => 1, // do you want routing to be server-side?
                "routing_js" => 0, // do you want routing to be client-side?
                "load_paths_js" => 0, // do you want load paths via ajax/xhr?
                "msg_level" => 4, // outputting msgs importance, if higher or equal will be said.
                "log_level" => 1, // logging msgs importance, if higher or equal will be logged.
                "output_types" => array("json" => true), // output types (.extensions) allowed
                "cache_renders" => 0, // cache rendered outputs
                "compress_renders" => 0, // compress rendered outputs
                "cache_compress_assets" => 0, // cache and compress used assets like css and js files
                "langs" => array("en"), // first option is the default language file chosen for the clone, can be anything but options can be found here: https://en.wikipedia.org/wiki/List_of_tz_database_time_zones
                "user_types" => array("guest", "user", "admin"), // user types
                "user_states" => array("active", "inactive"), // user states
                "timezone" => "Europe/Tallinn", // time zone of the clone, options found here: https://en.wikipedia.org/wiki/List_of_tz_database_time_zones
                "date_time_format" => "Y-m-d H:i:s.u", // time format including milliseconds
            ),
            "clone" => array(
                "salt" => "", // gHash_generateRandomKey(), salting communications
                "secret_salt" => "", // gHash_generateRandomKey(), salting secret
                "user_salt" => "", // gHash_generateRandomKey(), salting password
                "hash" => "", // used as hash_clone, in db records when clone creates data
                "alias" => "", // gHash_generateRandomKey(16, false), key and hash separates this clone from others.
                "name" => "", // gHash_generateRandomKey(16, false), key and hash separates this clone from others.
                "contact" => "", //
                "secret" => "", // salted secret key, like a password for this clone
            ),
            "admin" => array(
                "hash" => "", // used as hash_user, in db records when clone creates data
                "alias" => "admin", // you can change, but pass needs to be regenerated
                "name" => "Genes Admin", // you can change does not matter.
                "email" => "", //
                "open_pass" => "", // genes admin password, when written not encoded and reset_pwd is true, salted, encoded and set again.
                "pass" => "", // genes admin password, when written not encoded and reset_pwd is true, salted, encoded and set again.
            ),
            "users" => array(),
            "db" => array(
                "conns" => array(
                    "default" => array(
                        "type" => "mysql", // mysql, sqlite, dbaas or else..
                        "path" => "", // if sqlite enter direct path
                        "name" => "",
                        "user" => "",
                        "pass" => "",
                        "charset" => "utf8",
                    ),
                ),
                "tables" => array(
                    "clones" => array("default", "g_clones"),
                    "labels" => array("default", "g_labels"),
                    "users" => array("default", "g_users"),
                    "items" => array("default", "g_items"),
                    "events" => array("default", "g_events"),
                ),
            ),
            "views" => array( // Views data :: (optional) view.json...
                // first come first serve..
                // genes queries, custom paths leading to function or file name
                "Index" => array(
                    "urls" => array("en" => "index"),
                    "bits" => array("title" => array("en" => "Genes Clone Index")),
                ),
            ),
            "mods" => array(
                "user" => array(),
                "admin" => array(),
            ), // Mods give clones extra powers without messing their structure...
            "tmpls" => array(), // Template configuration :: (optional) tmpl.json...
            "bits" => array( // Data with i18n :: (optional) data.json...
                // genes labels, things that i18nized but not changed according to page
                "clone_shortname" => array("en" => "A Genes Clone"), // decide a unique shortname for the clone
                "lang_en" => array("en" => "English"),
            ),
            "base" => array(), // Base is necessary if you are using a flat-file db...
            "rules" => array(
                "no" => array(),
            ), // Rules are for permissions...
            "checks" => array(), // Checks will be used so there are no unnecessary queries made to db or other heavy functions...
            "env" => array(), // If the clone runs at any other environment, is clone url_key exists here use its config.
        );
        g::set("config", $config);
        g::set("op", array( // Output data
            "meta" => array(),
            "data" => array(),
            "tmpl" => "",
            "html" => "",
        ));
        g::set("msgs", array());
        g::set("void", array(
            "time" => microtime(true),
            "mem" => memory_get_usage(),
        ));
    },
    "SetEnvironment" => function () {
        error_reporting(E_ALL);
        setlocale(LC_CTYPE, 'en_US.utf8');
        g::run("core.SessionStart");
        g::run("core.GetServerUrl");
        g::run("core.CreateSetConfigFilesFolders");
        date_default_timezone_set(g::get("config.settings.timezone"));
    },
    "FigureState" => function () {
        g::set("op.meta.url.base", g::get("clone.url"));
        g::set("post", g::run("tools.CleanData", $_POST));
        g::set("files", g::run("tools.CleanData", $_FILES));

        $clone_hash = g::get("config.clone.hash");
        if (empty($_SESSION[$clone_hash])) {$_SESSION[$clone_hash] = array();}
        g::set("session", g::run("tools.CleanData", $_SESSION[$clone_hash]));
        if (!empty($_COOKIE["genes_$clone_hash"])) {g::set("cookie", g::run("tools.CleanData", g::run("tools.JD", $_COOKIE["genes_$clone_hash"])));}

        g::run("core.SessionGetSet");
        g::run("core.ParseUrlQuery");

        g::set("op.meta.user", g::run("core.CheckLogin"));
    },
    "IncludeMods" => function () {
        $mods = g::get("config.mods");
        $mod_folder = g::get("config.paths.genes_mods_folder");
        $any_mod = false;
        // include mod files
        foreach ($mods as $mod_name => $mod_config) {
            if ($mod_config !== false) {
                require $mod_folder . "genes.$mod_name.php";
                $areKeysSet = g::get("config.checks." . $mod_name . "_keys_set");
                if ($areKeysSet != 1) {
                    $v = g::get("void.$mod_name.views");
                    $b = g::get("void.$mod_name.bits");
                    $t = g::get("void.$mod_name.tmpls");
                    $o = g::get("void.$mod_name.opts");
                    $r = g::get("void.$mod_name.rules");
                    g::run("core.WriteModViewsLabelsTmplsOptsRules", $mod_name, $v, $b, $t, $o, $r);
                    g::set("config.checks." . $mod_name . "_keys_set", 1);
                    $any_mod = true;
                }
                g::set("op.meta.mods.$mod_name", 1);
            }
        }
        if ($any_mod) {
            $config_mod_update = g::get("config.mods");
            $config_rules_update = g::get("config.rules");
            $config_checks = g::get("config.checks");

            $cuki = g::get("clone.uki");
            $config_paths = g::get("config.env.$cuki.paths");
            $config_path = $config_paths["clone_config_file"];
            $config_data = g::run("tools.ReadFileJD", $config_path);

            $config_data["paths"] = $config_paths;
            $config_data["mods"] = $config_mod_update;
            $config_data["rules"] = $config_rules_update;
            $config_data["checks"] = $config_checks;

            $config_data["paths"]["clone_views_file"] = "";
            $config_data["paths"]["clone_tmpls_file"] = "";
            $config_data["paths"]["clone_bits_file"] = "";
            $config_data["paths"]["clone_base_file"] = "";
            g::run("tools.UpdateConfigComplete", $config_data, false);
        }
    },
    "RulesArrayDepth" => function ($rules, $lang, $user_type) {
        $user_types = g::get("config.settings.user_types");
        $langs = g::get("config.settings.langs");

        if (is_array($rules)) {
            foreach ($rules as $key => $val) {
                $any_1 = (!empty($val["any"]["any"])) ? $val["any"]["any"] : array();
                $any_2 = (!empty($val[$lang]["any"])) ? $val[$lang]["any"] : array();
                $any_3 = (!empty($val[$user_type]["any"])) ? $val[$user_type]["any"] : array();
                $any_4 = (!empty($val["any"])) ? $val["any"] : array();

                $lang_1 = (!empty($val[$lang])) ? $val[$lang] : array();
                $lang_2 = (!empty($val[$lang][$user_type])) ? $val[$lang][$user_type] : array();
                $user_type_1 = (!empty($val[$user_type])) ? $val[$user_type] : array();
                $user_type_2 = (!empty($val[$user_type][$lang])) ? $val[$user_type][$lang] : array();
                $rules[$key] = array_merge($any_1, $any_2, $any_3, $any_4, $lang_1, $lang_2, $user_type_1, $user_type_2);
            }
        }
        return $rules;
    },
    "ProcessRules" => function () {
        g::run("core.DecideViewLang");
        $op_meta = g::get("op.meta");
        $op_meta_url = $op_meta["url"];
        $lang = $op_meta_url["lang"];
        $view = $op_meta_url["view"];

        $op_meta_user = $op_meta["user"];
        $user_type = $op_meta_user["type"];

        $rules = g::get("config.rules");
        $rules = g::run("core.RulesArrayDepth", $rules, $lang, $user_type);
        g::set("op.meta.rules", $rules);
    },
    "CheckPermissions" => function () {
        $meta = g::get("op.meta");
        $rules = $meta["rules"];
        $url_base = $meta["url"]["base"];
        $user_type = $meta["user"]["type"];
        $url_view = $meta["url"]["view"];
        $url_bare = $meta["url"]["bare"];
        $url_lang = $meta["url"]["lang"];
        $no = (!empty($rules["no"])) ? $rules["no"] : array();
        if (
            in_array($url_base, $no) ||
            in_array($url_bare, $no) ||
            in_array($url_view, $no)
        ) {
            return g::run("core.NotAllowed");
        } else {
            return true;
        }
    },
    "CheckLogin" => function () {
        $user = array("type" => "guest");
        $su = g::get("op.meta.user");
        if (!empty($su)) {
            $user = $su;
        }
        $user["login_ip"] = g::run("core.GetUserIP");
        return $user;
    },
    "GetUserIP" => function () {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if ($ip === "::1") {
            $ip = "127.0.0.1";
        }
        return $ip;
    },
    "NotAllowed" => function () {
        g::set("op.meta.user.not_allowed", true);
        $murl = g::get("op.meta.url");
        $na = $murl["base"] . $murl["bare"];
        g::run("core.SessionSet", "redir", $na);
        g::run("core.DecideRedirection");
        return false;
    },
    "DecideRedirection" => function ($mode = false) {
        $una = g::get("op.meta.user.not_allowed");
        if ($mode) {
            // user is logged in
            $redirect_to = "index";
            $url_after_login = g::get("config.mods.user.opts.url_after_login");
            if (!empty($url_after_login)) {
                $redirect_to = $url_after_login;
            }
            g::run("tools.Say", "user-logged-in");
        } else {
            // user is not allowed / logged out
            $redirect_to = "login";
            $url_after_logout = g::get("config.mods.user.opts.url_after_logout");
            if (!empty($url_after_logout)) {
                $redirect_to = $url_after_logout;
            }
            g::run("tools.Say", "user-not-logged-in", 5);
        }

        $murl = g::get("op.meta.url");
        $na = $murl["base"] . $murl["bare"];
        $redirecter = g::run("core.SessionGet", "redir");
        if ($mode && !empty($redirecter) && $redirecter != $na) {
            $redirect_to = $redirecter;
        }
        g::run("tools.Redirect", $redirect_to);
        if (!empty($redirecter) && $redirecter !== $redirect_to) {
            g::run("core.SessionSet", "redir", $redirecter);
        } else {
            g::run("core.SessionSet", "redir", null);
        }
    },
    "TriggerFunctions" => function () {
        $found_view = g::get("op.meta.url.view");
        if ($found_view && is_callable(g::ret("clone.$found_view"))) {
            g::run("clone.$found_view");
        } elseif ($found_view && is_array(g::get("config.mods"))) {
            $mods = g::get("config.mods");
            foreach ($mods as $mod_name => $mod_config) {
                if (is_callable(g::ret("mods.$mod_name.$found_view"))) {
                    g::run("mods.$mod_name.$found_view");
                    break;
                }
            }
        } else {
            $found_view = "Query";
            if ($found_view && is_callable(g::ret("clone.$found_view"))) {
                g::run("clone.$found_view");
            } else {
                g::run("core.Query");
            }
        }

        g::set("op.meta.url.call", $found_view);
    },
    "RenderOutput" => function () {
        g::run("core.EmbedBits");
        g::set("op.meta.config", g::run("core.CreateMetaConfigClasses"));

        $op = g::get("op");
        $op_type = $op["meta"]["url"]["output"];

        $op_redirect = (!empty($op["meta"]["redirect"])) ? $op["meta"]["redirect"] : "";
        if (!empty($op_redirect)) {
            if (!empty($op["meta"]["msgs"])) {g::run("core.SessionSet", "op.meta.msgs", $op["meta"]["msgs"]);}
            if ($op_type !== "json") {
                g::run("tools.RedirectNow", $op_redirect);
            }
        } else {
            g::run("core.SessionSet", "op.meta.msgs", "");
            //$ut = g::get("op.meta.user.type");
            //if ($ut === "guest") {
            // g::run("core.SessionEnd");
            //}
        }

        $api_serves_tmpl = g::get("config.settings.api_serves_tmpl");
        $api_serves_html = g::get("config.settings.api_serves_html");
        $api_serves_data = g::get("config.settings.api_serves_data");

        //ob_start("g_compress");
        ob_start();
        //* HEADERS *******************************************************************/
        //header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        //header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        //header("Cache-Control: no-store, no-cache, must-revalidate");
        //header("Cache-Control: post-check=0, pre-check=0", false);
        //header("Pragma: no-cache");
        if ($op_type === "json") {
            header('Content-type:application/json;charset=utf-8');
            if (!$api_serves_html) {
                unset($op["html"]);
            } else {
                $op["html"] = g::run("ui.ProcessTags", $op["tmpl"]);
            }
            if (!$api_serves_tmpl) {
                unset($op["tmpl"]);
            }
            if (!$api_serves_data) {
                unset($op["bits"]);
                unset($op["data"]);
            }
            echo json_encode($op);
        } elseif ($op_type === "txt") {
            header("Content-Type: text/plain;charset=utf-8");
            echo $op["txt"];
        } else {
            header("Content-Type: text/html;charset=utf-8");
            echo g::run("ui.ProcessTags", $op["tmpl"]);
        }
        $op = ob_get_clean();
        echo $op;
    },
    "CreateMetaConfigClasses" => function () {
        $classes = "";
        $config = g::get("config.settings");
        /*
        "render_html_server": 1,
        "render_html_js": 1,
        "api_serves_data": 0,
        "api_serves_tmpl": 0,
        "api_serves_html": 1,
        "routing_server": 1,
        "routing_js": 1,
        "load_paths_js": 1,
         */
        if ($config["render_html_server"]) {
            $classes .= " data-rhs";
        }
        if ($config["render_html_js"]) {
            $classes .= " data-rhj";
        }
        if ($config["api_serves_data"]) {
            $classes .= " data-asd";
        }
        if ($config["api_serves_tmpl"]) {
            $classes .= " data-ast";
        }
        if ($config["api_serves_html"]) {
            $classes .= " data-ash";
        }
        if ($config["routing_server"]) {
            $classes .= " data-rs";
        }
        if ($config["routing_js"]) {
            $classes .= " data-rj";
        }
        if ($config["load_paths_js"]) {
            $classes .= " data-lpj";
        }

        return trim($classes);
    },
    "CreateSetConfigFilesFolders" => function () {
        g::run("tools.CreateFolder", g::get("config.paths.clone_cache_folder"));
        g::run("tools.CreateFolder", g::get("config.paths.clone_data_folder"));
        g::run("tools.CreateFolder", g::get("config.paths.clone_ui_folder"));

        $htaccess = g::get("config.paths.clone_folder") . ".htaccess";
        g::run("tools.CreateHtaccess", $htaccess);

        $apphtml = g::get("config.paths.clone_ui_folder") . "app.html";
        g::run("tools.CreateAppHtml", $apphtml);

        $cuki = g::get("clone.uki");
        g::run("config.env.$cuki", array());

        // do not write config paths to config.
        // plus this path comes from the config CONSTANT.
        g::run("tools.CreateSetConfig", "config", g::get("config.paths.clone_config_file"));

        // if there are specific customizations for this environment, merge them.
        if (!empty(g::get("config.env.$cuki"))) {
            $live_config = g::get("config");
            $env_config = g::get("config.env.$cuki");
            $active_config = g::run("tools.ArrayMergeRecurseProper", $live_config, $env_config);
            g::set("config", $active_config);
        }
        g::run("tools.CreateSetConfig", "config.views", g::get("config.paths.clone_views_file"));
        g::run("tools.CreateSetConfig", "config.mods", g::get("config.paths.clone_mods_file"));
        g::run("tools.CreateSetConfig", "config.tmpls", g::get("config.paths.clone_tmpls_file"));
        g::run("tools.CreateSetConfig", "config.bits", g::get("config.paths.clone_bits_file"));
        g::run("tools.CreateSetConfig", "config.base", g::get("config.paths.clone_base_file"));
    },
    "ParseUrlQuery" => function () {
        // Get complete query
        $gq = g::get("get");
        if (empty($gq)) {
            g::set("get", "");
        }
        $query_complete = trim($gq);

        // Select a default language
        $bcl = g::get("config.settings.langs");
        $clone_lang = $bcl[0];

        // Set default index page and view path for clone
        $clone_index = $query_path = "index";

        if ($query_complete == ".json") {
            header("Location: $clone_index.json");
            die;
        }

        // Set other variables defaults
        $query_bare = $query_folders = $query_actual = $query_match
        = $query_args = $query_output = $path_view
        = $query_css = $path_gqls = $path_bits = null;

        $clone_base = g::get("op.meta.url.base");

        g::set("op.meta.clone", g::run("core.SetCloneInfo"));
        g::set("op.meta.clone.index", $clone_index);

        // THE OUTPUT > META > URL DETAILS
        g::set("op.meta.url", array(
            "base" => $clone_base,
            "request" => $query_complete, //$this->str_url_safe($uc);
            "bare" => $query_bare, //$uac;
            "args" => $query_args, //$ua;
            "folder" => $query_folders, //$uf;
            "match" => $query_match, //$this->str_url_safe($um);
            "output" => $query_output, //$this->str_url_safe($uo);
        ));

        // IS THERE A REFERER
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        // THERE IS NO QUERY STRING...
        if (empty($query_complete)) {
            $lv = g::get("config.views.Index.urls." . $clone_lang);
            $ld = g::get("config.bits.url.Index." . $clone_lang);
            if (!empty($lv)) {
                if (is_array($lv)) {
                    $clone_index = $lv[0];
                } else {
                    $clone_index = $lv;
                }
            } elseif (!empty($ld)) {
                if (is_array($ld)) {
                    $clone_index = $ld[0];
                } else {
                    $clone_index = $ld;
                }
            }
            $query_actual = $query_complete = $query_bare = $clone_index;
        }
        // THERE IS A QUERY STRING
        else {
            $query_bare = $query_actual = $query_complete;
            // THERE IS A SLASH.. LETS DECIDE FOLDER STRUCTURE.
            if (strpos($query_complete, "/") !== false) {
                $qArr = explode('/', trim($query_complete));
                $ca = count($qArr);
                $currf = &$query_folders;
                for ($i = 0; $i + 1 < $ca; $i++) {
                    $query_folders[] = $qArr[$i];
                }
                $query_bare = $query_actual = $qArr[$ca - 1];
            }
            // THERE IS A DOT.. LETS DECIDE OUTPUT TYPE.
            if (strpos($query_bare, ".") !== false) {
                $qArr = explode('.', trim($query_bare));
                $query_bare = $query_actual = $qArr[0];
                $query_output = $qArr[1];
                // IF URL OUTPUT IS NOT ONE OF THE ALLOWED ONES LEAVE AN ERROR LOG...
                $bot = g::get("config.settings.output_types." . $query_output);
                if ($query_output !== "" && empty($bot)) {
                    $query_output = "";
                    g::run("tools.Say", $query_complete);
                    // show_not_found();
                }
            }

            // IS THERE A REDIRECT ~
            if (strpos($query_bare, "~") !== false) {
                $qr = explode("~", $query_bare);
                $query_bare_new = $qr[0];
                if (count($qr) == 2) {
                    $referer = $qr[1];
                } else {
                    $referer = str_replace("$query_bare_new~", "", $query_bare);
                }
                $query_bare = $query_bare_new;
            }

            // THERE IS ; AND = SO THERE ARE ARGS HERE!
            if (strpos($query_bare, ";") !== false || strpos($query_bare, "=") !== false) {
                $query_args = g::run("core.GQLParse", $query_bare);
                reset($query_args);
                $query_actual = key($query_args);
            } else {
                $query_match = $query_bare;
            }
        }

        $meta_url = array(
            "base" => $clone_base,
            "request" => $query_complete, //$this->str_url_safe($uc);
            "bare" => $query_bare, //$uac;
            "args" => $query_args, //$ua;
            "folder" => $query_folders, //$uf;
            "match" => $query_match, //$this->str_url_safe($um);
            "output" => $query_output, //$this->str_url_safe($uo);
            "refer" => $referer,
            //"redirecter" => $redirecter,
        );
        $config_urls = g::get("config.urls");
        if (empty($config_urls["clone_ui"])) {
            $config_urls["clone_ui"] = $clone_base . "ui/";
        }
        $meta_url = array_merge($meta_url, $config_urls);
        g::set("op.meta.url", $meta_url);
        //print_r($meta_url);die;
    },
    "GetServerUrl" => function () {
        $server_request_uri = rawurldecode(str_replace("?", "&", $_SERVER['REQUEST_URI']));
        $server_query_string = rawurldecode(str_replace("?", "&", $_SERVER['QUERY_STRING']));
        $clone_query = $server_query_string;
        $clone_url_path = $server_request_uri;
        if (!empty($clone_query)) {
            $pos = strrpos($server_request_uri, $clone_query);
            //var_dump($pos);
            if ($pos !== false) {
                $clone_url_path = substr_replace($server_request_uri, "", $pos, strlen($clone_query));
            }
        }
        //print_r(array("server_request_uri"=>$server_request_uri,"server_query_string"=>$server_query_string,"clone_query"=>$clone_query,"clone_url_path"=>$clone_url_path));
        // http or https
        if (!empty($_SERVER["REQUEST_SCHEME"])) {
            $protocol = $_SERVER["REQUEST_SCHEME"];
        } else {
            $s = empty($_SERVER["HTTPS"]) ? '' : (($_SERVER["HTTPS"] == "on") ? "s" : "");
            $protocol = strtolower(explode("/", $_SERVER["SERVER_PROTOCOL"])[0]) . $s;
            $protocol = g::run("tools.StrLeft", strtolower($_SERVER["SERVER_PROTOCOL"]), "/") . $s;
        }
        // does it run on another port
        $port = ($_SERVER["SERVER_PORT"] == "80" || $_SERVER["SERVER_PORT"] == "443") ? "" : (":" . $_SERVER["SERVER_PORT"]);
        // collect the clone_url
        $clone_url = $protocol . "://" . $_SERVER['SERVER_NAME'] . $port . $clone_url_path;
        $clone_url = str_replace("index.php&", "", $clone_url);
        $clone_url = rtrim($clone_url, '/');
        $clone_url = "$clone_url/";
        g::set("clone.url", $clone_url);

        $clone_url_key = str_replace(array("https://", "http://", ".", ":", "/"), array("", "", "-", "-", "_"), $clone_url);
        $clone_url_key = substr($clone_url_key, 0, -1);

        g::set("clone.uki", $clone_url_key);
        g::set("clone.uqs", $clone_query);
        if ($clone_query === "403.shtml") {
            die;
        }
        //print_r(g::get("clone"));
        g::set("get", g::run("tools.CleanQS", $clone_query));
        //print_r(g::get("get"));
    },
    "SetCloneInfo" => function () {
        $clone = array("type" => "default");
        $sc = g::run("core.SessionGet", "clone");
        if (!empty($sc)) {
            $clone = $sc;
        }
        return $clone;
    },
    "PathTranslateFindViewSetLang" => function ($paths) {
        // FIRST COME FIRST SERVER
        // TRIES TO FIND THE FIRST SUITABLE
        // FIRST TRIES TO MATCH EQUAL
        // THEN TRIES TO MATCH IF THE BEGINNING IS SAME
        $views = g::get("config.views");
        $langs = g::get("config.settings.langs");
        g::set("op.meta.url.lang", $langs[0]);

        $mods = g::get("config.mods");
        if (!empty($mods)) {
            foreach ($mods as $mod_name => $mod_config) {
                if ($mod_config !== false) {
                    if (!empty($mod_config["views"])) {
                        $mod_views = $mod_config["views"];
                        $nv = array_merge($views, $mod_views);
                        $views = $nv;
                    }
                }
            }
        }

        foreach ($views as $key => $details) {
            $urls = $details["urls"];
            foreach ($langs as $lang) {
                $url_lang = false;
                $is_url_array = false;
                if (!empty($urls[$lang])) {
                    $url_lang = $urls[$lang];
                    if (is_array($urls[$lang])) {
                        $is_url_array = true;
                    }
                }

                if ($url_lang !== false) {
                    foreach ($paths as $path) {
                        // echo "$path\n";
                        if ($is_url_array === false) {
                            if ($url_lang === $path) {
                                // found.
                                g::set("op.meta.url.lang", $lang);
                                return $key;
                            } elseif (strpos($path, $url_lang) === 0) {
                                // found.
                                g::set("op.meta.url.lang", $lang);
                                return $key;
                            }
                        } else {
                            if (in_array($path, $url_lang)) {
                                // found
                                g::set("op.meta.url.lang", $lang);
                                return $key;
                            } else {
                                foreach ($url_lang as $urlp) {
                                    if (strpos($path, $urlp) === 0) {
                                        // found.
                                        g::set("op.meta.url.lang", $lang);
                                        return $key;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return "";
    },
    "GQLParse" => function ($gql) {
        $gqls = array();
        if (strpos($gql, ";") !== false) {
            $qArr = explode(';', trim($gql));
            foreach ($qArr as $argbits) {
                if (strpos($argbits, "=") !== false) {
                    $qArrb = explode('=', trim($argbits));
                    $gqls[$qArrb[0]] = $qArrb[1];
                } else {
                    $gqls[trim($argbits)] = 1;
                }
            }
        } elseif (strpos($gql, "=") !== false) {
            $qArr = explode('=', trim($gql));
            $gqls[$qArr[0]] = $qArr[1];
        }
        return $gqls;
    },
    "GQLtoSQL" => function ($cid, $table_name, $args) {
        $sql = array();
        $sql_array = array();

        $sql_query = $sql_vals = null;

        /*
        Alphanumeric :    a b c d e f g h i j k l m n o p q r s t u v w x y z A B C D E F G H I J K L M N O P Q R S T U V W X Y Z 0 1 2 3 4 5 6 7 8 9
        Unreserved :    - _ . ~
        Reserved :    ! * ' ( ) ; : @ & = + $ , / ? % # [ ]
        Source : https://developers.google.com/maps/documentation/urls/url-encoding
        FILTERS
        /185 --> match=185 (id)
        /title-of-the-content --> match=title-of-the-content (safe_title)
        /qWxm --> match=qWxm (short_url)
         * Other than that a url can have
        (;) | (=) | (,) | (-) | (_) | (~)
         * And other than "match", url can be queried with
        "type" | "tag" | "find" | "date" | "user" | "sort" | "list" | "page"
        "price" | "location" ???
        "stat" (like|view) ???
        args are separated with ;
        plus (+) is not allowed but if necessary ~ is available, eg: 2017~2020
        dash (-) is also used in safe url so can only be used in the beginning of word eg: -metal
        /arg=this,that || query this or that
        /arg=this_that || query this and that
        /arg=this,those,-that || query this or those but not that
        /arg=2d || query in last 2 days
        /arg=3m || query in last 3 months
        /arg=1y || query in last 1 year
        /price=100-- || price less than 100
        /price=100++ || price greater than 100
        /type=products;tag=mittens,hats;date=3m;price=50--;list=20
        || list 20 mitten or hat products made in the last 3 months, price under 50
        type=bundle,content;
        tag=crochet,yarn,-bulky;
        find=mitten,-glove;
        date=-30d;
        user=-birdy99;
        price=20TL-50USD;
        location=TR,CA,US;
        sort=day-desc,title-asc;
        pattern-list-20.pdf
        list=50;
        page=2
        stat=like-top,view-bottom
        additionally....
        act=edit|update|delete|new
        id=518
        type=contents;act=edit;id=65
         */

        if (!empty($args["match"])) {
            $sql_query = "SELECT * FROM $table_name WHERE cid=$cid AND (id=? OR g_hash=? OR g_alias=? OR g_name=?)";
            $sql_vals = array($args["match"], $args["match"], $args["match"], $args["match"]);
        }

        if (!empty($args["find"])) {
            $sql_query = "SELECT * FROM $table_name WHERE cid=$cid AND (g_hash LIKE(?) OR g_alias LIKE(?) OR g_name LIKE(?) OR g_blurb LIKE(?) OR g_text LIKE(?))";
            $sql_vals = array("%" . $args["find"] . "%", "%" . $args["find"] . "%", "%" . $args["find"] . "%", "%" . $args["find"] . "%", "%" . $args["find"] . "%");
        }

        if (!empty($args["tag"])) {
            // $sql_query = "SELECT * FROM $table_name WHERE cid=$cid AND WHERE g_labels->`$[0]`=?";
            $sql_query = "SELECT * FROM $table_name WHERE cid=$cid AND JSON_CONTAINS(`g_labels`, ?, '$.item_labels')=1";
            //$sql_query = "SELECT * FROM $table_name WHERE cid=$cid AND JSON_SEARCH(g_labels, 'one', ?) IS NOT NULL";
            $tags = explode(",", $args["tag"]);
            $sql_vals = array(g::run("tools.JE", $tags));
        }

        if (!empty($args["sort"])) {
            if (strpos($args["sort"], "-") > -1) {
                $ss = explode("-", $args["sort"]);
            }
            $sql_query .= " ORDER BY " . $ss[0] . " " . $ss[1];
        }

        if (!empty($args["list"])) {
            $sql_query .= " LIMIT " . $args["list"];
        }

        if (!empty($sql_query)) {
            $sql_array = array($sql_query, $sql_vals);
            return $sql_array;
        } else {
            return false;
        }
    },
    "Query" => function ($loops = array()) {
        $config = g::get("config");
        $dataset = array();
        $dataset_sql_array = array();

        $cid = g::run("db.GetCloneId");

        if ($cid !== false) {
            $table = "items";
            $db_table = $config["db"]["tables"][$table];
            $conn = $db_table[0];
            $table_name = $db_table[1];

            if (empty($loops)) {
                $args = g::get("op.meta.url.args");
                $match = g::get("op.meta.url.match");
                if (!empty($args)) {
                    // args are set now must be converted to sql queries to select from db
                    $dataset_sql_array["main"] = g::run("core.GQLtoSQL", $cid, $table_name, $args);
                } else if (!empty($match)) {
                    // args (match is an arg) are set now must be converted to sql queries to select from db
                    $dataset_sql_array["main"] = g::run("core.GQLtoSQL", $cid, $table_name, array("match" => $match));
                } else {
                    g::set("op.data", "No content found.");
                }
            } else {
                foreach ($loops as $name => $query_bare) {
                    $args = g::run("core.GQLParse", $query_bare);
                    $dataset_sql_array[$name] = g::run("core.GQLtoSQL", $cid, $table_name, $args);
                    // args are set now must be converted to sql queries to select from db
                }
            }

            $executing_query = array($conn => $dataset_sql_array);
            $dataset = g::run("db.Execute", $executing_query);
            //print_r($dataset);
            //die;
        }
        $view = g::get("op.meta.url.view");
        $call = g::get("op.meta.url.call");
        $base = g::get("op.meta.url.base");
        if (empty($view) && empty($dataset)) {
            g::run("tools.Say", "Your query returned nothing.", 5);
            g::run("tools.Redirect", $base);
        } else {
            g::set("op.data", $dataset);
        }
    },
    "Check" => function ($key) {
        $value = null;
        $keyExists = false;
        if (strpos($key, ".") > -1) {
            $ps = explode('.', $key);
            $value = &$g;
            foreach ($ps as $part) {
                $value = &$value[$part];
            }
            if (isset($value)) {
                $keyExists = true;
            }
        } else {
            $value = &$g[$key];
            if (isset($g[$key])) {
                $keyExists = true;
            }
        }
        if ($keyExists) {
            if (is_callable($value)) {
                return "function";
            } elseif (is_array($value)) {
                return "array";
            } else {
                return "string";
            }
        }
        return false;
    },
    "WriteModViewsLabelsTmplsOptsRules" => function ($mod_name, $v, $b, $t, $o, $r) {
        if (is_array($v) && !empty($v)) {
            $views = g::get("config.mods.$mod_name.views");
            $nv = $v;
            if (!empty($views)) {
                $nv = array_merge($views, $v);
            }
            g::set("config.mods.$mod_name.views", $nv);
        }
        if (is_array($b) && !empty($b)) {
            $bits = g::get("config.mods.$mod_name.bits");
            $nb = $b;
            if (!empty($bits)) {
                $nb = array_merge($bits, $b);
            }
            g::set("config.mods.$mod_name.bits", $nb);
        }
        if (is_array($t) && !empty($t)) {
            $tmpls = g::get("config.mods.$mod_name.tmpls");
            $nt = $t;
            if (!empty($tmpls)) {
                $nt = array_merge($tmpls, $t);
            }
            g::set("config.mods.$mod_name.tmpls", $nt);
        }
        if (is_array($o) && !empty($o)) {
            $opts = g::get("config.mods.$mod_name.opts");
            $no = $o;
            if (!empty($opts)) {
                $no = array_merge($opts, $o);
            }
            g::set("config.mods.$mod_name.opts", $no);
        }
        if (is_array($r) && !empty($r)) {
            $rules = g::get("config.rules");
            $nr = $r;
            if (!empty($rules) || !empty($nr)) {
                $nr = array_merge_recursive($rules, $r);
            }
            g::set("config.rules", $nr);
        }
    },
    "EmbedBits" => function () {
        $op_meta_url = g::get("op.meta.url");
        $lang = $op_meta_url["lang"];
        $view = $op_meta_url["view"];
        // $call = $op_meta_url["call"];
        $bits = g::get("config.bits");
        $view_bits = g::get("config.views.$view.bits");

        $mods = g::get("config.mods");
        if (!empty($mods)) {
            foreach ($mods as $mod_name => $mod_config) {
                if ($mod_config !== false) {
                    if (!empty($mod_config["bits"])) {
                        $mod_bits = $mod_config["bits"];
                        $nv = array_merge($bits, $mod_bits);
                        $bits = $nv;
                    }
                }
            }
        }

        if (is_array($view_bits)) {
            $bits = array_merge($bits, $view_bits);
        }
        $bits = g::run("tools.BitsArrayDepth", $bits, $lang);
        g::set("op.bits", $bits);
    },
    "SessionStart" => function () {
        session_start();
    },
    "SessionEnd" => function () {
        setcookie(session_id(), "", time() - 3600);
        // session_write_close();
        session_unset();
        session_destroy();
        $clone_hash = g::get("config.clone.hash");
        g::run("core.CookieDel", "genes_$clone_hash");
        g::set("cookie", null);
        g::set("session", null);
    },
    "SessionSet" => function ($key, $value) {
        $clone_hash = g::get("config.clone.hash");
        $_SESSION[$clone_hash][$key] = $value;
        $session = g::get("session");
        $session[$key] = $value;
        g::set("session", $session);
    },
    "SessionGet" => function ($key) {
        return g::get("session.$key");
    },
    "SessionGetSet" => function ($key = "") {
        $session = g::get("session");
        if (empty($key)) {
            foreach ($session as $key => $value) {
                g::set($key, $value);
            }
        } else {
            $value = $session[$key];
            g::set($key, $value);
        }
    },
    "CookieAdd" => function ($key, $value, $expires) {
        setcookie($key, $value, $expires);
    },
    "CookieSet" => function ($key, $value, $expires, $name = "genes") {
        $cookie = g::get("cookie");
        if (empty($cookie)) {
            $cookie = array();
        }
        $cookie[$key] = array($value, $expires);
        $contents = g::run("tools.JE", $cookie);
        g::run("core.CookieAdd", $name, $contents, time() + 365 * 24 * 60 * 60);
    },
    "CookieDel" => function ($key) {
        setcookie($key, "", time() - 3600);
    },
    "CookieGetSet" => function ($key, $value, $expires) {
        $cookie = g::get("cookie");
        if (empty($key)) {
            foreach ($cookie as $key => $value) {
                g::set($key, $value);
            }
        } else {
            $value = $cookie[$key];
            g::set($key, $value);
        }
    },
    "DecideViewLang" => function () {
        $url = g::get("op.meta.url");

        $g_query_call = $query_name = null;
        $query_paths = array();
        // Function Name could be "bare"
        // Function Name could be "folder[0]"
        // Function Name could be "array_keys(args)[0]"
        // Else if match=bare then filter contents (id=,short_url=,safe_url=)
        if (!empty($url["bare"]) ||
            ($url["bare"] === $url["match"] || $url["bare"] === g::get("op.meta.clone.index"))) {
            $query_paths[] = $url["bare"];
        }

        if (!empty($url["folder"])) {
            $query_paths[] = $url["folder"][0];
        }

        if (!empty($url["args"])) {
            $fn = array_keys($url["args"]);
            $query_paths[] = $fn[0];
        }

        $found_view = g::run("core.PathTranslateFindViewSetLang", $query_paths);
        g::set("op.meta.url.view", $found_view);
    },
    "ProcessUploads" => function ($files, $folder = "") {
        $dataset = array();
        $errors = array();
        $extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (empty($folder)) {
            $folder = g::get("config.paths.clone_data_folder") . "uploads" . V;
            g::run("tools.CreateFolder", $folder);
        }

        //if ($_SERVER["CONTENT_LENGTH"] > (int)(str_replace("M", "", ini_get("post_max_size")) * 1024 * 1024)) {
        //    $errors[] = 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        //    g::set("op.meta.msgs", $errors);
        //    return false;
        //}
        $file_names = $files["uploads"]["name"];
        $file_count = count($file_names);
        $file_types = $files["uploads"]["type"];
        $file_tmp_names = $files["uploads"]["tmp_name"];
        $file_errors = $files["uploads"]["error"];
        $file_sizes = $files["uploads"]["size"];

        for ($i = 0; $i < $file_count; $i++) {
            $file_tmp_name = $file_tmp_names[$i];
            $file_name = $file_names[$i];
            $file_type = $file_types[$i];
            $file_size = $file_sizes[$i];
            $file_error = $file_errors[$i];

            $tmp = explode('.', $file_name);
            $file_ext = strtolower(end($tmp));

            if (!in_array($file_ext, $extensions)) {
                $errors[$file_name][] = 'Extension not allowed: ' . $file_name . ' - ' . $file_type;
            }

            if ($file_size > (1024 * 1024 * 2)) {
                $errors[$file_name][] = 'File size exceeds limit: ' . $file_name . ' - ' . $file_size;
            }

            if ($file_error > 0) {
                $phpFileUploadErrors = array(
                    0 => 'There is no error, the file uploaded with success',
                    1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                    3 => 'The uploaded file was only partially uploaded',
                    4 => 'No file was uploaded',
                    6 => 'Missing a temporary folder',
                    7 => 'Failed to write file to disk.',
                    8 => 'A PHP extension stopped the file upload.',
                );
                $error_type = $phpFileUploadErrors[$file_error];
                $errors[$file_name][] = "File upload error: $error_type | $file_name - $file_type - $file_size";
            }

            $upload_file = basename($file_name);
            $upload_file_path = $folder . $upload_file;

            if (empty($errors)) {
                move_uploaded_file($file_tmp_name, $upload_file_path);
            }
        }

        if ($errors) {
            g::set("op.meta.msgs", $errors);
        }
    },
    "ProcessUploadDeletes" => function ($files, $folder = "") {
        if (empty($folder)) {
            $folder = g::get("config.paths.clone_data_folder") . "uploads" . V;
        }

        if (!empty($files)) {
            //error_log(json_encode($files));
            if (is_array($files)) {
                foreach ($files as $key => $file) {
                    $filename = $folder . $file;
                    if (file_exists($filename)) {
                        unlink($filename);
                    } else {
                        g::run("tools.Say", "File not found, can not delete.", 5);
                    }
                }
            } else {
                $filename = $folder . $files;
                if (file_exists($filename)) {
                    unlink($filename);
                } else {
                    g::run("tools.Say", "File not found, can not delete.", 5);
                }
            }

        }
    },
    "ProcessUploadsAfterAdd" => function ($files, $hash, $rename_files = true, $format = "", $tmp_folder = "", $folder = "") {
        $renames = array();
        if (empty($tmp_folder)) {
            $tmp_folder = g::get("config.paths.clone_data_folder") . "uploads" . V;
            g::run("tools.CreateFolder", $tmp_folder);
        }
        if (empty($folder)) {
            $folder = g::get("config.paths.clone_ui_folder") . "uploads" . V;
            g::run("tools.CreateFolder", $folder);
        }

        //g::run("tools.Say", "New files: " . g::run("tools.JE", $files));
        //g::run("tools.Say", "Format: " . $format);
        //g::run("tools.Say", "Hash: " . $hash);

        if ($rename_files != true) {
            $renames = $files;
        } else {
            $fl = count($files);
            for ($i = 0; $i < $fl; $i++) {
                $file = $files[$i];
                $tmp = explode('.', $file);
                $file_ext = strtolower(end($tmp));
                if (empty($format)) {
                    $filename = explode(".$file_ext", $file);
                    $clean_file = g::run("tools.ToAscii", $filename[0]) . ".$file_ext";
                } else {
                    $n = sprintf("%02d", $i);
                    $clean_file = "$format-$n-$hash.$file_ext";
                }
                rename($tmp_folder . $file, $folder . $clean_file);
                $renames[] = $clean_file;
            }
        }
        return $renames;
    },
    "ProcessUploadsAfterEdit" => function ($files, $old_files, $hash, $rename_files = true, $format = "", $tmp_folder = "", $folder = "") {
        $renames = array();
        if (empty($tmp_folder)) {
            $tmp_folder = g::get("config.paths.clone_data_folder") . "uploads" . V;
            g::run("tools.CreateFolder", $tmp_folder);
        }
        if (empty($folder)) {
            $folder = g::get("config.paths.clone_ui_folder") . "uploads" . V;
            g::run("tools.CreateFolder", $folder);
        }

        //g::run("tools.Say", "New files: " . g::run("tools.JE", $files));
        //g::run("tools.Say", "Old files: " . g::run("tools.JE", $old_files));
        //g::run("tools.Say", "Hash: " . $hash);

        if ($files === $old_files) {
            return $files;
        } else {
            if ($rename_files != true) {
                $delete_files = array_diff($old_files, $files);
                g::run("core.ProcessUploadDeletes", $delete_files, $folder);
                //g::run("tools.Say", "Deleting files -1: " . g::run("tools.JE", $delete_files));
                return $files;
            } else {
                $delete_files = array_diff($old_files, $files);
                g::run("core.ProcessUploadDeletes", $delete_files, $folder);
                //g::run("tools.Say", "Deleting files -2: " . g::run("tools.JE", $delete_files));

                $fl = count($files);
                for ($i = 0; $i < $fl; $i++) {
                    $file = $files[$i];
                    $tmp = explode('.', $file);
                    $file_ext = strtolower(end($tmp));
                    if (empty($format)) {
                        $filename = explode(".$file_ext", $file);
                        $clean_file = g::run("tools.ToAscii", $filename[0]) . ".$file_ext";
                    } else {
                        $n = sprintf("%02d", $i);
                        $clean_file = "$format-$n-$hash.$file_ext";
                    }

                    $renames[] = $clean_file;

                    if (file_exists($folder . $file)) {
                        rename($folder . $file, $folder . $clean_file);
                    } else if (file_exists($tmp_folder . $file)) {
                        rename($tmp_folder . $file, $folder . $clean_file);
                    }
                }
            }
        }
        //g::run("tools.Say", "Renamed Images: " . g::run("tools.JE", $renames));
        return $renames;
    },
    "PrepareUploadedImagesEdit" => function ($images, $folder = "") {
        if (empty($folder)) {
            $folder = g::get("config.paths.clone_ui_folder") . "uploads" . V;
        }
        $imgs = array();
        if (!empty($images)) {
            foreach ($images as $k => $img) {
                $img_path = $folder . $img;
                if (file_exists($img_path)) {
                    $imgs[] = array(
                        $img,
                        mime_content_type($img_path),
                        filesize($img_path),
                    );
                }
            }
            //$enc_imgs = g::run("tools.JEAS", $imgs);
            return $imgs;
        } else {
            return $imgs;
        }
    }
));
