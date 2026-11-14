---
inclusion: auto
---

# MAGIREM AI Design Guidance

Use these project-specific rules alongside the existing brand, design-system, UI-styling, and UI/UX Pro Max guidance.

- Preserve the official MAGIREM AI logo, existing illustrations, and MAGI mascot artwork. Do not redraw or replace them.
- Keep the established forest-green, muted-sage, warm-ivory, mint, and restrained-gold palette. Reuse current tokens such as `--msc-home-forest`, `--msc-home-sage`, `--msc-home-cream`, and `--msc-home-line` instead of introducing competing colors.
- Retain the calm, student-friendly wellness tone: editorial headings, readable supporting text, generous but efficient spacing, and existing hand-drawn visual language.
- Prefer lightweight, purposeful motion using opacity and transforms. Honor `prefers-reduced-motion`; avoid unnecessary perpetual, parallax, or scroll-triggered effects.
- Reuse the installed `framer-motion` dependency for existing React work. Do not add a second animation runtime unless a separately approved migration justifies it. Keep Blade pages on their current CSS/JavaScript path.
- Protect initial load performance: lazy-load large interactive surfaces and media, avoid increasing the initial React bundle for decorative effects, and keep assets appropriately sized.
- Maintain keyboard access, visible focus states, semantic controls, sufficient contrast, and comfortable mobile touch targets.
- Keep existing Laravel routes, role/consent checks, APIs, and server-side behavior unchanged during visual-only work.
