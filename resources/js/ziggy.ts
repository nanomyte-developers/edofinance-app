// resources/js/ziggy.ts
import ziggyConfig from './ziggy.json';

export const Ziggy = {
    ...ziggyConfig,
    url: import.meta.env.VITE_APP_URL || 'http://localhost:8000'
};