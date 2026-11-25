# Changelog

All notable changes to `itmosoft/laravel-product-bundles` are documented here.

## [1.0.1] - 2026-11-25

### Fixed
- Reject unsaved bundle and component models.
- Validate finite, non-negative fixed and component prices.
- Validate three-letter bundle currencies.
- Reject negative component positions.
- Validate complete component replacement before deleting existing rows.
- Lock bundle row during component synchronization.
- Guard dynamic price calculation against invalid stored values and numeric overflow.
- Add regression tests for the above cases.

## [1.0.0] - 2026-11-16

### Added
- Product bundle model with fixed and dynamic pricing modes.
- Polymorphic bundle component references.
- Component quantity, unit-price snapshot, and display position.
- Manager APIs for creating, finding, adding, removing, synchronizing, pricing, activating, and deleting bundles.
- Configurable table prefix.
- PHPUnit test suite and PHP 8.3/8.4 GitHub Actions matrix.
