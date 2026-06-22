export type TimelineItem = {
  id: string;
  title: string;
  date?: string;
  description?: string;
};

export function Timeline({ items }: { items: TimelineItem[] }) {
  if (!items.length) {
    return <p className="text-sm text-muted">Sin eventos registrados.</p>;
  }

  return (
    <ol className="space-y-4">
      {items.map((item) => (
        <li className="border-l-2 border-border pl-4" key={item.id}>
          <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm font-medium text-text">{item.title}</p>
            {item.date ? <time className="text-xs text-muted">{item.date}</time> : null}
          </div>
          {item.description ? <p className="mt-1 text-sm text-muted">{item.description}</p> : null}
        </li>
      ))}
    </ol>
  );
}
