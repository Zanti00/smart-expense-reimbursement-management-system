# Agent Setup Guides — Canonical Homes

> **Last updated:** Sept 6, 2026

Each capstone project ships its own one-shot subagent setup guide **inside its own repo** at `docs/agent-setup/`. Feed the guide to a subagent to get a fully working local stack from a bare machine — the subagent handles everything and stops only for secret `.env` values (`USER-INPUT-GATE`s).

| Project | Canonical guide (lives in that project's repo) |
| :--- | :--- |
| `capstone-auth-module` | `docs/agent-setup/SETUP-01-capstone-auth-module.md` (typical path `C:\Projects\capstone-auth-module`) |
| `ocr-pipeline` | `docs/agent-setup/SETUP-02-ocr-pipeline.md` (typical path `C:\Projects\ocr-pipeline`) |
| SERMS (this repo) | [`SETUP-03-serms.md`](SETUP-03-serms.md) |

Recommended bring-up order: auth → OCR → SERMS (auth and SERMS both bind host port `8000` — do not run both at once without remapping; see any guide's port-conflict warning).

> **Single-source rule:** the guide in each project's repo is the only canonical copy. Do not duplicate guides across repos — cross-link here instead.
