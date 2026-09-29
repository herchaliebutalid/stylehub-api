const API_BASE_URL = '/api/v1';

/**
 * Error returned by the StyleHub API.
 */
export class ApiError extends Error {
    constructor(message, status = 0, data = null) {
        super(message);

        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
}

/**
 * Safely parse an API response.
 */
async function parseResponse(response) {
    const contentType = response.headers.get('content-type') ?? '';

    if (contentType.includes('application/json')) {
        try {
            return await response.json();
        } catch {
            return null;
        }
    }

    return null;
}

/**
 * Send a request to the StyleHub Laravel API.
 */
export async function apiRequest(
    path,
    {
        method = 'GET',
        token = null,
        body = undefined,
        headers = {},
    } = {},
) {
    const requestHeaders = {
        Accept: 'application/json',
        ...headers,
    };

    let requestBody;

    if (body !== undefined && body !== null) {
        if (body instanceof FormData) {
            requestBody = body;
        } else {
            requestHeaders['Content-Type'] = 'application/json';
            requestBody = JSON.stringify(body);
        }
    }

    if (token) {
        requestHeaders.Authorization = `Bearer ${token}`;
    }

    let response;

    try {
        response = await fetch(`${API_BASE_URL}${path}`, {
            method,
            headers: requestHeaders,
            body: requestBody,
            credentials: 'same-origin',
            cache: 'no-store',
        });
    } catch {
        throw new ApiError(
            'Unable to connect to the StyleHub server. Please check your connection and try again.',
        );
    }

    const data = await parseResponse(response);

    if (!response.ok) {
        throw new ApiError(
            data?.message ?? 'The request could not be completed.',
            response.status,
            data,
        );
    }

    return data;
}