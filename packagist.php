<?php 

include 'functions.php';

$allowUnsupported = [
    'silverstripe/',
    'silverstripe-themes/',
    'cwp/',
];

$organisations = $argv[1] ?? 'silverstripe,silverstripe-themes,cwp,symbiote,dnadesign,tractorcow,bringyourownideas,colymba,hafriedlander,lekoala,undefinedoffset,asyncphp';
$organisations = preg_split('#,#', $organisations);

$supportedPackages = [];
$json = file_get_contents('https://raw.githubusercontent.com/silverstripe/supported-modules/refs/heads/main/repositories.json');
$data = json_decode($json, true);
foreach ($data as $repos) {
    foreach ($repos as $repo) {
        $package = $repo['packagist'] ?? false;
        // things list eslint are supported though not on packagist
        if (!$package) {
            continue;
        }
        $supportedPackages[] = $package;
    }
}

$packageMaintainers = [
    'supported' => [],
    'unsupported' => [],
];
$maintainerPackages = [];

foreach ($organisations as $organisation) {
    $json = fetch("https://packagist.org/packages/list.json?vendor=$organisation");
    $packages = json_decode($json, true)['packageNames'];
    foreach ($packages as $package) {
        $allowed = false;
        $support = in_array($package, $supportedPackages) ? 'supported' : 'unsupported';
        if ($support == 'supported') {
            $allowed = true;
        } else {
            foreach ($allowUnsupported as $str) {
                if (str_contains($package, $str)) {
                    $allowed = true;
                }
            }
        }
        if (!$allowed) {
            continue;
        }
        $packageMaintainers[$support][$package] = [];
        $json = fetch("https://packagist.org/packages/$package.json");
        $data = json_decode($json, true)['package'];
        foreach ($data['maintainers'] as $maintainer) {
            $name = $maintainer['name'];
            $packageMaintainers[$support][$package][] = $name;
            $maintainerPackages[$name] ??= [
                'supported' => [],
                'unsupported' => [],
            ];
            $maintainerPackages[$name][$support][] = $package;
        }
    }
}

$lines = [];
foreach (array_keys($packageMaintainers) as $support) {
    foreach ($packageMaintainers[$support] as $package => $maintainers) {
        $lines[] = "# $package ($support)";
        foreach ($maintainers as $maintainer) {
            $lines[] = "- $maintainer";
        }
        $lines[] = '';
    }
}
write_report('report-package-maintainers.txt', $lines);

$lines = [];
foreach (array_keys($maintainerPackages) as $maintainer) {
    $lines[] = "# $maintainer";
    foreach ($maintainerPackages[$maintainer] as $support => $packages) {
        foreach ($packages as $package) {
            $lines[] = "- $package ($support)";
        }
    }
    $lines[] = '';
}
write_report('report-maintainer-packages.txt', $lines);
