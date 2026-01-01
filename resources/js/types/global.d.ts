import { AxiosInstance } from 'axios';
import { route as routeFn } from 'ziggy-js';

declare global {
    interface Window {
        axios: AxiosInstance;
    }

    var route: typeof routeFn;
}

declare module '@inertiajs/core' {
    interface PageProps {
        auth: {
            user: {
                id: number;
                name: string;
                email: string;
                email_verified_at: string;
                roles: Array<{ name: string }>;
            } | null;
        };
        flash: {
            message: string | null;
            error: string | null;
            warning: string | null;
        };
        [key: string]: any;
    }
}
