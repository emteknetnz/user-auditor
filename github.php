<?php

include 'env.php';
include 'functions.php';

$organisation = $argv[1];
$notOrgAdmins = preg_split('#,#', $argv[2] ?? '');
$admins = [];
$teams = [];
$repos = [];
$ghrepoAdmins = [];

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
        'team_names_permissions' => [],
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
                'permissions' => [],
            ];
        }
        // team permission can be different between repos, so collect all of them and then
        // report on the most common one later
        $teams[$teamName]['permissions'][] = $teamData['permission'];
        $teams[$teamName]['max_permissions'][] = max_permission($teamData['permissions']);
        $repo['team_names_permissions'][] = [
            'team_name' => $teamName,
            'permission' => $teamData['permission'],
        ];
    }
    usort($repo['team_names_permissions'], fn($a, $b) => $a['team_name'] <=> $b['team_name']);
    // Get repo users
    foreach (github_api("https://api.github.com/repos/$ghrepo/collaborators") as $userData) {
        $user = $userData['login'];
        // check if the user is an admin (organisation Owner)
        // organisation owners are on every repo in the org
        // there doesn't appear to be an API endpoint for https://github.com/orgs/<organisation>/people
        // if someone is in this report when they shouldn't be, likely that the team permissions are
        // too high on a particular repo - see report-repo-teams.txt
        $maxUserPermission = max_permission($userData['permissions']);
        if ($maxUserPermission == 'admin' && !in_array($user, $notOrgAdmins)) {
            $admins[$user] = true;
            continue;
        }
        // check if user is already in a team
        $inTeam = false;
        foreach ($repo['team_names_permissions'] as $teamNameMaxPermssion) {
            $teamName = $teamNameMaxPermssion['team_name'];
            $teamPermission = $teamNameMaxPermssion['permission'];
            $team = $teams[$teamName];
            if (!in_array($user, $team['members'])) {
                continue;
            }
            // check if the user has a higher permission than the team
            // if so, then count them as not in the team
            if (user_permission_is_higher($maxUserPermission, $teamPermission)) {
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
write_report('report-admins.txt', $lines);

// Teams report
$lines = ['# Teams', ''];
// create a sorted copy of $teams, will loose assoc array key during sort
$teamsForReport = $teams;
usort($teamsForReport, fn($a, $b) => $a['name'] <=> $b['name']);
foreach ($teamsForReport as $team) {
    $teamName = $team['name'];
    $permissionNice = permission_nice(most_common($team['permissions']));
    $lines[] = "$teamName ($permissionNice)";
    sort($team['members']);
    foreach ($team['members'] as $member) {
        $lines[] = "- $member";
    }
    $lines[] = '';
}
write_report('report-teams.txt', $lines);

// Team-repos report
$teamRepos = [];
$lines = ['# Team-repos', ''];
foreach ($repos as $ghrepo => $repo) {
    $combinedTeams = implode(', ', array_map(function($teamMaxPermission) {
        $teamName = $teamMaxPermission['team_name'];
        return sprintf('%s (%s)', $teamName, permission_nice($teamMaxPermission['permission']));
    }, $repo['team_names_permissions']));
    if (empty($combinedTeams)) {
        $combinedTeams = 'No teams';
    }
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
write_report('report-repo-teams.txt', $lines);

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
write_report('report-extra-users.txt', $lines);
