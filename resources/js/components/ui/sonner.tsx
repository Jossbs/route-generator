import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            // Arriba y al centro, debajo del header: abajo tapaba el botón de
            // enviar en el celular, que es justo donde el asesor tiene el pulgar.
            position="top-center"
            offset={{ top: 'calc(var(--header-h) + 0.75rem)' }}
            mobileOffset={{
                top: 'calc(var(--header-h) + 0.5rem)',
                left: '1rem',
                right: '1rem',
            }}
            closeButton
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
