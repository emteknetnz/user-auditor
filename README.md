# user-auditor

Create reports of groups and users on github

## Token

Create a classic token on github and tick the "repo" checkboxes which will automatically tick the checkboxes below it

https://github.com/settings/tokens/new

Remember to revoke the token when you are done using it

## Usage

`TOKEN=abc run.php [organisation] [notOrgAdmins]`

- `organisation` is the name of the organisation you want to audit
- `notOrgAdmins` and optional comma separated list of users who are not org admins and should be instead identified as "extra users". Include is there are users in `report-admins.txt` who should not be there
