// AI CaseLab report MD -> HTML -> PDF build script.
//
// Setup (one-time, in this directory):
//   npm init -y && npm install marked playwright
// Usage:
//   node build-report-pdf.mjs        (writes intermediate files here, then merge-report-pdf.py splices them)
//   python merge-report-pdf.py       (requires: pip install pymupdf)
//
// Renders docs/AI-CaseLab-Final-Project-Report.md to
// docs/AI-CaseLab-Final-Project-Report.pdf using headless Microsoft Edge
// (via Playwright's `channel: 'msedge'`, which drives the system-installed
// Edge without downloading a separate Chromium build) and Mermaid v10 loaded
// from the jsDelivr CDN for diagram rendering.
//
// Two figures (4.1 "High-Level System Architecture" and 8.1 "Empty-Reply
// Investigation Decision Tree") are wide/tall enough that they read poorly at
// portrait body-text width, so they're extracted from the main flow and
// rendered as their own dedicated pages (4.1 landscape, 8.1 a full-height
// portrait page), then spliced back into the merged PDF at the same spot by
// merge-report-pdf.py. Add more entries to TARGETS in extractLandscapeFigures
// if a future diagram needs the same treatment.
import { marked } from 'marked';
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = process.env.AICASELAB_ROOT || path.resolve(__dirname, '../..');
const MD_PATH = path.join(PROJECT_ROOT, 'docs/AI-CaseLab-Final-Project-Report.md');
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
`;

function readMd() {
  return fs.readFileSync(MD_PATH, 'utf8');
}

// --- Extract the two oversized diagrams so they can be rendered on their own
// dedicated landscape pages instead of being squeezed into portrait width. ---
function extractLandscapeFigures(md) {
  const targets = [
    { key: 'fig4-1', captionRe: /\*\*Figure 4\.1 — High-Level System Architecture\*\*/ },
    { key: 'fig8-1', captionRe: /\*\*Figure 8\.1 — Empty-Reply Investigation Decision Tree\*\*/ },
  ];
  const extracted = {};
  for (const t of targets) {
    const capMatch = md.match(t.captionRe);
    if (!capMatch) throw new Error(`Could not find caption for ${t.key}`);
    const start = capMatch.index;
    const rest = md.slice(start);
    const fenceMatch = rest.match(/```mermaid\n([\s\S]*?)```/);
    if (!fenceMatch) throw new Error(`Could not find mermaid fence for ${t.key}`);
    const wholeChunk = rest.slice(0, fenceMatch.index + fenceMatch[0].length);
    extracted[t.key] = {
      caption: capMatch[0].replace(/\*\*/g, ''),
      mermaid: fenceMatch[1].trim(),
    };
    md = md.slice(0, start) + `<div class="landscape-placeholder">LANDSCAPE_PLACEHOLDER_${t.key.toUpperCase()}</div>\n` + md.slice(start + wholeChunk.length);
  }
  return { md, extracted };
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

  // ---- main portrait document ----
  marked.use({ renderer: renderer() });
  let mainHtml = marked.parse(mdNoLandscape);
  mainHtml = markPagebreakHeadings(mainHtml);
  // first block is the cover <div align="center">...</div> -> tag it .cover
  mainHtml = mainHtml.replace('<div align="center">', '<div align="center" class="cover">');

  const mainPage = buildPage({ bodyHtml: mainHtml, title: 'AI CaseLab Report' });
  fs.writeFileSync(path.join(WORK_DIR, 'main.html'), mainPage);

  // ---- fig4-1: wide diagram -> its own landscape page, scaled to full width ----
  const fig41 = extracted['fig4-1'];
  const fig41Body = `<div class="landscape-figure-page"><h2 class="fig-caption">${fig41.caption}</h2>${mermaidHtml(fig41.mermaid)}</div>`;
  const fig41Css = `@page { size: A4 landscape; margin: 15mm 15mm; }`;
  const fig41Page = buildPage({ bodyHtml: fig41Body, extraCss: fig41Css, title: 'Figure 4.1' });
  fs.writeFileSync(path.join(WORK_DIR, 'fig4-1.html'), fig41Page);

  // ---- fig8-1: tall/narrow decision tree -> its own portrait page, scaled to full height ----
  const fig81 = extracted['fig8-1'];
  const fig81Body = `<div class="tall-figure-page"><h2 class="fig-caption">${fig81.caption}</h2>${mermaidHtml(fig81.mermaid)}</div>`;
  const fig81Css = `@page { size: A4; margin: 15mm 18mm; }`;
  const fig81Page = buildPage({ bodyHtml: fig81Body, extraCss: fig81Css, title: 'Figure 8.1' });
  fs.writeFileSync(path.join(WORK_DIR, 'fig8-1.html'), fig81Page);

  const browser = await chromium.launch({ channel: 'msedge', headless: true });
  await printPdf(browser, mainPage, path.join(WORK_DIR, 'main.pdf'), {});
  await printPdf(browser, fig41Page, path.join(WORK_DIR, 'fig4-1.pdf'), { landscape: true });
  await printPdf(browser, fig81Page, path.join(WORK_DIR, 'fig8-1.pdf'), {});
  await browser.close();

  console.log('Rendered main.pdf, fig4-1.pdf, fig8-1.pdf in', WORK_DIR, '- run merge-report-pdf.py next.');
}

main().catch((e) => { console.error(e); process.exit(1); });
