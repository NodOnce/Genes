<?php
if ($handle = opendir('./img/')) {
    $c = 0;
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            $c++;
            echo "body.bgi$c { background-image: url('img/$entry'); }\n";
        }
    }
    closedir($handle);
}
