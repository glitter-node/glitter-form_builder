import { describe, expect, it } from 'vitest';
import fs from 'node:fs'; import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const files = ['admin_form_builder_form_index.json', 'admin_form_builder_form_create.json', 'admin_form_builder_form_edit.json'];
const readLayout = (file: string): any => JSON.parse(fs.readFileSync(path.join(root, 'layouts/admin', file), 'utf8'));
const walk = (value: any, visit: (node: any) => void): void => { if (Array.isArray(value)) return value.forEach(item => walk(item, visit)); if (!value || typeof value !== 'object') return; visit(value); Object.values(value).forEach(item => walk(item, visit)); };
const resolveRuntimeTranslation = (translations: any, key: string): unknown => key.split('.').reduce((value, segment) => value?.[segment], translations);
describe('admin designer layouts', () => {
  it('hydrates the edit designer from the form data source success payload', () => {
    const edit = readLayout('admin_form_builder_form_edit.json');
    const formSource = edit.data_sources.find((source: { id?: string }) => source.id === 'form');
    expect(formSource).toMatchObject({
      endpoint: '/api/modules/glitter-form_builder/admin/forms/{{route.id}}',
      if: '{{route?.id}}',
      onSuccess: {
        handler: 'glitter-form_builder.hydrateForm',
        params: { resource: '{{response.data.data}}' },
        resultTo: { target: '_local', key: 'formDesigner', merge: 'replace' },
      },
    });
  });
  it('keeps the 0.3.16 package manifests synchronized', () => {
    for (const file of ['module.json', 'composer.json', 'package.json', 'package-lock.json']) expect(JSON.parse(fs.readFileSync(path.join(root, '..', file), 'utf8')).version).toBe('0.3.16');
  });
  it('provides routes and layouts', () => { const routes = JSON.parse(fs.readFileSync(path.join(root, 'routes/admin.json'), 'utf8')); expect(routes.routes.map((r: any) => r.path)).toContain('*/admin/form-builder/forms/:id/edit'); for (const file of files) expect(fs.existsSync(path.join(root, 'layouts/admin', file))).toBe(true); });
  it('uses canonical names and namespaced handlers', () => { const edit = fs.readFileSync(path.join(root, 'layouts/admin/admin_form_builder_form_edit.json'), 'utf8'); expect(edit).toContain('schemaVersion'); expect(edit).not.toContain('schema_version'); expect(edit).toContain('glitter-form_builder.addField'); expect(edit).toContain('/admin/field-types'); });
  it('uses translation references for literal text', () => { for (const file of files) { const layout = JSON.parse(fs.readFileSync(path.join(root, 'layouts/admin', file), 'utf8')); const visit = (v: any) => { if (Array.isArray(v)) return v.forEach(visit); if (!v || typeof v !== 'object') return; if (typeof v.text === 'string' && !v.text.startsWith('{{')) expect(v.text).toMatch(/^\$t:/); Object.values(v).forEach(visit); }; visit(layout); } });
  it('keeps CSS selectors module-scoped', () => { const css = fs.readFileSync(path.join(root, 'css/main.css'), 'utf8'); expect(css).not.toMatch(/(^|})\s*(button|input|body|\.form|\.field|\.error)\b/m); expect(css.match(/\[data-module="glitter-form_builder"\]/g)?.length).toBeGreaterThanOrEqual(4); });
  it('uses registered template components and supported handlers only', () => {
    const components = new Set(['Div', 'Span', 'Button', 'Input', 'Select', 'Checkbox', 'H1', 'H2', 'H3', 'P']);
    const builtIns = new Set(['navigate', 'setState', 'refetchDataSource']);
    for (const file of files) walk(readLayout(file), node => {
      if (typeof node.name === 'string') expect(components.has(node.name), `component ${node.name}`).toBe(true);
      if (typeof node.handler === 'string') expect(node.handler.startsWith('glitter-form_builder.') || builtIns.has(node.handler), `handler ${node.handler}`).toBe(true);
    });
  });
  it('resolves every module translation key', () => {
    const translations = JSON.parse(fs.readFileSync(path.join(root, 'lang/en.json'), 'utf8'));
    for (const file of files) walk(readLayout(file), node => Object.values(node).forEach(value => {
      if (typeof value === 'string' && value.startsWith('$t:glitter-form_builder.')) {
        const moduleKey = value.replace('$t:glitter-form_builder.', '');
        expect(resolveRuntimeTranslation(translations, moduleKey), moduleKey).toEqual(expect.any(String));
      }
    }));
  });
  it('uses the fully qualified module parent and UUID edit parameter', () => {
    expect(readLayout('admin_form_builder_form_create.json').extends).toBe('glitter-form_builder.admin_form_builder_form_edit');
    const routes = JSON.parse(fs.readFileSync(path.join(root, 'routes/admin.json'), 'utf8'));
    expect(routes.routes.find((route: any) => route.layout === 'admin_form_builder_form_edit').path).toContain('/:id/edit');
  });
  it('uses canonical list and metadata persistence contracts', () => {
    const index = readLayout('admin_form_builder_form_index.json');
    expect(index.data_sources[0].params.search).toBe('{{_local.keyword}}');
    expect(JSON.stringify(index)).toContain('{{form.title || form.slug}}');
    const edit = JSON.stringify(readLayout('admin_form_builder_form_edit.json'));
    expect(edit).toContain('retention_acknowledged');
    expect(edit).toContain('retention_days');
    expect(edit).toContain('form?.data');
  });
  it('binds create metadata through atomic property/value actions and renders field errors', () => {
    const nodes: any[] = [];
    walk(readLayout('admin_form_builder_form_edit.json'), node => nodes.push(node));
    const metadataHandlers = nodes
      .flatMap(node => node.actions ?? [])
      .filter((action: any) => action.handler === 'glitter-form_builder.updateMetadata');
    expect(metadataHandlers.map((action: any) => action.params.property)).toEqual([
      'slug', 'title', 'description', 'access_mode', 'retention_mode', 'retention_days', 'retention_acknowledged',
    ]);
    expect(metadataHandlers.every((action: any) => Object.prototype.hasOwnProperty.call(action.params, 'value'))).toBe(true);
    expect(metadataHandlers.find((action: any) => action.params.property === 'title')?.params.locale).toBe('{{$locale}}');
    expect(metadataHandlers.find((action: any) => action.params.property === 'description')?.params.locale).toBe('{{$locale}}');
    expect(JSON.stringify(metadataHandlers)).not.toContain('{{$locale}}\":');
    expect(nodes.filter(node => node.props?.className === 'gfb-metadata-error').length).toBeGreaterThanOrEqual(7);
    const serialized = JSON.stringify(readLayout('admin_form_builder_form_edit.json'));
    expect(serialized).toContain('_local.formDesigner.metadata.slug');
    expect(serialized).toContain('_local.formDesigner.metadata.retentionAcknowledged');
    expect(serialized).not.toContain('_local.formMetadata');
    expect(serialized).not.toContain('defaultValue');
  });
  it('passes the click-time designer snapshot to every persistence and validation action', () => {
    const nodes: any[] = []; walk(readLayout('admin_form_builder_form_edit.json'), node => nodes.push(node));
    const critical = nodes.flatMap(node => node.actions ?? []).filter((action: any) => [
      'glitter-form_builder.createForm', 'glitter-form_builder.saveDraft', 'glitter-form_builder.validateSchema', 'glitter-form_builder.publish',
    ].includes(action.handler));
    expect(critical.map((action: any) => action.handler).sort()).toEqual([
      'glitter-form_builder.createForm', 'glitter-form_builder.publish', 'glitter-form_builder.saveDraft', 'glitter-form_builder.validateSchema',
    ]);
    expect(critical.every((action: any) => action.params.designerState === '{{_local.formDesigner}}')).toBe(true);
  });
  it('uses one hydrated ability source for publish rendering and action entry', () => {
    const nodes: any[] = []; walk(readLayout('admin_form_builder_form_edit.json'), node => nodes.push(node));
    const button = nodes.find(node => node.actions?.some((action: any) => action.handler === 'glitter-form_builder.publish'));
    const action = button?.actions?.find((item: any) => item.handler === 'glitter-form_builder.publish');
    expect(button?.props?.type).toBe('button');
    expect(button?.props?.disabled).toContain('_local.formDesigner.abilities.canPublish');
    expect(action?.params?.canPublish).toBe('{{_local.formDesigner.abilities.canPublish}}');
    expect(action?.params?.formId).toBe('{{route.id}}');
  });
  it('keeps field selection separate from local action buttons to prevent bubbling state rollback', () => {
    const nodes: any[] = [];
    walk(readLayout('admin_form_builder_form_edit.json'), node => nodes.push(node));
    const card = nodes.find(node => node.props?.className === 'gfb-field-card');
    const summary = card?.children?.find((node: any) => node.props?.className === 'gfb-field-summary');
    const localHandlers = card?.children
      ?.find((node: any) => node.props?.className === 'gfb-field-actions')
      ?.children?.map((node: any) => node.actions?.[0]?.handler);

    expect(card?.actions).toBeUndefined();
    expect(summary?.name).toBe('Button');
    expect(summary?.props?.type).toBe('button');
    expect(summary?.actions?.[0]?.handler).toBe('glitter-form_builder.selectField');
    expect(localHandlers).toEqual([
      'glitter-form_builder.moveFieldUp',
      'glitter-form_builder.moveFieldDown',
      'glitter-form_builder.duplicateField',
      'glitter-form_builder.removeField',
    ]);
  });
  it('binds key and label inputs through the same controlled selected-field expression contract', () => {
    const nodes: any[] = [];
    walk(readLayout('admin_form_builder_form_edit.json'), node => nodes.push(node));
    const controls = nodes.filter(node => node.props?.className === 'gfb-field-control');
    const keyInput = controls[0]?.children?.find((node: any) => node.name === 'Input');
    const labelInput = controls[1]?.children?.find((node: any) => node.name === 'Input');

    expect(keyInput?.props?.value).toBe("{{_local.formDesigner.schema.fields[_local.formDesigner.selectedFieldIndex]?.key || ''}}");
    expect(labelInput?.props?.value).toContain('_local.formDesigner.schema.fields[_local.formDesigner.selectedFieldIndex]');
    expect(keyInput?.props).not.toHaveProperty('defaultValue');
    expect(labelInput?.props).not.toHaveProperty('defaultValue');
    expect(keyInput?.actions?.[0]?.handler).toBe('glitter-form_builder.updateSelectedField');
    expect(labelInput?.actions?.[0]?.handler).toBe('glitter-form_builder.updateSelectedField');
    expect(keyInput?.actions?.[0]?.params).toEqual({ property: 'key', value: '{{$event.target.value}}' });
    expect(labelInput?.actions?.[0]?.params).toEqual({ property: 'label', locale: '{{$locale}}', value: '{{$event.target.value}}' });
    expect(labelInput?.actions?.[0]?.params).not.toHaveProperty('changes');
  });
  it('passes only canonical type and locale context from the palette', () => {
    const nodes: any[] = []; walk(readLayout('admin_form_builder_form_edit.json'), node => nodes.push(node));
    const palette = nodes.find(node => node.props?.className === 'gfb-palette-item');
    expect(palette?.actions?.[0]?.params).toEqual({ type: '{{fieldType.type}}', locale: '{{$locale}}', supportedLocales: '{{$locales}}', errorMessage: '$t:glitter-form_builder.admin.palette.add_error' });
    expect(palette?.actions?.[0]?.params).not.toHaveProperty('fieldType');
  });
});
