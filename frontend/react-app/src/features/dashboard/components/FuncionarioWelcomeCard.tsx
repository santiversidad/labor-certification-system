import { ArrowRight, FilePlus2 } from 'lucide-react';
import { Link } from 'react-router-dom';
import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';
import type { User } from '../../../types/common.types';

export function FuncionarioWelcomeCard({ user }: { user: User | null }) {
  return (
    <Card className="border-govBlue/20 bg-gradient-to-br from-govBlue to-govBlueDark p-6 text-white">
      <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
        <div className="max-w-2xl">
          <p className="text-sm font-medium text-white/80">Alcaldia de Villavicencio</p>
          <h1 className="mt-2 text-2xl font-semibold tracking-normal text-white sm:text-3xl">
            Bienvenido, {user?.name ?? 'funcionario'}
          </h1>
          <p className="mt-2 text-sm leading-6 text-white/85">
            Consulte el estado de sus solicitudes, descargue certificados disponibles y radique nuevas certificaciones laborales desde un entorno institucional seguro.
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row lg:flex-col xl:flex-row">
          <Link to="/app/solicitudes/nueva">
            <Button className="w-full bg-white text-govBlue hover:bg-blue-50" icon={<FilePlus2 size={18} />} type="button">
              Solicitar certificacion
            </Button>
          </Link>
          <Link to="/app/certificados">
            <Button className="w-full border-white/30 bg-white/10 text-white hover:bg-white/20" icon={<ArrowRight size={18} />} type="button">
              Ver certificados
            </Button>
          </Link>
        </div>
      </div>
    </Card>
  );
}
