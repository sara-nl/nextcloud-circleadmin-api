# Circles Admin API

Admin management for all Nextcloud Circles/Teams — without being a member.

## Authentication

All endpoints require **Nextcloud admin** credentials via Basic Auth and the OCS header:

```bash
curl -u admin:password \
  -H "OCS-APIRequest: true" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  https://your-nextcloud.example.com/ocs/v2.php/apps/circlesadmin/api/v1/...
```

Non-admin users receive `401` or `403`.

## Base URL

```
/ocs/v2.php/apps/circlesadmin/api/v1
```

---

## Circles

### List all circles

```
GET /circles
```

Returns all circles on the instance (including system, hidden, and backend circles).

**Response** `200`
```json
{
  "ocs": {
    "data": [
      {
        "id": "abc123",
        "name": "My Circle",
        "owner": "john",
        "memberCount": 3,
        "config": 0,
        "configFlags": [],
        "appManaged": false,
        "federated": false,
        "source": 16
      }
    ]
  }
}
```

---

### Get circle details

```
GET /circles/{circleId}
```

Returns circle info including description and all members.

**Response** `200`
```json
{
  "ocs": {
    "data": {
      "id": "abc123",
      "name": "My Circle",
      "owner": "john",
      "memberCount": 3,
      "config": 24,
      "configFlags": ["visible", "open"],
      "appManaged": false,
      "federated": false,
      "source": 16,
      "description": "A description for the circle",
      "members": [
        {
          "id": "mem456",
          "singleId": "single789",
          "userId": "john",
          "displayName": "John Doe",
          "level": 9,
          "levelName": "Owner",
          "status": "Member",
          "statusName": "Member",
          "userType": 1,
          "userTypeName": "User"
        }
      ]
    }
  }
}
```

**Errors**: `404` Circle not found

---

### Create circle

```
POST /circles
```

**Body**
```json
{
  "name": "New Circle",
  "owner": "john",
  "desc": "Optional description",
  "federated": true
}
```

| Parameter    | Type   | Required | Description                              |
|--------------|--------|----------|------------------------------------------|
| `name`       | string | yes      | Circle name (min 3 characters)           |
| `owner`      | string | no       | User ID of owner. Defaults to admin user |
| `desc`       | string | no       | Circle description                       |
| `federated`  | bool   | no       | `true` enables the federated flag on the new team. Default: local. |
| `appManaged` | bool   | no       | `true` creates an **app-managed (locked) team** — see [App-managed teams](#app-managed-locked-teams) |
| `role`       | string | no       | For app-managed teams only: role of the given `owner` — `moderator` (default), `admin`, or `member` |
| `members`    | array  | no       | Members to add to the new team in the same request (see below) |
| *config flags* | bool | no       | Any [config flag](#team-configuration-flags) can be enabled on creation, e.g. `federated`, `visible`, `open`. Only flags sent as `true` are applied; a flag sent as `false` (or omitted) is a no-op. |

**Adding members at creation.** Pass a `members` array to create the team and populate it in one request. Each entry:

| Field    | Type   | Required | Description                                                    |
|----------|--------|----------|----------------------------------------------------------------|
| `userId` | string | yes      | User ID, or (for `type: circle`) the single ID of the team to nest |
| `type`   | string | no       | `user` (default) or `circle`                                   |
| `level`  | int    | no       | `1` Member (default), `4` Moderator, `8` Admin, `9` Owner      |

Adding members is **best-effort**: the team is still created if a member fails, and each failure is reported under `memberErrors` in the response. Successfully added members are returned under `members`.

> ⚠️ On an **app-managed** team, do not use `"level": 9` (Owner) — it transfers ownership away from the app. See [App-managed teams](#app-managed-locked-teams).

```json
{
  "name": "Example Team in Team",
  "owner": "alice",
  "desc": "A team with a nested team",
  "members": [
    { "userId": "childTeamSingleId", "type": "circle", "level": 4 }
  ]
}
```

> **Note**: The description field is named `desc` (not `description`) due to a Nextcloud OCS framework limitation.

> **Note**: Config flags and members are applied as part of the create, so a single request can create a federated (or otherwise configured) team and populate it with users and nested teams at chosen levels.

**Response** `201`
```json
{
  "ocs": {
    "data": {
      "id": "abc123",
      "name": "New Circle",
      "owner": "john",
      "memberCount": 1,
      "config": 36864,
      "configFlags": ["local", "federated"],
      "appManaged": false,
      "federated": true,
      "source": 16,
      "description": "Optional description"
    }
  }
}
```

Every circle object now also carries:

| Field         | Type   | Description                                                        |
|---------------|--------|-------------------------------------------------------------------|
| `configFlags` | array  | Readable list of the user-settable flags currently set (see below) |
| `appManaged`  | bool   | `true` if the team is locked from the Nextcloud UI (`CFG_APP`)     |
| `federated`   | bool   | `true` if federated members can be added (`CFG_FEDERATED`)         |

**Errors**: `400` Invalid name or user not found

---

### Update circle

```
PUT /circles/{circleId}
```

Updates a circle's name and/or description.

**Body**
```json
{
  "name": "Renamed Circle",
  "description": "A description for the circle"
}
```

| Parameter     | Type   | Required | Description                            |
|---------------|--------|----------|----------------------------------------|
| `name`        | string | no       | New circle name (min 3 characters)     |
| `description` | string | no       | New circle description                 |

At least one parameter must be provided.

**Response** `200`
```json
{
  "ocs": {
    "data": {
      "id": "abc123",
      "name": "Renamed Circle",
      "owner": "john",
      "memberCount": 3,
      "config": 0,
      "configFlags": [],
      "appManaged": false,
      "federated": false,
      "source": 16,
      "description": "A description for the circle"
    }
  }
}
```

**Errors**: `400` Circle not found, invalid name, or no parameters provided

---

### Delete circle

```
DELETE /circles/{circleId}
```

Permanently deletes a circle regardless of who owns it.

**Response** `200`
```json
{
  "ocs": {
    "data": {
      "message": "Circle deleted"
    }
  }
}
```

**Errors**: `400` Circle not found

> App-managed teams (`CFG_APP`) cannot normally be deleted through the OCS API. This endpoint handles that automatically: it clears the app-managed flag first, then deletes the team.

---

## Team configuration (flags)

A team's behaviour is controlled by a bitmask (`config`). This API exposes the user-settable flags by name so you don't have to work with raw bit values.

### Update config flags

```
PUT /circles/{circleId}/config
```

Send one or more flag names with a truthy (`1`, `true`) or falsy (`0`, `false`) value. Flags not mentioned are left unchanged. You can set and unset several flags in a single request.

**Body**
```json
{
  "federated": true,
  "visible": true,
  "local": false
}
```

**Available flags**

| Flag         | Bit    | Nextcloud UI setting                                   | Meaning                                                        |
|--------------|--------|--------------------------------------------------------|----------------------------------------------------------------|
| `visible`    | 8      | Zichtbaar voor iedereen / *Visible to everyone*        | Team is found in search; otherwise you must know its name      |
| `open`       | 16     | Iedereen kan lid worden / *Anyone can join*            | Anyone can join without an invitation                          |
| `invite`     | 32     | Leden moeten de uitnodiging accepteren                 | Adding a member creates an invitation they must accept         |
| `request`    | 64     | Lidmaatschap moet bevestigd door een moderator         | Join requests need moderator approval                          |
| `friend`     | 128    | Leden kunnen anderen uitnodigen                        | Members may invite others                                      |
| `protected`  | 256    | Wachtwoordbeveiliging afdwingen voor **gedeelde bestanden** | Enforces a password on **file shares** made with this team — **not** a password to join the team (see note) |
| `local`      | 4096   | *(local team)*                                         | Team stays on this instance only; the opposite of `federated`  |
| `federated`  | 32768  | Sta federatieleden toe / *Allow federated members*     | **Lets federated users be added via the Contacts app**         |
| `mountpoint` | 65536  | *(no UI checkbox)*                                     | Generates a Files folder for the team                          |

**Response** `200` — the updated circle object (including the new `configFlags` and `federated` fields).

**Errors**: `400` Unknown flag name, circle not found, or no flags provided

> **About `protected`**: this maps to Nextcloud's *"Enforce password protection for files shared with this team"*, so its effect is only visible when you share a file with the team. It is **not** a password to join the team, and no team password is stored. The related *"Use a single password for all shares"* option is a separate setting that this API does not manage.

> **`local` vs `federated`**: these are opposites. For a genuinely federated team, set `federated=true` and `local=false`. Nextcloud may re-assert `local` if you enable `federated` without disabling it.

### Enable federated members (common case)

To let a team accept federated users from other Nextcloud instances via the Contacts app:

```bash
# On an existing team
curl -u admin:password -H "OCS-APIRequest: true" -H "Accept: application/json" \
  -X PUT ".../api/v1/circles/{circleId}/config" \
  -d '{"federated": true, "local": false}'

# Or directly at creation
curl -u admin:password -H "OCS-APIRequest: true" -H "Accept: application/json" \
  -X POST ".../api/v1/circles" \
  -d '{"name": "Federated Project", "owner": "alice", "federated": true}'
```

---

## App-managed (locked) teams

An **app-managed team** is owned by the Circles app itself and flagged with `CFG_APP` (131072). Regular users — including admins — **cannot edit its settings, rename it, or delete it from the Nextcloud Teams UI**. Members are managed only through this admin API. This mirrors how Nextcloud's group-backed system circles work.

Create one by passing `appManaged: true`:

```json
{
  "name": "Managed Team",
  "owner": "alice",
  "appManaged": true,
  "role": "moderator"
}
```

- **`owner` is optional.** If given, that user is added to the team at the level set by `role`; if omitted, only the app owns the team and no one can manage it from the UI.
- **`role`** controls what the given user can do in the UI:

  | `role`      | Level | Can manage members (UI) | Can edit settings / delete (UI) |
  |-------------|-------|-------------------------|---------------------------------|
  | `moderator` | 4 (default) | ✅ yes             | ❌ no                            |
  | `admin`     | 8     | ✅ yes                   | ✅ yes (team is no longer fully locked) |
  | `member`    | 1     | ❌ no                    | ❌ no                            |

**Response** `201` includes `"appManaged": true`.

Member management (list / add / remove / set level) and deletion all work as normal through this API — the lock only applies to the Nextcloud front-end.

> ⚠️ **Do not give a human member Owner (level 9) on an app-managed team.** An app-managed team is owned by the Circles app; Circles allows only one owner, so promoting a user to level 9 (via `setMemberLevel` or a `members` entry with `"level": 9`) **transfers ownership away from the app** — the app is demoted to Admin and the team is no longer truly app-managed. Use Admin (8) as the highest level for human managers.

---

## Members

### Member object

All member endpoints return members with these fields:

| Field          | Type   | Description                                |
|----------------|--------|--------------------------------------------|
| `id`           | string | Member ID (use this for remove/level ops)  |
| `singleId`     | string | Single circle ID of the member             |
| `userId`       | string | Nextcloud user ID                          |
| `displayName`  | string | Display name                               |
| `level`        | int    | Permission level (1/4/8/9)                 |
| `levelName`    | string | Human-readable level name                  |
| `status`       | string | Raw membership status from Circles         |
| `statusName`   | string | Friendly status — an invited-but-not-yet-active member reads as `Invited` instead of an empty/unknown value |
| `userType`     | int    | Member type (1=User, 2=Group, 16=Circle, etc.) |
| `userTypeName` | string | Human-readable type name                   |
| `circle`       | object | Present only when the member is a nested team: `{ "id": "<team single ID>", "name": "<team name>" }`. Use this instead of guessing the team from `userId`/`singleId`. |

**User types**:

| Type | Name    |
|------|---------|
| `1`  | User    |
| `2`  | Group   |
| `4`  | Mail    |
| `8`  | Contact |
| `16` | Circle  |

---

### List members

```
GET /circles/{circleId}/members
```

**Response** `200`
```json
{
  "ocs": {
    "data": [
      {
        "id": "mem456",
        "singleId": "single789",
        "userId": "john",
        "displayName": "John Doe",
        "level": 9,
        "levelName": "Owner",
        "status": "Member",
        "statusName": "Member",
        "userType": 1,
        "userTypeName": "User"
      }
    ]
  }
}
```

**Errors**: `404` Circle not found

---

### Add member

```
POST /circles/{circleId}/members
```

Adds a member to a team. By default a Nextcloud user is added; pass `type: "circle"` to add another team as a member (nested teams).

**Body**
```json
{
  "userId": "jane"
}
```

Nest a team inside another team:
```json
{
  "userId": "childTeamSingleId",
  "type": "circle"
}
```

| Parameter | Type   | Required | Description                                                                 |
|-----------|--------|----------|-----------------------------------------------------------------------------|
| `userId`  | string | yes      | The Nextcloud user ID, or (for `type: circle`) the single ID of the team to nest |
| `type`    | string | no       | `user` (default) or `circle`. `circle` adds another team as a member.       |

A nested team is returned with `userType: 16` / `userTypeName: "Circle"` and an explicit `circle` object identifying which team it is.

**Response** `201` (adding a user)
```json
{
  "ocs": {
    "data": {
      "id": "mem789",
      "singleId": "single012",
      "userId": "jane",
      "displayName": "Jane Smith",
      "level": 1,
      "levelName": "Member",
      "status": "Member",
      "statusName": "Member",
      "userType": 1,
      "userTypeName": "User"
    }
  }
}
```

**Response** `201` (adding a team with `type: "circle"`)
```json
{
  "ocs": {
    "data": {
      "id": "mem456",
      "singleId": "childTeamSingleId",
      "userId": "Design Team",
      "displayName": "Design Team",
      "level": 1,
      "levelName": "Member",
      "status": "Member",
      "statusName": "Member",
      "userType": 16,
      "userTypeName": "Circle",
      "circle": {
        "id": "childTeamSingleId",
        "name": "Design Team"
      }
    }
  }
}
```

A nested team can be promoted like any member: `PUT /circles/{id}/members/{memberId}/level` with `{"level": 4}` (Moderator) or `8` (Admin).

**Errors**: `400` User/team not found, already a member, or circle not found

---

### Remove member

```
DELETE /circles/{circleId}/members/{memberId}
```

Removes a member from a circle. Use the `id` field from the member object (not `singleId` or `userId`).

**Response** `200`
```json
{
  "ocs": {
    "data": {
      "message": "Member removed"
    }
  }
}
```

**Errors**: `400` Member not found

---

### Set member level

```
PUT /circles/{circleId}/members/{memberId}/level
```

**Body**
```json
{
  "level": 4
}
```

| Level | Name      | Description                          |
|-------|-----------|--------------------------------------|
| `1`   | Member    | Regular member                       |
| `4`   | Moderator | Can manage members                   |
| `8`   | Admin     | Can manage members and circle config |
| `9`   | Owner     | Full control (transfers ownership)   |

Setting level `9` transfers ownership: the current owner is demoted to Admin.

**Response** `200`
```json
{
  "ocs": {
    "data": {
      "message": "Level updated"
    }
  }
}
```

**Errors**: `400` Invalid level or member not found

---

## Example: Full workflow

```bash
BASE="https://cloud.example.com/ocs/v2.php/apps/circlesadmin/api/v1"
AUTH="admin:password"
HEADERS='-H "OCS-APIRequest: true" -H "Accept: application/json" -H "Content-Type: application/json"'

# 1. Create local circle with description (owner: alice) — default
curl -u $AUTH $HEADERS -X POST "$BASE/circles" \
  -d '{"name":"Project X","owner":"alice","desc":"Main project circle"}'

# 1b. Or create a federated circle
curl -u $AUTH $HEADERS -X POST "$BASE/circles" \
  -d '{"name":"Fed Project","owner":"alice","desc":"Federated circle","federated":true}'

# 2. Update circle name & description
curl -u $AUTH $HEADERS -X PUT "$BASE/circles/{circleId}" \
  -d '{"name":"Project X Renamed","description":"Updated description"}'

# 3. Add member bob
curl -u $AUTH $HEADERS -X POST "$BASE/circles/{circleId}/members" \
  -d '{"userId":"bob"}'

# 4. Promote bob to moderator
curl -u $AUTH $HEADERS -X PUT "$BASE/circles/{circleId}/members/{memberId}/level" \
  -d '{"level":4}'

# 5. Transfer ownership to bob
curl -u $AUTH $HEADERS -X PUT "$BASE/circles/{circleId}/members/{memberId}/level" \
  -d '{"level":9}'

# 6. Remove alice
curl -u $AUTH $HEADERS -X DELETE "$BASE/circles/{circleId}/members/{aliceMemberId}"

# 7. Delete circle
curl -u $AUTH $HEADERS -X DELETE "$BASE/circles/{circleId}"
```

### Example: federated team

```bash
# Create a team that accepts federated members, with alice as owner
curl -u $AUTH $HEADERS -X POST "$BASE/circles" \
  -d '{"name":"Federated Team","owner":"alice","federated":true}'

# Or enable federation on an existing team
curl -u $AUTH $HEADERS -X PUT "$BASE/circles/{circleId}/config" \
  -d '{"federated":true,"local":false}'
```

### Example: app-managed (locked) team

```bash
# Create a locked team; alice becomes a moderator (can manage members,
# but cannot change settings or delete the team from the UI)
curl -u $AUTH $HEADERS -X POST "$BASE/circles" \
  -d '{"name":"Managed Team","owner":"alice","appManaged":true,"role":"moderator"}'

# Members are still managed through the API as usual
curl -u $AUTH $HEADERS -X POST "$BASE/circles/{circleId}/members" -d '{"userId":"bob"}'

# Deletion works too — the API clears the lock automatically
curl -u $AUTH $HEADERS -X DELETE "$BASE/circles/{circleId}"
```
