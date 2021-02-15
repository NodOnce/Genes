<?php
g::set("ui", array(
    "tmp_data" => array(),
));

g::def("ui", array(
    "LoadViewHtml" => function () {
        $bare = g::get("op.meta.url.bare");
        $mod = g::get("op.meta.url.mod");
        $view = g::get("op.meta.url.view");

        $tmpl_mod = g::get("config.tmpls.$mod");
        $tmpl_view = g::get("config.tmpls.$view");

        $genes_ui_html = g::get("config.paths.genes_ui_html");
        $clone_ui_html = g::get("config.paths.clone_ui_html");

        $fallback_genes_ui = false;
        $fallback_clone_ui = false;

        $mods = g::get("config.mods");
        if (!empty($mods)) {
            foreach ($mods as $mod_name => $mod_config) {
                if ($mod_config !== false) {
                    if (!empty($mod_config["views"])) {
                        $mod_views = $mod_config["views"];
                        if (!empty($mod_views[$mod]) || !empty($mod_views[$view])) {
                            $fallback_genes_ui = true;
                            break;
                        }
                    }
                }
            }
        }

        if (!empty($tmpl_mod)) {
            if (is_file($tmpl_mod)) {
                $file = $tmpl_mod;
            } else {
                $file = $genes_ui_html;
            }
        } else if (!empty($tmpl_view)) {
            if (is_file($tmpl_view)) {
                $file = $tmpl_view;
            } else {
                if ($fallback_genes_ui) {
                    $file = $genes_ui_html;
                } else {
                    $file = $clone_ui_html;
                }
            }
        } else {
            if ($fallback_genes_ui) {
                $file = $genes_ui_html;
            } else {
                $file = $clone_ui_html;
            }
            if (!is_file($file)) {
                $file = null;
            }
            g::run("tools.Say", "error|view-definition-does-not-exist|mod:$mod|view:$view|bare:$bare");
        }

        if (!empty($file)) {
            $tmpl = g::run("tools.LoadFileSimple", $file);
            g::set("op.tmpl", $tmpl);
        } else {
            g::run("tools.Say", "error|tmpl-file-does-not-exist|$file");
        }
    },
    "ProcessTags" => function ($html) {
        $rgx = '/<([a-zA-Z0-9]+)([^>][\s\w="@|:{}.,\;\-\/\\\']*?)([a-zA-Z0-9._-]+)>(.*?)<\/\1>[\s]*<!--\3-->/msi';

        if (preg_match_all($rgx, $html, $tags, PREG_SET_ORDER, 0)) {
            foreach ($tags as $tag) {
                $tn = trim($tag[1]); // tag_name
                $ta = trim($tag[2]); // tag_attributes
                $tc = trim($tag[4]); // tag_content

                $response = g::run("ui.ParseTagAttributes", $ta, $tc);
                $ta = (empty($response["ta"]) ? "" : " " . trim($response["ta"]));
                $tc = (empty($response["tc"]) ? $tc : trim($response["tc"]));

                $remove = (empty($response["remove"]) ? 0 : trim($response["remove"]));

                if ($remove > 0) {
                    $html = str_replace($tag[0], "", $html);
                } elseif ($response["tc"] === false) {
                    $tc = "";
                    $tn = "<$tn $ta>$tc</$tn>";
                    $html = str_replace($tag[0], $tn, $html);
                } else {
                    $tc = g::run("ui.ProcessTags", $tc); // tag_content

                    if ($tn === "del") {
                        $tn = $tc;
                    } else {
                        $tn = "<$tn$ta>$tc</$tn>";
                    }

                    $html = str_replace($tag[0], $tn, $html);
                }
            }
        }

        // genes key replacements
        $html = g::run("ui.ProcessKeys", $html);

        return trim($html);
    },
    "ParseTagAttributes" => function ($ta, $tc) {
        // $rgx = '/(\S+)=["\']?((?:.(?!["\']?\s+(?:\S+)=|[>"\']))+.)["\']?/msi';
        $rgx = '/([^\s="\']+)=["\']?((?:.(?!["\']?\s+(?:\S+)=|[>"\']))+.)["\']?/msi';
        $tag_attrs = array();
        $response = array();
        if (preg_match_all($rgx, $ta, $attrs, PREG_SET_ORDER, 0)) {

            foreach ($attrs as $attr) {
                $key = $attr[1];
                $val = $attr[2];

                // there may be a " with single numeric values, just in case..
                $val = str_replace('"', '', trim($val));

                $tag_attrs[$key] = $val;
            }

            if (!empty($tag_attrs["g"])) {
                $g_val = $tag_attrs["g"];
                unset($tag_attrs["g"]);
                $response = g::run("ui.ProcessGAttribute", $g_val, $tag_attrs, $tc);
            }
            else if (!empty($tag_attrs["data-g"])) {
                $g_val = $tag_attrs["data-g"];
                unset($tag_attrs["data-g"]);
                $response = g::run("ui.ProcessGAttribute", $g_val, $tag_attrs, $tc);
            }
        }

        return $response;
    },
    "ProcessGAttribute" => function ($g_val, $tag_attrs, $tc) {
        $g_val_arr = explode("|", $g_val);

        $operator = $g_val_arr[0];
        $data = (empty($g_val_arr[1]) ? "" : g::get("op.$g_val_arr[1]"));
        $arr = g::get("ui.tmp_data");
        $sub_data = array();

        if (!empty($arr)) {
            if (empty($arr[$g_val_arr[1]])) {
                if (strpos($g_val_arr[1], ".") > -1) {
                    $ps = explode('.', $g_val_arr[1]);
                    $value = $arr;
                    foreach ($ps as $part) {
                        $value = $value[$part];
                    }
                    $sub_data = $value;
                } else {
                    if (!empty($arr[$g_val_arr[1]])) {
                        $data = $arr[$g_val_arr[1]];
                    }
                }
            } else {
                $data = $arr[$g_val_arr[1]];
            }
        }

        $comparison = (empty($g_val_arr[2]) ? "" : $g_val_arr[2]);

        $response = array(
            "ta" => "",
            "tc" => "",
        );

        if ($operator === "remove") {
            $response["remove"] = 1;
            return $response;
        } elseif ($operator === "each") {
            if (!empty($data)) {
                $tmp_data = g::get("ui.tmp_data");
                foreach ($data as $row) {
                    g::set("ui.tmp_data", $row);
                    $response["tc"] .= g::run("ui.ProcessTags", $tc);
                }
                g::set("ui.tmp_data", $tmp_data);
            } else if (!empty($sub_data)) {
                foreach ($sub_data as $row) {
                    g::set("ui.tmp_data", $row);
                    $response["tc"] .= g::run("ui.ProcessTags", $tc);
                }
                g::set("ui.tmp_data", $arr);
            } else {
                $response["tc"] = false;
            }
        } elseif ($operator === "if") {
            if (!empty($comparison)) {
                $comp_arr = explode(":", $comparison);
                $comp_op = $comp_arr[0];
                $comp_val = $comp_arr[1];

                if (($comp_op === "is" || $comp_op === "eq")) {
                    if (strpos($comp_val, ",") > -1) {
                        $comp_val_arr = explode(",", $comp_val);
                        if ($data === false && $comp_val != "false") {
                            $response["remove"] = 1;
                            return $response;
                        }
                        $is_found = 0;
                        foreach ($comp_val_arr as $comp_val) {
                            if (($data == $comp_val)) {
                                $is_found++;
                            }
                        }
                        if (($is_found === 0)) {
                            $response["remove"] = 1;
                            return $response;
                        }
                    } else {
                        if (($data != $comp_val)) {
                            $response["remove"] = 1;
                            return $response;
                        }
                    }
                } elseif (($comp_op === "has")) {
                    if (strpos($comp_val, ",") > -1) {
                        $comp_val_arr = explode(",", $comp_val);
                        if ($data === false && $comp_val != "false") {
                            $response["remove"] = 1;
                            return $response;
                        }
                        $is_found = 0;
                        foreach ($comp_val_arr as $comp_val) {
                            if (in_array($comp_val, $data)) {
                                $is_found++;
                            }
                        }
                        if (($is_found === 0)) {
                            $response["remove"] = 1;
                            return $response;
                        }
                    } else {
                        if (!in_array($comp_val, $data)) {
                            $response["remove"] = 1;
                            return $response;
                        }
                    }
                } elseif (($comp_op === "gt") && ($data <= $comp_val)) {
                    $response["remove"] = 1;
                    return $response;
                } elseif (($comp_op === "lt") && ($data >= $comp_val)) {
                    $response["remove"] = 1;
                    return $response;
                } elseif (($comp_op === "not")) {
                    if (strpos($comp_val, ",") > -1) {
                        $comp_val_arr = explode(",", $comp_val);
                        if ($data === false && $comp_val != "false") {

                        } else {
                            $is_found = 0;
                            foreach ($comp_val_arr as $comp_val) {
                                if (($data == $comp_val)) {
                                    $is_found++;
                                }
                            }
                            if (($is_found > 0)) {
                                $response["remove"] = 1;
                                return $response;
                            }
                        }
                    } else {
                        if (($data == $comp_val)) {
                            $response["remove"] = 1;
                            return $response;
                        }
                    }
                } elseif (($comp_op === "dnh")) {
                    // does not have
                    if (strpos($comp_val, ",") > -1) {
                        $comp_val_arr = explode(",", $comp_val);
                        if ($data === false && $comp_val != "false") {
                            $response["remove"] = 1;
                            return $response;
                        }
                        $is_found = 0;
                        foreach ($comp_val_arr as $comp_val) {
                            if (!in_array($comp_val, $data)) {
                                $is_found++;
                            }
                        }
                        if (($is_found === 0)) {
                            $response["remove"] = 1;
                            return $response;
                        }
                    } else {
                        if (in_array($comp_val, $data)) {
                            $response["remove"] = 1;
                            return $response;
                        }
                    }
                } elseif (($comp_op === "set") && $comp_val == "1") {
                    // value does exist
                    if (empty($data)) {
                        $response["remove"] = 1;
                        return $response;
                    }
                } elseif (($comp_op === "set") && $comp_val == "0") {
                    // value does not exist
                    if (!empty($data)) {
                        $response["remove"] = 1;
                        return $response;
                    }
                }
            }
        } elseif ($operator === "use") {
            $file_path = "clone_ui_folder";
            if (!empty($g_val_arr[2])) {
                $file_path = $g_val_arr[2];
            }
            $file = g::get("config.paths.$file_path") . $g_val_arr[1];
            $tmpl = g::run("tools.LoadFileSimple", $file);
            $response["tc"] = g::run("ui.ProcessTags", $tmpl);
        } elseif ($operator === "html") {
            $response["tc"] = $data;
        }

        $ta = "";
        foreach ($tag_attrs as $key => $value) {
            $ta .= $key . '="' . $value . '" ';
        }

        $response["ta"] = $ta;
        return $response;
    },
    "ProcessKeys" => function ($html) {
        $arr = g::get("ui.tmp_data");
        $re = '/{{(\w.*)}}/msiU';
        if (preg_match_all($re, $html, $keys, PREG_SET_ORDER, 0)) {
            foreach ($keys as $key) {
                $rk = $key[1];
                if (empty($arr)) {
                    $html = str_replace($key[0], g::get("op." . $rk), $html);
                } else {
                    if (is_array($arr)) {
                        // print_r($arr);
                        if (empty($arr[$rk])) {
                            if (strpos($rk, ".") > -1) {
                                $ps = explode('.', $rk);
                                $value = &$arr;
                                foreach ($ps as $part) {
                                    $value = &$value[$part];
                                }
                            } else {
                                $value = &$arr[$rk];
                            }
                        } else {
                            $value = &$arr[$rk];
                        }
                        $html = str_replace($key[0], html_entity_decode($value, ENT_NOQUOTES, "UTF-8"), $html);
                    } else {
                        $html = str_replace($key[0], $arr, $html);
                    }
                }
            }
        }
        return trim($html);
    },
));
