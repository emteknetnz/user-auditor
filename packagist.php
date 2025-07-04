<?php 

function fetch($url) {
    echo "Fetching from $url\n";
    return file_get_contents($url);
}

function dj($json) {
    $s = json_encode(json_decode($json), 448);
    file_put_contents('debug.json', $s);
}

$package_maintainers = [];

$organisation = $argv[1] ?? '';
if (!$organisation) {
    echo "Usage: php packagist.php <organisation>\n";
    die;
}

$json = fetch("https://packagist.org/packages/list.json?vendor=$organisation");
$packages = json_decode($json, true)['packageNames'];
foreach ($packages as $package) {
    if ($package != 'silverstripe/admin') {
        continue;
    }
    $package_maintainers[$package] = [];
    $json = fetch("https://packagist.org/packages/$package.json");
    $data = json_decode($json, true)['package'];
    foreach ($data['maintainers'] as $maintainer) {
        $name = $maintainer['name'];
        $package_maintainers[$package][] = $name;
    }
}

foreach ($package_maintainers as $package => $maintainers) {
    //
}