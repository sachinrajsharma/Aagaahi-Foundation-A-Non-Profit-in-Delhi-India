// lib/config.js — read-once view of environment configuration.
require('dotenv').config();

const bool = (v, d = false) => (v == null ? d : /^(1|true|yes|on)$/i.test(String(v).trim()));
const num  = (v, d) => (Number.isFinite(parseInt(v)) ? parseInt(v) : d);

module.exports = {
  env:        process.env.NODE_ENV || 'development',
  port:       num(process.env.PORT, 4200),
  baseUrl:    (process.env.BASE_URL || 'http://localhost:4200').replace(/\/+$/, ''),
  sessionSecret: process.env.SESSION_SECRET || 'insecure-dev-secret-change-me',
  uploadMaxBytes: num(process.env.UPLOAD_MAX_MB, 8) * 1024 * 1024,
  trackingRetentionDays: num(process.env.TRACKING_RETENTION_DAYS, 730),
  ai: {
    enabled: !!process.env.ANTHROPIC_API_KEY,
    apiKey:  process.env.ANTHROPIC_API_KEY || '',
    model:   process.env.AI_MODEL || 'claude-sonnet-5',
  },
};
