export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
    };
    locale: 'ja' | 'en';
    supported_locales: ('ja' | 'en')[];
    flash: {
        error: string | null;
        success: string | null;
    };
    errors?: Record<string, string | string[]>;
};
