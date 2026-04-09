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

    console.log('hasRole - userRoles:', userRoles); // Debug

    if (!role) return false;
    
    // Handle roles that might be objects (like {id: 14, name: 'admin', ...})
    const roleNames = userRoles.map(r => {
        if (typeof r === 'object' && r !== null && r.name) {
            return r.name;
        }
        return r; // Already a string
    });

    console.log('hasRole - extracted roleNames:', roleNames); // Debug

    if (Array.isArray(role)) {
        return role.some((r) => roleNames.includes(r));
    }
    return roleNames.includes(role);
};

/**
 * Check if current user has specific permission
 * @param {string|array} permission - Single permission or array of permissions
 * @returns {boolean}
 */
export const hasPermission = (permission) => {
    const page = usePage();
    const userPermissions = page.props.auth?.user?.permissions || [];

    console.log('hasPermission - userPermissions:', userPermissions); // Debug

    if (!permission) return false;
    
    // Handle permissions that might be objects
    const permissionNames = userPermissions.map(p => {
        if (typeof p === 'object' && p !== null && p.name) {
            return p.name;
        }
        return p; // Already a string
    });

    console.log('hasPermission - extracted permissionNames:', permissionNames); // Debug

    if (Array.isArray(permission)) {
        return permission.some((p) => permissionNames.includes(p));
    }
    return permissionNames.includes(permission);
};

/**
 * DEVELOPMENT MODE - Bypass checks during development
 * @returns {boolean}
 */
export const isDevelopmentMode = () => {
    return import.meta.env.DEV || import.meta.env.MODE === 'development';
};

/**
 * Check if user can approve a specific voucher (with dev mode)
 * @param {Object} voucher
 * @returns {boolean}
 */
export const canApproveVoucher = (voucher) => {
    if (!voucher) return false;

    console.log('canApproveVoucher - checking voucher:', { 
        type: voucher.voucher_type, 
        status: voucher.status 
    });

    // Check voucher conditions
    const isPrepaymentSubmitted =
        voucher.voucher_type === 'prepayment' && voucher.status === 'Submitted';

    console.log('canApproveVoucher - isPrepaymentSubmitted:', isPrepaymentSubmitted);

    if (!isPrepaymentSubmitted) return false;

    // Development mode - bypass all checks
    if (isDevelopmentMode()) {
        console.log('DEV MODE: Allowing approval');
        return true;
    }

    // Check user roles and permissions
    // Note: Fixed typo - 'FInal Account' to 'Final Account'
    const hasRequiredRole = hasRole(['Final Account', 'admin']);
    const hasRequiredPermission = hasPermission('approve_vouchers');
    
    console.log('canApproveVoucher - hasRequiredRole:', hasRequiredRole);
    console.log('canApproveVoucher - hasRequiredPermission:', hasRequiredPermission);
    
    return hasRequiredRole || hasRequiredPermission;
};

/**
 * Check if user can retire a specific voucher (with dev mode)
 * @param {Object} voucher
 * @param {Object} retirementStatusData
 * @returns {boolean}
 */
export const canRetireVoucher = (voucher, retirementStatusData = {}) => {
    if (!voucher) return false;

    console.log('canRetireVoucher - checking voucher:', { 
        type: voucher.voucher_type, 
        status: voucher.status 
    });
    console.log('canRetireVoucher - retirementStatusData:', retirementStatusData);

    // Check voucher conditions
    const isPrepaymentApproved =
        voucher.voucher_type === 'prepayment' && voucher.status === 'Approved';

    console.log('canRetireVoucher - isPrepaymentApproved:', isPrepaymentApproved);

    if (!isPrepaymentApproved) return false;

    // Check retirement status
    if (
        retirementStatusData.already_retired ||
        !retirementStatusData.can_retire
    ) {
        console.log('canRetireVoucher - failed retirement status check');
        return false;
    }

    // Development mode - bypass all checks
    if (isDevelopmentMode()) {
        console.log('DEV MODE: Allowing retirement');
        return true;
    }

    // Check user roles and permissions
    const hasRequiredRole = hasRole(['Final Account', 'admin']);
    const hasRequiredPermission = hasPermission('retire_vouchers');
    
    console.log('canRetireVoucher - hasRequiredRole:', hasRequiredRole);
    console.log('canRetireVoucher - hasRequiredPermission:', hasRequiredPermission);
    
    return hasRequiredRole || hasRequiredPermission;
};