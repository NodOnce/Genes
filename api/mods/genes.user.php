<?php
g::set("void.user", array(
    "views" => array(
        "UserLogin" => array("urls" => array("en" => array("login", "user=login"))),
        "UserQuickLogin" => array("urls" => array("en" => array("quick", "user=quick"))),
        "UserLogout" => array("urls" => array("en" => array("logout", "user=logout"))),
        "UserRegister" => array("urls" => array("en" => array("register", "user=register"))),
        "UserActivate" => array("urls" => array("en" => array("activate", "user=activate"))),
        // "UserDelete" => array("urls" => array("en" => array("delete-account", "user=delete"))),
        "UserProfile" => array("urls" => array("en" => array("profile", "user=profile"))),
        "UserForgot" => array("urls" => array("en" => array("forgot", "user=forgot"))),
    ),
    "bits" => array(
        "login" => array("en" => "Login"),
        "quick" => array("en" => "Quick Login"),
        "logout" => array("en" => "Logout"),
        "msg_err_login" => array("en" => "Can not login."),
        "msg_err_login_passive" => array("en" => "User is not activated."),
        "msg_err_not_allowed" => array("en" => "This user does not have any allowed paths. Logged out."),
    ),
    "tmpls" => array(),
    "opts" => array(
        "url_after_login" => "admin",
        "url_after_logout" => "login",
    ),
    "rules" => array(
        "no" => array(
            "any" => array("UserRegister", "UserActivate", "UserQuickLogin", "UserForgot", "UserDelete", "UserProfile"),
        ),
    ),
));
g::def("mods.user", array(
    "Init" => function () {
        g::set("op.meta.url.mod", "User");
    },
    "Setup" => function () {
        $db_is_connected = g::run("db.IsConnected");
        $db_is_proper = g::get("config.checks.db_is_proper");
        $user_db_is_init = g::get("config.checks.user_db_is_init");
        $user_config_is_init = g::get("config.checks.user_config_is_init");
        $admin_user_details = g::get("config.admin");
        $user_list = g::get("config.users");
        $tables = g::get("config.db.tables.users");

        if ($db_is_connected && $db_is_proper == 1 && $user_db_is_init != 1) {
            // there is db connection
            // there is no user table
            // there is user table
            g::run("tools.UpdateConfigCheck", "user_db_is_init", 1);
        }
        if (!empty($admin_user_details) && $user_config_is_init != 1) {
            // there is no db connection
            // there is no admin user in the config
            // there is admin user
            g::run("tools.UpdateConfigCheck", "user_config_is_init", 1);
        }
    },
    "UserQuickLogin" => function () {
        g::run("mods.user.Init");
        g::set("op.meta.url.mod", "User");
        g::run("ui.LoadViewHtml");
        return true;

        // Return complete profile details from db
    },
    "UserLogin" => function () {
        g::run("mods.user.Init");
        $args = g::get("op.meta.url.args");
        $user_db_is_init = g::get("config.checks.user_db_is_init");
        $user_config_is_init = g::get("config.checks.user_config_is_init");
        $user_login = false;
        $post = g::get("post");
        if (empty($post)) {
            g::run("ui.LoadViewHtml");
        } else {
            $username = $post["username"];
            $password = $post["password"];

            if (empty($username)) {
                /* DID NOT ENTER USERNAME, WTF? */
                g::run("tools.Say", "can-not-login-did-not-enter-username", 5);
            } else {
                if (empty($password)) {
                    /* DID NOT ENTER PASSWORD, WTF? */
                    g::run("tools.Say", "can-not-login-did-not-enter-password", 5);
                } else {
                    /* FINE, LET'S CHECK USER, THERE IS ENOUGH INFO */
                    if ($user_db_is_init != 1) {
                        if ($user_config_is_init != 1) {
                            g::run("tools.Say", "can-not-login-no-way-to-login-to-this-clone", 5);
                        } else {
                            $user_login = CheckUserConfig($username, $password);
                        }
                    } else {
                        $user_login = CheckUserConfig($username, $password);
                        if ($user_login == false) {
                            $user_login = CheckUserDB($username, $password);
                        }
                    }
                }
            }
            g::run("core.DecideRedirection", $user_login);
        }
    },
    "UserLogout" => function () {
        g::run("core.SessionEnd");
        g::run("core.DecideRedirection");
    },
    "UserRegister" => function () {
        g::run("mods.user.Init");
        // Insert to db with default details
        // Any modification should be done on clone.

        // user types are : admin / user / deleted
        $user_db_is_init = g::get("config.checks.user_db_is_init");
        $user_config_is_init = g::get("config.checks.user_config_is_init");
        $user_register = false;

        $post = g::get("post");
        if (empty($post)) {
            g::run("ui.LoadViewHtml");
        } else {
            $username = $post["username"];
            $password = $post["password"];
            $password_rep = $post["password_rep"];

            if (empty($username)) {
                /* DID NOT ENTER USERNAME, WTF? */
                g::run("tools.Say", "can-not-register-did-not-enter-username", 5);
            } else {
                if (empty($password)) {
                    /* DID NOT ENTER PASSWORD, WTF? */
                    g::run("tools.Say", "can-not-register-did-not-enter-password", 5);
                } else if ($password !== $password_rep) {
                    /* DID NOT ENTER PASSWORD, WTF? */
                    g::run("tools.Say", "can-not-register-password-not-same", 5);
                } else {
                    /* FINE, LET'S CHECK USER, THERE IS ENOUGH INFO */
                    if ($user_db_is_init != 1) {
                        if ($user_config_is_init != 1) {
                            g::run("tools.Say", "can-not-register-no-way-to-register-to-this-clone", 5);
                        } else {
                            $user_register = CreateUserConfig($username, $password);
                        }
                    } else {
                        $user_register = CreateUserDB($username, $password);
                    }
                }
            }

            if ($user_register) {
                g::run("tools.Redirect", "login");
            } else {
                g::run("tools.Say", "can-not-register", 5);
                g::run("tools.Redirect", "register");
            }
        }
    },
    "UserActivate" => function () {
        g::run("mods.user.Init");
        g::set("op.meta.url.mod", "User");
        g::run("ui.LoadViewHtml");
        return true;

        // Set active on db
    },
    "UserDelete" => function () {
        g::run("mods.user.Init");
        g::set("op.meta.url.mod", "User");
        g::run("ui.LoadViewHtml");
        return true;

        // Change user details from DB
        // Set user details as deleted user with no personal information
        // Set user type to deleted.
    },
    "UserProfile" => function () {
        g::run("mods.user.Init");
        g::set("op.meta.url.mod", "User");
        g::run("ui.LoadViewHtml");
        return true;

        // Return complete profile details from db
    },
    "UserForgot" => function () {
        g::run("mods.user.Init");
        g::set("op.meta.url.mod", "User");
        g::run("ui.LoadViewHtml");
        return true;

        // Return complete profile details from db
    },
));

// http://themetrace.com/template/bracket/app/index.html
// http://themepixels.me/bracketplus/1.4/app/template/index.html
// http://themepixels.me/cassie/pages/dashboard-two.html

// mod helper functions
function CheckUserConfig($user, $pass)
{
    $cfg_adm = g::get("config.admin");
    $salt = g::get("config.clone.user_salt");
    $salty_pass = g::run("crypt.MakeSaltySecret", $pass, $salt);

    if ($user == $cfg_adm["alias"] || (!empty($cfg_adm["email"]) && $user == $cfg_adm["email"])) {
        if ($salty_pass == $cfg_adm["pass"]) {
            g::run("tools.Say", "logged-in-via-config");
            $user = array(
                "type" => "admin",
                "login_date" => g::run("tools.Now"),
                "hash" => $cfg_adm["hash"],
                "alias" => $cfg_adm["alias"],
                "name" => $cfg_adm["name"],
                "email" => $cfg_adm["email"],
            );
            g::run("core.SessionSet", "op.meta.user", $user);
            return true;
        } else {
            return false;
        }
    } else {
        $cfg_users = g::get("config.users");
        foreach ($cfg_users as $cfg_user) {
            if ($user == $cfg_user["alias"] || (!empty($cfg_user["email"]) && $user == $cfg_user["email"])) {
                if ($salty_pass == $cfg_user["pass"] && $cfg_user["state"] == "active") {
                    g::run("tools.Say", "logged-in-via-config");
                    $user = array(
                        "type" => $cfg_user["type"],
                        "login_date" => g::run("tools.Now"),
                        "hash" => $cfg_user["hash"],
                        "alias" => $cfg_user["alias"],
                        "name" => $cfg_user["alias"],
                        "email" => $cfg_user["email"],
                    );
                    g::run("core.SessionSet", "op.meta.user", $user);
                    return true;
                } else {
                    return false;
                }
            }
        }
    }
    return false;
}

function CheckUserDB($user, $pass)
{
    $salt = g::get("config.clone.user_salt");
    $salty_pass = g::run("crypt.MakeSaltySecret", $pass, $salt);
    $user_exists = g::run("db.Get", array("*", "users", "((g_email='$user' OR g_alias='$user'))"));
    // Removeed so we can check if user exists: AND pass='$password'

    if ($user_exists["total"] > 1) {
        /* USER EXISTS MORE THAN ONCE, WTF? */
        g::run("tools.Say", "can-not-login-user-exists-more-than-once-wtf");
    } elseif ($user_exists["total"] == 0) {
        /* USER DOES NOT EXIST */
        g::run("tools.Say", "can-not-login-user-or-password-wrong");
    } else {
        $db_user = $user_exists["list"][0];
        if ($db_user["g_state"] !== "active") {
            /* USER IS NOT ACTIVATED */
            g::run("tools.Say", "can-not-login-user-not-activated");
        } else if ($db_user["g_pwd"] !== $salty_pass) {
            /* PASSWORD DOES NOT MATCH */
            g::run("tools.Say", "can-not-login-user-or-password-wrong");
        } else {
            $user = array(
                "type" => $db_user["g_type"],
                "login_date" => g::run("tools.Now"),
                "hash" => $db_user["g_hash"],
                "alias" => $db_user["g_alias"],
                "name" => $db_user["g_alias"],
                "email" => $db_user["g_email"],
            );
            g::run("core.SessionSet", "op.meta.user", $user);
            return true;

            // send event user_login
        }
    }
}

function CreateUserConfig($user, $pass)
{
    $cfg_users = g::get("config.users");
    $salt = g::get("config.clone.user_salt");
    $salty_pass = g::run("crypt.MakeSaltySecret", $pass, $salt);
    $now_arr = g::run("tools.DTS", 7);

    if (!empty($cfg_users)) {
        foreach ($cfg_users as $cfg_user) {
            if (!empty($cfg_user["email"]) && $user == $cfg_user["email"]) {
                return false;
            } else {
                g::run("tools.Say", "registered-via-config");
                $user = array(
                    "type" => "user",
                    "login_date" => g::run("tools.DTS", 5),
                    "hash" => g::run("tools.DTS", 7),
                    "alias" => "",
                    "name" => "",
                    "email" => $user,
                    "pass" => $salty_pass,
                    "state" => "active",
                );
                $cfg_users[] = $user;
                g::run("tools.UpdateConfigFiles", "users", $cfg_users);
                return true;
            }
        }
    } else {
        g::run("tools.Say", "registered-via-config");
        $user = array(
            "type" => "user",
            "login_date" => g::run("tools.DTS", 5),
            "hash" => g::run("tools.DTS", 7),
            "alias" => "",
            "name" => "",
            "email" => $user,
            "pass" => $salty_pass,
            "state" => "active",
        );
        $cfg_users[] = $user;
        g::run("tools.UpdateConfigFiles", "users", $cfg_users);
        return true;
    }
}

function CreateUserDB($user, $pass)
{
    $user_exists = g::run('db.Get', array('*', 'users', "(email='$user')"));
    $password = g::run('crypt.MakeSaltySecret', $pass);

    if ($user_exists['total'] > 1) {
        /* USER EXISTS MORE THAN ONCE, WTF? */
        g::run('tools.Say', 'user-login-register-user-exists-more-than-once');
    } elseif ($user_exists['total'] < 1) {
        /* USER DOES NOT EXIST */
        g::run('tools.Say', 'user-login-register-user-does-not-exist-create-user');
    } else {
        // Send login info
        g('tools.Say', 'user-login-register-user-exists');
        g('tools.Say', 'There is already a user with this email, please login with your information.', 5);
    }
}

g::run("mods.user.Setup");
