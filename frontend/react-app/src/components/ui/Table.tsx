import type { ReactNode } from 'react';

export type TableColumn<T> = {
  header: string;
  accessor: keyof T | ((row: T) => ReactNode);
};

type TableProps<T> = {
  columns: TableColumn<T>[];
  data: T[];
  emptyMessage?: string;
};

export function Table<T extends { id: string }>({ columns, data, emptyMessage = 'Sin registros.' }: TableProps<T>) {
  return (
    <div className="overflow-hidden rounded-md border border-border">
      <div className="overflow-x-auto">
        <table className="min-w-full divide-y divide-border text-sm">
          <thead className="bg-background text-left text-xs uppercase tracking-wide text-muted">
            <tr>
              {columns.map((column) => (
                <th className="px-4 py-3 font-semibold" key={column.header}>
                  {column.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-border bg-surface">
            {data.length ? (
              data.map((row) => (
                <tr className="hover:bg-background/70" key={row.id}>
                  {columns.map((column) => (
                    <td className="whitespace-nowrap px-4 py-3 text-text" key={`${row.id}-${column.header}`}>
                      {typeof column.accessor === 'function' ? column.accessor(row) : String(row[column.accessor] ?? '')}
                    </td>
                  ))}
                </tr>
              ))
            ) : (
              <tr>
                <td className="px-4 py-6 text-center text-muted" colSpan={columns.length}>
                  {emptyMessage}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
