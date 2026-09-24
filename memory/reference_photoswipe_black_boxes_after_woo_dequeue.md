---
name: reference_photoswipe_black_boxes_after_woo_dequeue
description: Black boxes below the footer on every page = WooCommerce PhotoSwipe dialog printed with its CSS/JS dequeued, NOT a footer bug
metadata:
  type: reference
---

Found on mammothmachinery.ca 2026-09-02. Operator reported "black boxes below the
footer" and asked for a **footer rebuild**. The footer was not the cause and a rebuild
would not have fixed it.

**What renders:** at the very end of `wp_footer`, after the Elementor footer template,
WooCommerce prints its product-gallery lightbox shell:

```
<div id="photoswipe-fullscreen-dialog" class="pswp" role="dialog" aria-hidden="true">
  .pswp__bg  .pswp__item x3  .pswp__counter
  .pswp__button--zoom / --fs / --share / --close     <- row of 4 black boxes
  .pswp__button--arrow--left / --arrow--right        <- 2 more below
```

That 4-then-2 button layout is exactly the shape of the black boxes on screen.

**Why they are visible:** the speed-sprint snippet dequeued every WooCommerce asset
site-wide. Verified on the live page: photoswipe **CSS none**, photoswipe **JS none**,
all Woo assets absent, and **no rule anywhere sets `.pswp{display:none}`**. Normally
PhotoSwipe's own stylesheet keeps `.pswp` hidden until opened, so removing the CSS while
leaving the markup makes the unstyled dialog paint in the page flow. `aria-hidden="true"`
does not hide it visually. Present on 4/4 pages tested, so it is site-wide.

**Fix, best first:**
1. Add `remove_action( 'wp_footer', 'woocommerce_photoswipe', 15 );` to the same snippet
   that dequeues Woo. Removes ~1.5KB of dead markup from every page. Operator action:
   the snippet is outside plugin scope.
2. Stopgap inside plugin scope: `.pswp{display:none!important}` via the Elementor Kit
   custom CSS. Masks it but leaves the markup.

**Lesson:** when you dequeue a plugin's assets, check what markup that plugin still
prints. Dequeuing CSS without removing the markup it styles is how invisible chrome
becomes visible garbage.

**The footer itself audited clean** (contradicting the older defect list in the
mammothmachinery-design skill, which is stale): 4 proper H4 column headings, logo has
alt text, ZERO empty links, no stray headings. Do not rebuild it.

Related: [[feedback_probe_discipline_positive_controls]], [[feedback_dom_is_ground_truth_not_parsers]].

## RESOLVED 2026-09-02, and the two lessons

Fixed by kit setting `custom_css` = `.pswp{display:none!important}` (pending #1184,
`allow_create:true` because the kit had never set that key). **It only appeared to fail
until the SiteGround cache was purged.**

**Lesson 1 - remove_action needs the EXACT priority.** The first attempt,
`remove_action('wp_footer','woocommerce_photoswipe',15)`, silently matched nothing: 15
was quoted from memory, not read off the install, and a wrong priority fails with no
error. The priority-agnostic form walks `$wp_filter['wp_footer']->callbacks` and removes
by callback name at whatever priority it is registered on. Never hand an operator a
`remove_action` priority you have not verified.

**Lesson 2 - `X-Proxy-Cache: MISS` on curl does NOT mean the operator sees what you see.**
I used a MISS header to tell the operator "this is a fresh render, not cache" while their
browser was still being served stale HTML/CSS. After ANY kit or CSS change on a
SiteGround site, purge before concluding anything, and say "purge, then look" rather than
declaring the fix failed.

Still outstanding but low value: the dead markup is still printed on every page (~1.5KB).
Removing the wp_footer action would clean it up; the CSS already fixes what anyone sees.
