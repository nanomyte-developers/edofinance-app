// / <reference types="ziggy-js" />

declare module 'ziggy-js' {
    import { Config, RouteParamsWithQueryOverload } from 'ziggy-js';

    export function route(
        name?: keyof typeof import('../../../ziggy.json').routes,
        params?: RouteParamsWithQueryOverload | undefined,
        absolute?: boolean,
        config?: Config
    ): string;
}
