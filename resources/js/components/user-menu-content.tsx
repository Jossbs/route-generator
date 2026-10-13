import { DropdownMenuLabel } from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import type { User } from '@/types';

type Props = {
    user: User;
};

/**
 * Sin cerrar sesión ni editar la cuenta: la sesión y el perfil son del sistema
 * anfitrión, no de este módulo. El tema tampoco va aquí: tiene su propio icono
 * en el header.
 */
export function UserMenuContent({ user }: Props) {
    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
        </>
    );
}
