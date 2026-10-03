const fs = require('node:fs');
const assert = require('node:assert/strict');
const css = fs.readFileSync('assets/css/refinements.css', 'utf8');
function luminance(hex) {
    const parts = hex.length === 4 ? hex.slice(1).split('').map(ch => ch + ch) : hex.slice(1).match(/../g);
    const channels = parts.map(part => parseInt(part, 16) / 255).map(value => value <= .04045 ? value / 12.92 : ((value + .055) / 1.055) ** 2.4);
    return channels[0] * .2126 + channels[1] * .7152 + channels[2] * .0722;
}
function contrast(a, b) {
    const x = luminance(a), y = luminance(b);
    return (Math.max(x, y) + .05) / (Math.min(x, y) + .05);
}
for (const [label, selector] of [['light', 'body.ui-refined'], ['dark', 'body.ui-refined.theme-dark']]) {
    const start = css.indexOf(`${selector} {`);
    const block = css.slice(start, css.indexOf('}', start));
    const tokens = Object.fromEntries([...block.matchAll(/--([\w-]+):\s*(#[a-f\d]+);/gi)].map(match => [match[1], match[2]]));
    for (const foreground of ['text-primary', 'text-secondary', 'accent', 'success', 'warning', 'danger']) {
        for (const background of ['surface', 'surface-soft']) assert(contrast(tokens[foreground], tokens[background]) >= 4.5, `${label}: ${foreground} on ${background} needs readable contrast`);
    }
    assert(contrast(tokens['accent-ink'], tokens.accent) >= 4.5, `${label}: primary button text must contrast with its background`);
}
console.log('Light and dark theme token contrast checks passed. Visual layout still requires a browser.');
