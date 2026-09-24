"""Mirrors the plugin's queue-side schema validation locally, so a bad key costs a
second instead of a 20-second round trip through the browser transport.

Checks, in the same order as CC_Assistant_Widget_Schema::validate_settings:
  unknown_setting_key   - key (or its device base) is not a control
  unknown_setting_key   - device variant of a control with no registered variant
  invalid_option_value  - value outside a select/choose control's options
  invalid_option_value  - repeater row field that is not a field of that repeater
  inert_setting         - control whose condition is not satisfied by the final settings
"""
import json
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
SUFFIXES = ('_mobile', '_mobile_extra', '_tablet', '_tablet_extra', '_laptop', '_widescreen')
ALWAYS = {'__globals__', '__dynamic__'}
SCHEMAS = {
    'container': 'ws-container.json', 'image': 'ws-image.json', 'button': 'ws-button.json',
    'html': 'ws-html.json', 'nav-menu': 'ws-nav.json', 'social-icons': 'ws-soc.json',
    'icon-list': 'ws-iconlist.json', 'heading': 'ws-heading.json', 'text-editor': 'ws-texteditor.json',
}


def load(name):
    with open(os.path.join(HERE, SCHEMAS[name]), encoding='utf-8') as f:
        return json.load(f)['controls']


def base_of(key):
    for s in SUFFIXES:
        if key.endswith(s) and len(key) > len(s):
            return key[:-len(s)]
    return None


def visible(control, settings, controls):
    """Evaluates an Elementor control condition against the final settings."""
    cond = control.get('condition') or control.get('conditions')
    if not cond or not isinstance(cond, dict):
        return True
    for raw, expected in cond.items():
        negate = raw.endswith('!')
        name = raw[:-1] if negate else raw
        name = re.sub(r'\[.*\]$', '', name)
        value = settings.get(name, (controls.get(name) or {}).get('default', ''))
        if isinstance(value, dict):
            value = value.get('url', value.get('value', ''))
        options = expected if isinstance(expected, list) else [expected]
        hit = any(str(value) == str(o) for o in options)
        if hit == negate:
            return False
    return True


def check(elements, findings, depth=0):
    for el in elements:
        wtype = el.get('widgetType') if el['elType'] == 'widget' else 'container'
        if wtype not in SCHEMAS:
            findings.append(f'NO LOCAL SCHEMA for {wtype}')
            continue
        controls = load(wtype)
        settings = el.get('settings', {})
        where = f"{wtype}#{settings.get('_element_id', el.get('id'))}"
        for key, value in settings.items():
            if key in ALWAYS:
                continue
            b = base_of(key)
            root = b if b is not None else key
            control = controls.get(root)
            if control is None:
                findings.append(f'{where}: unknown_setting_key "{key}"')
                continue
            if b is not None and not control.get('responsive'):
                findings.append(f'{where}: unknown_setting_key "{key}" (no device variant of "{root}")')
            opts = control.get('options')
            if opts and isinstance(value, str) and value != '' and value not in opts:
                findings.append(f'{where}: invalid_option_value "{key}"="{value}" (allowed: {opts})')
            if control.get('type') == 'repeater':
                fields = control.get('fields', {})
                for i, row in enumerate(value if isinstance(value, list) else []):
                    for fkey in row:
                        if fkey != '_id' and fkey not in fields:
                            findings.append(f'{where}: unknown repeater field "{key}[{i}].{fkey}"')
            if not visible(control, settings, controls):
                findings.append(f'{where}: inert_setting "{key}" (gate {control.get("condition") or control.get("conditions")} not met)')
        check(el.get('elements', []), findings, depth + 1)
    return findings


if __name__ == '__main__':
    path = sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, 'raw_data.json')
    with open(path, encoding='utf-8') as f:
        tree = json.load(f)
    out = check(tree, [])
    print('\n'.join(out) if out else 'clean: no blocking findings')
