import { getErrorMessage, getValidationErrors } from '../../../lib/utils/errors';
import type { Run, TemplateContent, Variable } from '../types/template.types';

export function displayRuns(runs: Run[], variables: Variable[]) {
  return runs.map((run) => run.type === 'text' ? run.text : `[${variables.find((v) => v.id === run.variable)?.etiqueta ?? 'Variable no disponible'}]`).join('');
}
export function parseRuns(text: string, variables: Variable[], bold = false, italic = false): Run[] {
  const runs: Run[] = []; let offset = 0;
  for (const match of text.matchAll(/\[([^\]]+)\]/g)) {
    const variable = variables.find((v) => v.etiqueta === match[1]);
    if (!variable) continue;
    if (match.index > offset) runs.push({ type: 'text', text: text.slice(offset, match.index), bold, italic });
    runs.push({ type: 'variable', variable: variable.id, bold, italic }); offset = match.index + match[0].length;
  }
  if (offset < text.length || !runs.length) runs.push({ type: 'text', text: text.slice(offset), bold, italic });
  return runs;
}
export function templateIssues(content: TemplateContent) {
  if (!content.title.trim()) return 'Escriba el título del certificado.';
  if (content.typography.size < 9 || content.typography.size > 12) return 'El tamaño de letra debe estar entre 9 y 12 puntos.';
  for (const paragraph of content.paragraphs) {
    if (!paragraph.runs.some((run) => run.type === 'variable' || run.text.trim())) return 'Complete los párrafos vacíos o elimínelos si son opcionales.';
    if (paragraph.runs.some((run) => run.type === 'text' && /[[\]<>]|\{\{|\}\}/.test(run.text))) return 'Hay una etiqueta de variable incompleta o desconocida. Use Insertar variable; no se admite HTML.';
  }
  return '';
}
export async function templateError(error: unknown) {
  const candidate = error as { response?: { data?: unknown } };
  if (candidate.response?.data instanceof Blob) {
    try { candidate.response.data = JSON.parse(await candidate.response.data.text()); } catch { /* Binary/empty errors retain the generic message. */ }
  }
  return Object.values(getValidationErrors(error)).flat()[0] ?? getErrorMessage(error);
}
export const typeLabel = (type: string) => type === 'funciones' ? 'Certificado con funciones' : 'Certificado sencillo';
export const dateLabel = (value: string | null) => value ? new Date(value).toLocaleString('es-CO', { timeZone: 'America/Bogota', dateStyle: 'medium', timeStyle: 'short' }) : 'Sin publicación';
