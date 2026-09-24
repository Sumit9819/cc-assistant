<?php
/** Shared, versioned agent contract. Preserve empty schema objects. */
$cc_data = json_decode( <<<'CC_SHARED_JSON'
[
  {
    "name": "whoami",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "render_probe",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        },
        "extract": {
          "type": "string",
          "description": "Optional comma list: schema,links,images,headings. Default all four."
        },
        "diff": {
          "type": "boolean",
          "description": "Return ONLY the delta vs the previous probe of this post (kept 24h) — use for the AFTER probe of a verify cycle; unchanged facets collapse to counts."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "get_elementor_tree",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "schema_scan",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_ids": {
          "type": "string",
          "description": "Optional comma list of post IDs. Omit to scan a representative sample (front page + most-recently-modified pages)."
        },
        "limit": {
          "type": "integer",
          "description": "Max pages to probe. Default 12, cap 40."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "redirect_audit",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "managed_schema",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Optional. Return the full raw JSON-LD for this one post; omit to list all posts with managed schema."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "draft_rebuild_section",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post ID."
        },
        "remove_id": {
          "type": "string",
          "description": "Element id of the existing section to replace."
        },
        "settings": {
          "type": "object",
          "description": "Settings for the new root container (boxed width, bg, padding, etc.)."
        },
        "children": {
          "type": "array",
          "description": "Pre-built child specs (widgets/containers), same shape as draft_add_elementor_container."
        },
        "position": {
          "type": "integer",
          "description": "Optional insert position for the new section."
        },
        "parent_id": {
          "type": "string",
          "description": "Optional parent container id; empty = page root."
        },
        "el_type": {
          "type": "string",
          "description": "Optional: container (default), section, or column."
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        }
      },
      "required": [
        "post_id",
        "remove_id",
        "settings"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_add_section",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post ID."
        },
        "recipe": {
          "type": "string",
          "description": "hero | card_grid | checklist_2col | cta_banner | section_header."
        },
        "data": {
          "type": "object",
          "description": "Recipe content — see the tool description for each recipe's fields."
        },
        "position": {
          "type": "integer",
          "description": "Optional insert position (root index)."
        },
        "remove_id": {
          "type": "string",
          "description": "Optional: atomically REPLACE this existing section instead of just adding."
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        }
      },
      "required": [
        "post_id",
        "recipe",
        "data"
      ]
    },
    "description": ""
  },
  {
    "name": "seo_playbook",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "health",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "list_posts",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_type": {
          "type": "string",
          "description": "Post type slug: \"post\", \"page\", or \"any\". Default: searches both posts and pages. Only types in the allowlist are accepted."
        },
        "status": {
          "type": "string",
          "description": "Post status: publish, draft, pending, private, future, any. Default \"any\"."
        },
        "per_page": {
          "type": "integer",
          "description": "Items per page. Default 20."
        },
        "page": {
          "type": "integer",
          "description": "Page number, 1-indexed. Default 1."
        },
        "search": {
          "type": "string",
          "description": "Optional keyword search."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "get_post",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        },
        "slim": {
          "type": "boolean",
          "description": "Skip body content and parsed Elementor tree. Default false."
        },
        "widget_id": {
          "type": "string",
          "description": "Optional Elementor widget id (8-char hex). When set, returns only that single widget instead of the full post."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "get_elementor_widgets",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        },
        "format": {
          "type": "string",
          "description": "\"summary\" (default, terse) or \"full\" (every widget with details)."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "get_page_map",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        },
        "include_ascii": {
          "type": "boolean",
          "description": "Include a plain-text ASCII tree of the map. Default true."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "entity_lookup",
    "inputSchema": {
      "type": "object",
      "properties": {
        "q": {
          "type": "string",
          "description": "Name or slug to search (matches post_title LIKE and post_name/slug)."
        }
      },
      "required": [
        "q"
      ]
    },
    "description": ""
  },
  {
    "name": "layout_spec",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post/template ID."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "layout_compare",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Target post/template ID (your build)."
        },
        "reference_id": {
          "type": "integer",
          "description": "Reference post/template ID to match against."
        }
      },
      "required": [
        "id",
        "reference_id"
      ]
    },
    "description": ""
  },
  {
    "name": "find_topic_clusters",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_type": {
          "type": "string",
          "description": "Post type to scan. Default \"page\"."
        },
        "threshold": {
          "type": "number",
          "description": "Similarity threshold 0..1. Default 0.7."
        },
        "limit": {
          "type": "integer",
          "description": "Max posts to scan. Default 200, cap 1000."
        },
        "format": {
          "type": "string",
          "description": "\"summary\" (default) or \"full\" (includes per-pair similarity breakdown)."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "analyze_topic_cluster",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_ids": {
          "type": "array",
          "description": "Post IDs in the cluster.",
          "items": {
            "type": "integer"
          }
        },
        "excerpt_chars": {
          "type": "integer",
          "description": "Body excerpt length per page. Default 500. Raise to 2000 for deeper read."
        }
      },
      "required": [
        "post_ids"
      ]
    },
    "description": ""
  },
  {
    "name": "list_pending_changes",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "draft_update_post_meta",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post ID."
        },
        "field": {
          "type": "string",
          "description": "One of: post_title, post_excerpt, post_name, post_status, post_author. For post_author, value = user ID, login, email, or exact display name (resolved to an ID at queue time)."
        },
        "value": {
          "type": "string",
          "description": "New value for the field."
        },
        "summary": {
          "type": "string",
          "description": "One-line description for the inbox."
        },
        "reasoning": {
          "type": "string",
          "description": "Why this change makes sense. Shown to the reviewer."
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, return the validation result without queueing a pending row. No DB write."
        },
        "success_metrics": {
          "type": "object",
          "description": "Stated intent for outcome scoring. Most useful on post_title changes where CTR delta is measurable.",
          "properties": {
            "target_query": {
              "type": "string"
            },
            "target_position": {
              "type": "number"
            },
            "target_ctr": {
              "type": "number"
            },
            "eval_window_days": {
              "type": "integer"
            },
            "hypothesis": {
              "type": "string"
            }
          }
        }
      },
      "required": [
        "post_id",
        "field",
        "value"
      ]
    },
    "description": " post_status accepts only draft, pending or private. Publishing, scheduling and trashing cannot use this generic field route; use the dedicated reviewed workflows."
  },
  {
    "name": "draft_create_redirect",
    "inputSchema": {
      "type": "object",
      "properties": {
        "source": {
          "type": "string",
          "description": "Old URL or path on this site (e.g. \"/old-slug/\" or \"https://eroflufkin.com/old-slug/\")."
        },
        "destination": {
          "type": "string",
          "description": "Full destination URL (e.g. \"https://eroflufkin.com/new-slug/\")."
        },
        "http_code": {
          "type": "integer",
          "description": "One of: 301 (default permanent), 302 (temporary), 307, 410 (gone, no destination needed), 451."
        },
        "summary": {
          "type": "string",
          "description": "One-line description for the inbox."
        },
        "reasoning": {
          "type": "string",
          "description": "Why this redirect exists. Shown to reviewer."
        },
        "_diag": {
          "type": "boolean",
          "description": "Diagnostic mode. When true, returns the raw Rank Math storage investigation (table existence, sample rows, LIKE-match results, marker hits) WITHOUT queueing a pending. Used to debug dedup mismatches across Rank Math storage formats."
        }
      },
      "required": [
        "source"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_delete_redirect",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Numeric Rank Math redirect id to delete."
        },
        "reasoning": {
          "type": "string",
          "description": "Why this redirect is being removed. Shown to reviewer."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_untrash_redirect",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Numeric Rank Math redirect id to restore to active."
        },
        "reasoning": {
          "type": "string",
          "description": "Why this redirect is being restored. Shown to reviewer."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "get_working_state",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": "Read the saved job record. Treat it as historical context and resume only when it matches the current user request. Preserve explicit constraints; an active status does not authorize switching tasks, publication, or applying stale SEO recipes."
  },
  {
    "name": "update_working_state",
    "inputSchema": {
      "type": "object",
      "properties": {
        "active_task": {
          "type": "string",
          "description": "One line: the job in progress."
        },
        "status": {
          "type": "string",
          "description": "active | paused | done"
        },
        "current_plan": {
          "type": "string",
          "description": "The very next action a resuming chat should take."
        },
        "target_post_ids": {
          "type": "array",
          "items": {
            "type": "integer"
          }
        },
        "steps": {
          "type": "array",
          "items": {
            "type": "string"
          },
          "description": "Replace the checklist. Prefix done items with [x]."
        },
        "add_steps": {
          "type": "array",
          "items": {
            "type": "string"
          }
        },
        "decisions": {
          "type": "array",
          "items": {
            "type": "string"
          }
        },
        "add_decisions": {
          "type": "array",
          "items": {
            "type": "string"
          }
        },
        "constraints": {
          "type": "array",
          "items": {
            "type": "string"
          }
        },
        "add_constraints": {
          "type": "array",
          "items": {
            "type": "string"
          }
        },
        "open_loops": {
          "type": "array",
          "items": {
            "type": "string"
          }
        },
        "add_open_loops": {
          "type": "array",
          "items": {
            "type": "string"
          }
        },
        "remove_open_loops": {
          "type": "array",
          "items": {
            "type": "string"
          }
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "gbp_locations",
    "inputSchema": {
      "type": "object",
      "properties": {
        "refresh": {
          "type": "boolean",
          "description": "Re-fetch from Google instead of the cached list. Default false."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "gbp_performance",
    "inputSchema": {
      "type": "object",
      "properties": {
        "location": {
          "type": "string",
          "description": "Location resource name, e.g. \"locations/123\" (from gbp_locations)."
        },
        "days": {
          "type": "integer",
          "description": "Trailing window in days (1-537). Default 30."
        }
      },
      "required": [
        "location"
      ]
    },
    "description": ""
  },
  {
    "name": "page_quality_gate",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post id to evaluate."
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "win_audit",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "The page to audit."
        },
        "query": {
          "type": "string",
          "description": "Target search query this page should win. Recommended."
        },
        "competitor_urls": {
          "type": "array",
          "items": {
            "type": "string"
          },
          "description": "2-5 top ranking/cited competitor URLs for the query (from your own web search). Omit for an absolute-only audit."
        },
        "days": {
          "type": "integer",
          "description": "GSC window for query grounding. Default 28 (7-90)."
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "page_robustness_audit",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "The page/post to audit."
        },
        "strict": {
          "type": "boolean",
          "description": "Deprecated compatibility argument. Findings now come from verified_page_audit; editorial heuristics are not blocking rules."
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "build_page_from_spec",
    "inputSchema": {
      "type": "object",
      "properties": {
        "title": {
          "type": "string",
          "description": "Post title (also the H1 context). Required."
        },
        "slug": {
          "type": "string",
          "description": "URL slug. Optional. Ignored with target_post_id."
        },
        "post_type": {
          "type": "string",
          "description": "page (default) or post. Ignored with target_post_id."
        },
        "style_mirror_post_id": {
          "type": "integer",
          "description": "Required. A well-built page on this site to sample heading/text/card styles from."
        },
        "target_post_id": {
          "type": "integer",
          "description": "Optional. Rebuild-in-place: instead of creating a new draft, queue ONE pending change that replaces this existing post's Elementor tree (elementor_full_import path: fresh element ids, pre-apply snapshot). Response includes target_post_id + pending_id + structure."
        },
        "seo": {
          "type": "object",
          "description": "{title, description, focus_keyword} — written via the active SEO plugin (queued as pending changes when target_post_id is used)."
        },
        "sections": {
          "type": "array",
          "description": "Ordered section specs. Each: {template: hero|text_image|card_grid|steps|faq|cta_band|form|map, ...fields per template}. Exactly one hero."
        },
        "dry_run": {
          "type": "boolean",
          "description": "Validate + return structure without creating anything. Default false. Recommended first call."
        },
        "override_lint": {
          "type": "boolean",
          "description": "Bypass content lint hard-violations. Default false."
        },
        "override_images": {
          "type": "boolean",
          "description": "Allow a deliberately image-free page. Default false."
        }
      },
      "required": [
        "title",
        "style_mirror_post_id",
        "sections"
      ]
    },
    "description": ""
  },
  {
    "name": "find_duplicate_content",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_ids": {
          "type": "array",
          "items": {
            "type": "integer"
          },
          "description": "Array of post IDs to compare. Typically gathered from list_posts(search=\"topic\"). Min 2."
        },
        "threshold": {
          "type": "number",
          "description": "Cosine similarity threshold 0.3-0.99. Default 0.7 (catches near-duplicates). 0.85+ catches only obvious duplicates."
        }
      },
      "required": [
        "post_ids"
      ]
    },
    "description": ""
  },
  {
    "name": "gsc_inspect_url",
    "inputSchema": {
      "type": "object",
      "properties": {
        "url": {
          "type": "string",
          "description": "Full URL to inspect (e.g. \"https://eroflufkin.com/some-page/\")."
        },
        "post_id": {
          "type": "integer",
          "description": "WordPress post ID. Resolves to the post permalink. Use either url or post_id, not both."
        },
        "force_refresh": {
          "type": "boolean",
          "description": "Bypass the 1h transient cache. Default false."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "propose_emergency_service_schema",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post or page ID this schema attaches to."
        },
        "name": {
          "type": "string",
          "description": "Business name. Defaults to post title if omitted."
        },
        "url": {
          "type": "string",
          "description": "Canonical URL. Defaults to permalink if omitted."
        },
        "telephone": {
          "type": "string",
          "description": "E.164 or formatted phone (e.g. \"+1-936-427-1313\")."
        },
        "address": {
          "type": "object",
          "description": "PostalAddress fields. @type is auto-prepended.",
          "properties": {
            "streetAddress": {
              "type": "string"
            },
            "addressLocality": {
              "type": "string"
            },
            "addressRegion": {
              "type": "string"
            },
            "postalCode": {
              "type": "string"
            },
            "addressCountry": {
              "type": "string"
            }
          }
        },
        "geo": {
          "type": "object",
          "description": "GeoCoordinates. Latitude + longitude as floats.",
          "properties": {
            "latitude": {
              "type": "number"
            },
            "longitude": {
              "type": "number"
            }
          }
        },
        "area_served": {
          "type": "string",
          "description": "Town/county/region this page serves (e.g. \"Burke, TX\" or [\"Burke, TX\", \"Hudson, TX\"])."
        },
        "opening_hours": {
          "type": "string",
          "description": "Pass \"24/7\" for always-open. Or pass a custom array via the array form (not yet supported via tool input)."
        },
        "price_range": {
          "type": "string",
          "description": "Optional, e.g. \"$$\"."
        },
        "image": {
          "type": "string",
          "description": "Optional hero image URL."
        },
        "same_as": {
          "type": "array",
          "items": {
            "type": "string"
          },
          "description": "Social / authority profile URLs (Facebook, Instagram, GBP, etc.)."
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_update_postmeta",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer"
        },
        "meta_key": {
          "type": "string",
          "description": "Meta key. SEO-plugin keys (_yoast_wpseo_*, rank_math_*, _aioseo_*, _seopress_*) are blocked unless force_raw=true."
        },
        "value": {
          "type": "string"
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        },
        "force_raw": {
          "type": "boolean",
          "description": "Bypass the SEO-key guard. Use only for migration / data fixes; otherwise prefer draft_update_seo_meta."
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, return the validation result without queueing a pending row."
        },
        "success_metrics": {
          "type": "object",
          "description": "Stated intent for outcome scoring.",
          "properties": {
            "target_query": {
              "type": "string"
            },
            "target_position": {
              "type": "number"
            },
            "target_ctr": {
              "type": "number"
            },
            "eval_window_days": {
              "type": "integer"
            },
            "hypothesis": {
              "type": "string"
            }
          }
        }
      },
      "required": [
        "post_id",
        "meta_key",
        "value"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_trash_post",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "ID of the page/post to trash."
        },
        "summary": {
          "type": "string",
          "description": "Optional human-facing summary for the inbox. Defaults to \"Trash {type} \\\"{title}\\\" (ID N)\"."
        },
        "reasoning": {
          "type": "string"
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_add_elementor_widget",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer"
        },
        "parent_id": {
          "type": "string",
          "description": "Existing container/section/column id to insert into."
        },
        "widget_type": {
          "type": "string",
          "description": "Elementor widgetType, e.g. heading, text-editor, icon-box, icon-list, price-list, button, image."
        },
        "settings": {
          "type": "object",
          "description": "Widget settings object."
        },
        "position": {
          "type": "integer",
          "description": "Optional 0-based insert index. Omit to append at end."
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        },
        "override_lint": {
          "type": "boolean"
        },
        "dry_run": {
          "type": "boolean"
        }
      },
      "required": [
        "post_id",
        "parent_id",
        "widget_type",
        "settings"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_remove_elementor_widget",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer"
        },
        "widget_id": {
          "type": "string"
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        },
        "dry_run": {
          "type": "boolean"
        }
      },
      "required": [
        "post_id",
        "widget_id"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_add_elementor_container",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer"
        },
        "parent_id": {
          "type": "string",
          "description": "Parent container id, or \"\" to insert at page root."
        },
        "el_type": {
          "type": "string",
          "description": "section | container | column. Default container."
        },
        "settings": {
          "type": "object",
          "description": "Container settings (may be empty object)."
        },
        "children": {
          "type": "array",
          "description": "Optional pre-built children: [{type, widgetType?, settings, children?}]."
        },
        "position": {
          "type": "integer"
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        },
        "dry_run": {
          "type": "boolean"
        },
        "override_position": {
          "type": "boolean",
          "description": "Bypass the missing-position warning. Use when appending to page bottom is intentional."
        },
        "override_map_check": {
          "type": "boolean",
          "description": "v0.34 — bypass the map-first gate. Use ONLY when the page has no existing logical sections to split (very rare). Logged to activity log when used."
        },
        "override_section_continuity": {
          "type": "boolean",
          "description": "v0.34 — bypass the section-continuity check. Use ONLY when you really mean to split an H2 from its body container. Logged to activity log when used."
        },
        "override_a11y": {
          "type": "boolean",
          "description": "Bypass the contrast warning for proposed children. Use when the dark section background is handled outside the subtree."
        },
        "override_section_width": {
          "type": "boolean",
          "description": "Bypass the global section-width rule (1100-1300px boxed, 700-900px heading cap). Use when the section is intentionally edge-to-edge (e.g. a hero)."
        },
        "override_lint": {
          "type": "boolean",
          "description": "Bypass hard lint violations (em_dashes / ai_tells / style_guide / wall_of_text / placeholders / address_consistency / hospital_comparison). Use only after the human accepted the failures."
        }
      },
      "required": [
        "post_id",
        "parent_id",
        "settings"
      ]
    },
    "description": ""
  },
  {
    "name": "get_page_style_context",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer"
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_add_accordion_item",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer"
        },
        "accordion_widget_id": {
          "type": "string",
          "description": "Widget id of the nested-accordion. Find with get_elementor_widgets."
        },
        "title": {
          "type": "string",
          "description": "Question text (item_title)."
        },
        "content_html": {
          "type": "string",
          "description": "Answer body HTML."
        },
        "position": {
          "type": "integer"
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        },
        "override_lint": {
          "type": "boolean"
        },
        "override_dup": {
          "type": "boolean",
          "description": "Bypass the fuzzy-duplicate guard. Use only when the near-match is a deliberate variant."
        },
        "dry_run": {
          "type": "boolean"
        }
      },
      "required": [
        "post_id",
        "accordion_widget_id",
        "title"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_remove_accordion_item",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer"
        },
        "accordion_widget_id": {
          "type": "string",
          "description": "Widget id of the nested-accordion."
        },
        "item_id": {
          "type": "string",
          "description": "The _id of the accordion item to remove (from settings.items[].._id)."
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        },
        "dry_run": {
          "type": "boolean"
        }
      },
      "required": [
        "post_id",
        "accordion_widget_id",
        "item_id"
      ]
    },
    "description": ""
  },
  {
    "name": "audit_page_design",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post id to audit."
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "list_sections",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post id to list root-level Elementor sections for."
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "list_popups",
    "inputSchema": {
      "type": "object",
      "properties": {
        "covers_post_id": {
          "type": "integer",
          "description": "Optional target page/post id. Adds covers_this_post (true/false/null) per popup, computed from its display conditions. ALWAYS pass this when you are about to wire a popup button on a specific page."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "list_theme_templates",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "refresh_theme_builder_conditions",
    "inputSchema": {
      "type": "object",
      "properties": {
        "repair_post_id": {
          "type": "integer",
          "description": "Optional. Template post id to re-stamp before the cache regen."
        },
        "template_type": {
          "type": "string",
          "description": "Required with repair_post_id. One of: error-404, header, footer, single, single-page, single-post, archive, search-results, section, popup."
        },
        "display_conditions": {
          "type": "array",
          "items": {
            "type": "string"
          },
          "description": "Optional with repair_post_id. Elementor Pro condition strings, e.g. [\"include/singular/not_found404\"]."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "replace_section_content",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Required. Post id to mutate."
        },
        "section_id": {
          "type": "string",
          "description": "Optional. Root-level container id from list_sections. Empty string = whole-page batch."
        },
        "widget_updates": {
          "type": "array",
          "description": "Required. Array of { widget_id, settings } objects. settings is a partial Elementor settings object deep-merged onto the existing widget."
        },
        "reasoning": {
          "type": "string",
          "description": "Optional. Human-visible reason shown in the pending inbox."
        },
        "override_lint": {
          "type": "boolean",
          "description": "Optional. Bypass the content-quality lint (em dashes, AI-tells, style-guide, placeholder/lorem-ipsum, wall-of-text, address-consistency, hospital-comparison) that now runs on this path. Default false. Same gate as container_add / widget_add."
        }
      },
      "required": [
        "post_id",
        "widget_updates"
      ]
    },
    "description": ""
  },
  {
    "name": "list_image_placeholders",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post id to list image placeholders for."
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "export_elementor_data",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Required. Post id on THIS site to export."
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "import_elementor_data",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Required. Target post on THIS site that receives the cloned tree."
        },
        "raw_data": {
          "type": "string",
          "description": "Required. Raw _elementor_data JSON string from export_elementor_data on a sister site."
        },
        "replacements": {
          "type": "object",
          "description": "Map of search=>replace applied to text-bearing fields. Order matters — longer phrases first. Examples: {\"IV Therapy in Irving, TX\": \"Chest Pain Treatment in White Rock, TX\", \"irvingwellnessclinic.com\": \"erofwhiterock.com\", \"Irving Health and Wellness Clinic\": \"ER of White Rock\"}."
        },
        "regenerate_ids": {
          "type": "boolean",
          "description": "Default true. Assign fresh 8-char hex ids to every cloned element to avoid collision with existing target ids."
        },
        "strip_images": {
          "type": "boolean",
          "description": "Default false. Clear all image src URLs (image widget, container background_image, image-typed icons) so operator can upload real photos."
        },
        "map_kit_globals": {
          "type": "boolean",
          "description": "Default true. Keep system color/typography token refs (primary/secondary/text/accent) so target Kit resolves them; strip unresolvable custom-token refs."
        },
        "source_kit_globals": {
          "type": "object",
          "description": "The kit_globals payload from export_elementor_data on the source site. Optional but recommended for accurate mapping."
        },
        "snapshot_first": {
          "type": "boolean",
          "description": "Default true. Create a pre_elementor_full_import snapshot before applying so a botched import can be rolled back in one click."
        },
        "reasoning": {
          "type": "string",
          "description": "Optional. Human-visible reason shown in the pending inbox."
        },
        "dry_run": {
          "type": "boolean",
          "description": "If true, return the transform summary without queueing a pending. Useful to preview which replacements / globals would be applied."
        },
        "sort_replacements_by_length": {
          "type": "boolean",
          "description": "v0.31 default true. Auto-sort replacements longest-key-first so \"Irvingwellnessclinic\" matches before \"Irving\" (fixes the \"White Rockwellnessclinic\" substring-overlap bug)."
        },
        "replace_in_inline_styles": {
          "type": "boolean",
          "description": "v0.31 default true. Also scrub style=\"color:#xx\" / \"background-color:#xx\" colors embedded in editor HTML via the color_remap. Source-Kit-resolved colors baked into TinyMCE HTML survive widget-setting remaps without this."
        },
        "color_remap": {
          "type": "object",
          "description": "v0.31 — explicit { source-hex: target-hex } rewrites. Applied to widget color settings AND inline editor HTML (when replace_in_inline_styles=true). Hex / rgb / rgba all accepted. Example: { \"#003017\": \"#11468F\", \"#ffd900\": \"#DA1212\" } to wipe source-site brand colors during cross-site clone."
        },
        "strip_cross_domain_schema_html": {
          "type": "boolean",
          "description": "v0.31 default true. Drops html/text-editor widgets whose JSON-LD @id / url / sameAs / mainEntityOfPage points to a host other than the target site. Prevents source-site Person/MedicalClinic/FAQPage schemas from carrying over and linking back to the source domain."
        }
      },
      "required": [
        "post_id",
        "raw_data"
      ]
    },
    "description": ""
  },
  {
    "name": "build_service_page",
    "inputSchema": {
      "type": "object",
      "properties": {
        "mirror_post_id": {
          "type": "integer",
          "description": "Required. Post id of a working pillar to clone (e.g., IV pillar 137)."
        },
        "title": {
          "type": "string",
          "description": "Required. post_title for the new draft."
        },
        "slug": {
          "type": "string",
          "description": "Optional. URL slug; derived from title if omitted."
        },
        "post_type": {
          "type": "string",
          "description": "page (default) or post; must be in site allowlist."
        },
        "replacements": {
          "type": "object",
          "description": "Ordered associative map of search => replace. Order matters — longer phrases (e.g., \"IV Therapy in Irving, TX\") first, shorter (\"IV Therapy\") last, otherwise the long-form replace cannot match. Applied with str_ireplace (case-insensitive)."
        },
        "skip_widget_ids": {
          "type": "array",
          "description": "Optional. Array of node ids from the MIRROR to drop entirely (subtree). Use get_page_style_context(mirror_post_id) to find root container ids."
        },
        "seo_title": {
          "type": "string",
          "description": "Optional. Auto-routes to rank_math_title / _yoast_wpseo_title / _aioseo_title."
        },
        "seo_description": {
          "type": "string",
          "description": "Optional. 120-160 chars recommended. Auto-routes to the SEO plugin in use."
        },
        "focus_keyword": {
          "type": "string",
          "description": "Optional. Rank Math focus keyword (or equivalent on other SEO plugins)."
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, return the structure summary + lint report WITHOUT writing the post or queueing a pending."
        },
        "override_lint": {
          "type": "boolean",
          "description": "Set true ONLY when human has accepted the hard-lint failures (em_dashes / ai_tells / placeholders / style_guide)."
        }
      },
      "required": [
        "mirror_post_id",
        "title"
      ]
    },
    "description": ""
  },
  {
    "name": "suggest_best_mirror",
    "inputSchema": {
      "type": "object",
      "properties": {
        "service_keyword": {
          "type": "string",
          "description": "Optional. Filter candidates by keyword that must appear in title or slug (e.g., \"iv therapy\")."
        },
        "target_language": {
          "type": "string",
          "description": "Optional. Polylang/WPML language tag (e.g., \"en\", \"es\") to constrain results to same-language pillars."
        },
        "exclude_id": {
          "type": "integer",
          "description": "Optional. Post id to omit from candidates (e.g., the page being replaced)."
        },
        "limit": {
          "type": "integer",
          "description": "Optional. Max candidates to return (1-10, default 5)."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "page_completeness_score",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Page to score."
        },
        "mirror_post_id": {
          "type": "integer",
          "description": "Reference pillar to score against."
        }
      },
      "required": [
        "post_id",
        "mirror_post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "service_inventory",
    "inputSchema": {
      "type": "object",
      "properties": {}
    },
    "description": ""
  },
  {
    "name": "reject_pending_change",
    "inputSchema": {
      "type": "object",
      "properties": {
        "pending_id": {
          "type": "integer",
          "description": "Single pending id to reject (use this OR pending_ids)."
        },
        "pending_ids": {
          "type": "array",
          "description": "Array of pending ids to bulk-reject (preferred for multi-reject).",
          "items": {
            "type": "integer"
          }
        },
        "note": {
          "type": "string",
          "description": "Rejection reason captured on the pending row for audit."
        }
      }
    },
    "description": ""
  },
  {
    "name": "draft_update_elementor_widget",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer"
        },
        "widget_id": {
          "type": "string",
          "description": "Elementor widget id (8-char hex, get from get_elementor_widgets)."
        },
        "settings": {
          "type": "object",
          "description": "Object of widget settings to merge."
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        },
        "override_lint": {
          "type": "boolean",
          "description": "Set true ONLY if you have shown lint failures to the human and they accepted them."
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, return lint + would-be-pending without queueing."
        },
        "success_metrics": {
          "type": "object",
          "description": "Stated intent for outcome scoring.",
          "properties": {
            "target_query": {
              "type": "string"
            },
            "target_position": {
              "type": "number"
            },
            "target_ctr": {
              "type": "number"
            },
            "eval_window_days": {
              "type": "integer"
            },
            "hypothesis": {
              "type": "string"
            }
          }
        }
      },
      "required": [
        "post_id",
        "widget_id",
        "settings"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_update_post_content",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post ID whose body to replace."
        },
        "content": {
          "type": "string",
          "description": "Full new HTML body. Replaces post_content entirely on approval."
        },
        "summary": {
          "type": "string",
          "description": "One-line description for the inbox (e.g., \"Body expansion: add severity staging + risk groups\")."
        },
        "reasoning": {
          "type": "string",
          "description": "Why this rewrite makes sense. Shown to the reviewer alongside the diff. Reference the brief and the SERP signals you used."
        },
        "override_lint": {
          "type": "boolean",
          "description": "Set true ONLY if you have already shown the lint failures to the human and they have explicitly accepted them. Default false: hard violations refuse the change."
        },
        "force_no_outline": {
          "type": "boolean",
          "description": "Bypass the outline-required gate for large rewrites. Use only for migrations / batch fixes; otherwise call propose_rewrite_outline first."
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, return lint + would-be-pending without queueing. Use for self-checks before the human commits."
        },
        "success_metrics": {
          "type": "object",
          "description": "Stated intent, used by edit_outcomes to score the change later. Strongly recommended.",
          "properties": {
            "target_query": {
              "type": "string",
              "description": "GSC query you expect this rewrite to lift."
            },
            "target_position": {
              "type": "number",
              "description": "Target average position after the rewrite settles (e.g., 3.0)."
            },
            "target_ctr": {
              "type": "number",
              "description": "Target CTR after the rewrite (0..1). Often only realistic alongside a meta title change."
            },
            "eval_window_days": {
              "type": "integer",
              "description": "How long to wait before scoring the change. 7..90, default 28."
            },
            "hypothesis": {
              "type": "string",
              "description": "One-sentence \"I believe X will move Y because Z\" so the verdict tool can summarise it."
            }
          }
        }
      },
      "required": [
        "post_id",
        "content"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_patch_post_content",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post ID whose body to patch."
        },
        "patches": {
          "type": "array",
          "description": "Ordered list of search/replace pairs. Each search must match exactly once in the current post_content. Max 50 per call.",
          "items": {
            "type": "object",
            "properties": {
              "search": {
                "type": "string",
                "description": "Substring from current post_content. Must match byte-for-byte and appear exactly once. Include enough surrounding context to disambiguate if the literal phrase repeats."
              },
              "replace": {
                "type": "string",
                "description": "Replacement text. Typically the search string wrapped in an <a> tag, or the search with a small phrase tweak."
              }
            },
            "required": [
              "search",
              "replace"
            ]
          }
        },
        "summary": {
          "type": "string",
          "description": "One-line description for the inbox."
        },
        "reasoning": {
          "type": "string",
          "description": "Why this patch makes sense. Shown to the reviewer alongside the diff."
        },
        "override_lint": {
          "type": "boolean",
          "description": "Set true ONLY if you have shown the lint failures to the human and they have explicitly accepted them."
        },
        "force_no_outline": {
          "type": "boolean",
          "description": "Bypass the outline-required gate. Surgical patches almost never trigger it; rarely needed."
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, applies patches in-memory and returns lint + would-be-pending without queueing."
        },
        "success_metrics": {
          "type": "object",
          "description": "Stated intent, used by edit_outcomes to score the change later.",
          "properties": {
            "target_query": {
              "type": "string"
            },
            "target_position": {
              "type": "number"
            },
            "target_ctr": {
              "type": "number"
            },
            "eval_window_days": {
              "type": "integer"
            },
            "hypothesis": {
              "type": "string"
            }
          }
        }
      },
      "required": [
        "post_id",
        "patches"
      ]
    },
    "description": ""
  },
  {
    "name": "list_divi_modules",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post ID of the Divi-built post."
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "find_asset_references",
    "inputSchema": {
      "type": "object",
      "properties": {
        "url": {
          "type": "string",
          "description": "The media URL, e.g. https://example.com/wp-content/uploads/2026/08/hero.webp. Root-relative also works."
        },
        "limit": {
          "type": "integer",
          "description": "Max locations to return (default 300)."
        }
      },
      "required": [
        "url"
      ]
    },
    "description": ""
  },
  {
    "name": "replace_asset_reference",
    "inputSchema": {
      "type": "object",
      "properties": {
        "old_url": {
          "type": "string",
          "description": "URL currently stored."
        },
        "new_url": {
          "type": "string",
          "description": "URL to store instead. Must already be uploaded."
        },
        "reasoning": {
          "type": "string",
          "description": "Why this swap. Shown to the reviewer alongside the location list."
        },
        "dry_run": {
          "type": "boolean",
          "description": "Return the location list and what would change, without queueing."
        },
        "allow_missing_target": {
          "type": "boolean",
          "description": "Set true ONLY when new_url is served from somewhere other than this server (CDN, external host). Otherwise a missing file is refused."
        },
        "limit": {
          "type": "integer",
          "description": "Max locations to include (default 300)."
        }
      },
      "required": [
        "old_url",
        "new_url"
      ]
    },
    "description": ""
  },
  {
    "name": "upload_media",
    "inputSchema": {
      "type": "object",
      "properties": {
        "filename": {
          "type": "string",
          "description": "Target filename WITH extension, e.g. \"insulin-resistance-symptoms.webp\". Sanitised via sanitize_file_name. Use the destination post slug as the stem so the library stays greppable."
        },
        "content_base64": {
          "type": "string",
          "description": "Raw file bytes, base64-encoded. No data: URI prefix."
        },
        "alt": {
          "type": "string",
          "description": "Alt text, written to _wp_attachment_image_alt. Describe what the image SHOWS in the context of its section; do not restate the post title (the featured image already owns that)."
        },
        "title": {
          "type": "string",
          "description": "Optional media-library title. Defaults to the filename stem."
        },
        "post_id": {
          "type": "integer",
          "description": "Optional post to set as post_parent, which files the image under that post in the library. Does NOT set the featured image and does NOT place it in the body."
        }
      },
      "required": [
        "filename",
        "content_base64"
      ]
    },
    "description": ""
  },
  {
    "name": "delete_media",
    "inputSchema": {
      "type": "object",
      "properties": {
        "ids": {
          "type": "array",
          "items": {
            "type": "integer"
          },
          "description": "Attachment IDs to delete. Max 50 per call."
        },
        "confirm": {
          "type": "boolean",
          "description": "Default false, which is a dry run reporting what would go. Pass true to actually delete. Deletion is permanent."
        },
        "allow_foreign": {
          "type": "boolean",
          "description": "Default false. Permits deleting attachments this plugin did not upload. Only pass this when a human has confirmed the specific files are disposable."
        }
      },
      "required": [
        "ids"
      ]
    },
    "description": ""
  },
  {
    "name": "media_audit",
    "inputSchema": {
      "type": "object",
      "properties": {
        "min_kb": {
          "type": "integer",
          "description": "Only report images at least this many KB on disk (default 150)."
        },
        "limit": {
          "type": "integer",
          "description": "Max rows in the heaviest list (default and max 60)."
        }
      }
    },
    "description": ""
  },
  {
    "name": "get_divi_module",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post ID of the Divi-built post."
        },
        "index": {
          "type": "integer",
          "description": "Module index from list_divi_modules (same parse)."
        }
      },
      "required": [
        "post_id",
        "index"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_update_divi_modules",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post ID of the Divi-built post."
        },
        "edits": {
          "type": "array",
          "description": "Module edits, each targeting a distinct index from the CURRENT list_divi_modules parse. Max 30.",
          "items": {
            "type": "object",
            "properties": {
              "index": {
                "type": "integer",
                "description": "Module index from list_divi_modules."
              },
              "type": {
                "type": "string",
                "description": "Expected module type (e.g. et_pb_text). Safety check against stale indexes."
              },
              "inner_content": {
                "type": "string",
                "description": "Replacement inner HTML for the module. Non-structural modules only; no section/row/column shortcodes."
              },
              "set_attrs": {
                "type": "object",
                "description": "Attributes to set on the open tag: {name: value}. Pass null as value to remove an attribute. Encode double quotes inside values as %22 (Divi convention)."
              }
            },
            "required": [
              "index",
              "type"
            ]
          }
        },
        "summary": {
          "type": "string",
          "description": "One-line description for the inbox."
        },
        "reasoning": {
          "type": "string",
          "description": "Why this edit makes sense. Shown to the reviewer alongside the diff."
        },
        "override_lint": {
          "type": "boolean",
          "description": "Set true ONLY if you have shown the lint failures to the human and they have explicitly accepted them."
        },
        "force_no_outline": {
          "type": "boolean",
          "description": "Bypass the outline-required gate. Module-level edits rarely trigger it."
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, splices edits in-memory and returns lint + would-be-pending without queueing."
        },
        "success_metrics": {
          "type": "object",
          "description": "Stated intent, used by edit_outcomes to score the change later.",
          "properties": {
            "target_query": {
              "type": "string"
            },
            "target_position": {
              "type": "number"
            },
            "target_ctr": {
              "type": "number"
            },
            "eval_window_days": {
              "type": "integer"
            },
            "hypothesis": {
              "type": "string"
            }
          }
        }
      },
      "required": [
        "post_id",
        "edits"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_remove_divi_module",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post ID of the Divi-built post or Theme Builder layout (body_layout_id from list_theme_templates)."
        },
        "index": {
          "type": "integer",
          "description": "Module index from the CURRENT list_divi_modules parse."
        },
        "type": {
          "type": "string",
          "description": "Expected module type at that index (e.g. difl_dual_button). Guard against a stale inventory."
        },
        "confirm_children": {
          "type": "boolean",
          "description": "Required only when the module contains nested modules; acknowledges that the whole subtree goes."
        },
        "summary": {
          "type": "string",
          "description": "One-line description for the inbox."
        },
        "reasoning": {
          "type": "string",
          "description": "Why removing it is safe. Shown to the reviewer alongside the diff."
        },
        "override_lint": {
          "type": "boolean",
          "description": "Set true ONLY if you have shown the lint failures to the human and they have explicitly accepted them."
        },
        "force_no_outline": {
          "type": "boolean",
          "description": "Bypass the outline-required gate."
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, splices the removal in-memory and returns lint + blast radius without queueing. Use this first."
        }
      },
      "required": [
        "post_id",
        "index",
        "type"
      ]
    },
    "description": ""
  },
  {
    "name": "audit_post_links",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        },
        "check_external": {
          "type": "boolean",
          "description": "If true, makes HEAD requests to find broken external links."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "pre_publish_check",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": "Read the advisory content checklist and publication_gate, the actual quality gate used by publish_draft. Top-level pass only covers the checklist. Proposal freshness, human approval, factual correctness, rendered behavior, indexing and ranking are not certified."
  },
  {
    "name": "get_site_memory",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": "CRITICAL: call this at the start of every session before proposing changes. Returns the auto-detected stack (SEO plugin, page builder, multilingual, commerce, caching, theme) plus user-maintained notes. The seo.meta_keys map tells you which postmeta keys to use for that site (Yoast vs Rank Math vs AIOSEO use different keys). If you write the wrong keys you create orphan data the user has to manually clean up. Includes memory_consistency: potential outdated durable guidance, a full-notes fingerprint and precedence/context rules. Phrase flags require review; the scan does not inspect local files."
  },
  {
    "name": "update_site_memory_notes",
    "inputSchema": {
      "type": "object",
      "properties": {
        "notes": {
          "type": "string",
          "description": "Short markdown line. Keep terse: future you only sees the last ~500 chars in session_recap."
        },
        "section": {
          "type": "string",
          "description": "rules | decisions | sessions (default). Rules and Decisions persist; Sessions is append-only timestamped log."
        },
        "mode": {
          "type": "string",
          "description": "append (default) or replace (overwrites the entire blob — use only when consolidating)."
        },
        "acknowledge_open_loops": {
          "type": "boolean",
          "description": "v0.81: a Sessions note is REFUSED (409 open_loops_unacknowledged) while working_state is active with open_loops. Close them first (update_working_state remove_open_loops / status), or pass true to log anyway; the loops are appended to the note so the next chat inherits them explicitly."
        }
      },
      "required": [
        "notes"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_update_seo_meta",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer"
        },
        "logical_key": {
          "type": "string",
          "description": "description, title, focus_keyword, canonical, og_title, og_description"
        },
        "value": {
          "type": "string"
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        },
        "override_lint": {
          "type": "boolean",
          "description": "Set true ONLY if you have shown lint failures to the human and they accepted them."
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, return lint + would-be-pending without queueing."
        },
        "success_metrics": {
          "type": "object",
          "description": "Stated intent, used by edit_outcomes to score the change later. Strongly recommended for title/description changes since CTR delta is directly measurable.",
          "properties": {
            "target_query": {
              "type": "string"
            },
            "target_position": {
              "type": "number"
            },
            "target_ctr": {
              "type": "number"
            },
            "eval_window_days": {
              "type": "integer"
            },
            "hypothesis": {
              "type": "string"
            }
          }
        }
      },
      "required": [
        "post_id",
        "logical_key",
        "value"
      ]
    },
    "description": ""
  },
  {
    "name": "get_style_guide",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "draft_create_post",
    "inputSchema": {
      "type": "object",
      "properties": {
        "title": {
          "type": "string"
        },
        "content": {
          "type": "string",
          "description": "HTML body of the post."
        },
        "excerpt": {
          "type": "string",
          "description": "Manual excerpt for the post. Recommended; without one WP auto-generates a usually-unflattering preview from the body."
        },
        "post_type": {
          "type": "string",
          "description": "Default \"page\". Must be in the site allowlist. v0.51.5: \"elementor_library\" is also accepted when the \"Allow editing Theme Builder templates\" setting is on — pass template_type (and usually display_conditions) with it to compose a Theme Builder template (e.g. a 404 page) through the same draft->approve flow."
        },
        "template_type": {
          "type": "string",
          "description": "REQUIRED when post_type is a Theme Builder CPT. One of: error-404, header, footer, single, single-page, single-post, archive, search-results, section, popup. Stamps _elementor_template_type + the elementor_library_type taxonomy so the template shows correctly in Theme Builder."
        },
        "display_conditions": {
          "type": "array",
          "items": {
            "type": "string"
          },
          "description": "Elementor Pro display-condition strings written to _elementor_conditions, e.g. [\"include/singular/not_found404\"] for a 404 template or [\"include/general\"] site-wide. Dormant while the template is a draft; on approve the plugin regenerates the Elementor Pro conditions cache so the template goes live in its slot. Add the Elementor tree afterwards via import_elementor_data(post_id, raw_data)."
        },
        "categories": {
          "type": "array",
          "items": {
            "type": [
              "string",
              "integer"
            ]
          },
          "description": "Categories for the post. Pass an array of category names (strings) or IDs (integers). Names are resolved against existing terms; missing names are auto-created. Only applied when the post type supports the \"category\" taxonomy (default for post_type=post). Without this, WP assigns \"Uncategorized\", which is bad SEO."
        },
        "tags": {
          "type": "array",
          "items": {
            "type": "string"
          },
          "description": "Tags for the post. Array of strings; missing tags are auto-created. Only applied when the post type supports the \"post_tag\" taxonomy (default for post_type=post)."
        },
        "featured_image_id": {
          "type": "integer",
          "description": "Attachment ID of an image already uploaded to the WP media library. Sets the featured image (post thumbnail) on the new draft. Posts without a featured image look bare in lists, social shares, and OG cards."
        },
        "summary": {
          "type": "string"
        },
        "override_lint": {
          "type": "boolean",
          "description": "Set true ONLY if you have shown lint failures to the human and they accepted them."
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, validate body + title without inserting a WP draft post or a pending row. Use for self-checks."
        },
        "success_metrics": {
          "type": "object",
          "description": "Stated intent for outcome scoring on the new post.",
          "properties": {
            "target_query": {
              "type": "string"
            },
            "target_position": {
              "type": "number"
            },
            "target_ctr": {
              "type": "number"
            },
            "eval_window_days": {
              "type": "integer"
            },
            "hypothesis": {
              "type": "string"
            }
          }
        },
        "reasoning": {
          "type": "string"
        },
        "category_ids": {
          "type": "array",
          "description": "Optional array of WordPress category term IDs to attach on insert. The new post is wp_set_post_terms-ed to these categories before the publish_draft pending is queued, so the draft never lands uncategorized. Get valid IDs from the WP REST /wp/v2/categories endpoint.",
          "items": {
            "type": "integer"
          }
        },
        "lang": {
          "type": "string",
          "description": "Optional language slug (e.g. \"en\", \"es\"). On multilingual sites this assigns the new post to that language taxonomy via the active engine (Polylang supported; WPML and TranslatePress not yet — see multilingual_warnings in the response). Ignored on single-language sites."
        },
        "polylang_translation_of": {
          "type": "integer",
          "description": "Optional existing post_id this new draft is a translation of. After insert the apply layer calls pll_save_post_translations() to pair them. On a 2-language site `lang` can be omitted (derived from the source's \"other\" language). Source post must already have its language set. Translation pairing is logged in `multilingual_warnings` on partial failure rather than aborting the post create."
        },
        "slug": {
          "type": "string",
          "description": "Optional canonical URL slug (post_name). When omitted, WordPress derives the slug from the title — long H1s produce 100+ char slugs that hurt shareability and SEO. Pass an explicit short slug (e.g. \"infectious-disease-vector-borne-emergencies-near-white-rock-lake\") to set it at create time and avoid a follow-up draft_update_post_meta(post_name). Sanitised via sanitize_title."
        },
        "workflow_id": {
          "type": "string",
          "pattern": "^workflow-[a-f0-9]{32}$",
          "description": "For a new_blog workflow, bind this draft to its current evidence. Requires post_type=post. Call verify_content_workflow with the returned post ID."
        },
        "author_id": {
          "type": "integer",
          "minimum": 1,
          "description": "Existing public author ID from get_content_authors. Required unless a validated author is saved in content scope. Never silently use the automation login."
        }
      },
      "required": [
        "title",
        "content"
      ]
    },
    "description": ""
  },
  {
    "name": "polylang_link_translations",
    "inputSchema": {
      "type": "object",
      "properties": {
        "pairs": {
          "type": "array",
          "description": "Array of pair objects. Each pair maps language slugs to post IDs covering all translations of one content item.",
          "items": {
            "type": "object",
            "description": "Map of language_slug => post_id (e.g. {\"en\": 4986, \"es\": 5009}).",
            "additionalProperties": {
              "type": "integer"
            }
          }
        },
        "force_language": {
          "type": "boolean",
          "description": "When true, overwrite a post's existing language if it conflicts with the pair (e.g. an EN post mistakenly tagged as ES). Default false — conflicts return an error on that pair without touching it."
        }
      },
      "required": [
        "pairs"
      ]
    },
    "description": ""
  },
  {
    "name": "create_category",
    "inputSchema": {
      "type": "object",
      "properties": {
        "name": {
          "type": "string",
          "description": "Display name (e.g. \"Pediatric Care\")."
        },
        "slug": {
          "type": "string",
          "description": "URL slug. Optional — defaults to sanitised name."
        },
        "description": {
          "type": "string",
          "description": "Optional category description shown on archive pages."
        },
        "parent": {
          "type": "integer",
          "description": "Optional parent category term ID for hierarchy."
        }
      },
      "required": [
        "name"
      ]
    },
    "description": ""
  },
  {
    "name": "list_categories",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "list_product_categories",
    "inputSchema": {
      "type": "object",
      "properties": {
        "taxonomy": {
          "type": "string",
          "description": "product_cat (default) or category."
        },
        "full": {
          "type": "boolean",
          "description": "Return complete description HTML instead of 240-char excerpts. Default false."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "list_installed_plugins",
    "inputSchema": {
      "type": "object",
      "properties": {
        "include_inactive": {
          "type": "boolean",
          "description": "Default true. Set false for active plugins only."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "stack_admin_menu",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "get_plugin_settings",
    "inputSchema": {
      "type": "object",
      "properties": {
        "slug": {
          "type": "string",
          "description": "Plugin folder slug from list_installed_plugins (e.g. \"woo-multi-currency\"), or the active theme's slug (e.g. \"divi\") to read theme options instead."
        }
      },
      "required": [
        "slug"
      ]
    },
    "description": ""
  },
  {
    "name": "get_kit_settings",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "draft_update_kit_setting",
    "inputSchema": {
      "type": "object",
      "properties": {
        "path": {
          "type": "string",
          "description": "Dot path into the kit settings, e.g. \"link_normal_color\" or \"system_colors.primary.color\"."
        },
        "value": {
          "description": "New scalar value (string, number, or boolean)."
        },
        "allow_create": {
          "type": "boolean",
          "description": "Permit creating a scalar key that does not exist yet. Never creates list rows."
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        },
        "dry_run": {
          "type": "boolean",
          "description": "Return the before/after without queueing."
        }
      },
      "required": [
        "path",
        "value"
      ]
    },
    "description": ""
  },
  {
    "name": "get_rank_math_schema",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post or page id."
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_update_rank_math_schema",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post or page id."
        },
        "set": {
          "type": "object",
          "description": "Object of dot-path => scalar value. Example: {\"offers.price\": \"38999\"}."
        },
        "meta_key": {
          "type": "string",
          "description": "Which row, e.g. rank_math_schema_Product. Optional when the post has exactly one; required when it has several."
        },
        "summary": {
          "type": "string",
          "description": "One-line description for the inbox."
        },
        "reasoning": {
          "type": "string",
          "description": "Why this change makes sense. Shown to the reviewer."
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, validate and return the would-be change without queueing."
        },
        "success_metrics": {
          "type": "object",
          "properties": {
            "target_query": {
              "type": "string"
            },
            "target_position": {
              "type": "number"
            },
            "target_ctr": {
              "type": "number"
            },
            "eval_window_days": {
              "type": "integer"
            },
            "hypothesis": {
              "type": "string"
            }
          }
        }
      },
      "required": [
        "post_id",
        "set"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_update_plugin_setting",
    "inputSchema": {
      "type": "object",
      "properties": {
        "option_name": {
          "type": "string",
          "description": "Exact wp_options name, from get_plugin_settings."
        },
        "path": {
          "type": "string",
          "description": "Dot path to the leaf inside a nested option, e.g. \"feedrules.thousand_separator\". Omit to replace a scalar option outright."
        },
        "value": {
          "description": "New scalar value (string, number, or boolean). Empty string blanks the setting."
        },
        "allow_create": {
          "type": "boolean",
          "description": "Default false. Creation also requires an explicit registered REST schema for this path; model certainty is not evidence."
        },
        "summary": {
          "type": "string",
          "description": "One-line description for the inbox."
        },
        "reasoning": {
          "type": "string",
          "description": "Why. Shown to the reviewer."
        },
        "dry_run": {
          "type": "boolean",
          "description": "Return the before/after without queueing."
        },
        "adapter": {
          "type": "string",
          "enum": [
            "siteground_frontend_v1"
          ],
          "description": "Only when inspect_plugin_capability reports this native adapter available. Omit for generic settings."
        }
      },
      "required": [
        "option_name",
        "value"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_bulk_assign_term",
    "inputSchema": {
      "type": "object",
      "properties": {
        "taxonomy": {
          "type": "string",
          "description": "Target taxonomy, e.g. product_brand, product_cat, post_tag."
        },
        "term_id": {
          "type": "integer",
          "description": "Term to assign. Must already exist."
        },
        "post_type": {
          "type": "string",
          "description": "product (default), post, or page."
        },
        "match_type": {
          "type": "string",
          "description": "title_contains (default) | in_term | post_ids"
        },
        "match_value": {
          "type": "string",
          "description": "Search string for title_contains, e.g. \"Kichler\"."
        },
        "source_term": {
          "type": "integer",
          "description": "For match_type=in_term: term id whose members (incl. sub-terms) to tag."
        },
        "post_ids": {
          "type": "array",
          "items": {
            "type": "integer"
          },
          "description": "For match_type=post_ids."
        },
        "mode": {
          "type": "string",
          "description": "append (default, keeps existing terms) | replace"
        },
        "summary": {
          "type": "string",
          "description": "One-line description for the inbox."
        },
        "reasoning": {
          "type": "string",
          "description": "Why. Shown to the reviewer."
        },
        "dry_run": {
          "type": "boolean",
          "description": "Return the plan without queueing."
        }
      },
      "required": [
        "taxonomy",
        "term_id"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_update_term",
    "inputSchema": {
      "type": "object",
      "properties": {
        "term_id": {
          "type": "integer",
          "description": "Target term id (from list_product_categories)."
        },
        "taxonomy": {
          "type": "string",
          "description": "product_cat (default) or category."
        },
        "description": {
          "type": "string",
          "description": "New archive description HTML. Omit to leave unchanged."
        },
        "seo_title": {
          "type": "string",
          "description": "Rank Math term SEO title. Omit to leave unchanged; empty string deletes."
        },
        "seo_description": {
          "type": "string",
          "description": "Rank Math term SEO description. Omit to leave unchanged; empty string deletes."
        },
        "summary": {
          "type": "string",
          "description": "One-line description for the inbox."
        },
        "reasoning": {
          "type": "string",
          "description": "Why this update makes sense. Shown to the reviewer."
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, return lint + would-be-pending without queueing."
        },
        "override_lint": {
          "type": "boolean",
          "description": "Set true ONLY if the human has accepted the lint failures."
        },
        "success_metrics": {
          "type": "object",
          "properties": {
            "target_query": {
              "type": "string"
            },
            "target_position": {
              "type": "number"
            },
            "target_ctr": {
              "type": "number"
            },
            "eval_window_days": {
              "type": "integer"
            },
            "hypothesis": {
              "type": "string"
            }
          }
        }
      },
      "required": [
        "term_id"
      ]
    },
    "description": ""
  },
  {
    "name": "delete_category",
    "inputSchema": {
      "type": "object",
      "properties": {
        "term_ids": {
          "type": "array",
          "description": "Array of category term IDs to delete. Get IDs from list_categories.",
          "items": {
            "type": "integer"
          }
        },
        "force": {
          "type": "boolean",
          "description": "When true, delete a term even if it has posts attached. Default false. Use only after confirming the posts have been reassigned or that orphaning them is intended."
        }
      },
      "required": [
        "term_ids"
      ]
    },
    "description": ""
  },
  {
    "name": "draft_update_categories",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer"
        },
        "category_ids": {
          "type": "array",
          "description": "WordPress category term IDs to attach.",
          "items": {
            "type": "integer"
          }
        },
        "append": {
          "type": "boolean",
          "description": "When true, ADD these categories without removing existing ones. Default false (replace)."
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        },
        "success_metrics": {
          "type": "object",
          "description": "Stated intent for outcome scoring.",
          "properties": {
            "target_query": {
              "type": "string"
            },
            "target_position": {
              "type": "number"
            },
            "target_ctr": {
              "type": "number"
            },
            "eval_window_days": {
              "type": "integer"
            },
            "hypothesis": {
              "type": "string"
            }
          }
        },
        "dry_run": {
          "type": "boolean"
        }
      },
      "required": [
        "post_id",
        "category_ids"
      ]
    },
    "description": ""
  },
  {
    "name": "review_health",
    "inputSchema": {
      "type": "object",
      "properties": {
        "refresh": {
          "type": "boolean",
          "description": "Fetch from Google before reporting instead of reading the daily cache. Default false."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "gsc_status",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "gsc_opportunities",
    "inputSchema": {
      "type": "object",
      "properties": {
        "min_position": {
          "type": "number",
          "description": "Lower bound for average position. Default 5."
        },
        "max_position": {
          "type": "number",
          "description": "Upper bound. Default 15."
        },
        "min_impressions": {
          "type": "integer",
          "description": "Minimum total impressions in the window. Default 50."
        },
        "limit": {
          "type": "integer",
          "description": "Max rows. Default 100, cap 500."
        },
        "days": {
          "type": "integer",
          "description": "Trailing window in days. Default 28, cap 90."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "gsc_low_ctr",
    "inputSchema": {
      "type": "object",
      "properties": {
        "min_impressions": {
          "type": "integer",
          "description": "Minimum impressions per page. Default 1000."
        },
        "max_ctr": {
          "type": "number",
          "description": "Maximum CTR (0..1). Default 0.02."
        },
        "limit": {
          "type": "integer",
          "description": "Max rows. Default 100."
        },
        "days": {
          "type": "integer",
          "description": "Trailing window in days. Default 28."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "gsc_missing_mentions",
    "inputSchema": {
      "type": "object",
      "properties": {
        "min_impressions": {
          "type": "integer",
          "description": "Minimum impressions per (page, query). Default 100."
        },
        "limit": {
          "type": "integer",
          "description": "Max rows. Default 50."
        },
        "days": {
          "type": "integer",
          "description": "Trailing window in days. Default 28."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "gsc_page_queries",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "WordPress post ID."
        },
        "page": {
          "type": "string",
          "description": "Full page URL as in GSC. Use if post_id is not handy."
        },
        "limit": {
          "type": "integer",
          "description": "Max queries. Default 100."
        },
        "days": {
          "type": "integer",
          "description": "Trailing window. Default 28."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "gsc_trends",
    "inputSchema": {
      "type": "object",
      "properties": {
        "window_days": {
          "type": "integer",
          "description": "Window length in days for both halves of the comparison. Default 7, cap 30."
        },
        "lens": {
          "type": "string",
          "description": "all | decay | rise | new_striking | lost. Default all."
        },
        "limit": {
          "type": "integer",
          "description": "Max rows per lens. Default 25."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "list_recent_edits",
    "inputSchema": {
      "type": "object",
      "properties": {
        "limit": {
          "type": "integer",
          "description": "Max edits to return. Default 25."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "get_edit_outcome",
    "inputSchema": {
      "type": "object",
      "properties": {
        "edit_id": {
          "type": "integer",
          "description": "ID of an edit row from list_recent_edits."
        }
      },
      "required": [
        "edit_id"
      ]
    },
    "description": ""
  },
  {
    "name": "gsc_anomalies",
    "inputSchema": {
      "type": "object",
      "properties": {
        "window_days": {
          "type": "integer",
          "description": "Recent window in days. Default 7, cap 14."
        },
        "baseline_days": {
          "type": "integer",
          "description": "Baseline length in days. Default 28, cap 60."
        },
        "direction": {
          "type": "string",
          "description": "decay | rise | both. Default both."
        },
        "limit": {
          "type": "integer",
          "description": "Max rows. Default 25."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "gsc_ai_overview",
    "inputSchema": {
      "type": "object",
      "properties": {
        "days": {
          "type": "integer",
          "description": "Trailing window. Default 28, cap 90."
        },
        "limit": {
          "type": "integer",
          "description": "Max rows. Default 50."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "aio_ctr_drop_alert",
    "inputSchema": {
      "type": "object",
      "properties": {
        "min_impressions": {
          "type": "integer",
          "description": "Minimum total impressions in window. Default 500."
        },
        "max_position": {
          "type": "number",
          "description": "Only consider pages ranking at or above this position. Default 5.0 (i.e. avg position ≤ 5)."
        },
        "max_ratio": {
          "type": "number",
          "description": "Flag if actual_ctr / expected_ctr ≤ this. Default 0.5 (CTR at half of expected or worse). Set 0.7 for a wider net, 0.3 for hard outliers only."
        },
        "limit": {
          "type": "integer",
          "description": "Max rows. Default 50, cap 200."
        },
        "days": {
          "type": "integer",
          "description": "Trailing window in days. Default 28, cap 90."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "gsc_intent_breakdown",
    "inputSchema": {
      "type": "object",
      "properties": {
        "days": {
          "type": "integer",
          "description": "Trailing window. Default 28."
        },
        "post_id": {
          "type": "integer",
          "description": "Restrict to one page. 0 = site-wide."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "llm_crawls",
    "inputSchema": {
      "type": "object",
      "properties": {
        "days": {
          "type": "integer",
          "description": "Trailing window. Default 7, cap 90."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "links_summary",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "links_orphans",
    "inputSchema": {
      "type": "object",
      "properties": {
        "limit": {
          "type": "integer",
          "description": "Max rows. Default 50, cap 500."
        },
        "include_pending": {
          "type": "boolean",
          "description": "When true, include orphans that already have a queued pending inbound link (annotated with pending_inbound). Default false."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "links_audit_post",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "WordPress post ID."
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "links_rebuild",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "content_audits",
    "inputSchema": {
      "type": "object",
      "properties": {
        "limit": {
          "type": "integer",
          "description": "Max posts to scan. Default 200, cap 1000."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "connection_test",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "topical_authority",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_type": {
          "type": "string",
          "description": "Post type to scan. Default \"post\"."
        },
        "threshold": {
          "type": "number",
          "description": "Cluster similarity threshold 0..1. Default 0.55."
        },
        "limit": {
          "type": "integer",
          "description": "Max posts to scan. Default 200, cap 1000."
        },
        "days": {
          "type": "integer",
          "description": "GSC window in days. Default 28, cap 90."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "list_topic_clusters",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "get_topic_cluster",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Cluster ID from list_topic_clusters."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "propose_topic_cluster",
    "inputSchema": {
      "type": "object",
      "properties": {
        "name": {
          "type": "string"
        },
        "description": {
          "type": "string"
        },
        "pillar_post_id": {
          "type": "integer",
          "description": "Post ID of the pillar (canonical) page for this topic."
        },
        "supporting_post_ids": {
          "type": "array",
          "items": {
            "type": "integer"
          },
          "description": "Post IDs of supporting pages."
        },
        "summary": {
          "type": "string",
          "description": "One-line description for the inbox."
        },
        "reasoning": {
          "type": "string",
          "description": "Why this grouping. Shown to the human reviewer."
        },
        "success_metrics": {
          "type": "object",
          "description": "Stated intent for outcome scoring on the cluster (e.g. expected pillar query lift after consolidation).",
          "properties": {
            "target_query": {
              "type": "string"
            },
            "target_position": {
              "type": "number"
            },
            "target_ctr": {
              "type": "number"
            },
            "eval_window_days": {
              "type": "integer"
            },
            "hypothesis": {
              "type": "string"
            }
          }
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, return the would-be-cluster preview without queueing."
        }
      },
      "required": [
        "name"
      ]
    },
    "description": ""
  },
  {
    "name": "propose_cluster_assignment",
    "inputSchema": {
      "type": "object",
      "properties": {
        "cluster_id": {
          "type": "integer"
        },
        "post_id": {
          "type": "integer"
        },
        "role": {
          "type": "string",
          "description": "\"pillar\" or \"supporting\" (default supporting)."
        },
        "summary": {
          "type": "string"
        },
        "reasoning": {
          "type": "string"
        },
        "success_metrics": {
          "type": "object",
          "description": "Stated intent for outcome scoring.",
          "properties": {
            "target_query": {
              "type": "string"
            },
            "target_position": {
              "type": "number"
            },
            "target_ctr": {
              "type": "number"
            },
            "eval_window_days": {
              "type": "integer"
            },
            "hypothesis": {
              "type": "string"
            }
          }
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, return the would-be-assignment preview without queueing."
        }
      },
      "required": [
        "cluster_id",
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "bulk_propose_cluster_assignments",
    "inputSchema": {
      "type": "object",
      "properties": {
        "dry_run": {
          "type": "boolean",
          "description": "When true (default), returns candidate matches without queueing anything. Set false to actually queue propose_cluster_assignment for each match meeting the threshold."
        },
        "min_score": {
          "type": "number",
          "description": "Token-containment threshold 0..1. Default 0.4 (40% of post tokens must appear in cluster signature). Lower for more aggressive matching, higher for stricter."
        },
        "limit": {
          "type": "integer",
          "description": "Max unclustered posts to evaluate. Default 200."
        },
        "max_queue": {
          "type": "integer",
          "description": "Max pending changes to queue in one call. Default 20 — keeps the inbox reviewable."
        },
        "post_types": {
          "type": "array",
          "description": "Post types to scan. Default [\"post\",\"page\"].",
          "items": {
            "type": "string"
          }
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "gsc_cannibalization",
    "inputSchema": {
      "type": "object",
      "properties": {
        "days": {
          "type": "integer",
          "description": "Window in days (1..90). Default 28."
        },
        "min_impressions": {
          "type": "integer",
          "description": "Minimum impressions per page to count. Default 25."
        },
        "max_position": {
          "type": "integer",
          "description": "Pages must rank within this position to count as competing. Default 30."
        },
        "limit": {
          "type": "integer",
          "description": "Max conflicts to return. Default 50."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "audit_post_images",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "refresh_queue",
    "inputSchema": {
      "type": "object",
      "properties": {
        "days": {
          "type": "integer",
          "description": "Trends window in days. Default 28."
        },
        "min_click_drop": {
          "type": "integer",
          "description": "Minimum click decline to include. Default 5."
        },
        "min_age_days": {
          "type": "integer",
          "description": "Skip pages modified in the last N days. Default 90."
        },
        "limit": {
          "type": "integer",
          "description": "Max queue size. Default 25."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "click_depth_audit",
    "inputSchema": {
      "type": "object",
      "properties": {
        "max_depth_warn": {
          "type": "integer",
          "description": "Depth at which pages are flagged as buried. Default 4."
        },
        "buried_only": {
          "type": "boolean",
          "description": "If true, only return buried pages."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "post_dossier",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "analyze_post_structure",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "competitor_brief",
    "inputSchema": {
      "type": "object",
      "properties": {
        "keyword": {
          "type": "string",
          "description": "Target search query."
        },
        "days": {
          "type": "integer",
          "description": "GSC window in days. Default 28."
        }
      },
      "required": [
        "keyword"
      ]
    },
    "description": ""
  },
  {
    "name": "weekly_priorities",
    "inputSchema": {
      "type": "object",
      "properties": {
        "force": {
          "type": "boolean",
          "description": "Bypass cache and recompute. Default false."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "brief_for_keyword",
    "inputSchema": {
      "type": "object",
      "properties": {
        "keyword": {
          "type": "string",
          "description": "Target search query for the new post."
        },
        "intent_hint": {
          "type": "string",
          "description": "Optional override: informational | commercial | transactional | navigational | local."
        },
        "days": {
          "type": "integer",
          "description": "GSC window in days. Default 28."
        }
      },
      "required": [
        "keyword"
      ]
    },
    "description": ""
  },
  {
    "name": "prepare_rewrite_brief",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post ID being rewritten."
        },
        "keyword": {
          "type": "string",
          "description": "Optional. Override the primary keyword (defaults to the highest-impression GSC query for the post)."
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "propose_rewrite_outline",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post ID being rewritten."
        },
        "outline": {
          "type": "array",
          "items": {
            "type": "object",
            "properties": {
              "action": {
                "type": "string",
                "description": "One of: keep, cut, merge, add, rewrite. Keep = leave as-is. Cut = delete the section. Merge = fold into another section. Add = brand-new H2. Rewrite = same heading, new content."
              },
              "h2": {
                "type": "string",
                "description": "The H2 heading text (existing or proposed)."
              },
              "notes": {
                "type": "string",
                "description": "Optional one-line note explaining the action (e.g. \"fold into Treatment Paths\")."
              }
            }
          },
          "description": "Ordered list of outline rows representing the proposed H2 structure of the rewritten post."
        },
        "rationale": {
          "type": "string",
          "description": "One-sentence editorial summary: what the rewrite is changing and why."
        },
        "summary": {
          "type": "string",
          "description": "Optional one-line description for the inbox. Auto-generated from the action counts if omitted."
        },
        "reasoning": {
          "type": "string",
          "description": "Why this plan. Shown to the reviewer alongside the outline."
        }
      },
      "required": [
        "post_id",
        "outline"
      ]
    },
    "description": ""
  },
  {
    "name": "verify_change",
    "inputSchema": {
      "type": "object",
      "properties": {
        "pending_id": {
          "type": "integer",
          "description": "The pending_id returned by a draft_* or propose_* tool."
        }
      },
      "required": [
        "pending_id"
      ]
    },
    "description": ""
  },
  {
    "name": "cluster_gsc",
    "inputSchema": {
      "type": "object",
      "properties": {
        "cluster_id": {
          "type": "integer",
          "description": "Cluster ID from list_topic_clusters."
        },
        "days": {
          "type": "integer",
          "description": "GSC window in days. Default 28, range 7..90."
        }
      },
      "required": [
        "cluster_id"
      ]
    },
    "description": ""
  },
  {
    "name": "accessibility_audit",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "canonical_audit",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "hreflang_audit",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID. Audits the full translation cluster this post belongs to."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "eeat_coverage_audit",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": "Legacy heuristic check of observed byline, schema and citation proxies. Its pass/fail and density thresholds are plugin-defined, not Google requirements or proof of expertise, truth or ranking impact. Inspect actual claims and editorial responsibility. Never invent credentials, provider consent, bylines or Person markup to satisfy it; use verified_page_audit and appropriate subject review."
  },
  {
    "name": "schema_parity_check",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "self_review_detection",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "helpful_content_score",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        },
        "refresh": {
          "type": "boolean",
          "description": "Bypass cache and recompute. Default false."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "site_quality_score",
    "inputSchema": {
      "type": "object",
      "properties": {
        "refresh_stale": {
          "type": "boolean",
          "description": "Re-score posts whose cached score is older than freshness_days. Caps at rescore_cap to keep one call cheap. Default false."
        },
        "freshness_days": {
          "type": "integer",
          "description": "Cache freshness in days. Older entries are stale. Default 14, cap 90."
        },
        "bottom_n": {
          "type": "integer",
          "description": "Number of worst-scoring posts to enrich and return. Default 10, cap 100."
        },
        "rescore_cap": {
          "type": "integer",
          "description": "Max posts to re-score in one call when refresh_stale=true. Default 10, cap 50."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "external_originality_check",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        },
        "keyword": {
          "type": "string",
          "description": "Target keyword. Defaults to the post's top-impression GSC query, then to the post title."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "keyword_research",
    "inputSchema": {
      "type": "object",
      "properties": {
        "seed": {
          "type": "string",
          "description": "Seed keyword to research around."
        },
        "days": {
          "type": "integer",
          "description": "GSC window in days. Default 28."
        }
      },
      "required": [
        "seed"
      ]
    },
    "description": ""
  },
  {
    "name": "generate_schema",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "propose_schema",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        },
        "reasoning": {
          "type": "string",
          "description": "Why this schema. Shown to reviewer."
        },
        "include_breadcrumb": {
          "type": "boolean",
          "description": "Default false. Force a BreadcrumbList node even when the active SEO plugin (Yoast / Rank Math) already emits one site-wide."
        },
        "success_metrics": {
          "type": "object",
          "description": "Stated intent (e.g. expect FAQ rich result for target_query).",
          "properties": {
            "target_query": {
              "type": "string"
            },
            "target_position": {
              "type": "number"
            },
            "target_ctr": {
              "type": "number"
            },
            "eval_window_days": {
              "type": "integer"
            },
            "hypothesis": {
              "type": "string"
            }
          }
        },
        "dry_run": {
          "type": "boolean",
          "description": "When true, generate + lint the schema preview without queueing."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "gsc_warehouse_sync",
    "inputSchema": {
      "type": "object",
      "properties": {
        "max_dates": {
          "type": "integer",
          "description": "Date budget for this call. Default 30, cap 120 (each date = 1+ HTTP round trips)."
        },
        "refresh_days": {
          "type": "integer",
          "description": "Trailing dates always re-pulled to catch late-arriving GSC data. Default 7, cap 30."
        },
        "backfill": {
          "type": "boolean",
          "description": "Walk backward filling missing history toward the 16-month floor. Default true."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "gsc_warehouse_query",
    "inputSchema": {
      "type": "object",
      "properties": {
        "sql": {
          "type": "string",
          "description": "One SELECT or WITH statement. No writes — the connection is opened read-only."
        },
        "params": {
          "type": "array",
          "description": "Optional positional values for ? placeholders."
        },
        "max_rows": {
          "type": "integer",
          "description": "Row cap. Default 200, max 5000."
        }
      },
      "required": [
        "sql"
      ]
    },
    "description": ""
  },
  {
    "name": "outcome_report",
    "inputSchema": {
      "type": "object",
      "properties": {
        "window": {
          "type": "integer",
          "description": "Days each side of the apply date. Default 28, range 7-56."
        },
        "limit": {
          "type": "integer",
          "description": "How many recent applied edits to evaluate. Default 20, max 100."
        },
        "min_pre_clicks": {
          "type": "integer",
          "description": "Below this many clicks in BOTH windows the verdict is low_data. Default 20."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "verified_page_audit",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "minimum": 1
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "page_facts",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Post ID."
        },
        "refresh": {
          "type": "boolean",
          "description": "Force a fresh capture even if the record is not stale."
        }
      },
      "required": [
        "post_id"
      ]
    },
    "description": ""
  },
  {
    "name": "keyword_targets",
    "inputSchema": {
      "type": "object",
      "properties": {
        "action": {
          "type": "string",
          "description": "report (default) | set | remove | list"
        },
        "query": {
          "type": "string",
          "description": "The search query to win (set/remove)."
        },
        "owner_page": {
          "type": "string",
          "description": "Full URL of the page that should own this query (set)."
        },
        "target_position": {
          "type": "number",
          "description": "Position at or above which the target counts as won. Default 3."
        },
        "notes": {
          "type": "string",
          "description": "Optional operator note (why this query matters, competitor names, constraints)."
        },
        "days": {
          "type": "integer",
          "description": "Measurement window for report. Default 28, range 7-90."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "wcag_sweep",
    "inputSchema": {
      "type": "object",
      "properties": {
        "action": {
          "type": "string",
          "description": "run (default) | report (summarise the stored last run, no browser)."
        },
        "urls": {
          "type": "array",
          "items": {
            "type": "string"
          },
          "description": "Explicit URLs to audit instead of the sitemap."
        },
        "max_pages": {
          "type": "integer",
          "description": "Sitemap cap, default 40 (homepage first, then alphabetical so slices are stable). 1-1000."
        },
        "keyboard": {
          "type": "boolean",
          "description": "Run the keyboard/focus/form/popup checks (default true; adds ~6s a page)."
        },
        "concurrency": {
          "type": "integer",
          "description": "Parallel pages, 1-8, default 4."
        }
      },
      "required": []
    },
    "description": " Form checks observe markup without clicking or submitting. Submission and error announcements remain untested, not passed or failed."
  },
  {
    "name": "site_status",
    "inputSchema": {
      "type": "object",
      "properties": {
        "days": {
          "type": "integer",
          "description": "Window length in days, compared against the equal window before it. Default 28, range 7-90."
        },
        "max_findings": {
          "type": "integer",
          "description": "Cap on returned \"lacking\" findings, severity-ranked. Default 8, range 3-20."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "commodity_audit",
    "inputSchema": {
      "type": "object",
      "properties": {
        "days": {
          "type": "integer",
          "description": "Warehouse window. Default 28, range 7-90."
        },
        "limit": {
          "type": "integer",
          "description": "Top pages by impressions to classify. Default 25, max 50."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "aeo_snapshot",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "propose_revert",
    "inputSchema": {
      "type": "object",
      "properties": {
        "edit_id": {
          "type": "integer",
          "description": "The applied edit to revert (from outcome_report or list_recent_edits)."
        },
        "force": {
          "type": "boolean",
          "description": "Queue even though newer applied edits on the same post would also be undone. Default false."
        },
        "reasoning": {
          "type": "string",
          "description": "Why — cite the outcome verdict and did_delta_pct."
        }
      },
      "required": [
        "edit_id"
      ]
    },
    "description": ""
  },
  {
    "name": "lead_events",
    "inputSchema": {
      "type": "object",
      "properties": {
        "days": {
          "type": "integer",
          "description": "Lookback window. Default 90, max 400."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "operator_kit",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "operator_kit_push_skills",
    "inputSchema": {
      "type": "object",
      "properties": {
        "global": {
          "type": "string",
          "description": "Full text of the global design-system skill."
        },
        "site": {
          "type": "string",
          "description": "Full text of this site's brand/design skill."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "operator_brain_status",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "operator_brain_push",
    "inputSchema": {
      "type": "object",
      "properties": {
        "paths": {
          "type": "array",
          "minItems": 1,
          "maxItems": 50,
          "items": {
            "type": "string",
            "minLength": 1,
            "maxLength": 240
          }
        }
      }
    },
    "description": "Sync local operator knowledge to this site. Prefer paths: an explicit list of 1-50 collected paths such as memory/feedback_erofwhiterock_current_evidence_policy.md. Selected sync merges only these files, never deletes remote files, and verifies selected hashes; it does not claim full-brain sync. Missing/empty paths are rejected when selected mode is requested. OMITTING paths uses legacy full-brain sync, including deletion of remote files absent locally: only do that when the whole reviewed dataset belongs on this site. Never upload unrelated clients or secrets."
  },
  {
    "name": "operator_brain_pull",
    "inputSchema": {
      "type": "object",
      "properties": {
        "what": {
          "type": "string",
          "enum": [
            "all",
            "memory",
            "skills",
            "project",
            "bridge"
          ],
          "description": "Default all."
        },
        "mode": {
          "type": "string",
          "enum": [
            "missing_only",
            "replace"
          ],
          "description": "Default missing_only."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "widget_schema",
    "inputSchema": {
      "type": "object",
      "properties": {
        "widget_type": {
          "type": "string",
          "description": "Exact type to inspect, or omit when post_id and widget_id identify a saved element."
        },
        "section": {
          "type": "string",
          "description": "Optional control-section filter (full schemas run ~60KB; a section is a few hundred bytes). Section names are in the unfiltered response's `sections` field."
        },
        "post_id": {
          "type": "integer",
          "description": "With widget_id: return stored vs effective values + applies flags for that saved element (must be of widget_type)."
        },
        "widget_id": {
          "type": "string",
          "description": "Elementor element id on post_id (widget OR container)."
        }
      },
      "required": []
    },
    "description": "Inspect the current Elementor control registry, accepted values and defaults. With post_id plus widget_id, resolve the actual saved element type automatically and include stored/effective settings; widget_type is optional in this mode. With no target or type, list available widget types. Supply both target IDs; verify rendered effects separately."
  },
  {
    "name": "attention_spec",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "description": "Optional: resolve the archetype for this post."
        },
        "archetype": {
          "type": "string",
          "description": "Optional override: emergency_transactional | consideration_conversion | service_local | informational_guide."
        }
      },
      "required": []
    },
    "description": ""
  },
  {
    "name": "attention_audit",
    "inputSchema": {
      "type": "object",
      "properties": {
        "id": {
          "type": "integer",
          "description": "Post ID."
        },
        "archetype": {
          "type": "string",
          "description": "Optional archetype override; default auto-resolves from page intent + business mode."
        }
      },
      "required": [
        "id"
      ]
    },
    "description": ""
  },
  {
    "name": "gsc_warehouse_status",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "description": ""
  },
  {
    "name": "get_content_scope",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    },
    "annotations": {
      "readOnlyHint": true
    },
    "description": ""
  },
  {
    "name": "plan_blog_content",
    "inputSchema": {
      "type": "object",
      "properties": {
        "service_post_ids": {
          "type": "array",
          "items": {
            "type": "integer",
            "minimum": 1
          },
          "maxItems": 40
        },
        "topics": {
          "type": "array",
          "items": {
            "type": "string",
            "maxLength": 220
          },
          "maxItems": 40
        },
        "limit": {
          "type": "integer",
          "minimum": 1,
          "maximum": 30
        },
        "scan_limit": {
          "type": "integer",
          "minimum": 10,
          "maximum": 500
        },
        "offset": {
          "type": "integer",
          "minimum": 0
        },
        "days": {
          "type": "integer",
          "minimum": 7,
          "maximum": 90
        },
        "candidate_offset": {
          "type": "integer",
          "minimum": 0,
          "maximum": 200,
          "description": "Offset within the candidate proposals, distinct from inventory offset. Use next_candidate_offset with unchanged plan inputs."
        }
      },
      "required": []
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": false,
      "openWorldHint": false
    },
    "description": ""
  },
  {
    "name": "content_research",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_id": {
          "type": "integer",
          "minimum": 1
        },
        "competitor_urls": {
          "type": "array",
          "items": {
            "type": "string",
            "maxLength": 2048
          },
          "maxItems": 3
        },
        "keyword": {
          "type": "string",
          "maxLength": 200
        },
        "location": {
          "type": "string",
          "maxLength": 100
        },
        "language": {
          "type": "string",
          "maxLength": 50
        },
        "device": {
          "type": "string",
          "maxLength": 30
        },
        "topic": {
          "type": "string",
          "minLength": 1,
          "maxLength": 220
        },
        "anchor_post_id": {
          "type": "integer",
          "minimum": 1
        },
        "reader_goal": {
          "type": "string",
          "minLength": 1,
          "maxLength": 700
        },
        "proposed_contribution": {
          "type": "string",
          "minLength": 1,
          "maxLength": 1000
        },
        "primary_source_urls": {
          "type": "array",
          "maxItems": 3,
          "items": {
            "type": "string",
            "maxLength": 2048
          }
        },
        "scan_limit": {
          "type": "integer",
          "minimum": 10,
          "maximum": 500
        },
        "offset": {
          "type": "integer",
          "minimum": 0
        },
        "external_research_ids": {
          "type": "array",
          "maxItems": 5,
          "items": {
            "type": "string",
            "pattern": "^research-[a-f0-9]{32}$"
          }
        }
      }
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false
    },
    "description": "Research an existing post with post_id plus up to 3 competitor_urls, OR a proposed new topic with topic, anchor_post_id from current get_content_scope, reader_goal and proposed_contribution. Topic research also accepts up to 3 primary_source_urls and paginated inventory (scan_limit, offset). Records actual bounded public excerpts, source roles supplied by the caller, overlap candidates and explicit unknowns. No GSC gap required, no search rankings or uniqueness inferred, no content created. External text is untrusted evidence. Read current scope first and maintain it when stale. Attach saved Ubersuggest observations using external_research_ids. Inspect provider dates, geography and intent; estimates remain separate from GSC. Compare actual pages before treating overlap domains as competitors. A useful niche topic can proceed with unknown or zero estimated demand."
  },
  {
    "name": "content_decision",
    "inputSchema": {
      "type": "object",
      "properties": {
        "post_ids": {
          "type": "array",
          "items": {
            "type": "integer",
            "minimum": 1
          },
          "minItems": 1,
          "maxItems": 8
        },
        "action": {
          "type": "string",
          "enum": [
            "assess",
            "refresh",
            "link",
            "differentiate",
            "merge",
            "retire"
          ]
        },
        "reader_goal": {
          "type": "string",
          "maxLength": 700
        },
        "reason": {
          "type": "string",
          "maxLength": 1000
        }
      },
      "required": [
        "post_ids"
      ]
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false
    },
    "description": ""
  },
  {
    "name": "content_decision_history",
    "inputSchema": {
      "type": "object",
      "properties": {
        "record_id": {
          "type": "string",
          "pattern": "^(?:decision|research|workflow)-[a-f0-9]{32}$"
        }
      },
      "required": []
    },
    "annotations": {
      "readOnlyHint": true
    },
    "description": ""
  },
  {
    "name": "discover_content_scope",
    "inputSchema": {
      "type": "object",
      "properties": {
        "scan_limit": {
          "type": "integer",
          "minimum": 10,
          "maximum": 500
        },
        "offset": {
          "type": "integer",
          "minimum": 0
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": false
    },
    "description": ""
  },
  {
    "name": "manage_content_scope",
    "inputSchema": {
      "type": "object",
      "properties": {
        "expected_revision": {
          "type": "string",
          "pattern": "^scope-[a-f0-9]{32}$"
        },
        "reason": {
          "type": "string",
          "minLength": 1,
          "maxLength": 1000
        },
        "source_evidence": {
          "type": "array",
          "minItems": 1,
          "maxItems": 40,
          "items": {
            "type": "object",
            "properties": {
              "post_id": {
                "type": "integer",
                "minimum": 1
              },
              "evidence_id": {
                "type": "string",
                "pattern": "^post-[0-9]+-[a-f0-9]{24}$"
              },
              "reason": {
                "type": "string",
                "minLength": 1,
                "maxLength": 400
              }
            },
            "required": [
              "post_id",
              "evidence_id",
              "reason"
            ],
            "additionalProperties": false
          }
        },
        "audience": {
          "type": "string",
          "maxLength": 500
        },
        "excluded_topics": {
          "type": "array",
          "maxItems": 50,
          "items": {
            "type": "string",
            "maxLength": 220
          }
        },
        "exclusion_change_reason": {
          "type": "string",
          "maxLength": 700
        },
        "reader_questions": {
          "type": "array",
          "maxItems": 50,
          "items": {
            "type": "object",
            "properties": {
              "post_id": {
                "type": "integer",
                "minimum": 1
              },
              "question": {
                "type": "string",
                "minLength": 1,
                "maxLength": 220
              }
            },
            "required": [
              "post_id",
              "question"
            ],
            "additionalProperties": false
          }
        },
        "context_hash": {
          "type": "string",
          "pattern": "^[a-f0-9]{64}$",
          "description": "context.context_hash returned by scope/discovery; protects concurrent changes to site notes and identity."
        },
        "author_id": {
          "type": "integer",
          "minimum": 1
        },
        "expected_author_name": {
          "type": "string",
          "minLength": 1,
          "maxLength": 250
        }
      },
      "required": [
        "expected_revision",
        "reason",
        "source_evidence",
        "context_hash"
      ],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": false,
      "openWorldHint": false
    },
    "description": ""
  },
  {
    "name": "content_workflow",
    "inputSchema": {
      "type": "object",
      "properties": {
        "objective": {
          "type": "string",
          "enum": [
            "new_blog",
            "refresh",
            "site_review"
          ]
        },
        "post_ids": {
          "type": "array",
          "maxItems": 8,
          "items": {
            "type": "integer",
            "minimum": 1
          }
        },
        "limit": {
          "type": "integer",
          "minimum": 1,
          "maximum": 10
        }
      },
      "required": [],
      "additionalProperties": false
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": false,
      "openWorldHint": false
    },
    "description": ""
  },
  {
    "name": "inspect_plugin_capability",
    "inputSchema": {
      "type": "object",
      "required": [
        "slug"
      ],
      "additionalProperties": false,
      "properties": {
        "slug": {
          "type": "string",
          "description": "Exact installed plugin directory slug."
        },
        "option_name": {
          "type": "string"
        },
        "path": {
          "type": "string"
        },
        "proposed_value": {
          "description": "Optional value to validate without mutation."
        },
        "widget_type": {
          "type": "string"
        },
        "post_id": {
          "type": "integer",
          "minimum": 1
        },
        "element_id": {
          "type": "string"
        }
      }
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": false
    },
    "description": ""
  },
  {
    "name": "verify_content_workflow",
    "inputSchema": {
      "type": "object",
      "required": [
        "workflow_id"
      ],
      "additionalProperties": false,
      "properties": {
        "workflow_id": {
          "type": "string",
          "pattern": "^workflow-[a-f0-9]{32}$"
        },
        "post_id": {
          "type": "integer",
          "minimum": 1,
          "description": "Actual result ID from draft_create_post(workflow_id=...). Omit to inspect whether any result was supplied."
        }
      }
    },
    "annotations": {
      "readOnlyHint": true,
      "destructiveHint": false,
      "idempotentHint": true,
      "openWorldHint": false
    },
    "description": ""
  },
  {
    "name": "get_content_authors",
    "description": "Read existing public authors without login names or emails. Claude selects the established organization or other operator-authorized identity, saves author_id and expected_author_name through manage_content_scope, and passes author_id to new drafts. Do not invent clinicians, credentials or medical review.",
    "inputSchema": {
      "type": "object",
      "properties": {},
      "required": []
    }
  },
  {
    "name": "record_external_research",
    "description": "Record selected actual Ubersuggest MCP observations with tool, target, location, language, capture time and provider update dates. These are agent-transcribed provider estimates, not server-authenticated responses or GSC observations. Omit missing metrics; zero volume does not mean no opportunity. Do not sum keyword variants. Domain overlap does not establish local business competition. Attach returned research IDs through content_research.external_research_ids. Never send account details or credentials.",
    "inputSchema": {
      "type": "object",
      "properties": {
        "provider": {
          "type": "string",
          "enum": [
            "ubersuggest"
          ]
        },
        "tool_name": {
          "type": "string",
          "enum": [
            "domain_overview",
            "domain_keywords",
            "domain_top_pages",
            "competitors",
            "keyword_metrics",
            "keyword_overview",
            "keyword_suggestions",
            "serp_analysis",
            "content_ideas",
            "google_suggestions",
            "match_keywords",
            "page_keywords",
            "page_overview"
          ]
        },
        "captured_at_utc": {
          "type": "string",
          "maxLength": 30
        },
        "target": {
          "type": "string",
          "minLength": 1,
          "maxLength": 500
        },
        "location": {
          "type": "string",
          "minLength": 1,
          "maxLength": 150
        },
        "location_id": {
          "type": "integer",
          "minimum": 1
        },
        "language": {
          "type": "string",
          "minLength": 1,
          "maxLength": 50
        },
        "device": {
          "type": "string",
          "maxLength": 40,
          "default": "unspecified"
        },
        "observations": {
          "type": "array",
          "minItems": 1,
          "maxItems": 30,
          "items": {
            "type": "object",
            "additionalProperties": false,
            "required": [
              "subject"
            ],
            "properties": {
              "subject": {
                "type": "string",
                "minLength": 1,
                "maxLength": 300
              },
              "url": {
                "type": "string",
                "maxLength": 2048
              },
              "estimated_volume": {
                "type": "integer",
                "minimum": 0
              },
              "estimated_difficulty": {
                "type": "number",
                "minimum": 0,
                "maximum": 100
              },
              "position": {
                "type": "number",
                "minimum": 1
              },
              "provider_updated_at_utc": {
                "type": "string",
                "maxLength": 30
              },
              "note": {
                "type": "string",
                "maxLength": 500
              }
            }
          }
        }
      },
      "required": [
        "provider",
        "tool_name",
        "captured_at_utc",
        "target",
        "location",
        "location_id",
        "language",
        "observations"
      ],
      "additionalProperties": false
    }
  },
  {
    "name": "refresh_publish_proposal",
    "inputSchema": {
      "type": "object",
      "required": [
        "pending_id",
        "workflow_id",
        "reason"
      ],
      "additionalProperties": false,
      "properties": {
        "pending_id": {
          "type": "integer",
          "minimum": 1
        },
        "workflow_id": {
          "type": "string",
          "pattern": "^workflow-[a-f0-9]{32}$"
        },
        "reason": {
          "type": "string",
          "minLength": 1,
          "maxLength": 1000
        },
        "dry_run": {
          "type": "boolean",
          "default": false
        }
      }
    },
    "annotations": {
      "readOnlyHint": false,
      "destructiveHint": false,
      "idempotentHint": false,
      "openWorldHint": false
    },
    "description": ""
  }
]
CC_SHARED_JSON
);
$cc_convert = static function ( $value ) use ( &$cc_convert ) {
    if ( is_object( $value ) ) {
        $vars = get_object_vars( $value );
        if ( empty( $vars ) ) { return new stdClass(); }
        return array_map( $cc_convert, $vars );
    }
    return is_array( $value ) ? array_map( $cc_convert, $value ) : $value;
};
return $cc_convert( $cc_data );
