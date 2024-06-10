<?php

include 'env.php';
include 'functions.php';

$organisation = $argv[1];
$admins = [];
$teams = [];
$repos = [];

# Fetch data

$ghrepos = [];
for ($i = 1; $i <= 10; $i++) {
    $reposData = github_api("https://api.github.com/orgs/$organisation/repos?per_page=100&page=$i");
    foreach ($reposData as $repoData) {
        $ghrepos[] = $repoData['full_name'];
    }
    if (count($reposData) < 100) {
        break;
    }
}

foreach ($ghrepos as $ghrepo) {
    $repo = [
        'name' => $ghrepo,
        'team_names' => [],
        'extra_users' => [],
    ];
    // Get repo teams
    foreach (github_api("https://api.github.com/repos/$ghrepo/teams") as $teamData) {
        $teamName = $teamData['name'];
        // Update $teams if it hasn't been set
        if (!isset($teams[$teamName])) {
            $membersUrl = str_replace('{/member}', '', $teamData['members_url']);
            $members = [];
            foreach (github_api($membersUrl) as $memberData) {
                $members[] = $memberData['login'];
            };
            $teams[$teamName] = [
                'id' => $teamData['id'],
                'name' => $teamName,
                'members' => $members,
                'max_permission' => max_permission($teamData['permissions']),
            ];
        }
        $repo['team_names'][] = $teamName;
    }
    sort($repo['team_names']);
    // Get repo users
    foreach (github_api("https://api.github.com/repos/$ghrepo/collaborators") as $userData) {
        $user = $userData['login'];
        // check if the user is an admin (organisation Owner)
        // organisation owners are on every repo in the org
        // there doesn't appear to be an API endpoint for https://github.com/orgs/<organisation>/people
        // if someone is in this report when they shouldn't be, likely that the team permissions are
        // too high on a particular repo - see report-repo-teams.txt
        $maxUserPermission = max_permission($userData['permissions']);
        if ($maxUserPermission == 'admin') {
            $admins[$user] = true;
            continue;
        }
        // check if user is already in a team
        $inTeam = false;
        foreach ($teams as $team) {
            if (!in_array($user, $team['members'])) {
                continue;
            }
            // check if the user has a higher permission than the team
            // if so, then count them as not in the team
            if (user_permission_is_higher($maxUserPermission, $team['max_permission'])) {
                continue;
            }
            $inTeam = true;
            break;
        }
        if (!$inTeam) {
            $repo['extra_users'][] = $user;
        }
        $repos[$ghrepo] = $repo;
    }
}

# Create reports

// Admins report
$lines = ['# Admins', ''];
ksort($admins);
foreach ($admins as $admin => $true) {
    $lines[] = "- $admin";
}
$lines[] = '';
file_put_contents('report-admins.txt', implode("\n", $lines));

// Teams report
$lines = ['# Teams', ''];
// create a sorted copy of $teams, will loose assoc array key during sort
$teamsForReport = $teams;
usort($teamsForReport, fn($a, $b) => $a['name'] <=> $b['name']);
foreach ($teamsForReport as $team) {
    $teamName = $team['name'];
    $permissionNice = permission_nice($team['max_permission']);
    $lines[] = "$teamName ($permissionNice)";
    sort($team['members']);
    foreach ($team['members'] as $member) {
        $lines[] = "- $member";
    }
    $lines[] = '';
}
file_put_contents('report-teams.txt', implode("\n", $lines));

// Team-repos report
$teamRepos = [];
$lines = ['# Team-repos', ''];
foreach ($repos as $ghrepo => $repo) {
    $combinedTeams = implode(', ', array_map(function($teamName) use ($teams) {
        $team = $teams[$teamName];
        return sprintf('%s (%s)', $team['name'], permission_nice($team['max_permission']));
    }, $repo['team_names']));
    $teamRepos[$combinedTeams] ??= [];
    $teamRepos[$combinedTeams][] = $ghrepo;
}
ksort($teamRepos);
foreach ($teamRepos as $combinedTeams => $ghrepos) {
    $lines[] = "# $combinedTeams";
    foreach ($ghrepos as $ghrepo) {
        $lines[] = "- $ghrepo";
    }
    $lines[] = '';
}
file_put_contents('report-repo-teams.txt', implode("\n", $lines));

// Extra users report
$extraUserRepos = [];
foreach ($repos as $ghrepo => $repo) {
    foreach ($repo['extra_users'] as $user) {
        $extraUserRepos[$user] ??= [];
        $extraUserRepos[$user][] = $ghrepo;
    }
}
$lines = ['# Extra users', ''];
foreach ($extraUserRepos as $user => $ghrepos) {
    $lines[] = $user;
    foreach ($ghrepos as $ghrepo) {
        $lines[] = "- $ghrepo";
    }
    $lines[] = '';
}
file_put_contents('report-extra-users.txt', implode("\n", $lines));
