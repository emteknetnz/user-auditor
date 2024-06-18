<?php

// loop json and txt files and run shred -vuz on them
$files = glob('*.json');
$files = array_merge($files, glob('*.txt'));

foreach ($files as $file) {
    echo "Shredding $file\n";
    $output = shell_exec("shred -vuz '$file'");
    echo $output;
}
echo "Done\n";
