# Database Setup Guide for Classy Cash

## Problem Summary
The Classy Cash application uses PostgreSQL with a custom schema (`classycash`) and requires:
1. Automatic schema creation when it doesn't exist
2. Sessions table creation for Symfony's PDO session handler
3. Proper schema search path configuration

## Solution Components

### 1. Schema Auto-Creation Command
Use the custom command that ensures schema exists before running Doctrine operations:

```bash
# Create schema if needed and run schema update
docker compose run --rm php bin/console app:ensure-schema
docker compose run --rm php bin/console d:s:u --force
```

### 2. Manual Schema Creation (Alternative)
If the automatic approach fails, create the schema manually:

```bash
# Create schema directly in PostgreSQL
docker compose exec db psql -U app -d app -c "CREATE SCHEMA IF NOT EXISTS classycash;"

# Then run Doctrine schema update
docker compose run --rm php bin/console d:s:u --force
```

### 3. Sessions Table Configuration
The sessions table is automatically created by Doctrine schema update with these configurations:

- **Session Handler**: `Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler`
- **Table Name**: `classycash.sessions` (configured in `services.yaml`)
- **Schema Filter**: Updated to allow all tables in the custom schema

## Configuration Details

### Doctrine Configuration (`config/packages/doctrine.yaml`)
```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        schema_filter: '~^(?!public\.)~'  # Allow all tables except public schema
        # ... other configuration
```

### Session Handler Configuration (`config/services.yaml`)
```yaml
Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler:
    arguments:
      - '%env(DATABASE_URL)%'
      - { db_table: '%env(DATABASE_SCHEMA)%.sessions' }
```

### Schema Search Path Middleware
The application uses a custom middleware to set the PostgreSQL search path:
- **File**: `src/Infrastructure/Doctrine/Middleware/SchemaSearchPathConnection.php`
- **Purpose**: Automatically sets `search_path TO classycash` for all database connections

## Troubleshooting

### Schema Does Not Exist Error
If you encounter "no schema has been selected to create in":
1. Create the schema manually: `docker compose exec db psql -U app -d app -c "CREATE SCHEMA classycash;"`
2. Run the schema update: `docker compose run --rm php bin/console d:s:u --force`

### Sessions Table Not Created
If sessions table is missing:
1. Ensure schema exists (see above)
2. Run Doctrine schema update with force: `docker compose run --rm php bin/console d:s:u --force`
3. Verify table creation: `docker compose exec db psql -U app -d app -c "\dt classycash.sessions"`

### Console Commands Not Working
If Symfony console commands fail with bootstrap errors:
1. Clear cache: `docker compose run --rm php rm -rf var/cache/*`
2. Ensure database is accessible: `docker compose exec db psql -U app -d app -c "SELECT 1;"`
3. Check environment variables in `.env` file

## Development Workflow

### Initial Setup
```bash
# 1. Use the automated command that handles everything
docker compose run --rm php bin/console app:doctrine:schema-update --force

# 2. Verify everything works
docker compose run --rm php bin/console d:s:validate
```

### Manual Setup (Alternative)
```bash
# 1. Create database schema
docker compose exec db psql -U app -d app -c "CREATE SCHEMA IF NOT EXISTS classycash;"

# 2. Run Doctrine schema update
docker compose run --rm php bin/console d:s:u --force

# 3. Create sessions table (required for Symfony session handler)
docker compose exec db psql -U app -d app -c "
CREATE TABLE IF NOT EXISTS classycash.sessions (
    sess_id VARCHAR(128) NOT NULL PRIMARY KEY,
    sess_data BYTEA NOT NULL,
    sess_time INTEGER NOT NULL,
    sess_lifetime INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS sessions_sess_lifetime_idx ON classycash.sessions (sess_lifetime);
"

# 4. Verify everything works
docker compose run --rm php bin/console d:s:validate
```

### After Entity Changes
```bash
# Update schema after entity modifications
docker compose run --rm php bin/console d:s:u --dump-sql  # Review changes
docker compose run --rm php bin/console d:s:u --force       # Apply changes
```

### Production Deployment
```bash
# Ensure schema exists before running migrations
docker compose exec db psql -U app -d app -c "CREATE SCHEMA IF NOT EXISTS classycash;"
docker compose run --rm php bin/console d:s:u --force
```

## Key Files Modified

1. **`config/packages/doctrine.yaml`** - Updated schema filter
2. **`config/services.yaml`** - Session handler configuration
3. **`src/Application/Command/EnsureSchemaCommand.php`** - Schema creation command
4. **`src/Application/Command/DoctrineSchemaUpdateCommand.php`** - Wrapper command
5. **`src/Infrastructure/Doctrine/Middleware/SchemaSearchPathConnection.php`** - Search path middleware

## Best Practices

1. **Always create schema first** before running Doctrine commands
2. **Use `--dump-sql`** to review schema changes before applying
3. **Test in development** before running schema updates in production
4. **Backup database** before major schema changes
5. **Monitor logs** for any schema-related errors during application startup

This solution ensures that the Classy Cash application can automatically handle schema creation and maintain proper database structure across different environments.
