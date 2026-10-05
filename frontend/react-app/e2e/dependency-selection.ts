import { expect, type APIRequestContext, type Page } from '@playwright/test';
import { sessionHeaders } from './security-session';

export async function fixtureUnit(request: APIRequestContext, session: string, nombre = 'Autoservicio E2E') {
  const response = await request.get('http://e2e-api:8280/api/v1/manual-funciones/dependencias', { headers: sessionHeaders(session), params: { fecha: '2024-01-15', q: nombre } });
  expect(response.status()).toBe(200);
  const unidades = (await response.json()).data as { id: string; nombre: string }[];
  const unidad = unidades.find((item) => item.nombre === nombre);
  expect(unidad).toBeTruthy();
  return unidad!.id;
}
export async function selectEmployment(page: Page, cargoLabel: string, nombre = 'Autoservicio E2E') {
  await page.getByLabel('Fecha de vinculación').fill('2024-01-15');
  const unidad = page.getByRole('combobox', { name: 'Dependencia', exact: true });
  await expect(unidad.locator('option').filter({ hasText: nombre })).toHaveCount(1);
  await unidad.selectOption({ label: nombre });
  const cargo = page.getByRole('combobox', { name: 'Cargo', exact: true });
  await expect(cargo.locator('option').filter({ hasText: cargoLabel })).toHaveCount(1);
  await cargo.selectOption({ label: cargoLabel });
  await expect(page.getByText(/Perfil funcional asociado:/)).toBeVisible();
}
