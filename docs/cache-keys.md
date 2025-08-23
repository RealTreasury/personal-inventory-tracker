# Cache Keys

This document lists transient cache keys used by Personal Inventory Tracker and their purposes. Follow these patterns when adding new caches to ensure consistent invalidation.

Unless otherwise noted, caches created with `PIT_Cache::get_or_set()` expire after **1 hour**. Keys should use the `pit_` prefix followed by the feature name and description.

## Summary reports
- **Key:** `pit_reco_summary`
- **Purpose:** Stores dashboard summary totals and recent purchase data.
- **Expiration:** 1 hour.
- **Invalidation:** Cleared automatically whenever a `pit_item` post is created, updated, or deleted.

## Naming pattern
Use the prefix `pit_` followed by the feature or context. Example: `pit_{feature}_{description}`. Keeping keys predictable simplifies clearing them when data changes.
