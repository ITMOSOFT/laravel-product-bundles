# Release instructions

## Repository

- Name: `laravel-product-bundles`
- Organization: `ITMOSOFT`
- Description: Fixed-price and component-priced product bundles for Laravel applications.
- Composer package: `itmosoft/laravel-product-bundles`

## Validate locally

```bash
composer validate --strict
composer install
composer test
```

Do not publish until all local tests pass and all four GitHub Actions matrix jobs are green.

## Initialize and push

```bash
git init
git branch -M main
git add .
git commit -m "feat: add Laravel product bundles"
git remote add origin https://github.com/ITMOSOFT/laravel-product-bundles.git
git push -u origin main
```

## Tag

Only after CI passes:

```bash
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```

## GitHub Release

Title: `v1.0.0 - Initial Release`

Description:

```markdown
## Initial Release – Laravel Product Bundles

- Create fixed-price and component-priced product bundles.
- Attach Eloquent products or variants as polymorphic components.
- Store component quantities, unit-price snapshots, and display positions.
- Calculate bundle price from fixed price or component snapshots.
- Synchronize and remove components.
- Activate/deactivate and manage bundles.

### Compatibility
- PHP 8.3+
- Laravel 12 and 13

MIT License.
```

## Packagist

Submit `https://github.com/ITMOSOFT/laravel-product-bundles` at https://packagist.org/packages/submit after the GitHub repository and release are available.
