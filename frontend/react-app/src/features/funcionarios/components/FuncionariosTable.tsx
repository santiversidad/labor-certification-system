import { KeyRound, Pencil, Search } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Badge } from '../../../components/ui/Badge';
import { Button } from '../../../components/ui/Button';
import { Table, type TableColumn } from '../../../components/ui/Table';
import type { Funcionario } from '../types/funcionario.types';

export function FuncionariosTable({ funcionarios, onReset }: { funcionarios: Funcionario[]; onReset: (id: number) => void }) {
  const columns: TableColumn<Funcionario>[] = [
    { header: 'Cédula', accessor: 'numero_documento' },
    { header: 'Nombre', accessor: (row) => <span className="font-medium text-text">{row.nombres} {row.apellidos}</span> },
    { header: 'Cargo', accessor: (row) => <span>{row.cargo?.denominacion ?? 'Sin cargo'}<small className="block text-muted">{row.cargo ? `${row.cargo.codigo} · grado ${row.cargo.grado}` : ''}</small></span> },
    { header: 'Dependencia', accessor: (row) => row.dependencia ?? '—' },
    { header: 'Funcionario', accessor: (row) => <Badge tone={row.estado === 'activo' ? 'green' : 'gray'}>{row.estado}</Badge> },
    { header: 'Acceso', accessor: (row) => <span className="space-y-1"><Badge tone={row.usuario?.estado ? 'green' : 'gray'}>{row.usuario?.estado ? 'Activo' : 'Inactivo'}</Badge>{row.usuario?.must_change_password ? <small className="block text-amber-700">Cambio pendiente</small> : null}</span> },
    { header: 'Acciones', accessor: (row) => <div className="flex gap-1"><Link to={`${row.id}`}><Button aria-label="Ver funcionario" icon={<Search size={15} />} type="button" variant="ghost" /></Link><Link to={`${row.id}/editar`}><Button aria-label="Editar funcionario" icon={<Pencil size={15} />} type="button" variant="ghost" /></Link><Button aria-label="Restablecer acceso" icon={<KeyRound size={15} />} onClick={() => onReset(row.id)} type="button" variant="ghost" /></div> },
  ];
  return <Table columns={columns} data={funcionarios} />;
}
