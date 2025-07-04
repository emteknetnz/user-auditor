<?php 

include 'functions.php';

// This should be 3 even though if if these packages no longer receieve updates, because the repos could still be used to provide packages to live websites that just happen to be out of date
$LEAST_SUPPORTED_CMS = 3;

$organisations = $argv[1] ?? '';
if (!$organisations) {
    echo "Usage: php packagist.php <organisations>\n";
    die;
}
$organisations = preg_split('#,#', $organisations);

$includeUnsupported = (bool) ($argv[2] ?? false);

$supportedPackages = [];
$json = file_get_contents('https://raw.githubusercontent.com/silverstripe/supported-modules/refs/heads/main/repositories.json');
$data = json_decode($json, true);
foreach ($data as $_ => $repos) {
    $supported = false;
    foreach ($repos as $repo) {
        foreach (array_keys($repo['majorVersionMapping']) as $version) {
            if ($version >= $LEAST_SUPPORTED_CMS) {
                $supported = true;
                break;
            }
        }
        if ($supported) {
            $package = $repo['packagist'];
            $supportedPackages[] = $package;
        }
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
        $support = in_array($package, $supportedPackages) ? 'supported' : 'unsupported';
        if (!$includeUnsupported && $support == 'unsupported') {
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
