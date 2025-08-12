# Team Members Functionality

This document describes the team member functionality implemented in the KibaAuth application.

## Overview

Team members allow workspace owners to add users to teams within their workspace. Each team member has a status (active or pending) and is associated with an existing user in the system.

## Database Structure

### Team Members Table
- `id` - Primary key
- `team_id` - Foreign key to teams table (cascades on delete)
- `user_id` - Foreign key to users table (cascades on delete, nullable)
- `email` - String field for email-based invitations (nullable)
- `status` - Enum: 'active' or 'pending' (defaults to 'pending')
- `created_at` - Timestamp
- `updated_at` - Timestamp
- Indexes on `team_id` + `user_id` and `team_id` + `email` for performance
- Business logic prevents duplicate user_id per team and duplicate email per team

## Models

### TeamMember Model
Located at: `app/Models/TeamMember.php`

**Relationships:**
- `team()` - BelongsTo Team
- `user()` - BelongsTo User

**Helper Methods:**
- `isActive()` - Returns true if status is 'active'
- `isPending()` - Returns true if status is 'pending'
- `hasUser()` - Returns true if associated with a registered user
- `isEmailOnly()` - Returns true if email-only (invited but not registered)
- `getDisplayNameAttribute()` - Returns user email or invitation email

### Updated Models
**Team Model** - Added `teamMembers()` hasMany relationship
**User Model** - Added `teamMembers()` hasMany relationship

## API Endpoints

### Create Team Member
```
POST /api/workspaces/{workspace}/teams/{team}/members
```

**Authentication:** Required (Bearer token)

**Authorization:** Only workspace owners can create team members

**Request Body (Option 1 - User ID):**
```json
{
  "user_id": 123,
  "status": "active" // Optional, defaults to "pending"
}
```

**Request Body (Option 2 - Email):**
```json
{
  "email": "user@example.com",
  "status": "pending" // Optional, defaults to "pending"
}
```

**Response (201 Created) - Existing User:**
```json
{
  "message": "Team member added successfully",
  "data": {
    "id": 1,
    "team_id": 5,
    "user_id": 123,
    "email": null,
    "status": "active",
    "created_at": "2025-08-12T12:34:56.000000Z",
    "updated_at": "2025-08-12T12:34:56.000000Z",
    "user": {
      "id": 123,
      "email": "user@example.com"
    }
  }
}
```

**Response (201 Created) - Email Invitation:**
```json
{
  "message": "Team member added successfully",
  "data": {
    "id": 2,
    "team_id": 5,
    "user_id": null,
    "email": "invite@example.com",
    "status": "pending",
    "created_at": "2025-08-12T12:34:56.000000Z",
    "updated_at": "2025-08-12T12:34:56.000000Z"
  }
}
```

## Validation Rules

### Conditional Required Fields
- Either `user_id` OR `email` must be provided (but not both)
- `user_id` - Must be a valid integer and exist in the users table
- `email` - Must be a valid email format

### Optional Fields
- `status` - Must be either 'active' or 'pending'

### Business Rules
- Users cannot be added to the same team twice (by user_id or email)
- Email addresses cannot be invited to the same team twice
- When adding by email:
  - If user exists: converts to user_id, stores as registered member
  - If user doesn't exist: stores email as invitation
- Only workspace owners can add team members
- Team must belong to the specified workspace
- Workspace must belong to the authenticated client

## Error Responses

### 401 Unauthorized
When no authentication token is provided.

### 403 Forbidden
When the authenticated user is not the workspace owner.

### 404 Not Found
- When the workspace doesn't exist
- When the team doesn't exist
- When the team doesn't belong to the workspace
- When the workspace doesn't belong to the client

### 422 Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "user_id": ["This user is already a member of this team."],
    "email": ["Either user_id or email must be provided."]
  }
}
```

## Testing

Comprehensive test suite located at: `tests/Feature/TeamMemberTest.php`

**Test Coverage:**
- ✅ Workspace owner can create team members (by user_id)
- ✅ Workspace owner can create team members (by email for existing users)
- ✅ Workspace owner can create email-only invitations (for non-existing users)
- ✅ Status defaults to 'pending' when not provided
- ✅ User data is included in response (for registered users only)
- ✅ Prevents duplicate team members (by user_id and email)
- ✅ Prevents duplicate email invitations
- ✅ Authorization checks (workspace owner only)
- ✅ Workspace/team ownership validation
- ✅ Client context validation
- ✅ Input validation (either user_id or email required, not both)
- ✅ Email format validation
- ✅ 404 responses for non-existent resources
- ✅ Authentication requirements

**Running Tests:**
```bash
php artisan test --filter="Team Member API"
```

## Files Created/Modified

### New Files
- `database/migrations/2025_08_12_124701_create_team_members_table.php`
- `app/Models/TeamMember.php`
- `app/Http/Controllers/TeamMemberController.php`
- `app/Http/Requests/StoreTeamMemberRequest.php`
- `database/factories/TeamMemberFactory.php`
- `tests/Feature/TeamMemberTest.php`

### Modified Files
- `app/Models/Team.php` - Added teamMembers relationship
- `app/Models/User.php` - Added teamMembers relationship
- `routes/workspaces.php` - Added team member route

## Usage Example

```php
// Create a team member with existing user
$workspace = Workspace::find(1);
$team = $workspace->teams()->first();
$user = User::find(123);

$teamMember = TeamMember::create([
    'team_id' => $team->id,
    'user_id' => $user->id,
    'email' => null,
    'status' => 'active'
]);

// Create an email-only invitation
$emailInvitation = TeamMember::create([
    'team_id' => $team->id,
    'user_id' => null,
    'email' => 'invite@example.com',
    'status' => 'pending'
]);

// Check team member status and type
if ($teamMember->isActive()) {
    // Team member is active
}

if ($emailInvitation->isEmailOnly()) {
    // This is an email invitation without registered user
}

// Get display name (works for both types)
$displayName = $teamMember->display_name;

// Get all team members for a team (including email invitations)
$members = $team->teamMembers()->with('user')->get();

// Get all teams a user belongs to
$userTeams = $user->teamMembers()->with('team')->get();
```

## Security Considerations

1. **Authorization**: Only workspace owners can manage team members
2. **Client Isolation**: Team members are isolated by OAuth client context
3. **Data Validation**: All inputs are validated before processing
4. **Duplicate Prevention**: Database constraints prevent duplicate memberships
5. **Cascading Deletes**: Team members are automatically removed when teams or users are deleted

## Future Enhancements

Potential future features that could be added:
- Update team member status endpoint
- Remove team member endpoint
- List team members endpoint
- Bulk team member operations
- Team member roles/permissions
- Email invitation acceptance/signup flow
- Automatic conversion of email invitations to user accounts on signup
- Invitation expiration and resend functionality