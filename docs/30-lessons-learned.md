# 30 — Lessons Learned

> **Audience note:** this document was specifically requested in expanded form by the project's academic supervisor, in addition to its place in the standard documentation index. It is written as a professional engineering retrospective — lessons about process, judgment, and collaboration, not only technical bugs.
> **Related:** [21-problems-and-solutions](21-problems-and-solutions.md) · [20-design-decisions](20-design-decisions.md) · [18-development-phases](18-development-phases.md)

## Software Architecture

**Patterns are tools for managing complexity, not a checklist to apply uniformly.** The Repository Pattern was deliberately withheld from `Category`, `Role`, and `EvidenceType` — trivial lookup tables — while applied to the six aggregates with real query complexity. The disciplined part wasn't choosing the pattern; it was stating, explicitly and in writing, *where the line is* and why, so the decision could be defended and reused consistently by whoever touches the schema next, rather than re-litigated ad hoc each time.

**A module boundary is worth defending even when it costs a little convenience.** `app/Discussion/` depends on Version 1 through exactly one read-only adapter (`CaseAttemptDiscussionSubject`), and Version 1 depends on it not at all. The one place this boundary was bent — `DiscussionService` reading `$attempt->case->model_solution_summary` directly for `LeakageGuard`, bypassing the subject abstraction — was done deliberately, with the reason documented in the class's own docblock, rather than either (a) quietly violating the boundary everywhere it was inconvenient, or (b) adding an interface method for one narrow caller that would have bloated `DiscussionSubjectInterface` for every future implementation. Knowing when a small, documented exception is the right call — versus a slippery slope — is a judgment skill, not a rule.

**Reuse a proven pattern's *shape*, not necessarily its code.** The Discussion Engine's persona/provider abstractions are structurally identical in spirit to the Evaluation Engine's Strategy pattern (one interface, swappable implementations, a resolver), built four phases and roughly two weeks later by people who had internalized why the first one worked well. This is a healthy kind of pattern reuse — recognizing "this is the same shape of problem" — distinct from copy-pasting code, which was not done.

## AI Integration

**Cost-safety and provider-agnosticism as structural guarantees, not runtime checks, was the single highest-leverage architectural decision in the AI subsystem.** A runtime check ("if this flag is true, don't call the paid client") can have a bug. A structural guarantee (the paid client's constructor call has exactly one call site, lexically inside the guard — there is no code path that reaches it otherwise) can't be bypassed by a bug in a *different* part of the code, only by a bug in that one guarded line itself, which is trivially small to review. This distinction — provable by reading the code, not just by testing its behavior — is worth applying to any future "must never happen" requirement, not just this one.

**API conformance and behavioral conformance are different questions, and testing one doesn't answer the other.** Every provider client has thorough `Http::fake()`-based unit tests proving correct request shape and response parsing. None of that testing could ever have caught the real Ollama `/v1`-suffix bug (a network-shape issue, not a parsing issue) or the OpenRouter model's tendency to attempt a prompt-injection compliance (a behavioral issue, not an API issue). Both were caught only by a separate, deliberately-real, deliberately-costly conformance harness. The lesson: for any system that talks to an external, non-deterministic model, budget for a distinct validation activity beyond unit testing, and treat "the API test suite is green" as necessary, not sufficient.

**A "safe fallback on failure" needs its own regression tests, not just a happy-path test.** The empty-AI-reply bug lived in a fallback path that existed specifically to degrade gracefully — and the fallback itself had a latent defect (setting `reply_text` to a genuinely empty raw string) that only a real reasoning-model's real behavior exposed. The fix's regression tests (a truly-empty case and a whitespace-only case, specifically) are now permanent proof that this exact failure mode can't silently regress — a concrete argument for testing failure/degradation paths as rigorously as success paths, not as an afterthought.

## Testing Strategy

**"Run it in isolation, then run the full suite" catches state-leakage bugs before they become a flaky-suite problem — but it also means investigating every anomaly, not just the ones that look scary.** The Phase 13 Faker flake (an unrelated test failing once) was investigated with the same rigor as a real regression would have been — re-run in isolation, re-run the full suite twice more — before being confidently attributed to test-data randomness rather than the change under review. The discipline is in doing that check *every time*, not just when a failure looks suspicious.

**Coverage gaps hide in "we tested the group, so every member is covered" reasoning.** Both the Phase 12 authorization audit and the Phase 17 Discussion-routes audit found the identical class of gap: a shared middleware protects several routes, one route in the group has an explicit test proving the middleware works, and the others were assumed covered by inference rather than proven individually. The second occurrence, in a completely different subsystem months later, shows this isn't a one-off oversight but a recurring failure mode worth checking for deliberately whenever a new protected route group is added — which is now a standing practice, not a hope.

**Network-free testing for a non-deterministic external dependency is worth the up-front investment.** Building `FakeLlmClient` and wiring it through a single service-provider binding switch (bound only in the testing environment) meant the entire Discussion module — 100+ tests by the end of Version 2 — never once depended on network access, API keys, or provider quotas to run. This made the whole subsystem's test suite exactly as fast, free, and deterministic as the rest of the application's, despite sitting in front of a fundamentally non-deterministic external system.

## Debugging

**Verify each layer independently before assuming where a bug lives.** The empty-AI-reply investigation explicitly ruled out the frontend by checking three independent sources of truth (real database data, the exact fallback code path, a live raw request/response capture) before concluding the server was persisting a genuinely empty value. This is slower than guessing, but it means the fix targeted the actual cause on the first attempt rather than iterating through wrong hypotheses.

**When a fix's *cause* is ambiguous between two explanations, run a controlled experiment to distinguish them — don't just ship the fix and move on.** After patching the empty-reply symptom, a second, separate experiment (installing a non-reasoning model and testing against the *unmodified* code) confirmed the root cause was model-architecture-specific rather than a general Ollama-integration defect. Shipping only the symptom fix without that follow-up would have left a real open question — "is this going to happen with every Ollama model?" — unanswered.

**A manual verification mistake is still worth documenting, even when the shipped code was never wrong.** The `->update()` silently no-op'ing on a non-fillable column (Phase 18 Milestone 1) was a mistake in the *test setup*, not the product — but it was recorded in the commit anyway, because the reason it happened (mass-assignment guards silently no-op rather than error) is a real Laravel behavior worth remembering the next time a manual check needs to mutate a deliberately-unguarded column.

## Documentation

**Documentation that's produced *during* implementation, not reconstructed after, is dramatically higher-fidelity.** This entire documentation package was written by mining `CHANGELOG.md` and the git commit history as primary sources — and the fact that every commit message already contained objective, architectural reasoning, exact test counts, and explicitly-flagged deviations from plan is the only reason a document like [18-development-phases.md](18-development-phases.md) could be written accurately months later without access to the original working sessions. The lesson generalizes: the best time to write "why" is the moment the decision is made, not later from memory.

**A frozen design spec is a contract, but contracts have bugs too — and finding one is not a crisis.** `docs/13-ai-discussion-engine-design.md` was explicitly "frozen" before implementation began, and multiple small inaccuracies were still found during implementation (a reference to a `CaseAttemptPolicy` that doesn't exist; a roadmap milestone count that didn't match what got built; a summary sentence narrower than the spec it summarized). Each was corrected explicitly and documented as a correction, not silently patched over — treating "the spec says X" as a strong prior worth checking against reality, not an unquestionable source of truth.

## Design Systems

**A design system is worth formalizing once ad hoc consistency starts costing more than the formalization would.** For roughly a dozen phases, component-level CSS (the dark evidence/discussion panels, hint rows) was written with real care and stayed visually coherent through developer discipline alone. The trigger for formalizing a token system wasn't a deadline — it was the accumulating cost of retyping the same hex values as literals across multiple files with no single source of truth, and no shared spacing/radius/shadow scale to keep new components consistent with old ones *by construction* rather than by memory. Recognizing that inflection point, rather than either formalizing prematurely (before there was enough real UI to generalize from) or never formalizing at all, was itself the judgment call.

**Claiming "zero visual regression" for a refactor requires a way to actually check, not just careful code review.** Substituting hardcoded values for token references is mechanically simple and easy to get right by inspection — but "easy to get right" and "verified to be right" are different claims. A real before/after screenshot comparison (stashing the change, rebuilding, screenshotting, restoring, rebuilding, re-screenshotting) is the only thing that actually proves the claim, and is now the template for verifying every future token-layer change.

## Provider Abstraction

**One interface, three genuinely different wire formats, zero shared base class was the right call.** `OpenAiCompatibleLlmClient`, `AnthropicLlmClient`, and `GeminiLlmClient` share only `LlmClientInterface`, not a common base class, because their request/response shapes differ enough (system-prompt placement, role naming, authentication mechanism) that a shared base would have been a false abstraction over incidental similarity — exactly the kind of premature abstraction that becomes a liability the moment a fourth provider doesn't fit the assumed shape.

**A composite (`ChainedLlmClient`) implementing the same interface it composes is a clean way to add orchestration without a new architectural boundary.** `DiscussionService` never had to learn a "there might be a chain" concept — it depends on `LlmClientInterface` exactly as it would for a single provider, and the fallback behavior is entirely invisible above that interface. This is the Liskov Substitution Principle doing real, load-bearing work, not just a textbook example.

## Performance

**Measure before optimizing, and measure *scaling behavior*, not absolute numbers.** The Phase 12 performance audit's methodology — scale up seeded data and compare query counts at two different sizes — is what distinguished the one genuine N+1 (18 queries at 5 drafts, still 18 at 15 — wait, confirmed scaling separately by directly checking against draft count, not just eyeballing "18 looks like a lot") from pages whose query count merely looked high in isolation but were actually flat. A number in isolation doesn't tell you if you have a real problem; the same number measured at two different scales does.

**Not every measured inefficiency deserves a fix.** `AnalyticsService::categoryAggregates()`'s repeated per-category queries were identified, understood, and explicitly left alone — a real trade-off (query count vs. duplicated aggregation logic) accepted because the actual category counts in production don't make it a real problem yet. Documenting a known, accepted inefficiency is different from, and more useful than, either silently fixing everything found or silently ignoring it.

## Maintainability

**A documented, deliberate gap is not the same thing as an oversight, and the difference matters to whoever maintains the project next.** Evidence authoring having no admin UI, email verification not being enforced, and the Discussion Engine's repair-retry not being built are all real, current limitations — but each was a conscious scope decision made and recorded at a specific point, not a thing nobody thought about. A future maintainer reading [28-maintenance-guide.md](28-maintenance-guide.md#known-limitations) knows exactly what's missing and why, rather than having to reverse-engineer intent from an absence.

## UX Decisions

**A pedagogical fiction (the "Virtual Engineering Office") only works if it's applied consistently and the code stays honest underneath it.** Keeping two vocabularies — narrative UI copy and precise technical code — deliberately separate meant the product-facing experience could commit fully to "you are a junior engineer investigating a real incident" without that narrative ever leaking into class names, database columns, or route names in a way that would make the codebase harder to reason about for the next engineer.

**Reusing an already-validated UX idea beats inventing a new one, even under pressure to ship something "new and AI-flavored."** The accepted-discussion-prefills-the-diagnosis-form pattern deliberately mirrors the existing evidence-citation pre-check pattern (both are "pre-fill from what already happened, read at render time, never written early") rather than inventing a bespoke mechanism just because the feature involves AI. Novelty for its own sake was explicitly avoided.

## Development Workflow

**A hard stop-gate at every milestone — full suite green, manual check for UI work, before the next milestone starts — is what made a 22-phase, AI-augmented project traceable enough to write this document from primary sources.** The discipline cost real time at each individual milestone; the payoff was a project history detailed and honest enough to reconstruct months later without guessing.

**Self-auditing for a mistake you just found, in adjacent work, is cheap and catches real problems.** Discovering the missing `StructuredOutputParser` milestone triggered an explicit re-check of Phases 13 and 14 for the same class of mistake, which is what caught the missing `LeakageGuard` milestone in Phase 15 before it became a much later, much more expensive discovery (a safety-relevant class silently missing, discovered only when someone went looking for it under pressure).

## Version Control

**Commit messages that explain architectural reasoning, not just "what changed," are themselves a documentation asset.** Every phase/milestone commit in this project's history states objective, key decisions, trade-offs considered and rejected, and verification performed — which is the entire reason this documentation package could be written accurately from git history alone. A terse commit message ("fix bug") would have made this package impossible to write honestly months later.

**Never rewriting history and always creating new commits, even to fix something committed minutes ago, keeps the record trustworthy.** The project's standing rule against amending or force-pushing means the commit history is a genuine, complete record of what actually happened, including mistakes and their corrections — not a curated, retroactively-cleaned narrative.

## Project Planning

**A frozen spec plus a phased roadmap is only as good as the discipline to actually re-verify progress against it, not just assume the roadmap and reality stayed in sync.** The Phase 14/15 "missing milestone" incidents happened because milestone-count tracking drifted from the roadmap document's actual content — a reminder that a plan is a living reference to check against, not a one-time checklist to tick off from memory.

**Scope divergence between an original 12-phase roadmap and actual delivery (Phases 7–11 of the original plan folding into Phase 5/6's actual milestones) is normal and should be documented plainly, not hidden.** [18-development-phases.md](18-development-phases.md) states explicitly where the original numbering and the actual delivery sequence diverge, rather than presenting a cleaned-up narrative where the plan and reality always matched.

## Future Recommendations

1. **Build the evidence-authoring admin UI before authoring any case beyond the seeded demos** — it is the single most limiting known gap for actually growing the case library, which is the platform's own stated long-term vision.
2. **Re-run the provider conformance harness before ever recommending a specific model slug as a shipped default** — none is currently recommended, and provider/model behavior drifts over time even for a model that passed once.
3. **Revisit the Composer advisory-block override** the next time a Laravel upgrade is considered, rather than carrying it forward indefinitely on the assumption it's still the right call.
4. **Treat the "AI engineering coach" long-term vision as a hypothesis to test with a second real subject implementation**, not a guarantee to build toward blindly — the architecture is well-positioned for it, but that positioning is currently unproven by any actual second subject.
5. **Keep the milestone-level stop-gate discipline** for any future contributor, including AI-assisted development sessions — it is the single practice most responsible for this project's documentation, test coverage, and architectural traceability being as strong as they are.
