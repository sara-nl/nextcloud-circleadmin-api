#!/usr/bin/env bash
# End-to-end test against a running Nextcloud with this app enabled.
#
# usage: tests/e2e/e2e.sh <base_url> docker <container>
#        E2E_PW=<password> tests/e2e/e2e.sh <base_url> ssh <user@host> <nextcloud_path>
#
# Creates the users cadtestalice and cadtestbob, and a temporary admin cadtestadmin that is
# always deleted again. Teams whose name starts with "cadtest" are deleted before and after
# the run. A team left without owner cannot be deleted, so that case only runs in docker.
# Checks access to the API, creating, changing and deleting teams, members and
# nested teams, app-managed teams, Team Folders (Nextcloud 35+ with groupfolders) and the
# admin page.
# In ssh mode E2E_PW is required, because the test users would otherwise get a password that
# is public in this repository. Set E2E_CLEANUP=1 to also delete the other test users.
# Needs curl and python3.
set -uo pipefail
BASE=$1; MODE=$2; shift 2
if [ "$MODE" = docker ]; then CONTAINER=$1; else HOST=$1; NCPATH=$2; fi

if [ "$MODE" = ssh ] && [ -z "${E2E_PW:-}" ]; then
	echo "Set E2E_PW to a password of your own when testing a server over ssh." >&2
	exit 2
fi
PW=${E2E_PW:-'Cad-e2e-Pw-2026-Kx4!'}
A=cadtestalice; B=cadtestbob; ADM=cadtestadmin
pass=0; fail=0

occ() {
	if [ "$MODE" = docker ]; then
		docker exec -e OC_PASS="$PW" -u www-data "$CONTAINER" php occ "$@"
	else
		ssh -o BatchMode=yes "$HOST" "sudo -n -u www-data env OC_PASS=$(printf '%q' "$PW") php $NCPATH/occ $(printf '%q ' "$@")"
	fi
}
check() { if [ "$2" = "$3" ]; then echo "PASS  $1"; pass=$((pass+1)); else echo "FAIL  $1 (expected '$3', got '$2')"; fail=$((fail+1)); fi; }
skip() { echo "SKIP  $1"; }

# Calls the circlesadmin API as <user>, prints "<http status> <ocs data as json>"
api() { # <user> <method> <path> [json body]
	local user=$1 method=$2 path=$3 body=${4:-}
	curl -s -u "$user:$PW" -H 'OCS-APIRequest: true' -H 'Accept: application/json' -H 'Content-Type: application/json' \
		-X "$method" ${body:+-d "$body"} -w '\n%{http_code}' "$BASE/ocs/v2.php/apps/circlesadmin/api/v1$path" |
		python3 -c 'import json,sys
body, status = sys.stdin.read().rsplit("\n", 1)
try:
    data = json.loads(body)["ocs"]["data"]
except Exception:
    data = None
print(status + " " + json.dumps(data))'
}
status() { echo "${1%% *}"; }
# Evaluates a Python expression on the data of an api response (as d). Lists are printed
# sorted and comma separated, booleans as yes/no, a failing expression as "-".
get() { # <api response> <python expression>
	python3 -c 'import json,sys
d = json.loads(sys.argv[1].split(" ", 1)[1])
try:
    r = eval(sys.argv[2])
except Exception:
    r = "-"
if isinstance(r, bool):
    r = "yes" if r else "no"
elif isinstance(r, list):
    r = ",".join(sorted(str(x) for x in r))
print(r)' "$1" "$2"
}
# Level name of <user id> in the members of an api response, or "none"
level_of() { get "$1" "next((m['levelName'] for m in d['members'] if m['userId'] == '$2'), 'none')"; }
member_id_of() { get "$1" "next(m['id'] for m in d['members'] if m['userId'] == '$2')"; }
create() { api $ADM POST /circles "$1"; }
show() { api $ADM GET "/circles/$1"; }
destroy() { api $ADM DELETE "/circles/$1"; }
in_list() { # yes/no, or "error <status>" when listing fails
	local r
	r=$(api $ADM GET /circles)
	if [ "$(status "$r")" = 200 ]; then get "$r" "'$1' in [c['id'] for c in d]"; else echo "error $(status "$r")"; fi
}
# Only regular teams (source 16): the users' own single teams are also named cadtest...
# Teams without owner are skipped, Circles cannot delete them.
delete_test_teams() {
	local id
	for id in $(get "$(api $ADM GET /circles)" "[c['id'] for c in d if c['source'] == 16 and c['owner'] is not None and c['name'].startswith('cadtest ')]" | tr ',' ' '); do
		destroy "$id" >/dev/null
	done
}
# yes when groupfolders has a Team Folder linked to <team id>
has_team_folder() {
	occ groupfolders:list --output=json | python3 -c 'import json,sys
print("yes" if any(f.get("team_circle_id") == sys.argv[1] for f in json.load(sys.stdin)) else "no")' "$1"
}
admin_page() { curl -s -u "$1:$PW" "$BASE/index.php/settings/admin/circlesadmin"; }
admin_page_status() { curl -s -o /dev/null -w '%{http_code}' -u "$1:$PW" "$BASE/index.php/settings/admin/circlesadmin"; }

# --- setup ---------------------------------------------------------------
cleanup() { delete_test_teams; occ user:delete $ADM >/dev/null 2>&1; }
trap cleanup EXIT

for u in $A $B; do
	occ user:add --password-from-env --display-name="$u" $u >/dev/null 2>&1 || occ user:resetpassword --password-from-env $u >/dev/null
done
occ user:add --password-from-env --display-name="$ADM" --group=admin $ADM >/dev/null 2>&1 || occ user:resetpassword --password-from-env $ADM >/dev/null
delete_test_teams

version=$(occ status --output=json | python3 -c 'import json,sys; print(json.load(sys.stdin)["versionstring"])')
major=${version%%.*}
echo "== Nextcloud $version"

# --- access --------------------------------------------------------------
check "anonymous requests are rejected" \
	"$(curl -s -o /dev/null -w '%{http_code}' -H 'OCS-APIRequest: true' "$BASE/ocs/v2.php/apps/circlesadmin/api/v1/circles")" 401
check "users who are not admin are rejected"                "$(status "$(api $A GET /circles)")" 403
check "users who are not admin cannot create teams"         "$(status "$(api $A POST /circles '{"name": "cadtest by alice"}')")" 403
check "admin page loads"                                    "$(admin_page_status $ADM)" 200
check "admin page shows the API reference"                  "$(admin_page $ADM | grep -c 'Circles Admin API' | tr -d ' ' | sed 's/^[1-9][0-9]*$/yes/')" yes
check "admin page is not shown to other users"              "$(admin_page $A | grep -c 'Circles Admin API' | tr -d ' ')" 0

# --- a regular team ------------------------------------------------------
r=$(create "{\"name\": \"cadtest team\", \"owner\": \"$A\", \"desc\": \"first\"}")
check "create a team"                                       "$(status "$r")" 201
T=$(get "$r" "d['id']")
check "the team has the given owner"                        "$(get "$r" "d['owner']")" $A
check "the team has the given description"                  "$(get "$r" "d['description']")" first
check "a new team is local"                                 "$(get "$r" "d['configFlags']")" local
check "a new team is not app-managed"                       "$(get "$r" "d['appManaged']")" no
check "the team is listed"                                  "$(in_list "$T")" yes
r=$(show "$T")
check "show the team"                                       "$(status "$r")" 200
check "the owner is a member at level Owner"                "$(level_of "$r" $A)" Owner
check "show an unknown team"                                "$(status "$(show doesnotexist)")" 404

r=$(api $ADM PUT "/circles/$T" '{"name": "cadtest renamed", "description": "second"}')
check "rename the team"                                     "$(get "$r" "d['name']")" 'cadtest renamed'
check "change the description"                              "$(get "$(show "$T")" "d['description']")" second
check "an update without fields is refused"                 "$(status "$(api $ADM PUT "/circles/$T" '{}')")" 400

check "enable config flags"                                 "$(get "$(api $ADM PUT "/circles/$T/config" '{"visible": true, "open": 1}')" "d['configFlags']")" local,open,visible
check "disable a config flag"                               "$(get "$(api $ADM PUT "/circles/$T/config" '{"open": false}')" "d['configFlags']")" local,visible
check "a config change without known flags is refused"      "$(status "$(api $ADM PUT "/circles/$T/config" '{"nosuchflag": true}')")" 400

# --- members -------------------------------------------------------------
r=$(api $ADM POST "/circles/$T/members" "{\"userId\": \"$B\"}")
check "add a member"                                        "$(status "$r")" 201
check "a new member has level Member"                       "$(get "$r" "d['levelName']")" Member
BM=$(get "$r" "d['id']")
r=$(api $ADM POST "/circles/$T/members" "{\"userId\": \"$B\", \"type\": \"group\"}")
check "a member of an unknown type is refused"              "$(status "$r")" 400
check "an unknown user is refused"                          "$(status "$(api $ADM POST "/circles/$T/members" '{"userId": "cadtestnobody"}')")" 400
check "promote the member to Moderator"                     "$(status "$(api $ADM PUT "/circles/$T/members/$BM/level" '{"level": 4}')")" 200
check "the member is now Moderator"                         "$(level_of "$(show "$T")" $B)" Moderator
check "an invalid level is refused"                         "$(status "$(api $ADM PUT "/circles/$T/members/$BM/level" '{"level": 5}')")" 400

r=$(create '{"name": "cadtest other"}')
O=$(get "$r" "d['id']")
OM=$(member_id_of "$(show "$O")" $ADM)
check "a member of another team cannot be removed here"     "$(status "$(api $ADM DELETE "/circles/$T/members/$OM")")" 400
check "a member of another team cannot be changed here"     "$(status "$(api $ADM PUT "/circles/$T/members/$OM/level" '{"level": 1}')")" 400
check "the other team keeps its owner"                      "$(level_of "$(show "$O")" $ADM)" Owner
check "remove the member"                                   "$(status "$(api $ADM DELETE "/circles/$T/members/$BM")")" 200
check "the member is gone"                                  "$(level_of "$(show "$T")" $B)" none

# --- creating with options -----------------------------------------------
r=$(create "{\"name\": \"cadtest federated\", \"federated\": true, \"members\": [{\"userId\": \"$B\", \"level\": 4}, {\"userId\": \"cadtestnobody\"}]}")
check "create a team with flags and members"                "$(status "$r")" 201
check "the flags are set"                                   "$(get "$r" "d['configFlags']")" federated,local
check "the team is federated"                               "$(get "$(show "$(get "$r" "d['id']")")" "d['federated']")" yes
check "the member is added at the given level"              "$(level_of "$r" $B)" Moderator
check "a member that cannot be added is reported"           "$(get "$r" "[e['userId'] for e in d['memberErrors']]")" cadtestnobody

r=$(create "{\"name\": \"cadtest parent\", \"members\": [{\"userId\": \"$T\", \"type\": \"circle\"}]}")
check "create a team with a nested team"                    "$(get "$r" "[m['circle']['id'] for m in d['members'] if 'circle' in m]")" "$T"
P=$(get "$r" "d['id']")
check "the nested team is a member of type Circle"          "$(get "$(show "$P")" "[m['userTypeName'] for m in d['members'] if m['singleId'] == '$T']")" Circle
r=$(api $ADM POST "/circles/$O/members" "{\"userId\": \"$T\", \"type\": \"circle\"}")
check "add a team as member"                                "$(get "$r" "d['circle']['id']")" "$T"
r=$(create "{\"name\": \"cadtest team owned\", \"owner\": \"$T\", \"owner_type\": \"circle\"}")
check "create a team owned by a team"                       "$(status "$r")" 201
check "the owner is the team"                               "$(get "$r" "d['owner']")" 'cadtest renamed'
TO=$(get "$r" "d['id']")

# --- app-managed teams ---------------------------------------------------
r=$(create "{\"name\": \"cadtest managed\", \"appManaged\": true, \"owner\": \"$A\", \"desc\": \"locked\", \"visible\": true}")
check "create an app-managed team"                          "$(status "$r")" 201
check "no manager error"                                    "$(get "$r" "d.get('managerError', 'none')")" none
M=$(get "$r" "d['id']")
r=$(show "$M")
check "the app-managed team is flagged"                     "$(get "$r" "d['appManaged']")" yes
check "the app-managed team is owned by the Circles app"    "$(get "$r" "d['owner']")" circles
check "the manager is Moderator"                            "$(level_of "$r" $A)" Moderator
check "the app-managed team has its description"            "$(get "$r" "d['description']")" locked
check "the app-managed team has its flags"                  "$(get "$r" "d['configFlags']")" visible
r=$(create "{\"name\": \"cadtest managed admin\", \"appManaged\": true, \"owner\": \"$A\", \"role\": \"admin\"}")
check "the manager can be Admin"                            "$(level_of "$r" $A)" Admin
r=$(create '{"name": "cadtest managed alone", "appManaged": true}')
check "an app-managed team without manager"                 "$(status "$r")" 201
check "an app-managed team without manager has no members"  "$(get "$r" "d.get('members', [])")" ''
r=$(api $ADM POST "/circles/$M/members" "{\"userId\": \"$B\"}")
check "add a member to an app-managed team"                 "$(status "$r")" 201
check "delete the app-managed team"                         "$(status "$(destroy "$M")")" 200
check "the app-managed team is gone"                        "$(status "$(show "$M")")" 404

# --- Team Folders ----------------------------------------------------------
if [ "$major" -ge 35 ] && occ app:list --enabled --output=json | grep -q '"groupfolders"'; then
	F=$(get "$(create '{"name": "cadtest folder"}')" "d['id']")
	check "a new team gets a Team Folder"                     "$(has_team_folder "$F")" yes
	N=$(get "$(create '{"name": "cadtest nofolder", "teamFolder": false}')" "d['id']")
	check "teamFolder=false creates no Team Folder"           "$(has_team_folder "$N")" no
	r=$(create "{\"name\": \"cadtest managed folder\", \"appManaged\": true, \"owner\": \"$A\"}")
	check "an app-managed team with a Team Folder"            "$(status "$r")" 201
	MF=$(get "$r" "d['id']")
	check "the app-managed team gets a Team Folder"           "$(has_team_folder "$MF")" yes
	check "the app-managed team with a Team Folder is flagged" "$(get "$(show "$MF")" "d['appManaged']")" yes
	check "the manager is added next to the Team Folder"      "$(level_of "$r" $A)" Moderator
	destroy "$F" >/dev/null
	check "deleting a team removes its Team Folder"           "$(has_team_folder "$F")" no
else
	skip "Team Folders (needs Nextcloud 35+ with groupfolders enabled)"
fi

# --- deleting --------------------------------------------------------------
# Deleting T leaves the team it owns without owner, and Circles cannot delete such a team.
# Only test that in docker, where the container is thrown away afterwards.
if [ "$MODE" != docker ]; then
	destroy "$TO" >/dev/null
fi
check "delete the team"                                     "$(status "$(destroy "$T")")" 200
check "the team is gone"                                    "$(status "$(show "$T")")" 404
check "the team is no longer listed"                        "$(in_list "$T")" no
if [ "$MODE" = docker ]; then
	check "a team whose owner team was deleted is still listed" "$(in_list "$TO")" yes
	check "a team whose owner team was deleted has no owner"    "$(get "$(show "$TO")" "d['owner']")" None
else
	skip "a team whose owner team was deleted (leaves a team that cannot be deleted, only in docker mode)"
fi

if [ "${E2E_CLEANUP:-0}" = 1 ]; then
	for u in $A $B; do occ user:delete $u >/dev/null; done
fi

echo "== $pass passed, $fail failed"
[ "$fail" = 0 ]
