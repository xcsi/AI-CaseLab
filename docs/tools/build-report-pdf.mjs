// AI CaseLab report MD -> HTML -> PDF build script. Generalized to build any
// Markdown report in this shape (not just the original academic report).
//
// Setup (one-time, in this directory):
//   npm init -y && npm install marked playwright
// Usage:
//   node build-report-pdf.mjs [--src=path/to/report.md] [--out=path/to/report.pdf] [--title="PDF title"]
//   python merge-report-pdf.py --src=<same md path>   (only needed if the report has landscape/tall figures — see below)
//
// Defaults to the original academic report's paths if --src/--out are omitted.
//
// Renders the source Markdown to PDF using headless Microsoft Edge (via
// Playwright's `channel: 'msedge'`, which drives the system-installed Edge
// without downloading a separate Chromium build) and Mermaid v10 loaded from
// the jsDelivr CDN for diagram rendering. Local images referenced with a
// relative path in the Markdown (e.g. `![x](screenshots/foo.jpg)`) are
// resolved to absolute file:// URIs against the source .md's own directory,
// so `page.setContent()` (which has no base URL) can still load them.
//
// Some reports have a figure wide/tall enough that it reads poorly at normal
// portrait body-text width — e.g. the academic report's Figure 4.1 and 8.1.
// Those are extracted from the main flow and rendered as their own dedicated
// pages (landscape or full-height portrait), written alongside main.pdf as
// fig-N.pdf files for merge-report-pdf.py to splice back in. A report with no
// such figures (the common case) skips all of this automatically: main.pdf
// *is* the final PDF, written directly to --out, no merge step needed.
import { marked } from 'marked';
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = process.env.AICASELAB_ROOT || path.resolve(__dirname, '../..');

const argMap = Object.fromEntries(
  process.argv.slice(2)
    .filter((a) => a.startsWith('--'))
    .map((a) => {
      const [k, ...v] = a.slice(2).split('=');
      return [k, v.join('=') || true];
    })
);

const MD_PATH = argMap.src
  ? path.resolve(PROJECT_ROOT, argMap.src)
  : path.join(PROJECT_ROOT, 'docs/AI-CaseLab-Final-Project-Report.md');
const OUT_PDF = argMap.out
  ? path.resolve(PROJECT_ROOT, argMap.out)
  : MD_PATH.replace(/\.md$/, '.pdf');
const PDF_TITLE = argMap.title || 'AI CaseLab Report';
const MD_DIR = path.dirname(MD_PATH);
const WORK_DIR = __dirname;

const MERMAID_CDN = 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.mjs';

const SHARED_CSS = `
@page { size: A4; margin: 25mm 22mm; }
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body {
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 11.5pt;
  line-height: 1.55;
  color: #1a1a1a;
}
h1, h2, h3 {
  font-family: Arial, Helvetica, sans-serif;
  color: #14161c;
  font-weight: 700;
  break-after: avoid;
}
h1 { font-size: 21pt; margin: 0 0 14px 0; padding-bottom: 10px; border-bottom: 3px solid #2952e3; }
h2 { font-size: 14.5pt; margin: 26px 0 10px 0; }
h3 { font-size: 12pt; margin: 20px 0 8px 0; }
p { margin: 0 0 11px 0; text-align: justify; }
ul, ol { margin: 0 0 12px 0; padding-left: 24px; }
li { margin-bottom: 5px; }
li > p { margin-bottom: 4px; }
strong { font-weight: 700; }
em { font-style: italic; }
a { color: #2952e3; text-decoration: none; }
hr { border: none; border-top: 1px solid #ccc; margin: 20px 0; }

code {
  font-family: 'Consolas', 'Courier New', monospace;
  font-size: 0.88em;
  background: #edf0f4;
  padding: 1px 5px;
  border-radius: 3px;
}
pre {
  background: #12131b;
  color: #e8e8ed;
  padding: 16px 18px;
  border-radius: 8px;
  font-family: 'Consolas', 'Courier New', monospace;
  font-size: 9.2pt;
  line-height: 1.55;
  white-space: pre-wrap;
  word-wrap: break-word;
  margin: 0 0 14px 0;
}
pre code { background: none; padding: 0; font-size: 1em; color: inherit; }
pre.mermaid { background: none; padding: 0; border-radius: 0; }

table {
  width: 100%;
  border-collapse: collapse;
  margin: 0 0 14px 0;
  font-size: 10.2pt;
  break-inside: auto;
}
thead { display: table-header-group; }
tr { break-inside: avoid; }
th, td {
  border: 1px solid #d7dce3;
  padding: 7px 10px;
  text-align: left;
  vertical-align: top;
}
th { background: #edf0f4; font-weight: 700; font-family: Arial, Helvetica, sans-serif; font-size: 9.8pt; }

/* explicit page-break markers: single mechanism only, applied directly to the
   heading that follows a <!-- pagebreak --> comment (see postprocess step) */
h1.pagebreak, h2.pagebreak {
  break-before: page;
  page-break-before: always;
}
h1.pagebreak:first-child { break-before: avoid; page-break-before: avoid; }

.cover { text-align: center; }
.cover h1 { font-size: 27pt; border-bottom: none; padding-bottom: 0; }
.cover h2 { font-size: 14pt; font-weight: 400; font-family: Georgia, serif; margin-top: 4px; }
.cover h3 { font-size: 13pt; font-family: Georgia, serif; font-weight: 400; margin-top: 10px; }

.mermaid-figure {
  width: 100%;
  border: 1px solid #d7dce3;
  border-radius: 10px;
  background: #fbfcfe;
  padding: 20px;
  margin: 4px 0 16px 0;
  break-inside: avoid;
}
.mermaid-figure svg {
  /* auto-fit within the available box, preserving aspect ratio: wide diagrams
     scale up to fill the page width, tall/narrow ones scale up to fill the
     page height instead -- whichever bound the diagram's own shape hits first.
     (mermaid stamps an inline max-width on the svg itself; !important is
     required here to override that inline style.) */
  max-width: 100% !important;
  max-height: 225mm !important;
  width: auto !important;
  height: auto !important;
  display: block;
  margin: 0 auto;
}

figure.screenshot {
  margin: 6px 0 18px 0;
  break-inside: avoid;
  text-align: center;
}
figure.screenshot img {
  max-width: 100%;
  border: 1px solid #d7dce3;
  border-radius: 6px;
  display: block;
  margin: 0 auto;
}
figure.screenshot figcaption {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 9.5pt;
  color: #5a6072;
  margin-top: 7px;
  font-style: normal;
}

.landscape-placeholder { break-before: page; break-after: page; font-size: 6pt; color: #fff; }

.landscape-figure-page { width: 100%; }
.landscape-figure-page h2.fig-caption {
  font-family: Georgia, serif;
  font-weight: 700;
  font-size: 13.5pt;
  margin: 0 0 16px 0;
  break-before: avoid;
}
.landscape-figure-page .mermaid-figure { padding: 26px; margin: 0; }

/* fig8-1: a naturally tall/narrow decision-tree flowchart — scale it by
   HEIGHT to fill a dedicated portrait page, rather than stretching its width,
   which would otherwise blow its (already tall) aspect ratio up further. */
.tall-figure-page { width: 100%; text-align: center; }
.tall-figure-page h2.fig-caption {
  font-family: Georgia, serif;
  font-weight: 700;
  font-size: 13.5pt;
  margin: 0 0 16px 0;
  text-align: left;
  break-before: avoid;
}
.tall-figure-page .mermaid-figure { display: inline-block; width: auto; padding: 26px; }
.tall-figure-page .mermaid-figure svg {
  width: auto !important;
  height: 220mm !important;
  max-width: 166mm;
  display: block;
  margin: 0 auto;
}

/* --- Phase 3 additions: callouts, stat cards, icon badges, compact cards ---
   Single accent (#2952e3 Signal) + Slate neutrals + Night dark panel only,
   per the approved Design System -- no second accent color introduced. */
.ico { width: 18px; height: 18px; vertical-align: -3px; margin-right: 7px; color: #2952e3; flex-shrink: 0; }

.callout {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  background: #eef1fc;
  border-left: 4px solid #2952e3;
  border-radius: 6px;
  padding: 12px 16px;
  margin: 4px 0 16px 0;
  break-inside: avoid;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 10pt;
  color: #2a2d38;
}
.callout .ico { width: 20px; height: 20px; margin-top: 1px; }
.callout strong { color: #1a2a6e; }
.callout-label {
  display: block;
  font-size: 8.5pt;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #2952e3;
  margin-bottom: 3px;
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin: 6px 0 18px 0;
}
.stat-card {
  border: 1px solid #d7dce3;
  border-radius: 10px;
  background: #fbfcfe;
  padding: 16px 10px;
  text-align: center;
  break-inside: avoid;
}
.stat-card .ico { width: 26px; height: 26px; margin: 0 auto 8px auto; display: block; color: #2952e3; }
.stat-card .stat-number {
  display: block;
  font-family: Arial, Helvetica, sans-serif;
  font-weight: 700;
  font-size: 22pt;
  color: #14161c;
  line-height: 1.1;
}
.stat-card .stat-label {
  display: block;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 8.7pt;
  color: #5a6072;
  margin-top: 5px;
  line-height: 1.35;
}

.card-grid { display: grid; grid-template-columns: 1fr; gap: 10px; margin: 6px 0 16px 0; }
.info-card {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  border: 1px solid #d7dce3;
  border-radius: 8px;
  background: #fbfcfe;
  padding: 11px 14px;
  break-inside: avoid;
}
.info-card .ico-badge {
  flex-shrink: 0;
  width: 30px; height: 30px;
  border-radius: 50%;
  background: #2952e3;
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-family: Arial, Helvetica, sans-serif;
  font-weight: 700;
  font-size: 12pt;
}
.info-card .info-card-body { font-family: Arial, Helvetica, sans-serif; font-size: 9.8pt; color: #2a2d38; line-height: 1.45; }
.info-card .info-card-title { font-weight: 700; color: #14161c; display: block; margin-bottom: 2px; }

table.table-compare th:not(:first-child), table.table-compare td:not(:first-child) { text-align: center; }
table.table-compare .yes { color: #2952e3; font-weight: 700; }

/* --- Final-phase additions: table of contents, closing pages --- */
.toc-list { font-family: Arial, Helvetica, sans-serif; font-size: 10.5pt; column-count: 2; column-gap: 28px; }
.toc-list ol { list-style: none; padding-left: 0; counter-reset: toc; }
.toc-list li { break-inside: avoid; margin-bottom: 7px; color: #2a2d38; }
.toc-list li::marker { color: #2952e3; }
.toc-list ul { margin-top: 4px; margin-bottom: 4px; padding-left: 18px; }
.toc-list ul li { font-size: 9.3pt; color: #5a6072; margin-bottom: 3px; }

.closing-page { text-align: center; padding-top: 40px; }
.closing-page .ico { width: 30px; height: 30px; color: #2952e3; margin: 0 auto 14px auto; display: block; }
.closing-page p { text-align: center; max-width: 480px; margin-left: auto; margin-right: auto; }
`;

function readMd() {
  return fs.readFileSync(MD_PATH, 'utf8');
}

// --- Extract any oversized diagrams so they can be rendered on their own
// dedicated landscape/tall pages instead of being squeezed into portrait
// width. Non-throwing: a report whose captions don't match any target (i.e.
// nearly every report other than the original academic one) simply yields no
// extractions, and main() writes main.pdf straight to --out with no merge
// step. Add entries to `targets` if a future report needs the same treatment
// for a specific figure. ---
function extractLandscapeFigures(md) {
  const targets = [
    { key: 'fig4-1', captionRe: /\*\*Figure 4\.1 — High-Level System Architecture\*\*/ },
    { key: 'fig8-1', captionRe: /\*\*Figure 8\.1 — Empty-Reply Investigation Decision Tree\*\*/ },
  ];
  const extracted = {};
  let remaining = md;
  for (const t of targets) {
    const capMatch = remaining.match(t.captionRe);
    if (!capMatch) continue;
    const start = capMatch.index;
    const rest = remaining.slice(start);
    const fenceMatch = rest.match(/```mermaid\n([\s\S]*?)```/);
    if (!fenceMatch) throw new Error(`Could not find mermaid fence for ${t.key}`);
    const wholeChunk = rest.slice(0, fenceMatch.index + fenceMatch[0].length);
    extracted[t.key] = {
      caption: capMatch[0].replace(/\*\*/g, ''),
      mermaid: fenceMatch[1].trim(),
    };
    remaining = remaining.slice(0, start) + `<div class="landscape-placeholder">LANDSCAPE_PLACEHOLDER_${t.key.toUpperCase()}</div>\n` + remaining.slice(start + wholeChunk.length);
  }
  return { md: remaining, extracted };
}

// Turn "<!-- pagebreak -->\n\n<heading>" into "<heading class=pagebreak>" as the
// SOLE page-break mechanism (no separate forcing element) so natural page-end +
// forced-break can never stack into a blank page.
function markPagebreakHeadings(html) {
  return html.replace(
    /<!-- pagebreak -->\s*<h([12])( id="[^"]*")?>/g,
    '<h$1$2 class="pagebreak">'
  );
}

function mermaidHtml(source) {
  const escaped = source.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  return `<div class="mermaid-figure"><pre class="mermaid">${escaped}</pre></div>`;
}

function escapeHtml(s) {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function renderer() {
  const r = new marked.Renderer();
  const origCode = r.code.bind(r);
  // marked v13+ passes a single token object ({ text, lang, ... }) to
  // renderer.code(), not the old (code, infostring) pair.
  r.code = (token) => {
    const text = typeof token === 'object' ? token.text : token;
    const lang = ((typeof token === 'object' ? token.lang : '') || '').trim().split(/\s+/)[0];
    if (lang === 'mermaid') {
      return mermaidHtml(text);
    }
    return origCode(token);
  };
  // Local, relative image paths (screenshots) are resolved against the
  // source .md's own directory and inlined as base64 data URIs.
  // page.setContent() has no base URL, and Chromium's page.setContent()
  // origin isn't granted local-file-read permission even for absolute
  // file:// paths -- inlining sidesteps that restriction entirely. Wrapped
  // as <figure> so the alt text renders as a real caption.
  const MIME = { '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.svg': 'image/svg+xml', '.webp': 'image/webp' };
  r.image = (token) => {
    const href = typeof token === 'object' ? token.href : token;
    const alt = (typeof token === 'object' ? token.text : '') || '';
    let resolvedSrc = href;
    if (!/^(https?:|data:)/i.test(href)) {
      const abs = path.resolve(MD_DIR, href);
      const mime = MIME[path.extname(abs).toLowerCase()] || 'application/octet-stream';
      const b64 = fs.readFileSync(abs).toString('base64');
      resolvedSrc = `data:${mime};base64,${b64}`;
    }
    const captionHtml = alt ? `<figcaption>${escapeHtml(alt)}</figcaption>` : '';
    return `<figure class="screenshot"><img src="${resolvedSrc}" alt="${escapeHtml(alt)}">${captionHtml}</figure>`;
  };
  return r;
}

function buildPage({ bodyHtml, extraCss = '', title }) {
  return `<!doctype html>
<html><head><meta charset="utf-8"><title>${title}</title>
<style>${SHARED_CSS}${extraCss}</style>
</head><body>${bodyHtml}
<script type="module">
  import mermaid from '${MERMAID_CDN}';
  mermaid.initialize({ startOnLoad: false, theme: 'neutral', flowchart: { htmlLabels: true } });
  window.__mermaidReady = false;
  mermaid.run({ querySelector: '.mermaid' }).then(() => { window.__mermaidReady = true; })
    .catch((e) => { window.__mermaidError = String(e); window.__mermaidReady = true; });
</script>
</body></html>`;
}

async function printPdf(browser, html, outPath, opts) {
  const page = await browser.newPage();
  page.on('console', (msg) => console.log('[page]', msg.type(), msg.text()));
  page.on('pageerror', (e) => console.log('[pageerror]', e));
  await page.setContent(html, { waitUntil: 'load' });
  await page.waitForFunction(() => window.__mermaidReady === true, undefined, { timeout: 60000 });
  const err = await page.evaluate(() => window.__mermaidError);
  if (err) console.warn('mermaid warning:', err);
  await page.pdf({ path: outPath, printBackground: true, ...opts });
  await page.close();
}

async function main() {
  let md = readMd();
  const { md: mdNoLandscape, extracted } = extractLandscapeFigures(md);
  const hasLandscapeFigures = Object.keys(extracted).length > 0;

  // ---- main portrait document ----
  marked.use({ renderer: renderer() });
  let mainHtml = marked.parse(mdNoLandscape);
  mainHtml = markPagebreakHeadings(mainHtml);
  // first block is the cover <div align="center">...</div> -> tag it .cover
  mainHtml = mainHtml.replace('<div align="center">', '<div align="center" class="cover">');

  // Optional, this-build-only cap on mermaid diagram height (e.g.
  // --mermaid-max-height=140mm), scoped via extraCss rather than editing
  // SHARED_CSS's max-height:225mm, so the original academic report's
  // already-tuned figure pagination is never affected by this flag.
  const mermaidCss = argMap['mermaid-max-height']
    ? `.mermaid-figure svg { max-height: ${argMap['mermaid-max-height']} !important; }`
    : '';
  const mainPage = buildPage({ bodyHtml: mainHtml, extraCss: mermaidCss, title: PDF_TITLE });
  fs.writeFileSync(path.join(WORK_DIR, 'main.html'), mainPage);

  const browser = await chromium.launch({ channel: 'msedge', headless: true });

  if (!hasLandscapeFigures) {
    // Common case: no oversized figures needing special treatment -> main.pdf
    // *is* the final document, written straight to --out. No merge step.
    fs.mkdirSync(path.dirname(OUT_PDF), { recursive: true });
    const footerOpts = argMap.footer ? {
      displayHeaderFooter: true,
      headerTemplate: '<div></div>',
      footerTemplate: `<div style="width:100%;font-family:Arial,Helvetica,sans-serif;font-size:8px;color:#8a8f9c;text-align:center;padding-top:4px;">AI CaseLab — Executive Report &nbsp;·&nbsp; Page <span class="pageNumber"></span> of <span class="totalPages"></span></div>`,
      margin: { top: '25mm', bottom: '18mm', left: '22mm', right: '22mm' },
    } : {};
    await printPdf(browser, mainPage, OUT_PDF, footerOpts);
    await browser.close();
    console.log('Rendered', OUT_PDF, '- no merge step needed.');
    return;
  }

  await printPdf(browser, mainPage, path.join(WORK_DIR, 'main.pdf'), {});

  // ---- fig4-1: wide diagram -> its own landscape page, scaled to full width ----
  if (extracted['fig4-1']) {
    const fig41 = extracted['fig4-1'];
    const fig41Body = `<div class="landscape-figure-page"><h2 class="fig-caption">${fig41.caption}</h2>${mermaidHtml(fig41.mermaid)}</div>`;
    const fig41Css = `@page { size: A4 landscape; margin: 15mm 15mm; }`;
    const fig41Page = buildPage({ bodyHtml: fig41Body, extraCss: fig41Css, title: 'Figure 4.1' });
    fs.writeFileSync(path.join(WORK_DIR, 'fig4-1.html'), fig41Page);
    await printPdf(browser, fig41Page, path.join(WORK_DIR, 'fig4-1.pdf'), { landscape: true });
  }

  // ---- fig8-1: tall/narrow decision tree -> its own portrait page, scaled to full height ----
  if (extracted['fig8-1']) {
    const fig81 = extracted['fig8-1'];
    const fig81Body = `<div class="tall-figure-page"><h2 class="fig-caption">${fig81.caption}</h2>${mermaidHtml(fig81.mermaid)}</div>`;
    const fig81Css = `@page { size: A4; margin: 15mm 18mm; }`;
    const fig81Page = buildPage({ bodyHtml: fig81Body, extraCss: fig81Css, title: 'Figure 8.1' });
    fs.writeFileSync(path.join(WORK_DIR, 'fig8-1.html'), fig81Page);
    await printPdf(browser, fig81Page, path.join(WORK_DIR, 'fig8-1.pdf'), {});
  }

  await browser.close();
  console.log('Rendered main.pdf + figure pages in', WORK_DIR, '- run merge-report-pdf.py next.');
}

main().catch((e) => { console.error(e); process.exit(1); });
