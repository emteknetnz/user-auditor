# user-auditor

Create reports of groups and users on github

## GitHub users

### Token

Create a classic token on github and tick the "repo" checkboxes which will automatically tick the checkboxes below it

https://github.com/settings/tokens/new

Remember to revoke the token when you are done using it

### Usage

`TOKEN=abc php github.php [organisation] [notOrgAdmins]`

- `organisation` is the name of the organisation you want to audit
- `notOrgAdmins` and optional comma separated list of users who are not org admins and should be instead identified as "extra users". Include is there are users in `report-admins.txt` who should not be there

## Packagist

php packagist.php [organisation]

## Shred

run `php shred.php` to remove any txt + json files created by run. This will use the `shred` command to overwrite the files with random data before deleting them.
