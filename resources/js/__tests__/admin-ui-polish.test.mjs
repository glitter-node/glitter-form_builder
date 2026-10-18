/** @vitest-environment jsdom */

import React from 'react';
import { afterEach, describe, expect, it } from 'vitest';
import { ComponentRegistry } from '../../../../../../resources/js/core/template-engine/ComponentRegistry';
import { createLayoutTest } from '../../../../../../resources/js/core/template-engine/__tests__/utils/layoutTestUtils';
import fs from 'node:fs';
import path from 'node:path';

const moduleRoot = path.resolve(import.meta.dirname, '../..');
const projectRoot = path.resolve(moduleRoot, '../../../..');
const readLayout = name => JSON.parse(fs.readFileSync(path.join(moduleRoot, 'layouts/admin', name), 'utf8'));
const walk = (value, visit) => {
  if (Array.isArray(value)) return value.forEach(item => walk(item, visit));
  if (!value || typeof value !== 'object') return;
  visit(value);
  Object.values(value).forEach(item => walk(item, visit));
};
const replaceSlots = (components, slots) => components.map(component => {
  if (!component || typeof component !== 'object') return component;
  if (component.slot && Object.hasOwn(slots, component.slot)) {
    const { slot, ...resolved } = component;
    return { ...resolved, children: slots[slot] };
  }
  return { ...component, ...(Array.isArray(component.children) ? { children: replaceSlots(component.children, slots) } : {}) };
});
const mergeLayouts = (parent, child) => ({
  ...parent,
  ...child,
  state: { ...(parent.state ?? {}), ...(child.state ?? {}) },
  components: replaceSlots(parent.components ?? [], child.slots ?? {}),
});
const resolveRuntimeLayouts = () => {
  const base = JSON.parse(fs.readFileSync(path.join(projectRoot, 'templates/_bundled/sirsoft-admin_basic/layouts/_admin_base.json'), 'utf8'));
  const index = mergeLayouts(base, readLayout('admin_form_builder_form_index.json'));
  const edit = mergeLayouts(base, readLayout('admin_form_builder_form_edit.json'));
  const create = mergeLayouts(edit, readLayout('admin_form_builder_form_create.json'));
  return { index, create, edit };
};

const element = tag => ({ children, text, className, type, disabled, value, placeholder, checked, min, max }) =>
  React.createElement(tag, { className, type, disabled, value, placeholder, checked, min, max, ...(['input', 'select'].includes(tag) ? { onChange: () => {} } : {}) }, children ?? text);
const registry = () => {
  const instance = ComponentRegistry.getInstance();
  instance.registry = Object.fromEntries([
    ['Div', 'div', 'basic'], ['Span', 'span', 'basic'], ['Button', 'button', 'basic'], ['Input', 'input', 'basic'],
    ['Select', 'select', 'composite'], ['Checkbox', 'input', 'basic'], ['H1', 'h1', 'basic'], ['H2', 'h2', 'basic'],
    ['H3', 'h3', 'basic'], ['P', 'p', 'basic'],
  ].map(([name, tag, type]) => [name, { component: element(tag), metadata: { name, type } }]));
  instance.registry.Fragment = { component: ({ children }) => React.createElement(React.Fragment, null, children), metadata: { name: 'Fragment', type: 'layout' } };
  return instance;
};

afterEach(() => document.body.replaceChildren());

describe('Form Builder admin UI polish', () => {
  it('renders the official layout through createLayoutTest with distinct accessible controls', async () => {
    const layout = readLayout('admin_form_builder_form_edit.json');
    layout.state = { ...layout.state, createMode: true };
    const translations = JSON.parse(fs.readFileSync(path.join(moduleRoot, 'lang/ko.json'), 'utf8'));
    const test = createLayoutTest(layout, {
      componentRegistry: registry(), locale: 'ko', translations: { 'glitter-form_builder': translations },
      initialState: { _local: { createMode: true, formMetadata: {}, formDesigner: { schema: { schemaVersion: '1.0', fields: [] }, selectedFieldIndex: null } } },
      initialData: { fieldTypes: { data: { fieldTypes: [{ type: 'text', name: '텍스트' }, { type: 'email', name: '이메일' }] } } },
    });

    const { container } = await test.render();
    const header = container.querySelector('.gfb-admin-header');
    const copy = header?.querySelector(':scope > .gfb-admin-header-copy');
    const actions = header?.querySelector(':scope > .gfb-admin-header-actions');
    expect(header, container.innerHTML).not.toBeNull();
    expect(copy).not.toBeNull();
    expect(actions).not.toBeNull();
    expect(header?.contains(copy)).toBe(true);
    expect(header?.contains(actions)).toBe(true);
    expect(actions?.querySelectorAll('button')).toHaveLength(4);
    expect(container.querySelector('.gfb-palette-list')).not.toBeNull();
    expect(container.querySelector('.gfb-canvas-empty')).not.toBeNull();
    expect(container.querySelector('.gfb-inspector-empty')).not.toBeNull();
    const paletteItems = [];
    const sectionHeadings = [];
    walk(layout, node => {
      if (node.props?.className === 'gfb-palette-item') paletteItems.push(node);
      if (node.props?.className === 'gfb-inspector-section-heading') sectionHeadings.push(node);
    });
    expect(paletteItems).toHaveLength(1);
    expect(paletteItems[0].name).toBe('Button');
    expect(sectionHeadings).toHaveLength(4);
    test.cleanup();
  });

  it('keeps every action button non-submitting and every visible literal translated', () => {
    for (const file of ['admin_form_builder_form_index.json', 'admin_form_builder_form_create.json', 'admin_form_builder_form_edit.json']) {
      walk(readLayout(file), node => {
        if (node.name === 'Button') expect(node.props?.type, `${file}:${node.id ?? node.props?.className ?? 'button'}`).toBe('button');
        if (typeof node.text === 'string' && !node.text.startsWith('{{')) expect(node.text).toMatch(/^\$t:/);
      });
    }
  });

  it('keeps the complete index and designer action sets inside module-owned headers', () => {
    const index = readLayout('admin_form_builder_form_index.json');
    const edit = readLayout('admin_form_builder_form_edit.json');
    const actionButtons = layout => {
      const buttons = [];
      walk(layout, node => {
        if (node.name === 'Button' && node.props?.className?.includes('gfb-header-action')) buttons.push(node);
      });
      return buttons;
    };
    expect(actionButtons(index)).toHaveLength(1);
    expect(actionButtons(edit)).toHaveLength(4);
    for (const layout of [index, edit]) {
      let pageHeaderCount = 0;
      walk(layout, node => { if (node.name === 'PageHeader') pageHeaderCount += 1; });
      expect(pageHeaderCount).toBe(0);
    }
  });

  it('keeps one direct full-width action row in every resolved runtime layout', () => {
    for (const [name, layout] of Object.entries(resolveRuntimeLayouts())) {
      const headers = [];
      const actionRows = [];
      let pageHeaders = 0;
      walk(layout.components, node => {
        if (node.name === 'PageHeader') pageHeaders += 1;
        if (node.props?.className === 'gfb-admin-header') headers.push(node);
        if (node.props?.className === 'gfb-admin-header-actions') actionRows.push(node);
      });
      expect(headers, name).toHaveLength(1);
      expect(actionRows, name).toHaveLength(1);
      expect(headers[0].children[0].props.className).toBe('gfb-admin-header-copy');
      expect(headers[0].children[1]).toBe(actionRows[0]);
      expect(pageHeaders, name).toBe(0);
    }
  });

  it('defines scoped wrapping, stack, palette, canvas, and inspector styles without fixed minimum widths', () => {
    const css = fs.readFileSync(path.join(moduleRoot, 'css/main.css'), 'utf8');
    expect(css).toContain('.gfb-admin-header');
    expect(css).toContain('.gfb-admin-header-copy');
    expect(css).toContain('.gfb-admin-header-actions');
    expect(css).toContain('flex-wrap: wrap');
    expect(css).toMatch(/\.gfb-admin-header\s*\{[^}]*flex-direction:\s*column/s);
    expect(css).toMatch(/\.gfb-admin-header-actions\s*\{[^}]*width:\s*100%/s);
    expect(css).toMatch(/\.gfb-admin-header-actions\s*\{[^}]*justify-content:\s*flex-start/s);
    expect(css).toMatch(/@media\s*\(max-width:\s*1100px\)/);
    expect(css).toContain('.gfb-palette-list');
    expect(css).toContain('.gfb-palette-item');
    expect(css).toContain(':focus-visible');
    expect(css).toContain('.gfb-canvas-empty');
    expect(css).toContain('.gfb-inspector-section-heading');
    expect(css).not.toMatch(/min-width:\s*(?:[1-9]\d*rem|[1-9]\d*px)/);
    for (const selector of css.split('}').map(rule => rule.split('{')[0]?.trim()).filter(Boolean)) {
      if (selector.startsWith('@')) continue;
      expect(selector, selector).toContain('[data-module="glitter-form_builder"]');
    }
  });
});
