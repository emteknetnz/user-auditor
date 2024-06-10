<?php

if (!getenv('TOKEN') || !isset($argv[1])) {
    echo "Usage: TOKEN=abc php run.php <organisation>\n";
    die;
}
