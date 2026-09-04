# CHANGELOG

All notable changes to NextGen Image Optimizer will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-08-29

### Added
- **Scheduled Background WP-Cron Batch Optimizer:** Added headless, asynchronous media library conversion that runs quietly via WP-Cron batches without requiring an active browser tab. Implements indexed monotonic cursor seeking (`WHERE ID > last_id`), 120s mutual exclusion locks, 15s execution time budgets, and auto-recovery from server timeouts.
- **Interactive Visual Split-View Image Comparison Slider:** Added pure CSS / Vanilla JS before-and-after visual difference preview tool in Admin allowing real-time inspection of WebP and Pro AVIF quality presets on user media with 100% stats and postmeta isolation.
- **Visualizer Admin Tab:** Added dedicated Quality Visualizer tab with live byte reduction readouts.

---

## [1.1.0] - 2026-08-29

### Added
- **Optimization Savings Dashboard:** Added real-time $O(1)$ savings summary cards in WordPress Admin tracking total images converted, megabytes saved, overall compression ratio, and format breakdown (WebP vs Pro AVIF).
- **Server & AVIF Diagnostics Scanner:** Added 100% read-only local environment capability detector auditing PHP version, memory limits, execution time, GD WebP/AVIF support, and ImageMagick delegates with a copyable clean Markdown report.
- **Calibrated Quality Presets:** Added High Quality (WebP 90 / AVIF 85), Balanced (WebP 82 / AVIF 75 - Default), and Aggressive (WebP 75 / AVIF 65) compression presets calibrated to visual structural similarity.
- **Failed Image Queue & One-Click Retry:** Added durable FIFO failure tracking (Max 250 items) with automatic categorization, transient worker locking, and one-click administrator retry controls.
- **Responsive Tabbed Admin UX:** Organized settings into Overview & Savings, Settings & Bulk Tools, System Diagnostics, and Failed Queue.

### Changed
- **Negative Compression Protection:** Enhanced across all quality presets to discard derivatives larger than or equal to source images.
- **Settings Migration:** Seamless non-destructive upgrade from v1.0.0 preserving existing companion files and license state.

---

## [1.0.0] - 2026-08-29

### Added
- Initial public commercial release of NextGen Image Optimizer (Free Base) and NextGen Image Optimizer Pro (AVIF Engine).
- Local WebP and AVIF image conversion via PHP GD and ImageMagick.
- Non-destructive companion file generation preserving original JPEG/PNG images.
- HTML5 `<picture>` tag output delivery with automatic srcset rewriting.
- Ed25519 cryptographic licensing client with 7-day offline verification cache.
- Authoritative Razorpay webhook subscription and license issuance backend.
