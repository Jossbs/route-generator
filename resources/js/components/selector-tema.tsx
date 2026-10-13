import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

const temas: { value: Appearance; icon: LucideIcon; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Claro' },
    { value: 'dark', icon: Moon, label: 'Oscuro' },
    { value: 'system', icon: Monitor, label: 'El del sistema' },
];

/**
 * El tema vive en el header y no en una pantalla aparte: es una preferencia
 * que se cambia al vuelo —de noche, en una sala con poca luz— y un viaje a
 * «Apariencia» costaba dos clics y salir de lo que se estaba haciendo.
 *
 * Es un icono y nada más, del mismo peso que el menú del celular, para que no
 * le dispute la atención a la navegación. El icono dice lo que está elegido,
 * incluido «el del sistema», que no se deja adivinar por los colores.
 */
export function SelectorTema() {
    const { appearance, updateAppearance } = useAppearance();
    const Icono =
        temas.find((tema) => tema.value === appearance)?.icon ?? Monitor;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="focus-visible:ring-ring focus-visible:ring-offset-header flex size-9 shrink-0 items-center justify-center rounded-full text-white/65 transition-colors outline-none hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-offset-2 data-[state=open]:bg-white/10 data-[state=open]:text-white"
                aria-label="Cambiar el tema"
                title="Tema"
                data-test="sys-header-tema"
            >
                <Icono className="size-[18px]" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-44 rounded-lg">
                <DropdownMenuLabel className="text-muted-foreground text-xs font-medium">
                    Tema
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuRadioGroup
                    value={appearance}
                    onValueChange={(valor) =>
                        updateAppearance(valor as Appearance)
                    }
                >
                    {temas.map(({ value, icon: Icon, label }) => (
                        <DropdownMenuRadioItem key={value} value={value}>
                            <Icon className="text-muted-foreground" />
                            {label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
