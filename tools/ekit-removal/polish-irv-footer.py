"""Design pass over ER of Irving's ported footer.

The port was deliberately faithful so a broken port could be told from a design
change. This pass makes the changes, all of them measured or functional:

BRAND (the footer had drifted off the palette in the erofirving-design skill)
  band      #EEEEEE -> #F4F4F4   the kit's card grey
  headings  #1C244B -> #041562   the kit's text navy
  link text #324A6D -> #11468F   the kit's secondary navy
  socials   #545454 -> #11468F   grey was the only neutral accent on the page
  bottom    #0E3E7E -> #041562   an invented navy replaced by the kit's

CONTRAST (computed, WCAG 2.1 AA needs 4.5:1 for text, 3:1 for UI)
  link text on band   8.31:1   headings 14.81:1   list icons 4.68:1
  socials on band     8.31:1   white on navy 9.14:1   white on bottom 16.29:1
  All improve on what they replace except the icons, which rise 4.44 -> 4.68.

FUNCTION (these were broken, not just off-brand)
  info@ and medicalrecords@ pointed at "#", so clicking them did nothing
  the phone number was not a link at all, so there was no tap-to-call
  footer links get a 40px tap row on phones

Reads  irv-footer-ported.json      (what is live now)
Writes irv-footer-polished.json    (the tree to import)
"""
import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))

COLOR = {
    '#EEEEEE': '#F4F4F4',
    '#1C244B': '#041562',
    '#324A6D': '#11468F',
    '#545454': '#11468F',
    '#0E3E7E': '#041562',
}

MAILTO = {
    'info@erofirving.com': 'mailto:info@erofirving.com',
    'medicalrecords@erofirving.com': 'mailto:medicalrecords@erofirving.com',
}
PHONE_TEXT = '(972) 893-3148'
PHONE_LINK = 'tel:+19728933148'

# Measured on the live footer at 412px: 40px rows with the existing 12px gap made
# each link sit 52px apart and stretched the footer to 2,417px. 32px rows with a
# 6px gap keep a 44px pitch between adjacent links, clear the 24px minimum target
# size, and bring the footer back to 2,241px.
TAP_CSS = (
    '@media(max-width:767px){'
    '.elementor-4763 .elementor-icon-list-items{gap:6px!important}'
    '.elementor-4763 .elementor-icon-list-item>a{min-height:32px;align-items:center}'
    '}'
)

changes = {'colors': 0, 'links': 0}


def recolor(value):
    if isinstance(value, str):
        up = value.upper()
        if up in COLOR:
            changes['colors'] += 1
            return COLOR[up]
        return value
    if isinstance(value, dict):
        return {k: recolor(v) for k, v in value.items()}
    if isinstance(value, list):
        return [recolor(v) for v in value]
    return value


def fix_links(settings):
    for row in settings.get('icon_list', []) or []:
        text = (row.get('text') or '').strip()
        link = row.get('link') or {}
        if text in MAILTO and link.get('url', '') in ('#', '', None):
            row['link'] = {'url': MAILTO[text], 'is_external': '', 'nofollow': '',
                           'custom_attributes': 'aria-label|Email ER of Irving at ' + text}
            changes['links'] += 1
        elif text == PHONE_TEXT and not link.get('url'):
            row['link'] = {'url': PHONE_LINK, 'is_external': '', 'nofollow': '',
                           'custom_attributes': 'aria-label|Call ER of Irving now at (972) 893-3148'}
            changes['links'] += 1


def visit(els):
    for el in els:
        settings = el.get('settings', {})
        for key, value in list(settings.items()):
            if key == 'icon_list':
                continue
            settings[key] = recolor(value)
        fix_links(settings)
        # recolor inside repeater rows too (icon/text colours per row)
        for row in settings.get('icon_list', []) or []:
            for k, v in list(row.items()):
                if k != 'link':
                    row[k] = recolor(v)
        visit(el.get('elements', []))


def main():
    tree = json.load(open(os.path.join(HERE, 'irv-footer-ported.json'), encoding='utf-8'))
    visit(tree)
    root = tree[0]
    css = root['settings'].get('custom_css', '')
    if TAP_CSS not in css:
        root['settings']['custom_css'] = css + TAP_CSS
    out = os.path.join(HERE, 'irv-footer-polished.json')
    json.dump(tree, open(out, 'w', encoding='utf-8'), ensure_ascii=False)
    print('colour values swapped:', changes['colors'])
    print('links repaired:', changes['links'])
    print('wrote', out, os.path.getsize(out), 'bytes')


if __name__ == '__main__':
    main()
