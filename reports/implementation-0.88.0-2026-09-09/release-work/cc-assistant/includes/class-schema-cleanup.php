<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema policy enforcement at render time. Strips two classes of structured-
 * data policy violations that Rank Math (or any other JSON-LD source) may
 * emit:
 *
 *   1. aggregateRating / review on the site's own Organization / LocalBusiness
 *      / MedicalClinic family entity. Google explicitly forbids self-reviews —
 *      the page becomes ineligible for review rich results AND can trip a
 *      manual action. ALWAYS stripped, every URL.
 *
 *   2. Cross-page service entities on non-homepage URLs. The site-wide
 *      LocalBusiness graph (with hasOfferCatalog references, areaServed
 *      lists, full credential lists) is appropriate on the homepage / About
 *      page, but on individual service pages it violates the structured-data
 *      parity policy: schema must describe content visible on THIS page.
 *
 * Hooks rank_math/json_ld at priority 999 so it runs after Rank Math built
 * its full graph but before output. Transformation is idempotent. The option
 * `cc_assistant_schema_cleanup_enabled` (default true) gates the whole pass.
 *
 * Performance: pure array-walk over the already-built JSON-LD. No DB queries,
 * no HTTP, no template rendering. Negligible per-request cost.
 */
class CC_Assistant_Schema_Cleanup {

	/**
	 * @type values that represent a business entity. aggregateRating / review
	 * are stripped from these unconditionally, plus heavy properties on
	 * non-homepage URLs.
	 */
	private static $business_types = array(
		'Organization', 'LocalBusiness', 'MedicalClinic', 'MedicalBusiness',
		'Hospital', 'Dentist', 'HealthAndBeautyBusiness', 'BeautySalon',
		'DaySpa', 'Pharmacy', 'Physician', 'Corporation',
	);

	/**
	 * Entry point. Walks the JSON-LD array Rank Math built, applies the
	 * cleanup rules, returns the modified array. Returns input unchanged if
	 * cleanup is disabled via the option or input is empty.
	 */
	public static function filter_jsonld( $data ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return $data;
		}
		if ( false === (bool) get_option( 'cc_assistant_schema_cleanup_enabled', true ) ) {
			return $data;
		}

		$is_homepage = is_front_page() || is_home();
		$current_url = self::current_canonical_url();
		$stripped    = array();

		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			// @graph-wrapped form
			$data['@graph'] = self::clean_graph( $data['@graph'], $is_homepage, $current_url, $stripped );
		} elseif ( self::is_entity_array( $data ) ) {
			// Single top-level entity
			$cleaned = self::clean_entity( $data, $is_homepage, $current_url, $stripped );
			$data    = ( null === $cleaned ) ? array() : $cleaned;
		} else {
			// Numeric-keyed array of entities (Rank Math's typical shape)
			$cleaned_set = array();
			foreach ( $data as $key => $entity ) {
				if ( is_array( $entity ) && self::is_entity_array( $entity ) ) {
					$entity = self::clean_entity( $entity, $is_homepage, $current_url, $stripped );
					if ( null === $entity ) {
						$stripped[] = 'dropped_top_level_entity';
						continue;
					}
				}
				$cleaned_set[ $key ] = $entity;
			}
			$data = $cleaned_set;
		}

		if ( ! empty( $stripped ) ) {
			self::log_cleanup_throttled( $stripped, $current_url );
		}

		return $data;
	}

	/**
	 * Yoast SEO fallback. Yoast's wpseo_schema_graph filter passes a flat
	 * array of entities (the @graph contents). Apply the same rules.
	 */
	public static function filter_yoast_graph( $graph ) {
		if ( empty( $graph ) || ! is_array( $graph ) ) {
			return $graph;
		}
		if ( false === (bool) get_option( 'cc_assistant_schema_cleanup_enabled', true ) ) {
			return $graph;
		}
		$is_homepage = is_front_page() || is_home();
		$current_url = self::current_canonical_url();
		$stripped    = array();
		$cleaned     = self::clean_graph( $graph, $is_homepage, $current_url, $stripped );
		if ( ! empty( $stripped ) ) {
			self::log_cleanup_throttled( $stripped, $current_url );
		}
		return $cleaned;
	}

	private static function clean_graph( $graph, $is_homepage, $current_url, &$stripped ) {
		$cleaned = array();
		foreach ( $graph as $entity ) {
			if ( ! is_array( $entity ) ) {
				continue;
			}
			$entity = self::clean_entity( $entity, $is_homepage, $current_url, $stripped );
			if ( null !== $entity ) {
				$cleaned[] = $entity;
			}
		}
		return $cleaned;
	}

	/**
	 * Returns the cleaned entity OR null if the entity itself should be
	 * dropped from its parent container (cross-page service entity on a
	 * non-matching page).
	 */
	private static function clean_entity( $entity, $is_homepage, $current_url, &$stripped ) {
		if ( ! is_array( $entity ) ) {
			return $entity;
		}
		$type      = isset( $entity['@type'] ) ? $entity['@type'] : null;
		$type_arr  = is_array( $type ) ? $type : ( is_string( $type ) ? array( $type ) : array() );
		$entity_id = isset( $entity['@id'] ) ? (string) $entity['@id'] : '';

		// Cross-page service entity? Drop entirely.
		if ( ! $is_homepage && '' !== $entity_id && self::is_cross_page_service_id( $entity_id, $current_url ) ) {
			$stripped[] = 'cross-service @id=' . $entity_id;
			return null;
		}

		$is_business = false;
		foreach ( $type_arr as $t ) {
			if ( in_array( $t, self::$business_types, true ) ) {
				$is_business = true;
				break;
			}
		}

		// ALWAYS strip aggregateRating + review on business entities.
		if ( $is_business ) {
			if ( isset( $entity['aggregateRating'] ) ) {
				unset( $entity['aggregateRating'] );
				$stripped[] = 'aggregateRating from ' . implode( ',', $type_arr );
			}
			if ( isset( $entity['review'] ) ) {
				unset( $entity['review'] );
				$stripped[] = 'review from ' . implode( ',', $type_arr );
			}
		}

		// Non-homepage trimming: areaServed + Person.hasCredential are
		// homepage-context properties; including them on individual service
		// pages violates the schema-DOM parity policy.
		if ( ! $is_homepage ) {
			if ( $is_business && isset( $entity['areaServed'] ) ) {
				unset( $entity['areaServed'] );
				$stripped[] = 'areaServed from ' . implode( ',', $type_arr ) . ' (non-homepage)';
			}
			if ( in_array( 'Person', $type_arr, true ) && isset( $entity['hasCredential'] ) ) {
				unset( $entity['hasCredential'] );
				$stripped[] = 'hasCredential from Person (non-homepage)';
			}
		}

		// Recurse into nested values. Nested entities use the same rules
		// (self-review-strip applies to a Person.affiliation = Organization
		// just as it would top-level, etc.).
		foreach ( $entity as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}
			if ( self::is_entity_array( $value ) ) {
				$nested = self::clean_entity( $value, $is_homepage, $current_url, $stripped );
				if ( null === $nested ) {
					unset( $entity[ $key ] );
				} else {
					$entity[ $key ] = $nested;
				}
			} elseif ( self::is_sequential_array( $value ) ) {
				// Sequential list of entities or scalars
				$list = array();
				foreach ( $value as $sub_value ) {
					if ( is_array( $sub_value ) && self::is_entity_array( $sub_value ) ) {
						$nested = self::clean_entity( $sub_value, $is_homepage, $current_url, $stripped );
						if ( null !== $nested ) {
							$list[] = $nested;
						}
					} else {
						$list[] = $sub_value;
					}
				}
				$entity[ $key ] = $list;
			} else {
				// Associative array WITHOUT @type — preserve keys. Anything
				// else (numeric-reindex) destroys object-shape properties like
				// ListItem.item = {"@id":..., "name":...}, address = {...},
				// geo = {"latitude":..., "longitude":...}. Recurse into values
				// so deeper entities still get cleaned.
				$assoc = array();
				foreach ( $value as $sub_key => $sub_value ) {
					if ( is_array( $sub_value ) && self::is_entity_array( $sub_value ) ) {
						$nested = self::clean_entity( $sub_value, $is_homepage, $current_url, $stripped );
						if ( null !== $nested ) {
							$assoc[ $sub_key ] = $nested;
						}
					} else {
						$assoc[ $sub_key ] = $sub_value;
					}
				}
				$entity[ $key ] = $assoc;
			}
		}

		return $entity;
	}

	private static function is_entity_array( $arr ) {
		return is_array( $arr ) && isset( $arr['@type'] );
	}

	/**
	 * True for numerically-keyed lists with consecutive 0..N-1 keys (PHP "list"
	 * shape). False for associative arrays whose keys are non-numeric or
	 * non-consecutive — those represent JSON objects that must NOT be
	 * numerically reindexed during the cleanup pass.
	 */
	private static function is_sequential_array( $arr ) {
		if ( ! is_array( $arr ) ) {
			return false;
		}
		if ( empty( $arr ) ) {
			return true;
		}
		return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
	}

	/**
	 * "Cross-page service entity" = an @id that is a full URL pointing to a
	 * different page on this host, with a service/procedure/offer fragment.
	 * Conservative: only drop entities whose @id fragment explicitly marks
	 * them as a service entity (#service, #procedure, #offer, #therapy).
	 *
	 * Same-page @ids (current URL match), fragment-only @ids (#clinic,
	 * #website, #breadcrumb), and cross-domain @ids are all KEPT.
	 */
	private static function is_cross_page_service_id( $entity_id, $current_url ) {
		// Fragment-only or relative @id: keep
		if ( ! preg_match( '#^https?://#i', $entity_id ) ) {
			return false;
		}
		// Strip fragment to compare URL portions
		$id_url_only = preg_replace( '/#.*$/', '', $entity_id );
		$current_url_only = preg_replace( '/#.*$/', '', $current_url );
		// Same URL — KEEP regardless of fragment
		if ( rtrim( $id_url_only, '/' ) === rtrim( $current_url_only, '/' ) ) {
			return false;
		}
		// Different host — KEEP (legitimate external reference, e.g. credential issuer)
		$id_host      = parse_url( $entity_id, PHP_URL_HOST );
		$current_host = parse_url( $current_url, PHP_URL_HOST );
		if ( $id_host && $current_host && strtolower( (string) $id_host ) !== strtolower( (string) $current_host ) ) {
			return false;
		}
		// Same host, different path — drop ONLY if the fragment marks it as a
		// service entity. (Person/Organization refs use named fragments like
		// #practitioner-lori, which we keep.)
		if ( preg_match( '~#(service|procedure|offer|therapy)$~', $entity_id ) ) {
			return true;
		}
		return false;
	}

	private static function current_canonical_url() {
		if ( is_singular() ) {
			$pid = (int) get_queried_object_id();
			if ( $pid > 0 ) {
				$perm = (string) get_permalink( $pid );
				if ( ! empty( $perm ) ) {
					return $perm;
				}
			}
		}
		$req = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
		return home_url( $req );
	}

	/**
	 * Activity log entry, throttled to once per hour. The filter fires on
	 * every front-end page render — without throttling, the log would flood.
	 *
	 * Wrapped in try/catch defensively: a logger failure (missing $wpdb,
	 * mb_string disabled, etc.) must NEVER break the filter, because that
	 * would bubble up and break the page render.
	 */
	private static function log_cleanup_throttled( $stripped, $url ) {
		// Intentionally a NO-OP on the public render path (v0.37). This is
		// invoked from the SEO-plugin schema filters, which fire during wp_head
		// on anonymous page views. The previous implementation did a
		// get_transient + set_transient (an options-table write on sites without
		// a persistent object cache) AND an activity-log table INSERT — i.e. DB
		// writes on the front-end render path, which the plugin forbids (heavy /
		// DB work belongs in admin or cron). The cleanup itself is idempotent
		// and silent-success is acceptable. This also removes the prior bug
		// where the activity-log row was written blank because the arguments to
		// CC_Assistant_Activity_Log::record() were passed in the wrong order.
		unset( $stripped, $url );
	}
}
