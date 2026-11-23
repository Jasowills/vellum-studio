# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Primary users are people who create images: they arrive with an idea, write a prompt, adjust a small number of generation settings, generate an image, inspect the result, revisit it later, and understand what it cost. They use Vellum repeatedly, not as a one-off demo. They need the product to be understandable within seconds without studying an artistic interface.

_Inferred from the one-shot redesign brief; no interview was held because the brief explicitly forbids questions._

## Product Purpose

Vellum turns written ideas into generated images through a real prompt-driven backend. Success means a user can move from thought to image with minimal friction, iterate on the result, find earlier work, and trust the cost and usage information.

## Positioning

An intelligent creative instrument for turning imagination into imagery. The product's distinguishing behavior is that creation starts as a real prompt inside the product environment, not as a marketing form that hands off to a separate dashboard.

## Operating Context

The product is a Laravel/Vue/Inertia web application with authenticated Studio, Library, Usage, Settings, and Admin surfaces. Generation is backed by Pixelforge. Real states include idle, prompt entered, submitting, queued, processing, completed, and failed. Users authenticate before generation; the landing prompt must survive authentication and restore into the studio composer. Repeated daily use, keyboard access, and small-screen use are part of the real context.

## Capabilities and Constraints

Confirmed capabilities: prompt-driven generation; model, aspect-ratio, and quality/resolution controls where the backend supports them; generated-image display and download; generation history and status; image detail views; prompt, model, dimensions, timestamp, and cost metadata; usage summaries and transaction records; per-user/admin reconciliation and margin data; authentication and authorization.

Hard constraints: preserve the existing backend, Pixelforge integration, webhook verification, idempotency, duplicate/out-of-order event handling, pricing, financial calculations, reconciliation, credits, database integrity, admin functions, and all tests. Do not invent features, generation records, prices, metrics, testimonials, or progress. Do not expose credentials or private images. Frontend redesign may replace layouts and components; backend changes are allowed only if genuinely required, isolated, and tested.

## Brand Commitments

Name: VELLUM. Category: AI image generation / creative studio. Positioning: “An intelligent creative instrument for turning imagination into imagery.” Tagline: “Turn words into worlds.” Primary hero statement: “A thought is only the beginning.” Visual world is explicitly pinned as SIGNAL / MIDNIGHT / CREATIVE TECHNOLOGY: deep black environment, geometric V light-beam identity, generative signal system, precise typography, purposeful motion, artwork integrated into the product, and functional controls. Display type is Sora; UI/body is Geist Sans; technical metadata is Geist Mono. No Instrument Serif or editorial serif primary language. No purple, blue, pink, neon, rainbow gradients, colored glows, generic AI gradients, excessive shadows, or glassmorphism. No numbered section titles, generic cards, dashboard templates, or oversized magazine typography.

## Evidence on Hand

Real generated artwork and product imagery already exist in `public/images/landing-01.jpg` through `landing-05.jpg` and `public/images/studio-interface.png`, derived from tracked real Pixelforge generations. Real reconciliation data is available through the admin report and `pixelforge:reconcile-usage`; no fake prices or statistics may be introduced. Existing routes, components, tests, and backend provide the authoritative feature inventory.

No customer testimonials, press, case studies, benchmarks, or purchased stock assets are available; future work must not fabricate them.

## Product Principles

1. The workspace must demonstrate the real product immediately; the landing page is a front door into a working instrument, not a detached brochure.
2. Creation is the primary task: prompt entry, settings, status, result, and next action stay legible and close together.
3. Artwork leads, but metadata and cost remain trustworthy and accessible when needed.
4. Controls are familiar, compact, progressive, and connected to real backend behavior.
5. The black environment is atmospheric and restrained; it must never obscure usability, contrast, or focus.

## Accessibility & Inclusion

Keyboard access, visible focus, adequate contrast, semantic controls, reduced-motion support, responsive behavior at desktop/tablet/mobile widths, and no horizontal overflow are required. Screen-reader users must be able to understand generation state and navigate every primary workflow.
