<?php
g::set("void.mailgun", array(
    "views" => array(),
    "bits" => array(),
    "opts" => array(
        "FROM_NAME" => "DV",
        "FROM_EMAIL" => "devrimvar@gmail.com",
        "API_KEY" => "a64ec1cdfb96474a6906b4b7ffaae3dd-19f318b0-b0b4fc7f",
        "API_BASE_URL" => "https://api.mailgun.net/v3/mg.nodonce.com",
    ),
    "tmpls" => array()));
g::def("mods.mailgun", array(
    "MailSend" => function ($to, $subject, $text, $html) {
        $from_name = g::get("config.mods.mailgun.opts.FROM_NAME");
        $from_email = g::get("config.mods.mailgun.opts.FROM_EMAIL");
        $api_key = g::get("config.mods.mailgun.opts.API_KEY");
        $api_base_url = g::get("config.mods.mailgun.opts.API_BASE_URL");

        $url = $api_base_url . "/messages";
        $data = array(
            "o:tracking" => true,
            "from" => "$from_name <$from_email>",
            "to" => $to,
            "subject" => $subject,
            "text" => $text,
            "html" => $html,
        );
        $auth = "api:$api_key";
        // echo $auth;
        g::run("tools.LoadPathSafe", $url, 'POST', $data, $auth);
    },
));
