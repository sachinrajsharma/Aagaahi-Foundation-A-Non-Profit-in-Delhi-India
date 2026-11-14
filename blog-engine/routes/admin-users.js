// routes/admin-users.js — staff accounts: add, change role / site access, reset password, deactivate, delete.
// Mounted inside routes/admin.js (so the admin is already signed in).
const express = require('express');
const pool = require('../lib/db');
const auth = require('../lib/auth');

const router = express.Router();

const intList = (v) => [].concat(v || []).map((x) => parseInt(x, 10)).filter((n) => n > 0);
const roleChoices = (actor) => (actor.role === 'super_admin' ? auth.ROLES : ['admin', 'editor']);
// An admin may manage everyone except super admins; a super admin may manage anyone.
const canManage = (actor, target) => actor.role === 'super_admin' || target.role !== 'super_admin';

async function activeSuperAdmins() {
  const [[r]] = await pool.query("SELECT COUNT(*) n FROM admins WHERE role='super_admin' AND is_active=1");
  return r.n;
}
async function setSites(adminId, siteIds) {
  await pool.query('DELETE FROM admin_sites WHERE admin_id = ?', [adminId]);
  for (const sid of siteIds) await pool.query('INSERT IGNORE INTO admin_sites (admin_id, site_id) VALUES (?, ?)', [adminId, sid]);
}
function flash(req, kind, text, extra) { req.session.flash = Object.assign({ kind, text }, extra || {}); }

router.get('/users', async (req, res) => {
  const [users] = await pool.query(
    "SELECT a.id, a.name, a.email, a.role, a.is_active, a.created_at, GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') AS site_names, GROUP_CONCAT(s.id) AS site_ids " +
    'FROM admins a LEFT JOIN admin_sites x ON x.admin_id = a.id LEFT JOIN sites s ON s.id = x.site_id GROUP BY a.id ORDER BY a.created_at');
  const [allSites] = await pool.query('SELECT id, name FROM sites ORDER BY name');
  const f = req.session.flash || null; req.session.flash = null;
  res.render('admin/users', { layout: 'layout', title: 'Staff & access', users, allSites, roles: roleChoices(req.session.admin), flash: f, me: req.session.admin });
});

router.post('/users', async (req, res) => {
  const actor = req.session.admin, b = req.body || {};
  const name = String(b.name || '').trim().slice(0, 120), email = String(b.email || '').trim().toLowerCase();
  const role = roleChoices(actor).includes(b.role) ? b.role : 'editor';
  if (!name || !auth.validEmail(email)) { flash(req, 'err', 'Please enter a name and a valid email address.'); return res.redirect('/admin/users'); }
  const [[dup]] = await pool.query('SELECT id FROM admins WHERE email = ?', [email]);
  if (dup) { flash(req, 'err', 'That email already has an account.'); return res.redirect('/admin/users'); }
  let plain = String(b.password || '');
  const generated = !plain;
  if (generated) plain = auth.tempPassword();
  else if (plain.length < 10) { flash(req, 'err', 'A password you choose must be at least 10 characters (or leave it empty to generate one).'); return res.redirect('/admin/users'); }
  const [ins] = await pool.query('INSERT INTO admins (name, email, password_hash, role, is_active) VALUES (?,?,?,?,1)', [name, email, await auth.hashPassword(plain), role]);
  if (role !== 'super_admin') await setSites(ins.insertId, intList(b.sites));
  flash(req, 'ok', 'Account created for ' + email + '.', generated ? { temp: plain, tempFor: email } : {});
  res.redirect('/admin/users');
});

router.post('/users/:id/update', async (req, res) => {
  const actor = req.session.admin, id = parseInt(req.params.id, 10), b = req.body || {};
  const [[t]] = await pool.query('SELECT * FROM admins WHERE id = ?', [id]);
  if (!t || !canManage(actor, t)) { flash(req, 'err', 'You cannot change that account.'); return res.redirect('/admin/users'); }
  const role = roleChoices(actor).includes(b.role) ? b.role : t.role;
  const active = b.is_active ? 1 : 0;
  if (id === actor.id && (role !== t.role || !active)) { flash(req, 'err', 'You cannot change your own role or deactivate yourself.'); return res.redirect('/admin/users'); }
  if (t.role === 'super_admin' && (role !== 'super_admin' || !active) && (await activeSuperAdmins()) <= 1) { flash(req, 'err', 'There must always be at least one active super admin.'); return res.redirect('/admin/users'); }
  await pool.query('UPDATE admins SET role = ?, is_active = ? WHERE id = ?', [role, active, id]);
  await setSites(id, role === 'super_admin' ? [] : intList(b.sites));
  flash(req, 'ok', 'Saved ' + t.email + '.');
  res.redirect('/admin/users');
});

router.post('/users/:id/password', async (req, res) => {
  const actor = req.session.admin, id = parseInt(req.params.id, 10);
  const [[t]] = await pool.query('SELECT * FROM admins WHERE id = ?', [id]);
  if (!t || !canManage(actor, t)) { flash(req, 'err', 'You cannot change that account.'); return res.redirect('/admin/users'); }
  const plain = auth.tempPassword();
  await pool.query('UPDATE admins SET password_hash = ? WHERE id = ?', [await auth.hashPassword(plain), id]);
  flash(req, 'ok', 'New temporary password set for ' + t.email + '. Share it privately and ask them to change it.', { temp: plain, tempFor: t.email });
  res.redirect('/admin/users');
});

router.post('/users/:id/delete', async (req, res) => {
  const actor = req.session.admin, id = parseInt(req.params.id, 10);
  const [[t]] = await pool.query('SELECT * FROM admins WHERE id = ?', [id]);
  if (!t || !canManage(actor, t)) { flash(req, 'err', 'You cannot delete that account.'); return res.redirect('/admin/users'); }
  if (id === actor.id) { flash(req, 'err', 'You cannot delete your own account.'); return res.redirect('/admin/users'); }
  if (t.role === 'super_admin' && t.is_active && (await activeSuperAdmins()) <= 1) { flash(req, 'err', 'There must always be at least one active super admin.'); return res.redirect('/admin/users'); }
  await pool.query('DELETE FROM admins WHERE id = ?', [id]); // admin_sites rows cascade
  flash(req, 'ok', 'Deleted ' + t.email + '.');
  res.redirect('/admin/users');
});

module.exports = router;
