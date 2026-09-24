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

Use these documents as evidence:

- `technology-choice.md` for the technology decision
- `architecture.md` for system context, data flow, and deployment diagrams
- `plan.md` for requirements and use cases
- `development-roadmap.md` for implementation and testing evidence
- `project-audit.md` for current limitations and risk awareness

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

Phase 1 Laravel foundation implementation is complete. Phase 2 authentication and profile specification is approved for IT Learning Hub.

The approved target stack is Laravel 13, PHP 8.3 to 8.5, Blade, Tailwind CSS, MySQL, Laravel authentication and authorization, Laravel Storage, PayMongo, Composer, and Git.

`FOR_UI/adminator (FOR USER DASHBOARD)` remains a read-only visual reference. It is not application source code.
