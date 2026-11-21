import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../resources/css/tokens.css', import.meta.url), 'utf8');
const declarations = (block) =>
    new Map([...block.matchAll(/(--yg-[\w-]+):\s*([^;]+);/g)].map(([, name, value]) => [name, value]));

const light = declarations(source.match(/:root\s*{([^}]+)}/)[1]);
const dark = declarations(source.match(/\[data-theme="dark"\]\s*{([^}]+)}/)[1]);
const publicIdentity = declarations(source.match(/\.public-identity\s*{([^}]+)}/)[1]);
const requiredPairs = [
    ['body', 'page'], ['body', 'surface'], ['body', 'assistant'],
    ['muted', 'page'], ['muted', 'surface'], ['muted', 'assistant'],
    ...['primary', 'accent', 'info', 'success', 'warning', 'danger',
        'ai-waiting', 'ai-review', 'ai-processing', 'ai-failed', 'ai-ready'].map(name => ['on-' + name, name]),
];

function luminance(tokens, semantic) {
    const alias = tokens.get('--yg-color-' + semantic)?.match(/^var\((--yg-[\w-]+)\)$/)?.[1];
    const color = tokens.get(alias)?.match(/^oklch\(([\d.]+) ([\d.]+) ([\d.]+)\)$/);
    if (!color) throw new Error(`Unresolved OKLCH color: ${semantic}`);

    // OKLCH to linear sRGB. Ship colors in sRGB so ratios match displayed output.
    const [lightness, chroma, degrees] = color.slice(1).map(Number);
    const hue = degrees * Math.PI / 180;
    const a = chroma * Math.cos(hue);
    const b = chroma * Math.sin(hue);
    const l = (lightness + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const m = (lightness - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const s = (lightness - 0.0894841775 * a - 1.291485548 * b) ** 3;
    const rgb = [
        4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
        -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
        -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s,
    ];

    if (rgb.some(channel => channel < -0.000001 || channel > 1.000001)) {
        throw new Error(`OKLCH color outside sRGB gamut: ${semantic}`);
    }
    return rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722;
}

const themes = [
    ['light', light],
    ['dark', new Map([...light, ...dark])],
    ['public light', new Map([...light, ...publicIdentity])],
    ['public dark', new Map([...light, ...dark, ...publicIdentity])],
];
for (const [theme, tokens] of themes) {
    for (const [text, surface] of requiredPairs) {
        const values = [luminance(tokens, text), luminance(tokens, surface)];
        const ratio = (Math.max(...values) + 0.05) / (Math.min(...values) + 0.05);
        if (ratio < 4.5) {
            console.error(`${theme}: ${text} on ${surface} has contrast ${ratio.toFixed(2)}:1 (minimum 4.5:1)`);
            process.exitCode = 1;
        }
    }
}
if (!process.exitCode) console.log(`AA contrast passed for ${requiredPairs.length * themes.length} light/dark pairs.`);
