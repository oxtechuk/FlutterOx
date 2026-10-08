(function (window) {
    'use strict';

    if (typeof window.DokenOxRegisterModule !== 'function') {
        return;
    }

    window.DokenOxRegisterModule('users', function (root, helpers) {
        const { element, render } = helpers;
        const { createElement: h, useEffect, useState, useRef } = element;
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

        const Users = () => {
            const [users, setUsers] = useState([]);
            const [loading, setLoading] = useState(true);
            const [page, setPage] = useState(1);
            const [search, setSearch] = useState('');
            const [statusFilter, setStatusFilter] = useState('');
            const debouncedSearch = useDebounce(search, 500);
            const [viewUser, setViewUser] = useState(null);
            const [refresh, setRefresh] = useState(0);

            useEffect(() => {
                setLoading(true);
                let url = `/doken-ox/v1/admin/customers?limit=20&page=${page}`;
                if (debouncedSearch) {
                    url += `&search=${encodeURIComponent(debouncedSearch)}`;
                }
                if (statusFilter) {
                    url += `&status=${statusFilter}`;
                }

                helpers.api
                    .get(url)
                    .then((response) => {
                        setUsers(response.data || []);
                        setLoading(false);
                    })
                    .catch(() => {
                        setUsers([]);
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
                    // alert(__('Action completed successfully.', 'doken-ox-pro'));
                }).catch(err => {
                    alert(err.message || 'Error');
                    setLoading(false);
                });
            };

            const Modal = ({ user, onClose }) => {
                if (!user) return null;
                return h('div', { className: 'dox-modal-overlay', onClick: onClose }, [
                    h('div', { className: 'dox-modal-content', onClick: e => e.stopPropagation() }, [
                        h('div', { className: 'dox-modal-header' }, [
                            h('h3', null, __('Customer Details', 'doken-ox-pro')),
                            h('button', { className: 'dox-modal-close', onClick: onClose }, '×')
                        ]),
                        h('div', { className: 'dox-modal-body' }, [
                            h('p', null, [h('strong', null, 'ID: '), user.id]),
                            h('p', null, [h('strong', null, 'Name: '), user.name]),
                            h('p', null, [h('strong', null, 'Email: '), user.email]),
                            h('p', null, [h('strong', null, 'Registered: '), user.registered]),
                            h('p', null, [h('strong', null, 'Total Orders: '), user.orders]),
                            h('p', null, [h('strong', null, 'Status: '), user.status]),
                        ])
                    ])
                ]);
            };

            return h('div', { className: 'dox-dashboard' }, [
                h('div', { className: 'dox-header' }, 
                    h('h2', null, __('All Customers', 'doken-ox-pro'))
                ),

                // Toolbar
                h('div', { className: 'dox-toolbar' }, [
                    h('input', {
                        type: 'text',
                        className: 'dox-input',
                        placeholder: __('Search customers...', 'doken-ox-pro'),
                        value: search,
                        onChange: (e) => {
                            setSearch(e.target.value);
                            setPage(1); // Reset page on search
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

                loading && !users.length ? h('div', { className: 'dox-card' }, __('Loading customers...', 'doken-ox-pro')) :
                h('div', { className: 'dox-card', style: { padding: 0, overflow: 'hidden' } }, [
                    h('table', { className: 'dox-table' }, [
                        h('thead', null, h('tr', null, [
                            h('th', null, 'ID'),
                            h('th', null, 'Name'),
                            h('th', null, 'Email'),
                            h('th', null, 'Status'),
                            h('th', null, 'Orders'),
                            h('th', null, 'Actions'),
                        ])),
                        h('tbody', null, users.map(user => 
                            h('tr', { key: user.id }, [
                                h('td', null, user.id),
                                h('td', null, user.name),
                                h('td', null, user.email),
                                h('td', null, 
                                    h('span', { 
                                        className: `dox-badge status-${user.status === 'suspended' ? 'suspended' : 'active'}` 
                                    }, user.status === 'suspended' ? 'Suspended' : 'Active')
                                ),
                                h('td', null, h('span', { className: 'dox-badge' }, user.orders)),
                                h('td', null, h('div', { className: 'dox-actions' }, [
                                    h('button', { 
                                        className: 'dox-btn dox-btn-sm',
                                        onClick: () => setViewUser(user)
                                    }, 'View'),
                                    h('button', { 
                                        className: 'dox-btn dox-btn-sm',
                                        style: { backgroundColor: user.status === 'suspended' ? '#dcfce7' : '#fef9c3', color: user.status === 'suspended' ? '#166534' : '#854d0e' },
                                        onClick: () => handleAction(user.id, 'update_status', { status: user.status === 'suspended' ? 'active' : 'suspended' })
                                    }, user.status === 'suspended' ? 'Activate' : 'Suspend'),
                                    h('button', { 
                                        className: 'dox-btn dox-btn-sm dox-btn-danger',
                                        onClick: () => handleAction(user.id, 'delete')
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
                            disabled: users.length < 20,
                            onClick: () => setPage(p => p + 1)
                        }, 'Next')
                    ])
                ]),

                viewUser && h(Modal, { user: viewUser, onClose: () => setViewUser(null) })
            ]);
        };

        render(h(Users), root);
    });
})(window);
