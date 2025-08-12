# Workspace API Documentation

This document describes the API endpoints for managing workspaces in the KibaAuth application.

## Authentication

All workspace endpoints require authentication using Laravel Passport. Include the Bearer token in the Authorization header:

```
Authorization: Bearer {your-access-token}
```

**Important**: Workspaces are scoped to both the authenticated user AND the OAuth client. Each client can only access workspaces that belong to that specific client.

## Base URL

All workspace endpoints are prefixed with `/api/workspaces`

## Endpoints

### 1. List Workspaces

**GET** `/api/workspaces`

Returns a list of workspaces belonging to the authenticated user for the current OAuth client.

#### Response

```json
{
  "data": [
    {
      "id": 1,
      "name": "Engineering Team",
      "slug": "engineering-team",
      "user_id": 1,
      "client_id": "01989dc5-3c6c-7107-9367-7884688d5724",
      "created_at": "2025-08-12T09:10:35.000000Z",
      "updated_at": "2025-08-12T09:10:35.000000Z"
    }
  ]
}
```

### 2. Create Workspace

**POST** `/api/workspaces`

Creates a new workspace for the authenticated user within the current OAuth client's scope.

#### Request Body

```json
{
  "name": "Engineering Team",
  "slug": "engineering-team" // Optional
}
```

#### Parameters

- `name` (required, string, max:255): The name of the workspace
- `slug` (optional, string, max:255, alpha_dash): The URL-friendly slug. If not provided, it will be generated from the name

**Note**: The workspace will automatically be associated with the OAuth client from the authentication token.

#### Response

```json
{
  "data": {
    "id": 1,
    "name": "Engineering Team",
    "slug": "engineering-team",
    "user_id": 1,
    "client_id": "01989dc5-3c6c-7107-9367-7884688d5724",
    "created_at": "2025-08-12T09:10:35.000000Z",
    "updated_at": "2025-08-12T09:10:35.000000Z"
  },
  "message": "Workspace created successfully"
}
```

#### Unique Slug Handling

Slugs are unique per OAuth client. If the provided slug (or generated slug from name) already exists for the current client, the system will automatically append a number to make it unique:

- First workspace with slug "eng" for Client A → "eng"
- Second workspace with slug "eng" for Client A → "eng-2"
- First workspace with slug "eng" for Client B → "eng" (allowed, different client)

### 3. Show Workspace

**GET** `/api/workspaces/{id}`

Returns a specific workspace belonging to the authenticated user and current OAuth client.

#### Response

```json
{
  "data": {
    "id": 1,
    "name": "Engineering Team",
    "slug": "engineering-team",
    "user_id": 1,
    "client_id": "01989dc5-3c6c-7107-9367-7884688d5724",
    "created_at": "2025-08-12T09:10:35.000000Z",
    "updated_at": "2025-08-12T09:10:35.000000Z"
  }
}
```

#### Error Response (Not Found)

```json
{
  "message": "Workspace not found"
}
```

### 4. Update Workspace

**PUT/PATCH** `/api/workspaces/{id}`

Updates a workspace belonging to the authenticated user and current OAuth client.

#### Request Body

```json
{
  "name": "Updated Team Name",
  "slug": "updated-slug" // Optional
}
```

#### Parameters

- `name` (optional, string, max:255): The new name of the workspace
- `slug` (optional, string, max:255, alpha_dash): The new slug for the workspace

#### Response

```json
{
  "data": {
    "id": 1,
    "name": "Updated Team Name",
    "slug": "updated-slug",
    "user_id": 1,
    "client_id": "01989dc5-3c6c-7107-9367-7884688d5724",
    "created_at": "2025-08-12T09:10:35.000000Z",
    "updated_at": "2025-08-12T09:15:22.000000Z"
  },
  "message": "Workspace updated successfully"
}
```

### 5. Delete Workspace

**DELETE** `/api/workspaces/{id}`

Deletes a workspace belonging to the authenticated user and current OAuth client.

#### Response

```json
{
  "message": "Workspace deleted successfully"
}
```

## Error Responses

### Authentication Errors

**401 Unauthorized**
```json
{
  "message": "Unauthenticated"
}
```

### Validation Errors

**422 Unprocessable Entity**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The workspace name is required."],
    "slug": ["The workspace slug may only contain letters, numbers, dashes, and underscores."]
  }
}
```

### Authorization Errors

**403 Forbidden**
```json
{
  "message": "This action is unauthorized."
}
```

### Not Found Errors

**404 Not Found**
```json
{
  "message": "No query results for model [App\\Models\\Workspace] {id}"
}
```

## Usage Examples

### Creating a Workspace

```bash
curl -X POST \
  http://your-domain.com/api/workspaces \
  -H 'Authorization: Bearer your-access-token' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "name": "Marketing Team",
    "slug": "marketing"
  }'
```

### Listing Workspaces

```bash
curl -X GET \
  http://your-domain.com/api/workspaces \
  -H 'Authorization: Bearer your-access-token' \
  -H 'Accept: application/json'
```

### Updating a Workspace

```bash
curl -X PUT \
  http://your-domain.com/api/workspaces/1 \
  -H 'Authorization: Bearer your-access-token' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "name": "Updated Marketing Team"
  }'
```

## Features

- **Automatic Slug Generation**: If no slug is provided, one is generated from the workspace name
- **Client-Scoped Slug Uniqueness**: Slugs are unique per OAuth client, allowing the same slug across different clients
- **User and Client Isolation**: Users can only access their own workspaces within the current OAuth client's scope
- **Full CRUD Operations**: Create, Read, Update, and Delete operations are all supported
- **Passport Security**: All endpoints are protected by Laravel Passport authentication
- **Multi-Client Support**: Each OAuth client maintains its own workspace namespace

## Database Schema

The workspaces table has the following structure:

```sql
CREATE TABLE workspaces (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  client_id CHAR(36) NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (client_id) REFERENCES oauth_clients(id) ON DELETE CASCADE,
  UNIQUE KEY unique_slug_per_client (slug, client_id),
  INDEX idx_user_client (user_id, client_id)
);
```
