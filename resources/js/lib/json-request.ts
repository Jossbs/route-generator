/** Peticiones del editor: los errores JSON mantienen el borrador en pantalla. */
export function jsonRequest(
    url: string,
    body: string,
    method: 'POST' | 'PUT' = 'POST',
    signal?: AbortSignal,
): Promise<Response> {
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    return fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(token ? { 'X-XSRF-TOKEN': decodeURIComponent(token) } : {}),
        },
        body,
        signal,
    });
}

export function fieldErrors(data: {
    errors?: Record<string, string | string[]>;
}): Record<string, string> {
    return Object.fromEntries(
        Object.entries(data.errors ?? {}).map(([key, messages]) => [
            key,
            Array.isArray(messages) ? messages[0] : messages,
        ]),
    );
}
