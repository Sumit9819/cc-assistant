"""Builds the REVAMPED, ElementsKit-free header for ER of Irving (new Pro template).

Mirrors the Lufkin header shipped 2026-09-18 (same direction, Irving's own content): a slim red trust strip over a white
main bar, with the call button carrying the phone number itself.

  red strip 34px : Open 24/7 . No appointment needed . Get Directions | socials
  white bar 80px : logo | menu (3-column services panel) | red call button
  phone          : strip (24/7 . directions) then logo | red Call | burger

Colours, type and spacing follow the erofirving-design skill: #DA1212 primary,
#11468F secondary, #041562 text navy, Montserrat 700 for the menu and the button,
Roboto for utility text, 8px layout grid, 44px minimum tap targets.

SCHEMA NOTES (Elementor 4.2.3 + Pro 4.0.1, verified against widget_schema):
- Device-suffix keys (padding_mobile, width_mobile, hide_mobile, ...) are REFUSED by
  the queue; only ~26 controls per widget register device variants. Every responsive
  rule therefore lives in the single custom_css block on the root container.
- Elementor stacks container children below its tablet breakpoint: hold the row on
  "#id > .e-con-inner", not on the container itself.
- The collapsed nav-menu dropdown sits IN FLOW and inflates the header until it is
  positioned absolute with top/left/width all !important against a relative bar.
- background_hover_color does not exist on the button (button_background_hover_color
  does, gated by button_background_hover_background). Alignment values are
  start/center/end, not left/right.

Writes raw_data.json (the Elementor tree) next to this file.
"""
import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))

RED = '#DA1212'       # brand primary: urgency, the call button
NAVY = '#11468F'      # brand secondary: menu items
DARKNAVY = '#041562'  # text navy: dropdown items, button hover
WHITE = '#FFFFFF'
CARD = '#F4F4F4'

PHONE_TEL = 'tel:+19728933148'
PHONE_TXT = '(972) 893-3148'
CALL_ARIA = 'aria-label|Call ER of Irving now at (972) 893-3148'
DIRECTIONS = 'https://www.google.com/maps/place/ER+of+Irving+-+Emergency+Room'
# matches the address in the page schema and the Google listing; the old header
# said "501 N Brentwood Dr. Lufkin, TX 75904 USA", which matched neither
ADDRESS = '8200 N MacArthur Blvd Suite 110, Irving, TX 75063'


def px(top, right, bottom, left):
    return {'unit': 'px', 'top': str(top), 'right': str(right), 'bottom': str(bottom), 'left': str(left), 'isLinked': False}


def size(n, unit='px'):
    return {'unit': unit, 'size': n, 'sizes': []}


def gap(n):
    return {'unit': 'px', 'size': n, 'column': str(n), 'row': str(n), 'isLinked': True}


def link(url, attrs=''):
    return {'url': url, 'is_external': '', 'nofollow': '', 'custom_attributes': attrs}


def fa(name, lib='fa-solid'):
    return {'value': name, 'library': lib}


def widget(eid, wtype, settings):
    return {'id': eid, 'elType': 'widget', 'widgetType': wtype, 'settings': settings, 'elements': []}


def container(eid, settings, children):
    return {'id': eid, 'elType': 'container', 'settings': settings, 'elements': children}


# ------------------------------------------------------------- red trust strip
strip_text = {
    'view': 'inline',
    'icon_align': 'start',
    'space_between': size(24),
    'icon_size': size(14),
    'icon_color': WHITE,
    'text_color': WHITE,
    'icon_typography_typography': 'custom',
    'icon_typography_font_family': 'Roboto',
    'icon_typography_font_size': size(14),
    'icon_typography_font_weight': '600',
}

trust = widget('irvtrust', 'icon-list', dict(strip_text, **{
    '_element_id': 'irv-trust',
    # The number and the address are visible, crawlable text here, written exactly
    # as the page schema and the Google listing carry them. The schema block on
    # every page already states telephone +1-972-893-3148 and 501 N Brentwood Dr,
    # Lufkin, TX 75904, so this keeps the two in step sitewide.
    'icon_list': [
        {'_id': 'irvtr1', 'text': 'Open 24/7', 'selected_icon': fa('fas fa-clock'), 'link': link('')},
        {'_id': 'irvtr2', 'text': PHONE_TXT, 'selected_icon': fa('fas fa-phone-alt'),
         'link': link(PHONE_TEL, CALL_ARIA)},
        {'_id': 'irvtr3', 'text': ADDRESS, 'selected_icon': fa('fas fa-map-marker-alt'),
         'link': link(DIRECTIONS, 'aria-label|Get directions to ER of Lufkin, 501 N Brentwood Dr')},
        {'_id': 'irvtr4', 'text': 'Directions', 'selected_icon': fa('fas fa-map-marker-alt'),
         'link': link(DIRECTIONS, 'aria-label|Get directions to ER of Irving')},
    ],
}))

SOCIALS = [
    ('irvsoc1', 'fab fa-facebook-f', 'https://www.facebook.com/erofirving/'),
    ('irvsoc2', 'fab fa-twitter', 'https://x.com/ERofIrving'),
    ('irvsoc3', 'fab fa-linkedin-in', 'https://www.linkedin.com/company/er-of-irving-emergency-room'),
    ('irvsoc4', 'fab fa-instagram', 'https://www.instagram.com/erofirving/'),
    ('irvsoc5', 'fab fa-yelp', 'https://www.yelp.com/biz/er-of-irving-irving'),
]

social = widget('irvsocial', 'social-icons', {
    '_element_id': 'irv-social',
    'social_icon_list': [
        {'_id': sid, 'social_icon': fa(icon, 'fa-brands'),
         'link': {'url': url, 'is_external': 'true', 'nofollow': '', 'custom_attributes': ''}}
        for sid, icon, url in SOCIALS
    ],
    'shape': 'square',
    'align': 'right',
    'icon_color': 'custom',
    'icon_primary_color': 'rgba(0,0,0,0)',
    'icon_secondary_color': WHITE,
    'hover_primary_color': 'rgba(0,0,0,0)',
    'hover_secondary_color': 'rgba(255,255,255,0.7)',
    'icon_size': size(15),
    'icon_spacing': size(9),
})

strip = container('irvstrip', {
    '_element_id': 'irv-strip',
    'background_background': 'classic',
    'background_color': RED,
    'boxed_width': size(1200),
    'padding': px(6, 15, 6, 15),
    'flex_direction': 'row',
    'flex_align_items': 'center',
    'flex_justify_content': 'space-between',
    'flex_gap': gap(16),
}, [trust, social])

# ------------------------------------------------------------------ white bar
logo = widget('irvlogo', 'image', {
    '_element_id': 'irv-logo',
    'image': {'id': 2869, 'url': 'https://erofirving.com/wp-content/uploads/2025/03/ER-of-Irving-Official-Logo.png',
              'alt': 'ER of Irving - Logo', 'source': 'library', 'size': ''},
    'image_size': 'full',
    'width': size(240),
    'align': 'start',
    'link_to': 'custom',
    'link': link('https://erofirving.com/', 'aria-label|ER of Irving home'),
})

nav = widget('irvnav', 'nav-menu', {
    '_element_id': 'irv-nav',
    'menu': 'menu',
    'layout': 'horizontal',
    'align_items': 'center',
    'submenu_icon': fa('fas fa-chevron-down'),
    'dropdown': 'tablet',
    'full_width': 'yes',
    'toggle': 'burger',
    'toggle_align': 'right',
    'menu_typography_typography': 'custom',
    'menu_typography_font_family': 'Montserrat',
    'menu_typography_font_size': size(14),
    'menu_typography_font_weight': '700',
    'menu_typography_text_transform': 'uppercase',
    'menu_typography_line_height': size(21),
    'color_menu_item': NAVY,
    'color_menu_item_hover': RED,
    'color_menu_item_active': RED,
    'pointer': 'underline',
    'animation_line': 'fade',
    'pointer_width': size(2),
    'pointer_color_menu_item_hover': RED,
    'pointer_color_menu_item_active': RED,
    'padding_horizontal_menu_item': size(8),
    'padding_vertical_menu_item': size(10),
    'dropdown_typography_typography': 'custom',
    'dropdown_typography_font_family': 'Roboto',
    'dropdown_typography_font_size': size(15),
    'dropdown_typography_font_weight': '400',
    'color_dropdown_item': DARKNAVY,
    'background_color_dropdown_item': WHITE,
    'color_dropdown_item_hover': RED,
    'background_color_dropdown_item_hover': CARD,
    'color_dropdown_item_active': RED,
    'background_color_dropdown_item_active': CARD,
    'padding_horizontal_dropdown_item': size(20),
    'padding_vertical_dropdown_item': size(10),
    'dropdown_box_shadow_box_shadow_type': 'yes',
    'dropdown_box_shadow_box_shadow': {'horizontal': 0, 'vertical': 0, 'blur': 10, 'spread': 0, 'color': 'rgba(0,0,0,0.12)'},
    'toggle_color': NAVY,
    'toggle_color_hover': RED,
    'toggle_size': size(24),
    'toggle_border_width': size(1),
    'toggle_border_radius': size(8),
})

button_base = {
    'link': link(PHONE_TEL, CALL_ARIA),
    'selected_icon': fa('fas fa-phone-alt'),
    'icon_align': 'row',   # Elementor 4.x: row / row-reverse, not left / right
    'icon_indent': size(10),
    'background_background': 'classic',
    'background_color': RED,
    'button_background_hover_background': 'classic',
    'button_background_hover_color': DARKNAVY,
    'button_text_color': WHITE,
    'hover_color': WHITE,
    'typography_typography': 'custom',
    'typography_font_family': 'Montserrat',
    'typography_font_weight': '700',
    'border_radius': {'unit': 'px', 'top': '8', 'right': '8', 'bottom': '8', 'left': '8', 'isLinked': True},
}

# the number itself is the CTA on anything wider than a phone: people read it as
# often as they tap it
call_full = widget('irvcall', 'button', dict(button_base, **{
    '_element_id': 'irv-call',
    'text': 'Call Now',
    'typography_font_size': size(16),
    'text_padding': px(14, 26, 14, 26),
}))

# phones get a short button so logo, call and burger fit one 360px row
call_short = widget('irvcalls', 'button', dict(button_base, **{
    '_element_id': 'irv-call-short',
    'text': 'Call',
    'typography_font_size': size(15),
    'text_padding': px(12, 16, 12, 16),
}))

col_logo = container('irvlogocol', {
    '_element_id': 'irv-logocol',
    'width': size(20, '%'),
    'content_width': 'full',
    'flex_gap': gap(0),
    'padding': px(0, 15, 0, 15),
}, [logo])

col_nav = container('irvnavcol', {
    '_element_id': 'irv-navcol',
    'width': size(64, '%'),
    'content_width': 'full',
    'flex_direction': 'row',
    'flex_justify_content': 'center',
    'flex_align_items': 'center',
    'flex_gap': gap(0),
    'padding': px(0, 15, 0, 15),
}, [nav])

col_cta = container('irvctacol', {
    '_element_id': 'irv-ctacol',
    'width': size(16, '%'),
    'content_width': 'full',
    'flex_direction': 'row',
    'flex_justify_content': 'flex-end',
    'flex_align_items': 'center',
    'flex_gap': gap(8),
    'padding': px(0, 15, 0, 15),
}, [call_full, call_short])

mainbar = container('irvmainbar', {
    '_element_id': 'irv-mainbar',
    'background_background': 'classic',
    'background_color': WHITE,
    'boxed_width': size(1300),
    'padding': px(10, 0, 10, 0),
    'flex_direction': 'row',
    'flex_align_items': 'center',
    'flex_gap': gap(10),
    # the live header sticks its white bar; carried over so the call button
    # follows the reader down the page
    'sticky': 'top',
    'sticky_on': ['desktop', 'tablet', 'mobile'],
}, [col_logo, col_nav, col_cta])

skip_target = widget('irvskip', 'html', {
    'html': '<div id="content" tabindex="-1" style="height:0;overflow:hidden"></div>',
})

RESPONSIVE_CSS = (
    # --- services panel: 21 links in 3 columns, so it fits one screen ---------
    # SmartMenus positions submenus with inline styles, hence the !important trio.
    '#irv-nav nav.elementor-nav-menu--main{position:relative}'
    '#irv-nav nav.elementor-nav-menu--main>ul>li.menu-item-576,'
    '#irv-nav nav.elementor-nav-menu--main>ul>li.grid-lg{position:static}'
    '#irv-nav nav.elementor-nav-menu--main>ul>li.menu-item-576>ul.sub-menu,'
    '#irv-nav nav.elementor-nav-menu--main>ul>li.grid-lg>ul.sub-menu{'
    'left:50%!important;right:auto!important;max-width:none!important;'
    'width:min(900px,calc(100vw - 48px))!important;transform:translateX(-50%);'
    'columns:3;column-gap:28px;padding:18px 22px;'
    'border-top:2px solid #DA1212;box-shadow:0 12px 28px rgba(0,0,0,.14)}'
    '#irv-nav nav.elementor-nav-menu--main>ul>li.menu-item-576>ul.sub-menu>li,'
    '#irv-nav nav.elementor-nav-menu--main>ul>li.grid-lg>ul.sub-menu>li{'
    'break-inside:avoid;page-break-inside:avoid}'
    '#irv-nav nav.elementor-nav-menu--main>ul>li.menu-item-576>ul.sub-menu>li>a,'
    '#irv-nav nav.elementor-nav-menu--main>ul>li.grid-lg>ul.sub-menu>li>a{'
    'padding:9px 12px!important;border-radius:4px;line-height:1.35}'
    # About Us carries ten links: two columns keeps it short
    '#irv-nav nav.elementor-nav-menu--main>ul>li.menu-item-404{position:static}'
    '#irv-nav nav.elementor-nav-menu--main>ul>li.menu-item-404>ul.sub-menu{'
    'left:50%!important;right:auto!important;max-width:none!important;'
    'width:min(560px,calc(100vw - 48px))!important;transform:translateX(-50%);'
    'columns:2;column-gap:24px;padding:16px 20px}'
    '#irv-nav nav.elementor-nav-menu--main>ul>li.menu-item-404>ul.sub-menu>li{break-inside:avoid}'
    # short dropdowns keep the same red rule and soft edge
    '#irv-nav nav.elementor-nav-menu--main>ul>li>ul.sub-menu{'
    'border-top:2px solid #DA1212;box-shadow:0 12px 28px rgba(0,0,0,.14);'
    'border-radius:0 0 8px 8px;padding:8px 0}'
    # the white bar separates from the page
    '#irv-mainbar{box-shadow:0 1px 0 #DDDDDD}'
    # six top-level items need 725px; they never wrap, and below 1250 the burger
    # takes over instead (Elementor's own switch is at 1024, which is too late here)
    '#irv-nav nav.elementor-nav-menu--main>ul{flex-wrap:nowrap}'
    # the short phone button and the short Directions label only exist below 768
    '#irv-call-short{display:none}'
    '#irv-trust .elementor-icon-list-item:nth-child(4){display:none}'
    # --- tablet ---------------------------------------------------------------
    '@media(max-width:1249px){'
    '#irv-mainbar{position:relative;padding:8px 16px}'
    '#irv-mainbar>.e-con-inner{flex-direction:row!important;flex-wrap:nowrap!important;align-items:center;gap:8px}'
    '#irv-navcol,#irv-nav,#irv-nav>.elementor-widget-container{position:static!important}'
    '#irv-nav nav.elementor-nav-menu--main{display:none}#irv-nav .elementor-menu-toggle{display:flex}'
    '#irv-logocol{width:34%}#irv-navcol{width:26%;justify-content:flex-end}#irv-ctacol{width:40%}'
    '#irv-logocol,#irv-navcol,#irv-ctacol{padding:0 8px}'
    '#irv-strip{padding:6px 16px}'
    '#irv-strip>.e-con-inner{flex-direction:row!important;flex-wrap:nowrap!important}'
    # "No appointment needed" is the longest line and the first to crowd
    '#irv-trust .elementor-icon-list-item:nth-child(1){display:none}'
    # once the burger is showing, the profiles are clutter: they are in the footer
    '#irv-social{display:none}'
    '#irv-nav nav.elementor-nav-menu--dropdown{position:absolute!important;top:100%!important;'
    'left:0!important;width:100%!important;z-index:999;margin-top:0!important;background:#FFFFFF;'
    'border-top:2px solid #DA1212;box-shadow:0 12px 28px rgba(0,0,0,.14);'
    'max-height:calc(100vh - 150px);overflow-y:auto}'
    '}'
    # --- phone ----------------------------------------------------------------
    '@media(max-width:767px){'
    '#irv-social{display:none}'
    '#irv-call{display:none}#irv-call-short{display:block}'
    '#irv-strip{padding:8px 16px}'
    '#irv-strip>.e-con-inner{justify-content:center;flex-wrap:nowrap!important}'
    # phones keep the tappable number and a short Directions link
    '#irv-trust .elementor-icon-list-item:nth-child(1),'
    '#irv-trust .elementor-icon-list-item:nth-child(3){display:none}'
    '#irv-trust .elementor-icon-list-item:nth-child(4){display:inline-block}'
    '#irv-trust .elementor-icon-list-item a,#irv-trust .elementor-icon-list-item{min-height:36px}'
    '#irv-trust .elementor-icon-list-text{font-size:15px}'
    '#irv-mainbar{padding:8px 12px}'
    # logo, call, burger in that order: the CTA sits under the thumb, not behind a menu
    '#irv-logocol{width:44%;order:1}#irv-ctacol{width:31%;order:2}#irv-navcol{width:25%;order:3}'
    '#irv-logocol,#irv-navcol,#irv-ctacol{padding:0 4px}'
    '#irv-logo img{width:170px}'
    '}'
)

root = container('irvhdroot', {
    '_element_id': 'irv-header',
    'content_width': 'full',
    'flex_direction': 'column',
    'flex_align_items': 'stretch',
    'flex_gap': gap(0),
    'padding': px(0, 0, 0, 0),
    'custom_css': RESPONSIVE_CSS,
}, [strip, mainbar, skip_target])

tree = [root]
out = os.path.join(HERE, 'irv-header-tree.json')
with open(out, 'w', encoding='utf-8') as f:
    json.dump(tree, f, ensure_ascii=False)
print('wrote', out, os.path.getsize(out), 'bytes')

widgets = []


def count(els):
    for e in els:
        if e['elType'] == 'widget':
            widgets.append(e['widgetType'])
        count(e['elements'])


count(tree)
print('widgets:', widgets)
