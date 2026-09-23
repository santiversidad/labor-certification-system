import { FuncionarioForm } from '../components/FuncionarioForm';
import { PageHeader } from '../../../components/ui/PageHeader';
import { useParams } from 'react-router-dom';

export function FuncionarioFormPage() {
  const { id } = useParams();
  return (
    <div className="app-page max-w-5xl">
      <PageHeader eyebrow="Talento Humano" title={id ? 'Editar funcionario' : 'Crear funcionario'} description="Registre la información institucional y asigne explícitamente la ficha normativa correcta." />
      <FuncionarioForm />
    </div>
  );
}
