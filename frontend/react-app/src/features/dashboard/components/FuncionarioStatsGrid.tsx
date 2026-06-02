import { BadgeCheck, Clock3, FileText, SearchCheck } from 'lucide-react';
import { Card } from '../../../components/ui/Card';
import type { DashboardMetric } from '../types/dashboard.types';

const icons = [Clock3, SearchCheck, BadgeCheck, FileText];

export function FuncionarioStatsGrid({ metrics }: { metrics: DashboardMetric[] }) {
  return (
    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      {metrics.map((metric, index) => {
        const Icon = icons[index] ?? FileText;

        return (
          <Card className="p-4" key={metric.label}>
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-sm text-muted">{metric.label}</p>
                <p className="mt-2 text-3xl font-semibold text-text">{metric.value}</p>
              </div>
              <span className="rounded-md bg-blue-50 p-2 text-govBlue">
                <Icon size={20} />
              </span>
            </div>
          </Card>
        );
      })}
    </div>
  );
}
