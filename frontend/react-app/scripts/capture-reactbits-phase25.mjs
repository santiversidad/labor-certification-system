import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

const root = 'http://localhost:3200';
const output = 'evidence/reactbits-phase25';
const employee = process.env.CT_EMPLOYEE_DOCUMENT ?? '000000003';
const employeePassword = process.env.CT_EMPLOYEE_PASSWORD ?? 'password';
const admin = process.env.CT_ADMIN_DOCUMENT ?? '000000001';
const adminPassword = process.env.CT_ADMIN_PASSWORD ?? 'password';

async function capture(page, filename, fullPage = true) {
  await page.waitForFunction(() => !document.querySelector('.animate-pulse'));
  await page.waitForFunction(() => !/Cargando información|Cargando pantalla/.test(document.body.innerText));
  await page.evaluate(async () => {
    await Promise.all(document.getAnimations().filter((animation) => animation.playState === 'running').map((animation) => animation.finished.catch(() => {})));
  });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
  await page.screenshot({ path: `${output}/${filename}`, fullPage });
  if (overflow) {
    const offenders = await page.evaluate(() => [...document.querySelectorAll('body *')]
      .filter((element) => element.getBoundingClientRect().right > window.innerWidth + 1)
      .slice(0, 8)
      .map((element) => `${element.tagName}.${element.className}: ${Math.round(element.getBoundingClientRect().right)}`));
    throw new Error(`Horizontal overflow before ${filename}: ${offenders.join(', ')}`);
  }
}

async function login(page, document, password, destination) {
  await page.goto(`${root}/login`);
  await page.getByLabel('Cédula', { exact: true }).fill(document);
  await page.getByLabel('Contraseña', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  await page.waitForURL(new RegExp(destination));
}

await mkdir(output, { recursive: true });
const browser = await chromium.launch({ channel: 'msedge', headless: true });
try {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 });
  await page.goto(`${root}/login`);
  await page.getByRole('heading', { name: 'Iniciar sesión' }).waitFor();
  await capture(page, '01-login-desktop.png');
  await page.setViewportSize({ width: 360, height: 800 });
  await capture(page, '02-login-mobile.png');

  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page, employee, employeePassword, '/app/inicio');
  await page.getByText('Certificado laboral sencillo').waitFor();
  await capture(page, '03-funcionario-dashboard-desktop.png');
  await page.setViewportSize({ width: 360, height: 800 });
  await capture(page, '04-funcionario-dashboard-mobile.png');
  await page.setViewportSize({ width: 1440, height: 900 });
  for (const [type, filename] of [['sencillo', '20-solicitud-sencillo.png'], ['funciones', '21-solicitud-funciones.png']]) {
    await page.goto(`${root}/app/solicitudes/nueva?modalidad=${type}`);
    await page.getByRole('heading', { name: 'Confirmar certificación laboral' }).waitFor();
    await capture(page, filename);
  }
  await page.getByRole('button', { name: 'Cerrar sesión' }).click();

  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page, admin, adminPassword, '/admin/dashboard');
  await page.getByRole('heading', { name: 'Dashboard', exact: true }).waitFor();
  await capture(page, '05-admin-dashboard-desktop.png');
  await page.setViewportSize({ width: 360, height: 800 });
  await capture(page, '06-admin-dashboard-mobile.png');
  await page.getByRole('button', { name: 'Abrir menú' }).click();
  if (!await page.getByRole('navigation', { name: 'Navegación administrativa' }).last().isVisible()) throw new Error('Mobile drawer did not open');
  await page.getByRole('button', { name: 'Cerrar menú' }).click();
  await page.setViewportSize({ width: 768, height: 900 });
  await capture(page, '12-admin-dashboard-tablet.png');
  await page.setViewportSize({ width: 1440, height: 900 });

  for (const [path, heading, filename] of [
    ['/admin/funcionarios', 'Funcionarios', '08-funcionarios.png'],
    ['/admin/manual-funciones', 'Manual de Funciones', '09-manual.png'],
    ['/admin/certificaciones', 'Certificados', '10-certificaciones.png'],
  ]) {
    await page.goto(`${root}${path}`);
    await page.getByRole('heading', { name: heading, exact: true }).waitFor();
    await capture(page, filename);
  }
  const certificadoDetail = await page.locator('a[href^="/admin/certificaciones/"]').first().getAttribute('href');
  await page.goto(`${root}/admin/funcionarios`);
  await page.getByRole('button', { name: 'Restablecer acceso' }).first().click();
  await page.getByRole('dialog', { name: 'Restablecer acceso' }).waitFor();
  await capture(page, '22-modal-confirmacion.png', false);
  await page.keyboard.press('Escape');
  if (await page.getByRole('dialog').count()) throw new Error('Dialog did not close with Escape');
  await page.goto(`${root}/admin/funcionarios`);
  await page.getByRole('button', { name: 'Ver funcionario' }).first().waitFor();
  const funcionarioDetail = await page.locator('a[href^="/admin/funcionarios/"]').evaluateAll((anchors) =>
    anchors.map((anchor) => anchor.getAttribute('href')).find((href) => /\/\d+$/.test(href ?? '')));
  for (const [path, heading, filename] of [
    ['/admin/funcionarios/nuevo', 'Crear funcionario', '13-alta-funcionario.png'],
    ['/admin/cargos', 'Cargos y grados', '14-cargos.png'],
    ['/admin/configuracion', 'Pago de certificaciones', '15-configuracion.png'],
    ['/admin/auditoria', 'Auditoría', '16-auditoria.png'],
    ['/admin/reportes', 'Reportes', '17-reportes.png'],
    [funcionarioDetail, 'Información básica', '18-detalle-funcionario.png'],
    [certificadoDetail, 'Detalle de certificado', '19-detalle-certificado.png'],
  ]) {
    if (!path) continue;
    await page.goto(`${root}${path}`);
    if (heading === 'Información básica') await page.getByText(heading).waitFor();
    else await page.getByRole('heading', { name: heading, exact: true }).waitFor();
    await capture(page, filename);
  }
  await page.goto(`${root}/validar-certificado/codigo-evidencia-no-valido`);
  await page.getByRole('heading', { name: 'Validar certificado' }).waitFor();
  await page.getByText(/No fue posible validar|no encontrado|inválido/i).first().waitFor();
  await capture(page, '11-validacion-publica.png');

  const reduced = await browser.newPage({ viewport: { width: 360, height: 800 }, reducedMotion: 'reduce' });
  await reduced.goto(`${root}/login`);
  await reduced.getByRole('heading', { name: 'Iniciar sesión' }).waitFor();
  if (await reduced.locator('.rb-gradient-text').evaluate((element) => getComputedStyle(element).animationName !== 'none')) {
    throw new Error('GradientText animates with reduced motion');
  }
  await login(reduced, employee, employeePassword, '/app/inicio');
  await reduced.getByText('Certificado laboral sencillo').waitFor();
  const spotlightTransition = await reduced.locator('.rb-spotlight-card').first().evaluate((element) => getComputedStyle(element, '::before').transitionDuration);
  if (parseFloat(spotlightTransition) > 0.001) throw new Error(`Spotlight transition with reduced motion: ${spotlightTransition}`);
  await reduced.close();
} finally {
  await browser.close();
}
