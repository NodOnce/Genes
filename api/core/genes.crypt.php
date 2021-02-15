<?php
g::set("crypt", array(
    "charset_cray" => "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789[]{};:?.,!@#$%^&*()-_=+|",
    "charset_hash" => "abcdefghijkmnopqrstuvwxyz0123456789",
    "charset_alpha" => "abcdefghijklmnopqrstuvwxyz",
    "charset_lcase" => "abcdefghijklmnopqrstuvwxyz0123456789",
    "charset_mixed" => "abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ",
    "hash_separator" => "l",
));

g::def("crypt", array(
    "MakeSaltySecret" => function ($string, $salt = "") {
        if (empty($salt)) {
            $salt = g::get("config.clone.secret_salt");
        }
        return md5($string . $salt);
    },
    "IsMD5" => function ($md5_str) {
        return strlen($md5_str) == 32 && ctype_xdigit($md5_str);
    },
    "HashTo" => function ($num, $b = 36) {
        $base = g::get("crypt.charset_hash");
        $config_chars = g::get("config.clone.chars");
        if (!empty($config_chars)) {
            $base = $config_chars;
        }

        $r = $num % $b;
        $res = $base[$r];
        $q = floor($num / $b);
        while ($q) {
            $r = $q % $b;
            $q = floor($q / $b);
            $res = $base[$r] . $res;
        }
        return $res;
    },
    "HashFrom" => function ($num, $b = 36) {
        $base = g::get("crypt.charset_hash");
        $config_chars = g::get("config.clone.chars");
        if (!empty($config_chars)) {
            $base = $config_chars;
        }

        $limit = strlen($num);
        $res = strpos($base, $num[0]);
        for ($i = 1; $i < $limit; $i++) {
            $res = $b * $res + strpos($base, $num[$i]);
        }
        return $res;
    },
    "GenerateRandomKey" => function ($len = 64, $type = "cray") {
        $randStringLen = $len;
        $charset = g::get("crypt.charset_$type");
        $randString = "";
        for ($i = 0; $i < $randStringLen; $i++) {
            $randString .= $charset[mt_rand(0, strlen($charset) - 1)];
        }

        return $randString;
    },
    "HashEndecode" => function ($in, $to_num = false, $passKey = null, $index = null) {
        if ($index === null) {
            $index = g::get("crypt.charset_hash");
        } elseif ($index === "alpha") {
            $index = g::get("crypt.charset_alpha");
        } elseif ($index === "lcase") {
            $index = g::get("crypt.charset_lcase");
        } elseif ($index === "mix") {
            $index = g::get("crypt.charset_mixed");
        } elseif ($index === "cray") {
            $index = g::get("crypt.charset_cray");
        }

        if ($passKey == null) {
            $passKey = "";
        }
        if ($passKey !== null) {
            for ($n = 0; $n < strlen($index); $n++) {
                $i[] = substr($index, $n, 1);
            }

            $passhash = hash('sha256', $passKey);
            $passhash = (strlen($passhash) < strlen($index)) ? hash('sha512', $passKey) : $passhash;

            for ($n = 0; $n < strlen($index); $n++) {
                $p[] = substr($passhash, $n, 1);
            }

            array_multisort($p, SORT_DESC, $i);
            $index = implode($i);
        }

        $base = strlen($index);

        if ($to_num) {
            // Digital number  <<--  alphabet letter code
            $in = strrev($in);
            $out = 0;
            $len = strlen($in) - 1;
            for ($t = 0; $t <= $len; $t++) {
                $bcpow = bcpow($base, $len - $t);
                $out = $out + strpos($index, substr($in, $t, 1)) * $bcpow;
            }
            $out = sprintf('%F', $out);
            $out = substr($out, 0, strpos($out, '.'));
        } else {
            // Digital number  -->>  alphabet letter code
            $out = "";
            for ($t = floor(log($in, $base)); $t >= 0; $t--) {
                $bcp = bcpow($base, $t);
                $a = floor($in / $bcp) % $base;
                $out = $out . substr($index, $a, 1);
                $in = $in - ($a * $bcp);
            }
            $out = strrev($out); // reverse
        }

        return $out;
    },
    "MicroHash" => function ($micro_now) {
        $md_arr = explode(".", $micro_now);
        return g::run("crypt.HashEndecode", $md_arr[0]) . g::get("crypt.hash_separator") . g::run("crypt.HashEndecode", $md_arr[1]);
    },
    "HashMicro" => function ($micro_hash) {
        $md_arr = explode(g::get("crypt.hash_separator"), $micro_hash);
        return g::run("crypt.HashEndecode", $md_arr[0], true) . "." . sprintf('%06d', g::run("crypt.HashEndecode", $md_arr[1], true));
    },
    "MakeSaltyKey" => function () {
    },
    "GenerateKeys" => function () {
        $clone_created = g::run("tools.DTS", 3);

        $chars = g::get("crypt.charset_hash");
        $shuffled_chars = str_shuffle($chars);
        g::get("crypt.charset_hash", $shuffled_chars);

        $clone_hash = g::run("tools.DTS", 7);

        $clone_salt = g::run("crypt.GenerateRandomKey");
        $clone_secret_salt = g::run("crypt.GenerateRandomKey");
        $clone_user_salt = g::run("crypt.GenerateRandomKey");

        $clone_secret = g::run("crypt.GenerateRandomKey", 8, "mixed");
        $user_open_pass = g::run("crypt.GenerateRandomKey", 8, "mixed");

        $clone = array(
            "chars" => $shuffled_chars, // salting secret
            "salt" => $clone_salt, // salting secret
            "secret_salt" => $clone_secret_salt, // gHash_generateRandomKey(), salting secret
            "user_salt" => $clone_user_salt, // gHash_generateRandomKey(), salting password
            "hash" => $clone_hash, // used as hash_clone, in db records when clone creates data
            "alias" => g::run("crypt.GenerateRandomKey", 16, "lcase"), // key and hash separates this clone from others.
            "name" => "Genes Clone", //
            "contact" => "", //
            "open_secret" => $clone_secret, // unsalted secret
            "secret" => g::run("crypt.MakeSaltySecret", $clone_secret, $clone_secret_salt), // salted secret
            "clone_create" => $clone_created,
        );
        $admin = array(
            "hash" => $clone_hash, // used as hash_user, in db records when clone creates data
            "alias" => "admin", // you can change, but pass needs to be regenerated
            "name" => "Genes Admin", // you can change does not matter.
            "email" => "", //
            "open_pass" => $user_open_pass, // genes admin password, when written not encoded and reset_pwd is true, salted, encoded and set again.
            "pass" => g::run("crypt.MakeSaltySecret", $user_open_pass, $clone_user_salt), // genes admin password, when written not encoded and reset_pwd is true, salted, encoded and set again.
        );
        // set keys to g.
        g::set("config.clone", $clone);
        g::set("config.admin", $admin);
    }
));
