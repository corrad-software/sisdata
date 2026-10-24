import { Badge } from '@/components/ui/Badge';
import type { LogImport } from '@/lib/gis';

const LABEL: Record<LogImport['status'], [string, 'success' | 'danger' | 'warning' | 'info' | 'neutral']> = {
    disemak: ['Sedang disemak', 'info'],
    ditolak: ['Ditolak', 'danger'],
    sedia: ['Lulus pengesahan', 'warning'],
    diterima: ['Diterima', 'success'],
    dibatalkan: ['Dibatalkan', 'neutral'],
};

export function BadgeStatusImport({ status }: { status: LogImport['status'] }) {
    const [label, variant] = LABEL[status] ?? [status, 'neutral'];
    return <Badge variant={variant}>{label}</Badge>;
}
