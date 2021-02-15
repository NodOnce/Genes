<?php
g::set("void.stats", array(
    "views" => array(
        "TriggerStats" => array("urls" => array("en" => "ping")),
    ),
    "bits" => array(),
    "tmpls" => array(),
));
g::def("mods.stats", array(
    "Init" => function () {
    },
    "TriggerStats" => function () {
        // g::set("op.html", "TriggerStats.");
        print_r(g::get("post"));
        die;
    },
    "VisitAdd" => function () {
        // Get unique identifier for the visitor, along with the session id and client info..
        // So we record it to Events with this, and consequent actions will be updated with this record.
        // Maybe timeonsite, and pageview count, will be reachable as combinable values.
        g::set("op.html", "VisitAdd.");
    },
    "VisitUpdatePageview" => function () {
        g::set("op.html", "VisitUpdatePageview.");
    },
    "VisitUpdateTimeOnSite" => function () {
        g::set("op.html", "VisitUpdateTimeOnSite.");
    },
    "SummarizeData" => function () {
        g::set("op.html", "SummarizeData.");
    },
));
