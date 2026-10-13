import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'DP SyS';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // Sin capa de acceso propia no hay layout de autenticación: cada página monta
    // el suyo. El módulo se inserta en un sistema que ya tiene el suyo.
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    // La barra de carga es lo único de la marca que se ve durante la espera:
    // en gris parecía de otro sistema.
    progress: {
        color: '#e91e63',
        delay: 150,
    },
});

// This will set light / dark mode on load...
initializeTheme();
