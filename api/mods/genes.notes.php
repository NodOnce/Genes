<?php
g::set("void.notes", array(
    "views" => array(
        "NotesAdd" => array("urls" => array("en" => "notes=add")),
        "NotesEdit" => array("urls" => array("en" => "notes=edit")),
        "NotesDelete" => array("urls" => array("en" => "notes=delete")),
        "Notes" => array("urls" => array("en" => "notes=list")),
    ),
    "bits" => array(
        "notes" => array("en" => "Notes"),
    ),
    "tmpls" => array(),
));

g::def("mods.notes", array(
    "Init" => function () {
        g::set("op.meta.url.mod", "Notes");
    },
    "Notes" => function () {
        g::run("mods.notes.Init");
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
                "filter" => array("g_type" => "note"),
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
                    "g_blurb" => $row["g_blurb"],
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
    "NotesAdd" => function () {
        g::run("mods.notes.Init");
        // echo "AdminItemsAdd called.";
        $post = g::get("post");
        if (!empty($post)) {
            if (empty($post["g_name"])) {
                // Item name is left empty. No!
                g::run("tools.Say", "item-name-left-empty", 5);
                g::run("tools.Redirect");
            } else {
                $name = $post["g_name"];
                $alias = (!empty($post["g_alias"])) ? $post["g_alias"] : g::run("tools.SafeUrl", $name);

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
                        "g_state" => "private",
                        "g_type" => "note",
                        "g_link" => (!empty($post["g_link"])) ? $post["g_link"] : "",
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
    "NotesEdit" => function () {
        g::run("mods.notes.Init");
        // echo "AdminItemsEdit called.";
        $post = g::get("post");
        if (!empty($post)) {
            if (empty($post["g_name"])) {
                // Item name is left empty. No!
                g::run("tools.Say", "item-name-left-empty", 5);
                g::run("tools.Redirect");
            } else {
                $name = $post["g_name"];
                $alias = (!empty($post["g_alias"])) ? $post["g_alias"] : g::run("tools.SafeUrl", $name);

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
                        "g_link" => (!empty($post["g_link"])) ? $post["g_link"] : "",
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
    }
));
