"""Builds the native (ElementsKit-free) header tree for ER of Lufkin template 7151.

Design values were measured from the live header (6221) with Playwright computed
styles. Off-brand colours (#FE0467 pink, #ff5e13 orange) are corrected to the Kit
brand (#DA1212 red, #11468F navy, #041562 dark navy) per the eroflufkin-design skill.

SCHEMA NOTES (Elementor 4.2.3 + Pro 4.0.1, checked against widget_schema):
- Device-suffix keys (padding_mobile, width_mobile, hide_mobile, ...) are REFUSED by
  the queue: this control stack registers device variants for only ~26 controls, and
  hide_desktop/hide_tablet/hide_mobile get read as variants of a non-existent "hide".
  All responsive behaviour therefore lives in one scoped custom_css block on the root.
- background_hover_color does not exist on the button; button_background_hover_color
  does, and it is gated by button_background_hover_background.

Writes raw_data.json (the Elementor tree) next to this file.
"""
import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))

RED = '#DA1212'       # brand primary
NAVY = '#11468F'      # brand secondary
DARKNAVY = '#041562'  # heading / text navy
WHITE = '#FFFFFF'
CARD = '#F4F4F4'

PHONE_TEL = 'tel:+19364271313'
PHONE_TXT = '(936) 427-1313'
CALL_ARIA = 'aria-label|Call ER of Lufkin now at (936) 427-1313'


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


# ---------------------------------------------------------------- top red bar
contact_items = [
    {'_id': 'lufcon1', 'text': PHONE_TXT, 'selected_icon': fa('fas fa-phone-alt'), 'link': link(PHONE_TEL, CALL_ARIA)},
    {'_id': 'lufcon2', 'text': 'General Inquiry: info@eroflufkin.com', 'selected_icon': fa('fas fa-envelope'),
     'link': link('mailto:info@eroflufkin.com')},
    {'_id': 'lufcon3', 'text': '501 N Brentwood Dr. Lufkin, TX 75904 USA', 'selected_icon': fa('fas fa-map-marker-alt'),
     'link': link('https://maps.app.goo.gl/uMX3wBgdL4rSjKsN8')},
]

contact_style = {
    'view': 'inline',
    'icon_align': 'start',   # Elementor 4.x renamed left/right to start/end
    'space_between': size(15),
    'icon_size': size(16),
    'icon_color': WHITE,
    'text_color': WHITE,
    'icon_typography_typography': 'custom',
    'icon_typography_font_family': 'Roboto',
    'icon_typography_font_size': size(16),
    'icon_typography_font_weight': '600',
}

contact_desktop = widget('lufcontd', 'icon-list', dict(contact_style, _element_id='luf-contact-full'))
contact_desktop['settings']['icon_list'] = contact_items

# Phones get the phone number only. Email, address and the six social icons stay in
# the footer; the old three-line block pushed the hero about 150px down.
contact_mobile = widget('lufcontm', 'icon-list', dict(contact_style, **{
    '_element_id': 'luf-contact-phone',
    'icon_list': [dict(contact_items[0], _id='lufconm1')],
    'icon_size': size(18),
    '_element_width': 'initial',
    '_flex_align_self': 'center',
}))

SOCIALS = [
    ('lufsoc1', 'fab fa-facebook-f', 'https://www.facebook.com/ERofLufkin/reviews'),
    ('lufsoc2', 'fab fa-twitter', 'https://x.com/eroflufkin'),
    ('lufsoc3', 'fab fa-linkedin-in', 'https://www.linkedin.com/company/er-of-lufkin-emergency-room'),
    ('lufsoc4', 'fab fa-instagram', 'https://www.instagram.com/eroflufkin/'),
    ('lufsoc5', 'fab fa-tiktok', 'https://www.tiktok.com/@eroflufkin'),
    ('lufsoc6', 'fab fa-yelp', 'https://www.yelp.com/biz/er-of-lufkin-lufkin-3'),
]

social = widget('lufsocial', 'social-icons', {
    '_element_id': 'luf-social',
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
    'icon_size': size(16),
    'icon_spacing': size(10),
})

open_247 = widget('luf247w', 'icon-list', dict(contact_style, **{
    '_element_id': 'luf-247',
    'icon_list': [{'_id': 'luf247', 'text': 'Open 24/7', 'selected_icon': fa('fas fa-clock'), 'link': link('')}],
    '_element_width': 'initial',
    '_flex_align_self': 'center',
}))

topbar = container('luftopbar', {
    '_element_id': 'luf-topbar',
    'background_background': 'classic',
    'background_color': RED,
    'boxed_width': size(1200),
    'padding': px(8, 15, 8, 15),
    'flex_direction': 'row',
    'flex_align_items': 'center',
    'flex_justify_content': 'space-between',
    'flex_wrap': 'wrap',
    'flex_gap': gap(10),
}, [contact_desktop, contact_mobile, open_247, social])

# ---------------------------------------------------------------- white bar
logo = widget('luflogo', 'image', {
    '_element_id': 'luf-logo',
    'image': {'id': 5997, 'url': 'https://eroflufkin.com/wp-content/uploads/2025/12/LufkinLogoNewHorizontalNew.png',
              'alt': 'ER of Lufkin - Logo', 'source': 'library', 'size': ''},
    'image_size': 'full',
    'width': size(256),
    'align': 'start',
    'link_to': 'custom',
    'link': link('https://eroflufkin.com/', 'aria-label|ER of Lufkin home'),
})

nav = widget('lufnav', 'nav-menu', {
    '_element_id': 'luf-nav',
    'menu': 'menu',
    'layout': 'horizontal',
    'align_items': 'center',
    'submenu_icon': fa('fas fa-chevron-down'),
    'dropdown': 'tablet',
    'full_width': 'yes',
    'toggle': 'burger',
    'toggle_align': 'right',
    # main items: Montserrat 14/700 uppercase navy, red underline on hover (measured)
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
    # dropdown: Roboto 15/400 on the light card grey (measured)
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
    # burger: brand navy, red on hover (was off-brand orange)
    'toggle_color': NAVY,
    'toggle_color_hover': RED,
    'toggle_size': size(22),
    'toggle_border_width': size(1),
    'toggle_border_radius': size(8),
})

call_button = widget('lufcall', 'button', {
    '_element_id': 'luf-call',
    'text': 'Call Now',
    'link': link(PHONE_TEL, CALL_ARIA),
    'background_background': 'classic',
    'background_color': NAVY,
    'button_background_hover_background': 'classic',
    'button_background_hover_color': DARKNAVY,
    'button_text_color': WHITE,
    'hover_color': WHITE,
    'typography_typography': 'custom',
    'typography_font_family': 'Montserrat',
    'typography_font_size': size(15),
    'typography_font_weight': '700',
    'border_radius': {'unit': 'px', 'top': '8', 'right': '8', 'bottom': '8', 'left': '8', 'isLinked': True},
    'text_padding': px(14, 28, 14, 28),
})

col_logo = container('luflogocol', {
    '_element_id': 'luf-logocol',
    'width': size(22, '%'),
    'content_width': 'full',
    'flex_gap': gap(0),
    'padding': px(0, 15, 0, 15),
}, [logo])

col_nav = container('lufnavcol', {
    '_element_id': 'luf-navcol',
    'width': size(62, '%'),
    'content_width': 'full',
    'flex_direction': 'row',
    'flex_justify_content': 'center',
    'flex_align_items': 'center',
    'flex_gap': gap(0),
    'padding': px(0, 15, 0, 15),
}, [nav])

col_cta = container('lufctacol', {
    '_element_id': 'luf-ctacol',
    'width': size(16, '%'),
    'content_width': 'full',
    'flex_direction': 'row',
    'flex_justify_content': 'flex-end',
    'flex_align_items': 'center',
    'flex_gap': gap(5),
    'padding': px(0, 15, 0, 15),
}, [call_button])

mainbar = container('lufmainbar', {
    '_element_id': 'luf-mainbar',
    # The live header sticks its white bar to the top (Elementor "sticky": "top" on
    # container 119c0077, not an ElementsKit feature). Carried over so the Call Now
    # button keeps following the reader down the page.
    'sticky': 'top',
    'sticky_on': ['desktop', 'tablet', 'mobile'],
    'background_background': 'classic',
    'background_color': WHITE,
    'boxed_width': size(1300),
    'padding': px(10, 0, 10, 0),
    'flex_direction': 'row',
    'flex_align_items': 'center',
    'flex_gap': gap(10),
}, [col_logo, col_nav, col_cta])

skip_target = widget('lufskip', 'html', {
    'html': '<div id="content" tabindex="-1" style="height:0;overflow:hidden"></div>',
})

# One scoped block carries every responsive rule, because this control stack refuses
# device-suffix settings through the queue (see SCHEMA NOTES above).
RESPONSIVE_CSS = (
    # --- services panel: 21 links in 3 columns, so it fits one screen ---------
    # SmartMenus positions submenus with inline styles, hence the !important trio.
    '#luf-nav nav.elementor-nav-menu--main{position:relative}'
    '#luf-nav nav.elementor-nav-menu--main>ul>li.menu-item-576,'
    '#luf-nav nav.elementor-nav-menu--main>ul>li.grid-lg{position:static}'
    '#luf-nav nav.elementor-nav-menu--main>ul>li.menu-item-576>ul.sub-menu,'
    '#luf-nav nav.elementor-nav-menu--main>ul>li.grid-lg>ul.sub-menu{'
    'left:50%!important;right:auto!important;max-width:none!important;'
    'width:min(900px,calc(100vw - 48px))!important;transform:translateX(-50%);'
    'columns:3;column-gap:28px;padding:18px 22px;'
    'border-top:2px solid #DA1212;box-shadow:0 12px 28px rgba(0,0,0,.14)}'
    '#luf-nav nav.elementor-nav-menu--main>ul>li.menu-item-576>ul.sub-menu>li,'
    '#luf-nav nav.elementor-nav-menu--main>ul>li.grid-lg>ul.sub-menu>li{'
    'break-inside:avoid;page-break-inside:avoid}'
    '#luf-nav nav.elementor-nav-menu--main>ul>li.menu-item-576>ul.sub-menu>li>a,'
    '#luf-nav nav.elementor-nav-menu--main>ul>li.grid-lg>ul.sub-menu>li>a{'
    'padding:9px 12px!important;border-radius:4px;line-height:1.35}'
    # short dropdowns keep a soft edge and the same red rule
    '#luf-nav nav.elementor-nav-menu--main>ul>li>ul.sub-menu{'
    'border-top:2px solid #DA1212;box-shadow:0 12px 28px rgba(0,0,0,.14);'
    'border-radius:0 0 8px 8px;padding:8px 0}'
    # the white bar gets an edge so it separates from the hero
    '#luf-mainbar{box-shadow:0 1px 0 #DDDDDD}'
    '#luf-contact-phone{display:none}'
    # Below Elementor's tablet breakpoint the container children stack, and the
    # collapsed mobile menu sits in the flow instead of hanging under the bar. Both
    # are held here: the row stays a row, and the panel drops out of flow.
    '@media(max-width:1024px){'
    '#luf-mainbar{position:relative;padding:8px 16px}'
    '#luf-mainbar>.e-con-inner{flex-direction:row!important;flex-wrap:nowrap!important;align-items:center;gap:8px}'
    '#luf-navcol,#luf-nav,#luf-nav>.elementor-widget-container{position:static!important}'
    '#luf-logocol{width:34%}#luf-navcol{width:34%;justify-content:flex-end}#luf-ctacol{width:32%}'
    '#luf-logocol,#luf-navcol,#luf-ctacol{padding:0 8px}'
    '#luf-topbar{padding:8px 16px}'
    '#luf-topbar>.e-con-inner{flex-direction:row!important;flex-wrap:nowrap!important}'
    '#luf-247{display:none}'
    # email and address are the long lines; the phone and the profiles stay
    '#luf-contact-full .elementor-icon-list-item:nth-child(2),'
    '#luf-contact-full .elementor-icon-list-item:nth-child(3){display:none}'
    '#luf-nav nav.elementor-nav-menu--dropdown{position:absolute!important;top:100%!important;left:0!important;width:100%!important;'
    'z-index:999;margin-top:0!important;background:#FFFFFF;border-top:2px solid #DA1212;'
    'box-shadow:0 12px 28px rgba(0,0,0,.14);'
    'max-height:calc(100vh - 150px);overflow-y:auto}'
    '}'
    '@media(max-width:767px){'
    '#luf-contact-full{display:none}#luf-social{display:none}#luf-ctacol{display:none}'
    '#luf-contact-phone{display:block}'
    '#luf-topbar{padding:10px 16px}#luf-topbar>.e-con-inner{justify-content:center;flex-wrap:nowrap!important}'
    '#luf-contact-phone .elementor-icon-list-item,#luf-contact-phone .elementor-icon-list-item a{min-height:44px}'
    '#luf-contact-phone .elementor-icon-list-text{font-size:17px}'
    '#luf-mainbar{padding:8px 16px}'
    '#luf-mainbar>.e-con-inner{flex-direction:row!important;flex-wrap:nowrap!important;align-items:center}'
    '#luf-logocol{width:62%}#luf-navcol{width:38%;justify-content:flex-end}'
    '#luf-logo img{width:190px}'
    '}'
)

root = container('lufhdroot', {
    '_element_id': 'luf-header',
    'content_width': 'full',
    'flex_direction': 'column',
    'flex_align_items': 'stretch',
    'flex_gap': gap(0),
    'padding': px(0, 0, 0, 0),
    'custom_css': RESPONSIVE_CSS,
}, [topbar, mainbar, skip_target])

tree = [root]
out = os.path.join(HERE, 'raw_data.json')
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
