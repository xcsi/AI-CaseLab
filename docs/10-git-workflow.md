# AI CaseLab — Git & GitHub Workflow

## Branch Strategy

- **`main`** — always deployable. Only receives merges from `develop` at milestone/release boundaries. Never committed to directly.
- **`develop`** — integration branch. Feature branches merge here; this is the "current state of the implementation phase."
- **`feature/<phase-number>-<short-name>`** — one per roadmap phase or sub-feature, e.g. `feature/01-laravel-setup`, `feature/07-evidence-log-viewer`. Branched from `develop`, merged back via PR (even if self-reviewed, to keep a clean merge-commit history and PR description trail).

```
main
 └─ develop
     ├─ feature/01-laravel-setup
     ├─ feature/02-auth-roles
     ├─ feature/03-database-schema
     └─ ...
```

Hotfixes directly against `main` (`hotfix/<name>`) are reserved for post-release production bugs — not expected during the initial build-out.

## Commit Convention — Conventional Commits

Format: `<type>(<scope>): <short summary>`

| Type | Use for |
|---|---|
| `feat` | A new feature |
| `fix` | A bug fix |
| `docs` | Documentation only |
| `refactor` | Code change that neither fixes a bug nor adds a feature |
| `test` | Adding/correcting tests |
| `chore` | Tooling, config, dependency bumps |
| `style` | Formatting only, no logic change |

Scope = the phase/module, e.g. `feat(auth): add role-based middleware`, `docs(roadmap): add phase 7 evidence management plan`.

Rules: one logical change per commit; never mix a feature with an unrelated formatting sweep; commit message body explains *why* when the change isn't self-evident from the diff.

## End-of-Milestone Checklist (used after every phase)

1. **Files created** — list.
2. **Files modified** — list.
3. **Git commit message(s)** — Conventional Commits, one per logical change.
4. **Suggested branch name** — `feature/<n>-<name>`.
5. **Changelog entry** — appended to `CHANGELOG.md` under `[Unreleased]`.
6. **Docs updated** — if the implementation changed anything in `01`–`09`, they're updated in the same milestone, not deferred.
7. **Push checklist** — presented to the user; nothing is pushed without explicit approval:
   - [ ] `develop` is up to date locally
   - [ ] Feature branch rebased/merged cleanly onto `develop`
   - [ ] Tests pass locally
   - [ ] User has approved the push
   - [ ] `git push origin <branch>` (only after approval; no force-push ever unless explicitly requested)

## What Happens Automatically vs. What Waits for Approval

| Action | When it happens |
|---|---|
| `git init`, branch creation, `.gitignore`/community files | Once, at repository setup (done in this milestone) |
| Local commits at the end of an approved milestone | Automatic, since the milestone itself was already approved |
| Creating a GitHub remote repository | Requires GitHub CLI (`gh`) authenticated — **not currently available on this machine** (checked: `gh` not found). Will need to be created manually via github.com, or `gh` installed and authenticated, before any push is possible. |
| `git push` to any remote branch | Only after explicit user approval, every time |
| Force-push / history rewrite | Never, unless explicitly requested |

## Recommended GitHub Repository Files (status)

| File | Status |
|---|---|
| `README.md` | Created — kept updated every milestone |
| `LICENSE` (MIT) | Created — replace copyright holder name if you want personal attribution instead of the project name |
| `.gitignore` (Laravel-tailored) | Created |
| `.editorconfig` | Created |
| `.gitattributes` | Created |
| `CONTRIBUTING.md` | Created |
| `CODE_OF_CONDUCT.md` (Contributor Covenant v2.1) | Created |
| `SECURITY.md` | Created |
| `CHANGELOG.md` (Keep a Changelog format) | Created |
| `.github/ISSUE_TEMPLATE/bug_report.md`, `feature_request.md` | Created |
| `.github/PULL_REQUEST_TEMPLATE.md` | Created |
