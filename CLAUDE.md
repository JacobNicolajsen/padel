# Padel Score Tracker

A real-time padel tournament scoring app using Firebase Realtime Database.

## Structure

```
frontend/   — All app files: static HTML/JS pages, PHP API, Firebase config, service worker
```

There is no separate `backend/` folder — `point_api.php` and its `config.php` live in `frontend/` alongside the pages.

### Key files

| File | Purpose |
|---|---|
| `frontend/index.html` | Main entry / home |
| `frontend/live_dommer.html` | Live scoring UI for referees |
| `frontend/admin_games.html` | Admin: create/manage games |
| `frontend/turnering.html` | Unified tournament management (bracket + group/knockout) |
| `frontend/statistik.html` | Statistics page |
| `frontend/point_watch.html` | Read-only score watcher |
| `frontend/point_api.html` | Manual point entry per team (button-based, transaction writes) |
| `frontend/opret_hold.html` | Create teams |
| `frontend/multibaner.html` | Multi-court view |
| `frontend/firebase-config.js` | Firebase client config (shared by all pages) |
| `frontend/point_api.php` | REST API — awards points, runs scoring engine |
| `frontend/service-worker.js` | PWA service worker |
| `frontend/config.php` | **Gitignored** — DB_URL + DB_SECRET (copy from `frontend/config.example.php`) |

## Setup

1. Copy `frontend/config.example.php` → `frontend/config.php` and fill in your Firebase Database Secret.
2. Serve the project from a web server with PHP support (the frontend pages load `firebase-config.js` via relative path).

## Architecture notes

- No build pipeline. All frontend pages are plain HTML with inline `<script>` tags loading Firebase SDK from CDN.
- `firebase-config.js` is the single source of truth for the Firebase client config.
- `point_api.php` is the only server-side component. It reads the current game state from Firebase, applies the scoring engine, and writes back.
- Firebase client API key is intentionally in source control — it is a public browser key. Security is enforced by Firebase Database Rules.
- `DB_SECRET` (legacy Firebase admin credential used by the PHP backend) must stay out of source control — keep it in `frontend/config.php`.

## Data structure rules

- Group tournament matches live under `tournaments/{id}/groups[].matches[]`, KO/bracket matches under `tournaments/{id}/rounds[]`. Read/write logic must handle both.
- All fields needed downstream (`teamAId`, `teamBId`, `playersA`, `playersB`, `name`) must be written at game creation time.
- Bye detection in bracket generation is scoped to round 1 only — never apply it to later rounds.
- Concurrent writers (live_dommer + point_api/point_api.php) must use Firebase transactions or `_meta` sender-tagging; never blind `set()` of full game state.
