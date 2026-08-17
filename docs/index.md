# Documentation Index — SERMS

**Project Name:** Smart Expense & Reimbursement Management System (SERMS)
**Project slug:** `serms`
**Maintained by:** SERMS Engineering Team
**Last updated:** 2026-08-17

---

## 1. Document Suite

| Document                           | File                   | Version | Status | Description                                           | Last Reconciled |
| ---------------------------------- | ---------------------- | ------- | ------ | ----------------------------------------------------- | --------------- |
| **SERMS** — Master Specification   | [SERMS.md](SERMS.md)   | 1.3     | Canonical | Authoritative single source of truth for all SERMS docs & code | 2026-08-17 |
| **PRD** — Product Requirements     | [PRD.md](PRD.md)       | 1.1     | Active | Goals, SBSI client context, Capstone ecosystem, and workflows | 2026-08-17 |
| **SAD** — Software Architecture    | [SAD.md](SAD.md)       | 1.3     | Active | High-level software architecture, ecosystem, and AI model | 2026-08-17 |
| **SDD** — System Design            | [SDD.md](SDD.md)       | 1.3     | Active | Monolithic modular system design, crypto, and OCR flows | 2026-08-17 |
| **DSD** — Design System            | [DSD.md](DSD.md)       | 1.2     | Active | Clinical visual guidelines and component tokens       | 2026-08-17      |
| **PR Guide** — Pull Request Guide | [PR_GUIDE.md](PR_GUIDE.md) | 1.0 | Draft | Guidelines, naming conventions, and best practices for creating PRs | 2026-07-13 |
| **Issue Guide** — Issue Creation Guide | [../documentations/ISSUE_GUIDE.md](../documentations/ISSUE_GUIDE.md) | 1.0 | Draft | Guidelines and templates for reporting bugs and feature requests | 2026-07-13 |
| **Branch Guide** — Branch Creation Guide | [../documentations/BRANCH_GUIDE.md](../documentations/BRANCH_GUIDE.md) | 1.0 | Draft | GitFlow strategy, branch naming, and syncing guidelines | 2026-07-13 |
| **Build** — Build & Setup Guide    | [Build.md](Build.md)   | 1.3     | Active | Environment setup, dev instructions, and golden paths | 2026-08-17      |
| **index** — Documentation Index    | [index.md](index.md)   | 1.3     | Active | Entry point and health status of SERMS documentation  | 2026-08-17      |
| **AGENTS** — Agent Quickstart      | [AGENTS.md](AGENTS.md) | 1.3     | Active | AI agent guidelines derived from Build.md             | 2026-08-17      |
| **OPS** — Operations Runbook       | [OPS.md](OPS.md)       | 1.1     | Active | Azure student infra, SLO targets, and runbooks        | 2026-08-17      |
| **QAD** — QA & Test Plan           | [QAD.md](QAD.md)       | 1.1     | Active | Testing strategy, Vitest/PHPUnit guides, and gates    | 2026-08-17      |
| **CHANGELOG** — Historical Changelog | [CHANGELOG.md](CHANGELOG.md) | 1.3 | Active | Comprehensive historical changelog for agents & developers | 2026-08-17 |

### Materialized Artifacts

These are **generated from canonical docs** — edit the doc and re-materialize; never hand-edit the artifact directly.

| Artifact                                                                                                                            | Canonical Source                              | Description                            |
| ----------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------- | -------------------------------------- |
| `../AGENTS.md` (project-root build rules)                                                                                           | [Build.md](Build.md) / [AGENTS.md](AGENTS.md) | High-level agent quickstart guidelines |

---

## 2. Change Log

All project, architectural, and documentation changes are recorded in **[`docs/CHANGELOG.md`](CHANGELOG.md)**. AI subagents and developers must consult that file for historical context.

---

## 3. Incident Log (Postmortems)

No incidents recorded yet.

---

## 4. Health Check

Quick triage checks for agents running at the start of a session:

- [ ] Every document's **Last Reconciled** date is newer than the last code change to its area.
- [ ] No document has been in `Draft` longer than expected without movement.
- [ ] System architecture and AI governance rules in SAD.md are reconciled with active guidelines.
- [ ] `AGENTS.md` in the project root matches [AGENTS.md](AGENTS.md) / [Build.md](Build.md).
- [ ] Pinned stack versions in the Build Guide match live dependencies (`composer.json`, `package.json`).
- [ ] Every open Postmortem's action items are closed (or tracked somewhere durable).
