import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from './Button';
import { EmptyState } from './EmptyState';
import { Skeleton } from './Skeleton';

export type TableColumn<T> = {
  header: string;
  accessor: keyof T | ((row: T) => ReactNode);
  className?: string;
  headerClassName?: string;
};

type TableProps<T> = {
  columns: TableColumn<T>[];
  data: T[];
  emptyMessage?: string;
  emptyTitle?: string;
  loading?: boolean;
  caption?: string;
  pagination?: {
    currentPage: number;
    lastPage: number;
    total: number;
    onPageChange: (page: number) => void;
  };
};

export function Table<T extends { id: string | number }>({
  columns,
  data,
  emptyMessage = 'No hay registros que coincidan con la consulta.',
  emptyTitle = 'Sin resultados',
  loading = false,
  caption,
  pagination,
}: TableProps<T>) {
  return (
    <div className="overflow-hidden rounded-lg border border-border bg-surface">
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-border text-sm">
          {caption ? <caption className="sr-only">{caption}</caption> : null}
          <thead className="bg-surface-muted/70 text-left text-xs uppercase tracking-[0.08em] text-muted">
            <tr>
              {columns.map((column) => (
                <th className={`whitespace-nowrap px-4 py-3.5 font-bold ${column.headerClassName ?? ''}`} key={column.header} scope="col">
                  {column.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-border bg-surface">
            {loading ? (
              Array.from({ length: 5 }).map((_, rowIndex) => (
                <tr key={`loading-${rowIndex}`}>
                  {columns.map((column) => (
                    <td className="px-4 py-4" key={`${column.header}-${rowIndex}`}><Skeleton className="h-4 w-28" /></td>
                  ))}
                </tr>
              ))
            ) : data.length ? (
              data.map((row) => (
                <tr className="transition-colors hover:bg-surface-muted/50" key={row.id}>
                  {columns.map((column) => (
                    <td className={`whitespace-nowrap px-4 py-3.5 align-middle text-text ${column.className ?? ''}`} key={`${row.id}-${column.header}`}>
                      {typeof column.accessor === 'function' ? column.accessor(row) : String(row[column.accessor] ?? '')}
                    </td>
                  ))}
                </tr>
              ))
            ) : (
              <tr>
                <td className="p-4" colSpan={columns.length}>
                  <EmptyState description={emptyMessage} title={emptyTitle} />
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
      {pagination ? (
        <div className="flex flex-col gap-3 border-t border-border px-4 py-3 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
          <p>{pagination.total} {pagination.total === 1 ? 'registro' : 'registros'}</p>
          <div className="flex items-center gap-2">
            <Button aria-label="Página anterior" disabled={pagination.currentPage <= 1} icon={<ChevronLeft size={16} />} onClick={() => pagination.onPageChange(pagination.currentPage - 1)} type="button" variant="secondary" />
            <span className="px-2 font-medium text-text">Página {pagination.currentPage} de {Math.max(pagination.lastPage, 1)}</span>
            <Button aria-label="Página siguiente" disabled={pagination.currentPage >= pagination.lastPage} icon={<ChevronRight size={16} />} onClick={() => pagination.onPageChange(pagination.currentPage + 1)} type="button" variant="secondary" />
          </div>
        </div>
      ) : null}
    </div>
  );
}
