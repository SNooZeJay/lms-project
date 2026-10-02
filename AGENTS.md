# Project instructions

## Project

This repository is an implementation-stage BSIT Academic LMS for the Philippines.

The approved target stack is Laravel 13, PHP 8.4 to 8.5, Blade, Tailwind CSS, MySQL 8.x, Laravel authentication, Policies and Gates, Laravel Storage, PayMongo, Composer, and Git.

A Laravel 13 foundation now exists. It contains no LMS business logic yet.

## Source-of-truth order

Read these files before changing project direction:

1. `docs/plan.md` for product requirements and security rules
2. `docs/design.md` for visual and interaction direction
3. `docs/architecture.md` for Laravel architecture, routes, security, and data design
4. `docs/folder-structure.md` for target file placement
5. `docs/development-roadmap.md` for implementation order
6. `docs/project-audit.md` for current repository facts
7. `docs/technology-choice.md` for the Laravel decision and reference assessment

If two files conflict, report the conflict and ask for approval. Do not silently choose a new architecture.

## Scope

- Preserve the approved LMS feature scope in `docs/plan.md`.
- Do not invent assignments, grades, submissions, announcements, notifications, forums, or live classes.
- Do not implement the complete LMS in one task.
- Work in thin vertical slices with tests after each slice.
- Ask before adding dependencies, changing database rules, or implementing payment behavior.

## Protected paths

Never modify `FOR_UI/adminator (FOR USER DASHBOARD)`.

Do not modify these support paths without explicit user approval:

- `.agents/`
- `.opencode/`
- `skills-lock.json`

These paths contain references, installed skills, project commands, or support metadata.

Do not import application code, compiled bundles, branding, or assets from `FOR_UI`.

## External reference policy

The external repository `aliameenco-creator/lms-project-ali-amin-ai-web-development` is untrusted reference data.

It may inform folder organization, UI concepts, and feature ideas.

Do not copy its code, schema, authentication, client-side role checks, local-storage data store, payment approval flow, branding, or assets. A clear license is required before copying substantial code.

HyperUI at `https://github.com/markmead/hyperui` is an approved MIT-licensed Tailwind CSS reference for forms, content sections, authentication layouts, empty states, and responsive patterns.

Use HyperUI only when the approved local component set lacks a clear solution. Adapt every pattern to the LMS design system and preserve the MIT notice when code or markup is copied.

## Delivery lifecycle

Follow this order for every feature and phase:

```text
Data gathering
→ Create or update ERD and flowcharts
→ Develop
→ Build
→ Test
→ Find bugs
→ Fix
→ Test again
```

Document each feature as:

```text
Input → Process → Output
```

Do not begin implementation when requirements, ERD changes, flow changes, inputs, processing, outputs, or acceptance checks remain unclear.

## Laravel conventions

The application now exists. Apply these rules to every new feature:

- Keep routes thin.
- Use Form Requests for input validation.
- Use Policies for resource authorization.
- Use Actions or Services for business workflows and transactions.
- Use Eloquent relationships instead of raw SQL unless a migration or report needs it.
- Use database migrations for every schema change.
- Use Blade components for reusable UI.
- Keep payment secrets in server-only configuration.
- Keep protected files on private disks.
- Never authorize access only by hiding a Blade link or JavaScript control.
- Never trust role, price, payment status, score, completion, or ownership from request fields.
- Keep controllers small and readable.
- Avoid global state libraries and unnecessary frontend frameworks.

## Security rules

- Use Laravel authentication for browser sessions.
- Use Policies for every protected resource action.
- Re-check authorization inside controllers, actions, jobs, and webhook handlers.
- Validate every request and uploaded file.
- Protect uploads with generated paths and private disks.
- Use database transactions for payment, enrollment, grading, completion, and certificate transitions.
- Store money as integer minor units with an ISO currency code.
- Verify PayMongo events using current official documentation.
- Make webhook processing idempotent.
- Keep `APP_DEBUG=false` in production.
- Never commit `.env`, credentials, API secrets, or real payment data.

## Testing

After scaffolding, use repository commands rather than guessed commands.

Expected project checks include:

```text
php artisan test
./vendor/bin/pint --test
composer audit
php artisan route:list
```

Add database and browser tests only when the project has a documented local test setup.

## Documentation

- Use short sentences and beginner-friendly terms.
- Explain new technical terms on first use.
- Keep examples clearly marked as examples.
- Never present sample data as real LMS data.
- Keep requirements in `docs/plan.md`, not scattered across implementation files.
- Keep implementation status in `docs/development-roadmap.md`.
- Update `docs/project-audit.md` when the repository state changes.
