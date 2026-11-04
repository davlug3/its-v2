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

console.log(`App:  http://localhost:${appPort}`);
console.log(`Vite: http://localhost:${vitePort}`);

const child = spawn('docker', ['compose', '--profile', 'dev', 'up', '-d'], {
    detached: true,
    stdio: 'ignore',
    env: { ...process.env, APP_PORT: String(appPort), VITE_PORT: String(vitePort) },
});
child.unref();