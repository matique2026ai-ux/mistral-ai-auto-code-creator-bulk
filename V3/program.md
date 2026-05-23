# AutoCoder V3 — Agent Program

This file defines how autonomous AI agents build and improve web projects.

## Goal

Generate a production-ready, visually stunning web project from a user's mission brief. Each build iterates through 12 pipeline stages with autonomous quality assurance and self-healing.

## Agents & Roles

| Agent | Role | Scope |
|-------|------|-------|
| Researcher | Market & competitor analysis | Niche research, copywriting hooks |
| Architect | System & UI design | Site structure, pages, colors, layout |
| Designer | CSS styling | Visual design, animations, responsiveness |
| Developer | Code generation | Full PHP/HTML pages with config integration |
| QA Reviewer | Code quality inspection | Syntax, links, completeness, aesthetics |

## Rules

1. **One file at a time** — each agent modifies exactly one file per iteration
2. **Fixed budget** — max 5 iterations per QA loop, score target >= 95/100
3. **Keep or discard** — if QA score drops, rollback; if it improves, keep
4. **Never stop** — the loop continues until target is met or max iterations reached
5. **Log everything** — every experiment is recorded in results.tsv

## Experiment Tracking

Each build produces a row in `results.tsv`:

```
commit  qa_score  files  status  description
```

- `commit`: project folder name
- `qa_score`: final QA score (0-100)
- `files`: number of files generated
- `status`: `completed`, `failed`, `partial`
- `description`: what was built

## Stack Constraints

- PHP + SQLite backend only (no MySQL/PostgreSQL)
- Vanilla CSS, Tailwind CDN, or Bootstrap 5
- Languages: English, Arabic (RTL), French, Spanish, German
- Output dir: `builds/site_<timestamp>_<slug>/`
