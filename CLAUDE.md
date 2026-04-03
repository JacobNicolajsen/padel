# Padel Score Tracker

A real-time padel tournament scoring app using Firebase Realtime Database.

## Structure

```
frontend/   — Static HTML/JS pages (no build step, loaded directly in browser)
backend/    — PHP API + Firebase config
```

### Key files

| File | Purpose |
|---|---|
| `frontend/index.html` | Main entry / home |
| `frontend/live_dommer.html` | Live scoring UI for referees |
| `frontend/admin_games.html` | Admin: create/manage games |
| `frontend/turnering.html` | Tournament bracket view |
| `frontend/statistik.html` | Statistics page |
| `frontend/point_watch.html` | Read-only score watcher |
| `frontend/point_api.html` | Manual point entry (debug) |
| `frontend/opret_hold.html` | Create teams |
| `frontend/multibaner.html` | Multi-court view |
| `backend/firebase-config.js` | Firebase client config (shared by all pages) |
| `backend/point_api.php` | REST API — awards points, runs scoring engine |
| `backend/service-worker.js` | PWA service worker |
| `backend/config.php` | **Gitignored** — DB_URL + DB_SECRET (copy from config.example.php) |

## Setup

1. Copy `backend/config.example.php` → `backend/config.php` and fill in your Firebase Database Secret.
2. Serve the project from a web server with PHP support (the frontend pages load `firebase-config.js` via relative path).

## Architecture notes

- No build pipeline. All frontend pages are plain HTML with inline `<script>` tags loading Firebase SDK from CDN.
- `firebase-config.js` is the single source of truth for the Firebase client config.
- `point_api.php` is the only server-side component. It reads the current game state from Firebase, applies the scoring engine, and writes back.
- Firebase client API key is intentionally in source control — it is a public browser key. Security is enforced by Firebase Database Rules.
- `DB_SECRET` (legacy Firebase admin credential used by the PHP backend) must stay out of source control — keep it in `backend/config.php`.
