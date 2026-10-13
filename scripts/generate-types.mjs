#!/usr/bin/env node
/**
 * Bangkitkan tipe API v3 dari `openapi/openapi-v3.json` (sumber kebenaran = api-crm):
 *   - packages/node/src/v3/types.gen.ts          (TypeScript)
 *   - packages/python/src/plasgos_crm/v3/types.py (TypedDict, Python ≥ 3.9)
 *
 *   node scripts/generate-types.mjs           tulis berkas
 *   node scripts/generate-types.mjs --check   gagal (exit 1) bila berkas tidak sinkron dengan spesifikasi (CI)
 *
 * Per operasi (operationId) dihasilkan: query, body, `data`, dan `meta` respons sukses.
 */
import { readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..");
const SPEC_PATH = join(ROOT, "openapi/openapi-v3.json");
const spec = JSON.parse(readFileSync(SPEC_PATH, "utf8"));
const schemas = spec.components.schemas;
const HEADER_NOTE = "Dibangkitkan dari openapi/openapi-v3.json oleh scripts/generate-types.mjs — jangan diubah manual.";
const ENVELOPES = new Set(["SuccessEnvelope", "ListEnvelope", "ErrorEnvelope"]);
/** Nama skema yang bentrok dengan nama bawaan bahasa (`Error` di JavaScript). */
const RENAMED = { Error: "ErrorObject" };

// ———————————————————————————————————————— Bantuan umum

const schemaName = (name) => RENAMED[name] ?? name;
const refName = (ref) => schemaName(ref.split("/").pop());
const deref = (node) => {
  let current = node;
  while (current && current.$ref) {
    current = current.$ref.split("/").slice(1).reduce((acc, key) => acc[key], spec);
  }
  return current;
};
const pascal = (text) => String(text).replace(/(^|[^A-Za-z0-9]+)([A-Za-z0-9])/g, (_, __, c) => c.toUpperCase());
const firstLine = (text) => String(text ?? "").split("\n")[0].replace(/\*\//g, "*\\/").trim();
const isEnvelopeType = (name) => {
  if (ENVELOPES.has(name)) return true;
  const all = schemas[name]?.allOf ?? [];
  return all.some((part) => part.$ref && ENVELOPES.has(part.$ref.split("/").pop()));
};
const typesOf = (schema) => (Array.isArray(schema.type) ? schema.type : schema.type ? [schema.type] : []);
const isObjectSchema = (schema) => typesOf(schema).includes("object") || Boolean(schema.properties);
/** `anyOf`/`oneOf` yang hanya berisi batasan validasi (mis. `{required: [...]}`) bukan varian tipe. */
const TYPE_KEYS = ["$ref", "type", "properties", "items", "const", "enum", "oneOf", "anyOf", "allOf"];
const constraintOnly = (variant) => !TYPE_KEYS.some((key) => key in variant);
function typed(schema) {
  if (!schema) return schema;
  const variants = schema.oneOf ?? schema.anyOf;
  if (variants && variants.every(constraintOnly)) {
    const { oneOf, anyOf, ...rest } = schema;
    return rest;
  }
  return schema;
}

/** Operasi: method, path, operationId, query, body, data, meta tambahan. */
function operations() {
  const result = [];
  for (const [path, item] of Object.entries(spec.paths)) {
    for (const method of ["get", "post", "put", "patch", "delete"]) {
      const op = item[method];
      if (!op || !op.operationId || op.operationId === "getOpenApiSpec") continue;
      const parameters = [...(item.parameters ?? []), ...(op.parameters ?? [])].map(deref);
      const query = parameters.filter((p) => p.in === "query");
      const body = op.requestBody ? deref(op.requestBody).content?.["application/json"]?.schema ?? null : null;
      const success = Object.entries(op.responses ?? {}).find(([status]) => /^2\d\d$/.test(status));
      const responseSchema = success ? deref(success[1]).content?.["application/json"]?.schema ?? null : null;
      const { data, meta } = envelopeParts(responseSchema);
      result.push({ path, method, id: op.operationId, query, body, data, meta, list: Boolean(meta.pagination) });
    }
  }
  return result;
}

/** Ratakan allOf amplop: `data` paling spesifik + properti `meta` tambahan. */
function envelopeParts(schema) {
  let data = null;
  const meta = {};
  const visit = (node) => {
    if (!node) return;
    if (node.$ref) {
      const name = node.$ref.split("/").pop();
      if (name === "SuccessEnvelope") return; // data: {} (apa saja)
      visit(deref(node));
      return;
    }
    (node.allOf ?? []).forEach(visit);
    const props = node.properties ?? {};
    if (props.data && (props.data.$ref || props.data.items || props.data.type || props.data.oneOf || props.data.allOf)) {
      if (!(props.data.type === "array" && !props.data.items && data)) data = props.data;
    }
    if (props.meta) collectMeta(props.meta, meta);
  };
  visit(schema);
  return { data, meta };
}

function collectMeta(node, into) {
  if (!node) return;
  if (node.$ref) {
    if (node.$ref.split("/").pop() === "Meta") return;
    collectMeta(deref(node), into);
    return;
  }
  (node.allOf ?? []).forEach((part) => collectMeta(part, into));
  for (const [key, value] of Object.entries(node.properties ?? {})) {
    if (key !== "request_id") into[key] = { schema: value, required: (node.required ?? []).includes(key) };
  }
}

/** Skema komponen yang dipakai sebagai body permintaan: field `content`-nya juga menerima builder `TemplateContent`. */
const REQUEST_SCHEMAS = new Set();
for (const item of Object.values(spec.paths)) {
  for (const op of Object.values(item)) {
    const body = op?.requestBody ? deref(op.requestBody).content?.["application/json"]?.schema : null;
    for (const part of body ? [body, ...(body.allOf ?? [])] : []) if (part.$ref) REQUEST_SCHEMAS.add(part.$ref.split("/").pop());
  }
}
const acceptsBuilder = (owner, key) => key === "content" && REQUEST_SCHEMAS.has(owner);

// ———————————————————————————————————————— TypeScript

function ts(input, indent = "", owner = null) {
  const schema = typed(input);
  if (!schema || Object.keys(schema).length === 0) return "unknown";
  if (schema.$ref) return refName(schema.$ref);
  if ("const" in schema) return JSON.stringify(schema.const);
  if (schema.enum) return schema.enum.map((v) => JSON.stringify(v)).join(" | ");
  if (schema.oneOf || schema.anyOf) return (schema.oneOf ?? schema.anyOf).map((s) => paren(ts(s, indent))).join(" | ");
  if (schema.allOf) {
    const parts = schema.allOf.map((s) => paren(ts(s, indent)));
    const own = schema.properties ? [tsObject(schema, indent)] : [];
    return [...parts, ...own].join(" & ");
  }
  const types = typesOf(schema);
  if (types.length > 1) return types.map((t) => paren(ts({ ...schema, type: t }, indent))).join(" | ");
  switch (types[0]) {
    case "null": return "null";
    case "string": return "string";
    case "integer":
    case "number": return "number";
    case "boolean": return "boolean";
    case "array": return `Array<${ts(schema.items ?? {}, indent)}>`;
    case "object": return tsObject(schema, indent, owner);
    default: return schema.properties ? tsObject(schema, indent, owner) : "unknown";
  }
}
const paren = (text) => (/[|&]/.test(text) && !/^[{(]/.test(text) ? `(${text})` : text);

function tsObject(schema, indent, owner = null) {
  const props = Object.entries(schema.properties ?? {});
  const extra = schema.additionalProperties;
  if (!props.length) {
    if (extra && extra !== true) return `Record<string, ${ts(extra, indent)}>`;
    return "Record<string, unknown>";
  }
  const inner = indent + "  ";
  const lines = props.map(([key, value]) => {
    const doc = firstLine(value.description);
    const optional = (schema.required ?? []).includes(key) ? "" : "?";
    const type = ts(value, inner) + (acceptsBuilder(owner, key) ? " | TemplateContent" : "");
    return `${doc ? `${inner}/** ${doc} */\n` : ""}${inner}${key}${optional}: ${type};`;
  });
  if (extra && extra !== false) lines.push(`${inner}[key: string]: unknown;`);
  return `{\n${lines.join("\n")}\n${indent}}`;
}

function tsQuery(params) {
  if (!params.length) return "Record<string, never>";
  const lines = params.map((p) => {
    const doc = firstLine(p.description);
    return `${doc ? `    /** ${doc} */\n` : ""}    ${p.name}?: ${ts(p.schema ?? {}, "    ")};`;
  });
  return `{\n${lines.join("\n")}\n  }`;
}

function tsMeta(meta) {
  const entries = Object.entries(meta);
  if (!entries.length) return "Meta";
  const lines = entries.map(([key, { schema, required }]) => `    ${key}${required ? "" : "?"}: ${ts(schema, "    ")};`);
  return `Meta & {\n${lines.join("\n")}\n  }`;
}

function renderTs(ops) {
  const out = [`// ${HEADER_NOTE}`, `// API ${spec.info.version}`, "", 'import type { TemplateContent } from "./content.js";', ""];
  for (const [name, schema] of Object.entries(schemas)) {
    if (isEnvelopeType(name)) continue;
    const doc = firstLine(schema.description);
    if (doc) out.push(`/** ${doc} */`);
    out.push(`export type ${schemaName(name)} = ${ts(schema, "", name)};`, "");
  }
  out.push("/** Query, body, `data` dan `meta` respons sukses per operasi (operationId OpenAPI). */");
  out.push("export interface Operations {");
  for (const op of ops) {
    out.push(`  /** ${op.method.toUpperCase()} ${op.path} */`);
    out.push(`  ${op.id}: {`);
    out.push(`    query: ${tsQuery(op.query).replace(/\n/g, "\n  ")};`);
    out.push(`    body: ${op.body ? ts(op.body, "    ") : "never"};`);
    out.push(`    data: ${op.data ? ts(op.data, "    ") : "unknown"};`);
    if (op.list) out.push(`    item: ${op.data?.items ? ts(op.data.items, "    ") : "unknown"};`);
    out.push(`    meta: ${tsMeta(op.meta).replace(/\n/g, "\n  ")};`);
    out.push("  };");
  }
  out.push("}", "");
  return out.join("\n");
}

// ———————————————————————————————————————— Python (TypedDict)

class PyEmitter {
  constructor() {
    this.classes = [];
    this.names = new Set(Object.keys(schemas).map(schemaName));
  }

  type(input, hint) {
    const schema = typed(input);
    if (!schema || Object.keys(schema).length === 0) return "Any";
    if (schema.$ref) return refName(schema.$ref);
    if ("const" in schema) return `Literal[${py(schema.const)}]`;
    if (schema.enum) return `Literal[${schema.enum.map(py).join(", ")}]`;
    if (schema.oneOf || schema.anyOf) {
      const variants = schema.oneOf ?? schema.anyOf;
      const nonNull = variants.filter((v) => !(typesOf(v).length === 1 && typesOf(v)[0] === "null"));
      const parts = nonNull.map((v, i) => this.type(v, hint + this.variantName(v, i)));
      const union = parts.length === 1 ? parts[0] : `Union[${parts.join(", ")}]`;
      return nonNull.length < variants.length ? `Optional[${union}]` : union;
    }
    if (schema.allOf) return this.allOf(schema, hint);
    const types = typesOf(schema);
    if (types.length > 1) {
      const nonNull = types.filter((t) => t !== "null");
      const inner = nonNull.map((t) => this.type({ ...schema, type: t }, hint));
      const union = inner.length === 1 ? inner[0] : `Union[${inner.join(", ")}]`;
      return types.includes("null") ? `Optional[${union}]` : union;
    }
    switch (types[0]) {
      case "null": return "None";
      case "string": return "str";
      case "integer": return "int";
      case "number": return "float";
      case "boolean": return "bool";
      case "array": return `List[${this.type(schema.items ?? {}, hint + "Item")}]`;
      case "object": return this.object(schema, hint);
      default: return schema.properties ? this.object(schema, hint) : "Any";
    }
  }

  variantName(variant, index) {
    const typeConst = variant.properties?.type?.const;
    if (typeConst) return pascal(typeConst);
    if (variant.$ref) return "";
    const key = Object.keys(variant.properties ?? {}).find((k) => k !== "type");
    return key ? pascal(key) : `Variant${index + 1}`;
  }

  object(schema, hint, bases = [], owner = hint) {
    const props = Object.entries(schema.properties ?? {});
    if (!props.length && !bases.length) {
      const extra = schema.additionalProperties;
      return `Dict[str, ${extra && extra !== true ? this.type(extra, hint + "Value") : "Any"}]`;
    }
    const name = this.unique(hint);
    const required = new Set(schema.required ?? []);
    const fields = props.map(([key, value]) => {
      const type = this.type(value, name + pascal(key));
      return { key, type: acceptsBuilder(owner, key) ? `Union[${type}, TemplateContent]` : type, required: required.has(key), doc: firstLine(value.description) };
    });
    this.classes.push({ name, bases, fields, doc: firstLine(schema.description) });
    return name;
  }

  allOf(schema, hint) {
    const bases = [];
    const merged = { properties: { ...(schema.properties ?? {}) }, required: [...(schema.required ?? [])] };
    for (const part of schema.allOf) {
      const target = part.$ref ? typed(deref(part)) : null;
      if (target && isObjectSchema(target) && !target.oneOf && !target.anyOf && !target.allOf && target.properties) {
        this.ensure(part.$ref.split("/").pop());
        bases.push(refName(part.$ref));
      } else if (target && !isObjectSchema(target)) return this.type(part, hint);
      else {
        const source = target ?? part;
        Object.assign(merged.properties, source.properties ?? {});
        merged.required.push(...(source.required ?? []));
      }
    }
    if (!Object.keys(merged.properties).length && bases.length === 1) return bases[0];
    return this.object(merged, hint, bases);
  }

  /** Pastikan kelas skema komponen sudah ditulis sebelum dipakai sebagai kelas induk. */
  ensure(rawName) {
    this.done ??= new Set();
    const name = schemaName(rawName);
    if (this.done.has(name)) return;
    this.done.add(name);
    this.names.delete(name);
    this.type(schemas[rawName], name);
  }

  unique(hint) {
    let name = hint;
    for (let i = 2; this.names.has(name) && !this.reserved?.has(name); i++) name = `${hint}${i}`;
    this.names.add(name);
    return name;
  }

  render(cls) {
    const lines = [];
    const required = cls.fields.filter((f) => f.required);
    const optional = cls.fields.filter((f) => !f.required);
    const field = (f) => `${f.doc ? `    #: ${f.doc}\n` : ""}    ${f.key}: ${f.type}`;
    const bases = cls.bases.length ? cls.bases.join(", ") : "TypedDict";
    if (required.length && optional.length) {
      lines.push(`class _${cls.name}Required(${bases}):`, ...required.map(field), "", "");
      lines.push(`class ${cls.name}(_${cls.name}Required, total=False):`);
      if (cls.doc) lines.push(`    """${cls.doc.replace(/"""/g, "'''")}"""`, "");
      lines.push(...optional.map(field));
    } else {
      lines.push(`class ${cls.name}(${bases}${optional.length ? ", total=False" : ""}):`);
      if (cls.doc) lines.push(`    """${cls.doc.replace(/"""/g, "'''")}"""`, "");
      const all = cls.fields.map(field);
      lines.push(...(all.length ? all : ["    pass"]));
    }
    return lines.join("\n");
  }
}
const py = (value) => (value === null ? "None" : typeof value === "boolean" ? (value ? "True" : "False") : JSON.stringify(value));

function renderPy(ops) {
  const emitter = new PyEmitter();
  const aliases = [];
  for (const [name, schema] of Object.entries(schemas)) {
    if (isEnvelopeType(name)) continue;
    const target = schemaName(name);
    emitter.done ??= new Set();
    if (emitter.done.has(target)) continue;
    emitter.done.add(target);
    emitter.names.delete(target);
    const type = emitter.type(schema, target);
    if (type !== target) aliases.push(`${target} = ${type}`);
  }
  for (const op of ops) {
    const base = pascal(op.id);
    const queryFields = op.query.map((p) => ({ key: p.name, type: emitter.type(p.schema ?? {}, `${base}Query${pascal(p.name)}`), required: false, doc: firstLine(p.description) }));
    if (queryFields.length) emitter.classes.push({ name: `${base}Query`, bases: [], fields: queryFields, doc: `Query ${op.method.toUpperCase()} ${op.path}` });
    if (op.body) {
      const bodyType = emitter.type(op.body, `${base}Body`);
      if (bodyType !== `${base}Body`) aliases.push(`${base}Body = ${bodyType}`);
    }
    const dataType = op.data ? emitter.type(op.data, `${base}Data`) : "Any";
    if (dataType !== `${base}Data`) aliases.push(`${base}Data = ${dataType}`);
    if (op.list) {
      const itemType = op.data?.items ? emitter.type(op.data.items, `${base}Item`) : "Any";
      if (itemType !== `${base}Item`) aliases.push(`${base}Item = ${itemType}`);
    }
    const metaFields = Object.entries(op.meta).map(([key, { schema, required }]) => ({ key, type: emitter.type(schema, `${base}Meta${pascal(key)}`), required, doc: firstLine(schema.description) }));
    emitter.classes.push({ name: `${base}Meta`, bases: ["Meta"], fields: metaFields, doc: `meta ${op.method.toUpperCase()} ${op.path}` });
  }
  const out = [
    `# ${HEADER_NOTE}`,
    `# API ${spec.info.version}`,
    '"""Tipe API v3 (TypedDict) — format kabel (snake_case), sama dengan `ApiResponse.data`."""',
    "",
    "from __future__ import annotations",
    "",
    "from typing import TYPE_CHECKING, Any, Dict, List, Literal, Optional, TypedDict, Union",
    "",
    "if TYPE_CHECKING:",
    "    from .content import TemplateContent",
    "",
  ];
  for (const cls of emitter.classes) out.push("", emitter.render(cls), "");
  if (aliases.length) out.push("", ...aliases, "");
  return out.join("\n").replace(/\n{4,}/g, "\n\n\n") + (out[out.length - 1] === "" ? "" : "\n");
}

// ———————————————————————————————————————— Tulis / periksa

const ops = operations();
const targets = [
  [join(ROOT, "packages/node/src/v3/types.gen.ts"), renderTs(ops)],
  [join(ROOT, "packages/python/src/plasgos_crm/v3/types.py"), renderPy(ops)],
];
const check = process.argv.includes("--check");
let stale = 0;
for (const [file, content] of targets) {
  let current = null;
  try { current = readFileSync(file, "utf8"); } catch {}
  if (check) {
    if (current !== content) { console.error(`✘ tidak sinkron dengan openapi-v3.json: ${file} — jalankan node scripts/generate-types.mjs`); stale++; }
  } else if (current !== content) {
    writeFileSync(file, content);
    console.log(`✔ ditulis ${file}`);
  }
}
if (check && !stale) console.log(`✔ tipe sinkron dengan openapi-v3.json (${ops.length} operasi)`);
process.exit(stale ? 1 : 0);
