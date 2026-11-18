# Brand extraction from youngschool.com (O-027)

Date: 2026-11-18. Method: WebFetch on public pages only (no logins, forms or downloads); `curl -I` for HTTP and `nslookup` for DNS.

## Verdict

**Extraction is not usable as Young Go brand evidence.** `youngschool.com` is reachable, but it is not the Young Go site. It is "Young School", a US childcare and preschool site (page title "Maryland Daycare, Preschool, and Infant Childcare | Young School") operated by Learning Care Group (US) No. 2 Inc. This confirms the conflict already recorded as B-01 / O-001. The owner's stated legacy hostname appears wrong, or the real legacy site has moved. No Young Go brand values were found, and none are invented here.

## What was fetched

| URL | Result |
|---|---|
| `https://youngschool.com` | WebFetch: `getaddrinfo ENOTFOUND` (tool-side DNS failure). `curl -I` returned `301` and `nslookup` resolved `23.101.174.2`, so the domain is live; the redirect goes to `www`. |
| `https://www.youngschool.com` | OK. US childcare homepage. |
| `https://www.youngschool.com/about-us/contact-us/` | OK. Confirms operator and "one of the group's brands". |

## Findings (about the unrelated site; recorded only to prove the mismatch)

| Fact | Value | Where found | Confidence |
|---|---|---|---|
| Operator | Learning Care Group (US) No. 2 Inc. | Footer, contact page | High |
| Site name | "Young School" (EN only) | Title, logo alt text | High |
| Arabic, Egypt, LMS or online-course content | None | Homepage and contact page | High |
| Tagline | Not captured (page title is a childcare SEO title) | Title | Low |
| Navigation | Education, Our Programs, Our Schools, About Us, Our Blog; utility: Schedule a Tour, Pay Online, Careers | Homepage | High |
| Footer | Repeated nav, Facebook/Instagram/Pinterest/YouTube, legal links, (c) 2026 Learning Care Group | Homepage | High |
| Contact | Phone 866.561.3414 (US); no email or address | Homepage | High |
| Logo URL | Not obtainable: the fetcher returns text only, `img src` was not exposed | n/a | None (UNKNOWN) |
| Favicon | UNKNOWN (link tags not exposed) | n/a | None |
| Primary/secondary/accent colors | UNKNOWN (no CSS or inline styles exposed) | n/a | None |
| Font families | UNKNOWN | n/a | None |
| Border radius, button style | UNKNOWN | n/a | None |

Because the site belongs to another organization, its logo, colors and copy must not be adopted even if later extracted. Doing so would also risk trademark problems.

## Already decided in the repo (not contradicted)

- `docs/brand/BRAND_SHEET.md` (D-027, approved 2026-11-18): owner approved the preview palette (operational teal primary sampled from approved reference RGB(2,109,108), terracotta accent hue 40, warm-gray neutral), locally bundled Noto Sans Arabic, and a plain localized Young Go wordmark. Legacy hostname, legacy artwork, typefaces and usage rules remain UNKNOWN.
- `docs/design-system/`: `reference-pack-2026-11-17/` (28 numbered PNGs, `15-public-home.png` is the home reference), `visual-contract.json`, `VISUAL_ACCEPTANCE.md`, `TABLE_WORKSPACE.md`, calm-workspace approved and rejected references. `tests/Browser/screenshots/` was not changed.

## Mapping to existing tokens (`resources/css/tokens.css`)

| Brand fact | Existing token | Current value | Live-site value | Differs? |
|---|---|---|---|---|
| Primary | `--yg-brand-600` / `--yg-color-primary` | oklch(0.484 0.0823 193.66) (teal) | UNKNOWN | Cannot compare |
| Accent | `--yg-accent-*` / `--yg-color-accent` | terracotta hue 40 | UNKNOWN | Cannot compare |
| Neutral | `--yg-neutral-*` | hue 250 in tokens (BRAND_SHEET says hue 78 "warm gray") | UNKNOWN | Internal doc/token mismatch, see below |
| Font | `--font-sans` | Noto Sans Arabic, system-ui fallbacks | UNKNOWN | Cannot compare |
| Radius | `--yg-radius-card` / `--yg-radius-control` | 1rem / 0.625rem | UNKNOWN | Cannot compare |
| Logo | `.yg-brand-lockup` (filament-theme.css) | text wordmark, system-ui 1.75rem | UNKNOWN | Cannot compare |

Tokens that differ from live-site values: none provable, since no valid live values exist.

## Recommendations

1. Make no token changes from this extraction. Keep D-027 as the project brand.
2. PO to confirm Young Go's real legacy hostname (B-01 / O-001), or confirm there is none. Then re-run this extraction on the confirmed site.
3. If the real site is found, fetch its CSS directly (a browser-based inspection is needed, since WebFetch returns text only) to capture logo, favicon, color and font values, and record only URLs for logos.
4. Separate small finding: `BRAND_SHEET.md` says the neutral is "Warm gray, OKLCH hue 78", but `tokens.css` neutrals use hue 250 (cool). PO to decide whether to fix the doc or the tokens. Not changed here.
5. Keep official asset fields UNKNOWN until the owner supplies logo files and usage rules.
