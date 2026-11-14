// lib/auth.js — staff passwords, roles and per-site access for the admin dashboard.
//
// Passwords use bcrypt. Older accounts that still carry a plain SHA-256 hash can sign in once and are upgraded
// to bcrypt automatically on that login.
//
// Roles (what each may do):
//   super_admin : everything, including sites/API keys and other super admins
//   admin       : manage staff (not super admins), posts, images, gallery, analytics  — only on sites they are given
//   editor      : posts, images, gallery                                              — only on sites they are given
const bcrypt = require('bcryptjs');
const crypto = require('crypto');
const pool   = require('./db');
const { sha256, safeEqual } = require('./util');

const ROLES = ['super_admin', 'admin', 'editor'];
const PERMS = {
  super_admin: ['users', 'sites', 'posts', 'media', 'gallery', 'insights'],
  admin:       ['users', 'posts', 'media', 'gallery', 'insights'],
  editor:      ['posts', 'media', 'gallery'],
};
const can = (role, perm) => !!(PERMS[role] && PERMS[role].indexOf(perm) !== -1);

const hashPassword = (plain) => bcrypt.hash(String(plain), 12);

// -> { ok, upgrade }   upgrade=true when the stored value was the legacy SHA-256 and should be re-saved as bcrypt.
async function verifyPassword(plain, stored) {
  plain = String(plain || ''); stored = String(stored || '');
  if (stored.startsWith('$2')) return { ok: await bcrypt.compare(plain, stored), upgrade: false };
  if (/^[a-f0-9]{64}$/i.test(stored)) return { ok: safeEqual(sha256(plain), stored.toLowerCase()), upgrade: true };
  return { ok: false, upgrade: false };
}

// 12 characters, no look-alikes (no 0/O, 1/l/I), easy to read out to a colleague.
function tempPassword() {
  const set = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
  let out = '';
  for (let i = 0; i < 12; i++) out += set[crypto.randomInt(set.length)];
  return out;
}

const validEmail = (e) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(e || ''));

// Sites this staff member may work on.
async function sitesFor(admin) {
  if (!admin) return [];
  if (admin.role === 'super_admin') {
    const [rows] = await pool.query('SELECT * FROM sites ORDER BY created_at DESC');
    return rows;
  }
  const [rows] = await pool.query(
    'SELECT s.* FROM sites s JOIN admin_sites a ON a.site_id = s.id WHERE a.admin_id = ? ORDER BY s.created_at DESC', [admin.id]);
  return rows;
}

// Express middleware: require a permission for the signed-in admin.
function requirePerm(perm) {
  return function (req, res, next) {
    const a = req.session && req.session.admin;
    if (a && can(a.role, perm)) return next();
    if (req.accepts('html')) return res.status(403).render('error', { title: 'No access', message: 'Your role does not have access to this page.' });
    return res.status(403).json({ error: 'Forbidden' });
  };
}

module.exports = { ROLES, PERMS, can, hashPassword, verifyPassword, tempPassword, validEmail, sitesFor, requirePerm };
