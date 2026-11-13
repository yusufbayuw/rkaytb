import { test, expect } from '@playwright/test';
import fs from 'node:fs';

const password = 'Browser-test-password-2026!';
const routes = () => JSON.parse(fs.readFileSync('.tools/browser-fixtures.json', 'utf8')).resources;

async function login(page, email = 'admin@example.test') {
    await page.goto('/admin/login');
    await expect(page.locator('input[type=email]')).toBeVisible();
    await page.locator('input[type=email]').fill(email);
    await page.locator('input[type=password]').fill(password);
    await page.locator('button[type=submit]').click();
    await expect(page).toHaveURL(/\/admin$/, { timeout: 45000 });
    await expect(page.locator('h1').first()).toBeVisible();
}

async function rkaCategory(page, name = 'Investasi') {
    await page.goto(routes().RkaResource);
    await categoryFilter(page, name);
}

async function categoryFilter(page, name) {
    const filters = page.locator('.fi-tabs').first();
    const button = filters.getByRole('button', { name, exact: true });
    if (await button.isVisible()) await button.click();
    else {
        await filters.locator('[data-tabs-overflow-ellipsis]:visible').click();
        await page.getByRole('menuitem', { name, exact: true }).click();
    }
}

test.beforeEach(async ({ page }) => {
    page._applicationErrors = [];
    page.on('pageerror', error => page._applicationErrors.push(error.message));
    page.on('response', response => {
        if (response.url().startsWith('http://127.0.0.1:8771') && response.status() >= 500) {
            page._applicationErrors.push(`${response.status()} ${response.url()}`);
        }
    });
});

test.afterEach(async ({ page }) => {
    expect(page._applicationErrors).toEqual([]);
});

test('all resources, dashboard, and profile load with working Livewire and both themes', async ({ page }, info) => {
    test.setTimeout(120000);
    await login(page);
    await page.emulateMedia({ colorScheme: 'dark' });
    await expect(page.locator('html')).toHaveClass(/dark/);
    await page.screenshot({ path: info.outputPath('dashboard-dark.png'), fullPage: true });
    await page.emulateMedia({ colorScheme: 'light' });
    await expect(page.locator('html')).not.toHaveClass(/dark/);
    for (const url of Object.values(routes())) {
        const response = await page.goto(url);
        expect(response.status(), url).toBe(200);
        await expect(page.locator('h1').first()).toBeVisible();
        await page.waitForFunction(() => Boolean(window.Livewire?.all().length));
    }
    await page.goto('/admin/profile');
    await page.getByLabel('Nama', { exact: true }).fill(`Administrator Browser ${info.project.name}`);
    await page.getByRole('button', { name: 'Simpan Profil', exact: true }).click();
    await expect(page.getByText('Profil berhasil diperbarui.', { exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByLabel('Nama', { exact: true })).toHaveValue(`Administrator Browser ${info.project.name}`);
    await page.screenshot({ path: info.outputPath('profile-light.png'), fullPage: true });
});

test('workspace switches organizations and live advanced form options', async ({ page }, info) => {
    await login(page);
    await page.goto('/admin/rka-platform-workspace');
    await page.getByRole('button', { name: 'Organisasi Uji B', exact: true }).click();
    await expect(page.getByRole('combobox', { name: 'Siklus RKA' }).locator('option:checked')).toHaveText(/Siklus Uji B/);
    await page.getByRole('button', { name: 'Organisasi Uji A', exact: true }).click();
    await expect(page.getByRole('combobox', { name: 'Siklus RKA' }).locator('option:checked')).toHaveText(/Siklus Uji A/);
    await page.getByText('Bank Pertanyaan dan Penugasan', { exact: true }).click();
    const questionForm = page.locator('form').filter({ has: page.getByPlaceholder('Kode pertanyaan', { exact: true }) });
    await questionForm.locator('summary').click();
    await questionForm.getByLabel('Aktifkan aturan berbasis JSON').check();
    await expect(page.locator('textarea[wire\\:model="questionRulesJson"]')).toBeVisible();
    await questionForm.getByLabel('Aktifkan aturan berbasis JSON').uncheck();
    await expect(page.locator('textarea[wire\\:model="questionRulesJson"]')).toHaveCount(0);
    const code = `BROWSER-${info.project.name}-${Date.now()}`;
    await questionForm.getByPlaceholder('Kode pertanyaan', { exact: true }).fill(code);
    await questionForm.getByPlaceholder('Pertanyaan', { exact: true }).fill('Bagaimana hasil kegiatan tahun ini?');
    await questionForm.getByRole('button', { name: 'Simpan Pertanyaan', exact: true }).click();
    await expect(page.getByText('Pertanyaan ditambahkan.', { exact: true })).toBeVisible();
    await page.screenshot({ path: info.outputPath('workspace.png'), fullPage: true });
});

test('permission upload supports column mapping and duplicate reporting', async ({ page }, info) => {
    await login(page);
    await page.goto(routes().PermissionResource);
    await page.getByRole('button', { name: 'Impor permission', exact: true }).click();
    const dialog = page.getByRole('dialog');
    await page.locator('input[type=file]').setInputFiles({
        name: 'permissions.csv', mimeType: 'text/csv',
        buffer: Buffer.from(`description,name\nfirst,browser:${info.project.name}\nagain,browser:${info.project.name}\n`),
    });
    await dialog.getByLabel('Kolom nama permission').selectOption('1');
    await dialog.locator('button[type=submit]').click();
    await expect(page.getByText('1 permission dibuat; 1 duplikat dilewati.', { exact: true })).toBeVisible();
    await page.getByRole('searchbox', { name: 'Cari', exact: true }).fill(`browser:${info.project.name}`);
    await expect(page.getByText(`browser:${info.project.name}`, { exact: true }).first()).toBeVisible();
});

test('legacy export retains preview, column selection, XLSX download, and printing', async ({ page }) => {
    await login(page, 'unit-a@example.test');
    await rkaCategory(page);
    await page.getByRole('searchbox', { name: 'Cari', exact: true }).fill('Rincian');
    await expect(page.getByText('Rincian Unit Uji A', { exact: true })).toBeVisible();
    await expect(page.getByText('Rincian Unit Uji B', { exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Ekspor', exact: true }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByRole('textbox', { name: /^Nama berkas/ }).fill('anggaran-browser');
    await dialog.getByLabel('Pratinjau dan cetak').click();
    await expect(dialog.locator('iframe[title="Pratinjau ekspor"]')).toBeVisible();
    await expect(dialog.frameLocator('iframe').getByText('Rincian Unit Uji A', { exact: true })).toBeVisible();
    await expect(dialog.frameLocator('iframe').getByText('Rincian Unit Uji B', { exact: true })).toHaveCount(0);
    const downloadPromise = page.waitForEvent('download');
    await dialog.locator('button[type=submit]').filter({ hasNotText: 'Cetak' }).click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toBe('anggaran-browser.xlsx');
    await page.getByRole('button', { name: 'Ekspor', exact: true }).click();
    const printDialog = page.getByRole('dialog');
    await page.evaluate(() => { window._legacyPrinted = false; window.Livewire.on('legacy-print', () => { window._legacyPrinted = true; }); });
    await printDialog.getByRole('button', { name: 'Cetak seluruh hasil', exact: true }).click();
    await expect.poll(() => page.evaluate(() => window._legacyPrinted)).toBe(true);
});

test('organization-only member has no legacy finance access and private files require authorization', async ({ page }) => {
    await login(page, 'member@example.test');
    await page.goto('/admin/rka-platform-workspace');
    await expect(page.getByRole('button', { name: 'Organisasi Uji A', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Organisasi Uji B', exact: true })).toHaveCount(0);
    const response = await page.goto(routes().RkaResource);
    expect(response.status()).toBe(403);
    const evidence = await page.request.get('/rka-platform/evidence/999999/download');
    expect([403, 404]).toContain(evidence.status());
    const book = await page.request.get('/rka-platform/books/999999/download/pdf');
    expect([403, 404]).toContain(book.status());
});

test('logout invalidates the browser session', async ({ page }) => {
    await login(page);
    await page.locator('.fi-account-widget-logout-form button[type=submit]:visible').click();
    await expect(page).toHaveURL(/\/admin\/login$/);
    await page.goto('/admin/profile');
    await expect(page).toHaveURL(/\/admin\/login$/);
});

test('Shield roles and superadmin system pages work without exposing operational access', async ({ page }, info) => {
    test.setTimeout(180000);
    await login(page);
    const name = `shield-${info.project.name}-${Date.now()}`;
    await page.goto('/admin/roles/create');
    await page.getByRole('textbox', { name: /^Nama role/ }).fill(name);
    await page.locator('input[type=checkbox][value="read:unit"]').check();
    await page.getByRole('tab', { name: 'Permission tambahan', exact: true }).click();
    await page.locator('input[type=checkbox][value="Keep:Browser"]').check();
    await page.getByRole('button', { name: /^(Buat|Create)$/ }).click();
    await expect(page).not.toHaveURL(/\/admin\/roles\/create$/, { timeout: 45000 });
    await page.goto('/admin/roles');
    const row = page.getByRole('row').filter({ hasText: name });
    await expect(row).toBeVisible();
    await row.getByRole('link', { name: /^(Ubah|Edit)$/ }).click();
    await expect(page.locator('input[type=checkbox][value="read:unit"]')).toBeChecked();
    await page.getByRole('tab', { name: 'Permission tambahan', exact: true }).click();
    await expect(page.locator('input[type=checkbox][value="Keep:Browser"]')).toBeChecked();
    await page.goto('/admin/roles');
    await page.locator('.fi-ta-table thead input[type=checkbox]').check();
    await expect(page.locator('.fi-ta-table tbody input[type=checkbox]:checked').first()).toBeChecked();
    await page.locator('.fi-ta-table thead input[type=checkbox]').uncheck();
    const sort = page.locator('.fi-ta-table thead button').filter({ hasText: /Name|Nama/ }).first();
    await sort.click();
    await expect(sort.locator('xpath=ancestor::th')).toHaveAttribute('aria-sort', /ascending|descending/);
    await page.goto('/admin/system/activities');
    await expect(page.locator('.fi-ta-table tbody tr').first()).toBeVisible();
    await page.goto('/admin/system/health');
    await page.getByRole('button', { name: 'Periksa sekarang', exact: true }).click();
    await expect(page.getByText('Pemeriksaan terakhir:', { exact: false })).toBeVisible();
    await page.goto('/admin/system/logs');
    await expect(page.getByText('Browser plugin log', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: /Clear|Hapus|Bersihkan/ })).toHaveCount(0);
    await page.screenshot({ path: info.outputPath('system-logs.png'), fullPage: true });
    await page.context().clearCookies();
    await login(page, 'finance@example.test');
    for (const path of ['activities', 'health', 'logs']) expect((await page.goto(`/admin/system/${path}`)).status()).toBe(403);
});

test('wide financial tables keep sticky headers, drag scrolling, decimal editing and 500-row pagination', async ({ page }, info) => {
    test.setTimeout(300000);
    await login(page, 'unit-a@example.test');
    await rkaCategory(page);
    const editMode = page.getByRole('button', { name: 'Mode: Edit', exact: true });
    if (await editMode.count()) {
        await editMode.click();
        await expect(page.getByRole('button', { name: 'Mode: Normal', exact: true })).toBeVisible();
    }
    await page.locator('select:visible').filter({ has: page.locator('option[value="500"]') }).first().selectOption('500');
    await expect(page.locator('.fi-ta-table tbody tr')).toHaveCount(500, { timeout: 90000 });
    await expect.poll(() => page.evaluate(() => Boolean(window.filamentTableDragScrollInstalled && window.filamentTableStickyHeaderInstalled))).toBe(true);
    const table = page.locator('.fi-ta-table').first();
    const container = page.locator('.fi-ta-content-ctn').first();
    if (info.project.name === 'desktop') {
        const target = table.locator('thead th').nth(1);
        await target.scrollIntoViewIfNeeded();
        const box = await target.boundingBox();
        await page.mouse.move(box.x + Math.min(box.width / 2, 80), box.y + box.height / 2);
        await page.mouse.down();
        await page.mouse.move(box.x - 180, box.y + box.height / 2, { steps: 12 });
        await page.mouse.up();
        await expect.poll(() => container.evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
    } else {
        await container.scrollIntoViewIfNeeded();
        const box = await container.boundingBox();
        const client = await page.context().newCDPSession(page);
        const startX = box.x + box.width * 0.8;
        const y = 400;
        await client.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: startX, y }] });
        for (let step = 1; step <= 8; step++) await client.send('Input.dispatchTouchEvent', {
            type: 'touchMove', touchPoints: [{ x: startX - box.width * 0.6 * step / 8, y }],
        });
        await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await client.detach();
        await expect.poll(() => container.evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
    }
    await table.evaluate(element => window.scrollTo(0, window.scrollY + element.getBoundingClientRect().top + 250));
    await expect(table.locator('thead')).toHaveClass(/fi-table-sticky-header-active/);
    await expect(table.locator('thead')).toHaveCount(1);
    await page.screenshot({ path: info.outputPath('table-500-sticky.png') });
    // Verify maximum page size separately from the many inline edit controls.
    await page.locator('select:visible').filter({ has: page.locator('option[value="500"]') }).first().selectOption('50');
    await expect(page.locator('.fi-ta-table tbody tr')).toHaveCount(50);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.getByRole('button', { name: 'Mode: Normal', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Mode: Edit', exact: true })).toBeVisible({ timeout: 120000 });
    const detail = page.locator('.fi-ta-table tbody tr').filter({ has: page.locator('input[value="Rincian Unit Uji A"]') });
    const price = detail.locator('input[type=number]').nth(1);
    const priceValue = info.project.name === 'mobile' ? '1250.85' : '1250.75';
    await price.fill(priceValue);
    const saved = page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/livewire-'), { timeout: 120000 });
    await price.press('Tab');
    expect((await saved).status()).toBe(200);
    await expect.poll(() => price.inputValue().then(Number)).toBe(Number(priceValue));
    await page.reload();
    await expect.poll(() => page.locator('.fi-ta-table tbody tr').filter({ has: page.locator('input[value="Rincian Unit Uji A"]') }).locator('input[type=number]').nth(1).inputValue().then(Number)).toBe(Number(priceValue));
    await page.locator('select:visible').filter({ has: page.locator('option[value="500"]') }).first().selectOption('50');
    await expect(page.locator('.fi-ta-table tbody tr')).toHaveCount(50);
    await page.getByRole('button', { name: 'Mode: Edit', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Mode: Normal', exact: true })).toBeVisible();
});

test('dynamic RKA categories support manual input, exact large cents, filtered export and deactivation', async ({ page }, info) => {
    test.setTimeout(240000);
    await login(page);
    expect(await page.getByRole('link', { name: 'RKA', exact: true }).count()).toBe(1);
    for (const name of ['RKA Investasi', 'RKA Maintenance', 'RKA Consumable', 'RKA Operasional', 'RKA Gaji', 'RKA Pendapatan']) {
        expect(await page.getByRole('link', { name, exact: true }).count()).toBe(0);
    }
    const categoryName = `Pelatihan ${info.project.name}`;
    await page.goto(routes().RkaCategoryResource);
    await page.getByRole('button', { name: 'Tambah kategori', exact: true }).click();
    let dialog = page.getByRole('dialog');
    await dialog.getByLabel(/^Nama kategori/).fill(categoryName);
    await dialog.getByLabel(/^Kode kategori/).fill(`training-${info.project.name}`);
    await dialog.getByLabel(/^Jenis/).selectOption('expense');
    await dialog.getByLabel(/^Perhitungan/).selectOption('manual');
    await dialog.getByLabel(/^Awalan kode dokumen/).fill(info.project.name === 'mobile' ? 'PTM' : 'PTD');
    await dialog.getByLabel(/^Urutan/).fill('7');
    await dialog.getByRole('button', { name: /^(Buat|Create)$/ }).click();
    await expect(dialog).toBeHidden();
    await rkaCategory(page, categoryName);
    await page.getByRole('button', { name: 'Tambah RKA', exact: true }).click();
    dialog = page.getByRole('dialog');
    await dialog.getByLabel(/^Uraian/).fill(`Pelatihan manual ${info.project.name}`);
    await dialog.getByLabel(/^Unit/).selectOption({ label: 'Unit Uji A' });
    await expect(dialog.getByLabel(/^Volume/)).toBeHidden();
    await dialog.getByLabel(/^Total manual/).fill('1234.56');
    await dialog.getByLabel(/^Januari/).fill('1234.56');
    await dialog.getByRole('button', { name: /^(Buat|Create)$/ }).click();
    await expect(dialog).toBeHidden();
    const row = page.getByRole('row').filter({ hasText: `Pelatihan manual ${info.project.name}` });
    await expect(row.getByText('1.234,56', { exact: true }).first()).toBeVisible();
    await row.getByRole('button', { name: /^(Ubah|Edit)$/ }).click();
    dialog = page.getByRole('dialog');
    await dialog.getByLabel(/^Total manual/).fill('999999999999999.99');
    await dialog.getByRole('button', { name: /^Simpan/ }).click();
    await expect(dialog).toBeHidden();
    await page.getByRole('button', { name: 'Ekspor', exact: true }).click();
    dialog = page.getByRole('dialog');
    await dialog.getByLabel(/^Nama berkas/).fill(`kategori-${info.project.name}`);
    await dialog.getByLabel(/^Format/).selectOption('csv');
    const downloadPromise = page.waitForEvent('download');
    await dialog.locator('button[type=submit]').filter({ hasNotText: 'Cetak' }).click();
    const download = await downloadPromise;
    const output = info.outputPath('manual-category.csv'); await download.saveAs(output);
    const csv = fs.readFileSync(output, 'utf8');
    expect(csv).toContain('999999999999999.99');
    expect(csv).toContain(`Pelatihan manual ${info.project.name}`);
    expect(csv).not.toContain('Rincian Unit Uji A');
    await row.getByRole('button', { name: /^(Ubah|Edit)$/ }).click();
    dialog = page.getByRole('dialog');
    await dialog.getByLabel(/^Total manual/).fill('1234.56');
    await dialog.getByRole('button', { name: /^Simpan/ }).click();
    await expect(dialog).toBeHidden();
    await page.goto(routes().RkaCategoryResource);
    await page.getByRole('row').filter({ hasText: categoryName }).getByRole('button', { name: /^(Ubah|Edit)$/ }).click();
    dialog = page.getByRole('dialog');
    await dialog.getByRole('switch', { name: 'Aktif untuk anggaran baru', exact: true }).click();
    await dialog.getByRole('button', { name: /^Simpan/ }).click();
    await expect(dialog).toBeHidden();
    await rkaCategory(page, `${categoryName} (nonaktif)`);
    await expect(page.getByText(`Pelatihan manual ${info.project.name}`, { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Tambah RKA', exact: true })).toHaveCount(0);
    await page.screenshot({ path: info.outputPath('unified-rka-category.png'), fullPage: true });
});

test('finance sees global salary but other category rows remain scoped and read only', async ({ page }) => {
    await login(page, 'finance@example.test');
    await rkaCategory(page, 'Gaji');
    await expect(page.getByText('Gaji Unit Uji B', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Tambah RKA', exact: true })).toHaveCount(0);
    await expect(page.getByRole('button', { name: /^Mode:/ })).toHaveCount(0);
    await expect(page.getByRole('button', { name: /^(Ubah|Edit|Hapus|Duplikat)$/ })).toHaveCount(0);
    await categoryFilter(page, 'Investasi');
    await page.getByRole('searchbox', { name: 'Cari', exact: true }).fill('Rincian');
    await expect(page.getByText('Rincian Unit Uji A', { exact: true })).toBeVisible();
    await expect(page.getByText('Rincian Unit Uji B', { exact: true })).toHaveCount(0);
    expect((await page.goto(routes().RkaCategoryResource)).status()).toBe(403);
});
