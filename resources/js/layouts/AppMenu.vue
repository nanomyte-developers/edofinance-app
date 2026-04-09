<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AppMenuItem from './AppMenuItem.vue';

// Helper methods
const hasRole = (role) => {
    console.log(role);
    return usePage().props.auth.userRoles.includes(role);
};

const hasPermission = (permission) => {
    return usePage().props.auth.userPermissions.includes(permission);
};

const model = ref([
    // {
    //     label: 'Pages',
    //     icon: 'pi pi-fw pi-briefcase',
    //     to: '/pages',
    //     items: [
    //         {
    //             label: 'Auth',
    //             icon: 'pi pi-fw pi-user',
    //             items: [
    //                 {
    //                     label: 'Login',
    //                     icon: 'pi pi-fw pi-sign-in',
    //                     to: '/auth/login',
    //                 },
    //                 {
    //                     label: 'Error',
    //                     icon: 'pi pi-fw pi-times-circle',
    //                     to: '/auth/error',
    //                 },
    //                 {
    //                     label: 'Access Denied',
    //                     icon: 'pi pi-fw pi-lock',
    //                     to: '/auth/access',
    //                 },
    //             ],
    //         },
    //     ],
    // },
    // {
    //     label: 'Hierarchy',
    //     items: [
    //         {
    //             label: 'Submenu 1',
    //             icon: 'pi pi-fw pi-bookmark',
    //             items: [
    //                 {
    //                     label: 'Submenu 1.1',
    //                     icon: 'pi pi-fw pi-bookmark',
    //                     items: [
    //                         { label: 'Submenu 1.1.1', icon: 'pi pi-fw pi-bookmark' },
    //                         { label: 'Submenu 1.1.2', icon: 'pi pi-fw pi-bookmark' },
    //                         { label: 'Submenu 1.1.3', icon: 'pi pi-fw pi-bookmark' }
    //                     ]
    //                 },
    //                 {
    //                     label: 'Submenu 1.2',
    //                     icon: 'pi pi-fw pi-bookmark',
    //                     items: [{ label: 'Submenu 1.2.1', icon: 'pi pi-fw pi-bookmark' }]
    //                 }
    //             ]
    //         },
    //         {
    //             label: 'Submenu 2',
    //             icon: 'pi pi-fw pi-bookmark',
    //             items: [
    //                 {
    //                     label: 'Submenu 2.1',
    //                     icon: 'pi pi-fw pi-bookmark',
    //                     items: [
    //                         { label: 'Submenu 2.1.1', icon: 'pi pi-fw pi-bookmark' },
    //                         { label: 'Submenu 2.1.2', icon: 'pi pi-fw pi-bookmark' }
    //                     ]
    //                 },
    //                 {
    //                     label: 'Submenu 2.2',
    //                     icon: 'pi pi-fw pi-bookmark',
    //                     items: [{ label: 'Submenu 2.2.1', icon: 'pi pi-fw pi-bookmark' }]
    //                 }
    //             ]
    //         }
    //     ]
    // },
    // {
    //     label: 'Get Started',
    //     items: [
    //         {
    //             label: 'Documentation',
    //             icon: 'pi pi-fw pi-book',
    //             to: '/documentation'
    //         },
    //         {
    //             label: 'View Source',
    //             icon: 'pi pi-fw pi-github',
    //             url: 'https://github.com/primefaces/sakai-vue',
    //             target: '_blank'
    //         }
    //     ]
    // }
]);

onMounted(() => {
    // console.log("Roles: " + roles);
    // console.log(usePage().props.auth.userRoles, usePage().props.auth.userPermissions);
    if (usePage().props.auth.userRoles.includes('Authenticated')) {
        model.value.push({
            label: 'Home',
            items: [{ label: 'Home', icon: 'pi pi-fw pi-home', to: '/' }],
        });
    }

    const menu = [];

    if (usePage().props.auth.userRoles.includes('Authenticated')) {
        menu.push({
            label: 'Dashboard',
            icon: 'pi pi-fw pi-globe',
            to: '/dashboard',
        });
    }

    if (usePage().props.auth.userRoles.includes('User')) {
        menu.push({ label: 'Users', icon: 'pi pi-fw pi-user', to: '/users' });
    }

    if (usePage().props.auth.userRoles.includes('Role')) {
        menu.push({
            label: 'Roles',
            icon: 'pi pi-fw pi-check-square',
            to: '/roles',
        });
    }
    if (usePage().props.auth.userRoles.includes('Permissions')) {
        menu.push({
            label: 'Permissions',
            icon: 'pi pi-fw pi-mobile',
            to: '/permissions',
            class: 'rotated-icon',
        });
    }

    // { label: 'Vouchers', icon: 'pi pi-fw pi-table', to: '/vouchers' },

    if (usePage().props.auth.userRoles.includes('Accountant General')) {
        menu.push({
            label: 'Accountant General (AG)',
            icon: 'pi pi-fw pi-image',
            to: '/accountant-general',
        });
    }
    if (usePage().props.auth.userRoles.includes('Director of Finance')) {
        menu.push({
            label: 'Director of Finance (DFA)',
            icon: 'pi pi-fw pi-list',
            items: [
                {
                    label: 'Schedules',
                    icon: 'pi pi-fw pi-times-circle',
                    to: '/schedules',
                },
                {
                    label: 'Voucher',
                    icon: 'pi pi-fw pi-cart-arrow-down',
                    to: '/vouchers',
                },
                // {
                //     label: "Sector's",
                //     icon: 'pi pi-fw pi-times-circle',
                //     to: '/sectors',
                // },
                // {
                //     label: 'Access Denied',
                //     icon: 'pi pi-fw pi-lock',
                //     to: '/auth/access'
                // }
            ],
        });
    }

    // if (usePage().props.auth.userRoles.includes('Final Account')) {
    //     menu.push({
    //         label: 'Final Account (FA)',
    //         icon: 'pi pi-fw pi-share-alt',
    //         to: '/final-accounts',
    //     });
    // }

    if (usePage().props.auth.userRoles.includes('Final Account')) {
        menu.push({
            label: 'Final Account (FA)',
            icon: 'pi pi-fw pi-share-alt',
            items: [
                {
                    label: 'Voucher Validation',
                    icon: 'pi pi-fw pi-times-circle',
                    to: '/vouchers',
                },
                {
                    label: 'Receipts',
                    icon: 'pi pi-fw pi-times-circle',
                    to: '/receipts',
                },
                {
                    label: 'Remittance',
                    icon: 'pi pi-fw pi-lock',
                    to: '/remittances',
                },
                {
                    label: 'Journals',
                    icon: 'pi pi-fw pi-lock',
                    to: '/journals/index',
                },

            ],
        });
    }

    if (usePage().props.auth.userRoles.includes('Expenditure Control')) {
        menu.push({
            label: 'Expenditure Control (EC)',
            icon: 'pi pi-fw pi-clone',
            to: '/expenditure-control',
        });
    }

    if (usePage().props.auth.userRoles.includes('Treasury Cash Office')) {
        menu.push({
            label: 'Treasury Cash Office (TCO)',
            icon: 'pi pi-fw pi-bars',
            to: '#',
        });
    }

    if (usePage().props.auth.userRoles.includes('Mgt. Account Section')) {
        menu.push({
            label: 'Mgt. Account Section (MAS)',
            icon: 'pi pi-fw pi-comment',
            to: '#',
        });
    }

    if (usePage().props.auth.userRoles.includes('General Ledger')) {
        menu.push({
            label: 'General Ledger (GL)',
            icon: 'pi pi-fw pi-file',
            to: '#',
        });
    }
    

    if (usePage().props.auth.userRoles.includes('Internal Audit')) {
        menu.push({
            label: 'Internal Audit (IA)',
            icon: 'pi pi-fw pi-chart-bar',
            to: '/internal-audits',
        });
    }



    // ============ ADD ACTIVITY LOGS MENU ITEM HERE ============
    // Check if user has admin or supervisor role for activity logs
    // if (
    //     usePage().props.auth.userRoles.includes('admin') ||
    //     usePage().props.auth.userRoles.includes('Authenticated') ||
    //     usePage().props.auth.userRoles.includes('supervisor') ||
    //     usePage().props.auth.userRoles.includes('Admin') || // Capitalized version
    //     usePage().props.auth.userRoles.includes('Supervisor')
    // ) {
    //     // Capitalized version

    //     menu.push({
    //         label: 'Activity Logs',
    //         icon: 'pi pi-fw pi-history',
    //         to: '/activity-logs',
    //         class: 'text-blue-500', // Optional: add special styling
    //     });
    // }
    // ============ END ACTIVITY LOGS MENU ITEM ============

    // { label: 'Logout', icon: 'pi pi-fw pi-user', to: '' },
    // { label: 'Misc', icon: 'pi pi-fw pi-circle', to: '/uikit/misc' }

    // if (usePage().props.auth.userRoles.includes('Authenticated')) {
    //     model.value.push({
    //         label: 'Logout',
    //         icon: 'pi pi-fw pi-sign-out',
    //         command: () => {
    //             console.log('Logout command called');
    //             handleLogout();
    //         },
    //         class: 'text-red-500 hover:text-red-700',
    //     });
    // }


    if (
        usePage().props.auth.userRoles.includes('Trial Balance')
        // usePage().props.auth.userRoles.includes('Authenticated') ||


    ) {


        // Capitalized version
        menu.push({
            label: 'Special Reports (SR)',
            icon: 'pi pi-fw pi-share-alt',
            items: [
                {
                    label: 'General Trial Balance',
                    icon: 'pi pi-fw pi-chart-bar',
                    to: '/reports/trialbalance',
                },
            ]

        });
    }
    if (
        usePage().props.auth.userRoles.includes('cashbook')
        // usePage().props.auth.userRoles.includes('Authenticated') ||


    ) {
        // Capitalized version
        menu.push({
            label: 'Special Reports (SR)',
            icon: 'pi pi-fw pi-share-alt',
            items: [
                {
                    label: 'View Cashbooks',
                    icon: 'pi pi-fw pi-chart-bar',
                    to: '/cashbook-years/1/months',
                },
            ]

        });
    }


    if (
        usePage().props.auth.userRoles.includes('admin') ||
        // usePage().props.auth.userRoles.includes('Authenticated') ||
        usePage().props.auth.userRoles.includes('supervisor') ||
        usePage().props.auth.userRoles.includes('Admin') || // Capitalized version
        usePage().props.auth.userRoles.includes('Supervisor')
    ) {
        // Capitalized version
        menu.push({
            label: 'System Settings (SS)',
            icon: 'pi pi-fw pi-share-alt',
            items: [
                // {
                //     label: 'Activity Logs',
                //     icon: 'pi pi-fw pi-times-circle',
                //     to: '/activity-logs',
                // },
                {
                    label: 'Bank Activities (BA)',
                    icon: 'pi pi-fw pi-times-circle',
                    to: '/bank-activities',
                },
                {
                    label: 'Eco Codes (EC)',
                    icon: 'pi pi-fw pi-lock',
                    to: '/economy-codess',
                },
                {
                    label: 'Eco Code Items (ECI)',
                    icon: 'pi pi-fw pi-lock',
                    to: '/economy-code-itemss',
                },
                {
                    label: 'Financial Years (FY)',
                    icon: 'pi pi-fw pi-lock',
                    to: '/financial-years',
                },
                // {
                //     label: 'Bank (BK)',
                //     icon: 'pi pi-fw pi-chart-bar',
                //     to: '/banks',
                // },
                {
                    label: 'Receipt-Activities (RA)',
                    icon: 'pi pi-fw pi-chart-bar',
                    to: '/receipt-activities',
                },
                {
                    label: 'Administrative-codes (AC)',
                    icon: 'pi pi-fw pi-chart-bar',
                    to: '/administrative-codes',
                },
                {
                    label: "Administrative-code-items (ACI)",
                    icon: 'pi pi-fw pi-times-circle',
                    to: '/administrative-code-itemss',
                },
                {
                    label: "Manage MDA's",
                    icon: 'pi pi-fw pi-sign-in',
                    to: '/mdas',
                },
                {
                    label: 'Cash Book Balance Bfw (CBBF)',
                    icon: 'pi pi-fw pi-chart-bar',
                    to: '/cash-book',
                },
                { label: 'Payees (PY)', icon: 'pi pi-fw pi-chart-bar', to: '/payees' },
                {
                    label: 'General Trial Balance',
                    icon: 'pi pi-fw pi-chart-bar',
                    to: '/reports/trialbalance',
                },
                {
                    label: 'Financial Performance',
                    icon: 'pi pi-fw pi-chart-bar',
                    to: '/reports/financial-report',
                },
                {
                    label: 'Financial Position',
                    icon: 'pi pi-fw pi-chart-bar',
                    to: '/reports/balance-sheet',
                },
                {
                    label: 'Cash Book Years',
                    icon: 'pi pi-fw pi-chart-bar',
                    to: '/cashbook-years',
                },
            ],
        });
    }

    model.value.push({
        label: 'Menu',
        items: menu,
    });
});
// Logout function using POST method
// const handleLogout = () => {
//     console.log('handleLogout function called');
//     if (confirm('Are you sure you want to logout?')) {
//         console.log('Proceeding with logout');
//         router.post(
//             '/logout',
//             {},
//             {
//                 onSuccess: () => console.log('Logout successful'),
//                 onError: (errors) => console.log('Logout error:', errors),
//             },
//         );
//     }
// };
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped></style>
