# Problem Statement — Codeface

**1. The Problem — fragmented, passive, and solitary coding practice.**
Students and developers who want to improve at programming face three gaps in existing tools.
Learning resources (tutorials, videos) are passive — they teach syntax but never build the
fluency that only comes from solving real problems. Practice platforms are fragmented — a
learner uses one site for problems, another for language reference, a third to find a study
partner, and none of them talk to each other. And almost all of them are solitary — there is
no lightweight way to open a shared editor with a friend and solve a problem together in real
time. On top of this, most such platforms are heavy framework services that are hard for a
student to host, inspect, and learn from themselves.

**2. Proposed PHP & MySQL Web Solution.**
We propose **Codeface**, a self-hostable coding practice and collaboration platform built with
**vanilla PHP (server-rendered pages + JSON APIs) and a MySQL/MariaDB relational database**,
with vanilla HTML/CSS/JS on the frontend — no frameworks, no Composer, deployable on any
shared LAMP host. The solution has three pillars, all backed by seeded SQL content:
(a) **Practice** — 526 curated problems across 12 topics, each presented in 12 programming
languages, with an in-browser judge for JavaScript/TypeScript (Web Worker) and Python
(Pyodide/WASM) and starter code, tests, and reference solutions for the rest, so no server-side
code execution is ever required; (b) **Learn** — 15 structured tracks (the 12 practice
languages plus SQL, Bash, and HTML & CSS) of 16 practical, scenario-driven lessons each across
four progressively locked levels, with runnable examples and per-lesson progress tracking;
and (c) **Collaborate** — persistent pair-programming rooms joinable by code, with per-language
pads, presence, submissions, plus leaderboards, ratings, skill-based matchmaking, and a Pro Mode
(debug labs, refactor gym) to sustain motivation. When a user clears an entire section, an
AI generator automatically produces 10 fresh items for them, indefinitely. All state — users, problems,
lessons, progress, rooms, submissions — is stored in MySQL tables with proper foreign keys,
and seed data is versioned so a fresh database bootstraps itself on first run.

**3. Expected Outcome.**
The result is a single platform where a learner can *learn a concept, immediately practice it,
and reinforce it with a partner* without leaving the site: machine-verified lesson examples
guarantee content correctness, per-language starters guarantee every problem is usable in every
supported language, and room codes turn practice into a social habit. Because the stack is pure
PHP + MySQL with zero build tooling, the entire system is transparent — any student can read,
run, and modify it — making the platform itself a learning artifact, not just a service.
