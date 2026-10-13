// Credit: https://usehooks-ts.com/
import { useState } from 'react';
import { toast } from 'sonner';

export type CopiedValue = string | null;
export type CopyFn = (text: string) => Promise<boolean>;
export type UseClipboardReturn = [CopiedValue, CopyFn];

export function useClipboard(): UseClipboardReturn {
    const [copiedText, setCopiedText] = useState<CopiedValue>(null);

    const copy: CopyFn = async (text) => {
        if (!navigator?.clipboard) {
            console.warn('Clipboard not supported');
            toast.error('No se pudo copiar', {
                description: 'Este navegador no deja copiar desde la página.',
            });

            return false;
        }

        try {
            await navigator.clipboard.writeText(text);
            setCopiedText(text);
            toast.success('Copiado. Ya lo puedes pegar en el chat.');

            return true;
        } catch (error) {
            console.warn('Copy failed', error);
            setCopiedText(null);
            toast.error('No se pudo copiar', {
                description: 'Selecciona el texto y cópialo a mano.',
            });

            return false;
        }
    };

    return [copiedText, copy];
}
