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
      "config": 0,
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
  "desc": "Optional description"
}
```

| Parameter | Type   | Required | Description                              |
|-----------|--------|----------|------------------------------------------|
| `name`    | string | yes      | Circle name (min 3 characters)           |
| `owner`   | string | no       | User ID of owner. Defaults to admin user |
| `desc`    | string | no       | Circle description                       |

> **Note**: The description field is named `desc` (not `description`) due to a Nextcloud OCS framework limitation.

**Response** `201`
```json
{
  "ocs": {
    "data": {
      "id": "abc123",
      "name": "New Circle",
      "owner": "john",
      "memberCount": 1,
      "config": 0,
      "source": 16,
      "description": "Optional description"
    }
  }
}
```

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
| `status`       | string | Membership status                          |
| `userType`     | int    | Member type (1=User, 2=Group, 16=Circle, etc.) |
| `userTypeName` | string | Human-readable type name                   |

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

**Body**
```json
{
  "userId": "jane"
}
```

| Parameter | Type   | Required | Description          |
|-----------|--------|----------|----------------------|
| `userId`  | string | yes      | Nextcloud user ID    |

**Response** `201`
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
      "userType": 1,
      "userTypeName": "User"
    }
  }
}
```

**Errors**: `400` User not found, already a member, or circle not found

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

# 1. Create circle with description (owner: alice)
curl -u $AUTH $HEADERS -X POST "$BASE/circles" \
  -d '{"name":"Project X","owner":"alice","desc":"Main project circle"}'

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
