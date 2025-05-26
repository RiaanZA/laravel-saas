# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-01-27

### Added
- Laravel 12 support

### Changed
- **BREAKING**: Updated minimum Laravel version requirement from 11.0 to 12.0
- **BREAKING**: Updated minimum PHP version requirement from 8.1 to 8.2
- Updated `laravel/framework` dependency to `^12.0`
- Updated `phpunit/phpunit` dependency to `^11.0`
- Updated `orchestra/testbench` dependency to `^10.0`
- Updated frontend dependencies:
  - `vue` from `^3.3.0` to `^3.4.0`
  - `@vitejs/plugin-vue` from `^4.0.0` to `^5.0.0`
  - `laravel-vite-plugin` from `^0.8.0` to `^1.0.0`
  - `tailwindcss` from `^3.3.0` to `^3.4.0`
  - `vite` from `^4.0.0` to `^5.0.0`
- Updated PHPUnit configuration schema to version 11.0
- Updated package version to 2.0.0 to reflect breaking changes

### Removed
- Support for Laravel 11.x and below
- Support for PHP 8.1

### Migration Guide

To upgrade from v1.x to v2.x:

1. **Update your Laravel application to version 12.0 or higher**
2. **Update your PHP version to 8.2 or higher**
3. Update the package via Composer:
   ```bash
   composer update riaan-za/laravel-subscription-management
   ```
4. Update your frontend dependencies:
   ```bash
   npm update
   ```
5. Run any pending migrations:
   ```bash
   php artisan migrate
   ```

### Notes

- This release maintains full backward compatibility with existing subscription data and functionality
- No database schema changes are required
- All existing features continue to work as expected
- Carbon 3 is now supported (automatically included with Laravel 12)

## [1.0.0] - 2024-01-01

### Added
- Initial release with Laravel 11 support
- Complete subscription management system
- Vue 3 + Inertia.js frontend
- Peach Payments integration
- Usage tracking and limits
- Feature-based access control
- Comprehensive testing suite
