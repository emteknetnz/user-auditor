<?php

function github_api($url, $returnOnFailure = []) {
    // silverstripe-themes has a kind of weird redirect only for api requests
    $url = str_replace('/silverstripe-themes/silverstripe-simple', '/silverstripe/silverstripe-simple', $url);
    print("Making GET curl request to $url\n");
    $token = getenv('TOKEN');
    $jsonStr = empty($data) ? '' : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, !empty($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: PHP script',
        'Accept: application/vnd.github+json',
        "Authorization: Bearer $token",
        'X-GitHub-Api-Version: 2022-11-28'
    ]);
    if ($jsonStr) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
    }
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpcode >= 300) {
        print("HTTP code $httpcode returned from GitHub API\n");
        print("$response\n");
        print("WARNING! Failure calling github api: $url\n");
        if (!file_exists('report-cron-failures.txt')) {
            file_put_contents('report-cron-failures.txt', '');
        }
        file_put_contents('report-cron-failures.txt', "GET $url\n", FILE_APPEND);
        return $returnOnFailure;
    }
    return json_decode($response, true);
}

function nice_permissions() {
    return [
        'admin' => 'Admin',
        'maintain' => 'Maintain',
        'push' => 'Write',
        'pull' => 'Read',
        'triage' => 'Triage',
        'none' => 'None',
    ];
}

function permission_nice($permission) {
    return nice_permissions()[$permission];
}

function max_permission($permissions) {
    foreach (array_keys(nice_permissions()) as $permission) {
        if ($permission == 'none') {
            continue;
        }
        if ($permissions[$permission]) {
            return $permission;
        }
    }
    return 'none';
}

function user_permission_is_higher($maxUserPermission, $maxTeamPermission) {
    if ($maxUserPermission == $maxTeamPermission) {
        return false;
    }
    $permissions = nice_permissions();
    foreach (array_keys($permissions) as $permission) {
        if ($permission == $maxUserPermission) {
            return true;
        }
        if ($permission == $maxTeamPermission) {
            return false;
        }
    }
    throw new Exception('Unknown permission');
}