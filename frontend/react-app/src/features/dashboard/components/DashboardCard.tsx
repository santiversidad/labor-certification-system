import { Card } from '../../../components/ui/Card';
import type { DashboardMetric } from '../types/dashboard.types';

export function DashboardCard({ metric }: { metric: DashboardMetric }) {
  return (
    <Card>
      <p className="text-sm text-muted">{metric.label}</p>
      <p className="mt-2 text-3xl font-semibold text-text">{metric.value}</p>
    </Card>
  );
}
