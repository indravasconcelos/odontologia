---
name: Dr. Maycon Gomes — Odontologia
description: Precisão que cuida — sistema visual do consultório do Dr. Maycon Gomes, Ariquemes/RO
colors:
  navy-950: "#04101f"
  navy-900: "#071b33"
  navy-850: "#08203b"
  navy-800: "#0a2540"
  navy-700: "#0f3257"
  navy-600: "#16406f"
  teal-300: "#7ff0e5"
  teal-400: "#3ee6d6"
  teal-500: "#14d6c4"
  teal-600: "#0fbba9"
  teal-700: "#0a6f62"
  blue-500: "#2e7be8"
  ink-900: "#0b1b2b"
  ink-800: "#16283a"
  ink-600: "#44566b"
  ink-500: "#5a6b7d"
  ink-400: "#55677a"
  surface: "#ffffff"
  surface-alt: "#f4f8fb"
  surface-soft: "#eef4f9"
  on-dark: "#eaf2f8"
  on-dark-soft: "#b9c9d9"
  on-dark-mute: "#8ba2b8"
  line: "rgba(7, 27, 51, 0.09)"
  line-strong: "rgba(7, 27, 51, 0.16)"
typography:
  display:
    fontFamily: '"Sora", "Segoe UI", system-ui, sans-serif'
    fontSize: "clamp(2.3rem, 5.2vw, 3.9rem)"
    fontWeight: 800
    lineHeight: 1.16
    letterSpacing: "-0.03em"
  headline:
    fontFamily: '"Sora", "Segoe UI", system-ui, sans-serif'
    fontSize: "clamp(1.75rem, 3.4vw, 2.7rem)"
    fontWeight: 700
    lineHeight: 1.16
    letterSpacing: "-0.025em"
  title:
    fontFamily: '"Sora", "Segoe UI", system-ui, sans-serif'
    fontSize: "clamp(1.12rem, 1.7vw, 1.3rem)"
    fontWeight: 700
    lineHeight: 1.16
    letterSpacing: "-0.015em"
  body:
    fontFamily: '"Inter", "Segoe UI", system-ui, sans-serif'
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.65
  lead:
    fontFamily: '"Inter", "Segoe UI", system-ui, sans-serif'
    fontSize: "clamp(1.02rem, 1.35vw, 1.16rem)"
    fontWeight: 400
    lineHeight: 1.75
  label:
    fontFamily: '"Sora", "Segoe UI", system-ui, sans-serif'
    fontSize: "0.78rem"
    fontWeight: 600
    letterSpacing: "0.16em"
  fine-print:
    fontFamily: '"Inter", "Segoe UI", system-ui, sans-serif'
    fontSize: "0.83rem"
    fontWeight: 400
    lineHeight: 1.6
rounded:
  sm: "12px"
  md: "18px"
  lg: "26px"
  xl: "34px"
  pill: "999px"
spacing:
  section: "clamp(5rem, 10vw, 8.25rem)"
  container: "1180px"
  gutter: "clamp(1.25rem, 4vw, 2rem)"
  head-gap: "clamp(2.5rem, 5vw, 3.75rem)"
  dep-gap: "1.5rem"
components:
  button-primary:
    backgroundColor: "linear-gradient(120deg, #14d6c4, #0fbba9 55%, #2e7be8 130%)"
    textColor: "#04202b"
    typography: "Sora 600 0.97rem"
    rounded: "{rounded.pill}"
    padding: "0.9rem 1.7rem"
  button-primary-lg:
    backgroundColor: "linear-gradient(120deg, #14d6c4, #0fbba9 55%, #2e7be8 130%)"
    textColor: "#04202b"
    typography: "Sora 600 1.02rem"
    rounded: "{rounded.pill}"
    padding: "1.05rem 2.1rem"
  button-primary-sm:
    backgroundColor: "linear-gradient(120deg, #14d6c4, #0fbba9 55%, #2e7be8 130%)"
    textColor: "#04202b"
    typography: "Sora 600 0.88rem"
    rounded: "{rounded.pill}"
    padding: "0.62rem 1.25rem"
  button-light:
    backgroundColor: "#ffffff"
    textColor: "{colors.navy-900}"
    typography: "Sora 600 0.97rem"
    rounded: "{rounded.pill}"
    padding: "0.9rem 1.7rem"
  button-light-lg:
    backgroundColor: "#ffffff"
    textColor: "{colors.navy-900}"
    typography: "Sora 600 1.02rem"
    rounded: "{rounded.pill}"
    padding: "1.05rem 2.1rem"
  button-ghost:
    backgroundColor: "rgba(255, 255, 255, 0.05)"
    textColor: "#ffffff"
    border: "1.5px solid rgba(255, 255, 255, 0.34)"
    rounded: "{rounded.pill}"
    padding: "0.9rem 1.7rem"
  button-outline:
    backgroundColor: "transparent"
    textColor: "{colors.navy-800}"
    border: "1.5px solid rgba(7, 27, 51, 0.16)"
    rounded: "{rounded.pill}"
    padding: "0.9rem 1.7rem"
  card-surface:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink-600}"
    rounded: "{rounded.lg}"
  card-quiet:
    backgroundColor: "{colors.surface-alt}"
    textColor: "{colors.ink-600}"
    rounded: "{rounded.lg}"
  nav-link:
    backgroundColor: "transparent"
    textColor: "{colors.navy-800}"
    typography: "Inter 500 0.92rem"
    rounded: "{rounded.sm}"
    padding: "0.55rem 0.7rem"
---

# Design System: Dr. Maycon Gomes — Odontologia

## Overview

**Creative North Star: "Precisão que Cuida"**

A landing page for a single orthodontist is not a catalogue. It is an instrument panel with one gauge: *is this the person I trust with my teeth?* The whole system is built to answer that quietly. Precision is expressed through restraint — a tight type scale, a closed radius scale, a two-colour accent that never runs away, shadows that exist to state hierarchy and nothing more. Care is expressed through warmth of the surface: off-white and near-white backgrounds instead of pure white, a deep navy that reads as calm night rather than corporate black, and rounded forms that read as handled, not machined.

The North Star is deliberately **not** clinical-cold and **not** lifestyle-warm. "Precisão que Cuida" occupies the exact middle a nervous patient is looking for: this person measures everything, and nothing about the measuring is intimidating. Every visual decision should be defensible in those two terms — does it communicate exactness, does it lower the pulse. If a change does neither, it is decoration and does not ship.

The system is a single world, not a family of page themes. Sections alternate light (`--surface`) and quiet-light (`--surface-alt`) planes, with three deliberate dark departures — hero, testimonials and the closing CTA — all built on the same navy-plus-glow foundation so the page breathes at a consistent cadence instead of jumping between moods.

**Key Characteristics:**
- Two fonts, two jobs: Sora for anything that speaks, Inter for anything that must be read.
- One accent (teal) with one partner (blue), used on under a tenth of any screen.
- Four radii and one pill. Nothing in between.
- Shadows are structural, never ambient decoration.
- Corners are generous and consistent; nothing is sharp except the eye of an icon.
- Copy sits in calm, wide-leaded blocks; measure never exceeds 62ch.

## Colors

A two-accent system over a deep navy base, with a cool neutral ramp for all reading.

### Primary
- **Turquesa Vivo** (`#14d6c4`): the single action colour. Buttons, focus rings, eyebrow bars, the animated highlight sweep. It is the only saturated colour a visitor sees at rest.
- **Turquesa Claro** (`#7ff0e5`): eyebrow label on dark grounds, where the full-strength teal would vibrate against navy.
- **Turquesa Profundo** (`#0fbba9`): the mid-stop inside the primary button gradient. Never used for text — it fails contrast on every light ground.
- **Turquesa Aferição** (`#0a6f62`): the only teal approved for **text on a light ground** (6.06:1 on white, 5.68:1 on `--surface-alt`). Carries the eyebrow label and any teal that must be read.

### Secondary
- **Azul Aferição** (`#2e7be8`): the partner stop that closes the accent gradient and carries the cool half of the dark backgrounds. Never used alone as a call to action.

### Neutral
- **Azul Meia-Noite** (`#071b33`): the primary dark plane — header on scroll, dark sections, footer. The dominant surface of the page.
- **Azul Fundo** (`#04101f`): the floor of every dark gradient. Never used as a flat fill.
- **Azul Intermediário** (`#0a2540`): the neutral button fill (`--btn-bg` default) and any dark-on-dark surface.
- **Tinta** (`#0b1b2b`): all headings on light grounds. Near-black, never `#000`.
- **Tinta Corpo** (`#44566b`): body copy on light grounds. Deliberately desaturated so long passages stay calm.
- **Tinta Suave** (`#5a6b7d`): lead paragraphs. **Tinta Leve** (`#55677a`): fine print and the scrolled header subtitle (5.82:1 on white).
- **Papel** (`#ffffff`): the base plane. **Papel Quieto** (`#f4f8fb`): alternating section fill. **Papel Suave** (`#eef4f9`): nested surfaces inside a quiet section.
- **Sobre Escuro** (`#eaf2f8`), **Sobre Escuro Suave** (`#b9c9d9`), **Sobre Escuro Apagado** (`#8ba2b8`): the three-step text ramp on navy.
- **Linha** (`rgba(7, 27, 51, 0.09)`) and **Linha Forte** (`rgba(7, 27, 51, 0.16)`): borders. Hairlines only; never a solid 1px grey.

### Named Rules

**The One Gauge Rule.** Turquesa Vivo appears on at most one element per viewport — one primary button, one eyebrow, one focus ring. Its rarity is what makes it read as a signal instead of decoration. When a second teal element would compete, the second one goes navy or white.

**The No Flat Black Rule.** Pure black and pure grey are banned. The darkest value in the system is `#04101f`, the lightest neutral is a blue-tinted ink. Every neutral is tinted toward the navy family.

## Typography

**Display Font:** Sora (fallback: Segoe UI, system-ui)
**Body Font:** Inter (fallback: Segoe UI, system-ui)
**Label/Mono Font:** none — labels use Sora at small size, uppercase, wide-tracked

**Character:** Sora carries the voice of the practice. Its geometric, slightly technical forms read as precise and modern without becoming cold, which is exactly the register "Precisão que Cuida" needs. Inter does all the reading work, with a tall x-height that keeps the longer clinical descriptions comfortable at small sizes. The pairing is deliberately asymmetric in weight, not in size: Sora is set heavy (700–800) so headings feel stated rather than whispered, Inter stays at 400–500 so text never competes.

### Hierarchy
- **Display** (800, `clamp(2.3rem, 5.2vw, 3.9rem)`, 1.16, −0.03em): the hero headline only. One per page.
- **Headline** (700, `clamp(1.75rem, 3.4vw, 2.7rem)`, 1.16, −0.025em): every section `h2`. One per section, always the largest element in it.
- **Title** (700, `clamp(1.12rem, 1.7vw, 1.3rem)`, 1.16, −0.015em): card and step titles (`h3`).
- **Body** (400, 1rem, 1.65): default. Long measures cap at 62ch for lead text, 68ch for fine print.
- **Lead** (400, `clamp(1.02rem, 1.35vw, 1.16rem)`, 1.75): the one paragraph per section that carries the argument.
- **Label** (600, 0.78rem, 0.16em, uppercase): the eyebrow above every headline, preceded by a 26×2px gradient bar.

### Named Rules

**The Two-Voice Rule.** Sora appears only where something is being *said* — headlines, titles, buttons, eyebrows. Inter appears only where something is being *read*. Never set Inter at display size to fake a headline, never set Sora at body size to fake emphasis.

**The Em Gradient Rule.** The emphasised fragment inside a headline (always one `em` per headline, never two) is the only place a gradient is applied to text. It is the reading entry point of the section.

**The Balanced Line Rule.** Every headline carries `text-wrap: balance` and every paragraph carries `text-wrap: pretty`. A headline that breaks on a single trailing word is a bug, not a style.

**The Measured Number Rule.** Anything the practice can be *measured* by — the CRO number, the `01/02/03` step scale, the CEP and phone in the contact block — is set with `font-variant-numeric: tabular-nums`, so digits align as readings on a scale. This is the one place the "Precisão que Cuida" north star becomes observable behaviour rather than palette. It is a real font feature, never a monospace costume: no third family is introduced to fake it. Body and button text stay on the default `normal` figures.

## Layout

A single centred container of 1180px, with a fluid gutter of `clamp(1.25rem, 4vw, 2rem)`. Vertical rhythm comes from one token: every section is padded `clamp(5rem, 10vw, 8.25rem)` top and bottom, so the page's beat is constant from the hero to the footer.

Sections alternate planes rather than divide with rules — the page is a stack of calm stripes. Two-column sections use a roughly equal split; the hero is asymmetric in favour of the copy, with the portrait block deliberately bleeding wider than its column.

Breakpoints are mobile-first and sit at 1440, 1120, 1040, 1020, 920, 720 and 480. The 1040 mark is the structural one: it is where the header nav stops fitting and collapses into the overlay menu. 1120 reduces the before/after gallery from four columns to three and narrows the testimonial cards. 920 stacks every two-column section and reduces the header to a compact bar. 720 drops to single column throughout and lets the carousel run at `82vw` cards.

Section heads are capped at 780px so a headline never runs the full measure, and centred section heads are used only where the content beneath is itself symmetric — treatments, gallery, testimonials.

## Elevation & Depth

Depth in this system is **structural, not ambient**. A shadow exists to answer one question — *what is above what?* — and is removed when it doesn't. Surfaces at rest are flat planes; the shadow appears where an element is genuinely lifted off its background: the primary button, a raised card, the lightbox, the overlay menu, and the final CTA's glass panel.

The one exception is the teal glow on the primary button, which is not a shadow but a coloured halo that ties the action to the accent colour and gives the button its weight. Because shadows here carry meaning, their opacity steps are wide and their blur radii large — a shadow should read as a real distance, not a smudge.

### Shadow Vocabulary
- **xs** (`0 2px 8px rgba(7,27,51,0.06)`): hairline lift for the smallest raised elements.
- **sm** (`0 6px 18px rgba(7,27,51,0.08)`): resting lift for cards.
- **md** (`0 16px 40px rgba(7,27,51,0.11)`): hovered cards and the overlay menu.
- **lg** (`0 30px 70px rgba(7,27,51,0.16)`): lightbox and the only full-screen layers.
- **teal** (`0 14px 34px rgba(20,214,196,0.32)`): the primary button at rest, expanding to `0 18px 42px rgba(20,214,196,0.4)` on hover.
- **white-deep** (`0 18px 44px rgba(4,16,31,0.35)`): the white button on dark grounds, where a navy shadow would disappear.

### Named Rules

**The Meaningful Shadow Rule.** Every shadow traces to a state or a layer. If you cannot name the state it marks, delete it. In particular: do not put a resting shadow on a flat card purely for decoration.

**The Halo Rule.** Only the primary action carries a coloured glow, and only in the accent colour. A second glowing element on the same screen halves the effect of the first.

## Shapes

The radius scale is closed and short: **12px** (small chips and focus buffers), **18px** (medium panels), **26px** (cards), **34px** (large glass panels), **999px** (every pill-shaped control). Nothing sits between steps, and nothing is sharp.

Buttons are always fully round. Cards are always 26px. The one large-radius surface — the closing CTA's glass card — is deliberately wider than the card scale at 44px so it reads as a distinct, culminating object rather than another card.

Borders are hairlines at 9% or 16% navy. A solid 1px grey border is banned; if an element needs separation, it gets a surface change or a shadow first.

Icons are 16–30px inline SVG at 1.7–2.6 stroke weight, drawn on a 24 or 48 viewBox. They are always `aria-hidden`; meaning lives in the label beside them.

## Components

Buttons feel **tactile and firm**: they lift 2px on hover and settle flat on press, with a specular sweep across the face that reads as light catching a physical surface. Weight comes from the teal halo and the heavy Sora setting, not from size.

### Buttons
- **Shape:** fully round (`999px`), always.
- **Primary:** the accent gradient `linear-gradient(120deg, #14d6c4, #0fbba9 55%, #2e7be8 130%)` with near-black text `#04202b` — dark type on bright ground, which is the highest-contrast pairing in the system. Padding `0.9rem 1.7rem`, Sora 600 at 0.97rem.
- **Sizes:** `sm` `0.62rem 1.25rem` / 0.88rem · default `0.9rem 1.7rem` / 0.97rem · `lg` `1.05rem 2.1rem` / 1.02rem.
- **Hover / Focus:** `translateY(-2px)` plus a widened teal halo; a skewed white gradient sweep crosses the face on a 0.6s ease. `:active` returns to zero lift. Focus is a 2.5px teal outline at 3px offset.
- **Light:** white fill with navy text, for use on dark grounds; carries the deep navy shadow instead of the teal halo.
- **Ghost:** translucent white with a 34% white hairline and a 6px backdrop blur, for dark grounds where a solid button would dominate.
- **Outline:** transparent with a 16% navy hairline, inverting to solid navy on hover, for light grounds.

### Chips / filter tabs
The treatment filters are pills with a transparent fill and a 16% navy hairline at rest. The selected state fills with the accent gradient and adopts the primary button's dark text and halo — the filter reads as a selected control, not a highlighted word. Equal height, generous horizontal padding, and they never wrap awkwardly on mobile.

### Cards / Containers
- **Corner Style:** 26px (`--radius-lg`) for cards; 44px for the closing CTA panel.
- **Background:** `--surface` on light sections, `--surface-soft` when nested inside a quiet section, glass (`rgba(255,255,255,0.05)` + `backdrop-filter: blur(18px)` + a 13% white hairline) on dark grounds.
- **Shadow Strategy:** flat at rest, `--shadow-sm`; `--shadow-md` on hover.
- **Border:** `--line` hairline, or `--line-strong` where the card must separate from a quiet section.
- **Internal Padding:** generous — the card padding is proportional to its content, never less than 1.5rem on any side.

### Inputs / Fields
Not a landing-page concern; the panel inputs use the navy family with a teal focus ring matching the button halo.

### Navigation
The header bar holds the logo lockup, five text links and one primary pill. Links are Inter 500 at 0.92rem with a 0.55rem vertical pad, no underline at rest, and the teal treatment on hover. Scrollspy marks the active section. Below 1040px the bar collapses into a hamburger that opens a full overlay with the nav stacked vertically at a larger touch size.

### Testimonial carousel
Six cards on a continuous marquee at a fixed 390px basis (340px at 1120, `82vw` at 720). Pauses on hover, focus-within and press; stops entirely under `prefers-reduced-motion`, where it becomes a scroll-snap track instead. The avatar is a two-letter monogram in the accent colour — no photographs of patients.

### Gallery carousel
Before/after cases sit on a horizontal track, four columns at full width, three at 1120, and 76% of the viewport at 720 so the next case is always hinted at. Each card opens a lightbox with keyboard navigation and a live counter.

## Do's and Don'ts

### Do:
- **Do** keep exactly one primary action per viewport, and repeat it consistently — the CTA is always "Agendar avaliação", always opening the chat with the evaluation request.
- **Do** let every shadow answer to a named state (hover, raised layer, focus).
- **Do** put exactly one gradient `em` inside each headline, and keep the word or phrase there short enough to read as emphasis rather than decoration.
- **Do** cap body measure at 62ch and set `text-wrap: balance` on every headline.
- **Do** keep dark sections to three: hero, testimonials, closing CTA. Their rarity is what makes the navy feel deep.
- **Do** pair every reveal animation with a `prefers-reduced-motion` fallback that leaves the content visible.

### Don't:
- **Don't** introduce a third accent colour, or move turquesa into a large fill. The One Gauge Rule holds.
- **Don't** add a new radius between 12 and 34px, and never square a corner that the system rounds.
- **Don't** set Sora at body size or Inter at display size — the Two-Voice Rule holds.
- **Don't** put grey text on a coloured ground, or grey text on navy below `--on-dark-soft`.
- **Don't** add a price, a deadline, a result guarantee or any clinical promise to any surface, including microcopy, alt text, or structured data.
- **Don't** present a testimonial, review score, patient count or outcome statistic that is not verified evidence. The six existing testimonials carry placeholder names and are not proof.
