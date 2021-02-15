<?php
g::def("tools", array(
    "ArrayMergeRecurseProper" => function ($array1, $array2) {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = g::run("tools.ArrayMergeRecurseProper", $array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        return $array1;
    },
    "BitsArrayDepth" => function ($bits, $lang) {
        foreach ($bits as $key => $val) {
            if (!empty($val[$lang])) {
                $bits[$key] = $bits[$key][$lang];
            } elseif (is_array($val)) {
                $bits[$key] = g::run("tools.BitsArrayDepth", $val, $lang);
            }
        }
        return $bits;
    },
    "PR" => function ($sth) {
        print_r($sth);
    },
    "Now" => function ($uts = null) {
        if (is_null($uts)) {
            $uts = microtime(true);
        }
        $ts = floor($uts);
        $mss = round(($uts - $ts) * 1000);
        if ($mss < 10) {
            $mss = "00" . $mss;
        } elseif ($mss < 100) {
            $mss = "0" . $mss;
        }

        return date(preg_replace('`(?<!\\\\)u`', $mss, g::get("config.settings.date_time_format")), $ts);
    },
    "Performance" => function () {
        $t = sprintf('%0.6f', microtime(true) - g::get("void.time"));
        $unit = array('Bytes', 'KB', 'MB', 'GB', 'tb', 'pb');
        $mm = memory_get_usage() - g::get("void.mem");
        if ($mm < 0) {
            $mm = 0;
        }
        $m = @round($mm / pow(1024, ($i = floor(log($mm, 1024)))), 2) . ' ' . $unit[$i];
        return ($t . " sec | " . $m . " ram. | " . g::get("op.meta.url.request"));
    },
    "Say" => function ($what, $importance = 0) {
        // Message levels,
        // 0 -- Not important, debugging, informational
        // 1 -- Success
        // 3 -- Warning
        // 5 -- Error
        // 9 -- Dead
        $t = g::run("tools.Now");

        $cml = g::get("config.settings.msg_level");
        $cll = g::get("config.settings.log_level");
        if ($importance >= $cml) {
            $marr = array();
            $msgs = g::get("op.meta.msgs");
            if (!empty($msgs)) {
                $marr = $msgs;
            }
            if (is_array($what)) {
                $marr[] = array($t, $importance, g::run("tools.JE", $what));
            } else {
                $marr[] = array($t, $importance, g::run("tools.Translate", $what));
            }
            g::set("op.meta.msgs", $marr);
        }
        if ($importance >= $cll) {
            if (is_array($what)) {
                g::run("tools.Log", "$t|" . g::run("tools.JE", $what));
            } else {
                g::run("tools.Log", "$t|" . g::run("tools.Translate", $what));
            }
        }
    },
    "Log" => function ($what) {
        $filename = g::get("config.paths.clone_log_file");
        g::run("tools.WriteFile", $what, $filename, true);
    },
    "Translate" => function ($what) {
        return $what;
    },
    "CreateFolder" => function ($folder, $mode = 0777) {
        return is_dir($folder) || mkdir($folder, $mode, true);
    },
    "CreateHtaccess" => function ($file_path) {
        if (!empty($file_path)) {
            if (!is_file($file_path)) {
                $htc = '';
                $htc .= '# AddHandler php5-fastcgi .php' . "\n";
                $htc .= '# Action php5-fastcgi /cgi-bin/php.fcgi' . "\n";
                $htc .= '# BEGIN GENES' . "\n";
                $htc .= '# Header set X-Robots-Tag "noindex, nofollow"' . "\n";
                $htc .= '# Force simple error message for requests for non-existent files.' . "\n";
                $htc .= '# There is no end quote below, for compatibility with Apache 1.3.' . "\n";
                $htc .= 'ErrorDocument 404 "The requested file was not found."' . "\n";
                $htc .= '<IfModule mod_rewrite.c>' . "\n";
                $htc .= '# Turn on URL rewriting' . "\n";
                $htc .= 'RewriteEngine On' . "\n";
                $htc .= 'Options +FollowSymLinks' . "\n";
                $htc .= 'Options -Indexes' . "\n";
                $htc .= '# Protect hidden files from being viewed' . "\n";
                $htc .= '<FilesMatch "\.(htaccess|htpasswd|ini|log|sh|inc|bak)$">' . "\n";
                $htc .= 'Order Allow,Deny' . "\n";
                $htc .= 'Deny from all' . "\n";
                $htc .= '</FilesMatch>' . "\n";
                $htc .= '# Allow system php files to work' . "\n";
                $htc .= '<Files ~ "(index)\.(php)$">' . "\n";
                $htc .= 'Order Deny,Allow' . "\n";
                $htc .= 'Allow From All' . "\n";
                $htc .= '</Files>' . "\n";
                $htc .= 'RewriteCond %{HTTP_HOST} ^www\.(.*)$ [NC]' . "\n";
                $htc .= 'RewriteRule ^(.*)$ http://%1/$1 [R=301,L]' . "\n";
                $htc .= '# PREVENT DATA FOLDER REACH' . "\n";
                $htc .= 'RewriteRule ^data - [F]' . "\n";
                $htc .= '# Allow any files or directories that exist to be displayed directly other go to index.php' . "\n";
                $htc .= 'RewriteCond %{REQUEST_FILENAME} !\.(gif|jpe?g|png|js|css|swf|ico|txt|pdf|xml|eot|svg|ttf|woff|woff2|mp3|zip|html|webmanifest)$' . "\n";
                $htc .= 'RewriteCond %{REQUEST_URI} !^(.)$' . "\n";
                $htc .= 'RewriteCond %{REQUEST_URI} !(index.php)$' . "\n";
                $htc .= 'RewriteRule ^(.*) index.php?$1 [L,NC,QSA]' . "\n";
                $htc .= '</IfModule>' . "\n";
                $htc .= '# END GENES';

                g::run("tools.WriteFile", $htc, $file_path);
            }
        }
    },
    "CreateAppHtml" => function ($file_path) {
        if (!empty($file_path)) {
            if (!is_file($file_path)) {
                $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8" /><title>{{bits.title}}</title></head><body><h3>Hello, world.</h3><h4>Genes setup complete.</h4><h5>Your <a href="{{meta.url.base}}">new clone</a> is ready to use.</h5><span>Thanks, Have Fun!<br><a href="https://genes.one" target="_blank">Genes</a></span></body></html>';
                g::run("tools.WriteFile", $html, $file_path);
            }
        }
    },
    "CreateSetConfig" => function ($data_path, $file_path) {
        if (!empty($file_path)) {
            if (!is_file($file_path)) {
                if ($data_path === "config") {
                    g::run("crypt.GenerateKeys");
                    $config = g::get("config");
                    $cuki = g::get("clone.uki");
                    $config["env"][$cuki]["paths"] = $config["paths"];
                    unset($config["paths"]);
                    g::run("tools.CreateFileJE", $file_path, $config);
                    g::set("config", $config);
                } else {
                    g::run("tools.CreateFileJE", $file_path, g::get($data_path));
                }
            } else {
                if ($data_path === "config") {
                    $config_file = g::run("tools.ReadFileJD", $file_path);
                    $cuki = g::get("clone.uki");
                    if (empty($config_file["env"][$cuki])) {
                        $config = g::get("config");
                        $config_file["env"][$cuki]["paths"] = $config["paths"];
                        g::run("tools.CreateFileJE", $file_path, $config_file);
                    }
                    g::set($data_path, $config_file);
                } else {
                    g::set($data_path, g::run("tools.ReadFileJD", $file_path));
                }
            }
        }
    },
    "UpdateConfigCheck" => function ($path, $value) {
        $config_path = g::get("config.paths.clone_config_file");
        $config_data = g::run("tools.ReadFileJD", $config_path);

        if (empty($config_data["checks"])) {
            $config_data["checks"] = array();
        }

        $config_data["checks"]["$path"] = $value;
        g::run("tools.CreateFileJE", $config_path, $config_data);
    },
    "UpdateConfigFiles" => function ($path, $value, $env = false) {
        $config_file = g::get("config.paths.clone_config_file");
        $config_data = g::run("tools.ReadFileJD", $config_file);

        $cuki = g::get("clone.uki");
        $config_data["paths"] = $config_data["env"][$cuki]["paths"];

        $clone_views_file = $config_data["paths"]["clone_views_file"];
        $clone_mods_file = $config_data["paths"]["clone_mods_file"];
        $clone_tmpls_file = $config_data["paths"]["clone_tmpls_file"];
        $clone_bits_file = $config_data["paths"]["clone_bits_file"];
        $clone_base_file = $config_data["paths"]["clone_base_file"];

        $edit_path = "";

        if (strpos($path, "views.") === 0 && !empty($clone_views_file)) {
            $config_data["views"] = g::run("tools.ReadFileJD", $clone_views_file);
            $config_file = $clone_views_file;
            $edit_path = "views";
        } else if (strpos($path, "mods.") === 0 && !empty($clone_mods_file)) {
            $config_data["mods"] = g::run("tools.ReadFileJD", $clone_mods_file);
            $config_file = $clone_mods_file;
            $edit_path = "mods";
        } else if (strpos($path, "tmpls.") === 0 && !empty($clone_tmpls_file)) {
            $config_data["tmpls"] = g::run("tools.ReadFileJD", $clone_tmpls_file);
            $config_file = $clone_tmpls_file;
            $edit_path = "tmpls";
        } else if (strpos($path, "bits.") === 0 && !empty($clone_bits_file)) {
            $config_data["bits"] = g::run("tools.ReadFileJD", $clone_bits_file);
            $config_file = $clone_bits_file;
            $edit_path = "bits";
        } else if (strpos($path, "base.") === 0 && !empty($clone_base_file)) {
            $config_data["base"] = g::run("tools.ReadFileJD", $clone_base_file);
            $config_file = $clone_base_file;
            $edit_path = "base";
        }

        unset($config_data["paths"]);
        g::set("void.config", $config_data);
        if ($env) {
            g::set("void.config.env.$cuki.$path", $value);
        } else {
            g::set("void.config.$path", $value);
        }

        if (!empty($edit_path)) {
            $changed_config_path = g::get("void.config.$edit_path");
        } else {
            $changed_config_path = g::get("void.config");
        }

        g::run("tools.CreateFileJE", $config_file, $changed_config_path);
        g::set("void.config", null);
    },
    "GetConfigComplete" => function () {
        $config_data = g::get("config");
        unset($config_data["paths"]);
        return $config_data;
    },
    "UpdateConfigComplete" => function ($posted_config, $is_json_encoded = true) {
        if (!empty($posted_config)) {
            $config_path = g::get("config.paths.clone_config_file");
            if ($is_json_encoded) {
                $posted_config = str_replace("\\\\", "\\", $posted_config);
                $config_data = g::run("tools.JDH", $posted_config);
            } else {
                $config_data = $posted_config;
            }

            $cuki = g::get("clone.uki");
            $config_data["paths"] = $config_data["env"][$cuki]["paths"];
            $files_data = array();

            $clone_views_file = $config_data["paths"]["clone_views_file"];
            if (!empty($clone_views_file)) {
                $files_data[] = array($clone_views_file, $config_data["views"]);
                $config_data["views"] = array();
            }
            $clone_mods_file = $config_data["paths"]["clone_mods_file"];
            if (!empty($clone_mods_file)) {
                $files_data[] = array($clone_mods_file, $config_data["mods"]);
                $config_data["mods"] = array();
            }
            $clone_tmpls_file = $config_data["paths"]["clone_tmpls_file"];
            if (!empty($clone_tmpls_file)) {
                $files_data[] = array($clone_tmpls_file, $config_data["tmpls"]);
                $config_data["tmpls"] = array();
            }
            $clone_bits_file = $config_data["paths"]["clone_bits_file"];
            if (!empty($clone_bits_file)) {
                $files_data[] = array($clone_bits_file, $config_data["bits"]);
                $config_data["bits"] = array();
            }
            $clone_base_file = $config_data["paths"]["clone_base_file"];
            if (!empty($clone_base_file)) {
                $files_data[] = array($clone_base_file, $config_data["base"]);
                $config_data["base"] = array();
            }

            unset($config_data["paths"]);
            $files_data[] = array($config_path, $config_data);

            $fl = count($files_data);
            for ($i = 0; $i < $fl; $i++) {
                g::run("tools.CreateFileJE", $files_data[$i][0], $files_data[$i][1]);
            }
        }
    },
    "WriteFile" => function ($str, $filename, $append = false) {
        if (!empty($filename)) {
            if (@file_exists($filename) && $append) {
                $fh = fopen($filename, "a") or die(g::run("tools.Say", "error|file-exists-cant-open-file-to-append", 5));
            } else {
                $fh = fopen($filename, "w") or die(g::run("tools.Say", "error|cant-open-file-to-write", 5));
            }
            fwrite($fh, "$str\n") or die(g::run("tools.Say", "error|cant-write-to-file", 5));
            fclose($fh);
        } else {
            g::run("tools.Say", "error|filename-empty-string-not-written|$filename|$str");
        }
    },
    "ReadFileJD" => function ($file_path) {
        return json_decode(g::run("tools.LoadPathSafe", $file_path), true);
    },
    "LoadPathSafe" => function ($url, $method = 'GET', $data = array(), $auth = null) {
        $isUrl = $isLocal = $callCurl = $callFopen = $fopenExists = $curlExists = $fopenUrlExists = false;

        if (strpos($url, 'http://') !== false || strpos($url, 'https://') !== false) {
            $isUrl = true;
        } else {
            $isLocal = true;
        }
        if (function_exists('curl_init')) {
            $curlExists = true;
        }

        if (function_exists('fopen') === true) {
            $fopenExists = true;
            if (ini_get('allow_url_fopen') === true) {
                $fopenUrlExists = true;
            }
        }

        if ($isUrl) {
            if ($curlExists) {
                $callCurl = true;
            } elseif ($fopenExists && $fopenUrlExists) {
                $callFopen = true;
            }
        } elseif ($isLocal) {
            if ($fopenExists) {
                $callFopen = true;
            } elseif ($curlExists) {
                $url = 'file:///' . realpath($url);
                $callCurl = true;
            }
        }
        if ($callCurl) {
            if ($method == 'GET') {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
                curl_setopt($ch, CURLOPT_URL, $url);
                // 2019-10-16, added to fix ipdata get data ssl issue
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $result = curl_exec($ch);
                /*
                $info = curl_getinfo($ch);
                $err = curl_error($ch);
                 */
                curl_close($ch);
                /*
            print_r($result);
            print_r($info);
            print_r($err);
            die;
             */

            } else {
                // $postdata = http_build_query($data);
                //open connection
                $ch = curl_init();
                //set the url, number of POST vars, POST data
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_URL, $url);
                if (!empty($auth)) {
                    curl_setopt($ch, CURLOPT_USERPWD, "$auth");
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                }
                curl_setopt($ch, CURLOPT_POST, count($data));
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $result = curl_exec($ch);
                $info = curl_getinfo($ch);
                curl_close($ch);

                /*
            print_r($result);
            print_r($info);
            print_r($err);
            die;
             */
            }
        } elseif ($callFopen) {
            if ($method == 'GET') {
                $result = file_get_contents($url);
            } else {
                $postdata = http_build_query($data);
                $opts = array('http' => array(
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $postdata,
                ),
                );
                $context = stream_context_create($opts);
                $result = @file_get_contents($url, false, $context);
            }
        } else {
            $result = "";
        }
        return $result;
    },
    "LoadFileSimple" => function ($file) {
        if (is_file($file)) {
            ob_start();
            require $file;
            return ob_get_clean();
        } else {
            g::run("tools.Say", "error|file-not-found|$file");
        }
    },
    "CreateFileJE" => function ($file_path, $contents) {
        g::run("tools.WriteFile", json_encode($contents), $file_path);
    },
    "StrLeft" => function ($s1, $s2) {
        return substr($s1, 0, strpos($s1, $s2));
    },
    "Redirect" => function ($url = "") {
        if (empty($url)) {
            $url = g::get("op.meta.url.refer");
        }
        if (!empty($url)) {
            g::set("op.meta.redirect", $url);
        }
    },
    "RedirectNow" => function ($url) {
        if (!headers_sent()) {
            if (preg_match('/(?i)msie [1-9]/', $_SERVER['HTTP_USER_AGENT'])) {
                header('Refresh:0;url=' . urldecode($url));
            } else {
                $blnReplace = true;
                $intHRC = 302;
                header('Refresh:0;url=' . urldecode($url));
                //header('Location: ' . urldecode($url), $blnReplace, $intHRC);
            }
            exit;
            die;
        } else {
            echo '<script type="text/javascript">';
            echo 'window.location.href="' . $url . '";';
            echo '</script>';
            echo '<noscript>';
            echo '<meta http-equiv="refresh" content="0;url=' . $url . '" />';
            echo '</noscript>';
            exit;
            die;
        }
    },
    "HeaderSet" => function ($key, $value) {
        header("$key: $value");
    },
    "HeaderGet" => function ($key) {
        $headers = array();
        if (!function_exists('getallheaders')) {
            foreach ($_SERVER as $name => $value) {
                /* RFC2616 (HTTP/1.1) defines header fields as case-insensitive entities. */
                if (strtolower(substr($name, 0, 5)) == 'http_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        } else {
            $headers = getallheaders();
        }
        return $headers; //[$key];
    },
    "MDArrayKeys" => function ($arr) {
        foreach ($arr as $k => $v) {
            $keys[] = $k;
            if (is_array($arr[$k])) {
                $keys = array_merge($keys, g::run("tools.MDArrayKeys", $arr[$k]));
            }

        }
        return $keys;
    },
    "MaxArrayValByKey" => function ($array, $key_search) {
        $currentMax = null;
        foreach ($array as $arr) {
            foreach ($arr as $key => $value) {
                if ($key == $key_search && ($value >= $currentMax)) {
                    $currentMax = $value;
                }
            }
        }

        return $currentMax;
    },
    "JE" => function ($arr) {
        return json_encode($arr);
    },
    "JD" => function ($json_str) {
        return json_decode($json_str, true);
    },
    "JDH" => function ($json_str) {
        // replace html entities
        $json_str = html_entity_decode($json_str, ENT_QUOTES, "UTF-8");
        // replace slashes so that json does not break
        $json_str = str_replace(array("\\", "/"), array("\\\\", "\/"), $json_str);
        // finally decode json to assoc array
        $json_arr = json_decode($json_str, true, 512, JSON_UNESCAPED_UNICODE);
        return $json_arr;
    },
    "JEAS" => function ($arr) {
        // Json Encode Attribute Safe
        $tmp = json_encode($arr);
        return str_replace(
            array('"', "/"),
            array('\"', "\/"),
            $tmp
        );
    },
    "JDAS" => function ($jstr) {
        // Json Data Attribute Safe
        return g::run("tools.JD", g::run("tools.DirtData", $jstr));
    },
    "CleanBreaks" => function ($val) {
        $val = str_replace(array('\r', '\n', '\t'), "", $val);
        return preg_replace('/\s+/S', " ", $val);
    },
    "CleanData" => function ($arr_or_str) {
        if (is_array($arr_or_str)) {
            foreach ($arr_or_str as $key => $value) {
                $arr_or_str[$key] = g::run("tools.CleanData", $value);
            }
        } else {
            $arr_or_str = str_replace(
                array(">", "<", "'", "\"", "\r", "\r\n", "\t"),
                array("&gt;", "&lt;", "&#039;", "&quot;", "", "", "  "),
                $arr_or_str
            );
        }
        return $arr_or_str;
    },
    "DirtData" => function ($arr_or_str) {
        if (is_array($arr_or_str)) {
            foreach ($arr_or_str as $key => $value) {
                $arr_or_str[$key] = g::run("tools.DirtData", $value);
            }
        } else {
            $arr_or_str = str_replace(
                array('\"', "&gt;", "&lt;", "&quot;", "&#039;", "&nbsp;"),
                array('"', ">", "<", '"', "'", " "),
                $arr_or_str
            );
        }
        return $arr_or_str;
    },
    "JDDB" => function ($json_str) {
        $arr_tmp = json_decode(html_entity_decode($json_str), true, 512, JSON_UNESCAPED_UNICODE);
        $arr = DBD($arr_tmp);
        return $arr;
    },
    "JEDB" => function ($arr) {
        $arr_clean = DBE($arr);
        return json_encode($arr_clean, JSON_UNESCAPED_UNICODE);
    },
    "DBE" => function ($arr_or_str) {
        if (is_array($arr_or_str)) {
            foreach ($arr_or_str as $key => $value) {
                $arr_or_str[$key] = DBE($value);
            }
        } else {
            $arr_or_str = str_replace(
                array("&", ">", "<", "'", "\""),
                array("&amp;", "&gt;", "&lt;", "&#039;", "&quot;"),
                $arr_or_str);
        }
        return $arr_or_str;
    },
    "DBD" => function ($arr_or_str) {
        if (is_array($arr_or_str)) {
            foreach ($arr_or_str as $key => $value) {
                $arr_or_str[$key] = DBD($value);
            }
        } else {
            $arr_or_str = str_replace(
                array("&amp;", "&gt;", "&lt;", "&#039;", "&quot;"),
                array("&", ">", "<", "'", "\""),
                $arr_or_str);
        }
        return $arr_or_str;
    },
    "DBQ" => function ($arr_or_str) {
        if (is_array($arr_or_str)) {
            foreach ($arr_or_str as $key => $value) {
                $arr_or_str[$key] = DBQ($value);
            }
        } else {
            $arr_or_str = str_replace(
                array("&quot;", '"'),
                array('\"', '&quot;'),
                $arr_or_str);
        }
        return $arr_or_str;
    },
    "CleanQS" => function ($str) {
        // Remove out Non "Letters"
        $str = str_replace("&", ";", $str);
        $str = preg_replace('/[^\\pL\d\.,;_\-+=\/ ]+/u', '', $str);
        return $str;
    },
    "SafeUrl" => function ($text, $wutf = false) {
        // First convert html entities back
        $text = str_replace(
            array("&amp;", "&gt;", "&lt;", "&#039;", "&quot;"),
            array("&", ">", "<", "'", "\""),
            $text
        );
        //--echo "1- $text\n";
        // Swap out Non "Letters" with a -
        $text = preg_replace('/[^\\pL\d\.;_+=,\/]+/u', '-', $text);
        //--echo "2- $text\n";
        // Trim out extra -'s
        $text = trim($text, '-');
        //--echo "3- $text\n";
        // Convert letters that we have left to the closest ASCII representation
        //setlocale(LC_ALL, 'en_US.utf8');
        //$text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = g::run("tools.RemoveAccents", $text);
        //--echo "4- $text\n";
        // Make text lowercase
        $text = strtolower($text);
        //--echo "5- $text\n";
        // exit here for proper genes path......................
        // Strip out anything we haven't been able to convert
        $text = preg_replace('/[^-\w\.;+\/]+/', '', $text);
        //--echo "6- $text\n";
        $text = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '-', $text);
        //--echo "7- $text\n";
        $text = preg_replace("/[\/_|+ -]+/", "-", $text);
        //--echo "8- $text\n";
        if (substr($text, -1) == "-") {
            $text = rtrim($text, "-");
            //--echo "9- $text\n";
        }
        //--echo "0- $text\n";
        return $text;
    },
    "ToAscii" => function ($str, $replace = array(), $delimiter = '-') {
        if (!empty($replace)) {
            $str = str_replace((array) $replace, ' ', $str);
        }
        $str = str_replace(array("&#039;", "&quot;", "&lt;", "&gt;"), "", $str);

        $clean = urldecode($str);
        $clean = g::run("tools.RemoveAccents", $clean);
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $clean);
        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
        $clean = strtolower(trim(substr($clean, 0, 128), '-'));
        $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);
        if (substr($clean, -1) == "-") {
            $clean = rtrim($clean, "-");
        }
        return $clean;
    },
    "RemoveAccents" => function ($str) {
        $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ');
        $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o');
        return str_replace($a, $b, $str);
    },
    "DTS" => function ($mode = 0, $timestamp = null) {

        $settings = g::get("config.settings");
        $time_zone = (!empty($settings["timezone"])) ? $settings["timezone"] : "Europe/Tallinn";
        $date_format = (!empty($settings["date_time_format"])) ? $settings["date_time_format"] : "Y-m-d H:i:s.u";

        // input timestamp / datetime

        // microtimestamp
        $mt = microtime();
        // $returns[] = $mt;
        // explode microtime
        $t = explode(" ", $mt);
        $ts = $t[1];
        $ms = $t[0];

        if (!empty($timestamp)) {
            $ts = $timestamp;
            $ms = 0;
        }

        // $mode = 0 :: timestamp return as seconds
        if ($mode == 0) {
            // 1589288061
            return $ts;
        }

        // $mode = 1 ::  timestamp return as milliseconds 3 digits
        $ts_dot_ms = sprintf("%0.3f", $ts + $ms);
        if ($mode == 1) {
            // 1589288096.181
            return $ts_dot_ms;
        }

        // $mode = 2 :: timestamp return as microseconds 6 digits
        $ts_dot_mcs = sprintf("%0.6f", $ts + $ms);
        if ($mode == 2) {
            // 1589288112.660112
            return $ts_dot_mcs;
        }

        // $mode = 3 ::  datetime return as bigint, utc, milliseconds 3 digits
        $ms3 = substr((string) $ms, 2, 3);
        $ms6 = substr((string) $ms, 2, 6);
        $dtn = new DateTime($time_zone);
        $dtn->setTimestamp($ts);
        $dtn->setTimeZone(new DateTimeZone('UTC'));
        $datetime = $dtn->format('YmdHis');
        if ($mode == 3) {
            // 20200512125526009
            return $datetime . $ms3;
        }

        // $mode = 4 :: datetime return as float, utc, microseconds 6 digits
        if ($mode == 4) {
            // 20200512125538.815640
            return $datetime . "." . $ms6;
        }

        // $mode = 5 :: datetime return human readable, utc, with seconds
        $datetimef = DateTime::createFromFormat('YmdHis', $datetime, new DateTimeZone('UTC'));
        $dthr = $datetimef->format('Y-m-d H:i:s');

        if ($mode == 5) {
            // 2020-05-12 12:55:55
            return $dthr;
        }

        // $mode = 6 :: datetime return human readable, utc/timezoned, with milliseconds 3 digits
        if (!empty($time_zone)) {
            $datetimef->setTimeZone(new DateTimeZone($time_zone));
        }
        $add_ms = false;
        if (empty($date_format)) {
            $date_format = 'Y-m-d H:i:s';
        } else {
            if (strpos($date_format, ".u") > -1) {
                $date_format = str_replace(".u", "", $date_format);
                $add_ms = true;
            }
        }
        $dthrtz = $datetimef->format($date_format);
        if ($add_ms) {
            $dthrtz .= "." . $ms3;
        }
        if ($mode == 6) {
            // 2020-05-12 15:56:23.006
            return $dthrtz;
        }

        // $mode = 7 :: hashed datetime from float, utc, microseconds 6 digits
        if ($mode == 7) {
            // pv7e0hppxl7xgv
            return g::run("crypt.MicroHash", $datetime . "." . $ms6);
        }
        /*
        $n = 1;
        $returns[] = $n;
        $returns[] = g::run("crypt.HashEndecode", $n);
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "alpha");
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "lcase");
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "mix");
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "cray");
        $returns[] = g::run("crypt.HashTo", $n);

        $n = 999;
        $returns[] = $n;
        $returns[] = g::run("crypt.HashEndecode", $n);
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "alpha");
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "lcase");
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "mix");
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "cray");
        $returns[] = g::run("crypt.HashTo", $n);

        $n = 99999;
        $returns[] = $n;
        $returns[] = g::run("crypt.HashEndecode", $n);
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "alpha");
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "lcase");
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "mix");
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "cray");
        $returns[] = g::run("crypt.HashTo", $n);

        $n = 999999;
        $returns[] = $n;
        $returns[] = g::run("crypt.HashEndecode", $n);
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "alpha");
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "lcase");
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "mix");
        $returns[] = g::run("crypt.HashEndecode", $n, false, null, "cray");
        $returns[] = g::run("crypt.HashTo", $n);

        return $returns;
         */
        return false;
    },
    "ExitWithOpDataResponse" => function () {
        $op = g::get("op");
        $op_type = $op["meta"]["url"]["output"];

        if ($op_type === "json") {
            header('Content-type:application/json;charset=utf-8');
            echo json_encode($op["data"]);
        }
        exit;
    },
    "ExitWithOpMetaResponse" => function () {
        $op = g::get("op");
        $op_type = $op["meta"]["url"]["output"];

        if ($op_type === "json") {
            header('Content-type:application/json;charset=utf-8');
            $op = array("meta" => $op["meta"]);
            echo json_encode($op);
        }
        exit;
    }
));
