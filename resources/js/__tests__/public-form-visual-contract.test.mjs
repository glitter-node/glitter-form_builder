import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';

const moduleRoot = path.resolve(import.meta.dirname, '../..');
const layout = JSON.parse(fs.readFileSync(path.join(moduleRoot, 'layouts/user/user_form_builder_form_show.json'), 'utf8'));
const css = fs.readFileSync(path.join(moduleRoot, 'css/main.css'), 'utf8');
const nodes = [];
const walk = value => {
  if (Array.isArray(value)) return value.forEach(walk);
  if (!value || typeof value !== 'object') return;
  nodes.push(value);
  Object.values(value).forEach(walk);
};
walk(layout);

describe('public form visual contract', () => {
  it('groups controls without changing their bindings or actions', () => {
    const root = nodes.find(node => node.props?.className === 'gfb-public');
    const field = nodes.find(node => node.props?.className === 'gfb-public-field');
    const consent = nodes.find(node => node.props?.className === 'gfb-public-consent');
    const controls = nodes.filter(node => ['Input', 'Textarea', 'Select'].includes(node.name));
    const submit = nodes.filter(node => node.props?.className === 'gfb-public-submit');

    expect(root?.props?.['data-module']).toBe('glitter-form_builder');
    expect(field?.children?.some(node => node.props?.className === 'gfb-public-field-label')).toBe(true);
    expect(controls.every(node => node.props?.className === 'gfb-public-field-control')).toBe(true);
    expect(controls.every(node => node.actions?.[0]?.handler === 'glitter-form_builder.updateSubmissionField')).toBe(true);
    expect(consent?.name).toBe('Label');
    expect(consent?.children?.map(node => node.name)).toEqual(['Checkbox', 'Span']);
    expect(consent?.children?.[0]?.actions?.[0]?.handler).toBe('glitter-form_builder.updateSubmissionField');
    expect(consent?.children?.[0]?.props?.checked).toBe('{{_local.publicSubmission.values[field.key] === true}}');
    expect(submit).toHaveLength(1);
    expect(submit[0].props.type).toBe('button');
    expect(submit[0].actions[0].handler).toBe('glitter-form_builder.submitForm');
    expect(JSON.stringify(layout)).not.toContain('style');
  });

  it('defines scoped visible controls and compact flow without positioning workarounds', () => {
    expect(css).toMatch(/\[data-module="glitter-form_builder"\]\.gfb-public \.gfb-public-field-control\s*\{[^}]*border:\s*1px solid[^}]*background:/s);
    expect(css).toMatch(/\.gfb-public-field-control:focus-visible\s*\{/);
    expect(css).toMatch(/\.gfb-public-consent\s*\{[^}]*display:\s*flex[^}]*gap:/s);
    expect(css).toMatch(/\.gfb-public-form\s*\{[^}]*gap:/s);
    expect(css).toMatch(/\.gfb-public-submit\s*\{[^}]*background:/s);

    const publicCss = css.slice(css.indexOf('[data-module="glitter-form_builder"].gfb-public'));
    expect(publicCss).not.toMatch(/overflow-x:\s*hidden/);
    expect(publicCss).not.toMatch(/position:\s*(?:absolute|fixed)/);
    expect(publicCss).not.toMatch(/margin(?:-[a-z]+)?:\s*-/);
    expect(publicCss).not.toMatch(/transform:\s*(?:translate|matrix)/);
  });
});
