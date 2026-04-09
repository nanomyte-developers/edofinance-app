// utils/permissions.js
import { usePage } from '@inertiajs/vue3';

/**
 * Check if current user has specific role
 * @param {string|array} role - Single role or array of roles
 * @returns {boolean}
 */
export const hasRole = (role) => {
    const page = usePage();
    const userRoles = page.props.auth?.user?.roles || [];

    if (!role) return false;
    if (Array.isArray(role)) {
        return role.some((r) => userRoles.includes(r));
    }
    return userRoles.includes(role);
};

/**
 * Check if current user has specific permission
 * @param {string|array} permission - Single permission or array of permissions
 * @returns {boolean}
 */
export const hasPermission = (permission) => {
    const page = usePage();
    const userPermissions = page.props.auth?.user?.permissions || [];

    if (!permission) return false;
    if (Array.isArray(permission)) {
        return permission.some((p) => userPermissions.includes(p));
    }
    return userPermissions.includes(permission);
};

/**
 * Check if user can approve a specific voucher
 * @param {Object} voucher
 * @returns {boolean}
 */
export const canApproveVoucher = (voucher) => {
    if (!voucher) return false;

    // Check voucher conditions
    const isPrepaymentSubmitted =
        voucher.voucher_type === 'prepayment' && voucher.status === 'Submitted';

    if (!isPrepaymentSubmitted) return false;

    // Check user roles and permissions
    return (
        hasRole(['FInal Account', 'admin']) || hasPermission('approve_vouchers')
    );
};

/**
 * Check if user can retire a specific voucher
 * @param {Object} voucher
 * @param {Object} retirementStatusData
 * @returns {boolean}
 */
export const canRetireVoucher = (voucher, retirementStatusData = {}) => {
    if (!voucher) return false;

    // Check voucher conditions
    const isPrepaymentApproved =
        voucher.voucher_type === 'prepayment' && voucher.status === 'Approved';

    if (!isPrepaymentApproved) return false;

    // Check retirement status
    if (
        retirementStatusData.already_retired ||
        !retirementStatusData.can_retire
    ) {
        return false;
    }

    // Check user roles and permissions
    return (
        hasRole(['Final Account', 'admin']) || hasPermission('retire_vouchers')
    );
};
