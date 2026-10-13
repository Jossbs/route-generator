export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

/**
 * El asesor que actúa. Lo resuelve `App\Services\Identity\CurrentAdvisor`, no un
 * login: este módulo se inserta en un sistema que ya tiene su propio acceso.
 */
export type Auth = {
    user: User;
};
