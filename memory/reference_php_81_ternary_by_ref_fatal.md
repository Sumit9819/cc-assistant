---
name: php-81-ternary-by-ref-fatal
description: "PHP 8.1+ throws a hard fatal \"Only variables should be passed by reference\" when a ternary expression is passed to an `&$param` argument. PHP 8.0 only warned. WP 7.0 \"Armstrong\" bumped PHP min to 8.1, so legacy plugin code that compiled on 8.0 may now fatal on Armstrong."
metadata: 
  node_type: memory
  type: reference
  originSessionId: 85dedec0-0402-4aff-aa45-54eee23a20e7
---

PHP 8.1 made strict the rule that **only variables can be passed to by-reference parameters**. Ternary expressions, method-call results, and array-key indexing on ternary results are NOT variables — they're temporaries — and the runtime rejects them with `Fatal error: Only variables should be passed by reference`.

**Common forms that fatal on 8.1+:**

```php
some_fn( $cond ? $a : $b, ... );   // fn signature: &$param
some_fn( get_thing(), ... );        // fn returns by value
some_fn( $arr[$key] ?? $default, ... ); // ?? expression
```

**Forms that still work:**

```php
$tmp =& ($cond ? $a : $b);  // No — `=&` of a ternary also fatals
// instead:
if ( $cond ) { $tmp =& $a; } else { $tmp =& $b; }
some_fn( $tmp, ... );
```

**Why this bit cc-assistant v0.31:**

`apply_section_content_replace` called `CC_Assistant_Elementor_Builder::find_node( $scope ? $scope['elements'] : $tree, $widget_id )`. The find_node signature is `&find_node( array &$tree, $target_id )`. PHP 8.0 silently passed a temporary; PHP 8.1+ throws fatal. WP 7.0 "Armstrong" (released 2026-05-20) bumped PHP min to 8.1, so every `replace_section_content` apply on erofwhiterock died there — with no apply_failed status update (the fatal happens BEFORE the success/failure flip), leaving the pending stuck at "pending" status and the user staring at WordPress's critical-error page.

**Fixed in v0.31.1** by binding the search target to a real variable reference first:

```php
if ( null !== $scope && isset( $scope['elements'] ) && is_array( $scope['elements'] ) ) {
    $search_in =& $scope['elements'];
} else {
    $search_in =& $tree;
}
$wrapper_ref =& CC_Assistant_Elementor_Builder::find_node( $search_in, $widget_id );
unset( $search_in );
```

**How to apply:** Grep plugin code for `&[a-zA-Z_]*\s*\(.*?\?\s*.*?:.*?,` to find similar ternary-as-by-ref-arg patterns. Refactor to bind a variable first.

Related: [[reference-wp-7-armstrong-ai-apis]], [[reference-local-php]].
