import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
// Add more components as needed

export default {
    install(app) {
        app.component('InputText', InputText);
        app.component('Password', Password);
        app.component('Checkbox', Checkbox);
        app.component('Button', Button);
    },
};
