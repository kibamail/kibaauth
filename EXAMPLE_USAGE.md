# Team Member Email Functionality - Example Usage

This document demonstrates how to use the email-based team member functionality through API calls.

## Prerequisites

1. You have a running KibaAuth instance
2. You have valid OAuth credentials
3. You have created a workspace and team

## API Examples

### 1. Create Team Member with User ID (Existing User)

```bash
curl -X POST "http://localhost:8000/api/workspaces/1/teams/1/members" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 123,
    "status": "active"
  }'
```

**Response:**
```json
{
  "message": "Team member added successfully",
  "data": {
    "id": 1,
    "team_id": 1,
    "user_id": 123,
    "email": null,
    "status": "active",
    "created_at": "2025-08-12T12:34:56.000000Z",
    "updated_at": "2025-08-12T12:34:56.000000Z",
    "user": {
      "id": 123,
      "email": "john@example.com"
    }
  }
}
```

### 2. Create Team Member with Email (Existing User)

When you provide an email that belongs to an existing user, the system automatically converts it to a user_id-based membership:

```bash
curl -X POST "http://localhost:8000/api/workspaces/1/teams/1/members" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "jane@example.com",
    "status": "active"
  }'
```

**Response (if jane@example.com is a registered user):**
```json
{
  "message": "Team member added successfully",
  "data": {
    "id": 2,
    "team_id": 1,
    "user_id": 456,
    "email": null,
    "status": "active",
    "created_at": "2025-08-12T12:34:56.000000Z",
    "updated_at": "2025-08-12T12:34:56.000000Z",
    "user": {
      "id": 456,
      "email": "jane@example.com"
    }
  }
}
```

### 3. Create Email Invitation (Non-Existing User)

When you provide an email that doesn't belong to any registered user, the system creates an email-only invitation:

```bash
curl -X POST "http://localhost:8000/api/workspaces/1/teams/1/members" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "newuser@example.com",
    "status": "pending"
  }'
```

**Response (if newuser@example.com is not registered):**
```json
{
  "message": "Team member added successfully",
  "data": {
    "id": 3,
    "team_id": 1,
    "user_id": null,
    "email": "newuser@example.com",
    "status": "pending",
    "created_at": "2025-08-12T12:34:56.000000Z",
    "updated_at": "2025-08-12T12:34:56.000000Z"
  }
}
```

## Error Examples

### 4. Validation Error - Both user_id and email provided

```bash
curl -X POST "http://localhost:8000/api/workspaces/1/teams/1/members" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 123,
    "email": "test@example.com",
    "status": "active"
  }'
```

**Error Response (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "user_id": ["Cannot provide both user_id and email. Choose one."],
    "email": ["Cannot provide both user_id and email. Choose one."]
  }
}
```

### 5. Validation Error - Neither user_id nor email provided

```bash
curl -X POST "http://localhost:8000/api/workspaces/1/teams/1/members" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "active"
  }'
```

**Error Response (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "user_id": ["Either user_id or email must be provided."],
    "email": ["Either user_id or email must be provided."]
  }
}
```

### 6. Duplicate Error - User already in team

```bash
curl -X POST "http://localhost:8000/api/workspaces/1/teams/1/members" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com"
  }'
```

**Error Response (422) - if john@example.com is already a team member:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["A user with this email is already a member of this team."]
  }
}
```

### 7. Duplicate Error - Email already invited

```bash
curl -X POST "http://localhost:8000/api/workspaces/1/teams/1/members" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "newuser@example.com"
  }'
```

**Error Response (422) - if newuser@example.com already has a pending invitation:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["This email has already been invited to this team."]
  }
}
```

## PHP Code Examples

### Using the Models Directly

```php
use App\Models\Team;
use App\Models\User;
use App\Models\TeamMember;

// Add existing user to team
$team = Team::find(1);
$user = User::find(123);

$teamMember = TeamMember::create([
    'team_id' => $team->id,
    'user_id' => $user->id,
    'email' => null,
    'status' => 'active'
]);

// Create email invitation
$emailInvitation = TeamMember::create([
    'team_id' => $team->id,
    'user_id' => null,
    'email' => 'invite@example.com',
    'status' => 'pending'
]);

// Check member types
if ($teamMember->hasUser()) {
    echo "This is a registered user: " . $teamMember->user->email;
}

if ($emailInvitation->isEmailOnly()) {
    echo "This is an email invitation: " . $emailInvitation->email;
}

// Get display name (works for both types)
echo $teamMember->display_name; // User's email
echo $emailInvitation->display_name; // Invitation email

// Query team members
$allMembers = $team->teamMembers()->with('user')->get();

foreach ($allMembers as $member) {
    if ($member->hasUser()) {
        echo "User: " . $member->user->email . " (Status: " . $member->status . ")";
    } else {
        echo "Invitation: " . $member->email . " (Status: " . $member->status . ")";
    }
}
```

### Smart Email Handling Logic

The controller automatically handles the email logic:

```php
// If email belongs to existing user
$existingUser = User::where('email', $email)->first();
if ($existingUser) {
    // Convert to user_id based membership
    $userId = $existingUser->id;
    $email = null;
} else {
    // Store as email invitation
    $userId = null;
    // $email remains as provided
}

$teamMember = TeamMember::create([
    'team_id' => $team->id,
    'user_id' => $userId,
    'email' => $email,
    'status' => $status
]);
```

## Database Structure

The `team_members` table supports both scenarios:

```sql
-- User-based membership
INSERT INTO team_members (team_id, user_id, email, status) 
VALUES (1, 123, NULL, 'active');

-- Email-based invitation  
INSERT INTO team_members (team_id, user_id, email, status) 
VALUES (1, NULL, 'invite@example.com', 'pending');
```

## Use Cases

1. **Existing Users**: Use `user_id` when you know the user exists in your system
2. **Email Lookup**: Use `email` when you want the system to automatically check if the user exists
3. **Invitations**: Use `email` for users who haven't signed up yet - they'll get email-only records
4. **Bulk Imports**: Use email for importing from external systems where you don't know user IDs

This flexible approach allows for both immediate team membership (for existing users) and invitation-based workflows (for new users).