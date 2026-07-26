# Contributing to AI CaseLab

Thank you for your interest in AI CaseLab. This project is developed following
professional open-source conventions even during its initial single-maintainer
build-out, so the workflow below applies from day one.

## Project Documentation

Before contributing, read the design documents in `docs/` — they are the
source of truth for architecture, database schema, and process:

- `01-business-requirements.md` – `02-srs.md` – `03-database-design.md`
- `04-architecture.md` – `05-ui-ux-design.md`
- `08-implementation-rules.md` – coding standards and process rules
- `09-workplace-terminology.md` – required UI language conventions
- `10-git-workflow.md` – full branch/commit workflow

## Branching

- `main` — always deployable, protected.
- `develop` — integration branch for ongoing work.
- `feature/<phase-number>-<short-name>` — all work happens on a feature branch
  cut from `develop`.

See `docs/10-git-workflow.md` for the full strategy.

## Commit Messages

This project uses [Conventional Commits](https://www.conventionalcommits.org/):

```
feat(scope): add new capability
fix(scope): correct a bug
docs(scope): documentation only
refactor(scope): no behavior change
test(scope): add or fix tests
chore(scope): tooling/config/dependencies
```

Keep commits small and focused — one logical change per commit. Do not mix an
unrelated formatting pass into a feature commit.

## Code Standards

- **PHP**: PSR-12, enforced via [Laravel Pint](https://laravel.com/docs/pint).
  Run `./vendor/bin/pint` before committing.
- **Architecture**: thin controllers, business logic in Services, Repositories
  only for the domain aggregates listed in `04-architecture.md`, Form Requests
  for validation, Policies for authorization. See `08-implementation-rules.md`
  for the full rule set — pull requests that don't follow it will be asked to
  revise.
- **Naming**: match the vocabulary already established in the docs. UI-facing
  copy follows `09-workplace-terminology.md`; code/domain naming stays
  technical (see that document for why the two differ deliberately).
- **Tests**: new features include feature/unit test coverage. Run the suite
  with `php artisan test` before opening a pull request.

## Pull Requests

- Target `develop`, not `main`.
- Use the PR template — describe what changed and why, link the roadmap phase
  or issue it addresses, and confirm tests pass.
- Keep PRs scoped to a single feature or fix.

## Reporting Issues

Use the GitHub issue templates (`Bug Report` / `Feature Request`). For
security vulnerabilities, follow `SECURITY.md` instead of opening a public
issue.
