(function (window) {
    'use strict';

    if (typeof window.DokenOxRegisterModule !== 'function') {
        return;
    }

    window.DokenOxRegisterModule('dashboard', function (root, helpers) {
        const { element, render, charts } = helpers;
        const { createElement: h, Fragment, useEffect, useState, useCallback } = element;
        const { __ } = helpers.i18n;

        const fetchStats = () => helpers.api.get('/doken-ox/v1/admin/system/stats').then((res) => res.data);
        const fetchBanners = () => helpers.api.get('/doken-ox/v1/admin/banners').then((res) => res.data);
        const fetchCustomers = () => helpers.api.get('/doken-ox/v1/admin/customers?limit=5').then((res) => res.data);
        const fetchVendors = () => helpers.api.get('/doken-ox/v1/admin/vendors?limit=5').then((res) => res.data);

        const Dashboard = () => {
            const [data, setData] = useState({
                stats: null,
                banners: [],
                customers: [],
                vendors: [],
                loading: true
            });
            const [newBanner, setNewBanner] = useState('');

            useEffect(() => {
                Promise.all([fetchStats(), fetchBanners(), fetchCustomers(), fetchVendors()])
                    .then(([stats, banners, customers, vendors]) => {
                        setData({
                            stats,
                            banners: Array.isArray(banners) ? banners : [],
                            customers,
                            vendors,
                            loading: false
                        });
                    })
                    .catch((err) => {
                        console.error(err);
                        setData(d => ({ ...d, loading: false }));
                    });
            }, []);

            useEffect(() => {
                if (!data.stats || !charts) return;
                const canvas = root.querySelector('#dox-sales-chart');
                if (canvas) {
                    charts.renderLineChart(
                        canvas,
                        [__('Week 1', 'doken-ox-pro'), __('Week 2', 'doken-ox-pro'), __('Week 3', 'doken-ox-pro'), __('Week 4', 'doken-ox-pro')],
                        [12, 19, 7, 15] // Placeholder data - in real app fetch from analytics
                    );
                }
            }, [data.stats]);

            const handleAddBanner = () => {
                if (!newBanner) return;
                const updatedBanners = [...data.banners, { url: newBanner }];
                helpers.api.post('/doken-ox/v1/admin/banners', updatedBanners).then(() => {
                    setData(d => ({ ...d, banners: updatedBanners }));
                    setNewBanner('');
                });
            };

            const handleRemoveBanner = (index) => {
                const updatedBanners = data.banners.filter((_, i) => i !== index);
                helpers.api.post('/doken-ox/v1/admin/banners', updatedBanners).then(() => {
                    setData(d => ({ ...d, banners: updatedBanners }));
                });
            };

            if (data.loading) {
                return h('div', { className: 'dox-loading' }, __('Loading Dashboard...', 'doken-ox-pro'));
            }

            const { stats, banners, customers, vendors } = data;

            // Stats Cards
            const statCards = [
                { label: __('Total Revenue', 'doken-ox-pro'), value: stats?.revenue_month, icon: 'dashicons-money-alt', color: '#4caf50' },
                { label: __('Orders', 'doken-ox-pro'), value: stats?.orders_count, icon: 'dashicons-cart', color: '#2196f3' },
                { label: __('Customers', 'doken-ox-pro'), value: stats?.users_count, icon: 'dashicons-admin-users', color: '#ff9800' },
                { label: __('Vendors', 'doken-ox-pro'), value: stats?.vendors_count, icon: 'dashicons-store', color: '#9c27b0' },
            ];

            return h(
                Fragment,
                null,
                // Stats Section
                h('div', { className: 'dox-section' },
                    h('h2', null, __('Overview', 'doken-ox-pro')),
                    h('div', { className: 'dox-grid' },
                        statCards.map((card) =>
                            h('div', { key: card.label, className: 'dox-card dox-stat-card', style: { borderTopColor: card.color } },
                                h('div', { className: 'dox-stat-icon', style: { color: card.color } }, h('span', { className: `dashicons ${card.icon}` })),
                                h('div', { className: 'dox-stat-content' },
                                    h('h3', null, card.label),
                                    h('strong', null, card.value)
                                )
                            )
                        )
                    )
                ),

                // Chart Section
                h('div', { className: 'dox-section' },
                    h('div', { className: 'dox-card' },
                        h('h3', null, __('Sales Analytics', 'doken-ox-pro')),
                        h('div', { className: 'dox-chart-container' },
                            h('canvas', { id: 'dox-sales-chart', height: 100 })
                        )
                    )
                ),

                // Banners Section
                h('div', { className: 'dox-section' },
                    h('div', { className: 'dox-card' },
                        h('div', { className: 'dox-card-header' },
                            h('h3', null, __('App Banners', 'doken-ox-pro')),
                            h('div', { className: 'dox-input-group' },
                                h('input', {
                                    type: 'text',
                                    placeholder: __('Enter image URL...', 'doken-ox-pro'),
                                    value: newBanner,
                                    onChange: (e) => setNewBanner(e.target.value)
                                }),
                                h('button', { className: 'button button-primary', onClick: handleAddBanner }, __('Add Banner', 'doken-ox-pro'))
                            )
                        ),
                        h('div', { className: 'dox-banners-grid' },
                            banners.map((banner, index) =>
                                h('div', { key: index, className: 'dox-banner-item' },
                                    h('img', { src: banner.url, alt: 'Banner' }),
                                    h('button', { className: 'dox-remove-btn', onClick: () => handleRemoveBanner(index) }, '×')
                                )
                            )
                        )
                    )
                ),

                // Users & Vendors Tables
                h('div', { className: 'dox-grid dox-tables-grid' },
                    // Vendors
                    h('div', { className: 'dox-card' },
                        h('h3', null, __('Recent Vendors', 'doken-ox-pro')),
                        h('table', { className: 'dox-table' },
                            h('thead', null,
                                h('tr', null,
                                    h('th', null, 'ID'),
                                    h('th', null, 'Name'),
                                    h('th', null, 'Store'),
                                    h('th', null, 'Email')
                                )
                            ),
                            h('tbody', null,
                                vendors.slice(0, 5).map(vendor =>
                                    h('tr', { key: vendor.id },
                                        h('td', null, `#${vendor.id}`),
                                        h('td', null, vendor.name),
                                        h('td', null, vendor.store?.store_name || '-'),
                                        h('td', null, vendor.email)
                                    )
                                )
                            )
                        ),
                        h('a', { href: '#/stores', className: 'dox-link' }, __('View All Vendors', 'doken-ox-pro'))
                    ),
                    // Customers
                    h('div', { className: 'dox-card' },
                        h('h3', null, __('Recent Customers', 'doken-ox-pro')),
                        h('table', { className: 'dox-table' },
                            h('thead', null,
                                h('tr', null,
                                    h('th', null, 'ID'),
                                    h('th', null, 'Name'),
                                    h('th', null, 'Orders'),
                                    h('th', null, 'Email')
                                )
                            ),
                            h('tbody', null,
                                customers.slice(0, 5).map(customer =>
                                    h('tr', { key: customer.id },
                                        h('td', null, `#${customer.id}`),
                                        h('td', null, customer.name),
                                        h('td', null, customer.orders),
                                        h('td', null, customer.email)
                                    )
                                )
                            )
                        ),
                        h('a', { href: '#/users', className: 'dox-link' }, __('View All Customers', 'doken-ox-pro'))
                    )
                )
            );
        };

        render(h(Dashboard), root);
    });
})(window);
