<?php
g::def("db", array(
    "ConnectIfAvailable" => function () {
        $db_conns = g::get("config.db.conns");
        foreach ($db_conns as $key => $value) {
            $db_key = $key;
            break;
        }
        if (!empty($db_conns[$db_key]["path"])) {
            g::run("db.Connect", $db_conns);
            g::set("op.meta.clone.db", 1);
        } else {
            $msg = "Default DB path is not given. Will not connect to DB.";
            g::run("tools.Say", $msg);
            g::set("op.meta.clone.db", 0);
        }
    },
    "IsConnected" => function () {
        $config_db_conns = g::get("config.db.conns");
        foreach ($config_db_conns as $conn_name => $details) {
            $conn = g::get("db.conns.$conn_name");
            if (empty($conn)) {
                return false;
            }
        }
        return true;
    },
    "Connect" => function ($db_conns) {
        foreach ($db_conns as $conn_name => $conn_details) {
            if ($conn_details["type"] == "mysql") {
                g::run("db.ConnectMySql", $conn_name, $conn_details);
            } elseif ($conn_details["type"] == "sqlite") {
                g::run("db.ConnectSQLite", $conn_name, $conn_details);
            } elseif ($conn_details["type"] == "mongodb") {
                g::run("db.ConnectMongoDB", $conn_name, $conn_details);
            }
        }
    },
    "ConnectMySql" => function ($key, $cd) {
        g::run("tools.Say", "Connected to MySQL: $key");

        // try connecting...
        try {
            if (!empty($cd["name"]) && !empty($cd["user"]) && !empty($cd["pass"])) {
                $dsn = "mysql:host=" . $cd["path"] . ";dbname=" . $cd["name"] . ";charset=" . $cd["charset"];
                $opt = array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                );
                $conn = new PDO($dsn, $cd["user"], $cd["pass"], $opt);
                g::set("db.conns.$key", $conn);
            } else {
                $msg = "DB name, user, pass information is not given can not connect to db.";
                g::run("tools.Say", $msg);
                echo $msg;
                die;
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            g::run("tools.Say", $msg);
            echo $msg;
            die;
        }

        // if there is no settings create tables and enter defaults.
        // and then try connecting again...
        g::run("db.CheckDBStructure");
    },
    "ConnectSQLite" => function ($key, $cd) {
        g::run("tools.Say", "Connected to SQLite: $key");
    },
    "ConnectMongoDB" => function ($key, $cd) {
        g::run("tools.Say", "Connected to MongoDB: $key");

        // try connecting..
        try {
            if (!empty($cd["name"]) && !empty($cd["user"]) && !empty($cd["pass"])) {
                $conn = new MongoDB\Driver\Manager("mongodb://" . $cd["path"]);
                g::set("db.conns.$key", $conn);
            } else {
                $msg = "DB name, user, pass information is not given can not connect to db.";
                g::run("tools.Say", $msg);
                echo $msg;
                die;
            }
        } catch (MongoDBDriverExceptionException $e) {
            $msg = $e->getMessage();
            g::run("tools.Say", $msg);
            echo $msg;
            die;
        }
    },
    "Disconnect" => function () {
    },
    "InsertCloneHash" => function () {
        // insert clone
        $config_clone = g::get("config.clone");
        $clone_hash = $config_clone["hash"];
        $clone_alias = $config_clone["alias"];
        $clone_name = $config_clone["name"];
        $clone_table = g::get("config.db.tables.clones");
        $query_sqls = array();
        $query_sqls[$clone_table[0]]["create_clone"] = array("INSERT INTO " . $clone_table[1] . "(`g_hash`, `g_alias`, `g_name`) VALUES ('$clone_hash', '$clone_alias', '$clone_name')");
        // run query function with sql array
        g::run("db.Execute", $query_sqls);
    },
    "CreateMissingTables" => function () {
        $tables = g::get("config.db.tables");
        $query_sqls = array();
        $not_exists = array();

        foreach ($tables as $key => $info) {
            $table_conn = $info[0];
            $table_name = $info[1];
            if (empty($not_exists[$table_conn])) {
                $not_exists[$table_conn] = array();
            }
            $sql = "SELECT 1 FROM $table_name LIMIT 1";
            $val = array();
            $query_sqls[$table_conn][$key] = array($sql, $val);
        }
        $response = g::run("db.Execute", $query_sqls);
        if (!empty($response["error"])) {
            $errors = $response["error"];
            // if missing a table select this.
            foreach ($errors as $table_key => $error_msg) {
                g::run("db.CreateTables", $table_key);
            }
        }
    },
    "CheckDBStructure" => function ($create_missing = true) {
        $db_is_proper = g::get("config.checks.db_is_proper");
        // Admin thinks db is not proper
        if ($db_is_proper !== 1) {
            g::run("db.CreateMissingTables");
            $cid = g::run("db.GetCloneId");
            if ($cid === false) {
                // Clone info is not matched to db clone table
                g::run("db.InsertCloneHash");
                g::set("config.checks.db_is_proper", 1);
                g::run("core.SessionSet", "cid", null);
                g::run("db.CreateDefaultLabels");
            }
            g::run("tools.UpdateConfigCheck", "db_is_proper", 1);
        }
    },
    "CreateTables" => function ($table_key, $format = false) {
        // echo "$table_key\n";
        $table_sql = array(
            "clones" => array(
                "name" => "",
                "cols" => array(
                    "id" => array("INT", "PRIMARY", "AUTOINC", "NOT NULL"),
                    "g_state" => array("VARCHAR|255"),
                    "g_type" => array("VARCHAR|255"),
                    "g_hash" => array("VARCHAR|15", "NOT NULL"),
                    "g_alias" => array("VARCHAR|255"),
                    "g_name" => array("VARCHAR|255"),
                    "g_blurb" => array("VARCHAR|767"),
                    "g_text" => array("MEDIUMTEXT"),
                    "g_bits" => array("JSON"),
                    "g_labels" => array("JSON"),
                    "tsc" => array("TIMESTAMP|DEFAULT"),
                    "uidc" => array("INT"),
                    "tsu" => array("TIMESTAMP"),
                    "uidu" => array("INT"),
                    "del" => array("TINYINT"),
                ),
                "extras" => array(
                    array("unique" => "id"),
                    array("unique" => "g_hash"),
                    array("unique" => "g_alias"),
                ),
            ),
            "users" => array(
                "name" => "",
                "cols" => array(
                    "id" => array("INT", "PRIMARY", "AUTOINC", "NOT NULL"),
                    "g_state" => array("VARCHAR|255"),
                    "g_type" => array("VARCHAR|255"),
                    "g_hash" => array("VARCHAR|15", "NOT NULL"),
                    "g_alias" => array("VARCHAR|255", "NULL"),
                    "g_email" => array("VARCHAR|255", "NOT NULL"),
                    "g_pwd" => array("VARCHAR|255", "NOT NULL"),
                    "g_blurb" => array("VARCHAR|767"),
                    "g_text" => array("MEDIUMTEXT"),
                    "g_bits" => array("JSON"),
                    "g_media" => array("JSON"),
                    "g_labels" => array("JSON"),
                    "cid" => array("INT"),
                    "tsc" => array("TIMESTAMP|DEFAULT"),
                    "uidc" => array("INT"),
                    "tsu" => array("TIMESTAMP"),
                    "uidu" => array("INT"),
                    "del" => array("TINYINT"),
                ),
                "extras" => array(
                    array("unique" => "id"),
                    array("unique" => "g_hash"),
                    array("unique" => array("clone_user_email" => array("g_email", "cid"))),
                    array("unique" => array("clone_user_alias" => array("g_alias", "cid"))),
                ),
            ),
            "labels" => array(
                "name" => "",
                "cols" => array(
                    "id" => array("INT", "PRIMARY", "AUTOINC", "NOT NULL"),
                    "g_state" => array("VARCHAR|255"),
                    "g_type" => array("VARCHAR|255", "NOT NULL"),
                    "g_context" => array("VARCHAR|255"),
                    "g_hash" => array("VARCHAR|15", "NOT NULL"),
                    "g_key" => array("VARCHAR|255", "NOT NULL"),
                    "g_value" => array("VARCHAR|255"),
                    "g_bits" => array("JSON"),
                    "cid" => array("INT"),
                    "tsc" => array("TIMESTAMP|DEFAULT"),
                    "uidc" => array("INT"),
                    "tsu" => array("TIMESTAMP"),
                    "uidu" => array("INT"),
                    "del" => array("TINYINT"),
                ),
                "extras" => array(
                    array("unique" => "id"),
                    array("unique" => array("clone_type_label" => array("g_key", "g_type", "g_context", "cid"))),
                ),
            ),
            "items" => array(
                "name" => "",
                "cols" => array(
                    "id" => array("INT", "PRIMARY", "AUTOINC", "NOT NULL"),
                    "g_state" => array("VARCHAR|255"),
                    "g_type" => array("VARCHAR|255"),
                    "g_hash" => array("VARCHAR|15", "NOT NULL"),
                    "g_alias" => array("VARCHAR|255", "NOT NULL"),
                    "g_link" => array("VARCHAR|255"),
                    "g_name" => array("VARCHAR|255", "NOT NULL"),
                    "g_blurb" => array("VARCHAR|767"),
                    "g_text" => array("MEDIUMTEXT"),
                    "g_bits" => array("JSON"),
                    "g_media" => array("JSON"),
                    "g_labels" => array("JSON"),
                    "tss" => array("TIMESTAMP"),
                    "tse" => array("TIMESTAMP"),
                    "cid" => array("INT"),
                    "tsc" => array("TIMESTAMP|DEFAULT"),
                    "uidc" => array("INT"),
                    "tsu" => array("TIMESTAMP"),
                    "uidu" => array("INT"),
                    "del" => array("TINYINT"),
                ),
                "extras" => array(
                    array("unique" => "id"),
                    array("unique" => array("clone_item_alias" => array("g_alias", "cid"))),
                    array("unique" => array("clone_item_name" => array("g_name", "cid"))),
                ),
            ),
            "events" => array(
                "name" => "",
                "cols" => array(
                    "id" => array("INT", "PRIMARY", "AUTOINC", "NOT NULL"),
                    "g_state" => array("VARCHAR|255"),
                    "g_type" => array("VARCHAR|255"),
                    "g_hash" => array("VARCHAR|15", "NOT NULL"),
                    "g_key" => array("VARCHAR|255"),
                    "g_value" => array("VARCHAR|767"),
                    "g_void" => array("VARCHAR|767"),
                    "g_bits" => array("JSON"),
                    "g_labels" => array("JSON"),
                    "cid" => array("INT"), // clone id
                    "lid" => array("INT"), // label id
                    "uid" => array("INT"), // user id
                    "iid" => array("INT"), // item id
                    "tss" => array("TIMESTAMP"), // timestamp start
                    "tse" => array("TIMESTAMP"), // timestamp end
                    "tsc" => array("TIMESTAMP|DEFAULT"), // timestamp create
                    "uidc" => array("INT"), // user id create
                    "tsu" => array("TIMESTAMP"), // timestamp update
                    "uidu" => array("INT"), // user id update
                    "del" => array("TINYINT"),
                ),
                "extras" => array(
                ),
            ),
        );

        $query_sqls = array();

        if ($format) {
            $table_info = g::get("config.db.tables");
            foreach ($table_info as $key => $details) {
                $table_conn = $details[0];
                $db_type = g::get("config.db.conns.$table_conn.type");
                $table_name = $details[1];
                $table_sql[$key]["name"] = $table_name;
                $query_sqls[$table_conn]["delete_$table_name"] = array("DROP TABLE IF EXISTS $table_name");
                $query_sqls[$table_conn]["create_$table_name"] = array(g::run("db.GenerateCreateTablesSql", $db_type, $table_sql[$key]));
            }

            // insert clone
            $config_clone = g::get("config.clone");
            $clone_hash = $config_clone["hash"];
            $clone_alias = $config_clone["alias"];
            $clone_name = $config_clone["name"];
            $clone_table = g::get("config.db.tables.clones");
            $query_sqls[$clone_table[0]]["create_clone"] = array("INSERT INTO " . $clone_table[1] . "(`g_hash`, `g_alias`, `g_name`) VALUES ('$clone_hash', '$clone_alias', '$clone_name')");
            //print_r($query_sqls);die;
            // run query function with sql array
            g::run("db.Execute", $query_sqls);
        } else {
            $table_info = g::get("config.db.tables.$table_key");
            $table_conn = $table_info[0];
            $db_type = g::get("config.db.conns.$table_conn.type");
            $table_name = $table_info[1];
            $table_sql[$table_key]["name"] = $table_name;
            $query_sqls[$table_conn]["delete_$table_name"] = array("DROP TABLE IF EXISTS $table_name");
            $query_sqls[$table_conn]["create_$table_name"] = array(g::run("db.GenerateCreateTablesSql", $db_type, $table_sql[$table_key]));

            if ($table_key === "clones") {
                // insert clone
                $config_clone = g::get("config.clone");
                $clone_hash = $config_clone["hash"];
                $clone_alias = $config_clone["alias"];
                $clone_name = $config_clone["name"];
                $clone_table = g::get("config.db.tables.clones");
                $query_sqls[$clone_table[0]]["create_clone"] = array("INSERT INTO " . $clone_table[1] . "(`g_hash`, `g_alias`, `g_name`) VALUES ('$clone_hash', '$clone_alias', '$clone_name')");
            }
            //print_r($query_sqls);die;
            g::run("db.Execute", $query_sqls);
        }
    },
    "CreateDefaultLabels" => function () {
        // ENTER DEFAULT GENES LABELS
        $sql_rows = array(
            // basic two options
            array("insert_ine", "labels", array("g_state" => "system", "g_type" => "label_types", "g_context" => "clone", "g_key" => "label_types"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "system", "g_type" => "label_types", "g_context" => "clone", "g_key" => "label_states"), array("g_type", "g_context", "g_key")),
            // default label states :: draft, private, public
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "label_states", "g_context" => "label", "g_key" => "draft"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "label_states", "g_context" => "label", "g_key" => "private"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "label_states", "g_context" => "label", "g_key" => "public"), array("g_type", "g_context", "g_key")),
            // basic label types
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "label_types", "g_context" => "clone", "g_key" => "event_types"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "label_types", "g_context" => "clone", "g_key" => "user_types"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "label_types", "g_context" => "clone", "g_key" => "item_types"), array("g_type", "g_context", "g_key")),
            // basic label type :: states
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "label_types", "g_context" => "clone", "g_key" => "event_states"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "label_types", "g_context" => "clone", "g_key" => "user_states"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "label_types", "g_context" => "clone", "g_key" => "item_states"), array("g_type", "g_context", "g_key")),
            // basic label types :: labels
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "label_types", "g_context" => "clone", "g_key" => "event_labels"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "label_types", "g_context" => "clone", "g_key" => "user_labels"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "label_types", "g_context" => "clone", "g_key" => "item_labels"), array("g_type", "g_context", "g_key")),
            // default event states :: single, session, history
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_states", "g_context" => "event", "g_key" => "single"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_states", "g_context" => "event", "g_key" => "session"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_states", "g_context" => "event", "g_key" => "history"), array("g_type", "g_context", "g_key")),
            // default user states :: active, inactive
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "user_states", "g_context" => "user", "g_key" => "active"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "user_states", "g_context" => "user", "g_key" => "inactive"), array("g_type", "g_context", "g_key")),
            // default item states :: draft, public
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "item_states", "g_context" => "item", "g_key" => "draft"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "item_states", "g_context" => "item", "g_key" => "public"), array("g_type", "g_context", "g_key")),
            // default event types :: create, update, delete :: clone, label, user, item
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_types", "g_context" => "event", "g_key" => "clone_create"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_types", "g_context" => "event", "g_key" => "user_create"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_types", "g_context" => "event", "g_key" => "label_create"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_types", "g_context" => "event", "g_key" => "item_create"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_types", "g_context" => "event", "g_key" => "clone_update"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_types", "g_context" => "event", "g_key" => "user_update"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_types", "g_context" => "event", "g_key" => "label_update"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_types", "g_context" => "event", "g_key" => "item_update"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_types", "g_context" => "event", "g_key" => "clone_delete"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_types", "g_context" => "event", "g_key" => "user_delete"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_types", "g_context" => "event", "g_key" => "label_delete"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_types", "g_context" => "event", "g_key" => "item_delete"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "event_types", "g_context" => "event", "g_key" => "user_login"), array("g_type", "g_context", "g_key")),
            // default user types :: user, admin
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "user_types", "g_context" => "user", "g_key" => "user"), array("g_type", "g_context", "g_key")),
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "user_types", "g_context" => "user", "g_key" => "admin"), array("g_type", "g_context", "g_key")),
            // default item types :: content
            array("insert_ine", "labels", array("g_state" => "public", "g_type" => "item_types", "g_context" => "item", "g_key" => "content"), array("g_type", "g_context", "g_key")),
        );
        $db_result = g::run("db.Prepare", $sql_rows);
        return true;
    },
    "GenerateCreateTablesSql" => function ($db_type, $table_sql) {
        if ($db_type === "mysql") {
            $s = "`";
            $sqltemp = "";
            foreach ($table_sql as $key => $value) {
                if ($key == "name") {
                    $sqltemp .= "CREATE TABLE " . $s . $value . $s . " (";
                } elseif ($key == "cols") {
                    foreach ($value as $col => $colval) {
                        $sqltemp .= $s . $col . $s;
                        foreach ($colval as $coldets) {
                            $val = explode("|", $coldets);
                            $tempmore = "";
                            $temp = $val[0];
                            if (isset($val[1])) {
                                $tempmore = $val[1];
                            }
                            switch (strtolower($temp)) {
                                case "decimal":
                                    $sqltemp .= " " . $temp . "(" . $tempmore . ")";
                                    break;
                                case "varchar":
                                    $sqltemp .= " " . $temp . "(" . $tempmore . ")";
                                    break;
                                case "default":
                                    $sqltemp .= " " . $temp . " '" . $tempmore . "'";
                                    break;
                                case "tinyint":
                                    $sqltemp .= " tinyint DEFAULT 0";
                                    break;
                                case "int":
                                    $sqltemp .= " int";
                                    break;
                                case "bigint":
                                    $sqltemp .= " bigint";
                                    break;
                                case "primary":
                                    $sqltemp .= " PRIMARY KEY";
                                    break;
                                case "autoinc":
                                    $sqltemp .= " AUTO_INCREMENT";
                                    break;
                                case "date":
                                    $sqltemp .= " datetime";
                                    break;
                                case "timestamp":
                                    if ($tempmore == "UPDATE") {
                                        $sqltemp .= " TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
                                    } else if ($tempmore == "DEFAULT") {
                                        $sqltemp .= " TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                                    } else {
                                        $sqltemp .= " TIMESTAMP NULL";
                                    }
                                    break;
                                default:
                                    $sqltemp .= " " . $temp;
                                    break;
                            }
                        }
                        $sqltemp .= ", ";
                    }
                } elseif ($key == "extras") {
                    foreach ($value as $valArr) {
                        foreach ($valArr as $col => $colval) {
                            switch (strtolower($col)) {
                                case "primary_key":
                                    $sqltemp .= " PRIMARY KEY (" . $s . $colval . $s . "), ";
                                    break;
                                case "unique":
                                    if (is_array($colval)) {
                                        foreach ($colval as $colkey => $coldeets) {
                                            $sqltemp .= " CONSTRAINT $colkey UNIQUE(" . $s . implode("$s,$s", $coldeets) . $s . "), ";
                                        }
                                        // CONSTRAINT uni_clone_tag UNIQUE(alias, hash_clone)
                                    } else {
                                        $sqltemp .= " UNIQUE KEY (" . $s . $colval . $s . "), ";
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                    }
                }
            }
            $sqltemp = substr($sqltemp, 0, -2);
            $sqltemp .= ") ENGINE=InnoDB AUTO_INCREMENT=1234 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            return $sqltemp;
        }
    },
    "Execute" => function ($query_sqls) {
        $result = array();
        foreach ($query_sqls as $table_conn => $sqls) {
            $conn = g::get("db.conns.$table_conn");
            if ($conn !== false) {
                $conn->beginTransaction();
                foreach ($sqls as $query_name => $sql_line) {
                    if (!empty($sql_line)) {
                        // $key = $sql_line[0];
                        $sql = $sql_line[0];
                        if (!empty($sql_line[1])) {
                            $val = $sql_line[1];
                        } else {
                            $val = array();
                        }

                        try {
                            if (strpos($sql, "INSERT") > -1 || strpos($sql, "UPDATE") > -1 || strpos($sql, "DELETE") > -1) {
                                // "prepare"
                                $pack = $conn->prepare($sql);
                                $pack->execute($val);
                                $result[$query_name]["last_id"] = $conn->lastInsertId();
                            } elseif (strpos($sql, "SELECT") > -1) {
                                $pack = $conn->prepare($sql);
                                $pack->execute($val);

                                $response["count"] = $pack->rowCount();
                                $response["list"] = $pack->fetchAll(PDO::FETCH_ASSOC);
                                $result[$query_name] = $response;
                            } else {
                                $pack = $conn->prepare($sql);
                                $pack->execute($val);
                            }
                            g::run("tools.Say", "DB.Execute: " . $sql, 1);
                        } catch (PDOException $e) {
                            $error = $e->getMessage();
                            // var_dump($error);die;
                            $result["error"][$query_name] = $error;
                            // $result[$query_name]["msg"]["err"] = $error;
                            g::run("tools.Say", "There is an error with the db execution: $sql");
                            g::run("tools.Say", g::run("tools.JE", $result["error"]));
                        }
                    }
                }
                $conn->commit();
            }
        }

        /*
        $this->db_connect();

        $sql = "SELECT * FROM genes_settings";
        $val = array();

        $sql = "SELECT * FROM genes_settings WHERE id > ?";
        $val = array(30);

        $sql = "UPDATE genes_settings SET setting_description=? WHERE id = ?";
        $val = array("Adding a user...", 32);

        $sql = "SELECT * FROM genes_settings WHERE id > ?";
        $val = array(30);

        $sql = "INSERT INTO genes_settings (setting_type, setting_title, setting_key, setting_value) VALUES (?, ?, ?, ?)";
        $val = array("thunder_cats", "Thunder Cats", "tc", "liono");

        $sql = "SELECT * FROM genes_settings WHERE id > ?";
        $val = array(54);

        $sql = "DELETE FROM genes_settings WHERE id = ?";
        $val = array(55);

        $this->db_exec($sql, $val);
         */
        return $result;
    },
    "GetCloneId" => function () {
        $db_is_proper = g::get("config.checks.db_is_proper");
        // Admin thinks db is not proper
        if ($db_is_proper === 1) {
            $cid = g::run("core.SessionGet", "cid");
            if (empty($cid)) {
                $cid = false;
                $config = g::get("config");
                $clone_hash = $config["clone"]["hash"];
                $clone_table = $config["db"]["tables"]["clones"];
                $table_conn = $clone_table[0];
                $table_name = $clone_table[1];
                $query_sqls = array($clone_table[0] => array(array("SELECT id FROM " . $clone_table[1] . " WHERE `g_hash`='$clone_hash';")));
                $result = g::run("db.Execute", $query_sqls);
                if (!empty($result[0]) && $result[0]["count"] > 0) {
                    $cid = $result[0]["list"][0]["id"];
                    g::run("core.SessionSet", "cid", $cid);
                }

                return $cid;
            } else {
                return $cid;
            }
        }
        return false;
    },
    "Prepare" => function ($sql_rows, $is_genes_db = true) {
        $query_sqls = array();
        $cid = g::run("db.GetCloneId");
        $config = g::get("config");

        $meta = g::get("op.meta");
        if (!empty($meta["url"]["lang"])) {
            $lang = $meta["url"]["lang"]; // use later, not implemented yet.
        }
        $user = $meta["user"]; // use later, not implemented yet.

        $ts_ms = g::run("tools.TsMs");
        $ts_hash = g::run("tools.DTS", 7);

        $hc = 0;
        foreach ($sql_rows as $key => $sql_details) {
            $hc++;
            // increment milisecond to create different hashed ofr each insert
            $action = $sql_details[0];
            $table_key = $sql_details[1];

            $table_info = $config["db"]["tables"][$table_key];
            $table_conn = $table_info[0];
            $table_name = $table_info[1];

            $table_cols = array_keys($sql_details[2]);
            $table_vals = array_values($sql_details[2]);

            if ($action == "insert_ine" || $action == "insert") {
                if ($is_genes_db) {
                    if (!in_array("g_hash", $table_cols)) {
                        $table_cols[] = "g_hash";
                        $table_vals[] = $ts_hash;
                    }
                }
            }

            if ($action !== "update") {
                if ($is_genes_db) {
                    $table_cols[] = "cid";
                    $table_vals[] = $cid;
                }
            }

            $table_cols_csv = implode("`,`", $table_cols);
            $table_ptr_csv = "";
            foreach ($table_cols as $col) {
                $table_ptr_csv .= "?, ";
            }
            $table_ptr_csv = substr($table_ptr_csv, 0, -2);

            if ($action === "insert_ine") {
                $table_vals_csv = "'";
                $tc = 0;
                foreach ($table_vals as $i => $val) {
                    if (strpos($val, "'") > -1) {
                        $val = str_replace("'", "\'", $val);
                    }
                    $tc++;
                    $table_vals_csv .= $val . "' as A$tc,'";
                }
                $table_vals_csv = substr($table_vals_csv, 0, -2);

                $table_ine_query = "";
                $table_ineq = $sql_details[2];
                $given = false;
                if (!empty($sql_details[3])) {
                    $table_ineq_keys = $sql_details[3];
                    foreach ($table_ineq_keys as $i => $tkey) {
                        $val = $table_ineq[$tkey];
                        if (strpos($val, "'") > -1) {
                            $val = str_replace("'", "\'", $val);
                        }
                        $table_ine_query .= "`$tkey`='$val' AND ";
                    }
                } else {
                    foreach ($table_ineq as $rkey => $val) {
                        if (strpos($val, "'") > -1) {
                            $val = str_replace("'", "\'", $val);
                        }
                        $table_ine_query .= "`$rkey`='$val' AND ";
                    }
                }

                if ($is_genes_db) {
                    $table_ine_query .= "cid=$cid";
                } else {
                    $table_ine_query .= "1=1";
                }

                $sql = "INSERT INTO $table_name (`$table_cols_csv`) ";
                $sql .= "SELECT * FROM (SELECT $table_vals_csv) AS tmp WHERE NOT EXISTS (";
                $sql .= "SELECT id FROM $table_name WHERE $table_ine_query";
                $sql .= ") LIMIT 1;";
                $val = array();
                $tmp_sql = array($sql, $val);
            } elseif ($action === "insert") {
                $sql = "INSERT INTO $table_name (`$table_cols_csv`) VALUES ($table_ptr_csv)";
                $val = $table_vals;
                $tmp_sql = array($sql, $val);
            } elseif ($action === "update") {
                if ($is_genes_db) {
                    $where = "cid=$cid";
                } else {
                    $where = "1=1";
                }
                if (!empty($sql_details[3])) {
                    $where .= " AND " . $sql_details[3];
                }

                $table_cols_ptr_csv = implode("=?,", $table_cols) . "=?";

                $sql = "UPDATE $table_name SET $table_cols_ptr_csv WHERE $where";
                $val = $table_vals;
                $tmp_sql = array($sql, $val);
            } elseif ($action === "delete") {
                if ($is_genes_db) {
                    $where = "cid=$cid";
                } else {
                    $where = "1=1";
                }
                if (!empty($sql_details[2])) {
                    $where .= " AND " . $sql_details[2];
                }

                $sql = "DELETE FROM $table_name WHERE $where";
                $tmp_sql = array($sql, array());
            }

            $query_sqls[$table_conn][$key] = $tmp_sql;
        }

        return g::run("db.Execute", $query_sqls);
    },
    "Get" => function ($query, $is_genes_db = true) {
        $response = array();

        $cols = $query[0];
        $table_key = $query[1];

        $config = g::get("config");
        $cid = g::run("db.GetCloneId");

        if ($is_genes_db) {
            $where = "cid=$cid";
        } else {
            $where = "1=1";
        }

        if (!empty($query[2])) {
            $where .= " AND " . $query[2];
        }

        $table_info = $config["db"]["tables"][$table_key];
        $table_conn = $table_info[0];
        $conn = g::get("db.conns.$table_conn");
        $table_name = $table_info[1];

        $sql = "SELECT COUNT(*) FROM $table_name WHERE $where";

        try {
            $response["total"] = $conn->query($sql)->fetchColumn();
        } catch (Exception $e) {
            // $msg = $e->errorInfo;
            $response["total"] = 0;
        }

        $order = "";
        if (!empty($query[3])) {
            $order = "ORDER BY " . $query[3];
        }

        $start = null;

        $limit = "";
        if (!empty($query[4])) {
            $limit = "LIMIT " . $query[4];
            if (strpos($query[4], ",") > 0) {
                $lr = explode(",", $query[4]);
                $start = (int) trim($lr[0]);
                $end = $start + trim($lr[1]);
            }
        }

        $sql = "SELECT $cols FROM $table_name WHERE $where $order $limit";

        try {
            $pack = $conn->prepare($sql);
            $pack->execute();

            if (!empty($start) || $start === 0) {$response["start"] = $start;}
            if (!empty($end)) {$response["end"] = $end;}
            $response["count"] = $pack->rowCount();
            $response["list"] = $pack->fetchAll(PDO::FETCH_ASSOC);
            //g::run("tools.Say", $sql, 1);
            //g::run("tools.Say", "DB.Get: " . g::run("tools.CleanBreaks", g::run("tools.JE", $query)) . "", 1);
        } catch (Exception $e) {
            // print_r($e);
            $msg = $e->errorInfo;
            g::run("tools.Say", $msg[2], 5);
            $response["count"] = 0;
            $response["list"] = array();
        }
        return $response;
    },
    "Select" => function ($table_name, $query) {
        /*
        type=newsletter;sort=dateSubscribed,desc;list=30-40-150
         * Other than that a url can have
        (;) | (=) | (,) | (-) | (_)
         * And other than "match", url can be queried with
        "type" | "tag" | "find" | "date" | "user" | "sort" | "list" | "page"
         */
        return false;
    },
    "Update" => function ($table_name, $query) {
    },
    "Delete" => function ($table_name, $query) {
    },
    "GetWithSessionPostedQueryArgs" => function ($mixed_query) {
        /*
        $mixed_query = array(
        "cols" => "*",
        "table" => "old_users",
        "sort" => array("created_date" => "DESC"),
        "filter" => array(),
        "args" => $args,
        "post" => $post
        );
         */
        $args = $mixed_query["args"];
        $seq = (empty($args["seq"])) ? "" : $args["seq"];
        $post = $mixed_query["post"];
        $sort = $mixed_query["sort"];
        $filter = $mixed_query["filter"];

        $cols = $mixed_query["cols"];
        $table = $mixed_query["table"];

        $sort_val = $filter_val = "";
        $get_rows = 25;
        $page_val = 1;

        if (!empty($post)) {
            $sort_val = $post["sort"];
            if (!empty($sort_val)) {
                $sort = array();
                if (strpos($sort_val, ",") > -1) {
                    $psarr = explode(",", $sort_val);
                    foreach ($psarr as $psr) {
                        $psdata = explode("|", $psr);
                        $sort[$psdata[0]] = $psdata[1];
                    }
                } else {
                    $psdata = explode("|", $sort_val);
                    if (count($psdata) > 1) {
                        $sort[$psdata[0]] = strtoupper($psdata[1]);
                    }
                }
            }
            $filter_val = $post["filter"];
            if (!empty($filter_val)) {
                if (strpos($filter_val, ",") > -1) {
                    $psarr = explode(",", $filter_val);
                    foreach ($psarr as $psr) {
                        $psdata = explode("|", $psr);
                        $filter[$psdata[0]] = $psdata[1];
                    }
                } else {
                    $psdata = explode("|", $filter_val);
                    if (count($psdata) > 1) {
                        $filter[$psdata[0]] = $psdata[1];
                    }
                }
            }

            if (!empty($post["rows"])) {
                $get_rows = $post["rows"];
                $args["rows"] = $get_rows;
                g::run("core.SessionSet", "$seq-rows", $get_rows);
            }

            if (!empty($post["page"])) {
                $page_val = $post["page"];
                $args["start"] = ($page_val - 1) * $get_rows;
                g::run("core.SessionSet", "$seq-page", $page_val);
                g::run("core.SessionSet", "$seq-start", $args["start"]);
            }

            g::run("core.SessionSet", "$seq-sort_val", $sort_val);
            g::run("core.SessionSet", "$seq-sort", $sort);
            g::run("core.SessionSet", "$seq-filter_val", $filter_val);
            g::run("core.SessionSet", "$seq-filter", $filter);
        } else {
            if (empty($seq)) {
            }
        }

        $where = "";
        $order = "";

        if (!empty($seq)) {
            if (empty($post)) {
                $session_filter = g::run("core.SessionGet", "$seq-filter");
                $filter = (!empty($session_filter)) ? $session_filter : $filter;
                $session_sort = g::run("core.SessionGet", "$seq-sort");
                $sort = (!empty($session_sort)) ? $session_sort : $sort;
                $session_sort_val = g::run("core.SessionGet", "$seq-sort_val");
                $sort_val = (!empty($session_sort_val)) ? $session_sort_val : $sort_val;
                $session_filter_val = g::run("core.SessionGet", "$seq-filter_val");
                $filter_val = (!empty($session_filter_val)) ? $session_filter_val : $filter_val;

                $get_rows = g::run("core.SessionGet", "seq-rows");
                $page_val = g::run("core.SessionGet", "$seq-page");

                if (!empty($args["rows"])) {
                    $get_rows = $args["rows"];
                    g::run("core.SessionSet", "$seq-rows", $get_rows);
                }
            }
        }

        $filters = array();
        foreach ($filter as $key => $value) {
            if (strpos($value, "%") > -1) {
                $filters[] = "$key LIKE '$value'";
            } else {
                $filters[] = "$key='$value'";
            }
        }
        if (!empty($filters)) {
            $where .= implode(" AND ", $filters);
        }

        foreach ($sort as $key => $value) {
            if (!empty($order)) {
                $order .= ", $key $value ";
            } else {
                $order .= "$key $value ";
            }
        }

        $rows = (empty($args["rows"])) ? 25 : $args["rows"];
        $start = (empty($args["start"])) ? 0 : $args["start"];
        $page_val = (empty($args["page"])) ? $page_val : $args["page"];
        $limit = "$start, $rows";

        $dataset = g::run("db.Get", array($cols, $table, $where, $order, $limit));

        $total = $dataset["total"];
        $count = $dataset["count"];

        $current_page = ($start / $rows) + 1;
        $total_pages = ceil(($total / $rows));

        $dataset["current_page"] = $current_page;
        $dataset["total_pages"] = $total_pages;

        $dataset["seq"] = $seq;
        $dataset["rows"] = $rows;
        $dataset["start"] = $start;
        $dataset["sort_val"] = $sort_val;
        $dataset["filter_val"] = $filter_val;

        $dataset["prev_start"] = 0;
        $dataset["next_start"] = $start;

        if ($current_page == $total_pages) {
            $dataset["prev_start"] = $start - $rows;
        } else if ($start > 0) {
            $dataset["next_start"] = $start + $rows;
            $dataset["prev_start"] = $start - $rows;
        } else if ($start == 0) {
            $dataset["next_start"] = $start + $rows;
        }

        if ($dataset["prev_start"] < 0) {
            $dataset["prev_start"] = 0;
        }

        if (ceil($dataset["next_start"] / $dataset["rows"]) > $total_pages) {
            $dataset["next_start"] = $start;
        }

        return $dataset;
    }
));
