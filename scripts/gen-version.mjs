import { execSync } from 'node:child_process';
import { writeFileSync, mkdirSync } from 'node:fs';

const env = process.env.APP_ENV || process.env.VITE_APP_ENV || 'local';

let hash = 'unknown';
try {
    hash = execSync('git rev-parse --short HEAD', { encoding: 'utf8' }).trim();
} catch {
    // not a git checkout
}

const now = new Date();
const pad = (n) => String(n).padStart(2, '0');
const build = `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}`;

const version = `${hash}_${build}_${env}`;

writeFileSync('resources/js/version.ts', `export const APP_VERSION = ${JSON.stringify(version)};\n`);

mkdirSync('storage/app', { recursive: true });
writeFileSync('storage/app/version.txt', version);

console.log('Generated version:', version);
