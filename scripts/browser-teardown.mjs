import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

export default function teardown() {
    const metadataFile = fileURLToPath(new URL('../.tools/browser-server.json', import.meta.url));
    const stopFile = fileURLToPath(new URL('../.tools/browser-server-stop.json', import.meta.url));
    if (!fs.existsSync(metadataFile)) return;
    const metadata = JSON.parse(fs.readFileSync(metadataFile, 'utf8'));
    if (typeof metadata.stopToken !== 'string' || !/^[a-f0-9]{48}$/.test(metadata.stopToken)) {
        throw new Error('Invalid isolated browser server metadata.');
    }
    fs.writeFileSync(stopFile, JSON.stringify({ stopToken: metadata.stopToken }));
}
