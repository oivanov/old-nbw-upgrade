# Drupal 11 Upgrade Log

This document tracks the progress of upgrading the NBW Data App from Drupal 10 to Drupal 11.

## Project Information

- **Site**: Neighborhood Bike Works (NBW) Data App
- **Hosting**: Pantheon (using separate GitHub repo for upgrade work)
- **Local Dev**: DDEV
- **Upgrade Repository**: git@github.com:oivanov/old-nbw-upgrade.git

---

## Session 1: 2025-12-31

### Initial State

| Component | Version |
|-----------|---------|
| Drupal Core | 10.4.3 |
| Webform | 6.2.9 |
| PHP | 8.2.0 |

### Problem Analysis

The main blocker for upgrading to Drupal 11 was the **Group module** (`drupal/group`), which depends on **variationcache** - a module that is not compatible with Drupal 11.

The user confirmed they do not need the Group module, but previous attempts to uninstall it broke the Webform module.

### Step 1: Create Backup and Setup Git Repository

1. Created new GitHub repository for upgrade work: `git@github.com:oivanov/old-nbw-upgrade.git`
2. Replaced Pantheon remote with GitHub remote
3. Added database dump patterns to `.gitignore`:
   ```
   *.sql
   *.sql.gz
   ```
4. Created database backup: `backup-before-d11-upgrade.sql.gz`
5. Committed initial state

**Commit**: `5698d8701` - "saving changes before upgrading to Drupal 11"

### Step 2: Fix Broken Site - Group Entity Type Error

After initial cleanup attempts, the site broke with the error:

```
The 'group' entity type does not exist.
Drupal\Core\Entity\Exception\InvalidPluginDefinitionException
```

**Root Cause**: The `webform_views` module was trying to reference the Group entity type that wasn't properly installed. There was a config/database mismatch where Group was in the config file but not fully installed in the database.

**Solution**: Created PHP script to clean up the database:

```php
// fix_modules.php - removed from database:
// - group
// - variationcache
// - webform_views
```

The script:
1. Removed modules from `core.extension` config in database
2. Removed entries from `key_value` table (system.schema collection)

**Commit**: `ec2bc32a5` - "Remove Group, variationcache, and webform_views modules"

### Step 3: Upgrade Drupal Core

Attempted to upgrade Webform first, but Composer blocked due to security advisories on Drupal Core 10.4.3.

**Solution**: Upgraded Drupal Core first:

```bash
ddev composer require drupal/core-recommended:^10.6 drupal/core-composer-scaffold:^10.6 drupal/core-project-message:^10.6 --with-all-dependencies
```

| Component | From | To |
|-----------|------|-----|
| Drupal Core | 10.4.3 | 10.6.1 |

### Step 4: Upgrade Webform Module

```bash
ddev composer require 'drupal/webform:^6.3@beta'
```

| Component | From | To |
|-----------|------|-----|
| Webform | 6.2.9 | 6.3.0-beta6 |

**Issue**: Database updates failed due to deprecated webform submodules.

**Deprecated modules removed**:
- `webform_location_places` (removed in Webform 6.3)
- `webform_bootstrap` (removed in Webform 6.3)

Created PHP script to remove these from database before running updates.

### Step 5: Remove Rules Module

After upgrading to Drupal 10.6, the Rules module threw an error:

```
Class 'Doctrine\Common\Annotations\AnnotationRegistry' not found
```

**Root Cause**: Drupal 10.6 removed Doctrine Annotations support. Rules 4.0.0 still depends on annotations.

**Solution**:
1. Disabled Rules module via database
2. Removed `drupal/rules` from `composer.json`
3. Removed `rules` and `typed_data` from `config/core.extension.yml`
4. Ran `ddev composer update` to remove packages

### Step 6: Final Cleanup and Testing

1. Created PHP script to remove `typed_data` from database
2. Ran database updates:
   ```bash
   ddev drush updb -y
   ```
3. Cleared cache and exported configuration:
   ```bash
   ddev drush cr && ddev drush cex -y
   ```
4. Tested site functionality:
   - Homepage loads correctly
   - Webforms render and function properly
   - Admin toolbar works

### Step 7: Commit and Push

**Commit**: `e2b1267be` - "Upgrade Drupal 10.4 to 10.6.1 and Webform to 6.3.0-beta6 for D11 preparation"

```bash
git push origin main
```

---

## Current State

| Component | Version | D11 Compatible |
|-----------|---------|----------------|
| Drupal Core | 10.6.1 | Ready to upgrade |
| Webform | 6.3.0-beta6 | Yes |
| PHP | 8.2.0 | Yes |

### Modules Removed

| Module | Reason |
|--------|--------|
| `drupal/group` | Depends on variationcache (not D11 compatible) |
| `drupal/variationcache` | Not D11 compatible |
| `drupal/webform_views` | Depended on Group module |
| `drupal/rules` | Incompatible with Drupal 10.6 (Doctrine annotations removed) |
| `drupal/typed_data` | Dependency of Rules |
| `webform_bootstrap` | Deprecated in Webform 6.3 |
| `webform_location_places` | Deprecated in Webform 6.3 |

### Git History

```
e2b1267be Upgrade Drupal 10.4 to 10.6.1 and Webform to 6.3.0-beta6 for D11 preparation
ec2bc32a5 Remove Group, variationcache, and webform_views modules
fb62f4a20 Add database dump patterns to .gitignore
5698d8701 saving changes before upgrading to Drupal 11
```

---

## Next Steps

### To Complete D11 Upgrade

1. **Check Upgrade Status Report**
   ```
   https://old-nbw.ddev.site/admin/reports/upgrade-status
   ```

2. **Review Remaining Incompatible Modules**
   - Check each module for D11 compatibility
   - Update or remove as needed

3. **Upgrade to Drupal 11**
   ```bash
   ddev composer require drupal/core-recommended:^11 drupal/core-composer-scaffold:^11 drupal/core-project-message:^11 --with-all-dependencies
   ddev drush updb -y
   ddev drush cr
   ```

4. **Test All Functionality**
   - Webforms
   - User registration
   - Content editing
   - Views
   - Custom modules (nbw_data_app, nbw_tools, nbw_users_registration)

5. **Update Custom Themes**
   - Check `nbw_uswds` theme for D11 compatibility
   - Update any deprecated Twig functions

---

## Troubleshooting Reference

### Common Issues and Solutions

#### "Entity type does not exist" Error
If a module is partially uninstalled and leaves orphaned config:
```php
// Create a PHP script to remove from database:
$config = $container->get('config.factory')->getEditable('core.extension');
$modules = $config->get('module');
unset($modules['module_name']);
$config->set('module', $modules);
$config->save();

// Also remove from key_value:
$database->delete('key_value')
  ->condition('collection', 'system.schema')
  ->condition('name', 'module_name')
  ->execute();
```

#### Doctrine Annotations Error (Drupal 10.6+)
Modules using `@Annotation` syntax are incompatible with Drupal 10.6+. Either:
- Wait for module update with PHP 8 Attributes
- Disable/remove the module

#### Webform Deprecated Submodules
Webform 6.3 removed several submodules. Remove from database before upgrading:
- `webform_bootstrap`
- `webform_location_places`

---

## Backup Files

| File | Description | Location |
|------|-------------|----------|
| `backup-before-d11-upgrade.sql.gz` | DB before any changes | Project root (gitignored) |

---

*Last updated: 2025-12-31*
