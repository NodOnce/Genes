<?php
g::set("void.admin", array(
    "views" => array(
        // Configuration functions: config.json
        "AdminConfig" => array("urls" => array("en" => array("config", "admin=config"))),
        // Log read functions: sys.log
        "AdminLogs" => array("urls" => array("en" => array("logs", "admin=logs"))),
        // Content management functions
        "AdminItemsAdd" => array("urls" => array("en" => "admin=items;add", "tr" => array("yonetim=icerik;ekle", "icerik-ekle"))),
        "AdminItemsEdit" => array("urls" => array("en" => "admin=items;edit")),
        "AdminItems" => array("urls" => array("en" => "admin=items")),
        // Tag management functions
        "AdminLabelsAdd" => array("urls" => array("en" => "admin=labels;add")),
        "AdminLabelsEdit" => array("urls" => array("en" => "admin=labels;edit")),
        "AdminLabels" => array("urls" => array("en" => "admin=labels")),
        // Event management functions
        "AdminEventsAdd" => array("urls" => array("en" => "admin=events;add")),
        "AdminEventsEdit" => array("urls" => array("en" => "admin=events;edit")),
        "AdminEvents" => array("urls" => array("en" => "admin=events")),
        // User management functions
        "AdminUsersAdd" => array("urls" => array("en" => "admin=users;add")),
        "AdminUsersEdit" => array("urls" => array("en" => "admin=users;edit")),
        "AdminUsers" => array("urls" => array("en" => "admin=users")),
        "AdminProfile" => array("urls" => array("en" => array("profile", "admin=profile"))),
        // General admin page
        "Admin" => array("urls" => array("en" => "admin", "tr" => "yonetim")),
    ),
    "bits" => array(
        "admin" => array("en" => "Dashboard"),
        "admin_config" => array("en" => "Edit Configuration Files"),
        "admin_logs" => array("en" => "Last 100 Log Entries"),
        "admin_items" => array("en" => "Items"),
        "admin_labels" => array("en" => "Labels"),
        "admin_events" => array("en" => "Events"),
        "admin_users" => array("en" => "Users"),
        "admin_profile" => array("en" => "Profile"),
        "msg_ask_delete" => array("en" => "Do you want to delete this?"),
    ),
    "tmpls" => array(),
    "opts" => array(),
    "rules" => array(
        "no" => array(
            "guest" => array("Admin", "AdminUsers", "AdminConfig", "AdminItems", "AdminLabels", "AdminEvents"),
        ),
    ),
));

g::def("mods.admin", array(
    "Init" => function () {
        g::set("op.meta.url.mod", "Admin");
    },
    "Admin" => function () {
        g::run("mods.admin.Init");
        g::run("ui.LoadViewHtml");
    },
    "AdminConfig" => function () {
        g::run("mods.admin.Init");
        $post = g::get("post");
        if (!empty($post)) {
            g::run("tools.UpdateConfigComplete", $post["config"]);
            g::run("tools.Redirect");
        } else {
            $query = g::get("op.meta.url.args.query");

            if (!empty($query) && $query === "all") {
                $cfg = g::run("tools.GetConfigComplete");
                g::set("op.tmp.config", $cfg);
            } else {
                g::run("ui.LoadViewHtml");
            }
        }
    },
    "AdminLogs" => function () {
        g::run("mods.admin.Init");
        $file = g::get("config.paths.clone_log_file");
        $linecount = 101;
        $length = 80;
        $offset_factor = 1;

        $bytes = filesize($file);

        $fp = fopen($file, "r") or die("Can't open $file");

        $complete = false;
        while (!$complete) {
            $offset = $linecount * $length * $offset_factor;
            fseek($fp, -$offset, SEEK_END);
            if ($offset < $bytes) {
                fgets($fp);
            }
            $lines = array();
            while (!feof($fp)) {
                $line = fgets($fp);
                $eline = explode("|", trim($line));
                $ce = count($eline);
                if ($ce > 2) {
                    $line = array(
                        "date" => trim($eline[0]),
                        "render_time" => trim($eline[1]),
                        "render_mem" => trim($eline[2]),
                        "msg" => trim($eline[3]),
                    );
                    array_push($lines, $line);
                } else if ($ce > 1) {
                    $line = array(
                        "date" => trim($eline[0]),
                        "msg" => trim($eline[1]),
                    );
                    array_push($lines, $line);
                } else {
                }
                if (count($lines) > $linecount) {
                    array_shift($lines);
                    $complete = true;
                }
            }
            if ($offset >= $bytes) {
                $complete = true;
            } else {
                $offset_factor *= 2;
            }
        }
        fclose($fp);
        g::set("op.data.logs.list", array_reverse($lines));
        g::run("ui.LoadViewHtml");
    },
    "AdminItemsAdd" => function () {
        g::run("mods.admin.Init");
        // echo "AdminItemsAdd called.";
        $post = g::get("post");
        if (!empty($post)) {
            if (empty($post["g_name"])) {
                // Item name is left empty. No!
                g::run("tools.Say", "item-name-left-empty", 5);
                g::run("tools.Redirect");
            } else {
                $name = $post["g_name"];
                $alias = $post["g_alias"];
                if (empty($alias)) {
                    $alias = g::run("tools.SafeUrl", $name);
                }

                $name_alias_exists = g::run("db.Get", array("*", "items", "g_name='$name' OR g_alias='$alias'"));
                if ($name_alias_exists["total"] != 0) {
                    // Oh, no alias or name exists.
                    g::run("tools.Say", "item-exists", 5);
                    g::run("tools.Redirect");
                } else {
                    $hash = g::run("tools.DTS", 7);

                    $safe_title = $alias;
                    $rename_files = true;
                    $uploaded_images = g::run("tools.JDH", $post["dropzone_files"]);
                    $renamed_images = array();
                    if (!empty($uploaded_images)) {
                        $renamed_images = g::run("core.ProcessUploadsAfterAdd", $uploaded_images, $hash, $rename_files, $safe_title);
                    }
                    $g_media = g::run("tools.JE", array("image" => $renamed_images));

                    if (!empty($post["tss"])) {
                        $tss = $post["tss"];
                    } else {
                        $tss = g::run("tools.DTS", 5);
                    }

                    $row_data = array(
                        "g_state" => $post["g_state"],
                        "g_type" => $post["g_type"],
                        "g_link" => $post["g_link"],
                        "g_hash" => $hash,
                        "g_alias" => "$alias",
                        "g_name" => "$name",
                        "g_blurb" => $post["g_blurb"],
                        "g_text" => $post["g_text"],
                        "g_bits" => g::run("tools.JE", g::run("tools.JDAS", $post["g_bits"])),
                        "g_media" => $g_media,
                        "tss" => $tss,
                    );

                    if (!empty($post["g_labels"])) {
                        $row_data["g_labels"] = gAdminLabelsKeysForDB("item_labels", $post["g_labels"]);
                    }

                    $event_data = array(
                        "g_type" => "item_create",
                        "g_hash" => $hash,
                        "g_key" => $post["g_type"],
                        "g_value" => $name,
                    );

                    $sql_rows = array(
                        array("insert", "items", $row_data), // insert item
                        array("insert", "events", $event_data), // insert event
                    );

                    if (!empty($post["g_labels"])) {
                        $labelstoDBSQL = gAdminLabelsInsertToDB("item", "item_labels", $post["g_labels"]);
                        if ($labelstoDBSQL !== false) {
                            $sql_rows = array_merge($sql_rows, $labelstoDBSQL);
                        }
                    }

                    g::run("db.Prepare", $sql_rows);
                    g::run("tools.Redirect");
                }
            }
        } else {
            g::run("tools.Say", "item-everything-empty", 5);
            g::run("tools.Redirect");
        }
    },
    "AdminItemsEdit" => function () {
        g::run("mods.admin.Init");
        // echo "AdminItemsEdit called.";
        $post = g::get("post");
        if (!empty($post)) {
            if (empty($post["g_name"])) {
                // Item name is left empty. No!
                g::run("tools.Say", "item-name-left-empty", 5);
                g::run("tools.Redirect");
            } else {
                $name = $post["g_name"];
                $alias = $post["g_alias"];
                if (empty($alias)) {
                    $alias = g::run("tools.SafeUrl", $name);
                }

                $id = $post["id"];
                $open_ids = g::run("core.SessionGet", "items_open");
                if (!in_array($id, $open_ids)) {
                    g::run("tools.Say", "id-does-not-exist-in-opens", 5);
                    g::run("tools.Redirect");
                    return false;
                }
                g::run("core.SessionSet", "items_open", null);

                $name_alias_exists = g::run("db.Get", array("*", "items", "(g_name='$name' OR g_alias='$alias') AND id <> $id"));
                if ($name_alias_exists["total"] != 0) {
                    // Oh, no alias or name exists.
                    g::run("tools.Say", "item-exists", 5);
                    g::run("tools.Redirect");
                } else {
                    $hash = g::run("tools.DTS", 7);

                    $safe_title = $alias;
                    $rename_files = true;
                    $uploaded_images = g::run("tools.JDH", $post["dropzone_files"]);
                    if (!empty($uploaded_images)) {
                        $prev_images_query = g::run("db.Get", array("g_media", "items", "id=$id"));
                        $prev_images = g::run("tools.JDAS", $prev_images_query["list"][0]["g_media"]);
                        if (!empty($prev_images["image"])) {
                            $prev_images = $prev_images["image"];
                        } else {
                            $prev_images = array();
                        }

                        $renamed_images = g::run("core.ProcessUploadsAfterEdit", $uploaded_images, $prev_images, $hash, $rename_files, $safe_title);
                        $g_media = g::run("tools.JE", array("image" => $renamed_images));
                    } else {
                        $g_media = g::run("tools.JE", array());
                    }

                    if (!empty($post["tss"])) {
                        $tss = $post["tss"];
                    } else {
                        $tss = g::run("tools.DTS", 5);
                    }

                    $row_data = array(
                        "g_state" => $post["g_state"],
                        "g_type" => $post["g_type"],
                        "g_link" => $post["g_link"],
                        "g_hash" => $hash,
                        "g_alias" => "$alias",
                        "g_name" => "$name",
                        "g_blurb" => $post["g_blurb"],
                        "g_text" => $post["g_text"],
                        "g_bits" => g::run("tools.JE", g::run("tools.JDAS", $post["g_bits"])),
                        "g_media" => $g_media,
                        "tss" => $tss,
                    );

                    if (!empty($post["g_labels"])) {
                        $row_data["g_labels"] = gAdminLabelsKeysForDB("item_labels", $post["g_labels"]);
                    }

                    $event_data = array(
                        "g_type" => "item_update",
                        "g_hash" => $hash,
                        "g_key" => $post["g_type"],
                        "g_value" => $name,
                    );

                    $sql_rows = array(
                        array("update", "items", $row_data, "id='$id'"), // update item
                        array("insert", "events", $event_data), // insert event
                    );

                    if (!empty($post["g_labels"])) {
                        $labelstoDBSQL = gAdminLabelsInsertToDB("item", "item_labels", $post["g_labels"]);
                        if ($labelstoDBSQL !== false) {
                            $sql_rows = array_merge($sql_rows, $labelstoDBSQL);
                        }
                    }
                    g::run("db.Prepare", $sql_rows);
                    g::run("tools.Redirect");
                }
            }
        } else {
            g::run("tools.Say", "item-everything-empty", 5);
            g::run("tools.Redirect");
        }
    },
    "AdminItems" => function () {
        g::run("mods.admin.Init");
        // echo "AdminItems called.";
        $args = g::get("op.meta.url.args");
        $post = g::get("post");

        if (!empty($args["query"]) && $args["query"] === "labels") {
            gAdminLabelsList("item_labels", $post["txt"]);
        } else if (!empty($args["query"]) && $args["query"] === "upload") {
            $files = g::get("files");
            if (!empty($files)) {
                g::run("core.ProcessUploads", $files);
            }
        } else if (!empty($args["query"]) && $args["query"] === "upload-delete") {
            if (!empty($post) && !empty($post["delete_file"])) {
                $file = $post["delete_file"];
                g::run("core.ProcessUploadDeletes", $file);
            }
        } else {
            $args["seq"] = (!empty($args["seq"])) ? $args["seq"] : "items";

            $mixed_query = array(
                "cols" => "*",
                "table" => "items",
                "sort" => array(),
                "filter" => array(),
                "args" => $args,
                "post" => $post,
            );

            $dataset = g::run("db.GetWithSessionPostedQueryArgs", $mixed_query);

            $open_ids = array();
            foreach ($dataset["list"] as $key => $value) {
                $row = $dataset["list"][$key];
                $row["g_labels"] = gAdminLabelsGetValuesDB("item_labels", $row["g_labels"]);
                $row["g_bits"] = g::run("tools.JDAS", $row["g_bits"]);
                $row["g_media"] = g::run("tools.JDAS", $row["g_media"]);
                if (!empty($row["g_media"]) && !empty($row["g_media"]["image"])) {
                    $row["g_media"] = g::run("core.PrepareUploadedImagesEdit", $row["g_media"]["image"]);
                }
                $row = g::run("tools.DirtData", $row);
                $open_ids[] = $row["id"];
                $dataset["list"][$key] = array(
                    "id" => $row["id"],
                    "g_fill" => g::run("tools.JE", $row),
                    "g_name" => $row["g_name"],
                    "g_state" => $row["g_state"],
                    "g_type" => $row["g_type"],
                    "tsc" => $row["tsc"],
                );
            }

            g::run("core.SessionSet", "items_open", $open_ids);
            g::set("op.data.items", $dataset);

            $states = g::run("db.Get", array("g_key", "labels", "g_type='item_states'"));
            $sa = array_column($states["list"], "g_key");
            g::set("op.data.states", $sa);

            $types = g::run("db.Get", array("g_key", "labels", "g_type='item_types'"));
            $ta = array_column($types["list"], "g_key");
            g::set("op.data.types", $ta);

            g::run("ui.LoadViewHtml");
        }
    },
    "AdminLabelsAdd" => function () {
        g::run("mods.admin.Init");
        // echo "AdminLabelsAdd called.";
        $post = g::get("post");
        if (!empty($post)) {
            if (empty($post["g_key"]) && empty($post["g_value"])) {
                // Label key is left empty. No!
                g::run("tools.Say", "label-key-and-value-left-empty", 5);
                g::run("tools.Redirect");
            } else {
                $key = $post["g_key"];
                $context = $post["g_context"];
                $type = $post["g_type"];
                $state = $post["g_state"];
                $value = $post["g_value"];
                if (empty($key)) {
                    $key = g::run("tools.SafeUrl", $value);
                }

                $key_type_context_exists = g::run("db.Get", array("*", "labels", "g_key='$key' AND g_type='$type' AND g_context='$context'"));
                if ($key_type_context_exists["total"] != 0) {
                    // Oh, no alias or name exists.
                    g::run("tools.Say", "label-exists", 5);
                    g::run("tools.Redirect");
                } else {
                    $hash = g::run("tools.DTS", 7);

                    $row_data = array(
                        "g_hash" => $hash,
                        "g_context" => $context,
                        "g_type" => $type,
                        "g_state" => $state,
                        "g_key" => $key,
                        "g_value" => $value,
                        "g_bits" => g::run("tools.JE", g::run("tools.JDAS", $post["g_bits"])),
                    );

                    $event_data = array(
                        "g_type" => "label_create",
                        "g_hash" => $hash,
                        "g_key" => $key,
                        "g_value" => "$context / $type / $value",
                    );

                    $sql_rows = array(
                        array("insert", "labels", $row_data), // insert item
                        array("insert", "events", $event_data), // insert event
                    );

                    g::run("db.Prepare", $sql_rows);
                    g::run("tools.Redirect");
                }
            }
        } else {
            g::run("tools.Say", "label-everything-empty", 5);
            g::run("tools.Redirect");
        }
    },
    "AdminLabelsEdit" => function () {
        g::run("mods.admin.Init");
        // echo "AdminLabelsEdit called.";
        $post = g::get("post");
        if (!empty($post)) {
            if (empty($post["g_key"]) && empty($post["g_value"])) {
                // Label key is left empty. No!
                g::run("tools.Say", "label-key-and-value-left-empty", 5);
                g::run("tools.Redirect");
            } else {
                $key = $post["g_key"];
                $context = $post["g_context"];
                $type = $post["g_type"];
                $state = $post["g_state"];
                $value = $post["g_value"];
                if (empty($key)) {
                    $key = g::run("tools.SafeUrl", $value);
                }

                $id = $post["id"];
                $open_ids = g::run("core.SessionGet", "labels_open");
                if (!in_array($id, $open_ids)) {
                    g::run("tools.Say", "id-does-not-exist-in-opens", 5);
                    g::run("tools.Redirect");
                    return false;
                }
                g::run("core.SessionSet", "labels_open", null);

                $key_type_context_exists = g::run("db.Get", array("*", "labels", "(g_key='$key' AND g_type='$type' AND g_context='$context') AND id <> $id"));
                if ($key_type_context_exists["total"] != 0) {
                    // Oh, no alias or name exists.
                    g::run("tools.Say", "label-exists", 5);
                    g::run("tools.Redirect");
                } else {
                    $hash = g::run("tools.DTS", 7);

                    $row_data = array(
                        "g_context" => $context,
                        "g_type" => $type,
                        "g_state" => $state,
                        "g_key" => $key,
                        "g_value" => $value,
                        "g_bits" => g::run("tools.JE", g::run("tools.JDAS", $post["g_bits"])),
                    );

                    $event_data = array(
                        "g_type" => "label_update",
                        "g_hash" => $hash,
                        "g_key" => $key,
                        "g_value" => "$context / $type / $value",
                    );

                    $sql_rows = array(
                        array("update", "labels", $row_data, "id='$id'"), // insert item
                        array("insert", "events", $event_data), // insert event
                    );

                    g::run("db.Prepare", $sql_rows);
                    g::run("tools.Redirect");
                }
            }
        } else {
            g::run("tools.Say", "item-everything-empty", 5);
            g::run("tools.Redirect");
        }
    },
    "AdminLabels" => function () {
        g::run("mods.admin.Init");
        // echo "AdminLabels called.";
        $args = g::get("op.meta.url.args");
        $post = g::get("post");

        $args["seq"] = (!empty($args["seq"])) ? $args["seq"] : "labels";

        $mixed_query = array(
            "cols" => "*",
            "table" => "labels",
            "sort" => array(),
            "filter" => array(),
            "args" => $args,
            "post" => $post,
        );

        $dataset = g::run("db.GetWithSessionPostedQueryArgs", $mixed_query);

        $open_ids = array();
        foreach ($dataset["list"] as $key => $value) {
            $row = $dataset["list"][$key];
            $row["g_bits"] = g::run("tools.JDAS", $row["g_bits"]);
            $row = g::run("tools.DirtData", $row);
            $open_ids[] = $row["id"];
            $dataset["list"][$key] = array(
                "id" => $row["id"],
                "g_fill" => g::run("tools.JE", $row),
                "g_context" => $row["g_context"],
                "g_key" => $row["g_key"],
                "g_value" => $row["g_value"],
                "g_state" => $row["g_state"],
                "g_type" => $row["g_type"],
                "tsc" => $row["tsc"],
            );
        }
        g::run("core.SessionSet", "labels_open", $open_ids);
        g::set("op.data.labels", $dataset);

        $states = g::run("db.Get", array("g_key", "labels", "g_type='label_states'"));
        $sa = array_column($states["list"], "g_key");
        g::set("op.data.states", $sa);

        $types = g::run("db.Get", array("g_key", "labels", "g_type='label_types'"));
        $ta = array_column($types["list"], "g_key");
        g::set("op.data.types", $ta);

        g::run("ui.LoadViewHtml");
    },
    "AdminEventsAdd" => function () {
        g::run("mods.admin.Init");
        echo "AdminEventsAdd called.";
    },
    "AdminEventsEdit" => function () {
        g::run("mods.admin.Init");
        echo "AdminEventsEdit called.";
    },
    "AdminEvents" => function () {
        g::run("mods.admin.Init");
        // echo "AdminEvents called.";
        $args = g::get("op.meta.url.args");
        $post = g::get("post");

        if (!empty($args["query"]) && $args["query"] === "labels") {
            gAdminLabelsList("event_labels", $post["txt"]);
        }
        $args["seq"] = (!empty($args["seq"])) ? $args["seq"] : "events";

        $mixed_query = array(
            "cols" => "*",
            "table" => "events",
            "sort" => array(),
            "filter" => array(),
            "args" => $args,
            "post" => $post,
        );

        $dataset = g::run("db.GetWithSessionPostedQueryArgs", $mixed_query);

        $open_ids = array();
        foreach ($dataset["list"] as $key => $value) {
            $row = $dataset["list"][$key];
            $row["g_labels"] = gAdminLabelsGetValuesDB("event_labels", $row["g_labels"]);
            $row["g_bits"] = g::run("tools.JDAS", $row["g_bits"]);
            $row = g::run("tools.DirtData", $row);
            $open_ids[] = $row["id"];
            $dataset["list"][$key] = array(
                "id" => $row["id"],
                "g_fill" => g::run("tools.JE", $row),
                "g_key" => $row["g_key"],
                "g_value" => $row["g_value"],
                "g_state" => $row["g_state"],
                "g_type" => $row["g_type"],
                "tsc" => $row["tsc"],
            );
        }
        g::run("core.SessionSet", "events_open", $open_ids);
        g::set("op.data.events", $dataset);

        $states = g::run("db.Get", array("g_key", "labels", "g_type='event_states'"));
        $sa = array_column($states["list"], "g_key");
        g::set("op.data.states", $sa);

        $types = g::run("db.Get", array("g_key", "labels", "g_type='event_types'"));
        $ta = array_column($types["list"], "g_key");
        g::set("op.data.types", $ta);

        g::run("ui.LoadViewHtml");
    },
    "AdminUsersAdd" => function () {
        g::run("mods.admin.Init");
        // echo "AdminUsersAdd called.";
        $post = g::get("post");
        if (!empty($post)) {
            if (empty($post["g_email"])) {
                // Email is left empty. No!
                g::run("tools.Say", "user-email-left-empty", 5);
                g::run("tools.Redirect");
            } else {
                $alias = $post["g_alias"];
                $email = $post["g_email"];
                $pwd = $post["g_pwd"];
                $salt = g::get("config.clone.user_salt");

                $email_alias_exists = g::run("db.Get", array("*", "users", "g_email='$email' OR g_alias='$alias'"));
                if ($email_alias_exists["total"] != 0) {
                    // Oh, no alias or email exists.
                    g::run("tools.Say", "user-exists", 5);
                    g::run("tools.Redirect");
                } else {
                    $hash = g::run("tools.DTS", 7);

                    $safe_title = g::run("tools.SafeUrl", $post["g_alias"]);
                    $rename_files = true;
                    $uploaded_images = g::run("tools.JDH", $post["dropzone_files"]);
                    $renamed_images = array();
                    if (!empty($uploaded_images)) {
                        $renamed_images = g::run("core.ProcessUploadsAfterAdd", $uploaded_images, $hash, $rename_files, $safe_title);
                    }
                    $g_media = g::run("tools.JE", array("image" => $renamed_images));

                    $row_data = array(
                        "g_state" => $post["g_state"],
                        "g_type" => $post["g_type"],
                        "g_hash" => $hash,
                        "g_alias" => "$alias",
                        "g_email" => "$email",
                        "g_blurb" => $post["g_blurb"],
                        "g_text" => $post["g_text"],
                        "g_bits" => g::run("tools.JE", g::run("tools.JDAS", $post["g_bits"])),
                        "g_media" => $g_media,
                    );

                    if (!empty($pwd)) {
                        $row_data["g_pwd"] = g::run("crypt.MakeSaltySecret", $pwd, $salt);
                    }

                    if (!empty($post["g_labels"])) {
                        $row_data["g_labels"] = gAdminLabelsKeysForDB("user_labels", $post["g_labels"]);
                    }

                    $event_data = array(
                        "g_type" => "user_create",
                        "g_hash" => $hash,
                        "g_key" => $email,
                    );

                    $sql_rows = array(
                        array("insert", "users", $row_data), // insert user
                        array("insert", "events", $event_data), // insert event
                    );
                    $labelstoDBSQL = gAdminLabelsInsertToDB("user", "user_labels", $post["g_labels"]);
                    if ($labelstoDBSQL !== false) {
                        $sql_rows = array_merge($sql_rows, $labelstoDBSQL);
                    }

                    g::run("db.Prepare", $sql_rows);
                    g::run("tools.Redirect");
                }
            }
        } else {
            g::run("tools.Say", "user-everything-empty", 5);
            g::run("tools.Redirect");
        }
    },
    "AdminUsersEdit" => function () {
        g::run("mods.admin.Init");
        // echo "AdminUsersEdit called.";
        $post = g::get("post");
        if (!empty($post)) {
            if (empty($post["g_email"])) {
                // Email is left empty. No!
                g::run("tools.Say", "user-email-left-empty", 5);
                g::run("tools.Redirect");
            } else {
                $alias = $post["g_alias"];
                $email = $post["g_email"];

                // extra safe layer be sure that at least it is one of the open users
                $id = $post["id"];
                $open_ids = g::run("core.SessionGet", "users_open");
                if (!in_array($id, $open_ids)) {
                    g::run("tools.Say", "id-does-not-exist-in-opens", 5);
                    g::run("tools.Redirect");
                    return false;
                }
                g::run("core.SessionSet", "users_open", null);

                $pwd = $post["g_pwd"];
                $salt = g::get("config.clone.user_salt");

                $email_alias_exists = g::run("db.Get", array("*", "users", "(g_email='$email' OR g_alias='$alias') AND id <> $id"));
                if ($email_alias_exists["total"] != 0) {
                    // Oh, no alias or email exists.
                    g::run("tools.Say", "user-exists", 5);
                    g::run("tools.Redirect");
                } else {
                    $hash = g::run("tools.DTS", 7);

                    $safe_title = g::run("tools.SafeUrl", $post["g_alias"]);
                    $rename_files = true;
                    $uploaded_images = g::run("tools.JDH", $post["dropzone_files"]);
                    if (!empty($uploaded_images)) {
                        $prev_images_query = g::run("db.Get", array("g_media", "users", "id=$id"));
                        $prev_images = g::run("tools.JDAS", $prev_images_query["list"][0]["g_media"]);
                        $prev_images = $prev_images["image"];

                        $renamed_images = g::run("core.ProcessUploadsAfterEdit", $uploaded_images, $prev_images, $hash, $rename_files, $safe_title);
                        $g_media = g::run("tools.JE", array("image" => $renamed_images));
                    } else {
                        $g_media = g::run("tools.JE", array());
                    }

                    $row_data = array(
                        "g_state" => $post["g_state"],
                        "g_type" => $post["g_type"],
                        "g_hash" => $hash,
                        "g_alias" => "$alias",
                        "g_email" => "$email",
                        "g_blurb" => $post["g_blurb"],
                        "g_text" => $post["g_text"],
                        "g_bits" => g::run("tools.JE", g::run("tools.JDAS", $post["g_bits"])),
                        "g_media" => $g_media,
                    );

                    if (!empty($pwd)) {
                        $row_data["g_pwd"] = g::run("crypt.MakeSaltySecret", $pwd, $salt);
                    }

                    if (!empty($post["g_labels"])) {
                        $row_data["g_labels"] = gAdminLabelsKeysForDB("user_labels", $post["g_labels"]);
                    }

                    $event_data = array(
                        "g_type" => "user_update",
                        "g_hash" => $hash,
                        "g_key" => "$email",
                    );

                    $sql_rows = array(
                        array("update", "users", $row_data, "id='$id'"), // update user
                        array("insert", "events", $event_data), // insert event
                    );
                    $labelstoDBSQL = gAdminLabelsInsertToDB("user", "user_labels", $post["g_labels"]);
                    if ($labelstoDBSQL !== false) {
                        $sql_rows = array_merge($sql_rows, $labelstoDBSQL);
                    }
                    g::run("db.Prepare", $sql_rows);
                    g::run("tools.Redirect");
                }
            }
        } else {
            g::run("tools.Say", "user-everything-empty", 5);
            g::run("tools.Redirect");
        }
    },
    "AdminUsersDelete" => function () {
        g::run("mods.admin.Init");
        // echo "AdminUsersDelete called.";
        $get = g::get("get");
        print_r($get);
        die;
    },
    "AdminUsers" => function () {
        g::run("mods.admin.Init");
        // echo "AdminUsers called.";
        $args = g::get("op.meta.url.args");
        $post = g::get("post");

        if (!empty($args["query"]) && $args["query"] === "labels") {
            gAdminLabelsList("user_labels", $post["txt"]);
        } else if (!empty($args["query"]) && $args["query"] === "upload") {
            $files = g::get("files");
            if (!empty($files)) {
                g::run("core.ProcessUploads", $files);
            }
        } else if (!empty($args["query"]) && $args["query"] === "upload-delete") {
            if (!empty($post) && !empty($post["delete_file"])) {
                $file = $post["delete_file"];
                g::run("core.ProcessUploadDeletes", $file);
            }
        }

        $args["seq"] = (!empty($args["seq"])) ? $args["seq"] : "users";

        $mixed_query = array(
            "cols" => "*",
            "table" => "users",
            "sort" => array(),
            "filter" => array(),
            "args" => $args,
            "post" => $post,
        );

        $dataset = g::run("db.GetWithSessionPostedQueryArgs", $mixed_query);

        $open_ids = array();
        foreach ($dataset["list"] as $key => $value) {
            $row = $dataset["list"][$key];
            $row["g_labels"] = gAdminLabelsGetValuesDB("user_labels", $row["g_labels"]);
            $row["g_bits"] = g::run("tools.JDAS", $row["g_bits"]);
            $row["g_media"] = g::run("tools.JDAS", $row["g_media"]);
            if (!empty($row["g_media"]) && !empty($row["g_media"]["image"])) {
                $row["g_media"] = g::run("core.PrepareUploadedImagesEdit", $row["g_media"]["image"]);
            }
            $row = g::run("tools.DirtData", $row);
            $open_ids[] = $row["id"];
            $dataset["list"][$key] = array(
                "id" => $row["id"],
                "g_fill" => g::run("tools.JE", $row),
                "g_alias" => $row["g_alias"],
                "g_email" => $row["g_email"],
                "g_state" => $row["g_state"],
                "g_type" => $row["g_type"],
                "tsc" => $row["tsc"],
            );
        }
        g::run("core.SessionSet", "users_open", $open_ids);
        g::set("op.data.users", $dataset);

        $states = g::run("db.Get", array("g_key", "labels", "g_type='user_states'"));
        $sa = array_column($states["list"], "g_key");
        g::set("op.data.states", $sa);

        $types = g::run("db.Get", array("g_key", "labels", "g_type='user_types'"));
        $ta = array_column($types["list"], "g_key");
        g::set("op.data.types", $ta);

        g::run("ui.LoadViewHtml");
    },
    "AdminProfile" => function () {
        g::run("mods.admin.Init");
        // echo "AdminProfile called.";
        g::run("ui.LoadViewHtml");
    },
));

function gAdminLabelsList($type, $txt)
{
    $labels_db = g::run("db.Get", array("g_key AS 'key', g_value AS 'value'", "labels", "g_type='$type' AND (g_key LIKE '%$txt%' OR g_value LIKE '%$txt%')"));
    $output = array();
    if ($labels_db["total"] > 0) {
        $output = $labels_db["list"];
    }
    g::set("op.data.labels", $output);
    g::run("tools.ExitWithOpDataResponse");
    die;
}
function gAdminLabelsKeysForDB($type, $posted_labels)
{
    $labels = g::run("tools.JDAS", $posted_labels);
    $labels_arr = array();
    if (!empty($labels)) {
        foreach ($labels as $label) {$labels_arr[] = g::run("tools.ToAscii", $label["value"]);}
    }
    return g::run("tools.JE", array($type => $labels_arr));
}
function gAdminLabelsGetValuesDB($type, $db_labels, $is_js_encoded = true)
{
    $labels_arr = $db_labels;
    if ($is_js_encoded) {
        $curr_labels = g::run("tools.JDAS", $labels_arr);
        $labels_arr = $curr_labels[$type];
    }

    if (!empty($labels_arr)) {
        $labels_str = implode('","', $labels_arr);
        $labels_db = g::run("db.Get", array("g_key AS 'key', g_value AS 'value'", "labels", "g_type='$type' AND g_key IN (\"$labels_str\")"));
        if ($labels_db["total"] > 0) {
            return $labels_db["list"];
        } else {
            return array();
        }
    } else {
        return array();
    }
}
function gAdminLabelsInsertToDB($context, $type, $posted_labels)
{
    $labels = g::run("tools.JDAS", $posted_labels);
    $sqls = array();
    if (!empty($labels)) {
        foreach ($labels as $label) {
            $sqls[] = array(
                "insert_ine",
                "labels",
                array(
                    "g_context" => $context,
                    "g_type" => $type,
                    "g_key" => g::run("tools.ToAscii", $label["value"]),
                    "g_value" => $label["value"],
                ),
                array("g_type", "g_key", "g_value"));
        }
    }
    return $sqls;
}
function gAdminLabelsKeysForMultiDBInsertToDB($labels_md_array)
{
    $result = array(
        "g_labels" => "",
        "label_sqls" => array(),
    );

    $sqls = array();
    $labels_array = array();
    foreach ($labels_md_array as $context => $details) {
        foreach ($details as $key => $value) {
            $labels_arr = array();
            $labels_md_array[$key] = g::run("tools.JDAS", $value);
            $labels = $labels_md_array[$key];
            foreach ($labels as $label) {
                $labels_arr[] = g::run("tools.ToAscii", $label["value"]);
                $sqls[] = array(
                    "insert_ine",
                    "labels",
                    array(
                        "g_context" => $context,
                        "g_type" => $key,
                        "g_key" => g::run("tools.ToAscii", $label["value"]),
                        "g_value" => $label["value"],
                    ),
                    array("g_type", "g_key", "g_value"));
            }
            $labels_array[$key] = $labels_arr;
        }
    }

    $result = array(
        "g_labels" => g::run("tools.JE", $labels_array),
        "label_sqls" => $sqls,
    );

    return $result;
}
