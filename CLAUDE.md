# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Drupal 10 site for Neighborhood Bike Works (NBW), hosted on Pantheon. The project uses Composer for dependency management and DDEV for local development.

## Common Commands

### Local Development (DDEV)
```bash
ddev start              # Start the local environment
ddev stop               # Stop the environment
ddev ssh                # SSH into the web container
ddev drush <command>    # Run Drush commands
ddev composer <command> # Run Composer commands
```

### Drush Commands
```bash
ddev drush cr           # Clear cache
ddev drush cim          # Import configuration
ddev drush cex          # Export configuration
ddev drush updb         # Run database updates
ddev drush uli          # Generate one-time login link
```

### Theme Development (USWDS)
The theme uses USWDS (U.S. Web Design System) with Gulp for compilation.
```bash
cd web/themes/custom/nbw_uswds
npm install
npx gulp compile        # Compile SASS to CSS
npx gulp watch          # Watch for changes
```

## Architecture

### Directory Structure
- `web/` - Drupal docroot
- `config/` - Configuration sync directory (exported via `drush cex`)
- `vendor/` - Composer dependencies (gitignored)
- `upstream-configuration/` - Pantheon upstream config

### Custom Modules (`web/modules/custom/nbw/`)
- **nbw_data_app** - Core functionality for day-to-day operations
- **nbw_tools** - Utility tools including check-in/out functionality
- **nbw_users_registration** - Webform handler for Youth Waiver form that creates Youth and Guardian users

### Custom Themes (`web/themes/custom/`)
- **nbw_uswds** - Main theme based on `uswds_base`, uses USWDS v3
- **nbw_custom_theme** - Legacy/alternate theme

### Key Contrib Modules
- `webform` - Form building with handlers for user registration
- `group` - Group/membership management
- `smart_date_calendar_kit` - Calendar functionality
- `entity_print` - PDF generation with dompdf
- `mailchimp` - Newsletter integration

## Configuration Management

Configuration is stored in `config/` and synced to `web/sites/default/files/sync/`. Use:
```bash
ddev drush cim          # Import config from config/ to database
ddev drush cex          # Export config from database to config/
```

## Hosting

Hosted on Pantheon with Integrated Composer. Pushes to the repository trigger automatic builds. Patches are managed via `composer.patches.json`.
