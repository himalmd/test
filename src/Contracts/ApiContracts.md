# Snaply API Contracts

This document defines the stable contract for the Snaply API endpoints. Front-end implementations and future features should rely on these specifications.

## Table of Contents

1. [Overview](#overview)
2. [Response Format](#response-format)
3. [Error Handling](#error-handling)
4. [Soft Delete Behaviour](#soft-delete-behaviour)
5. [Projects API](#projects-api)
6. [Pages API](#pages-api)
7. [Snapshots API](#snapshots-api)
8. [Comments API](#comments-api)

---

## Overview

### Base URL

All API endpoints are prefixed with `/api`:

```
https://your-domain.com/api/projects
https://your-domain.com/api/pages/1
```

### Content Type

- Request bodies must be `application/json` unless uploading files
- File uploads use `multipart/form-data`
- All responses are `application/json`

### Authentication

Authentication is not currently implemented. Future versions will add authentication headers.

---

## Response Format

### Success Response

```json
{
  "success": true,
  "data": { ... },
  "meta": { ... }  // Optional metadata
}
```

### List Response

```json
{
  "success": true,
  "data": [
    { ... },
    { ... }
  ]
}
```

### Error Response

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": {
      "field_name": "Error message for this field"
    }
  }
}
```

---

## Error Handling

### Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `VALIDATION_ERROR` | 422 | Input validation failed |
| `NOT_FOUND` | 404 | Resource not found |
| `BAD_REQUEST` | 400 | Malformed request |
| `METHOD_NOT_ALLOWED` | 405 | HTTP method not supported |
| `INTERNAL_ERROR` | 500 | Server error |

### HTTP Status Codes

| Status | Usage |
|--------|-------|
| 200 | Successful GET, PUT, PATCH |
| 201 | Successful POST (resource created) |
| 204 | Successful DELETE (no content) |
| 400 | Bad request |
| 404 | Resource not found |
| 405 | Method not allowed |
| 422 | Validation error |
| 500 | Internal server error |

---

## Soft Delete Behaviour

Snaply uses soft delete for Projects and Pages:

1. **Default behaviour**: All list and get operations exclude soft-deleted entities
2. **Cascading visibility**: When a project is soft-deleted, its pages and their snapshots/comments are hidden in standard queries
3. **Explicit inclusion**: Add `?include_deleted=true` to include soft-deleted items
4. **Restore capability**: Soft-deleted items can be restored via POST to `/{resource}/{id}/restore`

### Cascade Rules

- Soft-deleting a **Project** hides all its Pages (and their Snapshots/Comments)
- Soft-deleting a **Page** hides all its Snapshots (and their Comments)
- Restoring a Page requires its parent Project to be active
- Snapshots and Comments use hard delete

---

## Projects API

### List Projects

```
GET /api/projects
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `include_deleted` | boolean | false | Include soft-deleted projects |

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Website Redesign",
      "description": "Q4 redesign project",
      "created_at": "2024-01-15T10:30:00+00:00",
      "updated_at": "2024-01-15T10:30:00+00:00",
      "deleted_at": null
    }
  ]
}
```

---

### Get Project

```
GET /api/projects/{id}
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `include` | string | - | Comma-separated includes: `pages` |
| `include_deleted` | boolean | false | Include soft-deleted entities |

**Response (without includes):**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Website Redesign",
    "description": "Q4 redesign project",
    "created_at": "2024-01-15T10:30:00+00:00",
    "updated_at": "2024-01-15T10:30:00+00:00",
    "deleted_at": null
  }
}
```

**Response (with `?include=pages`):**

```json
{
  "success": true,
  "data": {
    "project": {
      "id": 1,
      "name": "Website Redesign",
      ...
    },
    "pages": [
      {
        "id": 1,
        "project_id": 1,
        "url": "https://example.com/home",
        "title": "Home Page",
        ...
      }
    ]
  }
}
```

---

### Create Project

```
POST /api/projects
```

**Request Body:**

```json
{
  "name": "Website Redesign",
  "description": "Q4 redesign project"
}
```

| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `name` | string | Yes | Max 255 characters |
| `description` | string | No | Max 65535 characters |

**Response:** `201 Created`

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Website Redesign",
    "description": "Q4 redesign project",
    "created_at": "2024-01-15T10:30:00+00:00",
    "updated_at": "2024-01-15T10:30:00+00:00",
    "deleted_at": null
  }
}
```

---

### Update Project

```
PUT /api/projects/{id}
```

**Request Body:**

```json
{
  "name": "Website Redesign 2024",
  "description": "Updated description"
}
```

All fields are optional. Only provided fields are updated.

**Response:** `200 OK` with updated project

---

### Delete Project (Soft Delete)

```
DELETE /api/projects/{id}
```

**Response:** `204 No Content`

---

### Restore Project

```
POST /api/projects/{id}/restore
```

**Response:** `200 OK` with restored project

---

## Pages API

### List Pages for Project

```
GET /api/projects/{projectId}/pages
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `include_deleted` | boolean | false | Include soft-deleted pages |

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "project_id": 1,
      "url": "https://example.com/home",
      "title": "Home Page",
      "created_at": "2024-01-15T10:30:00+00:00",
      "updated_at": "2024-01-15T10:30:00+00:00",
      "deleted_at": null
    }
  ]
}
```

---

### Get Page

```
GET /api/pages/{id}
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `include` | string | - | Comma-separated includes: `snapshots` |
| `include_deleted` | boolean | false | Include soft-deleted entities |

**Response (with `?include=snapshots`):**

```json
{
  "success": true,
  "data": {
    "page": {
      "id": 1,
      "project_id": 1,
      "url": "https://example.com/home",
      "title": "Home Page",
      ...
    },
    "snapshots": [
      {
        "id": 1,
        "page_id": 1,
        "media_id": 5,
        "width": 1920,
        "height": 1080,
        "captured_at": "2024-01-15T10:30:00+00:00",
        "media_url": "/storage/media/2024/01/15/abc123_screenshot.png"
      }
    ]
  }
}
```

---

### Create Page

```
POST /api/projects/{projectId}/pages
```

**Request Body:**

```json
{
  "url": "https://example.com/home",
  "title": "Home Page"
}
```

| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `url` | string | Yes | Valid URL, max 2048 characters |
| `title` | string | No | Max 500 characters |

**Response:** `201 Created` with created page

---

### Update Page

```
PUT /api/pages/{id}
```

**Request Body:**

```json
{
  "url": "https://example.com/new-home",
  "title": "Updated Home Page"
}
```

**Response:** `200 OK` with updated page

---

### Delete Page (Soft Delete)

```
DELETE /api/pages/{id}
```

**Response:** `204 No Content`

---

### Restore Page

```
POST /api/pages/{id}/restore
```

**Note:** Fails if parent project is soft-deleted.

**Response:** `200 OK` with restored page

---

## Snapshots API

### List Snapshots for Page

```
GET /api/pages/{pageId}/snapshots
```

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "page_id": 1,
      "media_id": 5,
      "width": 1920,
      "height": 1080,
      "captured_at": "2024-01-15T10:30:00+00:00",
      "created_at": "2024-01-15T10:30:00+00:00",
      "media_url": "/storage/media/2024/01/15/abc123_screenshot.png"
    }
  ]
}
```

---

### Get Snapshot

```
GET /api/snapshots/{id}
```

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `include` | string | - | Comma-separated includes: `comments` |

**Response (with `?include=comments`):**

```json
{
  "success": true,
  "data": {
    "snapshot": {
      "id": 1,
      "page_id": 1,
      "media_id": 5,
      "width": 1920,
      "height": 1080,
      "captured_at": "2024-01-15T10:30:00+00:00",
      "created_at": "2024-01-15T10:30:00+00:00",
      "media_url": "/storage/media/2024/01/15/abc123_screenshot.png"
    },
    "comments": [
      {
        "id": 1,
        "snapshot_id": 1,
        "author_name": "John Doe",
        "author_email": "john@example.com",
        "content": "This button needs attention",
        "x_norm": 0.45,
        "y_norm": 0.32,
        "created_at": "2024-01-15T11:00:00+00:00"
      }
    ]
  }
}
```

---

### Create Snapshot

Snapshots can be created using either multipart form data (file upload) or JSON with base64-encoded image.

#### Option 1: Multipart Form Data

```
POST /api/pages/{pageId}/snapshots
Content-Type: multipart/form-data
```

**Form Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `image` | file | Yes | Screenshot image (PNG, JPEG, GIF, WebP) |
| `width` | integer | Yes | Viewport width in pixels |
| `height` | integer | Yes | Viewport height in pixels |
| `captured_at` | string | No | ISO 8601 timestamp |

#### Option 2: JSON with Base64 Image

```
POST /api/pages/{pageId}/snapshots
Content-Type: application/json
```

**Request Body:**

```json
{
  "image": "data:image/png;base64,iVBORw0KGgo...",
  "filename": "screenshot.png",
  "width": 1920,
  "height": 1080,
  "captured_at": "2024-01-15T10:30:00Z"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `image` | string | Yes | Base64 image (with or without data URI prefix) |
| `filename` | string | No | Original filename (default: "screenshot.png") |
| `width` | integer | Yes | Viewport width in pixels |
| `height` | integer | Yes | Viewport height in pixels |
| `captured_at` | string | No | ISO 8601 timestamp |

**Response:** `201 Created`

```json
{
  "success": true,
  "data": {
    "id": 1,
    "page_id": 1,
    "media_id": 5,
    "width": 1920,
    "height": 1080,
    "captured_at": "2024-01-15T10:30:00+00:00",
    "created_at": "2024-01-15T10:30:00+00:00",
    "media_url": "/storage/media/2024/01/15/abc123_screenshot.png"
  }
}
```

---

### Delete Snapshot

```
DELETE /api/snapshots/{id}
```

Deletes the snapshot and its associated media file.

**Response:** `204 No Content`

---

## Comments API

### List Comments for Snapshot

```
GET /api/snapshots/{snapshotId}/comments
```

**Response:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "snapshot_id": 1,
      "author_name": "John Doe",
      "author_email": "john@example.com",
      "content": "This button needs attention",
      "x_norm": 0.45,
      "y_norm": 0.32,
      "created_at": "2024-01-15T11:00:00+00:00",
      "updated_at": "2024-01-15T11:00:00+00:00"
    }
  ]
}
```

---

### Get Comment

```
GET /api/comments/{id}
```

**Response:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "snapshot_id": 1,
    "author_name": "John Doe",
    "author_email": "john@example.com",
    "content": "This button needs attention",
    "x_norm": 0.45,
    "y_norm": 0.32,
    "created_at": "2024-01-15T11:00:00+00:00",
    "updated_at": "2024-01-15T11:00:00+00:00"
  }
}
```

---

### Create Comment

```
POST /api/snapshots/{snapshotId}/comments
```

**Request Body:**

```json
{
  "author_name": "John Doe",
  "author_email": "john@example.com",
  "content": "This button needs attention",
  "x_norm": 0.45,
  "y_norm": 0.32
}
```

| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `author_name` | string | Yes | Max 255 characters |
| `author_email` | string | No | Valid email, max 255 characters |
| `content` | string | Yes | Max 65535 characters |
| `x_norm` | float | No | Normalised X coordinate (0.0 to 1.0) |
| `y_norm` | float | No | Normalised Y coordinate (0.0 to 1.0) |

**Coordinate Normalisation:**

Coordinates are normalised between 0.0 and 1.0, representing the position relative to the snapshot dimensions:
- `x_norm = 0.0` is the left edge
- `x_norm = 1.0` is the right edge
- `y_norm = 0.0` is the top edge
- `y_norm = 1.0` is the bottom edge

To convert from pixel coordinates:
```javascript
x_norm = pixel_x / snapshot_width
y_norm = pixel_y / snapshot_height
```

To convert back to pixel coordinates:
```javascript
pixel_x = x_norm * snapshot_width
pixel_y = y_norm * snapshot_height
```

**Response:** `201 Created` with created comment

---

### Update Comment

```
PUT /api/comments/{id}
```

**Request Body:**

```json
{
  "content": "Updated comment text",
  "x_norm": 0.5,
  "y_norm": 0.5
}
```

All fields are optional. Only provided fields are updated. Set a field to `null` to clear it (for optional fields).

**Response:** `200 OK` with updated comment

---

### Delete Comment

```
DELETE /api/comments/{id}
```

**Response:** `204 No Content`

---

## Usage Examples

### Create a Project with Pages and Snapshots

```bash
# 1. Create project
curl -X POST /api/projects \
  -H "Content-Type: application/json" \
  -d '{"name": "My Website", "description": "Website review"}'

# Response: {"success": true, "data": {"id": 1, ...}}

# 2. Add a page to the project
curl -X POST /api/projects/1/pages \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com", "title": "Homepage"}'

# Response: {"success": true, "data": {"id": 1, ...}}

# 3. Create a snapshot of the page
curl -X POST /api/pages/1/snapshots \
  -H "Content-Type: application/json" \
  -d '{
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "width": 1920,
    "height": 1080
  }'

# Response: {"success": true, "data": {"id": 1, "media_url": "...", ...}}

# 4. Add a comment to the snapshot
curl -X POST /api/snapshots/1/comments \
  -H "Content-Type: application/json" \
  -d '{
    "author_name": "Jane Smith",
    "content": "The logo looks off-centre",
    "x_norm": 0.15,
    "y_norm": 0.08
  }'

# Response: {"success": true, "data": {"id": 1, ...}}
```

### Retrieve Full Project Hierarchy

```bash
# Get project with all pages
curl "/api/projects/1?include=pages"

# Get page with all snapshots
curl "/api/pages/1?include=snapshots"

# Get snapshot with all comments
curl "/api/snapshots/1?include=comments"
```

### Working with Soft-Deleted Items

```bash
# List all projects including deleted
curl "/api/projects?include_deleted=true"

# Restore a deleted project
curl -X POST /api/projects/1/restore

# Restore a deleted page (will fail if project is deleted)
curl -X POST /api/pages/1/restore
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2024-01 | Initial API release |
