import { expect, test } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

// No login or real enrollment. This intentionally invalid Base32 value is only
// a visual fixture; it cannot configure a working authenticator/account.
const secret = 'VISUAL-ONLY-NOT-A-VALID-TOTP-SEED';
const user = { id: 999, name: 'Vista sintética', username: 'visual.only', documento: null,
  tipo_cuenta: 'backoffice', estado: true, must_change_password: false,
  mfa_pending: true, mfa_enabled: false, roles: ['admin'], permisos: [] };

for (const width of [360, 768, 1440]) {
  test(`enrollment accesible y responsive a ${width}px con fixture visual no funcional`, async ({ page }) => {
    await page.setViewportSize({ width, height: 1000 });
    const requests: string[] = [];
    page.on('request', (request) => requests.push(request.url()));
    await page.route('**/api/v1/auth/me', (route) => route.fulfill({ json: { success: true, data: user } }));
    await page.route('**/api/v1/auth/mfa/setup', (route) => route.fulfill({ json: { success: true, data: {
      secret, issuer: 'Certificaciones Villavicencio', account: user.username,
      otpauth_uri: `otpauth://totp/Certificaciones%20Villavicencio:visual.only?secret=${secret}&issuer=Certificaciones%20Villavicencio&algorithm=SHA1&digits=6&period=30`,
    } } }));
    await page.goto('/seguridad/mfa');
    const qr = page.getByRole('img', { name: /Código QR para agregar/ });
    await expect(qr).toBeVisible();
    await expect(page.getByLabel('Clave de configuración')).toBeHidden();
    const bounds = await qr.boundingBox();
    expect(Boolean(bounds && bounds.width >= 180 && bounds.width <= 220 && bounds.width === bounds.height)).toBe(true);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await expect(page.getByRole('heading', { name: 'Abra una aplicación de autenticación' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Ingrese el código de 6 dígitos' })).toBeVisible();
    await page.getByText('No puedo escanear el código', { exact: true }).focus();
    await page.keyboard.press('Enter');
    await expect(page.getByLabel('Clave de configuración')).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(page.getByLabel('Clave de configuración')).toBeHidden();
    await mkdir('evidence/mfa-enrollment', { recursive: true });
    await page.screenshot({ path: `evidence/mfa-enrollment/setup-${width}-synthetic.png`, fullPage: true, animations: 'disabled' });
    expect(requests.every((url) => !url.includes(secret) && !url.includes('otpauth'))).toBe(true);
    expect(requests.every((url) => new URL(url).origin === new URL(page.url()).origin)).toBe(true);
    expect(await page.evaluate(() => localStorage.length === 0 && sessionStorage.length === 0)).toBe(true);
  });
}
