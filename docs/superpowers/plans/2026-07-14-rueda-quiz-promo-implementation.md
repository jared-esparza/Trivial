# Rueda Quiz Promo Video Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Producir un vídeo promocional de Rueda Quiz de exactamente 10 segundos, reconocible sin sonido y entregado como MP4 1920 × 1080 a 30 fps.

**Architecture:** Una composición HyperFrames independiente vivirá en `promo-video/` y reutilizará copias locales de los recursos visuales del juego. Un único `index.html` contendrá cuatro escenas temporizadas, transiciones continuas y una línea de tiempo GSAP determinista; un archivo WAV local aportará música y efectos sincronizados.

**Tech Stack:** HyperFrames CLI, HTML5, CSS, GSAP 3.14.2, FFmpeg, MP4 H.264.

**User-approved path override:** Execute every plan path written as `promo-video/` under `video/` instead. The generated HyperFrames project and all rendered deliverables must remain inside `video/`.

## Global Constraints

- Resolución exacta: 1920 × 1080 píxeles, relación 16:9 horizontal.
- Duración exacta: 10 segundos a 30 fps.
- Entrega final: MP4 H.264.
- Sin locución; el montaje debe entenderse con el audio silenciado.
- Paleta: `#031a46`, `#ffffff`, `#e7bd5b`, `#0d4fea` y los seis colores de categoría existentes.
- La aplicación PHP, sus recursos originales y su comportamiento no se modifican.
- No se usarán cortes secos, animaciones infinitas, aleatoriedad ni texto pequeño.

---

### Task 1: Scaffold, visual identity and local assets

**Files:**
- Create: `promo-video/DESIGN.md`
- Create: `promo-video/index.html` through the HyperFrames scaffold
- Create: `promo-video/assets/home-board-art.png`
- Create: `promo-video/assets/local-board-art.png`
- Create: `promo-video/assets/home-ui.png`
- Create: `promo-video/assets/local-ui.png`

**Interfaces:**
- Consumes: `public/assets/home-board-art.png`, `public/assets/local-board-art.png`, `Mockups/Mockup_principal.png`, `Mockups/Mockup_partida_local.png`.
- Produces: a self-contained `promo-video/` source directory whose assets are addressed as `assets/<name>`.

- [ ] **Step 1: Verify the production environment**

Run:

```powershell
node --version
npx hyperframes doctor
```

Expected: Node 22 or newer, Chrome available and FFmpeg available.

- [ ] **Step 2: Scaffold the composition**

Run:

```powershell
npx hyperframes init promo-video --non-interactive
```

Expected: `promo-video/index.html` exists and HyperFrames reports successful initialization.

- [ ] **Step 3: Copy only the approved visual assets**

Run:

```powershell
New-Item -ItemType Directory -Force promo-video/assets
Copy-Item public/assets/home-board-art.png promo-video/assets/home-board-art.png
Copy-Item public/assets/local-board-art.png promo-video/assets/local-board-art.png
Copy-Item Mockups/Mockup_principal.png promo-video/assets/home-ui.png
Copy-Item Mockups/Mockup_partida_local.png promo-video/assets/local-ui.png
```

Expected: the four destination PNG files exist and the source files remain unchanged.

- [ ] **Step 4: Define the visual identity**

Create `promo-video/DESIGN.md` with these exact sections and rules:

```markdown
# Rueda Quiz Promo Visual Identity

## Style Prompt
Energetic competitive game-show motion design on a deep navy canvas. A gold rim, crisp white typography and six saturated category colors make the radial board the hero. Motion feels quick and decisive: controlled spins, elastic dice impacts, masked interface reveals and firm typographic landings.

## Colors
- Canvas Navy: #031a46
- Primary White: #ffffff
- Trophy Gold: #e7bd5b
- Action Blue: #0d4fea
- Category Blue: #2563eb
- Category Green: #16a34a
- Category Yellow: #eab308
- Category Orange: #f97316
- Category Red: #ef4444
- Category Purple: #7c3aed

## Typography
- Headlines and supporting copy: Oswald, sans-serif, from weight 350 to 900

## What NOT to Do
- No generic blue-purple gradients.
- No tiny interface copy.
- No jump cuts or infinite loops.
- No random particle placement.
- No full-screen dark linear gradients.
```

- [ ] **Step 5: Confirm the app remains untouched**

Run:

```powershell
git status --short
```

Expected: new changes are confined to `promo-video/` and this plan; the pre-existing untracked `TODO_LIST.md` remains untouched.

### Task 2: Deterministic 10-second composition

**Files:**
- Modify: `promo-video/index.html`

**Interfaces:**
- Consumes: the four local PNG assets and the palette in `promo-video/DESIGN.md`.
- Produces: `window.__timelines['rueda-quiz-promo']`, a paused GSAP timeline mapped to a root composition with `data-duration="10"`.

- [ ] **Step 1: Write the static hero layouts**

Replace the scaffold with a root composition using this structural contract:

```html
<main data-composition-id="rueda-quiz-promo" data-width="1920" data-height="1080" data-duration="10">
  <section id="challenge" class="scene" data-start="0" data-duration="2">...</section>
  <section id="play" class="scene" data-start="1.7" data-duration="4.6">...</section>
  <section id="promise" class="scene" data-start="5.9" data-duration="2.9">...</section>
  <section id="brand" class="scene" data-start="8.5" data-duration="1.5">...</section>
  <audio data-start="0" data-duration="10" data-track-index="10" src="assets/rueda-quiz-promo.wav" data-volume="0.8"></audio>
</main>
```

Each `.scene-content` fills the canvas with `width:100%`, `height:100%`, `padding`, flex layout and `box-sizing:border-box`. Decorative rings may use absolute positioning; content containers may not.

- [ ] **Step 2: Implement the approved copy and scene content**

Use exactly these visible messages:

```text
¿Cuánto sabes?
PARTIDA LOCAL
SALA ONLINE
Juega. Compite.
Conquista los 6 quesitos.
RUEDA QUIZ
Pon a prueba tus conocimientos
```

The challenge scene uses `home-board-art.png`; the play scene uses readable crops of `home-ui.png` and `local-ui.png`; the promise and brand scenes use the board, six category chips and a CSS-built six-segment wheel mark.

- [ ] **Step 3: Add deterministic GSAP choreography**

Register synchronously:

```html
<script src="https://cdn.jsdelivr.net/npm/gsap@3.14.2/dist/gsap.min.js"></script>
<script>
window.__timelines = window.__timelines || {};
const tl = gsap.timeline({ paused: true });
// Challenge entrances begin at 0.15 s.
// Cross-scene wipes overlap at 1.7 s, 5.9 s and 8.5 s.
// No pre-transition exit tween is added to scenes 1-3.
// The final brand remains fully visible at 10.0 s.
window.__timelines['rueda-quiz-promo'] = tl;
</script>
```

Use at least three entrance eases per scene, finite motion only, and `gsap.from()` for every visible scene element. Transition overlays perform the outgoing motion.

- [ ] **Step 4: Verify source-level timing rules**

Run:

```powershell
rg -n "Math.random|Date.now|repeat:\s*-1|display:\s*none|visibility" promo-video/index.html
```

Expected: no matches related to animation logic; static responsive CSS media rules are acceptable only if they do not hide timed clips.

### Task 3: Ten-second original audio bed

**Files:**
- Create: `promo-video/assets/rueda-quiz-promo.wav`

**Interfaces:**
- Consumes: scene beat timestamps `0.15`, `1.7`, `5.9`, and `8.5` seconds.
- Produces: a 48 kHz stereo WAV of exactly 10 seconds referenced by the composition audio element.

- [ ] **Step 1: Generate the instrumental pulse and hit accents**

Run FFmpeg with generated oscillators and delays:

```powershell
ffmpeg -y -f lavfi -i "sine=frequency=110:duration=10:sample_rate=48000" -f lavfi -i "sine=frequency=220:duration=0.16:sample_rate=48000" -filter_complex "[0:a]volume=0.05,tremolo=f=4:d=0.65[bed];[1:a]volume=0.22,adelay=150|150[h0];[1:a]volume=0.20,adelay=1700|1700[h1];[1:a]volume=0.24,adelay=5900|5900[h2];[1:a]volume=0.30,adelay=8500|8500[h3];[bed][h0][h1][h2][h3]amix=inputs=5:duration=longest:normalize=0,atrim=0:10,afade=t=out:st=9.8:d=0.2,aformat=sample_rates=48000:channel_layouts=stereo" promo-video/assets/rueda-quiz-promo.wav
```

Expected: FFmpeg exits with code 0.

- [ ] **Step 2: Verify audio duration and streams**

Run:

```powershell
ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 promo-video/assets/rueda-quiz-promo.wav
```

Expected: `10.000000` within one audio sample of tolerance.

### Task 4: HyperFrames validation and visual QA

**Files:**
- Modify: `promo-video/index.html` only when checks reveal a composition defect.
- Create: `promo-video/.hyperframes/` inspection artifacts.

**Interfaces:**
- Consumes: completed HTML, local assets and WAV.
- Produces: a lint-clean, contrast-clean and overflow-clean source composition.

- [ ] **Step 1: Run structural validation**

Run:

```powershell
npx hyperframes lint promo-video
npx hyperframes validate promo-video
```

Expected: both commands pass with no errors; contrast warnings must be corrected rather than suppressed.

- [ ] **Step 2: Inspect layout at dense samples and hero frames**

Run:

```powershell
npx hyperframes inspect promo-video --samples 15
npx hyperframes inspect promo-video --at 1.4,3.8,7.4,9.5
```

Expected: no unintended text overflow, clipping or off-canvas content.

- [ ] **Step 3: Generate and review the animation map**

Run:

```powershell
node C:/Users/jespa/.codex/plugins/cache/openai-curated-remote/hyperframes/0.1.2/skills/hyperframes/scripts/animation-map.mjs promo-video --out promo-video/.hyperframes/anim-map
```

Expected: no unexpected `offscreen`, `collision`, `invisible`, `paced-fast` or `paced-slow` flags; the only long hold is the readable final brand lockup.

- [ ] **Step 4: Render a draft for frame review**

Run:

```powershell
npx hyperframes render promo-video --output promo-video/rueda-quiz-promo-draft.mp4 --quality draft --fps 30
```

Expected: render exits successfully and the file contains 300 frames.

- [ ] **Step 5: Extract four representative frames**

Run:

```powershell
New-Item -ItemType Directory -Force promo-video/review-frames
ffmpeg -y -ss 1.4 -i promo-video/rueda-quiz-promo-draft.mp4 -frames:v 1 promo-video/review-frames/01-challenge.png
ffmpeg -y -ss 3.8 -i promo-video/rueda-quiz-promo-draft.mp4 -frames:v 1 promo-video/review-frames/02-play.png
ffmpeg -y -ss 7.4 -i promo-video/rueda-quiz-promo-draft.mp4 -frames:v 1 promo-video/review-frames/03-promise.png
ffmpeg -y -ss 9.5 -i promo-video/rueda-quiz-promo-draft.mp4 -frames:v 1 promo-video/review-frames/04-brand.png
```

Expected: all four PNG files exist and visually match their intended hero frames.

### Task 5: Final render and delivery verification

**Files:**
- Create: `promo-video/rueda-quiz-promo.mp4`

**Interfaces:**
- Consumes: the visually approved HyperFrames composition.
- Produces: the final user-facing MP4.

- [ ] **Step 1: Render the high-quality deliverable**

Run:

```powershell
npx hyperframes render promo-video --output promo-video/rueda-quiz-promo.mp4 --quality high --fps 30 --strict
```

Expected: render exits successfully.

- [ ] **Step 2: Verify exact delivery metadata**

Run:

```powershell
ffprobe -v error -select_streams v:0 -count_frames -show_entries stream=codec_name,width,height,r_frame_rate,nb_read_frames -show_entries format=duration -of json promo-video/rueda-quiz-promo.mp4
```

Expected: H.264, 1920 × 1080, 30/1 fps, 300 frames and 10.000 seconds.

- [ ] **Step 3: Run repository safety checks**

Run:

```powershell
git diff --check
git status --short
```

Expected: no whitespace errors; application files remain unchanged; `TODO_LIST.md` remains untouched.
