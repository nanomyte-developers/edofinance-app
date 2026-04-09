// resources/js/services/activitySocket.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    forceTLS: true,
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            Authorization: 'Bearer ' + localStorage.getItem('access_token'),
        },
    },
});

export const activitySocket = {
    connect() {
        echo.private('activity').listen('.ActivityLogged', (event) => {
            this.handleActivity(event.activity);
        });
    },

    disconnect() {
        echo.leave('activity');
    },

    handleActivity(activity) {
        // Dispatch event for components to listen to
        const event = new CustomEvent('activity-logged', { detail: activity });
        window.dispatchEvent(event);

        // Show notification
        if (this.shouldNotify(activity)) {
            this.showNotification(activity);
        }
    },

    shouldNotify(activity) {
        // Customize notification rules
        const importantEvents = ['deleted', 'approved', 'rejected'];
        const importantModules = ['user', 'voucher', 'receipt'];

        return (
            importantEvents.includes(activity.event) ||
            importantModules.includes(activity.log_name)
        );
    },

    showNotification(activity) {
        if (Notification.permission === 'granted') {
            const notification = new Notification('New Activity', {
                body: `${activity.causer?.name || 'System'} ${activity.event} ${activity.log_name}`,
                icon: '/favicon.ico',
                tag: `activity-${activity.id}`,
            });

            notification.onclick = () => {
                window.focus();
                window.location.href = '/activity-logs';
            };
        }
    },
};

// Request notification permission
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}
