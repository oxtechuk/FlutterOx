(function (window) {
    'use strict';

    if (typeof window.DokenOxRegisterModule !== 'function') {
        return;
    }

    window.DokenOxRegisterModule('orders', function (root, helpers) {
        const { element, render } = helpers;
        const { createElement: h, useEffect, useState } = element;
        const { __ } = helpers.i18n;

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

        const Orders = () => {
            const [orders, setOrders] = useState([]);
            const [loading, setLoading] = useState(true);
            const [page, setPage] = useState(1);
            const [search, setSearch] = useState('');
            const [statusFilter, setStatusFilter] = useState('');
            const debouncedSearch = useDebounce(search, 500);
            const [viewOrder, setViewOrder] = useState(null);
            const [refresh, setRefresh] = useState(0);

            useEffect(() => {
                setLoading(true);
                let url = `/doken-ox/v1/admin/orders?limit=20&page=${page}`;
                if (debouncedSearch) {
                    url += `&search=${encodeURIComponent(debouncedSearch)}`;
                }
                if (statusFilter) {
                    url += `&status=${statusFilter}`;
                }

                helpers.api.get(url)
                    .then((response) => {
                        setOrders(response.data || []);
                        setLoading(false);
                    })
                    .catch(() => {
                        setOrders([]);
                        setLoading(false);
                    });
            }, [page, debouncedSearch, statusFilter, refresh]);

            const handleAction = (id, action, payload = {}) => {
                if (!confirm(__('Are you sure?', 'doken-ox-pro'))) return;
                
                setLoading(true);
                helpers.api.apiFetch({
                    path: `/doken-ox/v1/admin/actions/order/${id}/${action}`,
                    method: 'POST',
                    data: payload
                }).then(() => {
                    setRefresh(prev => prev + 1);
                    if (viewOrder && viewOrder.id === id) setViewOrder(null); // Close modal if open
                }).catch(err => {
                    alert(err.message || 'Error');
                    setLoading(false);
                });
            };

            const renderModal = () => {
                if (!viewOrder) return null;
                return h('div', { className: 'dox-modal-overlay', onClick: () => setViewOrder(null) },
                    h('div', { className: 'dox-modal-content', onClick: e => e.stopPropagation() }, [
                        h('div', { className: 'dox-modal-header' }, [
                            h('h3', null, __('Order Details', 'doken-ox-pro')),
                            h('button', { className: 'dox-close-btn', onClick: () => setViewOrder(null) }, '×')
                        ]),
                        h('div', { className: 'dox-modal-body' }, [
                            h('p', null, [h('strong', null, __('Order ID:', 'doken-ox-pro')), ` #${viewOrder.order_number}`]),
                            h('p', null, [h('strong', null, __('Customer:', 'doken-ox-pro')), ` ${viewOrder.customer}`]),
                            h('p', null, [h('strong', null, __('Total:', 'doken-ox-pro')), h('span', { dangerouslySetInnerHTML: { __html: viewOrder.total } })]), // total is HTML formatted
                            h('p', null, [h('strong', null, __('Status:', 'doken-ox-pro')), ` ${viewOrder.status}`]),
                            h('p', null, [h('strong', null, __('Date:', 'doken-ox-pro')), ` ${viewOrder.date}`]),
                            h('p', null, [h('strong', null, __('Items:', 'doken-ox-pro')), ` ${viewOrder.item_count}`]),
                        ]),
                        h('div', { className: 'dox-modal-footer' }, [
                            h('button', { 
                                className: 'dox-btn dox-btn-secondary',
                                onClick: () => setViewOrder(null)
                            }, __('Close', 'doken-ox-pro'))
                        ])
                    ])
                );
            };

            return h('div', { className: 'dox-container' }, [
                // Toolbar
                h('div', { className: 'dox-toolbar' }, [
                    h('input', {
                        type: 'text',
                        className: 'dox-input',
                        placeholder: __('Search orders...', 'doken-ox-pro'),
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
                        h('option', { value: 'completed' }, __('Completed', 'doken-ox-pro')),
                        h('option', { value: 'processing' }, __('Processing', 'doken-ox-pro')),
                        h('option', { value: 'pending' }, __('Pending', 'doken-ox-pro')),
                        h('option', { value: 'on-hold' }, __('On Hold', 'doken-ox-pro')),
                        h('option', { value: 'cancelled' }, __('Cancelled', 'doken-ox-pro')),
                        h('option', { value: 'refunded' }, __('Refunded', 'doken-ox-pro')),
                    ])
                ]),

                // Content
                loading ? h('div', { className: 'dox-loading' }, __('Loading...', 'doken-ox-pro')) :
                orders.length === 0 ? h('p', null, __('No orders found.', 'doken-ox-pro')) :
                h('div', { className: 'dox-table-wrapper' }, 
                    h('table', { className: 'dox-table' }, [
                        h('thead', null, h('tr', null, [
                            h('th', null, __('Order', 'doken-ox-pro')),
                            h('th', null, __('Customer', 'doken-ox-pro')),
                            h('th', null, __('Status', 'doken-ox-pro')),
                            h('th', null, __('Total', 'doken-ox-pro')),
                            h('th', null, __('Date', 'doken-ox-pro')),
                            h('th', null, __('Actions', 'doken-ox-pro')),
                        ])),
                        h('tbody', null, orders.map(order => 
                            h('tr', { key: order.id }, [
                                h('td', null, `#${order.order_number}`),
                                h('td', null, order.customer),
                                h('td', null, h('span', { className: `dox-badge status-${order.status}` }, order.status)),
                                h('td', null, h('span', { dangerouslySetInnerHTML: { __html: order.total } })),
                                h('td', null, order.date),
                                h('td', { className: 'dox-actions' }, [
                                    h('button', {
                                        className: 'dox-btn dox-btn-small dox-btn-view',
                                        title: __('View', 'doken-ox-pro'),
                                        onClick: () => setViewOrder(order)
                                    }, h('span', { className: 'dashicons dashicons-visibility' })),
                                    h('select', {
                                        className: 'dox-select-small',
                                        value: '',
                                        onChange: (e) => handleAction(order.id, 'update_status', { status: e.target.value })
                                    }, [
                                        h('option', { value: '', disabled: true }, __('Change Status', 'doken-ox-pro')),
                                        h('option', { value: 'completed' }, __('Complete', 'doken-ox-pro')),
                                        h('option', { value: 'processing' }, __('Process', 'doken-ox-pro')),
                                        h('option', { value: 'cancelled' }, __('Cancel', 'doken-ox-pro')),
                                    ]),
                                    h('button', {
                                        className: 'dox-btn dox-btn-small dox-btn-delete',
                                        title: __('Delete', 'doken-ox-pro'),
                                        onClick: () => handleAction(order.id, 'delete')
                                    }, h('span', { className: 'dashicons dashicons-trash' }))
                                ])
                            ])
                        ))
                    ])
                ),

                // Pagination (Simple)
                h('div', { className: 'dox-pagination' }, [
                    h('button', { 
                        disabled: page === 1, 
                        onClick: () => setPage(p => p - 1),
                        className: 'dox-btn dox-btn-secondary' 
                    }, __('Previous', 'doken-ox-pro')),
                    h('span', { className: 'dox-page-info' }, `${__('Page', 'doken-ox-pro')} ${page}`),
                    h('button', { 
                        onClick: () => setPage(p => p + 1),
                        className: 'dox-btn dox-btn-secondary'
                    }, __('Next', 'doken-ox-pro')),
                ]),

                renderModal()
            ]);
        };

        render(h(Orders), root);
    });
})(window);
