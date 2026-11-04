import { createServer } from 'node:net';
import { spawn } from 'node:child_process';

async function isFree(port) {
    return new Promise((resolve) => {
        const server = createServer();
        server.once('error', () => resolve(false));
        server.once('listening', () => {
            server.close(() => resolve(true));
        });
        server.listen(port, '127.0.0.1');
    });
}

async function freePort(start) {
    let port = start;
    while (!(await isFree(port))) {
        port += 1;
    }
    return port;
}

const appPort = await freePort(8080);
const vitePort = await freePort(5173);

console.log(`Laravel: http://localhost:${appPort}`);
console.log(`Vite:    http://localhost:${vitePort}`);

const vite = spawn('pnpm', ['dev', '--', '--port', String(vitePort)], {
    stdio: 'inherit',
});
const laravel = spawn('php', ['artisan', 'serve', `--port=${appPort}`], {
    stdio: 'inherit',
});

function stop() {
    vite.kill();
    laravel.kill();
    process.exit(0);
}

process.on('SIGINT', stop);
process.on('SIGTERM', stop);

vite.on('exit', (code) => {
    if (code !== 0 && code !== null) {
        laravel.kill();
        process.exit(code);
    }
});
laravel.on('exit', (code) => {
    if (code !== 0 && code !== null) {
        vite.kill();
        process.exit(code);
    }
});
