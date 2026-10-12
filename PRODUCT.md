# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

**Primary:** adults and adolescents in Ariquemes - RO and surroundings researching orthodontic or general dental treatment. They arrive worried about pain, about what it will cost, and about how long it takes. Some come from another clinic, some from a promotional post; they are deciding whether to trust this specific professional with a first evaluation. Their job is to understand whether this is the right dentist for their case and to book that evaluation.

**Secondary:** the dentist and clinic staff, who manage agenda, patients, services, blocks and hours through the `/admin` panel. Recorded because the same codebase serves both, even though landing-page design work does not target them.

## Product Purpose

Turn a visitor into a booked evaluation. Success is a scheduled evaluation appointment — not a treatment sale. Every element of the landing page exists to make that one next step feel safe and obvious.

## Positioning

A single named professional, CRO/RO 3795, who carries each case personally from diagnosis to result, explains the plan in plain language before any commitment, and is present at every follow-up. The differentiator is continuity and transparency of the decision — not technology, price, or speed.

## Operating Context

- Scheduling runs in the `/admin` panel (PHP 8.1+, MySQL) on ordinary shared hosting. Hostinger is the documented target (`MIGRACAO-HOSTINGER.md`).
- A portable runtime in `_demo/` (PHP + MySQL on port 3307, site on port 8080) serves local demos. It is never deployed.
- The AI assistant (`api/chat.php`, `app/ia/`) defaults to `AI_MODO=local`: a rule-based responder with no API key and no internet. DeepSeek is optional (`AI_MODO=deepseek` + `DEEPSEEK_API_KEY`).
- Agenda rules: 120 minutes minimum lead time, 60 days ahead (`AGENDA_ANTECEDENCIA`, `AGENDA_DIAS_A_FUTURO`).
- **Two phases:** the build is presented for client approval first, then published for real patients. Anything visible on screen must still be defensible in the second phase.
- The public site must keep working with zero third-party services on the critical path. The map degrades to a plain link; fonts are the only external request.

## Capabilities and Constraints

- Every scheduling path — header CTA, hero, Sobre, vídeo, FAQ, final CTA and the chat widget — leads to a **chat conversation that books an evaluation only**. The assistant never schedules a procedure; `app/ia/Tools.php` enforces this.
- The assistant answers treatment questions from a local knowledge base and does not diagnose.
- Treatments are presented under four filters: Alinhamento, Saúde e restauração, Estética e prótese, Como funciona.
- **Binding constraints** (changing any of these requires asking the user):
  - No price, no deadline, no result guarantee, no clinical promise in any copy.
  - *Copy aprovada é intocável* — approved wording keeps its meaning. Rhythm, typographic treatment and layout around it may change; changing words requires asking.
  - *Identidade visual é intocável* — the teal/blue system, Sora + Inter, large corner radii and the gradient are a decided system.
  - The evaluation is the only entrance to treatment; there is no direct procedure booking.
- Orthodontics (aparelhos ortopédicos and alinhadores invisíveis) carries the structural and visual emphasis; general clinical care is secondary. (User decision.)
- Undecided: whether a lead form or a direct booking path will ever exist alongside the chat. Today the chat is the only route.
- Must not be fabricated: patient names, prices, durations, review scores, awards, clinic size, or equipment absent from `assets/`.

## Brand Commitments

- Name and credential: **Dr. Maycon Gomes**, Cirurgião-Dentista, CRO/RO 3795. Brand line used in header and footer: "Ortodontia / Clínico Geral".
- Contact facts: Rua Salvador, 2076, Ariquemes - RO, CEP 76870-416 · (69) 99988-0560 · Instagram @maycongomees_.
- Voice: calm, plain, close ("a gente", "seu sorriso"). No urgency, no superlatives, no fear. Short sentences.
- Honesty rule already present in the copy itself: "Os resultados variam de pessoa para pessoa e dependem de cada caso."
- The visual identity is a binding user decision, recorded here without expansion. The system itself belongs in DESIGN.md.

## Evidence on Hand

Real assets in `assets/`:

- `dr. maycon.png` — portrait of the doctor (Hero)
- `img-sobre.jpg` — doctor in appointment (Sobre)
- `logo.jpg` — brand mark
- `antes-depois-01..04.jpg` and `antes e depois 5.png` — five before/after case photos (Antes e Depois)
- `video_horizontal.mp4` + `capa-video.jpg` — the specialist's video
- `video-fiame.mp4` + `capa-video-fiame.jpg` — tour of the office
- Office location, shown by the MapLibre map with a Google Maps fallback

**Known absences — future work must not fill these with invention:**

- The six testimonials in `#depoimentos` use **fictitious placeholder names** (Mariana Ferreira, Rafael Andrade, Juliana Prado, Camila Souza, Diego Martins, Beatriz Almeida), chosen deliberately during the build. They are **not** verified patient quotes. They must not be treated as evidence, extended, cited as social proof, or replaced by invented variants.
- No verified review platform, no patient volume, no pricing, no clinical outcome data.
- No photography of the building exterior or of the team beyond the two doctor images.

## Product Principles

1. **The evaluation is the product.** One next step, repeated everywhere. Whatever competes with it is noise.
2. **Say what happens, not what it costs.** The site reduces anxiety by explaining the process (avaliação → planejamento → acompanhamento), never by promising outcomes.
3. **One professional, end to end.** Personal continuity of care is the real differentiator and must be felt in the copy and in the interface.
4. **Orthodontics leads.** Alignment is the strongest demand; hierarchy, ordering and emphasis follow it.
5. **Works with nothing.** No internet, no API key, no third-party service on the critical path — degrade to plain HTML, never to an error.

## Accessibility & Inclusion

No product-specific standard has been set by the user. The baseline already present in the code must be preserved: skip link, `lang="pt-BR"`, labelled controls, `aria-expanded`/`aria-controls` on the menu and accordions, visible focus, `prefers-reduced-motion` support, and alt text on content images.
