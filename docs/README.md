# Documentation guide

This folder contains the project truth for the BSIT Academic LMS.

## Source of truth

| File | Responsibility |
|---|---|
| `plan.md` | Product scope, roles, workflows, security rules, and acceptance criteria |
| `design.md` | Visual identity, page behavior, accessibility, responsive rules, and UI states |
| `architecture.md` | Laravel architecture, database model, authorization, payments, storage, and deployment |
| `folder-structure.md` | Target Laravel folder tree and file-placement rules |
| `development-roadmap.md` | Beginner learning path and incremental implementation order |
| `project-audit.md` | Current repository state and known gaps |
| `deployment.md` | Production setup, verification, backups, and rollback runbook |
| `defense.md` | SIA1 evidence pack: diagrams, matrix, checklist, tradeoffs, limitations |
| `technology-choice.md` | Laravel decision, alternatives, SIA1 fit, and external reference assessment |
| `glossary.md` | Beginner-friendly definitions |

## Reading order for a beginner

Read the files in this order:

1. `glossary.md`
2. `technology-choice.md`
3. `plan.md`
4. `design.md`
5. `architecture.md`
6. `folder-structure.md`
7. `development-roadmap.md`

## Reading order for a developer

Before implementing a feature, read:

1. The relevant section in `plan.md`
2. The relevant section in `architecture.md`
3. The file-placement rule in `folder-structure.md`
4. The current milestone in `development-roadmap.md`
5. Existing source and tests for the affected feature

## Reading order for an SIA1 defense

Start with `defense.md`. It is written in presentation order and gathers the
evidence from the other documents.

- `defense.md` for the diagrams, the authorization matrix, the security
  checklist, the tradeoffs, and the honest limitations
- `technology-choice.md` for the technology decision
- `architecture.md` for the full technical detail behind each diagram
- `plan.md` for requirements and use cases
- `development-roadmap.md` for implementation and testing evidence
- `project-audit.md` for current limitations and risk awareness
- `deployment.md` for the production release and rollback steps

## Change rules

- Put product requirements in `plan.md`.
- Put UI decisions in `design.md`.
- Put technical decisions in `architecture.md`.
- Put current repository facts in `project-audit.md`.
- Do not create duplicate PRD, architecture, or domain files.
- Do not add features without approved evidence.
- Mark unresolved choices as deferred with a safe default.
- Keep examples separate from confirmed requirements.

## Project status

V1 is built. Every phase from the Laravel foundation through Phase 15 is
implemented, tested, and committed for IT Learning Hub.

The approved target stack is Laravel 13, PHP 8.4 to 8.5, Blade, Tailwind CSS, MySQL, Laravel authentication and authorization, Laravel Storage, PayMongo, Composer, and Git.

Two steps stay outside the repository: placing the release on a hosting
account, and confirming one real test-mode payment with live credentials. Both
are written up in `deployment.md`.

`FOR_UI/adminator (FOR USER DASHBOARD)` remains a read-only visual reference. It is not application source code.
