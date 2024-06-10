<?php

function github_api($url, $data = [], $httpMethod = '') {
    // silverstripe-themes has a kind of weird redirect only for api requests
    $url = str_replace('/silverstripe-themes/silverstripe-simple', '/silverstripe/silverstripe-simple', $url);
    $method = $httpMethod ? strtoupper($httpMethod) : 'GET';
    print("Making $method curl request to $url\n");
    $token = getenv('TOKEN');
    $jsonStr = empty($data) ? '' : json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, !empty($data));
    if ($httpMethod) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
    }
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
        print("Failure calling github api: $url\n");
        exit;
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