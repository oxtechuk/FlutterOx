(function (window) {
    'use strict';

    if (typeof window.DokenOxRegisterModule !== 'function') {
        return;
    }

    window.DokenOxRegisterModule('products', function (root, helpers) {
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

        const Products = () => {
            const [products, setProducts] = useState([]);
            const [loading, setLoading] = useState(true);
            const [page, setPage] = useState(1);
            const [search, setSearch] = useState('');
            const [statusFilter, setStatusFilter] = useState('');
            const debouncedSearch = useDebounce(search, 500);
            const [viewProduct, setViewProduct] = useState(null);
            const [refresh, setRefresh] = useState(0);

            useEffect(() => {
                setLoading(true);
                let url = `/doken-ox/v1/admin/products?limit=20&page=${page}`;
                if (debouncedSearch) {
                    url += `&search=${encodeURIComponent(debouncedSearch)}`;
                }
                if (statusFilter) {
                    url += `&status=${statusFilter}`;
                }

                helpers.api.get(url)
                    .then((response) => {
                        setProducts(response.data || []);
                        setLoading(false);
                    })
                    .catch(() => {
                        setProducts([]);
                        setLoading(false);
                    });
            }, [page, debouncedSearch, statusFilter, refresh]);

            const handleAction = (id, action, payload = {}) => {
                if (!confirm(__('Are you sure?', 'doken-ox-pro'))) return;
                
                setLoading(true);
                helpers.api.apiFetch({
                    path: `/doken-ox/v1/admin/actions/product/${id}/${action}`,
                    method: 'POST',
                    data: payload
                }).then(() => {
                    setRefresh(prev => prev + 1);
                    if (viewProduct && viewProduct.id === id) setViewProduct(null);
                }).catch(err => {
                    alert(err.message || 'Error');
                    setLoading(false);
                });
            };

            const renderModal = () => {
                if (!viewProduct) return null;
                return h('div', { className: 'dox-modal-overlay', onClick: () => setViewProduct(null) },
                    h('div', { className: 'dox-modal-content', onClick: e => e.stopPropagation() }, [
                        h('div', { className: 'dox-modal-header' }, [
                            h('h3', null, __('Product Details', 'doken-ox-pro')),
                            h('button', { className: 'dox-close-btn', onClick: () => setViewProduct(null) }, '×')
                        ]),
                        h('div', { className: 'dox-modal-body' }, [
                            viewProduct.image && h('div', { style: { textAlign: 'center', marginBottom: '15px' } }, 
                                h('img', { src: viewProduct.image, style: { maxHeight: '150px', maxWidth: '100%' } })
                            ),
                            h('p', null, [h('strong', null, __('Name:', 'doken-ox-pro')), ` ${viewProduct.name}`]),
                            h('p', null, [h('strong', null, __('SKU:', 'doken-ox-pro')), ` ${viewProduct.sku || '-'}`]),
                            h('p', null, [h('strong', null, __('Price:', 'doken-ox-pro')), h('span', { dangerouslySetInnerHTML: { __html: viewProduct.price } })]),
                            h('p', null, [h('strong', null, __('Stock:', 'doken-ox-pro')), ` ${viewProduct.stock !== null ? viewProduct.stock : 'N/A'}`]),
                            h('p', null, [h('strong', null, __('Status:', 'doken-ox-pro')), ` ${viewProduct.status}`]),
                        ]),
                        h('div', { className: 'dox-modal-footer' }, [
                            h('button', { 
                                className: 'dox-btn dox-btn-secondary',
                                onClick: () => setViewProduct(null)
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
                        placeholder: __('Search products...', 'doken-ox-pro'),
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
                        h('option', { value: 'publish' }, __('Published', 'doken-ox-pro')),
                        h('option', { value: 'draft' }, __('Draft', 'doken-ox-pro')),
                        h('option', { value: 'pending' }, __('Pending', 'doken-ox-pro')),
                        h('option', { value: 'private' }, __('Private', 'doken-ox-pro')),
                    ])
                ]),

                // Content
                loading ? h('div', { className: 'dox-loading' }, __('Loading...', 'doken-ox-pro')) :
                products.length === 0 ? h('p', null, __('No products found.', 'doken-ox-pro')) :
                h('div', { className: 'dox-table-wrapper' }, 
                    h('table', { className: 'dox-table' }, [
                        h('thead', null, h('tr', null, [
                            h('th', { style: { width: '60px' } }, __('Image', 'doken-ox-pro')),
                            h('th', null, __('Name', 'doken-ox-pro')),
                            h('th', null, __('Price', 'doken-ox-pro')),
                            h('th', null, __('Stock', 'doken-ox-pro')),
                            h('th', null, __('Status', 'doken-ox-pro')),
                            h('th', null, __('Actions', 'doken-ox-pro')),
                        ])),
                        h('tbody', null, products.map(product => 
                            h('tr', { key: product.id }, [
                                h('td', null, product.image ? h('img', { src: product.image, style: { width: '40px', height: '40px', objectFit: 'cover', borderRadius: '4px' } }) : '—'),
                                h('td', null, [
                                    h('div', { style: { fontWeight: 'bold' } }, product.name),
                                    h('div', { style: { fontSize: '0.8em', color: '#666' } }, product.sku)
                                ]),
                                h('td', null, h('span', { dangerouslySetInnerHTML: { __html: product.price } })),
                                h('td', null, product.stock !== null ? product.stock : '—'),
                                h('td', null, h('span', { className: `dox-badge status-${product.status}` }, product.status)),
                                h('td', { className: 'dox-actions' }, [
                                    h('button', {
                                        className: 'dox-btn dox-btn-small dox-btn-view',
                                        title: __('View', 'doken-ox-pro'),
                                        onClick: () => setViewProduct(product)
                                    }, h('span', { className: 'dashicons dashicons-visibility' })),
                                    h('select', {
                                        className: 'dox-select-small',
                                        value: '',
                                        onChange: (e) => handleAction(product.id, 'update_status', { status: e.target.value })
                                    }, [
                                        h('option', { value: '', disabled: true }, __('Change Status', 'doken-ox-pro')),
                                        h('option', { value: 'publish' }, __('Publish', 'doken-ox-pro')),
                                        h('option', { value: 'draft' }, __('Draft', 'doken-ox-pro')),
                                        h('option', { value: 'pending' }, __('Pending', 'doken-ox-pro')),
                                    ]),
                                    h('button', {
                                        className: 'dox-btn dox-btn-small dox-btn-delete',
                                        title: __('Delete', 'doken-ox-pro'),
                                        onClick: () => handleAction(product.id, 'delete')
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

        render(h(Products), root);
    });
})(window);
