# Snaply Database

This directory contains database schema documentation and migration scripts for the Snaply application.

## Directory Structure

```
database/
├── README.md                 # This file
├── schema/
│   └── snaply_schema.md      # Complete schema design documentation
└── migrations/
    └── (SQL migration files)
```

## Schema Overview

Snaply uses MySQL with the following core tables:

| Table | Purpose |
|-------|---------|
| `projects` | Top-level containers for organizing page captures |
| `pages` | Web pages being tracked within projects |
| `media` | Storage abstraction for uploaded files |
| `snapshots` | Point-in-time captures with dimensions |
| `comments` | User annotations with normalised coordinates |

## Key Features

- **Soft Deletes**: Projects, pages, and snapshots use `deleted_at` timestamps
- **Normalised Coordinates**: Comments store x/y positions as values between 0-1
- **Media Abstraction**: Storage backend can be switched without schema changes
- **Threaded Comments**: Self-referencing parent_id enables reply threads

## Documentation

See [schema/snaply_schema.md](schema/snaply_schema.md) for complete documentation including:

- Table definitions and column specifications
- Index strategy and foreign key relationships
- Coordinate normalisation formulas
- Soft delete query patterns
- Database vs application-level integrity rules

## Requirements

- MySQL 5.7+ or MySQL 8.0+
- InnoDB storage engine
- Character set: utf8mb4
- Collation: utf8mb4_unicode_ci
