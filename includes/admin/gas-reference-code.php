<?php
/**
 * Google Apps Script Reference Code
 * Returns the GAS code as a string for the settings page.
 */

if (!defined('ABSPATH')) { exit; }

return <<<'GAS'
/**
 * Elementor Webhook -> Google Sheets (ALWAYS-ON DEBUG when DEBUG.ENABLED = true)
 *
 * IMPORTANT DEPLOYMENT NOTE:
 * - Editing Code.gs does NOT update your live webhook until you redeploy.
 * - Deploy -> Manage deployments -> Edit -> New version -> Deploy.
 *
 * WEB APP SETTINGS:
 * - Execute as: Me
 * - Who has access: Anyone (or Anyone with the link)
 * - Elementor uses the /exec URL (not /dev)
 */

const CONFIG = {
  SPREADSHEET_ID: "1ci1sKv0sZnyyg0iGGozWrDL5H32up7xOH_AbpeAFO68",
  INTAKE_SHEET_NAME: "Sheet1",
  HEADER_ROW: 1,
  DEBUG_SHEET_PREFIX: "DBG_",
  DEBUG_SUMMARY_SHEET_NAME: "DEBUG_LOG",
  DEBUG_DUMP_SHEET_NAME: "DEBUG_DUMPS",
};

const DEBUG = {
  ENABLED: false,
  CREATE_NEW_SHEET_PER_HIT: false,
  WRITE_SUMMARY_ROW: false,
  WRITE_DUMPS_WHEN_SHEET_FAILS: false,
  CHUNK_SIZE: 30000,
  SAMPLE_CONTENT_TYPE: "application/x-www-form-urlencoded",
  SAMPLE_POST_BODY: "",
};

const STATIC_COLUMN_VALUES = {
  signed_by: "michaelperes.com-join-tmpp-script",
  website_importable: "N",
  delivery_importable: "N",
};

const COLUMN_RULES = {
  title: {
    type: "computed",
    fn: (ctx) => {
      const fullName = computeFullName_(ctx);
      return fullName ? ("A Conversation with " + fullName) : "A Conversation";
    },
  },
  guest: {
    type: "computed",
    fn: (ctx) => computeFullName_(ctx),
  },
  whatsapp:   { type: "elementor_first", key: "fields[whatsapp][value]" },
  outreach_agent_name:   { type: "elementor_first", key: "fields[pr_agent_name][value]" },
  emails:               { type: "elementor_first", key: "fields[email][value]" },
  submission_questions: { type: "elementor_first", key: "fields[questions][value]" },
  submission_biography: { type: "elementor_first", key: "fields[biography][value]" },
  submission_website:   { type: "elementor_first", key: "fields[guest_website][value]" },
  submission_service_request: {
    type: "computed",
    fn: (ctx) => (Array.isArray(ctx.crucial.services) ? ctx.crucial.services : []).join(", "),
  },
  submission_date: {
    type: "computed",
    fn: (ctx) => ctx.receivedAt,
  },
};

function doPost(e) {
  return handleWebhook_(e, { source: "live_webhook" });
}

function doGet(e) {
  if (e && e.parameter && e.parameter.ping === "1") {
    return handleWebhook_(e, { source: "ping_test" });
  }
  return ContentService.createTextOutput("Webhook endpoint is live. POST to submit data.");
}
GAS;
