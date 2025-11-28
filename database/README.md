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

## Running Migrations

### Quick Start

1. Create your database:
   ```sql
   CREATE DATABASE snaply CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Run all migrations:
   ```bash
   mysql -u <username> -p snaply < database/migrations/run_migrations.sql
   ```

### Individual Migrations

Migrations can also be run individually in dependency order:

```bash
mysql -u <username> -p snaply < database/migrations/001_create_projects_table.sql
mysql -u <username> -p snaply < database/migrations/002_create_media_table.sql
mysql -u <username> -p snaply < database/migrations/003_create_pages_table.sql
mysql -u <username> -p snaply < database/migrations/004_create_snapshots_table.sql
mysql -u <username> -p snaply < database/migrations/005_create_comments_table.sql
```

### Rollback (Development Only)

To drop all tables and start fresh:

```bash
mysql -u <username> -p snaply < database/migrations/rollback_all.sql
```

**Warning**: This permanently deletes all data. Use only in development environments.

## Migration Files

| File | Description |
|------|-------------|
| `001_create_projects_table.sql` | Projects table with status enum and soft delete |
| `002_create_media_table.sql` | Media storage abstraction table |
| `003_create_pages_table.sql` | Pages table with FK to projects |
| `004_create_snapshots_table.sql` | Snapshots with dimensions and FK to pages/media |
| `005_create_comments_table.sql` | Comments with normalised coordinates |
| `run_migrations.sql` | Master script to run all migrations |
| `rollback_all.sql` | Drop all tables (development only)
