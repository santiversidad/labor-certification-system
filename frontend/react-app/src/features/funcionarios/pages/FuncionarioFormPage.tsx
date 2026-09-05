import { FuncionarioForm } from '../components/FuncionarioForm';

export function FuncionarioFormPage() {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-text">Funcionario</h1>
        <p className="mt-1 text-sm text-muted">La cuenta se crea automáticamente; el login y la contraseña temporal inicial son la cédula.</p>
      </div>
      <FuncionarioForm />
    </div>
  );
}
