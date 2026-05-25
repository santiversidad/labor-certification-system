import { FuncionarioForm } from '../components/FuncionarioForm';

export function FuncionarioFormPage() {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-text">Formulario de funcionario</h1>
        <p className="mt-1 text-sm text-muted">Alta y edicion placeholder con React Hook Form y Zod.</p>
      </div>
      <FuncionarioForm />
    </div>
  );
}
