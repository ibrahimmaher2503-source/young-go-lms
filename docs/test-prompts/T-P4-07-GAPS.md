# Tests prompt: T-P4-07 A3 Periodic Watermark Motion and Explicit T2 Render Evidence

You are closing the remaining test gaps for `T-P4-07` (`A3`: periodic watermark motion and explicit T2 render/fullscreen evidence). Do **not** modify production files under `app/`, `config/`, `database/`, `resources/`, `routes/`, or `lang/`. If a test exposes a bug, write it accurately, mark it `->skip('BUG: ...')`, and record it in `docs/reviews/BUGS_FOUND.md`.

Run inside `C:/projects/young-go-lms-pack-v3.2/young-go-lms-pack-v3.2` on branch `feat/lms-build`. Check `git rev-parse --show-toplevel` and `git branch --show-current` first. On Windows run Pest with `php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G`.

## Files and classes
- `config/media.php` (`watermark_interval_ms` default `12000`)
- `resources/views/learn/show.blade.php` (`[data-watermark]`, `data-watermark-tier`, `data-watermark-interval`, toolbar fullscreen trigger for T2 embeds)
- `resources/views/components/learning/lesson-media.blade.php` (`allow="fullscreen 'none'"` on `stageFullscreen`, `data-player-vimeo`)
- `resources/js/player.js` (`initWatermark(mark)`, 5-spot video/embed cycle, 3-spot audio-only cycle, `dataset.spot`, `dataset.moves`, interval floor `>= 250`, `pagehide` cleanup)
- `resources/css/app.css` (`[data-player-stage]:fullscreen :is(video, iframe)`)
- `App\Domain\Media\Watermark` (`applies()`, `label()`)
- `App\Domain\Media\PlaybackDescriptor`

## Behaviours and edge cases to test

### 1. JavaScript unit tests (`tests/Unit/player.test.mjs`)
- Verify `initWatermark` sets `dataset.spot === '0'` and `dataset.moves === '0'` on initial mount and reads `dataset.watermarkInterval` (falling back to `12000` when missing/invalid and clamping `< 250` or keeping `>= 250`).
- Invoke the registered `setInterval` callback repeatedly and assert `dataset.spot` cycles through `'0' -> '1' -> '2' -> '3' -> '4' -> '0'` while `dataset.moves` increments monotonically and `style.cssText` uses RTL-safe logical properties (`inset-inline-start`, `inset-inline-end`, `inset-inline`).
- Verify an audio-only stage (`stage.querySelector('audio')` with no `video, iframe`) cycles over 3 compact top spots (`top: .25rem`).
- Verify `window.dispatchEvent(new Event('pagehide'))` clears the registered watermark interval.

### 2. Feature/HTTP & Blade rendering tests (`tests/Feature/Media/MediaProvidersTest.php` and `tests/Feature/Identity/SessionLimitsTest.php`)
Tag with `->group('T-P4-07', 'A3', 'YG-GAP-VID-003')`:
- Render `resources/views/learn/show.blade.php` (or via a test-only route / `view('learn.show', ...)` with an authentic `PlaybackDescriptor` of tier `MediaAsset::TIER_RESTRICTED` (`T2`)) for:
  - `'vimeo'` embed (`T2` descriptor): assert `[data-watermark]`, `data-watermark-tier="T2"`, `data-watermark-interval="12000"`, masked `Watermark::label($learner)`, `allow="fullscreen 'none'"`, `data-player-vimeo`, and `[data-player-fullscreen]` button present.
  - non-Vimeo embed (e.g., `'bunny'` embed `PlaybackDescriptor(PlaybackDescriptor::EMBED, 'bunny', MediaAsset::TIER_RESTRICTED, ...)`): assert `[data-watermark][data-watermark-tier="T2"]`, `allow="fullscreen 'none'"`, and `[data-player-fullscreen]` button present in the toolbar.
  - `'bunny'` HLS video (`PlaybackDescriptor(PlaybackDescriptor::VIDEO, 'bunny', MediaAsset::TIER_RESTRICTED, ...)`): assert `[data-watermark][data-watermark-tier="T2"]` and `controlslist="nodownload nofullscreen"`.
- Verify changing `config(['media.watermark_interval_ms' => 500])` renders `data-watermark-interval="500"` (and values `< 250` render clamped `data-watermark-interval="250"`).
- Verify `T3` (`TIER_OPEN`) Vimeo/YouTube/Bunny renders **no** `[data-watermark]`.

### 3. Browser periodic motion & explicit T2 render/fullscreen evidence (`tests/Browser/WatermarkBrowserTest.php`)
Tag with `->group('browser', 'T-P4-07', 'A3', 'YG-GAP-VID-003')`:
- **Periodic motion test (desktop `1440×900` & phone `390×844` in `ar` and `en`, plus fullscreen):**
  - Set `config(['media.watermark_interval_ms' => 300])` before visiting `/learn/{course}/{lesson}`.
  - Record initial `dataset.spot`, `dataset.moves`, and `getBoundingClientRect()` (`left`, `top`).
  - Wait across >= 4 intervals (~1400ms total, polling/waiting until `Number(mark.dataset.moves) >= 4`) and assert:
    - Every spot (`1`, `2`, `3`, `4`) is visited in turn (`dataset.spot` changes),
    - `getBoundingClientRect()` (`left` and/or `top`) actually moved on screen,
    - At every spot (including spot `4` on `390×844` phone and in `:fullscreen`), `mark.getBoundingClientRect()` stays strictly inside `[data-player-stage]` / video bounds (`scrollWidth <= innerWidth`, zero overflow).
- **Explicit T2 embed render & fullscreen capture:**
  - Using a test-only route registered inside the test (`Route::middleware('web')->get('/__test/t2-watermark/{course}/{item}', ...)` rendering `learn.show` with a `PlaybackDescriptor(PlaybackDescriptor::EMBED, 'bunny', MediaAsset::TIER_RESTRICTED, embedUrl: '/__test/t2-frame')` pointing to a lightweight same-origin HTML fixture frame):
    - Assert `[data-watermark][data-watermark-tier="T2"]` is visible above the iframe, masked label matches `Watermark::label($learner)`, `iframe` lacks native `allowfullscreen`, and clicking `[data-player-fullscreen]` puts `[data-player-stage]` into `:fullscreen` with `[data-watermark]` visible inside `document.fullscreenElement` (`z-index === "10"` and `elementsFromPoint` includes `stage`).
    - Capture desktop (`1440×900`) and mobile (`390×844`) + fullscreen screenshots in AR/EN into `tests/Browser/screenshots/T-P4-07/`.

## Setup hints & SQLite limits
- `npm run build` must run before browser tests so Vite picks up `resources/js/player.js` and `resources/css/app.css`.
- Always use `Storage::fake(...)` for media fixtures and copy `tests/Fixtures/lesson-player.mp4`.
- Never touch `database/database.sqlite` or production files.

## Verification commands
```bash
npm run build
node --test tests/Unit/player.test.mjs
php -d memory_limit=2G vendor/bin/pest tests/Feature/Identity/SessionLimitsTest.php tests/Feature/Media/MediaProvidersTest.php tests/Feature/Media/MediaPrimitivesEdgeCasesTest.php
php -d opcache.enable_cli=0 -d extension=sockets -d memory_limit=2G vendor/bin/pest tests/Browser/WatermarkBrowserTest.php
```
