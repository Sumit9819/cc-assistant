"""Ports ER of Irving's footer out of its ElementsKit template into a tree the
cc-assistant queue accepts, without losing the mobile layout.

Why this exists: the footer's widgets are already native (headings, icon lists,
image, social icons, text), but the tree carries 40 device-suffix settings
(width_mobile, padding_mobile, flex_direction_mobile, typography_font_size_tablet...).
validate_tree only skips settings that already match the target post, so porting
into a fresh template validates everything, and this Elementor build registers
device variants for almost no controls, so all 40 are refused. Dropping them would
silently change how the footer stacks on phones.

So: each element that owns device settings gets an _element_id, the device keys are
removed, and the identical rules are re-emitted as CSS in one block on the root.
Breakpoints match Elementor's: tablet <= 1024, mobile <= 767.

Reads  irv-footer-2224.json (the export)
Writes irv-footer-ported.json (the tree to import)
"""
import json
import os
import re

HERE = os.path.dirname(os.path.abspath(__file__))
SUFFIXES = (('_mobile', 767), ('_tablet', 1024))
# _title is an editor label; content_position is the pre-flex vertical align,
# superseded here by flex_direction + flex_align_items on the same containers
DROP_KEYS = ('_title', 'content_position')
DROP_FIELD_KEYS = ('__dynamic__',)  # empty dynamic bindings inside repeater rows


def dim(v):
    """Elementor dimensions -> css shorthand."""
    u = v.get('unit', 'px')
    parts = [v.get(k, '') for k in ('top', 'right', 'bottom', 'left')]
    parts = [('0' if p in ('', None) else str(p)) + (u if u != 'custom' else '') for p in parts]
    return ' '.join(parts)


def slider(v):
    if not isinstance(v, dict):
        return None
    size = v.get('size', '')
    if size in ('', None):
        return None
    return f"{size}{v.get('unit', 'px')}"


def rules_for(key, value, sel):
    """Returns css declarations for one device setting, or None if unsupported."""
    if key == 'width':
        s = slider(value)
        return f'{sel}{{width:{s}}}' if s else None
    if key == 'margin':
        return f'{sel}{{margin:{dim(value)}}}'
    if key == 'padding':
        return f'{sel}{{padding:{dim(value)}}}'
    if key == 'flex_direction':
        return f'{sel},{sel}>.e-con-inner{{flex-direction:{value}!important}}'
    if key == 'flex_align_items':
        return f'{sel},{sel}>.e-con-inner{{align-items:{value}}}'
    if key == 'flex_wrap':
        return f'{sel},{sel}>.e-con-inner{{flex-wrap:{value}}}'
    if key == 'flex_gap':
        col = value.get('column', value.get('size', ''))
        row = value.get('row', value.get('size', ''))
        u = value.get('unit', 'px')
        if col in ('', None) and row in ('', None):
            return None
        col = col if col not in ('', None) else 0
        row = row if row not in ('', None) else 0
        return f'{sel},{sel}>.e-con-inner{{gap:{row}{u} {col}{u}}}'
    if key == 'typography_font_size':
        s = slider(value)
        if not s:
            return None
        # headings put the type on the inner title element
        return f'{sel},{sel} .elementor-heading-title{{font-size:{s}}}'
    return None


def main():
    src = json.load(open(os.path.join(HERE, 'irv-footer-2224.json'), encoding='utf-8'))['data']
    raw = src['raw_data']
    tree = json.loads(raw) if isinstance(raw, str) else raw

    css_by_bp = {767: [], 1024: []}
    unsupported = []
    touched = 0

    def visit(els):
        nonlocal touched
        for el in els:
            settings = el.get('settings', {})
            device = {}
            for key in list(settings.keys()):
                for suffix, bp in SUFFIXES:
                    if key.endswith(suffix) and len(key) > len(suffix):
                        device[key[:-len(suffix)]] = (bp, settings.pop(key))
                        break
            for key in DROP_KEYS:
                settings.pop(key, None)
            # repeater rows carry empty dynamic bindings the validator rejects
            for value in settings.values():
                if isinstance(value, list):
                    for row in value:
                        if isinstance(row, dict):
                            for k in DROP_FIELD_KEYS:
                                row.pop(k, None)
            # Elementor 4.x renamed these values on SOME widgets only: image, icon-list
            # and heading take start/end, social-icons still takes left/right
            if el.get('widgetType') in ('image', 'icon-list', 'heading'):
                for key in ('align', 'icon_align'):
                    if settings.get(key) == 'left':
                        settings[key] = 'start'
                    elif settings.get(key) == 'right':
                        settings[key] = 'end'

            if device:
                eid = 'irvf-' + el['id']
                settings['_element_id'] = eid
                touched += 1
                for key, (bp, value) in device.items():
                    rule = rules_for(key, value, '#' + eid)
                    if rule:
                        css_by_bp[bp].append(rule)
                    else:
                        unsupported.append(f"{el.get('widgetType') or 'container'} {el['id']} {key}={json.dumps(value)[:60]}")
            visit(el.get('elements', []))

    visit(tree)

    # tablet rules first so mobile can override them
    css = ''
    if css_by_bp[1024]:
        css += '@media(max-width:1024px){' + ''.join(css_by_bp[1024]) + '}'
    if css_by_bp[767]:
        css += '@media(max-width:767px){' + ''.join(css_by_bp[767]) + '}'

    # the export is a list of root containers; the CSS block rides on the first
    if css:
        root = tree[0]
        root.setdefault('settings', {})
        existing = root['settings'].get('custom_css', '')
        root['settings']['custom_css'] = (existing + css) if existing else css

    out = os.path.join(HERE, 'irv-footer-ported.json')
    json.dump(tree, open(out, 'w', encoding='utf-8'), ensure_ascii=False)
    print('elements given an id + css:', touched)
    print('css bytes:', len(css))
    print('unsupported (check by hand):', unsupported or 'none')
    print('wrote', out, os.path.getsize(out), 'bytes')


if __name__ == '__main__':
    main()
