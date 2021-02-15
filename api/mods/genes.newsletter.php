<?php
g::set("void.newsletter", array(
    "views" => array(
        "Subscribe" => array("urls" => array("en" => "subscribe")),
        "Unsubscribe" => array("urls" => array("en" => "unsubscribe")),
        "ListSubscribers" => array("urls" => array("en" => "list-subscribers")),
    ),
    "bits" => array(),
    "tmpls" => array(),
));
g::def("mods.newsletter", array(
    "Setup" => function () {
        $db_is_connected = g::run("db.IsConnected");
        $db_is_proper = g::get("config.checks.db_is_proper");
        $newsletter_is_init = g::get("config.checks.newsletter_is_init");
        $tables = g::get("config.db.tables");

        if ($db_is_connected && $db_is_proper == 1 && $newsletter_is_init != 1) {
            $sql_rows = array(
                array(
                    "insert_ine",
                    "settings",
                    array("label" => "Setting Type", "value" => "setting_type", "type" => "setting_type"),
                    array("value", "type"),
                ),
                array(
                    "insert_ine",
                    "settings",
                    array("label" => "User Type", "value" => "user_type", "type" => "setting_type"),
                    array("value", "type"),
                ),
                array(
                    "insert_ine",
                    "settings",
                    array("label" => "Newsletter", "value" => "newsletter", "type" => "user_type"),
                    array("value", "type"),
                ),
            );
            g::run("db.Prepare", $sql_rows);
            g::run("tools.UpdateConfigCheck", "newsletter_is_init", 1);
        }
    },
    "Subscribe" => function () {
        //g("op.html", "Subscribe.");
        $hashes = g::run("crypt.MicroNowArr");

        $date = $hashes["proper_date"];
        $id = $hashes["hash_date"];
        $hash = $hashes["micro_hash"];

        $email = g::get("post.email");

        $sql_rows = array();
        $user_exists_hash = g::run("db.Get", array("hash", "users", "email='$email'", 1));
        if (empty($user_exists_hash["data"])) {
            $sql_rows = array(
                // insert user
                array(
                    "insert",
                    "users",
                    array("id" => "$id", "hash" => "$hash", "email" => "$email", "type" => "newsletter"),
                ),
                // insert event
                array(
                    "insert",
                    "events",
                    array("value" => "$hash", "type" => "newsletter", "state" => "subscribed", "date_state" => "$date"),
                ),
            );
            g::run("tools.Say", "Subscribed new user.", 1);
        } else {
            $user_exists_hash = $user_exists_hash["data"]["hash"];
            $user_event_exists = g::run("db.Get", array("hash, js_bits", "events", "value='$user_exists_hash' AND type='newsletter'", 1));
            if (empty($user_event_exists["data"])) {
                // insert event
                $sql_rows = array(
                    array(
                        "insert",
                        "events",
                        array("value" => "$user_exists_hash", "type" => "newsletter", "state" => "subscribed", "date_state" => "$date"),
                    ),
                );
                g::run("tools.Say", "Subscribed existing user.", 1);
            } else {
                $event_hash = $user_event_exists["data"]["hash"];
                $event_js_bits = $user_event_exists["data"]["js_bits"];
                // update event
                $sql_rows = array(
                    array(
                        "update",
                        "events",
                        array("state" => "subscribed", "date_state" => "$date", "js_bits" => "$event_js_bits"),
                        "hash='$event_hash'",
                    ),
                );
                g::run("tools.Say", "Re-subscribed existing user.", 1);
            }
        }
        g::run("db.Prepare", $sql_rows);
        g::run("tools.Redirect");
    },
    "Unsubscribe" => function () {
        g::set("op.html", "Unsubscribe.");
    },
    "ListSubscribers" => function () {
        g::set("op.html", "Listing Subscribers.");
    }
));
g::run("mods.newsletter.Setup");
