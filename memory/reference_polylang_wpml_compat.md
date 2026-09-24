---
name: Polylang defines ICL_LANGUAGE_CODE — detect Polylang BEFORE WPML
description: Polylang's WPML compatibility shim defines ICL_LANGUAGE_CODE, so naive WPML detection (defined ICL_LANGUAGE_CODE) misidentifies Polylang sites — always check pll_current_language first
type: reference
originSessionId: 1cdab24d-def4-4b3b-b07e-6e26ef982579
---
**Polylang ships with a "WPML compatibility" mode that defines `ICL_LANGUAGE_CODE` and a partial set of WPML's filters.** Code that detects WPML by checking `defined( 'ICL_LANGUAGE_CODE' )` will misidentify Polylang sites as WPML — and Polylang's compat shim is INCOMPLETE (e.g., it doesn't implement `wpml_element_trid` or `wpml_get_element_translations`), so any code branched off "WPML detected" returns empty.

**Correct detection order (most-specific first):**

```php
if ( function_exists( 'pll_current_language' ) ) {
    return 'polylang';  // Polylang-specific function, no WPML shim
}
if ( class_exists( 'SitePress' ) ) {
    return 'wpml';  // SitePress is the actual WPML main class
}
if ( defined( 'TRP_PLUGIN_VERSION' ) ) {
    return 'translatepress';
}
return null;
```

**Do NOT use `defined( 'ICL_LANGUAGE_CODE' )` as a WPML signal.** Polylang sets it. Use class_exists('SitePress') instead — that's WPML's actual main class, not shimmable.

**Caught via:** the bulk-link translation work on jayard35 (May 2026). Polylang correctly stored 39 translation pairs (verified via direct pll_get_post_translations call), but cc-assistant's list_posts response showed empty `translations` for every page. Root cause: engine() returned 'wpml', WPML branch called wpml_element_trid filter which Polylang doesn't fully implement, returned null, $out stayed empty. Fixed by checking pll_current_language first in both `class-multilingual.php::engine()` and `class-site-memory.php::detect_multilingual()`.

**How to apply:** Anywhere in the cc-assistant plugin (or anywhere else) that needs to distinguish WPML from Polylang, gate on Polylang-specific functions/classes first. The same applies to any third-party multilingual plugin — check the plugin's own unique signature before falling back to a constant or shared interface.
