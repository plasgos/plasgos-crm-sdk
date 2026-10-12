// Packs the built package, installs the tarball into a throwaway project, and
// verifies it is usable from BOTH CommonJS (`require`) and ESM (`import`),
// including the `plasgos-crm-sdk/webhooks` subpath.
//
// Run: npm run smoke   (assumes `npm run build` already produced dist/)

import { execFileSync } from "node:child_process";
import { mkdtempSync, writeFileSync, rmSync, readdirSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";

const pkgDir = new URL("..", import.meta.url).pathname;
const run = (cmd, args, cwd) =>
  execFileSync(cmd, args, { cwd, stdio: ["ignore", "pipe", "inherit"] }).toString().trim();

const work = mkdtempSync(join(tmpdir(), "plasgos-sdk-smoke-"));
let ok = false;
try {
  // 1. pack
  run("npm", ["pack", "--pack-destination", work], pkgDir);
  const tgz = readdirSync(work).find((f) => f.endsWith(".tgz"));
  if (!tgz) throw new Error("npm pack produced no tarball");

  // 2. throwaway consumer project
  writeFileSync(join(work, "package.json"), JSON.stringify({ name: "smoke", private: true }));
  run("npm", ["install", "--no-audit", "--no-fund", "--silent", join(work, tgz)], work);

  // 3a. CommonJS consumer
  writeFileSync(
    join(work, "cjs.cjs"),
    `const sdk = require("plasgos-crm-sdk");
     const wh = require("plasgos-crm-sdk/webhooks");
     const a = require("node:assert");
     a.equal(typeof sdk.PlasgosCrmClient, "function");
     a.equal(typeof sdk.PlasgosCrmCredentialsClient, "function");
     a.equal(sdk.computeSignature("test_key","test_secret",1700000000),
       "e18a0c152134399f8efa60f058ced975a2f24657e231c3bd95351d527a3c1fcb");
     a.equal(typeof wh.verifyWebhook, "function");
     a.equal(wh.WebhookEventName.MESSAGE_RECEIVED, "message.received");
     new sdk.PlasgosCrmClient("k","s",{baseUrl:"sandbox"}).official.broadcasts;
     // API v3 (2.x)
     a.equal(typeof sdk.Client, "function");
     a.equal(typeof new sdk.Client("k","s",{baseUrl:"sandbox"}).contacts.upsert, "function");
     a.equal(sdk.signV3("test_secret",1700000000,"GET","/api/v3/me",""),
       "v1=78a0135c200caa6b0c92f57344a7dfa4e4ae41b7c6eff8912d06116dc44d6289");
     a.deepEqual(sdk.Content.text("Halo"), { type: "text", text: { body: "Halo", preview_url: false } });
     a.equal(typeof wh.Webhook.verify, "function");
     console.log("  require() OK");`,
  );
  run("node", ["cjs.cjs"], work);

  // 3b. ESM consumer
  writeFileSync(
    join(work, "esm.mjs"),
    `import { PlasgosCrmClient, computeSignature, PlasgosApiError } from "plasgos-crm-sdk";
     import { verifyWebhook, WebhookEventName } from "plasgos-crm-sdk/webhooks";
     import a from "node:assert";
     a.equal(typeof PlasgosCrmClient, "function");
     a.ok(PlasgosApiError);
     a.equal(computeSignature("test_key","test_secret",1700000000),
       "e18a0c152134399f8efa60f058ced975a2f24657e231c3bd95351d527a3c1fcb");
     a.equal(WebhookEventName.MESSAGE_STATUSES, "message.statuses");
     const e = verifyWebhook({"x-hub-token":"t"}, JSON.stringify({event:"message.statuses"}), {expectedToken:"t"});
     a.equal(e.event, "message.statuses");
     // API v3 (2.x)
     const { Client, Webhook, WebhookEventType } = await import("plasgos-crm-sdk");
     a.equal(typeof new Client("k","s").messages.send, "function");
     const signed = Webhook.signatureHeaders("evt_1", 1700000000, '{"id":"evt_1","type":"webhook.test"}', "whsec_x");
     a.equal(Webhook.verify('{"id":"evt_1","type":"webhook.test"}', signed, "whsec_x", { now: 1700000000 }).type, WebhookEventType.WEBHOOK_TEST);
     console.log("  import   OK");`,
  );
  run("node", ["esm.mjs"], work);

  ok = true;
  console.log("smoke-consume: CommonJS + ESM consumption verified");
} finally {
  rmSync(work, { recursive: true, force: true });
}
process.exit(ok ? 0 : 1);
