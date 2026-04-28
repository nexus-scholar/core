# Nexus Scholarly Core

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nexus-scholar/core.svg?style=flat-square)](https://packagist.org/packages/nexus-scholar/core)
[![Tests](https://github.com/nexus-scholar/core/actions/workflows/test.yml/badge.svg)](https://github.com/nexus-scholar/core/actions/workflows/test.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/nexus-scholar/core.svg?style=flat-square)](https://packagist.org/packages/nexus-scholar/core)
[![License](https://img.shields.io/packagist/l/nexus-scholar/core.svg?style=flat-square)](https://packagist.org/packages/nexus-scholar/core)

A Systematic Literature Review (SLR) toolkit for PHP 8.3+. Nexus Scholarly provides a robust, hexagonal-architecture-driven framework to search, deduplicate, and analyze scholarly literature from multiple providers.

## Features
- **Multi-Provider Search:** Concurrently search arXiv, Crossref, DOAJ, IEEE, OpenAlex, PubMed, and Semantic Scholar.
- **Advanced Deduplication:** Rule-based and fuzzy-matching strategies to detect overlapping works.
- **Citation Networks:** Build and persist citation graphs (citation, co-citation, and bibliographic coupling).
- **Framework Agnostic Domain:** Core logic operates independently, with an included Laravel integration layer.

## Installation

You can install the package via composer:

```bash
composer require nexus-scholar/core
```

For Laravel usage, publish the configuration file:

```bash
php artisan vendor:publish --tag="nexus-config"
```

## Basic Usage

Using the included Artisan command to run a batch search:

```bash
php artisan nexus:search "Segment Anything AND tomato" --from-year=2024 --max=50
```

Alternatively, use a YAML file for batch processing:

```bash
php artisan nexus:search --file=queries.yml
```

## Documentation

For full architecture notes and domain rules, please refer to the `docs/` directory.
- [Product Vision](docs/00-product-vision.md)
- [Architecture Rules](docs/03-architecture-rules.md)
- [Old Readme](docs/README-OLD.md)

## Testing

To run the robust Pest test suite included with the core, make sure you bump the memory limit since the integration test's VCR YAML recordings can exceed defaults.

```bash
php -d memory_limit=512M vendor/bin/pest
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
