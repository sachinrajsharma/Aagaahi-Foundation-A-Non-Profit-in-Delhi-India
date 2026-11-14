// server.js — BlogForge Engine entry point.
// Standalone Blog Dashboard + first-party SEO Analytics that any website
// (PHP, Laravel, Node) uses via a hosted blog (reverse-proxy friendly), a
// headless REST API, sitemap/RSS/JSON-LD, and a Yoast-style SEO assistant.
require('dotenv').config();
const path       = require('path');
const express    = require('express');
const session    = require('express-session');
const MySQLStore = require('express-mysql-session')(session);
const helmet     = require('helmet');
const morgan     = require('morgan');
const ejsLayouts = require('express-ejs-layouts');

const cfg  = require('./lib/config');
const pool = require('./lib/db');
const tracking = require('./lib/tracking');

const app = express();
app.set('trust proxy', 1);
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));
app.use(ejsLayouts);
app.set('layout', 'layout');

app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc: ["'self'", "'unsafe-inline'", 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com'],
      styleSrc: ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com', 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com'],
      fontSrc: ["'self'", 'https://fonts.gstatic.com', 'https://cdnjs.cloudflare.com'],
      imgSrc: ["'self'", 'data:', 'https:'],
      connectSrc: ["'self'"],
    },
  },
  crossOriginResourcePolicy: { policy: 'cross-origin' },  // allow proxied embedding of images
}));
if (cfg.env !== 'test') app.use(morgan('dev'));

app.use(express.json({ limit: '2mb' }));
app.use(express.urlencoded({ extended: true, limit: '2mb' }));
app.use('/uploads', express.static(path.join(__dirname, 'public', 'uploads'), { maxAge: '30d' }));
app.use(express.static(path.join(__dirname, 'public')));

const sessionStore = new MySQLStore({
  host: process.env.DB_HOST || '127.0.0.1',
  port: parseInt(process.env.DB_PORT) || 3306,
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASS || '',
  database: process.env.DB_NAME || 'blogforge',
});
app.use(session({
  key: 'blogforge.sid',
  secret: cfg.sessionSecret,
  store: sessionStore,
  resave: false,
  saveUninitialized: false,
  cookie: { httpOnly: true, secure: cfg.env === 'production', maxAge: 7 * 24 * 60 * 60 * 1000 },
}));

app.use((req, res, next) => {
  res.locals.admin = req.session && req.session.admin || null;
  res.locals.cfg = cfg;
  res.locals.title = 'BlogForge';
  next();
});

app.get('/health', async (req, res) => {
  try { await pool.query('SELECT 1'); res.json({ ok: true, db: true, ts: Date.now() }); }
  catch (e) { res.status(500).json({ ok: false, db: false, error: e.message }); }
});

app.get('/', (req, res) => res.render('home', { title: 'BlogForge Engine' }));

app.use('/api', require('./routes/api'));
app.use('/admin', require('./routes/admin'));
app.use('/', require('./routes/blog'));   // hosted blog + sitemap/rss/robots (site-resolved)

app.use((req, res) => {
  if (req.path.startsWith('/api')) return res.status(404).json({ error: 'Not found.' });
  res.status(404).render('error', { title: 'Not found', message: 'The page you requested does not exist.' });
});
app.use((err, req, res, next) => {
  console.error('[error]', err);
  if (req.path.startsWith('/api')) return res.status(500).json({ error: 'Server error.' });
  res.status(500).render('error', { title: 'Error', message: 'Something went wrong.' });
});

app.listen(cfg.port, async () => {
  console.log(`\nBlogForge Engine running → ${cfg.baseUrl}  (port ${cfg.port}, env ${cfg.env})`);
  try { await tracking.pruneOld(pool); } catch (e) { /* table may not exist yet */ }
  console.log('AI writing assist:', cfg.ai.enabled ? 'enabled' : 'off (set ANTHROPIC_API_KEY)');
});
