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

const model = ref([]);

onMounted(() => {
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
            ],
        });
    }

    // ============ FINAL ACCOUNT (FA) MENU - COMPLETE ============
    if (usePage().props.auth.userRoles.includes('Final Account')) {
        menu.push({
            label: 'Final Account (FA)',
            icon: 'pi pi-fw pi-share-alt',
            items: [
                {
                    label: 'Vouchers',
                    icon: 'pi pi-fw pi-times-circle',
                    items: [
                        {
                            label: 'Standard Voucher',
                            icon: 'pi pi-fw pi-bookmark',
                            to: '/final-accounts/vouchers/create?type=standard',
                            description: 'Direct approval - Standard'
                        },
                        {
                            label: 'Prepayment Voucher',
                            icon: 'pi pi-fw pi-list',
                            to: '/final-accounts/vouchers/create?type=prepayment',
                            description: 'Direct approval - Prepayment'
                        },
                        {
                            label: 'Salary Voucher',
                            icon: 'pi pi-fw pi-dollar',
                            to: '/final-accounts/vouchers/create?type=salary',
                            description: 'Direct approval - Salary'
                        },
                        {
                            label: 'Validate All Vouchers',
                            icon: 'pi pi-fw pi-check-square',
                            to: '/vouchers',
                            description: 'View and validate all vouchers'
                        }
                    ]
                },
                {
                    label: 'Receipts',
                    icon: 'pi pi-fw pi-receipt',
                    to: '/receipts',
                },
                {
                    label: 'Remittance',
                    icon: 'pi pi-fw pi-send',
                    to: '/remittances',
                },
                {
                    label: 'Journals',
                    icon: 'pi pi-fw pi-book',
                    to: '/journals/index',
                },
            ],
        });
    }
    // ============ END FINAL ACCOUNT MENU ============

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

    // ============ SPECIAL REPORTS MENU ============
    if (usePage().props.auth.userRoles.includes('Trial Balance')) {
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
    
    if (usePage().props.auth.userRoles.includes('cashbook')) {
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

    // ============ SYSTEM SETTINGS MENU - COMPLETE ============
    if (
        usePage().props.auth.userRoles.includes('admin') ||
        usePage().props.auth.userRoles.includes('supervisor') ||
        usePage().props.auth.userRoles.includes('Admin') ||
        usePage().props.auth.userRoles.includes('Supervisor')
    ) {
        menu.push({
            label: 'System Settings (SS)',
            icon: 'pi pi-fw pi-cog',
            items: [
                {
                    label: 'Activity Logs',
                    icon: 'pi pi-fw pi-history',
                    to: '/activity-logs',
                },
                {
                    label: 'Bank Activities (BA)',
                    icon: 'pi pi-fw pi-building',
                    to: '/bank-activities',
                },
                {
                    label: 'Eco Codes (EC)',
                    icon: 'pi pi-fw pi-tag',
                    to: '/economy-codess',
                },
                {
                    label: 'Eco Code Items (ECI)',
                    icon: 'pi pi-fw pi-tags',
                    to: '/economy-code-itemss',
                },
                {
                    label: 'Financial Years (FY)',
                    icon: 'pi pi-fw pi-calendar',
                    to: '/financial-years',
                },
                {
                    label: 'Receipt Activities (RA)',
                    icon: 'pi pi-fw pi-receipt',
                    to: '/receipt-activities',
                },
                {
                    label: 'Administrative Codes (AC)',
                    icon: 'pi pi-fw pi-code',
                    to: '/administrative-codes',
                },
                {
                    label: 'Administrative Code Items (ACI)',
                    icon: 'pi pi-fw pi-codesandbox',
                    to: '/administrative-code-itemss',
                },
                {
                    label: 'Manage MDAs',
                    icon: 'pi pi-fw pi-building',
                    to: '/mdas',
                },
                {
                    label: 'Cash Book Balance B/F (CBBF)',
                    icon: 'pi pi-fw pi-book',
                    to: '/cash-book',
                },
                {
                    label: 'Payees (PY)',
                    icon: 'pi pi-fw pi-users',
                    to: '/payees',
                },
                {
                    label: 'General Trial Balance',
                    icon: 'pi pi-fw pi-chart-bar',
                    to: '/reports/trialbalance',
                },
                {
                    label: 'Financial Performance',
                    icon: 'pi pi-fw pi-chart-line',
                    to: '/reports/financial-report',
                },
                {
                    label: 'Financial Position',
                    icon: 'pi pi-fw pi-chart-pie',
                    to: '/reports/balance-sheet',
                },
                {
                    label: 'Cash Book Years',
                    icon: 'pi pi-fw pi-calendar',
                    to: '/cashbook-years',
                },
                {
                    label: 'Programme Codes',
                    icon: 'pi pi-fw pi-bookmark',
                    to: '/programme-codes',
                },
                {
                    label: 'Budget Management',
                    icon: 'pi pi-fw pi-chart-line',
                    to: '/budget-management',
                },
            ],
        });
    }
    // ============ END SYSTEM SETTINGS MENU ============

    // Add the menu to the model
    model.value.push({
        label: 'Menu',
        items: menu,
    });
});
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped>
.layout-menu {
    list-style: none;
    margin: 0;
    padding: 0;
    
    .menu-separator {
        border-top: 1px solid var(--p-surface-200);
        margin: 0.5rem 0;
    }
}
</style>