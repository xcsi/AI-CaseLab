# 16 — Design System

> **Status:** Approved 2026-08-03 as the binding visual reference for all AI CaseLab UI work. Originally published as an interactive Artifact ("AI CaseLab — Design System v1"); reproduced here in full as the durable, version-controlled record.
> **Related:** [06-frontend-architecture](06-frontend-architecture.md) · [20-design-decisions](20-design-decisions.md) · [19-milestones](19-milestones.md) (Visual Identity rollout)

## Visual Identity, in One Sentence

> **AI CaseLab looks like the internal engineering tools its students are training to use — calm, precise, and built for focus, not a course platform dressed up as software.**

## Brand Personality

| Trait | Why |
|---|---|
| **Precise** | Every number, log line, and status is exact — nothing hand-wavy. |
| **Calm** | Low visual noise so the incident, not the UI, holds attention. |
| **Authentic** | Reads like a tool engineers actually use, not an edu-tech skin. |
| **Unshowy** | Confidence from restraint — one accent color, used sparingly. |

**Not:** gamified (badges, confetti, mascots), marketing-SaaS (gradients, big hero claims), or a generic AI-chat demo.

## Color Palette

### Signal — the one accent

| Name | Hex | Usage |
|---|---|---|
| Signal | `#2952E3` | Actions, links, focus, active state (already the app's `$primary`) |
| Signal Wash | `#EEF1FD` | Selected/active background tint |

### Slate — neutral scale (light shell)

| Name | Hex |
|---|---|
| Paper | `#F7F8FA` |
| Slate 100 | `#EEF0F4` |
| Slate 200 | `#E1E4EA` |
| Slate 300 | `#C7CCD6` |
| Slate 400 | `#9AA1B0` |
| Slate 500 | `#6B7284` |
| Slate 600 | `#4B5164` |
| Slate 700 | `#363B4A` |
| Ink | `#12141C` |

### Night — dark instrument panel (evidence, code, discussion)

| Name | Hex | Role |
|---|---|---|
| Night Deep | `#14141F` | Recessed/input surfaces |
| Night | `#1E1E2E` | Panel surface |
| Night Raised | `#2A2D3F` | AI discussion bubble |
| Night Raised Alt | `#33354A` | Student discussion bubble / hover |
| Night Muted | `#8890A6` | Muted text/icons on dark |
| Night Text | `#D4D4E0` | Primary text on dark |

### Semantic — muted, never neon

| Name | Hex | Role |
|---|---|---|
| Moss (success) | `#1A7F4B` | |
| Amber (warning) | `#B4740E` | |
| Ember (danger) | `#D13B3B` | |

**Already shipped in code, kept as-is, not replaced:** the `#2952E3` primary and the entire Night scale (already present in `discussion-panel`/evidence viewers before this specification existed). This system formalizes them into named tokens; it does not redesign them.

## Typography

Two faces only. **Figtree** (already loaded, weights 400/500/600) carries every UI role from display down to labels — differentiated by size and weight, never a second display face. System **monospace** is reserved for anything that is literally data: logs, code, IDs, ticket numbers, timestamps.

| Role | Size / weight | Example use |
|---|---|---|
| Display | 40px / 600 | Rare — a large stat number |
| H1 | 28px / 600 | Page title ("Assigned Incidents") |
| H2 | 22px / 600 | Section heading |
| H3 | 18px / 600 | Card/panel heading |
| Body | 15px / 400, line-height 1.6 | Running text |
| Small | 13px / 400 | Secondary/meta text |
| Micro | 11px / 600, uppercase, +0.05em tracking | Eyebrow labels ("Evidence · Logs") |
| Mono | 13px, system monospace, tabular-nums | Log timestamps, status codes, ticket IDs |

## Spacing Scale

4px base grid. Layout comes from `gap`, not stacked margins.

| Token | Value |
|---|---|
| space-1 | 4px |
| space-2 | 8px |
| space-3 | 12px |
| space-4 | 16px |
| space-6 | 24px |
| space-8 | 32px |
| space-12 | 48px |
| space-16 | 64px |

## Border Radius

Three steps, capped low — nothing reads as "bubbly"; a workbench, not a mobile app.

| Token | Value | Used for |
|---|---|---|
| sm | 4px | Chips, inline tags |
| base | 6px | Buttons, inputs, cards, chat bubbles |
| lg | 10px | Modals, popovers only |

## Shadow System

Shadow is the exception, not the default. Cards and panels are separated by a 1px border, never a drop shadow.

| Token | Value | Used for |
|---|---|---|
| none | — | Default for cards/panels |
| sm | `0 2px 6px rgba(18,20,28,0.10)` | Dropdowns, tooltips |
| md | `0 8px 24px rgba(18,20,28,0.18)` | Modals only |

## Icon Style

Outline strokes only, 1.5px stroke weight, on a 24px grid (Lucide-compatible). Never filled or colored icons — those read as marketing, not tooling. Vendored as inline SVG, no external icon-font dependency.

## Buttons

Four variants. Primary is the only solid-fill color in the whole system — reserved for the one recommended action per screen.

| Variant | Appearance | Hover | Use |
|---|---|---|---|
| Primary | Solid Signal fill, white text | Darkens ~8% | The one recommended action per screen |
| Secondary | Paper/white fill, Slate-300 border, Ink text | Slate-100 background tint (no color invert) | The app's default secondary action |
| Ghost | Transparent, Ink text | Slate-100 background | Toolbar/icon actions |
| Destructive | Transparent, Ember border/text | Light Ember wash | Stays quiet until the confirm step — solid Ember reserved for the final destructive confirmation only |

Focus: every button gets a 2px Signal outline with a 2px offset on keyboard focus — never removed without a replacement.

## Cards

1px Slate-200 border, `base` radius, no shadow by default. An optional header slot uses a bottom hairline divider, never a colored strip. A clickable card's hover state darkens the border to Slate-300 — no lift, no shadow-pop.

## Forms

1px Slate-300 border, `base` radius, Paper background. Focus is a crisp border-color change to Signal plus a 2px low-opacity ring — never a blurred glow. Labels are Small/500-weight/Slate-600. Errors are a border-color change plus one plain sentence below the field — never a red background wash.

## Chat Bubble Styles (Engineering Discussion)

Explicitly reuses the Night dark-panel system already established for evidence/code viewers — **not a new chat-bubble design language**. Student turns are right-aligned on Night Raised Alt; AI turns are left-aligned on Night Raised with a 2px Night Muted left border acting as an "instrument reading" marker rather than a speech-bubble tail. No avatars. No rounded speech-bubble tails. No animated bouncing "typing…" dots — a static, calm "Reviewing your reasoning…" line instead. See [09-discussion-engine.md](09-discussion-engine.md).

## Empty States

Centered within the panel it belongs to (not the whole page) — an outline icon (Slate-400) + one factual sentence + an optional single Ghost-button action. No illustrations, no mascots — consistent everywhere (Inbox, Assigned Incidents, Work History, Evidence panel empty states).

## Loading States

Skeleton blocks shaped like the real content (Slate-100 pulsing to Slate-200, 1.2s ease-in-out, freezing to static under `prefers-reduced-motion`) for content areas — never a spinner for a whole content region. A thin 2px Signal ring spinner is reserved for button-level in-progress actions only ("Submitting…"). No full-page blocking overlays.

## Hover / Focus Behavior

- **Nav links:** bold + underline when active — never a filled pill.
- **Buttons/cards:** background/border shift only on hover — no scale or lift transforms.
- **Focus:** every interactive element gets a visible 2px Signal ring with 2px offset — outlines are never removed without a replacement.
- **Body links:** Signal-colored, underlined only on hover (not always-underlined running text).

## Animation Principles

| Rule | Value |
|---|---|
| Micro-interaction duration | 120–180ms |
| Panel/tab transition duration | 200–240ms |
| Easing | Ease-out on entry; linear only for continuous loops (skeleton pulse) |
| Allowed | Opacity/height fades for expand-collapse, subtle skeleton pulse, spinner rotation |
| Disallowed | Bounce/elastic easing, autoplay/looping decorative motion, parallax, animated gradients, celebratory effects on results (this is an incident review, not a game win screen) |
| Accessibility | `prefers-reduced-motion` collapses all of the above to instant/opacity-only |

## Accessibility

**Keyboard focus**
- Every interactive element gets the 2px Signal ring, 2px offset — never removed without a replacement.
- Focus order matches visual order: nav → page content → primary action.
- Each authenticated shell (student, admin) should carry a "Skip to content" link as the first focusable element.
- Modals/dialogs trap focus while open and return it to the trigger on close.

**Contrast**
- Body text meets WCAG AA (4.5:1) at both Ink-on-Paper and Night-Text-on-Night.
- Slate 500 is the lightest tone allowed for text; Slate 400 and lighter are borders/icons/disabled only.
- Semantic color is never the only signal — status always pairs a color with an icon or word.

**Screen reader considerations**
- Status chips and severity marks carry a text equivalent, not color alone (e.g., "Status: In progress").
- Icon-only buttons always carry an `aria-label`.
- Decorative icons get `aria-hidden="true"` (already the pattern for the hint-lock icon).
- Skeleton loaders carry `aria-busy` on the region they're replacing.

## Responsive Behavior

Three tiers, matching Bootstrap's existing `md` breakpoint the nav already relies on — no new breakpoint system.

| Tier | Range | Layout |
|---|---|---|
| Desktop | ≥992px | Full sidebar/nav; Evidence Explorer and Viewer side by side; cards in a grid |
| Tablet | 768–991px | Nav collapses; Evidence panels stack vertically instead of splitting; cards drop to one/two columns |
| Mobile | <768px | Fully stacked; existing hamburger + collapse nav; touch targets ≥40px tall; discussion bubbles widen to ~90% instead of 82% |

## Do / Don't

| Don't | Do |
|---|---|
| Gradient-filled, drop-shadowed card with emoji and celebratory copy | Flat card, hairline border, factual status text |
| Pill-shaped gradient button with a glow shadow | Flat Signal-fill button, 6px radius, verb-first label |
| White rounded chat bubble with an avatar, drop shadow, and animated "Typing…" dots | Night-panel bubble, static "Reviewing your reasoning…" text, no avatar |

## Implementation Status

The token layer (palette, radius, shadow, spacing custom properties) and the button/form-control component layer were implemented in `resources/sass/_variables.scss` and `resources/sass/app.scss` as Milestones 1–2 of the Visual Identity rollout, verified with a pixel-identical before/after comparison for every dark-panel component (evidence viewer, hint rows, Engineering Discussion) to confirm the token refactor introduced zero visual regression. See [19-milestones.md](19-milestones.md) for the full milestone-by-milestone rollout status and [20-design-decisions.md](20-design-decisions.md) for why a token layer was introduced rather than continuing page-by-page CSS.
