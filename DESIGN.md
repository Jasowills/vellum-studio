# Vellum design system — MIDNIGHT / SIGNAL

Vellum is a black room with one source of light: a geometric V that resolves out of converging planes. The visual language is a creative instrument, not an editorial magazine, dashboard template, or generated-art gallery. Every surface is built to make one action clearer: turn a written intention into a real image, then understand exactly what it cost.

## Direction

- **Name:** VELLUM
- **Positioning:** An intelligent creative instrument for turning imagination into imagery
- **Tagline:** Turn words into worlds
- **First statement:** A thought is only the beginning
- **World:** MIDNIGHT / SIGNAL / CREATIVE TECHNOLOGY
- **Shape language:** square edges, hairline structure, converging beams, exact grids
- **Motion language:** emergence, scan, settle; never decorative bounce or fake progress

## Palette

The palette is closed. No purple, blue, pink, neon, rainbow, colored glow, generic gradient, or chromatic accent is permitted.

```text
#000000  void            dominant ground
#020202  abyss           secondary ground
#030303  night           recessed ground
#070707  surface         panel field
#0A0A0A  raised          raised field
#0D0D0D  elevated        control field
#191919  edge            quiet divider
#232323  line            structural divider
#494949  edge-strong     emphatic divider / disabled weight
#F5F5F5  document        primary text and signal
#A0A0A0  ash             secondary text
#737373  stone           metadata
#FFFFFF  paperwhite      reserved pure white
```

Black is dominant. White is structural and active. Grey is informational. Borders are hairlines, not ornament. Radius is zero everywhere.

## Typography

```text
Display / brand:  Sora
UI / body / nav:  Geist Sans
Metadata:         Geist Mono, used sparingly
```

Instrument Serif is not part of this system. Display type is uppercase only in the wordmark and tiny metadata labels; headlines use natural case. Letter spacing is restrained except in the wordmark and mono labels. Body copy is short, direct, and concrete.

## Identity

- **Vellum glyph:** a V cut from two converging light planes with a small notch, rendered as a monochrome inline SVG.
- **Wordmark:** `VELLUM` in Sora, uppercase, with `Creative instrument` as a subordinate Geist Mono descriptor on wide viewports.
- **Hero sculpture:** a lazy-loaded Three.js geometric V with a restrained pointer response, one slow sweep, and a static inline SVG fallback for reduced-motion or low-power conditions.
- **Favicon family:** `favicon.svg`, refreshed `favicon.ico`, and refreshed `apple-touch-icon.png`, all black-dominant and monochrome.

## Layout grammar

- A single `vellum-shell` governs content width and responsive gutters.
- Landing sections use exact required headlines and no numbered section titles:
    - A thought is only the beginning
    - Imagine it. Then see it.
    - Everything begins with an idea.
    - Make room for the unexpected.
    - Know what it costs. Keep creating.
    - Let’s see what you imagine.
- Landing proof is real: tracked Pixelforge plates, a real Studio screenshot, and the documented rate-card example.
- Studio is canvas-first. The composer is anchored to the work, settings are progressive, history is collapsible, and only real states appear.
- Library, detail, usage, and admin use ledger logic: exact figures, restrained tables, hairline rows, and no decorative chart candy.
- Auth and settings use the same field rhythm as the product shell, with recessive shared chrome.

## Components

- **Buttons:** square, flat, high-contrast primary; quiet bordered secondary; visible focus ring.
- **Fields:** black ground, hairline border, mono counters/metadata, no glass or inner glow.
- **Cards:** used only where a real record or image needs a boundary; no card-everything layout.
- **Badges/statuses:** grayscale text and hairline structure only; state is described in words.
- **Tables:** stable alignment, 9-decimal money where relevant, horizontal scroll inside the container on small screens.
- **Prompts:** real inputs backed by the estimate endpoint and generation workflow; no invented suggestions or fake progress.

## Motion

- The landing V mounts on intersection, pauses off-screen and when the document is hidden, scales through `ResizeObserver`, and disposes on unmount.
- Reduced motion disables reveal animation and smooth scrolling and keeps the V static.
- Reveal and hover transitions are short, low-amplitude, and grayscale.
- No percentage progress, pulsing success, skeleton theater, or animation that implies work the backend is not doing.

## Responsive behavior

- Verified at 1440 and 390 px across all primary surfaces.
- No horizontal document scroll. Wide tables and charts scroll inside their own containers.
- Navigation collapses to a compact authenticated shell and a mobile control; the landing header keeps Explore, Studio, Library, and the primary action reachable.
- Images use lazy loading with intrinsic dimensions to avoid layout shift.

## Provenance

Landing imagery is derived from tracked real generations:

```text
landing-01.jpg <- 01M84MAZQKJJBTF7RJDFY0C7W2
landing-02.jpg <- 01M84MAZS5VA0656T8R2DD44Y4
landing-03.jpg <- 01M84MAZSH1PSMQ3842RMQ33F4
landing-04.jpg <- 01M84MAZSVK1B5VVYWGJNWYSY0
landing-05.jpg <- 01M84MAZT385988N2C2NNDC59P
studio-interface.png <- real Studio capture
01M84M979D3MYC5BRPHFK9P8X5 <- retained in ledger, not used as landing art
```

Landing cost example, taken from the documented rate card:

```text
provider cost   $0.373875000
studio fee 25%  $0.093468750
charged         $0.467343750
```

No testimonials, press, fake metrics, invented generation records, or stock photography are used.

## Non-goals

- No magazine/fashion layout
- No Web3, cyberpunk, or AI-template aesthetic
- No giant rounded cards or glassmorphism
- No colored status system
- No excessive borders, panels, or badges
- No editorial serif as primary language
