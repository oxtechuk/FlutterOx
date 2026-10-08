(function (window) {
    'use strict';

    if (typeof window.DokenOxRegisterModule !== 'function') {
        return;
    }

    window.DokenOxRegisterModule('stores', function (root, helpers) {
        const { element, render } = helpers;
        const { createElement: h, useEffect, useState } = element;
        const { __ } = helpers.i18n;

        // Debounce Hook
        function useDebounce(value, delay) {
            const [debouncedValue, setDebouncedValue] = useState(value);
            useEffect(() => {
                const handler = setTimeout(() => {
                    setDebouncedValue(value);
                }, delay);
                return () => clearTimeout(handler);
            }, [value, delay]);
            return debouncedValue;
        }

        const Stores = () => {
            const [vendors, setVendors] = useState([]);
            const [loading, setLoading] = useState(true);
            const [page, setPage] = useState(1);
            const [search, setSearch] = useState('');
            const [statusFilter, setStatusFilter] = useState('');
            const debouncedSearch = useDebounce(search, 500);
            const [viewVendor, setViewVendor] = useState(null);
            const [refresh, setRefresh] = useState(0);

            useEffect(() => {
                setLoading(true);
                let url = `/doken-ox/v1/admin/vendors?limit=20&page=${page}`;
                if (debouncedSearch) url += `&search=${encodeURIComponent(debouncedSearch)}`;
                if (statusFilter) url += `&status=${statusFilter}`;

                helpers.api
                    .get(url)
                    .then((response) => {
                        setVendors(response.data || []);
                        setLoading(false);
                    })
                    .catch(() => {
                        setVendors([]);
                        setLoading(false);
                    });
            }, [page, debouncedSearch, statusFilter, refresh]);

            const handleAction = (id, action, payload = {}) => {
                if (!confirm(__('Are you sure?', 'doken-ox-pro'))) return;

                setLoading(true);
                helpers.api.apiFetch({
                    path: `/doken-ox/v1/admin/actions/user/${id}/${action}`,
                    method: 'POST',
                    data: payload
                }).then(() => {
                    setRefresh(prev => prev + 1);
                }).catch(err => {
                    alert(err.message || 'Error');
                    setLoading(false);
                });
            };

            const Modal = ({ vendor, onClose }) => {
                if (!vendor) return null;
                return h('div', { className: 'dox-modal-overlay', onClick: onClose }, [
                    h('div', { className: 'dox-modal-content', onClick: e => e.stopPropagation() }, [
                        h('div', { className: 'dox-modal-header' }, [
                            h('h3', null, __('Vendor Details', 'doken-ox-pro')),
                            h('button', { className: 'dox-modal-close', onClick: onClose }, '×')
                        ]),
                        h('div', { className: 'dox-modal-body' }, [
                            h('p', null, [h('strong', null, 'Store Name: '), vendor.store?.store_name || 'N/A']),
                            h('p', null, [h('strong', null, 'Owner Name: '), vendor.name]),
                            h('p', null, [h('strong', null, 'Email: '), vendor.email]),
                            h('p', null, [h('strong', null, 'Registered: '), vendor.registered]),
                            h('p', null, [h('strong', null, 'Status: '), vendor.status]),
                            h('p', null, [h('strong', null, 'Phone: '), vendor.store?.phone || 'N/A']),
                            h('p', null, [h('strong', null, 'Address: '), vendor.store?.address?.street_1 || 'N/A']),
                        ])
                    ])
                ]);
            };

            return h('div', { className: 'dox-dashboard' }, [
                h('div', { className: 'dox-header' }, 
                    h('h2', null, __('All Vendors', 'doken-ox-pro'))
                ),

                // Toolbar
                h('div', { className: 'dox-toolbar' }, [
                    h('input', {
                        type: 'text',
                        className: 'dox-input',
                        placeholder: __('Search vendors...', 'doken-ox-pro'),
                        value: search,
                        onChange: (e) => {
                            setSearch(e.target.value);
                            setPage(1);
                        }
                    }),
                    h('select', {
                        className: 'dox-select',
                        value: statusFilter,
                        onChange: (e) => {
                            setStatusFilter(e.target.value);
                            setPage(1);
                        }
                    }, [
                        h('option', { value: '' }, __('All Status', 'doken-ox-pro')),
                        h('option', { value: 'active' }, __('Active', 'doken-ox-pro')),
                        h('option', { value: 'suspended' }, __('Suspended', 'doken-ox-pro')),
                    ])
                ]),

                loading && !vendors.length ? h('div', { className: 'dox-card' }, __('Loading vendors...', 'doken-ox-pro')) :
                h('div', { className: 'dox-card', style: { padding: 0, overflow: 'hidden' } }, [
                    h('table', { className: 'dox-table' }, [
                        h('thead', null, h('tr', null, [
                            h('th', null, 'ID'),
                            h('th', null, 'Store Name'),
                            h('th', null, 'Owner'),
                            h('th', null, 'Status'),
                            h('th', null, 'Email'),
                            h('th', null, 'Actions'),
                        ])),
                        h('tbody', null, vendors.map(vendor => 
                            h('tr', { key: vendor.id }, [
                                h('td', null, vendor.id),
                                h('td', null, vendor.store?.store_name || 'N/A'),
                                h('td', null, vendor.name),
                                h('td', null, 
                                    h('span', { 
                                        className: `dox-badge status-${vendor.status === 'suspended' ? 'suspended' : 'active'}` 
                                    }, vendor.status === 'suspended' ? 'Suspended' : 'Active')
                                ),
                                h('td', null, vendor.email),
                                h('td', null, h('div', { className: 'dox-actions' }, [
                                    h('button', { 
                                        className: 'dox-btn dox-btn-sm',
                                        onClick: () => setViewVendor(vendor)
                                    }, 'View'),
                                    h('button', { 
                                        className: 'dox-btn dox-btn-sm',
                                        style: { backgroundColor: vendor.status === 'suspended' ? '#dcfce7' : '#fef9c3', color: vendor.status === 'suspended' ? '#166534' : '#854d0e' },
                                        onClick: () => handleAction(vendor.id, 'update_status', { status: vendor.status === 'suspended' ? 'active' : 'suspended' })
                                    }, vendor.status === 'suspended' ? 'Activate' : 'Suspend'),
                                    h('button', { 
                                        className: 'dox-btn dox-btn-sm dox-btn-danger',
                                        onClick: () => handleAction(vendor.id, 'delete')
                                    }, 'Delete'),
                                ]))
                            ])
                        ))
                    ]),
                    
                    // Pagination
                    h('div', { style: { padding: '16px', display: 'flex', justifyContent: 'space-between', borderTop: '1px solid #e2e8f0' } }, [
                        h('button', { 
                            className: 'dox-btn', 
                            disabled: page === 1,
                            onClick: () => setPage(p => Math.max(1, p - 1))
                        }, 'Previous'),
                        h('span', { style: { alignSelf: 'center' } }, `Page ${page}`),
                        h('button', { 
                            className: 'dox-btn', 
                            disabled: vendors.length < 20,
                            onClick: () => setPage(p => p + 1)
                        }, 'Next')
                    ])
                ]),

                viewVendor && h(Modal, { vendor: viewVendor, onClose: () => setViewVendor(null) })
            ]);
        };

        render(h(Stores), root);
    });
})(window);
