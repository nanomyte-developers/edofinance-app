import { Ziggy } from './ziggy';

export default {
    install: (app, options = Ziggy) => {
        app.config.globalProperties.route = (name, params, absolute) => {
            const route = options.routes[name];

            if (!route) {
                throw new Error(`Route [${name}] not defined.`);
            }

            let uri = route.uri;

            // Replace route parameters
            if (params) {
                Object.keys(params).forEach((key) => {
                    uri = uri.replace(`{${key}}`, params[key]);
                    uri = uri.replace(`{${key}?}`, params[key]);
                });
            }

            // Remove any optional parameters that weren't provided
            uri = uri.replace(/\/\{[^}]+\?\}/g, '');
            uri = uri.replace(/\{[^}]+\?\}/g, '');

            const url = new URL(uri, options.url);

            return absolute !== false ? url.href : uri;
        };
    },
};
